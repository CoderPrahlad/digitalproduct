<?php
/**
 * USDT Payment — TXID Submission
 * Saves the TXID against the pending order, then redirects the customer to
 * WhatsApp with a prefilled message so they can confirm with support.
 * Admin still has to manually verify & approve in Admin -> Orders (same as
 * manual UPI) — this endpoint does NOT auto-mark the order as paid.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(SITE_URL); }
verifyCsrf(SITE_URL);

$orderRef = clean($_POST['order_ref'] ?? '');
$txid     = clean($_POST['txid'] ?? '');

if (!$orderRef || !$txid) {
    flash('error', 'TXID is required.');
    redirect(SITE_URL . '/dashboard.php');
}

$stmt = $pdo->prepare("SELECT o.*, p.title FROM orders o JOIN products p ON p.id=o.product_id WHERE o.order_ref=? AND o.user_id=? AND o.payment_method='usdt' LIMIT 1");
$stmt->execute([$orderRef, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) { flash('error', 'Order not found.'); redirect(SITE_URL . '/dashboard.php'); }

if ($order['status'] === 'pending') {
    // Apply the coupon now (matches when the manual-UPI flow counts a coupon
    // as used) — the coupon id was stashed in payment_proof at order creation.
    if (!empty($order['payment_proof']) && strpos($order['payment_proof'], 'COUPON_') === 0) {
        $couponId = (int)substr($order['payment_proof'], 7);
        if ($couponId) {
            $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id=?")->execute([$couponId]);
        }
    }
    $pdo->prepare("UPDATE orders SET utr_number=?, payment_proof=NULL WHERE id=?")->execute([$txid, $order['id']]);

    notifyTelegram("₿ New USDT Payment Submitted\n<b>{$order['title']}</b>\nTXID: $txid\nRef: {$order['order_ref']}\n💰 USDT " . number_format((float)$order['crypto_amount'], 2) . " (₹" . number_format($order['amount']) . ")\nGo to admin to verify.");
}

flash('success', 'Order submitted! We will verify your payment within 1-2 hours and deliver to your email automatically.');

// Send the user to the success page. That page shows the success message first,
// then (after a short delay) offers a WhatsApp confirmation popup — instead of
// yanking the user straight to WhatsApp.
redirect(SITE_URL . '/success.php?ref=' . urlencode($order['order_ref']) . '&wa=1');
