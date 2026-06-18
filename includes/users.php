<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// ─── Password setup tokens ────────────────────────────────────

/**
 * Generate a fresh 6-digit code for a user and persist it (30-minute TTL).
 * Any previous unused tokens for the same user are deleted first.
 */
function create_password_setup_token(int $userId): string
{
    // Reuse an existing valid token so re-entering username doesn't invalidate the email
    $stmt = db()->prepare(
        'SELECT token FROM password_setup_tokens
          WHERE user_id = ? AND expires_at > NOW() AND used_at IS NULL
          LIMIT 1'
    );
    $stmt->execute([$userId]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        return (string)$existing;
    }

    db()->prepare('DELETE FROM password_setup_tokens WHERE user_id = ?')->execute([$userId]);
    $code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 1800);
    db()->prepare(
        'INSERT INTO password_setup_tokens (user_id, token, expires_at) VALUES (?, ?, ?)'
    )->execute([$userId, $code, $expires]);
    return $code;
}

/**
 * Verify a code and mark it used in one operation.
 * Returns true once; subsequent calls with the same token return false.
 */
function consume_password_setup_token(int $userId, string $code): bool
{
    $stmt = db()->prepare(
        'SELECT id FROM password_setup_tokens
          WHERE user_id = ? AND token = ? AND expires_at > NOW() AND used_at IS NULL
          LIMIT 1'
    );
    $stmt->execute([$userId, $code]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    db()->prepare('UPDATE password_setup_tokens SET used_at = NOW() WHERE id = ?')
        ->execute([(int)$row['id']]);
    return true;
}
