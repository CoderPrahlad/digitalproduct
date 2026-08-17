<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['ok'=>false,'msg'=>'Please login to use wishlist.']); exit; }

$action    = $_POST['action'] ?? $_GET['action'] ?? '';
$productId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);

if ($action === 'toggle' && $productId) {
    // Check if already in wishlist
    $exists = $pdo->prepare("SELECT id FROM wishlist WHERE user_id=? AND product_id=?");
    $exists->execute([$_SESSION['user_id'], $productId]);
    if ($exists->fetch()) {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?")->execute([$_SESSION['user_id'], $productId]);
        echo json_encode(['ok'=>true,'in_wishlist'=>false,'msg'=>'Removed from wishlist']);
    } else {
        $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?,?)")->execute([$_SESSION['user_id'], $productId]);
        echo json_encode(['ok'=>true,'in_wishlist'=>true,'msg'=>'Added to wishlist ❤️']);
    }
    exit;
}

if ($action === 'check' && $productId) {
    $exists = $pdo->prepare("SELECT id FROM wishlist WHERE user_id=? AND product_id=?");
    $exists->execute([$_SESSION['user_id'], $productId]);
    echo json_encode(['ok'=>true,'in_wishlist'=>(bool)$exists->fetch()]);
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Invalid action']);
