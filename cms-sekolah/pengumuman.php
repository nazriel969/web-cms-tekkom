<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = getDB();

// Detail pengumuman jika ada slug
$slug   = trim($_GET['slug'] ?? '');
$detail = null;

if ($slug !== '') {
    $stmtD = $pdo->prepare("SELECT * FROM pengumuman WHERE slug = ? LIMIT 1");
    $stmtD->execute([$slug]);
    $detail = $stmtD->fetch();
}

// Pagination daftar
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$total      = (int)$pdo->query("SELECT COUNT(*) FROM pengumuman")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM pengumuman ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$perPage, $offset]);
$list = $stmt->fetchAll();

$pageTitle = $detail ? htmlspecialchars($detail['judul']) : 'Pengumuman';
include __DIR__ . '/includes/header.php';
?>

<!-- Banner -->
<div class="bg-gradient-to-r from-amber-600 to-amber-500 text-white py-12">
    <div class="container mx-auto px-4">
        <nav class="text-sm text-amber-200 mb-2">
            <a href="/cms-sekolah/index.php" class="hover:text-white">Beranda</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <?php if ($detail): ?>
            <a href="/cms-sekolah/pengumuman.php" class="hover:text-white">Pengumuman</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white line-clamp-1"><?= htmlspecialchars($detail['judul']) ?></span>
            <?php else: ?>
            <span class="text-white">Pengumuman</span>
            <?php endif; ?>
        </nav>
        <h1 class="text-3xl font-bold flex items-center gap-3">
            <i class="fas fa-bullhorn"></i>
            <?= $detail ? htmlspecialchars($detail['judul']) : 'Pengumuman Resmi' ?>
        </h1>
        <?php if (!$detail): ?>
        <p class="text-amber-100 mt-1">Informasi dan pemberitahuan resmi dari sekolah</p>
        <?php endif; ?>
    </div>
</div>

<div class="container mx-auto px-4 py-10">

    <?php if ($detail): ?>
    <!-- ===== DETAIL PENGUMUMAN ===== -->
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 md:p-8">
                <!-- Meta -->
                <div class="flex flex-wrap items-center gap-3 mb-5 pb-5 border-b border-gray-100">
                    <span class="inline-flex items-center gap-1.5 bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <i class="fas fa-bullhorn text-xs"></i> Pengumuman Resmi
                    </span>
                    <span class="text-sm text-gray-500 flex items-center gap-1">
                        <i class="far fa-calendar"></i> <?= formatTanggal($detail['created_at']) ?>
                    </span>
                </div>

                <!-- Judul -->
                <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 mb-6 leading-tight">
                    <?= htmlspecialchars($detail['judul']) ?>
                </h1>

                <!-- Isi -->
                <div class="prose prose-sm md:prose max-w-none text-gray-700 leading-relaxed
                            prose-headings:font-bold prose-headings:text-gray-900
                            prose-a:text-amber-600">
                    <?= $detail['isi_pengumuman'] ?>
                </div>

                <!-- Lampiran -->
                <?php if (!empty($detail['file_lampiran'])): ?>
                <div class="mt-8 p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-center gap-4">
                    <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-paperclip text-amber-600"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800">File Lampiran</p>
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($detail['file_lampiran']) ?></p>
                    </div>
                    <a href="/cms-sekolah/assets/uploads/lampiran/<?= urlencode($detail['file_lampiran']) ?>"
                       download
                       class="flex-shrink-0 bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                        <i class="fas fa-download"></i> Unduh
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Kembali -->
        <div class="mt-6">
            <a href="/cms-sekolah/pengumuman.php"
               class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-primary-700 transition-colors">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pengumuman
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- ===== DAFTAR PENGUMUMAN ===== -->
    <?php if (empty($list)): ?>
    <div class="text-center py-20 text-gray-400">
        <i class="fas fa-bullhorn text-6xl mb-4 block opacity-30"></i>
        <p class="text-lg font-medium">Belum ada pengumuman.</p>
    </div>
    <?php else: ?>

    <div class="max-w-3xl mx-auto space-y-4">
        <?php foreach ($list as $idx => $p): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 hover:shadow-md hover:border-amber-200 transition-all group overflow-hidden">
            <div class="flex items-start gap-4 p-5">
                <!-- Nomor / Icon -->
                <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <span class="text-amber-600 font-bold text-lg"><?= ($offset + $idx + 1) ?></span>
                </div>

                <div class="flex-1 min-w-0">
                    <a href="/cms-sekolah/pengumuman.php?slug=<?= urlencode($p['slug']) ?>"
                       class="block text-base font-bold text-gray-900 group-hover:text-amber-700 transition-colors leading-snug mb-2">
                        <?= htmlspecialchars($p['judul']) ?>
                    </a>
                    <p class="text-sm text-gray-500 line-clamp-2 mb-3">
                        <?= truncate(strip_tags($p['isi_pengumuman']), 150) ?>
                    </p>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
                        <span class="flex items-center gap-1">
                            <i class="far fa-calendar"></i> <?= formatTanggal($p['created_at']) ?>
                        </span>
                        <?php if (!empty($p['file_lampiran'])): ?>
                        <span class="flex items-center gap-1 text-amber-600 font-medium">
                            <i class="fas fa-paperclip"></i> Ada Lampiran
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="/cms-sekolah/pengumuman.php?slug=<?= urlencode($p['slug']) ?>"
                   class="flex-shrink-0 w-9 h-9 bg-gray-100 group-hover:bg-amber-100 rounded-xl flex items-center justify-center transition-colors">
                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-amber-600 text-sm transition-colors"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center items-center gap-2 mt-10">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-sm hover:bg-amber-50 hover:border-amber-300 transition-colors">
            <i class="fas fa-chevron-left text-xs mr-1"></i> Prev
        </a>
        <?php endif; ?>
        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
        <a href="?page=<?= $i ?>"
           class="px-4 py-2 rounded-xl text-sm transition-colors <?= $i===$page ? 'bg-amber-500 text-white shadow' : 'bg-white border border-gray-200 hover:bg-amber-50' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-sm hover:bg-amber-50 hover:border-amber-300 transition-colors">
            Next <i class="fas fa-chevron-right text-xs ml-1"></i>
        </a>
        <?php endif; ?>
    </div>
    <p class="text-center text-xs text-gray-400 mt-3">Halaman <?= $page ?> dari <?= $totalPages ?> &bull; <?= $total ?> pengumuman</p>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
