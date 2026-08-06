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

    // Assessment nags retired: clear any leftover flags so banners stay off.
    try {
        $pdo->exec('UPDATE governance_membership SET needs_assessment = 0 WHERE needs_assessment = 1');
    } catch (Throwable) {
        // ignore
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS governance_people (
      person_id   VARCHAR(120) NOT NULL PRIMARY KEY,
      name        VARCHAR(200) NOT NULL,
      role        VARCHAR(300) NOT NULL DEFAULT '',
      tags_json   TEXT         NULL,
      created_by  VARCHAR(120) NOT NULL DEFAULT '',
      created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Older installs may lack tags_json (person attributes / filter types)
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM governance_people LIKE 'tags_json'")->fetchAll();
        if (!$cols) {
            $pdo->exec('ALTER TABLE governance_people ADD COLUMN tags_json TEXT NULL AFTER role');
        }
    } catch (Throwable) {
        // ignore if race / already present
    }

    // Catalog of person types (Champion, Decider, custom types, …)
    $pdo->exec("CREATE TABLE IF NOT EXISTS governance_attributes (
      attr_id     VARCHAR(80)  NOT NULL PRIMARY KEY,
      label       VARCHAR(200) NOT NULL,
      is_seed     TINYINT(1)   NOT NULL DEFAULT 0,
      sort_order  INT          NOT NULL DEFAULT 100,
      created_by  VARCHAR(120) NOT NULL DEFAULT '',
      created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    governance_ensure_attributes_seeded();

    // One opinion per signed-in user, per person, per group
    $pdo->exec("CREATE TABLE IF NOT EXISTS governance_votes (
      id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id     INT UNSIGNED NOT NULL,
      user_label  VARCHAR(200) NOT NULL DEFAULT '',
      person_id   VARCHAR(120) NOT NULL,
      group_id    VARCHAR(80)  NOT NULL,
      vote        VARCHAR(10)  NOT NULL,
      comment     VARCHAR(500) NOT NULL DEFAULT '',
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


    // Private rationale on a mark (visible only to that voter)
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM governance_votes LIKE 'comment'")->fetchAll();
        if (!$cols) {
            $pdo->exec(
                "ALTER TABLE governance_votes
                   ADD COLUMN comment VARCHAR(500) NOT NULL DEFAULT '' AFTER vote"
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
 * Only designated confirmers may change live membership and group status
 * (add / move / remove / create groups / set Active vs In development).
 * Today that is portal administrators (Lisa / William); everyone else suggests.
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

/**
 * Built-in person-type labels from seed JSON (or hard-coded defaults).
 *
 * @return array<string, string> attr id => label
 */
function governance_seed_attribute_defaults(): array
{
    $defaults = [
        'advisor' => 'Advisor',
        'designer' => 'Designer',
        'decider' => 'Decider',
        'champion' => 'Champion',
        'trainer' => 'Trainer',
        'tester' => 'Tester',
        'influencer' => 'Influencer',
        'customer' => 'Customer',
        'expert' => 'Expert',
        'informed' => 'Informed',
        'consulted' => 'Consulted',
        'stakeholder-key' => 'Key stakeholder',
        'stakeholder-primary' => 'Primary stakeholder',
        'stakeholder-secondary' => 'Secondary stakeholder',
    ];
    $path = dirname(__DIR__) . '/assets/data/governance-people.json';
    if (is_file($path)) {
        $raw = json_decode((string)file_get_contents($path), true);
        if (is_array($raw['meta']['attributeLabels'] ?? null)) {
            foreach ($raw['meta']['attributeLabels'] as $id => $label) {
                $id = strtolower(trim((string)$id));
                $label = trim((string)$label);
                if ($id !== '' && $label !== '') {
                    $defaults[$id] = $label;
                }
            }
        }
    }
    return $defaults;
}

/**
 * Insert seed attribute rows once (empty table only). Deletions are not re-inserted.
 */
function governance_ensure_attributes_seeded(): void
{
    $pdo = db();
    $count = (int)$pdo->query('SELECT COUNT(*) FROM governance_attributes')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $ins = $pdo->prepare(
        'INSERT INTO governance_attributes (attr_id, label, is_seed, sort_order, created_by, created_at)
         VALUES (?, ?, 1, ?, \'seed\', NOW())'
    );
    $sort = 10;
    foreach (governance_seed_attribute_defaults() as $id => $label) {
        $ins->execute([$id, $label, $sort]);
        $sort += 10;
    }
}

/**
 * @return array<string, array{id:string,label:string,isSeed:bool,sort:int}>
 */
function governance_attribute_meta(): array
{
    governance_ensure_table();
    $stmt = db()->query(
        'SELECT attr_id, label, is_seed, sort_order
           FROM governance_attributes
          ORDER BY sort_order ASC, label ASC'
    );
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (string)$row['attr_id'];
        $out[$id] = [
            'id' => $id,
            'label' => (string)$row['label'],
            'isSeed' => (int)$row['is_seed'] === 1,
            'sort' => (int)$row['sort_order'],
        ];
    }
    return $out;
}

/**
 * Canonical person-attribute labels (Champion, Decider, Key stakeholder, …).
 *
 * @return array<string, string> tag id => label
 */
function governance_attribute_labels(): array
{
    $labels = [];
    foreach (governance_attribute_meta() as $id => $meta) {
        $labels[$id] = $meta['label'];
    }
    return $labels;
}

/**
 * @param mixed $raw
 * @return list<string>
 */
function governance_normalize_tags($raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        } else {
            $parts = preg_split('/\s*,\s*/', $raw);
            $raw = is_array($parts) ? $parts : [];
        }
    }
    if (!is_array($raw)) {
        return [];
    }
    $allowed = governance_attribute_labels();
    $out = [];
    $seen = [];
    foreach ($raw as $t) {
        $id = strtolower(trim((string)$t));
        if ($id === '' || !isset($allowed[$id]) || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $out[] = $id;
    }
    return $out;
}

/**
 * Create a custom person type (e.g. Change agent, Leader).
 *
 * @return array{id:string,label:string,isSeed:bool}
 */
function governance_create_attribute(string $label, string $createdBy): array
{
    if (!governance_can_shape()) {
        throw new RuntimeException('Forbidden');
    }
    $label = trim($label);
    if ($label === '') {
        throw new InvalidArgumentException('Attribute name is required');
    }
    if (mb_strlen($label) > 200) {
        throw new InvalidArgumentException('Attribute name is too long');
    }

    governance_ensure_table();
    $base = governance_slugify($label);
    if ($base === '' || str_starts_with($base, 'person-')) {
        $base = 'attr-' . ($base !== '' ? preg_replace('/^person-/', '', $base) : bin2hex(random_bytes(3)));
    }
    $attrId = $base;
    $n = 2;
    $pdo = db();
    $meta = governance_attribute_meta();
    while (isset($meta[$attrId])) {
        $attrId = $base . '-' . $n;
        $n++;
        if ($n > 50) {
            throw new InvalidArgumentException('Could not create a unique attribute id');
        }
    }

    $maxSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 100) FROM governance_attributes')->fetchColumn();
    $pdo->prepare(
        'INSERT INTO governance_attributes (attr_id, label, is_seed, sort_order, created_by, created_at)
         VALUES (?, ?, 0, ?, ?, NOW())'
    )->execute([$attrId, $label, $maxSort + 10, $createdBy]);

    return [
        'id' => $attrId,
        'label' => $label,
        'isSeed' => false,
    ];
}

/**
 * Rename a person type (seed or custom).
 *
 * @return array{id:string,label:string,isSeed:bool}
 */
function governance_rename_attribute(string $attrId, string $label, string $updatedBy): array
{
    if (!governance_can_shape()) {
        throw new RuntimeException('Forbidden');
    }
    $attrId = strtolower(trim($attrId));
    $label = trim($label);
    if ($attrId === '') {
        throw new InvalidArgumentException('Attribute id is required');
    }
    if ($label === '') {
        throw new InvalidArgumentException('Attribute name is required');
    }
    if (mb_strlen($label) > 200) {
        throw new InvalidArgumentException('Attribute name is too long');
    }

    governance_ensure_table();
    $meta = governance_attribute_meta()[$attrId] ?? null;
    if ($meta === null) {
        throw new InvalidArgumentException('Unknown attribute');
    }

    db()->prepare('UPDATE governance_attributes SET label = ? WHERE attr_id = ?')->execute([$label, $attrId]);

    return [
        'id' => $attrId,
        'label' => $label,
        'isSeed' => !empty($meta['isSeed']),
    ];
}

/**
 * Delete a custom person type and strip it from everyone.
 * Built-in (seed) types cannot be deleted — rename them instead if needed.
 */
function governance_delete_attribute(string $attrId, string $updatedBy = ''): void
{
    if (!governance_can_shape()) {
        throw new RuntimeException('Forbidden');
    }
    $attrId = strtolower(trim($attrId));
    $meta = governance_attribute_meta()[$attrId] ?? null;
    if ($meta === null) {
        throw new InvalidArgumentException('Unknown attribute');
    }
    if (!empty($meta['isSeed'])) {
        throw new InvalidArgumentException('Built-in person types cannot be deleted — rename them, or create a custom type instead');
    }

    governance_ensure_table();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Strip from custom / overridden people rows
        $stmt = $pdo->query('SELECT person_id, tags_json FROM governance_people WHERE tags_json IS NOT NULL');
        $upd = $pdo->prepare('UPDATE governance_people SET tags_json = ? WHERE person_id = ?');
        foreach ($stmt->fetchAll() as $row) {
            $decoded = json_decode((string)($row['tags_json'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }
            $next = [];
            foreach ($decoded as $t) {
                $t = strtolower(trim((string)$t));
                if ($t === '' || $t === $attrId) {
                    continue;
                }
                if (!in_array($t, $next, true)) {
                    $next[] = $t;
                }
            }
            $upd->execute([json_encode($next, JSON_UNESCAPED_UNICODE), $row['person_id']]);
        }
        $pdo->prepare('DELETE FROM governance_attributes WHERE attr_id = ? AND is_seed = 0')->execute([$attrId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** @return list<array{id: string, name: string, role: string, groups: list<string>, tags: list<string>, source: string}> */
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
            'tags' => governance_normalize_tags($p['tags'] ?? []),
            'source' => 'seed',
        ];
    }
    return $out;
}

/** @return list<array{id: string, name: string, role: string, groups: list<string>, tags: list<string>, source: string}> */
function governance_custom_people(): array
{
    governance_ensure_table();
    $stmt = db()->query('SELECT person_id, name, role, tags_json FROM governance_people ORDER BY name ASC');
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'id' => (string)$row['person_id'],
            'name' => (string)$row['name'],
            'role' => (string)$row['role'],
            'groups' => [],
            'tags' => governance_normalize_tags($row['tags_json'] ?? null),
            'source' => 'custom',
            // True when this row carries an explicit tags override (including empty list).
            'tagsOverride' => array_key_exists('tags_json', $row) && $row['tags_json'] !== null,
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

function governance_membership_pair_count(): int
{
    governance_ensure_table();
    return (int)db()->query('SELECT COUNT(*) FROM governance_membership')->fetchColumn();
}

/**
 * Materialise seed + custom person.groups into governance_membership when the table is empty.
 * Prevents the first place/move from making a single-row table the source of truth.
 *
 * @return int rows inserted
 */
function governance_ensure_membership_seeded(string $updatedBy = 'system'): int
{
    governance_ensure_table();
    if (governance_membership_pair_count() > 0) {
        return 0;
    }

    $allowed = governance_group_ids();
    $byId = [];
    foreach (governance_seed_people() as $p) {
        $byId[$p['id']] = $p;
    }
    foreach (governance_custom_people() as $p) {
        if (isset($byId[$p['id']])) {
            // Keep seed groups; custom people rows usually have empty groups.
            $byId[$p['id']]['name'] = $p['name'];
            if ($p['role'] !== '') {
                $byId[$p['id']]['role'] = $p['role'];
            }
        } else {
            $byId[$p['id']] = $p;
        }
    }

    $pdo = db();
    $ins = $pdo->prepare(
        'INSERT INTO governance_membership
          (person_id, group_id, vote, sort_order, needs_assessment, updated_by, updated_at)
         VALUES (?, ?, \'unset\', ?, 0, ?, NOW())'
    );
    $n = 0;
    $pdo->beginTransaction();
    try {
        foreach ($byId as $p) {
            $groups = is_array($p['groups'] ?? null) ? $p['groups'] : [];
            $sort = 0;
            foreach ($groups as $gid) {
                $gid = (string)$gid;
                if ($gid === '' || !in_array($gid, $allowed, true)) {
                    continue;
                }
                $ins->execute([(string)$p['id'], $gid, $sort, $updatedBy]);
                $n++;
                $sort++;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

        return $n;
}

/**
 * Restore placements from seed JSON only for people who currently have zero membership rows.
 * Safe recovery after an accidental wipe; does not move people already on a list.
 *
 * @return array{restoredPeople:int,insertedPairs:int}
 */
function governance_reseed_unassigned_from_seed(string $updatedBy): array
{
    if (!governance_can_confirm_membership()) {
        throw new RuntimeException('Only a membership confirmer can reseed unassigned people from the seed list');
    }
    return governance_rebuild_membership_from_seed($updatedBy);
}

/**
 * Ensure every seed person↔group pair from governance-people.json exists in live membership.
 * Does not remove people from lists (keeps live extras such as Business Group placements).
 * When $unassignedOnly is true, only people with zero current membership rows are considered.
 *
 * @return array{restoredPeople:int,insertedPairs:int,seedPairs:int,allowedGroups:list<string>}
 */
function governance_reapply_seed_placements(string $updatedBy, bool $unassignedOnly = false): array
{
    governance_ensure_table();
    $allowed = governance_group_ids();
    $seedPairs = 0;
    foreach (governance_seed_people() as $p) {
        foreach ($p['groups'] as $gid) {
            if (in_array((string)$gid, $allowed, true)) {
                $seedPairs++;
            }
        }
    }

    $currentPairs = [];
    $peopleWithAny = [];
    foreach (db()->query('SELECT person_id, group_id FROM governance_membership')->fetchAll() as $row) {
        $pid = (string)$row['person_id'];
        $gid = (string)$row['group_id'];
        $currentPairs[$pid . '|' . $gid] = true;
        $peopleWithAny[$pid] = true;
    }

    $pdo = db();
    $ins = $pdo->prepare(
        'INSERT INTO governance_membership
          (person_id, group_id, vote, sort_order, needs_assessment, updated_by, updated_at)
         VALUES (?, ?, \'unset\', ?, 0, ?, NOW())
         ON DUPLICATE KEY UPDATE updated_by = VALUES(updated_by), updated_at = NOW()'
    );
    $peopleRestored = 0;
    $pairs = 0;
    // Avoid multi-statement transactions on shared hosts — commit each insert.
    foreach (governance_seed_people() as $p) {
        $pid = (string)$p['id'];
        if ($unassignedOnly && isset($peopleWithAny[$pid])) {
            continue;
        }
        $groups = is_array($p['groups'] ?? null) ? $p['groups'] : [];
        $added = 0;
        $sort = 0;
        foreach ($groups as $gid) {
            $gid = (string)$gid;
            if ($gid === '' || !in_array($gid, $allowed, true)) {
                continue;
            }
            $key = $pid . '|' . $gid;
            if (isset($currentPairs[$key])) {
                $sort++;
                continue;
            }
            $ins->execute([$pid, $gid, $sort, $updatedBy]);
            $currentPairs[$key] = true;
            $pairs++;
            $added++;
            $sort++;
        }
        if ($added > 0) {
            $peopleRestored++;
        }
    }

    if ($pairs > 0) {
        try {
            governance_capture_history($unassignedOnly ? 'reseed_unassigned' : 'reapply_seed', $updatedBy, true);
        } catch (Throwable) {
            // Placements already saved; history is best-effort.
        }
    }

    return [
        'restoredPeople' => $peopleRestored,
        'insertedPairs' => $pairs,
        'seedPairs' => $seedPairs,
        'allowedGroups' => array_values($allowed),
        'membershipTotal' => governance_membership_pair_count(),
    ];
}

/**
 * Hard rebuild: replace membership for every seed person with exactly their seed groups.
 * Custom people (not in the seed file) keep their placements. Use when lists are emptied.
 *
 * @return array{
 *   restoredPeople:int,
 *   insertedPairs:int,
 *   removedPairs:int,
 *   seedPairs:int,
 *   allowedGroups:list<string>,
 *   membershipTotal:int
 * }
 */
function governance_rebuild_membership_from_seed(string $updatedBy): array
{
    if (!governance_can_confirm_membership()) {
        throw new RuntimeException('Only a membership confirmer can rebuild membership from the seed list');
    }
    governance_ensure_table();
    governance_seed_core_groups();
    $allowed = governance_group_ids();
    if ($allowed === []) {
        throw new RuntimeException('No groups are available — cannot rebuild membership');
    }

    $seedPeople = governance_seed_people();
    $seedIds = [];
    $seedPairsPlan = [];
    foreach ($seedPeople as $p) {
        $pid = (string)$p['id'];
        $seedIds[$pid] = true;
        $sort = 0;
        foreach ($p['groups'] as $gid) {
            $gid = (string)$gid;
            if ($gid === '' || !in_array($gid, $allowed, true)) {
                continue;
            }
            $seedPairsPlan[] = [$pid, $gid, $sort];
            $sort++;
        }
    }
    if ($seedPairsPlan === []) {
        throw new RuntimeException(
            'Seed file has no group placements to apply (check assets/data/governance-people.json on the server)'
        );
    }

    $pdo = db();
    $removed = 0;
    // Remove existing placements for seed people only (keeps pure custom people).
    $ids = array_keys($seedIds);
    foreach (array_chunk($ids, 50) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("DELETE FROM governance_membership WHERE person_id IN ($placeholders)");
        $stmt->execute($chunk);
        $removed += $stmt->rowCount();
    }

    $ins = $pdo->prepare(
        'INSERT INTO governance_membership
          (person_id, group_id, vote, sort_order, needs_assessment, updated_by, updated_at)
         VALUES (?, ?, \'unset\', ?, 0, ?, NOW())'
    );
    $inserted = 0;
    $people = [];
    foreach ($seedPairsPlan as [$pid, $gid, $sort]) {
        $ins->execute([$pid, $gid, $sort, $updatedBy]);
        $inserted++;
        $people[$pid] = true;
    }

    try {
        governance_capture_history('rebuild_seed', $updatedBy, true);
    } catch (Throwable) {
        // best-effort
    }

    $total = governance_membership_pair_count();
    if ($total < count($seedPairsPlan)) {
        throw new RuntimeException(
            "Rebuild wrote $inserted pairs but membership table only has $total — database write may have failed"
        );
    }

    return [
        'restoredPeople' => count($people),
        'insertedPairs' => $inserted,
        'removedPairs' => $removed,
        'seedPairs' => count($seedPairsPlan),
        'allowedGroups' => array_values($allowed),
        'membershipTotal' => $total,
    ];
}

/**
 * Replace live membership with a history snapshot (confirmers only).
 *
 * @return array{historyId:int,insertedPairs:int}
 */
function governance_restore_from_history(int $historyId, string $updatedBy, bool $force = false): array
{
    if (!governance_can_confirm_membership()) {
        throw new RuntimeException('Only a membership confirmer can restore a history snapshot');
    }
    $entry = governance_get_history($historyId);
    if ($entry === null) {
        throw new InvalidArgumentException('History entry not found');
    }
    $groups = $entry['snapshot']['groups'] ?? null;
    if (!is_array($groups)) {
        throw new InvalidArgumentException('History snapshot is missing groups');
    }

    $allowed = governance_group_ids();
    $rows = [];
    foreach ($groups as $g) {
        if (!is_array($g)) {
            continue;
        }
        $gid = (string)($g['id'] ?? '');
        if ($gid === '' || !in_array($gid, $allowed, true)) {
            continue;
        }
        $members = is_array($g['members'] ?? null) ? $g['members'] : [];
        $sort = 0;
        foreach ($members as $m) {
            if (!is_array($m)) {
                continue;
            }
            $pid = (string)($m['id'] ?? '');
            if ($pid === '') {
                continue;
            }
            $key = $pid . '|' . $gid;
            if (!isset($rows[$key])) {
                $rows[$key] = $sort;
            }
            $sort++;
        }
    }

    $newCount = count($rows);
    $currentCount = governance_membership_pair_count();
    if ($newCount < 1 && $currentCount > 0 && !$force) {
        throw new InvalidArgumentException(
            'Refusing to restore an empty snapshot while live membership has placements. Choose a fuller history entry.'
        );
    }
    if ($currentCount > 8 && $newCount < (int)ceil($currentCount * 0.35) && !$force) {
        throw new InvalidArgumentException(
            'This snapshot has far fewer placements than live membership. Pick an earlier full snapshot, or confirm a forced restore.'
        );
    }

    $pdo = db();
    $existingNeeds = [];
    foreach ($pdo->query('SELECT person_id, group_id, needs_assessment FROM governance_membership')->fetchAll() as $row) {
        $existingNeeds[$row['person_id'] . '|' . $row['group_id']] = (int)$row['needs_assessment'] === 1;
    }

    $pdo->beginTransaction();
    try {
        $keepKeys = array_keys($rows);
        $delVote = $pdo->prepare('DELETE FROM governance_votes WHERE person_id = ? AND group_id = ?');
        foreach ($pdo->query('SELECT person_id, group_id FROM governance_membership')->fetchAll() as $pair) {
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
        foreach ($rows as $key => $sort) {
            [$pid, $gid] = explode('|', $key, 2);
            $needs = !empty($existingNeeds[$key]) ? 1 : 0;
            $ins->execute([$pid, $gid, (int)$sort, $needs, $updatedBy]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    governance_capture_history('restore', $updatedBy, true);

    return ['historyId' => $historyId, 'insertedPairs' => $newCount];
}

function governance_load_bundle(): array
{
    governance_ensure_table();
    // Empty membership + seed JSON in the UI is unsafe: the first place/move
    // would make that one row the source of truth and unassign everyone else.
    if (governance_membership_pair_count() === 0) {
        governance_ensure_membership_seeded((string)($_SESSION['admin_user'] ?? 'system'));
    }

    $byId = [];
    foreach (governance_seed_people() as $p) {
        $byId[$p['id']] = $p;
    }
    foreach (governance_custom_people() as $p) {
        if (isset($byId[$p['id']])) {
            $byId[$p['id']]['name'] = $p['name'];
            $byId[$p['id']]['role'] = $p['role'] !== '' ? $p['role'] : $byId[$p['id']]['role'];
            $byId[$p['id']]['source'] = 'both';
            if (!empty($p['tagsOverride'])) {
                $byId[$p['id']]['tags'] = $p['tags'];
            }
        } else {
            unset($p['tagsOverride']);
            $byId[$p['id']] = $p;
        }
    }
    foreach ($byId as &$personRow) {
        unset($personRow['tagsOverride']);
        if (!isset($personRow['tags']) || !is_array($personRow['tags'])) {
            $personRow['tags'] = [];
        }
    }
    unset($personRow);

    $orderBuckets = [];
    $membership = [];
    $dbHasRows = false;

    $stmt = db()->query(
        'SELECT person_id, group_id, sort_order
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
    }

    $userId = governance_current_user_id();
    $myVotes = [];
    $myVoteNotes = [];
    $tallies = [];

    $vstmt = db()->query(
        'SELECT user_id, user_label, person_id, group_id, vote, comment
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
            $note = trim((string)($row['comment'] ?? ''));
            if ($note !== '') {
                if (!isset($myVoteNotes[$pid])) {
                    $myVoteNotes[$pid] = [];
                }
                $myVoteNotes[$pid][$gid] = $note;
            }
        }
    }

    $needs = [];

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
        'myVoteNotes' => $myVoteNotes ?: new stdClass(),
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
        'attributeLabels' => governance_attribute_labels(),
        'attributes' => governance_attribute_meta(),
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
    // Placement no longer requires a Yes/Maybe/No from every signed-in user.
    // Suggest → confirm (Lisa / William as confirmers) covers list changes;
    // personal marks remain optional and do not drive banners.
    return 0;
}

function governance_banner_html(): string
{
    return '';
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

    $existingCount = count($existingNeeds);
    $newCount = count($rows);
    if ($existingCount > 5 && $newCount === 0) {
        throw new InvalidArgumentException(
            'Refusing to wipe all membership via save. Restore from history or reseed unassigned people instead.'
        );
    }
    if ($existingCount > 10 && $newCount < (int)ceil($existingCount * 0.35)) {
        throw new InvalidArgumentException(
            'Refusing to save a membership map that removes most placements. Check the client state or restore from history.'
        );
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
 * Optional $comment is a private rationale (only returned to that user in the bundle).
 * Pass $updateComment = true to write the comment; otherwise an existing note is kept.
 */
function governance_set_vote(
    string $personId,
    string $groupId,
    string $vote,
    ?string $comment = null,
    bool $updateComment = false
): void {
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

    $note = $updateComment ? substr(trim((string)$comment), 0, 500) : null;

    if ($note !== null) {
        $pdo->prepare(
            'INSERT INTO governance_votes (user_id, user_label, person_id, group_id, vote, comment, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
               vote = VALUES(vote),
               comment = VALUES(comment),
               user_label = VALUES(user_label),
               updated_at = NOW()'
        )->execute([$userId, $label, $personId, $groupId, $vote, $note]);
        return;
    }

    $pdo->prepare(
        'INSERT INTO governance_votes (user_id, user_label, person_id, group_id, vote, comment, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
           vote = VALUES(vote),
           user_label = VALUES(user_label),
           updated_at = NOW()'
    )->execute([$userId, $label, $personId, $groupId, $vote, '']);
}

/**
 * Update only the private note on the current user's existing mark.
 */
function governance_set_vote_note(string $personId, string $groupId, string $comment): void
{
    if (!governance_can_vote()) {
        throw new RuntimeException('Forbidden');
    }
    $personId = trim($personId);
    $groupId = trim($groupId);
    $comment = substr(trim($comment), 0, 500);
    if ($personId === '' || !in_array($groupId, governance_group_ids(), true)) {
        throw new InvalidArgumentException('Invalid person or group');
    }

    governance_ensure_table();
    $userId = governance_current_user_id();
    $stmt = db()->prepare(
        'UPDATE governance_votes
            SET comment = ?, updated_at = NOW()
          WHERE user_id = ? AND person_id = ? AND group_id = ?'
    );
    $stmt->execute([$comment, $userId, $personId, $groupId]);
    if ($stmt->rowCount() < 1) {
        throw new InvalidArgumentException('Save a Yes / Maybe / No mark before adding a private note');
    }
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
 * Confirmers change the live list; others use suggestions. No “mark needed” flag.
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
    governance_ensure_membership_seeded($updatedBy);
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
             VALUES (?, ?, \'unset\', ?, 0, ?, NOW())
             ON DUPLICATE KEY UPDATE
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
    governance_ensure_membership_seeded($updatedBy);
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
    governance_ensure_membership_seeded($updatedBy);
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
        'INSERT INTO governance_people (person_id, name, role, tags_json, created_by, created_at)
         VALUES (?, ?, ?, NULL, ?, NOW())'
    )->execute([$personId, $name, $role, $updatedBy]);

    $max = $pdo->prepare('SELECT COALESCE(MIN(sort_order), 0) - 1 FROM governance_membership WHERE group_id = ?');
    $max->execute([$groupId]);
    $sort = (int)$max->fetchColumn();

    $pdo->prepare(
        'INSERT INTO governance_membership
          (person_id, group_id, vote, sort_order, needs_assessment, updated_by, updated_at)
         VALUES (?, ?, \'unset\', ?, 0, ?, NOW())
         ON DUPLICATE KEY UPDATE updated_by = VALUES(updated_by), updated_at = NOW()'
    )->execute([$personId, $groupId, $sort, $updatedBy]);

    governance_capture_history('add_person', $updatedBy);

    return [
        'personId' => $personId,
        'name' => $name,
        'role' => $role,
        'groupId' => $groupId,
        'tags' => [],
    ];
}

/**
 * Set project attributes (Champion, Decider, Key stakeholder, …) on a person.
 * Confirmers only. Upserts a governance_people row so seed people can be overridden.
 *
 * @param list<string>|mixed $tags
 * @return array{personId: string, tags: list<string>}
 */
function governance_set_person_tags(string $personId, $tags, string $updatedBy): array
{
    if (!governance_can_shape()) {
        throw new RuntimeException('Only a membership confirmer can edit person attributes');
    }
    $personId = trim($personId);
    if ($personId === '') {
        throw new InvalidArgumentException('Person id is required');
    }

    $normalized = governance_normalize_tags($tags);
    governance_ensure_table();

    $name = '';
    $role = '';
    $found = false;
    foreach (array_merge(governance_seed_people(), governance_custom_people()) as $p) {
        if ($p['id'] === $personId) {
            $name = (string)$p['name'];
            $role = (string)($p['role'] ?? '');
            $found = true;
            break;
        }
    }
    if (!$found) {
        throw new InvalidArgumentException('Unknown person');
    }

    $pdo = db();
    $pdo->prepare(
        'INSERT INTO governance_people (person_id, name, role, tags_json, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
           name = VALUES(name),
           role = IF(VALUES(role) = \'\', role, VALUES(role)),
           tags_json = VALUES(tags_json)'
    )->execute([$personId, $name, $role, json_encode($normalized, JSON_UNESCAPED_UNICODE), $updatedBy]);

    return ['personId' => $personId, 'tags' => $normalized];
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

    // First visit with no history yet: seed membership if needed, then pin composition.
    if ($total === 0 && $filter === 'all' && governance_can_shape()) {
        $user = (string)($_SESSION['admin_user'] ?? 'user');
        governance_ensure_membership_seeded($user);
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
