<?php require_once __DIR__ . '/icons.php'; ?>
<?php
// Determine active page
$currentPage = basename($_SERVER['PHP_SELF']);

$navItems = [
    'index.php'            => 'Home',
    'presentation.php'     => 'Presentation',
    'history.php'          => 'ERC Digital History',
    'reports.php'          => 'Reports',
    'resources.php'        => 'Resources',
    'sector.php'           => 'Sector Comparisons',
    'best-practice.php'    => 'Best Practice',
    'scotland-policy.php'  => 'Scotland\'s Policy',
    'digital-direction.php'=> 'Digital Direction',
];

// Page title mapping
$pageTitles = [
    'index.php'            => 'Home — Dashboard',
    'presentation.php'     => 'Interview Presentation',
    'history.php'          => 'ERC Digital Transformation History',
    'reports.php'          => 'Key Reports & Analysis',
    'resources.php'        => 'Research Resources',
    'sector.php'           => 'Sector Comparisons',
    'best-practice.php'    => 'Best Practice Guidance',
    'scotland-policy.php'  => 'Scotland\'s Policy Context',
    'digital-direction.php'=> 'Future Digital Direction',
];

$pageTitle = isset($pageTitles[$currentPage]) ? $pageTitles[$currentPage] : 'Research Portal';
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($pageTitle); ?> | ERC Housing Digital Transformation</title>
    <?php
    // Calculate relative path depth to assets folder
    // Pages in website root: PHP_SELF = /website/index.php → depth 1 below docroot, assets/ is sibling
    $scriptPath = ltrim($_SERVER['PHP_SELF'], '/');
    $depth = substr_count($scriptPath, '/');
    $assetBase = $depth > 1 ? str_repeat('../', $depth - 1) : '';
    ?>
    <link rel="stylesheet" href="<?php echo $assetBase; ?>assets/css/style.css">
</head>
<body>

<!-- Top Bar -->
<div class="site-topbar">
    <div class="topbar-left">
        <img src="<?php echo $assetBase; ?>assets/media/leaves.png" alt="East Renfrewshire Council" class="topbar-leaves" width="28" height="20">
        <span>East Renfrewshire Council &mdash; Housing Digital Transformation</span>
    </div>
    <div id="current-date" style="color: rgba(255,255,255,0.6); font-size: 0.72rem;"></div>
</div>

<!-- Site Header -->
<header class="site-header">
    <div class="header-inner">
        <a href="index.php" class="site-logo" aria-label="ERC Interview Research Portal — Home">
            <span class="logo-name">East Renfrewshire <span>Council</span></span>
            <span class="logo-subtitle">Housing Digital Transformation &mdash; Interview Research Portal</span>
        </a>

        <!-- Desktop Navigation -->
        <nav class="site-nav" aria-label="Main navigation">
            <?php foreach ($navItems as $file => $label): ?>
                <a href="<?php echo $file; ?>"
                   <?php echo ($currentPage === $file) ? 'class="active" aria-current="page"' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Hamburger (mobile) -->
        <button class="hamburger" id="hamburger" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-nav">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <!-- Mobile Navigation -->
    <nav class="mobile-nav" id="mobile-nav" aria-label="Mobile navigation">
        <?php foreach ($navItems as $file => $label): ?>
            <a href="<?php echo $file; ?>"
               <?php echo ($currentPage === $file) ? 'class="active" aria-current="page"' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</header>
