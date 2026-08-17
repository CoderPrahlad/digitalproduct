<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();

$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'buyer';

// ---- ACCOUNT: update email / phone ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {
    verifyCsrf(SITE_URL . '/dashboard.php?tab=account');
    $curPass  = $_POST['current_password'] ?? '';
    $newEmail = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $newPhone = clean($_POST['phone'] ?? '');
    $meRowStmt = $pdo->prepare("SELECT email, password FROM users WHERE id=?");
    $meRowStmt->execute([$uid]);
    $meRow = $meRowStmt->fetch();
    if (!$curPass || !password_verify($curPass, $meRow['password'])) {
        flash('error', 'Current password is incorrect.');
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Enter a valid email address.');
    } else {
        $emailTaken = false;
        if (strtolower($newEmail) !== strtolower($meRow['email'])) {
            $dupe = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $dupe->execute([$newEmail, $uid]);
            $emailTaken = (bool)$dupe->fetch();
        }
        if ($emailTaken) { flash('error', 'This email is already used by another account.');
        } else {
            $pdo->prepare("UPDATE users SET email=?, phone=? WHERE id=?")->execute([$newEmail, $newPhone, $uid]);
            flash('success', 'Account details updated.');
        }
    }
    redirect(SITE_URL . '/dashboard.php?tab=account');
}

// ---- ACCOUNT: change password ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    verifyCsrf(SITE_URL . '/dashboard.php?tab=account');
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    if (strlen($newPass) < 6) {
        flash('error', 'New password must be at least 6 characters.');
    } elseif ($newPass !== $confirmPass) {
        flash('error', 'New passwords do not match.');
    } else {
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $uid]);
        flash('success', 'Password changed successfully.');
    }
    redirect(SITE_URL . '/dashboard.php?tab=account');
}

// Orders
$ordersStmt = $pdo->prepare("SELECT o.*, p.title, p.slug, p.image FROM orders o
    JOIN products p ON p.id=o.product_id
    WHERE o.user_id=? ORDER BY o.created_at DESC LIMIT 50");
$ordersStmt->execute([$uid]);
$orders = $ordersStmt->fetchAll();

// Seller stats
$esQ = $pdo->prepare("SELECT
    COALESCE(SUM(seller_amount),0) total_earned,
    COALESCE(SUM(CASE WHEN payout_status='unpaid' THEN seller_amount ELSE 0 END),0) unpaid_balance,
    COALESCE(SUM(CASE WHEN payout_status='paid' THEN seller_amount ELSE 0 END),0) paid_out,
    COUNT(*) total_sales
    FROM seller_earnings WHERE seller_id=?");
$esQ->execute([$uid]); $es = $esQ->fetch();

// My listings
$myProds = $pdo->prepare("SELECT * FROM products WHERE seller_id=? ORDER BY created_at DESC");
$myProds->execute([$uid]); $myProds = $myProds->fetchAll();

// Withdraw history
$wh = [];
try { $whQ = $pdo->prepare("SELECT * FROM withdraw_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 10"); $whQ->execute([$uid]); $wh = $whQ->fetchAll(); } catch(Exception $e){}

// Pending withdraw
$pendingW = null;
try { $pwQ = $pdo->prepare("SELECT * FROM withdraw_requests WHERE user_id=? AND status='pending' LIMIT 1"); $pwQ->execute([$uid]); $pendingW = $pwQ->fetch(); } catch(Exception $e){}

$meQ = $pdo->prepare("SELECT email,phone,payout_upi,payout_note,wallet_balance FROM users WHERE id=?"); $meQ->execute([$uid]); $me = $meQ->fetch();

// Referral
$refCode = ensureReferralCode($pdo, $uid);
$refLink = SITE_URL . '/register.php?ref=' . $refCode;
$rTotalQ = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by=?"); $rTotalQ->execute([$uid]);
$rEarnedQ = $pdo->prepare("SELECT COALESCE(SUM(commission_amount),0) FROM referral_commissions WHERE referrer_id=?"); $rEarnedQ->execute([$uid]);
$refStats = ['total' => (int)$rTotalQ->fetchColumn(), 'earned' => (float)$rEarnedQ->fetchColumn()];
$refUsersQ = $pdo->prepare("SELECT u.name, u.created_at, COALESCE((SELECT SUM(rc.commission_amount) FROM referral_commissions rc WHERE rc.buyer_id=u.id AND rc.referrer_id=?),0) earned FROM users u WHERE u.referred_by=? ORDER BY u.created_at DESC");
$refUsersQ->execute([$uid, $uid]); $refUsers = $refUsersQ->fetchAll();

$walletBal = (float)($me['wallet_balance'] ?? 0);
$walletTxQ = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 20"); $walletTxQ->execute([$uid]); $walletTx = $walletTxQ->fetchAll();

// Vendor fee vars
$buyerFeePct   = vendorBuyerFeePercent($pdo);
$commissionPct = vendorCommissionPercent($pdo);
$categories    = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Edit product fetch
$editProd = null;
$tab = $_GET['tab'] ?? ($role === 'seller' ? 'overview' : 'orders');
if ($tab === 'edit' && isset($_GET['pid'])) {
    $epQ = $pdo->prepare("SELECT * FROM products WHERE id=? AND seller_id=?");
    $epQ->execute([(int)$_GET['pid'], $uid]); $editProd = $epQ->fetch();
}

// Delete product
if (isset($_GET['delete_product']) && isset($_GET['t'])) {
    if (hash_equals(csrf_token(), $_GET['t'])) {
        $dp = $pdo->prepare("SELECT * FROM products WHERE id=? AND seller_id=?");
        $dp->execute([(int)$_GET['delete_product'], $uid]); $dprod = $dp->fetch();
        if ($dprod) {
            if ($dprod['image'] && file_exists(UPLOAD_PATH.'/products/'.$dprod['image'])) unlink(UPLOAD_PATH.'/products/'.$dprod['image']);
            if ($dprod['file_path'] && file_exists(DOWNLOAD_PATH.'/'.$dprod['file_path'])) unlink(DOWNLOAD_PATH.'/'.$dprod['file_path']);
            $pdo->prepare("DELETE FROM products WHERE id=? AND seller_id=?")->execute([$dprod['id'], $uid]);
            flash('success', 'Product deleted.');
        }
    }
    redirect(SITE_URL.'/dashboard.php?tab=listings');
}

$pageTitle = 'Dashboard';

// =====================================================================
// SELLER → admin-style layout
// =====================================================================
if ($role === 'seller'):
$currentPage = $tab;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seller Dashboard — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/admin/assets/admin.css">
</head>
<body>
  <div class="admin-mobile-bar" id="sellerMobileBar">
  <a href="<?= SITE_URL ?>" class="logo">⚡ <?= SITE_NAME ?></a>
  <button class="admin-hamburger" onclick="toggleSellerSidebar()">☰</button>
</div>
<div class="admin-sidebar-overlay" id="sellerOverlay" onclick="toggleSellerSidebar()"></div>
<div class="admin-layout">
<style>
/* Seller Dashboard Responsive */
.seller-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 24px;
}
.seller-two-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}
.seller-ref-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  margin-bottom: 22px;
}
@media (max-width: 900px) {
  .seller-stat-grid { grid-template-columns: 1fr 1fr; }
  .seller-ref-grid  { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 640px) {
  .seller-stat-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
  .seller-two-grid  { grid-template-columns: 1fr; gap: 12px; }
  .seller-ref-grid  { grid-template-columns: 1fr; gap: 10px; }
  .admin-topbar { flex-direction: column; align-items: flex-start; gap: 10px; }
  .admin-topbar .btn { width: 100%; text-align: center; }
}
</style>

  <!-- Sidebar -->
  <aside class="admin-sidebar" id="sellerSidebar">
    <a href="<?= SITE_URL ?>" class="logo">⚡ <?= SITE_NAME ?></a>
    <nav class="sidebar-nav">
      <a href="?tab=overview"  class="<?= $currentPage==='overview' ?'active':'' ?>">📊 Overview</a>
      <a href="?tab=listings"  class="<?= in_array($currentPage,['listings','sell','edit']) ?'active':'' ?>">📦 My Products</a>
      <a href="?tab=earnings"  class="<?= $currentPage==='earnings' ?'active':'' ?>">💰 Earnings</a>
      <a href="?tab=referral"  class="<?= $currentPage==='referral' ?'active':'' ?>">🤝 Referral</a>
      <a href="?tab=account"   class="<?= $currentPage==='account'  ?'active':'' ?>">👤 Account</a>
    </nav>
    <div style="margin-top:auto;padding-top:20px;border-top:1px solid var(--border)">
      <a href="<?= SITE_URL ?>" target="_blank" style="display:block;padding:8px 12px;color:var(--muted);font-size:13px">🌐 View Store</a>
      <a href="<?= SITE_URL ?>/logout.php" style="display:block;padding:8px 12px;color:var(--danger);font-size:13px">🚪 Logout</a>
    </div>
  </aside>

  <!-- Main -->
  <main class="admin-main">
<?php
$fs=flash('success'); $fe=flash('error'); $fi=flash('info');
if ($fs) echo '<div class="alert alert-success" data-auto-hide>✓ '.clean($fs).'</div>';
if ($fe) echo '<div class="alert alert-error"   data-auto-hide>✗ '.clean($fe).'</div>';
if ($fi) echo '<div class="alert alert-success" data-auto-hide>ℹ '.clean($fi).'</div>';
?>

<?php /* ===== OVERVIEW ===== */ if ($tab === 'overview'): ?>
<div class="admin-topbar">
  <h1>👋 Welcome, <?= clean($_SESSION['user_name']) ?>!</h1>
  <a href="?tab=sell" class="btn btn-primary btn-sm">+ Add New Product</a>
</div>

<div class="seller-stat-grid">
  <div class="section-card" style="text-align:center;padding:20px">
    <div style="font-size:26px;font-weight:800;color:#34d399">₹<?= number_format($es['unpaid_balance'],2) ?></div>
    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;margin-top:4px">Unpaid Balance</div>
  </div>
  <div class="section-card" style="text-align:center;padding:20px">
    <div style="font-size:26px;font-weight:800;color:#a78bfa">₹<?= number_format($es['total_earned'],2) ?></div>
    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;margin-top:4px">Total Earned</div>
  </div>
  <div class="section-card" style="text-align:center;padding:20px">
    <div style="font-size:26px;font-weight:800"><?= (int)$es['total_sales'] ?></div>
    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;margin-top:4px">Total Sales</div>
  </div>
  <div class="section-card" style="text-align:center;padding:20px">
    <div style="font-size:26px;font-weight:800"><?= count($myProds) ?></div>
    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;margin-top:4px">My Products</div>
  </div>
</div>

<!-- Recent products -->
<div class="section-card">
  <div class="admin-topbar" style="margin-bottom:14px">
    <h3>📦 My Products</h3>
    <a href="?tab=sell" class="btn btn-primary btn-sm">+ Add Product</a>
  </div>
  <?php if (empty($myProds)): ?>
  <p style="color:var(--muted);text-align:center;padding:30px">No products yet. <a href="?tab=sell">Add your first product →</a></p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Thumbnail</th><th>Title</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach(array_slice($myProds,0,5) as $p): ?>
      <tr>
        <td><?php if($p['image']):?><img src="<?= SITE_URL ?>/uploads/products/<?= clean($p['image']) ?>" style="width:46px;height:34px;object-fit:cover;border-radius:6px"><?php else:?>—<?php endif;?></td>
        <td><strong><?= clean($p['title']) ?></strong></td>
        <td>₹<?= number_format($p['seller_base_price']?:$p['price']) ?></td>
        <td>
          <?php if($p['approval_status']==='pending'):?><span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24">Pending</span>
          <?php elseif($p['approval_status']==='approved'):?><span class="badge badge-active">Live</span>
          <?php else:?><span class="badge" style="background:rgba(239,68,68,.15);color:#ef4444">Rejected</span><?php endif;?>
        </td>
        <td style="display:flex;gap:6px">
          <a href="?tab=edit&pid=<?= $p['id'] ?>" class="btn btn-sm btn-outline">✏️ Edit</a>
          <a href="<?= SITE_URL ?>/product.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn btn-sm btn-outline">👁 View</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if(count($myProds)>5):?><p style="text-align:center;margin-top:10px"><a href="?tab=listings">View all <?= count($myProds) ?> products →</a></p><?php endif; ?>
  <?php endif; ?>
</div>

<?php /* ===== MY PRODUCTS / LISTINGS ===== */ elseif (in_array($tab,['listings','sell'])): ?>
<div class="admin-topbar">
  <h1>📦 My Products</h1>
  <a href="?tab=sell" class="btn btn-primary btn-sm">+ Add New Product</a>
</div>

<?php if ($tab === 'sell'): ?>
<!-- ADD PRODUCT FORM -->
<div class="section-card">
  <h3 style="margin-bottom:16px">🚀 List a New Product</h3>
  <div style="background:rgba(124,58,237,.06);border:1px solid rgba(124,58,237,.18);border-radius:10px;padding:11px 14px;margin-bottom:18px;font-size:13px;color:var(--muted2)">
    Price ₹1000 → buyer pays <strong>₹<?= number_format(vendorBuyerPrice($pdo,1000)) ?></strong> → you earn <strong style="color:#34d399">₹<?= number_format(vendorSellerEarning($pdo,1000)) ?></strong>
    <span style="color:var(--muted)"> · +<?= (int)$buyerFeePct ?>% buyer fee · <?= (100-(int)$commissionPct) ?>% to you</span>
  </div>
  <form method="POST" action="<?= SITE_URL ?>/dashboard.php?tab=listings" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="submit_product" value="1">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 18px">
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Title *</label><input class="form-control" type="text" name="title" required placeholder="e.g. Laravel CRM System"></div>
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Category</label><select class="form-control" name="category_id"><option value="">-- None --</option><?php foreach($categories as $c):?><option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option><?php endforeach;?></select></div>
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Your Price (₹) *</label><input class="form-control" type="number" min="1" step="1" name="base_price" id="bpi" required oninput="upv()" placeholder="999"><div id="pv" style="font-size:11px;color:var(--muted);margin-top:4px">Enter price to see breakdown</div></div>
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Demo URL (optional)</label><input class="form-control" type="url" name="demo_url" placeholder="https://demo.yoursite.com"></div>
      <div class="form-group" style="grid-column:1/-1"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Short Description</label><input class="form-control" type="text" name="short_desc" placeholder="One-line summary shown on cards"></div>
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Full Description</label><textarea class="form-control" name="description" rows="4" placeholder="Tech stack, features, use cases..."></textarea></div>
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Features (one per line)</label><textarea class="form-control" name="features" rows="4" placeholder="Complete source code&#10;Admin panel&#10;Mobile responsive"></textarea></div>
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Thumbnail Image</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Product File * <span style="font-weight:400;color:var(--muted)">(ZIP/RAR/7z/PDF)</span></label><input class="form-control" type="file" name="product_file" accept=".zip,.rar,.7z,.pdf" required></div>
      <div class="form-group" style="grid-column:1/-1"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Gallery Screenshots <span style="font-weight:400;color:var(--muted)">(optional · up to 5)</span></label><input class="form-control" type="file" name="gallery_images[]" accept=".jpg,.jpeg,.png,.webp" multiple></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:14px">
      <button type="submit" class="btn btn-primary">Submit for Approval</button>
      <a href="?tab=listings" class="btn btn-outline">Cancel</a>
    </div>
    <p style="font-size:11px;color:var(--muted);margin-top:7px">Admin reviews before listing goes live</p>
  </form>
</div>

<?php else: ?>
<!-- PRODUCTS LIST -->
<?php if (empty($myProds)): ?>
<div class="section-card" style="text-align:center;padding:60px">
  <div style="font-size:52px;margin-bottom:14px">📦</div>
  <p style="color:var(--muted);margin-bottom:16px">No products yet.</p>
  <a href="?tab=sell" class="btn btn-primary">🚀 Add Your First Product</a>
</div>
<?php else: ?>
<div class="section-card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Thumbnail</th><th>Title</th><th>Base Price</th><th>Buyer Pays</th><th>You Earn</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($myProds as $p): ?>
      <tr>
        <td style="color:var(--muted)"><?= $p['id'] ?></td>
        <td><?php if($p['image']):?><img src="<?= SITE_URL ?>/uploads/products/<?= clean($p['image']) ?>" style="width:50px;height:38px;object-fit:cover;border-radius:6px"><?php else:?>—<?php endif;?></td>
        <td><strong><?= clean($p['title']) ?></strong></td>
        <td>₹<?= number_format($p['seller_base_price']?:$p['price']) ?></td>
        <td>₹<?= number_format($p['price']) ?></td>
        <td style="color:#34d399;font-weight:600">₹<?= number_format(vendorSellerEarning($pdo, $p['seller_base_price']?:$p['price']),2) ?></td>
        <td>
          <?php if($p['approval_status']==='pending'):?><span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24">⏳ Pending</span>
          <?php elseif($p['approval_status']==='approved'):?><span class="badge badge-active">✅ Live</span>
          <?php else:?><span class="badge" style="background:rgba(239,68,68,.15);color:#ef4444" title="<?= clean($p['reject_reason']?:'') ?>">❌ Rejected</span><?php endif;?>
        </td>
        <td style="display:flex;gap:6px;flex-wrap:wrap">
          <a href="?tab=edit&pid=<?= $p['id'] ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
          <a href="<?= SITE_URL ?>/product.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn btn-sm btn-outline">👁 View</a>
          <a href="?delete_product=<?= $p['id'] ?>&t=<?= csrf_token() ?>" class="btn btn-sm btn-danger confirm-action" data-confirm="Delete this product?">🗑 Del</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php /* ===== EDIT PRODUCT ===== */ elseif ($tab === 'edit'): ?>
<div class="admin-topbar">
  <h1>✏️ Edit Product</h1>
  <a href="?tab=listings" class="btn btn-outline btn-sm">← Back to Listings</a>
</div>
<?php if ($editProd): ?>
<div class="section-card">
  <div style="background:rgba(251,191,36,.07);border:1px solid rgba(251,191,36,.2);border-radius:8px;padding:10px 14px;font-size:12px;color:#fbbf24;margin-bottom:16px">
    ⚠️ Editing re-submits for admin review. Product will be temporarily unlisted until approved.
  </div>
  <form method="POST" action="<?= SITE_URL ?>/dashboard.php?tab=listings" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="edit_product" value="<?= $editProd['id'] ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 18px">
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Title *</label><input class="form-control" type="text" name="title" value="<?= clean($editProd['title']) ?>" required></div>
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Demo URL</label><input class="form-control" type="url" name="demo_url" value="<?= clean($editProd['demo_url']) ?>" placeholder="https://"></div>
      <div class="form-group" style="grid-column:1/-1"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Short Description</label><input class="form-control" type="text" name="short_desc" value="<?= clean($editProd['short_desc']) ?>"></div>
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Full Description</label><textarea class="form-control" name="description" rows="5"><?= clean($editProd['description']) ?></textarea></div>
      <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Features (one per line)</label><textarea class="form-control" name="features" rows="5"><?= clean($editProd['features']) ?></textarea></div>
      <div class="form-group" style="grid-column:1/-1">
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Replace Thumbnail (optional)</label>
        <?php if($editProd['image']):?><img src="<?= SITE_URL ?>/uploads/products/<?= clean($editProd['image']) ?>" style="height:52px;border-radius:7px;display:block;margin-bottom:8px;border:1px solid var(--border)"><?php endif;?>
        <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px">
      <button type="submit" class="btn btn-primary">💾 Save & Resubmit</button>
      <a href="?tab=listings" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<?php else: ?>
<div class="section-card" style="text-align:center;padding:50px"><p style="color:var(--muted)">Product not found.</p><a href="?tab=listings" class="btn btn-outline btn-sm" style="margin-top:12px">← Back</a></div>
<?php endif; ?>

<?php /* ===== EARNINGS ===== */ elseif ($tab === 'earnings'): ?>
<div class="admin-topbar"><h1>💰 Earnings & Payouts</h1></div>

<div class="seller-stat-grid">
  <div class="section-card" style="text-align:center;padding:18px"><div style="font-size:24px;font-weight:800;color:#34d399">₹<?= number_format($es['unpaid_balance'],2) ?></div><div style="font-size:11px;color:var(--muted);margin-top:3px">Unpaid Balance</div></div>
  <div class="section-card" style="text-align:center;padding:18px"><div style="font-size:24px;font-weight:800;color:#a78bfa">₹<?= number_format($es['total_earned'],2) ?></div><div style="font-size:11px;color:var(--muted);margin-top:3px">Total Earned</div></div>
  <div class="section-card" style="text-align:center;padding:18px"><div style="font-size:24px;font-weight:800">₹<?= number_format($es['paid_out'],2) ?></div><div style="font-size:11px;color:var(--muted);margin-top:3px">Paid Out</div></div>
  <div class="section-card" style="text-align:center;padding:18px"><div style="font-size:24px;font-weight:800"><?= (int)$es['total_sales'] ?></div><div style="font-size:11px;color:var(--muted);margin-top:3px">Total Sales</div></div>
</div>

<div class="seller-two-grid">
  <!-- Payout Details -->
  <div class="section-card">
    <h3 style="margin-bottom:14px">💳 Payout Details</h3>
    <form method="POST" action="<?= SITE_URL ?>/dashboard.php?tab=earnings">
      <?= csrf_field() ?><input type="hidden" name="save_payout" value="1">
      <input type="hidden" name="_redirect" value="<?= SITE_URL ?>/dashboard.php?tab=earnings">
      <div class="form-group" style="margin-bottom:10px"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">UPI ID / Payment Address</label><input class="form-control" type="text" name="payout_upi" value="<?= clean($me['payout_upi']??'') ?>" placeholder="yourname@upi"></div>
      <div class="form-group" style="margin-bottom:12px"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Bank Details / Note (optional)</label><input class="form-control" type="text" name="payout_note" value="<?= clean($me['payout_note']??'') ?>" placeholder="Account no, IFSC..."></div>
      <button type="submit" class="btn btn-outline btn-sm">Save Details</button>
    </form>
  </div>
  <!-- Withdraw -->
  <div class="section-card">
    <h3 style="margin-bottom:14px">📤 Request Withdrawal</h3>
    <?php if ($pendingW): ?>
    <div style="background:rgba(52,211,153,.07);border:1px solid rgba(52,211,153,.25);border-radius:10px;padding:14px">
      <div style="font-weight:700;color:#34d399;margin-bottom:4px">Pending Request</div>
      <div style="font-size:13px;color:var(--muted2)">₹<?= number_format($pendingW['amount'],2) ?> · submitted <?= date('d M Y',strtotime($pendingW['created_at'])) ?></div>
      <div style="font-size:12px;color:var(--muted);margin-top:3px">Admin will process within 24-48 hours</div>
    </div>
    <?php elseif ($es['unpaid_balance'] > 0): ?>
      <?php if (!$me['payout_upi']): ?>
      <div style="background:rgba(251,191,36,.07);border:1px solid rgba(251,191,36,.25);border-radius:8px;padding:12px;font-size:13px;color:#fbbf24">Set your UPI ID before requesting withdrawal.</div>
      <?php else: ?>
      <p style="color:var(--muted);font-size:13px;margin-bottom:13px">Unpaid: <strong style="color:#34d399">₹<?= number_format($es['unpaid_balance'],2) ?></strong></p>
      <form method="POST" action="<?= SITE_URL ?>/dashboard.php?tab=earnings">
        <?= csrf_field() ?><input type="hidden" name="request_withdraw" value="1">
        <input type="hidden" name="_redirect" value="<?= SITE_URL ?>/dashboard.php?tab=earnings">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
          <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Amount (₹) *</label><input class="form-control" type="number" name="withdraw_amount" min="50" max="<?= floor($es['unpaid_balance']) ?>" step="1" value="<?= floor($es['unpaid_balance']) ?>" required><div style="font-size:11px;color:var(--muted);margin-top:3px">Min ₹50</div></div>
          <div class="form-group"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Note (optional)</label><input class="form-control" type="text" name="withdraw_note" placeholder="urgent, bank..."></div>
        </div>
        <div style="background:var(--bg-card2);border-radius:8px;padding:9px 12px;font-size:12px;color:var(--muted);margin-bottom:12px">Sending to: <strong style="color:var(--muted2)"><?= clean($me['payout_upi']) ?></strong></div>
        <button type="submit" class="btn btn-primary btn-sm">Request Withdrawal</button>
      </form>
      <?php endif; ?>
    <?php else: ?>
    <p style="color:var(--muted);font-size:13px">No unpaid balance yet.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Sales Ledger -->
<?php
$reQ = $pdo->prepare("SELECT se.*,p.title FROM seller_earnings se JOIN products p ON p.id=se.product_id WHERE se.seller_id=? ORDER BY se.created_at DESC LIMIT 30");
$reQ->execute([$uid]); $recentE = $reQ->fetchAll();
?>
<div class="section-card">
  <h3 style="margin-bottom:14px">📊 Sales Ledger</h3>
  <?php if (empty($recentE)): ?>
  <p style="color:var(--muted);text-align:center;padding:30px">No sales yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th>Buyer Paid</th><th>You Earned</th><th>Payout</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach($recentE as $e): ?>
      <tr>
        <td><?= clean($e['title']) ?></td>
        <td>₹<?= number_format($e['buyer_amount'],2) ?></td>
        <td style="color:#34d399;font-weight:600">₹<?= number_format($e['seller_amount'],2) ?></td>
        <td><?= $e['payout_status']==='paid' ? '<span class="badge badge-active">Paid</span>' : '<span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24">Unpaid</span>' ?></td>
        <td style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($e['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($wh)): ?>
<div class="section-card" style="margin-top:16px">
  <h3 style="margin-bottom:14px">📄 Withdrawal History</h3>
  <div class="table-wrap"><table>
    <thead><tr><th>Amount</th><th>Status</th><th>Note</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach($wh as $w): ?>
    <tr>
      <td style="font-weight:600">₹<?= number_format($w['amount'],2) ?></td>
      <td><?php if($w['status']==='paid'):?><span class="badge badge-active">Paid</span><?php elseif($w['status']==='pending'):?><span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24">Pending</span><?php else:?><span class="badge" style="background:rgba(239,68,68,.15);color:#ef4444"><?= ucfirst($w['status']) ?></span><?php endif;?></td>
      <td style="color:var(--muted);font-size:12px"><?= clean($w['note']?:'—') ?></td>
      <td style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($w['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php /* ===== REFERRAL ===== */ elseif ($tab === 'referral'): ?>
<div class="admin-topbar"><h1>🤝 Referral Program</h1></div>

<div class="seller-ref-grid">
  <div class="section-card" style="text-align:center;padding:18px"><div style="font-size:24px;font-weight:800;color:#a78bfa"><?= number_format($refStats['total']) ?></div><div style="font-size:11px;color:var(--muted);margin-top:3px">People Referred</div></div>
  <div class="section-card" style="text-align:center;padding:18px"><div style="font-size:24px;font-weight:800;color:#34d399">₹<?= number_format($refStats['earned'],2) ?></div><div style="font-size:11px;color:var(--muted);margin-top:3px">Total Earned</div></div>
  <div class="section-card" style="text-align:center;padding:18px"><div style="font-size:24px;font-weight:800;color:#fbbf24">₹<?= number_format($walletBal,2) ?></div><div style="font-size:11px;color:var(--muted);margin-top:3px">Wallet Balance</div></div>
</div>

<div class="section-card" style="margin-bottom:16px">
  <h3 style="margin-bottom:12px">🔗 Your Referral Link</h3>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="text" id="refLinkInput" value="<?= clean($refLink) ?>" readonly style="flex:1;min-width:0;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:9px 12px;color:var(--text);font-size:12px;font-family:monospace">
    <button type="button" onclick="copyRefLink()" class="btn btn-primary btn-sm" id="refCopyBtn">📋 Copy</button>
  </div>
  <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
    <a href="https://wa.me/?text=<?= urlencode('Join ' . SITE_NAME . '! Register: ' . $refLink) ?>" target="_blank" class="btn btn-outline btn-sm">💬 WhatsApp</a>
    <a href="https://t.me/share/url?url=<?= urlencode($refLink) ?>&text=<?= urlencode('Join ' . SITE_NAME) ?>" target="_blank" class="btn btn-outline btn-sm">✈️ Telegram</a>
  </div>
</div>

<div class="section-card">
  <h3 style="margin-bottom:14px">👥 Your Referrals</h3>
  <?php if (empty($refUsers)): ?>
  <p style="color:var(--muted);font-size:13px;padding:20px 0">No referrals yet. Share your link!</p>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Joined</th><th style="text-align:right">Commission</th></tr></thead>
    <tbody>
    <?php foreach($refUsers as $ru): ?>
    <tr>
      <td><?= clean($ru['name']) ?></td>
      <td style="color:var(--muted)"><?= date('d M Y', strtotime($ru['created_at'])) ?></td>
      <td style="text-align:right;color:#34d399"><?= $ru['earned']>0 ? '₹'.number_format($ru['earned'],2) : '<span style="color:var(--muted)">₹0.00</span>' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php /* ===== ACCOUNT ===== */ elseif ($tab === 'account'): ?>
<div class="admin-topbar"><h1>👤 Account Settings</h1></div>

<div class="seller-two-grid">
  <div class="section-card">
    <h3 style="margin-bottom:14px">✉️ Change Email / Phone</h3>
    <form method="POST" action="<?= SITE_URL ?>/dashboard.php?tab=account">
      <?= csrf_field() ?><input type="hidden" name="update_account" value="1">
      <div class="form-group" style="margin-bottom:10px"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Email Address</label><input class="form-control" type="email" name="email" value="<?= clean($me['email']??'') ?>" required></div>
      <div class="form-group" style="margin-bottom:10px"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Phone / WhatsApp</label><input class="form-control" type="text" name="phone" value="<?= clean($me['phone']??'') ?>" placeholder="91XXXXXXXXXX"></div>
      <div class="form-group" style="margin-bottom:12px"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Current Password *</label><input class="form-control" type="password" name="current_password" required></div>
      <button type="submit" class="btn btn-outline btn-sm">Save Changes</button>
    </form>
  </div>
  <div class="section-card">
    <h3 style="margin-bottom:14px">🔒 Change Password</h3>
    <form method="POST" action="<?= SITE_URL ?>/dashboard.php?tab=account">
      <?= csrf_field() ?><input type="hidden" name="update_password" value="1">
      <div class="form-group" style="margin-bottom:10px"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">New Password</label><input class="form-control" type="password" name="new_password" placeholder="Min 6 characters" required></div>
      <div class="form-group" style="margin-bottom:12px"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Confirm New Password</label><input class="form-control" type="password" name="confirm_password" required></div>
      <button type="submit" class="btn btn-primary btn-sm">Update Password</button>
    </form>
  </div>
</div>

<?php endif; ?>

  </main>
</div>
<div id="toast-container" class="toast-container"></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
function copyRefLink() {
  var inp = document.getElementById('refLinkInput');
  if (!inp) return;
  inp.select(); inp.setSelectionRange(0, 99999);
  try { document.execCommand('copy'); } catch(e) { navigator.clipboard && navigator.clipboard.writeText(inp.value); }
  var btn = document.getElementById('refCopyBtn');
  if (btn) { btn.textContent = '✅ Copied!'; setTimeout(function(){ btn.textContent = '📋 Copy'; }, 2000); }
}
function upv(){
  var b=parseFloat(document.getElementById('bpi').value)||0,
      f=<?= (float)$buyerFeePct ?>,c=<?= (float)$commissionPct ?>;
  var bp=Math.round(b*(1+f/100)),ye=Math.round(b*(1-c/100));
  document.getElementById('pv').innerHTML=b>0?'Buyer pays <strong style="color:#a78bfa">₹'+bp.toLocaleString('en-IN')+'</strong> · You earn <strong style="color:#34d399">₹'+ye.toLocaleString('en-IN')+'</strong>':'Enter price to see breakdown';
}
</script>
<script>
function toggleSellerSidebar() {
  document.getElementById('sellerSidebar').classList.toggle('open');
  document.getElementById('sellerOverlay').classList.toggle('open');
  document.body.style.overflow =
    document.getElementById('sellerSidebar').classList.contains('open') ? 'hidden' : '';
}
document.querySelectorAll('.sidebar-nav a').forEach(function(a){
  a.addEventListener('click', function(){
    if (window.innerWidth <= 900) toggleSellerSidebar();
  });
});
</script>
</body>
</html>

<?php
// =====================================================================
// BUYER → original layout
// =====================================================================
else:
$currentActiveTab = $tab;
require_once __DIR__ . '/includes/header.php';
?>
<style>
.db-wrap{max-width:1100px;margin:0 auto;padding:32px 16px}
@media(max-width:600px){.account-grid{grid-template-columns:1fr !important}}
.db-hero{background:linear-gradient(135deg,rgba(124,58,237,.15),rgba(139,92,246,.05));border:1px solid rgba(124,58,237,.2);border-radius:16px;padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px}
.db-hero-greet{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:800;margin-bottom:3px}
.db-tabs{display:flex;gap:4px;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:5px;margin-bottom:22px;overflow-x:auto;flex-wrap:nowrap}
.db-tab{padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:600;color:var(--muted2);text-decoration:none;white-space:nowrap;transition:all .18s;border:none;background:none;cursor:pointer}
.db-tab.active,.db-tab:hover{background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff}
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px}
@media(max-width:700px){.stat-row{grid-template-columns:1fr 1fr}}
.stat-box{background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:18px 16px;text-align:center}
.stat-box .sv{font-size:22px;font-weight:800;line-height:1;margin-bottom:4px}
.stat-box .sl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:22px;margin-bottom:16px}
.card-title{font-size:14px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.card.card-compact{padding:16px 18px;margin-bottom:12px}
.card.card-compact .card-title{margin-bottom:10px;font-size:13px}
.card.card-compact .form-group{margin-bottom:8px!important}
.orow{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--border)}
.orow:last-child{border-bottom:none}
.orow-img{width:44px;height:34px;object-fit:cover;border-radius:7px;background:var(--bg2);flex-shrink:0}
.orow-ni{width:44px;height:34px;background:var(--bg-card2);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.section-hidden{display:none !important}
.pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px}
.pf-grid .span2{grid-column:1/-1}
.fl13{font-size:13px;margin-bottom:5px;display:block;font-weight:600}
</style>

<div class="db-wrap">
  <div class="db-hero">
    <div>
      <div class="db-hero-greet">👋 Hey, <?= clean($_SESSION['user_name']) ?>!</div>
      
      <div style="color:var(--muted);font-size:13px">Welcome to your dashboard</div>
      
    </div>
    <div style="display:flex;gap:10px">
      <a href="<?= SITE_URL ?>/#products" class="btn btn-primary btn-sm">🛍️ Browse Products</a>
    </div>
    
  </div>

  <div class="db-tabs">
    <button type="button" class="db-tab <?= $currentActiveTab==='orders'?'active':'' ?>" onclick="switchTab('orders')">📦 My Orders</button>
    <button type="button" class="db-tab <?= $currentActiveTab==='wishlist'?'active':'' ?>" onclick="switchTab('wishlist')">❤️ Wishlist</button>
    <button type="button" class="db-tab <?= $currentActiveTab==='referral'?'active':'' ?>" onclick="switchTab('referral')">🤝 Referral</button>
    <button type="button" class="db-tab <?= $currentActiveTab==='account'?'active':'' ?>" onclick="switchTab('account')">👤 Account</button>
  </div>

  <!-- ORDERS -->
  <div id="tab-orders" class="dashboard-section <?= $currentActiveTab!=='orders'?'section-hidden':'' ?>">
    <?php if (empty($orders)): ?>
    <div style="text-align:center;padding:70px 20px;background:var(--bg-card);border:1px solid var(--border);border-radius:14px">
      <div style="font-size:52px;margin-bottom:14px">🛒</div>
      <h3 style="margin-bottom:8px">No orders yet</h3>
      <p style="color:var(--muted);margin-bottom:20px;font-size:14px">Browse our premium source code collection</p>
      <a href="<?= SITE_URL ?>/#products" class="btn btn-primary">Browse Products</a>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-title">📦 My Orders <span style="color:var(--muted);font-weight:400;font-size:12px">(<?= count($orders) ?>)</span></div>
      <?php foreach ($orders as $o): ?>
      <div class="orow">
        <?php if ($o['image']): ?><img class="orow-img" src="<?= SITE_URL ?>/uploads/products/<?= clean($o['image']) ?>"><?php else: ?><div class="orow-ni">📦</div><?php endif; ?>
        <div style="flex:1;min-width:0">
          <strong style="display:block;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= clean($o['title']) ?></strong>
          <span style="color:var(--muted);font-size:11px;font-family:monospace"><?= clean($o['order_ref']) ?></span>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <strong style="color:#a78bfa;display:block;font-size:13px">₹<?= number_format($o['amount']) ?></strong>
          <span style="color:var(--muted);font-size:11px"><?= date('d M Y', strtotime($o['created_at'])) ?></span>
        </div>
        <div style="flex-shrink:0;min-width:80px;text-align:right">
          <?php if (in_array($o['status'], ['paid','delivered']) && $o['download_token']): ?>
            <a href="<?= SITE_URL ?>/download.php?token=<?= urlencode($o['download_token']) ?>&ref=<?= urlencode($o['order_ref']) ?>" class="btn btn-success btn-sm" target="_blank">⬇ Download</a>
          <?php elseif ($o['status']==='pending'): ?>
            <span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24">⏳ Pending</span>
          <?php elseif ($o['status']==='rejected'): ?>
            <a href="https://wa.me/<?= WA_NUMBER ?>?text=Order+<?= urlencode($o['order_ref']) ?>+rejected" target="_blank" class="btn btn-outline btn-sm" style="font-size:11px">Support</a>
          <?php else: ?>
            <span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- REFERRAL -->
  <div id="tab-referral" class="dashboard-section <?= $currentActiveTab!=='referral'?'section-hidden':'' ?>">
    <div class="card card-compact">
      <div class="card-title">🤝 Referral Program</div>
      <p style="color:var(--muted);font-size:13px;margin-bottom:14px">Earn <strong style="color:#a78bfa"><?= (float)getSetting($pdo,'referral_commission_pct',5) ?>%</strong> commission on every sale made by users you refer.</p>
      <div class="stat-row" style="grid-template-columns:repeat(3,1fr)">
        <div class="stat-box"><div class="sv" style="color:#a78bfa"><?= number_format($refStats['total']) ?></div><div class="sl">People Referred</div></div>
        <div class="stat-box"><div class="sv" style="color:#34d399">₹<?= number_format($refStats['earned'],2) ?></div><div class="sl">Total Earned</div></div>
        <div class="stat-box"><div class="sv" style="color:#fbbf24">₹<?= number_format($walletBal,2) ?></div><div class="sl">Wallet Balance</div></div>
      </div>
      <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:14px">
        <div style="font-size:13px;font-weight:700;margin-bottom:8px">🔗 Your Referral Link</div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="text" id="refLinkInput" value="<?= clean($refLink) ?>" readonly style="flex:1;min-width:0;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:9px 12px;color:var(--text);font-size:12px;font-family:monospace">
          <button type="button" onclick="copyRefLink()" class="btn btn-primary btn-sm" id="refCopyBtn">📋 Copy</button>
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
          <a href="https://wa.me/?text=<?= urlencode('Join ' . SITE_NAME . '! ' . $refLink) ?>" target="_blank" class="btn btn-outline btn-sm">💬 WhatsApp</a>
          <a href="https://t.me/share/url?url=<?= urlencode($refLink) ?>" target="_blank" class="btn btn-outline btn-sm">✈️ Telegram</a>
        </div>
      </div>
      <?php if (!empty($refUsers)): ?>
      <div style="font-size:14px;font-weight:700;margin-bottom:10px">👥 Your Referrals</div>
      <table style="width:100%;font-size:13px;border-collapse:collapse">
        <thead><tr style="color:var(--muted);text-align:left;border-bottom:1px solid var(--border)"><th style="padding:6px 10px">Name</th><th style="padding:6px 10px">Joined</th><th style="padding:6px 10px;text-align:right">Commission</th></tr></thead>
        <tbody><?php foreach($refUsers as $ru):?><tr style="border-bottom:1px solid var(--border)"><td style="padding:7px 10px"><?= clean($ru['name']) ?></td><td style="padding:7px 10px;color:var(--muted)"><?= date('d M Y', strtotime($ru['created_at'])) ?></td><td style="padding:7px 10px;text-align:right;color:#34d399"><?= $ru['earned']>0?'₹'.number_format($ru['earned'],2):'<span style="color:var(--muted)">₹0.00</span>' ?></td></tr><?php endforeach;?></tbody>
      </table>
      <?php else: ?>
      <p style="color:var(--muted);font-size:13px">No referrals yet. Share your link to start earning!</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ACCOUNT -->
  <div id="tab-account" class="dashboard-section <?= $currentActiveTab!=='account'?'section-hidden':'' ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start" class="account-grid">
      <div class="card card-compact">
        <div class="card-title">✉️ Change Email / Phone</div>
        <form method="POST" action="<?= SITE_URL ?>/dashboard.php?tab=account">
          <?= csrf_field() ?><input type="hidden" name="update_account" value="1">
          <div class="form-group" style="margin-bottom:8px"><label class="fl13">Email Address</label><input class="form-control" type="email" name="email" value="<?= clean($me['email']??'') ?>" required></div>
          <div class="form-group" style="margin-bottom:8px"><label class="fl13">Phone / WhatsApp</label><input class="form-control" type="text" name="phone" value="<?= clean($me['phone']??'') ?>" placeholder="91XXXXXXXXXX"></div>
          <div class="form-group" style="margin-bottom:10px"><label class="fl13">Current Password *</label><input class="form-control" type="password" name="current_password" required></div>
          <button type="submit" class="btn btn-outline btn-sm">Save Changes</button>
        </form>
      </div>
      <div class="card card-compact">
        <div class="card-title">🔒 Change Password</div>
        <form method="POST" action="<?= SITE_URL ?>/dashboard.php?tab=account">
          <?= csrf_field() ?><input type="hidden" name="update_password" value="1">
          <div class="form-group" style="margin-bottom:8px"><label class="fl13">New Password</label><input class="form-control" type="password" name="new_password" placeholder="Min 6 characters" required></div>
          <div class="form-group" style="margin-bottom:10px"><label class="fl13">Confirm New Password</label><input class="form-control" type="password" name="confirm_password" required></div>
          <button type="submit" class="btn btn-primary btn-sm">Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function switchTab(name) {
  document.querySelectorAll('.dashboard-section').forEach(function(s){ s.classList.add('section-hidden'); });
  document.querySelectorAll('.db-tab').forEach(function(b){ b.classList.remove('active'); });
  var s = document.getElementById('tab-'+name);
  if (s) s.classList.remove('section-hidden');
  if (window.event && window.event.target) window.event.target.classList.add('active');
}
function copyRefLink() {
  var inp = document.getElementById('refLinkInput');
  if (!inp) return;
  inp.select(); inp.setSelectionRange(0,99999);
  try { document.execCommand('copy'); } catch(e) { navigator.clipboard && navigator.clipboard.writeText(inp.value); }
  var btn = document.getElementById('refCopyBtn');
  if (btn) { btn.textContent='✅ Copied!'; setTimeout(function(){ btn.textContent='📋 Copy'; },2000); }
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>