<?php
/**
 * Router for PHP built-in server.
 * Run from project root: php -S localhost:8001 router.php
 *
 * /reports.php or /public/reports.php → website/reports.php
 * Static files (assets/, public/) served by PHP
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ltrim($uri, '/');
$phpFile = preg_replace('#^public/#', '', $path);

// Route .php to website/
if (preg_match('#\.php$#', $phpFile)) {
    $file = __DIR__ . '/website/' . $phpFile;
    if (file_exists($file) && is_file($file)) {
        chdir(__DIR__ . '/website');
        $_SERVER['SCRIPT_NAME'] = '/' . $phpFile;
        $_SERVER['PHP_SELF'] = '/' . $phpFile;
        require $file;
        return true;
    }
}

return false;
