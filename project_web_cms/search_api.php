<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

$like = '%' . $q . '%';
$stmt = mysqli_prepare($conn,
    "SELECT p.id, p.title, c.name AS category
     FROM posts p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.status = 'published' AND (p.title LIKE ? OR p.excerpt LIKE ?)
     ORDER BY p.created_at DESC LIMIT 6");
mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'id'       => (int)$row['id'],
        'title'    => htmlspecialchars($row['title']),
        'category' => htmlspecialchars($row['category'] ?? 'Umum'),
    ];
}
mysqli_stmt_close($stmt);
echo json_encode($data, JSON_UNESCAPED_UNICODE);
