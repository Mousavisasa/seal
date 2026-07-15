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

// وضعیت‌های پلمپ در چرخه انبارداری
const SEAL_STATUSES = ['در انبار مرکزی', 'در انبار منطقه', 'الصاق شده', 'باطل شده', 'مفقود شده'];

// رنگ اختصاصی هر نوع فرآورده (برای نمودارها و نشانگرها)
const PRODUCT_COLORS = [
    'نفتگاز' => '#FAE78A',
    'بنزین'  => '#EEAABD',
    'نفت'    => '#DDF0FC',
    'سوپر'   => '#B2DEC0',
];

// کلید API نقشه (map.ir) — منبع واحد؛ در سمت کلاینت از طریق window.MAPIR_API_KEY در دسترس است
define('MAPIR_API_KEY', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImQ2ZDdlYjQ4MTFhNjU4ZTliMjk0YTdlZGVmMzVmZjQxOWU3NjBkOWM5MzQxMzM4YmJjODk1MzVkOTFkYzM1NGQzZWQ1YjFmMDZiODY3ZWQzIn0.eyJhdWQiOiI0Mjc1MyIsImp0aSI6ImQ2ZDdlYjQ4MTFhNjU4ZTliMjk0YTdlZGVmMzVmZjQxOWU3NjBkOWM5MzQxMzM4YmJjODk1MzVkOTFkYzM1NGQzZWQ1YjFmMDZiODY3ZWQzIiwiaWF0IjoxNzg0MDE5MjIxLCJuYmYiOjE3ODQwMTkyMjEsImV4cCI6MTc4NjYxMTIyMSwic3ViIjoiIiwic2NvcGVzIjpbImJhc2ljIl19.Pq3H-95Me7bYUojFvjh0G1I4c6XW6JgvwddnzqCAa2rJ5VOa-iuwbtbhRBh49F3Gffx3bX1Oomjz2DEPEql3cwrp33gJn7QNAR9tTQHnly1CmvzxIkuAPzHlO7K-h3l1jY1h_D-ll6jkNHIo-iTgMV6yYQOE5IYrLJD0HQsa8nKT0FDX-MZJ59QSPaxrhvOe-g2tzkQa-gLJmTTcB93CqMIGyQ8F8wRd18v5NIY17Rdm4UWGPrT3KlnELD-JYKlZVbNc1x4A25PuZK3CWTE7QxQF6jzYXBprD1sb6FbZF8cgevq157xFJHzz6u6LBZ1Ja6dwQcERICiloNLKKJZ6Dw');
