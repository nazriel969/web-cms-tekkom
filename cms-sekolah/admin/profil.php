<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$pdo = getDB();

$alertMsg  = flashMessage('alert_msg')  ?? '';
$alertType = flashMessage('alert_type') ?? 'success';

// Ambil data profil (selalu 1 row)
$profil = $pdo->query("SELECT * FROM profil_sekolah LIMIT 1")->fetch();

// Jika belum ada row, insert kosong
if (!$profil) {
    $pdo->exec("INSERT INTO profil_sekolah (nama_sekolah) VALUES ('Nama Sekolah')");
    $profil = $pdo->query("SELECT * FROM profil_sekolah LIMIT 1")->fetch();
}

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'nama_sekolah'   => sanitize($_POST['nama_sekolah']   ?? ''),
        'npsn'           => sanitize($_POST['npsn']           ?? ''),
        'sambutan_kepsek'=> trim($_POST['sambutan_kepsek']    ?? ''),
        'sejarah'        => trim($_POST['sejarah']            ?? ''),
        'visi'           => trim($_POST['visi']               ?? ''),
        'misi'           => trim($_POST['misi']               ?? ''),
        'alamat'         => sanitize($_POST['alamat']         ?? ''),
        'telepon'        => sanitize($_POST['telepon']        ?? ''),
        'email'          => filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
        'map_embed'      => trim($_POST['map_embed']          ?? ''),
    ];

    $errors = [];
    if (mb_strlen($fields['nama_sekolah']) < 3) $errors[] = 'Nama sekolah minimal 3 karakter.';
    if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    // Upload foto kepala sekolah
    if (!empty($_FILES['foto_kepsek']['name'])) {
        $uploaded = uploadImage($_FILES['foto_kepsek'], __DIR__ . '/../assets/uploads/profil/');
        if ($uploaded === false) {
            $errors[] = 'Upload foto gagal. Format JPG/PNG/WEBP, maksimal 2MB.';
        } else {
            // Hapus foto lama
            if (!empty($profil['foto_kepsek'])) {
                $oldPath = __DIR__ . '/../assets/uploads/profil/' . $profil['foto_kepsek'];
                if (file_exists($oldPath)) unlink($oldPath);
            }
            $fields['foto_kepsek'] = $uploaded;
        }
    }

    if (empty($errors)) {
        $setClauses = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $stmt = $pdo->prepare("UPDATE profil_sekolah SET $setClauses WHERE id = ?");
        $stmt->execute(array_merge(array_values($fields), [$profil['id']]));

        flashMessage('alert_msg',  'Data profil sekolah berhasil disimpan.');
        flashMessage('alert_type', 'success');
        redirect('/cms-sekolah/admin/profil.php');
    } else {
        $alertMsg  = implode(' ', $errors);
        $alertType = 'error';
        // Merge posted values untuk repopulate form
        $profil = array_merge($profil, array_map('htmlspecialchars_decode', $fields));
    }
}

$pageTitle = 'Pengaturan Profil Sekolah';
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

<form method="POST" enctype="multipart/form-data">
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- Kiri: Informasi Dasar + Kontak -->
    <div class="xl:col-span-2 space-y-6">

        <!-- Informasi Dasar -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <i class="fas fa-school text-blue-500"></i>
                <h2 class="font-bold text-gray-800">Informasi Dasar</h2>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Nama Sekolah <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nama_sekolah"
                           value="<?= htmlspecialchars($profil['nama_sekolah'] ?? '') ?>"
                           placeholder="Contoh: SMA Negeri 1 Kota"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 focus:border-primary-400 outline-none transition bg-gray-50 focus:bg-white" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">NPSN</label>
                    <input type="text" name="npsn"
                           value="<?= htmlspecialchars($profil['npsn'] ?? '') ?>"
                           placeholder="Nomor Pokok Sekolah Nasional"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 outline-none transition bg-gray-50 focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Sekolah</label>
                    <input type="email" name="email"
                           value="<?= htmlspecialchars($profil['email'] ?? '') ?>"
                           placeholder="email@sekolah.sch.id"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 outline-none transition bg-gray-50 focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Telepon</label>
                    <input type="text" name="telepon"
                           value="<?= htmlspecialchars($profil['telepon'] ?? '') ?>"
                           placeholder="(021) 1234-5678"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 outline-none transition bg-gray-50 focus:bg-white">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Alamat Lengkap</label>
                    <textarea name="alamat" rows="2"
                              placeholder="Jl. Pendidikan No. 1, Kota, Provinsi"
                              class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 outline-none transition resize-none bg-gray-50 focus:bg-white"><?= htmlspecialchars($profil['alamat'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Visi & Misi -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <i class="fas fa-eye text-green-500"></i>
                <h2 class="font-bold text-gray-800">Visi &amp; Misi</h2>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Visi Sekolah</label>
                    <textarea name="visi" rows="3"
                              placeholder="Tuliskan visi sekolah di sini..."
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 outline-none transition resize-y bg-gray-50 focus:bg-white"><?= htmlspecialchars($profil['visi'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Misi Sekolah</label>
                    <textarea name="misi" rows="5"
                              placeholder="Tuliskan misi sekolah di sini (tiap baris untuk tiap poin)..."
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 outline-none transition resize-y bg-gray-50 focus:bg-white"><?= htmlspecialchars($profil['misi'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Sejarah -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <i class="fas fa-book-open text-amber-500"></i>
                <h2 class="font-bold text-gray-800">Sejarah Sekolah</h2>
            </div>
            <div class="p-6">
                <textarea name="sejarah" rows="8"
                          placeholder="Tuliskan sejarah berdirinya sekolah di sini..."
                          class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-300 outline-none transition resize-y bg-gray-50 focus:bg-white"><?= htmlspecialchars($profil['sejarah'] ?? '') ?></textarea>
                <p class="text-xs text-gray-400 mt-1">HTML dasar diperbolehkan.</p>
            </div>
        </div>

        <!-- Embed Peta -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <i class="fas fa-map text-red-500"></i>
                <h2 class="font-bold text-gray-800">Embed Peta (Google Maps)</h2>
            </div>
            <div class="p-6">
                <textarea name="map_embed" rows="4"
                          placeholder='<iframe src="https://maps.google.com/..." ...></iframe>'
                          class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-primary-300 outline-none transition resize-y bg-gray-50 focus:bg-white"><?= htmlspecialchars($profil['map_embed'] ?? '') ?></textarea>
                <p class="text-xs text-gray-400 mt-1">Paste kode embed dari Google Maps (Share → Embed a map).</p>
                <?php if (!empty($profil['map_embed'])): ?>
                <div class="mt-3 rounded-xl overflow-hidden border border-gray-200" style="height:200px">
                    <?= $profil['map_embed'] ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kanan: Foto + Sambutan Kepsek -->
    <div class="space-y-6">

        <!-- Foto & Sambutan Kepala Sekolah -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <i class="fas fa-user-tie text-purple-500"></i>
                <h2 class="font-bold text-gray-800 text-sm">Kepala Sekolah</h2>
            </div>
            <div class="p-5 space-y-4">
                <!-- Preview foto -->
                <?php if (!empty($profil['foto_kepsek'])): ?>
                <div class="text-center">
                    <img src="/cms-sekolah/assets/uploads/profil/<?= htmlspecialchars($profil['foto_kepsek']) ?>"
                         id="fotoPreview"
                         alt="Foto Kepala Sekolah"
                         class="w-32 h-32 rounded-2xl object-cover mx-auto border-4 border-gray-100 shadow-md">
                </div>
                <?php else: ?>
                <div class="text-center">
                    <div id="fotoPlaceholder" class="w-32 h-32 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto border-4 border-gray-100">
                        <i class="fas fa-user-tie text-gray-300 text-3xl"></i>
                    </div>
                    <img id="fotoPreview" src="" alt="" class="hidden w-32 h-32 rounded-2xl object-cover mx-auto border-4 border-gray-100 shadow-md">
                </div>
                <?php endif; ?>

                <!-- Upload -->
                <label class="flex flex-col items-center justify-center w-full h-20 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-purple-400 hover:bg-purple-50 transition-all group">
                    <i class="fas fa-camera text-gray-400 group-hover:text-purple-500 text-lg mb-1 transition-colors"></i>
                    <span class="text-xs text-gray-500 group-hover:text-purple-600">Ganti foto kepala sekolah</span>
                    <input type="file" name="foto_kepsek" accept="image/jpeg,image/png,image/webp" class="hidden"
                           onchange="previewFoto(this)">
                </label>
                <p class="text-xs text-gray-400 text-center">JPG, PNG, WEBP — maks 2MB</p>

                <!-- Sambutan -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Sambutan Kepala Sekolah</label>
                    <textarea name="sambutan_kepsek" rows="10"
                              placeholder="Tuliskan kata sambutan dari Kepala Sekolah..."
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-300 outline-none transition resize-y bg-gray-50 focus:bg-white"><?= htmlspecialchars($profil['sambutan_kepsek'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Tombol Simpan -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <button type="submit"
                    class="w-full bg-primary-700 hover:bg-primary-800 text-white font-semibold py-3 px-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                <i class="fas fa-save"></i> Simpan Semua Perubahan
            </button>
            <p class="text-xs text-gray-400 text-center mt-3">
                <i class="fas fa-info-circle mr-1"></i>Perubahan langsung tampil di halaman publik.
            </p>
        </div>
    </div>
</div>
</form>

<script>
function previewFoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview     = document.getElementById('fotoPreview');
            const placeholder = document.getElementById('fotoPlaceholder');
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            if (placeholder) placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
