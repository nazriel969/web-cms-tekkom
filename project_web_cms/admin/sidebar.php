<?php
/**
 * Shared sidebar admin
 * Set $active_menu sebelum include, contoh: $active_menu = 'dashboard';
 * Nilai valid: dashboard, create, categories, gallery, teachers, comments, users, profil, password
 */
$pending_comments = 0;
if (isset($conn)) {
    $pc = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS t FROM comments WHERE status='pending'"));
    $pending_comments = (int)($pc['t'] ?? 0);
}
$am = $active_menu ?? '';
?>
<button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')" aria-label="Menu">&#9776;</button>

<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="logo-icon">&#9881;</span>
        <span>Admin CMS</span>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php"           class="nav-item <?= $am==='dashboard' ?'active':'' ?>">&#128202; Dashboard</a>
        <a href="create.php"          class="nav-item <?= $am==='create'    ?'active':'' ?>">&#10133; Tulis Artikel</a>
        <a href="categories.php"      class="nav-item <?= $am==='categories'?'active':'' ?>">&#128193; Kategori</a>
        <a href="gallery.php"         class="nav-item <?= $am==='gallery'   ?'active':'' ?>">&#128247; Galeri</a>
        <a href="teachers.php"        class="nav-item <?= $am==='teachers'  ?'active':'' ?>">&#128218; Data Guru</a>
        <a href="comments.php"        class="nav-item <?= $am==='comments'  ?'active':'' ?>">&#128172; Komentar
            <?php if ($pending_comments > 0): ?>
                <span class="comment-badge"><?= $pending_comments ?></span>
            <?php endif; ?>
        </a>
        <a href="users.php"           class="nav-item <?= $am==='users'     ?'active':'' ?>">&#128101; Pengguna</a>
        <a href="profil.php"          class="nav-item <?= $am==='profil'    ?'active':'' ?>">&#127979; Profil Sekolah</a>
        <a href="change_password.php" class="nav-item <?= $am==='password'  ?'active':'' ?>">&#128274; Ganti Password</a>
        <a href="../index.php"        class="nav-item" target="_blank">&#127760; Lihat Situs</a>
    </nav>
    <div class="sidebar-footer">
        <span>&#128100; <?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?></span>
        <a href="logout.php" class="btn-logout">&#9099; Keluar</a>
    </div>
</aside>
<div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('open')"></div>
