<?php
/**
 * بررسی حصار جغرافیایی قبل از تایید نهایی «حضور متصدی» در مبدا/مقصد
 * (بدون نیاز به سشن/ورود به پنل) — معادل waybill_geofence_check.php برای متصدی
 *
 * ورودی (GET): فقط و فقط «token».
 * این توکن یک‌بارمصرف است و می‌تواند یکی از این دو نوع باشد:
 *  ۱) توکن «تایید حضور» (opact:origin|destination:<id>، ساخته‌شده با
 *     helpers/tokens.php::create_operator_action_token) — نقش (origin/destination) +
 *     شناسه بارنامه از داخل خودِ توکن استخراج می‌شود.
 *  ۲) توکن «تایید بارگیری/تحویل» (opapprove:loading|delivery:<id>، ساخته‌شده با
 *     helpers/tokens.php::create_operator_approve_token) — نوع عملیات + شناسه
 *     بارنامه از داخل خودِ توکن استخراج می‌شود.
 *
 * این صفحه خودش هیچ تغییری در بارنامه ایجاد نمی‌کند؛ فقط موقعیت فعلی متصدی
 * را می‌گیرد و با محدودهٔ جغرافیایی مکان مبدا یا مقصد (بسته به نوع عملیات)
 * مقایسه می‌کند. تایید نهایی همیشه با کلیک دستی کاربر انجام می‌شود و با همین
 * توکن (بدون تغییر) به waybill_operator_loading.php و از آن‌جا به وب‌سرویس
 * api/operator_confirm.php درخواست می‌زند؛ آن وب‌سرویس بر اساس نوع توکن،
 * تصمیم می‌گیرد که حضور را ثبت کند یا وضعیت بارنامه را تغییر دهد.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/tokens.php';
require_once __DIR__ . '/helpers/settings.php';

$token = trim((string)($_GET['token'] ?? ''));

$errors              = [];
$operator            = null;
$waybill             = null;
$role                = ''; // 'origin' یا 'destination' — برای تعیین مکان هدف حصار
$tokenKind           = ''; // 'presence' یا 'approve'
$approveType         = ''; // فقط برای tokenKind === 'approve': 'loading' یا 'delivery'
$waybill_number           = 0;
$geofenceEnabled     = (int)get_setting(SETTING_GEOFENCE_CONTROL_OPERATOR, '1') === 1;

$resolved = validate_operator_action_token($token);
if ($resolved) {
    $tokenKind = 'presence';
    $operator  = $resolved['operator'];
    $role      = $resolved['role']; // 'origin' یا 'destination'
    $waybill_number = $resolved['waybill_number'];

} else {
    $resolved = validate_operator_approve_token($token);
    if ($resolved) {
        $tokenKind   = 'approve';
        $operator    = $resolved['operator'];
        $approveType = $resolved['approve_type']; // 'loading' یا 'delivery'
        $role        = $approveType === 'loading' ? 'origin' : 'destination';
        $waybill_number   = $resolved['waybill_number'];
    }
}

if (!$operator) {
    $errors[] = 'توکن نامعتبر است یا منقضی شده است. لطفاً دوباره وارد شوید.';
} elseif ($operator['user_type'] !== 'operator') {
    $operator = null;
    $errors[] = 'این توکن متعلق به یک حساب متصدی نیست.';
} elseif ((int)($operator['is_active'] ?? 1) === 0) {
    $operator = null;
    $errors[] = 'حساب کاربری شما غیرفعال شده است. برای اطلاعات بیشتر با مدیر سامانه تماس بگیرید.';
}

if ($operator && $waybill_number > 0) {
    try {
        ensure_operator_confirm_columns();
        $stmt = db()->prepare(
                'SELECT w.*,
                    ol.title AS origin_title, ol.lat AS origin_lat, ol.lon AS origin_lon, ol.geojson AS origin_geojson,
                    dl.title AS destination_title, dl.lat AS destination_lat, dl.lon AS destination_lon, dl.geojson AS destination_geojson
             FROM fuel_waybills w
             INNER JOIN locations ol ON ol.id = w.origin_location_id
             INNER JOIN locations dl ON dl.id = w.destination_location_id
             WHERE w.waybill_number = ? LIMIT 1'
        );
        $stmt->execute([$waybill_number]);
        $waybill = $stmt->fetch();

        $ownerColumn = $role === 'origin' ? 'origin_operator_user_id' : 'destination_operator_user_id';

        if (!$waybill) {
            $errors[] = ' بارنامه مورد نظر یافت نشد.'.$waybill_number;
        } elseif ((int)$waybill[$ownerColumn] !== (int)$operator['id']) {
            $waybill = null;
            $errors[] = ' این بارنامه به شما تخصیص داده نشده است.'.$waybill_number;
        } elseif ($tokenKind === 'presence') {
            $confirmColumn = $role === 'origin' ? 'origin_confirmed_at' : 'destination_confirmed_at';
            if (!empty($waybill[$confirmColumn])) {
                $errors[] = ' حضور شما برای این بارنامه قبلاً تایید شده است.'.$waybill_number;
            }
        } else { // approve
            $expectedStatus = $approveType === 'loading' ? 'بارگیری شده' : 'پایان پیمایش';
            if ($waybill['send_status'] !== $expectedStatus) {
                $errors[] = 'این بارنامه در وضعیت «' . $expectedStatus . '» نیست. وضعیت فعلی: ' . $waybill['send_status'];
            }
        }
    } catch (PDOException $e) {
        error_log('Waybill operator geofence check error: ' . $e->getMessage());
        $errors[] = 'خطایی در دریافت اطلاعات بارنامه رخ داد.'.$waybill_number;
    }
} elseif (!$errors) {
    $errors[] = 'درخواست نامعتبر است.'.$waybill_number;
}

// مکان هدف: برای متصدی مبدا، مکان مبدا؛ برای متصدی مقصد، مکان مقصد
$targetLocationId = null;
$targetTitle      = '';
$targetLat        = null;
$targetLon        = null;
$targetGeojson    = null;

if ($waybill) {
    if ($role === 'origin') {
        $targetLocationId = (int)$waybill['origin_location_id'];
        $targetTitle      = $waybill['origin_title'];
        $targetLat        = (float)$waybill['origin_lat'];
        $targetLon        = (float)$waybill['origin_lon'];
        $rawGeojson       = $waybill['origin_geojson'];
    } else {
        $targetLocationId = (int)$waybill['destination_location_id'];
        $targetTitle      = $waybill['destination_title'];
        $targetLat        = (float)$waybill['destination_lat'];
        $targetLon        = (float)$waybill['destination_lon'];
        $rawGeojson       = $waybill['destination_geojson'];
    }

    if (!empty($rawGeojson)) {
        $decoded = json_decode((string)$rawGeojson, true);
        if (is_array($decoded)) {
            $targetGeojson = $decoded;
        }
    }
}

$roleLabel = $role === 'destination' ? 'مقصد' : 'مبدا';

if ($tokenKind === 'approve') {
    $flowTitle    = $approveType === 'loading' ? 'تایید بارگیری متصدی مبدا' : 'تایید تحویل متصدی مقصد';
    $actionButton = $approveType === 'loading' ? 'ثبت تایید بارگیری' : 'ثبت تایید تحویل';
} else {
    $flowTitle    = 'تایید حضور متصدی ' . $roleLabel;
    $actionButton = 'ثبت تایید حضور';
}

// لینک بازگشت به فهرست بارنامه‌ها؛ چون این صفحه دیگر توکن اصلی operator_waybills
// را در URL ندارد (فقط توکن یک‌بارمصرف عملیات)، برای بازگشت یک توکن تازه می‌سازیم
$backUrl = BASE_URL . '/operator_waybills_public.php';
if ($operator) {
    $backUrl .= '?token=' . rawurlencode(create_access_token((int)$operator['id'], 'operator_waybills'));
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بررسی حصار جغرافیایی — <?= e($flowTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/vazirmatn.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <script src="<?= BASE_URL ?>/assets/js/iconify.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/iconify-icons.js"></script>
    <style>
        #map { height: 420px; border-radius: .75rem; z-index: 0; }
        .my-location-dot {
            display: block; width: 14px; height: 14px; background: #1a73e8;
            border: 3px solid #fff; border-radius: 50%; box-shadow: 0 0 0 2px rgba(26, 115, 232, .4);
        }
    </style>
</head>
<body class="public-page-body">

<div class="public-page-wrap">

    <div class="text-center mb-4">
        <span class="iconify fs-1 text-jade" data-icon="solar:map-point-search-bold"></span>
        <h1 class="h4 fw-bold mt-2 mb-1"><?php if ($geofenceEnabled): ?>بررسی حصار جغرافیایی — <?= e($flowTitle) ?><?php else: ?><?= e($flowTitle) ?><?php endif; ?></h1>
        <p class="text-muted small mb-0">
            <?php if ($geofenceEnabled): ?>
                موقعیت فعلی شما گرفته می‌شود و با محدودهٔ مکان <?= e($roleLabel) ?> مقایسه می‌گردد.
            <?php else: ?>
                برای تایید حضور خود آماده هستید.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
            <span class="iconify fs-5" data-icon="solar:danger-triangle-bold"></span>
            <div>
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="text-center">
            <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">
                <span class="iconify" data-icon="solar:arrow-right-bold"></span> بازگشت به فهرست بارنامه‌ها
            </a>
        </div>
    <?php else: ?>

        <div class="card border-0 shadow-sm mb-3" style="border-radius: 1rem;">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="fw-bold ltr-text fs-5"><?= e($waybill['waybill_number']) ?></div>
                        <div class="small text-muted d-flex align-items-center gap-1 mt-1">
                            <span class="iconify" data-icon="solar:point-on-map-bold"></span>
                            <?= e($waybill['origin_title']) ?> <span class="iconify" data-icon="solar:arrow-left-bold"></span> <?= e($waybill['destination_title']) ?>
                        </div>
                        <div class="small text-muted d-flex align-items-center gap-1 mt-1">
                            <span class="iconify" data-icon="solar:fuel-bold"></span> <?= e($waybill['product_type']) ?>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="status-badge status-registered"><?= e($waybill['send_status']) ?></span>
                        <div class="small text-muted mt-2">
                            <span class="iconify" data-icon="solar:flag-bold"></span>
                            مکان هدف برای <?= e($flowTitle) ?>: <span class="fw-bold"><?= e($targetTitle) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <?php if ($geofenceEnabled): ?>
                <div class="col-lg-8">
                    <div class="card panel-card">
                        <div class="card-body p-2">
                            <div id="map"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card panel-card">
                        <div class="card-body">
                            <div id="result" class="alert alert-secondary">در حال دریافت موقعیت مکانی شما…</div>

                            <button id="btnRecheck" type="button" class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2 mb-3">
                                <span class="iconify" data-icon="solar:refresh-bold"></span> بررسی مجدد موقعیت
                            </button>

                            <button id="btnConfirm" type="button" disabled
                                    class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                <span class="iconify" data-icon="solar:check-circle-bold"></span> <?= e($actionButton) ?>
                            </button>

                            <div class="text-center mt-3">
                                <a href="<?= e($backUrl) ?>" class="small text-muted">
                                    انصراف و بازگشت به فهرست بارنامه‌ها
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-lg-8">
                    <div class="card panel-card">
                        <div class="card-body p-4 text-center">
                            <span class="iconify fs-1 text-success mb-3 d-block" data-icon="solar:check-circle-bold"></span>
                            <h5 class="fw-bold mb-2">کنترل جئوفنس غیرفعال است</h5>
                            <p class="text-muted mb-0">می‌توانید بدون بررسی موقعیت مکانی، تایید حضور خود را انجام دهید.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card panel-card">
                        <div class="card-body d-flex flex-column gap-3">
                            <button id="btnConfirmDirect" type="button"
                                    class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                <span class="iconify" data-icon="solar:check-circle-bold"></span> <?= e($actionButton) ?>
                            </button>

                            <div class="text-center">
                                <a href="<?= e($backUrl) ?>" class="small text-muted">
                                    انصراف و بازگشت به فهرست بارنامه‌ها
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

<script>
    window.MAPIR_API_KEY = <?= json_encode(MAPIR_API_KEY) ?>;
    window.GEOFENCE_ENABLED = <?= json_encode($geofenceEnabled) ?>;
</script>
<?php if (!$errors): ?>
    <?php if ($geofenceEnabled): ?>
        <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (function () {
                var TARGET_LOCATION_ID = <?= (int)$targetLocationId ?>;
                var TARGET_LAT = <?= json_encode($targetLat) ?>;
                var TARGET_LON = <?= json_encode($targetLon) ?>;
                var TARGET_TITLE = <?= json_encode($targetTitle) ?>;
                var TARGET_GEOJSON = <?= json_encode($targetGeojson) ?>;
                var CHECK_URL = '<?= BASE_URL ?>/map/geofence/check.php';
                var LOADING_PAGE_URL = '<?= BASE_URL ?>/waybill_operator_loading.php';
                var TOKEN = <?= json_encode($token) ?>; // همان توکن یک‌بارمصرفِ عملیات؛ تنها ورودی صفحهٔ لودینگ/وب‌سرویس

                var resultDiv      = document.getElementById('result');
                var btnConfirm      = document.getElementById('btnConfirm');
                var btnRecheck      = document.getElementById('btnRecheck');
                var lastLatLng      = null;
                var userMarker      = null;
                var insideGeofence  = false;

                function showResult(type, text) {
                    resultDiv.className = 'alert alert-' + type;
                    resultDiv.textContent = text;
                }

                btnConfirm.addEventListener('click', function () {
                    if (!insideGeofence || btnConfirm.disabled) return;
                    window.location.href = LOADING_PAGE_URL + '?token=' + encodeURIComponent(TOKEN)+'&myseal='+<?= e((string)$waybill_number) ?>;
                });

                var baseLayers = {
                    'تم روز (داخلی)': L.tileLayer('https://memaps.ir/hot/{z}/{x}/{y}.png',
                        { maxZoom: 20, attribution: 'Memaps Hot' }),
                    'ماهواره‌ای (داخلی)': L.tileLayer('https://memaps.ir/api/google-earth/satellite/{z}/{x}/{y}.png',
                        { maxZoom: 20, attribution: 'Google Satellite via Memaps' }),
                    'نقشه جهانی (OSM)': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                        { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' })
                };

                var map = L.map('map', {
                    center: [TARGET_LAT || 35.6892, TARGET_LON || 51.3890],
                    zoom: 14,
                    layers: [baseLayers['تم روز (داخلی)']]
                });
                L.control.layers(baseLayers).addTo(map);

                var myLocationIcon = L.divIcon({
                    className: 'my-location-marker',
                    html: '<span class="my-location-dot"></span>',
                    iconSize: [18, 18],
                    iconAnchor: [9, 9]
                });

                if (TARGET_LAT && TARGET_LON) {
                    L.marker([TARGET_LAT, TARGET_LON]).addTo(map).bindPopup(TARGET_TITLE);
                }

                if (TARGET_GEOJSON) {
                    var fenceLayer = L.geoJSON(TARGET_GEOJSON, {
                        style: { color: '#6f42c1', weight: 2, fillOpacity: 0.15 }
                    }).addTo(map);
                    map.fitBounds(fenceLayer.getBounds());
                }

                function checkGeofence(lat, lon) {
                    showResult('secondary', 'در حال بررسی موقعیت نسبت به محدوده…');
                    insideGeofence = false;
                    btnConfirm.disabled = true;

                    fetch(CHECK_URL + '?id=' + TARGET_LOCATION_ID + '&lat=' + lat + '&lon=' + lon)
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data.success) {
                                showResult('danger', 'خطا: ' + (data.error || 'بررسی محدوده ممکن نشد.'));
                                return;
                            }
                            if (data.inside) {
                                showResult('success', 'شما داخل محدودهٔ «' + data.name + '» هستید. برای ادامه، دکمهٔ زیر را بزنید.');
                                insideGeofence = true;
                                btnConfirm.disabled = false;
                            } else {
                                showResult('warning', 'شما هنوز داخل محدودهٔ «' + data.name + '» نیستید. به مکان مورد نظر نزدیک‌تر شوید و دوباره بررسی کنید.');
                            }
                        })
                        .catch(function () {
                            showResult('danger', 'خطای ارتباط با سرور هنگام بررسی محدوده.');
                        });
                }

                var isSecureContext = window.isSecureContext === true ||
                    location.protocol === 'https:' ||
                    location.hostname === 'localhost' ||
                    location.hostname === '127.0.0.1';

                function locate() {
                    if (!isSecureContext) {
                        showResult('danger', 'برای دریافت موقعیت مکانی، این صفحه باید با آدرس https باز شود (مرورگرها موقعیت مکانی را روی http غیرمجاز می‌کنند). لطفاً از آدرس https همین سامانه استفاده کنید.');
                        return;
                    }

                    if (!navigator.geolocation) {
                        showResult('danger', 'مرورگر شما از موقعیت‌یابی پشتیبانی نمی‌کند.');
                        return;
                    }

                    showResult('secondary', 'در حال دریافت موقعیت مکانی شما…');

                    navigator.geolocation.getCurrentPosition(function (pos) {
                        lastLatLng = [pos.coords.latitude, pos.coords.longitude];

                        if (userMarker) {
                            userMarker.setLatLng(lastLatLng);
                        } else {
                            userMarker = L.marker(lastLatLng, {
                                icon: myLocationIcon,
                                title: 'موقعیت فعلی شما',
                                zIndexOffset: 1000
                            }).addTo(map).bindTooltip('موقعیت فعلی شما');
                        }

                        map.setView(lastLatLng, 15);
                        checkGeofence(lastLatLng[0], lastLatLng[1]);
                    }, function (err) {
                        var messages = {
                            1: 'اجازهٔ دسترسی به موقعیت مکانی داده نشد. آن را از تنظیمات مرورگر/گوشی برای این سایت فعال کنید.',
                            2: 'موقعیت مکانی در دسترس نیست. GPS گوشی را روشن کنید و دوباره تلاش کنید.',
                            3: 'زمان دریافت موقعیت مکانی به پایان رسید. لطفاً دوباره تلاش کنید.'
                        };
                        showResult('danger', messages[err.code] || 'دسترسی به موقعیت مکانی امکان‌پذیر نشد.');
                    }, {
                        enableHighAccuracy: true,
                        timeout: 20000,
                        maximumAge: 0
                    });
                }

                btnRecheck.addEventListener('click', locate);
                locate();
            })();
        </script>
    <?php else: ?>
        <script>
            (function () {
                var LOADING_PAGE_URL = '<?= BASE_URL ?>/waybill_operator_loading.php';
                var TOKEN = <?= json_encode($token) ?>;

                var btnConfirmDirect = document.getElementById('btnConfirmDirect');

                btnConfirmDirect.addEventListener('click', function () {
                    window.location.href = LOADING_PAGE_URL + '?token=' + encodeURIComponent(TOKEN)+'&myseal='+<?= e((string)$waybill_number) ?>;
                });
            })();
        </script>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>