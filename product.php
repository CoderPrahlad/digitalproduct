<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.status='active' LIMIT 1");
$stmt->execute([$slug]);
$p = $stmt->fetch();

if (!$p) { http_response_code(404); require_once __DIR__.'/includes/header.php'; echo '<div class="container" style="padding:80px 0;text-align:center"><h2>Product Not Found</h2><a href="'.SITE_URL.'" class="btn btn-outline" style="margin-top:20px">Go Back</a></div>'; require_once __DIR__.'/includes/footer.php'; exit; }

$price = $p['discount_price'] ?: $p['price'];
$save  = $p['discount_price'] ? round((($p['price']-$p['discount_price'])/$p['price'])*100) : 0;
$feats = array_filter(explode("\n", $p['features'] ?? ''));

// View counter — one increment per visitor per product per session, so
// refreshing the page or an admin previewing it doesn't inflate the count.
if (empty($_SESSION['viewed_products'][$p['id']])) {
    $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id=?")->execute([$p['id']]);
    $_SESSION['viewed_products'][$p['id']] = time();
    $p['view_count'] = (int)($p['view_count'] ?? 0) + 1;
}

// Build gallery
$gs = $pdo->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order,id");
$gs->execute([$p['id']]);
$galleryImgs = $gs->fetchAll();
$allImages = [];
if ($p['image']) $allImages[] = $p['image'];
foreach ($galleryImgs as $gi) $allImages[] = $gi['image'];

// Reviews
$reviews = $pdo->prepare("SELECT r.*, u.name uname FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.product_id=? AND r.status='approved' ORDER BY r.created_at DESC");
$reviews->execute([$p['id']]);
$reviews = $reviews->fetchAll();
$avgRating  = count($reviews) ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$ratingDist = array_fill(1, 5, 0);
foreach ($reviews as $rv) $ratingDist[$rv['rating']]++;

$canReview = false; $userReviewed = false; $userOrderId = null; $inWishlist = false;
if (isLoggedIn()) {
    $ur = $pdo->prepare("SELECT id FROM orders WHERE user_id=? AND product_id=? AND status IN ('paid','delivered') LIMIT 1");
    $ur->execute([$_SESSION['user_id'], $p['id']]);
    $userOrder = $ur->fetch();
    if ($userOrder) {
        $userOrderId = $userOrder['id'];
        $hasReviewed = $pdo->prepare("SELECT id FROM reviews WHERE user_id=? AND product_id=?");
        $hasReviewed->execute([$_SESSION['user_id'], $p['id']]);
        if ($hasReviewed->fetch()) { $userReviewed = true; } else { $canReview = true; }
    }
    $wl = $pdo->prepare("SELECT id FROM wishlist WHERE user_id=? AND product_id=?");
    $wl->execute([$_SESSION['user_id'], $p['id']]);
    $inWishlist = (bool)$wl->fetch();
}

$descWords = explode(' ', strip_tags($p['description'] ?? ''));
$isLongDesc = count($descWords) > 40;

$pageTitle    = $p['title'];
$pageDesc     = mb_substr(strip_tags($p['description'] ?? $p['short_desc'] ?? ''), 0, 160);
$pageKeywords = clean($p['title']) . ', source code, php, download, ' . clean($p['cat_name'] ?? '');
$ogType       = 'product';
$ogImage      = $p['image'] ? SITE_URL . '/uploads/products/' . $p['image'] : SITE_URL . '/assets/img/og-default.png';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ---- product detail overrides ---- */
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:36px; align-items:start; }
@media(max-width:768px){ .detail-grid{ grid-template-columns:1fr; } }

/* Gallery */
.gallery-wrap { position:relative; }
.detail-img { border-radius:16px; overflow:hidden; background:var(--bg-card); border:1px solid var(--border); aspect-ratio:16/10; display:flex; align-items:center; justify-content:center; cursor:zoom-in; }
.detail-img img { width:100%; height:100%; object-fit:contain; transition:transform .3s; }
.detail-img:hover img { transform:scale(1.02); }

/* Wishlist heart floating on image */
.wish-float-btn {
  position:absolute; top:12px; right:12px; z-index:10;
  width:40px; height:40px; border-radius:50%;
  background:rgba(10,10,20,.55); backdrop-filter:blur(8px);
  border:1.5px solid rgba(255,255,255,.12);
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; font-size:20px; transition:all .2s;
  box-shadow:0 2px 12px rgba(0,0,0,.3);
}
.wish-float-btn:hover { transform:scale(1.15); background:rgba(239,68,68,.2); border-color:rgba(239,68,68,.4); }
.wish-float-btn.active { background:rgba(239,68,68,.25); border-color:#ef4444; }

/* Thumbs */
.gallery-thumbs { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.gallery-thumb { width:70px; height:54px; border-radius:8px; overflow:hidden; border:2px solid var(--border); cursor:pointer; transition:border-color .2s; flex-shrink:0; background:var(--bg-card); }
.gallery-thumb.active { border-color:var(--primary); }
.gallery-thumb img { width:100%; height:100%; object-fit:contain; }

/* Demo button below gallery */
.demo-under-gallery { margin-top:12px; }

/* Full-view image lightbox */
.img-lightbox { display:none; position:fixed; inset:0; z-index:1000; background:rgba(5,5,15,.9); align-items:center; justify-content:center; padding:40px; cursor:zoom-out; }
.img-lightbox.active { display:flex; }
.img-lightbox img { max-width:min(90vw,900px); max-height:85vh; width:auto; height:auto; object-fit:contain; border-radius:14px; border:1px solid var(--border); background:var(--bg-card); box-shadow:0 20px 60px rgba(0,0,0,.5); }
.lightbox-close { position:absolute; top:22px; right:28px; width:42px; height:42px; border-radius:50%; border:none; background:rgba(239,68,68,.85); color:#fff; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:.2s; box-shadow:0 2px 12px rgba(0,0,0,.4); }
.lightbox-close:hover { background:#ef4444; transform:scale(1.08); }

/* Description collapse */
.desc-text { color:var(--muted2); line-height:1.7; }
.desc-collapsed { max-height:80px; overflow:hidden; position:relative; }
.desc-collapsed::after { content:''; position:absolute; bottom:0; left:0; right:0; height:36px; background:linear-gradient(transparent, var(--bg)); }
.desc-more-btn { background:none; border:none; color:var(--primary); font-size:13px; cursor:pointer; padding:4px 0; margin-top:4px; font-weight:600; }
.desc-more-btn:hover { text-decoration:underline; }

/* Feature list balance */
.feature-list-cols { display:grid; grid-template-columns:1fr 1fr; gap:4px 12px; }
@media(max-width:500px){ .feature-list-cols { grid-template-columns:1fr; } }

/* Info section vertical balance */
.info-side { display:flex; flex-direction:column; gap:0; }

/* Wishlist header pop animation */
@keyframes wishPop {
  0%   { transform:scale(1); }
  40%  { transform:scale(1.35); }
  70%  { transform:scale(.9); }
  100% { transform:scale(1); }
}
.wish-pop { animation: wishPop .45s cubic-bezier(.36,.07,.19,.97); }
</style>

<div class="container">
  <!-- Back -->
  <div class="reveal" style="margin-top:5px;margin-bottom:14px">
    <a href="<?= SITE_URL ?>/"
       style="display:inline-flex;align-items:center;gap:6px;color:var(--muted2);font-size:14px;text-decoration:none;border:1px solid var(--border);padding:8px 14px;border-radius:var(--radius-sm);background:var(--bg-card);transition:.2s"
       onmouseover="this.style.color='var(--text)';this.style.borderColor='var(--primary)'"
       onmouseout="this.style.color='var(--muted2)';this.style.borderColor='var(--border)'">
      ← Back
    </a>
  </div>

  <div class="detail-grid">

    <!-- ====== LEFT: Gallery ====== -->
    <div style="animation:fadeInUp .4s ease">
      <div class="gallery-wrap">
        <div class="detail-img" id="mainImgWrap">
          <?php if (!empty($allImages)): ?>
            <img id="mainProductImg" src="<?= SITE_URL ?>/uploads/products/<?= clean($allImages[0]) ?>" alt="<?= clean($p['title']) ?>">
          <?php else: ?>
            <div style="height:320px;display:flex;align-items:center;justify-content:center;font-size:80px">💻</div>
          <?php endif; ?>
        </div>

        <!-- Floating wishlist heart on image corner -->
        <?php if (isLoggedIn()): ?>
        <button id="wishFloatBtn" class="wish-float-btn <?= $inWishlist ? 'active' : '' ?>" onclick="toggleWishlist(<?= $p['id'] ?>)" title="<?= $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>">
          <?= $inWishlist ? '❤️' : '🤍' ?>
        </button>
        <?php endif; ?>
      </div>

      <!-- Thumbs -->
      <?php if (count($allImages) > 1): ?>
      <div class="gallery-thumbs">
        <?php foreach ($allImages as $i => $img): ?>
        <div class="gallery-thumb<?= $i === 0 ? ' active' : '' ?>" data-img="<?= SITE_URL ?>/uploads/products/<?= clean($img) ?>">
          <img src="<?= SITE_URL ?>/uploads/products/<?= clean($img) ?>" alt="photo <?= $i+1 ?>">
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Live Demo button below gallery -->
      <?php if ($p['demo_url']): ?>
      <div class="demo-under-gallery">
        <a href="<?= clean($p['demo_url']) ?>" target="_blank" class="btn btn-outline btn-block" style="margin-top:0">
          🔗 Live Demo
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Full-view image lightbox -->
    <div class="img-lightbox" id="imgLightbox" onclick="closeLightbox(event)">
      <button class="lightbox-close" onclick="closeLightbox(event)">✕</button>
      <img id="lightboxImg" src="" alt="" onclick="event.stopPropagation()">
    </div>

    <!-- ====== RIGHT: Info ====== -->
    <div class="info-side" style="animation:fadeInUp .4s .1s ease both">

      <h1 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:800;margin-bottom:12px;line-height:1.3"><?= clean($p['title']) ?></h1>

      <!-- Description with collapse -->
      <?php if ($p['description']): ?>
      <div style="margin-bottom:16px">
        <div id="descText" class="desc-text <?= $isLongDesc ? 'desc-collapsed' : '' ?>">
          <?= nl2br(clean($p['description'])) ?>
        </div>
        <?php if ($isLongDesc): ?>
        <button class="desc-more-btn" id="descMoreBtn" onclick="toggleDesc()">More ▼</button>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Features — two columns if many -->
      <?php if (!empty($feats)): ?>
      <ul class="feature-list <?= count($feats) > 4 ? 'feature-list-cols' : '' ?>" style="margin-bottom:16px;padding:0;list-style:none">
        <?php foreach ($feats as $f): ?>
        <li style="display:flex;align-items:flex-start;gap:8px;font-size:14px;padding:3px 0">
          <span style="color:#10b981;font-weight:700;flex-shrink:0">✓</span>
          <span style="color:var(--muted2)"><?= clean(trim($f)) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

     <!-- Category + Countdown -->
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:5px;margin:1px 0;background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:10px 14px">
        <?php if ($p['cat_name']): ?>
          <span class="badge badge-active" style="display:inline-block;width:fit-content"><?= clean($p['cat_name']) ?></span>
        <?php endif; ?>
        <div class="countdown" style="margin:0">
          ⏱ Offer expires in: <strong class="countdown-timer" data-end="<?= getOfferEndTimestamp($p) * 1000 ?>" style="font-size:15px;color:var(--warning)">00:00:00</strong>
        </div>
      </div>

      <!-- Price -->
      <div class="price-row" style="margin-bottom:16px">
        <?php if ($p['discount_price']): ?>
          <span class="price-old" style="font-size:16px">₹<?= number_format($p['price']) ?></span>
        <?php endif; ?>
        <span class="price-new" style="font-size:32px">₹<?= number_format($price) ?></span>
        <?php if ($save): ?><span class="price-save" style="font-size:13px"><?= $save ?>% OFF</span><?php endif; ?>
      </div>

      <!-- Instant badge -->
      <div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:18px;font-size:13px;color:#34d399">
        ⚡ <strong>Instant Automatic Delivery</strong> — Download link & license key sent to your email immediately after payment.
      </div>

      <!-- CTA -->
<?php if (isLoggedIn()): ?>
  <?php if (($_SESSION['user_role'] ?? 'buyer') === 'seller'): ?>
    <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:var(--radius-sm);padding:12px 16px;font-size:13px;color:#f87171;text-align:center">
      🏪 Seller account se purchase nahi kar sakte.<br>
      <a href="<?= SITE_URL ?>/dashboard.php" style="color:#f87171;font-weight:600">Seller Dashboard →</a>
    </div>
  <?php else: ?>
    <a href="<?= SITE_URL ?>/checkout.php?product_id=<?= $p['id'] ?>" class="btn btn-primary btn-block" style="padding:16px;font-size:16px">
      🛒 Buy Now — ₹<?= number_format($price) ?>
    </a>
  <?php endif; ?>
<?php else: ?>
        <a href="<?= SITE_URL ?>/login.php?next=<?= urlencode('product.php?slug='.$p['slug']) ?>" class="btn btn-primary btn-block" style="padding:16px;font-size:16px">
          Login to Buy — ₹<?= number_format($price) ?>
        </a>
        <p style="text-align:center;margin-top:10px;font-size:13px;color:var(--muted)">New user? <a href="<?= SITE_URL ?>/register.php">Register free</a></p>
      <?php endif; ?>

    </div><!-- end info-side -->
  </div><!-- end detail-grid -->
</div>

<!-- REVIEWS SECTION -->
<div class="container" style="margin-top:48px;margin-bottom:60px">
  <?php if (!empty($reviews) || $canReview): ?>
  <div style="border-top:1px solid var(--border);padding-top:36px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px">
      <div>
        <h2 style="font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;margin-bottom:4px">Customer Reviews</h2>
        <?php if (!empty($reviews)): ?>
        <div style="display:flex;align-items:center;gap:10px">
          <span style="color:#fbbf24;font-size:20px"><?= str_repeat('★', round($avgRating)) ?><?= str_repeat('☆', 5-round($avgRating)) ?></span>
          <strong><?= number_format($avgRating, 1) ?></strong>
          <span style="color:var(--muted);font-size:14px">(<?= count($reviews) ?> review<?= count($reviews)!==1?'s':'' ?>)</span>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($canReview): ?>
      <button onclick="document.getElementById('reviewModal').style.display='flex'" class="btn btn-outline btn-sm">✍️ Write a Review</button>
      <?php elseif ($userReviewed): ?>
      <span style="color:var(--muted);font-size:13px">✅ You reviewed this product</span>
      <?php endif; ?>
    </div>
    <?php if (!empty($reviews)): ?>
    <div style="display:flex;flex-direction:column;gap:14px">
      <?php foreach ($reviews as $rv): ?>
      <div class="reveal" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:20px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:10px">
          <div>
            <strong style="font-size:15px"><?= clean($rv['uname']) ?></strong>
            <div style="color:#fbbf24;font-size:14px;margin-top:2px"><?= str_repeat('★', $rv['rating']) ?><?= str_repeat('☆', 5-$rv['rating']) ?></div>
          </div>
          <span style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($rv['created_at'])) ?></span>
        </div>
        <?php if ($rv['title']): ?><strong style="font-size:14px"><?= clean($rv['title']) ?></strong><br><?php endif; ?>
        <p style="color:var(--muted2);margin-top:6px;line-height:1.6;font-size:14px"><?= nl2br(clean($rv['body'])) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color:var(--muted);font-size:14px">No reviews yet. <?= $canReview ? 'Be the first to review!' : '' ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Review Modal -->
<?php if ($canReview): ?>
<div id="reviewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:28px;width:100%;max-width:500px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Write a Review</h3>
      <button onclick="document.getElementById('reviewModal').style.display='none'" style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer">✕</button>
    </div>
    <form method="POST" action="<?= SITE_URL ?>/api/submit_review.php">
      <?= csrf_field() ?>
      <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
      <input type="hidden" name="order_id" value="<?= $userOrderId ?>">
      <div class="form-group">
        <label>Rating *</label>
        <div style="display:flex;gap:6px;margin-top:6px">
          <?php for ($i=5;$i>=1;$i--): ?>
          <label style="cursor:pointer;font-size:24px;line-height:1">
            <input type="radio" name="rating" value="<?= $i ?>" <?= $i===5?'checked':'' ?> style="display:none" onchange="updateStars(<?= $i ?>)">
            <span class="star-label" data-val="<?= $i ?>">⭐</span>
          </label>
          <?php endfor; ?>
        </div>
      </div>
      <div class="form-group">
        <label>Title (optional)</label>
        <input class="form-control" type="text" name="title" placeholder="Great product!" maxlength="200">
      </div>
      <div class="form-group">
        <label>Your Review *</label>
        <textarea class="form-control" name="body" rows="4" placeholder="Share your experience..." required style="resize:vertical"></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Submit Review</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Related Products -->
<?php
$related = $pdo->prepare("SELECT p.*, c.name cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='active' AND p.id != ? AND (p.category_id=? OR 1=1) ORDER BY RAND() LIMIT 3");
$related->execute([$p['id'], $p['category_id']]);
$related = $related->fetchAll();
if (!empty($related)): ?>
<div class="container" style="margin-bottom:60px">
  <h2 style="font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;margin-bottom:20px;text-align:center">You May Also Like</h2>
  <div class="product-grid">
    <?php foreach ($related as $rp): $rprice = $rp['discount_price'] ?: $rp['price']; ?>
    <div class="product-card reveal">
      <div class="card-img-wrap">
        <?php if ($rp['image']): ?>
          <img src="<?= SITE_URL ?>/uploads/products/<?= clean($rp['image']) ?>" alt="<?= clean($rp['title']) ?>" loading="lazy">
        <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--bg2),var(--bg-card2));font-size:48px">💻</div>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <h3><?= clean($rp['title']) ?></h3>
        <div class="price-row" style="margin-top:auto">
          <?php if ($rp['discount_price']): ?><span class="price-old">₹<?= number_format($rp['price']) ?></span><?php endif; ?>
          <span class="price-new">₹<?= number_format($rprice) ?></span>
        </div>
        <a href="<?= SITE_URL ?>/product.php?slug=<?= urlencode($rp['slug']) ?>" class="btn btn-outline btn-block" style="margin-top:10px">View Product</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
/* Gallery */
document.querySelectorAll('.gallery-thumb').forEach(function(t){
  t.addEventListener('click', function(){
    document.getElementById('mainProductImg').src = this.dataset.img;
    document.querySelectorAll('.gallery-thumb').forEach(function(x){ x.classList.remove('active'); });
    this.classList.add('active');
  });
});

/* Full-view lightbox: click main image to open it at full size */
var mainImgEl = document.getElementById('mainProductImg');
if (mainImgEl) {
  mainImgEl.addEventListener('click', function(){
    document.getElementById('lightboxImg').src = this.src;
    document.getElementById('imgLightbox').classList.add('active');
  });
}
function closeLightbox(e) {
  if (e) e.stopPropagation();
  document.getElementById('imgLightbox').classList.remove('active');
}
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') closeLightbox();
});

/* Wishlist toggle (heart on image + header nav pop) */
function toggleWishlist(productId) {
  var btn  = document.getElementById('wishFloatBtn');
  var navLink = document.querySelector('.main-nav a[href*="wishlist"]');
  var fd = new FormData();
  fd.append('action', 'toggle');
  fd.append('product_id', productId);
  fetch('<?= SITE_URL ?>/api/wishlist.php', { method:'POST', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) {
        var isIn = d.in_wishlist;
        btn.textContent = isIn ? '❤️' : '🤍';
        btn.title = isIn ? 'Remove from Wishlist' : 'Add to Wishlist';
        btn.classList.toggle('active', isIn);
        /* Pop the header Wishlist link once */
        if (isIn && navLink) {
          navLink.classList.remove('wish-pop');
          void navLink.offsetWidth; // reflow to restart animation
          navLink.classList.add('wish-pop');
          setTimeout(function(){ navLink.classList.remove('wish-pop'); }, 500);
        }
      } else if (d.msg) { alert(d.msg); }
    });
}

/* Description collapse/expand with 30-sec auto-reset */
var descExpanded = false;
var descTimer = null;
function toggleDesc() {
  var el  = document.getElementById('descText');
  var btn = document.getElementById('descMoreBtn');
  if (!descExpanded) {
    el.classList.remove('desc-collapsed');
    btn.textContent = 'Less ▲';
    descExpanded = true;
    /* Auto-collapse after 30s */
    clearTimeout(descTimer);
    descTimer = setTimeout(function(){
      el.classList.add('desc-collapsed');
      btn.textContent = 'More ▼';
      descExpanded = false;
    }, 30000);
  } else {
    clearTimeout(descTimer);
    el.classList.add('desc-collapsed');
    btn.textContent = 'More ▼';
    descExpanded = false;
  }
}

function updateStars(val) {
  document.querySelectorAll('.star-label').forEach(function(s){
    s.textContent = parseInt(s.dataset.val) <= val ? '⭐' : '☆';
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>