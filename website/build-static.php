<?php
/**
 * Build static HTML from PHP for GitHub Pages.
 * Run: php build-static.php
 * Output: ../index.html, ../presentation.html, etc. + assets/
 */
$pages = [
    'index.php' => 'index.html',
    'presentation.php' => 'presentation.html',
    'history.php' => 'history.html',
    'reports.php' => 'reports.html',
    'resources.php' => 'resources.html',
    'sector.php' => 'sector.html',
    'best-practice.php' => 'best-practice.html',
    'scotland-policy.php' => 'scotland-policy.html',
    'digital-direction.php' => 'digital-direction.html',
];

$outputDir = dirname(__DIR__);

foreach ($pages as $phpFile => $htmlFile) {
    $_SERVER['PHP_SELF'] = '/' . $phpFile;
    $_SERVER['REQUEST_URI'] = '/' . $phpFile;
    ob_start();
    require __DIR__ . '/' . $phpFile;
    $html = ob_get_clean();
    // Replace .php links with .html
    $html = preg_replace('/href="([^"]*)\.php([^"]*)"/', 'href="$1.html$2"', $html);
    $html = preg_replace('/href="([^"]*)\.php"/', 'href="$1.html"', $html);
    // Fix asset paths: assets/ stays as assets/ (same level)
    $html = preg_replace('#href="(\.\./)*assets/#', 'href="assets/', $html);
    $html = preg_replace('#src="(\.\./)*assets/#', 'src="assets/', $html);
    $html = preg_replace('#href="assets/#', 'href="assets/', $html);
    file_put_contents($outputDir . '/' . $htmlFile, $html);
    echo "Generated $htmlFile\n";
}

// Copy assets
$assetsSrc = __DIR__ . '/assets';
$assetsDst = $outputDir . '/assets';
if (!is_dir($assetsDst)) {
    mkdir($assetsDst, 0755, true);
}
// Copy media (video, images, PDF, PPTX) from website/assets/media or project root
$mediaDst = $assetsDst . '/media';
if (!is_dir($mediaDst)) {
    mkdir($mediaDst, 0755, true);
}
$mediaSrc = $assetsSrc . '/media';
$rootMediaFiles = ['videoplayback.mp4', 'InfoGraphic.png', 'New infographis.png', 'ERC_Digital_Strategy.pptx', 'East-Ren-Digital-Housing-Transformation-William-Ellis-CV.pdf', 'mind-map.png', 'mind-map.pdf', 'leaves.png'];
foreach ($rootMediaFiles as $f) {
    $fromRoot = $outputDir . '/' . $f;
    $fromMedia = $mediaSrc . '/' . $f;
    if (file_exists($fromRoot)) {
        copy($fromRoot, $mediaDst . '/' . $f);
        echo "Copied media: $f (from root)\n";
    } elseif (file_exists($fromMedia)) {
        copy($fromMedia, $mediaDst . '/' . $f);
        echo "Copied media: $f\n";
    }
}
// Also copy any other files in website/assets/media
if (is_dir($mediaSrc)) {
    foreach (scandir($mediaSrc) as $f) {
        if ($f !== '.' && $f !== '..' && is_file($mediaSrc . '/' . $f)) {
            if (!file_exists($mediaDst . '/' . $f)) {
                copy($mediaSrc . '/' . $f, $mediaDst . '/' . $f);
                echo "Copied media: $f\n";
            }
        }
    }
}
foreach (['css', 'js'] as $sub) {
    $src = $assetsSrc . '/' . $sub;
    $dst = $assetsDst . '/' . $sub;
    if (is_dir($src)) {
        if (!is_dir($dst)) mkdir($dst, 0755, true);
        foreach (scandir($src) as $f) {
            if ($f !== '.' && $f !== '..') {
                copy($src . '/' . $f, $dst . '/' . $f);
                echo "Copied assets/$sub/$f\n";
            }
        }
    }
}
echo "Done. Static site ready in project root for GitHub Pages.\n";
