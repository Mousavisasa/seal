<?php
/**
 * وب‌سرویس دریافت بارنامه «فعال» راننده — بدون نیاز به ورود/سشن
 * GET یا POST /api/active_waybill.php?token=...
 * ورودی: token (پارامتر GET یا POST/form-data/JSON)
 * خروجی: همیشه JSON با ساختار ثابت { success, message, waybill }
 *
 * «بارنامه ی انتخاب شده» یک تریگر داخلی است (ستون users.selected_waybill_id)،
 * نه وابسته به send_status: همین که راننده روی «شروع سفر» یک بارنامه کلیک کند
 * (یعنی وارد waybill_geofence_check.php با action=start شود)، همان بارنامه به‌عنوان
 * انتخاب‌شدهٔ او ثبت می‌شود؛ با «پایان سفر» موفق هم پاک می‌شود. اگر بارنامه‌ای
 * انتخاب نشده باشد، پاسخ همچنان موفق (success: true) است ولی waybill مقدار
 * null دارد — نبودِ بارنامه انتخاب‌شده یک خطا نیست، صرفاً یک وضعیت معتبر است.
 *
 * فقط توکن معتبر و متعلق به یک حساب راننده فعال پذیرفته می‌شود (همان توکن
 * driver_waybills که از طریق وب‌سرویس ورود یا صفحه driver_waybills.php صادر می‌شود).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/jalali.php';
require_once __DIR__ . '/../helpers/tokens.php';

header('Content-Type: application/json; charset=utf-8');

/** پاسخ استاندارد JSON */
function json_response(int $status, bool $success, string $message, ?array $waybill = null, bool $includeWaybill = false): void
{
    http_response_code($status);
    $body = ['success' => $success, 'message' => $message];
    if ($includeWaybill) {
        $body['waybill'] = $waybill;
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

    $selectedWaybillId = (int)($driver['selected_waybill_id'] ?? 0);
    $w = false;

    if ($selectedWaybillId > 0) {
        $stmt = db()->prepare(
            "SELECT w.id, w.waybill_number, w.issue_date, w.distance_km, w.product_type, w.send_status,
                    w.trip_started_at,
                    ol.title AS origin_title, ol.lat AS origin_lat, ol.lon AS origin_lon,
                    dl.title AS destination_title, dl.lat AS destination_lat, dl.lon AS destination_lon,
                    opOrig.first_name AS origin_operator_first, opOrig.last_name AS origin_operator_last,
                    opDest.first_name AS dest_operator_first, opDest.last_name AS dest_operator_last,
                    sl.seal_id AS attached_seal_id, sl.service_uuid AS service_uuid, sl.characteristic_uuid AS characteristic_uuid
             FROM fuel_waybills w
             INNER JOIN locations ol ON ol.id = w.origin_location_id
             INNER JOIN locations dl ON dl.id = w.destination_location_id
             LEFT JOIN users opOrig ON opOrig.id = w.origin_operator_user_id
             LEFT JOIN users opDest ON opDest.id = w.destination_operator_user_id
             LEFT JOIN seals sl ON sl.fuel_waybill_id = w.id
             WHERE w.id = ? AND w.driver_user_id = ?
             LIMIT 1"
        );
        $stmt->execute([$selectedWaybillId, $driver['id']]);
        $w = $stmt->fetch();
    }

    if (!$w) {
        json_response(200, true, 'در حال حاضر هیچ بارنامه ی انتخاب شدهی برای شما وجود ندارد.', null, true);
    }

    $waybill = [
        'id'                   => (int)$w['id'],
        'waybill_number'       => $w['waybill_number'],
        'issue_date'           => $w['issue_date'],
        'issue_date_jalali'    => to_jalali_display($w['issue_date']),
        'distance_km'          => (float)$w['distance_km'],
        'product_type'         => $w['product_type'],
        'send_status'          => $w['send_status'],
        'trip_started_at'      => $w['trip_started_at'],
        'origin_title'         => $w['origin_title'],
        'origin_lat'           => (float)$w['origin_lat'],
        'origin_lon'           => (float)$w['origin_lon'],
        'destination_title'    => $w['destination_title'],
        'destination_lat'      => (float)$w['destination_lat'],
        'destination_lon'      => (float)$w['destination_lon'],
        'origin_operator'      => $w['origin_operator_first'] ? trim($w['origin_operator_first'] . ' ' . $w['origin_operator_last']) : null,
        'destination_operator' => $w['dest_operator_first'] ? trim($w['dest_operator_first'] . ' ' . $w['dest_operator_last']) : null,
        'seal_id'              => $w['attached_seal_id'],
        'service_uuid'         => $w['service_uuid'],
        'characteristic_uuid'  => $w['characteristic_uuid'],
    ];

    json_response(200, true, 'بارنامه ی انتخاب شده با موفقیت دریافت شد.', $waybill, true);
} catch (PDOException $e) {
    error_log('API active_waybill error: ' . $e->getMessage());
    json_response(500, false, 'خطای داخلی سرور. لطفاً بعداً تلاش کنید.');
}
