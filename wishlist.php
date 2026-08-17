<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
requireLogin();

$items = $pdo->prepare("SELECT w.*, p.title, p.slug, p.price, p.discount_price, p.image, p.short_desc FROM wishlist w JOIN products p ON p.id=w.product_id WHERE w.user_id=? AND p.status='active' ORDER BY w.created_at DESC");
$items->execute([$_SESSION['user_id']]);
$items = $items->fetchAll();

$pageTitle = 'My Wishlist';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding:50px 0">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px">
    <h1 style="font-family:'Space Grotesk',sans-serif;font-size:24px;font-weight:700">❤️ My Wishlist</h1>
    <a href="<?= SITE_URL ?>/#products" class="btn btn-primary btn-sm">Browse Products</a>
  </div>

  <?php if (empty($items)): ?>
  <div style="text-align:center;padding:80px 20px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius)">
    <div style="font-size:56px;margin-bottom:16px">🤍</div>
    <h3 style="margin-bottom:8px">Your wishlist is empty</h3>
    <p style="color:var(--muted);margin-bottom:24px">Save products you like for later</p>
    <a href="<?= SITE_URL ?>/#products" class="btn btn-primary">Browse Products</a>
  </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px">
    <?php foreach ($items as $item):
      $price = $item['discount_price'] ?: $item['price'];
      $save  = $item['discount_price'] ? round((($item['price']-$item['discount_price'])/$item['price'])*100) : 0;
    ?>
    <div class="product-card reveal">
      <div class="card-img-wrap">
        <?php if ($item['image']): ?>
          <img src="<?= SITE_URL ?>/uploads/products/<?= clean($item['image']) ?>" alt="<?= clean($item['title']) ?>" loading="lazy">
        <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--bg2),var(--bg-card2));font-size:48px">💻</div>
        <?php endif; ?>
        <button onclick="removeWishlist(<?= $item['product_id'] ?>, this.closest('.product-card'))"
          style="position:absolute;top:10px;right:10px;background:rgba(239,68,68,.85);border:none;color:white;border-radius:50%;width:30px;height:30px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center">✕</button>
      </div>
      <div class="card-body">
        <h3><?= clean($item['title']) ?></h3>
        <?php if ($item['short_desc']): ?><p class="short-desc"><?= clean($item['short_desc']) ?></p><?php endif; ?>
        <div class="price-row">
          <?php if ($item['discount_price']): ?><span class="price-old">₹<?= number_format($item['price']) ?></span><?php endif; ?>
          <span class="price-new">₹<?= number_format($price) ?></span>
          <?php if ($save): ?><span class="price-save"><?= $save ?>% OFF</span><?php endif; ?>
        </div>
        <a href="<?= SITE_URL ?>/checkout.php?product_id=<?= $item['product_id'] ?>" class="btn btn-primary btn-block" style="margin-top:10px">🛒 Buy Now</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
function removeWishlist(productId, card) {
  var fd = new FormData();
  fd.append('action', 'toggle');
  fd.append('product_id', productId);
  fetch('<?= SITE_URL ?>/api/wishlist.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => { if (d.ok && !d.in_wishlist) { card.style.opacity='0'; card.style.transform='scale(.9)'; setTimeout(()=>card.remove(),300); } });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
