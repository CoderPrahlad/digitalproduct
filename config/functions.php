<?php
function getSetting($pdo, $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($pdo->query("SELECT key_name, key_value FROM settings")->fetchAll() as $row) {
            $cache[$row['key_name']] = $row['key_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function setSetting($pdo, $key, $value) {
    $pdo->prepare("INSERT INTO settings (key_name,key_value) VALUES (?,?) ON DUPLICATE KEY UPDATE key_value=?")
        ->execute([$key, $value, $value]);
}

/**
 * cfg() — get an admin-editable value.
 * Looks in the `settings` table first (edited from Admin -> Settings),
 * falls back to the constant defined in config.php if not set / empty.
 */
function cfg($pdo, $key, $constName = null) {
    $v = getSetting($pdo, $key, null);
    if ($v !== null && $v !== '') return $v;
    if ($constName && defined($constName)) return constant($constName);
    return '';
}

/**
 * Returns the currently enabled AUTOMATIC (Razorpay-type) payment gateway row,
 * or null if none is configured/enabled. Falls back to the constants in
 * config.php (as a virtual gateway) when the payment_gateways table is empty,
 * so the store keeps working right after the migration is applied.
 */
function activeAutoGateway($pdo) {
    try {
        $g = $pdo->query("SELECT * FROM payment_gateways WHERE type='razorpay' AND enabled=1 ORDER BY sort_order ASC LIMIT 1")->fetch();
        if ($g) return $g;
        $any = $pdo->query("SELECT COUNT(*) c FROM payment_gateways")->fetch();
        if ($any && (int)$any['c'] > 0) return null; // gateways exist but none enabled -> automatic disabled on purpose
    } catch (Exception $e) { /* table might not exist yet on old installs */ }
    if (defined('RAZORPAY_KEY_ID') && RAZORPAY_KEY_ID && strpos(RAZORPAY_KEY_ID,'XXXX') === false) {
        return ['id'=>0,'name'=>'Razorpay','type'=>'razorpay','key_id'=>RAZORPAY_KEY_ID,'key_secret'=>RAZORPAY_KEY_SECRET];
    }
    return null;
}

/** Returns the enabled WatchPays gateway row, if any. key_id = merchant_id, key_secret = api_key. */
function activeWatchPaysGateway($pdo) {
    try {
        return $pdo->query("SELECT * FROM payment_gateways WHERE type='watchpays' AND enabled=1 ORDER BY sort_order ASC LIMIT 1")->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/** Returns all enabled manual (UPI-type) gateways. Falls back to config.php UPI constants. */
function activeManualGateways($pdo) {
    try {
        $rows = $pdo->query("SELECT * FROM payment_gateways WHERE type='upi' AND enabled=1 ORDER BY sort_order ASC")->fetchAll();
        if ($rows) return $rows;
        $any = $pdo->query("SELECT COUNT(*) c FROM payment_gateways WHERE type='upi'")->fetch();
        if ($any && (int)$any['c'] > 0) return [];
    } catch (Exception $e) { /* old install without table yet */ }
    if (defined('UPI_ID') && UPI_ID) {
        return [['id'=>0,'name'=>'UPI','type'=>'upi','upi_id'=>UPI_ID,'upi_name'=>defined('UPI_NAME')?UPI_NAME:SITE_NAME]];
    }
    return [];
}

/** Returns all enabled crypto-type gateways (USDT TRC20/BEP20/etc, or any coin admin adds). Falls back to legacy USDT_TRC20_ADDRESS setting. */
function activeCryptoGateways($pdo) {
    try {
        $rows = $pdo->query("SELECT * FROM payment_gateways WHERE type='crypto' AND enabled=1 ORDER BY sort_order ASC")->fetchAll();
        if ($rows) return $rows;
        $any = $pdo->query("SELECT COUNT(*) c FROM payment_gateways WHERE type='crypto'")->fetch();
        if ($any && (int)$any['c'] > 0) return []; // crypto gateways exist but none enabled
    } catch (Exception $e) { /* old install without table yet */ }
    if (defined('USDT_ENABLED') && USDT_ENABLED && defined('USDT_TRC20_ADDRESS') && USDT_TRC20_ADDRESS) {
        return [['id'=>0,'name'=>'USDT','type'=>'crypto','upi_id'=>USDT_TRC20_ADDRESS,'upi_name'=>'TRC20 (Tron)']];
    }
    return [];
}

function clean($s) { return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8'); }

/**
 * Countdown "offer ends" timestamp for a product card / detail page.
 * - If admin has set `offer_ends_at` on the product, that exact datetime is used.
 * - Otherwise, a per-product rolling window is generated automatically from the
 *   product's ID, so every product shows a different (but stable, not randomly
 *   jumping every reload) countdown with zero admin action needed — new products
 *   get their own timer the moment they're added.
 * Returns a UNIX timestamp (seconds).
 */
function getOfferEndTimestamp($product, $cycleHours = 6) {
    if (!empty($product['offer_ends_at'])) {
        $ts = strtotime($product['offer_ends_at']);
        if ($ts !== false) return $ts;
    }
    $cycleSeconds = max(1, $cycleHours) * 3600;
    $pid    = (int)($product['id'] ?? 0);
    $offset = ($pid * 733) % $cycleSeconds; // unique phase per product id
    $now    = time();
    $cycleIndex = (int)floor(($now + $offset) / $cycleSeconds);
    return ($cycleIndex + 1) * $cycleSeconds - $offset;
}

// ---- Vendor marketplace (user-listed products) ----
// Buyer fee %  = added ON TOP of the seller's price to get the price shown to buyers.
// Commission % = taken OUT of the seller's price; the rest is what the seller earns.
// Example: seller sets price = 1000 -> buyer sees 1000*(1+10%) = 1100.
//          seller earns 1000*(1-20%) = 800. Platform keeps 1100-800 = 300.
function vendorBuyerFeePercent($pdo)  { return (float)cfg($pdo, 'vendor_buyer_fee_percent', null) ?: 10; }
function vendorCommissionPercent($pdo){ return (float)cfg($pdo, 'vendor_commission_percent', null) ?: 20; }

/** Given a seller's own asking price, returns the price buyers see (base + buyer fee). */
function vendorBuyerPrice($pdo, $basePrice) {
    $fee = vendorBuyerFeePercent($pdo);
    return round((float)$basePrice * (1 + $fee / 100), 2);
}

/** Given a seller's own asking price, returns what the seller actually earns per sale (base - commission). */
function vendorSellerEarning($pdo, $basePrice) {
    $commission = vendorCommissionPercent($pdo);
    return round((float)$basePrice * (1 - $commission / 100), 2);
}

/**
 * Call this right after (and only after) an order's status is set to 'paid'.
 * Idempotent -- safe to call from every payment-success code path
 * (Razorpay verify, manual UPI/USDT admin-approve, WatchPays webhook)
 * without ever double-crediting a seller.
 * Does nothing for admin-owned products (seller_id IS NULL).
 */
function creditVendorEarning($pdo, $orderId) {
    try {
        $o = $pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
        $o->execute([$orderId]);
        $order = $o->fetch();
        if (!$order) return;

        $p = $pdo->prepare("SELECT * FROM products WHERE id=? LIMIT 1");
        $p->execute([$order['product_id']]);
        $product = $p->fetch();
        if (!$product || empty($product['seller_id'])) return; // not a vendor product

        $chk = $pdo->prepare("SELECT id FROM seller_earnings WHERE order_id=? LIMIT 1");
        $chk->execute([$orderId]);
        if ($chk->fetch()) return; // already credited

        $buyerAmount  = (float)$order['amount'];  // actual amount paid (after coupon)
        $commission   = vendorCommissionPercent($pdo);
        $sellerAmount = round($buyerAmount * (1 - $commission / 100), 2);
        $base         = (float)($product['seller_base_price'] ?? 0) ?: $buyerAmount;
        $platformFee  = round($buyerAmount - $sellerAmount, 2);

        $pdo->prepare("INSERT INTO seller_earnings (order_id, product_id, seller_id, buyer_amount, base_price, seller_amount, platform_fee, payout_status)
            VALUES (?,?,?,?,?,?,?, 'unpaid')")
            ->execute([$orderId, $product['id'], $product['seller_id'], $buyerAmount, $base, $sellerAmount, $platformFee]);
    } catch (Exception $e) {
        error_log('creditVendorEarning failed: ' . $e->getMessage());
    }
}

/**
 * Referral/Affiliate commission — credits the referrer's wallet when their
 * referred buyer completes a purchase. Called from every payment-success path.
 * Safe to call multiple times (idempotent via UNIQUE KEY on order_id).
 */
function creditReferralCommission($pdo, $orderId) {
    try {
        // Get order + buyer
        $o = $pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
        $o->execute([$orderId]); $order = $o->fetch();
        if (!$order) return;

        // Get buyer's referred_by
        $u = $pdo->prepare("SELECT id, referred_by FROM users WHERE id=? LIMIT 1");
        $u->execute([$order['user_id']]); $buyer = $u->fetch();
        if (!$buyer || empty($buyer['referred_by'])) return;

        $referrerId = (int)$buyer['referred_by'];

        // Avoid double-credit
        $chk = $pdo->prepare("SELECT id FROM referral_commissions WHERE order_id=? LIMIT 1");
        $chk->execute([$orderId]);
        if ($chk->fetch()) return;

        $commissionPct = (float)getSetting($pdo, 'referral_commission_pct', 5);
        if ($commissionPct <= 0) return;

        $commissionAmt = round((float)$order['amount'] * $commissionPct / 100, 2);
        if ($commissionAmt <= 0) return;

        // Log commission
        $pdo->prepare("INSERT INTO referral_commissions (order_id, referrer_id, buyer_id, order_amount, commission_pct, commission_amount) VALUES (?,?,?,?,?,?)")
            ->execute([$orderId, $referrerId, $order['user_id'], $order['amount'], $commissionPct, $commissionAmt]);

        // Credit referrer's wallet
        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?")
            ->execute([$commissionAmt, $referrerId]);
        $newBal = (float)$pdo->prepare("SELECT wallet_balance FROM users WHERE id=?")->execute([$referrerId]) ? 0 : 0;
        $nb = $pdo->prepare("SELECT wallet_balance FROM users WHERE id=?"); $nb->execute([$referrerId]);
        $newBal = (float)$nb->fetchColumn();

        $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, ref_id, balance_after) VALUES (?,'credit',?,?,?,?)")
            ->execute([$referrerId, $commissionAmt, 'Referral commission — order ' . $order['order_ref'], $order['order_ref'], $newBal]);

        notifyTelegram("🤝 Referral Commission\nReferrer ID: $referrerId\nAmount: ₹$commissionAmt ({$commissionPct}%)\nOrder: {$order['order_ref']}");
    } catch (Exception $e) {
        error_log('creditReferralCommission failed: ' . $e->getMessage());
    }
}

/**
 * Generate a unique referral code for a user if they don't have one yet.
 * Returns the code.
 */
function ensureReferralCode($pdo, $userId) {
    $r = $pdo->prepare("SELECT referral_code FROM users WHERE id=?");
    $r->execute([$userId]);
    $code = $r->fetchColumn();
    if ($code) return $code;
    // generate unique 8-char alphanumeric code
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        $exists = $pdo->prepare("SELECT id FROM users WHERE referral_code=?");
        $exists->execute([$code]);
    } while ($exists->fetch());
    $pdo->prepare("UPDATE users SET referral_code=? WHERE id=?")->execute([$code, $userId]);
    return $code;
}

// Keeps the orders table from growing forever: once total orders cross $limit,
// the oldest already-resolved orders (paid/delivered/rejected/refunded) are
// deleted first, so nothing still 'pending' review ever gets silently removed.
function pruneOldOrders($pdo, $limit = 500) {
    $total = (int)$pdo->query("SELECT COUNT(*) c FROM orders")->fetch()['c'];
    if ($total <= $limit) return 0;
    $excess = (int)($total - $limit);
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE status != 'pending' ORDER BY created_at ASC LIMIT $excess");
    $stmt->execute();
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $pdo->exec("DELETE FROM orders WHERE id IN ($in)");
    }
    return count($ids);
}

function redirect($url) { header("Location: $url"); exit; }
function isLoggedIn() { return !empty($_SESSION['user_id']); }
function isAdmin()    { return !empty($_SESSION['admin_id']); }
function requireLogin() { if (!isLoggedIn()) redirect(SITE_URL.'/login.php'); }
function requireAdmin() { if (!isAdmin()) redirect(SITE_URL.'/admin/login.php'); }

// ---- CSRF protection ----
// csrf_token(): ek random token session me banata/return karta hai.
// csrf_field(): form ke andar chipkane wala hidden <input>.
// verifyCsrf(): POST ke shuru me call karo — token match nahi to request reject.
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}
function verifyCsrf($redirectTo = null) {
    $sent = $_POST['csrf_token'] ?? '';
    $ok = !empty($sent) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
    if (!$ok) {
        if ($redirectTo) {
            flash('error', 'Security check failed (page expired). Please try again.');
            redirect($redirectTo);
        }
        http_response_code(403);
        die('Security check failed. Please go back and try again.');
    }
}

// ---- Login rate limiting (per IP + username, DB-backed) ----
// Table auto-skips if migration not run yet, so it never breaks existing installs.
function clientIp() {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
function isRateLimited($pdo, $identifier, $maxAttempts = 5, $windowMinutes = 15) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier=? AND attempted_at > (NOW() - INTERVAL ? MINUTE)");
        $stmt->execute([$identifier, $windowMinutes]);
        return (int)$stmt->fetchColumn() >= $maxAttempts;
    } catch (Exception $e) { return false; /* table not migrated yet */ }
}
function recordLoginAttempt($pdo, $identifier) {
    try {
        $pdo->prepare("INSERT INTO login_attempts (identifier, attempted_at) VALUES (?, NOW())")->execute([$identifier]);
        $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)")->execute();
    } catch (Exception $e) { /* table not migrated yet */ }
}
function clearLoginAttempts($pdo, $identifier) {
    try {
        $pdo->prepare("DELETE FROM login_attempts WHERE identifier=?")->execute([$identifier]);
    } catch (Exception $e) { /* table not migrated yet */ }
}

// ---- Generic rate limiting (contact form, etc — same idea as login limiter
// but backed by its own table so it never gets mixed up with login counts) ----
function isGenericRateLimited($pdo, $identifier, $maxAttempts = 3, $windowMinutes = 30) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limit_attempts WHERE identifier=? AND attempted_at > (NOW() - INTERVAL ? MINUTE)");
        $stmt->execute([$identifier, $windowMinutes]);
        return (int)$stmt->fetchColumn() >= $maxAttempts;
    } catch (Exception $e) { return false; /* table not migrated yet */ }
}
function recordGenericAttempt($pdo, $identifier) {
    try {
        $pdo->prepare("INSERT INTO rate_limit_attempts (identifier, attempted_at) VALUES (?, NOW())")->execute([$identifier]);
        $pdo->prepare("DELETE FROM rate_limit_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)")->execute();
    } catch (Exception $e) { /* table not migrated yet */ }
}

function flash($key, $msg = null) {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return; }
    if (!empty($_SESSION['flash'][$key])) { $m=$_SESSION['flash'][$key]; unset($_SESSION['flash'][$key]); return $m; }
    return null;
}

function generateToken($orderId, $email) {
    return hash_hmac('sha256', $orderId.'|'.$email, DOWNLOAD_TOKEN_SECRET);
}

function generateOrderRef() {
    return 'DS' . strtoupper(bin2hex(random_bytes(5)));
}

function generateLicenseKey() {
    $seg = fn() => strtoupper(bin2hex(random_bytes(2)));
    return $seg().'-'.$seg().'-'.$seg().'-'.$seg();
}

// ---- Forgot / Reset password ----
// A random token is emailed to the user (never stored in DB in plain form);
// only its sha256 hash is stored, so a leaked DB alone can't be used to reset accounts.
function createPasswordResetToken($pdo, $email, $ttlMinutes = 60) {
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
    // Invalidate any older, still-unused tokens for this email first
    $pdo->prepare("UPDATE password_resets SET used=1 WHERE email=? AND used=0")->execute([$email]);
    $pdo->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?,?,?)")
        ->execute([$email, $hash, $expiresAt]);
    return $token;
}

function findValidPasswordReset($pdo, $email, $token) {
    $hash = hash('sha256', (string)$token);
    // Compare against PHP's current time (not MySQL's NOW()) to avoid
    // false "expired" results when the PHP and MySQL server timezones differ.
    $nowStr = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email=? AND token_hash=? AND used=0 AND expires_at > ? LIMIT 1");
    $stmt->execute([$email, $hash, $nowStr]);
    return $stmt->fetch();
}

function markPasswordResetUsed($pdo, $id) {
    $pdo->prepare("UPDATE password_resets SET used=1 WHERE id=?")->execute([$id]);
}

function notifyTelegram($message) {
    if (!TG_BOT_TOKEN || !TG_CHAT_ID) return;
    $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'    => TG_CHAT_ID,
            'text'       => $message,
            'parse_mode' => 'HTML',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function slugify($text) {
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    return $text;
}

/**
 * Downloads an image from a remote URL (e.g. pasted from Google Images) and
 * saves it locally in uploads/products/, returning the new filename.
 * Downloading it (instead of hotlinking) means the product page always loads
 * the photo fast from our own server, and it keeps working even if the
 * original source link goes down later.
 * Returns null on any failure (invalid URL, not an image, too large, etc.)
 */
function fetchImageFromUrl($url) {
    $url = trim((string)$url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) return null;

    $maxBytes = 8 * 1024 * 1024; // 8MB safety cap
    $data = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS       => 4,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; DevStoreImageFetch/1.0)',
            CURLOPT_RANGE          => '0-' . $maxBytes,
        ]);
        $data = curl_exec($ch);
        curl_close($ch);
    }
    if ($data === false && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
        $data = @file_get_contents($url, false, $ctx);
    }
    if (!$data || strlen($data) < 100 || strlen($data) > $maxBytes) return null;

    $info = @getimagesizefromstring($data);
    if (!$info || empty($info['mime'])) return null;
    $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $ext = $mimeToExt[$info['mime']] ?? null;
    if (!$ext) return null;

    $name = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (@file_put_contents(UPLOAD_PATH . '/products/' . $name, $data) === false) return null;
    return $name;
}


function getUserRole($pdo = null): ?string {
    if (!isset($_SESSION['user_id'])) return null;
    // Session cached
    if (isset($_SESSION['user_role'])) return $_SESSION['user_role'];
    // DB se fetch karo aur cache karo
    if ($pdo) {
        $row = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $row->execute([$_SESSION['user_id']]);
        $r = $row->fetchColumn();
        $_SESSION['user_role'] = $r ?: 'buyer';
        return $_SESSION['user_role'];
    }
    return 'buyer';
}

/**
 * Sirf Seller access karne deta hai.
 * Non-sellers ko redirect karta hai.
 */
function requireSeller($pdo): void {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/login.php');
    }
    if (getUserRole($pdo) !== 'seller') {
        flash('error', 'This area is for sellers only.');
        redirect(SITE_URL . '/dashboard.php');
    }
}

/**
 * Sirf Buyer access karne deta hai.
 * Sellers ko seller dashboard pe redirect karta hai.
 */
function requireBuyer($pdo): void {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/login.php');
    }
    if (getUserRole($pdo) === 'seller') {
        flash('info', 'Sellers cannot purchase. Switch to a buyer account.');
        redirect(SITE_URL . '/dashboard.php');
    }
}