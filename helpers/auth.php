<?php
/**
 * توابع احراز هویت و کنترل دسترسی (نقش میان‌افزار ساده)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

/** آیا کاربری وارد شده است؟ */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/** آیا کاربر جاری ادمین است؟ */
function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user_type'] ?? '') === 'admin';
}

/** محافظ صفحات ادمین: در صورت نبود دسترسی، هدایت به صفحه ورود */
function require_admin(): void
{
    if (!is_admin()) {
        set_flash('warning', 'برای دسترسی به این بخش ابتدا وارد شوید.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/** ورود کاربر و ثبت سشن */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['user_type']     = $user['user_type'];
    $_SESSION['full_name']     = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['national_code'] = $user['national_code'];
}

/** خروج کاربر */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
