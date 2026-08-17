<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

echo "Token: " . TG_BOT_TOKEN . "<br>";
echo "Chat ID: " . TG_CHAT_ID . "<br>";

notifyTelegram("Test message from DevStore");
echo "Sent!";