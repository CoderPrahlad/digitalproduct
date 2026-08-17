<?php
/**
 * Admin-only: download a product's uploaded file to inspect it before approving.
 * (Works even while the product is still pending — unlike the buyer-facing
 * download.php, which requires a paid order.)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
requireAdmin();

$pid = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT title, file_path FROM products WHERE id=? LIMIT 1");
$stmt->execute([$pid]);
$product = $stmt->fetch();

if (!$product || !$product['file_path']) { http_response_code(404); die('File not found.'); }

// External link products don't have a local file to serve — send admin straight to it.
if (preg_match('~^https?://~i', $product['file_path'])) {
    header('Location: ' . $product['file_path']);
    exit;
}

$filePath = DOWNLOAD_PATH . '/' . $product['file_path'];
if (!file_exists($filePath)) { http_response_code(404); die('File not found on server.'); }

$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $product['title']) . '.' . pathinfo($filePath, PATHINFO_EXTENSION);
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache');
readfile($filePath);
exit;
