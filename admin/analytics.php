<?php
$pageTitle = 'Analytics';
require_once __DIR__ . '/includes/admin_header.php';

// Revenue by day (last 30 days)
$dailyRevenue = $pdo->query("
    SELECT DATE(created_at) as day, SUM(amount) as revenue, COUNT(*) as orders
    FROM orders WHERE status IN ('paid','delivered')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll();

// Revenue by week (last 12 weeks)
$weeklyRevenue = $pdo->query("
    SELECT YEARWEEK(created_at,1) as week, SUM(amount) as revenue, COUNT(*) as orders,
           MIN(DATE(created_at)) as week_start
    FROM orders WHERE status IN ('paid','delivered')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
    GROUP BY YEARWEEK(created_at,1)
    ORDER BY week ASC
")->fetchAll();

// Revenue by month (last 12 months)
$monthlyRevenue = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, SUM(amount) as revenue, COUNT(*) as orders,
           DATE_FORMAT(created_at,'%b %Y') as label
    FROM orders WHERE status IN ('paid','delivered')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ORDER BY month ASC
")->fetchAll();

// Best-selling products
$topProducts = $pdo->query("
    SELECT p.title, COUNT(o.id) as sold, SUM(o.amount) as revenue
    FROM orders o JOIN products p ON p.id=o.product_id
    WHERE o.status IN ('paid','delivered')
    GROUP BY o.product_id, p.title
    ORDER BY sold DESC LIMIT 8
")->fetchAll();

// Summary stats
$stats = $pdo->query("
    SELECT
      COALESCE(SUM(CASE WHEN status IN ('paid','delivered') THEN amount END),0) as total_revenue,
      COALESCE(SUM(CASE WHEN status IN ('paid','delivered') AND created_at >= DATE_SUB(NOW(),INTERVAL 30 DAY) THEN amount END),0) as month_revenue,
      COALESCE(SUM(CASE WHEN status IN ('paid','delivered') AND created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) THEN amount END),0) as week_revenue,
      COUNT(CASE WHEN status IN ('paid','delivered') THEN 1 END) as paid_orders,
      COUNT(CASE WHEN status='pending' THEN 1 END) as pending_orders
    FROM orders
")->fetch();

$statusCounts = $pdo->query("SELECT status, COUNT(*) c FROM orders GROUP BY status")->fetchAll();
?>

<style>
/* ── Analytics page ── */
.an-topbar {
  display:flex; justify-content:space-between; align-items:center;
  margin-bottom:24px; flex-wrap:wrap; gap:12px;
}
.an-topbar h1 { font-size:22px; font-weight:700; font-family:'Space Grotesk',sans-serif; }

/* Period tabs */
.period-tabs { display:flex; background:var(--bg2); border-radius:10px; padding:4px; gap:2px; border:1px solid var(--border); }
.period-tab {
  padding:7px 18px; border-radius:7px; font-size:13px; font-weight:600;
  border:none; background:transparent; color:var(--muted); cursor:pointer; transition:.18s;
}
.period-tab.active { background:var(--primary); color:#fff; box-shadow:0 2px 8px rgba(124,58,237,.35); }
.period-tab:not(.active):hover { color:var(--text); background:var(--bg-card2); }

/* Stat cards */
.an-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:22px; }
.an-stat {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:14px; padding:20px 22px; transition:border-color .2s, transform .2s;
  position:relative; overflow:hidden;
}
.an-stat:hover { border-color:var(--primary); transform:translateY(-2px); }
.an-stat::before {
  content:''; position:absolute; top:0; left:0; right:0; height:3px;
  background:var(--accent-grad, linear-gradient(90deg,#7c3aed,#a78bfa));
  opacity:0; transition:opacity .2s;
}
.an-stat:hover::before { opacity:1; }
.an-stat-icon { font-size:22px; margin-bottom:10px; display:block; }
.an-stat-val { font-size:24px; font-weight:800; font-family:'Space Grotesk',sans-serif; line-height:1.1; }
.an-stat-label { font-size:12px; color:var(--muted); margin-top:4px; }

/* Chart card */
.an-chart-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:16px; padding:24px; margin-bottom:20px;
}
.an-chart-card h3 {
  font-size:15px; font-weight:700; color:var(--text);
  margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid var(--border);
  display:flex; align-items:center; gap:8px;
}

/* Bottom grid */
.an-bottom { display:grid; grid-template-columns:3fr 2fr; gap:18px; margin-bottom:24px; }
@media(max-width:900px){ .an-bottom{ grid-template-columns:1fr; } }

/* Bar rows */
.product-bar { margin-bottom:14px; }
.product-bar-header { display:flex; justify-content:space-between; font-size:13px; margin-bottom:5px; }
.product-bar-header span:first-child { color:var(--text); font-weight:500; max-width:65%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.product-bar-header span:last-child { color:#a78bfa; font-weight:700; flex-shrink:0; }
.product-bar-track { background:var(--bg2); border-radius:20px; height:7px; overflow:hidden; }
.product-bar-fill { height:100%; border-radius:20px; background:linear-gradient(90deg,#7c3aed,#a78bfa); transition:width .7s cubic-bezier(.25,.46,.45,.94); }

/* Donut legend */
.donut-legend { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; justify-content:center; }
.donut-legend-item { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--muted2); }
.donut-legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
</style>

<div class="an-topbar">
  <h1>📈 Sales Analytics</h1>
  <div class="period-tabs">
    <button class="period-tab active" id="tab-daily"   onclick="switchPeriod('daily')">Daily</button>
    <button class="period-tab"        id="tab-weekly"  onclick="switchPeriod('weekly')">Weekly</button>
    <button class="period-tab"        id="tab-monthly" onclick="switchPeriod('monthly')">Monthly</button>
  </div>
</div>

<!-- Summary stat cards -->
<div class="an-stat-grid">
  <div class="an-stat" style="--accent-grad:linear-gradient(90deg,#7c3aed,#a78bfa)">
    <span class="an-stat-icon">💰</span>
    <div class="an-stat-val">₹<?= number_format($stats['total_revenue']) ?></div>
    <div class="an-stat-label">All-Time Revenue</div>
  </div>
  <div class="an-stat" style="--accent-grad:linear-gradient(90deg,#10b981,#34d399)">
    <span class="an-stat-icon">📅</span>
    <div class="an-stat-val">₹<?= number_format($stats['month_revenue']) ?></div>
    <div class="an-stat-label">This Month</div>
  </div>
  <div class="an-stat" style="--accent-grad:linear-gradient(90deg,#3b82f6,#60a5fa)">
    <span class="an-stat-icon">📆</span>
    <div class="an-stat-val">₹<?= number_format($stats['week_revenue']) ?></div>
    <div class="an-stat-label">This Week</div>
  </div>
  <div class="an-stat" style="--accent-grad:linear-gradient(90deg,#f59e0b,#fbbf24)">
    <span class="an-stat-icon">✅</span>
    <div class="an-stat-val"><?= number_format($stats['paid_orders']) ?></div>
    <div class="an-stat-label">Paid Orders</div>
  </div>
  <div class="an-stat" style="--accent-grad:linear-gradient(90deg,#ef4444,#f87171)">
    <span class="an-stat-icon">⏳</span>
    <div class="an-stat-val"><?= number_format($stats['pending_orders']) ?></div>
    <div class="an-stat-label">Pending Orders</div>
  </div>
</div>

<!-- Revenue Chart -->
<div class="an-chart-card">
  <h3>📊 Revenue Over Time <span style="font-size:12px;font-weight:400;color:var(--muted);margin-left:auto" id="chartSubtitle">Last 30 days</span></h3>
  <canvas id="revenueChart" height="90"></canvas>
</div>

<!-- Best sellers + Status donut -->
<div class="an-bottom">
  <div class="an-chart-card" style="margin-bottom:0">
    <h3>🏆 Best-Selling Products</h3>
    <?php if (empty($topProducts)): ?>
      <p style="color:var(--muted);font-size:14px">No sales data yet.</p>
    <?php else: ?>
      <?php $maxSold = max(array_column($topProducts, 'sold')) ?: 1; ?>
      <?php foreach ($topProducts as $tp): ?>
      <div class="product-bar">
        <div class="product-bar-header">
          <span><?= clean($tp['title']) ?></span>
          <span><?= $tp['sold'] ?> sold · ₹<?= number_format($tp['revenue']) ?></span>
        </div>
        <div class="product-bar-track">
          <div class="product-bar-fill" style="width:<?= round(($tp['sold']/$maxSold)*100) ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="an-chart-card" style="margin-bottom:0">
    <h3>🍩 Order Status</h3>
    <canvas id="statusChart" height="200"></canvas>
    <div class="donut-legend" id="donutLegend"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var dailyData   = <?= json_encode(array_values($dailyRevenue)) ?>;
var weeklyData  = <?= json_encode(array_values($weeklyRevenue)) ?>;
var monthlyData = <?= json_encode(array_values($monthlyRevenue)) ?>;
var statusLabels = <?= json_encode(array_column($statusCounts,'status')) ?>;
var statusValues = <?= json_encode(array_column($statusCounts,'c')) ?>;
var statusColors = ['#fbbf24','#10b981','#7c3aed','#ef4444','#3b82f6','#ec4899'];

var revenueChart;
var subtitles = { daily:'Last 30 days', weekly:'Last 12 weeks', monthly:'Last 12 months' };

function buildDataset(rows, labelKey) {
  return {
    labels:   rows.map(function(r){ return r[labelKey]||r.day||r.week_start||r.month; }),
    revenues: rows.map(function(r){ return parseFloat(r.revenue)||0; }),
    orders:   rows.map(function(r){ return parseInt(r.orders)||0; })
  };
}

function switchPeriod(type) {
  document.querySelectorAll('.period-tab').forEach(function(b){ b.classList.remove('active'); });
  document.getElementById('tab-'+type).classList.add('active');
  document.getElementById('chartSubtitle').textContent = subtitles[type];

  var data;
  if (type==='daily')   data = buildDataset(dailyData,   'day');
  if (type==='weekly')  data = buildDataset(weeklyData,  'week_start');
  if (type==='monthly') data = buildDataset(monthlyData, 'label');

  if (revenueChart) revenueChart.destroy();

  revenueChart = new Chart(document.getElementById('revenueChart').getContext('2d'), {
    type: 'line',
    data: {
      labels: data.labels,
      datasets: [{
        label: 'Revenue (₹)',
        data: data.revenues,
        borderColor: '#7c3aed',
        backgroundColor: function(ctx){
          var gradient = ctx.chart.ctx.createLinearGradient(0,0,0,ctx.chart.height);
          gradient.addColorStop(0,'rgba(124,58,237,.25)');
          gradient.addColorStop(1,'rgba(124,58,237,.01)');
          return gradient;
        },
        borderWidth: 2.5,
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#a78bfa',
        pointBorderColor: '#7c3aed',
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      interaction: { intersect:false, mode:'index' },
      plugins: {
        legend: { display:false },
        tooltip: {
          backgroundColor:'rgba(15,10,30,.92)',
          borderColor:'rgba(124,58,237,.3)',
          borderWidth:1,
          titleColor:'#e2e8f0',
          bodyColor:'#a78bfa',
          callbacks: { label: function(ctx){ return ' ₹' + ctx.raw.toLocaleString('en-IN'); } }
        }
      },
      scales: {
        x: { grid:{color:'rgba(255,255,255,.04)'}, ticks:{color:'#64748b',font:{size:11}} },
        y: {
          grid:{color:'rgba(255,255,255,.04)'},
          ticks:{color:'#64748b',font:{size:11}, callback: function(v){ return '₹'+v.toLocaleString('en-IN'); }}
        }
      }
    }
  });
}

switchPeriod('daily');

// Donut
var dChart = new Chart(document.getElementById('statusChart').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels: statusLabels.map(function(l){ return l.charAt(0).toUpperCase()+l.slice(1); }),
    datasets: [{ data: statusValues, backgroundColor: statusColors, borderWidth: 0, hoverOffset: 6 }]
  },
  options: {
    plugins: { legend:{ display:false } },
    cutout: '68%'
  }
});

// Custom legend
var legendEl = document.getElementById('donutLegend');
statusLabels.forEach(function(lbl, i){
  var item = document.createElement('div');
  item.className = 'donut-legend-item';
  item.innerHTML = '<span class="donut-legend-dot" style="background:'+statusColors[i]+'"></span>'
    + lbl.charAt(0).toUpperCase()+lbl.slice(1)
    + ' ('+statusValues[i]+')';
  legendEl.appendChild(item);
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
