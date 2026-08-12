<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$pdo = getDB();

// ── Ambil semua kategori untuk dropdown ──────────────────────────────────────
$categories = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

// ── Handle POST Actions ──────────────────────────────────────────────────────
$alertMsg  = flashMessage('alert_msg')  ?? '';
$alertType = flashMessage('alert_type') ?? 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── HAPUS ────────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Hapus file gambar jika ada
            $row = $pdo->prepare("SELECT gambar FROM berita WHERE id = ?");
            $row->execute([$id]);
            $existing = $row->fetch();
            if (!empty($existing['gambar'])) {
                $imgPath = __DIR__ . '/../assets/uploads/berita/' . $existing['gambar'];
                if (file_exists($imgPath)) unlink($imgPath);
            }
            $pdo->prepare("DELETE FROM berita WHERE id = ?")->execute([$id]);
            flashMessage('alert_msg',  'Berita berhasil dihapus.');
            flashMessage('alert_type', 'success');
        }
        redirect('/cms-sekolah/admin/berita.php');
    }

    // ── SIMPAN (Tambah / Edit) ───────────────────────────────────────────────
    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $judul       = sanitize($_POST['judul']       ?? '');
        $kategori_id = (int)($_POST['kategori_id']   ?? 0);
        $isi_berita  = $_POST['isi_berita']           ?? '';   // allow HTML dari editor
        $status      = in_array($_POST['status'] ?? '', ['published','draft']) ? $_POST['status'] : 'draft';
        $slug        = createSlug($judul);

        $errors = [];
        if (mb_strlen($judul) < 5)   $errors[] = 'Judul minimal 5 karakter.';
        if (mb_strlen(strip_tags($isi_berita)) < 10) $errors[] = 'Isi berita terlalu singkat.';

        // Upload gambar jika ada
        $gambar = null;
        if (!empty($_FILES['gambar']['name'])) {
            $uploaded = uploadImage($_FILES['gambar'], __DIR__ . '/../assets/uploads/berita/');
            if ($uploaded === false) {
                $errors[] = 'Upload gambar gagal. Pastikan format JPG/PNG/WEBP dan maksimal 2MB.';
            } else {
                $gambar = $uploaded;
            }
        }

        if (empty($errors)) {
            if ($id > 0) {
                // Edit — pastikan slug unik selain diri sendiri
                $slugCheck = $pdo->prepare("SELECT id FROM berita WHERE slug = ? AND id != ?");
                $slugCheck->execute([$slug, $id]);
                if ($slugCheck->fetch()) $slug .= '-' . $id;

                if ($gambar) {
                    // Hapus gambar lama
                    $old = $pdo->prepare("SELECT gambar FROM berita WHERE id = ?");
                    $old->execute([$id]);
                    $oldData = $old->fetch();
                    if (!empty($oldData['gambar'])) {
                        $oldPath = __DIR__ . '/../assets/uploads/berita/' . $oldData['gambar'];
                        if (file_exists($oldPath)) unlink($oldPath);
                    }
                    $stmt = $pdo->prepare("UPDATE berita SET judul=?,slug=?,kategori_id=?,isi_berita=?,gambar=?,status=? WHERE id=?");
                    $stmt->execute([$judul,$slug,$kategori_id,$isi_berita,$gambar,$status,$id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE berita SET judul=?,slug=?,kategori_id=?,isi_berita=?,status=? WHERE id=?");
                    $stmt->execute([$judul,$slug,$kategori_id,$isi_berita,$status,$id]);
                }
                flashMessage('alert_msg',  'Berita berhasil diperbarui.');
            } else {
                // Tambah baru
                $slugCheck = $pdo->prepare("SELECT id FROM berita WHERE slug = ?");
                $slugCheck->execute([$slug]);
                if ($slugCheck->fetch()) $slug .= '-' . time();

                $stmt = $pdo->prepare("
                    INSERT INTO berita (user_id,kategori_id,judul,slug,isi_berita,gambar,status,views,created_at)
                    VALUES (?,?,?,?,?,?,?,0,NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'],$kategori_id,$judul,$slug,$isi_berita,$gambar,$status]);
                flashMessage('alert_msg',  'Berita berhasil ditambahkan.');
            }
            flashMessage('alert_type', 'success');
            redirect('/cms-sekolah/admin/berita.php');
        } else {
            $alertMsg  = implode(' ', $errors);
            $alertType = 'error';
        }
    }
}

// ── Mode Edit: load data berita ──────────────────────────────────────────────
$editData = null;
$editId   = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmtE = $pdo->prepare("SELECT * FROM berita WHERE id = ?");
    $stmtE->execute([$editId]);
    $editData = $stmtE->fetch();
}

// ── Daftar berita (dengan search & filter) ───────────────────────────────────
$search     = trim($_GET['q']        ?? '');
$filterStat = trim($_GET['status']   ?? '');
$filterKat  = (int)($_GET['kategori'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 10;
$offset     = ($page - 1) * $perPage;

$params = [];
$where  = "WHERE 1=1";
if ($search !== '')     { $where .= " AND b.judul LIKE ?"; $params[] = "%$search%"; }
if ($filterStat !== '') { $where .= " AND b.status = ?";   $params[] = $filterStat; }
if ($filterKat > 0)     { $where .= " AND b.kategori_id=?";$params[] = $filterKat; }

$total      = (int)$pdo->prepare("SELECT COUNT(*) FROM berita b $where")->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM berita b $where") : null;
$countStmt  = $pdo->prepare("SELECT COUNT(*) FROM berita b $where");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$listStmt = $pdo->prepare("
    SELECT b.id, b.judul, b.slug, b.status, b.views, b.gambar, b.created_at,
           k.nama_kategori, u.nama_lengkap
    FROM berita b
    LEFT JOIN kategori k ON b.kategori_id = k.id
    LEFT JOIN users u ON b.user_id = u.id
    $where
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
");
$listStmt->execute(array_merge($params, [$perPage, $offset]));
$beritaList = $listStmt->fetchAll();

$pageTitle = $editData ? 'Edit Berita' : 'Kelola Berita';
include __DIR__ . '/../includes/admin_header.php';
?>

<!-- ── Alert ─────────────────────────────────────────────────────────────── -->
<?php if ($alertMsg): ?>
<?php $aStyle = $alertType==='success' ? 'bg-green-50 border-green-400 text-green-800' : 'bg-red-50 border-red-400 text-red-800'; ?>
<?php $aIcon  = $alertType==='success' ? 'fa-circle-check' : 'fa-circle-xmark'; ?>
<div class="border-l-4 p-4 rounded-r-xl mb-5 flex items-start gap-3 <?= $aStyle ?>" data-auto-dismiss>
    <i class="fas <?= $aIcon ?> mt-0.5 flex-shrink-0"></i>
    <span class="text-sm"><?= htmlspecialchars($alertMsg) ?></span>
</div>
<?php endif; ?>

<!-- ── Form Tambah / Edit ────────────────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
        <h2 class="font-bold text-gray-800 flex items-center gap-2">
            <i class="fas <?= $editData ? 'fa-pen-to-square text-blue-500' : 'fa-plus-circle text-green-500' ?>"></i>
            <?= $editData ? 'Edit Berita' : 'Tambah Berita Baru' ?>
        </h2>
        <?php if ($editData): ?>
        <a href="/cms-sekolah/admin/berita.php"
           class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1.5 bg-gray-200 hover:bg-gray-300 px-3 py-1.5 rounded-lg transition-colors">
            <i class="fas fa-xmark"></i> Batal Edit
        </a>
        <?php endif; ?>
    </div>

    <form method="POST" enctype="multipart/form-data" class="p-6">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id"     value="<?= $editData['id'] ?? 0 ?>">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Kiri: Judul + Isi -->
            <div class="lg:col-span-2 space-y-5">
                <!-- Judul -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Judul Berita <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="judul" id="judul"
                           value="<?= htmlspecialchars($editData['judul'] ?? '') ?>"
                           placeholder="Masukkan judul berita yang menarik..."
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 focus:border-primary-400 outline-none transition bg-gray-50 focus:bg-white"
                           required oninput="generateSlug(this.value)">
                </div>
                <!-- Slug preview -->
                <div class="flex items-center gap-2 -mt-3 text-xs text-gray-400">
                    <i class="fas fa-link"></i>
                    <span>Slug: <span id="slugPreview" class="text-primary-600 font-mono">
                        <?= htmlspecialchars($editData['slug'] ?? '') ?>
                    </span></span>
                </div>
                <!-- Isi Berita -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Isi Berita <span class="text-red-500">*</span>
                    </label>
                    <textarea name="isi_berita" id="isi_berita" rows="14"
                              placeholder="Tulis isi berita di sini..."
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 focus:border-primary-400 outline-none transition resize-y bg-gray-50 focus:bg-white font-mono"><?= htmlspecialchars($editData['isi_berita'] ?? '') ?></textarea>
                    <p class="text-xs text-gray-400 mt-1">HTML diperbolehkan (&lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;, &lt;img&gt;, dsb.)</p>
                </div>
            </div>

            <!-- Kanan: Meta -->
            <div class="space-y-5">
                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                    <select name="status"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 outline-none bg-gray-50 focus:bg-white transition">
                        <option value="draft"     <?= (($editData['status'] ?? 'draft') === 'draft')     ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= (($editData['status'] ?? '') === 'published') ? 'selected' : '' ?>>Terbitkan</option>
                    </select>
                </div>
                <!-- Kategori -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori</label>
                    <select name="kategori_id"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 outline-none bg-gray-50 focus:bg-white transition">
                        <option value="0">— Pilih Kategori —</option>
                        <?php foreach ($categories as $kat): ?>
                        <option value="<?= $kat['id'] ?>"
                            <?= (($editData['kategori_id'] ?? 0) == $kat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kat['nama_kategori']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Gambar -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Thumbnail <?= $editData ? '(kosongkan jika tidak diganti)' : '' ?>
                    </label>
                    <?php if (!empty($editData['gambar'])): ?>
                    <div class="mb-2 rounded-xl overflow-hidden border border-gray-200">
                        <img src="/cms-sekolah/assets/uploads/berita/<?= htmlspecialchars($editData['gambar']) ?>"
                             alt="Thumbnail" class="w-full h-32 object-cover">
                    </div>
                    <?php endif; ?>
                    <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-primary-400 hover:bg-primary-50 transition-all group">
                        <i class="fas fa-cloud-arrow-up text-gray-400 group-hover:text-primary-500 text-2xl mb-1.5 transition-colors"></i>
                        <span class="text-xs text-gray-500 group-hover:text-primary-600">Klik untuk pilih gambar</span>
                        <span class="text-xs text-gray-400">JPG, PNG, WEBP — maks 2MB</span>
                        <input type="file" name="gambar" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"
                               onchange="previewImage(this)">
                    </label>
                    <img id="imgPreview" src="" alt="Preview" class="hidden mt-2 rounded-xl w-full h-32 object-cover border border-gray-200">
                </div>
                <!-- Submit -->
                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="flex-1 bg-primary-700 hover:bg-primary-800 text-white font-semibold py-2.5 px-4 rounded-xl transition-all text-sm flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                        <i class="fas <?= $editData ? 'fa-save' : 'fa-plus' ?>"></i>
                        <?= $editData ? 'Simpan Perubahan' : 'Tambah Berita' ?>
                    </button>
                </div>
                <!-- Kategori Baru -->
                <div class="border-t border-gray-100 pt-4">
                    <p class="text-xs font-medium text-gray-600 mb-2">Tambah Kategori Baru</p>
                    <div class="flex gap-2">
                        <input type="text" id="newKatInput" placeholder="Nama kategori..."
                               class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-primary-300 outline-none transition">
                        <button type="button" onclick="tambahKategori()"
                                class="bg-teal-600 hover:bg-teal-700 text-white px-3 py-2 rounded-lg text-xs font-medium transition-colors">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div id="katMsg" class="text-xs mt-1 hidden"></div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- ── Daftar Berita ──────────────────────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 px-6 py-4 border-b border-gray-100 bg-gray-50">
        <h2 class="font-bold text-gray-800 flex items-center gap-2">
            <i class="fas fa-list text-gray-400"></i> Daftar Berita
            <span class="bg-gray-200 text-gray-600 text-xs px-2 py-0.5 rounded-full"><?= $total ?></span>
        </h2>
        <!-- Filter -->
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari judul..."
                   class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-primary-300 outline-none">
            <select name="status" class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs outline-none bg-white focus:ring-2 focus:ring-primary-300">
                <option value="">Semua Status</option>
                <option value="published" <?= $filterStat==='published'?'selected':'' ?>>Terbit</option>
                <option value="draft"     <?= $filterStat==='draft'    ?'selected':'' ?>>Draft</option>
            </select>
            <select name="kategori" class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs outline-none bg-white focus:ring-2 focus:ring-primary-300">
                <option value="0">Semua Kategori</option>
                <?php foreach ($categories as $kat): ?>
                <option value="<?= $kat['id'] ?>" <?= $filterKat==$kat['id']?'selected':'' ?>>
                    <?= htmlspecialchars($kat['nama_kategori']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-primary-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-primary-800 transition-colors">
                <i class="fas fa-filter"></i>
            </button>
            <?php if ($search || $filterStat || $filterKat): ?>
            <a href="/cms-sekolah/admin/berita.php" class="bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs hover:bg-gray-300 transition-colors">
                <i class="fas fa-xmark"></i> Reset
            </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                    <th class="text-left px-6 py-3 font-medium">Berita</th>
                    <th class="text-left px-4 py-3 font-medium">Kategori</th>
                    <th class="text-center px-4 py-3 font-medium">Status</th>
                    <th class="text-center px-4 py-3 font-medium">Views</th>
                    <th class="text-center px-4 py-3 font-medium">Tanggal</th>
                    <th class="text-center px-4 py-3 font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($beritaList)): ?>
                <tr>
                    <td colspan="6" class="text-center py-12 text-gray-400">
                        <i class="fas fa-newspaper text-4xl mb-2 block opacity-30"></i>
                        Belum ada berita. Tambahkan berita pertama Anda!
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($beritaList as $b): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($b['gambar'])): ?>
                            <img src="/cms-sekolah/assets/uploads/berita/<?= htmlspecialchars($b['gambar']) ?>"
                                 alt="" class="w-12 h-12 rounded-lg object-cover flex-shrink-0 border border-gray-100">
                            <?php else: ?>
                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-newspaper text-gray-300"></i>
                            </div>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 line-clamp-1 max-w-[280px]">
                                    <?= htmlspecialchars($b['judul']) ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    oleh <?= htmlspecialchars($b['nama_lengkap'] ?? 'Admin') ?>
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        <?= htmlspecialchars($b['nama_kategori'] ?? '—') ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($b['status']==='published'): ?>
                        <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-medium px-2.5 py-1 rounded-full">
                            <i class="fas fa-circle text-[6px]"></i> Terbit
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-xs font-medium px-2.5 py-1 rounded-full">
                            <i class="fas fa-circle text-[6px]"></i> Draft
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center text-gray-600 font-medium text-sm">
                        <?= number_format($b['views']) ?>
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-gray-500 whitespace-nowrap">
                        <?= formatTanggal($b['created_at']) ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-2">
                            <a href="/cms-sekolah/detail-berita.php?slug=<?= urlencode($b['slug']) ?>" target="_blank"
                               class="w-8 h-8 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center transition-colors" title="Lihat">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            <a href="/cms-sekolah/admin/berita.php?edit=<?= $b['id'] ?>"
                               class="w-8 h-8 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center transition-colors" title="Edit">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= $b['id'] ?>">
                                <button type="submit"
                                        data-confirm="Yakin hapus berita &quot;<?= htmlspecialchars(addslashes($b['judul'])) ?>&quot;? Tindakan ini tidak dapat dibatalkan."
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
    <?php endif; ?>
</div>

<script>
// Auto-slug dari judul
function generateSlug(val) {
    let slug = val.toLowerCase()
                  .replace(/[^a-z0-9\s-]/g, '')
                  .replace(/[\s-]+/g, '-')
                  .replace(/^-|-$/g, '');
    document.getElementById('slugPreview').textContent = slug || '—';
}

// Preview gambar sebelum upload
function previewImage(input) {
    const preview = document.getElementById('imgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Tambah kategori via AJAX
function tambahKategori() {
    const nama = document.getElementById('newKatInput').value.trim();
    const msg  = document.getElementById('katMsg');
    if (!nama) { msg.textContent = 'Nama kategori tidak boleh kosong.'; msg.className = 'text-xs mt-1 text-red-500'; msg.classList.remove('hidden'); return; }

    fetch('/cms-sekolah/admin/ajax_kategori.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'nama=' + encodeURIComponent(nama)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const sel = document.querySelector('select[name="kategori_id"]');
            const opt = document.createElement('option');
            opt.value = data.id; opt.textContent = data.nama; opt.selected = true;
            sel.appendChild(opt);
            document.getElementById('newKatInput').value = '';
            msg.textContent = 'Kategori berhasil ditambahkan!';
            msg.className = 'text-xs mt-1 text-green-600';
            msg.classList.remove('hidden');
            setTimeout(() => msg.classList.add('hidden'), 3000);
        } else {
            msg.textContent = data.error || 'Gagal menambahkan kategori.';
            msg.className = 'text-xs mt-1 text-red-500';
            msg.classList.remove('hidden');
        }
    })
    .catch(() => { msg.textContent = 'Terjadi kesalahan.'; msg.classList.remove('hidden'); });
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
