<?php
/**
 * توابع کمکی مشترک همهٔ endpointها
 * ---------------------------------------------------------
 *  - هدرهای پاسخ و CORS
 *  - پاسخ JSON استاندارد (موفق / خطا)
 *  - احراز هویت با کلید (اختیاری)
 *  - الگوریتم پرتوافکنی (Ray casting) برای point-in-polygon
 */

require_once __DIR__ . '/db.php';

/* ---------- هدرها و CORS ---------- */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// پاسخ به preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ---------- پاسخ‌های استاندارد ---------- */
function json_ok(array $data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(array_merge(['success' => true], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $code = 400, ?string $detail = null): void
{
    http_response_code($code);
    $out = ['success' => false, 'error' => $message];
    if ($detail !== null) {
        $out['detail'] = $detail;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------- احراز هویت (اختیاری) ---------- */
function require_key(): void
{
    if (API_KEY === '') {
        return; // احراز هویت غیرفعال است
    }
    $sent = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
    if (!hash_equals(API_KEY, (string)$sent)) {
        json_error('کلید API نامعتبر است', 401);
    }
}

/* ---------- محدودکردن متد ---------- */
function only(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        json_error('متد مجاز نیست', 405);
    }
}

/* ---------- خواندن بدنهٔ JSON در POST ---------- */
function body_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * الگوریتم پرتوافکنی (Ray casting)
 * بررسی قرارگرفتن یک نقطه در چندضلعی.
 *
 * @param float $lat   عرض جغرافیایی نقطه
 * @param float $lon   طول جغرافیایی نقطه
 * @param array $geometry  هندسهٔ GeoJSON از نوع Polygon (coordinates: [ [ [lon,lat], ... ] ])
 * @return bool  true اگر داخل محدوده باشد
 */
function point_in_polygon(float $lat, float $lon, array $geometry): bool
{
    if (($geometry['type'] ?? '') !== 'Polygon' || empty($geometry['coordinates'][0])) {
        return false;
    }
    $ring = $geometry['coordinates'][0]; // حلقهٔ بیرونی
    $inside = false;
    $n = count($ring);

    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = $ring[$i][0]; $yi = $ring[$i][1]; // [lon, lat]
        $xj = $ring[$j][0]; $yj = $ring[$j][1];

        $intersect = (($yi > $lat) !== ($yj > $lat)) &&
                     ($lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);
        if ($intersect) {
            $inside = !$inside;
        }
    }
    return $inside;
}

/**
 * اعتبارسنجی سادهٔ یک هندسهٔ Polygon در GeoJSON
 */
function validate_polygon($geom): ?array
{
    if (is_string($geom)) {
        $geom = json_decode($geom, true);
    }
    if (!is_array($geom)) {
        return null;
    }
    // اگر Feature یا FeatureCollection بود، هندسه را بیرون بکش
    if (($geom['type'] ?? '') === 'FeatureCollection' && !empty($geom['features'][0]['geometry'])) {
        $geom = $geom['features'][0]['geometry'];
    } elseif (($geom['type'] ?? '') === 'Feature' && !empty($geom['geometry'])) {
        $geom = $geom['geometry'];
    }
    if (($geom['type'] ?? '') !== 'Polygon') {
        return null;
    }
    $ring = $geom['coordinates'][0] ?? null;
    if (!is_array($ring) || count($ring) < 4) { // حداقل ۳ رأس + بستن حلقه
        return null;
    }
    return $geom;
}

/** شمارش رئوس چندضلعی (بدون احتساب نقطهٔ تکراری بستن حلقه) */
function count_vertices(array $geom): int
{
    $ring = $geom['coordinates'][0] ?? [];
    $n = count($ring);
    return $n > 0 ? $n - 1 : 0;
}
