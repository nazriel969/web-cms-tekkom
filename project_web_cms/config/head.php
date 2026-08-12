<?php
/**
 * Shared <head> meta tags — include di setiap halaman publik
 * Usage: require_once 'config/head.php'; (atau ../config/head.php dari subfolder)
 *
 * Variabel yang bisa di-set sebelum include:
 * $page_title       — judul halaman
 * $page_desc        — deskripsi halaman
 * $page_image       — URL gambar OG (absolut)
 * $page_url         — URL halaman (absolut)
 */
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST'];

// Tentukan root path relatif (berbeda untuk subfolder)
$depth      = substr_count($_SERVER['PHP_SELF'], '/') - 2;
$root_path  = str_repeat('../', max(0, $depth));

$site_name  = $page_site_name ?? 'Portal Berita Sekolah';
$title      = isset($page_title) ? $page_title . ' — ' . $site_name : $site_name;
$desc       = $page_desc  ?? 'Portal informasi terkini dan terpercaya dari ' . $site_name;
$img        = $page_image ?? $base_url . '/' . ltrim($root_path, '/') . 'assets/logo.jpg';
$url        = $page_url   ?? $base_url . $_SERVER['REQUEST_URI'];
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($desc) ?>">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= $root_path ?>assets/logo.jpg">
    <link rel="shortcut icon" href="<?= $root_path ?>assets/logo.jpg">
    <link rel="apple-touch-icon" href="<?= $root_path ?>assets/logo.jpg">

    <!-- Open Graph (WhatsApp, Facebook, dll) -->
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="<?= htmlspecialchars($site_name) ?>">
    <meta property="og:title"       content="<?= htmlspecialchars($title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($desc) ?>">
    <meta property="og:image"       content="<?= htmlspecialchars($img) ?>">
    <meta property="og:url"         content="<?= htmlspecialchars($url) ?>">
    <meta property="og:image:width"  content="600">
    <meta property="og:image:height" content="600">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= htmlspecialchars($title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($desc) ?>">
    <meta name="twitter:image"       content="<?= htmlspecialchars($img) ?>">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= htmlspecialchars($url) ?>">
    <!-- Sitemap -->
    <link rel="sitemap" type="application/xml" href="<?= $root_path ?>sitemap.php">
