<?php
/**
 * Returns the live INR-per-USDT rate as JSON, so the front-end currency
 * toggle uses the SAME source as the real USDT checkout flow (instead of a
 * hardcoded number that drifts from reality).
 */
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$rate = 93; // fallback if CoinGecko is unreachable
$ch = curl_init('https://api.coingecko.com/api/v3/simple/price?ids=tether&vs_currencies=inr');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 6,
]);
$resp = curl_exec($ch);
curl_close($ch);
$data = json_decode($resp, true);
if (!empty($data['tether']['inr']) && $data['tether']['inr'] > 0) {
    $rate = (float)$data['tether']['inr'];
}

echo json_encode(['rate' => $rate]);
