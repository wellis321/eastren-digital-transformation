<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/entra_config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';

use TheNetworg\OAuth2\Client\Provider\Azure;

function entra_oauth_provider(): Azure
{
    $provider = new Azure([
        'clientId'               => entra_client_id(),
        'clientSecret'           => entra_client_secret(),
        'redirectUri'            => entra_redirect_uri(),
        'tenant'                 => entra_tenant_id(),
        'defaultEndPointVersion' => Azure::ENDPOINT_VERSION_2_0,
    ]);
    $provider->defaultEndPointVersion = Azure::ENDPOINT_VERSION_2_0;
    return $provider;
}

function entra_start_login(): never
{
    if (!entra_enabled()) {
        flash('error', 'Microsoft sign-in is not configured.');
        redirect(APP_URL . '/login.php');
    }

    $provider = entra_oauth_provider();
    $nonce    = bin2hex(random_bytes(16));
    $_SESSION['entra_nonce'] = $nonce;

    $authUrl = $provider->getAuthorizationUrl([
        'scope' => 'openid profile email',
        'nonce' => $nonce,
    ]);
    $_SESSION['entra_oauth_state'] = $provider->getState();

    header('Location: ' . $authUrl);
    exit;
}

/** @return array{ok: bool, error?: string, next?: string} */
function entra_handle_callback(): array
{
    if (!entra_enabled()) {
        return ['ok' => false, 'error' => 'Microsoft sign-in is not configured.'];
    }

    if (empty($_GET['state']) || empty($_SESSION['entra_oauth_state'])
        || !hash_equals((string)$_SESSION['entra_oauth_state'], (string)$_GET['state'])) {
        return ['ok' => false, 'error' => 'Invalid sign-in state. Please try again.'];
    }
    unset($_SESSION['entra_oauth_state']);

    if (!empty($_GET['error'])) {
        return ['ok' => false, 'error' => 'Microsoft sign-in was cancelled or failed.'];
    }

    $code = $_GET['code'] ?? '';
    if ($code === '') {
        return ['ok' => false, 'error' => 'No authorization code received.'];
    }

    try {
        $provider = entra_oauth_provider();
        $token    = $provider->getAccessToken('authorization_code', [
            'code'  => $code,
            'scope' => 'openid profile email',
        ]);
        $claims = $token->getIdTokenClaims();
        if (!is_array($claims) || $claims === []) {
            return ['ok' => false, 'error' => 'Could not read identity token.'];
        }

        $expectedNonce = $_SESSION['entra_nonce'] ?? '';
        unset($_SESSION['entra_nonce']);
        if ($expectedNonce === '' || ($claims['nonce'] ?? '') !== $expectedNonce) {
            return ['ok' => false, 'error' => 'Invalid sign-in nonce. Please try again.'];
        }

        $result = login_user_from_entra($claims);
        if (!$result['ok']) {
            return $result;
        }

        $next = $_SESSION['entra_login_next'] ?? '';
        unset($_SESSION['entra_login_next']);
        $next = resolve_next_redirect($next, APP_URL . '/index.html');

        return ['ok' => true, 'next' => $next];
    } catch (Throwable $e) {
        if (APP_ENV === 'local') {
            return ['ok' => false, 'error' => 'Microsoft sign-in failed: ' . $e->getMessage()];
        }
        return ['ok' => false, 'error' => 'Microsoft sign-in failed. Please try again or use username/password.'];
    }
}
