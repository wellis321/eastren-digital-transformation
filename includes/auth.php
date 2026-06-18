<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect(APP_URL . '/login.php?next=' . urlencode(login_next_url()));
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_rate_limited(string $ip): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn() >= 10;
}

function record_failed_attempt(string $ip): void
{
    db()->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
}

function clear_attempts(string $ip): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
}

/** @param array<string,mixed> $user */
function establish_session(array $user, array $entraRoles = []): void
{
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user']      = (string)($user['display_name'] ?: $user['username']);
    $_SESSION['user_id']         = (int)$user['id'];
    $_SESSION['auth_provider']   = (string)($user['auth_provider'] ?? 'local');
    $_SESSION['app_role']        = (string)($user['app_role'] ?? 'viewer');
    $_SESSION['entra_roles']     = $entraRoles;
}

function attempt_login(string $user, string $pass): bool
{
    require_once __DIR__ . '/debug-log.php';

    $user = trim($user);
    $email = strtolower($user);

    $db   = db();
    $stmt = $db->prepare(
        'SELECT id, username, password_hash, auth_provider, display_name, app_role, email
         FROM users
         WHERE is_active = 1
           AND (username = ? OR LOWER(email) = ?)
         LIMIT 1'
    );
    $stmt->execute([$user, $email]);
    $row  = $stmt->fetch();

    $hash  = $row && !empty($row['password_hash'])
        ? $row['password_hash']
        : '$2y$12$invalidsaltinvalidsaltinvalidsaltinvalidsaltinvalidsa';
    $valid = password_verify($pass, $hash);

    $reason = 'bad_password';
    if (!$row) {
        $reason = 'user_not_found';
    } elseif (empty($row['password_hash'])) {
        $reason = 'microsoft_only_no_local_password';
    } elseif ($valid) {
        $reason = 'ok';
    }

    $_SESSION['_last_login_fail'] = $reason;

    $dbActual = '';
    try {
        $dbActual = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    } catch (Throwable) {
        $dbActual = 'unknown';
    }

    // #region agent log
    auth_debug_log('auth.php:attempt_login', 'login attempt', [
        'username' => $user,
        'db_name_config' => DB_NAME,
        'db_name_actual' => $dbActual,
        'user_found' => (bool)$row,
        'matched_username' => $row['username'] ?? null,
        'auth_provider' => $row['auth_provider'] ?? null,
        'has_password_hash' => $row && !empty($row['password_hash']),
        'password_ok' => $valid,
        'reason' => $reason,
    ], 'H-login');
    // #endregion

    if ($row && $valid && !empty($row['password_hash'])) {
        establish_session($row);
        unset($_SESSION['_last_login_fail']);
        return true;
    }
    return false;
}

function last_login_fail_reason(): string
{
    return (string)($_SESSION['_last_login_fail'] ?? '');
}

function connected_database_name(): string
{
    try {
        return (string)db()->query('SELECT DATABASE()')->fetchColumn();
    } catch (Throwable) {
        return 'connection failed';
    }
}

function active_users_count(): int
{
    try {
        return (int)db()->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
    } catch (Throwable) {
        return -1;
    }
}

/**
 * @param array<string,mixed> $claims
 * @return array{ok: bool, error?: string}
 */
function login_user_from_entra(array $claims): array
{
    require_once __DIR__ . '/entra_config.php';
    require_once __DIR__ . '/permissions.php';

    $oid   = (string)($claims['oid'] ?? '');
    $email = strtolower(trim((string)($claims['preferred_username'] ?? $claims['email'] ?? '')));
    $name  = trim((string)($claims['name'] ?? $email));

    if ($oid === '') {
        return ['ok' => false, 'error' => 'Microsoft account identifier missing from token.'];
    }

    $rolesClaim = $claims['roles'] ?? [];
    if (!is_array($rolesClaim)) {
        $rolesClaim = $rolesClaim !== '' && $rolesClaim !== null ? [(string)$rolesClaim] : [];
    }

    $allowed = array_values(array_intersect($rolesClaim, entra_allowed_role_names()));
    if ($allowed === []) {
        return ['ok' => false, 'error' => 'No application role assigned. Ask your administrator to assign SOR.Admin, SOR.Editor, or SOR.Viewer in Microsoft Entra.'];
    }

    $appRole = map_entra_roles_to_app_role($allowed);
    if ($appRole === null) {
        return ['ok' => false, 'error' => 'Unrecognised application role in token.'];
    }

    $db = db();

    $stmt = $db->prepare('SELECT * FROM users WHERE entra_oid = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$oid]);
    $user = $stmt->fetch();

    if (!$user && $email !== '') {
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $db->prepare(
                'UPDATE users SET entra_oid = ?, auth_provider = ?, display_name = ?, app_role = ? WHERE id = ?'
            )->execute([$oid, 'microsoft', $name ?: $user['display_name'], $appRole, $user['id']]);
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([(int)$user['id']]);
            $user = $stmt->fetch();
        }
    }

    if (!$user) {
        $username = $email !== '' ? $email : ('entra_' . substr($oid, 0, 12));
        $db->prepare(
            'INSERT INTO users (username, password_hash, auth_provider, entra_oid, email, display_name, app_role, is_active)
             VALUES (?, NULL, ?, ?, ?, ?, ?, 1)'
        )->execute([$username, 'microsoft', $oid, $email ?: null, $name ?: null, $appRole]);
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int)$db->lastInsertId()]);
        $user = $stmt->fetch();
    } else {
        $db->prepare(
            'UPDATE users SET auth_provider = ?, email = COALESCE(?, email), display_name = COALESCE(?, display_name), app_role = ? WHERE id = ?'
        )->execute(['microsoft', $email ?: null, $name ?: null, $appRole, $user['id']]);
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int)$user['id']]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        return ['ok' => false, 'error' => 'Could not provision user account.'];
    }

    establish_session($user, $allowed);
    return ['ok' => true];
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
