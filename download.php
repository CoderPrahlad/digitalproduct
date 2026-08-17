<?php
/**
 * Secure Token-Based File Download
 * Works from email link (no login needed) OR from dashboard (login needed)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$token = clean($_GET['token'] ?? '');
$ref   = clean($_GET['ref']   ?? '');

if (!$token || !$ref) {
    http_response_code(400); die('Invalid download link.');
}

// Find order by token and ref
$stmt = $pdo->prepare("SELECT o.*, p.title, p.file_path FROM orders o
    JOIN products p ON p.id = o.product_id
    WHERE o.download_token = ? AND o.order_ref = ? LIMIT 1");
$stmt->execute([$token, $ref]);
$order = $stmt->fetch();

if (!$order) { http_response_code(404); die('Download link not found or invalid.'); }
if (!in_array($order['status'], ['paid','delivered'])) { die('Payment not verified yet. Check your email or contact support.'); }

// Token expiry check
if ($order['token_expires'] && strtotime($order['token_expires']) < time()) {
    die('Download link has expired (72 hours). Contact support via WhatsApp to get a new link: <a href="https://wa.me/'.WA_NUMBER.'">Click here</a>');
}

// Max download count
$maxDl = (int)($pdo->query("SELECT key_value FROM settings WHERE key_name='max_downloads'")->fetchColumn() ?: 3);
if ($order['download_count'] >= $maxDl) {
    die('Maximum download limit reached. Contact support for help.');
}

// Find the file — could be a locally uploaded file OR an external link (Google Drive/Mega/etc.)
if (!$order['file_path']) {
    die('File not ready yet. Contact support on WhatsApp: <a href="https://wa.me/'.WA_NUMBER.'">Click here</a>');
}

// External link: redirect straight to it — no bandwidth/storage used on our own server
if (preg_match('~^https?://~i', $order['file_path'])) {
    $pdo->prepare("UPDATE orders SET download_count = download_count+1, status='delivered' WHERE id=?")->execute([$order['id']]);
    header('Location: ' . $order['file_path']);
    exit;
}

$filePath = DOWNLOAD_PATH . '/' . $order['file_path'];
if (!file_exists($filePath)) {
    die('File not ready yet. Contact support on WhatsApp: <a href="https://wa.me/'.WA_NUMBER.'">Click here</a>');
}

// Increment download count, mark delivered
$pdo->prepare("UPDATE orders SET download_count = download_count+1, status='delivered' WHERE id=?")->execute([$order['id']]);

// Serve the file
$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $order['title']) . '.zip';
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache');
readfile($filePath);
exit;
