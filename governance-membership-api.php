<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/governance_membership.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised — sign in via the PHP portal']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        echo json_encode(governance_load_bundle());
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? ($payload['csrf_token'] ?? ($_POST['csrf_token'] ?? ''));
    if (!is_string($csrf) || $csrf === '' || !hash_equals(csrf_token(), $csrf)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token — refresh the page and try again']);
        exit;
    }

    $user = (string)($_SESSION['admin_user'] ?? 'user');
    $action = (string)($payload['action'] ?? 'save');

    if ($action === 'set_vote') {
        governance_set_vote(
            (string)($payload['personId'] ?? ''),
            (string)($payload['groupId'] ?? ''),
            (string)($payload['vote'] ?? '')
        );
        echo json_encode(['ok' => true, 'state' => governance_load_bundle()]);
        exit;
    }

    if ($action === 'clear_my_votes') {
        $groupId = isset($payload['groupId']) ? (string)$payload['groupId'] : null;
        governance_clear_my_votes($groupId !== '' ? $groupId : null);
        echo json_encode(['ok' => true, 'state' => governance_load_bundle()]);
        exit;
    }

    if ($action === 'place_person') {
        $placed = governance_place_person(
            (string)($payload['personId'] ?? ''),
            (string)($payload['toGroupId'] ?? ''),
            (string)($payload['mode'] ?? 'add'),
            $user,
            isset($payload['fromGroupId']) ? (string)$payload['fromGroupId'] : null
        );
        echo json_encode(['ok' => true, 'placed' => $placed, 'state' => governance_load_bundle()]);
        exit;
    }

    if ($action === 'create_group') {
        $created = governance_create_group(
            (string)($payload['label'] ?? ''),
            (string)($payload['blurb'] ?? ''),
            $user
        );
        echo json_encode(['ok' => true, 'created' => $created, 'state' => governance_load_bundle()]);
        exit;
    }

    if ($action === 'delete_group') {
        governance_delete_group((string)($payload['groupId'] ?? ''));
        echo json_encode(['ok' => true, 'state' => governance_load_bundle()]);
        exit;
    }

    if ($action === 'add_person') {
        $added = governance_add_person(
            (string)($payload['name'] ?? ''),
            (string)($payload['role'] ?? ''),
            (string)($payload['groupId'] ?? ''),
            $user
        );
        echo json_encode([
            'ok' => true,
            'added' => $added,
            'state' => governance_load_bundle(),
        ]);
        exit;
    }

    // Default: membership / order save — does not overwrite others' votes
    if (!governance_can_shape()) {
        http_response_code(403);
        echo json_encode(['error' => 'Sign in to change membership lists']);
        exit;
    }
    governance_save_state($payload, $user);
    echo json_encode(['ok' => true, 'state' => governance_load_bundle()]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(403);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error saving membership']);
}
