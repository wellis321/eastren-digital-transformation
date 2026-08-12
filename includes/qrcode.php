<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function qr_ensure_table(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS qr_links (
        id          VARCHAR(16)   NOT NULL,
        url         VARCHAR(2048) NOT NULL,
        hits        INT UNSIGNED  NOT NULL DEFAULT 0,
        created_by  VARCHAR(120)  NOT NULL DEFAULT '',
        created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        archived_at DATETIME      NULL DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_qr_links_created (created_at),
        KEY idx_qr_links_archived (archived_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Upgrade older installs that pre-date archive support.
    $hasArchived = db()->query("SHOW COLUMNS FROM qr_links LIKE 'archived_at'")->fetch();
    if (!$hasArchived) {
        db()->exec('ALTER TABLE qr_links ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER created_at');
        try {
            db()->exec('ALTER TABLE qr_links ADD KEY idx_qr_links_archived (archived_at)');
        } catch (Throwable) {
            // Index may already exist on partial upgrades.
        }
    }
}

function qr_normalize_url(string $raw): ?string
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return null;
    }

    $withProtocol = preg_match('#^https?://#i', $trimmed) === 1
        ? $trimmed
        : 'https://' . $trimmed;

    $parts = parse_url($withProtocol);
    if ($parts === false || empty($parts['host'])) {
        return null;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null;
    }

    return $withProtocol;
}

function qr_new_id(): string
{
    return bin2hex(random_bytes(4));
}

function qr_is_valid_id(string $id): bool
{
    return preg_match('/^[a-f0-9]{8}$/', $id) === 1;
}

/**
 * @param 'active'|'archived'|'all' $status
 * @return list<array<string,mixed>>
 */
function qr_list_links(string $status = 'active', int $limit = 200): array
{
    qr_ensure_table();

    $sql = 'SELECT * FROM qr_links';
    if ($status === 'active') {
        $sql .= ' WHERE archived_at IS NULL';
    } elseif ($status === 'archived') {
        $sql .= ' WHERE archived_at IS NOT NULL';
    }
    $sql .= ' ORDER BY created_at DESC LIMIT ?';

    $stmt = db()->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** @return array<string,mixed>|null */
function qr_get_link(string $id): ?array
{
    qr_ensure_table();
    $stmt = db()->prepare('SELECT * FROM qr_links WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** @return array<string,mixed> */
function qr_create_link(string $url, string $createdBy = ''): array
{
    qr_ensure_table();
    $id = qr_new_id();
    db()->prepare(
        'INSERT INTO qr_links (id, url, hits, created_by, created_at, archived_at)
         VALUES (?, ?, 0, ?, NOW(), NULL)'
    )->execute([$id, $url, $createdBy]);

    $link = qr_get_link($id);
    if ($link === null) {
        throw new RuntimeException('Failed to create QR link.');
    }
    return $link;
}

/** @return array<string,mixed>|null */
function qr_record_hit(string $id): ?array
{
    qr_ensure_table();
    // Archived codes still redirect (printed QRs may remain in use); deleted codes do not.
    $stmt = db()->prepare('UPDATE qr_links SET hits = hits + 1 WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        return null;
    }
    return qr_get_link($id);
}

function qr_archive_link(string $id): bool
{
    qr_ensure_table();
    if (!qr_is_valid_id($id)) {
        return false;
    }
    $stmt = db()->prepare(
        'UPDATE qr_links SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL'
    );
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function qr_restore_link(string $id): bool
{
    qr_ensure_table();
    if (!qr_is_valid_id($id)) {
        return false;
    }
    $stmt = db()->prepare(
        'UPDATE qr_links SET archived_at = NULL WHERE id = ? AND archived_at IS NOT NULL'
    );
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function qr_delete_link(string $id): bool
{
    qr_ensure_table();
    if (!qr_is_valid_id($id)) {
        return false;
    }
    $stmt = db()->prepare('DELETE FROM qr_links WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function qr_track_url(string $id): string
{
    return rtrim(APP_URL, '/') . '/r/' . rawurlencode($id);
}

function qr_is_archived(array $link): bool
{
    return !empty($link['archived_at']);
}
