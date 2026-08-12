<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$pdo = getDB();

$alertMsg  = '';
$alertType = ''; // 'success' | 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passwordLama     = $_POST['password_lama']     ?? '';
    $passwordBaru     = $_POST['password_baru']     ?? '';
    $konfirmasiPassword = $_POST['konfirmasi_password'] ?? '';

    // ── Validasi kosong ───────────────────────────────────────────────────────
    if ($passwordLama === '' || $passwordBaru === '' || $konfirmasiPassword === '') {
        $alertMsg  = 'Semua kolom wajib diisi.';
        $alertType = 'error';
    } else {
        // ── Ambil password dari DB berdasarkan admin yang sedang login ─────────
        $adminId = (int)$_SESSION['admin_id'];
        $stmt    = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$adminId]);
        $user = $stmt->fetch();

        if (!$user) {
            $alertMsg  = 'Data akun tidak ditemukan.';
            $alertType = 'error';

        // ── Cek password lama: string comparison langsung (plaintext) ──────────
        } elseif ($passwordLama !== $user['password']) {
            $alertMsg  = 'Password lama yang Anda masukkan salah.';
            $alertType = 'error';

        // ── Cek kecocokan password baru ───────────────────────────────────────
        } elseif ($passwordBaru !== $konfirmasiPassword) {
            $alertMsg  = 'Password baru dan konfirmasi password tidak cocok.';
            $alertType = 'error';

        } else {
            // ── Semua lolos — simpan plaintext langsung via prepared statement ──
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$passwordBaru, $adminId]);

            $alertMsg  = 'Password berhasil diubah. Silakan gunakan password baru Anda mulai sekarang.';
            $alertType = 'success';
        }
    }
}

$pageTitle = 'Ganti Password';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-lg mx-auto">

    <!-- Breadcrumb -->
    <nav class="text-sm text-gray-500 mb-5 flex items-center gap-1.5">
        <a href="/cms-sekolah/admin/dashboard.php" class="hover:text-primary-700 transition-colors">Dashboard</a>
        <i class="fas fa-chevron-right text-xs text-gray-300"></i>
        <span class="text-gray-700 font-medium">Ganti Password</span>
    </nav>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        <!-- Header Card -->
        <div class="flex items-center gap-3 px-6 py-5 border-b border-gray-100 bg-gray-50">
            <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-lock text-primary-600 text-base"></i>
            </div>
            <div>
                <h2 class="font-bold text-gray-800">Ganti Password</h2>
                <p class="text-xs text-gray-500 mt-0.5">Akun: <strong><?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin') ?></strong></p>
            </div>
        </div>

        <!-- Alert -->
        <?php if ($alertMsg !== ''): ?>
        <?php
            $styles = [
                'success' => 'bg-green-50 border-green-400 text-green-800',
                'error'   => 'bg-red-50   border-red-400   text-red-800',
            ];
            $icons = [
                'success' => 'fa-circle-check',
                'error'   => 'fa-circle-xmark',
            ];
        ?>
        <div class="mx-6 mt-5 border-l-4 p-4 rounded-r-xl flex items-start gap-3
                    <?= $styles[$alertType] ?? $styles['error'] ?>">
            <i class="fas <?= $icons[$alertType] ?? $icons['error'] ?> mt-0.5 flex-shrink-0"></i>
            <span class="text-sm"><?= htmlspecialchars($alertMsg) ?></span>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="/cms-sekolah/admin/ganti_password.php" class="p-6 space-y-5">

            <!-- Password Lama -->
            <div>
                <label for="password_lama" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Password Lama <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <i class="fas fa-lock-open absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                    <input
                        type="password"
                        id="password_lama"
                        name="password_lama"
                        placeholder="Masukkan password lama Anda"
                        autocomplete="current-password"
                        required
                        class="w-full pl-10 pr-11 py-2.5 border border-gray-200 rounded-xl text-sm
                               bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary-300
                               focus:border-primary-400 outline-none transition"
                    >
                    <button type="button"
                            onclick="toggleVisibility('password_lama', 'eye_lama')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors text-sm">
                        <i id="eye_lama" class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Divider -->
            <div class="border-t border-gray-100"></div>

            <!-- Password Baru -->
            <div>
                <label for="password_baru" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Password Baru <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <i class="fas fa-key absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                    <input
                        type="password"
                        id="password_baru"
                        name="password_baru"
                        placeholder="Masukkan password baru"
                        autocomplete="new-password"
                        required
                        oninput="checkMatch()"
                        class="w-full pl-10 pr-11 py-2.5 border border-gray-200 rounded-xl text-sm
                               bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary-300
                               focus:border-primary-400 outline-none transition"
                    >
                    <button type="button"
                            onclick="toggleVisibility('password_baru', 'eye_baru')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors text-sm">
                        <i id="eye_baru" class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Konfirmasi Password Baru -->
            <div>
                <label for="konfirmasi_password" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Konfirmasi Password Baru <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <i class="fas fa-key absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                    <input
                        type="password"
                        id="konfirmasi_password"
                        name="konfirmasi_password"
                        placeholder="Ulangi password baru"
                        autocomplete="new-password"
                        required
                        oninput="checkMatch()"
                        class="w-full pl-10 pr-11 py-2.5 border border-gray-200 rounded-xl text-sm
                               bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary-300
                               focus:border-primary-400 outline-none transition"
                    >
                    <button type="button"
                            onclick="toggleVisibility('konfirmasi_password', 'eye_konfirmasi')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors text-sm">
                        <i id="eye_konfirmasi" class="fas fa-eye"></i>
                    </button>
                </div>
                <!-- Indikator kecocokan real-time -->
                <p id="matchMsg" class="text-xs mt-1.5 hidden"></p>
            </div>

            <!-- Tombol Submit -->
            <div class="pt-2">
                <button type="submit"
                        class="w-full bg-primary-700 hover:bg-primary-800 active:scale-95 text-white
                               font-semibold py-3 px-6 rounded-xl transition-all flex items-center
                               justify-center gap-2 shadow-md hover:shadow-lg">
                    <i class="fas fa-save"></i> Simpan Password Baru
                </button>
            </div>
        </form>
    </div>

    <!-- Link kembali -->
    <div class="mt-4 text-center">
        <a href="/cms-sekolah/admin/dashboard.php"
           class="text-sm text-gray-500 hover:text-primary-700 transition-colors flex items-center justify-center gap-1.5">
            <i class="fas fa-arrow-left text-xs"></i> Kembali ke Dashboard
        </a>
    </div>
</div>

<script>
// Toggle show/hide password
function toggleVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    const isPassword = input.type === 'password';
    input.type  = isPassword ? 'text' : 'password';
    icon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// Real-time cek kecocokan password baru vs konfirmasi
function checkMatch() {
    const baru       = document.getElementById('password_baru').value;
    const konfirmasi = document.getElementById('konfirmasi_password').value;
    const msg        = document.getElementById('matchMsg');

    if (konfirmasi === '') {
        msg.classList.add('hidden');
        return;
    }

    if (baru === konfirmasi) {
        msg.textContent  = '✓ Password cocok';
        msg.className    = 'text-xs mt-1.5 text-green-600 font-medium';
    } else {
        msg.textContent  = '✗ Password tidak cocok';
        msg.className    = 'text-xs mt-1.5 text-red-500 font-medium';
    }
    msg.classList.remove('hidden');
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
