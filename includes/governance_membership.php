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
      status      VARCHAR(20)  NOT NULL DEFAULT 'development',
      sort_order  INT          NOT NULL DEFAULT 100,
      created_by  VARCHAR(120) NOT NULL DEFAULT '',
      created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Older installs may lack status
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM governance_groups LIKE 'status'")->fetchAll();
        if (!$cols) {
            $pdo->exec(
                "ALTER TABLE governance_groups
                   ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'development' AFTER is_core"
            );
        }
    } catch (Throwable) {
        // ignore if race / already present
    }

    // Widen legacy group_id columns for custom group slugs
    try {
        $pdo->exec('ALTER TABLE governance_membership MODIFY group_id VARCHAR(80) NOT NULL');
        $pdo->exec('ALTER TABLE governance_votes MODIFY group_id VARCHAR(80) NOT NULL');
    } catch (Throwable) {
        // already wide / unsupported
    }

    governance_seed_core_groups();

    $pdo->exec("CREATE TABLE IF NOT EXISTS governance_history (
      id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      captured_at    DATETIME     NOT NULL,
      captured_by    VARCHAR(120) NOT NULL DEFAULT '',
      reason         VARCHAR(80)  NOT NULL DEFAULT '',
      content_hash   CHAR(64)     NOT NULL,
      snapshot_json  LONGTEXT     NOT NULL,
      KEY idx_captured_at (captured_at DESC),
      KEY idx_content_hash (content_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS governance_proposals (
      id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      person_id            VARCHAR(120) NOT NULL,
      group_id             VARCHAR(80)  NOT NULL,
      action               VARCHAR(20)  NOT NULL,
      from_group_id        VARCHAR(80)  NULL,
      proposed_by          VARCHAR(120) NOT NULL DEFAULT '',
      proposed_by_user_id  INT UNSIGNED NOT NULL DEFAULT 0,
      status               VARCHAR(20)  NOT NULL DEFAULT 'pending',
      decided_by           VARCHAR(120) NOT NULL DEFAULT '',
      decided_at           DATETIME     NULL,
      created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_status_created (status, created_at),
      KEY idx_person_group (person_id, group_id),
      KEY idx_group_status (group_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** @return array<string, array{label: string, blurb: string, isCore: bool, sortOrder: int}> */
function governance_core_group_definitions(): array
{
    return [
        'project-board' => [
            'label' => 'Project Board',
            'blurb' => 'The Project Board provides senior oversight of the Housing Digital Transformation programme. It is responsible for strategic direction, prioritisation, and decisions on investment, resources, and escalation.',
            'isCore' => true,
            'sortOrder' => 10,
        ],
        'steering-group' => [
            'label' => 'Steering Group',
            'blurb' => 'The Steering Group brings together service leadership to review progress, surface operational issues, and advise the Project Board so that decisions are informed by service-wide knowledge.',
            'isCore' => true,
            'sortOrder' => 20,
        ],
        'technical-group' => [
            'label' => 'Technical Group',
            'blurb' => 'The Technical Group provides specialist advice on information technology, systems architecture, and data. It meets as required when technical detail is needed to support design or delivery decisions.',
            'isCore' => true,
            'sortOrder' => 30,
        ],
        'business-group' => [
            'label' => 'Business Group',
            'blurb' => 'The Business Group draws on specialist expertise from across the Council. It meets as required to inform business requirements and the impact of change on services.',
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
    $upd = $pdo->prepare(
        'UPDATE governance_groups
            SET label = ?, blurb = ?, sort_order = ?, is_core = 1
          WHERE group_id = ?'
    );
    foreach (governance_core_group_definitions() as $id => $meta) {
        $ins->execute([$id, $meta['label'], $meta['blurb'], $meta['sortOrder']]);
        $upd->execute([$meta['label'], $meta['blurb'], $meta['sortOrder'], $id]);
    }
}

/** @return list<string> */
function governance_group_ids(): array
{
    return array_keys(governance_group_meta());
}

/**
 * Lifecycle states for a working group. Each group can be at a different stage.
 *
 * @return array<string, string> id => label
 */
function governance_group_status_labels(): array
{
    return [
        'development' => 'In development',
        'active' => 'Active / stable',
        'inactive' => 'Inactive',
        'dissolved' => 'Dissolved',
    ];
}

function governance_normalize_group_status(string $status): string
{
    $status = trim(strtolower($status));
    return isset(governance_group_status_labels()[$status]) ? $status : 'development';
}

/**
 * Only portal admins (you) may change group lifecycle status.
 * Matches require_admin(): viewer/editor blocked; admin and legacy empty role allowed.
 */
function governance_can_set_group_status(): bool
{
    if (empty($_SESSION['admin_logged_in'])) {
        return false;
    }
    $role = $_SESSION['app_role'] ?? '';
    return $role !== 'viewer' && $role !== 'editor';
}

/**
 * Core groups first (by sort_order), then custom groups by sort_order / created_at.
 *
 * @return array<string, array{label: string, blurb: string, isCore: bool, status: string}>
 */
function governance_group_meta(): array
{
    governance_ensure_table();
    $out = [];
    $stmt = db()->query(
        'SELECT group_id, label, blurb, is_core, status, sort_order
           FROM governance_groups
          ORDER BY is_core DESC, sort_order ASC, created_at ASC, label ASC'
    );
    foreach ($stmt->fetchAll() as $row) {
        $out[(string)$row['group_id']] = [
            'label' => (string)$row['label'],
            'blurb' => (string)$row['blurb'],
            'isCore' => (int)$row['is_core'] === 1,
            'status' => governance_normalize_group_status((string)($row['status'] ?? 'development')),
        ];
    }
    // Safety fallback if table empty mid-migrate
    if (!$out) {
        foreach (governance_core_group_definitions() as $id => $meta) {
            $out[$id] = [
                'label' => $meta['label'],
                'blurb' => $meta['blurb'],
                'isCore' => true,
                'status' => 'development',
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

/**
 * Only designated confirmers may change live membership (add / move / remove / create groups).
 * Today that is portal administrators; later: Project Owner and allocated group managers.
 * Matches require_admin(): viewer/editor blocked; admin and legacy empty role allowed.
 */
function governance_can_confirm_membership(): bool
{
    return governance_can_set_group_status();
}

/** @deprecated Prefer governance_can_confirm_membership — kept as the shape gate. */
function governance_can_shape(): bool
{
    return governance_can_confirm_membership();
}

/** Any signed-in user may propose membership changes (tentative until confirmed). */
function governance_can_propose(): bool
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
 *   total: int
 * }
 */
function governance_empty_tally(): array
{
    return ['yes' => 0, 'maybe' => 0, 'no' => 0, 'total' => 0];
}

/**
 * @return array{
 *   people: list<array{id:string,name:string,role:string,groups:list<string>,source:string}>,
 *   myVotes: array<string, array<string, string>>,
 *   tallies: array<string, array<string, array{yes:int,maybe:int,no:int,total:int}>>,
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
        'canConfirmMembership' => governance_can_confirm_membership(),
        'canPropose' => governance_can_propose(),
        'canSetGroupStatus' => governance_can_set_group_status(),
        'groupStatuses' => governance_group_status_labels(),
        'proposals' => governance_list_pending_proposals(),
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

    governance_capture_history('membership_save', $updatedBy);
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
        'INSERT INTO governance_groups (group_id, label, blurb, is_core, status, sort_order, created_by, created_at)
         VALUES (?, ?, ?, 0, \'development\', ?, ?, NOW())'
    )->execute([$groupId, $label, $blurb, $maxSort + 10, $createdBy]);

    governance_capture_history('create_group', $createdBy);

    return [
        'groupId' => $groupId,
        'label' => $label,
        'blurb' => $blurb,
        'status' => 'development',
    ];
}

/**
 * Set lifecycle status for a group (admin only).
 *
 * @return array{groupId: string, status: string, label: string}
 */
function governance_set_group_status(string $groupId, string $status, string $updatedBy): array
{
    if (!governance_can_set_group_status()) {
        throw new RuntimeException('Only an administrator can change group status');
    }
    $groupId = trim($groupId);
    $status = governance_normalize_group_status($status);
    $meta = governance_group_meta()[$groupId] ?? null;
    if ($meta === null) {
        throw new InvalidArgumentException('Unknown group');
    }
    if (($meta['status'] ?? '') === $status) {
        return [
            'groupId' => $groupId,
            'status' => $status,
            'label' => governance_group_status_labels()[$status],
        ];
    }

    db()->prepare('UPDATE governance_groups SET status = ? WHERE group_id = ?')->execute([$status, $groupId]);
    governance_capture_history('group_status', $updatedBy);

    return [
        'groupId' => $groupId,
        'status' => $status,
        'label' => governance_group_status_labels()[$status],
    ];
}

/**
 * Delete a custom group (core groups cannot be removed). Clears membership and votes for it.
 */
function governance_delete_group(string $groupId, string $updatedBy = ''): void
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

    governance_capture_history('delete_group', $updatedBy !== '' ? $updatedBy : (string)($_SESSION['admin_user'] ?? 'user'));
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

    governance_capture_history($mode === 'move' ? 'place_move' : 'place_add', $updatedBy);

    return [
        'personId' => $personId,
        'toGroupId' => $toGroupId,
        'mode' => $mode,
        'fromGroupId' => $fromGroupId,
    ];
}

/**
 * Remove a person from one group (confirmer only). Clears votes for that pair.
 */
function governance_remove_person_from_group(string $personId, string $groupId, string $updatedBy): void
{
    if (!governance_can_confirm_membership()) {
        throw new RuntimeException('Only a membership confirmer can remove people from a group');
    }
    $personId = trim($personId);
    $groupId = trim($groupId);
    if ($personId === '' || !in_array($groupId, governance_group_ids(), true)) {
        throw new InvalidArgumentException('Invalid person or group');
    }

    governance_ensure_table();
    $pdo = db();
    $chk = $pdo->prepare('SELECT 1 FROM governance_membership WHERE person_id = ? AND group_id = ?');
    $chk->execute([$personId, $groupId]);
    if (!$chk->fetchColumn()) {
        throw new InvalidArgumentException('Person is not on that group');
    }

    $pdo->prepare('DELETE FROM governance_votes WHERE person_id = ? AND group_id = ?')->execute([$personId, $groupId]);
    $pdo->prepare('DELETE FROM governance_membership WHERE person_id = ? AND group_id = ?')->execute([$personId, $groupId]);
    governance_capture_history('membership_remove', $updatedBy);
}

/**
 * @return list<array{id:int,personId:string,personName:string,personRole:string,groupId:string,groupLabel:string,action:string,fromGroupId:?string,fromGroupLabel:?string,proposedBy:string,proposedByUserId:int,createdAt:string}>
 */
function governance_list_pending_proposals(): array
{
    governance_ensure_table();
    $byId = [];
    foreach (array_merge(governance_seed_people(), governance_custom_people()) as $p) {
        $byId[$p['id']] = $p;
    }
    $meta = governance_group_meta();
    $stmt = db()->query(
        "SELECT id, person_id, group_id, action, from_group_id, proposed_by, proposed_by_user_id, created_at
           FROM governance_proposals
          WHERE status = 'pending'
          ORDER BY created_at ASC, id ASC"
    );
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $pid = (string)$row['person_id'];
        $gid = (string)$row['group_id'];
        $from = $row['from_group_id'] !== null && $row['from_group_id'] !== ''
            ? (string)$row['from_group_id']
            : null;
        $out[] = [
            'id' => (int)$row['id'],
            'personId' => $pid,
            'personName' => $byId[$pid]['name'] ?? $pid,
            'personRole' => $byId[$pid]['role'] ?? '',
            'groupId' => $gid,
            'groupLabel' => $meta[$gid]['label'] ?? $gid,
            'action' => (string)$row['action'],
            'fromGroupId' => $from,
            'fromGroupLabel' => $from !== null ? ($meta[$from]['label'] ?? $from) : null,
            'proposedBy' => (string)$row['proposed_by'],
            'proposedByUserId' => (int)$row['proposed_by_user_id'],
            'createdAt' => (string)$row['created_at'],
        ];
    }
    return $out;
}

function governance_supersede_pending_proposals(string $personId, string $groupId, string $reason = 'superseded'): void
{
    db()->prepare(
        "UPDATE governance_proposals
            SET status = ?, decided_by = 'system', decided_at = NOW()
          WHERE status = 'pending' AND person_id = ? AND group_id = ?"
    )->execute([$reason, $personId, $groupId]);
}

/**
 * Create a pending membership proposal (any signed-in user).
 *
 * @return array{id:int,action:string,personId:string,groupId:string}
 */
function governance_propose(
    string $personId,
    string $groupId,
    string $action,
    string $proposedBy,
    ?string $fromGroupId = null
): array {
    if (!governance_can_propose()) {
        throw new RuntimeException('Sign in to suggest a membership change');
    }
    $personId = trim($personId);
    $groupId = trim($groupId);
    $action = trim($action);
    $fromGroupId = $fromGroupId !== null ? trim($fromGroupId) : null;

    if (!in_array($action, ['add', 'remove', 'move'], true)) {
        throw new InvalidArgumentException('Action must be add, remove, or move');
    }
    if ($personId === '' || !in_array($groupId, governance_group_ids(), true)) {
        throw new InvalidArgumentException('Invalid person or group');
    }

    governance_ensure_table();
    $pdo = db();

    $known = false;
    foreach (array_merge(governance_seed_people(), governance_custom_people()) as $p) {
        if ($p['id'] === $personId) {
            $known = true;
            break;
        }
    }
    if (!$known) {
        $chk = $pdo->prepare('SELECT 1 FROM governance_membership WHERE person_id = ? LIMIT 1');
        $chk->execute([$personId]);
        $known = (bool)$chk->fetchColumn();
    }
    if (!$known) {
        throw new InvalidArgumentException('Unknown person');
    }

    $onGroup = $pdo->prepare('SELECT 1 FROM governance_membership WHERE person_id = ? AND group_id = ?');
    $onGroup->execute([$personId, $groupId]);
    $isOn = (bool)$onGroup->fetchColumn();

    if ($action === 'add') {
        if ($isOn) {
            throw new InvalidArgumentException('Already on that group');
        }
        $fromGroupId = null;
    } elseif ($action === 'remove') {
        if (!$isOn) {
            throw new InvalidArgumentException('Person is not on that group');
        }
        $fromGroupId = null;
    } else { // move
        if ($fromGroupId === null || $fromGroupId === '' || !in_array($fromGroupId, governance_group_ids(), true)) {
            throw new InvalidArgumentException('Move needs a valid from group');
        }
        if ($fromGroupId === $groupId) {
            throw new InvalidArgumentException('Already on that group');
        }
        $onFrom = $pdo->prepare('SELECT 1 FROM governance_membership WHERE person_id = ? AND group_id = ?');
        $onFrom->execute([$personId, $fromGroupId]);
        if (!$onFrom->fetchColumn()) {
            throw new InvalidArgumentException('Person is not on the from group');
        }
        if ($isOn) {
            throw new InvalidArgumentException('Already on the target group');
        }
    }

    // One pending proposal per person+group
    $existing = $pdo->prepare(
        "SELECT id, action FROM governance_proposals
          WHERE status = 'pending' AND person_id = ? AND group_id = ?
          LIMIT 1"
    );
    $existing->execute([$personId, $groupId]);
    $row = $existing->fetch();
    if ($row) {
        throw new InvalidArgumentException('There is already a pending suggestion for this person on that group');
    }

    $userId = governance_current_user_id();
    $pdo->prepare(
        'INSERT INTO governance_proposals
          (person_id, group_id, action, from_group_id, proposed_by, proposed_by_user_id, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, \'pending\', NOW())'
    )->execute([
        $personId,
        $groupId,
        $action,
        $action === 'move' ? $fromGroupId : null,
        substr(trim($proposedBy), 0, 120),
        $userId,
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'action' => $action,
        'personId' => $personId,
        'groupId' => $groupId,
    ];
}

/**
 * Accept or reject a pending proposal. Accept applies the live membership change.
 *
 * @return array{id:int,status:string}
 */
function governance_decide_proposal(int $id, string $decision, string $decidedBy): array
{
    if (!governance_can_confirm_membership()) {
        throw new RuntimeException('Only a membership confirmer can accept or reject suggestions');
    }
    $decision = trim($decision);
    if (!in_array($decision, ['accept', 'reject'], true)) {
        throw new InvalidArgumentException('Decision must be accept or reject');
    }
    if ($id < 1) {
        throw new InvalidArgumentException('Invalid proposal');
    }

    governance_ensure_table();
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT id, person_id, group_id, action, from_group_id, status
           FROM governance_proposals WHERE id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || (string)$row['status'] !== 'pending') {
        throw new InvalidArgumentException('Proposal is not pending');
    }

    $personId = (string)$row['person_id'];
    $groupId = (string)$row['group_id'];
    $action = (string)$row['action'];
    $fromGroupId = $row['from_group_id'] !== null && $row['from_group_id'] !== ''
        ? (string)$row['from_group_id']
        : null;

    if ($decision === 'reject') {
        $pdo->prepare(
            "UPDATE governance_proposals
                SET status = 'rejected', decided_by = ?, decided_at = NOW()
              WHERE id = ? AND status = 'pending'"
        )->execute([substr(trim($decidedBy), 0, 120), $id]);
        return ['id' => $id, 'status' => 'rejected'];
    }

    // Accept: mark first so supersede does not wipe this row, then apply
    $pdo->prepare(
        "UPDATE governance_proposals
            SET status = 'accepted', decided_by = ?, decided_at = NOW()
          WHERE id = ? AND status = 'pending'"
    )->execute([substr(trim($decidedBy), 0, 120), $id]);

    if ($action === 'remove') {
        governance_remove_person_from_group($personId, $groupId, $decidedBy);
    } elseif ($action === 'add') {
        governance_place_person($personId, $groupId, 'add', $decidedBy, null);
    } else {
        governance_place_person($personId, $groupId, 'move', $decidedBy, $fromGroupId);
    }

    governance_supersede_pending_proposals($personId, $groupId, 'superseded');
    if ($fromGroupId !== null) {
        governance_supersede_pending_proposals($personId, $fromGroupId, 'superseded');
    }
    // Ensure this proposal stays accepted after supersede of same person+group
    $pdo->prepare(
        "UPDATE governance_proposals
            SET status = 'accepted', decided_by = ?, decided_at = NOW()
          WHERE id = ?"
    )->execute([substr(trim($decidedBy), 0, 120), $id]);

    return ['id' => $id, 'status' => 'accepted'];
}

/**
 * Proposer (or confirmer) withdraws a pending suggestion.
 */
function governance_withdraw_proposal(int $id, string $by): void
{
    if (!governance_can_propose()) {
        throw new RuntimeException('Forbidden');
    }
    if ($id < 1) {
        throw new InvalidArgumentException('Invalid proposal');
    }
    governance_ensure_table();
    $stmt = db()->prepare(
        'SELECT id, proposed_by_user_id, status FROM governance_proposals WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || (string)$row['status'] !== 'pending') {
        throw new InvalidArgumentException('Proposal is not pending');
    }
    $uid = governance_current_user_id();
    if (!governance_can_confirm_membership() && (int)$row['proposed_by_user_id'] !== $uid) {
        throw new RuntimeException('You can only withdraw your own suggestions');
    }
    db()->prepare(
        "UPDATE governance_proposals
            SET status = 'withdrawn', decided_by = ?, decided_at = NOW()
          WHERE id = ? AND status = 'pending'"
    )->execute([substr(trim($by), 0, 120), $id]);
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

    governance_capture_history('add_person', $updatedBy);

    return [
        'personId' => $personId,
        'name' => $name,
        'role' => $role,
        'groupId' => $groupId,
    ];
}

/**
 * Build a membership-only snapshot (groups + who sat where, with name/role at that moment).
 * Votes are omitted so history stays about composition, not private opinions.
 *
 * @return array{groups: list<array{id:string,label:string,blurb:string,isCore:bool,members:list<array{id:string,name:string,role:string}>}>}
 */
function governance_build_membership_snapshot(): array
{
    governance_ensure_table();
    $meta = governance_group_meta();
    $byId = [];
    foreach (governance_seed_people() as $p) {
        $byId[$p['id']] = $p;
    }
    foreach (governance_custom_people() as $p) {
        if (isset($byId[$p['id']])) {
            $byId[$p['id']]['name'] = $p['name'];
            $byId[$p['id']]['role'] = $p['role'] !== '' ? $p['role'] : ($byId[$p['id']]['role'] ?? '');
        } else {
            $byId[$p['id']] = $p;
        }
    }

    $membersByGroup = [];
    $stmt = db()->query(
        'SELECT person_id, group_id, sort_order
           FROM governance_membership
          ORDER BY group_id ASC, sort_order ASC, person_id ASC'
    );
    foreach ($stmt->fetchAll() as $row) {
        $gid = (string)$row['group_id'];
        $pid = (string)$row['person_id'];
        if (!isset($membersByGroup[$gid])) {
            $membersByGroup[$gid] = [];
        }
        $membersByGroup[$gid][] = [
            'id' => $pid,
            'name' => $byId[$pid]['name'] ?? $pid,
            'role' => $byId[$pid]['role'] ?? '',
        ];
    }

    $groups = [];
    foreach ($meta as $gid => $g) {
        $groups[] = [
            'id' => $gid,
            'label' => $g['label'],
            'blurb' => $g['blurb'],
            'isCore' => !empty($g['isCore']),
            'status' => $g['status'] ?? 'development',
            'members' => $membersByGroup[$gid] ?? [],
        ];
    }
    // Include orphan membership groups not in meta (safety)
    foreach ($membersByGroup as $gid => $members) {
        if (isset($meta[$gid])) {
            continue;
        }
        $groups[] = [
            'id' => $gid,
            'label' => $gid,
            'blurb' => '',
            'isCore' => false,
            'status' => 'development',
            'members' => $members,
        ];
    }

    return ['groups' => $groups];
}

/**
 * Persist a history row unless the membership composition is unchanged from the last snapshot.
 * Pass $force = true to always write (e.g. named checkpoints at the same composition).
 *
 * @return array{id:int,capturedAt:string,reason:string}|null
 */
function governance_capture_history(string $reason, string $capturedBy, bool $force = false): ?array
{
    governance_ensure_table();
    $snapshot = governance_build_membership_snapshot();
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return null;
    }
    $hash = hash('sha256', $json);

    $pdo = db();
    if (!$force) {
        $prev = $pdo->query(
            'SELECT content_hash FROM governance_history ORDER BY captured_at DESC, id DESC LIMIT 1'
        )->fetchColumn();
        if (is_string($prev) && hash_equals($prev, $hash)) {
            return null;
        }
    }

    $reason = substr(trim($reason), 0, 80) ?: 'change';
    $capturedBy = substr(trim($capturedBy), 0, 120);
    $pdo->prepare(
        'INSERT INTO governance_history (captured_at, captured_by, reason, content_hash, snapshot_json)
         VALUES (NOW(), ?, ?, ?, ?)'
    )->execute([$capturedBy, $reason, $hash, $json]);

    $id = (int)$pdo->lastInsertId();
    $at = (string)$pdo->query('SELECT captured_at FROM governance_history WHERE id = ' . $id)->fetchColumn();

    return [
        'id' => $id,
        'capturedAt' => $at,
        'reason' => $reason,
    ];
}

/**
 * @return array{items: list<array{id:int,capturedAt:string,capturedBy:string,reason:string}>, total: int, limit: int, offset: int}
 */
function governance_list_history(int $limit = 25, int $offset = 0, string $filter = 'all'): array
{
    governance_ensure_table();
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $filter = trim(strtolower($filter));
    if (!in_array($filter, ['all', 'milestones'], true)) {
        $filter = 'all';
    }

    $pdo = db();
    $where = '';
    if ($filter === 'milestones') {
        $where = " WHERE reason IN ('checkpoint', 'baseline')";
    }

    $total = (int)$pdo->query("SELECT COUNT(*) FROM governance_history{$where}")->fetchColumn();

    // First visit with no history yet: pin today's composition as the starting point.
    if ($total === 0 && $filter === 'all' && governance_can_shape()) {
        $user = (string)($_SESSION['admin_user'] ?? 'user');
        $captured = governance_capture_history('baseline', $user, true);
        if ($captured !== null) {
            $total = 1;
        }
    }

    $stmt = $pdo->query(
        "SELECT id, captured_at, captured_by, reason
           FROM governance_history
          {$where}
          ORDER BY captured_at DESC, id DESC
          LIMIT {$limit} OFFSET {$offset}"
    );
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id' => (int)$row['id'],
            'capturedAt' => (string)$row['captured_at'],
            'capturedBy' => (string)$row['captured_by'],
            'reason' => (string)$row['reason'],
        ];
    }

    return [
        'items' => $items,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'filter' => $filter,
    ];
}

/**
 * @return array{id:int,capturedAt:string,capturedBy:string,reason:string,snapshot:array}|null
 */
function governance_get_history(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    governance_ensure_table();
    $stmt = db()->prepare(
        'SELECT id, captured_at, captured_by, reason, snapshot_json
           FROM governance_history WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $snapshot = json_decode((string)$row['snapshot_json'], true);
    if (!is_array($snapshot)) {
        $snapshot = ['groups' => []];
    }
    return [
        'id' => (int)$row['id'],
        'capturedAt' => (string)$row['captured_at'],
        'capturedBy' => (string)$row['captured_by'],
        'reason' => (string)$row['reason'],
        'snapshot' => $snapshot,
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
