<?php
$pageTitle = 'Products';
require_once __DIR__ . '/includes/admin_header.php';
require_once dirname(__DIR__) . '/mail/Mailer.php';

// DELETE SINGLE GALLERY IMAGE
if (isset($_GET['delete_image'])) {
    if (empty($_GET['t']) || !hash_equals(csrf_token(), $_GET['t'])) {
        flash('error','Security check failed. Try again.'); redirect(SITE_URL.'/admin/products.php');
    }
    $gi = $pdo->prepare("SELECT * FROM product_images WHERE id=?"); $gi->execute([(int)$_GET['delete_image']]); $gimg = $gi->fetch();
    if ($gimg) {
        if (file_exists(UPLOAD_PATH.'/products/'.$gimg['image'])) unlink(UPLOAD_PATH.'/products/'.$gimg['image']);
        $pdo->prepare("DELETE FROM product_images WHERE id=?")->execute([$gimg['id']]);
        flash('success','Gallery image removed.');
    }
    redirect(SITE_URL.'/admin/products.php?edit='.(int)($_GET['pid'] ?? 0).'#product-form');
}

// APPROVE VENDOR LISTING
if (isset($_GET['approve_vendor'])) {
    if (empty($_GET['t']) || !hash_equals(csrf_token(), $_GET['t'])) {
        flash('error','Security check failed. Try again.'); redirect(SITE_URL.'/admin/products.php');
    }
    $pid = (int)$_GET['approve_vendor'];
    $pdo->prepare("UPDATE products SET approval_status='approved', status='active', reject_reason=NULL WHERE id=? AND seller_id IS NOT NULL")->execute([$pid]);
    flash('success','Listing approved — it is now live on the storefront.');
    redirect(SITE_URL.'/admin/products.php?vendor=pending');
}

// REJECT VENDOR LISTING
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_vendor'])) {
    verifyCsrf(SITE_URL.'/admin/products.php');
    $pid = (int)$_POST['reject_vendor'];
    $reason = clean($_POST['reject_reason'] ?? '');
    $pdo->prepare("UPDATE products SET approval_status='rejected', status='inactive', reject_reason=? WHERE id=? AND seller_id IS NOT NULL")->execute([$reason, $pid]);
    flash('success','Listing rejected.');
    redirect(SITE_URL.'/admin/products.php?vendor=pending');
}

// DELETE
if (isset($_GET['delete'])) {
    if (empty($_GET['t']) || !hash_equals(csrf_token(), $_GET['t'])) {
        flash('error','Security check failed. Try again.'); redirect(SITE_URL.'/admin/products.php');
    }
    $d = $pdo->prepare("SELECT * FROM products WHERE id=?"); $d->execute([(int)$_GET['delete']]); $dp = $d->fetch();
    if ($dp) {
        if ($dp['image'] && file_exists(UPLOAD_PATH.'/products/'.$dp['image'])) unlink(UPLOAD_PATH.'/products/'.$dp['image']);
        if ($dp['file_path'] && file_exists(DOWNLOAD_PATH.'/'.$dp['file_path'])) unlink(DOWNLOAD_PATH.'/'.$dp['file_path']);
        $gimgs = $pdo->prepare("SELECT * FROM product_images WHERE product_id=?"); $gimgs->execute([(int)$_GET['delete']]);
        foreach ($gimgs->fetchAll() as $gi) {
            if (file_exists(UPLOAD_PATH.'/products/'.$gi['image'])) unlink(UPLOAD_PATH.'/products/'.$gi['image']);
        }
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([(int)$_GET['delete']]);
        flash('success','Product deleted.');
    }
    redirect(SITE_URL.'/admin/products.php');
}

// SAVE (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL.'/admin/products.php');
    $editId      = (int)($_POST['edit_id'] ?? 0);
    $title       = clean($_POST['title'] ?? '');
    $slug        = clean($_POST['slug'] ?? ''); if (!$slug) $slug = slugify($title);
    $shortDesc   = clean($_POST['short_desc'] ?? '');
    $desc        = clean($_POST['description'] ?? '');
    $features    = clean($_POST['features'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $discPrice   = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $offerEndsAt = !empty($_POST['offer_ends_at']) ? date('Y-m-d H:i:s', strtotime($_POST['offer_ends_at'])) : null;
    $demoUrl     = clean($_POST['demo_url'] ?? '');
    $catId       = (int)($_POST['category_id'] ?? 0) ?: null;
    $sortOrder   = (int)($_POST['sort_order'] ?? 0);
    $status      = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    if (!$title || !$price) { flash('error','Title and price are required.'); redirect(SITE_URL.'/admin/products.php'.($editId ? '?edit='.$editId : '')); }

    // Image upload — file takes priority; else try the pasted URL (e.g. Google Images link)
    $imgName = clean($_POST['existing_image'] ?? '');
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $imgName = 'img_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
            move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_PATH.'/products/'.$imgName);
        }
    } elseif (!empty($_POST['image_url'])) {
        $fetched = fetchImageFromUrl($_POST['image_url']);
        if ($fetched) { $imgName = $fetched; }
        else { flash('info', 'Thumbnail URL could not be fetched — kept the previous/no image. Try a direct image link (ending in .jpg/.png/.webp).'); }
    }
    // File upload — file takes priority; else use a pasted external link (Google Drive/Mega/etc.)
    // An external link is stored as-is and download.php redirects straight to it — no storage/bandwidth used on our own hosting.
    $fileName = clean($_POST['existing_file'] ?? '');
    if (!empty($_FILES['product_file']['name']) && $_FILES['product_file']['error'] === 0) {
        $ext2 = strtolower(pathinfo($_FILES['product_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext2, ['zip','rar','7z','pdf'])) {
            $fileName = 'dl_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext2;
            move_uploaded_file($_FILES['product_file']['tmp_name'], DOWNLOAD_PATH.'/'.$fileName);
        }
    } elseif (!empty($_POST['product_file_url'])) {
        $flink = trim($_POST['product_file_url']);
        if (filter_var($flink, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $flink)) {
            $fileName = $flink; // stored as-is; download.php detects http(s):// and redirects to it directly
        } else {
            flash('info', 'Download link looks invalid — kept the previous file/link.');
        }
    }

    if ($editId) {
        $pdo->prepare("UPDATE products SET category_id=?,title=?,slug=?,short_desc=?,description=?,features=?,price=?,discount_price=?,offer_ends_at=?,demo_url=?,image=?,file_path=?,status=?,sort_order=? WHERE id=?")
            ->execute([$catId,$title,$slug,$shortDesc,$desc,$features,$price,$discPrice,$offerEndsAt,$demoUrl,$imgName ?: null,$fileName ?: null,$status,$sortOrder,$editId]);
        $productId = $editId;
        flash('success','Product updated.');
    } else {
        $pdo->prepare("INSERT INTO products (category_id,title,slug,short_desc,description,features,price,discount_price,offer_ends_at,demo_url,image,file_path,status,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$catId,$title,$slug,$shortDesc,$desc,$features,$price,$discPrice,$offerEndsAt,$demoUrl,$imgName ?: null,$fileName ?: null,$status,$sortOrder]);
        $productId = $pdo->lastInsertId();
        flash('success','Product added!');

        // Send new product notification to active newsletter subscribers
        try {
            $productUrl  = SITE_URL . '/product.php?slug=' . rawurlencode($slug);
            $subscribers = $pdo->query("SELECT email, name FROM newsletter_subscribers WHERE status='active'")->fetchAll();
            foreach ($subscribers as $sub) {
                Mailer::sendNewProductAlert(
                    $sub['email'],
                    $sub['name'] ?: 'Subscriber',
                    $title,
                    $productUrl,
                    $shortDesc ?: $desc,
                    $discPrice ?: $price
                );
            }
        } catch (Exception $nlEx) {
            error_log('Newsletter new-product notify error: ' . $nlEx->getMessage());
        }
    }

    // Gallery images (3-5 extra photos customers can browse on the product page)
    if (!empty($_FILES['gallery_images']['name'][0])) {
        $maxSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM product_images WHERE product_id=".(int)$productId)->fetchColumn();
        foreach ($_FILES['gallery_images']['name'] as $i => $gname) {
            if ($_FILES['gallery_images']['error'][$i] !== 0 || !$gname) continue;
            $gext = strtolower(pathinfo($gname, PATHINFO_EXTENSION));
            if (!in_array($gext, ['jpg','jpeg','png','webp'])) continue;
            $maxSort++;
            $newName = 'gal_'.time().'_'.bin2hex(random_bytes(4)).'.'.$gext;
            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], UPLOAD_PATH.'/products/'.$newName)) {
                $pdo->prepare("INSERT INTO product_images (product_id, image, sort_order) VALUES (?,?,?)")
                    ->execute([$productId, $newName, $maxSort]);
            }
        }
    }

    // Gallery images via pasted URLs (one per line, e.g. copied from Google Images) — downloaded locally so they load fast
    if (!empty($_POST['gallery_image_urls'])) {
        $maxSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM product_images WHERE product_id=".(int)$productId)->fetchColumn();
        $urls = preg_split('/[\r\n]+/', trim($_POST['gallery_image_urls']));
        $failedCount = 0;
        $addedCount  = 0;
        foreach (array_slice($urls, 0, 5) as $u) {
            $u = trim($u);
            if ($u === '') continue;
            $fetched = fetchImageFromUrl($u);
            if (!$fetched) { $failedCount++; continue; }
            $maxSort++; $addedCount++;
            $pdo->prepare("INSERT INTO product_images (product_id, image, sort_order) VALUES (?,?,?)")
                ->execute([$productId, $fetched, $maxSort]);
        }
        if ($failedCount) {
            flash('info', $addedCount.' gallery image(s) added from URL, '.$failedCount.' link(s) could not be fetched (use a direct image link ending in .jpg/.png/.webp).');
        }
    }

    redirect(SITE_URL.'/admin/products.php?edit='.$productId.'#product-form');
}

// EDIT mode
$editMode = false;
$ep = ['id'=>0,'category_id'=>'','title'=>'','slug'=>'','short_desc'=>'','description'=>'','features'=>'','price'=>'','discount_price'=>'','offer_ends_at'=>'','demo_url'=>'','status'=>'active','sort_order'=>0,'image'=>'','file_path'=>''];
$galleryImgs = [];
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM products WHERE id=?"); $s->execute([(int)$_GET['edit']]); $found = $s->fetch();
    if ($found) {
        $ep = $found; $editMode = true;
        $gs = $pdo->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order,id"); $gs->execute([$ep['id']]);
        $galleryImgs = $gs->fetchAll();
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$products   = $pdo->query("SELECT p.*,c.name cname,u.name sname,u.email semail FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN users u ON u.id=p.seller_id ORDER BY p.sort_order,p.created_at DESC")->fetchAll();
$vendorPending = $pdo->query("SELECT p.*,u.name sname,u.email semail FROM products p JOIN users u ON u.id=p.seller_id WHERE p.approval_status='pending' ORDER BY p.created_at ASC")->fetchAll();
?>
<div class="admin-topbar">
  <h1><?= $editMode ? 'Edit Product' : 'Products' ?>
    <?php if (!$editMode && count($vendorPending) > 0): ?>
    <span style="background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.3);border-radius:20px;font-size:12px;font-weight:700;padding:3px 10px;margin-left:6px;vertical-align:middle"><?= count($vendorPending) ?> seller listing(s) awaiting approval</span>
    <?php endif; ?>
  </h1>
  <?php if ($editMode): ?>
    <a href="<?= SITE_URL ?>/admin/products.php" class="btn btn-outline btn-sm">← All Products</a>
  <?php else: ?>
    <button type="button" class="btn btn-primary" onclick="goToFormTab()">+ Add Product</button>
  <?php endif; ?>
</div>

<style>
.stabs-wrap{display:flex;gap:0;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:5px;margin-bottom:22px;overflow-x:auto;flex-wrap:nowrap}
.stab-btn{padding:9px 18px;border-radius:9px;font-size:13px;font-weight:600;color:var(--muted2);white-space:nowrap;cursor:pointer;border:none;background:none;transition:all .18s;text-decoration:none}
.stab-btn.active,.stab-btn:hover{background:var(--primary);color:#fff}
.spanel{display:none}.spanel.active{display:block}
</style>

<!-- Tab Nav -->
<div class="stabs-wrap">
  <button class="stab-btn<?= !$editMode ? ' active' : '' ?>" onclick="switchTab('list',this)">📋 All Products (<?= count($products) ?>)</button>
  <?php if (!empty($vendorPending)): ?>
  <button class="stab-btn" onclick="switchTab('pending',this)">🕓 Pending Approval (<?= count($vendorPending) ?>)</button>
  <?php endif; ?>
  <button class="stab-btn<?= $editMode ? ' active' : '' ?>" onclick="switchTab('product-form',this)"><?= $editMode ? '✏️ Edit Product' : '➕ Add Product' ?></button>
</div>
<script>
function switchTab(id, el) {
  document.querySelectorAll('.spanel').forEach(function(p){ p.classList.remove('active'); });
  document.querySelectorAll('.stab-btn').forEach(function(b){ b.classList.remove('active'); });
  var panel = document.getElementById('sp-'+id);
  if (panel) panel.classList.add('active');
  if (el) el.classList.add('active');
  history.replaceState(null,'','#'+id);
}
function goToFormTab() {
  var btn = document.querySelector('[onclick*="\'product-form\'"]');
  if (btn) switchTab('product-form', btn);
  document.getElementById('product-form').scrollIntoView({behavior:'smooth'});
}
window.addEventListener('DOMContentLoaded', function(){
  var h = location.hash.replace('#','');
  var btn = h ? document.querySelector('[onclick*="\''+h+'\'"]') : null;
  if (btn) switchTab(h, btn);
});
</script>

<?php if (!$editMode): ?>
<div id="sp-list" class="spanel active">
<div class="section-card">
  <h3>All Products (<?= count($products) ?>)</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Thumbnail</th><th>Title</th><th>Category</th><th>Seller</th><th>Price</th><th>Views</th><th>File</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($products)): ?><tr><td colspan="10" style="text-align:center;color:var(--muted);padding:30px">No products yet. Add your first product below.</td></tr><?php endif; ?>
        <?php foreach ($products as $p): ?>
        <tr>
          <td style="color:var(--muted)"><?= $p['id'] ?></td>
          <td><?php if ($p['image']): ?><img src="<?= SITE_URL ?>/uploads/products/<?= clean($p['image']) ?>" style="width:50px;height:38px;object-fit:contain;background:var(--bg2);border-radius:6px"><?php else: ?>—<?php endif; ?></td>
          <td><strong><?= clean($p['title']) ?></strong><br><small style="color:var(--muted)">/product/<?= clean($p['slug']) ?></small></td>
          <td><?= clean($p['cname'] ?? '—') ?></td>
          <td>
            <?php if ($p['seller_id']): ?>
              <span style="font-size:12px"><?= clean($p['sname']) ?></span><br>
              <?php if ($p['approval_status']==='pending'): ?><span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24;font-size:11px">Pending</span>
              <?php elseif ($p['approval_status']==='rejected'): ?><span class="badge" style="background:rgba(239,68,68,.15);color:#ef4444;font-size:11px">Rejected</span>
              <?php else: ?><span class="badge badge-active" style="font-size:11px">Vendor</span><?php endif; ?>
            <?php else: ?><span style="color:var(--muted);font-size:12px">— Admin —</span><?php endif; ?>
          </td>
          <td>
            <?php if ($p['discount_price']): ?>
              <s style="color:var(--muted);font-size:12px">₹<?= number_format($p['price']) ?></s><br>
              <strong style="color:#a78bfa">₹<?= number_format($p['discount_price']) ?></strong>
            <?php else: ?><strong>₹<?= number_format($p['price']) ?></strong><?php endif; ?>
          </td>
          <td><span style="color:var(--muted)">👁️ <?= number_format((int)($p['view_count'] ?? 0)) ?></span></td>
          <td><?= $p['file_path'] ? '<span style="color:var(--success)">✓</span>' : '<span style="color:var(--danger)">✗</span>' ?></td>
          <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="?edit=<?= $p['id'] ?>#product-form" class="btn btn-primary btn-sm">Edit</a>
            <a href="<?= SITE_URL ?>/product.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn btn-outline btn-sm">View</a>
            <a href="?delete=<?= $p['id'] ?>&t=<?= csrf_token() ?>" class="btn btn-danger btn-sm confirm-action" data-confirm="Delete this product?">Del</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php endif; ?>

<?php if (!$editMode && !empty($vendorPending)): ?>
<div id="sp-pending" class="spanel">
<div class="section-card" id="vendor-pending" style="border-color:rgba(251,191,36,.3)">
  <h3>🕓 Seller Listings Awaiting Approval (<?= count($vendorPending) ?>)</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Title</th><th>Seller</th><th>Seller's Price</th><th>Buyer Sees</th><th>Seller Earns</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($vendorPending as $vp): ?>
        <tr>
          <td><strong><?= clean($vp['title']) ?></strong></td>
          <td><?= clean($vp['sname']) ?><br><small style="color:var(--muted)"><?= clean($vp['semail']) ?></small></td>
          <td>₹<?= number_format($vp['seller_base_price'] ?: $vp['price']) ?></td>
          <td>₹<?= number_format($vp['price']) ?></td>
          <td style="color:#34d399">₹<?= number_format(vendorSellerEarning($pdo, $vp['seller_base_price'] ?: $vp['price']),2) ?></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="?edit=<?= $vp['id'] ?>#product-form" class="btn btn-outline btn-sm">Preview / Edit</a>
            <a href="?approve_vendor=<?= $vp['id'] ?>&t=<?= csrf_token() ?>" class="btn btn-success btn-sm confirm-action" data-confirm="Approve this listing? It will go live immediately.">✅ Approve</a>
            <button type="button" class="btn btn-danger btn-sm" onclick="var r=prompt('Reason for rejection (optional):'); if(r===null) return; var f=document.getElementById('rejectForm<?= $vp['id'] ?>'); f.reject_reason.value=r; f.submit();">❌ Reject</button>
            <form id="rejectForm<?= $vp['id'] ?>" method="POST" style="display:none">
              <?= csrf_field() ?>
              <input type="hidden" name="reject_vendor" value="<?= $vp['id'] ?>">
              <input type="hidden" name="reject_reason" value="">
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php endif; ?>

<div id="sp-product-form" class="spanel<?= $editMode ? ' active' : '' ?>">
<div class="section-card" id="product-form">
  <h3><?= $editMode ? 'Edit: '.clean($ep['title']) : 'Add New Product' ?></h3>
  <style>
  .pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px}
  .pf-grid .span2{grid-column:1/-1}
  .pf-compact .form-group{margin-bottom:10px}
  .pf-compact label{font-size:12px;font-weight:600;margin-bottom:3px;display:block;color:var(--muted2)}
  .pf-compact .form-control{padding:6px 10px;font-size:13px}
  .pf-compact textarea.form-control{resize:vertical}
  .pf-section{border-top:1px solid var(--border);padding-top:12px;margin-top:4px;grid-column:1/-1}
  .pf-section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:10px}
  </style>
  <form method="POST" enctype="multipart/form-data" class="pf-compact">
    <?= csrf_field() ?>
    <input type="hidden" name="edit_id" value="<?= (int)$ep['id'] ?>">
    <input type="hidden" name="existing_image" value="<?= clean($ep['image']) ?>">
    <input type="hidden" name="existing_file" value="<?= clean($ep['file_path']) ?>">

    <div class="pf-grid">
      <!-- Row 1: Title + Slug -->
      <div class="form-group" style="grid-column:1/-1">
        <label>Title *</label>
        <input class="form-control" type="text" name="title" value="<?= clean($ep['title']) ?>" required placeholder="Product name">
      </div>

      <!-- Row 2: Price + Discount -->
      <div class="form-group">
        <label>Price (₹) *</label>
        <input class="form-control" type="number" name="price" value="<?= $ep['price'] ?>" step="1" required>
      </div>
      <div class="form-group">
        <label>Discount Price (₹)</label>
        <input class="form-control" type="number" name="discount_price" value="<?= $ep['discount_price'] ?>" step="1" placeholder="Leave blank for none">
      </div>

      <!-- Row 3: Category + Status -->
      <div class="form-group">
        <label>Category</label>
        <select class="form-control" name="category_id">
          <option value="">-- None --</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $ep['category_id']==$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select class="form-control" name="status">
          <option value="active"   <?= $ep['status']==='active'   ?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $ep['status']==='inactive' ?'selected':'' ?>>Inactive</option>
        </select>
      </div>

      <!-- Row 4: Demo URL + Slug + Sort -->
      <div class="form-group">
        <label>Demo URL</label>
        <input class="form-control" type="url" name="demo_url" value="<?= clean($ep['demo_url']) ?>" placeholder="https://">
      </div>
      <div class="form-group" style="display:grid;grid-template-columns:3fr 1fr;gap:8px">
        <div>
          <label>URL Slug</label>
          <input class="form-control" type="text" name="slug" value="<?= clean($ep['slug']) ?>" placeholder="auto-generated">
        </div>
        <div>
          <label>Sort</label>
          <input class="form-control" type="number" name="sort_order" value="<?= $ep['sort_order'] ?>">
        </div>
      </div>

      <!-- Offer ends -->
      <div class="form-group">
        <label>Offer Ends At</label>
        <input class="form-control" type="datetime-local" name="offer_ends_at" value="<?= $ep['offer_ends_at'] ? date('Y-m-d\TH:i', strtotime($ep['offer_ends_at'])) : '' ?>">
      </div>
      <div class="form-group">
        <label>Short Description</label>
        <input class="form-control" type="text" name="short_desc" value="<?= clean($ep['short_desc']) ?>" placeholder="One line summary">
      </div>

      <!-- Descriptions -->
      <div class="form-group span2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
          <label>Full Description</label>
          <textarea class="form-control" name="description" rows="3"><?= clean($ep['description']) ?></textarea>
        </div>
        <div>
          <label>Features (one per line)</label>
          <textarea class="form-control" name="features" rows="3" placeholder="Complete source code&#10;Admin dashboard&#10;Mobile responsive"><?= clean($ep['features']) ?></textarea>
        </div>
      </div>

      <!-- Files section — redesigned -->
      <div class="pf-section" style="grid-column:1/-1">
        <div class="pf-section-title">📎 Media &amp; Files</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

          <!-- Thumbnail Card -->
          <div style="background:var(--card2,rgba(255,255,255,.04));border:1.5px dashed var(--border);border-radius:12px;padding:18px">
            <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">🖼️ Product Thumbnail</div>
            <?php if ($ep['image']): ?>
            <div style="margin-bottom:10px;text-align:center">
              <img id="img-preview" src="<?= SITE_URL ?>/uploads/products/<?= clean($ep['image']) ?>"
                   style="max-height:90px;max-width:100%;border-radius:8px;border:1px solid var(--border)">
            </div>
            <?php else: ?>
            <div id="img-preview-wrap" style="height:80px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;color:var(--muted);font-size:13px">No image yet</div>
            <?php endif; ?>
            <label style="display:flex;align-items:center;gap:8px;background:var(--primary,#7c3aed);color:#fff;border-radius:8px;padding:9px 14px;cursor:pointer;font-size:13px;font-weight:600;justify-content:center;transition:.2s">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Choose Image
              <input type="file" name="image" id="image-file-input" accept=".jpg,.jpeg,.png,.webp" style="display:none" onchange="previewImg(this)">
            </label>
            <div style="display:flex;align-items:center;gap:8px;margin:10px 0;color:var(--muted);font-size:12px"><div style="flex:1;height:1px;background:var(--border)"></div>or paste URL<div style="flex:1;height:1px;background:var(--border)"></div></div>
            <input class="form-control" type="url" name="image_url" id="image-url-input" placeholder="https://i.imgur.com/..." style="font-size:13px">
          </div>

          <!-- Downloadable File Card -->
          <div style="background:var(--card2,rgba(255,255,255,.04));border:1.5px dashed var(--border);border-radius:12px;padding:18px">
            <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">📦 Downloadable File</div>
            <?php if ($ep['file_path']): ?>
            <div style="background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.25);border-radius:8px;padding:10px 12px;margin-bottom:10px;font-size:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <?php if (preg_match('~^https?://~i', $ep['file_path'])): ?>
                🔗 <a href="<?= clean($ep['file_path']) ?>" target="_blank" style="color:#a78bfa;word-break:break-all"><?= clean(mb_strimwidth($ep['file_path'], 0, 38, '…')) ?></a>
                <a href="<?= clean($ep['file_path']) ?>" target="_blank" class="btn btn-outline btn-sm" style="padding:2px 10px;font-size:11px;margin-left:auto">↗ Open</a>
              <?php else: ?>
                ✅ <span style="color:var(--success)"><?= clean(mb_strimwidth($ep['file_path'], 0, 30, '…')) ?></span>
                <a href="<?= SITE_URL ?>/admin/download_file.php?id=<?= (int)$ep['id'] ?>" class="btn btn-outline btn-sm" style="padding:2px 10px;font-size:11px;margin-left:auto">⬇ Download</a>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <label style="display:flex;align-items:center;gap:8px;background:rgba(99,102,241,.15);color:#a5b4fc;border:1.5px solid rgba(99,102,241,.35);border-radius:8px;padding:9px 14px;cursor:pointer;font-size:13px;font-weight:600;justify-content:center;transition:.2s" onmouseover="this.style.background='rgba(99,102,241,.25)'" onmouseout="this.style.background='rgba(99,102,241,.15)'">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Choose File <span style="font-size:11px;opacity:.7">(ZIP / RAR / PDF)</span>
              <input type="file" name="product_file" accept=".zip,.rar,.7z,.pdf" style="display:none" onchange="showFileName(this)">
            </label>
            <div id="chosen-file-name" style="text-align:center;font-size:11px;color:var(--muted);margin-top:5px;min-height:14px"></div>
            <div style="display:flex;align-items:center;gap:8px;margin:10px 0;color:var(--muted);font-size:12px"><div style="flex:1;height:1px;background:var(--border)"></div>or paste link<div style="flex:1;height:1px;background:var(--border)"></div></div>
            <input class="form-control" type="url" name="product_file_url" placeholder="https://drive.google.com/..." style="font-size:13px" value="<?= (isset($ep['file_path']) && preg_match('~^https?://~i', $ep['file_path'])) ? clean($ep['file_path']) : '' ?>">
          </div>
        </div>

        <!-- Gallery -->
        <div style="background:var(--card2,rgba(255,255,255,.04));border:1.5px dashed var(--border);border-radius:12px;padding:18px">
          <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">🖼️ Gallery Photos <span style="font-weight:400;opacity:.6">(up to 5)</span></div>
          <?php if (!empty($galleryImgs)): ?>
          <div class="admin-gallery" style="margin-bottom:12px">
            <?php foreach ($galleryImgs as $gi): ?>
            <div class="admin-gallery-item">
              <img src="<?= SITE_URL ?>/uploads/products/<?= clean($gi['image']) ?>">
              <a class="del-img confirm-action" data-confirm="Remove this gallery image?"
                 href="?delete_image=<?= $gi['id'] ?>&pid=<?= $ep['id'] ?>&t=<?= csrf_token() ?>">×</a>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <label style="display:flex;align-items:center;gap:8px;background:rgba(251,191,36,.08);color:#fbbf24;border:1.5px solid rgba(251,191,36,.25);border-radius:8px;padding:9px 14px;cursor:pointer;font-size:13px;font-weight:600;justify-content:center" onmouseover="this.style.background='rgba(251,191,36,.16)'" onmouseout="this.style.background='rgba(251,191,36,.08)'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              Choose Photos
              <input type="file" name="gallery_images[]" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none" onchange="showGalleryCount(this)">
            </label>
            <textarea class="form-control" name="gallery_image_urls" rows="2" placeholder="Or paste image URLs, one per line" style="font-size:13px;resize:none"></textarea>
          </div>
          <div id="gallery-file-count" style="font-size:11px;color:var(--muted);margin-top:5px;min-height:14px"></div>
        </div>
      </div>

      <script>
      function previewImg(input) {
        if (input.files && input.files[0]) {
          var reader = new FileReader();
          reader.onload = function(e) {
            var prev = document.getElementById('img-preview');
            if (!prev) { prev = document.createElement('img'); prev.id='img-preview'; prev.style='max-height:90px;max-width:100%;border-radius:8px;border:1px solid var(--border)'; input.closest('div').insertBefore(prev, input.closest('label')); }
            prev.src = e.target.result;
            var wrap = document.getElementById('img-preview-wrap');
            if (wrap) wrap.style.display = 'none';
          };
          reader.readAsDataURL(input.files[0]);
        }
      }
      function showFileName(input) {
        var el = document.getElementById('chosen-file-name');
        if (el) el.textContent = input.files[0] ? '📎 ' + input.files[0].name : '';
      }
      function showGalleryCount(input) {
        var el = document.getElementById('gallery-file-count');
        if (el) el.textContent = input.files.length ? '📷 ' + input.files.length + ' photo(s) selected' : '';
      }
      </script>
    </div>

    <div style="display:flex;gap:10px;margin-top:14px">
      <button type="submit" class="btn btn-primary"><?= $editMode ? 'Update Product' : 'Add Product' ?></button>
      <?php if ($editMode): ?><a href="<?= SITE_URL ?>/admin/products.php" class="btn btn-outline">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
</div>

<script>
(function() {
  var fileInput = document.getElementById('image-file-input');
  var urlInput  = document.getElementById('image-url-input');
  var preview   = document.getElementById('img-preview');
  if (!urlInput) return;

  // Picking a file clears the URL field (only one source should be submitted)
  if (fileInput) fileInput.addEventListener('change', function() { if (fileInput.value) urlInput.value = ''; });

  // Pasting/typing a URL clears the file field and shows an instant preview
  urlInput.addEventListener('input', function() {
    if (urlInput.value && fileInput) fileInput.value = '';
    if (!urlInput.value) return;
    if (!preview) {
      preview = document.createElement('img');
      preview.id = 'img-preview';
      preview.style.cssText = 'height:70px;border-radius:8px;margin-bottom:8px;display:block';
      urlInput.parentNode.insertBefore(preview, fileInput);
    }
    preview.src = urlInput.value;
  });
})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>