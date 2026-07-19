<?php
/**
 * ابزار داخلی و مخصوص پنل ادمین (نه یک وب‌سرویس عمومی) برای صفحه تست/مستندات (api_test.php)
 * صرفاً برای «تست زنده» وب‌سرویس‌های فقط-خواندنیِ متصدی (مثل api/operator_waybills.php)
 * یک توکن استاندارد operator_waybills واقعی برای یک متصدی واقعی می‌سازد — دقیقاً همان
 * توکنی که بعد از ورود موفق در operator_waybills_public.php یا وب‌سرویس ورود صادر می‌شود.
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

$operatorId = (int)($_POST['operator_id'] ?? 0);
if ($operatorId <= 0) {
    out(false, 'ورودی نامعتبر است.');
}

try {
    $stmt = db()->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'operator' LIMIT 1");
    $stmt->execute([$operatorId]);
    if (!$stmt->fetch()) {
        out(false, 'متصدی مورد نظر یافت نشد.');
    }

    $token = create_access_token($operatorId, 'operator_waybills');
    out(true, 'توکن آزمایشی با موفقیت ساخته شد.', ['token' => $token]);
} catch (PDOException $e) {
    error_log('api_test_operator_token error: ' . $e->getMessage());
    out(false, 'خطای داخلی سرور.');
}