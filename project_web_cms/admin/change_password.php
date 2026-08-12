<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php"); exit;
}

require_once '../config/database.php';
require_once '../config/csrf.php';

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    // Validasi input
    if (empty($current) || empty($new) || empty($confirm)) {
        $errors[] = 'Semua field wajib diisi.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'Password baru minimal 6 karakter.';
    } elseif ($new !== $confirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    } else {
        // Ambil password lama dari database
        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $_SESSION['admin_id']);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row || !password_verify($current, $row['password'])) {
            $errors[] = 'Password saat ini salah.';
        } else {
            // Simpan password baru
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $upd    = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, 'si', $hashed, $_SESSION['admin_id']);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            $success = 'Password berhasil diubah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password — Admin CMS</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-page">

<?php $active_menu = 'password'; require_once 'sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <h2>&#128274; Ganti Password</h2>
        <a href="index.php" class="btn btn-sm btn-secondary">&larr; Dashboard</a>
    </header>

    <div style="max-width:480px">

        <?php if ($success): ?>
            <div class="alert alert-success">&#10003; <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" novalidate autocomplete="off">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="current_password">Password Saat Ini <span class="required">*</span></label>
                    <div class="input-eye">
                        <input type="password" id="current_password" name="current_password"
                               placeholder="Masukkan password saat ini"
                               autocomplete="current-password" required>
                        <button type="button" class="eye-btn" onclick="togglePwd('current_password', this)">&#128065;</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">Password Baru <span class="required">*</span></label>
                    <div class="input-eye">
                        <input type="password" id="new_password" name="new_password"
                               placeholder="Minimal 6 karakter"
                               autocomplete="new-password" required>
                        <button type="button" class="eye-btn" onclick="togglePwd('new_password', this)">&#128065;</button>
                    </div>
                    <!-- Indikator kekuatan password -->
                    <div class="pwd-strength" id="pwdStrength" style="display:none">
                        <div class="pwd-strength-bar">
                            <div class="pwd-strength-fill" id="pwdFill"></div>
                        </div>
                        <small id="pwdLabel"></small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password Baru <span class="required">*</span></label>
                    <div class="input-eye">
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Ulangi password baru"
                               autocomplete="new-password" required>
                        <button type="button" class="eye-btn" onclick="togglePwd('confirm_password', this)">&#128065;</button>
                    </div>
                    <small id="matchMsg" style="font-size:.8rem"></small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">&#128274; Simpan Password Baru</button>
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                </div>

            </form>
        </div>

    </div>
</main>

<script>
// Toggle show/hide password
function togglePwd(id, btn) {
    const input = document.getElementById(id);
    if (input.type === 'password') {
        input.type = 'text';
        btn.style.opacity = '1';
    } else {
        input.type = 'password';
        btn.style.opacity = '.5';
    }
}

// Indikator kekuatan password
document.getElementById('new_password').addEventListener('input', function() {
    const val      = this.value;
    const strength = document.getElementById('pwdStrength');
    const fill     = document.getElementById('pwdFill');
    const label    = document.getElementById('pwdLabel');

    if (val.length === 0) { strength.style.display = 'none'; return; }
    strength.style.display = 'block';

    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '20%', color: '#dc2626', text: 'Sangat Lemah' },
        { pct: '40%', color: '#f97316', text: 'Lemah' },
        { pct: '60%', color: '#eab308', text: 'Sedang' },
        { pct: '80%', color: '#22c55e', text: 'Kuat' },
        { pct: '100%', color: '#16a34a', text: 'Sangat Kuat' },
    ];
    const lvl = levels[Math.min(score - 1, 4)] || levels[0];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.color;
    label.textContent     = lvl.text;
    label.style.color     = lvl.color;
});

// Cek kesesuaian password
document.getElementById('confirm_password').addEventListener('input', function() {
    const msg = document.getElementById('matchMsg');
    const newPwd = document.getElementById('new_password').value;
    if (this.value === '') { msg.textContent = ''; return; }
    if (this.value === newPwd) {
        msg.textContent = '✓ Password cocok';
        msg.style.color = '#16a34a';
    } else {
        msg.textContent = '✗ Password tidak cocok';
        msg.style.color = '#dc2626';
    }
});
</script>

</body>
</html>
