<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();

$ref   = clean($_GET['ref'] ?? '');
$stmt  = $pdo->prepare("SELECT o.*, p.title, p.file_path FROM orders o JOIN products p ON p.id=o.product_id WHERE o.order_ref=? AND o.user_id=? LIMIT 1");
$stmt->execute([$ref, $_SESSION['user_id']]);
$order = $stmt->fetch();
if (!$order) { redirect(SITE_URL . '/dashboard.php'); }

// Manual UPI payments land here while still 'pending' (not yet verified by admin).
// In that case we also offer a WhatsApp confirmation popup after a short delay.
$isPending  = $order['status'] === 'pending';
$offerWa    = $isPending && ($_GET['wa'] ?? '') === '1';
$isUsdt     = $order['payment_method'] === 'usdt';
$waMessage  = $isUsdt
    ? "Hi, I've paid for order {$order['order_ref']} via USDT.\nProduct: {$order['title']}\nAmount: USDT " . number_format((float)$order['crypto_amount'], 2) . " (₹" . number_format($order['amount']) . ")\nTXID: {$order['utr_number']}\nPlease confirm."
    : "Hi, I've paid for order {$order['order_ref']} via UPI.\nProduct: {$order['title']}\nAmount: ₹" . number_format($order['amount']) . "\nUTR: {$order['utr_number']}\nPlease confirm.";
$waLink     = 'https://wa.me/' . WA_NUMBER . '?text=' . urlencode($waMessage);

$pageTitle = 'Payment Successful';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
<div class="success-wrap" style="animation:fadeInUp .5s ease">
  <div class="success-icon">🎉</div>
  <h2 style="font-family:'Space Grotesk',sans-serif;font-size:26px;margin-bottom:10px">Payment Successful!</h2>
  <?php if ($isPending): ?>
  <p style="color:var(--muted2);margin-bottom:24px">Your order has been received! We'll verify your payment and deliver your files to your email within 1-2 hours.</p>
  <?php else: ?>
  <p style="color:var(--muted2);margin-bottom:24px">Your order has been placed and your files are being delivered to your email.</p>
  <?php endif; ?>

  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:20px;text-align:left;margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
      <span style="color:var(--muted)">Order Ref</span>
      <strong><?= clean($order['order_ref']) ?></strong>
    </div>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
      <span style="color:var(--muted)">Product</span>
      <strong><?= clean($order['title']) ?></strong>
    </div>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
      <span style="color:var(--muted)">Amount</span>
      <strong style="color:#a78bfa">₹<?= number_format($order['amount']) ?></strong>
    </div>
    <?php if ($order['license_key']): ?>
    <div style="display:flex;justify-content:space-between;padding:8px 0">
      <span style="color:var(--muted)">License Key</span>
      <strong style="color:var(--success);font-family:monospace"><?= clean($order['license_key']) ?></strong>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($isPending): ?>
  <div style="background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.25);border-radius:var(--radius-sm);padding:14px;margin-bottom:24px;font-size:14px;color:#fbbf24">
    ⏳ Your payment is being verified. Files &amp; license key will be emailed to you right after confirmation.
  </div>
  <?php else: ?>
  <div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);border-radius:var(--radius-sm);padding:14px;margin-bottom:24px;font-size:14px;color:#34d399">
    ✉️ Download link & license key sent to your email!
  </div>
  <?php endif; ?>

  <?php if ($order['download_token']): ?>
  <a href="<?= SITE_URL ?>/download.php?token=<?= urlencode($order['download_token']) ?>&ref=<?= urlencode($order['order_ref']) ?>"
     class="btn btn-primary btn-block" style="padding:14px;font-size:15px;margin-bottom:12px">
    ⬇ Download Now
  </a>
  <?php endif; ?>
  <a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-outline btn-block">View All Orders</a>
</div>
</div>

<?php if ($offerWa): ?>
<!-- WhatsApp confirmation popup: appears a couple seconds after the success
     message, giving the user a clear choice instead of an automatic redirect. -->
<div id="waPopupBackdrop" class="wa-popup-backdrop">
  <div class="wa-popup-card">
    <div class="wa-popup-icon">💬</div>
    <h3 class="wa-popup-title">Confirm on WhatsApp?</h3>
    <p class="wa-popup-text">Sending your payment details on WhatsApp helps us verify &amp; deliver your order faster.</p>
    <div class="wa-popup-actions">
      <button type="button" class="btn btn-outline btn-block" id="waPopupCancel">Not now</button>
      <a href="<?= $waLink ?>" target="_blank" rel="noopener" class="btn btn-primary btn-block" id="waPopupOpen">✅ Open WhatsApp</a>
    </div>
  </div>
</div>
<style>
.wa-popup-backdrop{
  position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;
  display:flex;align-items:center;justify-content:center;padding:20px;
  opacity:0;pointer-events:none;transition:opacity .25s ease;
}
.wa-popup-backdrop.show{opacity:1;pointer-events:auto}
.wa-popup-card{
  background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);
  padding:28px;width:100%;max-width:380px;text-align:center;
  transform:translateY(16px) scale(.96);transition:transform .25s ease;
}
.wa-popup-backdrop.show .wa-popup-card{transform:translateY(0) scale(1)}
.wa-popup-icon{font-size:36px;margin-bottom:10px}
.wa-popup-title{font-size:19px;font-weight:700;margin-bottom:8px;font-family:'Space Grotesk',sans-serif}
.wa-popup-text{color:var(--muted2);font-size:13.5px;margin-bottom:22px;line-height:1.5}
.wa-popup-actions{display:flex;gap:10px}
.wa-popup-actions .btn{flex:1}
</style>
<script>
(function(){
  var backdrop = document.getElementById('waPopupBackdrop');
  var cancelBtn = document.getElementById('waPopupCancel');
  var openBtn   = document.getElementById('waPopupOpen');

  function showPopup(){ backdrop.classList.add('show'); }
  function hidePopup(){ backdrop.classList.remove('show'); }

  // Give the user a moment to read the success message before the popup appears.
  setTimeout(showPopup, 2500);

  cancelBtn.addEventListener('click', hidePopup);
  backdrop.addEventListener('click', function(e){ if (e.target === backdrop) hidePopup(); });
  openBtn.addEventListener('click', function(){ setTimeout(hidePopup, 300); });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
