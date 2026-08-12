<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo    = getDB();
$profil = $pdo->query("SELECT * FROM profil_sekolah LIMIT 1")->fetch();
$config = getSiteConfig();

$alertMsg  = '';
$alertType = 'success';
$formData  = ['nama'=>'','email'=>'','subjek'=>'','pesan'=>''];

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF sederhana: cek token session
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $alertMsg  = 'Permintaan tidak valid. Silakan coba lagi.';
        $alertType = 'error';
    } else {
        $nama   = sanitize($_POST['nama']   ?? '');
        $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $subjek = sanitize($_POST['subjek'] ?? '');
        $pesan  = sanitize($_POST['pesan']  ?? '');

        $errors = [];
        if (mb_strlen($nama)   < 2)               $errors[] = 'Nama minimal 2 karakter.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
        if (mb_strlen($subjek) < 3)               $errors[] = 'Subjek minimal 3 karakter.';
        if (mb_strlen($pesan)  < 10)              $errors[] = 'Pesan minimal 10 karakter.';

        if (!empty($errors)) {
            $alertMsg  = implode(' ', $errors);
            $alertType = 'error';
            $formData  = compact('nama', 'email', 'subjek', 'pesan');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO pesan_kontak (nama, email, subjek, pesan, dibaca, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$nama, $email, $subjek, $pesan]);

            // Regenerate CSRF token setelah submit berhasil
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $alertMsg  = 'Pesan Anda berhasil dikirim! Kami akan segera menghubungi Anda.';
            $alertType = 'success';
        }
    }
}

// Generate CSRF token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Kontak';
include __DIR__ . '/includes/header.php';
?>

<!-- Banner -->
<div class="bg-gradient-to-r from-green-700 to-green-600 text-white py-12">
    <div class="container mx-auto px-4">
        <nav class="text-sm text-green-300 mb-2">
            <a href="/cms-sekolah/index.php" class="hover:text-white">Beranda</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white">Kontak</span>
        </nav>
        <h1 class="text-3xl font-bold flex items-center gap-3">
            <i class="fas fa-envelope"></i> Hubungi Kami
        </h1>
        <p class="text-green-100 mt-1">Kami siap membantu pertanyaan dan kebutuhan Anda</p>
    </div>
</div>

<div class="container mx-auto px-4 py-12">
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-10">

        <!-- Info Kontak -->
        <div class="lg:col-span-2 space-y-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900 mb-1">Informasi Kontak</h2>
                <p class="text-gray-500 text-sm">Datang langsung atau hubungi kami melalui saluran di bawah ini.</p>
            </div>

            <?php
            $contacts = [
                ['icon'=>'fa-map-marker-alt', 'color'=>'bg-red-100 text-red-600',   'label'=>'Alamat',   'val'=> $profil['alamat']   ?? $config['footer']['address']],
                ['icon'=>'fa-phone',           'color'=>'bg-green-100 text-green-600','label'=>'Telepon', 'val'=> $profil['telepon']  ?? null],
                ['icon'=>'fa-envelope',        'color'=>'bg-blue-100 text-blue-600', 'label'=>'Email',    'val'=> $profil['email']    ?? null],
            ];
            foreach ($contacts as $c):
                if (empty($c['val'])) continue;
            ?>
            <div class="flex items-start gap-4 bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                <div class="w-11 h-11 <?= $c['color'] ?> rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $c['icon'] ?>"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium mb-0.5"><?= $c['label'] ?></p>
                    <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($c['val']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Jam Operasional -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-11 h-11 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-clock"></i>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">Jam Operasional</p>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-gray-700">
                        <span>Senin – Jumat</span>
                        <span class="font-medium text-green-600">07.00 – 16.00</span>
                    </div>
                    <div class="flex justify-between text-gray-700">
                        <span>Sabtu</span>
                        <span class="font-medium text-amber-600">07.00 – 13.00</span>
                    </div>
                    <div class="flex justify-between text-gray-500">
                        <span>Minggu / Hari Libur</span>
                        <span class="font-medium text-red-500">Tutup</span>
                    </div>
                </div>
            </div>

            <!-- Sosmed -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                <p class="text-sm font-semibold text-gray-800 mb-4">Ikuti Kami</p>
                <div class="flex gap-3">
                    <?php foreach ($config['social_media'] as $s): ?>
                    <a href="<?= htmlspecialchars($s['url']) ?>" target="_blank" rel="noopener"
                       class="flex items-center gap-2 bg-gray-100 hover:bg-primary-600 hover:text-white text-gray-600 text-xs font-medium px-4 py-2 rounded-xl transition-all">
                        <i class="fab fa-<?= htmlspecialchars($s['icon']) ?>"></i>
                        <?= htmlspecialchars($s['platform']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Form Kontak -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-1">Kirim Pesan</h2>
                <p class="text-sm text-gray-500 mb-6">Isi formulir di bawah ini dan kami akan merespons secepatnya.</p>

                <!-- Alert -->
                <?php if ($alertMsg): ?>
                <?php $alertStyles = ['success'=>'bg-green-50 border-green-400 text-green-800','error'=>'bg-red-50 border-red-400 text-red-800']; ?>
                <?php $alertIcons  = ['success'=>'fa-circle-check','error'=>'fa-circle-xmark']; ?>
                <div class="border-l-4 p-4 rounded-r-xl mb-6 flex items-start gap-3 <?= $alertStyles[$alertType] ?>" data-auto-dismiss>
                    <i class="fas <?= $alertIcons[$alertType] ?> mt-0.5 flex-shrink-0"></i>
                    <span class="text-sm"><?= htmlspecialchars($alertMsg) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="/cms-sekolah/kontak.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                        <!-- Nama -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Nama Lengkap <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="nama" value="<?= htmlspecialchars($formData['nama']) ?>"
                                   placeholder="Nama Anda" required
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-300 focus:border-green-400 outline-none transition bg-gray-50 focus:bg-white">
                        </div>
                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="email" value="<?= htmlspecialchars($formData['email']) ?>"
                                   placeholder="email@contoh.com" required
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-300 focus:border-green-400 outline-none transition bg-gray-50 focus:bg-white">
                        </div>
                    </div>

                    <!-- Subjek -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Subjek <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="subjek" value="<?= htmlspecialchars($formData['subjek']) ?>"
                               placeholder="Perihal pesan Anda" required
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-300 focus:border-green-400 outline-none transition bg-gray-50 focus:bg-white">
                    </div>

                    <!-- Pesan -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Pesan <span class="text-red-500">*</span>
                        </label>
                        <textarea name="pesan" rows="6" required
                                  placeholder="Tuliskan pesan Anda di sini..."
                                  class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-green-300 focus:border-green-400 outline-none transition resize-none bg-gray-50 focus:bg-white"><?= htmlspecialchars($formData['pesan']) ?></textarea>
                    </div>

                    <button type="submit"
                            class="w-full bg-green-600 hover:bg-green-700 active:scale-95 text-white font-semibold py-3 px-6 rounded-xl transition-all flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                        <i class="fas fa-paper-plane"></i> Kirim Pesan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Peta Lokasi -->
    <?php if (!empty($profil['map_embed'])): ?>
    <div class="mt-12 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-map-location-dot text-green-600"></i>
            <h3 class="font-semibold text-gray-800 text-sm">Lokasi Sekolah</h3>
        </div>
        <div style="height:350px">
            <?= $profil['map_embed'] ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto-dismiss alerts
document.querySelectorAll('[data-auto-dismiss]').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 500);
    }, 5000);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
