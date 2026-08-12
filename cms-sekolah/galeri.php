<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = getDB();

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$total      = (int)$pdo->query("SELECT COUNT(*) FROM galeri")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM galeri ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$perPage, $offset]);
$list = $stmt->fetchAll();

$pageTitle = 'Galeri Kegiatan';
include __DIR__ . '/includes/header.php';
?>

<!-- Banner -->
<div class="bg-gradient-to-r from-purple-700 to-purple-500 text-white py-12">
    <div class="container mx-auto px-4">
        <nav class="text-sm text-purple-300 mb-2">
            <a href="/cms-sekolah/index.php" class="hover:text-white">Beranda</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white">Galeri</span>
        </nav>
        <h1 class="text-3xl font-bold flex items-center gap-3">
            <i class="fas fa-images"></i> Galeri Kegiatan
        </h1>
        <p class="text-purple-200 mt-1">Dokumentasi kegiatan dan momen berharga sekolah</p>
    </div>
</div>

<div class="container mx-auto px-4 py-10">

    <?php if (empty($list)): ?>
    <div class="text-center py-24 text-gray-400">
        <i class="fas fa-images text-7xl mb-4 block opacity-30"></i>
        <p class="text-lg font-medium">Belum ada foto di galeri.</p>
    </div>
    <?php else: ?>

    <!-- Info -->
    <p class="text-sm text-gray-500 mb-6">Menampilkan <?= count($list) ?> dari <?= $total ?> foto</p>

    <!-- Grid Galeri -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4" id="galeriGrid">
        <?php foreach ($list as $g): ?>
        <div class="group relative overflow-hidden rounded-2xl bg-gray-200 aspect-square cursor-pointer shadow-sm hover:shadow-lg transition-all duration-300"
             onclick="openLightbox('<?= !empty($g['foto']) ? '/cms-sekolah/assets/uploads/galeri/' . htmlspecialchars($g['foto']) : '' ?>', '<?= htmlspecialchars(addslashes($g['judul'])) ?>', '<?= htmlspecialchars(addslashes($g['keterangan'] ?? '')) ?>')">

            <?php if (!empty($g['foto'])): ?>
            <img src="/cms-sekolah/assets/uploads/galeri/<?= htmlspecialchars($g['foto']) ?>"
                 alt="<?= htmlspecialchars($g['judul']) ?>"
                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                 loading="lazy">
            <?php else: ?>
            <div class="w-full h-full bg-gradient-to-br from-purple-100 to-purple-200 flex items-center justify-center">
                <i class="fas fa-image text-purple-300 text-4xl"></i>
            </div>
            <?php endif; ?>

            <!-- Overlay -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-3">
                <p class="text-white text-xs font-semibold line-clamp-2 leading-snug">
                    <?= htmlspecialchars($g['judul']) ?>
                </p>
                <?php if (!empty($g['keterangan'])): ?>
                <p class="text-white/75 text-xs mt-0.5 line-clamp-1">
                    <?= htmlspecialchars($g['keterangan']) ?>
                </p>
                <?php endif; ?>
                <div class="mt-2 flex items-center gap-1 text-white/60 text-xs">
                    <i class="far fa-calendar text-xs"></i>
                    <?= formatTanggal($g['created_at']) ?>
                </div>
            </div>

            <!-- Zoom icon -->
            <div class="absolute top-3 right-3 w-8 h-8 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="fas fa-magnifying-glass-plus text-white text-xs"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center items-center gap-2 mt-10">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-sm hover:bg-purple-50 hover:border-purple-300 transition-colors">
            <i class="fas fa-chevron-left text-xs mr-1"></i> Prev
        </a>
        <?php endif; ?>
        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
        <a href="?page=<?= $i ?>"
           class="px-4 py-2 rounded-xl text-sm transition-colors <?= $i===$page ? 'bg-purple-600 text-white shadow' : 'bg-white border border-gray-200 hover:bg-purple-50' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-sm hover:bg-purple-50 hover:border-purple-300 transition-colors">
            Next <i class="fas fa-chevron-right text-xs ml-1"></i>
        </a>
        <?php endif; ?>
    </div>
    <p class="text-center text-xs text-gray-400 mt-3">Halaman <?= $page ?> dari <?= $totalPages ?></p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Lightbox Modal -->
<div id="lightbox" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4"
     onclick="if(event.target===this) closeLightbox()">
    <div class="relative max-w-4xl w-full">
        <!-- Close btn -->
        <button onclick="closeLightbox()"
                class="absolute -top-10 right-0 text-white/80 hover:text-white text-2xl transition-colors z-10">
            <i class="fas fa-xmark"></i>
        </button>
        <!-- Image -->
        <div class="bg-black rounded-2xl overflow-hidden shadow-2xl">
            <img id="lightboxImg" src="" alt=""
                 class="max-h-[75vh] w-full object-contain">
            <div id="lightboxCaption" class="p-4 bg-gray-900/95">
                <p id="lightboxTitle" class="text-white font-semibold text-sm"></p>
                <p id="lightboxDesc" class="text-gray-400 text-xs mt-1"></p>
            </div>
        </div>
    </div>
</div>

<script>
function openLightbox(src, title, desc) {
    if (!src) return;
    document.getElementById('lightboxImg').src   = src;
    document.getElementById('lightboxTitle').textContent = title;
    document.getElementById('lightboxDesc').textContent  = desc;
    document.getElementById('lightbox').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.add('hidden');
    document.getElementById('lightboxImg').src = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
