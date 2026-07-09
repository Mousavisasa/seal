<?php
/**
 * فهرست مبادی/مقاصد با جستجو و فیلتر منطقه
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

$search = trim((string)($_GET['q'] ?? ''));
$regionFilter = (int)($_GET['region_id'] ?? 0);
$locations = [];
$regions = [];

try {
    $regions = db()->query('SELECT id, region_code, region_name FROM regions ORDER BY region_name')->fetchAll();

    $sql = 'SELECT l.*, r.region_name, r.region_code
            FROM locations l
            INNER JOIN regions r ON r.id = l.region_id
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
          <option value="<?= e((string)$r['id']) ?>" <?= $regionFilter === (int)$r['id'] ? 'selected' : '' ?>>
            <?= e($r['region_name']) ?> (<?= e($r['region_code']) ?>)
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
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>کد مکان</th>
            <th>عنوان</th>
            <th>منطقه</th>
            <th>مختصات جغرافیایی</th>
            <th class="text-start">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($locations as $l): ?>
          <tr>
            <td class="text-muted"><?= e((string)$l['id']) ?></td>
            <td class="ltr-text fw-bold"><?= e($l['location_code']) ?></td>
            <td><?= e($l['title']) ?></td>
            <td><span class="badge role-badge role-region"><?= e($l['region_name']) ?></span></td>
            <td class="ltr-text text-muted small"><?= e($l['geo_location'] ?: '—') ?></td>
            <td class="text-start">
              <a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1"
                 href="<?= BASE_URL ?>/locations/edit.php?id=<?= e((string)$l['id']) ?>">
                <span class="iconify" data-icon="solar:pen-bold"></span> ویرایش
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="text-center text-muted p-5">
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:signpost-line-duotone"></span>
        هیچ مکانی یافت نشد.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
