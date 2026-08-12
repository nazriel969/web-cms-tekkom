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
            $row = $pdo->prepare("SELECT foto FROM galeri WHERE id=?");
            $row->execute([$id]);
            $existing = $row->fetch();
            if (!empty($existing['foto'])) {
                $imgPath = __DIR__ . '/../assets/uploads/galeri/' . $existing['foto'];
                if (file_exists($imgPath)) unlink($imgPath);
            }
            $pdo->prepare("DELETE FROM galeri WHERE id=?")->execute([$id]);
            flashMessage('alert_msg',  'Foto berhasil dihapus.');
            flashMessage('alert_type', 'success');
        }
        redirect('/cms-sekolah/admin/galeri.php');
    }

    // ── HAPUS MASSAL ─────────────────────────────────────────────────────────
    if ($action === 'bulk_delete') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $pdo->prepare("SELECT foto FROM galeri WHERE id IN ($placeholders)");
            $rows->execute($ids);
            foreach ($rows->fetchAll() as $r) {
                if (!empty($r['foto'])) {
                    $p = __DIR__ . '/../assets/uploads/galeri/' . $r['foto'];
                    if (file_exists($p)) unlink($p);
                }
            }
            $pdo->prepare("DELETE FROM galeri WHERE id IN ($placeholders)")->execute($ids);
            flashMessage('alert_msg',  count($ids) . ' foto berhasil dihapus.');
            flashMessage('alert_type', 'success');
        }
        redirect('/cms-sekolah/admin/galeri.php');
    }

    // ── SIMPAN (Edit keterangan) ──────────────────────────────────────────────
    if ($action === 'save') {
        $id         = (int)($_POST['id'] ?? 0);
        $judul      = sanitize($_POST['judul']      ?? '');
        $keterangan = sanitize($_POST['keterangan'] ?? '');

        $errors = [];
        if (mb_strlen($judul) < 2) $errors[] = 'Judul minimal 2 karakter.';

        // Upload foto baru jika ada
        $foto = null;
        if (!empty($_FILES['foto']['name'])) {
            $uploaded = uploadImage($_FILES['foto'], __DIR__ . '/../assets/uploads/galeri/');
            if ($uploaded === false) {
                $errors[] = 'Upload foto gagal. Format JPG/PNG/WEBP, maksimal 2MB.';
            } else {
                $foto = $uploaded;
            }
        } elseif ($id === 0) {
            $errors[] = 'Foto wajib diunggah untuk entri baru.';
        }

        if (empty($errors)) {
            if ($id > 0) {
                if ($foto) {
                    $old = $pdo->prepare("SELECT foto FROM galeri WHERE id=?");
                    $old->execute([$id]);
                    $oldData = $old->fetch();
                    if (!empty($oldData['foto'])) {
                        $oldPath = __DIR__ . '/../assets/uploads/galeri/' . $oldData['foto'];
                        if (file_exists($oldPath)) unlink($oldPath);
                    }
                    $stmt = $pdo->prepare("UPDATE galeri SET judul=?,foto=?,keterangan=? WHERE id=?");
                    $stmt->execute([$judul,$foto,$keterangan,$id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE galeri SET judul=?,keterangan=? WHERE id=?");
                    $stmt->execute([$judul,$keterangan,$id]);
                }
                flashMessage('alert_msg', 'Foto berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO galeri (judul,foto,keterangan,created_at) VALUES (?,?,?,NOW())");
                $stmt->execute([$judul,$foto,$keterangan]);
                flashMessage('alert_msg', 'Foto berhasil ditambahkan ke galeri.');
            }
            flashMessage('alert_type', 'success');
            redirect('/cms-sekolah/admin/galeri.php');
        } else {
            $alertMsg  = implode(' ', $errors);
            $alertType = 'error';
        }
    }

    // ── UPLOAD MASSAL ─────────────────────────────────────────────────────────
    if ($action === 'bulk_upload') {
        $judul_prefix = sanitize($_POST['judul_prefix'] ?? 'Kegiatan');
        $files        = $_FILES['fotos'] ?? null;
        $successCount = 0;
        $failCount    = 0;

        if ($files && is_array($files['name'])) {
            $fileCount = count($files['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                $singleFile = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
                $uploaded = uploadImage($singleFile, __DIR__ . '/../assets/uploads/galeri/');
                if ($uploaded !== false) {
                    $judul = $judul_prefix . ' ' . ($i + 1);
                    $pdo->prepare("INSERT INTO galeri (judul,foto,keterangan,created_at) VALUES (?,?,?,NOW())")
                        ->execute([$judul, $uploaded, '']);
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
        }

        $msg = "$successCount foto berhasil diunggah.";
        if ($failCount > 0) $msg .= " $failCount foto gagal (format/ukuran tidak valid).";
        flashMessage('alert_msg',  $msg);
        flashMessage('alert_type', $failCount === 0 ? 'success' : 'warning');
        redirect('/cms-sekolah/admin/galeri.php');
    }
}

// ── Mode Edit ─────────────────────────────────────────────────────────────────
$editData = null;
$editId   = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmtE = $pdo->prepare("SELECT * FROM galeri WHERE id=?");
    $stmtE->execute([$editId]);
    $editData = $stmtE->fetch();
}

// ── Daftar ───────────────────────────────────────────────────────────────────
$search     = trim($_GET['q'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12;
$offset     = ($page - 1) * $perPage;

$params = [];
$where  = "WHERE 1=1";
if ($search !== '') { $where .= " AND judul LIKE ?"; $params[] = "%$search%"; }

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM galeri $where");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$listStmt = $pdo->prepare("SELECT * FROM galeri $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$listStmt->execute(array_merge($params, [$perPage, $offset]));
$list = $listStmt->fetchAll();

$pageTitle = $editData ? 'Edit Foto Galeri' : 'Kelola Galeri';
include __DIR__ . '/../includes/admin_header.php';
?>

<!-- Alert -->
<?php if ($alertMsg): ?>
<?php
$aColors = ['success'=>'bg-green-50 border-green-400 text-green-800','error'=>'bg-red-50 border-red-400 text-red-800','warning'=>'bg-amber-50 border-amber-400 text-amber-800'];
$aIcons  = ['success'=>'fa-circle-check','error'=>'fa-circle-xmark','warning'=>'fa-triangle-exclamation'];
$aStyle  = $aColors[$alertType] ?? $aColors['success'];
$aIcon   = $aIcons[$alertType]  ?? $aIcons['success'];
?>
<div class="border-l-4 p-4 rounded-r-xl mb-5 flex items-start gap-3 <?= $aStyle ?>" data-auto-dismiss>
    <i class="fas <?= $aIcon ?> mt-0.5 flex-shrink-0"></i>
    <span class="text-sm"><?= htmlspecialchars($alertMsg) ?></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

    <!-- Form Tambah / Edit -->
    <div class="xl:col-span-1">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                    <i class="fas <?= $editData ? 'fa-pen-to-square text-purple-500' : 'fa-plus-circle text-green-500' ?>"></i>
                    <?= $editData ? 'Edit Foto' : 'Tambah Foto' ?>
                </h2>
                <?php if ($editData): ?>
                <a href="/cms-sekolah/admin/galeri.php"
                   class="text-xs text-gray-500 hover:text-gray-700 bg-gray-200 hover:bg-gray-300 px-2.5 py-1 rounded-lg transition-colors">
                    <i class="fas fa-xmark"></i> Batal
                </a>
                <?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id"     value="<?= $editData['id'] ?? 0 ?>">

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">
                        Judul Foto <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="judul"
                           value="<?= htmlspecialchars($editData['judul'] ?? '') ?>"
                           placeholder="Nama kegiatan / judul foto"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none transition bg-gray-50 focus:bg-white"
                           required>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Keterangan</label>
                    <textarea name="keterangan" rows="3" placeholder="Deskripsi singkat foto (opsional)..."
                              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none transition resize-none bg-gray-50 focus:bg-white"><?= htmlspecialchars($editData['keterangan'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">
                        Foto <?= $editData ? '(kosongkan jika tidak diganti)' : '<span class="text-red-500">*</span>' ?>
                    </label>
                    <?php if (!empty($editData['foto'])): ?>
                    <div class="mb-2 rounded-lg overflow-hidden border border-gray-200">
                        <img src="/cms-sekolah/assets/uploads/galeri/<?= htmlspecialchars($editData['foto']) ?>"
                             alt="" class="w-full h-28 object-cover">
                    </div>
                    <?php endif; ?>
                    <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-purple-400 hover:bg-purple-50 transition-all group">
                        <i class="fas fa-cloud-arrow-up text-gray-400 group-hover:text-purple-500 text-xl mb-1 transition-colors"></i>
                        <span class="text-xs text-gray-500 group-hover:text-purple-600">Klik untuk pilih foto</span>
                        <span class="text-xs text-gray-400">JPG, PNG, WEBP — maks 2MB</span>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="hidden"
                               onchange="previewSingle(this)">
                    </label>
                    <img id="singlePreview" src="" alt="" class="hidden mt-2 rounded-lg w-full h-28 object-cover border border-gray-200">
                </div>

                <button type="submit"
                        class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2.5 rounded-xl transition-all text-sm flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                    <i class="fas <?= $editData ? 'fa-save' : 'fa-plus' ?>"></i>
                    <?= $editData ? 'Simpan Perubahan' : 'Tambah ke Galeri' ?>
                </button>
            </form>
        </div>

        <!-- Upload Massal -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mt-4">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                    <i class="fas fa-layer-group text-blue-500"></i> Upload Massal
                </h2>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                <input type="hidden" name="action" value="bulk_upload">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1.5">Prefix Judul</label>
                    <input type="text" name="judul_prefix" value="Kegiatan"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-300 outline-none bg-gray-50 focus:bg-white transition">
                    <p class="text-xs text-gray-400 mt-1">Judul akan: "Kegiatan 1", "Kegiatan 2", dst.</p>
                </div>
                <div>
                    <label class="flex flex-col items-center justify-center w-full h-20 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-all group">
                        <i class="fas fa-images text-gray-400 group-hover:text-blue-500 text-lg mb-1 transition-colors"></i>
                        <span class="text-xs text-gray-500 group-hover:text-blue-600" id="bulkLabel">Pilih beberapa foto sekaligus</span>
                        <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp"
                               multiple class="hidden"
                               onchange="document.getElementById('bulkLabel').textContent = this.files.length + ' foto dipilih'">
                    </label>
                </div>
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl transition-all text-sm flex items-center justify-center gap-2">
                    <i class="fas fa-upload"></i> Upload Semua
                </button>
            </form>
        </div>
    </div>

    <!-- Grid Foto -->
    <div class="xl:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                    <i class="fas fa-images text-gray-400"></i> Foto Tersimpan
                    <span class="bg-gray-200 text-gray-600 text-xs px-2 py-0.5 rounded-full"><?= $total ?></span>
                </h2>
                <div class="flex gap-2">
                    <form method="GET" class="flex gap-2">
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari judul..."
                               class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-purple-300 outline-none">
                        <button type="submit" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-purple-700 transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($search): ?>
                        <a href="/cms-sekolah/admin/galeri.php" class="bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs hover:bg-gray-300 transition-colors">
                            <i class="fas fa-xmark"></i>
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Bulk delete toolbar -->
            <form method="POST" id="bulkForm">
                <input type="hidden" name="action" value="bulk_delete">

                <div id="bulkToolbar" class="hidden px-5 py-2 bg-red-50 border-b border-red-100 flex items-center gap-3">
                    <span class="text-sm text-red-700 font-medium"><span id="selectedCount">0</span> foto dipilih</span>
                    <button type="submit"
                            data-confirm="Yakin hapus foto-foto yang dipilih?"
                            class="bg-red-600 hover:bg-red-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                        <i class="fas fa-trash"></i> Hapus Pilihan
                    </button>
                    <button type="button" onclick="clearSelection()"
                            class="bg-gray-200 text-gray-600 text-xs px-3 py-1.5 rounded-lg hover:bg-gray-300 transition-colors">
                        Batal
                    </button>
                </div>

                <?php if (empty($list)): ?>
                <div class="text-center py-16 text-gray-400">
                    <i class="fas fa-images text-5xl mb-3 block opacity-30"></i>
                    <p>Belum ada foto di galeri.</p>
                </div>
                <?php else: ?>
                <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <?php foreach ($list as $g): ?>
                    <div class="group relative rounded-xl overflow-hidden border-2 border-transparent hover:border-purple-300 transition-all cursor-pointer"
                         onclick="toggleSelect(<?= $g['id'] ?>, this)">
                        <!-- Checkbox -->
                        <input type="checkbox" name="ids[]" value="<?= $g['id'] ?>"
                               class="absolute top-2 left-2 z-10 w-4 h-4 accent-purple-600 opacity-0 group-hover:opacity-100 transition-opacity"
                               id="chk<?= $g['id'] ?>" onchange="updateBulkBar()" onclick="event.stopPropagation()">

                        <!-- Foto -->
                        <?php if (!empty($g['foto'])): ?>
                        <img src="/cms-sekolah/assets/uploads/galeri/<?= htmlspecialchars($g['foto']) ?>"
                             alt="<?= htmlspecialchars($g['judul']) ?>"
                             class="w-full aspect-square object-cover">
                        <?php else: ?>
                        <div class="w-full aspect-square bg-purple-50 flex items-center justify-center">
                            <i class="fas fa-image text-purple-200 text-3xl"></i>
                        </div>
                        <?php endif; ?>

                        <!-- Overlay info -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-2">
                            <p class="text-white text-xs font-medium line-clamp-1"><?= htmlspecialchars($g['judul']) ?></p>
                            <p class="text-white/60 text-xs"><?= formatTanggal($g['created_at']) ?></p>
                            <!-- Action buttons -->
                            <div class="flex gap-1.5 mt-2">
                                <a href="/cms-sekolah/admin/galeri.php?edit=<?= $g['id'] ?>"
                                   onclick="event.stopPropagation()"
                                   class="flex-1 bg-white/20 hover:bg-amber-500 backdrop-blur-sm text-white text-xs py-1 rounded-lg text-center transition-colors">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <button type="button"
                                        onclick="event.stopPropagation(); quickDelete(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['judul'])) ?>')"
                                        class="flex-1 bg-white/20 hover:bg-red-500 backdrop-blur-sm text-white text-xs py-1 rounded-lg transition-colors">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="flex justify-center items-center gap-2 px-5 py-4 border-t border-gray-100">
                    <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>"
                       class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs hover:bg-gray-200 transition-colors">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"
                       class="px-3 py-1.5 rounded-lg text-xs transition-colors <?= $i===$page ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
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
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- Hidden quick-delete form -->
<form method="POST" id="quickDeleteForm" class="hidden">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="quickDeleteId">
</form>

<script>
function previewSingle(input) {
    const preview = document.getElementById('singlePreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.classList.remove('hidden'); };
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleSelect(id, el) {
    const chk = document.getElementById('chk' + id);
    chk.checked = !chk.checked;
    el.classList.toggle('border-purple-500', chk.checked);
    el.classList.toggle('ring-2',            chk.checked);
    el.classList.toggle('ring-purple-400',   chk.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('#bulkForm input[name="ids[]"]:checked');
    const toolbar = document.getElementById('bulkToolbar');
    document.getElementById('selectedCount').textContent = checked.length;
    toolbar.classList.toggle('hidden', checked.length === 0);
    toolbar.classList.toggle('flex',   checked.length > 0);
}

function clearSelection() {
    document.querySelectorAll('#bulkForm input[name="ids[]"]').forEach(c => {
        c.checked = false;
        c.closest('.group').classList.remove('border-purple-500','ring-2','ring-purple-400');
    });
    updateBulkBar();
}

function quickDelete(id, title) {
    if (!confirm('Hapus foto "' + title + '"? Tindakan ini tidak dapat dibatalkan.')) return;
    document.getElementById('quickDeleteId').value = id;
    document.getElementById('quickDeleteForm').submit();
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
