<?php
$pageTitle = 'User Details';
require_once __DIR__ . '/includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$u = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$u->execute([$id]);
$u = $u->fetch();
if (!$u) { flash('error','User not found.'); redirect(SITE_URL.'/admin/users.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    verifyCsrf(SITE_URL.'/admin/user_view.php?id='.$id);
    $name  = clean($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $newPass = trim($_POST['new_password'] ?? '');

    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error','Please enter a valid name and email.');
        redirect(SITE_URL.'/admin/user_view.php?id='.$id);
    }

    $dupe = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=? LIMIT 1");
    $dupe->execute([$email, $id]);
    if ($dupe->fetch()) {
        flash('error','Another user already uses that email.');
        redirect(SITE_URL.'/admin/user_view.php?id='.$id);
    }

    if ($newPass !== '') {
        if (strlen($newPass) < 6) {
            flash('error','New password must be at least 6 characters.');
            redirect(SITE_URL.'/admin/user_view.php?id='.$id);
        }
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,password=? WHERE id=?")->execute([$name,$email,$phone,$hash,$id]);
        flash('success','User updated and password changed.');
    } else {
        $pdo->prepare("UPDATE users SET name=?,email=?,phone=? WHERE id=?")->execute([$name,$email,$phone,$id]);
        flash('success','User updated.');
    }
    redirect(SITE_URL.'/admin/user_view.php?id='.$id);
}

// Re-fetch after any update
$u = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$u->execute([$id]);
$u = $u->fetch();

$orders = $pdo->prepare("SELECT o.*, p.title ptitle FROM orders o JOIN products p ON p.id=o.product_id WHERE o.user_id=? ORDER BY o.created_at DESC");
$orders->execute([$id]);
$orders = $orders->fetchAll();

$totalPaid = 0;
foreach ($orders as $o) { if (in_array($o['status'], ['paid','delivered'], true)) $totalPaid += (float)$o['amount']; }

$wishlist = [];
try {
    $w = $pdo->prepare("SELECT w.*, p.title ptitle, p.price pprice FROM wishlist w JOIN products p ON p.id=w.product_id WHERE w.user_id=? ORDER BY w.created_at DESC");
    $w->execute([$id]);
    $wishlist = $w->fetchAll();
} catch (Exception $e) { /* wishlist table may not exist on older installs */ }
?>
<div class="admin-topbar">
  <h1>User: <?= clean($u['name']) ?></h1>
  <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-outline btn-sm">← Back to Users</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
  <div class="section-card">
    <h3>Profile</h3>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:14px">
      <span style="color:var(--muted)">User ID</span><span><?= $u['id'] ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:14px">
      <span style="color:var(--muted)">Joined</span><span><?= date('d M Y, h:i A', strtotime($u['created_at'])) ?></span>
    </div>
    <form method="POST" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_profile">
      <div class="form-group">
        <label>Name</label>
        <input class="form-control" type="text" name="name" required value="<?= clean($u['name']) ?>">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input class="form-control" type="email" name="email" required value="<?= clean($u['email']) ?>">
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input class="form-control" type="text" name="phone" value="<?= clean($u['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Reset Password</label>
        <input class="form-control" type="password" name="new_password" placeholder="Leave blank to keep current password" minlength="6">
        <p class="form-hint">Min 6 characters. Only fill this in if you want to change the user's password.</p>
      </div>
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
  </div>
  <div class="section-card">
    <h3>Summary</h3>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:14px">
      <span style="color:var(--muted)">Total Orders</span><span><?= count($orders) ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:14px">
      <span style="color:var(--muted)">Paid / Delivered Orders</span>
      <span style="color:var(--success)"><?= count(array_filter($orders, fn($o)=>in_array($o['status'],['paid','delivered'],true))) ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:14px">
      <span style="color:var(--muted)">Total Paid Amount</span><span>₹<?= number_format($totalPaid) ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:14px">
      <span style="color:var(--muted)">Wishlist Items</span><span><?= count($wishlist) ?></span>
    </div>
  </div>
</div>

<div class="section-card" style="margin-bottom:20px">
  <h3>Order History</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Order Ref</th><th>Product</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">No orders yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= clean($o['order_ref']) ?></td>
          <td><?= clean($o['ptitle']) ?></td>
          <td>₹<?= number_format($o['amount']) ?></td>
          <td><?= clean($o['payment_method']) ?></td>
          <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
          <td style="font-size:12px;color:var(--muted)"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
          <td><a class="btn btn-sm btn-outline" href="orders.php?id=<?= $o['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <h3>Wishlist</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th>Price</th><th>Added</th></tr></thead>
      <tbody>
        <?php if (empty($wishlist)): ?>
          <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:30px">No wishlist items.</td></tr>
        <?php endif; ?>
        <?php foreach ($wishlist as $w): ?>
        <tr>
          <td><?= clean($w['ptitle']) ?></td>
          <td>₹<?= number_format($w['pprice']) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= date('d M Y', strtotime($w['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
