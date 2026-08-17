<?php
$pageTitle = 'Vendor Payouts';
require_once __DIR__ . '/includes/admin_header.php';

// Mark ALL of one seller's currently-unpaid earnings as paid (admin has sent the money manually).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid_seller'])) {
    verifyCsrf(SITE_URL.'/admin/payouts.php');
    $sellerId = (int)$_POST['mark_paid_seller'];
    $pdo->prepare("UPDATE seller_earnings SET payout_status='paid', paid_at=NOW() WHERE seller_id=? AND payout_status='unpaid'")->execute([$sellerId]);
    flash('success', 'Marked as paid. Make sure you actually sent the money to their UPI ID first!');
    redirect(SITE_URL.'/admin/payouts.php');
}

// Mark a single earning row as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid_earning'])) {
    verifyCsrf(SITE_URL.'/admin/payouts.php');
    $eid = (int)$_POST['mark_paid_earning'];
    $pdo->prepare("UPDATE seller_earnings SET payout_status='paid', paid_at=NOW() WHERE id=? AND payout_status='unpaid'")->execute([$eid]);
    flash('success', 'Earning marked as paid.');
    redirect(SITE_URL.'/admin/payouts.php'.(!empty($_POST['back_seller']) ? '?seller='.(int)$_POST['back_seller'] : ''));
}

// ---- SELLER DETAIL VIEW ----
if (isset($_GET['seller'])) {
    $sid = (int)$_GET['seller'];
    $seller = $pdo->prepare("SELECT * FROM users WHERE id=?"); $seller->execute([$sid]); $seller = $seller->fetch();
    if (!$seller) { flash('error','Seller not found.'); redirect(SITE_URL.'/admin/payouts.php'); }

    $rows = $pdo->prepare("SELECT se.*, p.title, o.order_ref FROM seller_earnings se
        JOIN products p ON p.id=se.product_id JOIN orders o ON o.id=se.order_id
        WHERE se.seller_id=? ORDER BY se.created_at DESC");
    $rows->execute([$sid]);
    $rows = $rows->fetchAll();

    $unpaidTotal = 0; foreach ($rows as $r) if ($r['payout_status']==='unpaid') $unpaidTotal += (float)$r['seller_amount'];
    ?>
    <div class="admin-topbar">
      <h1>Payouts: <?= clean($seller['name']) ?></h1>
      <a href="<?= SITE_URL ?>/admin/payouts.php" class="btn btn-outline btn-sm">← Back</a>
    </div>
    <div class="section-card" style="margin-bottom:20px">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div>
          <p style="color:var(--muted);font-size:13px">Email: <?= clean($seller['email']) ?></p>
          <p style="color:var(--muted);font-size:13px">UPI ID: <strong><?= clean($seller['payout_upi'] ?: '— not set —') ?></strong></p>
          <?php if ($seller['payout_note']): ?><p style="color:var(--muted);font-size:13px">Note: <?= clean($seller['payout_note']) ?></p><?php endif; ?>
        </div>
        <div style="text-align:right">
          <div style="color:var(--muted);font-size:12px">Unpaid Balance</div>
          <div style="font-size:26px;font-weight:800;color:#34d399">₹<?= number_format($unpaidTotal,2) ?></div>
          <?php if ($unpaidTotal > 0): ?>
          <form method="POST" style="margin-top:8px">
            <?= csrf_field() ?>
            <input type="hidden" name="mark_paid_seller" value="<?= $sid ?>">
            <button type="submit" class="btn btn-success btn-sm confirm-action" data-confirm="Confirm you already sent ₹<?= number_format($unpaidTotal,2) ?> to <?= clean($seller['payout_upi'] ?: 'this seller') ?>? This marks the full balance as paid.">💸 Mark Full Balance Paid</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="section-card">
      <h3>All Sales</h3>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order Ref</th><th>Product</th><th>Buyer Paid</th><th>Seller Earned</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
          <tbody>
            <?php if (empty($rows)): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">No sales yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td style="font-family:monospace;font-size:12px"><?= clean($r['order_ref']) ?></td>
              <td><?= clean($r['title']) ?></td>
              <td>₹<?= number_format($r['buyer_amount'],2) ?></td>
              <td style="color:#34d399">₹<?= number_format($r['seller_amount'],2) ?></td>
              <td><?= $r['payout_status']==='paid' ? '<span class="badge badge-active">Paid</span>' : '<span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24">Unpaid</span>' ?></td>
              <td style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
              <td>
                <?php if ($r['payout_status']==='unpaid'): ?>
                <form method="POST">
                  <?= csrf_field() ?>
                  <input type="hidden" name="mark_paid_earning" value="<?= $r['id'] ?>">
                  <input type="hidden" name="back_seller" value="<?= $sid ?>">
                  <button type="submit" class="btn btn-outline btn-sm">Mark Paid</button>
                </form>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/admin_footer.php'; exit;
}

// ---- SELLER SUMMARY LIST ----
$sellers = $pdo->query("SELECT u.id, u.name, u.email, u.payout_upi,
    COALESCE(SUM(se.seller_amount),0) total_earned,
    COALESCE(SUM(CASE WHEN se.payout_status='unpaid' THEN se.seller_amount ELSE 0 END),0) unpaid_balance,
    COUNT(se.id) total_sales
    FROM seller_earnings se JOIN users u ON u.id=se.seller_id
    GROUP BY u.id ORDER BY unpaid_balance DESC")->fetchAll();

$totalUnpaid = array_sum(array_column($sellers, 'unpaid_balance'));
?>
<div class="admin-topbar">
  <h1>Vendor Payouts</h1>
</div>
<div class="section-card" style="margin-bottom:20px">
  <p style="color:var(--muted);font-size:13px;line-height:1.7">
    Sellers earn <?= 100 - (int)vendorCommissionPercent($pdo) ?>% of their own listed price the moment a sale is marked paid — it shows in <em>their</em> dashboard balance immediately.
    Sending the actual money is manual: transfer to the seller's UPI ID below, then click "Mark Paid" to clear their balance. Total currently owed to all sellers: <strong style="color:#34d399">₹<?= number_format($totalUnpaid,2) ?></strong>.
  </p>
</div>
<div class="section-card">
  <h3>Sellers (<?= count($sellers) ?>)</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Seller</th><th>UPI ID</th><th>Total Sales</th><th>Total Earned</th><th>Unpaid Balance</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($sellers)): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">No vendor sales yet.</td></tr><?php endif; ?>
        <?php foreach ($sellers as $s): ?>
        <tr>
          <td><strong><?= clean($s['name']) ?></strong><br><small style="color:var(--muted)"><?= clean($s['email']) ?></small></td>
          <td><?= clean($s['payout_upi'] ?: '—') ?></td>
          <td><?= (int)$s['total_sales'] ?></td>
          <td>₹<?= number_format($s['total_earned'],2) ?></td>
          <td style="color:#34d399"><strong>₹<?= number_format($s['unpaid_balance'],2) ?></strong></td>
          <td><a href="?seller=<?= $s['id'] ?>" class="btn btn-primary btn-sm">Manage</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
