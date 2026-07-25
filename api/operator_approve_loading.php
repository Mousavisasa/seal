<?php
/**
 * وب‌سرویس تایید بارگذاری متصدی مبدا
 * POST /api/operator_approve_loading.php
 *
 * ورودی (JSON یا form-data):
 * - token: توکن متصدی (دریافت‌شده از login.php)
 * - waybill_id: شناسه بارنامه
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, false, 'متد درخواست مجاز نیست. فقط POST پذیرفته می‌شود.');
}

// خواندن ورودی
$input = null;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
} else {
    $input = $_POST;
}

$token = trim((string)($input['token'] ?? ''));
$waybillId = (int)($input['waybill_id'] ?? 0);

if ($token === '' || $waybillId <= 0) {
    json_response(422, false, 'ارسال token و waybill_id الزامی است.');
}

try {
    // بررسی توکن متصدی
    $operator = validate_access_token($token, 'operator_waybills');
    if (!$operator) {
        json_response(401, false, 'توکن نامعتبر است یا منقضی شده است.');
    }

    if ($operator['user_type'] !== 'operator') {
        json_response(403, false, 'این توکن متعلق به یک حساب متصدی نیست.');
    }

    if ((int)($operator['is_active'] ?? 1) === 0) {
        json_response(403, false, 'حساب کاربری شما غیرفعال شده است.');
    }

    // دریافت بارنامه
    $stmt = db()->prepare('SELECT * FROM fuel_waybills WHERE id = ? LIMIT 1');
    $stmt->execute([$waybillId]);
    $waybill = $stmt->fetch();

    if (!$waybill) {
        json_response(404, false, 'بارنامه مورد نظر یافت نشد.');
    }

    // بررسی اینکه این متصدی متصدی مبدا این بارنامه است
    if ((int)$waybill['origin_operator_user_id'] !== (int)$operator['id']) {
        json_response(403, false, 'شما اجازه تایید این بارنامه را ندارید. شما متصدی مبدا این بارنامه نیستید.');
    }

    // بررسی وضعیت
    if ($waybill['send_status'] !== 'بارگیری شده') {
        json_response(409, false, 'این بارنامه در وضعیت «بارگیری شده» نیست. وضعیت فعلی: ' . $waybill['send_status']);
    }

    // تغییر وضعیت
    $upd = db()->prepare("UPDATE fuel_waybills SET send_status = 'ارسال شده', origin_operator_approved_at = NOW() WHERE id = ?");
    $upd->execute([$waybillId]);

    json_response(200, true, 'بارنامه با موفقیت برای ارسال تایید شد.');
} catch (PDOException $e) {
    error_log('API operator_approve_loading error: ' . $e->getMessage());
    json_response(500, false, 'خطای داخلی سرور. لطفاً بعداً تلاش کنید.');
}
