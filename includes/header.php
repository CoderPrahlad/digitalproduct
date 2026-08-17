<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';

if (getSetting($pdo, 'maintenance_mode', '0') === '1' && !isAdmin()) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
    <title>Under Maintenance | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    </head>
    <body>
    <canvas id="particles-canvas"></canvas>
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;text-align:center">
      <div style="max-width:460px">
        <div style="font-size:56px;margin-bottom:20px">🛠️</div>
        <h1 style="font-family:'Space Grotesk',sans-serif;font-size:28px;margin-bottom:14px">We'll be right back</h1>
        <p style="color:var(--muted2);font-size:15px;line-height:1.7">
          <?= clean(SITE_NAME) ?> is currently undergoing scheduled maintenance.<br>
          Please check back again shortly.
        </p>
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? clean($pageTitle).' | '.SITE_NAME : SITE_NAME.' — '.SITE_TAGLINE ?></title>
<meta name="description" content="<?= isset($pageDesc) ? clean($pageDesc) : clean(SITE_TAGLINE) ?>">
<meta name="keywords" content="<?= isset($pageKeywords) ? clean($pageKeywords) : 'source code, php projects, digital products, buy code' ?>">
<meta name="robots" content="index, follow">
<meta name="author" content="<?= clean(SITE_NAME) ?>">

<!-- Open Graph (WhatsApp, Telegram, Facebook preview) -->
<meta property="og:type"        content="<?= isset($ogType) ? $ogType : 'website' ?>">
<meta property="og:title"       content="<?= isset($pageTitle) ? clean($pageTitle).' | '.SITE_NAME : SITE_NAME.' — '.SITE_TAGLINE ?>">
<meta property="og:description" content="<?= isset($pageDesc) ? clean($pageDesc) : clean(SITE_TAGLINE) ?>">
<meta property="og:url"         content="<?= SITE_URL . $_SERVER['REQUEST_URI'] ?>">
<meta property="og:image"       content="<?= isset($ogImage) ? $ogImage : SITE_URL.'/assets/img/og-default.png' ?>">
<meta property="og:site_name"   content="<?= clean(SITE_NAME) ?>">

<!-- Twitter Card -->
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= isset($pageTitle) ? clean($pageTitle).' | '.SITE_NAME : SITE_NAME ?>">
<meta name="twitter:description" content="<?= isset($pageDesc) ? clean($pageDesc) : clean(SITE_TAGLINE) ?>">
<meta name="twitter:image"       content="<?= isset($ogImage) ? $ogImage : SITE_URL.'/assets/img/og-default.png' ?>">

<!-- Canonical URL -->
<link rel="canonical" href="<?= SITE_URL . strtok($_SERVER['REQUEST_URI'], '?') ?>">

<?php if (defined('GA4_ID') && GA4_ID): ?>
<!-- Google Analytics GA4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= GA4_ID ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= GA4_ID ?>');
</script>
<?php endif; ?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<script>
(function(){
  try {
    var t = localStorage.getItem('theme') || 'dark';
    if (t === 'light') document.documentElement.setAttribute('data-theme','light');
  } catch(e) {}
})();
</script>
</head>
<body>
<canvas id="circuit-canvas"></canvas>
<div class="video-overlay"></div>
<div class="page-loader" id="page-loader"><div class="spinner"></div></div>

<header class="site-header">
  <div class="header-inner">
    <a href="<?= SITE_URL ?>" class="logo">
      <?php if (defined('LOGO_URL') && LOGO_URL): ?>
        <img src="<?= SITE_URL . LOGO_URL ?>" alt="<?= clean(SITE_NAME) ?>" class="logo-img">
      <?php else: ?>
        ⚡ <?= SITE_NAME ?>
      <?php endif; ?>
    </a>
    <nav class="main-nav" id="mainNav">
      <div class="mobile-nav-head">
        <span class="mobile-nav-title">⚡ <?= SITE_NAME ?></span>
        <button class="mobile-nav-close" id="mobileNavClose" aria-label="Close menu">✕</button>
      </div>
     <?php if (isLoggedIn()): ?>
  <?php $__role = $_SESSION['user_role'] ?? 'buyer'; ?>
  <?php if ($__role === 'seller'): ?>
    <a href="<?= SITE_URL ?>/dashboard.php?tab=overview"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="<?= SITE_URL ?>/logout.php"><span class="nav-icon">🚪</span>Logout</a>
  <?php else: ?>
    <div class="nav-dropdown">
      <div class="nav-dropdown-menu">
        
        <div class="nav-dropdown-divider"></div>
                <a href="<?= SITE_URL ?>/dashboard.php?tab=orders"><span class="nav-icon">📦</span>My Orders</a>

        <a href="<?= SITE_URL ?>/logout.php"><span class="nav-icon">🚪</span>Logout</a>
      </div>
    </div>
  <?php endif; ?>
<?php else: ?>
  <a href="<?= SITE_URL ?>"><span class="nav-icon">🏠</span>Home</a>
  <a href="<?= SITE_URL ?>/#products"><span class="nav-icon">🛍️</span>Products</a>
  <a href="<?= SITE_URL ?>/contact.php"><span class="nav-icon">✉️</span>Contact</a>
  <a href="<?= SITE_URL ?>/login.php" class="nav-cta nav-cta-login btn"><span class="nav-icon">🔑</span>Login</a>
<?php endif; ?>
      <div class="mobile-nav-divider"></div>
      <a class="mobile-nav-support" href="https://wa.me/<?= WA_NUMBER ?>?text=Hello+<?= urlencode(SITE_NAME) ?>" target="_blank"><span class="nav-icon">💬</span>WhatsApp Support</a>
    </nav>
    <div class="mobile-nav-backdrop" id="mobileNavBackdrop"></div>
    <div class="header-actions">
      <form class="search-box" id="searchBox" action="<?= SITE_URL ?>/search.php" method="GET">
        <input type="text" name="q" id="searchInput" placeholder="Search products..." value="<?= isset($_GET['q']) ? clean($_GET['q']) : '' ?>">
      </form>
      <button type="button" class="search-toggle" id="searchToggle" aria-label="Search" title="Search products">
        <svg id="searchToggleIcon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="7"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark / light theme" title="Toggle dark / light theme">
        <span class="theme-toggle-track">
          <span class="theme-toggle-knob">
            <svg class="theme-icon-dark" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
              <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
            </svg>
            <svg class="theme-icon-light" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="4.2"></circle>
              <line x1="12" y1="1.5" x2="12" y2="4"></line>
              <line x1="12" y1="20" x2="12" y2="22.5"></line>
              <line x1="4.2" y1="4.2" x2="6" y2="6"></line>
              <line x1="18" y1="18" x2="19.8" y2="19.8"></line>
              <line x1="1.5" y1="12" x2="4" y2="12"></line>
              <line x1="20" y1="12" x2="22.5" y2="12"></line>
              <line x1="4.2" y1="19.8" x2="6" y2="18"></line>
              <line x1="18" y1="6" x2="19.8" y2="4.2"></line>
            </svg>
          </span>
        </span>
      </button>
      <button class="hamburger" aria-label="Menu">☰</button>
    </div>
  </div>
</header>

<script>
(function(){
  var box = document.getElementById('searchBox');
  var toggle = document.getElementById('searchToggle');
  var icon = document.getElementById('searchToggleIcon');
  var input = document.getElementById('searchInput');
  if (!box || !toggle) return;

  function setIconToClose() {
    icon.innerHTML = '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>';
  }
  function setIconToSearch() {
    icon.innerHTML = '<circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>';
  }

  function openBox() {
    box.classList.add('active');
    toggle.classList.add('active');
    setIconToClose();
    setTimeout(function(){ input.focus(); }, 200);
  }
  function closeBox() {
    box.classList.remove('active');
    toggle.classList.remove('active');
    setIconToSearch();
  }

  toggle.addEventListener('click', function(){
    box.classList.contains('active') ? closeBox() : openBox();
  });
  document.addEventListener('click', function(e){
    if (box.classList.contains('active') && !box.contains(e.target) && e.target !== toggle && !toggle.contains(e.target) && !input.value) {
      closeBox();
    }
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && box.classList.contains('active')) closeBox();
  });
  <?php if (isset($_GET['q']) && $_GET['q'] !== ''): ?>
  openBox();
  <?php endif; ?>
})();
</script>

<!-- Floating action buttons -->
<div class="floating-actions">
  <?php if (defined('YT_CHANNEL_URL') && YT_CHANNEL_URL): ?>
  <a class="fab fab-yt" href="<?= YT_CHANNEL_URL ?>?sub_confirmation=1" target="_blank" title="Subscribe on YouTube" rel="noopener noreferrer">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
  </a>
  <?php endif; ?>
  <a class="fab fab-tg" href="<?= TG_CHANNEL_URL ?>" target="_blank" title="Telegram Channel">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M22.05 2.71c-.34-.28-.87-.32-1.5-.1-.71.25-19.03 7.72-19.75 8.02-.42.16-1.29.53-1.21 1.24.07.65.85.93 1.34 1.1l4.9 1.6 1.86 5.99c.14.51.53 1.31 1.29 1.31.5 0 .84-.31 1.24-.7l2.76-2.74 4.86 3.62c.32.25.62.38.9.38.6 0 .88-.4 1.02-.75.15-.38 3.66-16.34 3.66-16.34.18-.83.29-1.44-.37-1.63z"/></svg>
  </a>
  <a class="fab fab-wa" href="https://wa.me/<?= WA_NUMBER ?>?text=Hello+<?= urlencode(SITE_NAME) ?>" target="_blank" title="WhatsApp">
    <svg width="21" height="21" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
  </a>
  <?php if (!empty(activeCryptoGateways($pdo))): ?>
  <a href="#" class="fab fab-usdt" title="Show prices in USDT">
    <span class="fab-usdt-label">USDT</span>
  </a>
  <?php endif; ?>
</div>

<?php
// Flash messages as hidden toasts
$fs = flash('success'); $fe = flash('error'); $fi = flash('info');
if ($fs): ?><div class="flash-toast" data-msg="<?= clean($fs) ?>" data-type="success"></div><?php endif;
if ($fe): ?><div class="flash-toast" data-msg="<?= clean($fe) ?>" data-type="error"></div><?php endif;
if ($fi): ?><div class="flash-toast" data-msg="<?= clean($fi) ?>" data-type="info"></div><?php endif;
?>
<main>