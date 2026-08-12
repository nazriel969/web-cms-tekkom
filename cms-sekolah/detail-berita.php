<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo  = getDB();
$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    redirect('/cms-sekolah/berita.php');
}

// Ambil berita
$stmt = $pdo->prepare("
    SELECT b.*, k.nama_kategori, k.slug AS kategori_slug, u.nama_lengkap
    FROM berita b
    LEFT JOIN kategori k ON b.kategori_id = k.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.slug = ? AND b.status = 'published'
    LIMIT 1
");
$stmt->execute([$slug]);
$berita = $stmt->fetch();

if (!$berita) {
    http_response_code(404);
    include __DIR__ . '/includes/header.php';
    echo '<div class="container mx-auto px-4 py-32 text-center text-gray-400"><i class="fas fa-newspaper text-6xl mb-4 block"></i><h2 class="text-2xl font-bold">Berita tidak ditemukan</h2><a href="/cms-sekolah/berita.php" class="mt-4 inline-block text-primary-600 hover:underline">Kembali ke Daftar Berita</a></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Increment views (anti-spam: cek session)
$viewKey = 'viewed_berita_' . $berita['id'];
if (empty($_SESSION[$viewKey])) {
    $pdo->prepare("UPDATE berita SET views = views + 1 WHERE id = ?")->execute([$berita['id']]);
    $_SESSION[$viewKey] = true;
    $berita['views']++;
}

// Berita terkait (same category, exclude current)
$related = [];
if (!empty($berita['kategori_id'])) {
    $stmtRel = $pdo->prepare("
        SELECT id, judul, slug, gambar, created_at
        FROM berita
        WHERE kategori_id = ? AND id != ? AND status = 'published'
        ORDER BY created_at DESC
        LIMIT 4
    ");
    $stmtRel->execute([$berita['kategori_id'], $berita['id']]);
    $related = $stmtRel->fetchAll();
}

$pageTitle = htmlspecialchars($berita['judul']);
include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div class="bg-gray-100 border-b border-gray-200 py-3">
    <div class="container mx-auto px-4 text-sm text-gray-500 flex flex-wrap items-center gap-1.5">
        <a href="/cms-sekolah/index.php" class="hover:text-primary-700">Beranda</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <a href="/cms-sekolah/berita.php" class="hover:text-primary-700">Berita</a>
        <?php if (!empty($berita['nama_kategori'])): ?>
        <i class="fas fa-chevron-right text-xs"></i>
        <a href="/cms-sekolah/berita.php?kategori=<?= urlencode($berita['kategori_id']) ?>" class="hover:text-primary-700">
            <?= htmlspecialchars($berita['nama_kategori']) ?>
        </a>
        <?php endif; ?>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-700 line-clamp-1 max-w-[200px]"><?= htmlspecialchars($berita['judul']) ?></span>
    </div>
</div>

<div class="container mx-auto px-4 py-10">
    <div class="flex flex-col lg:flex-row gap-8">

        <!-- Artikel Utama -->
        <article class="flex-1 min-w-0">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Thumbnail -->
                <?php if (!empty($berita['gambar'])): ?>
                <div class="w-full max-h-[480px] overflow-hidden">
                    <img src="/cms-sekolah/assets/uploads/berita/<?= htmlspecialchars($berita['gambar']) ?>"
                         alt="<?= htmlspecialchars($berita['judul']) ?>"
                         class="w-full object-cover">
                </div>
                <?php endif; ?>

                <div class="p-6 md:p-8">
                    <!-- Meta -->
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <?php if (!empty($berita['nama_kategori'])): ?>
                        <a href="/cms-sekolah/berita.php?kategori=<?= urlencode($berita['kategori_id']) ?>"
                           class="bg-primary-100 text-primary-700 text-xs font-semibold px-3 py-1 rounded-full hover:bg-primary-200 transition-colors">
                            <?= htmlspecialchars($berita['nama_kategori']) ?>
                        </a>
                        <?php endif; ?>
                        <span class="text-xs text-gray-400"><i class="far fa-calendar mr-1"></i><?= formatTanggal($berita['created_at']) ?></span>
                        <span class="text-xs text-gray-400"><i class="far fa-eye mr-1"></i><?= number_format($berita['views']) ?> dibaca</span>
                        <?php if (!empty($berita['nama_lengkap'])): ?>
                        <span class="text-xs text-gray-400"><i class="far fa-user mr-1"></i><?= htmlspecialchars($berita['nama_lengkap']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Judul -->
                    <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 leading-tight mb-6">
                        <?= htmlspecialchars($berita['judul']) ?>
                    </h1>

                    <!-- Isi Berita -->
                    <div class="prose prose-sm md:prose max-w-none text-gray-700 leading-relaxed
                                prose-headings:font-bold prose-headings:text-gray-900
                                prose-a:text-primary-600 prose-a:no-underline hover:prose-a:underline
                                prose-img:rounded-xl prose-img:shadow-md">
                        <?= $berita['isi_berita'] ?>
                    </div>

                    <!-- Share -->
                    <div class="mt-8 pt-6 border-t border-gray-100 flex flex-wrap items-center gap-3">
                        <span class="text-sm font-medium text-gray-600">Bagikan:</span>
                        <?php $shareUrl = urlencode("http://{$_SERVER['HTTP_HOST']}/cms-sekolah/detail-berita.php?slug={$berita['slug']}"); ?>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener"
                           class="flex items-center gap-2 bg-blue-600 text-white text-xs px-4 py-2 rounded-full hover:bg-blue-700 transition-colors">
                            <i class="fab fa-facebook-f"></i> Facebook
                        </a>
                        <a href="https://api.whatsapp.com/send?text=<?= urlencode($berita['judul']) . '%20' . $shareUrl ?>" target="_blank" rel="noopener"
                           class="flex items-center gap-2 bg-green-500 text-white text-xs px-4 py-2 rounded-full hover:bg-green-600 transition-colors">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($berita['judul']) ?>&url=<?= $shareUrl ?>" target="_blank" rel="noopener"
                           class="flex items-center gap-2 bg-sky-500 text-white text-xs px-4 py-2 rounded-full hover:bg-sky-600 transition-colors">
                            <i class="fab fa-twitter"></i> Twitter
                        </a>
                    </div>
                </div>
            </div>

            <!-- Berita Terkait -->
            <?php if (!empty($related)): ?>
            <div class="mt-8">
                <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-link text-primary-500 text-base"></i> Berita Terkait
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($related as $r): ?>
                    <a href="/cms-sekolah/detail-berita.php?slug=<?= urlencode($r['slug']) ?>"
                       class="flex gap-3 bg-white rounded-xl p-3 border border-gray-100 hover:shadow-md hover:border-primary-200 transition-all group">
                        <?php if (!empty($r['gambar'])): ?>
                        <img src="/cms-sekolah/assets/uploads/berita/<?= htmlspecialchars($r['gambar']) ?>"
                             alt="" class="w-16 h-16 rounded-lg object-cover flex-shrink-0">
                        <?php else: ?>
                        <div class="w-16 h-16 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-newspaper text-primary-400"></i>
                        </div>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 group-hover:text-primary-700 line-clamp-2 leading-snug">
                                <?= htmlspecialchars($r['judul']) ?>
                            </p>
                            <p class="text-xs text-gray-400 mt-1"><?= formatTanggal($r['created_at']) ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </article>

        <!-- Sidebar -->
        <aside class="w-full lg:w-72 flex-shrink-0 space-y-6">
            <!-- Berita Populer -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2 text-sm">
                    <i class="fas fa-fire text-orange-500"></i> Berita Populer
                </h3>
                <?php
                $popular = $pdo->prepare("SELECT id, judul, slug, gambar, views, created_at FROM berita WHERE status='published' ORDER BY views DESC LIMIT 5");
                $popular->execute();
                foreach ($popular->fetchAll() as $idx => $pop):
                ?>
                <a href="/cms-sekolah/detail-berita.php?slug=<?= urlencode($pop['slug']) ?>"
                   class="flex gap-3 items-start py-2.5 border-b border-gray-50 last:border-0 group hover:bg-gray-50 -mx-1 px-1 rounded-lg transition-colors">
                    <span class="w-6 h-6 bg-primary-100 text-primary-700 rounded-full text-xs font-bold flex items-center justify-center flex-shrink-0 mt-0.5">
                        <?= $idx + 1 ?>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-700 group-hover:text-primary-700 line-clamp-2 leading-snug">
                            <?= htmlspecialchars($pop['judul']) ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5"><i class="far fa-eye mr-1"></i><?= number_format($pop['views']) ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Kategori -->
            <?php if (!empty($categories)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-bold text-gray-900 mb-4 text-sm flex items-center gap-2">
                    <i class="fas fa-tags text-primary-500"></i> Kategori
                </h3>
                <div class="space-y-1">
                    <?php
                    $catCounts = $pdo->query("
                        SELECT k.id, k.nama_kategori, k.slug, COUNT(b.id) AS total
                        FROM kategori k
                        LEFT JOIN berita b ON b.kategori_id = k.id AND b.status='published'
                        GROUP BY k.id
                        ORDER BY total DESC
                    ")->fetchAll();
                    foreach ($catCounts as $kat):
                    ?>
                    <a href="/cms-sekolah/berita.php?kategori=<?= $kat['id'] ?>"
                       class="flex justify-between items-center px-3 py-2 rounded-lg text-sm hover:bg-primary-50 hover:text-primary-700 transition-colors text-gray-700 group">
                        <span class="flex items-center gap-2">
                            <i class="fas fa-folder text-gray-300 group-hover:text-primary-400 text-xs"></i>
                            <?= htmlspecialchars($kat['nama_kategori']) ?>
                        </span>
                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full"><?= $kat['total'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
