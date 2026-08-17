<?php
/**
 * Manual UPI Payment Submission
 * Admin se verify hone ke baad delivery hogi
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
requireLogin();

// Safety check: session ka user_id ab bhi users table me exist karta hai ya nahi
// (agar database dubara import hui ho to purana session invalid ho sakta hai)
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

$product_id = (int)($_POST['product_id'] ?? 0);
$utr        = clean($_POST['utr_number'] ?? '');
$coupon_id  = (int)($_POST['coupon_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM products WHERE id=? AND status='active' LIMIT 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) { flash('error','Product not found.'); redirect(SITE_URL.'/'); }
if (!$utr)     { flash('error','UTR number required.'); redirect(SITE_URL.'/checkout.php?product_id='.$product_id); }

$amount = $product['discount_price'] ?: $product['price'];

// Apply coupon discount (re-validated server-side — never trust the client amount)
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

$orderRef = generateOrderRef();
$proofName = null;

// Upload payment proof
if (!empty($_FILES['proof']['name'])) {
    $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png'])) {
        $proofName = 'proof_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        move_uploaded_file($_FILES['proof']['tmp_name'], UPLOAD_PATH.'/proofs/'.$proofName);
    }
}

$stmt = $pdo->prepare("INSERT INTO orders
    (order_ref, user_id, product_id, amount, payment_method, utr_number, payment_proof, status)
    VALUES (?,?,?,?,?,?,?,?)");
$stmt->execute([$orderRef, $_SESSION['user_id'], $product_id, $amount, 'upi_manual', $utr, $proofName, 'pending']);
pruneOldOrders($pdo);

// Increment coupon usage
if ($coupon_id) {
    $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id=?")->execute([$coupon_id]);
}

notifyTelegram("📩 New Manual Payment\n<b>{$product['title']}</b>\nUTR: $utr\nRef: $orderRef\n💰 ₹".number_format($amount)."\nGo to admin to verify.");

flash('success', 'Order submitted! We will verify your payment within 1-2 hours and deliver to your email automatically.');

// Send the user to the success page. That page shows the success message first,
// then (after a short delay) offers a WhatsApp confirmation popup — instead of
// yanking the user straight to WhatsApp.
redirect(SITE_URL . '/success.php?ref=' . urlencode($orderRef) . '&wa=1');