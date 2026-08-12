<?php
http_response_code(404);
require_once 'config/database.php';

$cat_result     = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categories_arr = [];
while ($r = mysqli_fetch_assoc($cat_result)) $categories_arr[] = $r;

$raw = mysqli_query($conn, "SELECT `key`, value FROM school_profile");
$sp  = [];
while ($r = mysqli_fetch_assoc($raw)) $sp[$r['key']] = $r['value'];
$p = fn(string $k, string $d = '') => htmlspecialchars($sp[$k] ?? $d);

// Artikel terbaru untuk saran
$latest = mysqli_query($conn,
    "SELECT id, title, image, created_at FROM posts
     WHERE status='published' ORDER BY created_at DESC LIMIT 3");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php
    $page_site_name = $sp['school_name'] ?? 'Portal Berita Sekolah';
    $page_title     = '404 — Halaman Tidak Ditemukan';
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
            <nav class="site-nav-top">
                <a href="admin/login.php" class="nav-admin">&#9881; Admin</a>
                <button id="darkToggle" title="Mode Gelap">&#127769;</button>
            </nav>
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
                <a href="guru.php">&#128218; Guru</a>
            </nav>
        </div>
    </div>
</header>

<div class="container main-content">
    <div class="not-found-box fade-up">
        <!-- Animasi 404 -->
        <div class="not-found-graphic">
            <span class="nf-four">4</span>
            <span class="nf-zero">&#128579;</span>
            <span class="nf-four">4</span>
        </div>
        <h2>Halaman Tidak Ditemukan</h2>
        <p>Maaf, halaman yang kamu cari tidak ada atau sudah dipindahkan.</p>

        <!-- Search -->
        <form class="nf-search" method="GET" action="index.php" style="margin:1.5rem 0">
            <input type="search" name="q" placeholder="Cari artikel…" aria-label="Cari">
            <button type="submit">&#128269; Cari</button>
        </form>

        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
            <a href="index.php" class="btn btn-primary">&#127968; Beranda</a>
            <a href="javascript:history.back()" class="btn btn-secondary">&larr; Halaman Sebelumnya</a>
        </div>
    </div>

    <!-- Artikel Terbaru -->
    <?php if (mysqli_num_rows($latest) > 0): ?>
    <div style="max-width:800px;margin:3rem auto 0">
        <h3 style="text-align:center;margin-bottom:1.5rem;color:var(--mid);font-size:1rem;text-transform:uppercase;letter-spacing:.08em">
            Mungkin kamu tertarik dengan ini
        </h3>
        <div class="posts-grid" style="grid-template-columns:repeat(3,1fr)">
            <?php while ($lp = mysqli_fetch_assoc($latest)): ?>
            <article class="post-card fade-up">
                <a href="post.php?id=<?= $lp['id'] ?>" class="post-card-link">
                    <div class="post-thumb">
                        <?php if ($lp['image']): ?>
                            <img src="assets/uploads/<?= htmlspecialchars($lp['image']) ?>"
                                 alt="<?= htmlspecialchars($lp['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="post-thumb-placeholder">&#128247;</div>
                        <?php endif; ?>
                    </div>
                    <div class="post-body">
                        <h3 class="post-title"><?= htmlspecialchars($lp['title']) ?></h3>
                        <div class="post-meta">
                            <span>&#128197; <?= date('d M Y', strtotime($lp['created_at'])) ?></span>
                        </div>
                    </div>
                </a>
            </article>
            <?php endwhile; ?>
        </div>
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
