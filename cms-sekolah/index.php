<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = getDB();

// Profil sekolah
$profil = $pdo->query("SELECT * FROM profil_sekolah LIMIT 1")->fetch();

// Berita terbaru (6 artikel, status published)
$stmtBerita = $pdo->prepare("
    SELECT b.id, b.judul, b.slug, b.gambar, b.created_at, b.views,
           k.nama_kategori, u.nama_lengkap
    FROM berita b
    LEFT JOIN kategori k ON b.kategori_id = k.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.status = 'published'
    ORDER BY b.created_at DESC
    LIMIT 6
");
$stmtBerita->execute();
$beritaTerbaru = $stmtBerita->fetchAll();

// Pengumuman terbaru (5 item)
$stmtPengumuman = $pdo->prepare("
    SELECT id, judul, slug, created_at
    FROM pengumuman
    ORDER BY created_at DESC
    LIMIT 5
");
$stmtPengumuman->execute();
$pengumumanTerbaru = $stmtPengumuman->fetchAll();

// Galeri terbaru (4 foto)
$stmtGaleri = $pdo->prepare("SELECT id, judul, foto, keterangan FROM galeri ORDER BY created_at DESC LIMIT 4");
$stmtGaleri->execute();
$galeriTerbaru = $stmtGaleri->fetchAll();

$pageTitle = 'Beranda';
include __DIR__ . '/includes/header.php';
?>

<!-- ======================== HERO ======================== -->
<section class="relative bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 text-white overflow-hidden">
    <!-- Background pattern -->
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-96 h-96 bg-white rounded-full -translate-y-1/2 translate-x-1/3"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-white rounded-full translate-y-1/2 -translate-x-1/3"></div>
    </div>
    <div class="relative container mx-auto px-4 py-20 md:py-28 flex flex-col md:flex-row items-center gap-10">
        <div class="flex-1 text-center md:text-left">
            <span class="inline-block bg-yellow-400 text-yellow-900 text-xs font-semibold px-3 py-1 rounded-full mb-4 uppercase tracking-wider">
                Selamat Datang
            </span>
            <h1 class="text-3xl md:text-5xl font-extrabold leading-tight mb-4">
                <?= htmlspecialchars($profil['nama_sekolah'] ?? 'Nama Sekolah') ?>
            </h1>
            <p class="text-primary-200 text-lg mb-8 max-w-xl mx-auto md:mx-0">
                <?= htmlspecialchars(truncate($profil['sambutan_kepsek'] ?? 'Website resmi sekolah kami.', 180)) ?>
            </p>
            <div class="flex flex-wrap gap-3 justify-center md:justify-start">
                <a href="/cms-sekolah/profil.php"
                   class="bg-white text-primary-800 font-semibold px-6 py-3 rounded-xl hover:bg-primary-50 transition-all shadow-lg hover:shadow-xl text-sm">
                    <i class="fas fa-school mr-2"></i>Profil Sekolah
                </a>
                <a href="/cms-sekolah/berita.php"
                   class="bg-yellow-400 text-yellow-900 font-semibold px-6 py-3 rounded-xl hover:bg-yellow-300 transition-all shadow-lg hover:shadow-xl text-sm">
                    <i class="fas fa-newspaper mr-2"></i>Baca Berita
                </a>
            </div>
        </div>
        <?php if (!empty($profil['foto_kepsek'])): ?>
        <div class="hidden md:block flex-shrink-0">
            <div class="relative">
                <div class="w-64 h-64 rounded-2xl overflow-hidden border-4 border-white/30 shadow-2xl">
                    <img src="/cms-sekolah/assets/uploads/profil/<?= htmlspecialchars($profil['foto_kepsek']) ?>"
                         alt="Kepala Sekolah" class="w-full h-full object-cover">
                </div>
                <div class="absolute -bottom-4 -left-4 bg-white text-primary-800 px-4 py-2 rounded-xl shadow-lg text-sm font-semibold">
                    <i class="fas fa-user-tie mr-1 text-primary-600"></i> Kepala Sekolah
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- Wave -->
    <div class="absolute bottom-0 left-0 right-0">
        <svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 60L60 50C120 40 240 20 360 20C480 20 600 40 720 45C840 50 960 40 1080 33.3C1200 27 1320 33 1380 36.7L1440 40V60H1380C1320 60 1200 60 1080 60C960 60 840 60 720 60C600 60 480 60 360 60C240 60 120 60 60 60H0Z" fill="rgb(249 250 251)"/>
        </svg>
    </div>
</section>

<!-- ======================== STATS ======================== -->
<?php
$stats = [
    ['icon'=>'fa-newspaper',  'color'=>'text-blue-600',  'bg'=>'bg-blue-50',  'val'=>$pdo->query("SELECT COUNT(*) FROM berita WHERE status='published'")->fetchColumn(),  'label'=>'Total Berita'],
    ['icon'=>'fa-bullhorn',   'color'=>'text-amber-600', 'bg'=>'bg-amber-50', 'val'=>$pdo->query("SELECT COUNT(*) FROM pengumuman")->fetchColumn(), 'label'=>'Pengumuman'],
    ['icon'=>'fa-images',     'color'=>'text-purple-600','bg'=>'bg-purple-50','val'=>$pdo->query("SELECT COUNT(*) FROM galeri")->fetchColumn(),     'label'=>'Foto Galeri'],
    ['icon'=>'fa-users',      'color'=>'text-green-600', 'bg'=>'bg-green-50', 'val'=>$pdo->query("SELECT COALESCE(SUM(views),0) FROM berita")->fetchColumn(), 'label'=>'Total Views'],
];
?>
<section class="container mx-auto px-4 -mt-4 mb-12 relative z-10">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php foreach ($stats as $s): ?>
        <div class="bg-white rounded-2xl shadow-md p-5 flex items-center gap-4 hover:shadow-lg transition-shadow">
            <div class="w-12 h-12 <?= $s['bg'] ?> rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas <?= $s['icon'] ?> <?= $s['color'] ?> text-xl"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-800"><?= number_format($s['val']) ?></div>
                <div class="text-xs text-gray-500"><?= $s['label'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ======================== BERITA TERBARU ======================== -->
<section class="container mx-auto px-4 mb-16">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Berita Terbaru</h2>
            <p class="text-gray-500 text-sm mt-1">Informasi dan kegiatan terkini sekolah</p>
        </div>
        <a href="/cms-sekolah/berita.php"
           class="text-sm text-primary-600 hover:text-primary-800 font-medium flex items-center gap-1 group">
            Lihat Semua <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </a>
    </div>

    <?php if (empty($beritaTerbaru)): ?>
    <div class="text-center py-16 text-gray-400">
        <i class="fas fa-newspaper text-5xl mb-3 block"></i>
        <p>Belum ada berita yang dipublikasikan.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($beritaTerbaru as $i => $b): ?>
        <article class="bg-white rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden group flex flex-col <?= $i === 0 ? 'md:col-span-2 lg:col-span-1' : '' ?>">
            <a href="/cms-sekolah/detail-berita.php?slug=<?= urlencode($b['slug']) ?>" class="block overflow-hidden">
                <?php if (!empty($b['gambar'])): ?>
                <img src="/cms-sekolah/assets/uploads/berita/<?= htmlspecialchars($b['gambar']) ?>"
                     alt="<?= htmlspecialchars($b['judul']) ?>"
                     class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300">
                <?php else: ?>
                <div class="w-full h-48 bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center">
                    <i class="fas fa-newspaper text-primary-400 text-4xl"></i>
                </div>
                <?php endif; ?>
            </a>
            <div class="p-5 flex flex-col flex-1">
                <?php if (!empty($b['nama_kategori'])): ?>
                <span class="inline-block bg-primary-100 text-primary-700 text-xs font-medium px-2 py-0.5 rounded-full mb-2 self-start">
                    <?= htmlspecialchars($b['nama_kategori']) ?>
                </span>
                <?php endif; ?>
                <h3 class="font-bold text-gray-900 mb-2 line-clamp-2 leading-snug group">
                    <a href="/cms-sekolah/detail-berita.php?slug=<?= urlencode($b['slug']) ?>"
                       class="hover:text-primary-700 transition-colors"><?= htmlspecialchars($b['judul']) ?></a>
                </h3>
                <div class="flex items-center gap-3 text-xs text-gray-400 mt-auto pt-3 border-t border-gray-50">
                    <span><i class="far fa-calendar mr-1"></i><?= formatTanggal($b['created_at']) ?></span>
                    <span><i class="far fa-eye mr-1"></i><?= number_format($b['views']) ?></span>
                    <?php if (!empty($b['nama_lengkap'])): ?>
                    <span class="ml-auto"><i class="far fa-user mr-1"></i><?= htmlspecialchars($b['nama_lengkap']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ======================== PENGUMUMAN & GALERI ======================== -->
<section class="bg-gray-100 py-14">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">

            <!-- Pengumuman -->
            <div class="lg:col-span-2">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-bullhorn text-amber-500"></i> Pengumuman
                    </h2>
                    <a href="/cms-sekolah/pengumuman.php" class="text-xs text-primary-600 hover:underline">Lihat Semua</a>
                </div>
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <?php if (empty($pengumumanTerbaru)): ?>
                    <p class="p-6 text-center text-gray-400 text-sm">Belum ada pengumuman.</p>
                    <?php else: ?>
                    <?php foreach ($pengumumanTerbaru as $p): ?>
                    <a href="/cms-sekolah/pengumuman.php?slug=<?= urlencode($p['slug']) ?>"
                       class="flex items-start gap-3 px-5 py-4 border-b border-gray-50 hover:bg-primary-50 transition-colors group last:border-0">
                        <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-bell text-amber-500 text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 group-hover:text-primary-700 line-clamp-2 leading-snug">
                                <?= htmlspecialchars($p['judul']) ?>
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                <i class="far fa-calendar mr-1"></i><?= formatTanggal($p['created_at']) ?>
                            </p>
                        </div>
                        <i class="fas fa-chevron-right text-gray-300 group-hover:text-primary-500 text-xs mt-1 flex-shrink-0"></i>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Galeri -->
            <div class="lg:col-span-3">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-images text-purple-500"></i> Galeri Kegiatan
                    </h2>
                    <a href="/cms-sekolah/galeri.php" class="text-xs text-primary-600 hover:underline">Lihat Semua</a>
                </div>
                <?php if (empty($galeriTerbaru)): ?>
                <div class="bg-white rounded-2xl p-8 text-center text-gray-400 text-sm shadow-sm">
                    <i class="fas fa-images text-3xl mb-2 block"></i> Belum ada foto.
                </div>
                <?php else: ?>
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach ($galeriTerbaru as $g): ?>
                    <a href="/cms-sekolah/galeri.php" class="relative overflow-hidden rounded-xl group block aspect-video bg-gray-200">
                        <?php if (!empty($g['foto'])): ?>
                        <img src="/cms-sekolah/assets/uploads/galeri/<?= htmlspecialchars($g['foto']) ?>"
                             alt="<?= htmlspecialchars($g['judul']) ?>"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                        <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-br from-purple-100 to-purple-200 flex items-center justify-center">
                            <i class="fas fa-image text-purple-300 text-2xl"></i>
                        </div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-end">
                            <p class="text-white text-xs p-2 font-medium leading-tight">
                                <?= htmlspecialchars($g['judul']) ?>
                            </p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ======================== SAMBUTAN KEPSEK ======================== -->
<?php if (!empty($profil['sambutan_kepsek'])): ?>
<section class="container mx-auto px-4 py-16">
    <div class="bg-gradient-to-r from-primary-50 to-blue-50 rounded-3xl p-8 md:p-12 flex flex-col md:flex-row gap-8 items-center border border-primary-100">
        <?php if (!empty($profil['foto_kepsek'])): ?>
        <div class="flex-shrink-0">
            <img src="/cms-sekolah/assets/uploads/profil/<?= htmlspecialchars($profil['foto_kepsek']) ?>"
                 alt="Kepala Sekolah"
                 class="w-32 h-32 md:w-44 md:h-44 rounded-2xl object-cover shadow-xl border-4 border-white">
        </div>
        <?php endif; ?>
        <div>
            <span class="text-xs font-semibold text-primary-600 uppercase tracking-wider mb-2 block">Sambutan</span>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Kepala Sekolah</h2>
            <blockquote class="text-gray-600 leading-relaxed italic border-l-4 border-primary-300 pl-4">
                "<?= htmlspecialchars(truncate($profil['sambutan_kepsek'], 400)) ?>"
            </blockquote>
            <a href="/cms-sekolah/profil.php" class="mt-4 inline-flex items-center gap-2 text-sm text-primary-600 font-medium hover:text-primary-800 transition-colors">
                Baca Selengkapnya <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
