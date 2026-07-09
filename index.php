<?php
/**
 * نقطه ورود پروژه: هدایت بر اساس نقش کاربر یا صفحه ورود
 */
require_once __DIR__ . '/helpers/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
