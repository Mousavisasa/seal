<?php
/**
 * توابع کمکی توکن دسترسی موقت (بدون سشن)
 * برای وب‌سرویس ورود و صفحاتی مثل driver_waybills.php که نباید کاربر
 * را وادار به ورود مجدد یا ارسال رمز عبور در URL کنند.
 */

const ACCESS_TOKEN_TTL_MINUTES = 10;

/**
 * اطمینان از وجود جدول access_tokens؛ اگر روی سرور ساخته نشده باشد
 * (مثلاً چون فقط database.sql قدیمی اجرا شده)، همین‌جا ساخته می‌شود.
 * این تابع بی‌خطر و قابل‌تکرار است (IF NOT EXISTS).
 */
function ensure_access_tokens_table(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `access_tokens` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `token` CHAR(64) NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `purpose` VARCHAR(50) NOT NULL DEFAULT 'driver_waybills',
                `expires_at` DATETIME NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_token` (`token`),
                KEY `idx_token_user` (`user_id`),
                KEY `idx_token_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (PDOException $e) {
        error_log('ensure_access_tokens_table error: ' . $e->getMessage());
    }
}

/**
 * ساخت یک توکن جدید برای کاربر مشخص و ذخیره آن در دیتابیس
 * @return string توکن ۶۴ کاراکتری (هگز)
 */
function create_access_token(int $userId, string $purpose = 'driver_waybills'): string
{
    ensure_access_tokens_table();

    $token = bin2hex(random_bytes(32)); // 64 کاراکتر هگزادسیمال

    // نکته مهم: زمان انقضا با NOW() خود MySQL محاسبه می‌شود، نه با تابع date() در PHP.
    // اگر ساعت/منطقه‌زمانی سرور PHP با MySQL یکی نباشد، محاسبه با date() می‌تواند
    // مقداری بسازد که از دید MySQL از قبل «گذشته» به‌حساب بیاید و توکن بلافاصله
    // توسط پاکسازی زیر حذف شود. استفاده از NOW() + INTERVAL این ناهماهنگی را حذف می‌کند.
    $stmt = db()->prepare(
        'INSERT INTO access_tokens (token, user_id, purpose, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    );
    $stmt->execute([$token, $userId, $purpose, ACCESS_TOKEN_TTL_MINUTES]);

    // پاکسازی فرصت‌طلبانه توکن‌های واقعاً منقضی‌شده قدیمی (بدون نیاز به کرون جاب جداگانه)
    // توکنی که همین الان ساختیم را عمداً از این پاکسازی مستثنی می‌کنیم تا در هیچ
    // شرایطی (حتی اختلاف ساعت جزئی) بلافاصله بعد از ساخت حذف نشود.
    try {
        $cleanupStmt = db()->prepare("DELETE FROM access_tokens WHERE expires_at < NOW() AND token <> ?");
        $cleanupStmt->execute([$token]);
    } catch (PDOException $e) {
        error_log('Access token cleanup error: ' . $e->getMessage());
    }

    return $token;
}

/**
 * بررسی اعتبار توکن و برگرداندن اطلاعات کاربر متعلق به آن
 * @return array|null اطلاعات کاربر (بدون رمز) یا null اگر توکن نامعتبر/منقضی باشد
 */
function validate_access_token(string $token, string $expectedPurpose = 'driver_waybills'): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    ensure_access_tokens_table();

    $stmt = db()->prepare(
        'SELECT u.* FROM access_tokens t
         INNER JOIN users u ON u.id = t.user_id
         WHERE t.token = ? AND t.purpose = ? AND t.expires_at >= NOW()
         LIMIT 1'
    );
    $stmt->execute([$token, $expectedPurpose]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    unset($user['password']);
    return $user;
}

/**
 * ساخت توکن یک‌بارمصرف مخصوص «شروع/پایان سفر» یک بارنامه مشخص.
 * عملیات (start/end) و شناسه بارنامه داخل خودِ توکن (فیلد purpose) قفل می‌شود
 * تا صفحه بررسی حصار و وب‌سرویس مربوطه فقط با همین یک توکن کار کنند و
 * نیازی به پارامتر جداگانه id/action در URL یا درخواست نباشد.
 */
function create_trip_action_token(int $userId, string $action, int $waybillId): string
{
    return create_access_token($userId, 'trip:' . $action . ':' . $waybillId);
}

/**
 * اعتبارسنجی توکن «شروع/پایان سفر» و استخراج عملیات + شناسه بارنامه از آن
 * @return array|null ['driver'=>..., 'action'=>'start'|'end', 'waybill_id'=>int] یا null
 */
function validate_trip_action_token(string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    ensure_access_tokens_table();

    $stmt = db()->prepare(
        "SELECT u.*, t.purpose FROM access_tokens t
         INNER JOIN users u ON u.id = t.user_id
         WHERE t.token = ? AND t.purpose LIKE 'trip:%' AND t.expires_at >= NOW()
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $parts = explode(':', $row['purpose'], 3);
    if (count($parts) !== 3 || !in_array($parts[1], ['start', 'end'], true) || !ctype_digit($parts[2])) {
        return null;
    }

    $driver = $row;
    unset($driver['password'], $driver['purpose']);

    return [
        'driver'     => $driver,
        'action'     => $parts[1],
        'waybill_id' => (int)$parts[2],
    ];
}

/**
 * اطمینان از وجود ستون‌های تایید حضور متصدی (origin_confirmed_at / destination_confirmed_at)
 * روی fuel_waybills؛ اگر روی سرور اضافه نشده باشند، همین‌جا اضافه می‌شوند.
 */
function ensure_operator_confirm_columns(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $cols = db()->query("SHOW COLUMNS FROM fuel_waybills LIKE 'origin_confirmed_at'")->fetch();
        if (!$cols) {
            db()->exec("ALTER TABLE fuel_waybills
                ADD COLUMN origin_confirmed_at TIMESTAMP NULL DEFAULT NULL AFTER trip_ended_at,
                ADD COLUMN destination_confirmed_at TIMESTAMP NULL DEFAULT NULL AFTER origin_confirmed_at");
        }
    } catch (PDOException $e) {
        error_log('ensure_operator_confirm_columns error: ' . $e->getMessage());
    }
}

/**
 * ساخت توکن یک‌بارمصرف مخصوص «تایید حضور متصدی» در مبدا یا مقصد یک بارنامه مشخص.
 * دقیقاً مانند create_trip_action_token، نقش (origin/destination) و شناسه بارنامه
 * داخل خودِ توکن قفل می‌شود.
 */
function create_operator_action_token(int $userId, string $role, int $waybillId): string
{
    return create_access_token($userId, 'opact:' . $role . ':' . $waybillId);
}

/**
 * اعتبارسنجی توکن «تایید حضور متصدی» و استخراج نقش + شناسه بارنامه از آن
 * @return array|null ['operator'=>..., 'role'=>'origin'|'destination', 'waybill_id'=>int] یا null
 */
function validate_operator_action_token(string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    ensure_access_tokens_table();

    $stmt = db()->prepare(
        "SELECT u.*, t.purpose FROM access_tokens t
         INNER JOIN users u ON u.id = t.user_id
         WHERE t.token = ? AND t.purpose LIKE 'opact:%' AND t.expires_at >= NOW()
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $parts = explode(':', $row['purpose'], 3);
    if (count($parts) !== 3 || !in_array($parts[1], ['origin', 'destination'], true) || !ctype_digit($parts[2])) {
        return null;
    }

    $operator = $row;
    unset($operator['password'], $operator['purpose']);

    return [
        'operator'   => $operator,
        'role'       => $parts[1],
        'waybill_id' => (int)$parts[2],
    ];
}
