<?php
// admin/logout.php - Admin Logout
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

unset($_SESSION[ADMIN_SESSION_KEY]);
session_regenerate_id(true);

set_flash('success', 'You have been logged out securely.');
header("Location: " . BASE_URL . "/admin/login.php");
exit;
