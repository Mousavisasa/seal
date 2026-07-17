<?php
/**
 * بررسی حصار جغرافیایی قبل از تایید نهایی «شروع سفر» / «پایان سفر»
 * (بدون نیاز به سشن/ورود به پنل — دقیقاً مثل driver_waybills.php با همان توکن)
 *
 * ورودی (GET):
 *   token  - توکن دسترسی موقت راننده (از driver_waybills.php)
 *   id     - شناسه بارنامه
 *   action - start_trip یا end_trip
 *
 * این صفحه خودش هیچ تغییری در وضعیت بارنامه ایجاد نمی‌کند؛ فقط موقعیت فعلی
 * راننده (از طریق addGeolocation در Mapp) را می‌گیرد، با محدودهٔ جغرافیایی
 * مکان مبدا (برای شروع سفر) یا مقصد (برای پایان سفر) از طریق
 * map/geofence/check.php مقایسه می‌کند، و در صورت تایید داخل‌محدوده‌بودن،
 * دکمهٔ تایید نهایی را فعال می‌کند که همان فرم POST قبلی را به
 * driver_waybills.php ارسال می‌کند.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/tokens.php';

$token     = trim((string)($_GET['token'] ?? ''));
$waybillId = (int)($_GET['id'] ?? 0);
$action    = (string)($_GET['action'] ?? '');

$errors  = [];
$driver  = null;
$waybill = null;

if (!in_array($action, ['start_trip', 'end_trip'], true)) {
    $errors[] = 'عملیات درخواستی نامعتبر است.';
} else {
    $driver = validate_access_token($token, 'driver_waybills');
    if (!$driver) {
        $errors[] = 'توکن نامعتبر است یا منقضی شده است. لطفاً دوباره وارد شوید.';
    } elseif ($driver['user_type'] !== 'driver') {
        $driver = null;
        $errors[] = 'این توکن متعلق به یک حساب راننده نیست.';
    } elseif ((int)($driver['is_active'] ?? 1) === 0) {
        $driver = null;
        $errors[] = 'حساب کاربری شما غیرفعال شده است. برای اطلاعات بیشتر با مدیر سامانه تماس بگیرید.';
    }
}

if ($driver && $waybillId > 0) {
    try {
        $stmt = db()->prepare(
            'SELECT w.*,
                    ol.title AS origin_title, ol.lat AS origin_lat, ol.lon AS origin_lon,
                    dl.title AS destination_title, dl.lat AS destination_lat, dl.lon AS destination_lon
             FROM fuel_waybills w
             INNER JOIN locations ol ON ol.id = w.origin_location_id
             INNER JOIN locations dl ON dl.id = w.destination_location_id
             WHERE w.id = ? LIMIT 1'
        );
        $stmt->execute([$waybillId]);
        $waybill = $stmt->fetch();

        if (!$waybill) {
            $errors[] = 'بارنامه مورد نظر یافت نشد.';
        } elseif ((int)$waybill['driver_user_id'] !== (int)$driver['id']) {
            $waybill = null;
            $errors[] = 'این بارنامه به شما تخصیص داده نشده است.';
        } elseif ($action === 'start_trip' && $waybill['send_status'] !== 'ثبت شده') {
            $errors[] = 'این بارنامه در وضعیت «ثبت شده» نیست، پس امکان شروع سفر وجود ندارد.';
        } elseif ($action === 'end_trip' && $waybill['send_status'] !== 'ارسال شده') {
            $errors[] = 'این بارنامه در وضعیت «ارسال شده» نیست، پس امکان پایان سفر وجود ندارد.';
        }
    } catch (PDOException $e) {
        error_log('Waybill geofence check error: ' . $e->getMessage());
        $errors[] = 'خطایی در دریافت اطلاعات بارنامه رخ داد.';
    }
} elseif (!$errors) {
    $errors[] = 'درخواست نامعتبر است.';
}

// مکان هدف: برای «شروع سفر» مبدا، برای «پایان سفر» مقصد
$targetLocationId = null;
$targetTitle      = '';
$targetLat        = null;
$targetLon        = null;

if ($waybill) {
    if ($action === 'start_trip') {
        $targetLocationId = (int)$waybill['origin_location_id'];
        $targetTitle      = $waybill['origin_title'];
        $targetLat        = (float)$waybill['origin_lat'];
        $targetLon        = (float)$waybill['origin_lon'];
    } else {
        $targetLocationId = (int)$waybill['destination_location_id'];
        $targetTitle      = $waybill['destination_title'];
        $targetLat        = (float)$waybill['destination_lat'];
        $targetLon        = (float)$waybill['destination_lon'];
    }
}

$actionLabel  = $action === 'end_trip' ? 'پایان سفر' : 'شروع سفر';
$actionButton = $action === 'end_trip' ? 'ثبت پایان سفر' : 'ثبت شروع سفر';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>بررسی حصار جغرافیایی <?= e($actionLabel) ?> | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
<!--
  TODO(مدیر پروژه): تگ‌های CSS/JS واقعی کتابخانه نقشه Mapp (map.ir) را همین‌جا
  اضافه کنید (لینک CDN + کلید API واقعی در صورت نیاز). در ادامه از window.Mapp
  و متدهای addLayers()/addGeolocation() طبق نمونه ارسالی استفاده شده است.
-->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<style>
  #app { height: 420px; border-radius: .75rem; }
  .my-location-dot {
    display: block;
    width: 14px;
    height: 14px;
    background: #1a73e8;
    border: 3px solid #fff;
    border-radius: 50%;
    box-shadow: 0 0 0 2px rgba(26, 115, 232, .4);
  }
</style>
</head>
<body class="public-page-body">

<div class="public-page-wrap">

  <div class="text-center mb-4">
    <span class="iconify fs-1 text-jade" data-icon="solar:map-point-search-bold"></span>
    <h1 class="h4 fw-bold mt-2 mb-1">بررسی حصار جغرافیایی — <?= e($actionLabel) ?></h1>
    <p class="text-muted small mb-0">
      موقعیت فعلی شما گرفته می‌شود و با محدودهٔ مکان <?= $action === 'end_trip' ? 'مقصد' : 'مبدا' ?> مقایسه می‌گردد.
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
      <a href="<?= BASE_URL ?>/driver_waybills.php?token=<?= e(rawurlencode($token)) ?>" class="btn btn-outline-secondary">
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
              مکان هدف برای <?= e($actionLabel) ?>: <span class="fw-bold"><?= e($targetTitle) ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card panel-card">
          <div class="card-body p-2">
            <div id="app"></div>
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

            <form method="post" action="<?= BASE_URL ?>/driver_waybills.php">
              <input type="hidden" name="token" value="<?= e($token) ?>">
              <input type="hidden" name="id" value="<?= e((string)$waybill['id']) ?>">
              <input type="hidden" name="action" value="<?= e($action) ?>">
              <button id="btnConfirm" type="submit" disabled
                      class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                <span class="iconify" data-icon="solar:check-circle-bold"></span> <?= e($actionButton) ?>
              </button>
            </form>

            <div class="text-center mt-3">
              <a href="<?= BASE_URL ?>/driver_waybills.php?token=<?= e(rawurlencode($token)) ?>" class="small text-muted">
                انصراف و بازگشت به فهرست بارنامه‌ها
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

  <?php endif; ?>

</div>

<script>
  window.MAPIR_API_KEY = <?= json_encode(MAPIR_API_KEY) ?>;
</script>
<?php if (!$errors): ?>
<script>
$(document).ready(function () {
  var TARGET_LOCATION_ID = <?= (int)$targetLocationId ?>;
  var TARGET_LAT = <?= json_encode($targetLat) ?>;
  var TARGET_LON = <?= json_encode($targetLon) ?>;
  var CHECK_URL  = '<?= BASE_URL ?>/map/geofence/check.php';

  var resultDiv    = document.getElementById('result');
  var btnConfirm   = document.getElementById('btnConfirm');
  var btnRecheck   = document.getElementById('btnRecheck');
  var lastLatLng   = null;
  var userMarker   = null;

  function showResult(type, text) {
    resultDiv.className = 'alert alert-' + type;
    resultDiv.textContent = text;
  }

  // مقداردهی اولیه نقشه با کتابخانه Mapp (map.ir) — طبق نمونه ارسالی
  var app = new Mapp({
    element: '#app',
    presets: {
      latlng: { lat: TARGET_LAT || 32, lng: TARGET_LON || 52 },
      zoom: 14
    },
    apiKey: window.MAPIR_API_KEY
  });
  app.addLayers();
  app.addGeolocation(); // دریافت موقعیت فعلی کاربر از طریق همین API

  // نشانگر مکان هدف (مبدا/مقصد بارنامه) روی نقشه
  if (app.map && TARGET_LAT && TARGET_LON) {
    L.marker([TARGET_LAT, TARGET_LON]).addTo(app.map).bindPopup(<?= json_encode($targetTitle) ?>);
  }

  function checkGeofence(lat, lon) {
    showResult('secondary', 'در حال بررسی موقعیت نسبت به محدوده…');
    btnConfirm.disabled = true;

    fetch(CHECK_URL + '?id=' + TARGET_LOCATION_ID + '&lat=' + lat + '&lon=' + lon)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          showResult('danger', 'خطا: ' + (data.error || 'بررسی محدوده ممکن نشد.'));
          return;
        }
        if (data.inside) {
          showResult('success', 'شما داخل محدودهٔ «' + data.name + '» هستید. اکنون می‌توانید ادامه دهید.');
          btnConfirm.disabled = false;
        } else {
          showResult('warning', 'شما هنوز داخل محدودهٔ «' + data.name + '» نیستید. به مکان مورد نظر نزدیک‌تر شوید و دوباره بررسی کنید.');
        }
      })
      .catch(function () {
        showResult('danger', 'خطای ارتباط با سرور هنگام بررسی محدوده.');
      });
  }

  // رویدادهای موقعیت‌یابی که addGeolocation در نقشه (Leaflet) فعال می‌کند
  if (app.map && typeof app.map.on === 'function') {
    app.map.on('locationfound', function (e) {
      lastLatLng = e.latlng;

      if (userMarker) {
        userMarker.setLatLng(e.latlng);
      } else {
        userMarker = L.marker(e.latlng, {
          icon: L.divIcon({
            className: 'my-location-marker',
            html: '<span class="my-location-dot"></span>',
            iconSize: [18, 18],
            iconAnchor: [9, 9]
          }),
          title: 'موقعیت فعلی شما',
          zIndexOffset: 1000
        }).addTo(app.map).bindTooltip('موقعیت فعلی شما');
      }

      checkGeofence(e.latlng.lat, e.latlng.lng);
    });

    app.map.on('locationerror', function () {
      showResult('danger', 'دسترسی به موقعیت مکانی امکان‌پذیر نشد. GPS دستگاه خود را روشن کرده و اجازه دسترسی به موقعیت را بدهید.');
    });
  }

  btnRecheck.addEventListener('click', function () {
    if (lastLatLng) {
      checkGeofence(lastLatLng.lat, lastLatLng.lng);
    } else if (app.map && typeof app.map.locate === 'function') {
      showResult('secondary', 'در حال دریافت موقعیت مکانی شما…');
      app.map.locate({ setView: true, enableHighAccuracy: true });
    } else {
      showResult('warning', 'موقعیت فعلی شما هنوز دریافت نشده است.');
    }
  });
});
</script>
<?php endif; ?>
</body>
</html>
