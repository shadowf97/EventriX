<?php
// Shared page header

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($base_path)) {
    $base_path = '';
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$site_name = "EventriX";
$site_tagline = "Every Event. One Experience.";
$current_title = (!empty($page_title) && $page_title !== $site_name) ? $page_title . " - " . $site_name : $site_name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($current_title); ?></title>
    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>assets/images/eventrix-logo.png">
    <!-- Open-source fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://use.hugeicons.com/font/icons.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/site.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip directly to main content</a>
    <?php if (empty($is_landing_page)): ?>
        <header>
            <h1>
                <a href="<?php echo $base_path; ?>index.html">
                    <img class="site-logo" src="<?php echo $base_path; ?>assets/images/eventrix-logo.png" alt="">
                    <?php echo $site_name; ?>
                </a>
            </h1>
        </header>
    <?php endif; ?>

    <?php require __DIR__ . '/navigation.php'; ?>

    <?php display_flash_message(); ?>

    <main id="main-content">
