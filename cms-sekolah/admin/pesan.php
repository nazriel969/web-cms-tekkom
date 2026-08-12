<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$pdo = getDB();

$alertMsg  = flashMessage('alert_msg')  ?? '';
$alertType = flashMessage('alert_type') ?? 'success';

// ── Handle POST Actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Tandai dibaca / belum dibaca
    if ($action === 'toggle_read') {
        $id     = (int)($_POST['id']     ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE pesan_kontak SET dibaca=? WHERE id=?")->execute([$status, $id]);
        }
        redirect('/cms-sekolah/admin/pesan.php' . (!empty($_GET['id']) ? '?id='.(int)$_GET['id'] : ''));
    }

    // Hapus satu pesan
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM pesan_kontak WHERE id=?")->execute([$id]);
            flashMessage('alert_msg',  'Pesan berhasil dihapus.');
            flashMessage('alert_type', 'success');
        }
        redirect('/cms-sekolah/admin/pesan.php');
    }

    // Hapus massal
    if ($action === 'bulk_delete') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM pesan_kontak WHERE id IN ($ph)")->execute($ids);
            flashMessage('alert_msg',  count($ids) . ' pesan berhasil dihapus.');
            flashMessage('alert_type', 'success');
        }
        redirect('/cms-sekolah/admin/pesan.php');
    }

    // Tandai semua sudah dibaca
    if ($action === 'mark_all_read') {
        $pdo->exec("UPDATE pesan_kontak SET dibaca=1");
        flashMessage('alert_msg',  'Semua pesan ditandai sudah dibaca.');
        flashMessage('alert_type', 'success');
        redirect('/cms-sekolah/admin/pesan.php');
    }
}

// ── Detail Pesan ─────────────────────────────────────────────────────────────
$detailId = (int)($_GET['id'] ?? 0);
$detail   = null;
if ($detailId > 0) {
    $stmtD = $pdo->prepare("SELECT * FROM pesan_kontak WHERE id=?");
    $stmtD->execute([$detailId]);
    $detail = $stmtD->fetch();
    // Auto-mark as read
    if ($detail && !$detail['dibaca']) {
        $pdo->prepare("UPDATE pesan_kontak SET dibaca=1 WHERE id=?")->execute([$detailId]);
        $detail['dibaca'] = 1;
    }
}

// ── Daftar Pesan ─────────────────────────────────────────────────────────────
$filterRead = $_GET['filter'] ?? 'all'; // all | unread | read
$search     = trim($_GET['q'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 15;
$offset     = ($page - 1) * $perPage;

$params = [];
$where  = "WHERE 1=1";
if ($filterRead === 'unread') { $where .= " AND dibaca=0"; }
if ($filterRead === 'read')   { $where .= " AND dibaca=1"; }
if ($search !== '') {
    $where   .= " AND (nama LIKE ? OR email LIKE ? OR subjek LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM pesan_kontak $where");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$listStmt = $pdo->prepare("SELECT * FROM pesan_kontak $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$listStmt->execute(array_merge($params, [$perPage, $offset]));
$list = $listStmt->fetchAll();

// Count unread for badge
$unreadCount = (int)$pdo->query("SELECT COUNT(*) FROM pesan_kontak WHERE dibaca=0")->fetchColumn();

$pageTitle = 'Pesan Masuk';
include __DIR__ . '/../includes/admin_header.php';
?>

<!-- Alert -->
<?php if ($alertMsg): ?>
<?php $aStyle = $alertType==='success' ? 'bg-green-50 border-green-400 text-green-800' : 'bg-red-50 border-red-400 text-red-800'; ?>
<?php $aIcon  = $alertType==='success' ? 'fa-circle-check' : 'fa-circle-xmark'; ?>
<div class="border-l-4 p-4 rounded-r-xl mb-5 flex items-start gap-3 <?= $aStyle ?>" data-auto-dismiss>
    <i class="fas <?= $aIcon ?> mt-0.5 flex-shrink-0"></i>
    <span class="text-sm"><?= htmlspecialchars($alertMsg) ?></span>
</div>
<?php endif; ?>

<?php if ($detail): ?>
<!-- ─────────────────────── DETAIL VIEW ─────────────────────────────────── -->
<div class="max-w-3xl mx-auto">
    <!-- Back -->
    <a href="/cms-sekolah/admin/pesan.php"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 mb-5 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Kembali ke Inbox
    </a>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-5 border-b border-gray-100 bg-gray-50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($detail['subjek']) ?></h2>
                    <div class="flex flex-wrap items-center gap-3 mt-2 text-sm text-gray-500">
                        <span class="flex items-center gap-1.5">
                            <i class="fas fa-user text-xs text-gray-400"></i>
                            <strong class="text-gray-700"><?= htmlspecialchars($detail['nama']) ?></strong>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <i class="fas fa-envelope text-xs text-gray-400"></i>
                            <a href="mailto:<?= htmlspecialchars($detail['email']) ?>"
                               class="text-primary-600 hover:underline"><?= htmlspecialchars($detail['email']) ?></a>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <i class="far fa-calendar text-xs text-gray-400"></i>
                            <?= formatTanggal($detail['created_at']) ?>
                        </span>
                    </div>
                </div>
                <span class="flex-shrink-0 <?= $detail['dibaca'] ? 'bg-gray-100 text-gray-500' : 'bg-red-100 text-red-600' ?> text-xs font-medium px-3 py-1 rounded-full">
                    <?= $detail['dibaca'] ? 'Sudah Dibaca' : 'Belum Dibaca' ?>
                </span>
            </div>
        </div>

        <!-- Body Pesan -->
        <div class="px-6 py-6">
            <div class="bg-gray-50 rounded-xl p-5 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap border border-gray-100">
<?= htmlspecialchars($detail['pesan']) ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="px-6 pb-6 flex flex-wrap gap-3">
            <!-- Balas via email -->
            <a href="mailto:<?= htmlspecialchars($detail['email']) ?>?subject=Re: <?= urlencode($detail['subjek']) ?>"
               class="flex items-center gap-2 bg-primary-700 hover:bg-primary-800 text-white text-sm font-medium px-5 py-2.5 rounded-xl transition-colors shadow-md">
                <i class="fas fa-reply"></i> Balas via Email
            </a>

            <!-- Toggle read -->
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="toggle_read">
                <input type="hidden" name="id"     value="<?= $detail['id'] ?>">
                <input type="hidden" name="status" value="<?= $detail['dibaca'] ? 0 : 1 ?>">
                <button type="submit"
                        class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-5 py-2.5 rounded-xl transition-colors">
                    <i class="fas <?= $detail['dibaca'] ? 'fa-envelope' : 'fa-envelope-open' ?>"></i>
                    <?= $detail['dibaca'] ? 'Tandai Belum Dibaca' : 'Tandai Sudah Dibaca' ?>
                </button>
            </form>

            <!-- Hapus -->
            <form method="POST" class="inline ml-auto">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id"     value="<?= $detail['id'] ?>">
                <button type="submit"
                        data-confirm="Yakin ingin menghapus pesan dari <?= htmlspecialchars(addslashes($detail['nama'])) ?>?"
                        class="flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium px-5 py-2.5 rounded-xl transition-colors">
                    <i class="fas fa-trash"></i> Hapus Pesan
                </button>
            </form>
        </div>
    </div>

    <!-- Navigasi Pesan Lain -->
    <?php
    $prevStmt = $pdo->prepare("SELECT id, nama, subjek FROM pesan_kontak WHERE id < ? ORDER BY id DESC LIMIT 1");
    $prevStmt->execute([$detail['id']]);
    $prevMsg  = $prevStmt->fetch();

    $nextStmt = $pdo->prepare("SELECT id, nama, subjek FROM pesan_kontak WHERE id > ? ORDER BY id ASC LIMIT 1");
    $nextStmt->execute([$detail['id']]);
    $nextMsg  = $nextStmt->fetch();
    ?>
    <?php if ($prevMsg || $nextMsg): ?>
    <div class="flex justify-between gap-4 mt-4">
        <?php if ($prevMsg): ?>
        <a href="?id=<?= $prevMsg['id'] ?>"
           class="flex items-center gap-2 text-sm text-gray-500 hover:text-primary-700 bg-white border border-gray-100 rounded-xl px-4 py-2.5 shadow-sm hover:shadow-md transition-all max-w-[48%]">
            <i class="fas fa-chevron-left text-xs flex-shrink-0"></i>
            <span class="truncate"><span class="font-medium"><?= htmlspecialchars($prevMsg['nama']) ?>:</span> <?= htmlspecialchars(truncate($prevMsg['subjek'],40)) ?></span>
        </a>
        <?php else: ?><div></div><?php endif; ?>

        <?php if ($nextMsg): ?>
        <a href="?id=<?= $nextMsg['id'] ?>"
           class="flex items-center gap-2 text-sm text-gray-500 hover:text-primary-700 bg-white border border-gray-100 rounded-xl px-4 py-2.5 shadow-sm hover:shadow-md transition-all max-w-[48%] text-right justify-end">
            <span class="truncate"><span class="font-medium"><?= htmlspecialchars($nextMsg['nama']) ?>:</span> <?= htmlspecialchars(truncate($nextMsg['subjek'],40)) ?></span>
            <i class="fas fa-chevron-right text-xs flex-shrink-0"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ─────────────────────── LIST VIEW ───────────────────────────────────── -->

<!-- Stat bar -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <?php
    $totalAll  = (int)$pdo->query("SELECT COUNT(*) FROM pesan_kontak")->fetchColumn();
    $totalRead = (int)$pdo->query("SELECT COUNT(*) FROM pesan_kontak WHERE dibaca=1")->fetchColumn();
    $statItems = [
        ['label'=>'Total Pesan',   'val'=>$totalAll,   'color'=>'from-blue-500 to-blue-600',  'icon'=>'fa-envelope'],
        ['label'=>'Belum Dibaca',  'val'=>$unreadCount,'color'=>'from-red-500 to-red-600',    'icon'=>'fa-envelope-open'],
        ['label'=>'Sudah Dibaca',  'val'=>$totalRead,  'color'=>'from-green-500 to-green-600','icon'=>'fa-check-circle'],
    ];
    foreach ($statItems as $s):
    ?>
    <div class="bg-gradient-to-br <?= $s['color'] ?> text-white rounded-2xl p-4 shadow-md flex items-center gap-4">
        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas <?= $s['icon'] ?> text-lg"></i>
        </div>
        <div>
            <div class="text-2xl font-extrabold"><?= number_format($s['val']) ?></div>
            <div class="text-xs opacity-80"><?= $s['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Toolbar -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 px-6 py-4 border-b border-gray-100 bg-gray-50">
        <div class="flex items-center gap-2">
            <h2 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-inbox text-gray-400"></i> Inbox
            </h2>
            <!-- Filter tabs -->
            <div class="flex gap-1 ml-3 text-xs">
                <?php foreach (['all'=>'Semua','unread'=>'Belum Dibaca','read'=>'Dibaca'] as $key=>$lbl): ?>
                <a href="?filter=<?= $key ?>"
                   class="px-3 py-1 rounded-full transition-colors font-medium <?= $filterRead===$key ? 'bg-primary-700 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
                    <?= $lbl ?>
                    <?php if ($key==='unread' && $unreadCount>0): ?>
                    <span class="ml-1 bg-red-500 text-white rounded-full px-1.5 text-[10px]"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <!-- Search -->
            <form method="GET" class="flex gap-2">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filterRead) ?>">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari pengirim / subjek..."
                       class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-primary-300 outline-none min-w-[180px]">
                <button type="submit" class="bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-primary-800 transition-colors">
                    <i class="fas fa-search"></i>
                </button>
                <?php if ($search): ?>
                <a href="?filter=<?= $filterRead ?>" class="bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs hover:bg-gray-300 transition-colors">
                    <i class="fas fa-xmark"></i>
                </a>
                <?php endif; ?>
            </form>

            <!-- Mark all read -->
            <?php if ($unreadCount > 0): ?>
            <form method="POST">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                    <i class="fas fa-check-double"></i> Tandai Semua Dibaca
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bulk actions bar -->
    <form method="POST" id="bulkForm">
        <input type="hidden" name="action" value="bulk_delete">

        <div id="bulkToolbar" class="hidden px-6 py-2 bg-red-50 border-b border-red-100 flex items-center gap-3">
            <span class="text-sm text-red-700 font-medium"><span id="selectedCount">0</span> pesan dipilih</span>
            <button type="submit"
                    data-confirm="Yakin hapus pesan-pesan yang dipilih?"
                    class="bg-red-600 hover:bg-red-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                <i class="fas fa-trash"></i> Hapus Pilihan
            </button>
            <button type="button" onclick="clearSelection()"
                    class="bg-gray-200 text-gray-600 text-xs px-3 py-1.5 rounded-lg hover:bg-gray-300 transition-colors">
                Batal
            </button>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="px-5 py-3 w-8">
                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)"
                                   class="w-4 h-4 accent-primary-600 rounded">
                        </th>
                        <th class="text-left px-4 py-3 font-medium">Pengirim</th>
                        <th class="text-left px-4 py-3 font-medium">Subjek</th>
                        <th class="text-center px-4 py-3 font-medium">Status</th>
                        <th class="text-center px-4 py-3 font-medium">Tanggal</th>
                        <th class="text-center px-4 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-14 text-gray-400">
                            <i class="fas fa-inbox text-5xl mb-3 block opacity-30"></i>
                            <?= $search ? 'Tidak ada pesan yang cocok.' : 'Tidak ada pesan masuk.' ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($list as $pesan): ?>
                    <tr class="hover:bg-gray-50 transition-colors <?= !$pesan['dibaca'] ? 'bg-blue-50/30' : '' ?>">
                        <td class="px-5 py-3 text-center">
                            <input type="checkbox" name="ids[]" value="<?= $pesan['id'] ?>"
                                   class="w-4 h-4 accent-primary-600 rounded" onchange="updateBulkBar()">
                        </td>
                        <td class="px-4 py-3">
                            <a href="/cms-sekolah/admin/pesan.php?id=<?= $pesan['id'] ?>"
                               class="block hover:text-primary-700 transition-colors">
                                <p class="font-<?= !$pesan['dibaca'] ? 'bold' : 'medium' ?> text-gray-800 text-sm">
                                    <?= htmlspecialchars($pesan['nama']) ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($pesan['email']) ?></p>
                            </a>
                        </td>
                        <td class="px-4 py-3 max-w-[260px]">
                            <a href="/cms-sekolah/admin/pesan.php?id=<?= $pesan['id'] ?>"
                               class="block hover:text-primary-700 transition-colors">
                                <p class="font-<?= !$pesan['dibaca'] ? 'semibold' : 'normal' ?> text-gray-800 text-sm line-clamp-1">
                                    <?php if (!$pesan['dibaca']): ?>
                                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full mr-1.5 align-middle"></span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($pesan['subjek']) ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">
                                    <?= htmlspecialchars(truncate($pesan['pesan'], 60)) ?>
                                </p>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if (!$pesan['dibaca']): ?>
                            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 text-xs font-medium px-2.5 py-1 rounded-full">
                                <i class="fas fa-circle text-[6px]"></i> Baru
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-400 text-xs px-2.5 py-1 rounded-full">
                                <i class="fas fa-check text-[9px]"></i> Dibaca
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-500 whitespace-nowrap">
                            <?= formatTanggal($pesan['created_at']) ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="/cms-sekolah/admin/pesan.php?id=<?= $pesan['id'] ?>"
                                   class="w-8 h-8 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center transition-colors" title="Baca">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                                <a href="mailto:<?= htmlspecialchars($pesan['email']) ?>?subject=Re: <?= urlencode($pesan['subjek']) ?>"
                                   class="w-8 h-8 bg-green-50 hover:bg-green-100 text-green-600 rounded-lg flex items-center justify-center transition-colors" title="Balas Email">
                                    <i class="fas fa-reply text-xs"></i>
                                </a>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id"     value="<?= $pesan['id'] ?>">
                                    <button type="submit"
                                            data-confirm="Hapus pesan dari <?= htmlspecialchars(addslashes($pesan['nama'])) ?>?"
                                            class="w-8 h-8 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg flex items-center justify-center transition-colors" title="Hapus">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center items-center gap-2 px-6 py-4 border-t border-gray-100">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>"
           class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs hover:bg-gray-200 transition-colors">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"
           class="px-3 py-1.5 rounded-lg text-xs transition-colors <?= $i===$page ? 'bg-primary-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>"
           class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs hover:bg-gray-200 transition-colors">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <p class="text-center text-xs text-gray-400 pb-4">Halaman <?= $page ?> dari <?= $totalPages ?> &bull; <?= $total ?> pesan</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
function toggleAll(master) {
    document.querySelectorAll('#bulkForm input[name="ids[]"]').forEach(c => { c.checked = master.checked; });
    updateBulkBar();
}
function updateBulkBar() {
    const checked = document.querySelectorAll('#bulkForm input[name="ids[]"]:checked');
    const toolbar = document.getElementById('bulkToolbar');
    if (!toolbar) return;
    document.getElementById('selectedCount').textContent = checked.length;
    toolbar.classList.toggle('hidden', checked.length === 0);
    toolbar.classList.toggle('flex',   checked.length > 0);
    const all = document.querySelectorAll('#bulkForm input[name="ids[]"]');
    const sa  = document.getElementById('selectAll');
    if (sa) sa.indeterminate = checked.length > 0 && checked.length < all.length;
    if (sa) sa.checked = all.length > 0 && checked.length === all.length;
}
function clearSelection() {
    document.querySelectorAll('#bulkForm input[name="ids[]"]').forEach(c => c.checked = false);
    const sa = document.getElementById('selectAll');
    if (sa) { sa.checked = false; sa.indeterminate = false; }
    updateBulkBar();
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
