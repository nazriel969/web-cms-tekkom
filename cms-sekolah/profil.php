<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo    = getDB();
$profil = $pdo->query("SELECT * FROM profil_sekolah LIMIT 1")->fetch();

$pageTitle = 'Profil Sekolah';
include __DIR__ . '/includes/header.php';
?>

<!-- Page Banner -->
<div class="bg-gradient-to-r from-primary-800 to-primary-600 text-white py-12">
    <div class="container mx-auto px-4">
        <nav class="text-sm text-primary-300 mb-2">
            <a href="/cms-sekolah/index.php" class="hover:text-white">Beranda</a>
            <i class="fas fa-chevron-right text-xs mx-2"></i>
            <span class="text-white">Profil Sekolah</span>
        </nav>
        <h1 class="text-3xl font-bold">Profil Sekolah</h1>
        <p class="text-primary-200 mt-1">Mengenal lebih dekat <?= htmlspecialchars($profil['nama_sekolah'] ?? 'Sekolah Kami') ?></p>
    </div>
</div>

<div class="container mx-auto px-4 py-12">

    <?php if (empty($profil)): ?>
    <div class="text-center py-20 text-gray-400">
        <i class="fas fa-school text-5xl mb-3 block"></i>
        <p>Data profil sekolah belum tersedia.</p>
    </div>
    <?php else: ?>

    <!-- Info Singkat -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <?php
        $infoCards = [
            ['icon'=>'fa-school',       'color'=>'bg-blue-100 text-blue-600',   'label'=>'Nama Sekolah', 'val'=>$profil['nama_sekolah']],
            ['icon'=>'fa-id-card',      'color'=>'bg-green-100 text-green-600', 'label'=>'NPSN',         'val'=>$profil['npsn']],
            ['icon'=>'fa-envelope',     'color'=>'bg-purple-100 text-purple-600','label'=>'Email',       'val'=>$profil['email']],
            ['icon'=>'fa-phone',        'color'=>'bg-amber-100 text-amber-600', 'label'=>'Telepon',      'val'=>$profil['telepon']],
            ['icon'=>'fa-map-marker-alt','color'=>'bg-red-100 text-red-600',    'label'=>'Alamat',       'val'=>$profil['alamat']],
        ];
        foreach ($infoCards as $card):
            if (empty($card['val'])) continue;
        ?>
        <div class="bg-white rounded-xl shadow-sm p-5 flex items-start gap-4 hover:shadow-md transition-shadow">
            <div class="w-10 h-10 <?= $card['color'] ?> rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas <?= $card['icon'] ?>"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5"><?= $card['label'] ?></p>
                <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($card['val']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Visi & Misi -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
        <!-- Visi -->
        <div class="bg-gradient-to-br from-primary-50 to-blue-50 border border-primary-100 rounded-2xl p-8">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 bg-primary-600 rounded-xl flex items-center justify-center">
                    <i class="fas fa-eye text-white"></i>
                </div>
                <h2 class="text-xl font-bold text-primary-800">Visi</h2>
            </div>
            <p class="text-gray-700 leading-relaxed text-sm">
                <?= nl2br(htmlspecialchars($profil['visi'] ?? 'Visi belum diisi.')) ?>
            </p>
        </div>

        <!-- Misi -->
        <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-100 rounded-2xl p-8">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 bg-green-600 rounded-xl flex items-center justify-center">
                    <i class="fas fa-bullseye text-white"></i>
                </div>
                <h2 class="text-xl font-bold text-green-800">Misi</h2>
            </div>
            <div class="text-gray-700 leading-relaxed text-sm">
                <?= nl2br(htmlspecialchars($profil['misi'] ?? 'Misi belum diisi.')) ?>
            </div>
        </div>
    </div>

    <!-- Sejarah -->
    <?php if (!empty($profil['sejarah'])): ?>
    <div class="bg-white rounded-2xl shadow-sm p-8 mb-12 border border-gray-100">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-book-open text-amber-600"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Sejarah Sekolah</h2>
        </div>
        <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed">
            <?= nl2br(htmlspecialchars($profil['sejarah'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sambutan Kepala Sekolah -->
    <?php if (!empty($profil['sambutan_kepsek'])): ?>
    <div class="bg-gradient-to-r from-primary-800 to-primary-700 rounded-2xl p-8 text-white mb-12">
        <div class="flex flex-col md:flex-row gap-8 items-start">
            <?php if (!empty($profil['foto_kepsek'])): ?>
            <div class="flex-shrink-0 text-center">
                <img src="/cms-sekolah/assets/uploads/profil/<?= htmlspecialchars($profil['foto_kepsek']) ?>"
                     alt="Kepala Sekolah"
                     class="w-36 h-36 rounded-2xl object-cover border-4 border-white/30 shadow-xl mx-auto">
                <p class="text-primary-200 text-xs mt-2">Kepala Sekolah</p>
            </div>
            <?php endif; ?>
            <div>
                <p class="text-primary-300 text-xs uppercase tracking-widest font-semibold mb-2">Sambutan Kepala Sekolah</p>
                <h3 class="text-xl font-bold mb-4">Kata Pengantar</h3>
                <blockquote class="text-primary-100 leading-relaxed text-sm italic border-l-4 border-white/30 pl-4">
                    <?= nl2br(htmlspecialchars($profil['sambutan_kepsek'])) ?>
                </blockquote>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Peta Lokasi -->
    <?php if (!empty($profil['map_embed'])): ?>
    <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-map text-red-500"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Lokasi Sekolah</h2>
        </div>
        <div class="rounded-xl overflow-hidden border border-gray-200" style="height:350px">
            <?= $profil['map_embed'] ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
