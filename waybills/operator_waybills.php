<?php
/**
 * فهرست بارنامه‌های ورودی به ایستگاه متصدی (نمای کارتی)
 * متصدی بارنامه‌هایی را می‌بیند که به‌عنوان متصدی مبدا یا متصدی مقصد آن تخصیص یافته است،
 * با امکان فیلتر بر اساس نقش (متصدی مبدا / متصدی مقصد).
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

$roleFilter = trim((string)($_GET['role'] ?? ''));
if (!in_array($roleFilter, ['origin', 'destination'], true)) {
    $roleFilter = '';
}

$waybills = [];
$statusClassMap = [
    'ثبت شده'   => 'status-registered',
    'ارسال شده' => 'status-sent',
    'تحویل شده' => 'status-delivered',
    'لغو شده'   => 'status-cancelled',
];

try {
    $sql = 'SELECT w.*, ol.title AS origin_title, dl.title AS destination_title,
                   opOrig.first_name AS origin_operator_first, opOrig.last_name AS origin_operator_last,
                   opDest.first_name AS dest_operator_first, opDest.last_name AS dest_operator_last,
                   drU.first_name AS driver_first, drU.last_name AS driver_last,
                   sl.seal_id AS attached_seal_id
            FROM fuel_waybills w
            INNER JOIN locations ol ON ol.id = w.origin_location_id
            INNER JOIN locations dl ON dl.id = w.destination_location_id
            LEFT JOIN users opOrig ON opOrig.id = w.origin_operator_user_id
            LEFT JOIN users opDest ON opDest.id = w.destination_operator_user_id
            LEFT JOIN users drU ON drU.id = w.driver_user_id
            LEFT JOIN seals sl ON sl.fuel_waybill_id = w.id
            WHERE ';

    $params = [];
    if ($roleFilter === 'origin') {
        $sql .= 'w.origin_operator_user_id = ?';
        $params[] = (int)$_SESSION['user_id'];
    } elseif ($roleFilter === 'destination') {
        $sql .= 'w.destination_operator_user_id = ?';
        $params[] = (int)$_SESSION['user_id'];
    } else {
        $sql .= '(w.origin_operator_user_id = ? OR w.destination_operator_user_id = ?)';
        $params[] = (int)$_SESSION['user_id'];
        $params[] = (int)$_SESSION['user_id'];
    }
    $sql .= ' ORDER BY w.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $waybills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Operator waybills error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست بارنامه‌ها رخ داد.');
}

$page_title = 'بارنامه‌های ایستگاه من';
$active = 'operator-waybills';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">بارنامه‌های ورودی به ایستگاه من</h1>
  <p class="text-muted small mb-0">تعداد کل: <?= e((string)count($waybills)) ?> بارنامه</p>
</div>

<?= render_flash() ?>

<div class="filter-bar mb-4">
  <form method="get" action="<?= BASE_URL ?>/waybills/operator_waybills.php" class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label" for="role">نقش متصدی</label>
      <select class="form-select" id="role" name="role">
        <option value="">همه (مبدا و مقصد)</option>
        <option value="origin" <?= $roleFilter === 'origin' ? 'selected' : '' ?>>متصدی مبدا</option>
        <option value="destination" <?= $roleFilter === 'destination' ? 'selected' : '' ?>>متصدی مقصد</option>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-soft-purple d-flex align-items-center gap-2">
        <span class="iconify" data-icon="solar:magnifer-bold"></span> اعمال
      </button>
      <a href="<?= BASE_URL ?>/waybills/operator_waybills.php" class="btn btn-outline-secondary">پاک‌کردن</a>
    </div>
  </form>
</div>

<?php if ($waybills): ?>
  <div class="row g-3">
    <?php foreach ($waybills as $w): ?>
      <?php
        $myRoles = [];
        if ((int)$w['origin_operator_user_id'] === (int)$_SESSION['user_id']) {
            $myRoles[] = 'متصدی مبدا';
        }
        if ((int)$w['destination_operator_user_id'] === (int)$_SESSION['user_id']) {
            $myRoles[] = 'متصدی مقصد';
        }
      ?>
      <div class="col-md-6 col-xl-4">
        <div class="card panel-card h-100">
          <div class="card-body d-flex flex-column gap-2">
            <div class="d-flex justify-content-between align-items-start">
              <div class="fw-bold ltr-text"><?= e($w['waybill_number']) ?></div>
              <span class="status-badge <?= e($statusClassMap[$w['send_status']] ?? '') ?>"><?= e($w['send_status']) ?></span>
            </div>

            <div class="d-flex flex-wrap gap-1">
              <?php foreach ($myRoles as $role): ?>
                <span class="badge role-badge role-region"><?= e($role) ?></span>
              <?php endforeach; ?>
            </div>

            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:point-on-map-bold"></span>
              <?= e($w['origin_title']) ?> <span class="iconify" data-icon="solar:arrow-left-bold"></span> <?= e($w['destination_title']) ?>
            </div>

            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:fuel-bold"></span> <?= e($w['product_type']) ?>
              <span class="mx-1">•</span>
              <span class="iconify" data-icon="solar:ruler-bold"></span> <?= e(number_format((float)$w['distance_km'], 2)) ?> کیلومتر
            </div>

            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:calendar-bold"></span> تاریخ صدور: <?= e(to_jalali_display($w['issue_date'])) ?>
            </div>

            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:lock-password-unlocked-bold"></span> شناسه پلمپ:
              <?= $w['attached_seal_id'] ? '<span class="ltr-text fw-bold">' . e($w['attached_seal_id']) . '</span>' : '<span class="text-muted">ثبت‌نشده</span>' ?>
            </div>

            <?php if ($w['origin_operator_first']): ?>
              <div class="small text-muted d-flex align-items-center gap-1">
                <span class="iconify" data-icon="solar:user-id-bold"></span> متصدی مبدا: <?= e($w['origin_operator_first'] . ' ' . $w['origin_operator_last']) ?>
              </div>
            <?php endif; ?>

            <?php if ($w['dest_operator_first']): ?>
              <div class="small text-muted d-flex align-items-center gap-1">
                <span class="iconify" data-icon="solar:user-id-bold"></span> متصدی مقصد: <?= e($w['dest_operator_first'] . ' ' . $w['dest_operator_last']) ?>
              </div>
            <?php endif; ?>

            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:bus-bold"></span>
              راننده: <?= $w['driver_first'] ? e($w['driver_first'] . ' ' . $w['driver_last']) : '<span class="text-muted">تخصیص‌نیافته</span>' ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card panel-card">
    <div class="card-body text-center text-muted p-5">
      <span class="iconify fs-1 d-block mb-2" data-icon="solar:fuel-line-duotone"></span>
      هیچ بارنامه‌ای مطابق فیلتر انتخابی یافت نشد.
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
