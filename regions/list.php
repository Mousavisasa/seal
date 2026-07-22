<?php
/**
 * فهرست مناطق با جستجوی ساده
 * توجه: region_code خودش کلید اصلی جدول است (طبق ساختار جدید)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

$regions = [];

try {
    $stmt = db()->query('SELECT * FROM regions ORDER BY region_code ASC');
    $regions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Regions list error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست مناطق رخ داد.');
}

$page_title = 'مدیریت مناطق';
$active = 'regions';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">مدیریت مناطق</h1>
    <p class="text-muted small mb-0">تعداد کل: <?= e((string)count($regions)) ?> منطقه</p>
  </div>
  <a href="<?= BASE_URL ?>/regions/create.php" class="btn btn-primary d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:add-circle-bold"></span> منطقه جدید
  </a>
</div>

<?= render_flash() ?>

<?php if ($regions): ?>
<div class="filter-bar mb-3">
  <div class="input-group" style="max-width: 340px;">
    <span class="input-group-text"><span class="iconify" data-icon="solar:magnifer-bold"></span></span>
    <input type="text" class="form-control" id="regionsSearch" placeholder="جستجو در کد یا نام منطقه">
  </div>
</div>
<?php endif; ?>

<div class="card panel-card">
  <div class="card-body p-0">
    <?php if ($regions): ?>
    <div id="gridRegions" class="seal-ag-grid"></div>
    <?php else: ?>
      <div class="text-center text-muted p-5">
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:map-point-line-duotone"></span>
        هیچ منطقه‌ای یافت نشد.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($regions): ?>
<script>
(function () {
  var rows = <?= json_encode(array_map(function ($r) {
      return [
          'region_code' => e((string)$r['region_code']),
          'region_name' => e($r['region_name']),
          'actions' => '<div class="d-flex gap-1 flex-wrap">'
              . '<a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1" href="' . BASE_URL . '/regions/edit.php?code=' . (int)$r['region_code'] . '"><span class="iconify" data-icon="solar:pen-bold"></span> ویرایش</a>'
              . '<form method="post" action="' . BASE_URL . '/regions/delete.php" class="d-inline" data-delete-form data-confirm="' . e('آیا از حذف منطقه «' . $r['region_name'] . '» مطمئن هستید؟') . '">'
              . csrf_field()
              . '<input type="hidden" name="region_code" value="' . (int)$r['region_code'] . '">'
              . '<button type="submit" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1"><span class="iconify" data-icon="solar:trash-bin-trash-bold"></span> حذف</button>'
              . '</form></div>',
      ];
  }, $regions), JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?>;
  var columnDefs = [
    { field: 'region_code', headerName: 'کد منطقه', flex: 1, html: true, cellClass: 'ltr-text fw-bold' },
    { field: 'region_name', headerName: 'نام منطقه', flex: 2, html: true },
    { field: 'actions', headerName: 'عملیات', flex: 1.5, html: true, sortable: false, filter: false }
  ];
  function boot() {
    if (typeof sealInitDataGrid === 'undefined') { setTimeout(boot, 30); return; }
    sealInitDataGrid('gridRegions', columnDefs, rows, { searchInputId: 'regionsSearch' });
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
