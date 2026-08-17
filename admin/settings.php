<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL.'/admin/settings.php');
    $form = $_POST['form'] ?? '';

    if ($form === 'general_download') {
        setSetting($pdo, 'download_expiry_hours', clean($_POST['download_expiry_hours'] ?? '72'));
        setSetting($pdo, 'max_downloads', clean($_POST['max_downloads'] ?? '3'));
        flash('success','Download settings saved.');
        redirect(SITE_URL.'/admin/settings.php#general');
    }
    if ($form === 'general_mode') {
        setSetting($pdo, 'maintenance_mode', clean($_POST['maintenance_mode'] ?? '0'));
        setSetting($pdo, 'payment_mode', clean($_POST['payment_mode'] ?? 'both'));
        flash('success','Mode settings saved.');
        redirect(SITE_URL.'/admin/settings.php#general');
    }
    if ($form === 'site_details') {
        foreach (['site_name','site_url','site_tagline'] as $f) setSetting($pdo, $f, trim($_POST[$f] ?? ''));
        flash('success','Site details saved.');
        redirect(SITE_URL.'/admin/settings.php#site');
    }
    if ($form === 'site_social') {
        foreach (['whatsapp_number','tg_channel_url'] as $f) setSetting($pdo, $f, trim($_POST[$f] ?? ''));
        flash('success','Social links saved.');
        redirect(SITE_URL.'/admin/settings.php#site');
    }
    if ($form === 'smtp') {
        foreach (['smtp_host','smtp_user','smtp_port','mail_from','mail_from_name'] as $f) setSetting($pdo, $f, trim($_POST[$f] ?? ''));
        if (trim($_POST['smtp_pass'] ?? '') !== '') setSetting($pdo, 'smtp_pass', trim($_POST['smtp_pass']));
        flash('success','Email settings saved.');
        redirect(SITE_URL.'/admin/settings.php#smtp');
    }
    if ($form === 'test_email') {
        require_once dirname(__DIR__) . '/mail/Mailer.php';
        $testTo = trim($_POST['test_email_to'] ?? '');
        if ($testTo && filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $ok = Mailer::send($testTo, 'Test Email from '.SITE_NAME, '<h2 style="font-family:sans-serif">✅ SMTP is working!</h2><p style="font-family:sans-serif;color:#555">Test email from <strong>'.SITE_NAME.'</strong>.</p>');
            flash($ok ? 'success' : 'error', $ok ? 'Test email sent to '.$testTo : 'Failed. Check SMTP settings and logs/php-error.log');
        } else { flash('error','Enter a valid email.'); }
        redirect(SITE_URL.'/admin/settings.php#smtp');
    }
    if ($form === 'telegram') {
        foreach (['tg_bot_token','tg_chat_id'] as $f) setSetting($pdo, $f, trim($_POST[$f] ?? ''));
        flash('success','Telegram settings saved.');
        redirect(SITE_URL.'/admin/settings.php#telegram');
    }
    if ($form === 'google') {
        foreach (['google_client_id','google_client_secret'] as $f) {
            $val = trim($_POST[$f] ?? ''); if ($val !== '') setSetting($pdo, $f, $val);
        }
        flash('success','Google OAuth settings saved.');
        redirect(SITE_URL.'/admin/settings.php#google');
    }
    if ($form === 'referral') {
        $pct = max(0, min(50, (float)($_POST['referral_commission_pct'] ?? 5)));
        setSetting($pdo, 'referral_commission_pct', $pct);
        flash('success','Referral settings saved.');
        redirect(SITE_URL.'/admin/settings.php#referral');
    }
    redirect(SITE_URL.'/admin/settings.php');
}

$settings = [];
foreach ($pdo->query("SELECT * FROM settings")->fetchAll() as $s) { $settings[$s['key_name']] = $s['key_value']; }
try {
    $nlCount = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='active'")->fetchColumn();
    $nlTotal = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
} catch(Exception $e) { $nlCount = 0; $nlTotal = 0; }
?>

<div class="admin-topbar"><h1>⚙️ Settings</h1></div>

<style>
.stabs-wrap{display:flex;gap:0;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:5px;margin-bottom:22px;overflow-x:auto;flex-wrap:nowrap}
.stab-btn{padding:9px 18px;border-radius:9px;font-size:13px;font-weight:600;color:var(--muted2);white-space:nowrap;cursor:pointer;border:none;background:none;transition:all .18s;text-decoration:none}
.stab-btn.active,.stab-btn:hover{background:var(--primary);color:#fff}
.spanel{display:none}.spanel.active{display:block}
.s2col{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start}
@media(max-width:820px){.s2col{grid-template-columns:1fr}}
.s-info-box{color:var(--muted);font-size:13px;line-height:1.8}
.s-info-box p{margin:0 0 6px}
</style>

<!-- Tab Nav -->
<div class="stabs-wrap">
  <button class="stab-btn active" onclick="switchTab('general',this)">🔧 General</button>
  <button class="stab-btn" onclick="switchTab('site',this)">🌐 Site Info</button>
  <button class="stab-btn" onclick="switchTab('smtp',this)">📧 Email/SMTP</button>
  <button class="stab-btn" onclick="switchTab('telegram',this)">✈️ Telegram</button>
  <button class="stab-btn" onclick="switchTab('google',this)">🔐 Google OAuth</button>
  <button class="stab-btn" onclick="switchTab('referral',this)">🤝 Referral</button>
  <button class="stab-btn" onclick="switchTab('newsletter',this)">📬 Newsletter</button>
  <button class="stab-btn" onclick="switchTab('gateways',this)">💳 Gateways</button>
</div>
<script>
function switchTab(id, el) {
  document.querySelectorAll('.spanel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.stab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('sp-'+id).classList.add('active');
  el.classList.add('active');
  history.replaceState(null,'','#'+id);
}
window.addEventListener('DOMContentLoaded', function(){
  var h = location.hash.replace('#','');
  var btn = h ? document.querySelector('[onclick*="\''+h+'\'"]') : null;
  if (btn) switchTab(h, btn);
});
</script>

<!-- ═══ GENERAL ═══ -->
<div id="sp-general" class="spanel active">
  <div class="s2col">
    <div class="section-card">
      <h3>⬇️ Download Settings</h3>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="general_download">
        <div class="form-group">
          <label>Download Link Expiry (hours)</label>
          <input class="form-control" type="number" name="download_expiry_hours" value="<?= clean($settings['download_expiry_hours'] ?? 72) ?>">
          <p class="form-hint">How long download links stay valid after delivery</p>
        </div>
        <div class="form-group">
          <label>Max Downloads Per Order</label>
          <input class="form-control" type="number" name="max_downloads" value="<?= clean($settings['max_downloads'] ?? 3) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save Download Settings</button>
      </form>
    </div>
    <div class="section-card">
      <h3>🔧 Site Mode</h3>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="general_mode">
        <div class="form-group">
          <label>Maintenance Mode</label>
          <select class="form-control" name="maintenance_mode">
            <option value="0" <?= ($settings['maintenance_mode']??'0')==='0'?'selected':'' ?>>Off</option>
            <option value="1" <?= ($settings['maintenance_mode']??'0')==='1'?'selected':'' ?>>On</option>
          </select>
        </div>
        <div class="form-group">
          <label>Payment Mode</label>
          <select class="form-control" name="payment_mode">
            <option value="both"      <?= ($settings['payment_mode']??'both')==='both'?'selected':'' ?>>Both — Auto Gateway + Manual UPI</option>
            <option value="automatic" <?= ($settings['payment_mode']??'both')==='automatic'?'selected':'' ?>>Automatic Only (Razorpay)</option>
            <option value="manual"    <?= ($settings['payment_mode']??'both')==='manual'?'selected':'' ?>>Manual Only (UPI/QR, admin approval)</option>
          </select>
          <p class="form-hint">Automatic hides UPI form. Manual hides the gateway button.</p>
        </div>
        <button type="submit" class="btn btn-primary">Save Mode Settings</button>
      </form>
    </div>
  </div>
</div>

<!-- ═══ SITE INFO ═══ -->
<div id="sp-site" class="spanel">
  <div class="s2col">
    <div class="section-card">
      <h3>🌐 Site Details</h3>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="site_details">
        <div class="form-group">
          <label>Site Name</label>
          <input class="form-control" type="text" name="site_name" value="<?= clean(cfg($pdo,'site_name','SITE_NAME')) ?>">
        </div>
        <div class="form-group">
          <label>Site URL</label>
          <input class="form-control" type="text" name="site_url" value="<?= clean(cfg($pdo,'site_url','SITE_URL')) ?>" placeholder="https://yourdomain.com">
          <p class="form-hint">No trailing slash. Takes effect immediately after saving.</p>
        </div>
        <div class="form-group">
          <label>Tagline</label>
          <input class="form-control" type="text" name="site_tagline" value="<?= clean(cfg($pdo,'site_tagline','SITE_TAGLINE')) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save Site Details</button>
      </form>
    </div>
    <div class="section-card">
      <h3>📱 Social / Contact</h3>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="site_social">
        <div class="form-group">
          <label>WhatsApp Number</label>
          <input class="form-control" type="text" name="whatsapp_number" value="<?= clean(cfg($pdo,'whatsapp_number','WA_NUMBER')) ?>" placeholder="919876543210 (with country code, no +)">
        </div>
        <div class="form-group">
          <label>Telegram Channel URL</label>
          <input class="form-control" type="text" name="tg_channel_url" value="<?= clean(cfg($pdo,'tg_channel_url','TG_CHANNEL_URL')) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save Social Links</button>
      </form>
    </div>
  </div>
</div>

<!-- ═══ EMAIL/SMTP ═══ -->
<div id="sp-smtp" class="spanel">
  <div class="s2col">
    <div class="section-card">
      <h3>📧 SMTP Configuration</h3>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="smtp">
        <div class="form-group">
          <label>SMTP Host</label>
          <input class="form-control" type="text" name="smtp_host" value="<?= clean(cfg($pdo,'smtp_host','MAIL_HOST')) ?>">
        </div>
        <div class="form-group">
          <label>SMTP Email / Username</label>
          <input class="form-control" type="text" name="smtp_user" value="<?= clean(cfg($pdo,'smtp_user','MAIL_USER')) ?>">
        </div>
        <div class="form-group">
          <label>SMTP App Password</label>
          <input class="form-control" type="password" name="smtp_pass" placeholder="Leave blank to keep current password">
          <p class="form-hint">Gmail: Account → Security → App Passwords (16 chars)</p>
        </div>
        <div class="form-group">
          <label>SMTP Port</label>
          <input class="form-control" type="number" name="smtp_port" value="<?= clean(cfg($pdo,'smtp_port','MAIL_PORT')) ?>">
        </div>
        <div class="form-group">
          <label>"From" Email</label>
          <input class="form-control" type="text" name="mail_from" value="<?= clean(cfg($pdo,'mail_from','MAIL_FROM')) ?>">
        </div>
        <div class="form-group">
          <label>"From" Name</label>
          <input class="form-control" type="text" name="mail_from_name" value="<?= clean(cfg($pdo,'mail_from_name','MAIL_FROM_NAME')) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save Email Settings</button>
      </form>
    </div>
    <div class="section-card">
      <h3>📤 Send Test Email</h3>
      <p style="color:var(--muted);font-size:13px;margin-bottom:16px">After saving SMTP settings, send a test to verify delivery works.</p>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="test_email">
        <div class="form-group">
          <label>Send Test To</label>
          <input class="form-control" type="email" name="test_email_to" placeholder="your@email.com" required>
        </div>
        <button type="submit" class="btn btn-outline">📤 Send Test Email</button>
      </form>
      <div style="margin-top:20px;padding:14px;background:var(--bg-card2);border:1px solid var(--border);border-radius:10px">
        <div class="s-info-box">
          <p>💡 <strong>Tips:</strong></p>
          <p>• Gmail: use App Passwords (not main password)</p>
          <p>• Port 587 = STARTTLS (recommended)</p>
          <p>• Port 465 = SSL/TLS</p>
          <p>• Check <code>logs/php-error.log</code> if mail fails</p>
          <p>• Auto-emails sent: welcome, delivery, password reset, new product alert</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ TELEGRAM ═══ -->
<div id="sp-telegram" class="spanel">
  <div class="s2col">
    <div class="section-card">
      <h3>✈️ Telegram Bot</h3>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="telegram">
        <div class="form-group">
          <label>Bot Token</label>
          <input class="form-control" type="password" name="tg_bot_token" placeholder="<?= cfg($pdo,'tg_bot_token','TG_BOT_TOKEN') ? 'Leave blank to keep current token' : 'e.g. 123456:ABC-DEF...' ?>">
        </div>
        <div class="form-group">
          <label>Chat ID</label>
          <input class="form-control" type="text" name="tg_chat_id" value="<?= clean(cfg($pdo,'tg_chat_id','TG_CHAT_ID')) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save Telegram Settings</button>
      </form>
    </div>
    <div class="section-card">
      <h3>ℹ️ How to Set Up</h3>
      <div class="s-info-box">
        <p>1. Open Telegram → search <strong>@BotFather</strong></p>
        <p>2. Type <code>/newbot</code> and follow prompts</p>
        <p>3. Copy the Bot Token and paste on the left</p>
        <p>4. Add your bot to your group/channel</p>
        <p>5. Get Chat ID via <strong>@userinfobot</strong></p>
        <p style="margin-top:10px;padding:10px;background:var(--bg-card2);border-radius:8px;font-size:12px">
          Notifications sent for: new orders, approvals, rejections, and new seller registrations.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- ═══ GOOGLE OAUTH ═══ -->
<div id="sp-google" class="spanel">
  <div class="s2col">
    <div class="section-card">
      <h3>🔐 Google OAuth</h3>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="google">
        <div class="form-group">
          <label>Google Client ID</label>
          <input class="form-control" type="text" name="google_client_id"
            value="<?= clean($settings['google_client_id'] ?? '') ?>"
            placeholder="xxxx.apps.googleusercontent.com">
        </div>
        <div class="form-group">
          <label>Google Client Secret</label>
          <input class="form-control" type="password" name="google_client_secret"
            placeholder="<?= !empty($settings['google_client_secret']) ? 'Leave blank to keep current secret' : 'GOCSPX-...' ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save Google Settings</button>
        <?php if (!empty($settings['google_client_id'])): ?>
          <span style="color:#34d399;font-size:13px;margin-left:12px">✅ Google Login active</span>
        <?php endif; ?>
      </form>
    </div>
    <div class="section-card">
      <h3>ℹ️ Setup Guide</h3>
      <div class="s-info-box">
        <p>1. Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color:#a78bfa">Google Cloud Console</a></p>
        <p>2. Create OAuth 2.0 Client ID</p>
        <p>3. Set Authorized Redirect URI to:</p>
        <code style="display:block;padding:8px;background:var(--bg-card2);border-radius:6px;font-size:11px;margin:6px 0;word-break:break-all"><?= SITE_URL ?>/auth/google_callback.php</code>
        <p>4. Copy Client ID & Secret to the form on the left</p>
        <p>5. Save and test login on your site</p>
      </div>
    </div>
  </div>
</div>

<!-- ═══ REFERRAL ═══ -->
<div id="sp-referral" class="spanel">
  <div class="s2col">
    <div class="section-card">
      <h3>🤝 Referral / Affiliate Program</h3>
      <p style="color:var(--muted);font-size:13px;margin-bottom:16px">Commission credited to referrer's wallet when referred user makes a purchase. Set to 0 to disable.</p>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="referral">
        <div class="form-group">
          <label>Commission % per sale</label>
          <input class="form-control" type="number" name="referral_commission_pct" min="0" max="50" step="0.5"
            value="<?= clean($settings['referral_commission_pct'] ?? 5) ?>">
          <p class="form-hint">e.g. 5 = referrer earns 5% of every order as store-credit.</p>
        </div>
        <button type="submit" class="btn btn-primary">Save Referral Settings</button>
      </form>
    </div>
    <div class="section-card">
      <h3>ℹ️ How It Works</h3>
      <div class="s-info-box">
        <p>• Users get a unique referral link from their dashboard</p>
        <p>• When a referred user registers and purchases, commission credits instantly</p>
        <p>• Commission goes to the referrer's store wallet balance</p>
        <p>• Wallet balance can be used at checkout</p>
        <p style="margin-top:10px;padding:10px;background:rgba(124,58,237,.08);border:1px solid rgba(124,58,237,.2);border-radius:8px;color:#a78bfa;font-size:12px">
          Current rate: <strong><?= (float)($settings['referral_commission_pct'] ?? 5) ?>%</strong> per sale
        </p>
      </div>
    </div>
  </div>
</div>

<!-- ═══ NEWSLETTER ═══ -->
<div id="sp-newsletter" class="spanel">
  <div class="s2col">
    <div class="section-card">
      <h3>📬 Newsletter Subscribers</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:18px">
        <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
          <div style="font-size:28px;font-weight:800;color:#a78bfa"><?= number_format($nlCount) ?></div>
          <div style="font-size:11px;color:var(--muted);text-transform:uppercase">Active</div>
        </div>
        <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
          <div style="font-size:28px;font-weight:800;color:#ef4444"><?= number_format($nlTotal - $nlCount) ?></div>
          <div style="font-size:11px;color:var(--muted);text-transform:uppercase">Unsubscribed</div>
        </div>
        <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
          <div style="font-size:28px;font-weight:800;color:var(--muted)"><?= number_format($nlTotal) ?></div>
          <div style="font-size:11px;color:var(--muted);text-transform:uppercase">Total</div>
        </div>
      </div>
      <a href="<?= SITE_URL ?>/admin/newsletter.php" class="btn btn-primary">📋 Manage Subscribers →</a>
    </div>
    <div class="section-card">
      <h3>ℹ️ Auto-Email Triggers</h3>
      <div class="s-info-box">
        <p>✅ <strong>Welcome email</strong> — sent when someone subscribes</p>
        <p>🚀 <strong>New product alert</strong> — sent to all active subscribers when admin adds a new product</p>
        <p>🎉 <strong>Order delivery</strong> — sent when order is approved</p>
        <p>🔒 <strong>Password reset</strong> — sent when user requests reset</p>
        <p style="margin-top:10px;padding:10px;background:rgba(52,211,153,.07);border:1px solid rgba(52,211,153,.2);border-radius:8px;color:#34d399;font-size:12px">
          ✅ All auto-emails active. Configure SMTP in the Email tab.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- ═══ GATEWAYS ═══ -->
<div id="sp-gateways" class="spanel">
  <div class="s2col">
    <div class="section-card">
      <h3>💳 Payment Gateways</h3>
      <p style="color:var(--muted);font-size:14px;margin-bottom:16px">Razorpay, UPI, USDT crypto wallets — all managed on a dedicated page. Add, edit, enable or disable as many as you like.</p>
      <a href="<?= SITE_URL ?>/admin/gateways.php" class="btn btn-primary">💳 Manage Gateways →</a>
    </div>
    <div class="section-card">
      <h3>💳 Supported Gateways</h3>
      <div class="s-info-box">
        <p>💳 <strong>Razorpay</strong> — Auto-payment, instant delivery</p>
        <p>📱 <strong>UPI / Manual</strong> — Admin approves orders manually</p>
        <p>🪙 <strong>USDT</strong> — TRC20, BEP20, any network</p>
        <p>⌚ <strong>WatchPays</strong> — Watch ads to pay</p>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>