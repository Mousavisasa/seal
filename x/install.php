<?php
/**
 * اسکریپت نصب اختیاری (یک بار اجرا شود):
 * رمز کاربر ادمین پیش‌فرض را با password_hash استاندارد بازتنظیم می‌کند.
 * بعد از اجرا این فایل را حذف کنید.
 */
require_once __DIR__ . '/config/db.php';

$hash = password_hash('Admin@123', PASSWORD_DEFAULT);
$stmt = db()->prepare("UPDATE users SET password = ? WHERE national_code = '1111111111' AND user_type = 'admin'");
$stmt->execute([$hash]);

echo 'رمز ادمین پیش‌فرض بازتنظیم شد (Admin@123). این فایل را حذف کنید.';
