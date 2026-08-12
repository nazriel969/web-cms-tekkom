<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Sudah login → redirect ke dashboard
if (!empty($_SESSION['admin_id'])) {
    redirect('/cms-sekolah/admin/dashboard.php');
}

// Generate CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$alertMsg   = '';
$alertType  = 'error';
$username   = '';
$activePanel = 'login'; // 'login' | 'lupa'

// ══════════════════════════════════════════════════════════════
//  PROSES LOGIN
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'login') {

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $alertMsg = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $alertMsg = 'Username dan password tidak boleh kosong.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT id, nama_lengkap, username, password, role FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID untuk mencegah session fixation
                session_regenerate_id(true);
                $_SESSION['admin_id']    = $user['id'];
                $_SESSION['admin_nama']  = $user['nama_lengkap'];
                $_SESSION['admin_role']  = $user['role'];
                $_SESSION['csrf_token']  = bin2hex(random_bytes(32));
                redirect('/cms-sekolah/admin/dashboard.php');
            } else {
                $alertMsg = 'Username atau password salah.';
                // Tambah sedikit delay untuk mencegah brute-force
                sleep(1);
            }
        }
    }
}

// ══════════════════════════════════════════════════════════════
//  PROSES LUPA PASSWORD
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'lupa') {
    $activePanel = 'lupa';

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $alertMsg = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $lupaUsername   = sanitize($_POST['lupa_username']    ?? '');
        $passwordBaru   = $_POST['lupa_password_baru']        ?? '';
        $konfirmasi     = $_POST['lupa_konfirmasi']           ?? '';

        if ($lupaUsername === '' || $passwordBaru === '' || $konfirmasi === '') {
            $alertMsg = 'Semua kolom wajib diisi.';
        } elseif ($passwordBaru !== $konfirmasi) {
            $alertMsg = 'Password baru dan konfirmasi tidak cocok.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$lupaUsername]);
            $user = $stmt->fetch();

            if (!$user) {
                $alertMsg = 'Username tidak ditemukan.';
            } else {
                // Simpan password baru langsung (plaintext)
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                    ->execute([$passwordBaru, $user['id']]);

                // Regenerate token setelah sukses
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $alertMsg    = 'Password berhasil direset! Silakan login dengan password baru.';
                $alertType   = 'success';
                $activePanel = 'login';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin | CMS Sekolah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT:'#1d4ed8', 600:'#2563eb', 700:'#1d4ed8', 800:'#1e40af', 900:'#1e3a8a' }
                    },
                    fontFamily: { sans: ['Inter','system-ui','sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-pattern {
            background-color: #1e3a8a;
            background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.05) 0%, transparent 50%),
                              radial-gradient(circle at 75% 75%, rgba(255,255,255,0.05) 0%, transparent 50%);
        }
        .panel { transition: all 0.25s ease; }
    </style>
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center p-4">

    <!-- Decorative blobs -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-primary-600 rounded-full opacity-20 blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-blue-400 rounded-full opacity-20 blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

            <!-- ── Header ──────────────────────────────────────────────── -->
            <div class="bg-gradient-to-br from-primary-800 to-primary-700 px-8 py-10 text-center">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                    <i class="fas fa-school text-white text-2xl"></i>
                </div>
                <h1 class="text-white font-bold text-2xl" id="cardTitle">
                    <?= $activePanel === 'lupa' ? 'Reset Password' : 'Panel Admin' ?>
                </h1>
                <p class="text-primary-200 text-sm mt-1" id="cardSubtitle">
                    <?= $activePanel === 'lupa' ? 'Buat password baru untuk akun Anda' : 'CMS Sekolah — Masuk ke akun Anda' ?>
                </p>
            </div>

            <div class="px-8 py-8">

                <!-- ── Alert ────────────────────────────────────────────── -->
                <?php if ($alertMsg !== ''): ?>
                <?php
                    $aStyle = $alertType === 'success'
                        ? 'bg-green-50 border-green-400 text-green-800'
                        : 'bg-red-50 border-red-400 text-red-800';
                    $aIcon  = $alertType === 'success' ? 'fa-circle-check' : 'fa-circle-xmark';
                ?>
                <div class="border-l-4 p-4 rounded-r-xl mb-6 flex items-start gap-3 text-sm <?= $aStyle ?>">
                    <i class="fas <?= $aIcon ?> mt-0.5 flex-shrink-0"></i>
                    <span><?= htmlspecialchars($alertMsg) ?></span>
                </div>
                <?php endif; ?>

                <!-- ══════════════════════════════════════════════════════ -->
                <!--  PANEL LOGIN                                          -->
                <!-- ══════════════════════════════════════════════════════ -->
                <div id="panelLogin" class="panel <?= $activePanel === 'lupa' ? 'hidden' : '' ?>">
                    <form method="POST" action="/cms-sekolah/admin/login.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form_type"  value="login">

                        <!-- Username -->
                        <div class="mb-5">
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                            <div class="relative">
                                <i class="fas fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" id="username" name="username"
                                       value="<?= htmlspecialchars($username) ?>"
                                       placeholder="Masukkan username"
                                       autocomplete="username"
                                       class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl text-sm bg-gray-50
                                              focus:bg-white focus:ring-2 focus:ring-primary-300 focus:border-primary-400
                                              outline-none transition"
                                       required autofocus>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-2">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="password" id="password" name="password"
                                       placeholder="Masukkan password"
                                       autocomplete="current-password"
                                       class="w-full pl-10 pr-11 py-3 border border-gray-200 rounded-xl text-sm bg-gray-50
                                              focus:bg-white focus:ring-2 focus:ring-primary-300 focus:border-primary-400
                                              outline-none transition"
                                       required>
                                <button type="button" id="togglePass"
                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors text-sm">
                                    <i class="fas fa-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Link Lupa Password -->
                        <div class="text-right mb-6">
                            <button type="button" onclick="switchPanel('lupa')"
                                    class="text-xs text-primary-600 hover:text-primary-800 hover:underline transition-colors">
                                Lupa password?
                            </button>
                        </div>

                        <button type="submit"
                                class="w-full bg-primary-700 hover:bg-primary-800 active:scale-95 text-white font-semibold
                                       py-3 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl flex items-center justify-center gap-2">
                            <i class="fas fa-right-to-bracket"></i> Masuk
                        </button>
                    </form>
                </div>

                <!-- ══════════════════════════════════════════════════════ -->
                <!--  PANEL LUPA PASSWORD                                  -->
                <!-- ══════════════════════════════════════════════════════ -->
                <div id="panelLupa" class="panel <?= $activePanel === 'login' ? 'hidden' : '' ?>">

                    <p class="text-sm text-gray-500 mb-5 text-center">
                        Masukkan username akun Anda dan buat password baru.
                    </p>

                    <form method="POST" action="/cms-sekolah/admin/login.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form_type"  value="lupa">

                        <!-- Username -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                            <div class="relative">
                                <i class="fas fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" name="lupa_username"
                                       placeholder="Masukkan username akun Anda"
                                       autocomplete="username"
                                       class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl text-sm bg-gray-50
                                              focus:bg-white focus:ring-2 focus:ring-primary-300 focus:border-primary-400
                                              outline-none transition"
                                       required>
                            </div>
                        </div>

                        <!-- Password Baru -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password Baru</label>
                            <div class="relative">
                                <i class="fas fa-key absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="password" name="lupa_password_baru" id="lupaPwBaru"
                                       placeholder="Masukkan password baru"
                                       autocomplete="new-password"
                                       oninput="checkLupaMatch()"
                                       class="w-full pl-10 pr-11 py-3 border border-gray-200 rounded-xl text-sm bg-gray-50
                                              focus:bg-white focus:ring-2 focus:ring-primary-300 focus:border-primary-400
                                              outline-none transition"
                                       required>
                                <button type="button" onclick="toggleVis('lupaPwBaru','eyeLupaBaru')"
                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors text-sm">
                                    <i class="fas fa-eye" id="eyeLupaBaru"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Konfirmasi Password Baru -->
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password Baru</label>
                            <div class="relative">
                                <i class="fas fa-key absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="password" name="lupa_konfirmasi" id="lupaPwKonfirmasi"
                                       placeholder="Ulangi password baru"
                                       autocomplete="new-password"
                                       oninput="checkLupaMatch()"
                                       class="w-full pl-10 pr-11 py-3 border border-gray-200 rounded-xl text-sm bg-gray-50
                                              focus:bg-white focus:ring-2 focus:ring-primary-300 focus:border-primary-400
                                              outline-none transition"
                                       required>
                                <button type="button" onclick="toggleVis('lupaPwKonfirmasi','eyeLupaKonfirmasi')"
                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors text-sm">
                                    <i class="fas fa-eye" id="eyeLupaKonfirmasi"></i>
                                </button>
                            </div>
                            <!-- Indikator kecocokan -->
                            <p id="lupaMatchMsg" class="text-xs mt-1.5 hidden"></p>
                        </div>

                        <div class="mt-6 flex flex-col gap-3">
                            <button type="submit"
                                    class="w-full bg-primary-700 hover:bg-primary-800 active:scale-95 text-white font-semibold
                                           py-3 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl flex items-center justify-center gap-2">
                                <i class="fas fa-rotate-right"></i> Reset Password
                            </button>
                            <button type="button" onclick="switchPanel('login')"
                                    class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium
                                           py-2.5 px-6 rounded-xl transition-all text-sm flex items-center justify-center gap-2">
                                <i class="fas fa-arrow-left text-xs"></i> Kembali ke Login
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Link Website -->
                <div class="mt-6 pt-5 border-t border-gray-100 text-center">
                    <a href="/cms-sekolah/index.php"
                       class="text-sm text-gray-500 hover:text-primary-700 transition-colors flex items-center justify-center gap-1.5">
                        <i class="fas fa-arrow-left text-xs"></i> Kembali ke Website
                    </a>
                </div>
            </div>
        </div>

        <p class="text-center text-white/50 text-xs mt-6">
            &copy; <?= date('Y') ?> CMS Sekolah. Hak Akses Terbatas.
        </p>
    </div>

<script>
// ── Toggle show/hide password ────────────────────────────────────────────────
const toggleBtn = document.getElementById('togglePass');
const passInput = document.getElementById('password');
const eyeIcon   = document.getElementById('eyeIcon');

toggleBtn.addEventListener('click', function () {
    const isPassword  = passInput.type === 'password';
    passInput.type    = isPassword ? 'text' : 'password';
    eyeIcon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
});

function toggleVis(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    const isPw  = input.type === 'password';
    input.type  = isPw ? 'text' : 'password';
    icon.className = isPw ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// ── Switch panel login ↔ lupa password ──────────────────────────────────────
function switchPanel(panel) {
    const isLupa   = panel === 'lupa';
    const title    = document.getElementById('cardTitle');
    const subtitle = document.getElementById('cardSubtitle');

    document.getElementById('panelLogin').classList.toggle('hidden',  isLupa);
    document.getElementById('panelLupa').classList.toggle('hidden',  !isLupa);

    title.textContent    = isLupa ? 'Reset Password' : 'Panel Admin';
    subtitle.textContent = isLupa ? 'Buat password baru untuk akun Anda' : 'CMS Sekolah — Masuk ke akun Anda';
}

// ── Cek kecocokan password lupa real-time ────────────────────────────────────
function checkLupaMatch() {
    const baru    = document.getElementById('lupaPwBaru').value;
    const konfirm = document.getElementById('lupaPwKonfirmasi').value;
    const msg     = document.getElementById('lupaMatchMsg');

    if (konfirm === '') { msg.classList.add('hidden'); return; }

    if (baru === konfirm) {
        msg.textContent = '✓ Password cocok';
        msg.className   = 'text-xs mt-1.5 text-green-600 font-medium';
    } else {
        msg.textContent = '✗ Password tidak cocok';
        msg.className   = 'text-xs mt-1.5 text-red-500 font-medium';
    }
    msg.classList.remove('hidden');
}
</script>
</body>
</html>
