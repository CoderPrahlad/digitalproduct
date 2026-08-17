<?php
$pageTitle = 'Marketplace';
require_once __DIR__ . '/includes/admin_header.php';
?>
<style>
/* ── MARKETPLACE RESPONSIVE ─────────────────────────────── */

/* Stat grid — 4 col → 2 col → 1 col */
.stat-grid {
  display: grid !important;
  grid-template-columns: repeat(4, 1fr) !important;
}
@media (max-width: 900px) {
  .stat-grid {
    grid-template-columns: repeat(2, 1fr) !important;
  }
}
@media (max-width: 500px) {
  .stat-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
  }
  .stat-card {
    padding: 12px !important;
  }
  .stat-card h3 {
    font-size: 18px !important;
  }
}

/* Admin topbar — stack on mobile */
.admin-topbar {
  flex-wrap: wrap !important;
  gap: 10px !important;
}
@media (max-width: 600px) {
  .admin-topbar {
    flex-direction: column !important;
    align-items: flex-start !important;
  }
  .admin-topbar h1 {
    font-size: 18px !important;
  }
}

/* Vendor detail — 2 col grid → 1 col */
@media (max-width: 768px) {
  div[style*="grid-template-columns:1fr 1fr"] {
    display: block !important;
  }
  div[style*="grid-template-columns:1fr 1fr"] > .section-card {
    margin-bottom: 16px !important;
  }
}

/* Tables — horizontal scroll */
.table-wrap {
  overflow-x: auto !important;
  -webkit-overflow-scrolling: touch;
}
.table-wrap table {
  min-width: 600px;
}

/* Pending withdraw action row — stack on mobile */
@media (max-width: 600px) {
  .table-wrap table td[style*="display:flex"] {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 4px !important;
  }
  .table-wrap table td form {
    flex-wrap: wrap !important;
  }
  .table-wrap table td input[style*="width:120px"] {
    width: 100% !important;
  }
}

/* Tab buttons — scroll horizontally */
.stabs-wrap {
  overflow-x: auto !important;
  -webkit-overflow-scrolling: touch;
  flex-wrap: nowrap !important;
}
@media (max-width: 500px) {
  .stab-btn {
    padding: 8px 12px !important;
    font-size: 12px !important;
  }
}

/* Payout details — flex rows stack */
@media (max-width: 500px) {
  div[style*="justify-content:space-between"] {
    flex-direction: column !important;
    gap: 4px !important;
  }
}

/* Pending withdraw form — stack */
@media (max-width: 600px) {
  div[style*="display:flex;gap:8px"] > form {
    width: 100% !important;
  }
  div[style*="display:flex;gap:8px"] > form button {
    width: 100% !important;
    margin-top: 6px !important;
  }
}

/* Product cards in vendor view */
@media (max-width: 500px) {
  div[style*="display:flex;gap:12px;align-items:center"] {
    flex-wrap: wrap !important;
  }
  div[style*="display:flex;gap:5px;flex-shrink:0"] {
    flex-wrap: wrap !important;
    width: 100% !important;
    margin-top: 6px !important;
  }
}

/* Section card padding */
@media (max-width: 500px) {
  .section-card {
    padding: 14px !important;
  }
}

/* Recent sales table badges */
@media (max-width: 600px) {
  .table-wrap table th, .table-wrap table td {
    font-size: 12px !important;
    padding: 8px 10px !important;
  }
}
</style>
<?php

// ── APPROVE VENDOR LISTING ──────────────────────────────────────────────────
if (isset($_GET['approve_vendor'])) {
    if (empty($_GET['t']) || !hash_equals(csrf_token(), $_GET['t'])) {
        flash('error','Security check failed.'); redirect(SITE_URL.'/admin/marketplace.php');
    }
    $pid = (int)$_GET['approve_vendor'];
    $vp = $pdo->prepare("SELECT seller_id FROM products WHERE id=? AND seller_id IS NOT NULL"); $vp->execute([$pid]); $vp = $vp->fetch();
    if ($vp) {
        $pdo->prepare("UPDATE products SET approval_status='approved', status='active', reject_reason=NULL WHERE id=?")->execute([$pid]);
        flash('success','Listing approved — now live!');
        redirect(SITE_URL.'/admin/marketplace.php?vendor='.$vp['seller_id']);
    }
    redirect(SITE_URL.'/admin/marketplace.php');
}

// ── REJECT VENDOR LISTING ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reject_vendor'])) {
    verifyCsrf(SITE_URL.'/admin/marketplace.php');
    $pid = (int)$_POST['reject_vendor'];
    $reason = clean($_POST['reject_reason'] ?? '');
    $vp = $pdo->prepare("SELECT seller_id FROM products WHERE id=?"); $vp->execute([$pid]); $vp = $vp->fetch();
    $pdo->prepare("UPDATE products SET approval_status='rejected', status='inactive', reject_reason=? WHERE id=?")->execute([$reason,$pid]);
    flash('success','Listing rejected.');
    redirect(SITE_URL.'/admin/marketplace.php'.($vp ? '?vendor='.$vp['seller_id'] : ''));
}

// ── APPROVE WITHDRAW ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['approve_withdraw'])) {
    verifyCsrf(SITE_URL.'/admin/marketplace.php');
    $wid = (int)$_POST['approve_withdraw'];
    $note = clean($_POST['admin_note'] ?? '');
    $wr = $pdo->prepare("SELECT * FROM withdraw_requests WHERE id=? AND status='pending'"); $wr->execute([$wid]); $wr = $wr->fetch();
    if ($wr) {
        $pdo->prepare("UPDATE withdraw_requests SET status='paid', admin_note=?, paid_at=NOW() WHERE id=?")->execute([$note,$wid]);
        $pdo->prepare("UPDATE seller_earnings SET payout_status='paid', paid_at=NOW() WHERE seller_id=? AND payout_status='unpaid'")->execute([$wr['user_id']]);
        flash('success','Withdrawal approved and balance marked paid.');
        redirect(SITE_URL.'/admin/marketplace.php?vendor='.$wr['user_id']);
    }
    flash('error','Request not found.');
    redirect(SITE_URL.'/admin/marketplace.php');
}

// ── REJECT WITHDRAW ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reject_withdraw'])) {
    verifyCsrf(SITE_URL.'/admin/marketplace.php');
    $wid = (int)$_POST['reject_withdraw'];
    $note = clean($_POST['admin_note'] ?? '');
    $wr = $pdo->prepare("SELECT user_id FROM withdraw_requests WHERE id=?"); $wr->execute([$wid]); $wr = $wr->fetch();
    $pdo->prepare("UPDATE withdraw_requests SET status='rejected', admin_note=? WHERE id=?")->execute([$note,$wid]);
    flash('error','Withdrawal request rejected.');
    redirect(SITE_URL.'/admin/marketplace.php'.($wr ? '?vendor='.$wr['user_id'] : ''));
}

// ── INDIVIDUAL VENDOR DASHBOARD ─────────────────────────────────────────────
if (isset($_GET['vendor'])) {
    $vid = (int)$_GET['vendor'];
    $vendor = $pdo->prepare("SELECT * FROM users WHERE id=?"); $vendor->execute([$vid]); $vendor = $vendor->fetch();
    if (!$vendor) { flash('error','User not found.'); redirect(SITE_URL.'/admin/marketplace.php'); }

    $earnings = $pdo->prepare("SELECT
        COALESCE(SUM(seller_amount),0) total_earned,
        COALESCE(SUM(CASE WHEN payout_status='unpaid' THEN seller_amount ELSE 0 END),0) unpaid,
        COALESCE(SUM(CASE WHEN payout_status='paid' THEN seller_amount ELSE 0 END),0) paid_out,
        COUNT(*) total_sales
        FROM seller_earnings WHERE seller_id=?");
    $earnings->execute([$vid]); $earnings = $earnings->fetch();

    $products = $pdo->prepare("SELECT p.*, c.name cname FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.seller_id=? ORDER BY p.created_at DESC");
    $products->execute([$vid]); $products = $products->fetchAll();

    $sales = $pdo->prepare("SELECT se.*, p.title, o.order_ref FROM seller_earnings se JOIN products p ON p.id=se.product_id JOIN orders o ON o.id=se.order_id WHERE se.seller_id=? ORDER BY se.created_at DESC LIMIT 30");
    $sales->execute([$vid]); $sales = $sales->fetchAll();

    $withdrawals = [];
    try {
        $wd = $pdo->prepare("SELECT * FROM withdraw_requests WHERE user_id=? ORDER BY created_at DESC");
        $wd->execute([$vid]); $withdrawals = $wd->fetchAll();
    } catch(Exception $e) {}

    $pendingWithdraw = null;
    foreach ($withdrawals as $w) { if ($w['status']==='pending') { $pendingWithdraw = $w; break; } }
?>

<div class="admin-topbar">
  <div style="display:flex;align-items:center;gap:12px">
    <a href="<?= SITE_URL ?>/admin/marketplace.php" class="btn btn-outline btn-sm">← Marketplace</a>
    <div>
      <h1 style="margin:0"><?= clean($vendor['name']) ?></h1>
      <div style="color:var(--muted);font-size:13px;margin-top:2px"><?= clean($vendor['email']) ?> <?= $vendor['phone'] ? '· '.$vendor['phone'] : '' ?></div>
    </div>
  </div>
  <a href="<?= SITE_URL ?>/admin/users.php?view=<?= $vid ?>" class="btn btn-outline btn-sm" target="_blank">View User Profile</a>
</div>

<!-- Earnings Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(52,211,153,.12);color:#34d399">💵</div>
    <div><h3>₹<?= number_format($earnings['unpaid'],2) ?></h3><p>Unpaid Balance</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(167,139,250,.12);color:#a78bfa">📈</div>
    <div><h3>₹<?= number_format($earnings['total_earned'],2) ?></h3><p>Total Earned</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(59,130,246,.12);color:#60a5fa">✅</div>
    <div><h3>₹<?= number_format($earnings['paid_out'],2) ?></h3><p>Paid Out</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(251,191,36,.12);color:#fbbf24">🛒</div>
    <div><h3><?= (int)$earnings['total_sales'] ?></h3><p>Total Sales</p></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

  <!-- Payout / Withdraw Info -->
  <div class="section-card" style="margin-bottom:0">
    <h3>💳 Payout Details</h3>
    <div style="display:grid;gap:10px">
      <div style="display:flex;justify-content:space-between;padding:10px;background:var(--bg2);border-radius:8px">
        <span style="color:var(--muted);font-size:13px">UPI ID</span>
        <strong style="font-size:13px"><?= $vendor['payout_upi'] ? clean($vendor['payout_upi']) : '<span style="color:var(--danger)">Not set</span>' ?></strong>
      </div>
      <?php if ($vendor['payout_note']): ?>
      <div style="display:flex;justify-content:space-between;padding:10px;background:var(--bg2);border-radius:8px">
        <span style="color:var(--muted);font-size:13px">Note</span>
        <span style="font-size:13px"><?= clean($vendor['payout_note']) ?></span>
      </div>
      <?php endif; ?>
      <div style="display:flex;justify-content:space-between;padding:10px;background:var(--bg2);border-radius:8px">
        <span style="color:var(--muted);font-size:13px">Unpaid Balance</span>
        <strong style="color:#34d399;font-size:15px">₹<?= number_format($earnings['unpaid'],2) ?></strong>
      </div>
    </div>

    <?php if ($pendingWithdraw): ?>
    <div style="margin-top:14px;padding:14px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.3);border-radius:10px">
      <div style="color:#fbbf24;font-weight:700;font-size:13px;margin-bottom:8px">⏳ Pending Withdraw Request — ₹<?= number_format($pendingWithdraw['amount'],2) ?></div>
      <div style="color:var(--muted);font-size:12px;margin-bottom:10px">Requested: <?= date('d M Y, h:i A', strtotime($pendingWithdraw['created_at'])) ?><?= $pendingWithdraw['note'] ? ' · '.$pendingWithdraw['note'] : '' ?></div>
      <div style="display:flex;gap:8px">
        <form method="POST" style="flex:1">
          <?= csrf_field() ?>
          <input type="hidden" name="approve_withdraw" value="<?= $pendingWithdraw['id'] ?>">
          <input class="form-control" type="text" name="admin_note" placeholder="Payment note (optional)" style="margin-bottom:8px;font-size:12px">
          <button type="submit" class="btn btn-success btn-sm confirm-action" data-confirm="Confirm payment sent to <?= clean($vendor['payout_upi'] ?: 'vendor') ?>? This will mark all unpaid earnings as paid." style="width:100%">✅ Approve & Mark Paid</button>
        </form>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="reject_withdraw" value="<?= $pendingWithdraw['id'] ?>">
          <input type="hidden" name="admin_note" value="Rejected by admin">
          <button type="submit" class="btn btn-danger btn-sm confirm-action" data-confirm="Reject this withdrawal request?" style="margin-top:28px">❌ Reject</button>
        </form>
      </div>
    </div>
    <?php elseif ($earnings['unpaid'] > 0): ?>
    <div style="margin-top:14px">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="approve_withdraw" value="0">
        <!-- Direct pay without request -->
        <p style="color:var(--muted);font-size:12px;margin-bottom:8px">No pending request — manually mark as paid after sending:</p>
        <div style="display:flex;gap:8px">
          <input class="form-control" type="text" name="admin_note" placeholder="Payment reference / note" style="font-size:12px">
        </div>
      </form>
    </div>
    <?php else: ?>
    <p style="color:var(--muted);font-size:13px;margin-top:12px">No outstanding balance.</p>
    <?php endif; ?>

    <!-- Withdraw History -->
    <?php if (!empty($withdrawals)): ?>
    <div style="margin-top:16px">
      <div style="font-size:13px;font-weight:600;margin-bottom:8px;color:var(--muted2)">Withdraw History</div>
      <?php foreach ($withdrawals as $w): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--bg2);border-radius:8px;margin-bottom:6px;font-size:12px">
        <div>
          <strong>₹<?= number_format($w['amount'],2) ?></strong>
          <?php if ($w['admin_note']): ?><span style="color:var(--muted)"> · <?= clean($w['admin_note']) ?></span><?php endif; ?>
          <div style="color:var(--muted);margin-top:2px"><?= date('d M Y', strtotime($w['created_at'])) ?></div>
        </div>
        <?php if ($w['status']==='paid'): ?><span class="badge badge-active">Paid</span>
        <?php elseif ($w['status']==='pending'): ?><span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24">Pending</span>
        <?php else: ?><span class="badge" style="background:rgba(239,68,68,.15);color:#ef4444">Rejected</span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Vendor Products -->
  <div class="section-card" style="margin-bottom:0">
    <h3>📦 Products (<?= count($products) ?>)</h3>
    <?php if (empty($products)): ?>
    <p style="color:var(--muted);font-size:13px">No products yet.</p>
    <?php else: ?>
    <div style="display:grid;gap:10px;max-height:420px;overflow-y:auto">
      <?php foreach ($products as $p): ?>
      <div style="display:flex;gap:12px;align-items:center;padding:10px;background:var(--bg2);border-radius:10px">
        <?php if ($p['image']): ?><img src="<?= SITE_URL ?>/uploads/products/<?= clean($p['image']) ?>" style="width:48px;height:36px;object-fit:cover;border-radius:6px;flex-shrink:0"><?php else: ?><div style="width:48px;height:36px;background:var(--bg-card);border-radius:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:18px">📦</div><?php endif; ?>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= clean($p['title']) ?></div>
          <div style="display:flex;gap:6px;align-items:center;margin-top:3px">
            <span style="font-size:12px;color:#a78bfa">₹<?= number_format($p['seller_base_price'] ?: $p['price']) ?></span>
            <?php if ($p['approval_status']==='pending'): ?>
              <span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24;font-size:10px">Pending</span>
            <?php elseif ($p['approval_status']==='approved'): ?>
              <span class="badge badge-active" style="font-size:10px">Live</span>
            <?php else: ?>
              <span class="badge" style="background:rgba(239,68,68,.15);color:#ef4444;font-size:10px">Rejected</span>
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:5px;flex-shrink:0">
          <a href="<?= SITE_URL ?>/admin/products.php?edit=<?= $p['id'] ?>#product-form" class="btn btn-outline btn-sm" style="padding:4px 10px;font-size:11px">Edit</a>
          <?php if ($p['approval_status']==='pending'): ?>
            <a href="<?= SITE_URL ?>/admin/marketplace.php?approve_vendor=<?= $p['id'] ?>&vendor=<?= $vid ?>&t=<?= csrf_token() ?>" class="btn btn-success btn-sm confirm-action" data-confirm="Approve this listing?" style="padding:4px 10px;font-size:11px">✅</a>
            <button type="button" class="btn btn-danger btn-sm" style="padding:4px 10px;font-size:11px"
              onclick="var r=prompt('Reject reason (optional):'); if(r===null)return; document.getElementById('rf<?= $p['id'] ?>').reject_reason.value=r; document.getElementById('rf<?= $p['id'] ?>').submit()">❌</button>
            <form id="rf<?= $p['id'] ?>" method="POST" style="display:none">
              <?= csrf_field() ?><input type="hidden" name="reject_vendor" value="<?= $p['id'] ?>"><input type="hidden" name="reject_reason" value="">
            </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Sales -->
<div class="section-card">
  <h3>🛒 Recent Sales</h3>
  <?php if (empty($sales)): ?>
  <p style="color:var(--muted);font-size:13px">No sales yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Order Ref</th><th>Product</th><th>Buyer Paid</th><th>Seller Earned</th><th>Payout</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($sales as $s): ?>
      <tr>
        <td style="font-family:monospace;font-size:12px"><?= clean($s['order_ref']) ?></td>
        <td><?= clean($s['title']) ?></td>
        <td>₹<?= number_format($s['buyer_amount'],2) ?></td>
        <td style="color:#34d399">₹<?= number_format($s['seller_amount'],2) ?></td>
        <td><?= $s['payout_status']==='paid' ? '<span class="badge badge-active">Paid</span>' : '<span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24">Unpaid</span>' ?></td>
        <td style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php
    require_once __DIR__ . '/includes/admin_footer.php'; exit;
}

// ── MAIN MARKETPLACE OVERVIEW ───────────────────────────────────────────────
$pendingProducts = $pdo->query("SELECT p.*, u.name sname, u.email semail FROM products p JOIN users u ON u.id=p.seller_id WHERE p.approval_status='pending' ORDER BY p.created_at ASC")->fetchAll();

$pendingWithdraws = [];
try {
    $pendingWithdraws = $pdo->query("SELECT wr.*, u.name uname, u.email uemail, u.payout_upi FROM withdraw_requests wr JOIN users u ON u.id=wr.user_id WHERE wr.status='pending' ORDER BY wr.created_at ASC")->fetchAll();
} catch(Exception $e) {}

$vendors = $pdo->query("SELECT u.id, u.name, u.email, u.payout_upi, u.created_at,
    COUNT(DISTINCT p.id) total_products,
    COUNT(DISTINCT CASE WHEN p.approval_status='pending' THEN p.id END) pending_products,
    COALESCE(SUM(se.seller_amount),0) total_earned,
    COALESCE(SUM(CASE WHEN se.payout_status='unpaid' THEN se.seller_amount ELSE 0 END),0) unpaid,
    COUNT(se.id) total_sales
    FROM users u
    LEFT JOIN products p ON p.seller_id=u.id
    LEFT JOIN seller_earnings se ON se.seller_id=u.id
    GROUP BY u.id HAVING total_products > 0 OR total_earned > 0
    ORDER BY unpaid DESC, total_earned DESC")->fetchAll();

$totalVendors  = count($vendors);
$totalUnpaid   = array_sum(array_column($vendors,'unpaid'));
$totalSales    = array_sum(array_column($vendors,'total_sales'));
$totalPending  = count($pendingProducts) + count($pendingWithdraws);
?>

<div class="admin-topbar">
  <h1>🏪 Marketplace
    <?php if ($totalPending > 0): ?>
    <span style="background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.3);border-radius:20px;font-size:12px;font-weight:700;padding:3px 10px;margin-left:8px;vertical-align:middle"><?= $totalPending ?> pending</span>
    <?php endif; ?>
    <?php if (!empty($pendingWithdraws)): ?>
    <span style="background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3);border-radius:20px;font-size:12px;font-weight:700;padding:3px 10px;margin-left:4px;vertical-align:middle"><?= count($pendingWithdraws) ?> withdraw</span>
    <?php endif; ?>
  </h1>
</div>

<!-- Overview Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon" style="background:rgba(167,139,250,.12);color:#a78bfa">👥</div><div><h3><?= $totalVendors ?></h3><p>Total Vendors</p></div></div>
  <div class="stat-card"><div class="stat-icon" style="background:rgba(52,211,153,.12);color:#34d399">💵</div><div><h3>₹<?= number_format($totalUnpaid,0) ?></h3><p>Total Unpaid</p></div></div>
  <?php if ($totalPending > 0): ?>
  <a href="javascript:void(0)" onclick="goToPendingTab()" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer">
    <div class="stat-icon" style="background:rgba(251,191,36,.12);color:#fbbf24">⏳</div>
    <div><h3><?= $totalPending ?></h3><p>
      <?php
        $parts = [];
        if (!empty($pendingWithdraws)) $parts[] = count($pendingWithdraws).' Withdraw';
        if (count($pendingProducts))   $parts[] = count($pendingProducts).' Approval';
        echo implode(' + ', $parts);
      ?>
      <span style="color:#fbbf24"> →</span></p></div>
  </a>
  <?php else: ?>
  <div class="stat-card"><div class="stat-icon" style="background:rgba(52,211,153,.12);color:#34d399">✅</div><div><h3>0</h3><p>All caught up!</p></div></div>
  <?php endif; ?>
  <div class="stat-card"><div class="stat-icon" style="background:rgba(59,130,246,.12);color:#60a5fa">🛒</div><div><h3><?= $totalSales ?></h3><p>Total Sales</p></div></div>
</div>

<?php
$mpDefaultTab = !empty($pendingWithdraws) ? 'withdraw' : (!empty($pendingProducts) ? 'approvals' : 'vendors');
?>
<style>
.stabs-wrap{display:flex;gap:0;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:5px;margin-bottom:22px;overflow-x:auto;flex-wrap:nowrap}
.stab-btn{padding:9px 18px;border-radius:9px;font-size:13px;font-weight:600;color:var(--muted2);white-space:nowrap;cursor:pointer;border:none;background:none;transition:all .18s;text-decoration:none}
.stab-btn.active,.stab-btn:hover{background:var(--primary);color:#fff}
.spanel{display:none}.spanel.active{display:block}
</style>

<div class="stabs-wrap">
  <?php if (!empty($pendingWithdraws)): ?>
  <button class="stab-btn<?= $mpDefaultTab==='withdraw' ? ' active' : '' ?>" onclick="switchTab('withdraw',this)">💸 Pending Withdrawals (<?= count($pendingWithdraws) ?>)</button>
  <?php endif; ?>
  <?php if (!empty($pendingProducts)): ?>
  <button class="stab-btn<?= $mpDefaultTab==='approvals' ? ' active' : '' ?>" onclick="switchTab('approvals',this)">🕐 Pending Approvals (<?= count($pendingProducts) ?>)</button>
  <?php endif; ?>
  <button class="stab-btn<?= $mpDefaultTab==='vendors' ? ' active' : '' ?>" onclick="switchTab('vendors',this)">👥 All Vendors (<?= $totalVendors ?>)</button>
</div>
<script>
function switchTab(id, el) {
  document.querySelectorAll('.spanel').forEach(function(p){ p.classList.remove('active'); });
  document.querySelectorAll('.stab-btn').forEach(function(b){ b.classList.remove('active'); });
  var panel = document.getElementById('sp-'+id);
  if (panel) panel.classList.add('active');
  if (el) el.classList.add('active');
  history.replaceState(null,'','#'+id);
}
function goToPendingTab() {
  var id = document.querySelector('[onclick*="\'withdraw\'"]') ? 'withdraw' : 'approvals';
  var btn = document.querySelector('[onclick*="\''+id+'\'"]');
  if (btn) switchTab(id, btn);
}
window.addEventListener('DOMContentLoaded', function(){
  var h = location.hash.replace('#','');
  var btn = h ? document.querySelector('[onclick*="\''+h+'\'"]') : null;
  if (btn) switchTab(h, btn);
});
</script>

<!-- Pending Withdraw Requests -->
<?php if (!empty($pendingWithdraws)): ?>
<div id="sp-withdraw" class="spanel<?= $mpDefaultTab==='withdraw' ? ' active' : '' ?>">
<div class="section-card" id="pendingSection" style="border-color:rgba(239,68,68,.3);margin-bottom:20px">
  <h3>💸 Pending Withdrawal Requests (<?= count($pendingWithdraws) ?>)</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Vendor</th><th>UPI ID</th><th>Amount</th><th>Note</th><th>Requested</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($pendingWithdraws as $pw): ?>
      <tr>
        <td><strong><?= clean($pw['uname']) ?></strong><br><small style="color:var(--muted)"><?= clean($pw['uemail']) ?></small></td>
        <td style="font-size:13px"><?= clean($pw['payout_upi'] ?: '—') ?></td>
        <td style="color:#34d399;font-weight:700">₹<?= number_format($pw['amount'],2) ?></td>
        <td style="color:var(--muted);font-size:12px"><?= clean($pw['note'] ?: '—') ?></td>
        <td style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($pw['created_at'])) ?></td>
        <td style="display:flex;gap:6px">
          <form method="POST" style="display:flex;gap:6px;align-items:center">
            <?= csrf_field() ?>
            <input type="hidden" name="approve_withdraw" value="<?= $pw['id'] ?>">
            <input class="form-control" type="text" name="admin_note" placeholder="Payment ref" style="width:120px;font-size:11px;padding:5px 8px">
            <button type="submit" class="btn btn-success btn-sm confirm-action" data-confirm="Approve ₹<?= number_format($pw['amount'],2) ?> withdrawal for <?= clean($pw['uname']) ?>?">✅ Pay</button>
          </form>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="reject_withdraw" value="<?= $pw['id'] ?>">
            <input type="hidden" name="admin_note" value="Rejected by admin">
            <button type="submit" class="btn btn-danger btn-sm confirm-action" data-confirm="Reject this request?">❌</button>
          </form>
          <a href="?vendor=<?= $pw['user_id'] ?>" class="btn btn-outline btn-sm">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php endif; ?>

<!-- Pending Product Approvals -->
<?php if (!empty($pendingProducts)): ?>
<div id="sp-approvals" class="spanel<?= $mpDefaultTab==='approvals' ? ' active' : '' ?>">
<div class="section-card" id="pendingApprovals" style="border-color:rgba(251,191,36,.3);margin-bottom:20px;scroll-margin-top:20px">
  <h3>🕐 Pending Product Approvals (<?= count($pendingProducts) ?>)</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th>Vendor</th><th>Seller Price</th><th>Buyer Sees</th><th>Delivery</th><th>Submitted</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($pendingProducts as $pp): ?>
      <tr>
        <td>
          <strong><?= clean($pp['title']) ?></strong>
          <?php if ($pp['short_desc']): ?><br><small style="color:var(--muted)"><?= clean(mb_strimwidth($pp['short_desc'],0,60,'…')) ?></small><?php endif; ?>
        </td>
        <td><a href="?vendor=<?= $pp['seller_id'] ?>" style="color:#a78bfa"><?= clean($pp['sname']) ?></a><br><small style="color:var(--muted)"><?= clean($pp['semail']) ?></small></td>
        <td>₹<?= number_format($pp['seller_base_price'] ?: $pp['price']) ?></td>
        <td>₹<?= number_format($pp['price']) ?></td>
        <td>
          <?php if (!$pp['file_path']): ?>
            <span style="color:var(--danger);font-size:12px">— missing</span>
          <?php elseif (preg_match('~^https?://~i', $pp['file_path'])): ?>
            <a href="<?= clean($pp['file_path']) ?>" target="_blank" class="btn btn-outline btn-sm" style="padding:2px 10px;font-size:11px">🔗 Visit Link</a>
          <?php else: ?>
            <a href="<?= SITE_URL ?>/admin/download_file.php?id=<?= $pp['id'] ?>" class="btn btn-outline btn-sm" style="padding:2px 10px;font-size:11px">⬇ Download</a>
          <?php endif; ?>
        </td>
        <td style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($pp['created_at'])) ?></td>
        <td style="display:flex;gap:6px;flex-wrap:wrap">
          <a href="<?= SITE_URL ?>/admin/products.php?edit=<?= $pp['id'] ?>#product-form" class="btn btn-outline btn-sm" target="_blank">✏️ Edit / Preview</a>
          <a href="?approve_vendor=<?= $pp['id'] ?>&t=<?= csrf_token() ?>" class="btn btn-success btn-sm confirm-action" data-confirm="Approve this listing?">✅ Approve</a>
          <button type="button" class="btn btn-danger btn-sm"
            onclick="var r=prompt('Reject reason:'); if(r===null)return; document.getElementById('rfm<?= $pp['id'] ?>').reject_reason.value=r; document.getElementById('rfm<?= $pp['id'] ?>').submit()">❌ Reject</button>
          <form id="rfm<?= $pp['id'] ?>" method="POST" style="display:none">
            <?= csrf_field() ?><input type="hidden" name="reject_vendor" value="<?= $pp['id'] ?>"><input type="hidden" name="reject_reason" value="">
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php endif; ?>

<!-- All Vendors List -->
<div id="sp-vendors" class="spanel<?= $mpDefaultTab==='vendors' ? ' active' : '' ?>">
<div class="section-card">
  <h3>All Vendors (<?= $totalVendors ?>)</h3>
  <?php if (empty($vendors)): ?>
  <p style="color:var(--muted);font-size:13px;padding:20px 0">No vendors yet. Users who list products will appear here.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Vendor</th><th>UPI ID</th><th>Products</th><th>Sales</th><th>Total Earned</th><th>Unpaid</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($vendors as $v): ?>
      <tr>
        <td>
          <strong><?= clean($v['name']) ?></strong><br>
          <small style="color:var(--muted)"><?= clean($v['email']) ?></small>
        </td>
        <td style="font-size:13px"><?= $v['payout_upi'] ? clean($v['payout_upi']) : '<span style="color:var(--danger);font-size:12px">Not set</span>' ?></td>
        <td>
          <?= (int)$v['total_products'] ?>
          <?php if ($v['pending_products'] > 0): ?>
          <span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24;font-size:10px;margin-left:4px"><?= $v['pending_products'] ?> pending</span>
          <?php endif; ?>
        </td>
        <td><?= (int)$v['total_sales'] ?></td>
        <td>₹<?= number_format($v['total_earned'],2) ?></td>
        <td style="color:#34d399;font-weight:700">₹<?= number_format($v['unpaid'],2) ?></td>
        <td><a href="?vendor=<?= $v['id'] ?>" class="btn btn-primary btn-sm">Manage →</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>