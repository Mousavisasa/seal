<?php
/**
 * بررسی حصار جغرافیایی مکان‌ها (جدول locations)
 * با قالب و استایل مشترک پنل
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_login();

$page_title = 'بررسی حصار جغرافیایی';
$active = 'map';
require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<style>
  #map { height: 480px; border-radius: .75rem; z-index: 0; }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">بررسی حصار جغرافیایی</h1>
    <p class="text-muted small mb-0">مکان را انتخاب کنید، روی نقشه نقطه بزنید و داخل یا خارج بودن از محدوده را بررسی کنید.</p>
  </div>
</div>

<div class="row g-4">
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
        <div class="mb-3">
          <label class="form-label" for="placeId">مکان</label>
          <select class="form-select" id="placeId">
            <option value="">در حال بارگذاری مکان‌ها…</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="inLat">عرض جغرافیایی (Lat)</label>
          <input type="text" class="form-control ltr-text" id="inLat" readonly placeholder="روی نقشه کلیک کنید">
        </div>

        <div class="mb-3">
          <label class="form-label" for="inLon">طول جغرافیایی (Lon)</label>
          <input type="text" class="form-control ltr-text" id="inLon" readonly placeholder="روی نقشه کلیک کنید">
        </div>

        <button id="btnCheck" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
          <span class="iconify" data-icon="solar:map-point-search-bold"></span> بررسی حصار
        </button>

        <div id="result" class="alert d-none mt-3 mb-0"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
  // لایه‌های پایه — پیش‌فرض تایل داخلی تا در ایران بدون مشکل لود شود
  const baseLayers = {
    'تم روز (داخلی)': L.tileLayer('https://memaps.ir/hot/{z}/{x}/{y}.png',
      { maxZoom: 20, attribution: 'Memaps Hot' }),
    'ماهواره‌ای (داخلی)': L.tileLayer('https://memaps.ir/api/google-earth/satellite/{z}/{x}/{y}.png',
      { maxZoom: 20, attribution: 'Google Satellite via Memaps' }),
    'نقشه جهانی (OSM)': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' })
  };

  const map = L.map('map', { center: [35.6892, 51.3890], zoom: 12, layers: [baseLayers['تم روز (داخلی)']] });
  L.control.layers(baseLayers).addTo(map);

  let marker;
  let fenceLayer;           // لایهٔ محدودهٔ مکان انتخاب‌شده
  const locationsById = {}; // کش مکان‌های خوانده‌شده از جدول locations

  const select = document.getElementById('placeId');
  const resultDiv = document.getElementById('result');

  function showResult(type, text) {
    resultDiv.className = 'alert mt-3 mb-0 alert-' + type;
    resultDiv.textContent = text;
  }

  // بارگذاری مکان‌ها از دیتابیس (جدول locations)
  (async function loadLocations() {
    try {
      const response = await fetch('api/locations.php');
      const data = await response.json();
      if (!data.success) throw new Error(data.error);

      select.innerHTML = '<option value="">— انتخاب مکان —</option>';
      for (const loc of data.locations) {
        locationsById[loc.id] = loc;
        const opt = document.createElement('option');
        opt.value = loc.id;
        opt.textContent = `${loc.title} (${loc.location_code})`;
        select.appendChild(opt);
      }
    } catch (err) {
      select.innerHTML = '<option value="">خطا در بارگذاری مکان‌ها</option>';
    }
  })();

  // با انتخاب مکان، محدودهٔ آن روی نقشه رسم شود
  select.addEventListener('change', function () {
    if (fenceLayer) {
      map.removeLayer(fenceLayer);
      fenceLayer = null;
    }
    const loc = locationsById[this.value];
    if (!loc) return;

    if (loc.geojson) {
      fenceLayer = L.geoJSON(loc.geojson, {
        style: { color: '#6f42c1', weight: 2, fillOpacity: 0.15 }
      }).addTo(map);
      map.fitBounds(fenceLayer.getBounds());
    } else if (loc.lat || loc.lon) {
      map.setView([loc.lat, loc.lon], 14);
    }
  });

  // کلیک روی نقشه برای انتخاب مختصات
  map.on('click', function (e) {
    const { lat, lng } = e.latlng;
    document.getElementById('inLat').value = lat.toFixed(6);
    document.getElementById('inLon').value = lng.toFixed(6);

    if (marker) {
      marker.setLatLng(e.latlng);
    } else {
      marker = L.marker(e.latlng).addTo(map);
    }
  });

  // بررسی حصار
  document.getElementById('btnCheck').addEventListener('click', async function () {
    const id = select.value;
    const lat = document.getElementById('inLat').value;
    const lon = document.getElementById('inLon').value;

    if (!id || !lat || !lon) {
      showResult('warning', 'لطفاً مکان را انتخاب کرده و نقطه‌ای را روی نقشه انتخاب کنید.');
      return;
    }

    showResult('secondary', 'در حال بررسی…');

    try {
      const response = await fetch(`geofence/check.php?id=${id}&lat=${lat}&lon=${lon}`);
      const data = await response.json();

      if (data.success) {
        if (data.inside) {
          showResult('success', `نتیجه: داخل محدوده (${data.name})`);
        } else {
          showResult('danger', 'نتیجه: خارج از محدوده');
        }
      } else {
        showResult('danger', 'خطا: ' + (data.error || 'امکان دریافت پاسخ از سرور وجود ندارد.'));
      }
    } catch (err) {
      showResult('danger', 'خطای ارتباطی با سرور.');
    }
  });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
