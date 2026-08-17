<?php
$pageTitle = 'Messages';
require_once __DIR__ . '/includes/admin_header.php';
if (isset($_GET['delete'])) {
    if (empty($_GET['t']) || !hash_equals(csrf_token(), $_GET['t'])) {
        flash('error','Security check failed. Try again.'); redirect(SITE_URL.'/admin/messages.php');
    }
    $pdo->prepare("DELETE FROM messages WHERE id=?")->execute([(int)$_GET['delete']]);
    flash('success','Deleted.'); redirect(SITE_URL.'/admin/messages.php');
}
if (isset($_GET['read'])) {
    $pdo->prepare("UPDATE messages SET is_read=1 WHERE id=?")->execute([(int)$_GET['read']]);
    redirect(SITE_URL.'/admin/messages.php');
}
$messages = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC")->fetchAll();
?>
<div class="admin-topbar"><h1>Messages</h1><span style="color:var(--muted);font-size:13px"><?= count($messages) ?> total</span></div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Subject</th><th>Message</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if(empty($messages)): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px">No messages.</td></tr><?php endif; ?>
      <?php foreach($messages as $m): ?>
      <tr style="<?= !$m['is_read'] ? 'background:rgba(124,58,237,.04)' : '' ?>">
        <td><strong><?= clean($m['name']) ?></strong><?php if(!$m['is_read']): ?> <span style="color:var(--primary);font-size:10px">NEW</span><?php endif; ?></td>
        <td><?php if($m['email']): ?><a href="mailto:<?= clean($m['email']) ?>"><?= clean($m['email']) ?></a><?php else: ?>—<?php endif; ?></td>
        <td><?php if($m['phone']): ?><a href="https://wa.me/<?= preg_replace('/\D/','',$m['phone']) ?>" target="_blank"><?= clean($m['phone']) ?></a><?php else: ?>—<?php endif; ?></td>
        <td><?= clean($m['subject'] ?: '—') ?></td>
        <td style="max-width:260px;white-space:pre-wrap;font-size:13px"><?= clean($m['message']) ?></td>
        <td style="font-size:12px;color:var(--muted)"><?= date('d M, H:i', strtotime($m['created_at'])) ?></td>
        <td style="display:flex;gap:6px">
          <?php if(!$m['is_read']): ?><a href="?read=<?= $m['id'] ?>" class="btn btn-outline btn-sm">Mark Read</a><?php endif; ?>
          <a href="?delete=<?= $m['id'] ?>&t=<?= csrf_token() ?>" class="btn btn-danger btn-sm confirm-action" data-confirm="Delete message?">Del</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
