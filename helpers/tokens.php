<?php
/**
 * توابع کمکی توکن دسترسی موقت (بدون سشن)
 * برای وب‌سرویس ورود و صفحاتی مثل driver_waybills.php که نباید کاربر
 * را وادار به ورود مجدد یا ارسال رمز عبور در URL کنند.
 */

const ACCESS_TOKEN_TTL_MINUTES = 10;

/**
 * ساخت یک توکن جدید برای کاربر مشخص و ذخیره آن در دیتابیس
 * @return string توکن ۶۴ کاراکتری (هگز)
 */
function create_access_token(int $userId, string $purpose = 'driver_waybills'): string
{
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
