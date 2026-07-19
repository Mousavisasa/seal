<?php
/**
 * ابزار داخلی و مخصوص پنل ادمین (نه یک وب‌سرویس عمومی) برای صفحه تست/مستندات (api_test.php)
 * صرفاً برای «تست زنده» وب‌سرویس تایید حضور متصدی، روی یک بارنامه واقعی، یک توکن
 * یک‌بارمصرف واقعی می‌سازد (همان helpers/tokens.php::create_operator_action_token
 * که در operator_waybills_public.php هم استفاده می‌شود) تا بلافاصله با
 * api/operator_action.php آزمایش شود.
 *
 * توجه: چون این عملیات واقعاً وضعیت تایید بارنامه را تغییر می‌دهد، فقط از طریق
 * سشن ادمین (require_admin) در دسترس است، نه به‌صورت وب‌سرویس عمومی.
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
$role      = (string)($_POST['role'] ?? '');

if ($waybillId <= 0 || !in_array($role, ['origin', 'destination'], true)) {
    out(false, 'ورودی نامعتبر است.');
}

try {
    ensure_operator_confirm_columns();

    $ownerColumn = $role === 'origin' ? 'origin_operator_user_id' : 'destination_operator_user_id';
    $confirmColumn = $role === 'origin' ? 'origin_confirmed_at' : 'destination_confirmed_at';

    $stmt = db()->prepare("SELECT * FROM fuel_waybills WHERE id = ? LIMIT 1");
    $stmt->execute([$waybillId]);
    $waybill = $stmt->fetch();

    if (!$waybill || !$waybill[$ownerColumn]) {
        out(false, 'بارنامه یا متصدی مرتبط با آن یافت نشد.');
    }
    if (!empty($waybill[$confirmColumn])) {
        out(false, 'حضور متصدی برای این بارنامه قبلاً تایید شده است (شاید توسط تست قبلی).');
    }

    $token = create_operator_action_token((int)$waybill[$ownerColumn], $role, $waybillId);
    out(true, 'توکن آزمایشی با موفقیت ساخته شد.', ['token' => $token]);
} catch (PDOException $e) {
    error_log('api_test_operator_action_token error: ' . $e->getMessage());
    out(false, 'خطای داخلی سرور.');
}