<?php
/**
 * توابع کمکی عمومی: خروجی امن، پیام فلش، اعتبارسنجی کد ملی، CSRF
 */

require_once __DIR__ . '/../config/config.php';

/** خروجی امن HTML */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** ثبت پیام فلش (success | danger | warning | info) */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** دریافت و پاک‌کردن پیام فلش */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** نمایش پیام فلش به‌صورت آلرت بوت‌استرپ */
function render_flash(): string
{
    $flash = get_flash();
    if (!$flash) {
        return '';
    }
    $icons = [
        'success' => 'solar:check-circle-bold',
        'danger'  => 'solar:close-circle-bold',
        'warning' => 'solar:danger-triangle-bold',
        'info'    => 'solar:info-circle-bold',
    ];
    $icon = $icons[$flash['type']] ?? $icons['info'];
    return '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert">'
        . '<span class="iconify fs-5" data-icon="' . $icon . '"></span>'
        . '<div>' . e($flash['message']) . '</div>'
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>'
        . '</div>';
}

/**
 * تبدیل ارقام فارسی/عربی به انگلیسی
 */
function normalize_digits(string $value): string
{
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($ar, $en, str_replace($fa, $en, trim($value)));
}

/**
 * اعتبارسنجی کد ملی ایران (طول ۱۰ رقم + رقم کنترل)
 */
function is_valid_national_code(string $code): bool
{
    $code = normalize_digits($code);

    if (!preg_match('/^\d{10}$/', $code)) {
        return false;
    }
    if (preg_match('/^(\d)\1{9}$/', $code)) {
        return false; // همه ارقام یکسان
    }

    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int)$code[$i] * (10 - $i);
    }
    $remainder = $sum % 11;
    $check = (int)$code[9];

    return ($remainder < 2 && $check === $remainder)
        || ($remainder >= 2 && $check === 11 - $remainder);
}

/** ساخت/دریافت توکن CSRF */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** فیلد مخفی CSRF برای فرم‌ها */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** بررسی توکن CSRF در درخواست POST */
function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** برچسب فارسی نقش کاربر */
function user_type_label(string $type): string
{
    return USER_TYPES[$type] ?? $type;
}

/** بررسی معتبربودن تاریخ به فرمت Y-m-d */
function is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/** بررسی عدد مثبت (برای مسافت و مقادیر مشابه) */
function is_positive_number($value): bool
{
    return is_numeric($value) && (float)$value > 0;
}

/** کلاس CSS نشان رنگی نوع فرآورده */
function product_badge_class(string $productType): string
{
    return 'product-badge-' . $productType;
}

/** رنگ اختصاصی نوع فرآورده (برای استفاده در نمودارها) */
function product_color(string $productType): string
{
    return PRODUCT_COLORS[$productType] ?? '#c7ccd1';
}
