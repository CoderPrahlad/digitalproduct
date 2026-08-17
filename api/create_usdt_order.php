<?php
/**
 * USDT (TRC20) Manual Crypto Payment — Create Order
 * INR amount -> live USDT amount (CoinGecko) -> pending order -> usdt_checkout.php
 * Approval is MANUAL: customer submits TXID, admin verifies & approves in
 * Admin -> Orders (same flow already used for manual UPI).
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(SITE_URL); }
verifyCsrf(SITE_URL);

// Resolve which crypto gateway the customer chose; must be a currently-enabled one.
$gatewayId = (int)($_POST['gateway_id'] ?? 0);
$gateway = null;
foreach (activeCryptoGateways($pdo) as $cg) {
    if ((int)$cg['id'] === $gatewayId || ($gatewayId === 0 && (int)$cg['id'] === 0)) { $gateway = $cg; break; }
}
if (!$gateway && !empty(activeCryptoGateways($pdo))) { $gateway = activeCryptoGateways($pdo)[0]; } // fallback to first enabled
if (!$gateway) { flash('error','Crypto payments are currently unavailable.'); redirect(SITE_URL); }

// Safety: add columns for installs that haven't run the crypto-gateway migration yet.
try {
    $col = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'crypto_amount'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `crypto_amount` DECIMAL(20,8) NULL DEFAULT NULL");
    }
    $col2 = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'crypto_address'")->fetch();
    if (!$col2) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `crypto_address` VARCHAR(255) NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `crypto_network` VARCHAR(150) NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `crypto_gateway_name` VARCHAR(150) NULL DEFAULT NULL");
    }
} catch (Exception $e) { /* non-fatal */ }

$product_id = (int)($_POST['product_id'] ?? 0);
$coupon_id  = (int)($_POST['coupon_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM products WHERE id=? AND status='active' LIMIT 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) { flash('error','Product not found.'); redirect(SITE_URL.'/'); }

$amount = $product['discount_price'] ?: $product['price'];

if ($coupon_id) {
    $couponRow = $pdo->prepare("SELECT * FROM coupons WHERE id=? AND active=1 LIMIT 1");
    $couponRow->execute([$coupon_id]);
    $couponRow = $couponRow->fetch();
    if ($couponRow && (!$couponRow['expires_at'] || strtotime($couponRow['expires_at']) > time()) && (!$couponRow['max_uses'] || $couponRow['used_count'] < $couponRow['max_uses']) && $amount >= $couponRow['min_order']) {
        $discount = $couponRow['type'] === 'percent' ? round($amount * $couponRow['value'] / 100, 2) : min($couponRow['value'], $amount);
        $amount   = max(1, $amount - $discount);
    } else {
        $coupon_id = 0;
    }
}

// ---- Live INR -> USDT rate (CoinGecko, free, no API key) ----
$usdtAmount = null;
$ch = curl_init('https://api.coingecko.com/api/v3/simple/price?ids=tether&vs_currencies=inr');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
]);
$resp = curl_exec($ch);
curl_close($ch);
$rateData = json_decode($resp, true);
$rate = $rateData['tether']['inr'] ?? null;

if (!$rate || $rate <= 0) {
    // Fallback rate if CoinGecko is unreachable, so checkout never hard-fails.
    $rate = 93;
}
$usdtAmount = round($amount / $rate, 2);

$orderRef = 'DSUSDT-' . strtoupper(bin2hex(random_bytes(6)));

$stmt = $pdo->prepare("INSERT INTO orders
    (order_ref, user_id, product_id, amount, payment_method, crypto_amount, crypto_address, crypto_network, crypto_gateway_name, payment_proof, status)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)");
$stmt->execute([$orderRef, $_SESSION['user_id'], $product_id, $amount, 'usdt', $usdtAmount, $gateway['upi_id'], $gateway['upi_name'], $gateway['name'], $coupon_id ? 'COUPON_'.$coupon_id : null, 'pending']);
pruneOldOrders($pdo);

redirect(SITE_URL . '/usdt_checkout.php?ref=' . urlencode($orderRef));
