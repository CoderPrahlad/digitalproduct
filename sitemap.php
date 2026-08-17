<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

header('Content-Type: application/xml; charset=utf-8');

// Fetch all active products
$stmt = $pdo->query("SELECT slug, updated_at FROM products WHERE status='active' ORDER BY updated_at DESC");
$products = $stmt->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

  <!-- Homepage -->
  <url>
    <loc><?= SITE_URL ?>/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Static pages -->
  <url>
    <loc><?= SITE_URL ?>/contact.php</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc><?= SITE_URL ?>/register.php</loc>
    <changefreq>monthly</changefreq>
    <priority>0.4</priority>
  </url>

  <!-- Product pages -->
  <?php foreach ($products as $p): ?>
  <url>
    <loc><?= SITE_URL ?>/product.php?slug=<?= urlencode($p['slug']) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($p['updated_at'])) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
  <?php endforeach; ?>

</urlset>