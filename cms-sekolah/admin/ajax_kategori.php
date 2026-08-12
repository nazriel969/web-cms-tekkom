<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json');

// Hanya izinkan admin yang login
if (empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$nama = sanitize($_POST['nama'] ?? '');
if (mb_strlen($nama) < 2) {
    echo json_encode(['success' => false, 'error' => 'Nama kategori minimal 2 karakter.']);
    exit;
}

$pdo  = getDB();
$slug = createSlug($nama);

// Cek duplikat
$check = $pdo->prepare("SELECT id FROM kategori WHERE nama_kategori = ? OR slug = ?");
$check->execute([$nama, $slug]);
if ($check->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Kategori sudah ada.']);
    exit;
}

// Pastikan slug unik
$slugCheck = $pdo->prepare("SELECT id FROM kategori WHERE slug = ?");
$slugCheck->execute([$slug]);
if ($slugCheck->fetch()) {
    $slug .= '-' . time();
}

$stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori, slug) VALUES (?, ?)");
$stmt->execute([$nama, $slug]);
$id = (int)$pdo->lastInsertId();

echo json_encode(['success' => true, 'id' => $id, 'nama' => $nama]);
