<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require_once '../config/database.php';
require_once '../config/csrf.php';

$errors  = [];
$success = '';

// Ambil semua data profil
$raw = mysqli_query($conn, "SELECT `key`, value FROM school_profile");
$profile = [];
while ($r = mysqli_fetch_assoc($raw)) $profile[$r['key']] = $r['value'];

// Proses simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $fields = [
        'school_name','school_address','school_phone','school_email','school_website',
        'school_founded','school_accreditation','school_vision','school_mission',
        'school_history','principal_name','principal_message',
        'total_students','total_teachers','total_staff',
        'extracurricular','facilities','achievements',
        'social_instagram','social_facebook','social_youtube','wa_number',
    ];

    // Upload foto kepala sekolah
    if (isset($_FILES['principal_photo']) && $_FILES['principal_photo']['error'] === UPLOAD_ERR_OK) {
        $file      = $_FILES['principal_photo'];
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime      = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed   = ['image/jpeg','image/png','image/webp'];

        if (!in_array($mime, $allowed)) {
            $errors[] = 'Format foto harus JPG, PNG, atau WEBP.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Ukuran foto maksimal 2 MB.';
        } else {
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_name = 'principal_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], '../assets/uploads/' . $new_name)) {
                // Hapus foto lama
                if (!empty($profile['principal_photo'])) {
                    $old = '../assets/uploads/' . $profile['principal_photo'];
                    if (file_exists($old)) unlink($old);
                }
                $_POST['principal_photo'] = $new_name;
                $fields[] = 'principal_photo';
            } else {
                $errors[] = 'Gagal upload foto.';
            }
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO school_profile (`key`, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)");
        foreach ($fields as $field) {
            $val = trim($_POST[$field] ?? '');
            mysqli_stmt_bind_param($stmt, 'ss', $field, $val);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $success = 'Profil sekolah berhasil disimpan.';

        // Refresh data
        $raw = mysqli_query($conn, "SELECT `key`, value FROM school_profile");
        $profile = [];
        while ($r = mysqli_fetch_assoc($raw)) $profile[$r['key']] = $r['value'];
    }
}

$p = fn(string $key, string $default = '') => htmlspecialchars($profile[$key] ?? $default);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Profil Sekolah — Admin CMS</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-page">

<?php $active_menu = 'profil'; require_once 'sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <h2>&#127979; Kelola Profil Sekolah</h2>
        <a href="../profil.php" target="_blank" class="btn btn-sm btn-primary">&#127760; Lihat Halaman Profil</a>
    </header>

    <?php if ($success): ?>
        <div class="alert alert-success">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

        <!-- Kolom Kiri -->
        <div>
            <div class="card">
                <h4 class="widget-title">&#127979; Identitas Sekolah</h4>
                <div class="form-group">
                    <label>Nama Sekolah</label>
                    <input type="text" name="school_name" value="<?= $p('school_name') ?>">
                </div>
                <div class="form-group">
                    <label>Alamat</label>
                    <textarea name="school_address" rows="3"><?= $p('school_address') ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Telepon</label>
                        <input type="text" name="school_phone" value="<?= $p('school_phone') ?>">
                    </div>
                    <div class="form-group">
                        <label>Tahun Berdiri</label>
                        <input type="text" name="school_founded" value="<?= $p('school_founded') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="school_email" value="<?= $p('school_email') ?>">
                    </div>
                    <div class="form-group">
                        <label>Akreditasi</label>
                        <input type="text" name="school_accreditation" value="<?= $p('school_accreditation') ?>" placeholder="A (Unggul)">
                    </div>
                </div>
                <div class="form-group">
                    <label>Website</label>
                    <input type="text" name="school_website" value="<?= $p('school_website') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jumlah Siswa</label>
                        <input type="text" name="total_students" value="<?= $p('total_students') ?>">
                    </div>
                    <div class="form-group">
                        <label>Jumlah Guru</label>
                        <input type="text" name="total_teachers" value="<?= $p('total_teachers') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Jumlah Tenaga Kependidikan</label>
                    <input type="text" name="total_staff" value="<?= $p('total_staff') ?>">
                </div>
            </div>

            <div class="card">
                <h4 class="widget-title">&#127908; Media Sosial</h4>
                <div class="form-group">
                    <label>Nomor WhatsApp <small>(format: 628xxxxxxxx)</small></label>
                    <input type="text" name="wa_number" value="<?= $p('wa_number') ?>" placeholder="628123456789">
                </div>
                <div class="form-group">
                    <label>Instagram (URL lengkap)</label>
                    <input type="url" name="social_instagram" value="<?= $p('social_instagram') ?>" placeholder="https://instagram.com/...">
                </div>
                <div class="form-group">
                    <label>Facebook (URL lengkap)</label>
                    <input type="url" name="social_facebook" value="<?= $p('social_facebook') ?>" placeholder="https://facebook.com/...">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label>YouTube (URL lengkap)</label>
                    <input type="url" name="social_youtube" value="<?= $p('social_youtube') ?>" placeholder="https://youtube.com/...">
                </div>
            </div>
        </div>

        <!-- Kolom Kanan -->
        <div>
            <div class="card">
                <h4 class="widget-title">&#128100; Kepala Sekolah</h4>
                <!-- Preview foto -->
                <div style="margin-bottom:1rem">
                    <?php if (!empty($profile['principal_photo'])): ?>
                        <img src="../assets/uploads/<?= $p('principal_photo') ?>"
                             alt="Foto Kepala Sekolah"
                             style="width:100px;height:120px;object-fit:cover;border-radius:8px;border:2px solid var(--border)">
                    <?php else: ?>
                        <div style="width:100px;height:120px;background:var(--light);border-radius:8px;border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;font-size:2rem;color:#94a3b8">&#128100;</div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Foto Kepala Sekolah <small>(JPG/PNG/WEBP, maks 2MB)</small></label>
                    <input type="file" name="principal_photo" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="form-group">
                    <label>Nama Kepala Sekolah</label>
                    <input type="text" name="principal_name" value="<?= $p('principal_name') ?>">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label>Sambutan / Pesan Kepala Sekolah</label>
                    <textarea name="principal_message" rows="5"><?= $p('principal_message') ?></textarea>
                </div>
            </div>

            <div class="card">
                <h4 class="widget-title">&#127919; Visi &amp; Misi</h4>
                <div class="form-group">
                    <label>Visi</label>
                    <textarea name="school_vision" rows="3"><?= $p('school_vision') ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label>Misi <small>(satu per baris)</small></label>
                    <textarea name="school_mission" rows="6"><?= $p('school_mission') ?></textarea>
                </div>
            </div>

            <div class="card">
                <h4 class="widget-title">&#128218; Sejarah Sekolah</h4>
                <div class="form-group" style="margin-bottom:0">
                    <textarea name="school_history" rows="6"><?= $p('school_history') ?></textarea>
                </div>
            </div>

            <div class="card">
                <h4 class="widget-title">&#127941; Fasilitas, Ekskul &amp; Prestasi</h4>
                <div class="form-group">
                    <label>Fasilitas <small>(pisahkan dengan koma)</small></label>
                    <textarea name="facilities" rows="3"><?= $p('facilities') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Ekstrakurikuler <small>(pisahkan dengan koma)</small></label>
                    <textarea name="extracurricular" rows="3"><?= $p('extracurricular') ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label>Prestasi <small>(pisahkan dengan koma)</small></label>
                    <textarea name="achievements" rows="3"><?= $p('achievements') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top:1.5rem;text-align:right">
        <button type="submit" class="btn btn-primary" style="padding:.7rem 2rem;font-size:1rem">
            &#10003; Simpan Semua Perubahan
        </button>
    </div>
    </form>
</main>
</body>
</html>
