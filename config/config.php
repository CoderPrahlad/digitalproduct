<?php
// ---- TIMEZONE ----
// Explicitly set so token expiry, order timestamps etc. are consistent
// regardless of the server's default PHP timezone (avoids "reset link
// expired instantly" bugs caused by PHP/MySQL timezone mismatches).
date_default_timezone_set('Asia/Kolkata');

// ---- DEBUG MODE ----
// Local testing (XAMPP) me true kar sakte ho taaki full error dikhe.
// LIVE / PRODUCTION site pe hamesha false hona chahiye — warna visitors ko
// server ka file path, DB structure waghera error screen me dikh sakta hai.
define('APP_DEBUG', true);

// ---- ERROR HANDLING ----
// Errors ab screen pe nahi, ek log file me jaate hain.
$__logDir = __DIR__ . '/../logs';
if (!is_dir($__logDir)) { @mkdir($__logDir, 0755, true); }
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $__logDir . '/php-error.log');
error_reporting(E_ALL);

set_exception_handler(function($e) {
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (APP_DEBUG) {
        echo '<pre>' . htmlspecialchars((string)$e) . '</pre>';
    } else {
        echo '<h2 style="font-family:sans-serif;padding:40px">Something went wrong. Please try again shortly.</h2>';
    }
    exit;
});

// ---- DATABASE (must stay hardcoded — needed to open the connection) ----
define('DB_HOST', '82.25.121.202');
define('DB_NAME', 'u137390330_digitalproduct');
define('DB_USER', 'u137390330_digitalproduct');
define('DB_PASS', 'u137390330_Digitalproduct');

// ---- SESSION HARDENING ----
if (session_status() === PHP_SESSION_NONE) {
    $__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $__isHttps,   // sirf HTTPS pe cookie bheji jaye
        'httponly' => true,         // JavaScript se cookie access nahi ho sakti
        'samesite' => 'Lax',        // CSRF ka ek aur layer
    ]);
    session_start();
}
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('<h2 style="font-family:sans-serif;color:red;padding:40px">Database connection failed. config.php check karo.</h2>');
}

// ---- HARD DEFAULTS ----
// These are just the starting values. Everything below marked "admin-editable"
// can be changed for real from Admin -> Settings / Admin -> Gateways without
// touching this file — the values saved there always win over the defaults here.
$__defaults = [
    'site_name'         => 'Coder Prahlad',
    'site_url'          => 'http://localhost/devstore1',
    'site_tagline'      => 'Premium Source Code — Instant Automatic Delivery',
    'tg_channel_url'    => 'https://t.me/coderprahlad',
    'yt_channel_url'    => 'https://www.youtube.com/@CoderPrahalad',
    'ga4_id'            => ' G-74XQQ5LLR6',   // e.g. G-XXXXXXXXXX
    'whatsapp_number'   => '919876543210',
    'usdt_trc20_address'=> 'TRPgPexxnKFrcVRiD5ABY8CxbhFo1YPStA',
    'usdt_enabled'      => '1',
    'smtp_host'         => 'smtp.gmail.com',
    'smtp_user'         => 'youremail@gmail.com',
    'smtp_pass'         => 'your_app_password',
    'smtp_port'         => 587,
    'mail_from'         => 'youremail@gmail.com',
    'mail_from_name'    => null, // falls back to site_name below
   'tg_bot_token' => '8269097621:AAH9C0wTi1YuilNO4gOgHfORzovrAy_XlmI',
'tg_chat_id'   => '7708846583',
];

// ---- Pull admin-saved overrides from the `settings` table (safe if table is empty/missing) ----
$__dbSettings = [];
try {
    foreach ($pdo->query("SELECT key_name, key_value FROM settings")->fetchAll() as $__row) {
        $__dbSettings[$__row['key_name']] = $__row['key_value'];
    }
} catch (Exception $e) { /* settings table not migrated yet — defaults above are used */ }

function __cfgval($key, $default, $dbSettings) {
    return (isset($dbSettings[$key]) && $dbSettings[$key] !== '') ? $dbSettings[$key] : $default;
}

// ---- Site identity (admin-editable: Admin -> Settings -> Site Info) ----
define('SITE_NAME',     __cfgval('site_name', $__defaults['site_name'], $__dbSettings));
define('SITE_URL',      rtrim(__cfgval('site_url', $__defaults['site_url'], $__dbSettings), '/'));
define('SITE_TAGLINE',  __cfgval('site_tagline', $__defaults['site_tagline'], $__dbSettings));

// Logo — upload your logo to assets/img/logo.png then set this, e.g. '/assets/img/logo.png'
// Leave empty to use the text logo (⚡ + SITE_NAME)
define('LOGO_URL', '');

// Telegram (channel link is admin-editable; owner handle stays static branding)
define('TG_CHANNEL_URL',    __cfgval('tg_channel_url', $__defaults['tg_channel_url'], $__dbSettings));
define('YT_CHANNEL_URL',    __cfgval('yt_channel_url', $__defaults['yt_channel_url'], $__dbSettings));
define('GA4_ID',            __cfgval('ga4_id',         $__defaults['ga4_id'],         $__dbSettings));
define('OWNER_TG_USERNAME', '@Prahladsharma');
define('OWNER_TG_URL',      'https://t.me/Prahladsharma');

// Demo page (shown on /demo.php) — fill these in with your real demo details
// ⚠️ SECURITY: DEMO_URL/DEMO_ADMIN_URL should point to a SEPARATE sandbox
// install with throwaway data — never your real production site. This page
// publicly displays these credentials to anyone who visits /demo.php.
define('DEMO_URL',          'https://yourdomain.com');
define('DEMO_PROJECT_INFO', 'DevStore — PHP + MySQL premium digital product store with Razorpay auto-payment, secure token-based downloads, and instant email delivery after purchase.');
define('DEMO_USER_URL',     'https://yourdomain.com/login.php');
define('DEMO_USER_EMAIL',   'demo@example.com');
define('DEMO_USER_PASS',    'demo123');
define('DEMO_ADMIN_URL',    'https://yourdomain.com/admin/login.php');
define('DEMO_ADMIN_EMAIL',  'admin@example.com');
define('DEMO_ADMIN_PASS',   'admin123');

// Payment gateway fallback constants — once you add gateways from
// Admin -> Gateways, those DB entries are used instead of these.
define('RAZORPAY_KEY_ID',     'rzp_test_XXXXXXXXXXXXXXXX');
define('RAZORPAY_KEY_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXX');
define('UPI_ID',   'yourname@upi');
define('UPI_NAME', 'DevStore');

// Email (admin-editable: Admin -> Settings -> Email (SMTP))
define('MAIL_HOST',      __cfgval('smtp_host', $__defaults['smtp_host'], $__dbSettings));
define('MAIL_USER',      __cfgval('smtp_user', $__defaults['smtp_user'], $__dbSettings));
define('MAIL_PASS',      __cfgval('smtp_pass', $__defaults['smtp_pass'], $__dbSettings));
define('MAIL_PORT',      (int)__cfgval('smtp_port', $__defaults['smtp_port'], $__dbSettings));
define('MAIL_FROM',      __cfgval('mail_from', $__defaults['mail_from'], $__dbSettings));
define('MAIL_FROM_NAME', __cfgval('mail_from_name', SITE_NAME, $__dbSettings));

// WhatsApp support number (admin-editable: Admin -> Settings -> Site Info)
define('WA_NUMBER', __cfgval('whatsapp_number', $__defaults['whatsapp_number'], $__dbSettings));

// USDT (TRC20) wallet address for crypto payments (admin-editable: Admin -> Settings -> Site Info)
define('USDT_TRC20_ADDRESS', __cfgval('usdt_trc20_address', $__defaults['usdt_trc20_address'], $__dbSettings));
define('USDT_ENABLED', __cfgval('usdt_enabled', $__defaults['usdt_enabled'], $__dbSettings) === '1');

// Telegram bot notifications (admin-editable: Admin -> Settings -> Telegram)
define('TG_BOT_TOKEN', __cfgval('tg_bot_token', $__defaults['tg_bot_token'], $__dbSettings));
define('TG_CHAT_ID',   __cfgval('tg_chat_id', $__defaults['tg_chat_id'], $__dbSettings));

define('DOWNLOAD_TOKEN_SECRET', 'change_me_to_32_random_chars_!!x');
define('ROOT_PATH',     dirname(__DIR__));
define('UPLOAD_PATH',   ROOT_PATH . '/uploads');
define('DOWNLOAD_PATH', ROOT_PATH . '/secure_downloads');

unset($__defaults, $__dbSettings);
