<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php"); exit;
}

require_once '../config/database.php';
require_once '../config/csrf.php';

// Validasi session
$_check = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($_check, 'i', $_SESSION['admin_id']);
mysqli_stmt_execute($_check);
mysqli_stmt_store_result($_check);
if (mysqli_stmt_num_rows($_check) === 0) {
    mysqli_stmt_close($_check);
    session_unset(); session_destroy();
    header("Location: login.php?err=session"); exit;
}
mysqli_stmt_close($_check);

// Toggle status via AJAX-like GET request
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $toggle_id = (int)$_GET['id'];
    $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM posts WHERE id = $toggle_id"));
    if ($cur) {
        $new_status = $cur['status'] === 'published' ? 'draft' : 'published';
        $ts = mysqli_prepare($conn, "UPDATE posts SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($ts, 'si', $new_status, $toggle_id);
        mysqli_stmt_execute($ts);
        mysqli_stmt_close($ts);
        $_SESSION['flash'] = [
            'type' => 'success',
            'msg'  => "Status artikel diubah ke: " . ($new_status === 'published' ? 'Dipublikasikan' : 'Draft')
        ];
    }
    header("Location: index.php" . (isset($_GET['page']) ? '?page='.(int)$_GET['page'] : ''));
    exit;
}

// Statistik
$total_posts      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM posts"))['t'];
$published_posts  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM posts WHERE status='published'"))['t'];
$draft_posts      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM posts WHERE status='draft'"))['t'];
$total_categories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM categories"))['t'];
$total_views      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(views),0) AS t FROM posts"))['t'];
$total_comments   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM comments WHERE status='pending'"))['t'];
$total_gallery    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM gallery"))['t'];

// Data chart: artikel per kategori (top 7)
$chart_data = mysqli_query($conn,
    "SELECT c.name, COUNT(p.id) AS total
     FROM categories c
     LEFT JOIN posts p ON p.category_id=c.id AND p.status='published'
     GROUP BY c.id ORDER BY total DESC LIMIT 7");
$chart_labels = [];
$chart_values = [];
$chart_max = 1;
while ($cr = mysqli_fetch_assoc($chart_data)) {
    $chart_labels[] = $cr['name'];
    $chart_values[] = (int)$cr['total'];
    if ($cr['total'] > $chart_max) $chart_max = $cr['total'];
}

// Artikel terpopuler (top 5)
$top_posts = mysqli_query($conn,
    "SELECT id, title, views, category_id FROM posts
     WHERE status='published' ORDER BY views DESC LIMIT 5");

// Pencarian & filter status
$search        = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';
$filter_date   = $_GET['date'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 10;
$offset        = ($page - 1) * $per_page;

$where_parts = [];
$bind_types  = '';
$bind_vals   = [];

if ($search !== '') {
    $where_parts[] = "(p.title LIKE ? OR c.name LIKE ?)";
    $like = "%$search%";
    $bind_types .= 'ss';
    $bind_vals[] = $like;
    $bind_vals[] = $like;
}
if (in_array($filter_status, ['published', 'draft'])) {
    $where_parts[] = "p.status = ?";
    $bind_types .= 's';
    $bind_vals[] = $filter_status;
}
// Filter tanggal
if ($filter_date === 'today') {
    $where_parts[] = "DATE(p.created_at) = CURDATE()";
} elseif ($filter_date === 'week') {
    $where_parts[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter_date === 'month') {
    $where_parts[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Count
$count_stmt = mysqli_prepare($conn,
    "SELECT COUNT(*) AS t FROM posts p LEFT JOIN categories c ON p.category_id = c.id $where");
if ($bind_types) mysqli_stmt_bind_param($count_stmt, $bind_types, ...$bind_vals);
mysqli_stmt_execute($count_stmt);
$total_filtered = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['t'];
$total_pages    = ceil($total_filtered / $per_page);

// Posts
$stmt = mysqli_prepare($conn,
    "SELECT p.id, p.title, p.status, p.created_at, p.image, p.views,
            c.name AS category_name, u.username
     FROM posts p
     LEFT JOIN categories c ON p.category_id = c.id
     LEFT JOIN users u ON p.user_id = u.id
     $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
$all_types = $bind_types . 'ii';
$all_vals  = array_merge($bind_vals, [$per_page, $offset]);
mysqli_stmt_bind_param($stmt, $all_types, ...$all_vals);
mysqli_stmt_execute($stmt);
$posts = mysqli_stmt_get_result($stmt);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Build query string helper
function admin_query(array $extra = []): string {
    global $search, $filter_status, $filter_date;
    $p = [];
    if ($search !== '')        $p['search'] = $search;
    if ($filter_status !== '') $p['status'] = $filter_status;
    if ($filter_date !== '')   $p['date']   = $filter_date;
    return '?' . http_build_query(array_merge($p, $extra));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — CMS</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-page">

<!-- Hamburger (mobile) -->
<?php $active_menu = 'dashboard'; require_once 'sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <h2>&#128202; Dashboard</h2>
        <a href="create.php" class="btn btn-success btn-sm">&#10133; Artikel Baru</a>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- Statistik -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">&#128196;</div>
            <div class="stat-info">
                <span class="stat-number"><?= $total_posts ?></span>
                <span class="stat-label">Total Artikel</span>
            </div>
        </div>
        <div class="stat-card" style="border-left:3px solid var(--success)">
            <div class="stat-icon" style="color:var(--success)">&#10003;</div>
            <div class="stat-info">
                <span class="stat-number"><?= $published_posts ?></span>
                <span class="stat-label">Dipublikasikan</span>
            </div>
        </div>
        <div class="stat-card" style="border-left:3px solid var(--warning)">
            <div class="stat-icon" style="color:var(--warning)">&#9998;</div>
            <div class="stat-info">
                <span class="stat-number"><?= $draft_posts ?></span>
                <span class="stat-label">Draft</span>
            </div>
        </div>
        <div class="stat-card" style="border-left:3px solid #8e44ad">
            <div class="stat-icon" style="color:#8e44ad">&#128193;</div>
            <div class="stat-info">
                <span class="stat-number"><?= $total_categories ?></span>
                <span class="stat-label">Kategori</span>
            </div>
        </div>
        <div class="stat-card" style="border-left:3px solid #0891b2">
            <div class="stat-icon" style="color:#0891b2">&#128065;</div>
            <div class="stat-info">
                <span class="stat-number"><?= number_format($total_views) ?></span>
                <span class="stat-label">Total Pembaca</span>
            </div>
        </div>
        <div class="stat-card" style="border-left:3px solid #f59e0b">
            <div class="stat-icon" style="color:#f59e0b">&#128172;</div>
            <div class="stat-info">
                <span class="stat-number"><?= $total_comments ?></span>
                <span class="stat-label">Komentar Pending</span>
            </div>
        </div>
        <div class="stat-card" style="border-left:3px solid #8b5cf6">
            <div class="stat-icon" style="color:#8b5cf6">&#128247;</div>
            <div class="stat-info">
                <span class="stat-number"><?= $total_gallery ?></span>
                <span class="stat-label">Foto Galeri</span>
            </div>
        </div>
    </div>

    <!-- Charts & Top Artikel -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">

        <!-- Chart Artikel per Kategori -->
        <div class="chart-card">
            <h3>&#128202; Artikel per Kategori</h3>
            <div class="mini-chart">
                <?php foreach ($chart_values as $i => $val): ?>
                <?php $pct = $chart_max > 0 ? round(($val / $chart_max) * 100) : 0; ?>
                <div class="mini-bar"
                     style="height:<?= max($pct, 5) ?>%"
                     data-label="<?= htmlspecialchars($chart_labels[$i]) ?>: <?= $val ?> artikel"
                     title="<?= htmlspecialchars($chart_labels[$i]) ?>: <?= $val ?>">
                </div>
                <?php endforeach; ?>
                <?php if (empty($chart_values)): ?>
                <p style="color:var(--mid);font-size:.85rem">Belum ada data.</p>
                <?php endif; ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.85rem">
                <?php foreach ($chart_labels as $i => $label): ?>
                <span style="font-size:.72rem;color:var(--mid)">
                    <span style="display:inline-block;width:8px;height:8px;background:var(--primary);border-radius:2px;margin-right:3px;opacity:<?= 0.4 + ($i * 0.1) ?>"></span>
                    <?= htmlspecialchars($label) ?> (<?= $chart_values[$i] ?>)
                </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Artikel -->
        <div class="chart-card">
            <h3>&#128065; Artikel Terpopuler</h3>
            <?php if (mysqli_num_rows($top_posts) === 0): ?>
                <p style="color:var(--mid);font-size:.85rem">Belum ada data views.</p>
            <?php else: ?>
            <?php $rank = 1; while ($tp = mysqli_fetch_assoc($top_posts)): ?>
            <div style="display:flex;align-items:center;gap:.75rem;padding:.55rem 0;border-bottom:1px solid var(--border)">
                <span style="width:24px;height:24px;background:<?= ['#f59e0b','#94a3b8','#cd7f32','var(--primary)','var(--primary)'][$rank-1] ?>;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;flex-shrink:0"><?= $rank++ ?></span>
                <div style="flex:1;overflow:hidden">
                    <a href="../post.php?id=<?= $tp['id'] ?>" target="_blank"
                       style="font-size:.84rem;font-weight:600;color:var(--dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;text-decoration:none">
                        <?= htmlspecialchars(mb_substr($tp['title'], 0, 45)) ?>
                    </a>
                </div>
                <span style="font-size:.78rem;color:var(--mid);white-space:nowrap">&#128065; <?= number_format($tp['views']) ?></span>
            </div>
            <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabel Artikel -->
    <div class="card">        <div class="card-header">
            <h3>Daftar Artikel</h3>
            <form method="GET" action="" class="search-form">
                <input type="text" name="search" placeholder="Cari judul / kategori…"
                       value="<?= htmlspecialchars($search) ?>">
                <select name="status" onchange="this.form.submit()" style="padding:.38rem .6rem;border:1.5px solid var(--border);border-radius:6px;font-size:.85rem">
                    <option value="">Semua Status</option>
                    <option value="published" <?= $filter_status === 'published' ? 'selected' : '' ?>>Publik</option>
                    <option value="draft"     <?= $filter_status === 'draft'     ? 'selected' : '' ?>>Draft</option>
                </select>
                <select name="date" onchange="this.form.submit()" style="padding:.38rem .6rem;border:1.5px solid var(--border);border-radius:6px;font-size:.85rem">
                    <option value="">Semua Waktu</option>
                    <option value="today" <?= $filter_date === 'today' ? 'selected' : '' ?>>Hari Ini</option>
                    <option value="week"  <?= $filter_date === 'week'  ? 'selected' : '' ?>>7 Hari Terakhir</option>
                    <option value="month" <?= $filter_date === 'month' ? 'selected' : '' ?>>30 Hari Terakhir</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Cari</button>
                <?php if ($search || $filter_status || $filter_date): ?>
                    <a href="index.php" class="btn btn-sm btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Gambar</th>
                        <th>Judul</th>
                        <th>Kategori</th>
                        <th>Penulis</th>
                        <th>Views</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no = $offset + 1; while ($row = mysqli_fetch_assoc($posts)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <?php if ($row['image']): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($row['image']) ?>" class="thumb" alt="">
                            <?php else: ?>
                                <span class="no-thumb">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:220px;white-space:normal">
                            <a href="../post.php?id=<?= $row['id'] ?>" target="_blank" style="color:var(--dark);font-weight:600">
                                <?= htmlspecialchars($row['title']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($row['category_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['username'] ?? '-') ?></td>
                        <td><?= number_format($row['views'] ?? 0) ?></td>
                        <td>
                            <!-- Toggle status button -->
                            <a href="<?= admin_query(['toggle' => 1, 'id' => $row['id'], 'page' => $page]) ?>"
                               class="badge badge-<?= $row['status'] === 'published' ? 'success' : 'warning' ?>"
                               style="cursor:pointer;text-decoration:none"
                               title="Klik untuk ubah status">
                                <?= $row['status'] === 'published' ? '&#10003; Publik' : '&#9998; Draft' ?>
                            </a>
                        </td>
                        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                        <td class="action-cell">
                            <a href="create.php?edit=<?= $row['id'] ?>" class="btn btn-xs btn-warning" title="Edit">&#9998;</a>
                            <a href="#" class="btn btn-xs btn-danger"
                               onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['title'])) ?>')"
                               title="Hapus">&#128465;</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($total_filtered == 0): ?>
                    <tr><td colspan="9" class="text-center" style="padding:2rem;color:var(--mid)">Tidak ada artikel ditemukan.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination" style="padding-top:1rem">
            <?php if ($page > 1): ?>
                <a href="<?= admin_query(['page' => $page - 1]) ?>" class="page-link">&laquo;</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="<?= admin_query(['page' => $i]) ?>"
                   class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= admin_query(['page' => $page + 1]) ?>" class="page-link">&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Konfirmasi Hapus -->
<div id="deleteModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <div class="modal-icon">&#128465;</div>
        <h3>Hapus Artikel?</h3>
        <p id="deleteModalText">Artikel ini akan dihapus permanen beserta gambarnya.</p>
        <div class="modal-actions">
            <a id="deleteConfirmBtn" href="#" class="btn btn-danger">Ya, Hapus</a>
            <button onclick="document.getElementById('deleteModal').style.display='none'"
                    class="btn btn-secondary">Batal</button>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, title) {
    document.getElementById('deleteModalText').textContent =
        'Artikel "' + title + '" akan dihapus permanen beserta gambarnya.';
    document.getElementById('deleteConfirmBtn').href = 'delete.php?id=' + id;
    document.getElementById('deleteModal').style.display = 'flex';
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

</body>
</html>
