<?php
/**
 * فهرست مکان‌ها از جدول locations
 * =========================================================
 *  GET /seal/map/api/locations.php          → همهٔ مکان‌ها
 *  GET /seal/map/api/locations.php?id=12    → یک مکان مشخص
 *
 *  پاسخ:
 *  { "success":true, "locations":[
 *      { "id":1, "location_code":"...", "region_id":3, "title":"...",
 *        "lat":35.6, "lon":51.3, "geojson":{...} | null }
 *  ] }
 */

require_once __DIR__ . '/../helpers.php';

only('GET');
require_key();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id !== null && $id !== false) {
    $stmt = db()->prepare(
        'SELECT id, location_code, region_id, title, lat, lon, geojson
         FROM locations WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        json_error('مکانی با این شناسه یافت نشد', 404);
    }
} else {
    $rows = db()->query(
        'SELECT id, location_code, region_id, title, lat, lon, geojson
         FROM locations ORDER BY title'
    )->fetchAll();
}

$locations = array_map(static function (array $row): array {
    $geom = $row['geojson'] !== null ? json_decode($row['geojson'], true) : null;
    return [
        'id'            => (int)$row['id'],
        'location_code' => $row['location_code'],
        'region_id'     => (int)$row['region_id'],
        'title'         => $row['title'],
        'lat'           => (float)$row['lat'],
        'lon'           => (float)$row['lon'],
        'geojson'       => is_array($geom) ? $geom : null,
    ];
}, $rows);

json_ok(['locations' => $locations]);
