<?php
/**
 * وب‌سرویس یکپارچهٔ «ثبت نهایی» بعد از بررسی حصار جغرافیایی متصدی
 * GET یا POST /api/operator_confirm.php?token=...
 *
 * ورودی: فقط و فقط «token» (پارامتر GET، form-data یا JSON) — همان توکنی که
 * در operator_waybills_public.php ساخته و بدون تغییر از
 * waybill_operator_geofence_check.php به waybill_operator_loading.php و از
 * آن‌جا به این‌جا رسیده است.
 *
 * این وب‌سرویس نوع توکن را تشخیص می‌دهد و عملیات متناظر را انجام می‌دهد:
 *  ۱) توکن «تایید حضور» (opact:origin|destination:<id>): مانند
 *     api/operator_action.php — فقط ستون origin_confirmed_at یا
 *     destination_confirmed_at را پر می‌کند و وضعیت بارنامه را تغییر نمی‌دهد.
 *  ۲) توکن «تایید بارگیری/تحویل» (opapprove:loading|delivery:<id>): مانند
 *     api/operator_approve_loading.php و api/operator_approve_delivery.php —
 *     وضعیت بارنامه را تغییر می‌دهد.
 *
 * خروجی: همیشه JSON با ساختار { success, message }
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/tokens.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(int $status, bool $success, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    json_response(405, false, 'متد درخواست مجاز نیست. فقط GET یا POST پذیرفته می‌شود.');
}

// خواندن توکن: از GET، یا از form-data، یا از بدنه JSON — تنها ورودی مجاز
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
if ($token === '') {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded) && !empty($decoded['token'])) {
        $token = trim((string)$decoded['token']);
    }
}

if ($token === '') {
    json_response(422, false, 'ارسال توکن الزامی است.');
}

try {
    ensure_operator_confirm_columns();

    // ابتدا توکن «تایید حضور» را امتحان می‌کنیم
    $resolved = validate_operator_action_token($token);
    if ($resolved) {
        handle_presence_confirm($resolved);
    }

    // در غیر این صورت، توکن «تایید بارگیری/تحویل» را امتحان می‌کنیم
    $resolved = validate_operator_approve_token($token);
    if ($resolved) {
        handle_approve_confirm($resolved);
    }

    json_response(401, false, 'توکن نامعتبر است یا منقضی شده است.');
} catch (PDOException $e) {
    error_log('API operator_confirm error: ' . $e->getMessage());
    json_response(500, false, 'خطای داخلی سرور. لطفاً بعداً تلاش کنید.');
}

/**
 * همان منطق api/operator_action.php: ثبت زمان حضور متصدی در مبدا/مقصد
 * (بدون تغییر وضعیت بارنامه). خروجی را مستقیماً چاپ و خارج می‌شود.
 */
function handle_presence_confirm(array $resolved): void
{
    $operator  = $resolved['operator'];
    $role      = $resolved['role']; // 'origin' یا 'destination'
    $waybillId = $resolved['waybill_id'];

    if ($operator['user_type'] !== 'operator') {
        json_response(403, false, 'این توکن متعلق به یک حساب متصدی نیست.');
    }
    if ((int)($operator['is_active'] ?? 1) === 0) {
        json_response(403, false, 'حساب کاربری شما غیرفعال شده است.');
    }

    $stmt = db()->prepare('SELECT * FROM fuel_waybills WHERE id = ? LIMIT 1');
    $stmt->execute([$waybillId]);
    $waybill = $stmt->fetch();

    if (!$waybill) {
        json_response(404, false, 'بارنامه مورد نظر یافت نشد.');
    }

    $ownerColumn   = $role === 'origin' ? 'origin_operator_user_id' : 'destination_operator_user_id';
    $confirmColumn = $role === 'origin' ? 'origin_confirmed_at' : 'destination_confirmed_at';

    if ((int)$waybill[$ownerColumn] !== (int)$operator['id']) {
        json_response(403, false, 'این بارنامه به شما تخصیص داده نشده است.');
    }

    if (!empty($waybill[$confirmColumn])) {
        json_response(409, false, 'حضور شما برای این بارنامه قبلاً تایید شده است.');
    }

    $upd = db()->prepare("UPDATE fuel_waybills SET {$confirmColumn} = NOW() WHERE id = ?");
    $upd->execute([$waybillId]);

    $label = $role === 'origin' ? 'مبدا' : 'مقصد';
    json_response(200, true, 'حضور شما در ' . $label . ' با موفقیت تایید شد.');
}

/**
 * همان منطق api/operator_approve_loading.php و api/operator_approve_delivery.php:
 * تغییر وضعیت بارنامه پس از تایید بارگیری (متصدی مبدا) یا تایید تحویل
 * (متصدی مقصد). خروجی را مستقیماً چاپ و خارج می‌شود.
 */
function handle_approve_confirm(array $resolved): void
{
    $operator    = $resolved['operator'];
    $approveType = $resolved['approve_type']; // 'loading' یا 'delivery'
    $waybillId   = $resolved['waybill_id'];

    if ($operator['user_type'] !== 'operator') {
        json_response(403, false, 'این توکن متعلق به یک حساب متصدی نیست.');
    }
    if ((int)($operator['is_active'] ?? 1) === 0) {
        json_response(403, false, 'حساب کاربری شما غیرفعال شده است.');
    }

    $stmt = db()->prepare('SELECT * FROM fuel_waybills WHERE id = ? LIMIT 1');
    $stmt->execute([$waybillId]);
    $waybill = $stmt->fetch();

    if (!$waybill) {
        json_response(404, false, 'بارنامه مورد نظر یافت نشد.');
    }

    if ($approveType === 'loading') {
        if ((int)$waybill['origin_operator_user_id'] !== (int)$operator['id']) {
            json_response(403, false, 'شما اجازه تایید این بارنامه را ندارید. شما متصدی مبدا این بارنامه نیستید.');
        }
        if ($waybill['send_status'] !== 'بارگیری شده') {
            json_response(409, false, 'این بارنامه در وضعیت «بارگیری شده» نیست. وضعیت فعلی: ' . $waybill['send_status']);
        }

        update_waybill_status_with_log(
            (int)$waybillId,
            'ارسال شده',
            'api/operator_confirm.php: تایید بارگیری متصدی مبدا',
            (int)$operator['id'],
            ['origin_operator_approved_at = NOW()']
        );

        json_response(200, true, 'بارنامه با موفقیت برای ارسال تایید شد.');
    }

    // delivery
    if ((int)$waybill['destination_operator_user_id'] !== (int)$operator['id']) {
        json_response(403, false, 'شما اجازه تایید این بارنامه را ندارید. شما متصدی مقصد این بارنامه نیستید.');
    }
    if ($waybill['send_status'] !== 'پایان پیمایش') {
        json_response(409, false, 'این بارنامه در وضعیت «پایان پیمایش» نیست. وضعیت فعلی: ' . $waybill['send_status']);
    }

    update_waybill_status_with_log(
        (int)$waybillId,
        'تحویل شده',
        'api/operator_confirm.php: تایید تحویل متصدی مقصد',
        (int)$operator['id'],
        ['destination_operator_delivered_at = NOW()']
    );

    json_response(200, true, 'بارنامه با موفقیت به عنوان تحویل‌شده ثبت شد.');
}
