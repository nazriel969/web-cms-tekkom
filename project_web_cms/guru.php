<?php
require_once 'config/database.php';

$cat_result     = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categories_arr = [];
while ($r = mysqli_fetch_assoc($cat_result)) $categories_arr[] = $r;

$raw = mysqli_query($conn, "SELECT `key`, value FROM school_profile");
$sp  = [];
while ($r = mysqli_fetch_assoc($raw)) $sp[$r['key']] = $r['value'];
$p = fn(string $k, string $d = '') => htmlspecialchars($sp[$k] ?? $d);

// Filter jabatan
$filter = trim($_GET['filter'] ?? '');
if ($filter !== '') {
    $stmt = mysqli_prepare($conn,
        "SELECT * FROM teachers WHERE position = ? ORDER BY sort_order ASC, name ASC");
    mysqli_stmt_bind_param($stmt, 's', $filter);
    mysqli_stmt_execute($stmt);
    $teachers = mysqli_stmt_get_result($stmt);
} else {
    $teachers = mysqli_query($conn,
        "SELECT * FROM teachers ORDER BY sort_order ASC, name ASC");
}

// Daftar jabatan unik
$positions_res = mysqli_query($conn,
    "SELECT DISTINCT position FROM teachers WHERE position IS NOT NULL AND position != '' ORDER BY position ASC");
$positions = [];
while ($r = mysqli_fetch_assoc($positions_res)) $positions[] = $r['position'];

$total = mysqli_num_rows($teachers);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php
    $page_site_name = $sp['school_name'] ?? 'Portal Berita Sekolah';
    $page_title     = 'Data Guru & Staff';
    $page_desc      = 'Daftar tenaga pengajar dan staff ' . ($sp['school_name'] ?? '');
    require_once 'config/head.php';
    ?>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body class="public-page">

<header class="site-header">
    <div class="site-header-top">
        <div class="container">
            <div class="site-brand">
                <img src="assets/logo.jpg" alt="Logo" class="site-logo">
                <div>
                    <h1><?= $p('school_name', 'Portal Berita Sekolah') ?></h1>
                    <p>Informasi Terkini &amp; Terpercaya</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                <form class="header-search" method="GET" action="index.php">
                    <input type="search" name="q" placeholder="Cari berita…" aria-label="Cari">
                    <button type="submit">&#128269;</button>
                </form>
                <nav class="site-nav-top">
                    <a href="admin/login.php" class="nav-admin">&#9881; Admin</a>
                    <button id="darkToggle" title="Mode Gelap">&#127769;</button>
                </nav>
            </div>
        </div>
    </div>
    <div class="site-header-nav">
        <div class="container">
            <nav class="site-nav">
                <a href="index.php">&#127968; Beranda</a>
                <?php foreach ($categories_arr as $cat): ?>
                    <a href="index.php?category=<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a>
                <?php endforeach; ?>
                <a href="profil.php">&#127979; Profil Sekolah</a>
                <a href="galeri.php">&#128247; Galeri</a>
                <a href="guru.php" class="active">&#128218; Guru</a>
            </nav>
        </div>
    </div>
</header>

<section class="hero">
    <div class="container hero-content">
        <div class="hero-text">
            <span class="hero-badge">&#128218; Tenaga Pendidik</span>
            <h2>Data Guru &amp; Staff</h2>
            <p>Daftar tenaga pengajar dan kependidikan <?= $p('school_name', 'sekolah kami') ?> yang berdedikasi.</p>
        </div>
    </div>
</section>

<div class="container main-content">

    <!-- Stats -->
    <div class="teacher-stats">
        <div class="teacher-stat">
            <span><?= $total ?></span>
            <small>Total Guru &amp; Staff</small>
        </div>
        <div class="teacher-stat">
            <span><?= $p('total_teachers', '–') ?></span>
            <small>Tenaga Pengajar</small>
        </div>
        <div class="teacher-stat">
            <span><?= $p('total_staff', '–') ?></span>
            <small>Tenaga Kependidikan</small>
        </div>
    </div>

    <!-- Filter -->
    <?php if (!empty($positions)): ?>
    <div class="gallery-filter" style="margin-bottom:2rem">
        <a href="guru.php" class="gallery-filter-btn <?= $filter==='' ? 'active' : '' ?>">
            Semua
        </a>
        <?php foreach ($positions as $pos): ?>
        <a href="guru.php?filter=<?= urlencode($pos) ?>"
           class="gallery-filter-btn <?= $filter===$pos ? 'active' : '' ?>">
            <?= htmlspecialchars($pos) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($total === 0): ?>
        <div class="empty-state">
            <span>&#128218;</span>
            <p>Belum ada data guru yang ditambahkan.</p>
            <a href="admin/teachers.php" class="btn btn-primary" style="margin-top:1rem">+ Tambah Data Guru</a>
        </div>
    <?php else: ?>
    <div class="teacher-grid">
        <?php while ($t = mysqli_fetch_assoc($teachers)): ?>
        <div class="teacher-card fade-up">
            <div class="teacher-photo">
                <?php if (!empty($t['photo'])): ?>
                    <img src="assets/uploads/<?= htmlspecialchars($t['photo']) ?>"
                         alt="<?= htmlspecialchars($t['name']) ?>">
                <?php else: ?>
                    <div class="teacher-photo-placeholder">
                        <?= mb_strtoupper(mb_substr($t['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="teacher-info">
                <h3><?= htmlspecialchars($t['name']) ?></h3>
                <?php if (!empty($t['position'])): ?>
                    <span class="teacher-position"><?= htmlspecialchars($t['position']) ?></span>
                <?php endif; ?>
                <?php if (!empty($t['subject'])): ?>
                    <p class="teacher-subject">&#128218; <?= htmlspecialchars($t['subject']) ?></p>
                <?php endif; ?>
                <?php if (!empty($t['education'])): ?>
                    <p class="teacher-edu">&#127891; <?= htmlspecialchars($t['education']) ?></p>
                <?php endif; ?>
                <?php if (!empty($t['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($t['email']) ?>" class="teacher-email">
                        &#128140; <?= htmlspecialchars($t['email']) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

</div>

<footer class="site-footer">
    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= $p('school_name', 'Portal Berita Sekolah') ?>. All Rights Reserved.</p>
        </div>
    </div>
</footer>

<a href="#" class="back-to-top" id="backToTop">&#8679;</a>
<?php require_once 'config/footer_widgets.php'; ?>
<script src="assets/app.js"></script>
</body>
</html>
