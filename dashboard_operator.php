<?php
/**
 * داشبورد متصدی: آمار بارنامه‌هایی که به‌عنوان متصدی مبدا یا مقصد به او تخصیص یافته
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/jalali.php';
require_once __DIR__ . '/helpers/charts.php';

require_login();

if (!is_operator()) {
    set_flash('danger', 'شما دسترسی لازم برای این بخش را ندارید.');
    header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
    exit;
}

$myId = (int)$_SESSION['user_id'];

$waybillCounts = ['total' => 0, 'ثبت شده' => 0, 'ارسال شده' => 0, 'تحویل شده' => 0, 'لغو شده' => 0];
$productCounts = [];
$roleCounts = ['origin' => 0, 'destination' => 0];
$driverAssignedCount = 0;
$recentWaybills = [];

try {
    $stmt = db()->prepare(
        'SELECT send_status, product_type,
                (origin_operator_user_id = ?) AS is_origin,
                (destination_operator_user_id = ?) AS is_destination,
                (driver_user_id IS NOT NULL) AS has_driver
         FROM fuel_waybills
         WHERE origin_operator_user_id = ? OR destination_operator_user_id = ?'
    );
    $stmt->execute([$myId, $myId, $myId, $myId]);
    $rows = $stmt->fetchAll();

    foreach (PRODUCT_TYPES as $p) {
        $productCounts[$p] = 0;
    }

    foreach ($rows as $row) {
        $waybillCounts[$row['send_status']] = ($waybillCounts[$row['send_status']] ?? 0) + 1;
        $waybillCounts['total']++;
        $productCounts[$row['product_type']] = ($productCounts[$row['product_type']] ?? 0) + 1;
        if ((int)$row['is_origin'] === 1) {
            $roleCounts['origin']++;
        }
        if ((int)$row['is_destination'] === 1) {
            $roleCounts['destination']++;
        }
        if ((int)$row['has_driver'] === 1) {
            $driverAssignedCount++;
        }
    }

    $stmt = db()->prepare(
        'SELECT w.waybill_number, w.send_status, w.issue_date, ol.title AS origin_title, dl.title AS destination_title,
                drU.first_name AS driver_first, drU.last_name AS driver_last
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         LEFT JOIN users drU ON drU.id = w.driver_user_id
         WHERE w.origin_operator_user_id = ? OR w.destination_operator_user_id = ?
         ORDER BY w.id DESC LIMIT 5'
    );
    $stmt->execute([$myId, $myId]);
    $recentWaybills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Operator dashboard error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت اطلاعات رخ داد.');
}

$statusClassMap = [
    'ثبت شده'   => 'status-registered',
    'ارسال شده' => 'status-sent',
    'تحویل شده' => 'status-delivered',
    'لغو شده'   => 'status-cancelled',
];

$page_title = 'داشبورد متصدی';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">داشبورد متصدی</h1>
    <span class="dashboard-role-badge"><span class="iconify" data-icon="solar:user-id-bold"></span> بارنامه‌های تخصیص‌یافته به شما</span>
  </div>
  <a href="<?= BASE_URL ?>/waybills/my_waybills.php" class="btn btn-primary d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:fuel-bold"></span> بارنامه‌های من
  </a>
</div>

<?= render_flash() ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:fuel-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['total']) ?></div>
          <div class="stat-label">کل بارنامه‌های من</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:point-on-map-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$roleCounts['origin']) ?></div>
          <div class="stat-label">به‌عنوان متصدی مبدا</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:point-on-map-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$roleCounts['destination']) ?></div>
          <div class="stat-label">به‌عنوان متصدی مقصد</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:bus-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$driverAssignedCount) ?></div>
          <div class="stat-label">با راننده تخصیص‌یافته</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <?php
    render_chart_card_with_table(
        'chartOperatorStatus',
        'وضعیت بارنامه‌های من',
        'solar:pie-chart-bold',
        array_slice(array_keys($waybillCounts), 1),
        array_slice(array_values($waybillCounts), 1),
        'تعداد بارنامه'
    );
    ?>
  </div>
  <div class="col-lg-6">
    <?php
    render_chart_card_with_table(
        'chartOperatorProduct',
        'توزیع نوع فرآورده',
        'solar:chart-2-bold',
        array_keys($productCounts),
        array_values($productCounts),
        'تعداد بارنامه'
    );
    ?>
  </div>
</div>

<div class="card panel-card">
  <div class="card-header d-flex align-items-center gap-2">
    <span class="iconify text-jade fs-5" data-icon="solar:clock-circle-bold"></span>
    <span class="fw-bold">آخرین بارنامه‌های تخصیص‌یافته</span>
  </div>
  <div class="card-body p-0">
    <?php if ($recentWaybills): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>شماره بارنامه</th>
            <th>مسیر</th>
            <th>وضعیت</th>
            <th>راننده</th>
            <th>تاریخ صدور</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentWaybills as $w): ?>
          <tr>
            <td class="ltr-text fw-bold"><?= e($w['waybill_number']) ?></td>
            <td class="small"><?= e($w['origin_title']) ?> ← <?= e($w['destination_title']) ?></td>
            <td><span class="status-badge <?= e($statusClassMap[$w['send_status']] ?? '') ?>"><?= e($w['send_status']) ?></span></td>
            <td><?= $w['driver_first'] ? e($w['driver_first'] . ' ' . $w['driver_last']) : '<span class="text-muted">تخصیص‌نیافته</span>' ?></td>
            <td class="ltr-text text-muted small"><?= e(to_jalali_display($w['issue_date'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted p-4 mb-0">هنوز بارنامه‌ای به شما تخصیص داده نشده است.</p>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  function boot() {
    if (typeof echarts === 'undefined') {
      setTimeout(boot, 30);
      return;
    }
    requestAnimationFrame(function () {
      requestAnimationFrame(renderCharts);
    });
  }

  function renderCharts() {
  var fontFamily = "'Vazirmatn', Tahoma, sans-serif";
  var productColors = <?= json_encode(PRODUCT_COLORS, JSON_UNESCAPED_UNICODE) ?>;

  window.__registeredCharts = window.__registeredCharts || [];

  function initChart(id) {
    var el = document.getElementById(id);
    if (!el) return null;
    var chart = echarts.init(el, null, { renderer: 'svg' });
    window.__registeredCharts.push(chart);
    return chart;
  }

  var statusChart = initChart('chartOperatorStatus');
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
  }

  var productChart = initChart('chartOperatorProduct');
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
        name: 'تعداد بارنامه',
        type: 'bar',
        data: productValues.map(function (v, i) {
          return { value: v, itemStyle: { color: productBarColors[i], borderRadius: [8, 8, 0, 0] } };
        }),
        barMaxWidth: 46
      }]
    });
  }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
