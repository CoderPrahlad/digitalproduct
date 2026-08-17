<?php
$pageTitle = 'Payment Gateways';
require_once __DIR__ . '/includes/admin_header.php';

// Make sure the table exists even if the SQL migration hasn't been run yet.
$pdo->exec("CREATE TABLE IF NOT EXISTS `payment_gateways` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `type`        ENUM('razorpay','watchpays','upi','crypto') NOT NULL DEFAULT 'upi',
  `key_id`      VARCHAR(255) DEFAULT NULL,
  `key_secret`  VARCHAR(255) DEFAULT NULL,
  `upi_id`      VARCHAR(150) DEFAULT NULL,
  `upi_name`    VARCHAR(150) DEFAULT NULL,
  `enabled`     TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Existing installs (created before WatchPays support) may still have the old,
// narrower ENUM. Widen it safely if needed — no-op if already up to date.
try {
    $colInfo = $pdo->query("SHOW COLUMNS FROM `payment_gateways` LIKE 'type'")->fetch();
    if ($colInfo && (strpos($colInfo['Type'], "'watchpays'") === false || strpos($colInfo['Type'], "'crypto'") === false)) {
        $pdo->exec("ALTER TABLE `payment_gateways` MODIFY `type` ENUM('razorpay','watchpays','upi','crypto') NOT NULL DEFAULT 'upi'");
    }
} catch (Exception $e) { /* ignore - non-fatal */ }

// One-time: if table is empty, seed it from config.php so nothing breaks.
$count = $pdo->query("SELECT COUNT(*) c FROM payment_gateways")->fetch()['c'];
if ((int)$count === 0) {
    if (defined('RAZORPAY_KEY_ID') && RAZORPAY_KEY_ID && strpos(RAZORPAY_KEY_ID,'XXXX') === false) {
        $pdo->prepare("INSERT INTO payment_gateways (name,type,key_id,key_secret,enabled,sort_order) VALUES (?,?,?,?,1,0)")
            ->execute(['Razorpay','razorpay',RAZORPAY_KEY_ID,RAZORPAY_KEY_SECRET]);
    }
    if (defined('UPI_ID') && UPI_ID) {
        $pdo->prepare("INSERT INTO payment_gateways (name,type,upi_id,upi_name,enabled,sort_order) VALUES (?,?,?,?,1,1)")
            ->execute(['UPI','upi',UPI_ID, defined('UPI_NAME')?UPI_NAME:SITE_NAME]);
    }
}

// One-time: seed a crypto gateway from the old single-address USDT setting, if no crypto gateway exists yet.
$cryptoCount = $pdo->query("SELECT COUNT(*) c FROM payment_gateways WHERE type='crypto'")->fetch()['c'];
if ((int)$cryptoCount === 0 && defined('USDT_TRC20_ADDRESS') && USDT_TRC20_ADDRESS) {
    $pdo->prepare("INSERT INTO payment_gateways (name,type,upi_id,upi_name,enabled,sort_order) VALUES (?,?,?,?,?,2)")
        ->execute(['USDT (TRC20)','crypto',USDT_TRC20_ADDRESS,'TRC20 (Tron)', (defined('USDT_ENABLED') && USDT_ENABLED) ? 1 : 0]);
}

// ---- ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL.'/admin/gateways.php');
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = clean($_POST['name'] ?? '');
        $type      = in_array($_POST['type'] ?? '', ['razorpay','watchpays','crypto'], true) ? $_POST['type'] : 'upi';
        $keyId     = clean($_POST['key_id'] ?? '');
        $keySecret = trim($_POST['key_secret'] ?? '');
        $upiId     = clean($_POST['upi_id'] ?? '');
        $upiName   = clean($_POST['upi_name'] ?? '');
        $sort      = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') { flash('error','Gateway name is required.'); redirect(SITE_URL.'/admin/gateways.php'); }

        if ($id > 0) {
            // keep existing secret if left blank on edit
            if ($keySecret === '') {
                $existing = $pdo->prepare("SELECT key_secret FROM payment_gateways WHERE id=?");
                $existing->execute([$id]);
                $keySecret = $existing->fetchColumn() ?: '';
            }
            $pdo->prepare("UPDATE payment_gateways SET name=?,type=?,key_id=?,key_secret=?,upi_id=?,upi_name=?,sort_order=? WHERE id=?")
                ->execute([$name,$type,$keyId,$keySecret,$upiId,$upiName,$sort,$id]);
            flash('success','Gateway updated.');
        } else {
            $pdo->prepare("INSERT INTO payment_gateways (name,type,key_id,key_secret,upi_id,upi_name,enabled,sort_order) VALUES (?,?,?,?,?,?,0,?)")
                ->execute([$name,$type,$keyId,$keySecret,$upiId,$upiName,$sort]);
            flash('success','Gateway added. Enable it from the list to make it live.');
        }
        redirect(SITE_URL.'/admin/gateways.php');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $row = $pdo->prepare("SELECT * FROM payment_gateways WHERE id=?"); $row->execute([$id]); $row = $row->fetch();
        if ($row) {
            $newState = $row['enabled'] ? 0 : 1;
            if ($newState && in_array($row['type'], ['razorpay','watchpays'], true)) {
                // Only one automatic gateway (Razorpay OR WatchPays) can be live at a time.
                $pdo->exec("UPDATE payment_gateways SET enabled=0 WHERE type IN ('razorpay','watchpays')");
            }
            $pdo->prepare("UPDATE payment_gateways SET enabled=? WHERE id=?")->execute([$newState,$id]);
            flash('success', $newState ? 'Gateway enabled.' : 'Gateway disabled.');
        }
        redirect(SITE_URL.'/admin/gateways.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM payment_gateways WHERE id=?")->execute([$id]);
        flash('success','Gateway deleted.');
        redirect(SITE_URL.'/admin/gateways.php');
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $e = $pdo->prepare("SELECT * FROM payment_gateways WHERE id=?");
    $e->execute([(int)$_GET['edit']]);
    $editing = $e->fetch();
}

$gateways = $pdo->query("SELECT * FROM payment_gateways ORDER BY type,sort_order,id")->fetchAll();
?>
<div class="admin-topbar"><h1>Payment Gateways</h1></div>

<div class="section-card">
  <h3><?= $editing ? 'Edit Gateway' : 'Add New Gateway' ?></h3>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Gateway Name *</label>
        <input class="form-control" type="text" name="name" required value="<?= clean($editing['name'] ?? '') ?>" placeholder="e.g. Razorpay Live, GPay, Business UPI">
      </div>
      <div class="form-group">
        <label>Gateway Type *</label>
        <select class="form-control" name="type" id="gwType" onchange="toggleGwFields()">
          <option value="razorpay" <?= ($editing['type'] ?? '')==='razorpay'?'selected':'' ?>>Automatic — Razorpay-compatible (UPI/Card/Netbanking via API)</option>
          <option value="watchpays" <?= ($editing['type'] ?? '')==='watchpays'?'selected':'' ?>>Automatic — WatchPays</option>
          <option value="upi" <?= ($editing['type'] ?? 'upi')==='upi'?'selected':'' ?>>Manual — UPI ID (customer pays &amp; submits UTR)</option>
          <option value="crypto" <?= ($editing['type'] ?? '')==='crypto'?'selected':'' ?>>Crypto — USDT/any coin, any network (customer pays &amp; submits TXID)</option>
        </select>
      </div>
    </div>
    <div id="gwRazorpayFields" class="form-row">
      <div class="form-group">
        <label id="gwKeyIdLabel">Key ID</label>
        <input class="form-control" type="text" name="key_id" value="<?= clean($editing['key_id'] ?? '') ?>" placeholder="rzp_live_xxxxxxxx">
      </div>
      <div class="form-group">
        <label id="gwKeySecretLabel">Key Secret</label>
        <input class="form-control" type="password" name="key_secret" placeholder="<?= !empty($editing['key_secret']) ? 'Leave blank to keep existing secret' : 'Secret key' ?>">
      </div>
    </div>
    <div id="gwUpiFields" class="form-row">
      <div class="form-group">
        <label id="gwUpiIdLabel">UPI ID</label>
        <input class="form-control" type="text" name="upi_id" id="gwUpiIdInput" value="<?= clean($editing['upi_id'] ?? '') ?>" placeholder="yourname@upi">
      </div>
      <div class="form-group">
        <label id="gwUpiNameLabel">Payee Name (shown to customer)</label>
        <input class="form-control" type="text" name="upi_name" id="gwUpiNameInput" value="<?= clean($editing['upi_name'] ?? '') ?>" placeholder="Your Business Name">
      </div>
    </div>
    <div class="form-group">
      <label>Sort Order</label>
      <input class="form-control" type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
      <p class="form-hint">Lower numbers show first at checkout when multiple UPI methods are enabled.</p>
    </div>
    <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add Gateway' ?></button>
    <?php if ($editing): ?><a href="<?= SITE_URL ?>/admin/gateways.php" class="btn btn-outline">Cancel</a><?php endif; ?>
  </form>
</div>

<div class="table-wrap" style="margin-top:20px">
  <table>
    <thead><tr><th>Name</th><th>Type</th><th>Details</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (empty($gateways)): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:40px">No gateways yet — add one above.</td></tr>
      <?php endif; ?>
      <?php foreach ($gateways as $g): ?>
      <tr>
        <td><strong><?= clean($g['name']) ?></strong></td>
        <td><span class="badge"><?= $g['type']==='upi' ? 'Manual UPI' : ($g['type']==='crypto' ? 'Crypto' : ('Automatic — '.ucfirst($g['type']))) ?></span></td>
        <td style="font-size:12px;color:var(--muted)">
          <?php if ($g['type']==='upi' || $g['type']==='crypto'): ?>
            <?= clean($g['upi_id'] ?: '—') ?><?= $g['type']==='crypto' && $g['upi_name'] ? ' · '.clean($g['upi_name']) : '' ?>
          <?php else: ?>
            <?= clean(substr($g['key_id'] ?: '—',0,24)) ?>
          <?php endif; ?>
        </td>
        <td>
          <form method="POST" style="display:inline">
          <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $g['id'] ?>">
            <button type="submit" class="btn btn-sm <?= $g['enabled'] ? 'btn-success' : 'btn-outline' ?>"><?= $g['enabled'] ? '✅ Enabled' : 'Disabled' ?></button>
          </form>
        </td>
        <td style="white-space:nowrap">
          <a href="?edit=<?= $g['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
          <form method="POST" style="display:inline">
          <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $g['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm confirm-action" data-confirm="Delete this gateway?">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function toggleGwFields() {
  var t = document.getElementById('gwType').value;
  document.getElementById('gwRazorpayFields').style.display = (t === 'razorpay' || t === 'watchpays') ? 'grid' : 'none';
  document.getElementById('gwUpiFields').style.display      = (t === 'upi' || t === 'crypto') ? 'grid' : 'none';
  document.getElementById('gwKeyIdLabel').textContent     = t === 'watchpays' ? 'Merchant ID' : 'Key ID';
  document.getElementById('gwKeySecretLabel').textContent = t === 'watchpays' ? 'API Key'     : 'Key Secret';
  if (t === 'crypto') {
    document.getElementById('gwUpiIdLabel').textContent   = 'Wallet Address';
    document.getElementById('gwUpiIdInput').placeholder   = 'T... / 0x... — full wallet address';
    document.getElementById('gwUpiNameLabel').textContent = 'Network / Label (shown to customer)';
    document.getElementById('gwUpiNameInput').placeholder = 'e.g. TRC20 (Tron), BEP20 (BNB Smart Chain)';
  } else {
    document.getElementById('gwUpiIdLabel').textContent   = 'UPI ID';
    document.getElementById('gwUpiIdInput').placeholder   = 'yourname@upi';
    document.getElementById('gwUpiNameLabel').textContent = 'Payee Name (shown to customer)';
    document.getElementById('gwUpiNameInput').placeholder = 'Your Business Name';
  }
}
toggleGwFields();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
