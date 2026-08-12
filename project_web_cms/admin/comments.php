<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require_once '../config/database.php';
require_once '../config/csrf.php';

// Aksi: approve / reject / delete
if (isset($_GET['action']) && isset($_GET['id'])) {
    $cid    = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        $stmt = mysqli_prepare($conn, "UPDATE comments SET status='approved' WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $cid);
        mysqli_stmt_execute($stmt);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Komentar disetujui.'];
    } elseif ($action === 'reject') {
        $stmt = mysqli_prepare($conn, "UPDATE comments SET status='rejected' WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $cid);
        mysqli_stmt_execute($stmt);
        $_SESSION['flash'] = ['type'=>'warning','msg'=>'Komentar ditolak.'];
    } elseif ($action === 'delete') {
        $stmt = mysqli_prepare($conn, "DELETE FROM comments WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $cid);
        mysqli_stmt_execute($stmt);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Komentar dihapus.'];
    }
    if (isset($stmt)) mysqli_stmt_close($stmt);
    header("Location: comments.php"); exit;
}

// Filter
$filter = $_GET['filter'] ?? 'pending';
$valid_filters = ['all','pending','approved','rejected'];
if (!in_array($filter, $valid_filters)) $filter = 'pending';

$where = $filter !== 'all' ? "WHERE c.status = '$filter'" : '';

// Statistik
$stats = [];
foreach (['pending','approved','rejected'] as $s) {
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS t FROM comments WHERE status='$s'"));
    $stats[$s] = $r['t'];
}
$stats['all'] = array_sum($stats);

// Query komentar
$comments = mysqli_query($conn,
    "SELECT c.*, p.title AS post_title, p.id AS post_id
     FROM comments c
     LEFT JOIN posts p ON c.post_id = p.id
     $where ORDER BY c.created_at DESC");

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderasi Komentar — Admin CMS</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-page">

<?php $active_menu = 'comments'; require_once 'sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <h2>&#128172; Moderasi Komentar</h2>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="comment-filter-tabs">
        <?php
        $labels = ['all'=>'Semua','pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak'];
        foreach ($labels as $k => $lbl):
        ?>
        <a href="?filter=<?= $k ?>"
           class="comment-filter-tab <?= $filter === $k ? 'active' : '' ?>">
            <?= $lbl ?>
            <span class="filter-count"><?= $stats[$k] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card" style="padding:0">
        <?php $has = false; while ($c = mysqli_fetch_assoc($comments)): $has = true; ?>
        <div class="comment-row">
            <div class="comment-meta">
                <strong><?= htmlspecialchars($c['name']) ?></strong>
                <?php if ($c['email']): ?>
                    <span style="color:var(--mid);font-size:.82rem">&lt;<?= htmlspecialchars($c['email']) ?>&gt;</span>
                <?php endif; ?>
                <span class="badge badge-<?= $c['status'] === 'approved' ? 'success' : ($c['status'] === 'pending' ? 'warning' : 'danger') ?>"
                      style="<?= $c['status']==='rejected' ? 'background:#fee2e2;color:#7f1d1d' : '' ?>">
                    <?= $c['status'] === 'approved' ? 'Disetujui' : ($c['status'] === 'pending' ? 'Menunggu' : 'Ditolak') ?>
                </span>
                <span style="color:#94a3b8;font-size:.78rem">&#128197; <?= date('d M Y H:i', strtotime($c['created_at'])) ?></span>
            </div>
            <div class="comment-post-ref">
                &#128196; Artikel: <a href="../post.php?id=<?= $c['post_id'] ?>" target="_blank">
                    <?= htmlspecialchars(mb_substr($c['post_title'] ?? '-', 0, 60)) ?>
                </a>
            </div>
            <div class="comment-content"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
            <div class="comment-actions">
                <?php if ($c['status'] !== 'approved'): ?>
                    <a href="?action=approve&id=<?= $c['id'] ?>&filter=<?= $filter ?>"
                       class="btn btn-xs btn-success">&#10003; Setujui</a>
                <?php endif; ?>
                <?php if ($c['status'] !== 'rejected'): ?>
                    <a href="?action=reject&id=<?= $c['id'] ?>&filter=<?= $filter ?>"
                       class="btn btn-xs btn-warning">&#10007; Tolak</a>
                <?php endif; ?>
                <a href="?action=delete&id=<?= $c['id'] ?>&filter=<?= $filter ?>"
                   class="btn btn-xs btn-danger"
                   onclick="return confirm('Hapus komentar ini permanen?')">&#128465; Hapus</a>
            </div>
        </div>
        <?php endwhile; ?>
        <?php if (!$has): ?>
            <div class="empty-state" style="padding:3rem">
                <span>&#128172;</span>
                <p>Tidak ada komentar <?= $filter !== 'all' ? '"' . $labels[$filter] . '"' : '' ?>.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
