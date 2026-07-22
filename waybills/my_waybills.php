<?php
/**
 * فهرست بارنامه‌های تخصیص‌یافته به کاربر متصدی جاری
 * متصدی بارنامه‌هایی را می‌بیند که به‌عنوان متصدی مبدا یا متصدی مقصد آن تخصیص یافته است.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';

require_login();

if (!is_operator() && !is_admin()) {
    set_flash('danger', 'شما دسترسی لازم برای این بخش را ندارید.');
    header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
    exit;
}

$waybills = [];
$statusClassMap = [
    'ثبت شده'   => 'status-registered',
    'ارسال شده' => 'status-sent',
    'تحویل شده' => 'status-delivered',
    'لغو شده'   => 'status-cancelled',
];

try {
    $stmt = db()->prepare(
        'SELECT w.*, ol.title AS origin_title, dl.title AS destination_title,
                drU.first_name AS driver_first, drU.last_name AS driver_last
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         LEFT JOIN users drU ON drU.id = w.driver_user_id
         WHERE w.origin_operator_user_id = ? OR w.destination_operator_user_id = ?
         ORDER BY w.id DESC'
    );
    $stmt->execute([(int)$_SESSION['user_id'], (int)$_SESSION['user_id']]);
    $waybills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('My waybills error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست بارنامه‌ها رخ داد.');
}

$page_title = 'بارنامه‌های من';
$active = 'my-waybills';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">بارنامه‌های تخصیص‌یافته به من</h1>
  <p class="text-muted small mb-0">تعداد کل: <?= e((string)count($waybills)) ?> بارنامه (به‌عنوان متصدی مبدا یا مقصد)</p>
</div>

<?= render_flash() ?>

<?php if ($waybills): ?>
<div class="filter-bar mb-3">
  <div class="input-group" style="max-width: 340px;">
    <span class="input-group-text"><span class="iconify" data-icon="solar:magnifer-bold"></span></span>
    <input type="text" class="form-control" id="myWaybillsSearch" placeholder="جستجوی سراسری">
  </div>
</div>
<?php endif; ?>

<div class="card panel-card">
  <div class="card-body p-0">
    <?php if ($waybills): ?>
    <div id="gridMyWaybills" class="seal-ag-grid"></div>
    <?php else: ?>
      <div class="text-center text-muted p-5">
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:fuel-line-duotone"></span>
        هیچ بارنامه‌ای به شما تخصیص داده نشده است.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($waybills): ?>
<script>
(function () {
  var rows = <?= json_encode(array_map(function ($w) use ($statusClassMap) {
      $myRoles = [];
      if ((int)$w['origin_operator_user_id'] === (int)$_SESSION['user_id']) {
          $myRoles[] = 'متصدی مبدا';
      }
      if ((int)$w['destination_operator_user_id'] === (int)$_SESSION['user_id']) {
          $myRoles[] = 'متصدی مقصد';
      }
      $rolesHtml = implode(' ', array_map(function ($role) {
          return '<span class="badge role-badge role-region">' . e($role) . '</span>';
      }, $myRoles));
      return [
          'waybill_number' => e($w['waybill_number']),
          'origin' => e($w['origin_title']),
          'destination' => e($w['destination_title']),
          'roles' => $rolesHtml,
          'product' => '<span class="product-badge ' . e(product_badge_class($w['product_type'])) . '">' . e($w['product_type']) . '</span>',
          'issue_date' => to_jalali_display($w['issue_date']),
          'status' => '<span class="status-badge ' . e($statusClassMap[$w['send_status']] ?? '') . '">' . e($w['send_status']) . '</span>',
          'driver' => $w['driver_first'] ? e($w['driver_first'] . ' ' . $w['driver_last']) : '<span class="text-muted">تخصیص‌نیافته</span>',
          'actions' => '<a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1" href="' . BASE_URL . '/waybills/assign_driver.php?id=' . (int)$w['id'] . '"><span class="iconify" data-icon="solar:bus-bold"></span> تخصیص راننده</a>',
      ];
  }, $waybills), JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?>;
  var columnDefs = [
    { field: 'waybill_number', headerName: 'شماره بارنامه', flex: 1, html: true, cellClass: 'ltr-text fw-bold' },
    { field: 'origin', headerName: 'مبدا', flex: 1, html: true },
    { field: 'destination', headerName: 'مقصد', flex: 1, html: true },
    { field: 'roles', headerName: 'نقش من', flex: 1, html: true },
    { field: 'product', headerName: 'فرآورده', flex: 1, html: true },
    { field: 'issue_date', headerName: 'تاریخ صدور', flex: 1, cellClass: 'ltr-text text-muted small' },
    { field: 'status', headerName: 'وضعیت', flex: 1, html: true },
    { field: 'driver', headerName: 'راننده', flex: 1, html: true },
    { field: 'actions', headerName: 'عملیات', flex: 1, html: true, sortable: false, filter: false }
  ];
  function boot() {
    if (typeof sealInitDataGrid === 'undefined') { setTimeout(boot, 30); return; }
    sealInitDataGrid('gridMyWaybills', columnDefs, rows, { searchInputId: 'myWaybillsSearch' });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
