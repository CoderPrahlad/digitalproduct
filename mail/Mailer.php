<?php
class Mailer {
    public static function send($to, $subject, $htmlBody) {
        $composerAutoload = ROOT_PATH . '/vendor/autoload.php';
        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USER;
                $mail->Password   = MAIL_PASS;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_PORT;
                $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->send();
                return true;
            } catch (Exception $e) {
                error_log('PHPMailer Error: ' . $mail->ErrorInfo);
                return false;
            }
        }
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $sent = @mail($to, $subject, $htmlBody, $headers);
        if (!$sent) {
            error_log("Mailer: PHP mail() failed sending to $to — Install PHPMailer: composer require phpmailer/phpmailer");
        }
        return $sent;
    }

    public static function sendDelivery($toEmail, $toName, $productTitle, $licenseKey, $downloadUrl) {
        $subject = "=?UTF-8?B?" . base64_encode("🎉 Your Order Delivered — " . $productTitle . " | " . SITE_NAME) . "?=";
        $html = self::deliveryTemplate($toName, $productTitle, $licenseKey, $downloadUrl);
        return self::send($toEmail, $subject, $html);
    }

    public static function sendPasswordReset($toEmail, $toName, $resetUrl) {
        $subject = "Reset Your Password | " . SITE_NAME;
        $html = self::resetTemplate($toName, $resetUrl);
        return self::send($toEmail, $subject, $html);
    }

    public static function sendNewsletterWelcome($toEmail, $toName) {
        $subject = '🎉 Welcome to ' . SITE_NAME . ' Newsletter!';
        $html = self::newsletterWelcomeTemplate($toName);
        return self::send($toEmail, $subject, $html);
    }

    public static function sendNewProductAlert($toEmail, $toName, $productTitle, $productUrl, $productDesc, $price) {
        $subject = '🚀 New Product: ' . $productTitle . ' | ' . SITE_NAME;
        $html = self::newProductTemplate($toName, $productTitle, $productUrl, $productDesc, $price);
        return self::send($toEmail, $subject, $html);
    }

    private static function newsletterWelcomeTemplate($name) {
        $siteName = SITE_NAME;
        $siteUrl  = defined('SITE_URL') ? SITE_URL : '';
        $unsubUrl = $siteUrl . '/unsubscribe.php';
        $year     = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Welcome | {$siteName}</title></head>
<body style="margin:0;padding:0;background:#07070f;font-family:'Segoe UI',Arial,sans-serif">
<div style="max-width:580px;margin:30px auto;padding:0 12px">
  <div style="background:#0f0f1c;border-radius:18px;overflow:hidden;border:1px solid #1a1a2e">
    <div style="background:linear-gradient(135deg,#7c3aed 0%,#2563eb 100%);padding:40px 30px;text-align:center">
      <div style="font-size:44px;margin-bottom:12px">🎉</div>
      <h1 style="color:#fff;margin:0;font-size:26px;font-weight:800">Welcome to {$siteName}!</h1>
      <p style="color:rgba(255,255,255,.8);margin:8px 0 0;font-size:14px">You're now subscribed to our newsletter</p>
    </div>
    <div style="padding:34px 30px">
      <p style="color:#e2e8f0;font-size:16px;margin:0 0 16px">Hello <strong style="color:#a78bfa">{$name}</strong>,</p>
      <p style="color:#94a3b8;margin:0 0 24px;line-height:1.7">
        Thanks for subscribing! You'll be the <strong style="color:#e2e8f0">first to know</strong> about new products, updates, and exclusive deals from <strong style="color:#c4b5fd">{$siteName}</strong>.
      </p>
      <a href="{$siteUrl}" style="display:block;background:linear-gradient(135deg,#7c3aed,#2563eb);color:#fff;text-align:center;padding:16px;border-radius:12px;font-weight:700;font-size:16px;text-decoration:none;margin:0 0 24px">🛒 Browse Products</a>
      <p style="color:#64748b;font-size:12px;margin:0;text-align:center">
        Don't want emails? <a href="{$unsubUrl}" style="color:#7c3aed;text-decoration:none">Unsubscribe here</a>
      </p>
    </div>
    <div style="background:#07070f;padding:20px 30px;text-align:center;border-top:1px solid #1a1a2e">
      <p style="color:#334155;font-size:12px;margin:0">© {$year} {$siteName} — All Rights Reserved</p>
    </div>
  </div>
</div>
</body></html>
HTML;
    }

    private static function newProductTemplate($name, $productTitle, $productUrl, $productDesc, $price) {
        $siteName = SITE_NAME;
        $siteUrl  = defined('SITE_URL') ? SITE_URL : '';
        $unsubUrl = $siteUrl . '/unsubscribe.php';
        $year     = date('Y');
        $priceHtml = $price ? "<strong style=\"color:#a78bfa\">₹" . number_format((float)$price) . "</strong>" : '';
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Product | {$siteName}</title></head>
<body style="margin:0;padding:0;background:#07070f;font-family:'Segoe UI',Arial,sans-serif">
<div style="max-width:580px;margin:30px auto;padding:0 12px">
  <div style="background:#0f0f1c;border-radius:18px;overflow:hidden;border:1px solid #1a1a2e">
    <div style="background:linear-gradient(135deg,#7c3aed 0%,#2563eb 100%);padding:40px 30px;text-align:center">
      <div style="font-size:44px;margin-bottom:12px">🚀</div>
      <h1 style="color:#fff;margin:0;font-size:26px;font-weight:800">New Product Alert!</h1>
      <p style="color:rgba(255,255,255,.8);margin:8px 0 0;font-size:14px">Just dropped on {$siteName}</p>
    </div>
    <div style="padding:34px 30px">
      <p style="color:#e2e8f0;font-size:16px;margin:0 0 16px">Hello <strong style="color:#a78bfa">{$name}</strong>,</p>
      <div style="background:#0a0a18;border:1px solid #2d1f6e;border-radius:12px;padding:20px 22px;margin-bottom:20px">
        <p style="color:#7c3aed;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;margin:0 0 8px">✨ New Product</p>
        <p style="color:#e2e8f0;font-size:18px;font-weight:800;margin:0 0 8px">{$productTitle}</p>
        {$priceHtml}
        <p style="color:#94a3b8;font-size:13px;margin:10px 0 0;line-height:1.6">{$productDesc}</p>
      </div>
      <a href="{$productUrl}" style="display:block;background:linear-gradient(135deg,#7c3aed 0%,#2563eb 100%);color:#fff;text-align:center;padding:18px;border-radius:12px;font-weight:700;font-size:17px;text-decoration:none;margin-bottom:24px;box-shadow:0 4px 20px rgba(124,58,237,.4)">
        👀 View Product
      </a>
      <p style="color:#64748b;font-size:12px;margin:0;text-align:center">
        Don't want emails? <a href="{$unsubUrl}" style="color:#7c3aed;text-decoration:none">Unsubscribe here</a>
      </p>
    </div>
    <div style="background:#07070f;padding:20px 30px;text-align:center;border-top:1px solid #1a1a2e">
      <p style="color:#334155;font-size:12px;margin:0">© {$year} {$siteName} — All Rights Reserved</p>
    </div>
  </div>
</div>
</body></html>
HTML;
    }

    private static function resetTemplate($name, $resetUrl) {
        $siteName = SITE_NAME;
        $siteUrl  = defined('SITE_URL') ? SITE_URL : '';
        $waNum    = defined('WA_NUMBER') ? WA_NUMBER : '';
        $waLink   = $waNum ? "https://wa.me/{$waNum}" : '#';
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password | {$siteName}</title>
</head>
<body style="margin:0;padding:0;background:#07070f;font-family:'Segoe UI',Arial,sans-serif">
<div style="max-width:580px;margin:30px auto;padding:0 12px">
  <div style="background:#0f0f1c;border-radius:18px;overflow:hidden;border:1px solid #1a1a2e">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#7c3aed 0%,#2563eb 100%);padding:38px 30px;text-align:center">
      <div style="font-size:40px;margin-bottom:10px">🔒</div>
      <h1 style="color:#fff;margin:0;font-size:24px;font-weight:800;letter-spacing:-0.5px">Reset Your Password</h1>
      <p style="color:rgba(255,255,255,.75);margin:8px 0 0;font-size:14px">We received a request to reset your password</p>
    </div>
    <!-- Body -->
    <div style="padding:34px 30px">
      <p style="color:#e2e8f0;font-size:16px;margin:0 0 16px">Hello <strong style="color:#a78bfa">{$name}</strong>,</p>
      <p style="color:#94a3b8;margin:0 0 24px;line-height:1.7">Click the button below to set a new password for your <strong style="color:#c4b5fd">{$siteName}</strong> account.</p>
      <a href="{$resetUrl}" style="display:block;background:linear-gradient(135deg,#7c3aed,#2563eb);color:#fff;text-align:center;padding:16px;border-radius:12px;font-weight:700;font-size:16px;text-decoration:none;margin:0 0 24px">🔑 Reset Password</a>
      <div style="background:#0a0a18;border:1px solid #1e1e35;border-radius:10px;padding:16px;margin-bottom:24px">
        <p style="color:#64748b;font-size:13px;margin:0;line-height:1.7">⚠️ This link is valid for <strong style="color:#94a3b8">1 hour</strong> and can only be used once.<br>If you didn't request this, you can safely ignore this email.</p>
      </div>
      <p style="color:#64748b;font-size:13px;margin:0">Need help? <a href="{$waLink}" style="color:#a78bfa;text-decoration:none">WhatsApp us anytime</a></p>
    </div>
    <!-- Footer -->
    <div style="background:#07070f;padding:20px 30px;text-align:center;border-top:1px solid #1a1a2e">
      <p style="color:#334155;font-size:12px;margin:0">© {$siteName} — All Rights Reserved</p>
    </div>
  </div>
</div>
</body></html>
HTML;
    }

    private static function deliveryTemplate($name, $product, $license, $dlUrl) {
        $siteName  = defined('SITE_NAME') ? SITE_NAME : 'DevStore';
        $siteUrl   = defined('SITE_URL')  ? SITE_URL  : '';
        $waNum     = defined('WA_NUMBER') ? WA_NUMBER : '';
        $waLink    = $waNum ? "https://wa.me/{$waNum}" : '#';
        $ytUrl     = 'https://www.youtube.com/@CoderPrahalad';
        $year      = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Delivered | {$siteName}</title>
</head>
<body style="margin:0;padding:0;background:#07070f;font-family:'Segoe UI',Arial,sans-serif">
<div style="max-width:580px;margin:30px auto;padding:0 12px">
  <div style="background:#0f0f1c;border-radius:18px;overflow:hidden;border:1px solid #1a1a2e;box-shadow:0 8px 40px rgba(0,0,0,.5)">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#7c3aed 0%,#2563eb 100%);padding:40px 30px;text-align:center">
      <div style="font-size:44px;margin-bottom:12px">🎉</div>
      <h1 style="color:#fff;margin:0;font-size:26px;font-weight:800;letter-spacing:-0.5px">Order Delivered!</h1>
      <p style="color:rgba(255,255,255,.8);margin:8px 0 0;font-size:14px">Your purchase is ready to download</p>
    </div>

    <!-- Body -->
    <div style="padding:34px 30px">

      <p style="color:#e2e8f0;font-size:16px;margin:0 0 8px">Hello <strong style="color:#a78bfa">{$name}</strong>,</p>
      <p style="color:#94a3b8;margin:0 0 26px;line-height:1.7">Thank you for purchasing <strong style="color:#e2e8f0">{$product}</strong> from <strong style="color:#c4b5fd">{$siteName}</strong>. Your files are ready for instant download.</p>

      <!-- License Key box -->
      <div style="background:#0a0a18;border:1px solid #2d1f6e;border-radius:12px;padding:20px 22px;margin-bottom:20px">
        <p style="color:#7c3aed;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;margin:0 0 10px">🔑 License Key</p>
        <p style="color:#c4b5fd;font-size:20px;font-weight:800;margin:0;letter-spacing:2px;word-break:break-all;font-family:'Courier New',monospace">{$license}</p>
      </div>

      <!-- Download Button -->
      <a href="{$dlUrl}" style="display:block;background:linear-gradient(135deg,#7c3aed 0%,#2563eb 100%);color:#fff;text-align:center;padding:18px;border-radius:12px;font-weight:700;font-size:17px;text-decoration:none;margin-bottom:24px;box-shadow:0 4px 20px rgba(124,58,237,.4)">
        ⬇ Download Now
      </a>

      <!-- Warning note -->
      <div style="background:#0a0a18;border:1px solid #1e1e35;border-left:3px solid #fbbf24;border-radius:0 10px 10px 0;padding:14px 16px;margin-bottom:28px">
        <p style="color:#94a3b8;font-size:13px;margin:0;line-height:1.7">
          ⚠️ This download link is <strong style="color:#e2e8f0">unique to your order</strong>. Do not share it.<br>
          Link valid for <strong style="color:#e2e8f0">72 hours</strong>. Contact support if it expires.
        </p>
      </div>

      <!-- YouTube Subscribe Section -->
      <div style="background:linear-gradient(135deg,rgba(124,58,237,.12),rgba(37,99,235,.08));border:1px solid rgba(124,58,237,.25);border-radius:14px;padding:22px;text-align:center;margin-bottom:24px">
        <div style="font-size:28px;margin-bottom:8px">📺</div>
        <p style="color:#e2e8f0;font-size:15px;font-weight:700;margin:0 0 6px">Get More Free Tutorials & Projects!</p>
        <p style="color:#94a3b8;font-size:13px;margin:0 0 16px;line-height:1.6">
          Subscribe to <strong style="color:#c4b5fd">Coder Prahalad</strong> on YouTube for PHP, Full Stack tutorials, and exclusive source code projects.
        </p>
        <a href="{$ytUrl}" target="_blank"
           style="display:inline-block;background:#ff0000;color:#fff;padding:11px 24px;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;letter-spacing:.3px">
          ▶ Subscribe on YouTube
        </a>
      </div>

      <!-- Support -->
      <p style="color:#64748b;font-size:13px;margin:0;text-align:center">
        Need help? <a href="{$waLink}" style="color:#a78bfa;text-decoration:none;font-weight:600">WhatsApp us anytime</a>
      </p>
    </div>

    <!-- Footer -->
    <div style="background:#07070f;padding:22px 30px;text-align:center;border-top:1px solid #1a1a2e">
      <p style="color:#334155;font-size:12px;margin:0 0 6px">© {$year} <strong style="color:#475569">{$siteName}</strong> — All Rights Reserved</p>
      <p style="color:#1e293b;font-size:11px;margin:0">
        <a href="{$siteUrl}" style="color:#334155;text-decoration:none">{$siteUrl}</a>
      </p>
    </div>
  </div>
</div>
</body></html>
HTML;
    }
}