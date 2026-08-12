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

$categories = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");

// Mode Edit
$edit_id   = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$post_data = [
    'title'       => '',
    'content'     => '',
    'excerpt'     => '',
    'category_id' => '',
    'status'      => 'draft',
    'image'       => '',
    'is_featured' => 0,
];

if ($edit_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM posts WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $edit_id);
    mysqli_stmt_execute($stmt);
    $found = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$found) { header("Location: index.php"); exit; }
    $post_data = $found;
}

define('UPLOAD_DIR', '../assets/uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024);
$allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $title       = trim($_POST['title']   ?? '');
    // Konten dari Summernote (HTML) — strip tag berbahaya tapi izinkan HTML formatting
    $content     = $_POST['content']      ?? '';
    $excerpt     = trim(strip_tags($_POST['excerpt'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);
    $raw_status  = trim($_POST['status']  ?? '');
    $status      = in_array($raw_status, ['published', 'draft'], true) ? $raw_status : 'draft';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $user_id     = $_SESSION['admin_id'];

    // Sanitasi konten HTML dari Summernote
    // Izinkan tag HTML umum, blokir script/iframe berbahaya
    $allowed_tags = '<p><br><b><strong><i><em><u><s><ul><ol><li><h1><h2><h3><h4><blockquote><pre><code><a><img><table><thead><tbody><tr><td><th><hr><span><div>';
    $content = strip_tags($content, $allowed_tags);
    // Hapus atribut on* (onclick, onload, dll) dan javascript:
    $content = preg_replace('/\s*on\w+\s*=\s*"[^"]*"/i', '', $content);
    $content = preg_replace('/\s*on\w+\s*=\s*\'[^\']*\'/i', '', $content);
    $content = preg_replace('/javascript\s*:/i', '', $content);

    if (empty($title))    $errors[] = 'Judul artikel wajib diisi.';
    if (empty($content) || trim(strip_tags($content)) === '')
                          $errors[] = 'Konten artikel wajib diisi.';
    if ($category_id < 1) $errors[] = 'Pilih kategori yang valid.';

    $image_name = $post_data['image'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file      = $_FILES['image'];
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = 'Format gambar tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.';
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'Ukuran gambar maksimal 2 MB.';
        } else {
            $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_name    = uniqid('img_', true) . '.' . $ext;
            $upload_path = UPLOAD_DIR . $new_name;
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                if ($edit_id > 0 && !empty($post_data['image'])) {
                    $old = UPLOAD_DIR . $post_data['image'];
                    if (file_exists($old)) unlink($old);
                }
                $image_name = $new_name;
            } else {
                $errors[] = 'Gagal mengupload gambar. Periksa izin folder uploads.';
            }
        }
    }

    if (isset($_POST['remove_image']) && $edit_id > 0 && !empty($post_data['image'])) {
        $old = UPLOAD_DIR . $post_data['image'];
        if (file_exists($old)) unlink($old);
        $image_name = '';
    }

    // Jika artikel ini dijadikan featured, non-featured artikel lain
    if ($is_featured && empty($errors)) {
        mysqli_query($conn, "UPDATE posts SET is_featured = 0 WHERE is_featured = 1");
    }

    if (empty($errors)) {
        if ($edit_id > 0) {
            // title(s), content(s), excerpt(s), category_id(i), status(s), image(s), is_featured(i), id(i)
            $stmt = mysqli_prepare($conn,
                "UPDATE posts SET title=?, content=?, excerpt=?, category_id=?, status=?, image=?, is_featured=?
                 WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'sssissii',
                $title, $content, $excerpt, $category_id, $status, $image_name, $is_featured, $edit_id);
        } else {
            // user_id(i), category_id(i), title(s), content(s), excerpt(s), image(s), status(s), is_featured(i)
            $stmt = mysqli_prepare($conn,
                "INSERT INTO posts (user_id, category_id, title, content, excerpt, image, status, is_featured, created_at)
                 VALUES (?,?,?,?,?,?,?,?,NOW())");
            mysqli_stmt_bind_param($stmt, 'iisssssi',
                $user_id, $category_id, $title, $content, $excerpt, $image_name, $status, $is_featured);
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash'] = [
                'type' => 'success',
                'msg'  => $edit_id > 0 ? 'Artikel berhasil diperbarui.' : 'Artikel berhasil disimpan.',
            ];
            mysqli_stmt_close($stmt);
            header("Location: index.php"); exit;
        } else {
            $errors[] = 'Gagal menyimpan: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }

    $post_data['title']       = $title;
    $post_data['content']     = $content;
    $post_data['excerpt']     = $excerpt;
    $post_data['category_id'] = $category_id;
    $post_data['status']      = $status;
    $post_data['is_featured'] = $is_featured;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_id > 0 ? 'Edit' : 'Tulis' ?> Artikel — Admin CMS</title>
    <link rel="stylesheet" href="../style.css">
    <!-- Summernote -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
</head>
<body class="admin-page">

<?php $active_menu = 'create'; require_once 'sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <h2><?= $edit_id > 0 ? '&#9998; Edit Artikel' : '&#10133; Tulis Artikel Baru' ?></h2>
        <a href="index.php" class="btn btn-sm btn-secondary">&larr; Kembali</a>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin:0;padding-left:1.2rem">
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="create-layout">
        <form method="POST" action="" enctype="multipart/form-data" novalidate id="articleForm">
            <?= csrf_field() ?>

            <!-- Kolom Kiri -->
            <div class="create-main">
                <div class="card">
                    <div class="form-group">
                        <label for="title">Judul Artikel <span class="required">*</span></label>
                        <input type="text" id="title" name="title"
                               value="<?= htmlspecialchars($post_data['title']) ?>"
                               placeholder="Masukkan judul artikel…" required>
                    </div>

                    <div class="form-group">
                        <label for="excerpt">Ringkasan <small>(opsional — tampil di kartu artikel)</small></label>
                        <textarea id="excerpt" name="excerpt" rows="2"
                                  placeholder="Tulis ringkasan singkat artikel (1–2 kalimat)…"
                                  style="min-height:70px"><?= htmlspecialchars($post_data['excerpt'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="content">Konten Artikel <span class="required">*</span></label>
                        <textarea id="content" name="content"><?= $post_data['content'] ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan -->
            <div class="create-sidebar">

                <!-- Publikasi -->
                <div class="card">
                    <h4 class="widget-title">&#128190; Publikasi</h4>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="draft"     <?= $post_data['status'] === 'draft'     ? 'selected' : '' ?>>&#9998; Draft</option>
                            <option value="published" <?= $post_data['status'] === 'published' ? 'selected' : '' ?>>&#10003; Publik</option>
                        </select>
                    </div>

                    <!-- Toggle Featured -->
                    <div class="featured-toggle">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_featured" value="1"
                                   <?= !empty($post_data['is_featured']) ? 'checked' : '' ?>>
                            <span class="toggle-switch"></span>
                            <span class="toggle-text">
                                &#11088; Jadikan Artikel Unggulan
                                <small>Tampil besar di halaman utama</small>
                            </span>
                        </label>
                    </div>

                    <div class="form-actions" style="flex-direction:column;gap:.5rem;margin-top:1rem">
                        <button type="submit" class="btn btn-primary btn-block">
                            <?= $edit_id > 0 ? '&#10003; Perbarui Artikel' : '&#10133; Simpan Artikel' ?>
                        </button>
                        <?php if ($edit_id > 0): ?>
                        <a href="../post.php?id=<?= $edit_id ?>&preview=1" target="_blank"
                           class="btn btn-preview btn-block" style="text-align:center">
                            &#128065; Preview Artikel
                        </a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-secondary btn-block" style="text-align:center">Batal</a>
                    </div>
                </div>

                <!-- Kategori -->
                <div class="card">
                    <h4 class="widget-title">&#128193; Kategori</h4>
                    <div class="form-group" style="margin-bottom:0">
                        <select id="category_id" name="category_id" required>
                            <option value="">— Pilih Kategori —</option>
                            <?php
                            mysqli_data_seek($categories, 0);
                            while ($cat = mysqli_fetch_assoc($categories)):
                            ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= (int)$post_data['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div style="margin-top:.6rem">
                        <a href="categories.php" style="font-size:.8rem;color:var(--primary)">+ Kelola Kategori</a>
                    </div>
                </div>

                <!-- Gambar -->
                <div class="card">
                    <h4 class="widget-title">&#128247; Gambar Utama</h4>
                    <div id="imagePreview" class="image-preview-box" style="<?= empty($post_data['image']) ? 'display:none' : '' ?>">
                        <img id="previewImg"
                             src="<?= !empty($post_data['image']) ? '../assets/uploads/' . htmlspecialchars($post_data['image']) : '' ?>"
                             alt="Preview">
                    </div>
                    <div id="previewPlaceholder" class="image-preview-placeholder"
                         style="<?= !empty($post_data['image']) ? 'display:none' : '' ?>">
                        &#128247; Belum ada gambar
                    </div>
                    <div class="form-group" style="margin-top:.75rem;margin-bottom:0">
                        <input type="file" id="image" name="image" accept="image/*"
                               onchange="previewImage(this)">
                        <small style="color:var(--mid)">JPG/PNG/WEBP/GIF, maks. 2 MB</small>
                    </div>
                    <?php if (!empty($post_data['image'])): ?>
                    <div style="margin-top:.6rem">
                        <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;cursor:pointer">
                            <input type="checkbox" name="remove_image" value="1"
                                   onchange="toggleRemoveImage(this)">
                            Hapus gambar saat ini
                        </label>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </form>
    </div>
</main>

<!-- jQuery + Summernote -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-id-ID.min.js"></script>
<script>
$(document).ready(function() {
    $('#content').summernote({
        lang: 'id-ID',
        height: 380,
        minHeight: 300,
        placeholder: 'Tulis isi artikel di sini…',
        toolbar: [
            ['style',   ['style']],
            ['font',    ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['para',    ['ul', 'ol', 'paragraph']],
            ['insert',  ['link', 'picture', 'hr']],
            ['table',   ['table']],
            ['view',    ['fullscreen', 'codeview', 'undo', 'redo']],
        ],
        styleTags: ['p', 'h2', 'h3', 'h4', 'blockquote', 'pre'],
        callbacks: {
            onImageUpload: function(files) {
                // Upload gambar inline via AJAX
                for (let i = 0; i < files.length; i++) {
                    uploadInlineImage(files[i], this);
                }
            }
        }
    });
});

// Upload gambar inline ke server
function uploadInlineImage(file, editor) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_inline', 1);

    fetch('upload_image.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.url) {
            $(editor).summernote('insertImage', data.url);
        } else {
            alert('Gagal upload gambar: ' + (data.error || 'unknown'));
        }
    })
    .catch(() => alert('Gagal upload gambar.'));
}

// Preview gambar utama
function previewImage(input) {
    const preview     = document.getElementById('previewImg');
    const box         = document.getElementById('imagePreview');
    const placeholder = document.getElementById('previewPlaceholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            box.style.display = 'block';
            placeholder.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleRemoveImage(cb) {
    const box         = document.getElementById('imagePreview');
    const placeholder = document.getElementById('previewPlaceholder');
    if (cb.checked) {
        box.style.display = 'none';
        placeholder.style.display = 'flex';
    } else {
        box.style.display = 'block';
        placeholder.style.display = 'none';
    }
}
</script>

</body>
</html>
