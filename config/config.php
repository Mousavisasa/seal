<?php
/**
 * تنظیمات کلی برنامه
 */

declare(strict_types=1);

// نمایش‌ندادن خطاها به کاربر (خطاها فقط در لاگ)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// تنظیمات امن سشن
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// اطلاعات اتصال به پایگاه داده (پیش‌فرض XAMPP)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'idtoir_smart_seal');
define('DB_USER', 'idtoir_smart_seal');
define('DB_PASS', 'MyPass@1234');
define('DB_CHARSET', 'utf8mb4');

// مسیر پایه پروژه (نام پوشه در htdocs)
define('BASE_URL', '/seal');

// عنوان سامانه
define('APP_NAME', 'سامانه مدیریت پلمپ هوشمند');

// نقش‌های کاربری: کلید انگلیسی برای دیتابیس، مقدار فارسی برای نمایش
const USER_TYPES = [
    'admin'    => 'ادمین',
    'driver'   => 'راننده',
    'operator' => 'متصدی',
    'region'   => 'منطقه',
];

// انواع فرآورده بارنامه سوخت
const PRODUCT_TYPES = ['بنزین', 'نفتگاز', 'سوپر', 'نفت'];

// وضعیت‌های ارسال بارنامه
const SEND_STATUSES = ['ثبت شده', 'ارسال شده', 'تحویل شده', 'لغو شده'];
