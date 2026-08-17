<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$q        = trim($_GET['q'] ?? '');
$catId    = (int)($_GET['cat'] ?? 0);
$priceKey = $_GET['price'] ?? '';
$sort     = $_GET['sort'] ?? 'relevance';

$priceRanges = [
    'under2k'  => ['label' => 'Under ₹2,000',      'min' => 0,     'max' => 2000],
    '2kto5k'   => ['label' => '₹2,000 – ₹5,000',   'min' => 2000,  'max' => 5000],
    '5kto10k'  => ['label' => '₹5,000 – ₹10,000',  'min' => 5000,  'max' => 10000],
    'above10k' => ['label' => 'Above ₹10,000',     'min' => 10000, 'max' => null],
];

$categories = $pdo->query("
    SELECT c.id, c.name, COUNT(p.id) AS cnt
    FROM categories c
    JOIN products p ON p.category_id = c.id AND p.status = 'active'
    GROUP BY c.id
    HAVING cnt > 0
    ORDER BY c.name
")->fetchAll();

$where  = ["p.status = 'active'"];
$params = [];

if ($q !== '') {
    $where[] = "(p.title LIKE ? OR p.short_desc LIKE ? OR p.description LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

if ($priceKey && isset($priceRanges[$priceKey])) {
    $pr = $priceRanges[$priceKey];
    $where[] = "COALESCE(p.discount_price, p.price) >= ?";
    $params[] = $pr['min'];
    if ($pr['max'] !== null) {
        $where[] = "COALESCE(p.discount_price, p.price) <= ?";
        $params[] = $pr['max'];
    }
}

if ($catId) {
    $where[] = "p.category_id = ?";
    $params[] = $catId;
}

$whereSql = implode(' AND ', $where);

$orderSql = "p.sort_order ASC, p.created_at DESC";
if ($sort === 'price_low')  $orderSql = "COALESCE(p.discount_price, p.price) ASC";
if ($sort === 'price_high') $orderSql = "COALESCE(p.discount_price, p.price) DESC";
if ($sort === 'name_az')    $orderSql = "p.title ASC";
if ($sort === 'newest')     $orderSql = "p.created_at DESC";

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS cat_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE $whereSql
    ORDER BY $orderSql
");
$stmt->execute($params);
$products = $stmt->fetchAll();

$recommended = [];
if (empty($products)) {
    $recStmt = $pdo->prepare("
        SELECT p.*, c.name AS cat_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $recStmt->execute();
    $recommended = $recStmt->fetchAll();
}

function filterUrl($overrides = []) {
    $qs = array_merge(['q' => $_GET['q'] ?? '', 'cat' => $_GET['cat'] ?? '', 'price' => $_GET['price'] ?? '', 'sort' => $_GET['sort'] ?? ''], $overrides);
    $qs = array_filter($qs, function($v) { return $v !== '' && $v !== 0 && $v !== '0'; });
    return SITE_URL . '/search.php' . (!empty($qs) ? '?' . http_build_query($qs) : '');
}

function renderProductCard($p, $i) {
    $price = $p['discount_price'] ?: $p['price'];
    $save  = $p['discount_price'] ? round((($p['price'] - $p['discount_price']) / $p['price']) * 100) : 0;
    $feats = array_filter(explode("\n", $p['features'] ?? ''));
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
        <?php if ($p['cat_name']): ?>
          <p style="color:var(--primary);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px"><?= clean($p['cat_name']) ?></p>
        <?php endif; ?>
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
    <?php
}

$pageTitle = ($q !== '' ? 'Search: ' . $q : 'Browse Products') . ' — ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<section class="section" style="padding-top:40px;">
  <div class="container">

    <div class="section-header reveal">
      <h2><?= $q !== '' ? 'Search results for "' . clean($q) . '"' : 'Browse Products' ?></h2>
    </div>

    <!-- Mobile Filter Toggle -->
    <button class="mobile-filter-btn" id="mobileFilterBtn">🔽 Filters</button>

    <div class="search-layout">

      <!-- SIDEBAR -->
      <aside class="search-sidebar" id="searchSidebar">

        <?php if ($q !== '' || $catId || $priceKey): ?>
          <a href="<?= filterUrl(['q' => $q, 'cat' => '', 'price' => '', 'sort' => 'relevance']) ?>" class="filter-clear">✕ Clear filters</a>
        <?php endif; ?>

        <div class="filter-group">
          <h4>🗂️ Category</h4>
          <ul class="filter-list">
            <li>
              <a href="<?= filterUrl(['cat' => '']) ?>" class="filter-item <?= !$catId ? 'active' : '' ?>">All Categories</a>
            </li>
            <?php foreach ($categories as $c): ?>
            <li>
              <a href="<?= filterUrl(['cat' => $c['id']]) ?>" class="filter-item <?= $catId === (int)$c['id'] ? 'active' : '' ?>">
                <?= clean($c['name']) ?> <span class="filter-count"><?= $c['cnt'] ?></span>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="filter-group">
          <h4>💰 Price</h4>
          <ul class="filter-list">
            <li><a href="<?= filterUrl(['price' => '']) ?>" class="filter-item <?= !$priceKey ? 'active' : '' ?>">Any Price</a></li>
            <?php foreach ($priceRanges as $key => $pr): ?>
            <li>
              <a href="<?= filterUrl(['price' => $key]) ?>" class="filter-item <?= $priceKey === $key ? 'active' : '' ?>"><?= $pr['label'] ?></a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

      </aside>

      <!-- RESULTS -->
      <div class="search-results">

        <div class="search-toolbar reveal">
          <span class="search-toolbar-count"></span>
          <form method="GET" action="<?= SITE_URL ?>/search.php" class="sort-form">
            <input type="hidden" name="q" value="<?= clean($q) ?>">
            <input type="hidden" name="cat" value="<?= $catId ?: '' ?>">
            <input type="hidden" name="price" value="<?= clean($priceKey) ?>">
            <label for="sortSelect">Sort by</label>
            <select name="sort" id="sortSelect" class="sort-select" onchange="this.form.submit()">
              <option value="relevance"  <?= $sort==='relevance'  ? 'selected' : '' ?>>Relevance</option>
              <option value="newest"     <?= $sort==='newest'     ? 'selected' : '' ?>>Newest</option>
              <option value="price_low"  <?= $sort==='price_low'  ? 'selected' : '' ?>>Price: Low to High</option>
              <option value="price_high" <?= $sort==='price_high' ? 'selected' : '' ?>>Price: High to Low</option>
              <option value="name_az"    <?= $sort==='name_az'    ? 'selected' : '' ?>>Name: A-Z</option>
            </select>
          </form>
        </div>

        <?php if (empty($products)): ?>
          <div class="no-results reveal">
            <span style="font-size:34px">🔍</span>
            <?php if ($q !== ''): ?>
              <p>No products found for <strong>"<?= clean($q) ?>"</strong> — try a different keyword.</p>
            <?php else: ?>
              <p>No products match the selected filters.</p>
            <?php endif; ?>
          </div>
          <?php if (!empty($recommended)): ?>
            <div class="section-header reveal" style="margin-top:8px">
              <h3 style="font-size:19px">You might like these instead</h3>
            </div>
            <div class="product-grid">
              <?php foreach ($recommended as $i => $p): renderProductCard($p, $i); endforeach; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="product-grid">
            <?php foreach ($products as $i => $p): renderProductCard($p, $i); endforeach; ?>
          </div>
        <?php endif; ?>

      </div><!-- /.search-results -->
    </div><!-- /.search-layout -->
  </div><!-- /.container -->
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>