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
        $historyId = isset($_GET['historyId']) ? (int)$_GET['historyId'] : 0;
        if ($historyId > 0) {
            $row = governance_get_history($historyId);
            if ($row === null) {
                http_response_code(404);
                echo json_encode(['error' => 'History entry not found']);
                exit;
            }
            echo json_encode($row);
            exit;
        }
        if (isset($_GET['history'])) {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $filter = isset($_GET['filter']) ? (string)$_GET['filter'] : 'all';
            $page = governance_list_history($limit, $offset, $filter);
            echo json_encode([
                'history' => $page['items'],
                'total' => $page['total'],
                'limit' => $page['limit'],
                'offset' => $page['offset'],
                'filter' => $page['filter'],
                'hasMore' => ($page['offset'] + count($page['items'])) < $page['total'],
            ]);
            exit;
        }
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
    $action = trim((string)($payload['action'] ?? ''));

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

    if ($action === 'propose') {
        $proposed = governance_propose(
            (string)($payload['personId'] ?? ''),
            (string)($payload['groupId'] ?? ''),
            (string)($payload['proposalAction'] ?? $payload['mode'] ?? ''),
            $user,
            isset($payload['fromGroupId']) ? (string)$payload['fromGroupId'] : null
        );
        echo json_encode(['ok' => true, 'proposed' => $proposed, 'state' => governance_load_bundle()]);
        exit;
    }

    if ($action === 'decide_proposal') {
        $decided = governance_decide_proposal(
            (int)($payload['proposalId'] ?? 0),
            (string)($payload['decision'] ?? ''),
            $user
        );
        echo json_encode(['ok' => true, 'decided' => $decided, 'state' => governance_load_bundle()]);
        exit;
    }

    if ($action === 'withdraw_proposal') {
        governance_withdraw_proposal((int)($payload['proposalId'] ?? 0), $user);
        echo json_encode(['ok' => true, 'state' => governance_load_bundle()]);
        exit;
    }

    if ($action === 'remove_person') {
        governance_remove_person_from_group(
            (string)($payload['personId'] ?? ''),
            (string)($payload['groupId'] ?? ''),
            $user
        );
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
        governance_delete_group((string)($payload['groupId'] ?? ''), $user);
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

    if ($action === 'set_group_status') {
        $updated = governance_set_group_status(
            (string)($payload['groupId'] ?? ''),
            (string)($payload['status'] ?? ''),
            $user
        );
        echo json_encode([
            'ok' => true,
            'updated' => $updated,
            'state' => governance_load_bundle(),
        ]);
        exit;
    }

    if ($action === 'capture_checkpoint') {
        if (!governance_can_shape()) {
            http_response_code(403);
            echo json_encode(['error' => 'Only a membership confirmer can save a checkpoint']);
            exit;
        }
        $captured = governance_capture_history('checkpoint', $user, true);
        $page = governance_list_history(25, 0, 'all');
        echo json_encode([
            'ok' => true,
            'captured' => $captured,
            'history' => $page['items'],
            'total' => $page['total'],
            'limit' => $page['limit'],
            'offset' => $page['offset'],
            'filter' => $page['filter'],
            'hasMore' => count($page['items']) < $page['total'],
        ]);
        exit;
    }

    if ($action === 'restore_history') {
        $force = !empty($payload['force']);
        $result = governance_restore_from_history(
            (int)($payload['historyId'] ?? 0),
            $user,
            $force
        );
        echo json_encode([
            'ok' => true,
            'restored' => $result,
            'state' => governance_load_bundle(),
        ]);
        exit;
    }

    if ($action === 'reseed_unassigned') {
        $result = governance_reseed_unassigned_from_seed($user);
        echo json_encode([
            'ok' => true,
            'reseeded' => $result,
            'state' => governance_load_bundle(),
        ]);
        exit;
    }

    if ($action === 'save') {
        if (!governance_can_shape()) {
            http_response_code(403);
            echo json_encode(['error' => 'Only a membership confirmer can change membership lists']);
            exit;
        }
        governance_save_state($payload, $user);
        echo json_encode(['ok' => true, 'state' => governance_load_bundle()]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(403);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
