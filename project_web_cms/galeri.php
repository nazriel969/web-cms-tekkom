<?php
require_once 'config/database.php';

// Kategori navigasi
$cat_result     = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categories_arr = [];
while ($r = mysqli_fetch_assoc($cat_result)) $categories_arr[] = $r;

// Profil sekolah
$raw = mysqli_query($conn, "SELECT `key`, value FROM school_profile");
$sp  = [];
while ($r = mysqli_fetch_assoc($raw)) $sp[$r['key']] = $r['value'];
$p = fn(string $k, string $d = '') => htmlspecialchars($sp[$k] ?? $d);

// Filter album
$album  = trim($_GET['album'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Daftar album
$albums_result = mysqli_query($conn,
    "SELECT album, COUNT(*) AS total FROM gallery GROUP BY album ORDER BY album ASC");
$albums = [];
while ($r = mysqli_fetch_assoc($albums_result)) $albums[] = $r;

// Query foto
if ($album !== '') {
    $stmt = mysqli_prepare($conn,
        "SELECT g.*, u.username FROM gallery g
         LEFT JOIN users u ON g.created_by = u.id
         WHERE g.album = ? ORDER BY g.created_at DESC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, 'sii', $album, $per_page, $offset);
    mysqli_stmt_execute($stmt);
    $photos = mysqli_stmt_get_result($stmt);

    $cnt_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS t FROM gallery WHERE album = ?");
    mysqli_stmt_bind_param($cnt_stmt, 's', $album);
    mysqli_stmt_execute($cnt_stmt);
    $total = mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_stmt))['t'];
} else {
    $stmt = mysqli_prepare($conn,
        "SELECT g.*, u.username FROM gallery g
         LEFT JOIN users u ON g.created_by = u.id
         ORDER BY g.created_at DESC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, 'ii', $per_page, $offset);
    mysqli_stmt_execute($stmt);
    $photos = mysqli_stmt_get_result($stmt);

    $total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM gallery"))['t'];
}
$total_pages = ceil($total / $per_page);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php
    $page_site_name = $sp['school_name'] ?? 'Portal Berita Sekolah';
    $page_title     = 'Galeri Foto';
    $page_desc      = 'Kumpulan dokumentasi kegiatan dan momen berharga ' . ($sp['school_name'] ?? '');
    require_once 'config/head.php';
    ?>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body class="public-page">

<!-- Header -->
<header class="site-header">
    <div class="site-header-top">
        <div class="container">
            <div class="site-brand">
                <img src="assets/logo.jpg" alt="Logo <?= $p('school_name') ?>" class="site-logo">
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
                <a href="galeri.php" class="active">&#128247; Galeri</a>
                <a href="guru.php">&#128218; Guru</a>
            </nav>
        </div>
    </div>
</header>

<!-- Hero -->
<section class="hero">
    <div class="container hero-content">
        <div class="hero-text">
            <span class="hero-badge">&#128247; Dokumentasi Kegiatan</span>
            <h2>Galeri Foto</h2>
            <p>Kumpulan dokumentasi kegiatan, prestasi, dan momen berharga di <?= $p('school_name', 'sekolah kami') ?>.</p>
        </div>
    </div>
</section>

<div class="container main-content">

    <!-- Filter Album -->
    <div class="gallery-filter">
        <a href="galeri.php" class="gallery-filter-btn <?= $album === '' ? 'active' : '' ?>">
            &#128248; Semua <span>(<?= $total ?>)</span>
        </a>
        <?php foreach ($albums as $alb): ?>
        <a href="galeri.php?album=<?= urlencode($alb['album']) ?>"
           class="gallery-filter-btn <?= $album === $alb['album'] ? 'active' : '' ?>">
            <?= htmlspecialchars($alb['album']) ?> <span>(<?= $alb['total'] ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($total === 0): ?>
        <div class="empty-state">
            <span>&#128247;</span>
            <p>Belum ada foto di galeri<?= $album ? ' album "' . htmlspecialchars($album) . '"' : '' ?>.</p>
        </div>
    <?php else: ?>

    <!-- Grid Foto -->
    <div class="gallery-grid" id="galleryGrid">
        <?php while ($photo = mysqli_fetch_assoc($photos)): ?>
        <div class="gallery-item fade-up"
             data-img="<?= htmlspecialchars($photo['image']) ?>"
             data-title="<?= htmlspecialchars($photo['title']) ?>"
             data-desc="<?= htmlspecialchars($photo['description'] ?? '') ?>">
            <div class="gallery-thumb">
                <img src="assets/uploads/<?= htmlspecialchars($photo['image']) ?>"
                     alt="<?= htmlspecialchars($photo['title']) ?>" loading="lazy">
                <div class="gallery-overlay">
                    <span class="gallery-zoom">&#128269;</span>
                </div>
            </div>
            <div class="gallery-caption">
                <strong><?= htmlspecialchars($photo['title']) ?></strong>
                <span>&#128193; <?= htmlspecialchars($photo['album']) ?></span>
                <span>&#128197; <?= date('d M Y', strtotime($photo['created_at'])) ?></span>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Paginasi -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top:2rem">
        <?php if ($page > 1): ?>
            <a href="?<?= $album ? 'album='.urlencode($album).'&' : '' ?>page=<?= $page-1 ?>" class="page-link">&laquo;</a>
        <?php endif; ?>
        <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
            <a href="?<?= $album ? 'album='.urlencode($album).'&' : '' ?>page=<?= $i ?>"
               class="page-link <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?<?= $album ? 'album='.urlencode($album).'&' : '' ?>page=<?= $page+1 ?>" class="page-link">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Lightbox -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()" style="display:none">
    <div class="lightbox-box" onclick="event.stopPropagation()">
        <button class="lightbox-close" onclick="closeLightbox()">&#10005;</button>
        <button class="lightbox-nav lightbox-prev" id="lightboxPrev" onclick="lightboxPrev()">&#10094;</button>
        <button class="lightbox-nav lightbox-next" id="lightboxNext" onclick="lightboxNext()">&#10095;</button>
        <img id="lightboxImg" src="" alt="">
        <div class="lightbox-info">
            <strong id="lightboxTitle"></strong>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.3rem">
                <p id="lightboxDesc"></p>
                <span id="lightboxCounter" class="lightbox-counter"></span>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="site-footer">
    <div class="footer-top">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <img src="assets/logo.jpg" alt="Logo" style="width:44px;height:44px;object-fit:contain;border-radius:6px;margin-bottom:.5rem">
                    <strong><?= $p('school_name', 'Portal Berita Sekolah') ?></strong>
                    <p><?= $p('school_vision', 'Informasi terkini dan terpercaya.') ?></p>
                </div>
                <div>
                    <h4>Navigasi</h4>
                    <ul>
                        <li><a href="index.php">&#127968; Beranda</a></li>
                        <li><a href="profil.php">&#127979; Profil Sekolah</a></li>
                        <li><a href="galeri.php">&#128247; Galeri</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Kategori</h4>
                    <ul>
                        <?php
                        $cf = mysqli_query($conn, "SELECT id,name FROM categories ORDER BY name ASC LIMIT 6");
                        while ($c = mysqli_fetch_assoc($cf)):
                        ?>
                        <li><a href="index.php?category=<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <div>
                    <h4>Kontak</h4>
                    <ul class="footer-contact">
                        <?php if (!empty($sp['school_address'])): ?>
                        <li><span>&#128205;</span><?= htmlspecialchars($sp['school_address']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($sp['school_phone'])): ?>
                        <li><span>&#128222;</span><?= htmlspecialchars($sp['school_phone']) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= $p('school_name', 'Portal Berita Sekolah') ?>. All Rights Reserved.</p>
        </div>
    </div>
</footer>

<a href="#" class="back-to-top" id="backToTop">&#8679;</a>
<?php require_once 'config/footer_widgets.php'; ?>
<script src="assets/app.js"></script>
<script>
function openLightbox(img, title, desc) {
    document.getElementById('lightboxImg').src = 'assets/uploads/' + img;
    document.getElementById('lightboxTitle').textContent = title;
    document.getElementById('lightboxDesc').textContent  = desc;
    document.getElementById('lightbox').style.display    = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>