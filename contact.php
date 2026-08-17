<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(SITE_URL.'/contact.php');
    $name    = clean($_POST['name']    ?? '');
    $email   = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone   = clean($_POST['phone']   ?? '');
    $subject = clean($_POST['subject'] ?? '');
    $message = clean($_POST['message'] ?? '');
    $website = trim($_POST['website'] ?? ''); // honeypot — real users never see/fill this field

    $rateKey = 'contact:' . clientIp();

    if ($website !== '') {
        // Silently "succeed" for bots that filled the honeypot — don't tip them off.
        flash('success','Message sent! We will reply within 24 hours.');
        redirect(SITE_URL.'/contact.php');
    }

    if (isGenericRateLimited($pdo, $rateKey, 3, 30)) {
        flash('error','Too many messages sent. Please wait a while before sending another.');
        redirect(SITE_URL.'/contact.php');
    }

    if ($name && $message) {
        recordGenericAttempt($pdo, $rateKey);
        $pdo->prepare("INSERT INTO messages (name,email,phone,subject,message) VALUES (?,?,?,?,?)")->execute([$name,$email,$phone,$subject,$message]);
        notifyTelegram("📬 New Contact\nName: $name\nEmail: $email\nSubject: $subject\nMessage: $message");
        flash('success','Message sent! We will reply within 24 hours.');
    } else {
        flash('error','Name and message are required.');
    }
    redirect(SITE_URL.'/contact.php');
}
$pageTitle = 'Contact Us';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container" style="padding:40px 0;max-width:800px">
  <div class="section-header reveal">
    <h2>Contact Us</h2>
    <p>We're here to help — typically reply within a few hours</p>
  </div>
  <div class="contact-grid">
    <div class="reveal">
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:28px">
        <form method="POST">
          <?= csrf_field() ?>
          <div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true">
            <label for="website">Leave this field empty</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Your Name *</label>
            <input class="form-control" type="text" name="name" required>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input class="form-control" type="email" name="email">
          </div>
          <div class="form-group">
            <label>Phone / WhatsApp</label>
            <input class="form-control" type="text" name="phone">
          </div>
          <div class="form-group">
            <label>Subject</label>
            <input class="form-control" type="text" name="subject" placeholder="e.g. Download issue, Demo request">
          </div>
          <div class="form-group">
            <label>Message *</label>
            <textarea class="form-control" name="message" rows="4" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Send Message</button>
        </form>
      </div>
    </div>
    <div class="reveal" style="display:flex;flex-direction:column;gap:14px">
      <?php foreach ([
        ['💬','WhatsApp','Fastest response — reply within minutes','https://wa.me/'.WA_NUMBER,'Chat on WhatsApp'],
        ['📧','Email',MAIL_FROM,'mailto:'.MAIL_FROM,'Send Email'],
        ['⚡','Instant Delivery','All orders auto-delivered after payment verification','#',''],
      ] as $c): ?>
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;display:flex;gap:14px;align-items:flex-start">
        <span style="font-size:28px"><?= $c[0] ?></span>
        <div>
          <strong><?= $c[1] ?></strong>
          <p style="color:var(--muted);font-size:13px;margin:4px 0"><?= $c[2] ?></p>
          <?php if ($c[4]): ?><a href="<?= $c[3] ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-top:8px"><?= $c[4] ?></a><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
