<?php
/**
 * Razorpay Payment Verification & Auto Delivery
 * Payment → Verify → Generate License → Send Email → Redirect
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
require_once dirname(__DIR__) . '/mail/Mailer.php';
requireLogin();

// Safety check: session ka user_id ab bhi users table me exist karta hai ya nahi
$uchk = $pdo->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
$uchk->execute([$_SESSION['user_id']]);
if (!$uchk->fetch()) {
    session_unset();
    session_destroy();
    session_start();
    flash('error', 'Your session expired. Please login again and retry.');
    redirect(SITE_URL . '/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(SITE_URL); }
verifyCsrf(SITE_URL);

$paymentId  = clean($_POST['razorpay_payment_id']  ?? '');
$orderId    = clean($_POST['razorpay_order_id']    ?? '');
$signature  = clean($_POST['razorpay_signature']   ?? '');
$product_id = (int)($_POST['product_id'] ?? 0);
$coupon_id  = (int)($_POST['coupon_id']  ?? 0);

// 1. Verify signature (against whichever automatic gateway is currently enabled in Admin -> Gateways)
$gateway = activeAutoGateway($pdo);
$gatewaySecret = $gateway['key_secret'] ?? RAZORPAY_KEY_SECRET;
$expectedSig = hash_hmac('sha256', $orderId . '|' . $paymentId, $gatewaySecret);
if (!hash_equals($expectedSig, $signature)) {
    flash('error', 'Payment verification failed. Contact support.');
    redirect(SITE_URL . '/checkout.php?product_id=' . $product_id);
}

// 2. Get product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id=? AND status='active' LIMIT 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) { flash('error', 'Product not found.'); redirect(SITE_URL . '/'); }

$amount = $product['discount_price'] ?: $product['price'];

// Apply coupon discount to final amount
if ($coupon_id) {
    $couponRow = $pdo->prepare("SELECT * FROM coupons WHERE id=? AND active=1 LIMIT 1");
    $couponRow->execute([$coupon_id]);
    $couponRow = $couponRow->fetch();
    if ($couponRow && (!$couponRow['expires_at'] || strtotime($couponRow['expires_at']) > time()) && (!$couponRow['max_uses'] || $couponRow['used_count'] < $couponRow['max_uses']) && $amount >= $couponRow['min_order']) {
        $discount = $couponRow['type'] === 'percent' ? round($amount * $couponRow['value'] / 100, 2) : min($couponRow['value'], $amount);
        $amount   = max(1, $amount - $discount);
    } else {
        $coupon_id = 0; // invalid coupon, don't track
    }
}

// 3. Get user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// 4. Generate order, license, download token
$orderRef    = generateOrderRef();
$licenseKey  = generateLicenseKey();
$dlToken     = bin2hex(random_bytes(32));
$tokenExpiry = date('Y-m-d H:i:s', strtotime('+72 hours'));

// 5. Insert order as PAID
$stmt = $pdo->prepare("INSERT INTO orders
    (order_ref, user_id, product_id, amount, payment_method, razorpay_order_id, razorpay_payment_id, status, license_key, download_token, token_expires)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)");
$stmt->execute([
    $orderRef, $_SESSION['user_id'], $product_id, $amount,
    'razorpay', $orderId, $paymentId,
    'paid', $licenseKey, $dlToken, $tokenExpiry
]);
$newOrderId = $pdo->lastInsertId();
creditVendorEarning($pdo, $newOrderId);
creditReferralCommission($pdo, $newOrderId);
pruneOldOrders($pdo);

// Increment coupon usage
if ($coupon_id) {
    $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id=?")->execute([$coupon_id]);
}

// 6. Build download URL (token-based, no login needed from email)
$downloadUrl = SITE_URL . '/download.php?token=' . $dlToken . '&ref=' . $orderRef;

// 7. Send delivery email — retry once, and record success/failure on the
//    order itself so Admin -> Orders can see and offer a "Resend Email" button
//    instead of the buyer silently never getting their confirmation mail.
$emailSent = Mailer::sendDelivery($user['email'], $user['name'], $product['title'], $licenseKey, $downloadUrl);
if (!$emailSent) {
    $emailSent = Mailer::sendDelivery($user['email'], $user['name'], $product['title'], $licenseKey, $downloadUrl);
}
$pdo->prepare("UPDATE orders SET email_sent=? WHERE id=?")->execute([$emailSent ? 1 : 0, $newOrderId]);
if (!$emailSent) {
    error_log("verify_payment: delivery email FAILED for order {$orderRef} to {$user['email']}");
    notifyTelegram("⚠️ Delivery email FAILED to send\nRef: $orderRef\n👤 {$user['name']} ({$user['email']})\nCheck SMTP settings / use Resend Email in Admin -> Orders.");
}

// 8. Telegram notification
notifyTelegram("🎉 New Order Paid!\n<b>{$product['title']}</b>\n👤 {$user['name']} ({$user['email']})\n💰 ₹" . number_format($amount) . "\n🔑 Ref: $orderRef");

// 9. Redirect to success page
redirect(SITE_URL . '/success.php?ref=' . $orderRef);
