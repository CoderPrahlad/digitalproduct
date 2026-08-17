<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();

$product_id = (int)($_GET['product_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM products WHERE id=? AND status='active' LIMIT 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) { flash('error','Product not found.'); redirect(SITE_URL.'/'); }

$amount = $product['discount_price'] ?: $product['price'];
$amountPaise = (int)($amount * 100); // Razorpay needs paise

$paymentMode = getSetting($pdo, 'payment_mode', 'both'); // both | automatic | manual

// Active automatic gateway (whichever one is enabled in Admin -> Gateways; falls back to config.php)
$autoGateway = activeAutoGateway($pdo);
$manualGateways = activeManualGateways($pdo);
$watchpaysGateway = ($paymentMode !== 'manual') ? activeWatchPaysGateway($pdo) : null;
$cryptoGateways = activeCryptoGateways($pdo);

// Create order via the active gateway's API (skip entirely if admin set Manual Only, or no gateway enabled)
$razorpayOrderId = null;
$gatewayKeyId    = null;
if ($paymentMode !== 'manual' && $autoGateway && !empty($autoGateway['key_id']) && strpos($autoGateway['key_id'],'XXXX') === false) {
    $gatewayKeyId = $autoGateway['key_id'];
    $data = json_encode(['amount'=>$amountPaise,'currency'=>'INR','receipt'=>generateOrderRef()]);
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_USERPWD        => $autoGateway['key_id'].':'.$autoGateway['key_secret'],
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $resp = curl_exec($ch); curl_close($ch);
    $rzpOrder = json_decode($resp, true);
    $razorpayOrderId = $rzpOrder['id'] ?? null;
}

$user = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

$pageTitle = 'Checkout — '.$product['title'];
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">

  <!-- Back button -->
  <div class="reveal" style="margin-top:5px;margin-bottom:7px">
    <a href="<?= SITE_URL ?>/product.php?slug=<?= urlencode($product['slug']) ?>"
       style="display:inline-flex;align-items:center;gap:6px;color:var(--muted2);font-size:14px;text-decoration:none;border:1px solid var(--border);padding:8px 14px;border-radius:var(--radius-sm);background:var(--bg-card);transition:.2s"
       onmouseover="this.style.color='var(--text)';this.style.borderColor='var(--primary)'"
       onmouseout="this.style.color='var(--muted2)';this.style.borderColor='var(--border)'">
      ← Back
    </a>
  </div>

<div class="checkout-wrap">

  <!-- Summary -->
  <div class="checkout-summary reveal">
    <h3>Order Summary</h3>
    <?php if ($product['image']): ?>
      <img src="<?= SITE_URL ?>/uploads/products/<?= clean($product['image']) ?>" style="width:100%;border-radius:10px;margin-bottom:16px;border:1px solid var(--border)">
    <?php endif; ?>
    <div class="summary-row"><span><?= clean($product['title']) ?></span></div>
    <?php if ($product['discount_price']): ?>
    <div class="summary-row"><span>Original Price</span><span style="text-decoration:line-through;color:var(--muted)">₹<?= number_format($product['price']) ?></span></div>
    <div class="summary-row"><span>Discount</span><span style="color:var(--success)">-₹<?= number_format($product['price']-$product['discount_price']) ?></span></div>
    <?php endif; ?>
    <div class="summary-row"><strong>Total</strong><strong style="color:#a78bfa;font-size:22px" id="priceDisplay">₹<?= number_format($amount) ?></strong></div>

    <!-- COUPON CODE -->
    <div style="margin-top:18px">
      <div style="display:flex;gap:8px">
        <input type="text" id="couponInput" placeholder="Coupon code (e.g. SAVE20)" style="flex:1;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:9px 12px;color:var(--text);font-size:13px" oninput="this.value=this.value.toUpperCase()">
        <button type="button" onclick="applyCoupon()" class="btn btn-outline btn-sm" style="white-space:nowrap">Apply</button>
      </div>
      <div id="couponMsg" style="font-size:12px;margin-top:6px"></div>
    </div>

    <div style="margin-top:20px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius-sm);padding:14px;font-size:13px;color:#34d399">
      ⚡ <strong>Instant Delivery</strong><br>
      Download link + license key will be sent to <strong><?= clean($user['email']) ?></strong> immediately after payment.
    </div>
  </div>

  <!-- Payment -->
  <div class="checkout-form reveal">
    <h3 style="margin-bottom:20px">Payment</h3>

    <div id="inrGatewaysWrap" class="no-currency-toggle">

    <!-- Payment-method picker — shown only when the person switches back to
         ₹ INR from the USDT screen, so they explicitly choose Automatic vs Manual -->
    <div id="inrGatewayPicker" style="display:none;margin-bottom:20px">
      <p style="text-align:center;color:var(--muted2);font-size:13px;margin-bottom:14px">How would you like to pay in ₹ INR?</p>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <?php if ($razorpayOrderId || $watchpaysGateway): ?>
        <button type="button" class="btn btn-primary" style="flex:1;min-width:160px;padding:16px" onclick="dsChooseInrOption('automatic')">🔐 Automatic Gateway</button>
        <?php endif; ?>
        <?php if ($paymentMode !== 'automatic' && !empty($manualGateways)): ?>
        <button type="button" class="btn btn-outline" style="flex:1;min-width:160px;padding:16px" onclick="dsChooseInrOption('manual')">🏦 Manual UPI Transfer</button>
        <?php endif; ?>
      </div>
    </div>

    <div id="inrAutoBlock">
    <?php if ($razorpayOrderId): ?>
    <!-- RAZORPAY AUTOMATIC -->
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:20px;margin-bottom:20px">
      <p style="font-size:13px;color:var(--muted2);margin-bottom:16px">Pay securely via Razorpay — UPI, Cards, Net Banking, Wallets accepted.</p>
      <button id="rzp-btn" class="btn btn-primary btn-block" style="padding:16px;font-size:16px" onclick="payNow()">
        🔐 Pay <span id="rzpAmountText">₹<?= number_format($amount) ?></span> — Secure Checkout
      </button>
      <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:10px">🔒 256-bit encrypted | Powered by Razorpay</p>
    </div>
    <?php endif; ?>

    <!-- WATCHPAYS AUTOMATIC -->
    <?php if ($watchpaysGateway): ?>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:20px;margin-bottom:20px">
      <p style="font-size:13px;color:var(--muted2);margin-bottom:16px">Pay securely via WatchPays.</p>
      <button id="wp-btn" class="btn btn-primary btn-block" style="padding:16px;font-size:16px" onclick="payViaWatchPays()">
        🔐 Pay <span id="wpAmountText">₹<?= number_format($amount) ?></span> via WatchPays
      </button>
      <p id="wpMsg" style="text-align:center;font-size:12px;color:var(--danger);margin-top:8px"></p>
    </div>
    <?php endif; ?>
    </div><!-- /inrAutoBlock -->

    <?php if (($razorpayOrderId || $watchpaysGateway) && $paymentMode !== 'automatic' && !empty($manualGateways)): ?>
    <div class="divider" id="inrManualDivider">or pay via UPI manually</div>
    <?php endif; ?>

    <!-- MANUAL UPI FALLBACK -->
    <?php if ($paymentMode !== 'automatic' && !empty($manualGateways)): ?>
    <div id="inrManualBlock">
    <div id="upi-section">
      <?php if (count($manualGateways) > 1): ?>
      <div class="upi-gateway-tabs" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
        <?php foreach ($manualGateways as $i => $mg): ?>
          <button type="button" class="btn btn-sm upi-gw-tab <?= $i===0 ? 'btn-primary' : 'btn-outline' ?>" data-gw="<?= $i ?>" onclick="selectUpiGateway(<?= $i ?>)"><?= clean($mg['name']) ?></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php foreach ($manualGateways as $i => $mg):
        $upiUri = 'upi://pay?pa='.urlencode($mg['upi_id']).'&pn='.urlencode($mg['upi_name'] ?: SITE_NAME).'&am='.$amount.'&cu=INR&tn='.urlencode('Order '.$product['title']);
        $upiQrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='.urlencode($upiUri);
      ?>
      <div class="upi-box upi-gw-panel" data-gw="<?= $i ?>" data-upi-pa="<?= clean($mg['upi_id']) ?>" data-upi-pn="<?= clean($mg['upi_name'] ?: SITE_NAME) ?>" data-upi-tn="<?= clean('Order '.$product['title']) ?>" style="<?= $i===0 ? '' : 'display:none' ?>">
        <p class="upi-send-label">Send <strong style="color:#a78bfa">₹<span class="upi-amount-text"><?= number_format($amount) ?></span></strong> to this UPI ID</p>
        <div class="upi-id-pill">
          <div class="upi-id upi-id-text"><?= clean($mg['upi_id']) ?></div>
          <button type="button" class="upi-copy-btn" onclick="copyUpiId(this)">📋 Copy</button>
        </div>
        <p class="upi-name-text">Name: <?= clean($mg['upi_name'] ?: SITE_NAME) ?></p>
        <div class="upi-qr-card"><img class="upi-qr-img" src="<?= $upiQrSrc ?>" alt="Scan to pay via UPI"></div>
        <p class="upi-scan-hint">📷 Scan with any UPI app to auto-fill payment</p>

        <div class="upi-app-divider">on this phone</div>
        <button type="button" class="upi-app-btn" onclick="dsOpenUpiApp(this)">
          📲 Pay ₹<span class="upi-amount-text"><?= number_format($amount) ?></span> with UPI App
        </button>
      </div>
      <?php endforeach; ?>
      <div class="checkout-timer" id="checkoutTimer">
        ⏱ Complete payment within <span id="checkoutTimerValue">05:00</span> or this order will be auto-cancelled
      </div>
      <form method="POST" action="<?= SITE_URL ?>/api/submit_manual.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= $product_id ?>">
        <input type="hidden" name="coupon_id" id="manualCouponId" value="">
        <div class="form-group">
          <label>UTR / Transaction Reference Number *</label>
          <input class="form-control" type="text" name="utr_number" placeholder="12-digit UTR number" required>
          <p class="form-hint">Payment app → Transaction details → UTR Number</p>
        </div>
        <div class="form-group">
          <label>Payment Screenshot (optional)</label>
          <div class="file-upload-wrap">
            <input class="file-upload-input" type="file" id="proofInput" name="proof" accept=".jpg,.jpeg,.png" onchange="dsUpdateFileLabel(this)">
            <label for="proofInput" class="file-upload-btn" id="proofUploadBtn">
              <span class="file-upload-icon">📎</span>
              <span class="file-upload-text" id="proofFileName">Choose payment screenshot</span>
              <span class="file-upload-clear" id="proofClearBtn" onclick="dsClearFileInput(event)">✕</span>
            </label>
          </div>
        </div>
        <button type="submit" class="upi-submit-btn">📨 Submit &amp; Confirm Payment</button>
        <p style="text-align:center;color:var(--muted);font-size:11px;margin-top:8px">After submission, you'll be redirected to WhatsApp for confirmation</p>
      </form>
    </div>
    </div><!-- /inrManualBlock -->
    <?php elseif (!$razorpayOrderId && !$watchpaysGateway): ?>
    <div class="alert alert-info">Payment gateway is not configured yet. Please contact support to complete this order.</div>
    <?php endif; ?>
    </div><!-- /inrGatewaysWrap -->

    <p id="switchToInrLink" style="display:none;text-align:center;margin:0 0 16px">
      <a href="#" onclick="dsSwitchToInr(); return false;" style="color:var(--muted2);font-size:13px">← Switch back to pay in ₹ INR</a>
    </p>

    <!-- USDT (CRYPTO) MANUAL — only shown when the person is in "pay with USDT"
         mode (via the floating USDT icon). If they came straight through the
         normal ₹ INR flow this stays hidden; dsApplyGatewayVisibility() below
         decides which one is visible on load. Starts hidden to avoid a flash. -->
    <?php if (!empty($cryptoGateways)): ?>
    <div id="usdtGatewayWrap" style="display:none;margin-bottom:20px">
      <?php foreach ($cryptoGateways as $cg): ?>
      <div style="background:var(--bg2);border:1px solid rgba(34,197,94,.3);border-radius:var(--radius-sm);padding:20px;margin-bottom:14px">
        <p style="font-size:13px;color:var(--muted2);margin-bottom:16px">Prefer crypto? Pay with <?= clean($cg['name']) ?><?= $cg['upi_name'] ? ' ('.clean($cg['upi_name']).')' : '' ?> — amount converts automatically at the live rate.</p>
        <form method="POST" action="<?= SITE_URL ?>/api/create_usdt_order.php">
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= $product_id ?>">
          <input type="hidden" name="gateway_id" value="<?= (int)$cg['id'] ?>">
          <input type="hidden" name="coupon_id" class="usdt-coupon-id-field" value="">
          <button type="submit" class="btn btn-outline btn-block" style="padding:16px;font-size:16px" onclick="document.querySelectorAll('.usdt-coupon-id-field').forEach(function(f){ f.value = window.appliedCouponId || ''; });">
            <span class="ds-blink-dot">🟢</span> Pay <span class="usdt-amount-preview"></span>with <?= clean($cg['name']) ?>
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>

<?php if ($razorpayOrderId): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
function setRzpBtnLoading(loading, label) {
  var btn = document.getElementById('rzp-btn');
  if (!btn) return;
  if (!btn.dataset.originalHtml) btn.dataset.originalHtml = btn.innerHTML;
  btn.disabled = loading;
  btn.classList.toggle('btn-loading', !!loading);
  if (loading) btn.innerHTML = '<span class="btn-spinner"></span>' + (label || 'Opening payment...');
  else btn.innerHTML = label || btn.dataset.originalHtml;
}

function payNow() {
  setRzpBtnLoading(true, 'Opening payment...');
  var options = {
    key: '<?= clean($gatewayKeyId ?? '') ?>',
    amount: <?= $amountPaise ?>,
    currency: 'INR',
    name: '<?= addslashes(SITE_NAME) ?>',
    description: '<?= addslashes($product['title']) ?>',
    order_id: '<?= $razorpayOrderId ?>',
    prefill: { name: '<?= addslashes($user['name']) ?>', email: '<?= addslashes($user['email']) ?>', contact: '<?= addslashes($user['phone'] ?? '') ?>' },
    theme: { color: '#7c3aed' },
    handler: function(response) {
      // Auto verify & deliver
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '<?= SITE_URL ?>/api/verify_payment.php';
      ['razorpay_payment_id','razorpay_order_id','razorpay_signature'].forEach(function(k) {
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = k; i.value = response[k];
        form.appendChild(i);
      });
      var pi = document.createElement('input');
      pi.type='hidden'; pi.name='product_id'; pi.value='<?= $product_id ?>';
      form.appendChild(pi);
      // Include coupon if applied
      if (window.appliedCouponId) {
        var ci = document.createElement('input');
        ci.type='hidden'; ci.name='coupon_id'; ci.value=window.appliedCouponId;
        form.appendChild(ci);
      }
      var ct = document.createElement('input');
      ct.type='hidden'; ct.name='csrf_token'; ct.value='<?= csrf_token() ?>';
      form.appendChild(ct);
      document.body.appendChild(form);
      form.submit();
    },
    modal: { ondismiss: function() {
      setRzpBtnLoading(false, '🔐 Pay ₹<?= number_format($amount) ?> — Secure Checkout');
    }}
  };
  var rzp = new Razorpay(options);
  rzp.open();
}
</script>
<?php endif; ?>


<script>
window.appliedCouponId = null;
var baseAmount = <?= $amount ?>;

function payViaWatchPays() {
  var btn = document.getElementById('wp-btn');
  var msgEl = document.getElementById('wpMsg');
  msgEl.textContent = '';
  btn.disabled = true;
  btn.textContent = 'Starting payment…';

  var fd = new FormData();
  fd.append('product_id', '<?= $product_id ?>');
  fd.append('coupon_id', window.appliedCouponId || '');

  fetch('<?= SITE_URL ?>/api/create_watchpays_order.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.ok && d.payment_url) {
        window.location.href = d.payment_url;
      } else {
        msgEl.textContent = d.msg || 'Could not start WatchPays payment.';
        btn.disabled = false;
        btn.innerHTML = '🔐 Pay <span id="wpAmountText">₹' + numberFormat(window.dsCurrentInrAmount || <?= $amount ?>) + '</span> via WatchPays';
      }
    })
    .catch(function() {
      msgEl.textContent = 'Network error. Please try again.';
      btn.disabled = false;
      btn.innerHTML = '🔐 Pay <span id="wpAmountText">₹' + numberFormat(window.dsCurrentInrAmount || <?= $amount ?>) + '</span> via WatchPays';
    });
}

function setTotalDisplay(amount) {
  var el = document.getElementById('priceDisplay');
  if (!el) return;
  if (window.dsGetCurrencyMode && window.dsGetCurrencyMode() === 'usdt' && window.dsUsdtRate) {
    el.textContent = (amount / window.dsUsdtRate).toFixed(2) + ' USDT';
  } else {
    el.textContent = '₹' + numberFormat(amount);
  }
}

function applyCoupon() {
  var code = document.getElementById('couponInput').value.trim();
  var msgEl = document.getElementById('couponMsg');
  if (!code) { msgEl.innerHTML = '<span style="color:var(--danger)">Please enter a coupon code.</span>'; return; }
  msgEl.innerHTML = '<span style="color:var(--muted)">Checking...</span>';
  var fd = new FormData();
  fd.append('code', code);
  fd.append('product_id', '<?= $product_id ?>');
  fetch('<?= SITE_URL ?>/api/validate_coupon.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        window.appliedCouponId = data.coupon_id;
        window.appliedDiscount = data.discount;
        setTotalDisplay(data.final_amount);
        msgEl.innerHTML = '<span style="color:var(--success)">✅ ' + data.msg + '</span>';
        // Update rzp amount if available
        if (window.appliedFinalAmount !== undefined) window.appliedFinalAmount = data.final_amount;
        // Store for form submission
        window.couponFinalAmount = Math.round(data.final_amount * 100);
        // Sync manual UPI box (amount text, QR code, deep-link) + hidden form field
        updateUpiBox(data.final_amount, data.coupon_id);
        syncGatewayAmounts(data.final_amount);
      } else {
        window.appliedCouponId = null;
        setTotalDisplay(baseAmount);
        msgEl.innerHTML = '<span style="color:var(--danger)">❌ ' + data.msg + '</span>';
        window.couponFinalAmount = null;
        updateUpiBox(baseAmount, '');
        syncGatewayAmounts(baseAmount);
      }
    })
    .catch(() => { msgEl.innerHTML = '<span style="color:var(--danger)">Error checking coupon.</span>'; });
}

function numberFormat(n) {
  return Math.round(n).toLocaleString('en-IN');
}

function syncGatewayAmounts(finalAmount) {
  var rzpEl = document.getElementById('rzpAmountText');
  if (rzpEl) rzpEl.textContent = '₹' + numberFormat(finalAmount);
  var wpEl = document.getElementById('wpAmountText');
  if (wpEl) wpEl.textContent = '₹' + numberFormat(finalAmount);
  window.dsCurrentInrAmount = finalAmount;
  refreshUsdtPreview();
}

function refreshUsdtPreview() {
  var els = document.querySelectorAll('.usdt-amount-preview');
  if (!els.length) return;
  var amt = window.dsCurrentInrAmount || baseAmount;
  var rate = window.dsUsdtRate;
  var text = rate ? (amt / rate).toFixed(2) + ' USDT ' : '';
  els.forEach(function(el) { el.textContent = text; });
}

function updateUpiBox(amount, couponId) {
  var roundedAmt = Math.round(amount);
  document.querySelectorAll('.upi-amount-text').forEach(function(el) {
    el.textContent = numberFormat(amount);
  });
  document.querySelectorAll('.upi-gw-panel').forEach(function(panel) {
    var pa = panel.dataset.upiPa, pn = panel.dataset.upiPn, tn = panel.dataset.upiTn;
    var upiUri = 'upi://pay?pa=' + encodeURIComponent(pa) + '&pn=' + encodeURIComponent(pn) + '&am=' + roundedAmt + '&cu=INR&tn=' + encodeURIComponent(tn);
    var img = panel.querySelector('.upi-qr-img');
    if (img) img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(upiUri);
  });
  var couponField = document.getElementById('manualCouponId');
  if (couponField) couponField.value = couponId || '';
}

// Override payNow to use coupon amount if applied
var _origPayNow = window.payNow;
window.payNow = function() {
  if (window.couponFinalAmount && window.couponFinalAmount < <?= $amountPaise ?>) {
    // Re-call with coupon amount via AJAX create order, for now just alert user about new amount
    setRzpBtnLoading(true, 'Processing...');
    var fd = new FormData();
    fd.append('product_id', '<?= $product_id ?>');
    fd.append('coupon_id', window.appliedCouponId);
    fetch('<?= SITE_URL ?>/api/create_razorpay_order.php', { method:'POST', body:fd })
      .then(r => r.json())
      .then(d => {
        if (!d.order_id) { alert('Could not create order. Please try again.'); setRzpBtnLoading(false, '🔐 Pay'); return; }
        openRazorpay(d.order_id, window.couponFinalAmount, window.appliedCouponId);
      })
      .catch(() => { setRzpBtnLoading(false); _origPayNow(); });
  } else {
    _origPayNow && _origPayNow();
  }
};

function openRazorpay(orderId, amtPaise, couponId) {
  var options = {
    key: '<?= clean($gatewayKeyId ?? '') ?>',
    amount: amtPaise,
    currency: 'INR',
    name: '<?= addslashes(SITE_NAME) ?>',
    description: '<?= addslashes($product['title']) ?>',
    order_id: orderId,
    prefill: { name: '<?= addslashes($user['name']) ?>', email: '<?= addslashes($user['email']) ?>', contact: '<?= addslashes($user['phone'] ?? '') ?>' },
    theme: { color: '#7c3aed' },
    handler: function(response) {
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '<?= SITE_URL ?>/api/verify_payment.php';
      ['razorpay_payment_id','razorpay_order_id','razorpay_signature'].forEach(function(k) {
        var i = document.createElement('input'); i.type='hidden'; i.name=k; i.value=response[k]; form.appendChild(i);
      });
      [['product_id','<?= $product_id ?>'],['coupon_id',couponId],['csrf_token','<?= csrf_token() ?>']].forEach(function(p){
        var i = document.createElement('input'); i.type='hidden'; i.name=p[0]; i.value=p[1]; form.appendChild(i);
      });
      document.body.appendChild(form); form.submit();
    },
    modal: { ondismiss: function() { setRzpBtnLoading(false, '🔐 Pay ₹<?= number_format($amount) ?> — Secure Checkout'); } }
  };
  new Razorpay(options).open();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
function copyUpiId(btn) {
  var text = btn.parentElement.querySelector('.upi-id-text').textContent.trim();
  function done() {
    var old = btn.textContent;
    btn.textContent = '✅ Copied';
    setTimeout(function(){ btn.textContent = old; }, 1500);
  }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(done).catch(function(){
      fallbackCopy(text); done();
    });
  } else {
    fallbackCopy(text); done();
  }
}
function fallbackCopy(text) {
  var ta = document.createElement('textarea');
  ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
  document.body.appendChild(ta); ta.focus(); ta.select();
  try { document.execCommand('copy'); } catch(e) {}
  document.body.removeChild(ta);
}
function selectUpiGateway(idx) {
  document.querySelectorAll('.upi-gw-panel').forEach(function(p){ p.style.display = (p.dataset.gw == idx) ? '' : 'none'; });
  document.querySelectorAll('.upi-gw-tab').forEach(function(t){
    t.classList.toggle('btn-primary', t.dataset.gw == idx);
    t.classList.toggle('btn-outline', t.dataset.gw != idx);
  });
}

// Opens the phone's installed UPI apps (GPay/PhonePe/Paytm/etc) via the
// standard upi://pay deep link, with UPI ID, payee name and the exact
// current amount (post-coupon if applied) pre-filled — user just confirms.
function dsOpenUpiApp(btn) {
  var panel = btn.closest('.upi-gw-panel');
  if (!panel) return;
  var pa = panel.dataset.upiPa, pn = panel.dataset.upiPn, tn = panel.dataset.upiTn;
  var amtEl = panel.querySelector('.upi-amount-text');
  var amt = amtEl ? amtEl.textContent.replace(/,/g, '') : '';
  var link = 'upi://pay?pa=' + encodeURIComponent(pa) + '&pn=' + encodeURIComponent(pn) +
             '&am=' + encodeURIComponent(amt) + '&cu=INR&tn=' + encodeURIComponent(tn);
  window.location.href = link;
}

function dsUpdateFileLabel(input) {
  var label = document.getElementById('proofFileName');
  var btn = document.getElementById('proofUploadBtn');
  if (!label) return;
  if (input.files && input.files[0]) {
    label.textContent = input.files[0].name;
    if (btn) btn.classList.add('has-file');
  } else {
    label.textContent = 'Choose payment screenshot';
    if (btn) btn.classList.remove('has-file');
  }
}

function dsClearFileInput(e) {
  e.preventDefault();
  e.stopPropagation();
  var input = document.getElementById('proofInput');
  if (input) { input.value = ''; dsUpdateFileLabel(input); }
}
</script>
<script>
(function(){
  var seconds = 5 * 60;
  var valueEl = document.getElementById('checkoutTimerValue');
  var boxEl   = document.getElementById('checkoutTimer');
  if (!valueEl) return;
  var iv = setInterval(function(){
    seconds--;
    if (seconds <= 0) {
      clearInterval(iv);
      valueEl.textContent = '00:00';
      alert('⏱ Payment time expired. This order has been auto-cancelled.');
      window.location.href = '<?= SITE_URL ?>/';
      return;
    }
    var m = String(Math.floor(seconds / 60)).padStart(2, '0');
    var s = String(seconds % 60).padStart(2, '0');
    valueEl.textContent = m + ':' + s;
    if (seconds <= 60 && boxEl) boxEl.classList.add('danger');
  }, 1000);
})();
</script>

<script>
// ---- USDT display-mode handling ----
// If the person turned on "Show prices in USDT" (the floating fab icon) on an
// earlier page, honor that here too: hide the INR-only gateways (Razorpay /
// WatchPays / Manual UPI) and show only the USDT payment method, since those
// gateways can't actually charge in USDT.
window.dsCurrentInrAmount = <?= $amount ?>;

function dsApplyGatewayVisibility() {
  var mode = window.dsGetCurrencyMode ? window.dsGetCurrencyMode() : 'inr';
  var inrWrap    = document.getElementById('inrGatewaysWrap');
  var switchLink = document.getElementById('switchToInrLink');
  var usdtWrap   = document.getElementById('usdtGatewayWrap');
  var picker     = document.getElementById('inrGatewayPicker');
  var autoBlock  = document.getElementById('inrAutoBlock');
  var manualBlock = document.getElementById('inrManualBlock');
  var manualDivider = document.getElementById('inrManualDivider');

  if (mode === 'usdt') {
    // USDT mode: hide all ₹ INR gateways, show only the USDT box.
    if (inrWrap) inrWrap.style.display = 'none';
    if (switchLink) switchLink.style.display = 'block';
    if (usdtWrap) usdtWrap.style.display = '';
    return;
  }

  // ₹ INR mode: USDT box + switch-back link stay hidden.
  if (switchLink) switchLink.style.display = 'none';
  if (usdtWrap) usdtWrap.style.display = 'none';
  if (inrWrap) inrWrap.style.display = '';

  var showPicker = false;
  try { showPicker = sessionStorage.getItem('ds_show_payment_picker') === '1'; } catch (e) {}

  if (showPicker) {
    // Came here via "Switch back to pay in ₹ INR" — let them explicitly
    // choose Automatic Gateway vs Manual UPI instead of dumping both at once.
    try { sessionStorage.removeItem('ds_show_payment_picker'); } catch (e) {}
    if (picker) picker.style.display = '';
    if (autoBlock) autoBlock.style.display = 'none';
    if (manualBlock) manualBlock.style.display = 'none';
    if (manualDivider) manualDivider.style.display = 'none';
  } else {
    // Normal first-time entry into checkout — show everything as usual.
    if (picker) picker.style.display = 'none';
    if (autoBlock) autoBlock.style.display = '';
    if (manualBlock) manualBlock.style.display = '';
    if (manualDivider) manualDivider.style.display = '';
  }
}

function dsChooseInrOption(choice) {
  var picker = document.getElementById('inrGatewayPicker');
  var autoBlock = document.getElementById('inrAutoBlock');
  var manualBlock = document.getElementById('inrManualBlock');
  var manualDivider = document.getElementById('inrManualDivider');
  if (picker) picker.style.display = 'none';
  if (autoBlock) autoBlock.style.display = (choice === 'automatic') ? '' : 'none';
  if (manualBlock) manualBlock.style.display = (choice === 'manual') ? '' : 'none';
  if (manualDivider) manualDivider.style.display = 'none';
}

function dsSwitchToInr() {
  try {
    sessionStorage.setItem('ds_currency_mode', 'inr');
    sessionStorage.setItem('ds_show_payment_picker', '1');
  } catch (e) {}
  window.location.reload();
}

fetch((window.SITE_URL || '<?= SITE_URL ?>') + '/api/get_usdt_rate.php')
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d && d.rate) window.dsUsdtRate = d.rate;
    refreshUsdtPreview();
  })
  .catch(function() {});

dsApplyGatewayVisibility();
</script>