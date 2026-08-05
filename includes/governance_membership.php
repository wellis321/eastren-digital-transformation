<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';

function governance_ensure_table(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS governance_membership (
      id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      person_id        VARCHAR(120) NOT NULL,
      group_id         VARCHAR(40)  NOT NULL,
      vote             VARCHAR(10)  NOT NULL DEFAULT 'unset',
      sort_order       INT          NOT NULL DEFAULT 0,
      needs_assessment TINYINT(1)   NOT NULL DEFAULT 0,
      updated_by       VARCHAR(120) NOT NULL DEFAULT '',
      updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_person_group (person_id, group_id),
      KEY idx_group_sort (group_id, sort_order),
      KEY idx_needs_assessment (needs_assessment)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Older installs may lack needs_assessment
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM governance_membership LIKE 'needs_assessment'")->fetchAll();
        if (!$cols) {
            $pdo->exec('ALTER TABLE governance_membership ADD COLUMN needs_assessment TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order');
            $pdo->exec('ALTER TABLE governance_membership ADD KEY idx_needs_assessment (needs_assessment)');
        }
    } catch (Throwable) {
        // ignore if race / already present
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS governance_people (
      person_id   VARCHAR(120) NOT NULL PRIMARY KEY,
      name        VARCHAR(200) NOT NULL,
      role        VARCHAR(300) NOT NULL DEFAULT '',
      created_by  VARCHAR(120) NOT NULL DEFAULT '',
      created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // One opinion per signed-in user, per person, per group
    $pdo->exec("CREATE TABLE IF NOT EXISTS governance_votes (
      id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id     INT UNSIGNED NOT NULL,
      user_label  VARCHAR(200) NOT NULL DEFAULT '',
      person_id   VARCHAR(120) NOT NULL,
      group_id    VARCHAR(80)  NOT NULL,
      vote        VARCHAR(10)  NOT NULL,
      updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_user_person_group (user_id, person_id, group_id),
      KEY idx_person_group (person_id, group_id),
      KEY idx_group (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS governance_groups (
      group_id    VARCHAR(80)  NOT NULL PRIMARY KEY,
      label       VARCHAR(200) NOT NULL,
      blurb       VARCHAR(500) NOT NULL DEFAULT '',
      is_core     TINYINT(1)   NOT NULL DEFAULT 0,
      sort_order  INT          NOT NULL DEFAULT 100,
      created_by  VARCHAR(120) NOT NULL DEFAULT '',
      created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Widen legacy group_id columns for custom group slugs
    try {
        $pdo->exec('ALTER TABLE governance_membership MODIFY group_id VARCHAR(80) NOT NULL');
        $pdo->exec('ALTER TABLE governance_votes MODIFY group_id VARCHAR(80) NOT NULL');
    } catch (Throwable) {
        // already wide / unsupported
    }

    governance_seed_core_groups();
}

/** @return array<string, array{label: string, blurb: string, isCore: bool, sortOrder: int}> */
function governance_core_group_definitions(): array
{
    return [
        'project-board' => [
            'label' => 'Project Board',
            'blurb' => 'Senior movers who hold purse strings and can unlock time and decisions.',
            'isCore' => true,
            'sortOrder' => 10,
        ],
        'steering-group' => [
            'label' => 'Steering Group',
            'blurb' => 'Service-wide knowledge — informs the Project Board.',
            'isCore' => true,
            'sortOrder' => 20,
        ],
        'technical-group' => [
            'label' => 'Technical Group',
            'blurb' => 'IT, systems and data depth — called when that detail matters.',
            'isCore' => true,
            'sortOrder' => 30,
        ],
        'business-group' => [
            'label' => 'Business Group',
            'blurb' => 'Expertise on tap (e.g. Social Care, planners) for as-needed support.',
            'isCore' => true,
            'sortOrder' => 40,
        ],
    ];
}

function governance_seed_core_groups(): void
{
    $pdo = db();
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO governance_groups
          (group_id, label, blurb, is_core, sort_order, created_by, created_at)
         VALUES (?, ?, ?, 1, ?, \'system\', NOW())'
    );
    foreach (governance_core_group_definitions() as $id => $meta) {
        $ins->execute([$id, $meta['label'], $meta['blurb'], $meta['sortOrder']]);
    }
}

/** @return list<string> */
function governance_group_ids(): array
{
    return array_keys(governance_group_meta());
}

/**
 * Core groups first (by sort_order), then custom groups by sort_order / created_at.
 *
 * @return array<string, array{label: string, blurb: string, isCore: bool}>
 */
function governance_group_meta(): array
{
    governance_ensure_table();
    $out = [];
    $stmt = db()->query(
        'SELECT group_id, label, blurb, is_core, sort_order
           FROM governance_groups
          ORDER BY is_core DESC, sort_order ASC, created_at ASC, label ASC'
    );
    foreach ($stmt->fetchAll() as $row) {
        $out[(string)$row['group_id']] = [
            'label' => (string)$row['label'],
            'blurb' => (string)$row['blurb'],
            'isCore' => (int)$row['is_core'] === 1,
        ];
    }
    // Safety fallback if table empty mid-migrate
    if (!$out) {
        foreach (governance_core_group_definitions() as $id => $meta) {
            $out[$id] = [
                'label' => $meta['label'],
                'blurb' => $meta['blurb'],
                'isCore' => true,
            ];
        }
    }
    return $out;
}

function governance_can_edit(): bool
{
    $role = $_SESSION['app_role'] ?? '';
    if ($role === '' || $role === 'admin' || $role === 'editor') {
        return true;
    }
    return false;
}

/** Any signed-in user may cast exactly one opinion per person/group. */
function governance_can_vote(): bool
{
    return !empty($_SESSION['admin_logged_in']) && governance_current_user_id() > 0;
}

/** Signed-in users may move people, put them on multiple lists, and create custom groups. */
function governance_can_shape(): bool
{
    return governance_can_vote();
}

function governance_current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function governance_current_user_label(): string
{
    $label = trim((string)($_SESSION['admin_user'] ?? ''));
    return $label !== '' ? $label : ('User ' . governance_current_user_id());
}

function governance_slugify(string $name): string
{
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: ('person-' . bin2hex(random_bytes(4)));
}

/** @return list<array{id: string, name: string, role: string, groups: list<string>, source: string}> */
function governance_seed_people(): array
{
    $path = dirname(__DIR__) . '/assets/data/governance-people.json';
    if (!is_file($path)) {
        return [];
    }
    $raw = json_decode((string)file_get_contents($path), true);
    if (!is_array($raw) || !is_array($raw['people'] ?? null)) {
        return [];
    }
    $out = [];
    foreach ($raw['people'] as $p) {
        if (!is_array($p) || empty($p['id']) || empty($p['name'])) {
            continue;
        }
        $groups = [];
        if (is_array($p['groups'] ?? null)) {
            foreach ($p['groups'] as $g) {
                $g = (string)$g;
                if (in_array($g, governance_group_ids(), true)) {
                    $groups[] = $g;
                }
            }
        }
        $out[] = [
            'id' => (string)$p['id'],
            'name' => (string)$p['name'],
            'role' => (string)($p['role'] ?? ''),
            'groups' => $groups,
            'source' => 'seed',
        ];
    }
    return $out;
}

/** @return list<array{id: string, name: string, role: string, groups: list<string>, source: string}> */
function governance_custom_people(): array
{
    governance_ensure_table();
    $stmt = db()->query('SELECT person_id, name, role FROM governance_people ORDER BY name ASC');
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'id' => (string)$row['person_id'],
            'name' => (string)$row['name'],
            'role' => (string)$row['role'],
            'groups' => [],
            'source' => 'custom',
        ];
    }
    return $out;
}

/**
 * @return array{
 *   yes: int,
 *   maybe: int,
 *   no: int,
 *   total: int,
 *   voters: list<array{userId:int,name:string,vote:string}>
 * }
 */
function governance_empty_tally(): array
{
    return ['yes' => 0, 'maybe' => 0, 'no' => 0, 'total' => 0, 'voters' => []];
}

/**
 * @return array{
 *   people: list<array{id:string,name:string,role:string,groups:list<string>,source:string}>,
 *   myVotes: array<string, array<string, string>>,
 *   tallies: array<string, array<string, array{yes:int,maybe:int,no:int,total:int,voters:list}>>,
 *   order: array<string, list<string>>,
 *   membership: array<string, list<string>>,
 *   needsAssessment: list<array{personId:string,name:string,role:string,groupId:string,groupLabel:string}>,
 *   pendingCount: int,
 *   canEdit: bool,
 *   canVote: bool,
 *   currentUser: array{id:int,label:string},
 *   groups: array<string, array{label:string,blurb:string}>,
 *   dbInitialized: bool
 * }
 */
function governance_load_bundle(): array
{
    governance_ensure_table();

    $byId = [];
    foreach (governance_seed_people() as $p) {
        $byId[$p['id']] = $p;
    }
    foreach (governance_custom_people() as $p) {
        if (isset($byId[$p['id']])) {
            $byId[$p['id']]['name'] = $p['name'];
            $byId[$p['id']]['role'] = $p['role'] !== '' ? $p['role'] : $byId[$p['id']]['role'];
            $byId[$p['id']]['source'] = 'both';
        } else {
            $byId[$p['id']] = $p;
        }
    }

    $orderBuckets = [];
    $membership = [];
    $needsFlags = [];
    $dbHasRows = false;

    $stmt = db()->query(
        'SELECT person_id, group_id, sort_order, needs_assessment
           FROM governance_membership
          ORDER BY group_id ASC, sort_order ASC, person_id ASC'
    );
    foreach ($stmt->fetchAll() as $row) {
        $dbHasRows = true;
        $pid = (string)$row['person_id'];
        $gid = (string)$row['group_id'];
        if (!isset($membership[$pid])) {
            $membership[$pid] = [];
        }
        if (!in_array($gid, $membership[$pid], true)) {
            $membership[$pid][] = $gid;
        }
        if (!isset($orderBuckets[$gid])) {
            $orderBuckets[$gid] = [];
        }
        $orderBuckets[$gid][] = $pid;
        if ((int)$row['needs_assessment'] === 1) {
            $needsFlags[$pid . '|' . $gid] = true;
        }
    }

    $userId = governance_current_user_id();
    $myVotes = [];
    $tallies = [];

    $vstmt = db()->query(
        'SELECT user_id, user_label, person_id, group_id, vote
           FROM governance_votes
          ORDER BY updated_at ASC, id ASC'
    );
    foreach ($vstmt->fetchAll() as $row) {
        $pid = (string)$row['person_id'];
        $gid = (string)$row['group_id'];
        $vote = (string)$row['vote'];
        if (!in_array($vote, ['yes', 'maybe', 'no'], true)) {
            continue;
        }
        if (!isset($tallies[$pid])) {
            $tallies[$pid] = [];
        }
        if (!isset($tallies[$pid][$gid])) {
            $tallies[$pid][$gid] = governance_empty_tally();
        }
        $tallies[$pid][$gid][$vote]++;
        $tallies[$pid][$gid]['total']++;
        $tallies[$pid][$gid]['voters'][] = [
            'userId' => (int)$row['user_id'],
            'name' => (string)$row['user_label'],
            'vote' => $vote,
        ];
        if ((int)$row['user_id'] === $userId) {
            if (!isset($myVotes[$pid])) {
                $myVotes[$pid] = [];
            }
            $myVotes[$pid][$gid] = $vote;
        }
    }

    $needs = [];
    foreach ($needsFlags as $key => $_) {
        [$pid, $gid] = explode('|', $key, 2);
        $already = isset($myVotes[$pid][$gid]);
        if ($already) {
            continue;
        }
        $needs[] = [
            'personId' => $pid,
            'name' => $byId[$pid]['name'] ?? $pid,
            'role' => $byId[$pid]['role'] ?? '',
            'groupId' => $gid,
            'groupLabel' => governance_group_meta()[$gid]['label'] ?? $gid,
        ];
    }

    $people = array_values($byId);
    if ($dbHasRows) {
        foreach ($people as &$p) {
            $p['groups'] = $membership[$p['id']] ?? [];
        }
        unset($p);
    }

    return [
        'people' => $people,
        'myVotes' => $myVotes ?: new stdClass(),
        'tallies' => $tallies ?: new stdClass(),
        'order' => $orderBuckets ?: new stdClass(),
        'membership' => $membership ?: new stdClass(),
        'needsAssessment' => $needs,
        'pendingCount' => count($needs),
        'canEdit' => governance_can_edit(),
        'canVote' => governance_can_vote(),
        'canShape' => governance_can_shape(),
        'currentUser' => [
            'id' => $userId,
            'label' => governance_current_user_label(),
        ],
        'groups' => governance_group_meta(),
        'groupOrder' => governance_group_ids(),
        'dbInitialized' => $dbHasRows,
    ];
}

function governance_pending_count(?int $userId = null): int
{
    try {
        governance_ensure_table();
        $uid = $userId ?? governance_current_user_id();
        if ($uid < 1) {
            return 0;
        }
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM governance_membership m
              WHERE m.needs_assessment = 1
                AND NOT EXISTS (
                  SELECT 1 FROM governance_votes v
                   WHERE v.user_id = ?
                     AND v.person_id = m.person_id
                     AND v.group_id = m.group_id
                )"
        );
        $stmt->execute([$uid]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

/**
 * Save membership list + order only. Does not touch per-user votes.
 *
 * @param array{order?: mixed, membership?: mixed} $payload
 */
function governance_save_state(array $payload, string $updatedBy): void
{
    if (!governance_can_shape()) {
        throw new RuntimeException('Forbidden');
    }

    governance_ensure_table();
    $allowedGroups = governance_group_ids();
    $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
    $membership = is_array($payload['membership'] ?? null) ? $payload['membership'] : [];

    $existingNeeds = [];
    $stmt = db()->query('SELECT person_id, group_id, needs_assessment FROM governance_membership');
    foreach ($stmt->fetchAll() as $row) {
        $key = $row['person_id'] . '|' . $row['group_id'];
        $existingNeeds[$key] = (int)$row['needs_assessment'] === 1;
    }

    /** @var array<string, array{sort_order: int, needs_assessment: int}> $rows */
    $rows = [];

    foreach ($membership as $personId => $groups) {
        $personId = (string)$personId;
        if ($personId === '' || !is_array($groups)) {
            continue;
        }
        foreach ($groups as $gid) {
            $gid = (string)$gid;
            if (!in_array($gid, $allowedGroups, true)) {
                continue;
            }
            $key = $personId . '|' . $gid;
            $rows[$key] = [
                'sort_order' => 0,
                'needs_assessment' => !empty($existingNeeds[$key]) ? 1 : 0,
            ];
        }
    }

    foreach ($order as $gid => $ids) {
        $gid = (string)$gid;
        if (!in_array($gid, $allowedGroups, true) || !is_array($ids)) {
            continue;
        }
        $i = 0;
        foreach ($ids as $personId) {
            $personId = (string)$personId;
            if ($personId === '') {
                continue;
            }
            $key = $personId . '|' . $gid;
            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'sort_order' => $i,
                    'needs_assessment' => !empty($existingNeeds[$key]) ? 1 : 0,
                ];
            } else {
                $rows[$key]['sort_order'] = $i;
            }
            $i++;
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Drop opinions for membership pairs that are being removed
        $keepKeys = array_keys($rows);
        $existingPairs = $pdo->query('SELECT person_id, group_id FROM governance_membership')->fetchAll();
        $delVote = $pdo->prepare(
            'DELETE FROM governance_votes WHERE person_id = ? AND group_id = ?'
        );
        foreach ($existingPairs as $pair) {
            $key = $pair['person_id'] . '|' . $pair['group_id'];
            if (!isset($rows[$key])) {
                $delVote->execute([(string)$pair['person_id'], (string)$pair['group_id']]);
            }
        }

        $pdo->exec('DELETE FROM governance_membership');
        $ins = $pdo->prepare(
            'INSERT INTO governance_membership
              (person_id, group_id, vote, sort_order, needs_assessment, updated_by, updated_at)
             VALUES (?, ?, \'unset\', ?, ?, ?, NOW())'
        );
        foreach ($rows as $key => $row) {
            [$personId, $gid] = explode('|', $key, 2);
            $ins->execute([
                $personId,
                $gid,
                (int)$row['sort_order'],
                (int)$row['needs_assessment'],
                $updatedBy,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Upsert or clear the current user's single opinion for a person/group.
 */
function governance_set_vote(string $personId, string $groupId, string $vote): void
{
    if (!governance_can_vote()) {
        throw new RuntimeException('Forbidden');
    }
    $personId = trim($personId);
    $groupId = trim($groupId);
    $vote = trim($vote);
    if ($personId === '' || !in_array($groupId, governance_group_ids(), true)) {
        throw new InvalidArgumentException('Invalid person or group');
    }
    if (!in_array($vote, ['yes', 'maybe', 'no', 'unset'], true)) {
        throw new InvalidArgumentException('Invalid vote');
    }

    governance_ensure_table();
    $userId = governance_current_user_id();
    $label = governance_current_user_label();
    $pdo = db();

    if ($vote === 'unset') {
        $pdo->prepare(
            'DELETE FROM governance_votes WHERE user_id = ? AND person_id = ? AND group_id = ?'
        )->execute([$userId, $personId, $groupId]);
        return;
    }

    $pdo->prepare(
        'INSERT INTO governance_votes (user_id, user_label, person_id, group_id, vote, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
           vote = VALUES(vote),
           user_label = VALUES(user_label),
           updated_at = NOW()'
    )->execute([$userId, $label, $personId, $groupId, $vote]);
}

/**
 * Clear the current user's opinions (optionally for one group only).
 */
function governance_clear_my_votes(?string $groupId = null): void
{
    if (!governance_can_vote()) {
        throw new RuntimeException('Forbidden');
    }
    governance_ensure_table();
    $userId = governance_current_user_id();
    if ($groupId !== null && $groupId !== '') {
        if (!in_array($groupId, governance_group_ids(), true)) {
            throw new InvalidArgumentException('Invalid group');
        }
        db()->prepare(
            'DELETE FROM governance_votes WHERE user_id = ? AND group_id = ?'
        )->execute([$userId, $groupId]);
        return;
    }
    db()->prepare('DELETE FROM governance_votes WHERE user_id = ?')->execute([$userId]);
}

/**
 * Create a custom working group (beyond the four core lists).
 *
 * @return array{groupId: string, label: string, blurb: string}
 */
function governance_create_group(string $label, string $blurb, string $createdBy): array
{
    if (!governance_can_shape()) {
        throw new RuntimeException('Forbidden');
    }
    $label = trim($label);
    $blurb = trim($blurb);
    if ($label === '') {
        throw new InvalidArgumentException('Group name is required');
    }
    if (mb_strlen($label) > 200) {
        throw new InvalidArgumentException('Group name is too long');
    }

    governance_ensure_table();
    $base = governance_slugify($label);
    if (str_starts_with($base, 'person-')) {
        $base = 'group-' . substr($base, 7);
    }
    $groupId = $base;
    $n = 2;
    $pdo = db();
    while (isset(governance_group_meta()[$groupId])) {
        $groupId = $base . '-' . $n;
        $n++;
        if ($n > 50) {
            throw new InvalidArgumentException('Could not create a unique group id');
        }
    }

    $maxSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 100) FROM governance_groups WHERE is_core = 0')->fetchColumn();
    $pdo->prepare(
        'INSERT INTO governance_groups (group_id, label, blurb, is_core, sort_order, created_by, created_at)
         VALUES (?, ?, ?, 0, ?, ?, NOW())'
    )->execute([$groupId, $label, $blurb, $maxSort + 10, $createdBy]);

    return [
        'groupId' => $groupId,
        'label' => $label,
        'blurb' => $blurb,
    ];
}

/**
 * Delete a custom group (core groups cannot be removed). Clears membership and votes for it.
 */
function governance_delete_group(string $groupId): void
{
    if (!governance_can_shape()) {
        throw new RuntimeException('Forbidden');
    }
    $groupId = trim($groupId);
    $meta = governance_group_meta()[$groupId] ?? null;
    if ($meta === null) {
        throw new InvalidArgumentException('Unknown group');
    }
    if (!empty($meta['isCore'])) {
        throw new InvalidArgumentException('The four core groups cannot be deleted');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM governance_votes WHERE group_id = ?')->execute([$groupId]);
        $pdo->prepare('DELETE FROM governance_membership WHERE group_id = ?')->execute([$groupId]);
        $pdo->prepare('DELETE FROM governance_groups WHERE group_id = ? AND is_core = 0')->execute([$groupId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Place a person on a group: add (keep existing lists) or move (leave fromGroup).
 * New placement is flagged for assessment so others mark them in that list.
 *
 * @return array{personId: string, toGroupId: string, mode: string, fromGroupId: ?string}
 */
function governance_place_person(
    string $personId,
    string $toGroupId,
    string $mode,
    string $updatedBy,
    ?string $fromGroupId = null
): array {
    if (!governance_can_shape()) {
        throw new RuntimeException('Forbidden');
    }
    $personId = trim($personId);
    $toGroupId = trim($toGroupId);
    $mode = trim($mode);
    $fromGroupId = $fromGroupId !== null ? trim($fromGroupId) : null;

    if ($personId === '' || !in_array($toGroupId, governance_group_ids(), true)) {
        throw new InvalidArgumentException('Invalid person or target group');
    }
    if (!in_array($mode, ['add', 'move'], true)) {
        throw new InvalidArgumentException('Mode must be add or move');
    }
    if ($mode === 'move') {
        if ($fromGroupId === null || $fromGroupId === '' || !in_array($fromGroupId, governance_group_ids(), true)) {
            throw new InvalidArgumentException('Move needs a valid from group');
        }
        if ($fromGroupId === $toGroupId) {
            throw new InvalidArgumentException('Already on that group');
        }
    }

    governance_ensure_table();
    $pdo = db();

    // Confirm person exists in seed or custom
    $known = false;
    foreach (array_merge(governance_seed_people(), governance_custom_people()) as $p) {
        if ($p['id'] === $personId) {
            $known = true;
            break;
        }
    }
    if (!$known) {
        // May already be on a membership row only
        $chk = $pdo->prepare('SELECT 1 FROM governance_membership WHERE person_id = ? LIMIT 1');
        $chk->execute([$personId]);
        $known = (bool)$chk->fetchColumn();
    }
    if (!$known) {
        throw new InvalidArgumentException('Unknown person');
    }

    $pdo->beginTransaction();
    try {
        if ($mode === 'move' && $fromGroupId !== null) {
            $pdo->prepare(
                'DELETE FROM governance_votes WHERE person_id = ? AND group_id = ?'
            )->execute([$personId, $fromGroupId]);
            $pdo->prepare(
                'DELETE FROM governance_membership WHERE person_id = ? AND group_id = ?'
            )->execute([$personId, $fromGroupId]);
        }

        $exists = $pdo->prepare(
            'SELECT 1 FROM governance_membership WHERE person_id = ? AND group_id = ?'
        );
        $exists->execute([$personId, $toGroupId]);
        if ($exists->fetchColumn()) {
            throw new InvalidArgumentException('Already on that group');
        }

        $max = $pdo->prepare(
            'SELECT COALESCE(MIN(sort_order), 0) - 1 FROM governance_membership WHERE group_id = ?'
        );
        $max->execute([$toGroupId]);
        $sort = (int)$max->fetchColumn();

        $pdo->prepare(
            'INSERT INTO governance_membership
              (person_id, group_id, vote, sort_order, needs_assessment, updated_by, updated_at)
             VALUES (?, ?, \'unset\', ?, 1, ?, NOW())
             ON DUPLICATE KEY UPDATE
               needs_assessment = 1,
               updated_by = VALUES(updated_by),
               updated_at = NOW()'
        )->execute([$personId, $toGroupId, $sort, $updatedBy]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'personId' => $personId,
        'toGroupId' => $toGroupId,
        'mode' => $mode,
        'fromGroupId' => $fromGroupId,
    ];
}

/**
 * @return array{personId: string, name: string, role: string, groupId: string}
 */
function governance_add_person(string $name, string $role, string $groupId, string $updatedBy): array
{
    if (!governance_can_shape()) {
        throw new RuntimeException('Forbidden');
    }
    $name = trim($name);
    $role = trim($role);
    $groupId = trim($groupId);
    if ($name === '' || $role === '') {
        throw new InvalidArgumentException('Name and job role are required');
    }
    if (!in_array($groupId, governance_group_ids(), true)) {
        throw new InvalidArgumentException('Invalid group');
    }

    governance_ensure_table();
    $base = governance_slugify($name);
    $personId = $base;
    $n = 2;
    $pdo = db();
    while (true) {
        $chk = $pdo->prepare('SELECT 1 FROM governance_people WHERE person_id = ?');
        $chk->execute([$personId]);
        $inCustom = (bool)$chk->fetchColumn();
        $inSeed = false;
        foreach (governance_seed_people() as $p) {
            if ($p['id'] === $personId) {
                $inSeed = true;
                break;
            }
        }
        if (!$inCustom && !$inSeed) {
            break;
        }
        $personId = $base . '-' . $n;
        $n++;
    }

    $pdo->prepare(
        'INSERT INTO governance_people (person_id, name, role, created_by, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    )->execute([$personId, $name, $role, $updatedBy]);

    $max = $pdo->prepare('SELECT COALESCE(MIN(sort_order), 0) - 1 FROM governance_membership WHERE group_id = ?');
    $max->execute([$groupId]);
    $sort = (int)$max->fetchColumn();

    $pdo->prepare(
        'INSERT INTO governance_membership
          (person_id, group_id, vote, sort_order, needs_assessment, updated_by, updated_at)
         VALUES (?, ?, \'unset\', ?, 1, ?, NOW())
         ON DUPLICATE KEY UPDATE needs_assessment = 1, updated_by = VALUES(updated_by), updated_at = NOW()'
    )->execute([$personId, $groupId, $sort, $updatedBy]);

    return [
        'personId' => $personId,
        'name' => $name,
        'role' => $role,
        'groupId' => $groupId,
    ];
}

function governance_banner_html(): string
{
    $count = governance_pending_count();
    if ($count < 1) {
        return '';
    }
    $label = $count === 1
        ? '1 person is awaiting your membership assessment'
        : $count . ' people are awaiting your membership assessment';
    $url = e(APP_URL . '/governance-membership.html#awaiting');
    return '<div class="gov-notify-banner" role="status">'
        . '<strong>Governance:</strong> ' . e($label) . '. '
        . '<a href="' . $url . '">Review in the membership tool</a>'
        . '</div>';
}
