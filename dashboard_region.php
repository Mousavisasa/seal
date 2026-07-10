<?php
/**
 * داشبورد کاربر منطقه: آمار بارنامه‌ها و پلمپ‌های مرتبط با منطقه خودش
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/jalali.php';
require_once __DIR__ . '/helpers/charts.php';

require_login();

if (!is_region()) {
    set_flash('danger', 'شما دسترسی لازم برای این بخش را ندارید.');
    header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
    exit;
}

$myRegionId = session_region_id();
if ($myRegionId === null) {
    logout_user();
    session_start();
    set_flash('danger', 'حساب کاربری شما به هیچ منطقه‌ای متصل نیست. لطفاً با مدیر سامانه تماس بگیرید.');
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$regionName = '';
$waybillCounts = ['total' => 0, 'ثبت شده' => 0, 'ارسال شده' => 0, 'تحویل شده' => 0, 'لغو شده' => 0];
$productCounts = [];
$sealCounts = ['total' => 0, 'در انبار منطقه' => 0, 'الصاق شده' => 0, 'باطل شده' => 0, 'مفقود شده' => 0];
$recentWaybills = [];

try {
    $stmt = db()->prepare('SELECT region_name FROM regions WHERE region_code = ? LIMIT 1');
    $stmt->execute([$myRegionId]);
    $r = $stmt->fetch();
    $regionName = $r ? $r['region_name'] : '';

    $stmt = db()->prepare(
        "SELECT w.send_status, COUNT(*) AS c
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         WHERE ol.region_id = ? OR dl.region_id = ?
         GROUP BY w.send_status"
    );
    $stmt->execute([$myRegionId, $myRegionId]);
    foreach ($stmt->fetchAll() as $row) {
        $waybillCounts[$row['send_status']] = (int)$row['c'];
        $waybillCounts['total'] += (int)$row['c'];
    }

    foreach (PRODUCT_TYPES as $p) {
        $productCounts[$p] = 0;
    }
    $stmt = db()->prepare(
        "SELECT w.product_type, COUNT(*) AS c
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         WHERE ol.region_id = ? OR dl.region_id = ?
         GROUP BY w.product_type"
    );
    $stmt->execute([$myRegionId, $myRegionId]);
    foreach ($stmt->fetchAll() as $row) {
        $productCounts[$row['product_type']] = (int)$row['c'];
    }

    $stmt = db()->prepare('SELECT seal_status, COUNT(*) AS c FROM seals WHERE region_id = ? GROUP BY seal_status');
    $stmt->execute([$myRegionId]);
    foreach ($stmt->fetchAll() as $row) {
        $sealCounts[$row['seal_status']] = (int)$row['c'];
        $sealCounts['total'] += (int)$row['c'];
    }

    $stmt = db()->prepare(
        "SELECT w.waybill_number, w.send_status, w.issue_date, ol.title AS origin_title, dl.title AS destination_title
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         WHERE ol.region_id = ? OR dl.region_id = ?
         ORDER BY w.id DESC LIMIT 5"
    );
    $stmt->execute([$myRegionId, $myRegionId]);
    $recentWaybills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Region dashboard error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت اطلاعات رخ داد.');
}

$statusClassMap = [
    'ثبت شده'   => 'status-registered',
    'ارسال شده' => 'status-sent',
    'تحویل شده' => 'status-delivered',
    'لغو شده'   => 'status-cancelled',
];

$page_title = 'داشبورد منطقه';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">داشبورد منطقه</h1>
    <span class="dashboard-role-badge"><span class="iconify" data-icon="solar:map-point-bold"></span> منطقه: <?= e($regionName) ?></span>
  </div>
  <a href="<?= BASE_URL ?>/waybills/create.php" class="btn btn-primary d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:add-circle-bold"></span> بارنامه جدید
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
          <div class="stat-label">کل بارنامه‌های منطقه</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:delivery-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['ارسال شده']) ?></div>
          <div class="stat-label">در حال ارسال</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:box-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$sealCounts['total']) ?></div>
          <div class="stat-label">پلمپ‌های منطقه</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:link-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$sealCounts['الصاق شده']) ?></div>
          <div class="stat-label">پلمپ الصاق‌شده</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/waybills/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--purple-soft); color: var(--purple-dark);"><span class="iconify" data-icon="solar:fuel-bold"></span></span>
          <div><div class="fw-bold small">مدیریت بارنامه سوخت</div></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/seals/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--jade-soft); color: var(--jade-dark);"><span class="iconify" data-icon="solar:shield-keyhole-bold"></span></span>
          <div><div class="fw-bold small">انبارداری پلمپ</div></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/locations/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--purple-soft); color: var(--purple-dark);"><span class="iconify" data-icon="solar:signpost-bold"></span></span>
          <div><div class="fw-bold small">مبادی و مقاصد</div></div>
        </div>
      </div>
    </a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <?php
    render_chart_card_with_table(
        'chartRegionWaybillStatus',
        'وضعیت بارنامه‌های منطقه',
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
        'chartRegionProductType',
        'توزیع نوع فرآورده منطقه',
        'solar:chart-2-bold',
        array_keys($productCounts),
        array_values($productCounts),
        'تعداد بارنامه'
    );
    ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <?php
    render_chart_card_with_table(
        'chartRegionSealStatus',
        'وضعیت پلمپ‌های منطقه',
        'solar:shield-keyhole-bold',
        array_slice(array_keys($sealCounts), 1),
        array_slice(array_values($sealCounts), 1),
        'تعداد پلمپ'
    );
    ?>
  </div>
  <div class="col-lg-7">
    <div class="card panel-card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="iconify text-jade fs-5" data-icon="solar:clock-circle-bold"></span>
        <span class="fw-bold">آخرین بارنامه‌های منطقه</span>
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
                <th>تاریخ صدور</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentWaybills as $w): ?>
              <tr>
                <td class="ltr-text fw-bold"><?= e($w['waybill_number']) ?></td>
                <td class="small"><?= e($w['origin_title']) ?> ← <?= e($w['destination_title']) ?></td>
                <td><span class="status-badge <?= e($statusClassMap[$w['send_status']] ?? '') ?>"><?= e($w['send_status']) ?></span></td>
                <td class="ltr-text text-muted small"><?= e(to_jalali_display($w['issue_date'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p class="text-muted p-4 mb-0">هنوز بارنامه‌ای برای این منطقه ثبت نشده است.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function bootRegionCharts() {
  if (typeof window.whenEChartsReady !== 'function') {
    setTimeout(bootRegionCharts, 30);
    return;
  }
  window.whenEChartsReady(function () {
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

  function commonPieOption(data, palette) {
    return {
      textStyle: { fontFamily: fontFamily },
      tooltip: { trigger: 'item' },
      legend: { bottom: 0, textStyle: { fontFamily: fontFamily } },
      color: palette,
      series: [{
        type: 'pie',
        radius: ['45%', '72%'],
        avoidLabelOverlap: true,
        itemStyle: { borderRadius: 8, borderColor: '#fff', borderWidth: 2 },
        label: { show: true, formatter: '{b}: {c}', fontFamily: fontFamily },
        data: data
      }]
    };
  }

  var statusChart = initChart('chartRegionWaybillStatus');
  if (statusChart) {
    var statusData = [
      { value: <?= (int)$waybillCounts['ثبت شده'] ?>, name: 'ثبت شده' },
      { value: <?= (int)$waybillCounts['ارسال شده'] ?>, name: 'ارسال شده' },
      { value: <?= (int)$waybillCounts['تحویل شده'] ?>, name: 'تحویل شده' },
      { value: <?= (int)$waybillCounts['لغو شده'] ?>, name: 'لغو شده' }
    ];
    statusChart.setOption(commonPieOption(statusData, ['#7048c8', '#f2a93b', '#0da678', '#e05263']));
  }

  var productChart = initChart('chartRegionProductType');
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

  var sealChart = initChart('chartRegionSealStatus');
  if (sealChart) {
    var sealData = [
      { value: <?= (int)$sealCounts['در انبار منطقه'] ?>, name: 'در انبار منطقه' },
      { value: <?= (int)$sealCounts['الصاق شده'] ?>, name: 'الصاق شده' },
      { value: <?= (int)$sealCounts['باطل شده'] ?>, name: 'باطل شده' },
      { value: <?= (int)$sealCounts['مفقود شده'] ?>, name: 'مفقود شده' }
    ];
    sealChart.setOption(commonPieOption(sealData, ['#7048c8', '#0da678', '#e05263', '#8a8f98']));
  }
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
