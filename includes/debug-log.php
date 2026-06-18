<?php
declare(strict_types=1);

/** @param array<string,mixed> $data */
function auth_debug_log(string $location, string $message, array $data = [], string $hypothesisId = ''): void
{
    // #region agent log
    $entry = json_encode([
        'sessionId' => '1fd31c',
        'timestamp' => (int)(microtime(true) * 1000),
        'location' => $location,
        'message' => $message,
        'data' => $data,
        'hypothesisId' => $hypothesisId,
    ], JSON_UNESCAPED_SLASHES);
    if ($entry === false) {
        return;
    }
    $logFile = dirname(__DIR__) . '/.cursor/debug-1fd31c.log';
    @file_put_contents($logFile, $entry . "\n", FILE_APPEND | LOCK_EX);
    @file_put_contents(dirname(__DIR__) . '/auth-login-debug.log', $entry . "\n", FILE_APPEND | LOCK_EX);
    // #endregion
}
