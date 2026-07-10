<?php
/**
 * وب‌سرویس ورود (REST API) - بدون نیاز به هدر خاص یا احراز هویت
 * POST /api/login.php
 * ورودی: username و password (form-data یا JSON، هدر اختیاری است)
 * خروجی: همیشه JSON با ساختار ثابت { success, message, user?, token?, token_expires_in_minutes? }
 *
 * توکن صادرشده حداکثر ۱۰ دقیقه معتبر است و می‌تواند برای فراخوانی صفحاتی مثل
 * driver_waybills.php?token=... استفاده شود، بدون نیاز به ارسال مجدد رمز عبور.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/tokens.php';

header('Content-Type: application/json; charset=utf-8');

/** پاسخ استاندارد JSON */
function json_response(int $status, bool $success, string $message, ?array $user = null, ?string $token = null): void
{
    http_response_code($status);
    $body = ['success' => $success, 'message' => $message];
    if ($user !== null) {
        $body['user'] = $user;
    }
    if ($token !== null) {
        $body['token'] = $token;
        $body['token_expires_in_minutes'] = ACCESS_TOKEN_TTL_MINUTES;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// فقط متد POST مجاز است
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, false, 'متد درخواست مجاز نیست. فقط POST پذیرفته می‌شود.');
}

// خواندن ورودی: بدون وابستگی به هدر Content-Type
// اول از form-data ($_POST) می‌خوانیم؛ اگر خالی بود، بدنه خام را به‌عنوان JSON امتحان می‌کنیم
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$username = normalize_digits((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
    json_response(422, false, 'نام کاربری و رمز عبور الزامی است.');
}

try {
    $stmt = db()->prepare('SELECT * FROM users WHERE national_code = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        json_response(401, false, 'نام کاربری یا رمز عبور نادرست است.');
    }

    if ((int)($user['is_active'] ?? 1) === 0) {
        json_response(403, false, 'حساب کاربری شما غیرفعال شده است.');
    }

    // ساخت توکن موقت (حداکثر ۱۰ دقیقه اعتبار) برای استفاده در صفحاتی مثل driver_waybills.php
    // این بخش جدا از احراز هویت اصلی مدیریت می‌شود: اگر جدول access_tokens هنوز
    // روی سرور ساخته نشده باشد، ورود کاربر همچنان موفق است، فقط توکن در پاسخ نمی‌آید.
    $token = null;
    try {
        $token = create_access_token((int)$user['id'], 'driver_waybills');
    } catch (PDOException $e) {
        error_log('Access token creation failed (is the access_tokens table created? run database.sql): ' . $e->getMessage());
    }

    // حذف هش رمز از خروجی
    unset($user['password']);
    $user['user_type_label'] = user_type_label($user['user_type']);

    json_response(200, true, 'ورود با موفقیت انجام شد.', $user, $token);
} catch (PDOException $e) {
    error_log('API login error: ' . $e->getMessage());
    json_response(500, false, 'خطای داخلی سرور. لطفاً بعداً تلاش کنید.');
}
