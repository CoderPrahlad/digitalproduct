<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';
unset($_SESSION['admin_id'],$_SESSION['admin_username']);
redirect(SITE_URL.'/admin/login.php');
