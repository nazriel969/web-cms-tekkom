<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

if (!isset($_POST['upload_inline']) || !isset($_FILES['file'])) {
    echo json_encode(['error' => 'No file']); exit;
}

$file          = $_FILES['file'];
$allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$max_size      = 2 * 1024 * 1024;
$upload_dir    = '../assets/uploads/';

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Upload error: ' . $file['error']]); exit;
}

$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['error' => 'Format tidak didukung']); exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['error' => 'Ukuran maks. 2 MB']); exit;
}

$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$new_name = 'inline_' . uniqid('', true) . '.' . $ext;
$dest     = $upload_dir . $new_name;

if (move_uploaded_file($file['tmp_name'], $dest)) {
    // Kembalikan URL relatif dari root proyek
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST'];
    $path = dirname(dirname($_SERVER['SCRIPT_NAME']));
    echo json_encode(['url' => $base . $path . '/assets/uploads/' . $new_name]);
} else {
    echo json_encode(['error' => 'Gagal menyimpan file']);
}
