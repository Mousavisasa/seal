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

/** آیا کاربر جاری نقش «منطقه» دارد؟ */
function is_region(): bool
{
    return is_logged_in() && ($_SESSION['user_type'] ?? '') === 'region';
}

/** آیا کاربر جاری نقش «متصدی» دارد؟ */
function is_operator(): bool
{
    return is_logged_in() && ($_SESSION['user_type'] ?? '') === 'operator';
}

/** آیا کاربر جاری نقش «راننده» دارد؟ */
function is_driver(): bool
{
    return is_logged_in() && ($_SESSION['user_type'] ?? '') === 'driver';
}

/** آیا کاربر جاری به ماژول بارنامه سوخت (فهرست/ایجاد/ویرایش/حذف) دسترسی دارد؟ (ادمین یا منطقه) */
function can_access_waybill_module(): bool
{
    return is_admin() || is_region();
}

/** آیا کاربر جاری می‌تواند متصدی را به بارنامه تخصیص دهد؟ (ادمین یا منطقه) */
function can_assign_operator(): bool
{
    return is_admin() || is_region();
}

/** آیا کاربر جاری می‌تواند راننده را به بارنامه تخصیص دهد؟ (ادمین یا متصدی) */
function can_assign_driver(): bool
{
    return is_admin() || is_operator();
}

/** مسیر مناسب پس از ورود بر اساس نقش کاربر */
function redirect_path_for_role(string $userType): string
{
    switch ($userType) {
        case 'driver':
            return 'waybills/my_trips.php';
        case 'operator':
            return 'waybills/my_waybills.php';
        case 'region':
            return 'waybills/list.php';
        default:
            return 'dashboard.php';
    }
}

/** محافظ عمومی: فقط کاربر واردشده */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'برای دسترسی به این بخش ابتدا وارد شوید.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
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

/** محافظ صفحات ماژول بارنامه سوخت: فقط ادمین یا منطقه */
function require_waybill_access(): void
{
    require_login();
    if (!can_access_waybill_module()) {
        set_flash('danger', 'شما دسترسی لازم برای این بخش را ندارید.');
        header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? '')); 
        exit;
    }
}

/** محافظ صفحات تخصیص متصدی: فقط ادمین یا منطقه */
function require_assign_operator_access(): void
{
    require_login();
    if (!can_assign_operator()) {
        set_flash('danger', 'شما دسترسی لازم برای این بخش را ندارید.');
        header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
        exit;
    }
}

/** محافظ صفحات تخصیص راننده: فقط ادمین یا متصدی */
function require_assign_driver_access(): void
{
    require_login();
    if (!can_assign_driver()) {
        set_flash('danger', 'شما دسترسی لازم برای این بخش را ندارید.');
        header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
        exit;
    }
}

/** محافظ صفحه راننده: فقط کاربر با نقش راننده (یا ادمین برای بازبینی) */
function require_driver_access(): void
{
    require_login();
    if (!is_driver() && !is_admin()) {
        set_flash('danger', 'شما دسترسی لازم برای این بخش را ندارید.');
        header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
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
