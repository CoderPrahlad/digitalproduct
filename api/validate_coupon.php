<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['ok'=>false,'msg'=>'Please login first.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'msg'=>'Invalid request.']); exit; }

$code      = strtoupper(trim($_POST['code'] ?? ''));
$productId = (int)($_POST['product_id'] ?? 0);

if (!$code) { echo json_encode(['ok'=>false,'msg'=>'Please enter a coupon code.']); exit; }

// Get product price
$product = $pdo->prepare("SELECT * FROM products WHERE id=? AND status='active' LIMIT 1");
$product->execute([$productId]);
$product = $product->fetch();
if (!$product) { echo json_encode(['ok'=>false,'msg'=>'Product not found.']); exit; }
$amount = $product['discount_price'] ?: $product['price'];

// Validate coupon
$coupon = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND active=1 LIMIT 1");
$coupon->execute([$code]);
$coupon = $coupon->fetch();

if (!$coupon) { echo json_encode(['ok'=>false,'msg'=>'Invalid or inactive coupon code.']); exit; }
if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) { echo json_encode(['ok'=>false,'msg'=>'This coupon has expired.']); exit; }
if ($coupon['max_uses'] && $coupon['used_count'] >= $coupon['max_uses']) { echo json_encode(['ok'=>false,'msg'=>'This coupon has reached its usage limit.']); exit; }
if ($coupon['min_order'] > 0 && $amount < $coupon['min_order']) {
    echo json_encode(['ok'=>false,'msg'=>'Minimum order amount of ₹'.number_format($coupon['min_order']).' required for this coupon.']);
    exit;
}

// Calculate discount
if ($coupon['type'] === 'percent') {
    $discount = round($amount * ($coupon['value'] / 100), 2);
} else {
    $discount = min($coupon['value'], $amount);
}
$finalAmount = max(1, $amount - $discount);

echo json_encode([
    'ok'           => true,
    'coupon_id'    => $coupon['id'],
    'coupon_code'  => $coupon['code'],
    'discount'     => $discount,
    'final_amount' => $finalAmount,
    'msg'          => 'Coupon applied! You save ₹'.number_format($discount)
]);
