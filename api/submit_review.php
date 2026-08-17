<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';

requireLogin();
verifyCsrf(SITE_URL . '/product.php');

$productId = (int)($_POST['product_id'] ?? 0);
$orderId   = (int)($_POST['order_id'] ?? 0);
$rating    = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$title     = trim($_POST['title'] ?? '');
$body      = trim($_POST['body'] ?? '');

if (!$productId || !$body) {
    flash('error', 'Please fill in your review.');
    redirect(SITE_URL . '/product.php?id=' . $productId);
}

// Verify user has a paid order for this product
$order = $pdo->prepare("SELECT id FROM orders WHERE user_id=? AND product_id=? AND status IN ('paid','delivered') ORDER BY created_at DESC LIMIT 1");
$order->execute([$_SESSION['user_id'], $productId]);
$order = $order->fetch();

if (!$order) {
    flash('error', 'You can only review products you have purchased.');
    // Get product slug to redirect back
    $p = $pdo->prepare("SELECT slug FROM products WHERE id=? LIMIT 1");
    $p->execute([$productId]);
    $p = $p->fetch();
    redirect(SITE_URL . '/product.php?slug=' . ($p['slug'] ?? ''));
}

// Check if already reviewed
$existing = $pdo->prepare("SELECT id FROM reviews WHERE user_id=? AND product_id=?");
$existing->execute([$_SESSION['user_id'], $productId]);
if ($existing->fetch()) {
    flash('error', 'You have already reviewed this product.');
    $p = $pdo->prepare("SELECT slug FROM products WHERE id=? LIMIT 1");
    $p->execute([$productId]);
    $p = $p->fetch();
    redirect(SITE_URL . '/product.php?slug=' . ($p['slug'] ?? ''));
}

$pdo->prepare("INSERT INTO reviews (product_id, user_id, order_id, rating, title, body, status) VALUES (?,?,?,?,?,?,'pending')")
    ->execute([$productId, $_SESSION['user_id'], $order['id'], $rating, $title, $body]);

flash('success', 'Thank you! Your review has been submitted and will appear after approval.');
$p = $pdo->prepare("SELECT slug FROM products WHERE id=? LIMIT 1");
$p->execute([$productId]);
$p = $p->fetch();
redirect(SITE_URL . '/product.php?slug=' . ($p['slug'] ?? ''));
