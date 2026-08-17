<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
if (isLoggedIn()) redirect(SITE_URL.'/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL.'/login.php');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $pass  = $_POST['password'] ?? '';
    $next  = clean($_POST['next'] ?? '');
    $rateKey = 'login:' . clientIp() . ':' . strtolower($email);
    if (isRateLimited($pdo, $rateKey)) {
        flash('error','Too many attempts. Please wait 15 minutes and try again.');
        redirect(SITE_URL.'/login.php'.($next ? '?next='.urlencode($next) : ''));
    }
    $stmt  = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $user  = $stmt->fetch();
if ($user && $pass === $user['password']) {
    clearLoginAttempts($pdo, $rateKey);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $role = $user['role'] ?? 'buyer';
    if ($next) {
        $redirect = $next;
    } elseif ($role === 'seller') {
        $redirect = SITE_URL.'/dashboard.php?tab=overview';
    } else {
        $redirect = SITE_URL.'/';
    }
    redirect($redirect);
} else {
        recordLoginAttempt($pdo, $rateKey);
        flash('error','Wrong email or password.');
        redirect(SITE_URL.'/login.php'.($next ? '?next='.urlencode($next) : ''));
    }
}
$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
$next = clean($_GET['next'] ?? '');
$googleClientId = getSetting($pdo, 'google_client_id', '');
if ($next) $_SESSION['google_next'] = $next;
?>
<div class="page-card" style="animation:fadeInUp .4s ease">
  <h2>Welcome Back</h2>
  <p class="subtitle">Login to access your orders and downloads</p>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= $next ?>">
    <div class="form-group">
      <label>Email</label>
      <input class="form-control" type="email" name="email" placeholder="your@email.com" required autofocus>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input class="form-control" type="password" name="password" required>
      <p class="form-hint" style="text-align:right;margin-top:6px"><a href="<?= SITE_URL ?>/forgot_password.php">Forgot password?</a></p>
    </div>
    <button type="submit" class="btn btn-primary btn-block" style="padding:13px">Login</button>
  </form>
  <?php if ($googleClientId): ?>
  <div style="margin-top:18px;text-align:center">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
      <div style="flex:1;height:1px;background:var(--border)"></div>
      <span style="color:var(--muted);font-size:12px">or</span>
      <div style="flex:1;height:1px;background:var(--border)"></div>
    </div>
    <a href="<?= SITE_URL ?>/auth/google_callback.php" class="btn btn-outline btn-block"
       style="display:flex;align-items:center;justify-content:center;gap:10px;padding:11px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      Continue with Google
    </a>
  </div>
  <?php endif; ?>
  <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--muted)">New here? <a href="<?= SITE_URL ?>/register.php">Create account</a></p>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
