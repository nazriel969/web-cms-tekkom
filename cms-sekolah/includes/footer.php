<?php
require_once __DIR__ . '/../config/helpers.php';
$config = getSiteConfig();
?>
</main>
<!-- Footer -->
<footer class="bg-gray-900 text-gray-300 mt-16">
    <!-- Footer Top -->
    <div class="container mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">

            <!-- Brand -->
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-primary-600 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-school text-white"></i>
                    </div>
                    <span class="font-bold text-white text-lg"><?= htmlspecialchars($config['site_name']) ?></span>
                </div>
                <p class="text-sm leading-relaxed text-gray-400"><?= htmlspecialchars($config['footer']['tagline']) ?></p>
                <!-- Sosial Media -->
                <div class="flex gap-3 mt-5">
                    <?php foreach ($config['social_media'] as $s): ?>
                    <a href="<?= htmlspecialchars($s['url']) ?>" target="_blank" rel="noopener"
                       class="w-9 h-9 rounded-full bg-gray-700 hover:bg-primary-600 flex items-center justify-center transition-colors">
                        <i class="fab fa-<?= htmlspecialchars($s['icon']) ?> text-sm"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Nav Links -->
            <div>
                <h4 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Navigasi</h4>
                <ul class="space-y-2">
                    <?php foreach ($config['nav_menu'] as $item): ?>
                    <li>
                        <a href="/cms-sekolah/<?= htmlspecialchars($item['url']) ?>"
                           class="text-sm text-gray-400 hover:text-white hover:translate-x-1 inline-block transition-all">
                            <i class="fas fa-chevron-right text-xs mr-1 text-primary-500"></i>
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Kontak -->
            <div>
                <h4 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Hubungi Kami</h4>
                <?php
                // Ambil kontak dari DB jika tersedia
                try {
                    require_once __DIR__ . '/../config/database.php';
                    $pdo    = getDB();
                    $profil = $pdo->query("SELECT alamat, telepon, email FROM profil_sekolah LIMIT 1")->fetch();
                } catch (Exception $e) { $profil = null; }
                ?>
                <ul class="space-y-3 text-sm text-gray-400">
                    <li class="flex items-start gap-2">
                        <i class="fas fa-map-marker-alt text-primary-500 mt-0.5 flex-shrink-0"></i>
                        <span><?= htmlspecialchars($profil['alamat'] ?? $config['footer']['address']) ?></span>
                    </li>
                    <?php if (!empty($profil['telepon'])): ?>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-phone text-primary-500 flex-shrink-0"></i>
                        <span><?= htmlspecialchars($profil['telepon']) ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($profil['email'])): ?>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-envelope text-primary-500 flex-shrink-0"></i>
                        <span><?= htmlspecialchars($profil['email']) ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="border-t border-gray-800">
        <div class="container mx-auto px-4 py-4 flex flex-col md:flex-row justify-between items-center gap-2 text-xs text-gray-500">
            <span><?= htmlspecialchars($config['footer']['copyright']) ?></span>
            <span>Dibuat dengan <i class="fas fa-heart text-red-500"></i> menggunakan PHP & Tailwind CSS</span>
        </div>
    </div>
</footer>
</body>
</html>
