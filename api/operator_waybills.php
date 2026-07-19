<?php
/**
 * وب‌سرویس دریافت بارنامه‌های ایستگاه متصدی — بدون نیاز به ورود/سشن
 * GET یا POST /api/operator_waybills.php?token=...
 * ورودی: token (پارامتر GET یا POST/form-data/JSON)
 * خروجی: همیشه JSON با ساختار ثابت { success, message, waybills }
 *
 * معادل api/active_waybill.php برای متصدی: چون متصدی برخلاف راننده یک
 * «بارنامه انتخاب‌شده» ندارد، این وب‌سرویس فهرست همه بارنامه‌هایی را برمی‌گرداند
 * که متصدی به‌عنوان متصدی مبدا یا مقصد آن‌ها تخصیص یافته (همان دادهٔ صفحهٔ
 * waybills/operator_waybills.php)، به‌همراه وضعیت تایید حضور او در هر بارنامه.
 *
 * فقط توکن معتبر و متعلق به یک حساب متصدی فعال پذیرفته می‌شود (همان توکن
 * operator_waybills که از طریق وب‌سرویس ورود یا صفحه operator_waybills_public.php صادر می‌شود).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/jalali.php';
require_once __DIR__ . '/../helpers/tokens.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(int $status, bool $success, string $message, array $waybills = []): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'waybills' => $waybills], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    json_response(405, false, 'متد درخواست مجاز نیست. فقط GET یا POST پذیرفته می‌شود.');
}

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

    $stmt = db()->prepare(
        "SELECT w.id, w.waybill_number, w.issue_date, w.distance_km, w.product_type, w.send_status,
                w.origin_operator_user_id, w.destination_operator_user_id,
                w.origin_confirmed_at, w.destination_confirmed_at,
                ol.title AS origin_title, ol.lat AS origin_lat, ol.lon AS origin_lon,
                dl.title AS destination_title, dl.lat AS destination_lat, dl.lon AS destination_lon,
                drU.first_name AS driver_first, drU.last_name AS driver_last,
                sl.seal_id AS attached_seal_id
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         LEFT JOIN users drU ON drU.id = w.driver_user_id
         LEFT JOIN seals sl ON sl.fuel_waybill_id = w.id
         WHERE w.origin_operator_user_id = ? OR w.destination_operator_user_id = ?
         ORDER BY w.id DESC"
    );
    $stmt->execute([$operator['id'], $operator['id']]);
    $rows = $stmt->fetchAll();

    $waybills = [];
    foreach ($rows as $w) {
        $role = (int)$w['origin_operator_user_id'] === (int)$operator['id'] ? 'origin' : 'destination';
        $waybills[] = [
            'id'                => (int)$w['id'],
            'waybill_number'    => $w['waybill_number'],
            'issue_date'        => $w['issue_date'],
            'issue_date_jalali' => to_jalali_display($w['issue_date']),
            'distance_km'       => (float)$w['distance_km'],
            'product_type'      => $w['product_type'],
            'send_status'       => $w['send_status'],
            'my_role'           => $role, // 'origin' یا 'destination'
            'origin_title'      => $w['origin_title'],
            'origin_lat'        => (float)$w['origin_lat'],
            'origin_lon'        => (float)$w['origin_lon'],
            'destination_title' => $w['destination_title'],
            'destination_lat'   => (float)$w['destination_lat'],
            'destination_lon'   => (float)$w['destination_lon'],
            'driver'            => $w['driver_first'] ? trim($w['driver_first'] . ' ' . $w['driver_last']) : null,
            'seal_id'           => $w['attached_seal_id'],
            'confirmed_at'      => $role === 'origin' ? $w['origin_confirmed_at'] : $w['destination_confirmed_at'],
        ];
    }

    json_response(200, true, 'بارنامه‌های ایستگاه با موفقیت دریافت شد.', $waybills);
} catch (PDOException $e) {
    error_log('API operator_waybills error: ' . $e->getMessage());
    json_response(500, false, 'خطای داخلی سرور. لطفاً بعداً تلاش کنید.');
}