<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
http_response_code(404);
$pageTitle = 'Page Not Found';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding:100px 0;text-align:center;max-width:600px">
  <div style="font-size:90px;line-height:1;margin-bottom:10px">🧭</div>
  <h1 style="font-family:'Space Grotesk',sans-serif;font-size:42px;margin-bottom:10px">404</h1>
  <h2 style="margin-bottom:14px">Page Not Found</h2>
  <p style="color:var(--muted);font-size:15px;line-height:1.7;margin-bottom:28px">
    The page you're looking for doesn't exist, may have moved, or the link is broken.
  </p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
    <a href="<?= SITE_URL ?>" class="btn btn-primary">🏠 Go Home</a>
    <a href="<?= SITE_URL ?>/#products" class="btn btn-outline">🛍️ Browse Products</a>
    <a href="<?= SITE_URL ?>/contact.php" class="btn btn-outline">✉️ Contact Support</a>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
