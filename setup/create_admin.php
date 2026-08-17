<?php
/**
 * ONE-TIME SETUP — Create Admin Account
 * 1. database.sql import karo
 * 2. Browser me open karo: yourdomain.com/setup/create_admin.php
 * 3. Admin banane ke baad IS FILE KO DELETE KARO!
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
$done = false; $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if (!$u || strlen($p) < 6) { $msg = 'Username required, password min 6 chars.'; }
    else {
        $hash = password_hash($p, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admins (username,password) VALUES (?,?) ON DUPLICATE KEY UPDATE password=?")->execute([$u,$hash,$hash]);
        $done = true; $msg = "Admin '$u' created! Login at: ".SITE_URL."/admin/login.php — NOW DELETE THIS FILE!";
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Setup</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head><body>
<div class="page-card" style="margin-top:80px">
<h2>Admin Setup</h2>
<?php if($msg): ?><div class="alert <?= $done ? 'alert-success':'alert-error' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if(!$done): ?>
<form method="POST">
  <?= csrf_field() ?>
  <div class="form-group"><label>Admin Username</label><input class="form-control" type="text" name="username" required></div>
  <div class="form-group"><label>Password (min 6 chars)</label><input class="form-control" type="password" name="password" required></div>
  <button type="submit" class="btn btn-primary btn-block">Create Admin</button>
</form>
<?php endif; ?>
</div></body></html>
