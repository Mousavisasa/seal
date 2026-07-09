<?php
/**
 * نقطه ورود پروژه: هدایت به داشبورد یا صفحه ورود
 */
require_once __DIR__ . '/helpers/auth.php';

if (is_admin()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
