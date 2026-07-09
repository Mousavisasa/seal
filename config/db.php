<?php
/**
 * اتصال متمرکز به پایگاه داده با PDO
 */

require_once __DIR__ . '/config.php';

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
            // خطای واقعی فقط در لاگ سرور ثبت می‌شود
            error_log('DB connection error: ' . $e->getMessage());
            http_response_code(500);
            exit('خطا در ارتباط با پایگاه داده. لطفاً بعداً تلاش کنید.');
        }
    }

    return $pdo;
}
