<?php
/**
 * فهرست بارنامه‌های سوخت با جستجو و فیلتر وضعیت/فرآورده
 * کاربر منطقه فقط بارنامه‌هایی را می‌بیند که مبدا یا مقصد آن در منطقه خودش باشد.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';

require_waybill_access();

$myRegionId = session_region_id();

$waybills = [];

try {
    $sql = 'SELECT w.*,
                   ol.title AS origin_title, dl.title AS destination_title,
                   opOrig.first_name AS origin_operator_first, opOrig.last_name AS origin_operator_last,
                   opDest.first_name AS dest_operator_first, opDest.last_name AS dest_operator_last,
                   drU.first_name AS driver_first, drU.last_name AS driver_last,
                   orRg.region_name AS origin_region_name, dsRg.region_name AS destination_region_name,
                   ol.region_id AS origin_region_code, dl.region_id AS destination_region_code,
                   sl.seal_id AS attached_seal_id
            FROM fuel_waybills w
            INNER JOIN locations ol ON ol.id = w.origin_location_id
            INNER JOIN locations dl ON dl.id = w.destination_location_id
            INNER JOIN regions orRg ON orRg.region_code = ol.region_id
            INNER JOIN regions dsRg ON dsRg.region_code = dl.region_id
            LEFT JOIN users opOrig ON opOrig.id = w.origin_operator_user_id
            LEFT JOIN users opDest ON opDest.id = w.destination_operator_user_id
            LEFT JOIN users drU ON drU.id = w.driver_user_id
            LEFT JOIN seals sl ON sl.fuel_waybill_id = w.id
            WHERE 1=1';
    $params = [];

    if ($myRegionId !== null) {
        $sql .= ' AND (ol.region_id = ? OR dl.region_id = ?)';
        $params[] = $myRegionId;
        $params[] = $myRegionId;
    }
    $sql .= ' ORDER BY w.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $waybills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Waybills list error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست بارنامه‌ها رخ داد.');
}

$statusClassMap = [
    'ثبت شده'   => 'status-registered',
    'ارسال شده' => 'status-sent',
    'تحویل شده' => 'status-delivered',
    'لغو شده'   => 'status-cancelled',
];

$page_title = 'مدیریت بارنامه سوخت';
$active = 'waybills';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">مدیریت بارنامه سوخت</h1>
    <p class="text-muted small mb-0">تعداد کل: <?= e((string)count($waybills)) ?> بارنامه</p>
  </div>
  <a href="<?= BASE_URL ?>/waybills/create.php" class="btn btn-primary d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:add-circle-bold"></span> بارنامه جدید
  </a>
</div>

<?= render_flash() ?>

<?php if ($waybills): ?>
<div class="filter-bar mb-3">
  <div class="input-group" style="max-width: 340px;">
    <span class="input-group-text"><span class="iconify" data-icon="solar:magnifer-bold"></span></span>
    <input type="text" class="form-control" id="waybillsSearch" placeholder="جستجوی سراسری (شماره، مبدا، مقصد، وضعیت و...)">
  </div>
  <p class="text-muted small mb-0 mt-2">برای فیلتر دقیق‌تر روی هر ستون، از فیلترهای زیر عنوان ستون‌ها استفاده کنید.</p>
</div>
<?php endif; ?>

<div class="card panel-card">
  <div class="card-body p-0">
    <?php if ($waybills): ?>
    <div id="gridWaybills" class="seal-ag-grid"></div>
    <?php else: ?>
      <div class="text-center text-muted p-5">
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:fuel-line-duotone"></span>
        هیچ بارنامه‌ای یافت نشد.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($waybills): ?>
<script>
(function () {
  var canAssignOperator = <?= can_assign_operator() ? 'true' : 'false' ?>;
  var rows = <?= json_encode(array_map(function ($w) use ($statusClassMap) {
      $destOperatorHtml = $w['dest_operator_first']
          ? '<span>' . e($w['dest_operator_first'] . ' ' . $w['dest_operator_last']) . '</span>'
          : '<span class="text-muted">—</span>';
      if (can_assign_operator()) {
          $destOperatorHtml = '<div class="d-flex align-items-center gap-2">' . $destOperatorHtml
              . '<a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1" href="' . BASE_URL . '/waybills/assign_operator.php?id=' . (int)$w['id'] . '&side=destination" title="تخصیص یا تغییر متصدی مقصد">'
              . '<span class="iconify" data-icon="' . ($w['dest_operator_first'] ? 'solar:pen-bold' : 'solar:add-circle-bold') . '"></span> '
              . ($w['dest_operator_first'] ? 'تغییر' : 'تخصیص') . '</a></div>';
      }
      return [
          'waybill_number' => e($w['waybill_number']),
          'origin' => e($w['origin_title']) . ' <span class="text-muted small">(' . e($w['origin_region_name']) . ')</span>',
          'destination' => e($w['destination_title']) . ' <span class="text-muted small">(' . e($w['destination_region_name']) . ')</span>',
          'distance' => number_format((float)$w['distance_km'], 2),
          'product' => '<span class="product-badge ' . e(product_badge_class($w['product_type'])) . '">' . e($w['product_type']) . '</span>',
          'issue_date' => to_jalali_display($w['issue_date']),
          'status' => '<span class="status-badge ' . e($statusClassMap[$w['send_status']] ?? '') . '">' . e($w['send_status']) . '</span>',
          'seal' => $w['attached_seal_id'] ? e($w['attached_seal_id']) : '<span class="text-muted">—</span>',
          'origin_operator' => $w['origin_operator_first'] ? e($w['origin_operator_first'] . ' ' . $w['origin_operator_last']) : '<span class="text-muted">—</span>',
          'dest_operator' => $destOperatorHtml,
          'driver' => $w['driver_first'] ? e($w['driver_first'] . ' ' . $w['driver_last']) : '<span class="text-muted">—</span>',
          'actions' => '<div class="d-flex gap-1 flex-wrap">'
              . '<a class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" href="' . BASE_URL . '/waybills/edit.php?id=' . (int)$w['id'] . '"><span class="iconify" data-icon="solar:pen-bold"></span> ویرایش</a>'
              . '<form method="post" action="' . BASE_URL . '/waybills/delete.php" class="d-inline" data-delete-form data-confirm="' . e('آیا از حذف بارنامه «' . $w['waybill_number'] . '» مطمئن هستید؟') . '">'
              . csrf_field()
              . '<input type="hidden" name="id" value="' . (int)$w['id'] . '">'
              . '<button type="submit" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1"><span class="iconify" data-icon="solar:trash-bin-trash-bold"></span> حذف</button>'
              . '</form></div>',
      ];
  }, $waybills), JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?>;
  var columnDefs = [
    { field: 'waybill_number', headerName: 'شماره بارنامه', flex: 1, html: true, cellClass: 'ltr-text fw-bold' },
    { field: 'origin', headerName: 'مبدا', flex: 1.5, html: true },
    { field: 'destination', headerName: 'مقصد', flex: 1.5, html: true },
    { field: 'distance', headerName: 'مسافت (کیلومتر)', flex: 1, cellClass: 'ltr-text' },
    { field: 'product', headerName: 'فرآورده', flex: 1, html: true },
    { field: 'issue_date', headerName: 'تاریخ صدور', flex: 1, cellClass: 'ltr-text text-muted small' },
    { field: 'status', headerName: 'وضعیت', flex: 1, html: true },
    { field: 'seal', headerName: 'پلمپ', flex: 1, html: true, cellClass: 'ltr-text' },
    { field: 'origin_operator', headerName: 'متصدی مبدا', flex: 1, html: true },
    { field: 'dest_operator', headerName: 'متصدی مقصد', flex: 1.5, html: true, filter: false },
    { field: 'driver', headerName: 'راننده', flex: 1, html: true },
    { field: 'actions', headerName: 'عملیات', flex: 2, html: true, sortable: false, filter: false }
  ];
  function boot() {
    if (typeof sealInitDataGrid === 'undefined') { setTimeout(boot, 30); return; }
    sealInitDataGrid('gridWaybills', columnDefs, rows, { searchInputId: 'waybillsSearch' });
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
