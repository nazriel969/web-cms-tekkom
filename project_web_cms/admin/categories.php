<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php"); exit;
}

require_once '../config/database.php';
require_once '../config/csrf.php';

$errors   = [];
$success  = '';
$edit_cat = null;

// Helper: buat slug dari nama
function make_slug(string $name): string {
    $slug = mb_strtolower(trim($name));
    $slug = str_replace(['&', ' ', '/', '\\'], '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// Edit mode
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($edit_id > 0) {
    $es = mysqli_prepare($conn, "SELECT * FROM categories WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($es, 'i', $edit_id);
    mysqli_stmt_execute($es);
    $edit_cat = mysqli_fetch_assoc(mysqli_stmt_get_result($es));
}

// Hapus
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Cek apakah ada artikel di kategori ini
    $chk = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS t FROM posts WHERE category_id = $del_id"));
    if ($chk['t'] > 0) {
        $errors[] = "Kategori ini masih memiliki {$chk['t']} artikel. Pindahkan atau hapus artikel terlebih dahulu.";
    } else {
        mysqli_query($conn, "DELETE FROM categories WHERE id = $del_id");
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Kategori berhasil dihapus.'];
        header("Location: categories.php"); exit;
    }
}

// Simpan / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $name    = trim($_POST['name'] ?? '');
    $post_id = (int)($_POST['id'] ?? 0);

    if (empty($name)) {
        $errors[] = 'Nama kategori wajib diisi.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Nama kategori maksimal 100 karakter.';
    } else {
        // Cek duplikat
        $dup_stmt = mysqli_prepare($conn,
            "SELECT id FROM categories WHERE name = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($dup_stmt, 'si', $name, $post_id);
        mysqli_stmt_execute($dup_stmt);
        mysqli_stmt_store_result($dup_stmt);
        $is_dup = mysqli_stmt_num_rows($dup_stmt) > 0;
        mysqli_stmt_close($dup_stmt);

        if ($is_dup) {
            $errors[] = "Kategori \"$name\" sudah ada.";
        } else {
            $slug = make_slug($name);

            // Pastikan slug unik — tambah angka kalau bentrok
            $slug_base = $slug;
            $i = 1;
            while (true) {
                $slug_check = mysqli_prepare($conn,
                    "SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1");
                mysqli_stmt_bind_param($slug_check, 'si', $slug, $post_id);
                mysqli_stmt_execute($slug_check);
                mysqli_stmt_store_result($slug_check);
                $exists = mysqli_stmt_num_rows($slug_check) > 0;
                mysqli_stmt_close($slug_check);
                if (!$exists) break;
                $slug = $slug_base . '-' . $i++;
            }

            if ($post_id > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE categories SET name = ?, slug = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'ssi', $name, $slug, $post_id);
                $msg = 'Kategori berhasil diperbarui.';
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO categories (name, slug) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, 'ss', $name, $slug);
                $msg = 'Kategori berhasil ditambahkan.';
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => $msg];
            header("Location: categories.php"); exit;
        }
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Daftar kategori beserta jumlah artikel
$cats = mysqli_query($conn,
    "SELECT c.id, c.name, COUNT(p.id) AS total_posts
     FROM categories c
     LEFT JOIN posts p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.name ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori — Admin CMS</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-page">

<?php $active_menu = 'categories'; require_once 'sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <h2>&#128193; Kelola Kategori</h2>
        <a href="index.php" class="btn btn-sm btn-secondary">&larr; Dashboard</a>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1.5rem;align-items:start">

        <!-- Form Tambah/Edit -->
        <div class="card">
            <h3 style="margin-bottom:1rem;font-size:1rem">
                <?= $edit_cat ? '&#9998; Edit Kategori' : '&#10133; Tambah Kategori' ?>
            </h3>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <?php if ($edit_cat): ?>
                    <input type="hidden" name="id" value="<?= $edit_cat['id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="cat_name">Nama Kategori <span class="required">*</span></label>
                    <input type="text" id="cat_name" name="name" maxlength="100"
                           value="<?= htmlspecialchars($edit_cat['name'] ?? ($_POST['name'] ?? '')) ?>"
                           placeholder="Contoh: Berita Sekolah" required autofocus>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_cat ? '&#10003; Perbarui' : '&#10133; Tambah' ?>
                    </button>
                    <?php if ($edit_cat): ?>
                        <a href="categories.php" class="btn btn-secondary">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Kategori -->
        <div class="card">
            <h3 style="margin-bottom:1rem;font-size:1rem">Daftar Kategori</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Kategori</th>
                        <th>Jumlah Artikel</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no = 1; while ($cat = mysqli_fetch_assoc($cats)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td>
                            <a href="index.php?status=&search=&category=<?= $cat['id'] ?>"
                               style="color:var(--primary);font-weight:600">
                                <?= $cat['total_posts'] ?> artikel
                            </a>
                        </td>
                        <td class="action-cell">
                            <a href="categories.php?edit=<?= $cat['id'] ?>"
                               class="btn btn-xs btn-warning" title="Edit">&#9998;</a>
                            <?php if ($cat['total_posts'] == 0): ?>
                                <a href="categories.php?delete=<?= $cat['id'] ?>"
                                   class="btn btn-xs btn-danger"
                                   onclick="return confirm('Hapus kategori \'<?= htmlspecialchars(addslashes($cat['name'])) ?>\'?')"
                                   title="Hapus">&#128465;</a>
                            <?php else: ?>
                                <span class="btn btn-xs btn-secondary"
                                      style="opacity:.4;cursor:not-allowed" title="Tidak bisa dihapus — ada artikel">&#128465;</span>
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
