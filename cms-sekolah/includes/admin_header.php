<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();
$config      = getSiteConfig();
$adminName   = $_SESSION['admin_nama'] ?? 'Admin';
$adminRole   = $_SESSION['admin_role'] ?? 'admin';
$currentFile = basename($_SERVER['PHP_SELF']);

$menuItems = [
    ['href' => 'dashboard.php',      'icon' => 'fa-gauge',        'label' => 'Dashboard'],
    ['href' => 'berita.php',         'icon' => 'fa-newspaper',    'label' => 'Berita'],
    ['href' => 'pengumuman.php',     'icon' => 'fa-bullhorn',     'label' => 'Pengumuman'],
    ['href' => 'galeri.php',         'icon' => 'fa-images',       'label' => 'Galeri'],
    ['href' => 'profil.php',         'icon' => 'fa-school',       'label' => 'Profil Sekolah'],
    ['href' => 'pesan.php',          'icon' => 'fa-envelope',     'label' => 'Pesan Masuk'],
    ['href' => 'ganti_password.php', 'icon' => 'fa-lock',         'label' => 'Ganti Password'],
];

// Hitung pesan belum dibaca
try {
    require_once __DIR__ . '/../config/database.php';
    $pdo      = getDB();
    $unread   = (int)$pdo->query("SELECT COUNT(*) FROM pesan_kontak WHERE dibaca = 0")->fetchColumn();
} catch (Exception $e) { $unread = 0; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin Panel') ?> | <?= htmlspecialchars($config['site_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT:'#1d4ed8',50:'#eff6ff',100:'#dbeafe',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a' }
                    },
                    fontFamily: { sans: ['Inter','system-ui','sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        #sidebar { transition: transform 0.3s ease; }
        @media (max-width: 768px) {
            #sidebar { position: fixed; top:0; left:0; height:100%; z-index:50; transform:translateX(-100%); }
            #sidebar.open { transform:translateX(0); }
        }
        .menu-active { background: rgba(255,255,255,0.15); border-left: 3px solid #fff; }
        .menu-item:hover { background: rgba(255,255,255,0.1); }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

<!-- Sidebar Overlay (mobile) -->
<div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-40 md:hidden" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside id="sidebar" class="w-64 bg-primary-800 text-white flex flex-col min-h-screen md:fixed md:top-0 md:left-0">
    <!-- Brand -->
    <div class="flex items-center gap-3 px-5 py-5 border-b border-primary-700">
        <div class="w-9 h-9 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
            <i class="fas fa-school text-sm"></i>
        </div>
        <div class="overflow-hidden">
            <div class="font-bold text-sm truncate"><?= htmlspecialchars($config['site_name']) ?></div>
            <div class="text-primary-300 text-xs">Panel Admin</div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 py-4 overflow-y-auto">
        <?php foreach ($menuItems as $item):
            $isActive = ($currentFile === $item['href']) ? 'menu-active' : '';
            $badgeHtml = ($item['href'] === 'pesan.php' && $unread > 0)
                ? "<span class='ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full'>$unread</span>"
                : '';
        ?>
        <a href="/cms-sekolah/admin/<?= htmlspecialchars($item['href']) ?>"
           class="menu-item flex items-center gap-3 px-5 py-3 text-sm font-medium text-white/90 hover:text-white transition-all <?= $isActive ?>">
            <i class="fas <?= $item['icon'] ?> w-4 text-center text-white/70"></i>
            <span><?= $item['label'] ?></span>
            <?= $badgeHtml ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- User & Logout -->
    <div class="border-t border-primary-700 p-4">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-user text-sm"></i>
            </div>
            <div class="overflow-hidden">
                <div class="text-sm font-medium truncate"><?= htmlspecialchars($adminName) ?></div>
                <div class="text-primary-300 text-xs capitalize"><?= htmlspecialchars($adminRole) ?></div>
            </div>
        </div>
        <a href="/cms-sekolah/admin/logout.php"
           class="flex items-center gap-2 text-sm text-white/80 hover:text-white bg-white/10 hover:bg-white/20 rounded-lg px-3 py-2 transition-all">
            <i class="fas fa-right-from-bracket"></i> Keluar
        </a>
    </div>
</aside>

<!-- Main wrapper -->
<div class="md:ml-64 flex flex-col min-h-screen">

    <!-- Top Bar -->
    <header class="bg-white shadow-sm sticky top-0 z-30 h-14 flex items-center px-4 gap-4">
        <!-- Hamburger -->
        <button onclick="toggleSidebar()" class="md:hidden text-gray-600 p-1 rounded hover:bg-gray-100">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <h1 class="text-base font-semibold text-gray-800 flex-1"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
        <a href="/cms-sekolah/index.php" target="_blank"
           class="text-xs text-primary-600 hover:text-primary-800 flex items-center gap-1">
            <i class="fas fa-arrow-up-right-from-square"></i> Lihat Website
        </a>
    </header>

    <!-- Content -->
    <main class="flex-1 p-5 md:p-6">
