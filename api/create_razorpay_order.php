<?php
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

// Apply coupon
if ($couponId) {
    $coupon = $pdo->prepare("SELECT * FROM coupons WHERE id=? AND active=1 LIMIT 1");
    $coupon->execute([$couponId]);
    $coupon = $coupon->fetch();
    if ($coupon && (!$coupon['expires_at'] || strtotime($coupon['expires_at']) > time()) && (!$coupon['max_uses'] || $coupon['used_count'] < $coupon['max_uses'])) {
        $discount = $coupon['type'] === 'percent' ? round($amount * $coupon['value'] / 100, 2) : min($coupon['value'], $amount);
        $amount   = max(1, $amount - $discount);
    }
}

$amountPaise = (int)($amount * 100);
$autoGateway = activeAutoGateway($pdo);
if (!$autoGateway || empty($autoGateway['key_id'])) { echo json_encode(['ok'=>false,'msg'=>'Gateway not configured']); exit; }

$data = json_encode(['amount'=>$amountPaise,'currency'=>'INR','receipt'=>generateOrderRef()]);
$ch   = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_USERPWD        => $autoGateway['key_id'].':'.$autoGateway['key_secret'],
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$resp = curl_exec($ch); curl_close($ch);
$rzp  = json_decode($resp, true);

echo json_encode(['ok'=>true,'order_id'=>$rzp['id'] ?? null,'amount'=>$amountPaise]);
