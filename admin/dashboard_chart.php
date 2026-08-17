<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
if (!isAdmin()) { echo json_encode([]); exit; }

header('Content-Type: application/json');

// ── Sales donut ───────────────────────────────────────────────────────
if (($_GET['type'] ?? '') === 'sales') {
    $m = (int)($_GET['month'] ?? date('n'));
    $y = (int)($_GET['year']  ?? date('Y'));
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','delivered') AND MONTH(created_at)=? AND YEAR(created_at)=?");
    $s->execute([$m, $y]); $month = (float)$s->fetchColumn();
    $week  = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','delivered') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $today = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','delivered') AND DATE(created_at)=CURDATE()")->fetchColumn();
    echo json_encode(['month'=>$month,'week'=>$week,'today'=>$today]);
    exit;
}

// ── Revenue chart ─────────────────────────────────────────────────────
$start = $_GET['start'] ?? date('Y-m-d', strtotime('-29 days'));
$end   = $_GET['end']   ?? date('Y-m-d');
$s = $pdo->prepare("SELECT DATE(created_at) d, COALESCE(SUM(amount),0) rev FROM orders WHERE status IN ('paid','delivered') AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY d ASC");
$s->execute([$start, $end]);
$rows = $s->fetchAll();
$map = [];
foreach ($rows as $r) $map[$r['d']] = (float)$r['rev'];
$labels = []; $values = [];
$cur  = new DateTime($start);
$endD = new DateTime($end);
while ($cur <= $endD) {
    $k=$cur->format('Y-m-d'); $labels[]=$cur->format('d M'); $values[]=$map[$k]??0;
    $cur->modify('+1 day');
}
echo json_encode(['labels'=>$labels,'values'=>$values]);