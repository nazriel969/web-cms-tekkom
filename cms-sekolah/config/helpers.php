<?php
/**
 * Load site config from JSON
 */
function getSiteConfig(): array {
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/site_config.json';
        $config = json_decode(file_get_contents($file), true) ?? [];
    }
    return $config;
}

/**
 * Generate URL-friendly slug from a string
 */
function createSlug(string $text): string {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Sanitize plain text input (strip tags + trim)
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Handle image upload — returns filename on success, false on failure
 */
function uploadImage(array $file, string $dir, int $maxSize = 2097152): string|false {
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowedExt  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if ($file['error'] !== UPLOAD_ERR_OK)       return false;
    if ($file['size'] > $maxSize)               return false;

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowedMime))     return false;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt))           return false;

    $filename = uniqid('img_', true) . '.' . $ext;
    $dest     = rtrim($dir, '/') . '/' . $filename;

    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

    return $filename;
}

/**
 * Handle file upload (PDF/DOC) — returns filename on success, false on failure
 */
function uploadFile(array $file, string $dir, int $maxSize = 5242880): string|false {
    $allowedMime = ['application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $allowedExt  = ['pdf', 'doc', 'docx'];

    if ($file['error'] !== UPLOAD_ERR_OK)       return false;
    if ($file['size'] > $maxSize)               return false;

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowedMime))     return false;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt))           return false;

    $filename = uniqid('file_', true) . '.' . $ext;
    $dest     = rtrim($dir, '/') . '/' . $filename;

    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

    return $filename;
}

/**
 * Redirect helper
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Flash message — set or get
 */
function flashMessage(string $key, string $message = null): ?string {
    if (!isset($_SESSION)) session_start();
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/**
 * Require admin login
 */
function requireAdmin(): void {
    if (!isset($_SESSION)) session_start();
    if (empty($_SESSION['admin_id'])) {
        redirect('/cms-sekolah/admin/login.php');
    }
}

/**
 * Format tanggal ke Bahasa Indonesia
 */
function formatTanggal(string $date): string {
    $bulan = ['', 'Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $ts = strtotime($date);
    return date('d', $ts) . ' ' . $bulan[(int)date('m', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Truncate text with ellipsis
 */
function truncate(string $text, int $length = 120): string {
    $clean = strip_tags($text);
    return mb_strlen($clean) > $length
        ? mb_substr($clean, 0, $length, 'UTF-8') . '...'
        : $clean;
}
