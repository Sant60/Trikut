<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/customer_auth.php';

customer_logout();
header('Location: ' . app_url('index.php'));
exit;
