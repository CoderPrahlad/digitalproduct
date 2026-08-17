<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/mail/Mailer.php';
if (isLoggedIn()) redirect(SITE_URL.'/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL.'/forgot_password.php');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

    $rateKey = 'forgot:' . clientIp() . ':' . strtolower($email);
    if (isRateLimited($pdo, $rateKey, 5, 15)) {
        flash('error', 'Too many attempts. Please wait 15 minutes and try again.');
        redirect(SITE_URL.'/forgot_password.php');
    }
    recordLoginAttempt($pdo, $rateKey);

    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = createPasswordResetToken($pdo, $email, 60);
            $resetUrl = SITE_URL . '/reset_password.php?email=' . urlencode($email) . '&token=' . $token;
            Mailer::sendPasswordReset($email, $user['name'], $resetUrl);
        }
        // Same message whether or not the email exists — avoids leaking which emails are registered
    }
    flash('success', 'If that email is registered, a password reset link has been sent. Please check your inbox (and spam folder).');
    redirect(SITE_URL.'/forgot_password.php');
}
$pageTitle = 'Forgot Password';
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-card" style="animation:fadeInUp .4s ease">
  <h2>Forgot Password</h2>
  <p class="subtitle">Enter your account email — we'll send you a reset link</p>
  <form method="POST">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>Email</label>
      <input class="form-control" type="email" name="email" placeholder="your@email.com" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-block" style="padding:13px">Send Reset Link</button>
  </form>
  <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--muted)">Remembered your password? <a href="<?= SITE_URL ?>/login.php">Login</a></p>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
