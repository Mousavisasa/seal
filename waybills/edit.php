<?php
/**
 * ویرایش بارنامه سوخت
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$waybill = null;
$errors = [];

$locations = [];
$regions = [];
$operators = [];
$drivers = [];

try {
    $locations = db()->query('SELECT id, location_code, title FROM locations ORDER BY title')->fetchAll();
    $regions   = db()->query('SELECT id, region_code, region_name FROM regions ORDER BY region_name')->fetchAll();
    $operators = db()->query("SELECT id, national_code, first_name, last_name FROM users WHERE user_type = 'operator' ORDER BY first_name")->fetchAll();
    $drivers   = db()->query("SELECT id, national_code, first_name, last_name FROM users WHERE user_type = 'driver' ORDER BY first_name")->fetchAll();

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
    'issue_date'               => $waybill['issue_date'],
    'send_status'               => $waybill['send_status'],
    'sender_operator_user_id'  => (string)$waybill['sender_operator_user_id'],
    'driver_user_id'            => (string)$waybill['driver_user_id'],
    'origin_region_id'          => (string)$waybill['origin_region_id'],
    'destination_region_id'     => (string)$waybill['destination_region_id'],
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
        $old['issue_date']               = trim((string)($_POST['issue_date'] ?? ''));
        $old['send_status']              = (string)($_POST['send_status'] ?? '');
        $old['sender_operator_user_id']  = (string)($_POST['sender_operator_user_id'] ?? '');
        $old['driver_user_id']            = (string)($_POST['driver_user_id'] ?? '');
        $old['origin_region_id']          = (string)($_POST['origin_region_id'] ?? '');
        $old['destination_region_id']     = (string)($_POST['destination_region_id'] ?? '');

        if ($old['waybill_number'] === '' || mb_strlen($old['waybill_number']) > 50) {
            $errors[] = 'شماره بارنامه الزامی است و باید حداکثر ۵۰ کاراکتر باشد.';
        }
        if (!is_positive_number($old['distance_km'])) {
            $errors[] = 'مسافت باید عددی مثبت باشد.';
        }
        if (!in_array($old['product_type'], PRODUCT_TYPES, true)) {
            $errors[] = 'نوع فرآورده انتخاب‌شده معتبر نیست.';
        }
        if (!is_valid_date($old['issue_date'])) {
            $errors[] = 'تاریخ صدور بارنامه معتبر نیست.';
        }
        if (!in_array($old['send_status'], SEND_STATUSES, true)) {
            $errors[] = 'وضعیت ارسال انتخاب‌شده معتبر نیست.';
        }
        if ((int)$old['origin_location_id'] <= 0) {
            $errors[] = 'انتخاب مبدا الزامی است.';
        }
        if ((int)$old['destination_location_id'] <= 0) {
            $errors[] = 'انتخاب مقصد الزامی است.';
        }
        if ((int)$old['origin_region_id'] <= 0) {
            $errors[] = 'انتخاب منطقه مبدا الزامی است.';
        }
        if ((int)$old['destination_region_id'] <= 0) {
            $errors[] = 'انتخاب منطقه مقصد الزامی است.';
        }
        if ((int)$old['sender_operator_user_id'] <= 0) {
            $errors[] = 'انتخاب متصدی ارسال الزامی است.';
        }
        if ((int)$old['driver_user_id'] <= 0) {
            $errors[] = 'انتخاب راننده حمل‌کننده الزامی است.';
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

                $stmt = $pdo->prepare('SELECT id FROM regions WHERE id = ? LIMIT 1');
                $stmt->execute([(int)$old['origin_region_id']]);
                if (!$stmt->fetch()) {
                    $errors[] = 'منطقه مبدا انتخاب‌شده معتبر نیست.';
                }

                $stmt->execute([(int)$old['destination_region_id']]);
                if (!$stmt->fetch()) {
                    $errors[] = 'منطقه مقصد انتخاب‌شده معتبر نیست.';
                }

                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'operator' LIMIT 1");
                $stmt->execute([(int)$old['sender_operator_user_id']]);
                if (!$stmt->fetch()) {
                    $errors[] = 'کاربر متصدی انتخاب‌شده معتبر نیست یا نقش متصدی ندارد.';
                }

                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'driver' LIMIT 1");
                $stmt->execute([(int)$old['driver_user_id']]);
                if (!$stmt->fetch()) {
                    $errors[] = 'راننده انتخاب‌شده معتبر نیست یا نقش راننده ندارد.';
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
                            waybill_number = ?, issue_date = ?, send_status = ?, sender_operator_user_id = ?,
                            driver_user_id = ?, origin_region_id = ?, destination_region_id = ?
                         WHERE id = ?'
                    );
                    $stmt->execute([
                        (int)$old['origin_location_id'],
                        (int)$old['destination_location_id'],
                        (float)$old['distance_km'],
                        $old['product_type'],
                        $old['waybill_number'],
                        $old['issue_date'],
                        $old['send_status'],
                        (int)$old['sender_operator_user_id'],
                        (int)$old['driver_user_id'],
                        (int)$old['origin_region_id'],
                        (int)$old['destination_region_id'],
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
  <p class="text-muted small mb-0">اطلاعات بارنامه انتخاب‌شده را ویرایش کنید.</p>
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
          <label class="form-label" for="issue_date">تاریخ صدور بارنامه</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:calendar-bold"></span></span>
            <input type="date" class="form-control ltr-text" id="issue_date" name="issue_date" required
                   value="<?= e($old['issue_date']) ?>">
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="origin_location_id">کد مبدا</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:point-on-map-bold"></span></span>
            <select class="form-select" id="origin_location_id" name="origin_location_id" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($locations as $l): ?>
                <option value="<?= e((string)$l['id']) ?>" <?= (string)$l['id'] === $old['origin_location_id'] ? 'selected' : '' ?>>
                  <?= e($l['title']) ?> (<?= e($l['location_code']) ?>)
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
                  <?= e($l['title']) ?> (<?= e($l['location_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="origin_region_id">کد منطقه مبدا</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
            <select class="form-select" id="origin_region_id" name="origin_region_id" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($regions as $r): ?>
                <option value="<?= e((string)$r['id']) ?>" <?= (string)$r['id'] === $old['origin_region_id'] ? 'selected' : '' ?>>
                  <?= e($r['region_name']) ?> (<?= e($r['region_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="destination_region_id">کد منطقه مقصد</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
            <select class="form-select" id="destination_region_id" name="destination_region_id" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($regions as $r): ?>
                <option value="<?= e((string)$r['id']) ?>" <?= (string)$r['id'] === $old['destination_region_id'] ? 'selected' : '' ?>>
                  <?= e($r['region_name']) ?> (<?= e($r['region_code']) ?>)
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

        <div class="col-md-6">
          <label class="form-label" for="send_status">وضعیت ارسال بارنامه</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:clipboard-check-bold"></span></span>
            <select class="form-select" id="send_status" name="send_status" required>
              <?php foreach (SEND_STATUSES as $s): ?>
                <option value="<?= e($s) ?>" <?= $old['send_status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="sender_operator_user_id">کد کاربر متصدی ارسال</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:user-id-bold"></span></span>
            <select class="form-select" id="sender_operator_user_id" name="sender_operator_user_id" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($operators as $u): ?>
                <option value="<?= e((string)$u['id']) ?>" <?= (string)$u['id'] === $old['sender_operator_user_id'] ? 'selected' : '' ?>>
                  <?= e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= e($u['national_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="driver_user_id">کد راننده حمل‌کننده</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:bus-bold"></span></span>
            <select class="form-select" id="driver_user_id" name="driver_user_id" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($drivers as $u): ?>
                <option value="<?= e((string)$u['id']) ?>" <?= (string)$u['id'] === $old['driver_user_id'] ? 'selected' : '' ?>>
                  <?= e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= e($u['national_code']) ?>)
                </option>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
