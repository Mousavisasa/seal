<?php
/**
 * ابزار داخلی و مخصوص پنل ادمین (نه یک وب‌سرویس عمومی) برای صفحه تست/مستندات (api_test.php)
 * صرفاً برای «تست زنده» وب‌سرویس‌های فقط-خواندنیِ راننده (مثل api/active_waybill.php)
 * یک توکن استاندارد driver_waybills واقعی برای یک راننده واقعی می‌سازد — دقیقاً همان
 * توکنی که بعد از ورود موفق در driver_waybills.php یا وب‌سرویس ورود صادر می‌شود.
 * چون این توکن فقط برای خواندن استفاده می‌شود، هیچ داده‌ای را تغییر نمی‌دهد.
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

$driverId = (int)($_POST['driver_id'] ?? 0);
if ($driverId <= 0) {
    out(false, 'ورودی نامعتبر است.');
}

try {
    $stmt = db()->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'driver' LIMIT 1");
    $stmt->execute([$driverId]);
    if (!$stmt->fetch()) {
        out(false, 'راننده مورد نظر یافت نشد.');
    }

    $token = create_access_token($driverId, 'driver_waybills');
    out(true, 'توکن آزمایشی با موفقیت ساخته شد.', ['token' => $token]);
} catch (PDOException $e) {
    error_log('api_test_driver_token error: ' . $e->getMessage());
    out(false, 'خطای داخلی سرور.');
}
