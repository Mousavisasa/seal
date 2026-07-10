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
    $expiresAt = date('Y-m-d H:i:s', time() + (ACCESS_TOKEN_TTL_MINUTES * 60));

    $stmt = db()->prepare(
        'INSERT INTO access_tokens (token, user_id, purpose, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$token, $userId, $purpose, $expiresAt]);

    // پاکسازی فرصت‌طلبانه توکن‌های منقضی‌شده قدیمی (بدون نیاز به کرون جاب جداگانه)
    try {
        db()->exec("DELETE FROM access_tokens WHERE expires_at < NOW()");
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
