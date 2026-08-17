<?php
$pageTitle = 'Orders';
require_once __DIR__ . '/includes/admin_header.php';
require_once dirname(__DIR__) . '/mail/Mailer.php';

// ---- BULK ACTION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    verifyCsrf(SITE_URL.'/admin/orders.php');
    $bulkAction = $_POST['bulk_action'];
    $ids = array_map('intval', (array)($_POST['order_ids'] ?? []));

    if (!empty($ids) && in_array($bulkAction, ['approve', 'reject', 'delete'])) {
        $approved = 0; $rejected = 0; $deleted = 0;

        if ($bulkAction === 'delete') {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $delStmt = $pdo->prepare("DELETE FROM orders WHERE id IN ($in)");
            $delStmt->execute($ids);
            $deleted = $delStmt->rowCount();
        }

        foreach (($bulkAction === 'delete' ? [] : $ids) as $oid) {
            // Only process pending orders
            $o = $pdo->prepare("SELECT o.*, u.name uname, u.email uemail, p.title ptitle
                FROM orders o
                JOIN users u ON u.id = o.user_id
                JOIN products p ON p.id = o.product_id
                WHERE o.id = ? AND o.status = 'pending' LIMIT 1");
            $o->execute([$oid]);
            $order = $o->fetch();
            if (!$order) continue;

            if ($bulkAction === 'approve') {
                $licenseKey  = generateLicenseKey();
                $dlToken     = bin2hex(random_bytes(32));
                $tokenExpiry = date('Y-m-d H:i:s', strtotime('+72 hours'));
                $pdo->prepare("UPDATE orders SET status='paid', license_key=?, download_token=?, token_expires=? WHERE id=?")
                    ->execute([$licenseKey, $dlToken, $tokenExpiry, $oid]);
                creditVendorEarning($pdo, $oid);
                creditReferralCommission($pdo, $oid);
                $dlUrl = SITE_URL . '/download.php?token=' . $dlToken . '&ref=' . $order['order_ref'];
                $emailOk = Mailer::sendDelivery($order['uemail'], $order['uname'], $order['ptitle'], $licenseKey, $dlUrl);
                $pdo->prepare("UPDATE orders SET email_sent=? WHERE id=?")->execute([$emailOk ? 1 : 0, $oid]);
                notifyTelegram("✅ Bulk Approved\nRef: {$order['order_ref']}\n👤 {$order['uname']}\n💰 ₹" . number_format($order['amount']));
                $approved++;
            } elseif ($bulkAction === 'reject') {
                $pdo->prepare("UPDATE orders SET status='rejected' WHERE id=?")->execute([$oid]);
                $rejected++;
            }
        }

        $msgs = [];
        if ($approved) $msgs[] = "$approved order(s) approved & email delivered.";
        if ($rejected) $msgs[] = "$rejected order(s) rejected.";
        if ($deleted)  $msgs[] = "$deleted order(s) permanently deleted.";
        if ($msgs) flash('success', implode(' ', $msgs));
        else       flash('error', 'No matching orders were found in the selection.');
    }

    $qs = http_build_query(['status' => $_GET['status'] ?? 'all', 'q' => $_GET['q'] ?? '']);
    redirect(SITE_URL . '/admin/orders.php?' . $qs);
}

// ---- SINGLE ORDER VIEW + APPROVE/REJECT ----
if (isset($_GET['id'])) {
    $o = $pdo->prepare("SELECT o.*, u.name uname, u.email uemail, p.title ptitle, p.file_path pfile
        FROM orders o JOIN users u ON u.id=o.user_id JOIN products p ON p.id=o.product_id
        WHERE o.id=? LIMIT 1");
    $o->execute([(int)$_GET['id']]);
    $order = $o->fetch();
    if (!$order) { flash('error', 'Order not found'); redirect(SITE_URL.'/admin/orders.php'); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf(SITE_URL.'/admin/orders.php?id='.$order['id']);
        $action = $_POST['action'] ?? '';

        if ($action === 'approve' && $order['status'] === 'pending') {
            $licenseKey  = generateLicenseKey();
            $dlToken     = bin2hex(random_bytes(32));
            $tokenExpiry = date('Y-m-d H:i:s', strtotime('+72 hours'));
            $pdo->prepare("UPDATE orders SET status='paid', license_key=?, download_token=?, token_expires=? WHERE id=?")
                ->execute([$licenseKey, $dlToken, $tokenExpiry, $order['id']]);
            creditVendorEarning($pdo, $order['id']);
            creditReferralCommission($pdo, $order['id']);
            $dlUrl = SITE_URL . '/download.php?token=' . $dlToken . '&ref=' . $order['order_ref'];
            $emailSent = Mailer::sendDelivery($order['uemail'], $order['uname'], $order['ptitle'], $licenseKey, $dlUrl);
            $pdo->prepare("UPDATE orders SET email_sent=? WHERE id=?")->execute([$emailSent ? 1 : 0, $order['id']]);
            notifyTelegram("✅ Order Approved\nRef: {$order['order_ref']}\n👤 {$order['uname']}\n💰 ₹" . number_format($order['amount']));
            if ($emailSent) flash('success', 'Order approved! Email sent to ' . $order['uemail']);
            else flash('error', 'Order approved but delivery email FAILED. Check SMTP settings.');

        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE orders SET status='rejected' WHERE id=?")->execute([$order['id']]);
            flash('success', 'Order rejected.');

        } elseif ($action === 'resend_email' && in_array($order['status'], ['paid','delivered'])) {
            $dlUrl = SITE_URL . '/download.php?token=' . $order['download_token'] . '&ref=' . $order['order_ref'];
            $emailSent = Mailer::sendDelivery($order['uemail'], $order['uname'], $order['ptitle'], $order['license_key'], $dlUrl);
            $pdo->prepare("UPDATE orders SET email_sent=? WHERE id=?")->execute([$emailSent ? 1 : 0, $order['id']]);
            if ($emailSent) flash('success', 'Delivery email re-sent to ' . $order['uemail']);
            else flash('error', 'Resend failed. Check SMTP settings.');

        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$order['id']]);
            flash('success', 'Order deleted.');
            redirect(SITE_URL . '/admin/orders.php');

        } elseif ($action === 'cancel_refund' && in_array($order['status'], ['paid','delivered'])) {
            $reason = clean($_POST['refund_reason'] ?? 'Refunded by admin');
            $pdo->prepare("UPDATE orders SET status='refunded', refund_reason=?, token_expires=NOW() WHERE id=?")
                ->execute([$reason, $order['id']]);
            // Credit the amount back as store-credit wallet balance instead of a live payment-gateway refund
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?")
                ->execute([$order['amount'], $order['user_id']]);
            $newBal = $pdo->prepare("SELECT wallet_balance FROM users WHERE id=?");
            $newBal->execute([$order['user_id']]);
            $newBal = (float)$newBal->fetchColumn();
            $pdo->prepare("INSERT INTO wallet_transactions (user_id,type,amount,description,ref_id,balance_after) VALUES (?,?,?,?,?,?)")
                ->execute([$order['user_id'], 'credit', $order['amount'], 'Refund for order ' . $order['order_ref'] . ' — ' . $reason, $order['order_ref'], $newBal]);
            notifyTelegram("↩️ Order Refunded\nRef: {$order['order_ref']}\n👤 {$order['uname']}\n💰 ₹" . number_format($order['amount']) . "\nReason: $reason");
            flash('success', 'Order refunded. ₹' . number_format($order['amount']) . ' credited to the buyer\'s store wallet and the download link was revoked.');
        }
        redirect(SITE_URL . '/admin/orders.php?id=' . $order['id']);
    }

    // Re-fetch after update
    $o->execute([(int)$_GET['id']]); $order = $o->fetch();
    ?>
    <div class="admin-topbar">
      <h1>Order: <?= clean($order['order_ref']) ?></h1>
      <a href="<?= SITE_URL ?>/admin/orders.php" class="btn btn-outline btn-sm">← Back to Orders</a>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div class="section-card">
        <h3>Order Details</h3>
        <?php
        $detailRows = [
          ['User',       clean($order['uname']).' ('.$order['uemail'].')'],
          ['Product',    clean($order['ptitle'])],
          ['Amount',     '₹'.number_format($order['amount'])],
          ['Method',     clean($order['payment_method'])],
          ['UTR',        clean($order['utr_number'] ?: '—')],
          ['Razorpay ID',clean($order['razorpay_payment_id'] ?: '—')],
        ];
        if ($order['payment_method'] === 'usdt') {
          $detailRows[] = ['Crypto Gateway', clean($order['crypto_gateway_name'] ?? '') ?: '—'];
          $detailRows[] = ['Crypto Network', clean($order['crypto_network'] ?? '') ?: '—'];
          $detailRows[] = ['Crypto Address', clean($order['crypto_address'] ?? '') ?: '—'];
          $detailRows[] = ['Crypto Amount',  isset($order['crypto_amount']) && $order['crypto_amount'] !== null ? number_format((float)$order['crypto_amount'],2) : '—'];
        }
        $detailRows = array_merge($detailRows, [
          ['Status',      '<span class="badge badge-'.$order['status'].'">'.ucfirst($order['status']).'</span>'],
          ['License Key', $order['license_key'] ? '<code>'.clean($order['license_key']).'</code>' : '—'],
          ['Downloads',   $order['download_count'].'/3'],
          ['Date',        date('d M Y H:i', strtotime($order['created_at']))],
        ]);
        ?>
        <?php foreach ($detailRows as $row): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:14px;gap:12px">
          <span style="color:var(--muted);flex-shrink:0"><?= $row[0] ?></span>
          <span style="text-align:right;word-break:break-all"><?= $row[1] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if ($order['payment_proof']): ?>
        <div style="margin-top:14px">
          <p style="color:var(--muted);font-size:13px;margin-bottom:8px">Payment Proof:</p>
          <a href="<?= SITE_URL ?>/uploads/proofs/<?= clean($order['payment_proof']) ?>" target="_blank" class="btn btn-outline btn-sm">View Screenshot</a>
        </div>
        <?php endif; ?>
      </div>

      <div class="section-card">
        <h3>Actions</h3>
        <?php if ($order['status'] === 'pending'): ?>
          <p style="color:var(--muted);font-size:13px;margin-bottom:16px">Review UTR / screenshot and approve if payment is confirmed.</p>
          <form method="POST" style="margin-bottom:10px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-success btn-block confirm-action" data-confirm="Approve and send delivery email to <?= clean($order['uemail']) ?>?">
              ✅ Approve &amp; Deliver
            </button>
          </form>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <button type="submit" class="btn btn-danger btn-block confirm-action" data-confirm="Reject this order?">
              ❌ Reject
            </button>
          </form>
        <?php elseif (in_array($order['status'], ['paid','delivered'])): ?>
          <div class="alert alert-success">Payment verified. File delivered.</div>
          <?php if (empty($order['email_sent'])): ?>
          <div class="alert alert-error" style="margin-top:10px">⚠️ Delivery email failed to send.</div>
          <?php endif; ?>
          <?php if ($order['download_token']): ?>
          <a href="<?= SITE_URL ?>/download.php?token=<?= urlencode($order['download_token']) ?>&ref=<?= urlencode($order['order_ref']) ?>"
             class="btn btn-outline btn-sm btn-block" style="margin-top:10px">Test Download Link</a>
          <?php endif; ?>
          <form method="POST" style="margin-top:10px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resend_email">
            <button type="submit" class="btn btn-outline btn-block confirm-action" data-confirm="Resend the delivery email to <?= clean($order['uemail']) ?>?">
              📧 Resend Delivery Email
            </button>
          </form>
          <form method="POST" style="margin-top:10px" onsubmit="return promptRefundReason(this)">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel_refund">
            <input type="hidden" name="refund_reason" value="">
            <button type="submit" class="btn btn-danger btn-block">
              ↩️ Cancel Order &amp; Refund
            </button>
          </form>
        <?php elseif ($order['status'] === 'refunded'): ?>
          <div class="alert alert-error">This order was refunded.</div>
          <?php if (!empty($order['refund_reason'])): ?>
          <p style="color:var(--muted);font-size:13px;margin-top:8px">Reason: <?= clean($order['refund_reason']) ?></p>
          <?php endif; ?>
          <p style="color:var(--muted);font-size:13px;margin-top:4px">₹<?= number_format($order['amount']) ?> was credited to the buyer's store wallet. Download link revoked.</p>
        <?php else: ?>
          <div class="alert alert-error">Order rejected.</div>
        <?php endif; ?>

        <form method="POST" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)" onsubmit="return confirm('Permanently delete this order? This cannot be undone.')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="btn btn-danger btn-block">🗑️ Delete Order</button>
        </form>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/admin_footer.php'; exit;
}

// ---- ORDERS LIST ----
pruneOldOrders($pdo); // keeps table capped at 500 — deletes oldest resolved orders once exceeded
$status   = $_GET['status'] ?? 'all';
$search   = trim($_GET['q'] ?? '');
$statuses = ['all','pending','paid','delivered','rejected','refunded'];

$conditions = [];
$bindings   = [];
if ($status !== 'all') { $conditions[] = "o.status = ?"; $bindings[] = $status; }
if ($search) {
    $conditions[] = "(o.order_ref LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR o.utr_number LIKE ? OR o.razorpay_payment_id LIKE ?)";
    $like = "%$search%";
    array_push($bindings, $like, $like, $like, $like, $like);
}
$joinWhere = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) c FROM orders o JOIN users u ON u.id=o.user_id JOIN products p ON p.id=o.product_id $joinWhere");
$countStmt->execute($bindings);
$totalCount = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$listStmt = $pdo->prepare("SELECT o.*, u.name uname, p.title ptitle FROM orders o
    JOIN users u ON u.id=o.user_id JOIN products p ON p.id=o.product_id
    $joinWhere ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset");
$listStmt->execute($bindings);
$orders = $listStmt->fetchAll();

$pendingCount = (int)$pdo->query("SELECT COUNT(*) c FROM orders WHERE status='pending'")->fetch()['c'];
?>

<style>
/* Bulk action bar */
.bulk-bar {
  display:none; align-items:center; gap:12px; flex-wrap:wrap;
  background:rgba(124,58,237,.1); border:1px solid rgba(124,58,237,.3);
  border-radius:10px; padding:12px 18px; margin-bottom:14px; font-size:14px;
}
.bulk-bar.show { display:flex; }
.bulk-count { font-weight:800; color:#a78bfa; }
.bulk-btn { padding:8px 18px; font-size:13px; font-weight:600; border-radius:8px; cursor:pointer; transition:.18s; }
.bulk-approve { background:rgba(16,185,129,.15); color:#10b981; border:1px solid rgba(16,185,129,.3); }
.bulk-approve:hover { background:rgba(16,185,129,.28); }
.bulk-reject  { background:rgba(239,68,68,.1); color:#ef4444; border:1px solid rgba(239,68,68,.25); }
.bulk-reject:hover { background:rgba(239,68,68,.22); }
.bulk-clear   { background:none; color:var(--muted); border:1px solid var(--border); }
.bulk-clear:hover { color:var(--text); border-color:var(--text); }
.bulk-delete  { background:rgba(239,68,68,.18); color:#fff; border:1px solid #ef4444; }
.bulk-delete:hover { background:#ef4444; }

/* Checkbox column */
th.cb-col, td.cb-col { width:44px; text-align:center; padding-left:8px !important; }
.order-cb { width:16px; height:16px; cursor:pointer; accent-color:#7c3aed; }
.no-cb { color:var(--border); font-size:18px; line-height:1; }
</style>

<div class="admin-topbar" style="flex-wrap:wrap;gap:12px">
  <h1>
    Orders
    <span style="color:var(--muted);font-weight:400;font-size:14px">(<?= number_format($totalCount) ?> total)</span>
    <?php if ($pendingCount > 0): ?>
    <span style="background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.3);border-radius:20px;font-size:12px;font-weight:700;padding:3px 10px;margin-left:6px;vertical-align:middle"><?= $pendingCount ?> pending</span>
    <?php endif; ?>
  </h1>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php foreach ($statuses as $s): ?>
      <a href="?status=<?= $s ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $status===$s ? 'btn-primary' : 'btn-outline' ?>"><?= ucfirst($s) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Search -->
<form method="GET" style="margin-bottom:14px;display:flex;gap:8px">
  <input type="hidden" name="status" value="<?= clean($status) ?>">
  <input type="text" name="q" value="<?= clean($search) ?>" placeholder="Search by ref, name, email, UTR, payment ID..."
    style="flex:1;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:9px 14px;color:var(--text);font-size:13px">
  <button type="submit" class="btn btn-primary btn-sm">🔍 Search</button>
  <?php if ($search): ?><a href="?status=<?= clean($status) ?>" class="btn btn-outline btn-sm">✕ Clear</a><?php endif; ?>
</form>
<?php if ($search): ?>
<p style="color:var(--muted);font-size:13px;margin-bottom:12px">Results for "<strong><?= clean($search) ?></strong>" — <?= $totalCount ?> found</p>
<?php endif; ?>

<!-- Bulk action bar (outside table form so it submits cleanly) -->
<div class="bulk-bar" id="bulkBar">
  <span>Selected: <span class="bulk-count" id="selCount">0</span> order(s)</span>
  <button type="button" class="bulk-btn bulk-approve" onclick="submitBulk('approve')">✅ Approve Selected</button>
  <button type="button" class="bulk-btn bulk-reject"  onclick="submitBulk('reject')">❌ Reject Selected</button>
  <button type="button" class="bulk-btn bulk-delete"  onclick="submitBulk('delete')">🗑️ Delete Selected</button>
  <button type="button" class="bulk-btn bulk-clear"   onclick="clearAll()">✕ Clear</button>
</div>

<!-- Hidden bulk form — separate from the table, submitted via JS -->
<form method="POST" action="<?= SITE_URL ?>/admin/orders.php?status=<?= urlencode($status) ?>&q=<?= urlencode($search) ?>" id="bulkForm" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
  <div id="bulkIdsContainer"></div>
</form>

<div class="table-wrap">
  <table id="ordersTable">
    <thead>
      <tr>
        <th class="cb-col">
          <input type="checkbox" class="order-cb" id="checkAll" title="Select all visible orders" onchange="toggleAll(this)">
        </th>
        <th>Ref</th><th>User</th><th>Product</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:40px">No orders found.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td class="cb-col">
          <input type="checkbox" class="order-cb row-cb" value="<?= $o['id'] ?>" onchange="updateBulk()">
        </td>
        <td style="font-family:monospace;font-size:12px"><?= clean($o['order_ref']) ?></td>
        <td><?= clean($o['uname']) ?></td>
        <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= clean($o['ptitle']) ?>"><?= clean($o['ptitle']) ?></td>
        <td><strong style="color:#a78bfa">₹<?= number_format($o['amount']) ?></strong></td>
        <td><span style="font-size:12px;text-transform:capitalize"><?= clean($o['payment_method']) ?></span></td>
        <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
        <td style="font-size:12px;color:var(--muted)"><?= date('d M, H:i', strtotime($o['created_at'])) ?></td>
        <td><a href="?id=<?= $o['id'] ?>" class="btn btn-primary btn-sm">Manage</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:18px;flex-wrap:wrap">
  <a href="?status=<?= $status ?>&q=<?= urlencode($search) ?>&page=<?= max(1,$page-1) ?>" class="btn btn-sm btn-outline" <?= $page<=1?'style="pointer-events:none;opacity:.4"':'' ?>>← Prev</a>
  <span style="color:var(--muted);font-size:13px">Page <?= $page ?> of <?= $totalPages ?></span>
  <a href="?status=<?= $status ?>&q=<?= urlencode($search) ?>&page=<?= min($totalPages,$page+1) ?>" class="btn btn-sm btn-outline" <?= $page>=$totalPages?'style="pointer-events:none;opacity:.4"':'' ?>>Next →</a>
</div>
<?php endif; ?>

<script>
function promptRefundReason(form) {
  var reason = prompt('Reason for cancelling & refunding this order (buyer\'s amount will be credited to their store wallet):', 'Customer requested cancellation');
  if (reason === null) return false; // user cancelled the prompt
  form.refund_reason.value = reason.trim() || 'Refunded by admin';
  return confirm('Refund this order? ₹ will be credited to the buyer\'s store wallet and the download link revoked.');
}

var selected = new Set();

function updateBulk() {
  selected.clear();
  document.querySelectorAll('.row-cb:checked').forEach(function(cb) {
    selected.add(cb.value);
  });
  document.getElementById('selCount').textContent = selected.size;
  document.getElementById('bulkBar').classList.toggle('show', selected.size > 0);
}

function toggleAll(master) {
  document.querySelectorAll('.row-cb').forEach(function(cb) {
    cb.checked = master.checked;
  });
  updateBulk();
}

function clearAll() {
  document.querySelectorAll('.row-cb').forEach(function(cb) { cb.checked = false; });
  var ca = document.getElementById('checkAll');
  if (ca) ca.checked = false;
  selected.clear();
  document.getElementById('selCount').textContent = '0';
  document.getElementById('bulkBar').classList.remove('show');
}

function submitBulk(action) {
  if (selected.size === 0) { alert('Koi order select nahi hai.'); return; }
  var label = action === 'approve'
    ? 'Approve & deliver email to ' + selected.size + ' order(s)?'
    : action === 'reject'
    ? 'Reject ' + selected.size + ' order(s)?'
    : 'Permanently DELETE ' + selected.size + ' order(s)? This cannot be undone.';
  if (!confirm(label)) return;

  /* Set action */
  document.getElementById('bulkActionInput').value = action;

  /* Inject IDs */
  var container = document.getElementById('bulkIdsContainer');
  container.innerHTML = '';
  selected.forEach(function(id) {
    var inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = 'order_ids[]';
    inp.value = id;
    container.appendChild(inp);
  });

  document.getElementById('bulkForm').submit();
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
