<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/entra_config.php';

/** @return array{viewer: int, editor: int, admin: int} */
function role_rank_map(): array
{
    return ['viewer' => 1, 'editor' => 2, 'admin' => 3];
}

function current_app_role(): string
{
    $role = $_SESSION['app_role'] ?? 'viewer';
    return in_array($role, ['viewer', 'editor', 'admin'], true) ? $role : 'viewer';
}

/** Highest internal role from Entra app role claims. */
function map_entra_roles_to_app_role(array $entraRoles): ?string
{
    $map   = entra_role_map();
    $ranks = role_rank_map();
    $best  = null;
    $bestRank = 0;
    foreach ($entraRoles as $name) {
        if (!isset($map[$name])) {
            continue;
        }
        $internal = $map[$name];
        $rank     = $ranks[$internal] ?? 0;
        if ($rank > $bestRank) {
            $bestRank = $rank;
            $best     = $internal;
        }
    }
    return $best;
}
