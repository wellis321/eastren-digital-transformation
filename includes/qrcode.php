<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function qr_ensure_table(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS qr_links (
        id         VARCHAR(16)   NOT NULL,
        url        VARCHAR(2048) NOT NULL,
        hits       INT UNSIGNED  NOT NULL DEFAULT 0,
        created_by VARCHAR(120)  NOT NULL DEFAULT '',
        created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_qr_links_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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

/** @return list<array<string,mixed>> */
function qr_list_links(int $limit = 200): array
{
    qr_ensure_table();
    $stmt = db()->prepare('SELECT * FROM qr_links ORDER BY created_at DESC LIMIT ?');
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
        'INSERT INTO qr_links (id, url, hits, created_by, created_at) VALUES (?, ?, 0, ?, NOW())'
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
    $stmt = db()->prepare('UPDATE qr_links SET hits = hits + 1 WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        return null;
    }
    return qr_get_link($id);
}

function qr_track_url(string $id): string
{
    return rtrim(APP_URL, '/') . '/r/' . rawurlencode($id);
}
