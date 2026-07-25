<?php
/**
 * توابع کمکی تنظیمات کلی برنامه
 * تنظیمات به‌صورت کلید/مقدار در جدول app_settings ذخیره می‌شوند.
 */

require_once __DIR__ . '/../config/db.php';

/** کلیدهای شناخته‌شدهٔ تنظیمات (مقدار پیش‌فرض BLE برای فرم تعریف پلمپ) */
const SETTING_DEFAULT_SERVICE_UUID          = 'default_service_uuid';
const SETTING_DEFAULT_CHARACTERISTIC_UUID   = 'default_characteristic_uuid';
const SETTING_GEOFENCE_CONTROL_ENABLED      = 'geofence_control_enabled';
const SETTING_GEOFENCE_CONTROL_OPERATOR     = 'geofence_control_operator_enabled';

/** دریافت مقدار یک تنظیم؛ در صورت نبود، مقدار پیش‌فرض داده‌شده بازگردانده می‌شود */
function get_setting(string $key, string $default = ''): string
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = ($row !== false && $row['setting_value'] !== null) ? $row['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log('Get setting error: ' . $e->getMessage());
        $cache[$key] = $default;
    }

    return $cache[$key];
}

/** دریافت چند تنظیم به‌صورت یک‌جا (کلید => مقدار) */
function get_settings(array $keysWithDefaults): array
{
    $result = [];
    foreach ($keysWithDefaults as $key => $default) {
        $result[$key] = get_setting($key, (string)$default);
    }
    return $result;
}

/** ذخیره یک تنظیم (در صورت وجود، مقدار آن به‌روزرسانی می‌شود) */
function set_setting(string $key, string $value): bool
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        return $stmt->execute([$key, $value]);
    } catch (PDOException $e) {
        error_log('Set setting error: ' . $e->getMessage());
        return false;
    }
}
