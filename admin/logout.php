<?php
/**
 * logout.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
logoutAdmin();
header('Location: ' . BASE_URL . '/admin/login');
exit;
