<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';

// ── Filter params ─────────────────────────────────────────────────────
$fMonth = isset($_GET['fm'])   ? (int)$_GET['fm']   : (int)date('n');
$fYear  = isset($_GET['fy'])   ? (int)$_GET['fy']   : (int)date('Y');
$fMode  = isset($_GET['mode']) ? $_GET['mode']       : 'month'; // month | year | alltime

// ── Mode ke hisaab se WHERE clause ───────────────────────────────────
if ($fMode === 'alltime') {
    $whereO  = "1=1";
    $whereOJ = "1=1";
    $whereU  = "1=1";
    $bindO   = [];
    $bindU   = [];
} elseif ($fMode === 'year') {
    $whereO  = "YEAR(created_at)=?";
    $whereOJ = "YEAR(o.created_at)=?";
    $whereU  = "YEAR(created_at)=?";
    $bindO   = [$fYear];
    $bindU   = [$fYear];
} else {
    $whereO  = "MONTH(created_at)=? AND YEAR(created_at)=?";
    $whereOJ = "MONTH(o.created_at)=? AND YEAR(o.created_at)=?";
    $whereU  = "MONTH(created_at)=? AND YEAR(created_at)=?";
    $bindO   = [$fMonth, $fYear];
    $bindU   = [$fMonth, $fYear];
}

// ── Helper ───────────────────────────────────────────────────────────
function fq($pdo, $sql, $params) {
    if (empty($params)) return $pdo->query($sql)->fetchColumn();
    $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchColumn();
}
function fqAll($pdo, $sql, $params) {
    if (empty($params)) return $pdo->query($sql)->fetchAll();
    $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll();
}

// ── ALL-TIME stats (hamesha fixed) ───────────────────────────────────
$totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalBuyers   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='buyer'")->fetchColumn();
$totalSellers  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='seller'")->fetchColumn();
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$totalPending  = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE approval_status='pending'")->fetchColumn();
$thisWeekRev   = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','delivered') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$todayRev      = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','delivered') AND DATE(created_at)=CURDATE()")->fetchColumn();

// ── FILTERED stats ────────────────────────────────────────────────────
$totalRevenue    = (float)fq($pdo, "SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','delivered') AND $whereO", $bindO);
$filteredMonthRev = $totalRevenue;
$totalOrders     = (int)fq($pdo, "SELECT COUNT(*) FROM orders WHERE $whereO", $bindO);
$filteredMonthOrders = $totalOrders;
$pendingOrders   = (int)fq($pdo, "SELECT COUNT(*) FROM orders WHERE status='pending' AND $whereO", $bindO);
$deliveredOrders = (int)fq($pdo, "SELECT COUNT(*) FROM orders WHERE status='delivered' AND $whereO", $bindO);
$cancelledOrders = (int)fq($pdo, "SELECT COUNT(*) FROM orders WHERE status='rejected' AND $whereO", $bindO);
$refundedOrders  = (int)fq($pdo, "SELECT COUNT(*) FROM orders WHERE status='refunded' AND $whereO", $bindO);
$filteredMonthUsers = (int)fq($pdo, "SELECT COUNT(*) FROM users WHERE $whereU", $bindU);
$avgOrderVal     = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// ── Top products ──────────────────────────────────────────────────────
$topProds = fqAll($pdo, "SELECT p.title, p.image, COUNT(o.id) sales, COALESCE(SUM(o.amount),0) rev FROM orders o JOIN products p ON p.id=o.product_id WHERE o.status IN ('paid','delivered') AND $whereOJ GROUP BY o.product_id ORDER BY sales DESC LIMIT 5", $bindO);
// ── New users ─────────────────────────────────────────────────────────
$newUsers = fqAll($pdo, "SELECT DATE(created_at) d, COUNT(*) cnt FROM users WHERE $whereU GROUP BY DATE(created_at) ORDER BY d ASC", $bindU);

// ── Recent orders ─────────────────────────────────────────────────────
$recentOrders = fqAll($pdo, "SELECT o.*,u.name uname,p.title ptitle FROM orders o JOIN users u ON u.id=o.user_id JOIN products p ON p.id=o.product_id WHERE $whereOJ ORDER BY o.created_at DESC LIMIT 5", $bindO);
// ── Status distribution ───────────────────────────────────────────────
$statusDist = fqAll($pdo, "SELECT status, COUNT(*) cnt FROM orders WHERE $whereO GROUP BY status", $bindO);

// ── Revenue chart data ────────────────────────────────────────────────
$revDays = fqAll($pdo, "SELECT DATE(created_at) d, COALESCE(SUM(amount),0) rev FROM orders WHERE status IN ('paid','delivered') AND $whereO GROUP BY DATE(created_at) ORDER BY d ASC", $bindO);

// ── All 12 months revenue (JS ke liye) ───────────────────────────────
$allMonthRevData = [];
for ($m = 1; $m <= 12; $m++) {
    $q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','delivered') AND MONTH(created_at)=? AND YEAR(created_at)=?");
    $q->execute([$m, $fYear]);
    $allMonthRevData[$m] = (float)$q->fetchColumn();
}

// ── Growth comparisons (hamesha real current vs last month) ──────────
$lastMonthRev    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','delivered') AND MONTH(created_at)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(NOW()-INTERVAL 1 MONTH)")->fetchColumn();
$thisMonthRev    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','delivered') AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
$lastMonthUsers  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE MONTH(created_at)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(NOW()-INTERVAL 1 MONTH)")->fetchColumn();
$thisMonthUsers  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
$lastMonthOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE MONTH(created_at)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(NOW()-INTERVAL 1 MONTH)")->fetchColumn();
$thisMonthOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
$lastMonthProds  = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active' AND MONTH(created_at)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(NOW()-INTERVAL 1 MONTH)")->fetchColumn();
$thisMonthProds  = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

$revGrowth   = $lastMonthRev    > 0 ? round((($thisMonthRev    - $lastMonthRev)    / $lastMonthRev)    * 100, 1) : 0;
$userGrowth  = $lastMonthUsers  > 0 ? round((($thisMonthUsers  - $lastMonthUsers)  / $lastMonthUsers)  * 100, 1) : 0;
$orderGrowth = $lastMonthOrders > 0 ? round((($thisMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100, 1) : 0;
$prodGrowth  = $lastMonthProds  > 0 ? round((($thisMonthProds  - $lastMonthProds)  / $lastMonthProds)  * 100, 1) : 0;

// ── Chart date range ──────────────────────────────────────────────────
$today = date('Y-m-d');
if ($fMode === 'alltime') {
    $firstDate = $pdo->query("SELECT DATE(MIN(created_at)) FROM orders")->fetchColumn();
    $startD = new DateTime($firstDate ?: $today);
    $endD   = new DateTime($today);
} elseif ($fMode === 'year') {
    $startD = new DateTime("$fYear-01-01");
    $endD   = ($fYear == date('Y')) ? new DateTime($today) : new DateTime("$fYear-12-31");
} else {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $fMonth, $fYear);
    $startD = new DateTime(sprintf('%04d-%02d-01', $fYear, $fMonth));
    $limitDay = ($fYear == date('Y') && $fMonth == date('n')) ? $today : sprintf('%04d-%02d-%02d', $fYear, $fMonth, $daysInMonth);
    $endD = new DateTime($limitDay);
}

// ── Build chart arrays ────────────────────────────────────────────────
$allDays = [];
$cur = clone $startD;
while ($cur <= $endD) { $allDays[$cur->format('Y-m-d')] = 0; $cur->modify('+1 day'); }
foreach ($revDays as $r) { if (isset($allDays[$r['d']])) $allDays[$r['d']] = (float)$r['rev']; }
$revLabels = []; $revData = [];
foreach ($allDays as $date => $rev) { $revLabels[] = date('d M', strtotime($date)); $revData[] = $rev; }

$statusLabels = []; $statusData = []; $statusColors = [];
$scMap = ['paid'=>'#6366f1','pending'=>'#f59e0b','delivered'=>'#10b981','rejected'=>'#ef4444','refunded'=>'#3b82f6'];
foreach ($statusDist as $s) {
    $statusLabels[] = ucfirst($s['status']);
    $statusData[]   = (int)$s['cnt'];
    $statusColors[] = $scMap[$s['status']] ?? '#94a3b8';
}

$allUD = [];
$cur2 = clone $startD;
while ($cur2 <= $endD) { $allUD[$cur2->format('Y-m-d')] = 0; $cur2->modify('+1 day'); }
foreach ($newUsers as $u) { if (isset($allUD[$u['d']])) $allUD[$u['d']] = (int)$u['cnt']; }
$userLabels = []; $userData = [];
foreach ($allUD as $date => $cnt) { $userLabels[] = date('d M', strtotime($date)); $userData[] = $cnt; }

$monthName = date('F', mktime(0,0,0,$fMonth,1));

// Status color map for badges
$sc2 = [
    'paid'     => ['color'=>'#6366f1','bg'=>'rgba(99,102,241,.15)'],
    'pending'  => ['color'=>'#f59e0b','bg'=>'rgba(245,158,11,.15)'],
    'delivered'=> ['color'=>'#10b981','bg'=>'rgba(16,185,129,.15)'],
    'rejected' => ['color'=>'#ef4444','bg'=>'rgba(239,68,68,.15)'],
    'refunded' => ['color'=>'#3b82f6','bg'=>'rgba(59,130,246,.15)'],
];
?>

<style>
*{box-sizing:border-box;margin:0;padding:0}
.ds{padding:20px 18px;background:#0f0f1e;min-height:100vh;font-family:'Inter',system-ui,sans-serif;color:#e2e8f0}

/* Topbar */
.ds-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.ds-top h1{font-size:22px;font-weight:800;margin-bottom:3px}
.ds-top p{font-size:12px;color:#64748b}
.ds-filter{display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:7px 14px}
.ds-filter select{background:transparent;border:none;color:#e2e8f0;font-size:13px;font-weight:700;cursor:pointer;outline:none}
.ds-filter select option{background:#1a1a2e;color:#e2e8f0}

/* Cards */
.ds-grid5{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:10px}
.ds-card{background:rgba(20,20,40,.9);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:12px 16px;position:relative;overflow:hidden;transition:transform .15s,box-shadow .15s;display:flex;align-items:center;justify-content:flex-start;gap:10px;height:100px;box-sizing:border-box}.ds-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.25)}
.ds-icon{width:48px;height:48px;min-width:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.ds-body{flex:1;min-width:0;overflow:hidden;width:0;text-align:center}
.ds-label{font-size:11px;color:#64748b;font-weight:500;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:center}
.ds-val{font-size:20px;font-weight:800;line-height:1.1;margin-bottom:4px;white-space:nowrap;text-align:center}
.ds-val.sm{font-size:16px}
.badge{display:inline-flex;align-items:center;justify-content:center;gap:px;font-size:10px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}.badge.up{color:#10b981}
.badge.down{color:#ef4444}
.badge.info{color:#10b981}
.ds-link{font-size:11px;font-weight:700;cursor:pointer;text-decoration:none}

/* Accent borders */
.ac-v{border-left:3px solid #8b5cf6}
.ac-g{border-left:3px solid #10b981}
.ac-b{border-left:3px solid #3b82f6}
.ac-o{border-left:3px solid #f59e0b}
.ac-i{border-left:3px solid #6366f1}
.ac-t{border-left:3px solid #14b8a6}
.ac-p{border-left:3px solid #ec4899}
.ac-r{border-left:3px solid #ef4444}

/* Chart panels */
.cc{background:rgba(20,20,40,.9);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:18px 20px}
.cc-title{font-size:14px;font-weight:700;margin-bottom:2px}
.cc-sub{font-size:11px;color:#64748b}

/* Row 3 grid */
.row3{display:grid;grid-template-columns:2fr 1.1fr 0.95fr;gap:12px;margin-bottom:12px}
/* Row 4 grid */
.row4{display:grid;grid-template-columns:1fr 0.8fr 1.4fr;gap:12px;margin-bottom:16px;min-width:0}

/* Donut wrapper */
.donut-wrap{position:relative;display:flex;align-items:center;justify-content:center;margin:0 auto}
.donut-center{position:absolute;text-align:center;pointer-events:none}

/* Status legend */
.sdot{width:9px;height:9px;border-radius:50%;display:inline-block;flex-shrink:0}
.status-row{display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:6px}

/* Top products */
.tp-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.tp-row:last-child{border-bottom:none}
.tp-num{width:22px;height:22px;border-radius:50%;background:rgba(99,102,241,.14);color:#818cf8;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.tp-bar-bg{height:3px;background:rgba(99,102,241,.14);border-radius:2px;margin-top:3px}
.tp-bar{height:3px;background:#6366f1;border-radius:2px}
.tp-img{width:32px;height:24px;object-fit:cover;border-radius:6px;flex-shrink:0}
.tp-thumb{width:32px;height:24px;background:rgba(255,255,255,.06);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}

/* Mini stat boxes */
.mini-box{border-radius:10px;padding:10px;text-align:center}

/* Recent orders table */
.ro{width:100%;border-collapse:collapse;font-size:11px;table-layout:fixed}
.ro th{padding:5px 8px;text-align:left;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid rgba(255,255,255,.06)}
.ro td{padding:8px 8px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle}
.ro tr:last-child td{border-bottom:none}
.ro-pill{padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;display:inline-block}

@media(max-width:1280px){.ds-grid5{grid-template-columns:repeat(3,1fr)}.row3,.row4{grid-template-columns:1fr}}
@media(max-width:768px){.ds-grid5{grid-template-columns:repeat(2,1fr)}.row3,.row4{grid-template-columns:1fr}}
</style>

<div class="ds">

<!-- TOPBAR -->
<div class="ds-top">
  <div>
    <h1>Dashboard</h1>
    <p>Welcome back, Admin! Here's what's happening with your store today.</p>
  </div>
  <div class="ds-filter">
    <span style="font-size:15px">📅</span>
    <select id="filterMode" onchange="applyFilter()">
        <option value="month"   <?=$fMode==='month'  ?'selected':''?>>This Month</option>
        <option value="year"    <?=$fMode==='year'   ?'selected':''?>>This Year</option>
        <option value="alltime" <?=$fMode==='alltime'?'selected':''?>>All Time</option>
    </select>
    <span style="color:#64748b;font-size:11px">|</span>
    <select id="filterMonth" onchange="applyFilter()" <?=$fMode==='alltime'?'disabled':''?>>
        <?php for($m=1;$m<=12;$m++): ?>
        <option value="<?=$m?>" <?=$m==$fMonth?'selected':''?>><?=date('M',mktime(0,0,0,$m,1))?></option>
        <?php endfor; ?>
    </select>
    <select id="filterYear" onchange="applyFilter()">
        <?php for($y=date('Y');$y>=date('Y')-4;$y--): ?>
        <option value="<?=$y?>" <?=$y==$fYear?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
    </select>
  </div><!-- ds-filter -->
</div><!-- ds-top -->

<!-- ROW 1: 6 Main Stat Cards — icon left, text right -->
<div class="ds-grid5">
  <!-- Total Users -->
  <div class="ds-card ac-v">
    <div class="ds-icon" style="background:rgba(139,92,246,.15)">👥</div>
    <div class="ds-body">
      <div class="ds-label">Total Users</div>
      <div class="ds-val"><?=number_format($totalUsers)?></div>
      <span class="badge <?=$userGrowth>=0?'up':'down'?>"><?=$userGrowth>=0?'▲':'▼'?> <?=abs($userGrowth)?>% vs last month</span>
    </div>
  </div>
  <!-- Total Vendors -->
  <div class="ds-card ac-g">
    <div class="ds-icon" style="background:rgba(16,185,129,.15)">🏪</div>
    <div class="ds-body">
      <div class="ds-label">Total Vendors</div>
      <div class="ds-val"><?=number_format($totalSellers)?></div>
      <span class="badge info">▲ Active sellers</span>
    </div>
  </div>
  <!-- Total Products -->
  <div class="ds-card ac-b">
    <div class="ds-icon" style="background:rgba(59,130,246,.15)">📦</div>
    <div class="ds-body">
      <div class="ds-label">Total Products</div>
      <div class="ds-val"><?=number_format($totalProducts)?></div>
      <span class="badge <?=$prodGrowth>=0?'up':'down'?>"><?=$prodGrowth>=0?'▲':'▼'?> <?=abs($prodGrowth)?>% vs last month</span>
    </div>
  </div>
  <!-- Total Orders -->
  <div class="ds-card ac-o">
    <div class="ds-icon" style="background:rgba(245,158,11,.15)">🛒</div>
    <div class="ds-body">
      <div class="ds-label">Total Orders</div>
      <div class="ds-val"><?=number_format($totalOrders)?></div>
      <span class="badge <?=$orderGrowth>=0?'up':'down'?>"><?=$orderGrowth>=0?'▲':'▼'?> <?=abs($orderGrowth)?>% vs last month</span>
    </div>
  </div>
 
  <!-- This Month Sales -->
  <div class="ds-card ac-t">
    <div class="ds-icon" style="background:rgba(20,184,166,.15)">📅</div>
    <div class="ds-body">
      <div class="ds-label">This Month Sales</div>
      <div class="ds-val sm" id="filterRevDisplay">₹<?=number_format($filteredMonthRev)?></div>
<span class="badge info">
  <?php
    if ($fMode==='alltime') echo '▲ All Time';
    elseif ($fMode==='year') echo "▲ Year $fYear";
    else echo "▲ $monthName $fYear";
  ?>
</span>
    </div>
  </div>
</div>

<!-- ROW 2: 4 Secondary Stat Cards — icon left, text right -->
<div class="ds-grid5">
  <div class="ds-card ac-o">
    <div class="ds-icon" style="background:rgba(245,158,11,.15)">⏳</div>
    <div class="ds-body">
      <div class="ds-label">Pending Orders</div>
      <div class="ds-val" style="color:#f59e0b"><?=number_format($pendingOrders)?></div>
      <a href="<?=SITE_URL?>/admin/orders.php?status=pending" class="ds-link" style="color:#f59e0b">View all →</a>
    </div>
  </div>
  <div class="ds-card ac-g">
    <div class="ds-icon" style="background:rgba(12, 210, 144, 0.15)">✅</div>
    <div class="ds-body">
      <div class="ds-label">Delivered Orders</div>
      <div class="ds-val" style="color:#10b981"><?=number_format($deliveredOrders)?></div>
      <a href="<?=SITE_URL?>/admin/orders.php?status=delivered" class="ds-link" style="color:#10b981">View all →</a>
    </div>
  </div>
   <!-- Total Sales -->
  <div class="ds-card ac-i">
    <div class="ds-icon" style="background:rgba(99,102,241,.15)">💰</div>
    <div class="ds-body">
      <div class="ds-label">Total Sales</div>
      <div class="ds-val sm">₹<?=number_format($totalRevenue)?></div>
      <span class="badge <?=$revGrowth>=0?'up':'down'?>"><?=$revGrowth>=0?'▲':'▼'?> <?=abs($revGrowth)?>% vs last month</span>
    </div>
  </div>
  <div class="ds-card ac-r">
    <div class="ds-icon" style="background:rgba(239,68,68,.15)">❌</div>
    <div class="ds-body">
      <div class="ds-label">Cancelled Orders</div>
      <div class="ds-val" style="color:#ef4444"><?=number_format($cancelledOrders)?></div>
      <a href="<?=SITE_URL?>/admin/orders.php?status=rejected" class="ds-link" style="color:#ef4444">View all →</a>
    </div>
  </div>
  <div class="ds-card ac-b">
    <div class="ds-icon" style="background:rgba(59,130,246,.15)">🔄</div>
    <div class="ds-body">
      <div class="ds-label">Refunded Orders</div>
      <div class="ds-val" style="color:#3b82f6"><?=number_format($refundedOrders)?></div>
      <a href="<?=SITE_URL?>/admin/orders.php?status=refunded" class="ds-link" style="color:#3b82f6">View all →</a>
    </div>
  </div>
</div>

<!-- ROW 3: Revenue Chart | Order Status Donut | Sales Overview -->
<div class="row3">

  <!-- Revenue Line Chart -->
  <div class="cc">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
      <div>
        <div class="cc-title">Revenue Overview</div>
        <div class="cc-sub" id="revSubLabel">Last 30 Days</div>
      </div>
      <div style="display:flex;align-items:center;gap:5px">
        <button onclick="shiftRevDays(1)" id="revPrevBtn" style="background:rgba(129,140,248,.1);border:1px solid rgba(129,140,248,.2);color:#818cf8;border-radius:6px;padding:3px 8px;cursor:pointer;font-size:12px">◀</button>
        <select id="revDaysSelect" onchange="revDaysChanged()" style="background:rgba(129,140,248,.1);border:1px solid rgba(129,140,248,.2);color:#818cf8;border-radius:6px;padding:3px 8px;font-size:11px;font-weight:700;cursor:pointer;outline:none">
          <option value="30" selected>Last 30 Days</option>
          <option value="60">Last 60 Days</option>
          <option value="120">Last 120 Days</option>
          <option value="180">Last 180 Days</option>
        </select>
        <button onclick="shiftRevDays(-1)" id="revNextBtn" style="background:rgba(129,140,248,.1);border:1px solid rgba(129,140,248,.2);color:#818cf8;border-radius:6px;padding:3px 8px;cursor:pointer;font-size:12px">▶</button>
      </div>
    </div>
    <canvas id="revChart" height="108"></canvas>
  </div>

  <!-- Order Status Donut -->
  <div class="cc">
    <div class="cc-title" style="margin-bottom:14px">Order Status Distribution</div>
    <div class="donut-wrap" style="width:150px;margin-bottom:14px">
      <canvas id="statusChart" width="150" height="150"></canvas>
      <div class="donut-center">
        <div style="font-size:20px;font-weight:800"><?=number_format($totalOrders)?></div>
        <div style="font-size:10px;color:#64748b">Total Orders</div>
      </div>
    </div>
    <?php
    $sdisplay=['paid'=>'Paid','pending'=>'Pending','delivered'=>'Delivered','rejected'=>'Cancelled','refunded'=>'Refunded'];
    foreach($statusDist as $s):
      $pct=$totalOrders>0?round(($s['cnt']/$totalOrders)*100,1):0;
      $col=$scMap[$s['status']]??'#94a3b8';
      $lbl=$sdisplay[$s['status']]??ucfirst($s['status']);
    ?>
    <div class="status-row">
      <span style="display:flex;align-items:center;gap:7px;color:#94a3b8">
        <span class="sdot" style="background:<?=$col?>"></span><?=$lbl?>
      </span>
      <span style="font-weight:700"><?=number_format($s['cnt'])?> <span style="color:#64748b;font-weight:400">(<?=$pct?>%)</span></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Sales Overview Donut -->
  <div class="cc" style="display:flex;flex-direction:column">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div class="cc-title">Sales Overview</div>
      <div style="display:flex;align-items:center;gap:4px">
        <button onclick="shiftSalesMonth(1)" style="background:rgba(129,140,248,.1);border:1px solid rgba(129,140,248,.3);color:#818cf8;border-radius:6px;padding:3px 9px;cursor:pointer;font-size:13px;line-height:1">◀</button>
        <span id="salesMonthLabel" style="font-size:10px;color:#818cf8;font-weight:700;background:rgba(129,140,248,.1);padding:3px 10px;border-radius:6px;border:1px solid rgba(129,140,248,.3);min-width:80px;text-align:center;display:inline-block"></span>
        <button onclick="shiftSalesMonth(-1)" id="salesNextBtn" style="background:rgba(129,140,248,.1);border:1px solid rgba(129,140,248,.3);color:#818cf8;border-radius:6px;padding:3px 9px;cursor:pointer;font-size:13px;line-height:1">▶</button>
      </div>
    </div>
    <div class="donut-wrap" style="width:130px;margin-bottom:14px">
      <canvas id="salesDonut" width="130" height="130"></canvas>
      <div class="donut-center">
        <div id="salesCenterVal" style="font-size:13px;font-weight:800;color:#818cf8">₹<?=number_format($filteredMonthRev)?></div>
        <div style="font-size:9px;color:#64748b">Total Sales</div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:auto">
      <div class="mini-box" style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2)">
        <div style="font-size:12px;font-weight:800;color:#818cf8">₹<?=number_format($thisWeekRev)?></div>
        <div style="font-size:9px;color:#64748b;margin-top:3px">📈 This Week</div>
      </div>
      <div class="mini-box" style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)">
        <div style="font-size:12px;font-weight:800;color:#10b981">₹<?=number_format($todayRev)?></div>
        <div style="font-size:9px;color:#64748b;margin-top:3px">📅 Today</div>
      </div>
    </div>
  </div>
</div>

<!-- ROW 4: Top Products | New Users | Recent Orders -->
<div class="row4">

  <!-- Top Selling Products -->
  <div class="cc" style="min-width:0;overflow:hidden">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div class="cc-title">Top Selling Products</div>
      <a href="<?=SITE_URL?>/admin/products.php" style="font-size:11px;color:#818cf8;font-weight:600;text-decoration:none">View All</a>
    </div>
    <?php if(empty($topProds)): ?>
      <p style="color:#64748b;text-align:center;padding:20px 0;font-size:12px">No sales yet.</p>
    <?php else: ?>
      <?php
      $maxSales = $topProds[0]['sales'] ?? 1;
      foreach($topProds as $i=>$t):
      ?>
      <div class="tp-row">
        <span class="tp-num"><?=$i+1?></span>
        <?php if(!empty($t['image'])): ?>
          <img src="<?=SITE_URL?>/uploads/products/<?=htmlspecialchars($t['image'])?>" class="tp-img">
        <?php else: ?>
          <div class="tp-thumb">💻</div>
        <?php endif; ?>
        <div style="flex:1;min-width:0">
          <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($t['title'])?></div>
          <div style="font-size:10px;color:#818cf8;margin-top:1px"><?=number_format($t['sales'])?> Sales</div>
          <div class="tp-bar-bg"><div class="tp-bar" style="width:<?=min(100,($t['sales']/max(1,$maxSales))*100)?>%"></div></div>
        </div>
        <div style="font-size:11px;font-weight:700;color:#10b981;text-align:right;flex-shrink:0;white-space:nowrap">₹<?=number_format($t['rev'])?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- New Users Bar Chart -->
  <div class="cc">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
      <div>
        <div class="cc-title">New Users</div>
        <div class="cc-sub">Last 7 Days</div>
      </div>
      <a href="<?=SITE_URL?>/admin/users.php" style="font-size:11px;color:#818cf8;font-weight:600;text-decoration:none">View All</a>
    </div>
    <canvas id="userChart" height="128"></canvas>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px">
      <div class="mini-box" style="background:rgba(255,255,255,.04)">
        <div style="font-size:16px;font-weight:800;color:#10b981"><?=number_format($totalBuyers)?></div>
        <div style="font-size:10px;color:#64748b;margin-top:2px">Total Buyers</div>
      </div>
      <div class="mini-box" style="background:rgba(255,255,255,.04)">
        <div style="font-size:16px;font-weight:800;color:#818cf8"><?=number_format($totalSellers)?></div>
        <div style="font-size:10px;color:#64748b;margin-top:2px">Total Sellers</div>
      </div>
    </div>
  </div>

  <!-- Recent Orders -->
  <div class="cc" style="overflow:hidden;min-width:0">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div class="cc-title">Recent Orders</div>
      <a href="<?=SITE_URL?>/admin/orders.php" style="font-size:11px;color:#818cf8;font-weight:600;text-decoration:none">View All</a>
    </div>
    <?php if(empty($recentOrders)): ?>
      <p style="color:#64748b;text-align:center;padding:20px 0;font-size:12px">No orders yet.</p>
    <?php else: ?>
    <table class="ro">
      <thead>
        <tr>
          <th>Order ID</th>
          <th>User</th>
          <th>Product</th>
          <th style="text-align:right">Amount</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:right">Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($recentOrders as $o):
          $col2=$sc2[$o['status']]??['color'=>'#94a3b8','bg'=>'rgba(148,163,184,.1)'];
        ?>
        <tr>
          <td style="font-family:monospace;font-size:10px;color:#818cf8;font-weight:700">#<?=substr(htmlspecialchars($o['order_ref']),-8)?></td>
          <td style="font-weight:600;max-width:70px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($o['uname'])?></td>
          <td style="max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#94a3b8;font-size:11px"><?=htmlspecialchars($o['ptitle'])?></td>
          <td style="text-align:right;font-weight:700;color:#818cf8">₹<?=number_format($o['amount'])?></td>
          <td style="text-align:center">
            <span class="ro-pill" style="color:<?=$col2['color']?>;background:<?=$col2['bg']?>"><?=ucfirst($o['status'])?></span>
          </td>
          <td style="text-align:right;color:#64748b;font-size:10px;white-space:nowrap"><?=date('d M, H:i',strtotime($o['created_at']))?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

</div><!-- /ds -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
var isDark    = document.documentElement.getAttribute('data-theme') !== 'light';
var gridColor = isDark ? 'rgba(255,255,255,.04)' : 'rgba(0,0,0,.04)';
var textColor = isDark ? '#64748b' : '#94a3b8';

// ── Revenue Chart (AJAX) ──────────────────────────────────────────────
var revChartObj  = null;
var revOffset    = 0;  // kitne din peeche
var revDaysCount = 30; // kitne din dikhane hain

function formatRevD(d) {
  return d.toLocaleDateString('en-IN', {day:'2-digit', month:'short'});
}

function loadRevChart() {
  var end   = new Date();
  end.setDate(end.getDate() - revOffset);
  var start = new Date(end);
  start.setDate(start.getDate() - revDaysCount + 1);

  // Labels update
  var rangeText = 'Last ' + revDaysCount + ' Days';
  if (revOffset > 0) rangeText += ' (' + formatRevD(start) + ' – ' + formatRevD(end) + ')';
  document.getElementById('revSubLabel').textContent = rangeText;

  // Next button disable karo agar current pe ho
  document.getElementById('revNextBtn').disabled = (revOffset <= 0);

  var s = start.toISOString().split('T')[0];
  var e = end.toISOString().split('T')[0];

  fetch('dashboard_chart.php?start='+s+'&end='+e)
    .then(r => r.json())
    .then(data => {
      if (revChartObj) revChartObj.destroy();
      revChartObj = new Chart(document.getElementById('revChart'), {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [{
            data: data.values,
            borderColor: '#818cf8',
            backgroundColor: function(ctx2) {
              var g = ctx2.chart.ctx.createLinearGradient(0,0,0,220);
              g.addColorStop(0,'rgba(129,140,248,.22)');
              g.addColorStop(1,'rgba(129,140,248,0)');
              return g;
            },
            borderWidth:2, pointRadius:3, pointBackgroundColor:'#818cf8',
            pointHoverRadius:5, fill:true, tension:0.45
          }]
        },
        options: {
          responsive:true,
          interaction:{mode:'index',intersect:false},
          plugins:{
            legend:{display:false},
            tooltip:{
              backgroundColor:'rgba(10,10,25,.92)',
              titleColor:'#94a3b8', bodyColor:'#e2e8f0',
              padding:10, borderColor:'rgba(129,140,248,.25)', borderWidth:1,
              callbacks:{label:ctx3=>'  ₹'+ctx3.parsed.y.toLocaleString('en-IN')}
            }
          },
          scales:{
            x:{ticks:{color:textColor,font:{size:10},maxTicksLimit:10},grid:{color:gridColor}},
            y:{ticks:{color:textColor,font:{size:10},callback:v=>'₹'+(v>=100000?(v/100000).toFixed(1)+'L':v>=1000?(v/1000).toFixed(0)+'K':v)},grid:{color:gridColor}}
          }
        }
      });
    });
}

function shiftRevDays(dir) {
  revOffset = Math.max(0, revOffset + (dir * revDaysCount));
  loadRevChart();
}

function revDaysChanged() {
  revDaysCount = parseInt(document.getElementById('revDaysSelect').value);
  revOffset    = 0;
  loadRevChart();
}

// Page load pe — upar ke filter se initial values lo
var urlParams = new URLSearchParams(window.location.search);
var initMode  = urlParams.get('mode') || 'month';
var initMonth = parseInt(urlParams.get('fm')) || <?=date('n')?>;
var initYear  = parseInt(urlParams.get('fy')) || <?=date('Y')?>;

// Upar ke filter ke hisaab se initial range set karo
if (initMode === 'alltime') {
  revDaysCount = 180;
  document.getElementById('revDaysSelect').value = '180';
} else if (initMode === 'year') {
  revDaysCount = 365;
  // 365 option nahi hai toh 180 select karo
  document.getElementById('revDaysSelect').value = '180';
  revDaysCount = 180;
} else {
  // month mode — us month ki range set karo
  var daysInM = new Date(initYear, initMonth, 0).getDate();
  var startOfMonth = new Date(initYear, initMonth-1, 1);
  var endOfMonth   = new Date(initYear, initMonth-1, daysInM);
  var today2 = new Date();
  var effectiveEnd = endOfMonth > today2 ? today2 : endOfMonth;
  revDaysCount = Math.ceil((effectiveEnd - startOfMonth) / (1000*60*60*24)) + 1;
  // Offset calculate karo — aaj se kitne din peeche end hai
  revOffset = Math.ceil((today2 - effectiveEnd) / (1000*60*60*24));
  // Closest option select karo
  var closest = [30,60,120,180].reduce((a,b) => Math.abs(b-revDaysCount)<Math.abs(a-revDaysCount)?b:a);
  document.getElementById('revDaysSelect').value = closest;
  revDaysCount = closest;
  revOffset = Math.max(0, revOffset);
}

loadRevChart();
// Order Status Donut
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?=json_encode($statusLabels)?>,
    datasets: [{
      data: <?=json_encode($statusData)?>,
      backgroundColor: <?=json_encode($statusColors)?>,
      borderWidth: 0, hoverOffset: 5
    }]
  },
  options: {responsive:true, plugins:{legend:{display:false}}, cutout:'72%'}
});

// ── Sales Overview Donut (AJAX + month shift) ─────────────────────────
var salesChartObj = null;
var salesMonthOff = 0; // 0 = current month
var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function loadSalesChart(offset) {
  var d = new Date(); d.setDate(1);
  d.setMonth(d.getMonth() - offset);
  var m = d.getMonth() + 1;
  var y = d.getFullYear();
  document.getElementById('salesMonthLabel').textContent = MONTHS[m-1] + ' ' + y;
  document.getElementById('salesNextBtn').disabled = (offset <= 0);

  fetch('dashboard_chart.php?type=sales&month=' + m + '&year=' + y)
    .then(r => r.json())
    .then(data => {
      // Center text update
      document.getElementById('salesCenterVal').textContent = '₹' + Math.round(data.month).toLocaleString('en-IN');
      if (salesChartObj) salesChartObj.destroy();
      salesChartObj = new Chart(document.getElementById('salesDonut'), {
        type: 'doughnut',
        data: {
          labels: ['This Month','This Week','Today'],
          datasets: [{
            data: [data.month, data.week, data.today],
            backgroundColor: ['#818cf8','#10b981','#3b82f6'],
            borderWidth: 0, hoverOffset: 4
          }]
        },
        options: {responsive:true, plugins:{legend:{display:false}}, cutout:'70%'}
      });
    });
}

function shiftSalesMonth(dir) {
  salesMonthOff = Math.max(0, salesMonthOff + dir);
  loadSalesChart(salesMonthOff);
}

loadSalesChart(0);

// New Users Bar Chart
new Chart(document.getElementById('userChart'), {
  type: 'bar',
  data: {
    labels: <?=json_encode($userLabels)?>,
    datasets: [{
      label: 'New Users',
      data: <?=json_encode($userData)?>,
      backgroundColor: 'rgba(16,185,129,.7)',
      borderRadius: 6, borderSkipped: false
    }]
  },
  options: {
    responsive: true,
    plugins: {legend:{display:false}},
    scales: {
      x: {ticks:{color:textColor,font:{size:10}}, grid:{display:false}},
      y: {ticks:{color:textColor,font:{size:10},stepSize:1}, grid:{color:gridColor}}
    }
  }
});

function applyFilter() {
  var mode = document.getElementById('filterMode').value;
  var m    = parseInt(document.getElementById('filterMonth').value);
  var y    = parseInt(document.getElementById('filterYear').value);
  document.getElementById('filterMonth').disabled = (mode === 'alltime');

  // Revenue chart bhi update karo
  if (mode === 'alltime') {
    revDaysCount = 180;
    revOffset    = 0;
  } else if (mode === 'year') {
    revDaysCount = 180;
    revOffset    = 0;
  } else {
    // us month ki actual range
    var daysInM2   = new Date(y, m, 0).getDate();
    var startOM    = new Date(y, m-1, 1);
    var endOM      = new Date(y, m-1, daysInM2);
    var today3     = new Date();
    var effEnd     = endOM > today3 ? today3 : endOM;
    revOffset      = Math.max(0, Math.ceil((today3 - effEnd) / (1000*60*60*24)));
    revDaysCount   = Math.ceil((effEnd - startOM) / (1000*60*60*24)) + 1;
    var closest2   = [30,60,120,180].reduce((a,b) => Math.abs(b-revDaysCount)<Math.abs(a-revDaysCount)?b:a);
    revDaysCount   = closest2;
    document.getElementById('revDaysSelect').value = closest2;
  }
  loadRevChart();

  // Sales chart bhi sync karo upar ke filter se
  if (mode === 'month') {
    salesMonthOff = Math.max(0, (new Date().getFullYear() - y) * 12 + (new Date().getMonth() + 1) - m);
  } else {
    salesMonthOff = 0;
  }
  loadSalesChart(salesMonthOff);

  var url = new URL(window.location.href);
  url.searchParams.set('fm', m);
  url.searchParams.set('fy', y);
  url.searchParams.set('mode', mode);
  window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>