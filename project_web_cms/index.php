<?php
require_once 'config/database.php';

$cat_id   = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search   = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 6;
$offset   = ($page - 1) * $per_page;

// Kategori navigasi
$cat_result     = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categories_arr = [];
while ($r = mysqli_fetch_assoc($cat_result)) $categories_arr[] = $r;

// Profil sekolah
$raw = mysqli_query($conn, "SELECT `key`, value FROM school_profile");
$sp  = [];
while ($r = mysqli_fetch_assoc($raw)) $sp[$r['key']] = $r['value'];
$p = fn(string $k, string $d = '') => htmlspecialchars($sp[$k] ?? $d);

// Meta tags
$page_site_name = $sp['school_name'] ?? 'Portal Berita Sekolah';
$page_title     = $cat_id > 0 ? ($active_cat_name ?? '') : null;
$page_desc      = $sp['school_vision'] ?? null;

// Artikel featured
$feat_stmt = mysqli_prepare($conn,
    "SELECT p.id, p.title, p.content, p.excerpt, p.image, p.created_at, p.views,
            c.name AS category_name, u.username
     FROM posts p
     LEFT JOIN categories c ON p.category_id = c.id
     LEFT JOIN users u ON p.user_id = u.id
     WHERE p.status = 'published' AND p.is_featured = 1 LIMIT 1");
mysqli_stmt_execute($feat_stmt);
$featured = mysqli_fetch_assoc(mysqli_stmt_get_result($feat_stmt));

// Artikel terbaru sidebar
$latest_stmt = mysqli_prepare($conn,
    "SELECT id, title, created_at, image FROM posts
     WHERE status='published' ORDER BY created_at DESC LIMIT 5");
mysqli_stmt_execute($latest_stmt);
$latest_posts = mysqli_stmt_get_result($latest_stmt);

// Artikel terpopuler sidebar
$popular_stmt = mysqli_prepare($conn,
    "SELECT id, title, views FROM posts
     WHERE status='published' ORDER BY views DESC LIMIT 5");
mysqli_stmt_execute($popular_stmt);
$popular_posts = mysqli_stmt_get_result($popular_stmt);

// WHERE clause
$where_parts = ["p.status = 'published'"];
$bind_types  = '';
$bind_vals   = [];
if ($cat_id > 0) {
    $where_parts[] = "p.category_id = ?";
    $bind_types   .= 'i';
    $bind_vals[]   = $cat_id;
}
if ($search !== '') {
    $where_parts[] = "(p.title LIKE ? OR p.content LIKE ?)";
    $bind_types   .= 'ss';
    $like          = "%$search%";
    $bind_vals[]   = $like;
    $bind_vals[]   = $like;
}
$where = 'WHERE ' . implode(' AND ', $where_parts);

// Hitung total
$count_stmt = mysqli_prepare($conn,
    "SELECT COUNT(*) AS total FROM posts p LEFT JOIN categories c ON p.category_id=c.id $where");
if ($bind_types) mysqli_stmt_bind_param($count_stmt, $bind_types, ...$bind_vals);
mysqli_stmt_execute($count_stmt);
$total_posts = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = ceil($total_posts / $per_page);

// Query artikel
$stmt = mysqli_prepare($conn,
    "SELECT p.id, p.title, p.content, p.excerpt, p.image, p.created_at, p.views,
            c.name AS category_name, u.username
     FROM posts p
     LEFT JOIN categories c ON p.category_id=c.id
     LEFT JOIN users u ON p.user_id=u.id
     $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
$all_types = $bind_types . 'ii';
$all_vals  = array_merge($bind_vals, [$per_page, $offset]);
mysqli_stmt_bind_param($stmt, $all_types, ...$all_vals);
mysqli_stmt_execute($stmt);
$posts = mysqli_stmt_get_result($stmt);

// Nama kategori aktif
$active_cat_name = $search !== '' ? "Hasil: \"$search\"" : 'Berita Terkini';
if ($cat_id > 0) {
    $cs = mysqli_prepare($conn, "SELECT name FROM categories WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($cs, 'i', $cat_id);
    mysqli_stmt_execute($cs);
    $cn = mysqli_fetch_assoc(mysqli_stmt_get_result($cs));
    if ($cn) $active_cat_name = $cn['name'];
}

// 3 artikel terbaru untuk hero ticker
$ticker_stmt = mysqli_prepare($conn,
    "SELECT p.id, p.title, p.created_at, c.name AS category_name
     FROM posts p LEFT JOIN categories c ON p.category_id=c.id
     WHERE p.status='published' ORDER BY p.created_at DESC LIMIT 3");
mysqli_stmt_execute($ticker_stmt);
$ticker_posts = mysqli_stmt_get_result($ticker_stmt);

function build_query(array $extra = []): string {
    global $cat_id, $search;
    $params = [];
    if ($cat_id > 0)    $params['category'] = $cat_id;
    if ($search !== '') $params['q']        = $search;
    return http_build_query(array_merge($params, $extra));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require_once 'config/head.php'; ?>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body class="public-page">

<!-- HEADER -->
<header class="site-header">
    <div class="site-header-top">
        <div class="container">
            <div class="site-brand">
                <img src="assets/logo.jpg" alt="Logo <?= $p('school_name') ?>" class="site-logo">
                <div>
                    <h1><?= $p('school_name', 'Portal Berita Sekolah') ?></h1>
                    <p><?= $p('school_accreditation') ? 'Akreditasi ' . $p('school_accreditation') . ' — ' : '' ?>Informasi Terkini &amp; Terpercaya</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                <form class="header-search" method="GET" action="">
                    <?php if ($cat_id > 0): ?>
                        <input type="hidden" name="category" value="<?= $cat_id ?>">
                    <?php endif; ?>
                    <input type="search" name="q" placeholder="Cari berita…"
                           value="<?= htmlspecialchars($search) ?>" aria-label="Cari">
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
                <a href="index.php" class="<?= ($cat_id===0 && $search==='') ? 'active' : '' ?>">&#127968; Beranda</a>
                <?php foreach ($categories_arr as $cat): ?>
                    <a href="?category=<?= $cat['id'] ?>"
                       class="<?= $cat_id===(int)$cat['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
                <a href="profil.php">&#127979; Profil Sekolah</a>
                <a href="galeri.php">&#128247; Galeri</a>
                <a href="guru.php">&#128218; Guru</a>
            </nav>
        </div>
    </div>
</header>

<?php if ($cat_id === 0 && $search === '' && $page === 1): ?>

<!-- HERO -->
<section class="hero">
    <div class="container hero-content">
        <div class="hero-text">
            <span class="hero-badge">&#11088; SMK Pusat Keunggulan</span>
            <h2><?= $p('school_name', 'Portal Berita Sekolah') ?></h2>
            <p><?= $p('school_vision', 'Menghasilkan sumber daya manusia bermutu dan berdaya saing.') ?></p>
            <div class="hero-actions">
                <a href="#berita" class="btn btn-hero-primary">&#128196; Lihat Berita</a>
                <a href="profil.php" class="btn btn-hero-outline">&#127979; Profil Sekolah</a>
            </div>
        </div>
    </div>
</section>

<!-- Ticker berita terbaru (di luar hero, di bawahnya) -->
<div class="hero-ticker">
    <div class="container">
        <div class="ticker-wrap">
            <span class="ticker-label">&#128197; Terbaru</span>
            <div class="ticker-items">
                <?php while ($tp = mysqli_fetch_assoc($ticker_posts)): ?>
                <a href="post.php?id=<?= $tp['id'] ?>" class="ticker-item">
                    <span class="ticker-cat"><?= htmlspecialchars($tp['category_name'] ?? '') ?></span>
                    <?= htmlspecialchars($tp['title']) ?>
                    <span class="ticker-date"><?= date('d M', strtotime($tp['created_at'])) ?></span>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- PPDB BANNER -->
<section class="ppdb-banner">
    <div class="container">
        <div class="ppdb-inner">
            <div>
                <span class="ppdb-badge">&#128276; Informasi PPDB</span>
                <h3>Pendaftaran Peserta Didik Baru</h3>
                <p>Bergabunglah bersama kami dan raih prestasi terbaik bersama <?= $p('school_name', 'sekolah kami') ?>.</p>
            </div>
            <div class="ppdb-actions">
                <?php
                $ppdb_cat = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT id FROM categories WHERE name LIKE '%PPDB%' LIMIT 1"));
                if ($ppdb_cat): ?>
                    <a href="?category=<?= $ppdb_cat['id'] ?>" class="btn btn-ppdb">&#128196; Info Lengkap</a>
                <?php endif; ?>
                <a href="profil.php?tab=kontak" class="btn btn-ppdb-outline">&#128222; Hubungi Kami</a>
            </div>
        </div>
    </div>
</section>

<!-- SAMBUTAN KEPALA SEKOLAH -->
<?php if (!empty($sp['principal_name'])): ?>
<section class="home-principal">
    <div class="container">
        <div class="home-principal-inner">
            <div class="home-principal-photo">
                <?php if (!empty($sp['principal_photo'])): ?>
                    <img src="assets/uploads/<?= htmlspecialchars($sp['principal_photo']) ?>"
                         alt="<?= $p('principal_name') ?>">
                <?php else: ?>
                    <div class="principal-avatar-placeholder">&#128100;</div>
                <?php endif; ?>
                <div class="home-principal-name" style="margin-top:.75rem;text-align:center">
                    <strong><?= $p('principal_name') ?></strong>
                    <span>Kepala Sekolah</span>
                </div>
            </div>
            <div class="home-principal-content">
                <span class="section-label">Sambutan Kepala Sekolah</span>
                <h3>Melangkah Menuju Prestasi</h3>
                <blockquote>
                    "<?= nl2br(htmlspecialchars(mb_substr($sp['principal_message'] ?? '', 0, 400))) ?>…"
                </blockquote>
                <a href="profil.php?tab=kepala" class="btn btn-primary btn-sm">Baca Selengkapnya</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php endif; ?>

<!-- KONTEN UTAMA -->
<div class="container main-content" id="berita">

    <?php if ($featured && $cat_id === 0 && $search === '' && $page === 1): ?>
    <div class="featured-article">
        <a href="post.php?id=<?= $featured['id'] ?>" class="featured-link">
            <div class="featured-image">
                <?php if ($featured['image']): ?>
                    <img src="assets/uploads/<?= htmlspecialchars($featured['image']) ?>"
                         alt="<?= htmlspecialchars($featured['title']) ?>">
                <?php else: ?>
                    <div class="featured-image-placeholder">&#127891;</div>
                <?php endif; ?>
                <span class="featured-badge">&#11088; Artikel Unggulan</span>
                <span class="post-category" style="top:auto;bottom:.75rem">
                    <?= htmlspecialchars($featured['category_name'] ?? 'Umum') ?>
                </span>
            </div>
            <div class="featured-body">
                <h2 class="featured-title"><?= htmlspecialchars($featured['title']) ?></h2>
                <p class="featured-excerpt">
                    <?php
                    $exc = !empty($featured['excerpt'])
                        ? $featured['excerpt']
                        : mb_substr(strip_tags($featured['content']), 0, 200);
                    echo htmlspecialchars($exc) . '…';
                    ?>
                </p>
                <div class="featured-meta">
                    <span>&#128100; <?= htmlspecialchars($featured['username'] ?? 'Admin') ?></span>
                    <span>&#128197; <?= date('d F Y', strtotime($featured['created_at'])) ?></span>
                    <span>&#128065; <?= number_format($featured['views'] ?? 0) ?> pembaca</span>
                </div>
                <span class="btn btn-primary" style="margin-top:1rem;pointer-events:none">Baca Selengkapnya &rarr;</span>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <div class="content-wrapper">

        <!-- Kolom Artikel -->
        <div class="content-main">
            <div class="section-header">
                <?php if ($cat_id > 0 || $search !== ''): ?>
                <nav class="breadcrumb" style="margin-bottom:.75rem">
                    <a href="index.php">Beranda</a>
                    <span>&#8250;</span>
                    <?php if ($cat_id > 0): ?>
                        <span><?= htmlspecialchars($active_cat_name) ?></span>
                    <?php else: ?>
                        <span>Pencarian: "<?= htmlspecialchars($search) ?>"</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
                <h2 class="section-title">
                    <?= htmlspecialchars($active_cat_name) ?>
                    <small>(<?= $total_posts ?> artikel)</small>
                </h2>
                <?php if ($search !== '' || $cat_id > 0): ?>
                    <a href="index.php" class="btn-reset-filter">&#10005; Reset filter</a>
                <?php endif; ?>
            </div>

            <?php if ($total_posts === 0): ?>
                <div class="empty-state">
                    <span>&#128269;</span>
                    <p>Tidak ada artikel ditemukan.</p>
                    <a href="index.php" class="btn btn-primary" style="margin-top:1rem">Lihat Semua</a>
                </div>
            <?php else: ?>
                <div class="posts-grid">
                    <?php $ani = 0; while ($post = mysqli_fetch_assoc($posts)): ?>
                    <article class="post-card fade-up"
                             data-date="<?= $post['created_at'] ?>"
                             style="animation-delay:<?= $ani * 0.08 ?>s">
                        <a href="post.php?id=<?= $post['id'] ?>" class="post-card-link">
                            <div class="post-thumb">
                                <?php if ($post['image']): ?>
                                    <img src="assets/uploads/<?= htmlspecialchars($post['image']) ?>"
                                         alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="post-thumb-placeholder">&#128247;</div>
                                <?php endif; ?>
                                <span class="post-category"><?= htmlspecialchars($post['category_name'] ?? 'Umum') ?></span>
                            </div>
                            <div class="post-body">
                                <h3 class="post-title"><?= htmlspecialchars($post['title']) ?></h3>
                                <p class="post-excerpt">
                                    <?php
                                    $ex = !empty($post['excerpt']) ? $post['excerpt']
                                        : mb_substr(strip_tags($post['content']), 0, 110);
                                    echo htmlspecialchars($ex) . '…';
                                    ?>
                                </p>
                                <div class="post-meta">
                                    <span>&#128197; <?= date('d M Y', strtotime($post['created_at'])) ?></span>
                                    <span>&#128065; <?= number_format($post['views'] ?? 0) ?></span>
                                    <?php
                                    $wc  = str_word_count(strip_tags($post['content']));
                                    $min = max(1, ceil($wc / 200));
                                    ?>
                                    <span class="read-time">&#9201; <?= $min ?> mnt</span>
                                </div>
                            </div>
                        </a>
                    </article>
                    <?php $ani++; endwhile; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= build_query(['page'=>$page-1]) ?>" class="page-link">&laquo;</a>
                    <?php endif; ?>
                    <?php for ($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
                        <a href="?<?= build_query(['page'=>$i]) ?>"
                           class="page-link <?= $i===$page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= build_query(['page'=>$page+1]) ?>" class="page-link">&raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <aside class="content-sidebar">

            <!-- Artikel Terbaru -->
            <div class="sidebar-widget">
                <h3 class="widget-title">&#128197; Artikel Terbaru</h3>
                <?php while ($lp = mysqli_fetch_assoc($latest_posts)): ?>
                <a href="post.php?id=<?= $lp['id'] ?>" class="widget-post">
                    <?php if ($lp['image']): ?>
                        <img src="assets/uploads/<?= htmlspecialchars($lp['image']) ?>"
                             alt="<?= htmlspecialchars($lp['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="widget-post-placeholder">&#128247;</div>
                    <?php endif; ?>
                    <div>
                        <p><?= htmlspecialchars($lp['title']) ?></p>
                        <small>&#128197; <?= date('d M Y', strtotime($lp['created_at'])) ?></small>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>

            <!-- Artikel Terpopuler -->
            <div class="sidebar-widget">
                <h3 class="widget-title">&#128293; Artikel Terpopuler</h3>
                <?php $pop_no = 1; while ($pp = mysqli_fetch_assoc($popular_posts)): ?>
                <a href="post.php?id=<?= $pp['id'] ?>" class="widget-post widget-popular">
                    <span class="popular-rank"><?= $pop_no++ ?></span>
                    <div>
                        <p><?= htmlspecialchars($pp['title']) ?></p>
                        <small>&#128065; <?= number_format($pp['views']) ?> pembaca</small>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>

            <!-- Kategori -->
            <div class="sidebar-widget">
                <h3 class="widget-title">&#128193; Kategori</h3>
                <?php
                $cat_counts = mysqli_query($conn,
                    "SELECT c.id, c.name, COUNT(p.id) AS total
                     FROM categories c
                     LEFT JOIN posts p ON p.category_id=c.id AND p.status='published'
                     GROUP BY c.id ORDER BY c.name ASC");
                while ($cc = mysqli_fetch_assoc($cat_counts)):
                ?>
                <a href="?category=<?= $cc['id'] ?>"
                   class="widget-cat <?= $cat_id===(int)$cc['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cc['name']) ?>
                    <span class="cat-count"><?= $cc['total'] ?></span>
                </a>
                <?php endwhile; ?>
            </div>

            <!-- Info Sekolah -->
            <div class="sidebar-widget school-info-widget">
                <h3 class="widget-title">&#127979; Info Sekolah</h3>
                <?php if (!empty($sp['school_address'])): ?>
                <div class="school-info-item">
                    <span>&#128205;</span>
                    <span><?= htmlspecialchars($sp['school_address']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($sp['school_phone'])): ?>
                <div class="school-info-item">
                    <span>&#128222;</span>
                    <span><?= htmlspecialchars($sp['school_phone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($sp['school_email'])): ?>
                <div class="school-info-item">
                    <span>&#128140;</span>
                    <a href="mailto:<?= htmlspecialchars($sp['school_email']) ?>">
                        <?= htmlspecialchars($sp['school_email']) ?>
                    </a>
                </div>
                <?php endif; ?>
                <div style="margin-top:.75rem;display:flex;gap:.5rem;flex-direction:column">
                    <a href="profil.php" class="btn btn-primary btn-sm btn-block" style="text-align:center">
                        &#127979; Profil Sekolah
                    </a>
                    <a href="galeri.php" class="btn btn-secondary btn-sm btn-block" style="text-align:center">
                        &#128247; Galeri Foto
                    </a>
                </div>
            </div>

        </aside>
    </div>
</div>

<!-- FOOTER -->
<footer class="site-footer">
    <div class="footer-top">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <img src="assets/logo.jpg" alt="Logo" style="width:44px;height:44px;object-fit:contain;border-radius:6px;margin-bottom:.5rem;display:block">
                    <strong><?= $p('school_name', 'Portal Berita Sekolah') ?></strong>
                    <p><?= $p('school_vision', 'Informasi terkini dan terpercaya.') ?></p>
                    <?php if (!empty($sp['social_instagram']) || !empty($sp['social_facebook']) || !empty($sp['social_youtube'])): ?>
                    <div class="footer-social">
                        <?php if (!empty($sp['social_instagram'])): ?>
                            <a href="<?= htmlspecialchars($sp['social_instagram']) ?>" target="_blank" title="Instagram">&#128247;</a>
                        <?php endif; ?>
                        <?php if (!empty($sp['social_facebook'])): ?>
                            <a href="<?= htmlspecialchars($sp['social_facebook']) ?>" target="_blank" title="Facebook">&#128172;</a>
                        <?php endif; ?>
                        <?php if (!empty($sp['social_youtube'])): ?>
                            <a href="<?= htmlspecialchars($sp['social_youtube']) ?>" target="_blank" title="YouTube">&#127909;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h4>Kategori Berita</h4>
                    <ul>
                        <?php
                        $cats_f = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
                        while ($cf = mysqli_fetch_assoc($cats_f)):
                        ?>
                        <li><a href="?category=<?= $cf['id'] ?>"><?= htmlspecialchars($cf['name']) ?></a></li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <div>
                    <h4>Tautan Cepat</h4>
                    <ul>
                        <li><a href="index.php">&#127968; Beranda</a></li>
                        <li><a href="galeri.php">&#128247; Galeri Foto</a></li>
                        <li><a href="profil.php">&#127979; Profil Sekolah</a></li>
                        <li><a href="profil.php?tab=kepala">&#128100; Kepala Sekolah</a></li>
                        <li><a href="profil.php?tab=fasilitas">&#127970; Fasilitas</a></li>
                        <li><a href="profil.php?tab=ekskul">&#127941; Ekstrakurikuler</a></li>
                        <li><a href="profil.php?tab=prestasi">&#127942; Prestasi</a></li>
                        <li><a href="profil.php?tab=kontak">&#128222; Kontak</a></li>
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
                        <?php if (!empty($sp['school_email'])): ?>
                        <li><span>&#128140;</span><a href="mailto:<?= htmlspecialchars($sp['school_email']) ?>"><?= htmlspecialchars($sp['school_email']) ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($sp['school_website'])): ?>
                        <li><span>&#127760;</span><?= htmlspecialchars($sp['school_website']) ?></li>
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
</body>
</html>
