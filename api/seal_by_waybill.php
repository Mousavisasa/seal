<?php
/**
 * وب‌سرویس دریافت اطلاعات پلمپ بر اساس شماره بارنامه — بدون نیاز به ورود/سشن
 * GET یا POST /api/seal_by_waybill.php?token=...&waybill_number=...
 * ورودی: token و waybill_number (پارامتر GET یا POST/form-data/JSON)
 * خروجی: همیشه JSON با ساختار ثابت { success, message, seal? }
 * فیلدهای seal: id, seal_id, seal_password, service_uuid, characteristic_uuid
 *
 * توکن معتبر و متعلق به یک حساب فعالِ راننده یا متصدی پذیرفته می‌شود:
 * - راننده: همان توکن driver_waybills که از طریق وب‌سرویس ورود یا صفحه
 *   driver_waybills.php صادر می‌شود.
 * - متصدی: همان توکن operator_waybills که از طریق وب‌سرویس ورود یا صفحه
 *   operator_waybills_public.php صادر می‌شود؛ در این حالت بارنامه باید یکی
 *   از بارنامه‌های تخصیص‌یافته به همان متصدی (به‌عنوان متصدی مبدا یا مقصد) باشد.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/tokens.php';

header('Content-Type: application/json; charset=utf-8');

/** پاسخ استاندارد JSON */
function json_response(int $status, bool $success, string $message, ?array $seal = null, bool $includeSeal = false): void
{
    http_response_code($status);
    $body = ['success' => $success, 'message' => $message];
    if ($includeSeal) {
        $body['seal'] = $seal;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// فقط GET و POST مجاز است
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    json_response(405, false, 'متد درخواست مجاز نیست. فقط GET یا POST پذیرفته می‌شود.');
}

// خواندن ورودی‌ها: از GET، یا از form-data، یا از بدنه JSON
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$waybillNumber = trim((string)($_GET['waybill_number'] ?? $_POST['waybill_number'] ?? ''));

if ($token === '' || $waybillNumber === '') {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded)) {
        if ($token === '' && !empty($decoded['token'])) {
            $token = trim((string)$decoded['token']);
        }
        if ($waybillNumber === '' && !empty($decoded['waybill_number'])) {
            $waybillNumber = trim((string)$decoded['waybill_number']);
        }
    }
}

if ($token === '') {
    json_response(422, false, 'ارسال توکن الزامی است.');
}
if ($waybillNumber === '') {
    json_response(422, false, 'ارسال شماره بارنامه الزامی است.');
}

try {
    // ابتدا توکن راننده امتحان می‌شود؛ اگر معتبر نبود، به‌عنوان توکن متصدی بررسی می‌شود
    $driver = validate_access_token($token, 'driver_waybills');
    $operator = $driver ? null : validate_access_token($token, 'operator_waybills');

    if (!$driver && !$operator) {
        json_response(401, false, 'توکن نامعتبر است یا منقضی شده است.');
    }

    if ($driver) {
        if ($driver['user_type'] !== 'driver') {
            json_response(403, false, 'این توکن متعلق به یک حساب راننده نیست.');
        }
        if ((int)($driver['is_active'] ?? 1) === 0) {
            json_response(403, false, 'حساب کاربری شما غیرفعال شده است.');
        }

        $stmt = db()->prepare(
            "SELECT sl.id, sl.seal_id, sl.seal_password, sl.service_uuid, sl.characteristic_uuid
             FROM fuel_waybills w
             INNER JOIN seals sl ON sl.fuel_waybill_id = w.id
             WHERE w.waybill_number = ?
             LIMIT 1"
        );
        $stmt->execute([$waybillNumber]);
    } else {
        if ($operator['user_type'] !== 'operator') {
            json_response(403, false, 'این توکن متعلق به یک حساب متصدی نیست.');
        }
        if ((int)($operator['is_active'] ?? 1) === 0) {
            json_response(403, false, 'حساب کاربری شما غیرفعال شده است.');
        }

        // متصدی فقط می‌تواند پلمپ بارنامه‌ای را ببیند که به‌عنوان متصدی مبدا یا مقصد آن تخصیص یافته
        $stmt = db()->prepare(
            "SELECT sl.id, sl.seal_id, sl.seal_password, sl.service_uuid, sl.characteristic_uuid
             FROM fuel_waybills w
             INNER JOIN seals sl ON sl.fuel_waybill_id = w.id
             WHERE w.id = ?
               AND (w.origin_operator_user_id = ? OR w.destination_operator_user_id = ?)
             LIMIT 1"
        );
        $stmt->execute([$waybillNumber, $operator['id'], $operator['id']]);
    }

    $s = $stmt->fetch();

    if (!$s) {
        json_response(404, false, 'پلمپی برای این بارنامه یافت نشد.', null, true);
    }

    $seal = [
        'id'                   => (int)$s['id'],
        'seal_id'              => $s['seal_id'],
        'seal_password'        => $s['seal_password'],
        'service_uuid'         => $s['service_uuid'],
        'characteristic_uuid'  => $s['characteristic_uuid'],
    ];

    json_response(200, true, 'اطلاعات پلمپ با موفقیت دریافت شد.', $seal, true);
} catch (PDOException $e) {
    error_log('API seal_by_waybill error: ' . $e->getMessage());
    json_response(500, false, 'خطای داخلی سرور. لطفاً بعداً تلاش کنید.');
}
