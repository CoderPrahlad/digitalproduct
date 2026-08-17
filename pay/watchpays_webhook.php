<?php
/**
 * WatchPays — Payment Webhook / Callback
 *
 * Field names confirmed from the working kingsclub.club integration:
 *   POST JSON body: { orderNo, merchantOrder, status, amount }
 *   status is the string 'success' when paid.
 * merchantOrder = the order_ref WE sent as merchant_order_no when creating the order.
 * orderNo       = WatchPays' own transaction id (stored for reference).
 *
 * Note: WatchPays does NOT send a signature on this callback (confirmed from
 * the live kingsclub.club handler), so there's no signature to verify here.
 * We rely on: (a) the merchantOrder matching a real pending order in our DB,
 * and (b) an idempotency check (status must still be 'pending') so a replayed
 * callback can't double-credit an order.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
require_once dirname(__DIR__) . '/mail/Mailer.php';

$logFile = dirname(__DIR__) . '/logs/watchpays_webhook.txt';
function wpLog($msg, $logFile) {
    @file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

// ---- Browser return (GET): just send the customer back into the site ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ref = clean($_GET['merchantOrder'] ?? $_GET['ref'] ?? '');
    if ($ref) {
        redirect(SITE_URL . '/success.php?ref=' . urlencode($ref));
    }
    redirect(SITE_URL . '/dashboard.php');
}

// ---- Server-to-server callback (POST) ----
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);
wpLog('Incoming webhook: ' . $jsonInput, $logFile);

header('Content-Type: application/json');

if (empty($data)) {
    echo json_encode(['status' => 'acknowledged']);
    exit;
}

$orderNo       = clean($data['orderNo'] ?? '');
$merchantOrder = clean($data['merchantOrder'] ?? '');
$status        = $data['status'] ?? '';
$amount        = (float)($data['amount'] ?? 0);

if ($status !== 'success' || !$merchantOrder) {
    echo json_encode(['status' => 'acknowledged']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_ref=? AND payment_method='watchpays' LIMIT 1");
$stmt->execute([$merchantOrder]);
$order = $stmt->fetch();

if (!$order) {
    wpLog("ERROR: No order found for merchantOrder: $merchantOrder", $logFile);
    echo json_encode(['status' => 'acknowledged']);
    exit;
}

if ($order['status'] !== 'pending') {
    // Already processed — WatchPays may retry the same callback.
    wpLog("SKIP: Already processed for merchantOrder: $merchantOrder", $logFile);
    echo json_encode(['status' => 'acknowledged']);
    exit;
}

// Atomic guard: only proceeds if still 'pending' at the moment of update,
// so two near-simultaneous retries can't both pass the check above and double-deliver.
$claim = $pdo->prepare("UPDATE orders SET status='paid', razorpay_payment_id=? WHERE id=? AND status='pending'");
$claim->execute([$orderNo, $order['id']]);
if ($claim->rowCount() === 0) {
    wpLog("SKIP: Race-lost, already claimed for merchantOrder: $merchantOrder", $logFile);
    echo json_encode(['status' => 'acknowledged']);
    exit;
}
creditVendorEarning($pdo, $order['id']);

// ---- Generate license/download token + deliver (same pattern as verify_payment.php) ----
$product = $pdo->prepare("SELECT * FROM products WHERE id=? LIMIT 1");
$product->execute([$order['product_id']]);
$product = $product->fetch();

$user = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$user->execute([$order['user_id']]);
$user = $user->fetch();

if (!$product || !$user) {
    wpLog("ERROR: order data incomplete for merchantOrder: $merchantOrder", $logFile);
    echo json_encode(['status' => 'acknowledged']);
    exit;
}

$licenseKey  = generateLicenseKey();
$dlToken     = bin2hex(random_bytes(32));
$tokenExpiry = date('Y-m-d H:i:s', strtotime('+72 hours'));

$pdo->prepare("UPDATE orders SET license_key=?, download_token=?, token_expires=? WHERE id=?")
    ->execute([$licenseKey, $dlToken, $tokenExpiry, $order['id']]);

// Apply coupon usage if this order was placed with one (stashed in utr_number as COUPON_<id>)
if (!empty($order['utr_number']) && strpos($order['utr_number'], 'COUPON_') === 0) {
    $couponId = (int)substr($order['utr_number'], 7);
    if ($couponId) {
        $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id=?")->execute([$couponId]);
    }
    $pdo->prepare("UPDATE orders SET utr_number=NULL WHERE id=?")->execute([$order['id']]);
}

creditReferralCommission($pdo, $order['id']);

$downloadUrl = SITE_URL . '/download.php?token=' . $dlToken . '&ref=' . $order['order_ref'];
$emailSent = Mailer::sendDelivery($user['email'], $user['name'], $product['title'], $licenseKey, $downloadUrl);
if (!$emailSent) { $emailSent = Mailer::sendDelivery($user['email'], $user['name'], $product['title'], $licenseKey, $downloadUrl); }
$pdo->prepare("UPDATE orders SET email_sent=? WHERE id=?")->execute([$emailSent ? 1 : 0, $order['id']]);
if (!$emailSent) {
    error_log("watchpays_webhook: delivery email FAILED for order {$order['order_ref']} to {$user['email']}");
    notifyTelegram("⚠️ Delivery email FAILED (WatchPays)\nRef: {$order['order_ref']}\n👤 {$user['name']} ({$user['email']})");
}
notifyTelegram("🎉 New Order Paid! (WatchPays)\n<b>{$product['title']}</b>\n👤 {$user['name']} ({$user['email']})\n💰 ₹" . number_format($order['amount']) . "\n🔑 Ref: {$order['order_ref']}");

wpLog("SUCCESS: order $merchantOrder delivered to UID {$order['user_id']} | orderNo: $orderNo", $logFile);
echo json_encode(['status' => 'acknowledged']);
