<?php
/**
 * داشبورد راننده: آمار سفرهای تخصیص‌یافته به او
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/jalali.php';
require_once __DIR__ . '/helpers/charts.php';

require_login();

if (!is_driver()) {
    set_flash('danger', 'شما دسترسی لازم برای این بخش را ندارید.');
    header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
    exit;
}

$myId = (int)$_SESSION['user_id'];

$waybillCounts = ['total' => 0, 'ثبت شده' => 0, 'ارسال شده' => 0, 'تحویل شده' => 0, 'لغو شده' => 0];
$productCounts = [];
$totalDistance = 0.0;
$recentTrips = [];

try {
    $stmt = db()->prepare('SELECT send_status, product_type, distance_km FROM fuel_waybills WHERE driver_user_id = ?');
    $stmt->execute([$myId]);
    $rows = $stmt->fetchAll();

    foreach (PRODUCT_TYPES as $p) {
        $productCounts[$p] = 0;
    }

    foreach ($rows as $row) {
        $waybillCounts[$row['send_status']] = ($waybillCounts[$row['send_status']] ?? 0) + 1;
        $waybillCounts['total']++;
        $productCounts[$row['product_type']] = ($productCounts[$row['product_type']] ?? 0) + 1;
        $totalDistance += (float)$row['distance_km'];
    }

    $stmt = db()->prepare(
        'SELECT w.waybill_number, w.send_status, w.issue_date, w.distance_km,
                ol.title AS origin_title, dl.title AS destination_title
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         WHERE w.driver_user_id = ?
         ORDER BY w.id DESC LIMIT 5'
    );
    $stmt->execute([$myId]);
    $recentTrips = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Driver dashboard error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت اطلاعات رخ داد.');
}

$statusClassMap = [
    'ثبت شده'   => 'status-registered',
    'ارسال شده' => 'status-sent',
    'تحویل شده' => 'status-delivered',
    'لغو شده'   => 'status-cancelled',
];

$page_title = 'داشبورد راننده';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">داشبورد راننده</h1>
    <span class="dashboard-role-badge"><span class="iconify" data-icon="solar:bus-bold"></span> خلاصه سفرهای شما</span>
  </div>
  <a href="<?= BASE_URL ?>/waybills/my_trips.php" class="btn btn-primary d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:map-arrow-square-bold"></span> سفرهای من
  </a>
</div>

<?= render_flash() ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:bus-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['total']) ?></div>
          <div class="stat-label">کل سفرهای من</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:play-circle-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['ارسال شده']) ?></div>
          <div class="stat-label">در حال انجام</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:check-circle-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['تحویل شده']) ?></div>
          <div class="stat-label">تکمیل‌شده</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:ruler-bold"></span></span>
        <div>
          <div class="stat-number ltr-text"><?= e(number_format($totalDistance, 0)) ?></div>
          <div class="stat-label">مجموع کیلومتر</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <?php
    render_chart_card_with_table(
        'chartDriverStatus',
        'وضعیت سفرهای من',
        'solar:pie-chart-bold',
        array_slice(array_keys($waybillCounts), 1),
        array_slice(array_values($waybillCounts), 1),
        'تعداد سفر'
    );
    ?>
  </div>
  <div class="col-lg-6">
    <?php
    render_chart_card_with_table(
        'chartDriverProduct',
        'توزیع نوع فرآورده حمل‌شده',
        'solar:chart-2-bold',
        array_keys($productCounts),
        array_values($productCounts),
        'تعداد سفر'
    );
    ?>
  </div>
</div>

<div class="card panel-card">
  <div class="card-header d-flex align-items-center gap-2">
    <span class="iconify text-jade fs-5" data-icon="solar:clock-circle-bold"></span>
    <span class="fw-bold">آخرین سفرهای من</span>
  </div>
  <div class="card-body p-0">
    <?php if ($recentTrips): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>شماره بارنامه</th>
            <th>مسیر</th>
            <th>مسافت</th>
            <th>وضعیت</th>
            <th>تاریخ صدور</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentTrips as $w): ?>
          <tr>
            <td class="ltr-text fw-bold"><?= e($w['waybill_number']) ?></td>
            <td class="small"><?= e($w['origin_title']) ?> ← <?= e($w['destination_title']) ?></td>
            <td class="ltr-text"><?= e(number_format((float)$w['distance_km'], 0)) ?> کیلومتر</td>
            <td><span class="status-badge <?= e($statusClassMap[$w['send_status']] ?? '') ?>"><?= e($w['send_status']) ?></span></td>
            <td class="ltr-text text-muted small"><?= e(to_jalali_display($w['issue_date'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted p-4 mb-0">هنوز سفری به شما تخصیص داده نشده است.</p>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  if (typeof echarts === 'undefined') return;
  var fontFamily = "'Vazirmatn', Tahoma, sans-serif";
  var productColors = <?= json_encode(PRODUCT_COLORS, JSON_UNESCAPED_UNICODE) ?>;

  function initChart(id) {
    var el = document.getElementById(id);
    if (!el) return null;
    return echarts.init(el, null, { renderer: 'svg' });
  }

  var statusChart = initChart('chartDriverStatus');
  if (statusChart) {
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
      color: ['#7048c8', '#f2a93b', '#0da678', '#e05263'],
      series: [{
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

  var productChart = initChart('chartDriverProduct');
  if (productChart) {
    var productLabels = <?= json_encode(array_keys($productCounts), JSON_UNESCAPED_UNICODE) ?>;
    var productValues = <?= json_encode(array_values($productCounts)) ?>;
    var productBarColors = productLabels.map(function (label) { return productColors[label] || '#c7ccd1'; });
    productChart.setOption({
      textStyle: { fontFamily: fontFamily },
      tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
      grid: { left: 10, right: 10, bottom: 10, top: 20, containLabel: true },
      xAxis: { type: 'category', data: productLabels, axisLabel: { fontFamily: fontFamily } },
      yAxis: { type: 'value', axisLabel: { fontFamily: fontFamily } },
      series: [{
        name: 'تعداد سفر',
        type: 'bar',
        data: productValues.map(function (v, i) {
          return { value: v, itemStyle: { color: productBarColors[i], borderRadius: [8, 8, 0, 0] } };
        }),
        barMaxWidth: 46
      }]
    });
    window.addEventListener('resize', function () { productChart.resize(); });
  }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
