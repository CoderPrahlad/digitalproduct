<?php
$pageTitle = 'Product Reviews';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL . '/admin/reviews.php');
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'approve') {
        $pdo->prepare("UPDATE reviews SET status='approved' WHERE id=?")->execute([$id]);
        flash('success', 'Review approved and published.');
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE reviews SET status='rejected' WHERE id=?")->execute([$id]);
        flash('success', 'Review rejected.');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM reviews WHERE id=?")->execute([$id]);
        flash('success', 'Review deleted.');
    }
    redirect(SITE_URL . '/admin/reviews.php?status=' . ($_GET['status'] ?? 'pending'));
}

$status   = $_GET['status'] ?? 'pending';
$statuses = ['pending', 'approved', 'rejected'];
$where    = in_array($status, $statuses) ? "WHERE r.status='$status'" : '';

$reviews = $pdo->query("SELECT r.*, u.name uname, p.title ptitle
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    JOIN products p ON p.id = r.product_id
    $where
    ORDER BY r.created_at DESC LIMIT 50")->fetchAll();
?>
<div class="admin-topbar">
  <h1>Product Reviews</h1>
  <div style="display:flex;gap:8px">
    <?php foreach (['pending','approved','rejected'] as $s): ?>
      <a href="?status=<?= $s ?>" class="btn btn-sm <?= $status===$s ? 'btn-primary' : 'btn-outline' ?>"><?= ucfirst($s) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (empty($reviews)): ?>
  <div style="text-align:center;padding:60px;color:var(--muted)">No <?= $status ?> reviews.</div>
<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:14px">
    <?php foreach ($reviews as $r): ?>
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:20px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
        <div>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
            <strong><?= clean($r['uname']) ?></strong>
            <span style="color:#fbbf24;font-size:15px"><?= str_repeat('★', $r['rating']) ?><?= str_repeat('☆', 5 - $r['rating']) ?></span>
            <span style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($r['created_at'])) ?></span>
          </div>
          <div style="font-size:12px;color:var(--muted);margin-bottom:8px">Product: <strong><?= clean($r['ptitle']) ?></strong></div>
          <?php if ($r['title']): ?><strong><?= clean($r['title']) ?></strong><br><?php endif; ?>
          <p style="color:var(--muted2);margin-top:4px"><?= nl2br(clean($r['body'])) ?></p>
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0">
          <?php if ($r['status'] !== 'approved'): ?>
          <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-success btn-sm">✅ Approve</button>
          </form>
          <?php endif; ?>
          <?php if ($r['status'] !== 'rejected'): ?>
          <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-outline btn-sm">❌ Reject</button>
          </form>
          <?php endif; ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this review?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-danger btn-sm">🗑</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
