<?php
/**
 * داشبورد ادمین: نمای کامل سامانه (کاربران، بارنامه‌ها، پلمپ‌ها)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';
require_once __DIR__ . '/../helpers/charts.php';

require_admin();

$counts = ['total' => 0, 'admin' => 0, 'driver' => 0, 'operator' => 0, 'region' => 0];
$latest = [];
$waybillCounts = ['total' => 0, 'ثبت شده' => 0, 'ارسال شده' => 0, 'تحویل شده' => 0, 'لغو شده' => 0];
$productCounts = [];
$monthlyTrend = [];
$sealCounts = ['total' => 0, 'در انبار مرکزی' => 0, 'در انبار منطقه' => 0, 'الصاق شده' => 0, 'باطل شده' => 0, 'مفقود شده' => 0];

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

    $sRows = db()->query('SELECT seal_status, COUNT(*) AS c FROM seals GROUP BY seal_status')->fetchAll();
    foreach ($sRows as $row) {
        $sealCounts[$row['seal_status']] = (int)$row['c'];
        $sealCounts['total'] += (int)$row['c'];
    }
} catch (PDOException $e) {
    error_log('Admin dashboard error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت اطلاعات رخ داد.');
}

$page_title = 'داشبورد ادمین';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">داشبورد ادمین</h1>
    <span class="dashboard-role-badge"><span class="iconify" data-icon="solar:shield-user-bold"></span> نمای کامل سامانه</span>
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
        <span class="stat-icon"><span class="iconify" data-icon="solar:box-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$sealCounts['total']) ?></div>
          <div class="stat-label">کل پلمپ‌ها</div>
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
          <div class="stat-label">بارنامه تحویل‌شده</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <a href="<?= BASE_URL ?>/regions/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--purple-soft); color: var(--purple-dark);"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
          <div><div class="fw-bold small">مدیریت مناطق</div></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-3">
    <a href="<?= BASE_URL ?>/locations/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--jade-soft); color: var(--jade-dark);"><span class="iconify" data-icon="solar:signpost-bold"></span></span>
          <div><div class="fw-bold small">مبادی و مقاصد</div></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-3">
    <a href="<?= BASE_URL ?>/waybills/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--purple-soft); color: var(--purple-dark);"><span class="iconify" data-icon="solar:fuel-bold"></span></span>
          <div><div class="fw-bold small">بارنامه سوخت</div></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-3">
    <a href="<?= BASE_URL ?>/seals/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--jade-soft); color: var(--jade-dark);"><span class="iconify" data-icon="solar:shield-keyhole-bold"></span></span>
          <div><div class="fw-bold small">انبارداری پلمپ</div></div>
        </div>
      </div>
    </a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <?php
    render_chart_card_with_table(
        'chartWaybillStatus',
        'وضعیت بارنامه‌ها',
        'solar:pie-chart-bold',
        array_keys($waybillCounts) === ['total'] ? [] : array_slice(array_keys($waybillCounts), 1),
        array_slice(array_values($waybillCounts), 1),
        'تعداد بارنامه'
    );
    ?>
  </div>
  <div class="col-lg-6">
    <?php
    render_chart_card_with_table(
        'chartProductType',
        'توزیع نوع فرآورده',
        'solar:chart-2-bold',
        array_keys($productCounts),
        array_values($productCounts),
        'تعداد بارنامه'
    );
    ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <?php
    render_chart_card_with_table(
        'chartMonthlyTrend',
        'روند ثبت بارنامه (۶ ماه اخیر)',
        'solar:graph-new-up-bold',
        array_map('ym_to_jalali_label', array_keys($monthlyTrend)),
        array_values($monthlyTrend),
        'تعداد بارنامه'
    );
    ?>
  </div>
  <div class="col-lg-5">
    <?php
    render_chart_card_with_table(
        'chartUserRoles',
        'توزیع کاربران بر اساس نقش',
        'solar:users-group-rounded-bold',
        ['ادمین', 'منطقه', 'متصدی', 'راننده'],
        [$counts['admin'], $counts['region'], $counts['operator'], $counts['driver']],
        'تعداد کاربر'
    );
    ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <?php
    render_chart_card_with_table(
        'chartSealStatus',
        'وضعیت پلمپ‌ها',
        'solar:shield-keyhole-bold',
        array_keys($sealCounts) === ['total'] ? [] : array_slice(array_keys($sealCounts), 1),
        array_slice(array_values($sealCounts), 1),
        'تعداد پلمپ'
    );
    ?>
  </div>
  <div class="col-lg-6">
    <div class="card panel-card h-100">
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
          <p class="text-muted p-4 mb-0">هنوز کاربری ثبت نشده است.</p>
        <?php endif; ?>
      </div>
    </div>
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
  var neutralPalette = ['#0da678', '#7048c8', '#f2a93b', '#e05263', '#4c9ee8', '#8bd3c7'];

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

  // وضعیت بارنامه‌ها
  var statusChart = initChart('chartWaybillStatus');
  if (statusChart) {
    var statusData = [
      { value: <?= (int)$waybillCounts['ثبت شده'] ?>, name: 'ثبت شده' },
      { value: <?= (int)$waybillCounts['ارسال شده'] ?>, name: 'ارسال شده' },
      { value: <?= (int)$waybillCounts['تحویل شده'] ?>, name: 'تحویل شده' },
      { value: <?= (int)$waybillCounts['لغو شده'] ?>, name: 'لغو شده' }
    ];
    statusChart.setOption(commonPieOption(statusData, ['#7048c8', '#f2a93b', '#0da678', '#e05263']));
  }

  // توزیع نوع فرآورده — با رنگ‌های اختصاصی درخواستی
  var productChart = initChart('chartProductType');
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

  // روند ثبت بارنامه
  var trendChart = initChart('chartMonthlyTrend');
  if (trendChart) {
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
        areaStyle: {
          color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: 'rgba(13, 166, 120, 0.25)' },
            { offset: 1, color: 'rgba(13, 166, 120, 0.02)' }
          ])
        }
      }]
    });
  }

  // توزیع کاربران بر اساس نقش
  var rolesChart = initChart('chartUserRoles');
  if (rolesChart) {
    var rolesData = [
      { value: <?= (int)$counts['admin'] ?>, name: 'ادمین' },
      { value: <?= (int)$counts['region'] ?>, name: 'منطقه' },
      { value: <?= (int)$counts['operator'] ?>, name: 'متصدی' },
      { value: <?= (int)$counts['driver'] ?>, name: 'راننده' }
    ];
    rolesChart.setOption(commonPieOption(rolesData, neutralPalette));
  }

  // وضعیت پلمپ‌ها
  var sealChart = initChart('chartSealStatus');
  if (sealChart) {
    var sealData = [
      { value: <?= (int)$sealCounts['در انبار مرکزی'] ?>, name: 'در انبار مرکزی' },
      { value: <?= (int)$sealCounts['در انبار منطقه'] ?>, name: 'در انبار منطقه' },
      { value: <?= (int)$sealCounts['الصاق شده'] ?>, name: 'الصاق شده' },
      { value: <?= (int)$sealCounts['باطل شده'] ?>, name: 'باطل شده' },
      { value: <?= (int)$sealCounts['مفقود شده'] ?>, name: 'مفقود شده' }
    ];
    sealChart.setOption(commonPieOption(sealData, ['#0da678', '#7048c8', '#4c9ee8', '#e05263', '#8a8f98']));
  }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
