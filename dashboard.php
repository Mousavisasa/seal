<?php
/**
 * داشبورد ادمین: کارت‌های خلاصه آمار کاربران
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/jalali.php';

require_admin();

$counts = ['total' => 0, 'admin' => 0, 'driver' => 0, 'operator' => 0, 'region' => 0];
$latest = [];
$waybillCounts = ['total' => 0, 'ثبت شده' => 0, 'ارسال شده' => 0, 'تحویل شده' => 0, 'لغو شده' => 0];
$productCounts = [];
$monthlyTrend = [];

try {
    $rows = db()->query('SELECT user_type, COUNT(*) AS c FROM users GROUP BY user_type')->fetchAll();
    foreach ($rows as $row) {
        $counts[$row['user_type']] = (int)$row['c'];
        $counts['total'] += (int)$row['c'];
    }
    $latest = db()->query('SELECT national_code, first_name, last_name, user_type, created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll();

    $wRows = db()->query('SELECT send_status, COUNT(*) AS c FROM fuel_waybills GROUP BY send_status')->fetchAll();
    foreach ($wRows as $row) {
        $waybillCounts[$row['send_status']] = (int)$row['c'];
        $waybillCounts['total'] += (int)$row['c'];
    }

    $pRows = db()->query('SELECT product_type, COUNT(*) AS c FROM fuel_waybills GROUP BY product_type')->fetchAll();
    foreach (PRODUCT_TYPES as $p) {
        $productCounts[$p] = 0;
    }
    foreach ($pRows as $row) {
        $productCounts[$row['product_type']] = (int)$row['c'];
    }

    // روند ثبت بارنامه در ۶ ماه اخیر (بر اساس تاریخ میلادی issue_date، محور با برچسب شمسی نمایش داده می‌شود)
    $mRows = db()->query(
        "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS ym, COUNT(*) AS c
         FROM fuel_waybills
         WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym ORDER BY ym ASC"
    )->fetchAll();
    foreach ($mRows as $row) {
        $monthlyTrend[$row['ym']] = (int)$row['c'];
    }
} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت اطلاعات رخ داد.');
}

$page_title = 'داشبورد';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">داشبورد</h1>
    <p class="text-muted small mb-0">نمای کلی کاربران سامانه</p>
  </div>
  <a href="<?= BASE_URL ?>/users/create.php" class="btn btn-primary d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:user-plus-rounded-bold"></span> کاربر جدید
  </a>
</div>

<?= render_flash() ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:users-group-two-rounded-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$counts['total']) ?></div>
          <div class="stat-label">کل کاربران</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:bus-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$counts['driver']) ?></div>
          <div class="stat-label">راننده</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:user-id-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$counts['operator']) ?></div>
          <div class="stat-label">متصدی</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$counts['region']) ?></div>
          <div class="stat-label">منطقه</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 mt-2">
  <h2 class="h6 fw-bold mb-0">ماژول بارنامه سوخت</h2>
  <a href="<?= BASE_URL ?>/waybills/create.php" class="btn btn-soft-purple d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:add-circle-bold"></span> بارنامه جدید
  </a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:fuel-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['total']) ?></div>
          <div class="stat-label">کل بارنامه‌ها</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:delivery-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['ارسال شده']) ?></div>
          <div class="stat-label">ارسال شده</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:check-circle-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['تحویل شده']) ?></div>
          <div class="stat-label">تحویل شده</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:close-circle-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['لغو شده']) ?></div>
          <div class="stat-label">لغو شده</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/regions/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--purple-soft); color: var(--purple-dark);"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
          <div>
            <div class="fw-bold">مدیریت مناطق</div>
            <div class="text-muted small">افزودن و ویرایش مناطق</div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/locations/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--jade-soft); color: var(--jade-dark);"><span class="iconify" data-icon="solar:signpost-bold"></span></span>
          <div>
            <div class="fw-bold">مدیریت مبادی و مقاصد</div>
            <div class="text-muted small">افزودن و ویرایش مکان‌ها</div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/waybills/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--purple-soft); color: var(--purple-dark);"><span class="iconify" data-icon="solar:fuel-bold"></span></span>
          <div>
            <div class="fw-bold">مدیریت بارنامه سوخت</div>
            <div class="text-muted small">فهرست و ثبت بارنامه‌ها</div>
          </div>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- ================= نمودارها ================= -->
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card panel-card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="iconify text-jade fs-5" data-icon="solar:pie-chart-bold"></span>
        <span class="fw-bold">وضعیت بارنامه‌ها</span>
      </div>
      <div class="card-body">
        <div id="chartWaybillStatus" style="width:100%; height:320px;"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card panel-card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="iconify text-purple fs-5" data-icon="solar:chart-2-bold"></span>
        <span class="fw-bold">توزیع نوع فرآورده</span>
      </div>
      <div class="card-body">
        <div id="chartProductType" style="width:100%; height:320px;"></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card panel-card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="iconify text-jade fs-5" data-icon="solar:graph-new-up-bold"></span>
        <span class="fw-bold">روند ثبت بارنامه (۶ ماه اخیر)</span>
      </div>
      <div class="card-body">
        <div id="chartMonthlyTrend" style="width:100%; height:320px;"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card panel-card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="iconify text-purple fs-5" data-icon="solar:users-group-rounded-bold"></span>
        <span class="fw-bold">توزیع کاربران بر اساس نقش</span>
      </div>
      <div class="card-body">
        <div id="chartUserRoles" style="width:100%; height:320px;"></div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  if (typeof echarts === 'undefined') return;
  var fontFamily = "'Vazirmatn', Tahoma, sans-serif";
  var palette = ['#0da678', '#7048c8', '#f2a93b', '#e05263', '#4c9ee8', '#8bd3c7'];

  // ---------- نمودار وضعیت بارنامه‌ها (دایره‌ای) ----------
  var statusEl = document.getElementById('chartWaybillStatus');
  if (statusEl) {
    var statusChart = echarts.init(statusEl, null, { renderer: 'svg' });
    var statusData = [
      { value: <?= (int)$waybillCounts['ثبت شده'] ?>, name: 'ثبت شده' },
      { value: <?= (int)$waybillCounts['ارسال شده'] ?>, name: 'ارسال شده' },
      { value: <?= (int)$waybillCounts['تحویل شده'] ?>, name: 'تحویل شده' },
      { value: <?= (int)$waybillCounts['لغو شده'] ?>, name: 'لغو شده' }
    ];
    statusChart.setOption({
      textStyle: { fontFamily: fontFamily },
      tooltip: { trigger: 'item' },
      legend: { bottom: 0, textStyle: { fontFamily: fontFamily } },
      color: palette,
      series: [{
        name: 'وضعیت بارنامه',
        type: 'pie',
        radius: ['45%', '72%'],
        avoidLabelOverlap: true,
        itemStyle: { borderRadius: 8, borderColor: '#fff', borderWidth: 2 },
        label: { show: true, formatter: '{b}: {c}', fontFamily: fontFamily },
        data: statusData
      }]
    });
    window.addEventListener('resize', function () { statusChart.resize(); });
  }

  // ---------- نمودار توزیع نوع فرآورده (میله‌ای) ----------
  var productEl = document.getElementById('chartProductType');
  if (productEl) {
    var productChart = echarts.init(productEl, null, { renderer: 'svg' });
    var productLabels = <?= json_encode(array_keys($productCounts), JSON_UNESCAPED_UNICODE) ?>;
    var productValues = <?= json_encode(array_values($productCounts)) ?>;
    productChart.setOption({
      textStyle: { fontFamily: fontFamily },
      tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
      grid: { left: 10, right: 10, bottom: 10, top: 20, containLabel: true },
      xAxis: { type: 'category', data: productLabels, axisLabel: { fontFamily: fontFamily } },
      yAxis: { type: 'value', axisLabel: { fontFamily: fontFamily } },
      series: [{
        name: 'تعداد بارنامه',
        type: 'bar',
        data: productValues,
        barMaxWidth: 46,
        itemStyle: { color: '#7048c8', borderRadius: [8, 8, 0, 0] }
      }]
    });
    window.addEventListener('resize', function () { productChart.resize(); });
  }

  // ---------- نمودار روند ثبت بارنامه (خطی) ----------
  var trendEl = document.getElementById('chartMonthlyTrend');
  if (trendEl) {
    var trendChart = echarts.init(trendEl, null, { renderer: 'svg' });
    var trendLabels = <?= json_encode(array_map('ym_to_jalali_label', array_keys($monthlyTrend)), JSON_UNESCAPED_UNICODE) ?>;
    var trendValues = <?= json_encode(array_values($monthlyTrend)) ?>;
    trendChart.setOption({
      textStyle: { fontFamily: fontFamily },
      tooltip: { trigger: 'axis' },
      grid: { left: 10, right: 20, bottom: 10, top: 20, containLabel: true },
      xAxis: { type: 'category', data: trendLabels, axisLabel: { fontFamily: fontFamily } },
      yAxis: { type: 'value', axisLabel: { fontFamily: fontFamily } },
      series: [{
        name: 'تعداد بارنامه',
        type: 'line',
        data: trendValues,
        smooth: true,
        symbol: 'circle',
        symbolSize: 8,
        lineStyle: { width: 3, color: '#0da678' },
        itemStyle: { color: '#0da678' },
        areaStyle: { color: 'rgba(13, 166, 120, 0.12)' }
      }]
    });
    window.addEventListener('resize', function () { trendChart.resize(); });
  }

  // ---------- نمودار توزیع کاربران بر اساس نقش (دایره‌ای) ----------
  var rolesEl = document.getElementById('chartUserRoles');
  if (rolesEl) {
    var rolesChart = echarts.init(rolesEl, null, { renderer: 'svg' });
    var rolesData = [
      { value: <?= (int)$counts['admin'] ?>, name: 'ادمین' },
      { value: <?= (int)$counts['region'] ?>, name: 'منطقه' },
      { value: <?= (int)$counts['operator'] ?>, name: 'متصدی' },
      { value: <?= (int)$counts['driver'] ?>, name: 'راننده' }
    ];
    rolesChart.setOption({
      textStyle: { fontFamily: fontFamily },
      tooltip: { trigger: 'item' },
      legend: { bottom: 0, textStyle: { fontFamily: fontFamily } },
      color: palette,
      series: [{
        name: 'نقش کاربران',
        type: 'pie',
        radius: '68%',
        itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 },
        label: { show: true, formatter: '{b}: {c}', fontFamily: fontFamily },
        data: rolesData
      }]
    });
    window.addEventListener('resize', function () { rolesChart.resize(); });
  }
})();
</script>

<div class="card panel-card">
  <div class="card-header d-flex align-items-center gap-2">
    <span class="iconify text-jade fs-5" data-icon="solar:clock-circle-bold"></span>
    <span class="fw-bold">آخرین کاربران ثبت‌شده</span>
  </div>
  <div class="card-body p-0">
    <?php if ($latest): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>نام و نام خانوادگی</th>
            <th>کد ملی</th>
            <th>نقش</th>
            <th>تاریخ ثبت</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($latest as $u): ?>
          <tr>
            <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
            <td class="ltr-text"><?= e($u['national_code']) ?></td>
            <td><span class="badge role-badge role-<?= e($u['user_type']) ?>"><?= e(user_type_label($u['user_type'])) ?></span></td>
            <td class="ltr-text text-muted small"><?= e(to_jalali_datetime_display($u['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted p-4 mb-0">هنوز کاربری ثبت نشده است. از دکمه «کاربر جدید» شروع کنید.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
