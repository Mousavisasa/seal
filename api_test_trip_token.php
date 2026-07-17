<?php
/**
 * ابزار داخلی و مخصوص پنل ادمین (نه یک وب‌سرویس عمومی) برای صفحه تست/مستندات (api_test.php)
 * صرفاً برای «تست زنده» وب‌سرویس شروع/پایان سفر، روی یک بارنامه واقعی، یک توکن
 * یک‌بارمصرف واقعی می‌سازد (همان helpers/tokens.php::create_trip_action_token
 * که در driver_waybills.php هم استفاده می‌شود) تا بلافاصله با api/trip_action.php آزمایش شود.
 *
 * توجه: چون این عملیات واقعاً وضعیت بارنامهٔ انتخاب‌شده را تغییر می‌دهد،
 * فقط از طریق سشن ادمین (require_admin) در دسترس است، نه به‌صورت وب‌سرویس عمومی.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/tokens.php';

require_admin();

header('Content-Type: application/json; charset=utf-8');

function out(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    out(false, 'فقط POST مجاز است.');
}

$waybillId = (int)($_POST['waybill_id'] ?? 0);
$action    = (string)($_POST['action'] ?? '');

if ($waybillId <= 0 || !in_array($action, ['start', 'end'], true)) {
    out(false, 'ورودی نامعتبر است.');
}

try {
    $stmt = db()->prepare('SELECT * FROM fuel_waybills WHERE id = ? LIMIT 1');
    $stmt->execute([$waybillId]);
    $waybill = $stmt->fetch();

    if (!$waybill || !$waybill['driver_user_id']) {
        out(false, 'بارنامه یا راننده مرتبط با آن یافت نشد.');
    }
    if ($action === 'start' && $waybill['send_status'] !== 'ثبت شده') {
        out(false, 'این بارنامه دیگر در وضعیت «ثبت شده» نیست (شاید توسط تست قبلی تغییر کرده است).');
    }
    if ($action === 'end' && $waybill['send_status'] !== 'ارسال شده') {
        out(false, 'این بارنامه در وضعیت «ارسال شده» نیست (شاید توسط تست قبلی تغییر کرده است).');
    }

    $token = create_trip_action_token((int)$waybill['driver_user_id'], $action, $waybillId);
    out(true, 'توکن آزمایشی با موفقیت ساخته شد.', ['token' => $token]);
} catch (PDOException $e) {
    error_log('api_test_trip_token error: ' . $e->getMessage());
    out(false, 'خطای داخلی سرور.');
}
