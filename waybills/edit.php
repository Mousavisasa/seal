<?php
/**
 * ویرایش بارنامه سوخت (اطلاعات اصلی بارنامه)
 * تخصیص متصدی/راننده و تغییر وضعیت سفر از این صفحه انجام نمی‌شود؛
 * برای آن صفحات مخصوص (تخصیص متصدی / تخصیص راننده / شروع و پایان سفر) وجود دارد.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';

require_waybill_access();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$waybill = null;
$errors = [];
$locations = [];

try {
    $locations = db()->query(
        'SELECT l.id, l.location_code, l.title, l.region_id, r.region_name
         FROM locations l INNER JOIN regions r ON r.region_code = l.region_id
         ORDER BY l.title'
    )->fetchAll();

    $stmt = db()->prepare('SELECT * FROM fuel_waybills WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $waybill = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Waybill edit form data error: ' . $e->getMessage());
}

if (!$waybill) {
    set_flash('danger', 'بارنامه مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/waybills/list.php');
    exit;
}

$old = [
    'origin_location_id'      => (string)$waybill['origin_location_id'],
    'destination_location_id' => (string)$waybill['destination_location_id'],
    'distance_km'              => (string)$waybill['distance_km'],
    'product_type'             => $waybill['product_type'],
    'waybill_number'           => $waybill['waybill_number'],
    'issue_date_jalali'        => to_jalali_display($waybill['issue_date']),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $old['origin_location_id']      = (string)($_POST['origin_location_id'] ?? '');
        $old['destination_location_id'] = (string)($_POST['destination_location_id'] ?? '');
        $old['distance_km']              = trim((string)($_POST['distance_km'] ?? ''));
        $old['product_type']             = (string)($_POST['product_type'] ?? '');
        $old['waybill_number']           = trim((string)($_POST['waybill_number'] ?? ''));
        $old['issue_date_jalali']        = trim((string)($_POST['issue_date_jalali'] ?? ''));

        if ($old['waybill_number'] === '' || mb_strlen($old['waybill_number']) > 50) {
            $errors[] = 'شماره بارنامه الزامی است و باید حداکثر ۵۰ کاراکتر باشد.';
        }
        if (!is_positive_number($old['distance_km'])) {
            $errors[] = 'مسافت باید عددی مثبت باشد.';
        }
        if (!in_array($old['product_type'], PRODUCT_TYPES, true)) {
            $errors[] = 'نوع فرآورده انتخاب‌شده معتبر نیست.';
        }

        $issueDateGregorian = null;
        if (!is_valid_jalali_date($old['issue_date_jalali'])) {
            $errors[] = 'تاریخ صدور بارنامه معتبر نیست.';
        } else {
            $issueDateGregorian = jalali_to_gregorian_string($old['issue_date_jalali']);
        }

        if ((int)$old['origin_location_id'] <= 0) {
            $errors[] = 'انتخاب مبدا الزامی است.';
        }
        if ((int)$old['destination_location_id'] <= 0) {
            $errors[] = 'انتخاب مقصد الزامی است.';
        }

        if (!$errors) {
            try {
                $pdo = db();

                $stmt = $pdo->prepare('SELECT id FROM locations WHERE id = ? LIMIT 1');
                $stmt->execute([(int)$old['origin_location_id']]);
                if (!$stmt->fetch()) {
                    $errors[] = 'مبدا انتخاب‌شده معتبر نیست.';
                }

                $stmt->execute([(int)$old['destination_location_id']]);
                if (!$stmt->fetch()) {
                    $errors[] = 'مقصد انتخاب‌شده معتبر نیست.';
                }
            } catch (PDOException $e) {
                error_log('Waybill relation validate error: ' . $e->getMessage());
                $errors[] = 'خطایی در بررسی اطلاعات رخ داد.';
            }
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare('SELECT id FROM fuel_waybills WHERE waybill_number = ? AND id <> ? LIMIT 1');
                $stmt->execute([$old['waybill_number'], $waybill['id']]);
                if ($stmt->fetch()) {
                    $errors[] = 'بارنامه دیگری با این شماره قبلاً ثبت شده است.';
                } else {
                    $stmt = db()->prepare(
                        'UPDATE fuel_waybills SET
                            origin_location_id = ?, destination_location_id = ?, distance_km = ?, product_type = ?,
                            waybill_number = ?, issue_date = ?
                         WHERE id = ?'
                    );
                    $stmt->execute([
                        (int)$old['origin_location_id'],
                        (int)$old['destination_location_id'],
                        (float)$old['distance_km'],
                        $old['product_type'],
                        $old['waybill_number'],
                        $issueDateGregorian,
                        $waybill['id'],
                    ]);
                    set_flash('success', 'بارنامه با موفقیت ویرایش شد.');
                    header('Location: ' . BASE_URL . '/waybills/list.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Update waybill error: ' . $e->getMessage());
                $errors[] = 'خطایی در ویرایش بارنامه رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'ویرایش بارنامه';
$active = 'waybills';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">ویرایش بارنامه سوخت</h1>
  <p class="text-muted small mb-0">اطلاعات اصلی بارنامه را ویرایش کنید.</p>
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

<div class="card panel-card">
  <div class="card-body p-4">
    <form method="post" action="<?= BASE_URL ?>/waybills/edit.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e((string)$waybill['id']) ?>">
      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label" for="waybill_number">شماره بارنامه</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:document-text-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="waybill_number" name="waybill_number" required maxlength="50"
                   value="<?= e($old['waybill_number']) ?>">
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="issue_date_jalali">تاریخ صدور بارنامه</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:calendar-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="issue_date_jalali" name="issue_date_jalali" required
                   autocomplete="off" data-jalali-datepicker
                   value="<?= e($old['issue_date_jalali']) ?>">
          </div>
          <div class="form-text">فرمت: سال/ماه/روز (تقویم شمسی)</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="origin_location_id">کد مبدا</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:point-on-map-bold"></span></span>
            <select class="form-select" id="origin_location_id" name="origin_location_id" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($locations as $l): ?>
                <option value="<?= e((string)$l['id']) ?>" <?= (string)$l['id'] === $old['origin_location_id'] ? 'selected' : '' ?>>
                  <?= e($l['title']) ?> (<?= e($l['location_code']) ?>) — <?= e($l['region_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="destination_location_id">کد مقصد</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:point-on-map-bold"></span></span>
            <select class="form-select" id="destination_location_id" name="destination_location_id" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($locations as $l): ?>
                <option value="<?= e((string)$l['id']) ?>" <?= (string)$l['id'] === $old['destination_location_id'] ? 'selected' : '' ?>>
                  <?= e($l['title']) ?> (<?= e($l['location_code']) ?>) — <?= e($l['region_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="distance_km">مسافت (کیلومتر)</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:ruler-bold"></span></span>
            <input type="number" step="0.01" min="0.01" class="form-control ltr-text" id="distance_km" name="distance_km" required
                   value="<?= e($old['distance_km']) ?>">
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="product_type">نوع فرآورده</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:fuel-bold"></span></span>
            <select class="form-select" id="product_type" name="product_type" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach (PRODUCT_TYPES as $p): ?>
                <option value="<?= e($p) ?>" <?= $old['product_type'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <span class="iconify" data-icon="solar:diskette-bold"></span> ذخیره تغییرات
        </button>
        <a href="<?= BASE_URL ?>/waybills/list.php" class="btn btn-outline-secondary">انصراف</a>
      </div>
    </form>
  </div>
</div>

<?php if (can_assign_operator()): ?>
<div class="card panel-card mt-3">
  <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <div class="fw-bold">تخصیص متصدی ارسال</div>
      <div class="text-muted small">تعیین کاربر متصدی مسئول این بارنامه</div>
    </div>
    <a href="<?= BASE_URL ?>/waybills/assign_operator.php?id=<?= e((string)$waybill['id']) ?>" class="btn btn-soft-purple d-flex align-items-center gap-2">
      <span class="iconify" data-icon="solar:user-id-bold"></span> تخصیص متصدی
    </a>
  </div>
</div>
<?php endif; ?>

<?php if (can_assign_driver()): ?>
<div class="card panel-card mt-3">
  <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <div class="fw-bold">تخصیص راننده حمل‌کننده</div>
      <div class="text-muted small">تعیین راننده مسئول حمل این بارنامه</div>
    </div>
    <a href="<?= BASE_URL ?>/waybills/assign_driver.php?id=<?= e((string)$waybill['id']) ?>" class="btn btn-soft-purple d-flex align-items-center gap-2">
      <span class="iconify" data-icon="solar:bus-bold"></span> تخصیص راننده
    </a>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
