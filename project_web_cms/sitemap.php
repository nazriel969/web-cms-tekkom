<?php
header('Content-Type: application/xml; charset=utf-8');
require_once 'config/database.php';

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
      . '://' . $_SERVER['HTTP_HOST'] . '/project_web_cms';

$posts = mysqli_query($conn,
    "SELECT id, created_at FROM posts WHERE status='published' ORDER BY created_at DESC");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= $base ?>/index.php</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= $base ?>/profil.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= $base ?>/galeri.php</loc>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= $base ?>/guru.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php while ($p = mysqli_fetch_assoc($posts)): ?>
    <url>
        <loc><?= $base ?>/post.php?id=<?= $p['id'] ?></loc>
        <lastmod><?= date('Y-m-d', strtotime($p['created_at'])) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>
    <?php endwhile; ?>
</urlset>
