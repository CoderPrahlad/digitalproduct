<?php
$pageTitle = 'Coupons';
require_once __DIR__ . '/includes/admin_header.php';

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL . '/admin/coupons.php');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code     = strtoupper(trim($_POST['code'] ?? ''));
        $type     = $_POST['type'] === 'fixed' ? 'fixed' : 'percent';
        $value    = max(0, (float)($_POST['value'] ?? 0));
        $minOrder = max(0, (float)($_POST['min_order'] ?? 0));
        $maxUses  = (int)($_POST['max_uses'] ?? 0);
        $expires  = $_POST['expires_at'] ? date('Y-m-d H:i:s', strtotime($_POST['expires_at'])) : null;
        if ($code && $value > 0) {
            try {
                $pdo->prepare("INSERT INTO coupons (code,type,value,min_order,max_uses,expires_at,active) VALUES (?,?,?,?,?,?,1)")
                    ->execute([$code, $type, $value, $minOrder, $maxUses ?: null, $expires]);
                flash('success', "Coupon $code created!");
            } catch (Exception $e) {
                flash('error', 'Coupon code already exists.');
            }
        } else { flash('error', 'Code and value are required.'); }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE coupons SET active = 1-active WHERE id=?")->execute([$id]);
        flash('success', 'Status updated.');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM coupons WHERE id=?")->execute([$id]);
        flash('success', 'Coupon deleted.');
    }
    redirect(SITE_URL . '/admin/coupons.php');
}

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();
?>
<div class="admin-topbar">
  <h1>Coupon Codes</h1>
  <button class="btn btn-primary btn-sm" onclick="document.getElementById('addCouponModal').style.display='flex'">+ Add Coupon</button>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Code</th><th>Type</th><th>Value</th><th>Min Order</th><th>Used / Max</th><th>Expires</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php if (empty($coupons)): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:40px">No coupons yet. Create your first one!</td></tr>
      <?php endif; ?>
      <?php foreach ($coupons as $c): ?>
      <tr>
        <td><code style="font-size:14px;color:#a78bfa;background:rgba(124,58,237,.1);padding:3px 8px;border-radius:4px"><?= clean($c['code']) ?></code></td>
        <td><?= $c['type'] === 'percent' ? 'Percentage' : 'Fixed ₹' ?></td>
        <td><strong><?= $c['type'] === 'percent' ? $c['value'].'%' : '₹'.number_format($c['value']) ?> OFF</strong></td>
        <td><?= $c['min_order'] > 0 ? '₹'.number_format($c['min_order']) : '—' ?></td>
        <td><?= $c['used_count'] ?> / <?= $c['max_uses'] ?? '∞' ?></td>
        <td style="font-size:12px;color:var(--muted)"><?= $c['expires_at'] ? date('d M Y', strtotime($c['expires_at'])) : 'No expiry' ?></td>
        <td><span class="badge badge-<?= $c['active'] ? 'paid' : 'rejected' ?>"><?= $c['active'] ? 'Active' : 'Inactive' ?></span></td>
        <td style="display:flex;gap:6px">
          <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button class="btn btn-outline btn-sm"><?= $c['active'] ? 'Disable' : 'Enable' ?></button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete coupon <?= clean($c['code']) ?>?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button class="btn btn-danger btn-sm">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Add Coupon Modal -->
<div id="addCouponModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:28px;width:100%;max-width:480px;margin:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Create Coupon</h3>
      <button onclick="document.getElementById('addCouponModal').style.display='none'" style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer">✕</button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label>Coupon Code *</label>
        <input class="form-control" type="text" name="code" placeholder="SAVE20" style="text-transform:uppercase" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label>Discount Type *</label>
          <select class="form-control" name="type">
            <option value="percent">Percentage (%)</option>
            <option value="fixed">Fixed Amount (₹)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Value *</label>
          <input class="form-control" type="number" name="value" placeholder="20" min="1" required>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label>Min Order (₹)</label>
          <input class="form-control" type="number" name="min_order" placeholder="0" min="0">
        </div>
        <div class="form-group">
          <label>Max Uses (blank = ∞)</label>
          <input class="form-control" type="number" name="max_uses" placeholder="100" min="1">
        </div>
      </div>
      <div class="form-group">
        <label>Expiry Date (optional)</label>
        <input class="form-control" type="date" name="expires_at">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create Coupon</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
