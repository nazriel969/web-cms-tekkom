<?php
require_once __DIR__ . '/../config/helpers.php';
$config  = getSiteConfig();
$navMenu = $config['nav_menu'] ?? [];

// Deteksi halaman aktif
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $config['site_name']) ?> | <?= htmlspecialchars($config['site_name']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($config['site_tagline']) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary:  { DEFAULT: '#1d4ed8', 50:'#eff6ff', 100:'#dbeafe', 500:'#3b82f6', 600:'#2563eb', 700:'#1d4ed8', 800:'#1e40af', 900:'#1e3a8a' },
                        secondary:{ DEFAULT: '#f59e0b', 500:'#f59e0b', 600:'#d97706' }
                    },
                    fontFamily: { sans: ['Inter','system-ui','sans-serif'] }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .nav-link.active { color: #1d4ed8; border-bottom: 2px solid #1d4ed8; }
        .dropdown:hover .dropdown-menu { display: block; }
        .prose img { border-radius: 0.5rem; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

<!-- Top Bar -->
<div class="bg-primary-800 text-white text-xs py-2 hidden md:block">
    <div class="container mx-auto px-4 flex justify-between items-center">
        <span><i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($config['footer']['address'] ?? '') ?></span>
        <div class="flex gap-4">
            <?php foreach ($config['social_media'] as $s): ?>
            <a href="<?= htmlspecialchars($s['url']) ?>" target="_blank" rel="noopener"
               class="hover:text-yellow-300 transition-colors">
                <i class="fab fa-<?= htmlspecialchars($s['icon']) ?>"></i>
                <span class="ml-1"><?= htmlspecialchars($s['platform']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="bg-white shadow-sm sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <a href="/cms-sekolah/index.php" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-primary-700 rounded-full flex items-center justify-center">
                    <i class="fas fa-school text-white text-lg"></i>
                </div>
                <div>
                    <div class="font-bold text-primary-800 text-sm leading-tight"><?= htmlspecialchars($config['site_name']) ?></div>
                    <div class="text-gray-500 text-xs"><?= htmlspecialchars($config['site_tagline']) ?></div>
                </div>
            </a>

            <!-- Desktop Nav -->
            <nav class="hidden md:flex items-center gap-1">
                <?php foreach ($navMenu as $item): 
                    $isActive = ($currentPage === $item['url'] || 
                                 basename($item['url']) === $currentPage) ? 'active' : '';
                ?>
                <a href="/cms-sekolah/<?= htmlspecialchars($item['url']) ?>"
                   class="nav-link px-4 py-5 text-sm font-medium text-gray-700 hover:text-primary-700 hover:bg-primary-50 transition-all <?= $isActive ?>">
                    <?= htmlspecialchars($item['label']) ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <!-- Mobile Menu Button -->
            <button id="menuBtn" class="md:hidden text-gray-700 p-2 rounded-lg hover:bg-gray-100">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Nav -->
    <div id="mobileMenu" class="hidden md:hidden bg-white border-t border-gray-100 shadow-lg">
        <?php foreach ($navMenu as $item): 
            $isActive = (basename($item['url']) === $currentPage) ? 'bg-primary-50 text-primary-700' : 'text-gray-700';
        ?>
        <a href="/cms-sekolah/<?= htmlspecialchars($item['url']) ?>"
           class="block px-5 py-3 text-sm font-medium <?= $isActive ?> hover:bg-primary-50 hover:text-primary-700 border-b border-gray-50 transition-colors">
            <?= htmlspecialchars($item['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</header>

<script>
    document.getElementById('menuBtn').addEventListener('click', function() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('hidden');
    });
</script>

<!-- Page Content Start -->
<main>
