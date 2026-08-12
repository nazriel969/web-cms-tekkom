<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require_once '../config/database.php';
require_once '../config/csrf.php';

define('UPLOAD_DIR', '../assets/uploads/');
$errors = [];

// Hapus
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT photo FROM teachers WHERE id=$did"));
    if ($row) {
        if (!empty($row['photo'])) {
            $f = UPLOAD_DIR . $row['photo'];
            if (file_exists($f)) unlink($f);
        }
        mysqli_query($conn, "DELETE FROM teachers WHERE id=$did");
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Data guru berhasil dihapus.'];
    }
    header("Location: teachers.php"); exit;
}

// Edit mode
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_data = null;
if ($edit_id > 0) {
    $es = mysqli_prepare($conn, "SELECT * FROM teachers WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($es, 'i', $edit_id);
    mysqli_stmt_execute($es);
    $edit_data = mysqli_fetch_assoc(mysqli_stmt_get_result($es));
}

// Simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $name       = trim($_POST['name']      ?? '');
    $position   = trim($_POST['position']  ?? '');
    $subject    = trim($_POST['subject']   ?? '');
    $education  = trim($_POST['education'] ?? '');
    $email      = trim($_POST['email']     ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $post_id    = (int)($_POST['id'] ?? 0);

    if (empty($name)) $errors[] = 'Nama wajib diisi.';

    $photo = $edit_data['photo'] ?? '';

    if (empty($errors) && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file  = $_FILES['photo'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed = ['image/jpeg','image/png','image/webp'];

        if (!in_array($mime, $allowed)) {
            $errors[] = 'Format foto harus JPG, PNG, atau WEBP.';
        } elseif ($file['size'] > 2*1024*1024) {
            $errors[] = 'Ukuran foto maks 2 MB.';
        } else {
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_name = 'teacher_' . uniqid('', true) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $new_name)) {
                if (!empty($photo) && file_exists(UPLOAD_DIR . $photo)) unlink(UPLOAD_DIR . $photo);
                $photo = $new_name;
            } else {
                $errors[] = 'Gagal upload foto.';
            }
        }
    }

    if (isset($_POST['remove_photo']) && !empty($photo)) {
        if (file_exists(UPLOAD_DIR . $photo)) unlink(UPLOAD_DIR . $photo);
        $photo = '';
    }

    if (empty($errors)) {
        if ($post_id > 0) {
            $stmt = mysqli_prepare($conn,
                "UPDATE teachers SET name=?,position=?,subject=?,education=?,email=?,photo=?,sort_order=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssssssi i',
                $name,$position,$subject,$education,$email,$photo,$sort_order,$post_id);
            // fix binding
            $stmt = mysqli_prepare($conn,
                "UPDATE teachers SET name=?,position=?,subject=?,education=?,email=?,photo=?,sort_order=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssssssii',
                $name,$position,$subject,$education,$email,$photo,$sort_order,$post_id);
            $msg = 'Data guru berhasil diperbarui.';
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO teachers (name,position,subject,education,email,photo,sort_order) VALUES (?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'ssssssi',
                $name,$position,$subject,$education,$email,$photo,$sort_order);
            $msg = 'Data guru berhasil ditambahkan.';
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['flash'] = ['type'=>'success','msg'=>$msg];
        header("Location: teachers.php"); exit;
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$teachers = mysqli_query($conn,
    "SELECT * FROM teachers ORDER BY sort_order ASC, name ASC");

$pending_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS t FROM comments WHERE status='pending'"))['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Guru — Admin CMS</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-page">

<button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')" aria-label="Menu">&#9776;</button>

<?php $active_menu = 'teachers'; require_once 'sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <h2>&#128218; Kelola Data Guru &amp; Staff</h2>
        <a href="../guru.php" target="_blank" class="btn btn-sm btn-primary">&#127760; Lihat Halaman Guru</a>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:340px 1fr;gap:1.5rem;align-items:start">

        <!-- Form -->
        <div class="card">
            <h4 class="widget-title"><?= $edit_data ? '&#9998; Edit Guru' : '&#10133; Tambah Guru/Staff' ?></h4>
            <form method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                <?php endif; ?>

                <!-- Preview foto -->
                <?php if (!empty($edit_data['photo'])): ?>
                <div style="margin-bottom:.75rem">
                    <img src="../assets/uploads/<?= htmlspecialchars($edit_data['photo']) ?>"
                         style="width:80px;height:90px;object-fit:cover;border-radius:8px;border:2px solid var(--border)">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Nama Lengkap <span class="required">*</span></label>
                    <input type="text" name="name"
                           value="<?= htmlspecialchars($edit_data['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Jabatan</label>
                    <input type="text" name="position"
                           value="<?= htmlspecialchars($edit_data['position'] ?? '') ?>"
                           placeholder="Kepala Sekolah / Guru / Staff...">
                </div>
                <div class="form-group">
                    <label>Mata Pelajaran</label>
                    <input type="text" name="subject"
                           value="<?= htmlspecialchars($edit_data['subject'] ?? '') ?>"
                           placeholder="Matematika / IPA / ...">
                </div>
                <div class="form-group">
                    <label>Pendidikan Terakhir</label>
                    <input type="text" name="education"
                           value="<?= htmlspecialchars($edit_data['education'] ?? '') ?>"
                           placeholder="S1 / S2 / ...">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email"
                           value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Urutan Tampil</label>
                    <input type="number" name="sort_order"
                           value="<?= $edit_data['sort_order'] ?? 0 ?>" min="0">
                </div>
                <div class="form-group">
                    <label>Foto <small>(JPG/PNG/WEBP, maks 2MB)</small></label>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                           onchange="previewTeacherPhoto(this)">
                    <div id="teacherPhotoPreview" style="margin-top:.5rem"></div>
                </div>
                <?php if (!empty($edit_data['photo'])): ?>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
                        <input type="checkbox" name="remove_photo" value="1">
                        Hapus foto saat ini
                    </label>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_data ? '&#10003; Perbarui' : '&#10133; Simpan' ?>
                    </button>
                    <?php if ($edit_data): ?>
                        <a href="teachers.php" class="btn btn-secondary">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel -->
        <div class="card">
            <h4 class="widget-title" style="margin-bottom:1rem">
                Daftar Guru &amp; Staff
                <small style="font-weight:400;color:var(--mid)">(<?= mysqli_num_rows($teachers) ?> orang)</small>
            </h4>
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Mata Pelajaran</th>
                            <th>Urutan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($t = mysqli_fetch_assoc($teachers)): ?>
                    <tr>
                        <td>
                            <?php if (!empty($t['photo'])): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($t['photo']) ?>"
                                     style="width:40px;height:44px;object-fit:cover;border-radius:5px;border:1px solid var(--border)">
                            <?php else: ?>
                                <div style="width:40px;height:44px;background:var(--primary);color:#fff;border-radius:5px;display:flex;align-items:center;justify-content:center;font-weight:700">
                                    <?= mb_strtoupper(mb_substr($t['name'],0,1)) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                        <td><?= htmlspecialchars($t['position'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($t['subject'] ?? '—') ?></td>
                        <td><?= $t['sort_order'] ?></td>
                        <td class="action-cell">
                            <a href="teachers.php?edit=<?= $t['id'] ?>" class="btn btn-xs btn-warning">&#9998;</a>
                            <a href="teachers.php?delete=<?= $t['id'] ?>"
                               class="btn btn-xs btn-danger"
                               onclick="return confirm('Hapus data <?= htmlspecialchars(addslashes($t['name'])) ?>?')">&#128465;</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function previewTeacherPhoto(input) {
    const preview = document.getElementById('teacherPhotoPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.innerHTML = `<img src="${e.target.result}" style="width:80px;height:90px;object-fit:cover;border-radius:8px;border:2px solid var(--border)">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
