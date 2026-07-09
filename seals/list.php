<?php
/**
 * فهرست پلمپ‌ها (انبارداری)
 * - ادمین: همه پلمپ‌ها در همه وضعیت‌ها را می‌بیند
 * - کاربر منطقه: فقط پلمپ‌های تخصیص‌یافته به منطقه خودش را می‌بیند
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';
require_once __DIR__ . '/../helpers/seals.php';

require_login();

if (!is_admin() && !is_region()) {
    set_flash('danger', 'شما دسترسی لازم برای این بخش را ندارید.');
    header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
    exit;
}

$myRegionId = session_region_id();

$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$seals = [];

try {
    $sql = 'SELECT s.*, r.region_name, w.waybill_number
            FROM seals s
            LEFT JOIN regions r ON r.region_code = s.region_id
            LEFT JOIN fuel_waybills w ON w.id = s.fuel_waybill_id
            WHERE 1=1';
    $params = [];

    if ($myRegionId !== null) {
        $sql .= ' AND s.region_id = ?';
        $params[] = $myRegionId;
    }
    if ($search !== '') {
        $sql .= ' AND s.seal_id LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($statusFilter !== '' && in_array($statusFilter, SEAL_STATUSES, true)) {
        $sql .= ' AND s.seal_status = ?';
        $params[] = $statusFilter;
    }
    $sql .= ' ORDER BY s.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $seals = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Seals list error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست پلمپ‌ها رخ داد.');
}

$page_title = 'انبارداری پلمپ';
$active = 'seals';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">انبارداری پلمپ</h1>
    <p class="text-muted small mb-0">
      تعداد کل: <?= e((string)count($seals)) ?> پلمپ
      <?php if ($myRegionId !== null): ?> (فقط پلمپ‌های منطقه شما)<?php endif; ?>
    </p>
  </div>
  <?php if (can_manage_seals_master()): ?>
    <a href="<?= BASE_URL ?>/seals/create.php" class="btn btn-primary d-flex align-items-center gap-2">
      <span class="iconify" data-icon="solar:add-circle-bold"></span> پلمپ جدید
    </a>
  <?php endif; ?>
</div>

<?= render_flash() ?>

<div class="filter-bar mb-4">
  <form method="get" action="<?= BASE_URL ?>/seals/list.php" class="row g-2 align-items-end">
    <div class="col-md-5">
      <label class="form-label" for="q">جستجو در شناسه پلمپ</label>
      <div class="input-group">
        <span class="input-group-text"><span class="iconify" data-icon="solar:magnifer-bold"></span></span>
        <input type="text" class="form-control ltr-text" id="q" name="q" value="<?= e($search) ?>" placeholder="مثلاً SEAL-0001">
      </div>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="status">وضعیت پلمپ</label>
      <select class="form-select" id="status" name="status">
        <option value="">همه وضعیت‌ها</option>
        <?php foreach (SEAL_STATUSES as $s): ?>
          <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-soft-purple d-flex align-items-center gap-2">
        <span class="iconify" data-icon="solar:magnifer-bold"></span> اعمال
      </button>
      <a href="<?= BASE_URL ?>/seals/list.php" class="btn btn-outline-secondary">پاک‌کردن</a>
    </div>
  </form>
</div>

<div class="card panel-card">
  <div class="card-body p-0">
    <?php if ($seals): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>شناسه پلمپ</th>
            <th>وضعیت</th>
            <th>منطقه فعلی</th>
            <th>بارنامه الصاق‌شده</th>
            <th>تاریخ ایجاد</th>
            <th class="text-start">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($seals as $s): ?>
          <tr>
            <td class="ltr-text fw-bold"><?= e($s['seal_id']) ?></td>
            <td><span class="status-badge <?= e(seal_status_class($s['seal_status'])) ?>"><?= e($s['seal_status']) ?></span></td>
            <td><?= $s['region_name'] ? e($s['region_name']) : '<span class="text-muted">انبار مرکزی</span>' ?></td>
            <td class="ltr-text"><?= $s['waybill_number'] ? e($s['waybill_number']) : '<span class="text-muted">—</span>' ?></td>
            <td class="ltr-text text-muted small"><?= e(to_jalali_display($s['created_at'])) ?></td>
            <td class="text-start">
              <div class="d-flex gap-1 justify-content-start flex-wrap">
                <a class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                   href="<?= BASE_URL ?>/seals/view.php?id=<?= e((string)$s['id']) ?>">
                  <span class="iconify" data-icon="solar:eye-bold"></span> جزئیات
                </a>
                <?php if (can_assign_seal_to_region()): ?>
                  <a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1"
                     href="<?= BASE_URL ?>/seals/assign_region.php?id=<?= e((string)$s['id']) ?>">
                    <span class="iconify" data-icon="solar:map-point-bold"></span> تخصیص منطقه
                  </a>
                <?php endif; ?>
                <?php if (can_attach_seal_to_waybill() && $s['seal_status'] === 'در انبار منطقه'): ?>
                  <a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1"
                     href="<?= BASE_URL ?>/seals/attach_waybill.php?id=<?= e((string)$s['id']) ?>">
                    <span class="iconify" data-icon="solar:link-bold"></span> الصاق به بارنامه
                  </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="text-center text-muted p-5">
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:box-line-duotone"></span>
        هیچ پلمپی یافت نشد.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
