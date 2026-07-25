<?php
/**
 * وب‌سرویس شروع/پایان سفر بارنامه — بدون نیاز به ورود/سشن
 * GET یا POST /api/trip_action.php?token=...
 *
 * ورودی: فقط و فقط «token» (پارامتر GET، form-data یا JSON).
 * این توکن یک‌بارمصرف و مخصوص همین عملیات است (از طریق
 * helpers/tokens.php::create_trip_action_token ساخته می‌شود) و عملیات
 * (شروع یا پایان سفر) + شناسه بارنامه از داخل خودِ توکن استخراج می‌شود؛
 * نقش/هویت راننده هم از همین توکن به دست می‌آید. هیچ پارامتر دیگری
 * پذیرفته یا استفاده نمی‌شود.
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
    $resolved = validate_trip_action_token($token);
    if (!$resolved) {
        json_response(401, false, 'توکن نامعتبر است یا منقضی شده است.');
    }

    $driver     = $resolved['driver'];
    $action     = $resolved['action']; // 'start' یا 'end'
    $waybillId  = $resolved['waybill_id'];

    if ($driver['user_type'] !== 'driver') {
        json_response(403, false, 'این توکن متعلق به یک حساب راننده نیست.');
    }
    if ((int)($driver['is_active'] ?? 1) === 0) {
        json_response(403, false, 'حساب کاربری شما غیرفعال شده است.');
    }

    $stmt = db()->prepare('SELECT * FROM fuel_waybills WHERE id = ? LIMIT 1');
    $stmt->execute([$waybillId]);
    $waybill = $stmt->fetch();

    if (!$waybill) {
        json_response(404, false, 'بارنامه مورد نظر یافت نشد.');
    }
    if ((int)$waybill['driver_user_id'] !== (int)$driver['id']) {
        json_response(403, false, 'این بارنامه به شما تخصیص داده نشده است.');
    }

    if ($action === 'start') {
        if ($waybill['send_status'] !== 'ثبت شده') {
            json_response(409, false, 'این بارنامه قبلاً شروع شده یا در وضعیت دیگری قرار دارد.');
        }
        update_waybill_status_with_log(
            (int)$waybillId,
            'بارگیری شده',
            'api/trip_action.php: سرویس شروع سفر',
            (int)$driver['id'],
            ['trip_started_at = NOW()']
        );
        json_response(200, true, 'سفر با موفقیت شروع شد. منتظر تایید متصدی مبدا برای ارسال بارنامه باشید.');
    }

    // action === 'end'
    if ($waybill['send_status'] !== 'ارسال شده') {
        json_response(409, false, 'این بارنامه هنوز شروع نشده یا قبلاً تحویل داده شده است.');
    }
    update_waybill_status_with_log(
        (int)$waybillId,
        'پایان پیمایش',
        'api/trip_action.php: سرویس پایان سفر',
        (int)$driver['id'],
        ['trip_ended_at = NOW()']
    );

    json_response(200, true, 'سفر با موفقیت به پایان رسید. منتظر تایید متصدی مقصد برای تحویل بارنامه باشید.');
} catch (PDOException $e) {
    error_log('API trip_action error: ' . $e->getMessage());
    json_response(500, false, 'خطای داخلی سرور. لطفاً بعداً تلاش کنید.');
}
