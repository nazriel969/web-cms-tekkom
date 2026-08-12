<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require_once '../config/database.php';
require_once '../config/csrf.php';

define('UPLOAD_DIR', '../assets/uploads/');
$errors  = [];
$success = '';

// Hapus foto
if (isset($_GET['delete'])) {
    $did  = (int)$_GET['delete'];
    $row  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM gallery WHERE id=$did"));
    if ($row) {
        $f = UPLOAD_DIR . $row['image'];
        if (file_exists($f)) unlink($f);
        mysqli_query($conn, "DELETE FROM gallery WHERE id=$did");
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Foto berhasil dihapus.'];
    }
    header("Location: gallery.php"); exit;
}

// Upload foto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $title  = trim($_POST['title']  ?? '');
    $desc   = trim($_POST['desc']   ?? '');
    $album  = trim($_POST['album']  ?? 'Umum');
    $custom = trim($_POST['album_custom'] ?? '');
    if ($album === '__custom__' && $custom !== '') $album = $custom;
    if (empty($title)) $errors[] = 'Judul foto wajib diisi.';

    $uploaded = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        foreach ($_FILES['photos']['tmp_name'] as $idx => $tmp) {
            if ($_FILES['photos']['error'][$idx] !== UPLOAD_ERR_OK) continue;
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            if (!in_array($mime, $allowed)) { $errors[] = 'File ke-'.($idx+1).': format tidak didukung.'; continue; }
            if ($_FILES['photos']['size'][$idx] > 3*1024*1024) { $errors[] = 'File ke-'.($idx+1).': maks 3 MB.'; continue; }
            $ext      = strtolower(pathinfo($_FILES['photos']['name'][$idx], PATHINFO_EXTENSION));
            $new_name = 'gal_' . uniqid('', true) . '.' . $ext;
            if (move_uploaded_file($tmp, UPLOAD_DIR . $new_name)) {
                $uploaded[] = $new_name;
            }
        }
    }

    if (empty($errors) && !empty($uploaded)) {
        $uid  = $_SESSION['admin_id'];
        $stmt = mysqli_prepare($conn,
            "INSERT INTO gallery (title, description, image, album, created_by) VALUES (?,?,?,?,?)");
        foreach ($uploaded as $img) {
            mysqli_stmt_bind_param($stmt, 'ssssi', $title, $desc, $img, $album, $uid);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $_SESSION['flash'] = ['type'=>'success','msg'=>count($uploaded).' foto berhasil diupload.'];
        header("Location: gallery.php"); exit;
    } elseif (empty($uploaded) && empty($errors)) {
        $errors[] = 'Pilih minimal 1 foto untuk diupload.';
    }
}

// Data
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$filter_album = trim($_GET['album'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 16;
$offset   = ($page - 1) * $per_page;

if ($filter_album !== '') {
    $stmt = mysqli_prepare($conn,
        "SELECT g.*, u.username FROM gallery g LEFT JOIN users u ON g.created_by=u.id
         WHERE g.album=? ORDER BY g.created_at DESC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, 'sii', $filter_album, $per_page, $offset);
    mysqli_stmt_execute($stmt);
    $photos = mysqli_stmt_get_result($stmt);
    $cnt_s  = mysqli_prepare($conn, "SELECT COUNT(*) AS t FROM gallery WHERE album=?");
    mysqli_stmt_bind_param($cnt_s, 's', $filter_album);
    mysqli_stmt_execute($cnt_s);
    $total = mysqli_fetch_assoc(mysqli_stmt_get_result($cnt_s))['t'];
} else {
    $stmt = mysqli_prepare($conn,
        "SELECT g.*, u.username FROM gallery g LEFT JOIN users u ON g.created_by=u.id
         ORDER BY g.created_at DESC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, 'ii', $per_page, $offset);
    mysqli_stmt_execute($stmt);
    $photos = mysqli_stmt_get_result($stmt);
    $total  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM gallery"))['t'];
}
$total_pages = ceil($total / $per_page);

$albums_res = mysqli_query($conn, "SELECT DISTINCT album FROM gallery ORDER BY album ASC");
$albums = [];
while ($r = mysqli_fetch_assoc($albums_res)) $albums[] = $r['album'];

// Pending komentar untuk badge
$pending_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS t FROM comments WHERE status='pending'"))['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Galeri — Admin CMS</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-page">

<?php $active_menu = 'gallery'; require_once 'sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <h2>&#128247; Kelola Galeri</h2>
        <a href="../galeri.php" target="_blank" class="btn btn-sm btn-primary">&#127760; Lihat Galeri</a>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:340px 1fr;gap:1.5rem;align-items:start">

        <!-- Form Upload -->
        <div class="card">
            <h4 class="widget-title">&#10133; Upload Foto</h4>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Judul Foto <span class="required">*</span></label>
                    <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                           placeholder="Contoh: Upacara HUT RI 2024" required>
                </div>
                <div class="form-group">
                    <label>Album</label>
                    <select name="album" id="albumSelect" onchange="toggleCustomAlbum(this)">
                        <option value="Umum">Umum</option>
                        <?php foreach ($albums as $alb): ?>
                            <option value="<?= htmlspecialchars($alb) ?>"><?= htmlspecialchars($alb) ?></option>
                        <?php endforeach; ?>
                        <option value="__custom__">+ Buat Album Baru…</option>
                    </select>
                </div>
                <div class="form-group" id="customAlbumWrap" style="display:none">
                    <label>Nama Album Baru</label>
                    <input type="text" name="album_custom" placeholder="Nama album…">
                </div>
                <div class="form-group">
                    <label>Deskripsi <small>(opsional)</small></label>
                    <textarea name="desc" rows="2" placeholder="Keterangan foto…"><?= htmlspecialchars($_POST['desc'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Pilih Foto <small>(bisa banyak, maks 3MB/foto)</small></label>
                    <!-- Drop zone -->
                    <div class="drop-zone" id="dropZone" onclick="document.getElementById('photoInput').click()">
                        <span class="drop-icon">&#128247;</span>
                        <p>Drag & drop foto di sini</p>
                        <small>atau klik untuk memilih</small>
                    </div>
                    <input type="file" id="photoInput" name="photos[]" accept="image/*" multiple
                           style="display:none" onchange="previewPhotos(this)">
                    <div id="photoPreviewList" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">&#10133; Upload Foto</button>
            </form>
        </div>

        <!-- Daftar Foto -->
        <div>
            <!-- Filter album -->
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1rem">
                <a href="gallery.php" class="gallery-filter-btn <?= $filter_album===''?'active':'' ?>">Semua</a>
                <?php foreach ($albums as $alb): ?>
                <a href="gallery.php?album=<?= urlencode($alb) ?>"
                   class="gallery-filter-btn <?= $filter_album===$alb?'active':'' ?>">
                    <?= htmlspecialchars($alb) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="admin-gallery-grid">
                <?php while ($photo = mysqli_fetch_assoc($photos)): ?>
                <div class="admin-gallery-item">
                    <div class="admin-gallery-thumb">
                        <img src="../assets/uploads/<?= htmlspecialchars($photo['image']) ?>"
                             alt="<?= htmlspecialchars($photo['title']) ?>" loading="lazy">
                        <div class="admin-gallery-actions">
                            <a href="gallery.php?delete=<?= $photo['id'] ?>"
                               class="btn btn-xs btn-danger"
                               onclick="return confirm('Hapus foto ini?')">&#128465;</a>
                        </div>
                    </div>
                    <div class="admin-gallery-info">
                        <strong><?= htmlspecialchars(mb_substr($photo['title'],0,40)) ?></strong>
                        <span>&#128193; <?= htmlspecialchars($photo['album']) ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php if ($total === 0): ?>
                    <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--mid)">
                        Belum ada foto. Upload foto pertama kamu!
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin-top:1rem">
                <?php for ($i=1;$i<=$total_pages;$i++): ?>
                    <a href="?<?= $filter_album?'album='.urlencode($filter_album).'&':'' ?>page=<?= $i ?>"
                       class="page-link <?= $i===$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function toggleCustomAlbum(sel) {
    document.getElementById('customAlbumWrap').style.display =
        sel.value === '__custom__' ? 'block' : 'none';
}

// Drag & drop + preview
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    document.getElementById('photoInput').files = e.dataTransfer.files;
    previewPhotos(document.getElementById('photoInput'));
});

function previewPhotos(input) {
    const list = document.getElementById('photoPreviewList');
    list.innerHTML = '';
    for (const file of input.files) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:70px;height:55px;object-fit:cover;border-radius:6px;border:2px solid var(--border)';
            list.appendChild(img);
        };
        reader.readAsDataURL(file);
    }
}
</script>
</body>
</html>
