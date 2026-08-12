<?php
session_start();

// Proteksi — harus login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once '../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id < 1) {
    header("Location: index.php");
    exit;
}

// Ambil data artikel untuk mendapatkan nama file gambar
$stmt = mysqli_prepare($conn, "SELECT id, image FROM posts WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$post   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$post) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Artikel tidak ditemukan.'];
    header("Location: index.php");
    exit;
}

// Hapus file gambar dari server (jika ada)
if (!empty($post['image'])) {
    $image_path = __DIR__ . '/../assets/uploads/' . $post['image'];
    if (file_exists($image_path)) {
        unlink($image_path);
    }
}

// Hapus record dari database
$stmt = mysqli_prepare($conn, "DELETE FROM posts WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$_SESSION['flash'] = ['type' => 'success', 'msg' => 'Artikel berhasil dihapus.'];
header("Location: index.php");
exit;
