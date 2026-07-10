<?php
/**
 * وب‌سرویس فهرست بارنامه‌های «ثبت شده» راننده — بدون نیاز به ورود/سشن
 * GET یا POST /api/waybills.php?token=...
 * ورودی: token (پارامتر GET یا POST/form-data/JSON)
 * خروجی: همیشه JSON با ساختار ثابت { success, message, waybills? }
 *
 * فقط توکن معتبر و متعلق به یک حساب راننده فعال پذیرفته می‌شود.
 * توکن از طریق وب‌سرویس ورود (api/login.php) صادر می‌شود و حداکثر
 * ACCESS_TOKEN_TTL_MINUTES دقیقه (پیش‌فرض ۱۰ دقیقه) اعتبار دارد.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/jalali.php';
require_once __DIR__ . '/../helpers/tokens.php';

header('Content-Type: application/json; charset=utf-8');

/** پاسخ استاندارد JSON */
function json_response(int $status, bool $success, string $message, ?array $waybills = null): void
{
    http_response_code($status);
    $body = ['success' => $success, 'message' => $message];
    if ($waybills !== null) {
        $body['count'] = count($waybills);
        $body['waybills'] = $waybills;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// فقط GET و POST مجاز است
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    json_response(405, false, 'متد درخواست مجاز نیست. فقط GET یا POST پذیرفته می‌شود.');
}

// خواندن توکن: از GET، یا از form-data، یا از بدنه JSON
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
    $driver = validate_access_token($token, 'driver_waybills');

    if (!$driver) {
        json_response(401, false, 'توکن نامعتبر است یا منقضی شده است.');
    }
    if ($driver['user_type'] !== 'driver') {
        json_response(403, false, 'این توکن متعلق به یک حساب راننده نیست.');
    }
    if ((int)($driver['is_active'] ?? 1) === 0) {
        json_response(403, false, 'حساب کاربری شما غیرفعال شده است.');
    }

    $stmt = db()->prepare(
        "SELECT w.id, w.waybill_number, w.issue_date, w.distance_km, w.product_type, w.send_status,
                ol.title AS origin_title, dl.title AS destination_title,
                opOrig.first_name AS origin_operator_first, opOrig.last_name AS origin_operator_last,
                opDest.first_name AS dest_operator_first, opDest.last_name AS dest_operator_last,
                sl.seal_id AS attached_seal_id
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         LEFT JOIN users opOrig ON opOrig.id = w.origin_operator_user_id
         LEFT JOIN users opDest ON opDest.id = w.destination_operator_user_id
         LEFT JOIN seals sl ON sl.fuel_waybill_id = w.id
         WHERE w.driver_user_id = ? AND w.send_status = 'ثبت شده'
         ORDER BY w.id DESC"
    );
    $stmt->execute([$driver['id']]);
    $rows = $stmt->fetchAll();

    $waybills = array_map(static function (array $w): array {
        return [
            'id'                => (int)$w['id'],
            'waybill_number'    => $w['waybill_number'],
            'issue_date'        => $w['issue_date'],
            'issue_date_jalali' => to_jalali_display($w['issue_date']),
            'distance_km'       => (float)$w['distance_km'],
            'product_type'      => $w['product_type'],
            'send_status'       => $w['send_status'],
            'origin_title'      => $w['origin_title'],
            'destination_title' => $w['destination_title'],
            'origin_operator'   => $w['origin_operator_first'] ? trim($w['origin_operator_first'] . ' ' . $w['origin_operator_last']) : null,
            'destination_operator' => $w['dest_operator_first'] ? trim($w['dest_operator_first'] . ' ' . $w['dest_operator_last']) : null,
            'seal_id'           => $w['attached_seal_id'],
        ];
    }, $rows);

    json_response(200, true, 'فهرست بارنامه‌ها با موفقیت دریافت شد.', $waybills);
} catch (PDOException $e) {
    error_log('API waybills error: ' . $e->getMessage());
    json_response(500, false, 'خطای داخلی سرور. لطفاً بعداً تلاش کنید.');
}
