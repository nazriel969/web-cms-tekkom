<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$pdo = getDB();

$alertMsg  = flashMessage('alert_msg')  ?? '';
$alertType = flashMessage('alert_type') ?? 'success';

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── HAPUS ────────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $row = $pdo->prepare("SELECT file_lampiran FROM pengumuman WHERE id = ?");
            $row->execute([$id]);
            $existing = $row->fetch();
            if (!empty($existing['file_lampiran'])) {
                $path = __DIR__ . '/../assets/uploads/lampiran/' . $existing['file_lampiran'];
                if (file_exists($path)) unlink($path);
            }
            $pdo->prepare("DELETE FROM pengumuman WHERE id = ?")->execute([$id]);
            flashMessage('alert_msg',  'Pengumuman berhasil dihapus.');
            flashMessage('alert_type', 'success');
        }
        redirect('/cms-sekolah/admin/pengumuman.php');
    }

    // ── SIMPAN ───────────────────────────────────────────────────────────────
    if ($action === 'save') {
        $id            = (int)($_POST['id'] ?? 0);
        $judul         = sanitize($_POST['judul']         ?? '');
        $isi           = $_POST['isi_pengumuman']         ?? '';
        $slug          = createSlug($judul);

        $errors = [];
        if (mb_strlen($judul) < 5) $errors[] = 'Judul minimal 5 karakter.';
        if (mb_strlen(strip_tags($isi)) < 10) $errors[] = 'Isi pengumuman terlalu singkat.';

        // Upload file lampiran
        $fileLampiran = null;
        if (!empty($_FILES['file_lampiran']['name'])) {
            $uploaded = uploadFile($_FILES['file_lampiran'], __DIR__ . '/../assets/uploads/lampiran/');
            if ($uploaded === false) {
                $errors[] = 'Upload file gagal. Format PDF/DOC/DOCX, maksimal 5MB.';
            } else {
                $fileLampiran = $uploaded;
            }
        }

        if (empty($errors)) {
            if ($id > 0) {
                // Cek slug unik
                $sc = $pdo->prepare("SELECT id FROM pengumuman WHERE slug=? AND id!=?");
                $sc->execute([$slug, $id]);
                if ($sc->fetch()) $slug .= '-' . $id;

                if ($fileLampiran) {
                    // Hapus file lama
                    $old = $pdo->prepare("SELECT file_lampiran FROM pengumuman WHERE id=?");
                    $old->execute([$id]);
                    $oldData = $old->fetch();
                    if (!empty($oldData['file_lampiran'])) {
                        $oldPath = __DIR__ . '/../assets/uploads/lampiran/' . $oldData['file_lampiran'];
                        if (file_exists($oldPath)) unlink($oldPath);
                    }
                    $stmt = $pdo->prepare("UPDATE pengumuman SET judul=?,slug=?,isi_pengumuman=?,file_lampiran=? WHERE id=?");
                    $stmt->execute([$judul,$slug,$isi,$fileLampiran,$id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE pengumuman SET judul=?,slug=?,isi_pengumuman=? WHERE id=?");
                    $stmt->execute([$judul,$slug,$isi,$id]);
                }
                flashMessage('alert_msg', 'Pengumuman berhasil diperbarui.');
            } else {
                $sc = $pdo->prepare("SELECT id FROM pengumuman WHERE slug=?");
                $sc->execute([$slug]);
                if ($sc->fetch()) $slug .= '-' . time();

                $stmt = $pdo->prepare("INSERT INTO pengumuman (judul,slug,isi_pengumuman,file_lampiran,created_at) VALUES (?,?,?,?,NOW())");
                $stmt->execute([$judul,$slug,$isi,$fileLampiran]);
                flashMessage('alert_msg', 'Pengumuman berhasil ditambahkan.');
            }
            flashMessage('alert_type', 'success');
            redirect('/cms-sekolah/admin/pengumuman.php');
        } else {
            $alertMsg  = implode(' ', $errors);
            $alertType = 'error';
        }
    }
}

// ── Mode Edit ────────────────────────────────────────────────────────────────
$editData = null;
$editId   = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmtE = $pdo->prepare("SELECT * FROM pengumuman WHERE id=?");
    $stmtE->execute([$editId]);
    $editData = $stmtE->fetch();
}

// ── Daftar ───────────────────────────────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$params = [];
$where  = "WHERE 1=1";
if ($search !== '') { $where .= " AND judul LIKE ?"; $params[] = "%$search%"; }

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM pengumuman $where");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$listStmt = $pdo->prepare("SELECT * FROM pengumuman $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$listStmt->execute(array_merge($params, [$perPage, $offset]));
$list = $listStmt->fetchAll();

$pageTitle = $editData ? 'Edit Pengumuman' : 'Kelola Pengumuman';
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

<!-- Form Tambah / Edit -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
        <h2 class="font-bold text-gray-800 flex items-center gap-2">
            <i class="fas <?= $editData ? 'fa-pen-to-square text-amber-500' : 'fa-plus-circle text-green-500' ?>"></i>
            <?= $editData ? 'Edit Pengumuman' : 'Tambah Pengumuman Baru' ?>
        </h2>
        <?php if ($editData): ?>
        <a href="/cms-sekolah/admin/pengumuman.php"
           class="text-sm text-gray-500 hover:text-gray-700 bg-gray-200 hover:bg-gray-300 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
            <i class="fas fa-xmark"></i> Batal Edit
        </a>
        <?php endif; ?>
    </div>

    <form method="POST" enctype="multipart/form-data" class="p-6">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id"     value="<?= $editData['id'] ?? 0 ?>">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-5">
                <!-- Judul -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Judul Pengumuman <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="judul"
                           value="<?= htmlspecialchars($editData['judul'] ?? '') ?>"
                           placeholder="Masukkan judul pengumuman..."
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none transition bg-gray-50 focus:bg-white"
                           required>
                </div>
                <!-- Isi -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Isi Pengumuman <span class="text-red-500">*</span>
                    </label>
                    <textarea name="isi_pengumuman" rows="12"
                              placeholder="Tuliskan isi pengumuman di sini..."
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none transition resize-y bg-gray-50 focus:bg-white"><?= htmlspecialchars($editData['isi_pengumuman'] ?? '') ?></textarea>
                    <p class="text-xs text-gray-400 mt-1">HTML dasar diperbolehkan.</p>
                </div>
            </div>

            <div class="space-y-5">
                <!-- File Lampiran -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        File Lampiran <?= $editData ? '(opsional — kosongkan jika tidak diganti)' : '(opsional)' ?>
                    </label>
                    <?php if (!empty($editData['file_lampiran'])): ?>
                    <div class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-xl mb-3">
                        <i class="fas fa-file-pdf text-amber-500 text-lg flex-shrink-0"></i>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium text-gray-700 truncate"><?= htmlspecialchars($editData['file_lampiran']) ?></p>
                            <p class="text-xs text-gray-400">File saat ini</p>
                        </div>
                        <a href="/cms-sekolah/assets/uploads/lampiran/<?= urlencode($editData['file_lampiran']) ?>"
                           download class="text-xs text-amber-600 hover:text-amber-800 flex-shrink-0">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-amber-400 hover:bg-amber-50 transition-all group">
                        <i class="fas fa-paperclip text-gray-400 group-hover:text-amber-500 text-xl mb-1 transition-colors"></i>
                        <span class="text-xs text-gray-500 group-hover:text-amber-600" id="fileLabel">Klik untuk pilih file</span>
                        <span class="text-xs text-gray-400">PDF, DOC, DOCX — maks 5MB</span>
                        <input type="file" name="file_lampiran" accept=".pdf,.doc,.docx" class="hidden"
                               onchange="document.getElementById('fileLabel').textContent = this.files[0]?.name || 'Klik untuk pilih file'">
                    </label>
                </div>
                <!-- Submit -->
                <button type="submit"
                        class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2.5 px-4 rounded-xl transition-all text-sm flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                    <i class="fas <?= $editData ? 'fa-save' : 'fa-plus' ?>"></i>
                    <?= $editData ? 'Simpan Perubahan' : 'Tambah Pengumuman' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Daftar Pengumuman -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 px-6 py-4 border-b border-gray-100 bg-gray-50">
        <h2 class="font-bold text-gray-800 flex items-center gap-2">
            <i class="fas fa-list text-gray-400"></i> Daftar Pengumuman
            <span class="bg-gray-200 text-gray-600 text-xs px-2 py-0.5 rounded-full"><?= $total ?></span>
        </h2>
        <form method="GET" class="flex gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari judul..."
                   class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-amber-300 outline-none">
            <button type="submit" class="bg-amber-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-amber-700 transition-colors">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($search): ?>
            <a href="/cms-sekolah/admin/pengumuman.php" class="bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs hover:bg-gray-300 transition-colors">
                <i class="fas fa-xmark"></i>
            </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                    <th class="text-left px-6 py-3 font-medium">Judul</th>
                    <th class="text-center px-4 py-3 font-medium">Lampiran</th>
                    <th class="text-center px-4 py-3 font-medium">Tanggal</th>
                    <th class="text-center px-4 py-3 font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($list)): ?>
                <tr>
                    <td colspan="4" class="text-center py-12 text-gray-400">
                        <i class="fas fa-bullhorn text-4xl mb-2 block opacity-30"></i>
                        Belum ada pengumuman.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($list as $p): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-3">
                        <p class="font-medium text-gray-800 line-clamp-1 max-w-[320px]"><?= htmlspecialchars($p['judul']) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5"><?= truncate(strip_tags($p['isi_pengumuman']), 80) ?></p>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if (!empty($p['file_lampiran'])): ?>
                        <a href="/cms-sekolah/assets/uploads/lampiran/<?= urlencode($p['file_lampiran']) ?>"
                           download class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 hover:bg-amber-200 text-xs px-2.5 py-1 rounded-full transition-colors font-medium">
                            <i class="fas fa-paperclip text-xs"></i> Ada
                        </a>
                        <?php else: ?>
                        <span class="text-gray-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-gray-500 whitespace-nowrap">
                        <?= formatTanggal($p['created_at']) ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-2">
                            <a href="/cms-sekolah/pengumuman.php?slug=<?= urlencode($p['slug']) ?>" target="_blank"
                               class="w-8 h-8 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center transition-colors" title="Lihat">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            <a href="/cms-sekolah/admin/pengumuman.php?edit=<?= $p['id'] ?>"
                               class="w-8 h-8 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center transition-colors" title="Edit">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= $p['id'] ?>">
                                <button type="submit"
                                        data-confirm="Yakin hapus pengumuman &quot;<?= htmlspecialchars(addslashes($p['judul'])) ?>&quot;?"
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
           class="px-3 py-1.5 rounded-lg text-xs transition-colors <?= $i===$page ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
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
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
