<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = getDB();

// Filter & Pencarian
$search      = trim($_GET['q'] ?? '');
$kategoriId  = (int)($_GET['kategori'] ?? 0);
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 9;
$offset      = ($page - 1) * $perPage;

// Build query
$params  = ['published'];
$where   = "WHERE b.status = ?";

if ($search !== '') {
    $where   .= " AND b.judul LIKE ?";
    $params[] = "%$search%";
}
if ($kategoriId > 0) {
    $where   .= " AND b.kategori_id = ?";
    $params[] = $kategoriId;
}

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM berita b $where");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch data
$stmtParams   = array_merge($params, [$perPage, $offset]);
$stmt = $pdo->prepare("
    SELECT b.id, b.judul, b.slug, b.gambar, b.created_at, b.views,
           k.nama_kategori, k.slug AS kategori_slug, u.nama_lengkap
    FROM berita b
    LEFT JOIN kategori k ON b.kategori_id = k.id
    LEFT JOIN users u ON b.user_id = u.id
    $where
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($stmtParams);
$beritaList = $stmt->fetchAll();

// Semua kategori untuk filter
$categories = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

$pageTitle = 'Berita';
include __DIR__ . '/includes/header.php';
?>

<!-- Page Banner -->
<div class="bg-gradient-to-r from-primary-800 to-primary-600 text-white py-12">
    <div class="container mx-auto px-4">
        <nav class="text-sm text-primary-300 mb-2">
            <a href="/cms-sekolah/index.php" class="hover:text-white">Beranda</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white">Berita</span>
        </nav>
        <h1 class="text-3xl font-bold">Berita Sekolah</h1>
        <p class="text-primary-200 mt-1">Informasi terkini seputar kegiatan dan prestasi sekolah</p>
    </div>
</div>

<div class="container mx-auto px-4 py-10">
    <!-- Filter & Search Bar -->
    <div class="bg-white rounded-2xl shadow-sm p-5 mb-8 border border-gray-100">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <!-- Search -->
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Cari berita..."
                       class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 focus:border-primary-400 outline-none transition">
            </div>
            <!-- Kategori -->
            <select name="kategori"
                    class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 focus:border-primary-400 outline-none bg-white">
                <option value="0">Semua Kategori</option>
                <?php foreach ($categories as $kat): ?>
                <option value="<?= $kat['id'] ?>" <?= ($kategoriId === (int)$kat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($kat['nama_kategori']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit"
                    class="bg-primary-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium hover:bg-primary-800 transition-colors flex items-center gap-2">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if ($search || $kategoriId > 0): ?>
            <a href="/cms-sekolah/berita.php"
               class="bg-gray-100 text-gray-600 px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-200 transition-colors flex items-center gap-2">
                <i class="fas fa-xmark"></i> Reset
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Result Info -->
    <?php if ($search || $kategoriId > 0): ?>
    <p class="text-sm text-gray-500 mb-5">
        Menampilkan <strong><?= $totalRows ?></strong> hasil
        <?= $search ? "untuk \"<strong>$search</strong>\"" : '' ?>
        <?php if ($kategoriId > 0):
            $katName = array_filter($categories, fn($k) => $k['id'] == $kategoriId);
            $katName = reset($katName);
        ?>
        dalam kategori <strong><?= htmlspecialchars($katName['nama_kategori'] ?? '') ?></strong>
        <?php endif; ?>
    </p>
    <?php endif; ?>

    <!-- Grid Berita -->
    <?php if (empty($beritaList)): ?>
    <div class="text-center py-20 text-gray-400">
        <i class="fas fa-newspaper text-6xl mb-4 block"></i>
        <p class="text-lg font-medium">Tidak ada berita ditemukan.</p>
        <a href="/cms-sekolah/berita.php" class="mt-3 inline-block text-sm text-primary-600 hover:underline">
            Lihat semua berita
        </a>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
        <?php foreach ($beritaList as $b): ?>
        <article class="bg-white rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden group flex flex-col">
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
                <a href="/cms-sekolah/berita.php?kategori=<?= urlencode($b['kategori_id'] ?? '') ?>"
                   class="inline-block bg-primary-100 text-primary-700 text-xs font-medium px-2.5 py-1 rounded-full mb-2 self-start hover:bg-primary-200 transition-colors">
                    <?= htmlspecialchars($b['nama_kategori']) ?>
                </a>
                <?php endif; ?>
                <h2 class="font-bold text-gray-900 mb-2 line-clamp-2 leading-snug flex-1">
                    <a href="/cms-sekolah/detail-berita.php?slug=<?= urlencode($b['slug']) ?>"
                       class="hover:text-primary-700 transition-colors">
                        <?= htmlspecialchars($b['judul']) ?>
                    </a>
                </h2>
                <div class="flex items-center gap-3 text-xs text-gray-400 mt-3 pt-3 border-t border-gray-50">
                    <span><i class="far fa-calendar mr-1"></i><?= formatTanggal($b['created_at']) ?></span>
                    <span><i class="far fa-eye mr-1"></i><?= number_format($b['views']) ?></span>
                    <?php if (!empty($b['nama_lengkap'])): ?>
                    <span class="ml-auto truncate max-w-[100px]"><i class="far fa-user mr-1"></i><?= htmlspecialchars($b['nama_lengkap']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center items-center gap-2 mt-6">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
           class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-sm hover:bg-primary-50 hover:border-primary-300 transition-colors">
            <i class="fas fa-chevron-left text-xs mr-1"></i> Prev
        </a>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
           class="px-4 py-2 rounded-xl text-sm transition-colors <?= $i === $page ? 'bg-primary-700 text-white shadow-md' : 'bg-white border border-gray-200 hover:bg-primary-50 hover:border-primary-300' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
           class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-sm hover:bg-primary-50 hover:border-primary-300 transition-colors">
            Next <i class="fas fa-chevron-right text-xs ml-1"></i>
        </a>
        <?php endif; ?>
    </div>
    <p class="text-center text-xs text-gray-400 mt-3">Halaman <?= $page ?> dari <?= $totalPages ?></p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
