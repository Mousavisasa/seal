<?php
/**
 * فهرست مناطق با جستجوی ساده
 * توجه: region_code خودش کلید اصلی جدول است (طبق ساختار جدید)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

$search = trim((string)($_GET['q'] ?? ''));
$regions = [];

try {
    if ($search !== '') {
        $stmt = db()->prepare(
            'SELECT * FROM regions WHERE region_name LIKE ? OR region_code LIKE ? ORDER BY region_code ASC'
        );
        $like = '%' . $search . '%';
        $stmt->execute([$like, $like]);
    } else {
        $stmt = db()->query('SELECT * FROM regions ORDER BY region_code ASC');
    }
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

<div class="filter-bar mb-4">
  <form method="get" action="<?= BASE_URL ?>/regions/list.php" class="row g-2 align-items-end">
    <div class="col-md-8">
      <label class="form-label" for="q">جستجو در کد یا نام منطقه</label>
      <div class="input-group">
        <span class="input-group-text"><span class="iconify" data-icon="solar:magnifer-bold"></span></span>
        <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="مثلاً تهران یا 1">
      </div>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button type="submit" class="btn btn-soft-purple d-flex align-items-center gap-2">
        <span class="iconify" data-icon="solar:magnifer-bold"></span> جستجو
      </button>
      <a href="<?= BASE_URL ?>/regions/list.php" class="btn btn-outline-secondary">پاک‌کردن</a>
    </div>
  </form>
</div>

<div class="card panel-card">
  <div class="card-body p-0">
    <?php if ($regions): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>کد منطقه</th>
            <th>نام منطقه</th>
            <th class="text-start">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($regions as $r): ?>
          <tr>
            <td class="ltr-text fw-bold"><?= e((string)$r['region_code']) ?></td>
            <td><?= e($r['region_name']) ?></td>
            <td class="text-start">
              <a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1"
                 href="<?= BASE_URL ?>/regions/edit.php?code=<?= e((string)$r['region_code']) ?>">
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
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:map-point-line-duotone"></span>
        هیچ منطقه‌ای یافت نشد.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
