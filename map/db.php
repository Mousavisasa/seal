<?php
/**
 * اتصال endpointهای نقشه به پایگاه داده
 * ---------------------------------------------------------
 * اگر config اصلی پروژه (config/db.php) در دسترس باشد از همان
 * اتصال متمرکز استفاده می‌شود؛ در غیر این صورت (مثلاً وقتی پوشهٔ
 * map جداگانه روی هاست آپلود شده) خودش مستقیم به دیتابیس وصل می‌شود.
 */

$mainDb = __DIR__ . '/../config/db.php';

if (is_file($mainDb)) {
    require_once $mainDb;
} else {
    // اتصال مستقل (همان مشخصات config/config.php)
    if (!defined('DB_HOST')) {
        define('DB_HOST', '127.0.0.1');
        define('DB_NAME', 'idtoir_smart_seal');
        define('DB_USER', 'idtoir_smart_seal');
        define('DB_PASS', 'MyPass@1234');
        define('DB_CHARSET', 'utf8mb4');
    }

    if (!function_exists('db')) {
        function db(): PDO
        {
            static $pdo = null;

            if ($pdo === null) {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                try {
                    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]);
                } catch (PDOException $e) {
                    error_log('DB connection error: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'خطا در ارتباط با پایگاه داده'],
                        JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            return $pdo;
        }
    }
}

// کلید API این endpointها؛ خالی یعنی احراز هویت غیرفعال است
if (!defined('API_KEY')) {
    define('API_KEY', '');
}
