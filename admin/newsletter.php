<?php
$pageTitle = 'Newsletter Subscribers';
require_once __DIR__ . '/includes/admin_header.php';

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $pdo->query("SELECT email, name, subscribed_at, status FROM newsletter_subscribers ORDER BY subscribed_at DESC")->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Name', 'Subscribed At', 'Status']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['email'], $row['name'] ?: '', $row['subscribed_at'], $row['status']]);
    }
    fclose($out);
    exit;
}

// Delete / unsubscribe action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf(SITE_URL . '/admin/newsletter.php');
    $id = (int)($_POST['sub_id'] ?? 0);
    if ($_POST['action'] === 'delete' && $id) {
        $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id=?")->execute([$id]);
        flash('success', 'Subscriber removed.');
    } elseif ($_POST['action'] === 'unsubscribe' && $id) {
        $pdo->prepare("UPDATE newsletter_subscribers SET status='unsubscribed' WHERE id=?")->execute([$id]);
        flash('success', 'Marked as unsubscribed.');
    }
    redirect(SITE_URL . '/admin/newsletter.php');
}

$filter = $_GET['status'] ?? 'active';
$allowed = ['active', 'unsubscribed', 'all'];
if (!in_array($filter, $allowed)) $filter = 'active';

$where = $filter !== 'all' ? "WHERE status='" . $filter . "'" : '';
$subs  = $pdo->query("SELECT * FROM newsletter_subscribers $where ORDER BY subscribed_at DESC")->fetchAll();
$total = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='active'")->fetchColumn();
?>

<div class="admin-topbar">
  <h1>📬 Newsletter Subscribers</h1>
  <div style="display:flex;gap:8px">
    <a href="?export=csv" class="btn btn-outline btn-sm">⬇️ Export CSV</a>
    <a href="<?= SITE_URL ?>/admin/settings.php" class="btn btn-outline btn-sm">← Back to Settings</a>
  </div>
</div>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <a href="?status=active"      class="btn btn-sm <?= $filter==='active'?'btn-primary':'btn-outline' ?>">✅ Active (<?= $total ?>)</a>
  <a href="?status=unsubscribed" class="btn btn-sm <?= $filter==='unsubscribed'?'btn-primary':'btn-outline' ?>">🚫 Unsubscribed</a>
  <a href="?status=all"         class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-outline' ?>">All</a>
</div>

<div class="section-card" style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead>
      <tr style="border-bottom:1px solid var(--border);color:var(--muted)">
        <th style="padding:10px;text-align:left">#</th>
        <th style="padding:10px;text-align:left">Email</th>
        <th style="padding:10px;text-align:left">Name</th>
        <th style="padding:10px;text-align:left">Subscribed At</th>
        <th style="padding:10px;text-align:left">Status</th>
        <th style="padding:10px;text-align:left">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($subs)): ?>
        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">No subscribers found.</td></tr>
      <?php endif; ?>
      <?php foreach ($subs as $i => $s): ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:9px 10px;color:var(--muted)"><?= $i + 1 ?></td>
        <td style="padding:9px 10px"><?= clean($s['email']) ?></td>
        <td style="padding:9px 10px;color:var(--muted)"><?= clean($s['name'] ?: '—') ?></td>
        <td style="padding:9px 10px;font-size:12px;color:var(--muted)"><?= date('d M Y, H:i', strtotime($s['subscribed_at'])) ?></td>
        <td style="padding:9px 10px">
          <span class="badge badge-<?= $s['status']==='active'?'paid':'rejected' ?>">
            <?= ucfirst($s['status']) ?>
          </span>
        </td>
        <td style="padding:9px 10px">
          <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
            <?php if ($s['status'] === 'active'): ?>
            <button name="action" value="unsubscribe" class="btn btn-sm btn-outline"
              onclick="return confirm('Mark as unsubscribed?')">🚫 Unsub</button>
            <?php endif; ?>
            <button name="action" value="delete" class="btn btn-sm btn-danger"
              onclick="return confirm('Delete this subscriber permanently?')" style="margin-left:4px">🗑️</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
