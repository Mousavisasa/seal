<?php
/**
 * جزئیات پلمپ + تاریخچه کامل رخدادها
 * دسترسی: ادمین (همه)، کاربر منطقه (فقط پلمپ‌های منطقه خودش)
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

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$seal = null;
$errors = [];
$movements = [];

try {
    $stmt = db()->prepare(
        'SELECT s.*, r.region_name, w.waybill_number
         FROM seals s
         LEFT JOIN regions r ON r.region_code = s.region_id
         LEFT JOIN fuel_waybills w ON w.id = s.fuel_waybill_id
         WHERE s.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $seal = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Seal fetch error: ' . $e->getMessage());
}

if (!$seal) {
    set_flash('danger', 'پلمپ مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/seals/list.php');
    exit;
}

if ($myRegionId !== null && (int)$seal['region_id'] !== $myRegionId) {
    set_flash('danger', 'شما مجاز به مشاهده این پلمپ نیستید.');
    header('Location: ' . BASE_URL . '/seals/list.php');
    exit;
}

// عملیات ادمین: ابطال یا اعلام مفقودی
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    $action = (string)($_POST['action'] ?? '');

    if (!verify_csrf()) {
        set_flash('danger', 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.');
    } elseif (in_array($action, ['void', 'report_lost'], true)) {
        $newStatus = $action === 'void' ? 'باطل شده' : 'مفقود شده';
        $actionLabel = $action === 'void' ? 'ابطال' : 'اعلام مفقودی';
        try {
            $stmt = db()->prepare('UPDATE seals SET seal_status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $seal['id']]);
            log_seal_movement(
                $seal['id'], $actionLabel, $seal['seal_status'], $newStatus,
                $seal['region_id'], $seal['fuel_waybill_id'], (int)($_SESSION['user_id'] ?? 0)
            );
            set_flash('success', 'وضعیت پلمپ «' . $seal['seal_id'] . '» به «' . $newStatus . '» تغییر یافت.');
        } catch (PDOException $e) {
            error_log('Seal status change error: ' . $e->getMessage());
            set_flash('danger', 'خطایی در تغییر وضعیت پلمپ رخ داد.');
        }
    }
    header('Location: ' . BASE_URL . '/seals/view.php?id=' . $seal['id']);
    exit;
}

try {
    $stmt = db()->prepare(
        'SELECT m.*, u.first_name, u.last_name, r.region_name, w.waybill_number
         FROM seal_movements m
         INNER JOIN users u ON u.id = m.performed_by
         LEFT JOIN regions r ON r.region_code = m.region_id
         LEFT JOIN fuel_waybills w ON w.id = m.fuel_waybill_id
         WHERE m.seal_id = ?
         ORDER BY m.id DESC'
    );
    $stmt->execute([$seal['id']]);
    $movements = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Seal movements fetch error: ' . $e->getMessage());
}

$page_title = 'جزئیات پلمپ';
$active = 'seals';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">جزئیات پلمپ <span class="ltr-text"><?= e($seal['seal_id']) ?></span></h1>
  <p class="text-muted small mb-0">اطلاعات کامل و تاریخچه رخدادهای این پلمپ</p>
</div>

<?= render_flash() ?>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card panel-card h-100">
      <div class="card-body p-4">
        <div class="fw-bold fs-5 ltr-text mb-2"><?= e($seal['seal_id']) ?></div>
        <span class="status-badge <?= e(seal_status_class($seal['seal_status'])) ?>"><?= e($seal['seal_status']) ?></span>
        <hr>
        <div class="small text-muted mb-1">منطقه فعلی</div>
        <div class="mb-2"><?= $seal['region_name'] ? e($seal['region_name']) : 'انبار مرکزی' ?></div>
        <div class="small text-muted mb-1">بارنامه الصاق‌شده</div>
        <div class="mb-2 ltr-text"><?= $seal['waybill_number'] ? e($seal['waybill_number']) : '—' ?></div>
        <div class="small text-muted mb-1">تاریخ ایجاد</div>
        <div class="ltr-text"><?= e(to_jalali_datetime_display($seal['created_at'])) ?></div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card panel-card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="iconify text-jade fs-5" data-icon="solar:clock-circle-bold"></span>
        <span class="fw-bold">تاریخچه رخدادها</span>
      </div>
      <div class="card-body p-0">
        <?php if ($movements): ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>رخداد</th>
                <th>از وضعیت</th>
                <th>به وضعیت</th>
                <th>منطقه</th>
                <th>بارنامه</th>
                <th>انجام‌دهنده</th>
                <th>زمان</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($movements as $m): ?>
              <tr>
                <td><?= e($m['action']) ?></td>
                <td class="text-muted small"><?= e($m['from_status'] ?? '—') ?></td>
                <td class="text-muted small"><?= e($m['to_status']) ?></td>
                <td><?= $m['region_name'] ? e($m['region_name']) : '<span class="text-muted">—</span>' ?></td>
                <td class="ltr-text"><?= $m['waybill_number'] ? e($m['waybill_number']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= e($m['first_name'] . ' ' . $m['last_name']) ?></td>
                <td class="ltr-text text-muted small"><?= e(to_jalali_datetime_display($m['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p class="text-muted p-4 mb-0">هنوز رخدادی ثبت نشده است.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="d-flex flex-wrap gap-2">
  <?php if (can_assign_seal_to_region() && $seal['seal_status'] !== 'الصاق شده'): ?>
    <a href="<?= BASE_URL ?>/seals/assign_region.php?id=<?= e((string)$seal['id']) ?>" class="btn btn-soft-purple d-flex align-items-center gap-2">
      <span class="iconify" data-icon="solar:map-point-bold"></span> تخصیص منطقه
    </a>
  <?php endif; ?>

  <?php if (can_attach_seal_to_waybill() && in_array($seal['seal_status'], ['در انبار منطقه', 'الصاق شده'], true)): ?>
    <a href="<?= BASE_URL ?>/seals/attach_waybill.php?id=<?= e((string)$seal['id']) ?>" class="btn btn-soft-purple d-flex align-items-center gap-2">
      <span class="iconify" data-icon="solar:link-bold"></span> الصاق/جداکردن بارنامه
    </a>
  <?php endif; ?>

  <?php if (is_admin() && !in_array($seal['seal_status'], ['باطل شده', 'مفقود شده'], true)): ?>
    <form method="post" action="<?= BASE_URL ?>/seals/view.php?id=<?= e((string)$seal['id']) ?>"
          onsubmit="return confirm('آیا از ابطال این پلمپ مطمئن هستید؟ این عملیات قابل بازگشت نیست.');">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e((string)$seal['id']) ?>">
      <input type="hidden" name="action" value="void">
      <button type="submit" class="btn btn-outline-danger d-flex align-items-center gap-2">
        <span class="iconify" data-icon="solar:close-circle-bold"></span> ابطال پلمپ
      </button>
    </form>
    <form method="post" action="<?= BASE_URL ?>/seals/view.php?id=<?= e((string)$seal['id']) ?>"
          onsubmit="return confirm('آیا از اعلام مفقودی این پلمپ مطمئن هستید؟');">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e((string)$seal['id']) ?>">
      <input type="hidden" name="action" value="report_lost">
      <button type="submit" class="btn btn-outline-danger d-flex align-items-center gap-2">
        <span class="iconify" data-icon="solar:danger-triangle-bold"></span> اعلام مفقودی
      </button>
    </form>
  <?php endif; ?>

  <a href="<?= BASE_URL ?>/seals/list.php" class="btn btn-outline-secondary">بازگشت به فهرست</a>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
