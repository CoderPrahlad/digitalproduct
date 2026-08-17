<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
requireAdmin();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
<title><?= isset($pageTitle) ? clean($pageTitle).' — Admin' : 'Admin Panel' ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/admin/assets/admin.css">
</head>
<body>
<div class="admin-mobile-bar">
  <a href="<?= SITE_URL ?>/admin/dashboard.php" class="logo">⚡ <?= SITE_NAME ?></a>
  <button class="admin-hamburger" onclick="toggleAdminSidebar()" aria-label="Menu">☰</button>
</div>
<div class="admin-sidebar-overlay" id="adminOverlay" onclick="toggleAdminSidebar()"></div>
<div class="admin-layout">
  <aside class="admin-sidebar" id="adminSidebar">
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="logo">⚡ <?= SITE_NAME ?></a>
    <nav class="sidebar-nav">
      <a href="<?= SITE_URL ?>/admin/dashboard.php"  class="<?= $currentPage==='dashboard.php' ?'active':'' ?>">📊 Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/products.php"   class="<?= $currentPage==='products.php'  ?'active':'' ?>">📦 Products</a>
      <a href="<?= SITE_URL ?>/admin/marketplace.php"  class="<?= $currentPage==='marketplace.php'?'active':'' ?>">🏪 Marketplace</a>
      <a href="<?= SITE_URL ?>/admin/categories.php" class="<?= $currentPage==='categories.php'?'active':'' ?>">🗂️ Categories</a>
      <a href="<?= SITE_URL ?>/admin/orders.php"     class="<?= $currentPage==='orders.php'    ?'active':'' ?>">🛒 Orders</a>
      <a href="<?= SITE_URL ?>/admin/gateways.php"   class="<?= $currentPage==='gateways.php'  ?'active':'' ?>">💳 Gateways</a>
      <a href="<?= SITE_URL ?>/admin/users.php"      class="<?= $currentPage==='users.php'     ?'active':'' ?>">👥 Users</a>
      <a href="<?= SITE_URL ?>/admin/analytics.php"  class="<?= $currentPage==='analytics.php' ?'active':'' ?>">📈 Analytics</a>
      <a href="<?= SITE_URL ?>/admin/settings.php"   class="<?= $currentPage==='settings.php'  ?'active':'' ?>">⚙️ Settings</a>
    </nav>
    <div style="margin-top:auto;padding-top:20px;border-top:1px solid var(--border);position:sticky;bottom:0;background:var(--bg2);padding-bottom:8px">
      <a href="<?= SITE_URL ?>" target="_blank" style="display:block;padding:8px 12px;color:var(--muted);font-size:13px">🌐 View Site</a>
      <a href="<?= SITE_URL ?>/admin/logout.php" style="display:block;padding:8px 12px;color:var(--danger);font-size:13px">🚪 Logout</a>
    </div>
  </aside>
  <main class="admin-main">
<?php
$fs=flash('success'); $fe=flash('error');
if ($fs) echo '<div class="alert alert-success" data-auto-hide>✓ '.clean($fs).'</div>';
if ($fe) echo '<div class="alert alert-error"   data-auto-hide>✗ '.clean($fe).'</div>';
?>