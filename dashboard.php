<?php
/**
 * فایل سازگاری: کاربران قدیمی که به dashboard.php لینک دارند
 * را به داشبورد مخصوص نقش خودشان هدایت می‌کند.
 */
require_once __DIR__ . '/helpers/auth.php';

require_login();
header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
exit;
