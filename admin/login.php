<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
if (isAdmin()) redirect(SITE_URL.'/admin/dashboard.php');

$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$noAdminYet = $adminCount === 0;

// ---- CREATE ADMIN (only allowed while no admin account exists at all) ----
if ($noAdminYet && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verifyCsrf(SITE_URL.'/admin/login.php');
    $u  = trim($_POST['username'] ?? '');
    $p  = $_POST['password'] ?? '';
    $p2 = $_POST['password2'] ?? '';
    if (!$u || strlen($p) < 6) {
        flash('error', 'Username required, password must be at least 6 characters.');
    } elseif ($p !== $p2) {
        flash('error', 'Passwords do not match.');
    } else {
        $pdo->prepare("INSERT INTO admins (username, password) VALUES (?,?)")->execute([$u, $p]);
        flash('success', 'Admin account created! Login below.');
    }
    redirect(SITE_URL.'/admin/login.php');
}

// ---- LOGIN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'login') === 'login') {
    verifyCsrf(SITE_URL.'/admin/login.php');
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rateKey = 'admin_login:' . clientIp();
    if (isRateLimited($pdo, $rateKey)) {
        flash('error','Too many attempts. Please wait 15 minutes and try again.');
        redirect(SITE_URL.'/admin/login.php');
    }
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username=? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
if ($admin && $password === $admin['password']) {
            clearLoginAttempts($pdo, $rateKey);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        redirect(SITE_URL.'/admin/dashboard.php');
    } else {
        recordLoginAttempt($pdo, $rateKey);
        flash('error','Invalid credentials.');
        redirect(SITE_URL.'/admin/login.php');
    }
}
$fe = flash('error');
$fs = flash('success');
?>
<!DOCTYPE html><html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $noAdminYet ? 'Create Admin Account' : 'Admin Login' ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head><body>
<canvas id="particles-canvas"></canvas>
<div class="page-loader" id="page-loader"><div class="spinner"></div></div>

<?php if ($noAdminYet): ?>
<!-- FIRST RUN — no admin account exists yet, show account creation -->
<div class="page-card" style="margin-top:80px;animation:fadeInUp .4s ease">
  <h2>Create Admin Account</h2>
  <p class="subtitle">No admin account found yet — set one up to get started.</p>
  <?php if($fe): ?><div class="alert alert-error"><?= clean($fe) ?></div><?php endif; ?>
  <?php if($fs): ?><div class="alert alert-success"><?= clean($fs) ?></div><?php endif; ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-group"><label>Admin Username</label><input class="form-control" type="text" name="username" required autofocus></div>
    <div class="form-group"><label>Password (min 6 chars)</label><input class="form-control" type="password" name="password" required minlength="6"></div>
    <div class="form-group"><label>Confirm Password</label><input class="form-control" type="password" name="password2" required minlength="6"></div>
    <button type="submit" class="btn btn-primary btn-block" style="padding:13px">Create Admin Account</button>
  </form>
  <p style="text-align:center;margin-top:14px"><a href="<?= SITE_URL ?>" style="color:var(--muted);font-size:13px">← Back to Site</a></p>
</div>
<?php else: ?>
<!-- Normal login -->
<div class="page-card" style="margin-top:80px;animation:fadeInUp .4s ease">
  <h2>Admin Login</h2>
  <p class="subtitle">DevStore Admin Panel</p>
  <?php if($fe): ?><div class="alert alert-error"><?= clean($fe) ?></div><?php endif; ?>
  <?php if($fs): ?><div class="alert alert-success"><?= clean($fs) ?></div><?php endif; ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="login">
    <div class="form-group"><label>Username</label><input class="form-control" type="text" name="username" required autofocus></div>
    <div class="form-group"><label>Password</label><input class="form-control" type="password" name="password" required></div>
    <button type="submit" class="btn btn-primary btn-block" style="padding:13px">Login to Admin</button>
  </form>
  <p style="text-align:center;margin-top:14px"><a href="<?= SITE_URL ?>" style="color:var(--muted);font-size:13px">← Back to Site</a></p>
</div>
<?php endif; ?>

<div id="toast-container" class="toast-container"></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body></html>
