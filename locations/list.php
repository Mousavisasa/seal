<?php
/**
 * فهرست مبادی/مقاصد با جستجو و فیلتر منطقه
 * locations.region_id به regions.region_code ارجاع دارد
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

$search = trim((string)($_GET['q'] ?? ''));
$regionFilter = (int)($_GET['region_id'] ?? 0);
$locations = [];
$regions = [];

try {
    $regions = db()->query('SELECT region_code, region_name FROM regions ORDER BY region_name')->fetchAll();

    $sql = 'SELECT l.*, r.region_name
            FROM locations l
            INNER JOIN regions r ON r.region_code = l.region_id
            WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (l.title LIKE ? OR l.location_code LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if ($regionFilter > 0) {
        $sql .= ' AND l.region_id = ?';
        $params[] = $regionFilter;
    }
    $sql .= ' ORDER BY l.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $locations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Locations list error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست مبادی و مقاصد رخ داد.');
}

$page_title = 'مدیریت مبادی و مقاصد';
$active = 'locations';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">مدیریت مبادی و مقاصد</h1>
    <p class="text-muted small mb-0">تعداد کل: <?= e((string)count($locations)) ?> مکان</p>
  </div>
  <a href="<?= BASE_URL ?>/locations/create.php" class="btn btn-primary d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:add-circle-bold"></span> مکان جدید
  </a>
</div>

<?= render_flash() ?>

<div class="filter-bar mb-4">
  <form method="get" action="<?= BASE_URL ?>/locations/list.php" class="row g-2 align-items-end">
    <div class="col-md-5">
      <label class="form-label" for="q">جستجو در کد یا عنوان مکان</label>
      <div class="input-group">
        <span class="input-group-text"><span class="iconify" data-icon="solar:magnifer-bold"></span></span>
        <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="مثلاً انبار نفت یا LOC-001">
      </div>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="region_id">فیلتر بر اساس منطقه</label>
      <select class="form-select" id="region_id" name="region_id">
        <option value="0">همه مناطق</option>
        <?php foreach ($regions as $r): ?>
          <option value="<?= e((string)$r['region_code']) ?>" <?= $regionFilter === (int)$r['region_code'] ? 'selected' : '' ?>>
            <?= e($r['region_name']) ?> (<?= e((string)$r['region_code']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-soft-purple d-flex align-items-center gap-2">
        <span class="iconify" data-icon="solar:magnifer-bold"></span> جستجو
      </button>
      <a href="<?= BASE_URL ?>/locations/list.php" class="btn btn-outline-secondary">پاک‌کردن</a>
    </div>
  </form>
</div>

<div class="card panel-card">
  <div class="card-body p-0">
    <?php if ($locations): ?>
    <div id="gridLocations" class="seal-ag-grid ag-theme-quartz"></div>
    <?php else: ?>
      <div class="text-center text-muted p-5">
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:signpost-line-duotone"></span>
        هیچ مکانی یافت نشد.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($locations): ?>
<script>
(function () {
  var rows = <?= json_encode(array_map(function ($l) {
      $coords = ((float)$l['lat'] !== 0.0 || (float)$l['lon'] !== 0.0) ? e($l['lat'] . ', ' . $l['lon']) : '—';
      return [
          'id' => (int)$l['id'],
          'location_code' => e($l['location_code']),
          'title' => e($l['title']),
          'region' => '<span class="badge role-badge role-region">' . e($l['region_name']) . '</span>',
          'coords' => $coords,
          'actions' => '<div class="d-flex gap-2 justify-content-end">'
              . '<a class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" href="' . BASE_URL . '/map/index.php?location_id=' . (int)$l['id'] . '"><span class="iconify" data-icon="solar:map-point-bold"></span> نقشه</a>'
              . '<a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1" href="' . BASE_URL . '/locations/edit.php?id=' . (int)$l['id'] . '"><span class="iconify" data-icon="solar:pen-bold"></span> ویرایش</a>'
              . '</div>',
      ];
  }, $locations), JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?>;
  var columnDefs = [
    { field: 'id', headerName: '#', width: 70, cellClass: 'text-muted' },
    { field: 'location_code', headerName: 'کد مکان', flex: 1, html: true, cellClass: 'ltr-text fw-bold' },
    { field: 'title', headerName: 'عنوان', flex: 1.5, html: true },
    { field: 'region', headerName: 'منطقه', flex: 1, html: true },
    { field: 'coords', headerName: 'مختصات جغرافیایی', flex: 1, html: true, cellClass: 'ltr-text text-muted small' },
    { field: 'actions', headerName: 'عملیات', flex: 1.5, html: true, sortable: false }
  ];
  function boot() {
    if (typeof sealInitDataGrid === 'undefined') { setTimeout(boot, 30); return; }
    sealInitDataGrid('gridLocations', columnDefs, rows);
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
