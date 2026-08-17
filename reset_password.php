<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
if (isLoggedIn()) redirect(SITE_URL.'/dashboard.php');

$email = filter_var($_GET['email'] ?? $_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$token = clean($_GET['token'] ?? $_POST['token'] ?? '');
$tokenRaw = $_GET['token'] ?? $_POST['token'] ?? ''; // raw (unescaped) token for hashing/comparison

$reset = ($email && $tokenRaw) ? findValidPasswordReset($pdo, $email, $tokenRaw) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL.'/reset_password.php?email='.urlencode($email).'&token='.urlencode($tokenRaw));
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$reset) {
        flash('error', 'This reset link is invalid or has expired. Please request a new one.');
        redirect(SITE_URL.'/forgot_password.php');
    } elseif (strlen($pass) < 6) {
        flash('error', 'Password must be at least 6 characters.');
        redirect(SITE_URL.'/reset_password.php?email='.urlencode($email).'&token='.urlencode($tokenRaw));
    } elseif ($pass !== $confirm) {
        flash('error', 'Passwords do not match.');
        redirect(SITE_URL.'/reset_password.php?email='.urlencode($email).'&token='.urlencode($tokenRaw));
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE email=?")->execute([$hash, $email]);
        markPasswordResetUsed($pdo, $reset['id']);
        flash('success', 'Password updated successfully. Please login with your new password.');
        redirect(SITE_URL.'/login.php');
    }
}

$pageTitle = 'Reset Password';
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-card" style="animation:fadeInUp .4s ease">
  <h2>Reset Password</h2>
  <?php if (!$reset): ?>
    <p class="subtitle">This reset link is invalid or has expired.</p>
    <p style="text-align:center;margin-top:16px">
      <a class="btn btn-primary" href="<?= SITE_URL ?>/forgot_password.php">Request a New Link</a>
    </p>
  <?php else: ?>
    <p class="subtitle">Choose a new password for <?= clean($email) ?></p>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="email" value="<?= clean($email) ?>">
      <input type="hidden" name="token" value="<?= clean($tokenRaw) ?>">
      <div class="form-group">
        <label>New Password</label>
        <input class="form-control" type="password" name="password" placeholder="Min 6 characters" required autofocus>
      </div>
      <div class="form-group">
        <label>Confirm New Password</label>
        <input class="form-control" type="password" name="confirm_password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="padding:13px">Update Password</button>
    </form>
  <?php endif; ?>
  <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--muted)"><a href="<?= SITE_URL ?>/login.php">Back to Login</a></p>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
