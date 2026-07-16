<?php
/**
 * بررسی حصار جغرافیایی
 * =========================================================
 *  GET /seal/map/geofence/check.php?id=12&lat=35.6892&lon=51.3890
 *
 *  سرور با id مکان را از دیتابیس می‌خواند، هندسهٔ آن را می‌گیرد،
 *  و با الگوریتم پرتوافکنی بررسی می‌کند که نقطه داخل محدوده هست یا نه.
 *  هیچ GeoJSON‌ای از بیرون گرفته نمی‌شود.
 *
 *  پاسخ:
 *  { "success":true, "id":12, "name":"...", "inside":true,
 *    "point":{"lat":35.6892,"lon":51.3890} }
 */

require_once __DIR__ . '/../helpers.php';

only('GET');
require_key();

$id  = filter_input(INPUT_GET, 'id',  FILTER_VALIDATE_INT);
$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);

if ($id === false || $id === null) {
    json_error('پارامتر id الزامی و باید عدد باشد');
}
if ($lat === false || $lat === null || $lon === false || $lon === null) {
    json_error('پارامترهای lat و lon الزامی و باید عدد باشند');
}
if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    json_error('مختصات خارج از بازهٔ مجاز است');
}

$stmt = db()->prepare('SELECT id, region_id, title, geojson, lat, lon FROM locations WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$place = $stmt->fetch();

if (!$place) {
    json_error('مکانی با این شناسه یافت نشد', 404);
}

$geom = validate_polygon($place['geojson']);
if ($geom === null) {
    json_error('برای این مکان محدودهٔ جغرافیایی (GeoJSON) ثبت نشده یا معتبر نیست', 422);
}

$inside = point_in_polygon((float)$lat, (float)$lon, $geom);

json_ok([
    'id'        => (int)$place['id'],
    'region_id' => (int)$place['region_id'],
    'name'      => $place['title'],
    'inside' => $inside,
    'point'  => ['lat' => (float)$lat, 'lon' => (float)$lon],
]);
