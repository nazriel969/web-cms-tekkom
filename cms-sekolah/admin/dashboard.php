<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$pdo = getDB();

// ── Statistik Utama ─────────────────────────────────────────────────────────
$stats = [
    'berita_published' => (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='published'")->fetchColumn(),
    'berita_draft'     => (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='draft'")->fetchColumn(),
    'pengumuman'       => (int)$pdo->query("SELECT COUNT(*) FROM pengumuman")->fetchColumn(),
    'galeri'           => (int)$pdo->query("SELECT COUNT(*) FROM galeri")->fetchColumn(),
    'pesan_total'      => (int)$pdo->query("SELECT COUNT(*) FROM pesan_kontak")->fetchColumn(),
    'pesan_unread'     => (int)$pdo->query("SELECT COUNT(*) FROM pesan_kontak WHERE dibaca=0")->fetchColumn(),
    'total_views'      => (int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM berita")->fetchColumn(),
    'kategori'         => (int)$pdo->query("SELECT COUNT(*) FROM kategori")->fetchColumn(),
];

// ── Berita Terbaru (5) ───────────────────────────────────────────────────────
$recentBerita = $pdo->query("
    SELECT b.id, b.judul, b.slug, b.status, b.views, b.created_at,
           k.nama_kategori, u.nama_lengkap
    FROM berita b
    LEFT JOIN kategori k ON b.kategori_id = k.id
    LEFT JOIN users u ON b.user_id = u.id
    ORDER BY b.created_at DESC LIMIT 5
")->fetchAll();

// ── Pesan Terbaru (5) ────────────────────────────────────────────────────────
$recentPesan = $pdo->query("
    SELECT id, nama, email, subjek, dibaca, created_at
    FROM pesan_kontak
    ORDER BY created_at DESC LIMIT 5
")->fetchAll();

// ── Berita Terpopuler (5) ────────────────────────────────────────────────────
$popularBerita = $pdo->query("
    SELECT id, judul, slug, views, created_at
    FROM berita WHERE status='published'
    ORDER BY views DESC LIMIT 5
")->fetchAll();

// ── Views 7 hari terakhir (untuk mini chart) ─────────────────────────────────
$viewsChart = $pdo->query("
    SELECT DATE(created_at) AS tgl, COUNT(*) AS total
    FROM berita
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY tgl ASC
")->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/admin_header.php';
?>

<!-- ── Stat Cards ─────────────────────────────────────────────────────────── -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">

    <?php
    $cards = [
        [
            'label'  => 'Berita Terbit',
            'value'  => $stats['berita_published'],
            'sub'    => $stats['berita_draft'] . ' draft',
            'icon'   => 'fa-newspaper',
            'color'  => 'from-blue-500 to-blue-600',
            'href'   => 'berita.php',
        ],
        [
            'label'  => 'Pengumuman',
            'value'  => $stats['pengumuman'],
            'sub'    => 'total pengumuman',
            'icon'   => 'fa-bullhorn',
            'color'  => 'from-amber-500 to-orange-500',
            'href'   => 'pengumuman.php',
        ],
        [
            'label'  => 'Foto Galeri',
            'value'  => $stats['galeri'],
            'sub'    => 'foto tersimpan',
            'icon'   => 'fa-images',
            'color'  => 'from-purple-500 to-purple-600',
            'href'   => 'galeri.php',
        ],
        [
            'label'  => 'Pesan Masuk',
            'value'  => $stats['pesan_total'],
            'sub'    => $stats['pesan_unread'] . ' belum dibaca',
            'icon'   => 'fa-envelope',
            'color'  => 'from-green-500 to-emerald-600',
            'href'   => 'pesan.php',
            'badge'  => $stats['pesan_unread'],
        ],
    ];
    foreach ($cards as $c):
    ?>
    <a href="/cms-sekolah/admin/<?= $c['href'] ?>"
       class="relative bg-gradient-to-br <?= $c['color'] ?> text-white rounded-2xl p-5 shadow-md hover:shadow-xl transition-all hover:-translate-y-0.5 group">
        <?php if (!empty($c['badge']) && $c['badge'] > 0): ?>
        <span class="absolute top-3 right-3 bg-red-400 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center animate-pulse">
            <?= $c['badge'] ?>
        </span>
        <?php endif; ?>
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fas <?= $c['icon'] ?> text-lg"></i>
            </div>
        </div>
        <div class="text-3xl font-extrabold leading-none mb-1"><?= number_format($c['value']) ?></div>
        <div class="text-sm font-semibold opacity-90"><?= $c['label'] ?></div>
        <div class="text-xs opacity-70 mt-0.5"><?= $c['sub'] ?></div>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Row 2: Total Views + Kategori ─────────────────────────────────────── -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-gradient-to-br from-primary-700 to-primary-900 text-white rounded-2xl p-5 shadow-md flex items-center gap-5">
        <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-chart-line text-2xl"></i>
        </div>
        <div>
            <div class="text-4xl font-extrabold"><?= number_format($stats['total_views']) ?></div>
            <div class="text-sm font-semibold opacity-90 mt-0.5">Total Views Berita</div>
            <div class="text-xs opacity-60 mt-0.5">Kumulatif semua artikel</div>
        </div>
    </div>
    <div class="bg-gradient-to-br from-teal-500 to-teal-600 text-white rounded-2xl p-5 shadow-md flex items-center gap-5">
        <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-tags text-2xl"></i>
        </div>
        <div>
            <div class="text-4xl font-extrabold"><?= $stats['kategori'] ?></div>
            <div class="text-sm font-semibold opacity-90 mt-0.5">Kategori Berita</div>
            <div class="text-xs opacity-60 mt-0.5">Kategori tersedia</div>
        </div>
    </div>
</div>

<!-- ── Row 3: Tabel Berita + Pesan ────────────────────────────────────────── -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

    <!-- Berita Terbaru -->
    <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="font-bold text-gray-800 flex items-center gap-2 text-sm">
                <i class="fas fa-newspaper text-blue-500"></i> Berita Terbaru
            </h2>
            <a href="/cms-sekolah/admin/berita.php"
               class="text-xs text-primary-600 hover:text-primary-800 font-medium flex items-center gap-1">
                Lihat Semua <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                        <th class="text-left px-5 py-3 font-medium">Judul</th>
                        <th class="text-left px-4 py-3 font-medium">Kategori</th>
                        <th class="text-center px-4 py-3 font-medium">Status</th>
                        <th class="text-right px-5 py-3 font-medium">Views</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($recentBerita)): ?>
                    <tr><td colspan="4" class="text-center py-8 text-gray-400 text-sm">Belum ada berita.</td></tr>
                    <?php else: ?>
                    <?php foreach ($recentBerita as $b): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3">
                            <a href="/cms-sekolah/admin/berita.php?edit=<?= $b['id'] ?>"
                               class="font-medium text-gray-800 hover:text-primary-700 line-clamp-1 block max-w-[220px]">
                                <?= htmlspecialchars($b['judul']) ?>
                            </a>
                            <span class="text-xs text-gray-400"><?= formatTanggal($b['created_at']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">
                            <?= htmlspecialchars($b['nama_kategori'] ?? '—') ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($b['status'] === 'published'): ?>
                            <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                <i class="fas fa-circle text-[6px]"></i> Terbit
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-xs font-medium px-2 py-0.5 rounded-full">
                                <i class="fas fa-circle text-[6px]"></i> Draft
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-right text-gray-600 font-medium">
                            <?= number_format($b['views']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Berita Populer -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-bold text-gray-800 flex items-center gap-2 text-sm">
                <i class="fas fa-fire text-orange-500"></i> Paling Banyak Dibaca
            </h2>
        </div>
        <div class="p-4 space-y-3">
            <?php if (empty($popularBerita)): ?>
            <p class="text-center py-6 text-gray-400 text-sm">Belum ada data.</p>
            <?php else: ?>
            <?php foreach ($popularBerita as $idx => $pb): ?>
            <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold
                    <?= $idx === 0 ? 'bg-yellow-100 text-yellow-700' : ($idx === 1 ? 'bg-gray-200 text-gray-600' : ($idx === 2 ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-500')) ?>">
                    <?= $idx + 1 ?>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="/cms-sekolah/admin/berita.php?edit=<?= $pb['id'] ?>"
                       class="text-xs font-medium text-gray-800 hover:text-primary-700 line-clamp-2 leading-snug block">
                        <?= htmlspecialchars($pb['judul']) ?>
                    </a>
                </div>
                <span class="text-xs text-gray-400 flex-shrink-0 font-medium"><?= number_format($pb['views']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Pesan Masuk Terbaru ───────────────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-800 flex items-center gap-2 text-sm">
            <i class="fas fa-envelope text-green-500"></i> Pesan Masuk Terbaru
            <?php if ($stats['pesan_unread'] > 0): ?>
            <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full ml-1">
                <?= $stats['pesan_unread'] ?> baru
            </span>
            <?php endif; ?>
        </h2>
        <a href="/cms-sekolah/admin/pesan.php"
           class="text-xs text-primary-600 hover:text-primary-800 font-medium flex items-center gap-1">
            Lihat Semua <i class="fas fa-arrow-right text-xs"></i>
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <th class="text-left px-5 py-3 font-medium">Pengirim</th>
                    <th class="text-left px-4 py-3 font-medium">Subjek</th>
                    <th class="text-left px-4 py-3 font-medium">Tanggal</th>
                    <th class="text-center px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($recentPesan)): ?>
                <tr><td colspan="4" class="text-center py-8 text-gray-400 text-sm">Belum ada pesan masuk.</td></tr>
                <?php else: ?>
                <?php foreach ($recentPesan as $pesan): ?>
                <tr class="hover:bg-gray-50 transition-colors <?= !$pesan['dibaca'] ? 'font-semibold' : '' ?>">
                    <td class="px-5 py-3">
                        <a href="/cms-sekolah/admin/pesan.php?id=<?= $pesan['id'] ?>"
                           class="text-gray-800 hover:text-primary-700 block">
                            <?= htmlspecialchars($pesan['nama']) ?>
                        </a>
                        <span class="text-xs text-gray-400 font-normal"><?= htmlspecialchars($pesan['email']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-gray-600 max-w-[200px]">
                        <span class="line-clamp-1 block"><?= htmlspecialchars($pesan['subjek']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                        <?= formatTanggal($pesan['created_at']) ?>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <?php if (!$pesan['dibaca']): ?>
                        <span class="inline-flex items-center gap-1 bg-red-100 text-red-600 text-xs font-medium px-2 py-0.5 rounded-full">
                            <i class="fas fa-circle text-[6px]"></i> Baru
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-400 text-xs px-2 py-0.5 rounded-full">
                            <i class="fas fa-check text-[9px]"></i> Dibaca
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
