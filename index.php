<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$activeCat = (int)($_GET['cat'] ?? 0);

$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    JOIN products p ON p.category_id = c.id AND p.status = 'active'
    GROUP BY c.id
    HAVING product_count > 0
    ORDER BY c.name
")->fetchAll();

if ($activeCat) {
    $stmt = $pdo->prepare("SELECT p.*, c.name as cat_name FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active' AND p.category_id = ? ORDER BY p.sort_order ASC, p.created_at DESC");
    $stmt->execute([$activeCat]);
    $products = $stmt->fetchAll();
} else {
    $products = $pdo->query("SELECT p.*, c.name as cat_name FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active' ORDER BY p.sort_order ASC, p.created_at DESC")->fetchAll();
}

// 100 ka baseline add kiya gaya hai — real delivered orders ke saath ye upar badhta rahega
$totalSold = 100 + (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('paid','delivered')")->fetchColumn();
$totalProds = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();

$pageTitle = SITE_NAME . ' — Premium Source Code';
require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="hero-badge">⚡ Instant Automatic Delivery</div>
    <h1>Premium Store</h1>
    <p>High-quality source code with instant automatic delivery after payment. Bug-free, clean &amp; ready to deploy.</p>
    <a href="#products" class="btn btn-primary" style="padding:14px 32px;font-size:16px">
      Browse Products <span style="margin-left:4px">↓</span>
    </a>
    <div class="hero-stats" style="margin-top:40px">
      <div class="hero-stat"><span><?= $totalSold ?>+</span><small>Orders Delivered</small></div>
      <div class="hero-stat"><span><?= $totalProds ?>+</span><small>Products</small></div>
      <div class="hero-stat"><span>100%</span><small>Automatic</small></div>
      <div class="hero-stat"><span>24/7</span><small>Support</small></div>
    </div>
  </div>
</section>

<!-- FEATURES MARQUEE -->
<div class="features-marquee-wrap" style="margin-bottom:20px;overflow:hidden">
  <div class="features-marquee">
    <?php
    $featureItems = [
      ['⚡','Instant Delivery','Auto delivered to your email after payment'],
      ['🔐','Secure Payment','Razorpay encrypted checkout'],
      ['🐛','Bug Free Code','Tested & production ready'],
      ['💬','24/7 Support','WhatsApp support anytime'],
      ['🚀','Fast Setup','Deploy in minutes'],
      ['🛡️','Lifetime Access','Buy once, download forever'],
    ];
    // Double karke seamless loop banao
    $items = array_merge($featureItems, $featureItems);
    foreach ($items as $f): ?>
    <div class="feature-card-slide">
      <span style="font-size:22px"><?= $f[0] ?></span>
      <div>
        <strong style="font-size:14px"><?= $f[1] ?></strong>
        <p style="color:var(--muted);font-size:12px;margin-top:2px"><?= $f[2] ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- PRODUCTS -->
<section class="section" id="products">
  <div class="container">
    <div class="section-header reveal">
      <h2>Our Products</h2>
      <p>Premium source code — ready to deploy on your hosting</p>
    </div>

    <?php if (!empty($categories)): ?>
    <div class="category-pills reveal">
      <a href="<?= SITE_URL ?>/#products" class="category-pill <?= !$activeCat ? 'active' : '' ?>">All</a>
      <?php foreach ($categories as $cat): ?>
        <a href="<?= SITE_URL ?>/?cat=<?= $cat['id'] ?>#products" class="category-pill <?= $activeCat === (int)$cat['id'] ? 'active' : '' ?>"><?= clean($cat['name']) ?> (<?= $cat['product_count'] ?>)</a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="product-grid">
      <?php if (empty($products)): ?>
        <p style="color:var(--muted);grid-column:1/-1;text-align:center">No products yet. Check back soon!</p>
      <?php endif; ?>
      <?php foreach ($products as $i => $p):
        $price  = $p['discount_price'] ?: $p['price'];
        $save   = $p['discount_price'] ? round((($p['price'] - $p['discount_price']) / $p['price']) * 100) : 0;
        $feats  = array_filter(explode("\n", $p['features'] ?? ''));
      ?>
      <div class="product-card reveal" style="animation-delay:<?= $i * 0.05 ?>s">
        <div class="card-img-wrap">
          <span class="card-badge">⚡ Instant</span>
          <?php if ($p['image']): ?>
            <img src="<?= SITE_URL ?>/uploads/products/<?= clean($p['image']) ?>" alt="<?= clean($p['title']) ?>" loading="lazy">
          <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--bg2),var(--bg-card2));font-size:48px">💻</div>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if ($p['cat_name']): ?><p style="color:var(--primary);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px"><?= clean($p['cat_name']) ?></p><?php endif; ?>
          <h3><?= clean($p['title']) ?></h3>
          <?php if ($p['short_desc']): ?>
            <p class="short-desc"><?= clean($p['short_desc']) ?></p>
          <?php endif; ?>
          <?php if (!empty($feats)): ?>
          <ul class="card-features">
            <?php foreach (array_slice($feats, 0, 3) as $f): ?>
              <li><?= clean(trim($f)) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <div class="countdown">
            ⏱ Offer ends: <span class="countdown-timer" data-end="<?= getOfferEndTimestamp($p) * 1000 ?>">00:00:00</span>
          </div>
          <div class="price-row">
            <?php if ($p['discount_price']): ?>
              <span class="price-old">₹<?= number_format($p['price']) ?></span>
            <?php endif; ?>
            <span class="price-new">₹<?= number_format($price) ?></span>
            <?php if ($save): ?><span class="price-save"><?= $save ?>% OFF</span><?php endif; ?>
          </div>
          <a href="<?= SITE_URL ?>/product.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-primary btn-block">
            Buy Now — ₹<?= number_format($price) ?>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>