<?php
require_once 'config/database.php';

$raw = mysqli_query($conn, "SELECT `key`, value FROM school_profile");
$profile = [];
while ($r = mysqli_fetch_assoc($raw)) $profile[$r['key']] = $r['value'];

$cat_result     = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categories_arr = [];
while ($r = mysqli_fetch_assoc($cat_result)) $categories_arr[] = $r;

$p = fn(string $key, string $default = '') => htmlspecialchars($profile[$key] ?? $default);

// Tab aktif dari hash
$active_tab = $_GET['tab'] ?? 'selayang';
$valid_tabs = ['selayang','visi','kepala','fasilitas','ekskul','prestasi','kontak'];
if (!in_array($active_tab, $valid_tabs)) $active_tab = 'selayang';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php
    $page_site_name = $profile['school_name'] ?? 'Portal Berita Sekolah';
    $page_title     = 'Profil Sekolah';
    $page_desc      = $profile['school_vision'] ?? null;
    require_once 'config/head.php';
    ?>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body class="public-page">

<!-- ===== HEADER ===== -->
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
                <a href="profil.php" class="active">&#127979; Profil Sekolah</a>
                <a href="galeri.php">&#128247; Galeri</a>
                <a href="guru.php">&#128218; Guru</a>
            </nav>
        </div>
    </div>
</header>

<!-- ===== HERO PROFIL ===== -->
<section class="hero">
    <div class="container hero-content">
        <div class="hero-text">
            <span class="hero-badge">&#127979; Profil Sekolah</span>
            <h2><?= $p('school_name', 'Portal Berita Sekolah') ?></h2>
            <p><?= $p('school_vision', 'Menghasilkan sumber daya manusia bermutu dan berdaya saing tinggi.') ?></p>
            <div class="hero-actions">
                <a href="#konten-profil" class="btn btn-hero-primary">&#128203; Lihat Profil</a>
                <a href="profil.php?tab=kontak" class="btn btn-hero-outline">&#128222; Hubungi Kami</a>
            </div>
        </div>
    </div>
</section>

<!-- ===== STATISTIK ===== -->
<section class="home-stats">
    <div class="container">
        <div class="home-stats-grid">
            <div class="home-stat">
                <span class="home-stat-icon">&#128197;</span>
                <span class="home-stat-num counter-num" data-target="<?= intval($profile['school_founded'] ?? 0) ?>"><?= $p('school_founded', '–') ?></span>
                <span class="home-stat-label">Tahun Berdiri</span>
            </div>
            <div class="home-stat">
                <span class="home-stat-icon">&#128218;</span>
                <span class="home-stat-num counter-num" data-target="<?= intval($profile['total_teachers'] ?? 0) ?>"><?= $p('total_teachers', '–') ?>+</span>
                <span class="home-stat-label">Tenaga Pengajar</span>
            </div>
            <div class="home-stat">
                <span class="home-stat-icon">&#128106;</span>
                <span class="home-stat-num counter-num" data-target="<?= intval(str_replace('.', '', $profile['total_students'] ?? 0)) ?>"><?= $p('total_students', '–') ?>+</span>
                <span class="home-stat-label">Siswa Aktif</span>
            </div>
            <div class="home-stat">
                <span class="home-stat-icon">&#127942;</span>
                <span class="home-stat-num"><?= $p('school_accreditation', 'A') ?></span>
                <span class="home-stat-label">Akreditasi</span>
            </div>
            <div class="home-stat">
                <span class="home-stat-icon">&#128188;</span>
                <span class="home-stat-num counter-num" data-target="<?= intval($profile['total_staff'] ?? 0) ?>"><?= $p('total_staff', '–') ?>+</span>
                <span class="home-stat-label">Tenaga Kependidikan</span>
            </div>
        </div>
    </div>
</section>

<!-- ===== KONTEN PROFIL ===== -->
<div id="konten-profil" class="container main-content">

    <!-- Tab Navigasi -->
    <div class="profil-tabs">
        <?php
        $tabs = [
            'selayang' => ['&#127981;', 'Selayang Pandang'],
            'visi'     => ['&#127919;', 'Visi & Misi'],
            'kepala'   => ['&#128100;', 'Kepala Sekolah'],
            'fasilitas'=> ['&#127970;', 'Fasilitas'],
            'ekskul'   => ['&#127941;', 'Ekstrakurikuler'],
            'prestasi' => ['&#127942;', 'Prestasi'],
            'kontak'   => ['&#128222;', 'Kontak'],
        ];
        foreach ($tabs as $key => [$icon, $label]):
        ?>
        <a href="profil.php?tab=<?= $key ?>"
           class="profil-tab <?= $active_tab === $key ? 'active' : '' ?>">
            <?= $icon ?> <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ===== SELAYANG PANDANG ===== -->
    <?php if ($active_tab === 'selayang'): ?>
    <div class="profil-section">
        <!-- Hero Card -->
        <div class="profil-hero-card">
            <div class="profil-hero-info">
                <span class="section-label">Tentang Kami</span>
                <h2><?= $p('school_name') ?></h2>
                <div class="profil-badges">
                    <span class="profil-badge">&#127891; Akreditasi <?= $p('school_accreditation', 'A') ?></span>
                    <span class="profil-badge">&#128197; Berdiri <?= $p('school_founded', '-') ?></span>
                    <span class="profil-badge">&#128205; <?= $p('school_address') ? explode(',', $profile['school_address'])[0] : '-' ?></span>
                </div>
                <p><?= nl2br($p('school_history')) ?></p>
            </div>
        </div>

        <!-- Quick Info Grid -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem;margin-top:1.5rem">
            <div class="card" style="border-left:4px solid var(--primary)">
                <div style="display:flex;align-items:center;gap:.85rem">
                    <span style="font-size:2rem">&#127919;</span>
                    <div>
                        <strong style="display:block;margin-bottom:.3rem">Visi</strong>
                        <p style="font-size:.875rem;color:var(--mid);line-height:1.6;margin:0">
                            <?= htmlspecialchars(mb_substr($profile['school_vision'] ?? '', 0, 120)) ?>…
                        </p>
                    </div>
                </div>
                <div style="margin-top:.85rem">
                    <a href="profil.php?tab=visi" class="btn btn-sm btn-primary">Selengkapnya</a>
                </div>
            </div>
            <div class="card" style="border-left:4px solid var(--success)">
                <div style="display:flex;align-items:center;gap:.85rem">
                    <span style="font-size:2rem">&#128100;</span>
                    <div>
                        <strong style="display:block;margin-bottom:.3rem">Kepala Sekolah</strong>
                        <p style="font-size:.875rem;color:var(--mid);margin:0"><?= $p('principal_name', '-') ?></p>
                    </div>
                </div>
                <div style="margin-top:.85rem">
                    <a href="profil.php?tab=kepala" class="btn btn-sm btn-success">Lihat Sambutan</a>
                </div>
            </div>
            <div class="card" style="border-left:4px solid var(--warning)">
                <div style="display:flex;align-items:center;gap:.85rem">
                    <span style="font-size:2rem">&#128222;</span>
                    <div>
                        <strong style="display:block;margin-bottom:.3rem">Kontak</strong>
                        <p style="font-size:.875rem;color:var(--mid);margin:0"><?= $p('school_phone', '-') ?></p>
                    </div>
                </div>
                <div style="margin-top:.85rem">
                    <a href="profil.php?tab=kontak" class="btn btn-sm btn-warning">Hubungi Kami</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== VISI MISI ===== -->
    <?php elseif ($active_tab === 'visi'): ?>
    <div class="profil-section">
        <div class="profil-section-header">
            <span class="section-label">Arah &amp; Tujuan</span>
            <h2>Visi &amp; Misi</h2>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
            <div class="card profil-visi-card">
                <div class="profil-visi-icon" style="background:#eff6ff;color:var(--primary)">&#127919;</div>
                <h3 style="color:var(--primary);margin-bottom:.85rem">Visi</h3>
                <p style="line-height:1.9;color:var(--mid);font-size:.95rem"><?= nl2br($p('school_vision')) ?></p>
            </div>
            <div class="card profil-visi-card">
                <div class="profil-visi-icon" style="background:#f0fdf4;color:var(--success)">&#127919;</div>
                <h3 style="color:var(--success);margin-bottom:.85rem">Misi</h3>
                <div style="line-height:1.9;color:var(--mid);font-size:.95rem"><?= nl2br($p('school_mission')) ?></div>
            </div>
        </div>
    </div>

    <!-- ===== KEPALA SEKOLAH ===== -->
    <?php elseif ($active_tab === 'kepala'): ?>
    <div class="profil-section">
        <div class="profil-section-header">
            <span class="section-label">Pimpinan</span>
            <h2>Kepala Sekolah</h2>
        </div>
        <!-- Pakai gaya sama dengan beranda -->
        <div class="home-principal-inner" style="max-width:900px">
            <div class="home-principal-photo">
                <?php if (!empty($profile['principal_photo'])): ?>
                    <img src="assets/uploads/<?= htmlspecialchars($profile['principal_photo']) ?>"
                         alt="<?= $p('principal_name') ?>">
                <?php else: ?>
                    <div class="principal-avatar-placeholder">&#128100;</div>
                <?php endif; ?>
                <div class="home-principal-name" style="margin-top:.85rem">
                    <strong><?= $p('principal_name') ?></strong>
                    <span>Kepala Sekolah</span>
                </div>
            </div>
            <div class="home-principal-content">
                <span class="section-label">Sambutan</span>
                <h3>Melangkah Menuju Prestasi</h3>
                <blockquote>
                    "<?= nl2br($p('principal_message')) ?>"
                </blockquote>
            </div>
        </div>
    </div>

    <!-- ===== FASILITAS ===== -->
    <?php elseif ($active_tab === 'fasilitas'): ?>
    <div class="profil-section">
        <div class="profil-section-header">
            <span class="section-label">Sarana &amp; Prasarana</span>
            <h2>Fasilitas Sekolah</h2>
        </div>
        <div class="facility-grid">
            <?php
            $facilities = array_filter(array_map('trim', explode(',', $profile['facilities'] ?? '')));
            $icons = ['&#128187;','&#128300;','&#128218;','&#127974;','&#127963;',
                      '&#9917;','&#128336;','&#127973;','&#128250;','&#128138;',
                      '&#127981;','&#128203;','&#127925;','&#127979;','&#128137;'];
            $i = 0;
            foreach ($facilities as $f):
                $icon = $icons[$i % count($icons)]; $i++;
            ?>
            <div class="facility-item-lg">
                <div class="facility-icon-lg"><?= $icon ?></div>
                <span><?= htmlspecialchars(trim($f)) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== EKSKUL ===== -->
    <?php elseif ($active_tab === 'ekskul'): ?>
    <div class="profil-section">
        <div class="profil-section-header">
            <span class="section-label">Pengembangan Diri</span>
            <h2>Kegiatan Ekstrakurikuler</h2>
        </div>
        <div class="ekskul-grid-lg">
            <?php
            $ekskuls = array_filter(array_map('trim', explode(',', $profile['extracurricular'] ?? '')));
            $eicons  = ['&#9917;','&#127944;','&#127992;','&#127996;','&#128138;',
                        '&#127928;','&#128251;','&#127981;','&#128218;','&#127758;',
                        '&#128247;','&#127775;','&#127884;','&#9878;','&#128104;'];
            $colors  = ['#eff6ff','#f0fdf4','#fef3c7','#fdf2f8','#f0f9ff',
                        '#fff7ed','#fafafa','#f0fdf4','#eff6ff','#fef3c7'];
            $i = 0;
            foreach ($ekskuls as $e):
                $icon  = $eicons[$i % count($eicons)];
                $color = $colors[$i % count($colors)];
                $i++;
            ?>
            <div class="ekskul-item-lg" style="background:<?= $color ?>">
                <span class="ekskul-icon-lg"><?= $icon ?></span>
                <span><?= htmlspecialchars(trim($e)) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== PRESTASI ===== -->
    <?php elseif ($active_tab === 'prestasi'): ?>
    <div class="profil-section">
        <div class="profil-section-header">
            <span class="section-label">Capaian</span>
            <h2>Prestasi Sekolah</h2>
        </div>
        <div class="prestasi-list">
            <?php
            $achievements = array_filter(array_map('trim', explode(',', $profile['achievements'] ?? '')));
            $no = 1;
            foreach ($achievements as $ach):
            ?>
            <div class="prestasi-item">
                <div class="prestasi-num">
                    <span>&#127942;</span>
                    <strong><?= $no++ ?></strong>
                </div>
                <div class="prestasi-body">
                    <p><?= htmlspecialchars(trim($ach)) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== KONTAK ===== -->
    <?php elseif ($active_tab === 'kontak'): ?>
    <div class="profil-section">
        <div class="profil-section-header">
            <span class="section-label">Hubungi Kami</span>
            <h2>Informasi Kontak</h2>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">
            <div>
                <div class="kontak-grid">
                    <div class="kontak-card">
                        <div class="kontak-icon" style="background:#eff6ff;color:var(--primary)">&#127968;</div>
                        <strong>Nama Sekolah</strong>
                        <p><?= $p('school_name') ?></p>
                    </div>
                    <div class="kontak-card">
                        <div class="kontak-icon" style="background:#fef3c7;color:#d97706">&#128205;</div>
                        <strong>Alamat</strong>
                        <p><?= nl2br($p('school_address')) ?></p>
                    </div>
                    <div class="kontak-card">
                        <div class="kontak-icon" style="background:#f0fdf4;color:var(--success)">&#128222;</div>
                        <strong>Telepon</strong>
                        <p><?= $p('school_phone') ?></p>
                    </div>
                    <div class="kontak-card">
                        <div class="kontak-icon" style="background:#fdf2f8;color:#9333ea">&#128140;</div>
                        <strong>Email</strong>
                        <p><a href="mailto:<?= $p('school_email') ?>"><?= $p('school_email') ?></a></p>
                    </div>
                    <div class="kontak-card">
                        <div class="kontak-icon" style="background:#f0f9ff;color:#0284c7">&#127760;</div>
                        <strong>Website</strong>
                        <p><?= $p('school_website') ?></p>
                    </div>
                </div>

                <?php if (!empty($profile['social_instagram']) || !empty($profile['social_facebook']) || !empty($profile['social_youtube'])): ?>
                <div class="card" style="margin-top:1rem">
                    <h4 style="margin-bottom:.85rem;font-size:.9rem;color:var(--mid)">&#127908; MEDIA SOSIAL</h4>
                    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                        <?php if (!empty($profile['social_instagram'])): ?>
                            <a href="<?= htmlspecialchars($profile['social_instagram']) ?>" target="_blank"
                               class="social-btn" style="background:#e1306c">&#128247; Instagram</a>
                        <?php endif; ?>
                        <?php if (!empty($profile['social_facebook'])): ?>
                            <a href="<?= htmlspecialchars($profile['social_facebook']) ?>" target="_blank"
                               class="social-btn" style="background:#1877f2">&#128172; Facebook</a>
                        <?php endif; ?>
                        <?php if (!empty($profile['social_youtube'])): ?>
                            <a href="<?= htmlspecialchars($profile['social_youtube']) ?>" target="_blank"
                               class="social-btn" style="background:#ff0000">&#127909; YouTube</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="card" style="padding:0;overflow:hidden">
                <div class="map-placeholder">
                    <span>&#128205;</span>
                    <p><?= $p('school_name') ?></p>
                    <small><?= $p('school_address') ?></small>
                    <a href="https://maps.google.com?q=<?= urlencode($profile['school_address'] ?? '') ?>"
                       target="_blank" class="btn btn-primary btn-sm" style="margin-top:.75rem">
                        &#128269; Buka di Google Maps
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ===== FOOTER ===== -->
<footer class="site-footer">
    <div class="footer-top">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <img src="assets/logo.jpg" alt="Logo" style="width:44px;height:44px;object-fit:contain;border-radius:6px;margin-bottom:.5rem;display:block">
                    <strong><?= $p('school_name', 'Portal Berita Sekolah') ?></strong>
                    <p><?= $p('school_vision', 'Informasi terkini dan terpercaya.') ?></p>
                    <?php if (!empty($profile['social_instagram']) || !empty($profile['social_facebook']) || !empty($profile['social_youtube'])): ?>
                    <div class="footer-social">
                        <?php if (!empty($profile['social_instagram'])): ?>
                            <a href="<?= htmlspecialchars($profile['social_instagram']) ?>" target="_blank">&#128247;</a>
                        <?php endif; ?>
                        <?php if (!empty($profile['social_facebook'])): ?>
                            <a href="<?= htmlspecialchars($profile['social_facebook']) ?>" target="_blank">&#128172;</a>
                        <?php endif; ?>
                        <?php if (!empty($profile['social_youtube'])): ?>
                            <a href="<?= htmlspecialchars($profile['social_youtube']) ?>" target="_blank">&#127909;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h4>Profil Sekolah</h4>
                    <ul>
                        <?php foreach ($tabs as $key => [$icon, $label]): ?>
                        <li><a href="profil.php?tab=<?= $key ?>"><?= $icon ?> <?= $label ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h4>Berita</h4>
                    <ul>
                        <li><a href="index.php">&#127968; Beranda</a></li>
                        <?php
                        $cats_f = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC LIMIT 6");
                        while ($cf = mysqli_fetch_assoc($cats_f)):
                        ?>
                        <li><a href="index.php?category=<?= $cf['id'] ?>"><?= htmlspecialchars($cf['name']) ?></a></li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <div>
                    <h4>Kontak</h4>
                    <ul class="footer-contact">
                        <?php if (!empty($profile['school_address'])): ?>
                        <li><span>&#128205;</span><?= htmlspecialchars($profile['school_address']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($profile['school_phone'])): ?>
                        <li><span>&#128222;</span><?= htmlspecialchars($profile['school_phone']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($profile['school_email'])): ?>
                        <li><span>&#128140;</span><a href="mailto:<?= htmlspecialchars($profile['school_email']) ?>"><?= htmlspecialchars($profile['school_email']) ?></a></li>
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

<a href="#" class="back-to-top" id="backToTop" aria-label="Kembali ke atas">&#8679;</a>
<?php require_once 'config/footer_widgets.php'; ?>
<script src="assets/app.js"></script>
</body>
</html>
