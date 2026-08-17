<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
if (isLoggedIn()) redirect(SITE_URL.'/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL.'/register.php');
    $name    = clean($_POST['name'] ?? '');
    $email   = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone   = clean($_POST['phone'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $role    = ($_POST['role'] ?? 'buyer') === 'seller' ? 'seller' : 'buyer';
    $next    = clean($_POST['next'] ?? '');

    $refCode = clean($_POST['ref'] ?? $_SESSION['referral_code'] ?? '');
    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
        flash('error','Fill all fields correctly. Password min 6 chars.');
    } elseif (!$phone) {
        flash('error','WhatsApp number is required.');
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email=?"); $check->execute([$email]);
        if ($check->fetch()) {
            flash('error','Email already registered. Login instead.');
        } else {
            // Resolve referrer
            $referrerId = null;
            if ($refCode) {
                $refRow = $pdo->prepare("SELECT id FROM users WHERE referral_code=? LIMIT 1");
                $refRow->execute([$refCode]);
                $refUser = $refRow->fetch();
                if ($refUser) $referrerId = (int)$refUser['id'];
            }
            $pdo->prepare("INSERT INTO users (name,email,phone,password,role,referred_by) VALUES (?,?,?,?,?,?)")
                ->execute([$name, $email, $phone, $pass, $role, $referrerId]);
            $newUid = (int)$pdo->lastInsertId();
            ensureReferralCode($pdo, $newUid);
            $_SESSION['user_id']   = $newUid;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;
            unset($_SESSION['referral_code']);
            flash('success', 'Welcome, '.$name.'! Account created.');
            // Seller → seller dashboard, Buyer → normal dashboard
            if ($role === 'seller') {
                redirect(SITE_URL.'/dashboard.php');
            } else {
                redirect($next ? SITE_URL.'/'.$next : SITE_URL.'/dashboard.php');
            }
        }
    }
    redirect(SITE_URL.'/register.php'.($next ? '?next='.urlencode($next) : ''));
}
$pageTitle = 'Register';
require_once __DIR__ . '/includes/header.php';
$next = clean($_GET['next'] ?? '');
// Pre-select role from URL param ?role=seller
$preRole = ($_GET['role'] ?? '') === 'seller' ? 'seller' : 'buyer';
?>
<div class="page-card" style="animation:fadeInUp .4s ease">
  <h2>Create Account</h2>
  <p class="subtitle">Join <?= SITE_NAME ?></p>

  <!-- Role Toggle -->
  <div style="display:flex;gap:10px;margin-bottom:22px;background:var(--surface2,#1a1a2e);border-radius:10px;padding:5px">
    <button type="button" id="roleBtn_buyer" onclick="setRole('buyer')"
      style="flex:1;padding:10px;border-radius:8px;border:none;cursor:pointer;font-size:14px;font-weight:600;transition:.2s">
      🛒 Buy Products
    </button>
    <button type="button" id="roleBtn_seller" onclick="setRole('seller')"
      style="flex:1;padding:10px;border-radius:8px;border:none;cursor:pointer;font-size:14px;font-weight:600;transition:.2s">
      🏪 Become a Seller
    </button>
  </div>
  <div id="roleHint" style="text-align:center;font-size:12px;color:var(--muted);margin-bottom:18px;margin-top:-10px"></div>

  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= $next ?>">
    <input type="hidden" name="role" id="roleInput" value="<?= $preRole ?>">

    <div class="form-group">
      <label>Full Name</label>
      <input class="form-control" type="text" name="name" placeholder="Your name" required>
    </div>
    <div class="form-group">
      <label>Email Address</label>
      <input class="form-control" type="email" name="email" placeholder="your@email.com" required>
      <p class="form-hint">Download link will be sent here</p>
    </div>
    <div class="form-group">
      <label>WhatsApp Number *</label>
      <input class="form-control" type="text" name="phone" placeholder="91XXXXXXXXXX" required>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input class="form-control" type="password" name="password" placeholder="Min 6 characters" required>
    </div>

    <?php
    $refParam = clean($_GET['ref'] ?? '');
    if ($refParam) { $_SESSION['referral_code'] = $refParam; }
    $refParam = $refParam ?: ($_SESSION['referral_code'] ?? '');
    ?>
    <?php if ($refParam): ?>
    <input type="hidden" name="ref" value="<?= clean($refParam) ?>">
    <div style="background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);border-radius:8px;padding:10px 14px;font-size:13px;color:#34d399;margin-bottom:14px">
      🎉 You were referred by a friend! Sign up to get started.
    </div>
    <?php endif; ?>

    <button type="submit" id="submitBtn" class="btn btn-primary btn-block" style="padding:13px">Create Account</button>
  </form>

  <?php $googleClientId = getSetting($pdo, 'google_client_id', ''); if ($googleClientId): ?>
  <div style="margin-top:18px;text-align:center">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
      <div style="flex:1;height:1px;background:var(--border)"></div>
      <span style="color:var(--muted);font-size:12px">or sign up with</span>
      <div style="flex:1;height:1px;background:var(--border)"></div>
    </div>
    <a href="<?= SITE_URL ?>/auth/google_callback.php" class="btn btn-outline btn-block"
       style="display:flex;align-items:center;justify-content:center;gap:10px;padding:11px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      Sign up with Google
    </a>
  </div>
  <?php endif; ?>
  <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--muted)">Already have an account? <a href="<?= SITE_URL ?>/login.php">Login</a></p>
</div>

<script>
const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--primary') || '#6c63ff';

function setRole(role) {
  document.getElementById('roleInput').value = role;
  const buyerBtn  = document.getElementById('roleBtn_buyer');
  const sellerBtn = document.getElementById('roleBtn_seller');
  const hint      = document.getElementById('roleHint');
  const submitBtn = document.getElementById('submitBtn');

  if (role === 'seller') {
    sellerBtn.style.background = 'var(--primary, #6c63ff)';
    sellerBtn.style.color      = '#fff';
    buyerBtn.style.background  = 'transparent';
    buyerBtn.style.color       = 'var(--muted)';
    hint.textContent           = '📦 You can list & sell your own digital products after registration.';
    submitBtn.textContent      = '🏪 Create Seller Account';
  } else {
    buyerBtn.style.background  = 'var(--primary, #6c63ff)';
    buyerBtn.style.color       = '#fff';
    sellerBtn.style.background = 'transparent';
    sellerBtn.style.color      = 'var(--muted)';
    hint.textContent           = '🛒 Browse and purchase premium digital products instantly.';
    submitBtn.textContent      = '🛒 Create Buyer Account';
  }
}

// Init with preselected role
setRole('<?= $preRole ?>');
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>