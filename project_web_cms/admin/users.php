<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require_once '../config/database.php';
require_once '../config/csrf.php';

// Cek session valid
$_chk = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($_chk, 'i', $_SESSION['admin_id']);
mysqli_stmt_execute($_chk);
mysqli_stmt_store_result($_chk);
if (mysqli_stmt_num_rows($_chk) === 0) {
    mysqli_stmt_close($_chk);
    session_unset(); session_destroy();
    header("Location: login.php"); exit;
}
mysqli_stmt_close($_chk);

$errors  = [];
$edit_user = null;

// Edit mode
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($edit_id > 0) {
    $es = mysqli_prepare($conn, "SELECT id, username, full_name, email, role FROM users WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($es, 'i', $edit_id);
    mysqli_stmt_execute($es);
    $edit_user = mysqli_fetch_assoc(mysqli_stmt_get_result($es));
}

// Hapus user
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    if ($del_id === $_SESSION['admin_id']) {
        $_SESSION['flash'] = ['type'=>'danger','msg'=>'Tidak bisa menghapus akun sendiri.'];
    } else {
        mysqli_query($conn, "DELETE FROM users WHERE id=$del_id");
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Pengguna berhasil dihapus.'];
    }
    header("Location: users.php"); exit;
}

// Simpan / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $username  = trim($_POST['username']  ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $email     = $email !== '' ? $email : null; // simpan NULL jika kosong
    $role      = in_array($_POST['role'] ?? '', ['admin','editor']) ? $_POST['role'] : 'editor';
    $password  = $_POST['password']  ?? '';
    $post_id   = (int)($_POST['id'] ?? 0);

    if (empty($username)) $errors[] = 'Username wajib diisi.';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Format email tidak valid.';
    if ($post_id === 0 && empty($password))
        $errors[] = 'Password wajib diisi untuk pengguna baru.';
    if (!empty($password) && strlen($password) < 6)
        $errors[] = 'Password minimal 6 karakter.';

    // Cek duplikat username
    if (empty($errors)) {
        $dup = mysqli_prepare($conn,
            "SELECT id FROM users WHERE username=? AND id!=? LIMIT 1");
        mysqli_stmt_bind_param($dup, 'si', $username, $post_id);
        mysqli_stmt_execute($dup);
        mysqli_stmt_store_result($dup);
        if (mysqli_stmt_num_rows($dup) > 0) $errors[] = 'Username sudah digunakan.';
        mysqli_stmt_close($dup);
    }

    if (empty($errors)) {
        if ($post_id > 0) {
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt   = mysqli_prepare($conn,
                    "UPDATE users SET username=?, full_name=?, email=?, role=?, password=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssssi',
                    $username, $full_name, $email, $role, $hashed, $post_id);
            } else {
                $stmt = mysqli_prepare($conn,
                    "UPDATE users SET username=?, full_name=?, email=?, role=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssssi',
                    $username, $full_name, $email, $role, $post_id);
            }
            $msg = 'Pengguna berhasil diperbarui.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = mysqli_prepare($conn,
                "INSERT INTO users (username, full_name, email, password, role) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sssss',
                $username, $full_name, $email, $hashed, $role);
            $msg = 'Pengguna berhasil ditambahkan.';
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['flash'] = ['type'=>'success','msg'=>$msg];
        header("Location: users.php"); exit;
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$users = mysqli_query($conn,
    "SELECT id, username, full_name, email, role, created_at FROM users ORDER BY created_at ASC");

$pending_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS t FROM comments WHERE status='pending'"))['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna — Admin CMS</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-page">

<?php $active_menu = 'users'; require_once 'sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <h2>&#128101; Kelola Pengguna</h2>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:360px 1fr;gap:1.5rem;align-items:start">

        <!-- Form -->
        <div class="card">
            <h4 class="widget-title"><?= $edit_user ? '&#9998; Edit Pengguna' : '&#10133; Tambah Pengguna' ?></h4>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
                </div>
            <?php endif; ?>
            <form method="POST" novalidate autocomplete="off">
                <?= csrf_field() ?>
                <?php if ($edit_user): ?>
                    <input type="hidden" name="id" value="<?= $edit_user['id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="username"
                           value="<?= htmlspecialchars($edit_user['username'] ?? ($_POST['username'] ?? '')) ?>"
                           autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="full_name"
                           value="<?= htmlspecialchars($edit_user['full_name'] ?? ($_POST['full_name'] ?? '')) ?>"
                           placeholder="Opsional">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email"
                           value="<?= htmlspecialchars($edit_user['email'] ?? ($_POST['email'] ?? '')) ?>"
                           placeholder="Opsional">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="admin"  <?= ($edit_user['role'] ?? '') === 'admin'  ? 'selected' : '' ?>>&#128274; Admin (akses penuh)</option>
                        <option value="editor" <?= ($edit_user['role'] ?? '') === 'editor' ? 'selected' : '' ?>>&#9998; Editor (tulis artikel saja)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password <?= $edit_user ? '<small>(kosongkan jika tidak diubah)</small>' : '<span class="required">*</span>' ?></label>
                    <div class="input-eye">
                        <input type="password" name="password" autocomplete="new-password"
                               placeholder="<?= $edit_user ? 'Kosongkan jika tidak diubah' : 'Minimal 6 karakter' ?>">
                        <button type="button" class="eye-btn"
                                onclick="this.previousElementSibling.type=this.previousElementSibling.type==='password'?'text':'password'">&#128065;</button>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_user ? '&#10003; Perbarui' : '&#10133; Tambah' ?>
                    </button>
                    <?php if ($edit_user): ?>
                        <a href="users.php" class="btn btn-secondary">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel -->
        <div class="card">
            <h4 class="widget-title" style="margin-bottom:1rem">Daftar Pengguna</h4>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Bergabung</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no=1; while ($u = mysqli_fetch_assoc($users)): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td>
                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                        <?php if ($u['id'] === $_SESSION['admin_id']): ?>
                            <span class="badge badge-success" style="font-size:.65rem">Kamu</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['full_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= $u['role']==='admin' ? 'badge-warning' : 'badge-success' ?>">
                            <?= $u['role'] === 'admin' ? '&#128274; Admin' : '&#9998; Editor' ?>
                        </span>
                    </td>
                    <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td class="action-cell">
                        <a href="users.php?edit=<?= $u['id'] ?>" class="btn btn-xs btn-warning">&#9998;</a>
                        <?php if ($u['id'] !== $_SESSION['admin_id']): ?>
                        <a href="users.php?delete=<?= $u['id'] ?>"
                           class="btn btn-xs btn-danger"
                           onclick="return confirm('Hapus pengguna <?= htmlspecialchars(addslashes($u['username'])) ?>?')">&#128465;</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
