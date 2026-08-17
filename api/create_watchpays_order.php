<?php
/**
 * WatchPays — Create Payment Link
 * Product/coupon -> pending order in DB -> WatchPays create-order API -> payment_url
 * Flow is redirect-based (not a popup like Razorpay): the browser is sent
 * to WatchPays' hosted payment page, and WatchPays calls our webhook
 * (pay/watchpays_webhook.php) once the payment is completed.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['ok'=>false,'msg'=>'Not logged in']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); exit; }

$productId = (int)($_POST['product_id'] ?? 0);
$couponId  = (int)($_POST['coupon_id'] ?? 0);

$product = $pdo->prepare("SELECT * FROM products WHERE id=? AND status='active' LIMIT 1");
$product->execute([$productId]);
$product = $product->fetch();
if (!$product) { echo json_encode(['ok'=>false,'msg'=>'Product not found']); exit; }

$amount = $product['discount_price'] ?: $product['price'];

// Apply coupon (same logic as Razorpay flow)
if ($couponId) {
    $coupon = $pdo->prepare("SELECT * FROM coupons WHERE id=? AND active=1 LIMIT 1");
    $coupon->execute([$couponId]);
    $coupon = $coupon->fetch();
    if ($coupon && (!$coupon['expires_at'] || strtotime($coupon['expires_at']) > time()) && (!$coupon['max_uses'] || $coupon['used_count'] < $coupon['max_uses']) && $amount >= $coupon['min_order']) {
        $discount = $coupon['type'] === 'percent' ? round($amount * $coupon['value'] / 100, 2) : min($coupon['value'], $amount);
        $amount   = max(1, $amount - $discount);
    } else {
        $couponId = 0;
    }
}

$gateway = activeWatchPaysGateway($pdo);
if (!$gateway || empty($gateway['key_id']) || empty($gateway['key_secret'])) {
    echo json_encode(['ok'=>false,'msg'=>'WatchPays is not configured/enabled. Check Admin -> Gateways.']);
    exit;
}
$merchantId = $gateway['key_id'];     // Merchant ID stored in key_id
$apiKey     = $gateway['key_secret']; // API Key stored in key_secret

$ramt = number_format((float)$amount, 2, '.', '');

// Round exact-thousand amounts get rejected by WatchPays' fraud check —
// nudge them down by ₹1 (same workaround used on kingsclub.club).
if (fmod((float)$ramt, 1000) == 0 && (float)$ramt > 0) {
    $ramt = number_format((float)$ramt - 1, 2, '.', '');
}

$user = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

// 1. Create our own order first (status=pending), so we have an order_ref
//    to send as merchant_order_no. Prefixed with DS- so it can never
//    collide with orders from another site sharing the same WatchPays account.
$orderRef = 'DSWP-' . strtoupper(bin2hex(random_bytes(6)));

$stmt = $pdo->prepare("INSERT INTO orders
    (order_ref, user_id, product_id, amount, payment_method, status)
    VALUES (?,?,?,?,?,?)");
$stmt->execute([$orderRef, $_SESSION['user_id'], $productId, $amount, 'watchpays', 'pending']);
pruneOldOrders($pdo);
$orderId = $pdo->lastInsertId();

// Track which coupon (if any) this pending order used, so the webhook can
// apply it only once the payment actually succeeds.
if ($couponId) {
    $pdo->prepare("UPDATE orders SET utr_number=? WHERE id=?")->execute(['COUPON_'.$couponId, $orderId]);
}

// 2. Call WatchPays
$callbackUrl = SITE_URL . '/pay/watchpays_webhook.php';
$signParams = [
    'amount'            => $ramt,
    'callback_url'      => $callbackUrl,
    'merchant_id'       => $merchantId,
    'merchant_order_no' => $orderRef,
];
ksort($signParams);
$signStr = '';
foreach ($signParams as $k => $v) { $signStr .= $k . '=' . $v . '&'; }
$signStr .= 'key=' . $apiKey;
$signature = md5($signStr);

$payload = [
    'merchant_id'       => $merchantId,
    'api_key'           => $apiKey,
    'amount'            => $ramt,
    'merchant_order_no' => $orderRef,
    'callback_url'      => $callbackUrl,
    'extra'             => 'UID_' . $_SESSION['user_id'],
    'signature'         => $signature,
];

$ch = curl_init('https://api.watchpays.com/v1/create');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 20,
]);
$response = curl_exec($ch);
$curlErr  = curl_error($ch);
curl_close($ch);
$resData  = json_decode($response, true);

if (isset($resData['success']) && $resData['success'] === true && !empty($resData['payment_url'])) {
    echo json_encode(['ok'=>true, 'payment_url'=>$resData['payment_url'], 'order_ref'=>$orderRef]);
} else {
    // Clean up the pending order since we couldn't get a payment link
    $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$orderId]);
    error_log('WatchPays create-order failed: ' . ($curlErr ?: $response));
    echo json_encode(['ok'=>false, 'msg'=>'Could not start WatchPays payment. Please try another method.']);
}
