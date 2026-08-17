<?php
/**
 * Google OAuth 2.0 Callback Handler
 * 
 * Setup karne ke liye:
 * 1. https://console.cloud.google.com pe jaao
 * 2. Project banao → APIs & Services → OAuth 2.0 Client IDs
 * 3. Authorized redirect URIs mein add karo: SITE_URL/auth/google_callback.php
 * 4. Client ID aur Secret ko Admin → Settings mein save karo
 *    Keys: 'google_client_id' aur 'google_client_secret'
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';

$clientId     = getSetting($pdo, 'google_client_id', '');
$clientSecret = getSetting($pdo, 'google_client_secret', '');

if (!$clientId || !$clientSecret) {
    flash('error', 'Google Login is not configured yet. Please contact admin.');
    redirect(SITE_URL . '/login.php');
}

$redirectUri = SITE_URL . '/auth/google_callback.php';
$next        = clean($_SESSION['google_next'] ?? '');

// Step 1: No code yet — redirect to Google
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_state'] = $state;
    $params = http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
    ]);
    redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
}

// Step 2: Google returned — verify state and exchange code
$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

if (!$state || empty($_SESSION['google_state']) || !hash_equals($_SESSION['google_state'], $state)) {
    flash('error', 'Security check failed. Please try again.');
    redirect(SITE_URL . '/login.php');
}
unset($_SESSION['google_state']);

// Exchange code for access token
$tokenData = _googlePost('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]);

if (empty($tokenData['access_token'])) {
    flash('error', 'Google login failed. Please try again.');
    redirect(SITE_URL . '/login.php');
}

// Fetch user info
$userInfo = _googleGet('https://www.googleapis.com/oauth2/v2/userinfo', $tokenData['access_token']);

if (empty($userInfo['email'])) {
    flash('error', 'Could not get your email from Google. Please try again.');
    redirect(SITE_URL . '/login.php');
}

$googleId = (string)($userInfo['id'] ?? '');
$email    = strtolower(trim($userInfo['email']));
$name     = clean($userInfo['name'] ?? 'User');
$avatar   = $userInfo['picture'] ?? null;

try {
    $u = null;
    // Try by google_id first
    if ($googleId) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id=? LIMIT 1");
        $stmt->execute([$googleId]);
        $u = $stmt->fetch();
    }
    // Fallback: match by email
    if (!$u) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
    }

    if ($u) {
        // Existing user — link google_id + refresh avatar
        $pdo->prepare("UPDATE users SET google_id=?, avatar=? WHERE id=?")
            ->execute([$googleId ?: ($u['google_id'] ?? null), $avatar ?: ($u['avatar'] ?? null), $u['id']]);
        ensureReferralCode($pdo, $u['id']);
        $_SESSION['user_id']   = $u['id'];
        $_SESSION['user_name'] = $u['name'];
        flash('success', 'Welcome back, ' . clean($u['name']) . '! 👋');
    } else {
        // New user — create account (no password for Google-only accounts)
        $refCode    = clean($_SESSION['referral_code'] ?? '');
        $referrerId = null;
        if ($refCode) {
            $rr = $pdo->prepare("SELECT id FROM users WHERE referral_code=? LIMIT 1");
            $rr->execute([$refCode]);
            $rRef = $rr->fetch();
            if ($rRef) $referrerId = (int)$rRef['id'];
        }
        $pdo->prepare("INSERT INTO users (name, email, phone, password, google_id, avatar, referred_by) VALUES (?,?,?,?,?,?,?)")
            ->execute([$name, $email, '', '', $googleId, $avatar, $referrerId]);
        $newUid = (int)$pdo->lastInsertId();
        ensureReferralCode($pdo, $newUid);
        unset($_SESSION['referral_code']);

        $_SESSION['user_id']   = $newUid;
        $_SESSION['user_name'] = $name;
        flash('success', 'Welcome to ' . SITE_NAME . ', ' . $name . '! 🎉');
    }
} catch (Exception $e) {
    error_log('Google OAuth DB error: ' . $e->getMessage());
    flash('error', 'Login failed due to an error. Please try again.');
    redirect(SITE_URL . '/login.php');
}

redirect($next ? SITE_URL . '/' . $next : SITE_URL . '/dashboard.php');

// ---- Internal helpers ----
function _googlePost($url, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : [];
}

function _googleGet($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : [];
}
