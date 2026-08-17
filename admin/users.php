<?php
$pageTitle = 'Users';
require_once __DIR__ . '/includes/admin_header.php';

// Role filter
$roleFilter = $_GET['role'] ?? 'all';
$whereClause = '';
$params = [];
if (in_array($roleFilter, ['buyer','seller'])) {
    $whereClause = 'WHERE u.role = ?';
    $params[] = $roleFilter;
}

$users = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM orders WHERE user_id=u.id AND status IN ('paid','delivered')) AS orders_paid
    FROM users u
    $whereClause
    ORDER BY u.created_at DESC
");
$users->execute($params);
$users = $users->fetchAll();

// Counts for tabs
$counts = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCount  = array_sum($counts);
$buyerCount  = $counts['buyer']  ?? 0;
$sellerCount = $counts['seller'] ?? 0;
?>
<div class="admin-topbar">
  <h1>Users</h1>
  <span style="color:var(--muted);font-size:13px"><?= count($users) ?> shown</span>
</div>

<!-- Role Filter Tabs -->
<div style="display:flex;gap:8px;margin-bottom:18px">
  <a href="users.php?role=all"
     class="btn btn-sm <?= $roleFilter==='all' ? 'btn-primary' : 'btn-outline' ?>">
    All (<?= $totalCount ?>)
  </a>
  <a href="users.php?role=buyer"
     class="btn btn-sm <?= $roleFilter==='buyer' ? 'btn-primary' : 'btn-outline' ?>">
    🛒 Buyers (<?= $buyerCount ?>)
  </a>
  <a href="users.php?role=seller"
     class="btn btn-sm <?= $roleFilter==='seller' ? 'btn-primary' : 'btn-outline' ?>">
    🏪 Sellers (<?= $sellerCount ?>)
  </a>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Role</th>
        <th>Paid Orders</th>
        <th>Joined</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if(empty($users)): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:40px">No users.</td></tr>
      <?php endif; ?>
      <?php foreach($users as $u): ?>
      <tr>
        <td style="color:var(--muted)"><?= $u['id'] ?></td>
        <td><strong><?= clean($u['name']) ?></strong></td>
        <td><?= clean($u['email']) ?></td>
        <td>
          <?php if($u['phone']): ?>
            <a href="https://wa.me/<?= preg_replace('/\D/','',$u['phone']) ?>" target="_blank"><?= clean($u['phone']) ?></a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <?php if(($u['role'] ?? 'buyer') === 'seller'): ?>
            <span style="background:rgba(251,191,36,.15);color:#f59e0b;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600">🏪 Seller</span>
          <?php else: ?>
            <span style="background:rgba(99,102,241,.15);color:#818cf8;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600">🛒 Buyer</span>
          <?php endif; ?>
        </td>
        <td><strong style="color:var(--success)"><?= $u['orders_paid'] ?></strong></td>
        <td style="font-size:12px;color:var(--muted)"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        <td>
          <a class="btn btn-sm btn-outline" href="user_view.php?id=<?= $u['id'] ?>">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>