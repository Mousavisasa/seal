<?php
/**
 * تخصیص راننده حمل‌کننده به بارنامه
 * دسترسی: ادمین (همه بارنامه‌ها) یا کاربر متصدی که به‌عنوان متصدی مبدا یا مقصد
 * همین بارنامه تخصیص یافته است.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';

require_assign_driver_access();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$waybill = null;
$errors = [];
$drivers = [];

try {
    $drivers = db()->query("SELECT id, national_code, first_name, last_name FROM users WHERE user_type = 'driver' ORDER BY first_name")->fetchAll();

    $stmt = db()->prepare(
        'SELECT w.*, ol.title AS origin_title, dl.title AS destination_title
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         WHERE w.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $waybill = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Assign driver fetch error: ' . $e->getMessage());
}

if (!$waybill) {
    set_flash('danger', 'بارنامه مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/waybills/list.php');
    exit;
}

// کاربر متصدی فقط مجاز به تخصیص راننده برای بارنامه‌هایی است که متصدی مبدا یا مقصد آن باشد
if (is_operator()) {
    $isOriginOperator = (int)$waybill['origin_operator_user_id'] === (int)$_SESSION['user_id'];
    $isDestOperator   = (int)$waybill['destination_operator_user_id'] === (int)$_SESSION['user_id'];
    if (!$isOriginOperator && !$isDestOperator) {
        set_flash('danger', 'این بارنامه به شما تخصیص داده نشده است.');
        header('Location: ' . BASE_URL . '/waybills/my_waybills.php');
        exit;
    }
}

$selectedDriver = $waybill['driver_user_id'] !== null ? (string)$waybill['driver_user_id'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $selectedDriver = trim((string)($_POST['driver_user_id'] ?? ''));
        $driverId = $selectedDriver !== '' ? (int)$selectedDriver : null;

        if ($driverId !== null) {
            try {
                $stmt = db()->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'driver' LIMIT 1");
                $stmt->execute([$driverId]);
                if (!$stmt->fetch()) {
                    $errors[] = 'راننده انتخاب‌شده معتبر نیست یا نقش راننده ندارد.';
                }
            } catch (PDOException $e) {
                error_log('Driver validate error: ' . $e->getMessage());
                $errors[] = 'خطایی در بررسی راننده رخ داد.';
            }
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare('UPDATE fuel_waybills SET driver_user_id = ? WHERE id = ?');
                $stmt->execute([$driverId, $waybill['id']]);
                set_flash('success', 'راننده بارنامه «' . $waybill['waybill_number'] . '» با موفقیت به‌روزرسانی شد.');
                header('Location: ' . BASE_URL . '/' . (is_admin() ? 'waybills/list.php' : 'waybills/my_waybills.php'));
                exit;
            } catch (PDOException $e) {
                error_log('Assign driver update error: ' . $e->getMessage());
                $errors[] = 'خطایی در تخصیص راننده رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'تخصیص راننده';
$active = is_operator() ? 'my-waybills' : 'waybills';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">تخصیص راننده حمل‌کننده</h1>
  <p class="text-muted small mb-0">راننده مسئول حمل این بارنامه را انتخاب کنید.</p>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <div class="d-flex align-items-center gap-2 fw-bold mb-2">
      <span class="iconify" data-icon="solar:danger-triangle-bold"></span> فرم دارای خطا است:
    </div>
    <ul class="mb-0 pe-4">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card panel-card h-100">
      <div class="card-body p-4">
        <div class="fw-bold fs-5 ltr-text mb-2"><?= e($waybill['waybill_number']) ?></div>
        <div class="text-muted small mb-1"><?= e($waybill['origin_title']) ?> ← <?= e($waybill['destination_title']) ?></div>
        <div class="text-muted small">تاریخ صدور: <?= e(to_jalali_display($waybill['issue_date'])) ?></div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card panel-card h-100">
      <div class="card-body p-4">
        <form method="post" action="<?= BASE_URL ?>/waybills/assign_driver.php" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((string)$waybill['id']) ?>">

          <label class="form-label" for="driver_user_id">کد راننده حمل‌کننده</label>
          <div class="input-group mb-3">
            <span class="input-group-text"><span class="iconify" data-icon="solar:bus-bold"></span></span>
            <select class="form-select" id="driver_user_id" name="driver_user_id">
              <option value="">— بدون راننده —</option>
              <?php foreach ($drivers as $u): ?>
                <option value="<?= e((string)$u['id']) ?>" <?= (string)$u['id'] === $selectedDriver ? 'selected' : '' ?>>
                  <?= e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= e($u['national_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (!$drivers): ?>
            <div class="text-warning small mb-3">هیچ راننده‌ای در سامانه ثبت نشده است.</div>
          <?php endif; ?>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
              <span class="iconify" data-icon="solar:diskette-bold"></span> ذخیره
            </button>
            <a href="<?= BASE_URL ?>/<?= is_admin() ? 'waybills/list.php' : 'waybills/my_waybills.php' ?>" class="btn btn-outline-secondary">بازگشت</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
