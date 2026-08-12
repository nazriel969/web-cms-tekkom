<?php
session_start();

if (isset($_SESSION['admin_id'])) {
    header("Location: index.php"); exit;
}

require_once '../config/database.php';
require_once '../config/csrf.php';

$error = '';

// Brute force protection — max 5 percobaan per 15 menit
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 menit dalam detik
$attempts_key = 'login_attempts';
$lockout_key  = 'login_lockout';

$locked_until = $_SESSION[$lockout_key] ?? 0;
$is_locked    = $locked_until > time();
$attempts     = $_SESSION[$attempts_key] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    csrf_abort();

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = mysqli_prepare($conn,
            "SELECT id, username, password FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            // Reset attempts setelah login sukses
            unset($_SESSION[$attempts_key], $_SESSION[$lockout_key]);

            // Regenerasi session ID untuk keamanan
            session_regenerate_id(true);

            $_SESSION['admin_id']       = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            header("Location: index.php"); exit;
        } else {
            $attempts++;
            $_SESSION[$attempts_key] = $attempts;

            $remaining = $max_attempts - $attempts;
            if ($attempts >= $max_attempts) {
                $_SESSION[$lockout_key] = time() + $lockout_time;
                $_SESSION[$attempts_key] = 0;
                $error = 'Terlalu banyak percobaan gagal. Akun dikunci selama 15 menit.';
            } else {
                $error = 'Username atau password salah. ' . ($remaining > 0 ? "Sisa $remaining percobaan." : '');
            }
        }
    }
}

if ($is_locked) {
    $wait = ceil(($locked_until - time()) / 60);
    $error = "Akun dikunci. Coba lagi dalam $wait menit.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin — CMS</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .login-attempts-bar {
            height: 3px;
            background: var(--border);
            border-radius: 99px;
            margin-top: 1rem;
            overflow: hidden;
        }
        .login-attempts-fill {
            height: 100%;
            border-radius: 99px;
            transition: width .3s, background .3s;
        }
    </style>
</head>
<body class="login-page">

<div class="login-wrapper">
    <div class="login-box">
        <div class="login-logo">
            <img src="../assets/logo.jpg" alt="Logo"
                 style="width:56px;height:56px;object-fit:contain;border-radius:10px;margin:0 auto .75rem;display:block">
            <h1>Admin CMS</h1>
            <p style="color:var(--mid);font-size:.82rem;margin-top:.25rem">Panel Pengelola Website</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$is_locked): ?>
        <form method="POST" action="" novalidate autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       placeholder="Masukkan username"
                       autocomplete="off" required autofocus>
            </div>
            <div class="form-group" style="margin-bottom:.5rem">
                <label for="password">Password</label>
                <div class="input-eye">
                    <input type="password" id="password" name="password"
                           placeholder="Masukkan password"
                           autocomplete="new-password" required>
                    <button type="button" class="eye-btn"
                            onclick="const i=document.getElementById('password');i.type=i.type==='password'?'text':'password'">
                        &#128065;
                    </button>
                </div>
            </div>

            <?php if ($attempts > 0): ?>
            <div class="login-attempts-bar">
                <?php
                $pct   = min(100, ($attempts / $max_attempts) * 100);
                $color = $pct < 60 ? '#f59e0b' : '#dc2626';
                ?>
                <div class="login-attempts-fill"
                     style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
            </div>
            <small style="color:var(--mid);font-size:.75rem">
                Percobaan: <?= $attempts ?>/<?= $max_attempts ?>
            </small>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:1rem">
                &#128275; Masuk
            </button>
        </form>
        <?php else: ?>
        <div style="text-align:center;padding:1rem 0;color:var(--mid)">
            <span style="font-size:2rem;display:block;margin-bottom:.5rem">&#128274;</span>
            <p>Akun sementara dikunci karena terlalu banyak percobaan gagal.</p>
            <p style="margin-top:.5rem;font-size:.85rem">
                Silakan coba lagi dalam <?= ceil(($locked_until - time()) / 60) ?> menit.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
