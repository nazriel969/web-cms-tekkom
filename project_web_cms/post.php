<?php
require_once 'config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id < 1) { header("Location: index.php"); exit; }

// Ambil artikel
$preview_mode = isset($_GET['preview']) && isset($_SESSION['admin_id']);
$status_cond  = $preview_mode ? "1=1" : "p.status = 'published'";

$stmt = mysqli_prepare($conn,
    "SELECT p.*, c.name AS category_name, c.id AS cat_id, u.username
     FROM posts p
     LEFT JOIN categories c ON p.category_id = c.id
     LEFT JOIN users u ON p.user_id = u.id
     WHERE p.id = ? AND $status_cond LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$post = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$post) { header("Location: index.php"); exit; }

// Tambah view counter
mysqli_query($conn, "UPDATE posts SET views = views + 1 WHERE id = $id");

// Prev & Next
$prev_stmt = mysqli_prepare($conn,
    "SELECT id, title FROM posts WHERE status='published' AND id < ? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($prev_stmt, 'i', $id);
mysqli_stmt_execute($prev_stmt);
$prev_post = mysqli_fetch_assoc(mysqli_stmt_get_result($prev_stmt));

$next_stmt = mysqli_prepare($conn,
    "SELECT id, title FROM posts WHERE status='published' AND id > ? ORDER BY id ASC LIMIT 1");
mysqli_stmt_bind_param($next_stmt, 'i', $id);
mysqli_stmt_execute($next_stmt);
$next_post = mysqli_fetch_assoc(mysqli_stmt_get_result($next_stmt));

// URL untuk share
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Proses komentar
$comment_errors  = [];
$comment_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $c_name    = trim($_POST['c_name']    ?? '');
    $c_email   = trim($_POST['c_email']   ?? '');
    $c_content = trim($_POST['c_content'] ?? '');

    // Rate limiting — maksimal 3 komentar per IP per jam
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $rate_check = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS t FROM comments
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
         AND post_id = $id"));
    // Per-post: max 5 komentar per jam global (anti spam)
    $rate_limit = (int)($rate_check['t'] ?? 0);

    if (empty($c_name))    $comment_errors[] = 'Nama wajib diisi.';
    if (empty($c_content)) $comment_errors[] = 'Komentar wajib diisi.';
    if (strlen($c_content) > 1000) $comment_errors[] = 'Komentar maksimal 1000 karakter.';
    if (!empty($c_email) && !filter_var($c_email, FILTER_VALIDATE_EMAIL))
        $comment_errors[] = 'Format email tidak valid.';
    if ($rate_limit >= 10) $comment_errors[] = 'Terlalu banyak komentar. Coba lagi nanti.';
    // Honeypot — jika terisi, ini bot
    if (!empty($_POST['website'])) exit;

    if (empty($comment_errors)) {
        $cst = mysqli_prepare($conn,
            "INSERT INTO comments (post_id, name, email, content, status, created_at)
             VALUES (?, ?, ?, ?, 'pending', NOW())");
        mysqli_stmt_bind_param($cst, 'isss', $id, $c_name, $c_email, $c_content);
        mysqli_stmt_execute($cst);
        mysqli_stmt_close($cst);
        $comment_success = 'Komentar berhasil dikirim dan menunggu persetujuan admin.';
    }
}

// Komentar disetujui
$com_stmt = mysqli_prepare($conn,
    "SELECT name, content, created_at FROM comments
     WHERE post_id = ? AND status = 'approved' ORDER BY created_at ASC");
mysqli_stmt_bind_param($com_stmt, 'i', $id);
mysqli_stmt_execute($com_stmt);
$comments      = mysqli_stmt_get_result($com_stmt);
$comment_count = mysqli_num_rows($comments);

// Artikel terkait
$rel_stmt = mysqli_prepare($conn,
    "SELECT id, title, image, created_at FROM posts
     WHERE status='published' AND category_id=? AND id!=?
     ORDER BY created_at DESC LIMIT 4");
mysqli_stmt_bind_param($rel_stmt, 'ii', $post['cat_id'], $id);
mysqli_stmt_execute($rel_stmt);
$related_arr = [];
while ($r = mysqli_fetch_assoc(mysqli_stmt_get_result($rel_stmt))) $related_arr[] = $r;

// Artikel terbaru sidebar
$latest_stmt = mysqli_prepare($conn,
    "SELECT id, title, created_at FROM posts
     WHERE status='published' AND id!=? ORDER BY created_at DESC LIMIT 5");
mysqli_stmt_bind_param($latest_stmt, 'i', $id);
mysqli_stmt_execute($latest_stmt);
$latest = mysqli_stmt_get_result($latest_stmt);

// Kategori navigasi
$cat_result     = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categories_arr = [];
while ($r = mysqli_fetch_assoc($cat_result)) $categories_arr[] = $r;

// Profil sekolah (untuk title)
$sp_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT value FROM school_profile WHERE `key`='school_name' LIMIT 1"));
$school_name = htmlspecialchars($sp_row['value'] ?? 'Portal Berita Sekolah');

// Meta OG
$base_url       = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST'];
$page_site_name = $sp_row['value'] ?? 'Portal Berita Sekolah';
$page_title     = $post['title'];
$page_desc      = mb_substr(strip_tags($post['content']), 0, 160);
$page_url       = $current_url;
$page_image     = !empty($post['image'])
    ? $base_url . '/project_web_cms/assets/uploads/' . $post['image']
    : $base_url . '/project_web_cms/assets/logo.jpg';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require_once 'config/head.php'; ?>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "NewsArticle",
        "headline": <?= json_encode($post['title']) ?>,
        "description": <?= json_encode(mb_substr(strip_tags($post['content']), 0, 160)) ?>,
        "datePublished": "<?= date('c', strtotime($post['created_at'])) ?>",
        "author": { "@type": "Person", "name": <?= json_encode($post['username'] ?? 'Admin') ?> },
        "publisher": { "@type": "Organization", "name": <?= json_encode($school_name) ?> },
        "image": <?= !empty($post['image']) ? json_encode($page_image) : '""' ?>
    }
    </script>
    <style>
        @media print {
            .site-header, .content-sidebar, .comments-section,
            .share-section, .post-nav, .back-to-top,
            .site-footer, .post-actions { display: none !important; }
            .content-wrapper { grid-template-columns: 1fr !important; }
            .post-detail { box-shadow: none; border: none; }
        }
    </style>
</head>
<body class="public-page">

<!-- Header -->
<header class="site-header">
    <div class="site-header-top">
        <div class="container">
            <div class="site-brand">
                <img src="assets/logo.jpg" alt="Logo <?= $school_name ?>" class="site-logo">
                <div>
                    <h1><?= $school_name ?></h1>
                    <p>Informasi Terkini &amp; Terpercaya</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                <form class="header-search" method="GET" action="index.php">
                    <input type="search" name="q" placeholder="Cari artikel…" aria-label="Cari">
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
                    <a href="index.php?category=<?= $cat['id'] ?>">
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

<div class="container main-content">

    <?php if ($preview_mode && $post['status'] === 'draft'): ?>
    <div class="preview-banner">
        <span>&#128065; Mode Preview — Artikel ini masih Draft dan tidak terlihat oleh pengunjung</span>
        <a href="admin/create.php?edit=<?= $post['id'] ?>" class="btn btn-sm btn-warning">&#9998; Edit Artikel</a>
    </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="index.php">Beranda</a>
        <span>&#8250;</span>
        <a href="index.php?category=<?= $post['cat_id'] ?>"><?= htmlspecialchars($post['category_name'] ?? 'Umum') ?></a>
        <span>&#8250;</span>
        <span><?= htmlspecialchars(mb_substr($post['title'], 0, 50)) ?><?= mb_strlen($post['title']) > 50 ? '…' : '' ?></span>
    </nav>

    <div class="content-wrapper">

        <!-- Artikel -->
        <article class="post-detail fade-up">
            <div class="post-detail-meta">
                <a href="index.php?category=<?= $post['cat_id'] ?>" class="post-category">
                    <?= htmlspecialchars($post['category_name'] ?? 'Umum') ?>
                </a>
                <span>&#128197; <?= date('d F Y', strtotime($post['created_at'])) ?></span>
                <span>&#128100; <?= htmlspecialchars($post['username'] ?? 'Admin') ?></span>
                <span>&#128065; <?= number_format(($post['views'] ?? 0) + 1) ?> pembaca</span>
                <?php
                $word_count = str_word_count(strip_tags($post['content']));
                $read_min   = max(1, ceil($word_count / 200));
                ?>
                <span class="read-time">&#9201; <?= $read_min ?> menit baca</span>
            </div>

            <h1 class="post-detail-title"><?= htmlspecialchars($post['title']) ?></h1>

            <?php if ($post['image']): ?>
            <div class="post-detail-image">
                <img src="assets/uploads/<?= htmlspecialchars($post['image']) ?>"
                     alt="<?= htmlspecialchars($post['title']) ?>">
            </div>
            <?php endif; ?>

            <div class="post-detail-content">
                <?= $post['content'] ?>
            </div>

            <!-- Tombol Aksi -->
            <div class="post-detail-footer">
                <div class="post-actions">
                    <a href="index.php" class="btn btn-secondary">&larr; Kembali</a>
                    <a href="index.php?category=<?= $post['cat_id'] ?>" class="btn btn-primary">
                        &#128193; <?= htmlspecialchars($post['category_name'] ?? '') ?>
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-print">
                        &#128438; Cetak
                    </button>
                </div>

                <!-- Share -->
                <div class="share-section">
                    <span class="share-label">&#128279; Bagikan:</span>
                    <div class="share-buttons">
                        <a href="https://wa.me/?text=<?= urlencode($post['title'] . ' ' . $current_url) ?>"
                           target="_blank" rel="noopener" class="share-btn share-wa">
                            &#128241; WhatsApp
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($current_url) ?>"
                           target="_blank" rel="noopener" class="share-btn share-fb">
                            &#128172; Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($post['title']) ?>&url=<?= urlencode($current_url) ?>"
                           target="_blank" rel="noopener" class="share-btn share-tw">
                            &#120143; Twitter
                        </a>
                        <button onclick="copyLink(this)" class="share-btn share-copy">
                            &#128203; Salin Link
                        </button>
                    </div>
                </div>

                <!-- Navigasi Prev/Next -->
                <?php if ($prev_post || $next_post): ?>
                <div class="post-nav">
                    <div class="post-nav-item">
                        <?php if ($prev_post): ?>
                        <a href="post.php?id=<?= $prev_post['id'] ?>" class="post-nav-link">
                            <span class="nav-dir">&larr; Artikel Sebelumnya</span>
                            <span class="nav-title"><?= htmlspecialchars(mb_substr($prev_post['title'], 0, 60)) ?></span>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="post-nav-item" style="text-align:right">
                        <?php if ($next_post): ?>
                        <a href="post.php?id=<?= $next_post['id'] ?>" class="post-nav-link">
                            <span class="nav-dir">Artikel Selanjutnya &rarr;</span>
                            <span class="nav-title"><?= htmlspecialchars(mb_substr($next_post['title'], 0, 60)) ?></span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Komentar -->
            <div class="comments-section" id="komentar">
                <h3 class="comments-title">&#128172; Komentar <span>(<?= $comment_count ?>)</span></h3>

                <?php if ($comment_success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($comment_success) ?></div>
                <?php endif; ?>

                <?php if ($comment_count > 0): ?>
                <div class="comments-list">
                    <?php while ($c = mysqli_fetch_assoc($comments)): ?>
                    <div class="comment-item fade-up">
                        <div class="comment-avatar">
                            <?= mb_strtoupper(mb_substr($c['name'], 0, 1)) ?>
                        </div>
                        <div class="comment-body">
                            <div class="comment-header">
                                <strong><?= htmlspecialchars($c['name']) ?></strong>
                                <span>&#128197; <?= date('d M Y, H:i', strtotime($c['created_at'])) ?></span>
                            </div>
                            <p><?= nl2br(htmlspecialchars($c['content'])) ?></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                    <p class="no-comments">Belum ada komentar. Jadilah yang pertama berkomentar!</p>
                <?php endif; ?>

                <div class="comment-form-wrap">
                    <h4>Tulis Komentar</h4>
                    <?php if (!empty($comment_errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($comment_errors as $e): ?>
                                <div><?= htmlspecialchars($e) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="#komentar" novalidate>
                        <!-- Honeypot anti-bot -->
                        <div style="display:none" aria-hidden="true">
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nama <span class="required">*</span></label>
                                <input type="text" name="c_name"
                                       value="<?= htmlspecialchars($_POST['c_name'] ?? '') ?>"
                                       placeholder="Nama kamu" required maxlength="100">
                            </div>
                            <div class="form-group">
                                <label>Email <small>(opsional)</small></label>
                                <input type="email" name="c_email"
                                       value="<?= htmlspecialchars($_POST['c_email'] ?? '') ?>"
                                       placeholder="email@contoh.com" maxlength="150">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Komentar <span class="required">*</span></label>
                            <textarea name="c_content" rows="4" required maxlength="1000"
                                      placeholder="Tulis komentar…"><?= htmlspecialchars($_POST['c_content'] ?? '') ?></textarea>
                            <small style="color:var(--mid)">Komentar ditampilkan setelah disetujui admin.</small>
                        </div>
                        <button type="submit" name="submit_comment" class="btn btn-primary">
                            &#128172; Kirim Komentar
                        </button>
                    </form>
                </div>
            </div>
        </article>

        <!-- Sidebar -->
        <aside class="content-sidebar">
            <?php if (!empty($related_arr)): ?>
            <div class="sidebar-widget">
                <h3 class="widget-title">&#128279; Artikel Terkait</h3>
                <?php foreach ($related_arr as $rel): ?>
                <a href="post.php?id=<?= $rel['id'] ?>" class="widget-post">
                    <?php if ($rel['image']): ?>
                        <img src="assets/uploads/<?= htmlspecialchars($rel['image']) ?>"
                             alt="<?= htmlspecialchars($rel['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="widget-post-placeholder">&#128247;</div>
                    <?php endif; ?>
                    <div>
                        <p><?= htmlspecialchars($rel['title']) ?></p>
                        <small>&#128197; <?= date('d M Y', strtotime($rel['created_at'])) ?></small>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="sidebar-widget">
                <h3 class="widget-title">&#128197; Artikel Terbaru</h3>
                <?php while ($lp = mysqli_fetch_assoc($latest)): ?>
                <a href="post.php?id=<?= $lp['id'] ?>" class="widget-post widget-post-text">
                    <div>
                        <p><?= htmlspecialchars($lp['title']) ?></p>
                        <small>&#128197; <?= date('d M Y', strtotime($lp['created_at'])) ?></small>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
        </aside>

    </div>
</div>

<footer class="site-footer">
    <div class="footer-top" style="padding:1.5rem 0">
        <div class="container">
            <p style="color:rgba(255,255,255,.5);font-size:.85rem;text-align:center">
                <?= $school_name ?> — Portal Informasi Resmi
            </p>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= $school_name ?>. All Rights Reserved.</p>
        </div>
    </div>
</footer>

<a href="#" class="back-to-top" id="backToTop" aria-label="Kembali ke atas">&#8679;</a>
<?php
// Load sp untuk WA button jika belum ada
if (!isset($sp)) { $sp = []; $raw2 = mysqli_query($conn, "SELECT `key`, value FROM school_profile"); while($r=mysqli_fetch_assoc($raw2)) $sp[$r['key']]=$r['value']; }
require_once 'config/footer_widgets.php';
?>
<script src="assets/app.js"></script>
<script>
function copyLink(el) {
    navigator.clipboard.writeText(window.location.href).then(() => {
        const orig = el.textContent;
        el.textContent = '✓ Tersalin!';
        el.style.background = '#16a34a';
        setTimeout(() => { el.textContent = orig; el.style.background = ''; }, 2000);
    });
}
</script>
</body>
</html>
