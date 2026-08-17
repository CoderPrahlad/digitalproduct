<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$token = clean($_GET['token'] ?? '');
$done  = false;
$error = false;

if ($token) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM newsletter_subscribers WHERE token=? AND status='active'");
        $stmt->execute([$token]);
        $sub = $stmt->fetch();
        if ($sub) {
            $pdo->prepare("UPDATE newsletter_subscribers SET status='unsubscribed' WHERE id=?")->execute([$sub['id']]);
            $done = true;
        } else {
            $error = 'Invalid or already unsubscribed link.';
        }
    } catch (Exception $e) {
        $error = 'Something went wrong. Please try again.';
    }
} else {
    $error = 'Missing unsubscribe token.';
}

$pageTitle = 'Unsubscribe';
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-card" style="text-align:center;animation:fadeInUp .4s ease">
  <?php if ($done): ?>
    <div style="font-size:48px;margin-bottom:16px">✅</div>
    <h2>Unsubscribed</h2>
    <p style="color:var(--muted);margin-top:10px">You have been removed from our newsletter. We're sorry to see you go!</p>
    <a href="<?= SITE_URL ?>" class="btn btn-primary" style="margin-top:20px">← Back to Home</a>
  <?php else: ?>
    <div style="font-size:48px;margin-bottom:16px">❌</div>
    <h2>Oops</h2>
    <p style="color:var(--muted);margin-top:10px"><?= clean($error) ?></p>
    <a href="<?= SITE_URL ?>" class="btn btn-outline" style="margin-top:20px">← Back to Home</a>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
