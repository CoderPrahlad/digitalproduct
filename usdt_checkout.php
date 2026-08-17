<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();

$ref  = clean($_GET['ref'] ?? '');
$stmt = $pdo->prepare("SELECT o.*, p.title FROM orders o JOIN products p ON p.id=o.product_id WHERE o.order_ref=? AND o.user_id=? AND o.payment_method='usdt' LIMIT 1");
$stmt->execute([$ref, $_SESSION['user_id']]);
$order = $stmt->fetch();
if (!$order) { redirect(SITE_URL . '/dashboard.php'); }

// Already paid/delivered — no need to show the payment screen again.
if (in_array($order['status'], ['paid','delivered'], true)) {
    redirect(SITE_URL . '/success.php?ref=' . urlencode($order['order_ref']));
}

$userStmt = $pdo->prepare("SELECT email FROM users WHERE id=? LIMIT 1");
$userStmt->execute([$_SESSION['user_id']]);
$userEmail = $userStmt->fetchColumn() ?: '';

$secondsLeft = 420 - (time() - strtotime($order['created_at'])); // 7 minutes
if ($secondsLeft < 0) $secondsLeft = 0;

$address = $order['crypto_address'] ?? null;
$network = $order['crypto_network'] ?? null;
$gwName  = $order['crypto_gateway_name'] ?? null;
if (!$address) { $address = defined('_TRC20_ADDRESS') ? _TRC20_ADDRESS : ''; }
if (!$network) { $network = 'TRC20 (Tron)'; }
if (!$gwName)  { $gwName  = ''; }
$qrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($address);

$pageTitle = 'Pay with ';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:900px">

  <div id="TimerBox" style="background:linear-gradient(135deg,rgba(234,179,8,.12),rgba(234,179,8,.04));border:1px solid rgba(234,179,8,.35);border-radius:var(--radius-sm);padding:18px;text-align:center;margin-bottom:20px">
    <div style="font-family:'Space Grotesk',sans-serif;font-size:34px;font-weight:700;color:#eab308" id="TimerValue">--:--</div>
    <div style="color:var(--muted2);font-size:13px;margin-top:4px">COMPLETE PAYMENT WITHIN 7 MINUTES</div>
    <div style="color:var(--muted);font-size:12px;margin-top:6px">ℹ️ Orders expire after 7 minutes to ensure fair pricing for all customers</div>
  </div>

  <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:20px;margin-bottom:20px;display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div>
      <div style="color:var(--muted);font-size:12px">Order ID</div>
      <strong><?= clean($order['order_ref']) ?></strong>
    </div>
    <div>
      <div style="color:var(--muted);font-size:12px">Email</div>
      <strong><?= clean($userEmail) ?></strong>
    </div>
    <div>
      <div style="color:var(--muted);font-size:12px">Amount</div>
      <strong style="color:#22c55e"> $<?= number_format((float)$order['crypto_amount'], 2) ?></strong>
    </div>
    <div>
      <div style="color:var(--muted);font-size:12px">Status</div>
      <strong style="color:#eab308">Pending Payment</strong>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="-grid">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:20px;text-align:center">
      <div style="display:inline-block;background:rgba(234,179,8,.12);border:1px solid rgba(234,179,8,.35);color:#eab308;font-size:12px;padding:5px 14px;border-radius:20px;margin-bottom:14px">🛡️ SEND ONLY <?= clean($gwName) ?></div>
      <p style="font-weight:600;margin-bottom:14px">Scan &amp; Pay</p>
      <img src="<?= clean($qrUrl) ?>" alt="<?= clean($gwName) ?> QR" style="background:#fff;padding:10px;border-radius:10px;width:200px;height:200px">
      <p style="color:var(--muted);font-size:12px;margin:14px 0 4px">Or Send to Address:</p>
      <div style="display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px 12px">
        <code id="Addr" style="flex:1;text-align:left;word-break:break-all;color:#22c55e;font-size:12.5px"><?= clean($address) ?></code>
        <button type="button" class="btn btn-primary" style="padding:6px 12px;font-size:12px" onclick="copyAddr()">Copy</button>
      </div>
      <p style="color:var(--muted);font-size:11px;margin-top:6px">Network: <?= clean($network) ?> · <?= clean($gwName) ?></p>
      <div style="margin-top:14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px;font-weight:700;color:#22c55e">
        $  <?= number_format((float)$order['crypto_amount'], 2) ?> <span style="color:var(--muted);font-weight:400;font-size:11px">Exact Amount</span>
      </div>
    </div>

    <div>
      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:20px;margin-bottom:16px">
        <p style="font-weight:600;margin-bottom:12px">Submit TXID</p>
        <form method="POST" action="<?= SITE_URL ?>/api/submit_usdt.php" id="Form">
          <?= csrf_field() ?>
          <input type="hidden" name="order_ref" value="<?= clean($order['order_ref']) ?>">
          <div class="form-group">
            <label style="font-size:12px;color:var(--muted)">TXID / Transaction Hash</label>
            <input class="form-control" type="text" name="txid" required placeholder="Enter your  transaction hash (TXID)">
            <p style="color:var(--muted);font-size:11px;margin-top:4px">💡 TXID wallet / exchange history me milta hai</p>
          </div>
          <button type="submit" class="btn btn-primary btn-block" style="padding:14px">📨 Submit &amp; Confirm Payment</button>
          <p style="text-align:center;color:var(--muted);font-size:11px;margin-top:8px">After submission, you'll be redirected to WhatsApp for confirmation</p>
        </form>
      </div>

      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:18px">
        <p style="font-weight:600;margin-bottom:10px">ℹ️ Important Instructions</p>
        <p style="font-size:13px;color:#22c55e;margin-bottom:6px">✅ Pay exact amount shown above</p>
        <p style="font-size:13px;color:#22c55e;margin-bottom:6px">✅ Select same network: <?= clean($network) ?></p>
        <p style="font-size:13px;color:#ef4444;margin-bottom:10px">⏱ 7-Minute Time Limit: Complete payment quickly</p>
        <div style="background:rgba(234,179,8,.1);border:1px solid rgba(234,179,8,.3);border-radius:8px;padding:10px;font-size:12px;color:#eab308">
          ⚠️ Note: Wrong network payment loss ho sakta hai. Network verify karke pay karein.
        </div>
      </div>
    </div>
  </div>

  <p style="text-align:center;margin-top:20px"><a href="<?= SITE_URL ?>/checkout.php?product_id=<?= (int)$order['product_id'] ?>" style="color:var(--muted2);font-size:13px">← Pay with INR instead</a></p>
</div>

<style>
@media (max-width: 700px) { .-grid { grid-template-columns: 1fr !important; } }
</style>

<script>
function copyAddr() {
  var text = document.getElementById('Addr').textContent.trim();
  var btn = event.target;
  var old = btn.textContent;
  function done(){ btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = old; }, 1500); }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(done).catch(function(){ fallbackCopy(text); done(); });
  } else { fallbackCopy(text); done(); }
}
function fallbackCopy(text) {
  var ta = document.createElement('textarea');
  ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
  document.body.appendChild(ta); ta.focus(); ta.select();
  try { document.execCommand('copy'); } catch(e) {}
  document.body.removeChild(ta);
}
(function() {
  var seconds = <?= (int)$secondsLeft ?>;
  var valueEl = document.getElementById('TimerValue');
  var boxEl   = document.getElementById('TimerBox');
  function render() {
    if (seconds <= 0) {
      valueEl.textContent = '00:00';
      return;
    }
    var m = String(Math.floor(seconds / 60)).padStart(2, '0');
    var s = String(seconds % 60).padStart(2, '0');
    valueEl.textContent = m + ':' + s;
  }
  render();
  var iv = setInterval(function() {
    seconds--;
    if (seconds <= 0) {
      clearInterval(iv);
      render();
      alert('⏱ Payment time expired. If you already paid, submit your TXID anyway — we will still verify it manually.');
      return;
    }
    render();
    if (seconds <= 60) boxEl.style.borderColor = '#ef4444';
  }, 1000);
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
