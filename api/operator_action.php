<?php
/**
 * وب‌سرویس «تایید حضور متصدی» در مبدا یا مقصد یک بارنامه — بدون نیاز به ورود/سشن
 * GET یا POST /api/operator_action.php?token=...
 *
 * دقیقاً مشابه api/trip_action.php: ورودی فقط و فقط «token» است (پارامتر GET،
 * form-data یا JSON)؛ توکن یک‌بارمصرف و مخصوص همین عملیات است (از طریق
 * helpers/tokens.php::create_operator_action_token ساخته می‌شود) و نقش
 * (origin/destination) + شناسه بارنامه از داخل خودِ توکن استخراج می‌شود؛
 * نقش/هویت متصدی هم از همین توکن به دست می‌آید. هیچ پارامتر دیگری پذیرفته
 * یا استفاده نمی‌شود.
 *
 * تایید متصدی به‌معنای ثبت زمان حضور او در محل (ستون origin_confirmed_at یا
 * destination_confirmed_at روی fuel_waybills) است؛ برخلاف شروع/پایان سفر
 * راننده، وضعیت (send_status) بارنامه را تغییر نمی‌دهد.
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

    $resolved = validate_operator_action_token($token);
    if (!$resolved) {
        json_response(401, false, 'توکن نامعتبر است یا منقضی شده است.');
    }

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
} catch (PDOException $e) {
    error_log('API operator_action error: ' . $e->getMessage());
    json_response(500, false, 'خطای داخلی سرور. لطفاً بعداً تلاش کنید.');
}