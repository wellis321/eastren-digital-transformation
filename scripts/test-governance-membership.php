#!/usr/bin/env php
<?php
/**
 * Heavy regression suite for governance membership.
 * Snapshots live membership, runs mutating tests, restores in finally.
 *
 * Usage: php scripts/test-governance-membership.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/db.php';
require_once $root . '/includes/governance_membership.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pass = 0;
$fail = 0;
$skip = 0;
$failures = [];

function as_admin(): void
{
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = 'test-admin';
    $_SESSION['user_id'] = 900001;
    $_SESSION['app_role'] = 'admin';
}

function as_editor(int $uid = 900002): void
{
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = 'test-editor';
    $_SESSION['user_id'] = $uid;
    $_SESSION['app_role'] = 'editor';
}

function as_guest(): void
{
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_user'], $_SESSION['user_id'], $_SESSION['app_role']);
}

function assert_true(string $name, bool $cond, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($cond) {
        $pass++;
        echo "  PASS  $name\n";
        return;
    }
    $fail++;
    $msg = $detail !== '' ? "$name — $detail" : $name;
    $failures[] = $msg;
    echo "  FAIL  $msg\n";
}

function assert_throws(string $name, callable $fn, ?string $needle = null): void
{
    try {
        $fn();
        assert_true($name, false, 'expected exception, none thrown');
    } catch (Throwable $e) {
        if ($needle !== null && stripos($e->getMessage(), $needle) === false) {
            assert_true($name, false, 'wrong exception: ' . $e->getMessage());
            return;
        }
        assert_true($name, true);
    }
}

function membership_counts(): array
{
    $out = ['total' => 0, 'people' => 0, 'byGroup' => []];
    $pdo = db();
    $out['total'] = (int)$pdo->query('SELECT COUNT(*) FROM governance_membership')->fetchColumn();
    $out['people'] = (int)$pdo->query('SELECT COUNT(DISTINCT person_id) FROM governance_membership')->fetchColumn();
    foreach ($pdo->query('SELECT group_id, COUNT(*) c FROM governance_membership GROUP BY group_id') as $row) {
        $out['byGroup'][(string)$row['group_id']] = (int)$row['c'];
    }
    ksort($out['byGroup']);
    return $out;
}

function snapshot_membership(): array
{
    $rows = db()->query(
        'SELECT person_id, group_id, vote, sort_order, needs_assessment, updated_by
           FROM governance_membership
          ORDER BY person_id, group_id'
    )->fetchAll();
    return $rows;
}

function restore_membership(array $rows): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM governance_membership');
        $ins = $pdo->prepare(
            'INSERT INTO governance_membership
              (person_id, group_id, vote, sort_order, needs_assessment, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        foreach ($rows as $r) {
            $ins->execute([
                $r['person_id'],
                $r['group_id'],
                $r['vote'] ?? 'unset',
                (int)($r['sort_order'] ?? 0),
                (int)($r['needs_assessment'] ?? 0),
                (string)($r['updated_by'] ?? 'test-restore'),
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function person_on(string $personId, string $groupId): bool
{
    $st = db()->prepare('SELECT 1 FROM governance_membership WHERE person_id = ? AND group_id = ?');
    $st->execute([$personId, $groupId]);
    return (bool)$st->fetchColumn();
}

function cleanup_test_artifacts(array $attrIds, array $personIds, array $proposalIds): void
{
    $pdo = db();
    foreach ($proposalIds as $id) {
        $pdo->prepare('DELETE FROM governance_proposals WHERE id = ?')->execute([(int)$id]);
    }
    foreach ($personIds as $pid) {
        $pdo->prepare('DELETE FROM governance_votes WHERE person_id = ?')->execute([$pid]);
        $pdo->prepare('DELETE FROM governance_membership WHERE person_id = ?')->execute([$pid]);
        $pdo->prepare('DELETE FROM governance_proposals WHERE person_id = ?')->execute([$pid]);
        $pdo->prepare('DELETE FROM governance_people WHERE person_id = ?')->execute([$pid]);
    }
    foreach ($attrIds as $aid) {
        // strip from any tags_json then delete attr
        foreach ($pdo->query('SELECT person_id, tags_json FROM governance_people WHERE tags_json IS NOT NULL') as $row) {
            $decoded = json_decode((string)$row['tags_json'], true);
            if (!is_array($decoded)) {
                continue;
            }
            $next = array_values(array_filter($decoded, static fn ($t) => strtolower((string)$t) !== $aid));
            if ($next !== $decoded) {
                $pdo->prepare('UPDATE governance_people SET tags_json = ? WHERE person_id = ?')
                    ->execute([json_encode($next, JSON_UNESCAPED_UNICODE), $row['person_id']]);
            }
        }
        $pdo->prepare('DELETE FROM governance_attributes WHERE attr_id = ? AND is_seed = 0')->execute([$aid]);
    }
    // votes from test users
    $pdo->prepare('DELETE FROM governance_votes WHERE user_id IN (900001, 900002, 900003)')->execute();
}

echo "=== Governance membership regression ===\n";
governance_ensure_table();
as_admin();

$baselineCounts = membership_counts();
$snapshot = snapshot_membership();
echo "Baseline membership rows: {$baselineCounts['total']} across {$baselineCounts['people']} people\n";
echo "Groups: " . json_encode($baselineCounts['byGroup']) . "\n\n";

$createdAttrIds = [];
$createdPersonIds = [];
$createdProposalIds = [];
$createdGroupIds = [];

try {
    // ------------------------------------------------------------------
    // A. Seed / catalog integrity (read-only)
    // ------------------------------------------------------------------
    echo "A. Seed & catalog integrity\n";
    $seed = governance_seed_people();
    assert_true('seed people count ~80', count($seed) >= 75 && count($seed) <= 85, 'got ' . count($seed));

    $seedBoard = array_filter($seed, static fn ($p) => in_array('project-board', $p['groups'], true));
    $seedSteer = array_filter($seed, static fn ($p) => in_array('steering-group', $p['groups'], true));
    $seedTech = array_filter($seed, static fn ($p) => in_array('technical-group', $p['groups'], true));
    assert_true('seed Project Board = 8', count($seedBoard) === 8, 'got ' . count($seedBoard));
    assert_true('seed Steering = 17', count($seedSteer) === 17, 'got ' . count($seedSteer));
    assert_true('seed Technical = 18', count($seedTech) === 18, 'got ' . count($seedTech));

    $live = membership_counts();
    assert_true(
        'live Board >= 8',
        ($live['byGroup']['project-board'] ?? 0) >= 8,
        'got ' . ($live['byGroup']['project-board'] ?? 0)
    );
    assert_true(
        'live Steering = 17',
        ($live['byGroup']['steering-group'] ?? 0) === 17,
        'got ' . ($live['byGroup']['steering-group'] ?? 0)
    );
    assert_true(
        'live Technical >= 18',
        ($live['byGroup']['technical-group'] ?? 0) >= 18,
        'got ' . ($live['byGroup']['technical-group'] ?? 0)
    );
    assert_true('live total placements > 40', $live['total'] > 40, 'got ' . $live['total']);

    $bundle = governance_load_bundle();
    assert_true('bundle people loaded', count($bundle['people'] ?? []) >= 75);
    assert_true('bundle needsAssessment empty', ($bundle['needsAssessment'] ?? ['x']) === [] || count($bundle['needsAssessment']) === 0);
    assert_true('pending_count is 0', governance_pending_count() === 0);
    assert_true('banner html empty', governance_banner_html() === '');
    assert_true('attributeLabels present', is_array($bundle['attributeLabels'] ?? null) && count($bundle['attributeLabels']) >= 10);
    assert_true('attributes catalog present', is_array($bundle['attributes'] ?? null) && count($bundle['attributes']) >= 10);

    $needsLeft = (int)db()->query('SELECT COUNT(*) FROM governance_membership WHERE needs_assessment = 1')->fetchColumn();
    assert_true('no needs_assessment flags in DB', $needsLeft === 0, "still $needsLeft");

    // ------------------------------------------------------------------
    // B. Wipe protections (the previous production bug)
    // ------------------------------------------------------------------
    echo "\nB. Wipe protections\n";
    $beforeWipe = membership_counts()['total'];
    assert_throws(
        'save empty membership refused',
        static function () {
            governance_save_state(['membership' => [], 'order' => []], 'test-admin');
        },
        'Refusing to wipe'
    );
    assert_true('membership unchanged after empty save attempt', membership_counts()['total'] === $beforeWipe);

    // gut most placements
    $tiny = [];
    $i = 0;
    foreach ($snapshot as $r) {
        if ($i++ >= 3) {
            break;
        }
        $pid = $r['person_id'];
        $gid = $r['group_id'];
        if (!isset($tiny[$pid])) {
            $tiny[$pid] = [];
        }
        $tiny[$pid][] = $gid;
    }
    assert_throws(
        'save gutting membership refused',
        static function () use ($tiny) {
            governance_save_state(['membership' => $tiny, 'order' => []], 'test-admin');
        },
        'removes most'
    );
    assert_true('membership unchanged after gut attempt', membership_counts()['total'] === $beforeWipe);

    // ------------------------------------------------------------------
    // C. Place / move / remove as confirmer (no assessment flag)
    // ------------------------------------------------------------------
    echo "\nC. Confirmer place / move / remove\n";
    as_admin();
    // pick an unassigned seed person if possible
    $unassigned = null;
    $onAny = [];
    foreach (db()->query('SELECT DISTINCT person_id FROM governance_membership') as $row) {
        $onAny[$row['person_id']] = true;
    }
    foreach ($seed as $p) {
        if (!isset($onAny[$p['id']])) {
            $unassigned = $p['id'];
            break;
        }
    }
    if ($unassigned === null) {
        // fallback: use a board person for move tests only
        echo "  SKIP  no unassigned seed person for add test\n";
        global $skip;
        $skip++;
        $probe = 'suzanne-conlin';
    } else {
        $probe = $unassigned;
        governance_place_person($probe, 'business-group', 'add', 'test-admin', null);
        assert_true('place add puts person on business', person_on($probe, 'business-group'));
        $flag = db()->prepare('SELECT needs_assessment FROM governance_membership WHERE person_id = ? AND group_id = ?');
        $flag->execute([$probe, 'business-group']);
        assert_true('place does not set needs_assessment', (int)$flag->fetchColumn() === 0);

        governance_place_person($probe, 'technical-group', 'move', 'test-admin', 'business-group');
        assert_true('move leaves from-group', !person_on($probe, 'business-group'));
        assert_true('move lands on to-group', person_on($probe, 'technical-group'));

        governance_remove_person_from_group($probe, 'technical-group', 'test-admin');
        assert_true('remove clears placement', !person_on($probe, 'technical-group'));
    }

    // ------------------------------------------------------------------
    // D. Suggest → confirm (editor proposes, admin accepts)
    // ------------------------------------------------------------------
    echo "\nD. Suggest → confirm\n";
    // Find person on steering to suggest remove, and unassigned/other for add
    $steerPerson = null;
    foreach (db()->query("SELECT person_id FROM governance_membership WHERE group_id = 'steering-group' LIMIT 5") as $row) {
        // skip william/lisa if we can — any is fine for test, restore later
        $steerPerson = (string)$row['person_id'];
        break;
    }
    assert_true('have steering person for propose', $steerPerson !== null);

    as_editor(900002);
    assert_true('editor cannot shape', !governance_can_shape());
    assert_true('editor can propose', governance_can_propose());

    $prop = governance_propose($steerPerson, 'business-group', 'add', 'test-editor', null);
    $createdProposalIds[] = $prop['id'];
    assert_true('propose add returns id', ($prop['id'] ?? 0) > 0);
    assert_true('propose does not place yet', !person_on($steerPerson, 'business-group'));

    $pending = governance_list_pending_proposals();
    $found = false;
    foreach ($pending as $p) {
        if ((int)$p['id'] === (int)$prop['id']) {
            $found = true;
            break;
        }
    }
    assert_true('proposal appears in pending list', $found);

    as_admin();
    governance_decide_proposal((int)$prop['id'], 'accept', 'test-admin');
    assert_true('accept places person', person_on($steerPerson, 'business-group'));
    // undo placement from accept so restore is cleaner (snapshot restore will fix anyway)
    if (person_on($steerPerson, 'business-group') && !in_array('business-group', array_column(array_filter($snapshot, fn ($r) => $r['person_id'] === $steerPerson), 'group_id'), true)) {
        // was not on business in snapshot — remove after test path
        governance_remove_person_from_group($steerPerson, 'business-group', 'test-admin');
        assert_true('cleaned accepted placement', !person_on($steerPerson, 'business-group'));
    }

    // reject path
    as_editor(900002);
    $prop2 = governance_propose($steerPerson, 'business-group', 'add', 'test-editor', null);
    $createdProposalIds[] = $prop2['id'];
    as_admin();
    governance_decide_proposal((int)$prop2['id'], 'reject', 'test-admin');
    assert_true('reject does not place', !person_on($steerPerson, 'business-group'));

    // editor cannot place directly
    as_editor(900002);
    assert_throws(
        'editor place_person forbidden',
        static function () use ($steerPerson) {
            governance_place_person($steerPerson, 'business-group', 'add', 'test-editor', null);
        },
        'Forbidden'
    );

    // ------------------------------------------------------------------
    // E. Add person + tags / attributes CRUD
    // ------------------------------------------------------------------
    echo "\nE. People & attributes CRUD\n";
    as_admin();
    $uniq = 'Test Person ' . bin2hex(random_bytes(3));
    $added = governance_add_person($uniq, 'Regression Tester', 'business-group', 'test-admin');
    $createdPersonIds[] = $added['personId'];
    assert_true('add_person returns id', ($added['personId'] ?? '') !== '');
    assert_true('new person on group', person_on($added['personId'], 'business-group'));
    $flag = db()->prepare('SELECT needs_assessment FROM governance_membership WHERE person_id = ?');
    $flag->execute([$added['personId']]);
    assert_true('add_person needs_assessment=0', (int)$flag->fetchColumn() === 0);

    $attr = governance_create_attribute('Regression Change Agent ' . bin2hex(random_bytes(2)), 'test-admin');
    $createdAttrIds[] = $attr['id'];
    assert_true('create_attribute id', ($attr['id'] ?? '') !== '');
    assert_true('create_attribute not seed', empty($attr['isSeed']));

    governance_set_person_tags($added['personId'], [$attr['id'], 'champion'], 'test-admin');
    $bundle2 = governance_load_bundle();
    $foundPerson = null;
    foreach ($bundle2['people'] as $p) {
        if ($p['id'] === $added['personId']) {
            $foundPerson = $p;
            break;
        }
    }
    assert_true('person in bundle after tags', $foundPerson !== null);
    assert_true(
        'person has custom tag',
        is_array($foundPerson['tags'] ?? null) && in_array($attr['id'], $foundPerson['tags'], true)
    );
    assert_true(
        'person has champion tag',
        in_array('champion', $foundPerson['tags'] ?? [], true)
    );

    $snap = governance_build_membership_snapshot();
    assert_true('snapshot version 2', ($snap['version'] ?? 0) === 2);
    assert_true('snapshot has attributes', is_array($snap['attributes'] ?? null) && count($snap['attributes']) >= 10);
    assert_true('snapshot has people', is_array($snap['people'] ?? null) && count($snap['people']) >= 1);
    $cap = governance_capture_history('checkpoint', 'test-admin', true);
    assert_true('checkpoint id for attr restore', ($cap['id'] ?? 0) > 0);
    $preRenameLabel = (string)(governance_attribute_meta()[$attr['id']]['label'] ?? '');

    $renamed = governance_rename_attribute($attr['id'], 'Renamed Change Agent', 'test-admin');
    assert_true('rename attribute', ($renamed['label'] ?? '') === 'Renamed Change Agent');

    // seed attr cannot delete
    assert_throws(
        'cannot delete seed attribute',
        static function () {
            governance_delete_attribute('champion', 'test-admin');
        },
        'Built-in'
    );

    governance_delete_attribute($attr['id'], 'test-admin');
    $createdAttrIds = array_values(array_filter($createdAttrIds, static fn ($id) => $id !== $attr['id']));
    assert_true('custom attr deleted from catalog', !isset(governance_attribute_meta()[$attr['id']]));

    $restored = governance_restore_from_history((int)$cap['id'], 'test-admin');
    assert_true('restore reports attributes', !empty($restored['restoredAttributes']));
    assert_true('restore reports people tags', !empty($restored['restoredPeopleTags']));
    assert_true('custom attr restored from history', isset(governance_attribute_meta()[$attr['id']]));
    assert_true(
        'restored attr label is pre-rename',
        (governance_attribute_meta()[$attr['id']]['label'] ?? '') === $preRenameLabel
    );
    $bRest = governance_load_bundle();
    $foundAgain = null;
    foreach ($bRest['people'] as $p) {
        if (($p['id'] ?? '') === $added['personId']) {
            $foundAgain = $p;
            break;
        }
    }
    assert_true(
        'person tags restored from history',
        is_array($foundAgain['tags'] ?? null) && in_array($attr['id'], $foundAgain['tags'], true)
    );

    governance_delete_attribute($attr['id'], 'test-admin');
    $createdAttrIds = array_values(array_filter($createdAttrIds, static fn ($id) => $id !== $attr['id']));
    assert_true('custom attr cleaned after restore test', !isset(governance_attribute_meta()[$attr['id']]));

    // ------------------------------------------------------------------
    // F. Votes (optional notes — private by default on set_vote, shareable)
    // ------------------------------------------------------------------
    echo "\nF. Votes & notes\n";
    as_admin();
    $voteTarget = $steerPerson ?? 'suzanne-conlin';
    $voteGroup = 'steering-group';
    if (!person_on($voteTarget, $voteGroup)) {
        $voteGroup = 'project-board';
    }
    governance_set_vote($voteTarget, $voteGroup, 'yes', 'because test', true, false);
    $b = governance_load_bundle();
    $myVotes = is_array($b['myVotes'] ?? null) ? $b['myVotes'] : (array)($b['myVotes'] ?? []);
    $myNotes = is_array($b['myVoteNotes'] ?? null) ? $b['myVoteNotes'] : (array)($b['myVoteNotes'] ?? []);
    $my = is_array($myVotes[$voteTarget] ?? null) ? ($myVotes[$voteTarget][$voteGroup] ?? null) : null;
    assert_true('vote saved as yes', $my === 'yes');
    $noteMap = is_array($myNotes[$voteTarget] ?? null) ? $myNotes[$voteTarget] : [];
    $note = $noteMap[$voteGroup] ?? null;
    assert_true('private note saved', $note === 'because test', 'got ' . json_encode($note));
    $sharedMap = is_array($b['sharedVoteNotes'] ?? null) ? $b['sharedVoteNotes'] : (array)($b['sharedVoteNotes'] ?? []);
    $sharedList = is_array($sharedMap[$voteTarget] ?? null)
        ? (is_array($sharedMap[$voteTarget][$voteGroup] ?? null) ? $sharedMap[$voteTarget][$voteGroup] : [])
        : [];
    assert_true('private note not in shared list', count($sharedList) === 0, json_encode($sharedList));

    governance_set_vote_note($voteTarget, $voteGroup, 'updated reason', true);
    $bNote = governance_load_bundle();
    $notes2 = is_array($bNote['myVoteNotes'] ?? null) ? $bNote['myVoteNotes'] : (array)($bNote['myVoteNotes'] ?? []);
    $note2 = is_array($notes2[$voteTarget] ?? null) ? ($notes2[$voteTarget][$voteGroup] ?? null) : null;
    assert_true('set_vote_note updates', $note2 === 'updated reason');
    $shared2 = is_array($bNote['sharedVoteNotes'] ?? null) ? $bNote['sharedVoteNotes'] : (array)($bNote['sharedVoteNotes'] ?? []);
    $sharedList2 = is_array($shared2[$voteTarget] ?? null)
        ? (is_array($shared2[$voteTarget][$voteGroup] ?? null) ? $shared2[$voteTarget][$voteGroup] : [])
        : [];
    assert_true('shared note visible in bundle', count($sharedList2) >= 1);
    $foundShared = false;
    foreach ($sharedList2 as $sn) {
        if (($sn['note'] ?? '') === 'updated reason' && !empty($sn['mine'])) {
            $foundShared = true;
            break;
        }
    }
    assert_true('shared note has mine flag', $foundShared);

    governance_set_vote_note($voteTarget, $voteGroup, 'back to private', false);
    $bPriv = governance_load_bundle();
    $shared3 = is_array($bPriv['sharedVoteNotes'] ?? null) ? $bPriv['sharedVoteNotes'] : (array)($bPriv['sharedVoteNotes'] ?? []);
    $sharedList3 = is_array($shared3[$voteTarget] ?? null)
        ? (is_array($shared3[$voteTarget][$voteGroup] ?? null) ? $shared3[$voteTarget][$voteGroup] : [])
        : [];
    $stillShared = false;
    foreach ($sharedList3 as $sn) {
        if (($sn['note'] ?? '') === 'back to private') {
            $stillShared = true;
        }
    }
    assert_true('unsharing removes from shared list', !$stillShared);

    governance_set_vote($voteTarget, $voteGroup, 'unset', null);
    $b2 = governance_load_bundle();
    $myVotes2 = is_array($b2['myVotes'] ?? null) ? $b2['myVotes'] : (array)($b2['myVotes'] ?? []);
    $cleared = !isset($myVotes2[$voteTarget]) || !isset($myVotes2[$voteTarget][$voteGroup]);
    assert_true('vote cleared', $cleared);

    // ------------------------------------------------------------------
    // G. Reapply seed (idempotent) + history
    // ------------------------------------------------------------------
    echo "\nG. Reapply seed & history\n";
    as_admin();
    $before = membership_counts()['total'];
    $r1 = governance_reapply_seed_placements('test-admin', false);
    assert_true('reapply when complete inserts 0', ($r1['insertedPairs'] ?? -1) === 0, json_encode($r1));
    assert_true('reapply does not shrink', membership_counts()['total'] >= $before);

    // Temporarily remove one seed pair then reapply
    $seedPair = null;
    foreach ($seedBoard as $p) {
        $seedPair = ['id' => $p['id'], 'g' => 'project-board'];
        break;
    }
    if ($seedPair) {
        db()->prepare('DELETE FROM governance_membership WHERE person_id = ? AND group_id = ?')
            ->execute([$seedPair['id'], $seedPair['g']]);
        assert_true('temp removed seed pair', !person_on($seedPair['id'], $seedPair['g']));
        $r2 = governance_reapply_seed_placements('test-admin', false);
        assert_true('reapply restores missing pair', ($r2['insertedPairs'] ?? 0) >= 1);
        assert_true('seed pair back', person_on($seedPair['id'], $seedPair['g']));
    }

    $hist = governance_list_history(5, 0, 'all');
    assert_true('history has items', ($hist['total'] ?? 0) > 0);

    // ------------------------------------------------------------------
    // H. Classic wipe scenario simulation
    // ------------------------------------------------------------------
    echo "\nH. Classic wipe scenario (first-place after empty table)\n";
    // Save current, empty table, ensure seed repopulates via load_bundle path
    $midSnapshot = snapshot_membership();
    db()->exec('DELETE FROM governance_membership');
    assert_true('membership emptied for scenario', membership_counts()['total'] === 0);
    // load_bundle should reseed when empty
    $b3 = governance_load_bundle();
    $afterSeed = membership_counts();
    assert_true(
        'empty table reseeds on load_bundle',
        $afterSeed['total'] >= 40,
        'got ' . $afterSeed['total']
    );
    assert_true(
        'reseed restores steering 17',
        ($afterSeed['byGroup']['steering-group'] ?? 0) === 17,
        'got ' . ($afterSeed['byGroup']['steering-group'] ?? 0)
    );
    // place one person should NOT leave only 1 row
    $u = null;
    $on = [];
    foreach (db()->query('SELECT DISTINCT person_id FROM governance_membership') as $row) {
        $on[$row['person_id']] = true;
    }
    foreach ($seed as $p) {
        if (!isset($on[$p['id']])) {
            $u = $p['id'];
            break;
        }
    }
    if ($u) {
        $beforePlace = membership_counts()['total'];
        governance_place_person($u, 'business-group', 'add', 'test-admin', null);
        $afterPlace = membership_counts()['total'];
        assert_true(
            'place after reseed does not wipe others',
            $afterPlace >= $beforePlace,
            "before=$beforePlace after=$afterPlace"
        );
        assert_true('others still present after place', $afterPlace > 5);
    }
    // restore mid snapshot (pre-wipe-scenario) before final restore
    restore_membership($midSnapshot);

    // ------------------------------------------------------------------
    // I. Permissions matrix
    // ------------------------------------------------------------------
    echo "\nI. Permissions\n";
    as_guest();
    assert_true('guest cannot vote', !governance_can_vote());
    assert_true('guest cannot propose', !governance_can_propose());
    assert_true('guest cannot confirm', !governance_can_confirm_membership());

    as_editor();
    assert_true('editor can vote', governance_can_vote());
    assert_true('editor can propose', governance_can_propose());
    assert_true('editor cannot confirm', !governance_can_confirm_membership());
    assert_true('editor cannot set status', !governance_can_set_group_status());

    as_admin();
    assert_true('admin can confirm', governance_can_confirm_membership());
    assert_true('admin can set status', governance_can_set_group_status());

} catch (Throwable $e) {
    echo "\n  FATAL  " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $fail++;
    $failures[] = 'FATAL: ' . $e->getMessage();
} finally {
    echo "\n--- Restoring live membership snapshot ---\n";
    try {
        as_admin();
        cleanup_test_artifacts($createdAttrIds, $createdPersonIds, $createdProposalIds);
        // delete any test custom groups
        foreach ($createdGroupIds as $gid) {
            try {
                governance_delete_group($gid, 'test-admin');
            } catch (Throwable) {
            }
        }
        restore_membership($snapshot);
        $restored = membership_counts();
        echo "Restored rows: {$restored['total']} (baseline was {$baselineCounts['total']})\n";
        if ($restored['total'] !== $baselineCounts['total'] || $restored['byGroup'] != $baselineCounts['byGroup']) {
            echo "  WARN  counts differ after restore: " . json_encode($restored['byGroup']) . "\n";
            // try once more
            restore_membership($snapshot);
            $restored = membership_counts();
        }
        assert_true(
            'restored membership matches baseline total',
            $restored['total'] === $baselineCounts['total'],
            "got {$restored['total']} expected {$baselineCounts['total']}"
        );
        assert_true(
            'restored group counts match',
            $restored['byGroup'] == $baselineCounts['byGroup'],
            json_encode($restored['byGroup']) . ' vs ' . json_encode($baselineCounts['byGroup'])
        );
    } catch (Throwable $e) {
        echo "  RESTORE FAILED: " . $e->getMessage() . "\n";
        $fail++;
        $failures[] = 'RESTORE FAILED: ' . $e->getMessage();
    }
}

echo "\n=== Results: $pass passed, $fail failed, $skip skipped ===\n";
if ($failures) {
    echo "Failures:\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "All checks passed.\n";
exit(0);
