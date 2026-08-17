<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';
unset($_SESSION['user_id'], $_SESSION['user_name']);
session_destroy();
redirect(SITE_URL . '/');
