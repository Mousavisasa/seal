<?php
/**
 * ایجاد مبدا/مقصد جدید
 * locations.region_id به regions.region_code ارجاع دارد
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

$errors = [];
$old = ['location_code' => '', 'region_id' => '', 'title' => '', 'lat' => '', 'lon' => '', 'geojson' => ''];
$regions = [];

try {
    $regions = db()->query('SELECT region_code, region_name FROM regions ORDER BY region_name')->fetchAll();
} catch (PDOException $e) {
    error_log('Regions fetch error: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $old['location_code'] = trim((string)($_POST['location_code'] ?? ''));
        $old['region_id']     = (string)($_POST['region_id'] ?? '');
        $old['title']         = trim((string)($_POST['title'] ?? ''));
        $old['lat']           = trim((string)($_POST['lat'] ?? ''));
        $old['lon']           = trim((string)($_POST['lon'] ?? ''));
        $old['geojson']       = trim((string)($_POST['geojson'] ?? ''));

        if ($old['location_code'] === '' || mb_strlen($old['location_code']) > 10) {
            $errors[] = 'کد مکان الزامی است و باید حداکثر ۱۰ کاراکتر باشد.';
        }
        if (mb_strlen($old['title']) < 2) {
            $errors[] = 'عنوان مکان باید حداقل ۲ حرف باشد.';
        }
        $regionId = (int)$old['region_id'];
        if ($regionId <= 0) {
            $errors[] = 'انتخاب منطقه الزامی است.';
        }
        $lat = $old['lat'] !== '' ? $old['lat'] : '0';
        $lon = $old['lon'] !== '' ? $old['lon'] : '0';
        if (!is_numeric($lat) || (float)$lat < -90 || (float)$lat > 90) {
            $errors[] = 'مقدار عرض جغرافیایی (lat) معتبر نیست.';
        }
        if (!is_numeric($lon) || (float)$lon < -180 || (float)$lon > 180) {
            $errors[] = 'مقدار طول جغرافیایی (lon) معتبر نیست.';
        }
        if ($old['geojson'] !== '' && json_decode($old['geojson']) === null) {
            $errors[] = 'داده GeoJSON معتبر نیست.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare('SELECT region_code FROM regions WHERE region_code = ? LIMIT 1');
                $stmt->execute([$regionId]);
                if (!$stmt->fetch()) {
                    $errors[] = 'منطقه انتخاب‌شده معتبر نیست.';
                }
            } catch (PDOException $e) {
                error_log('Region validate error: ' . $e->getMessage());
                $errors[] = 'خطایی در بررسی منطقه رخ داد.';
            }
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare('SELECT id FROM locations WHERE location_code = ? LIMIT 1');
                $stmt->execute([$old['location_code']]);
                if ($stmt->fetch()) {
                    $errors[] = 'مکانی با این کد قبلاً ثبت شده است.';
                } else {
                    $stmt = db()->prepare(
                        'INSERT INTO locations (location_code, region_id, title, lat, lon, geojson) VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $old['location_code'],
                        $regionId,
                        $old['title'],
                        (float)$lat,
                        (float)$lon,
                        $old['geojson'] !== '' ? $old['geojson'] : null,
                    ]);
                    set_flash('success', 'مکان «' . $old['title'] . '» با موفقیت ایجاد شد.');
                    header('Location: ' . BASE_URL . '/locations/list.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Create location error: ' . $e->getMessage());
                $errors[] = 'خطایی در ثبت مکان رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'مبدا/مقصد جدید';
$active = 'locations';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">ایجاد مبدا/مقصد جدید</h1>
  <p class="text-muted small mb-0">اطلاعات مکان را کامل و دقیق وارد کنید.</p>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <div class="d-flex align-items-center gap-2 fw-bold mb-2">
      <span class="iconify" data-icon="solar:danger-triangle-bold"></span> فرم دارای خطا است:
    </div>
    <ul class="mb-0 pe-4">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card panel-card">
  <div class="card-body p-4">
    <form method="post" action="<?= BASE_URL ?>/locations/create.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" id="geojson" name="geojson" value="<?= e($old['geojson']) ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="location_code">کد مکان</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:hashtag-square-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="location_code" name="location_code" required maxlength="10"
                   value="<?= e($old['location_code']) ?>" placeholder="مثلاً LOC-005">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="region_id">منطقه</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
            <select class="form-select" id="region_id" name="region_id" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($regions as $r): ?>
                <option value="<?= e((string)$r['region_code']) ?>" <?= (string)$r['region_code'] === $old['region_id'] ? 'selected' : '' ?>>
                  <?= e($r['region_name']) ?> (<?= e((string)$r['region_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="title">عنوان مکان</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:signpost-bold"></span></span>
            <input type="text" class="form-control" id="title" name="title" required minlength="2"
                   value="<?= e($old['title']) ?>" placeholder="مثلاً انبار نفت مرکزی">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="lat">عرض جغرافیایی (lat) <span class="text-muted small">(اختیاری)</span></label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:global-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="lat" name="lat"
                   value="<?= e($old['lat']) ?>" placeholder="مثلاً 35.6892">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="lon">طول جغرافیایی (lon) <span class="text-muted small">(اختیاری)</span></label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:global-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="lon" name="lon"
                   value="<?= e($old['lon']) ?>" placeholder="مثلاً 51.3890">
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <span class="iconify" data-icon="solar:add-circle-bold"></span> ثبت مکان
        </button>
        <a href="<?= BASE_URL ?>/locations/list.php" class="btn btn-outline-secondary">انصراف</a>
      </div>
    </form>
  </div>
</div>

<div class="card panel-card mt-3">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2">
      <span class="iconify text-jade fs-5" data-icon="solar:map-point-bold"></span>
      <span class="fw-bold">انتخاب مختصات از روی نقشه</span>
    </div>
    <button type="button" id="useMapCoordsBtn" class="btn btn-sm btn-soft-purple d-flex align-items-center gap-2">
      <span class="iconify" data-icon="solar:check-circle-bold"></span> استفاده از این مختصات در فرم
    </button>
  </div>
  <div class="card-body p-0">
    <div id="mapCoordsMsg" class="alert alert-info d-flex align-items-center gap-2 m-3 mb-0 d-none">
      <span class="iconify fs-5" data-icon="solar:info-circle-bold"></span>
      <span id="mapCoordsMsgText"></span>
    </div>
    <iframe id="locationMapFrame" src="<?= BASE_URL ?>/assets_map/map.html"
            title="نقشه انتخاب مکان"
            style="width:100%; height:520px; border:0; border-radius:0 0 1rem 1rem;"
            loading="lazy"></iframe>
  </div>
  <div class="card-footer bg-white text-muted small">
    <span class="iconify" data-icon="solar:info-circle-bold"></span>
    روی نقشه یک نقطه یا محدوده مشخص کنید (با ابزار رسم گوشه بالا-چپ نقشه)، سپس دکمه «استفاده از این مختصات در فرم» را بزنید تا عرض و طول جغرافیایی به‌صورت خودکار در فیلدهای بالا پر شود.
  </div>
</div>

<script>
(function () {
  'use strict';
  var btn = document.getElementById('useMapCoordsBtn');
  var frame = document.getElementById('locationMapFrame');
  var msgBox = document.getElementById('mapCoordsMsg');
  var msgText = document.getElementById('mapCoordsMsgText');
  if (!btn || !frame) return;

  function showMessage(text, isError) {
    if (!msgBox || !msgText) return;
    msgText.textContent = text;
    msgBox.classList.remove('d-none', 'alert-info', 'alert-warning');
    msgBox.classList.add(isError ? 'alert-warning' : 'alert-info');
  }

  /** میانگین مختصات یک آرایه از نقاط [lng, lat] برای مرکز چندضلعی */
  function centroidOfCoordinates(coords) {
    var sumLat = 0, sumLng = 0, count = 0;
    coords.forEach(function (pt) {
      if (Array.isArray(pt) && pt.length >= 2) {
        sumLng += Number(pt[0]);
        sumLat += Number(pt[1]);
        count++;
      }
    });
    return count > 0 ? { lat: sumLat / count, lng: sumLng / count } : null;
  }

  btn.addEventListener('click', function () {
    var win;
    try {
      win = frame.contentWindow;
    } catch (e) {
      showMessage('دسترسی به نقشه ممکن نشد.', true);
      return;
    }

    if (!win || typeof win.getDrawnGeoJSON !== 'function') {
      showMessage('نقشه هنوز به‌طور کامل بارگذاری نشده است. کمی صبر کنید و دوباره تلاش کنید.', true);
      return;
    }

    var geojson = win.getDrawnGeoJSON();
    var features = geojson && Array.isArray(geojson.features) ? geojson.features : [];

    if (!features.length) {
      showMessage('هیچ نقطه یا محدوده‌ای روی نقشه رسم نشده است. ابتدا با ابزار رسم گوشه بالا-چپ نقشه یک نقطه یا چندضلعی مشخص کنید.', true);
      return;
    }

    var lastFeature = features[features.length - 1];
    var geom = lastFeature.geometry || {};
    var lat = null, lng = null;

    if (geom.type === 'Point' && Array.isArray(geom.coordinates)) {
      lng = Number(geom.coordinates[0]);
      lat = Number(geom.coordinates[1]);
    } else if (geom.type === 'Polygon' && Array.isArray(geom.coordinates) && geom.coordinates[0]) {
      var center = centroidOfCoordinates(geom.coordinates[0]);
      if (center) { lat = center.lat; lng = center.lng; }
    } else if (geom.type === 'LineString' && Array.isArray(geom.coordinates)) {
      var centerLine = centroidOfCoordinates(geom.coordinates);
      if (centerLine) { lat = centerLine.lat; lng = centerLine.lng; }
    }

    if (lat === null || lng === null || !isFinite(lat) || !isFinite(lng)) {
      showMessage('مختصات قابل استخراج از رسم فعلی نیست.', true);
      return;
    }

    var latInput = document.getElementById('lat');
    var lonInput = document.getElementById('lon');
    var geojsonInput = document.getElementById('geojson');
    if (latInput) latInput.value = lat.toFixed(6);
    if (lonInput) lonInput.value = lng.toFixed(6);
    if (geojsonInput) geojsonInput.value = JSON.stringify(geojson);

    showMessage('مختصات با موفقیت در فرم قرار گرفت: ' + lat.toFixed(6) + ', ' + lng.toFixed(6), false);
  });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
