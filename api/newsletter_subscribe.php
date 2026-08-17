<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
require_once dirname(__DIR__) . '/mail/Mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$name  = clean($_POST['name'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'Valid email address required.']);
    exit;
}

// Rate limit: 3 subscribe attempts per IP per 30 min
$ipKey = 'newsletter_' . clientIp();
if (isGenericRateLimited($pdo, $ipKey, 3, 30)) {
    echo json_encode(['ok' => false, 'msg' => 'Too many attempts. Please try again later.']);
    exit;
}
recordGenericAttempt($pdo, $ipKey);

try {
    // Check if already subscribed
    $chk = $pdo->prepare("SELECT id, status FROM newsletter_subscribers WHERE email=?");
    $chk->execute([$email]);
    $existing = $chk->fetch();

    if ($existing) {
        if ($existing['status'] === 'unsubscribed') {
            // Re-subscribe
            $pdo->prepare("UPDATE newsletter_subscribers SET status='active', name=?, subscribed_at=NOW() WHERE id=?")
                ->execute([$name ?: null, $existing['id']]);
            Mailer::sendNewsletterWelcome($email, $name ?: 'Subscriber');
            echo json_encode(['ok' => true, 'msg' => 'You have been re-subscribed! 🎉']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'This email is already subscribed.']);
        }
        exit;
    }

    $token = bin2hex(random_bytes(24));
    $pdo->prepare("INSERT INTO newsletter_subscribers (email, name, token) VALUES (?, ?, ?)")
        ->execute([$email, $name ?: null, $token]);

    Mailer::sendNewsletterWelcome($email, $name ?: 'Subscriber');

    echo json_encode(['ok' => true, 'msg' => 'Subscribed successfully! Welcome 🎉']);
} catch (Exception $e) {
    error_log('Newsletter subscribe error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Something went wrong. Please try again.']);
}