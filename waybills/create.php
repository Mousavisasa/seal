<?php
/**
 * ایجاد بارنامه سوخت جدید
 * نکات این نسخه:
 * - متصدی مبدا/مقصد و راننده در این فرم دریافت نمی‌شوند؛ بعداً تخصیص داده می‌شوند
 * - کد منطقه مبدا/مقصد دریافت نمی‌شود؛ از طریق مکان انتخاب‌شده (locations.region_id) مشخص می‌شود
 * - کاربر با نقش «منطقه» فقط می‌تواند بارنامه‌ای ثبت کند که مبدا یا مقصد آن در منطقه خودش باشد
 * - تاریخ صدور به‌صورت شمسی از کاربر گرفته و به میلادی تبدیل می‌شود
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';
require_once __DIR__ . '/../helpers/seals.php';

require_waybill_access();

$myRegionId = session_region_id(); // null برای ادمین (بدون محدودیت)

$errors = [];
$old = [
    'origin_location_id'      => '',
    'destination_location_id' => '',
    'distance_km'              => '',
    'product_type'             => 'بنزین',
    'waybill_number'           => '',
    'issue_date_jalali'        => today_jalali(),
    'origin_operator_user_id'  => '',
    'driver_user_id'           => '',
    'seal_id'                  => '',
];

$locations = [];
$operators = [];
$drivers   = [];
$seals     = [];
$canAttachSeal = can_attach_seal_to_waybill();

try {
    $locations = db()->query(
        'SELECT l.id, l.location_code, l.title, l.region_id, l.lat, l.lon, r.region_name
         FROM locations l INNER JOIN regions r ON r.region_code = l.region_id
         ORDER BY l.title'
    )->fetchAll();

    $operators = db()->query(
        "SELECT id, national_code, first_name, last_name FROM users WHERE user_type = 'operator' ORDER BY first_name"
    )->fetchAll();

    $drivers = db()->query(
        "SELECT id, national_code, first_name, last_name FROM users WHERE user_type = 'driver' ORDER BY first_name"
    )->fetchAll();

    if ($canAttachSeal) {
        if ($myRegionId !== null) {
            $stmt = db()->prepare("SELECT id, seal_id, region_id FROM seals WHERE seal_status = 'در انبار منطقه' AND region_id = ? ORDER BY seal_id");
            $stmt->execute([$myRegionId]);
        } else {
            $stmt = db()->query("SELECT id, seal_id, region_id FROM seals WHERE seal_status = 'در انبار منطقه' ORDER BY seal_id");
        }
        $seals = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Waybill create form data error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در بارگذاری فرم رخ داد.');
}

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
        $old['origin_operator_user_id']  = trim((string)($_POST['origin_operator_user_id'] ?? ''));
        $old['driver_user_id']           = trim((string)($_POST['driver_user_id'] ?? ''));
        $old['seal_id']                   = $canAttachSeal ? trim((string)($_POST['seal_id'] ?? '')) : '';

        if ($old['waybill_number'] === '' || mb_strlen($old['waybill_number']) > 50) {
            $errors[] = 'شماره بارنامه الزامی است و باید حداکثر ۵۰ کاراکتر باشد.';
        }
        if ($old['distance_km'] !== '' && !is_positive_number($old['distance_km'])) {
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
        if (
            (int)$old['origin_location_id'] > 0
            && (int)$old['destination_location_id'] > 0
            && (int)$old['origin_location_id'] === (int)$old['destination_location_id']
        ) {
            $errors[] = 'مبدا و مقصد نمی‌توانند یکسان باشند.';
        }
        if ((int)$old['origin_operator_user_id'] <= 0) {
            $errors[] = 'انتخاب متصدی مبدا الزامی است.';
        }

        $originLocation = null;
        $destinationLocation = null;

        if (!$errors) {
            try {
                $pdo = db();

                $stmt = $pdo->prepare('SELECT id, region_id FROM locations WHERE id = ? LIMIT 1');
                $stmt->execute([(int)$old['origin_location_id']]);
                $originLocation = $stmt->fetch();
                if (!$originLocation) {
                    $errors[] = 'مبدا انتخاب‌شده معتبر نیست.';
                }

                $stmt->execute([(int)$old['destination_location_id']]);
                $destinationLocation = $stmt->fetch();
                if (!$destinationLocation) {
                    $errors[] = 'مقصد انتخاب‌شده معتبر نیست.';
                }

                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'operator' LIMIT 1");
                $stmt->execute([(int)$old['origin_operator_user_id']]);
                if (!$stmt->fetch()) {
                    $errors[] = 'متصدی مبدا انتخاب‌شده معتبر نیست یا نقش متصدی ندارد.';
                }

                if ($old['driver_user_id'] !== '') {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'driver' LIMIT 1");
                    $stmt->execute([(int)$old['driver_user_id']]);
                    if (!$stmt->fetch()) {
                        $errors[] = 'راننده انتخاب‌شده معتبر نیست یا نقش راننده ندارد.';
                    }
                }

                if ($canAttachSeal && $old['seal_id'] !== '') {
                    $stmt = $pdo->prepare("SELECT id, seal_id, region_id FROM seals WHERE id = ? AND seal_status = 'در انبار منطقه' LIMIT 1");
                    $stmt->execute([(int)$old['seal_id']]);
                    $selectedSealRow = $stmt->fetch();
                    if (!$selectedSealRow) {
                        $errors[] = 'پلمپ انتخاب‌شده معتبر نیست یا در وضعیت قابل‌الصاق قرار ندارد.';
                    } elseif ($myRegionId !== null && (int)$selectedSealRow['region_id'] !== $myRegionId) {
                        $errors[] = 'پلمپ انتخاب‌شده متعلق به منطقه شما نیست.';
                    }
                }
            } catch (PDOException $e) {
                error_log('Waybill relation validate error: ' . $e->getMessage());
                $errors[] = 'خطایی در بررسی اطلاعات رخ داد.';
            }
        }

        // کاربر منطقه فقط مجاز به ثبت بارنامه‌ای است که مبدا یا مقصد آن در منطقه خودش باشد
        if (!$errors && $myRegionId !== null && $originLocation && $destinationLocation) {
            $inMyRegion = ((int)$originLocation['region_id'] === $myRegionId) || ((int)$destinationLocation['region_id'] === $myRegionId);
            if (!$inMyRegion) {
                $errors[] = 'شما فقط می‌توانید بارنامه‌ای ثبت کنید که مبدا یا مقصد آن در منطقه شما باشد.';
            }
        }

        // بررسی یکتابودن شماره بارنامه و ثبت نهایی
        if (!$errors) {
            try {
                $stmt = db()->prepare('SELECT id FROM fuel_waybills WHERE waybill_number = ? LIMIT 1');
                $stmt->execute([$old['waybill_number']]);
                if ($stmt->fetch()) {
                    $errors[] = 'بارنامه‌ای با این شماره قبلاً ثبت شده است.';
                } else {
                    $stmt = db()->prepare(
                        'INSERT INTO fuel_waybills
                            (origin_location_id, destination_location_id, distance_km, product_type,
                             waybill_number, issue_date, send_status, origin_operator_user_id,
                             driver_user_id, created_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        (int)$old['origin_location_id'],
                        (int)$old['destination_location_id'],
                        $old['distance_km'] !== '' ? (float)$old['distance_km'] : 0,
                        $old['product_type'],
                        $old['waybill_number'],
                        $issueDateGregorian,
                        'ثبت شده',
                        (int)$old['origin_operator_user_id'],
                        $old['driver_user_id'] !== '' ? (int)$old['driver_user_id'] : null,
                        (int)($_SESSION['user_id'] ?? 0),
                    ]);
                    $newWaybillId = (int)db()->lastInsertId();
                    log_waybill_status_change(
                        $newWaybillId,
                        null,
                        'ثبت شده',
                        'waybills/create.php: فرم ایجاد بارنامه',
                        (int)($_SESSION['user_id'] ?? 0),
                        null,
                        $selectedSealRow['seal_id'] ?? null
                    );

                    if ($canAttachSeal && $old['seal_id'] !== '') {
                        $sealStmt = db()->prepare('SELECT * FROM seals WHERE id = ? LIMIT 1');
                        $sealStmt->execute([(int)$old['seal_id']]);
                        $sealRow = $sealStmt->fetch();
                        if ($sealRow) {
                            $updStmt = db()->prepare(
                                "UPDATE seals SET fuel_waybill_id = ?, seal_status = 'الصاق شده', attached_waybill_at = ? WHERE id = ?"
                            );
                            $updStmt->execute([$newWaybillId, date('Y-m-d H:i:s'), $sealRow['id']]);
                            log_seal_movement(
                                (int)$sealRow['id'], 'الصاق به بارنامه', $sealRow['seal_status'], 'الصاق شده',
                                (int)$sealRow['region_id'], $newWaybillId, (int)($_SESSION['user_id'] ?? 0)
                            );
                        }
                    }

                    set_flash('success', 'بارنامه «' . $old['waybill_number'] . '» با موفقیت ثبت شد. اکنون می‌توانید متصدی مقصد را از فهرست بارنامه‌ها تخصیص دهید.');
                    header('Location: ' . BASE_URL . '/waybills/list.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Create waybill error: ' . $e->getMessage());
                $errors[] = 'خطایی در ثبت بارنامه رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'بارنامه جدید';
$active = 'waybills';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">ایجاد بارنامه سوخت جدید</h1>
  <p class="text-muted small mb-0">
    اطلاعات بارنامه را وارد کنید. تخصیص متصدی و راننده پس از ثبت انجام می‌شود.
    <?php if ($myRegionId !== null): ?>
      <span class="d-block mt-1">شما فقط می‌توانید بارنامه‌ای ثبت کنید که مبدا یا مقصد آن در منطقه شما باشد.</span>
    <?php endif; ?>
  </p>
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
    <form method="post" action="<?= BASE_URL ?>/waybills/create.php" novalidate>
      <?= csrf_field() ?>

      <!-- کلاستر ۱: اطلاعات پایه -->
      <div class="form-section">
        <div class="form-section-head">
          <span class="form-section-icon"><span class="iconify" data-icon="solar:document-text-bold"></span></span>
          <div>
            <div class="form-section-title">
              اطلاعات پایه
              <span class="iconify form-section-hint" data-icon="solar:question-circle-bold" tabindex="0"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    title="شماره بارنامه باید در کل سامانه یکتا باشد. تاریخ صدور به تقویم شمسی وارد می‌شود و هنگام ذخیره به میلادی تبدیل می‌گردد."></span>
            </div>
            <div class="form-section-sub">شماره و تاریخ صدور بارنامه</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="waybill_number">شماره بارنامه <span class="req-mark">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:document-text-bold"></span></span>
              <input type="text" class="form-control ltr-text" id="waybill_number" name="waybill_number" required maxlength="50"
                     value="<?= e($old['waybill_number']) ?>" placeholder="مثلاً WB-1001">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="issue_date_jalali">تاریخ صدور بارنامه <span class="req-mark">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:calendar-bold"></span></span>
              <input type="text" class="form-control ltr-text" id="issue_date_jalali" name="issue_date_jalali" required
                     autocomplete="off" data-jalali-datepicker
                     value="<?= e($old['issue_date_jalali']) ?>" placeholder="مثلاً 1405/04/18">
            </div>
            <div class="form-text">فرمت: سال/ماه/روز (تقویم شمسی)</div>
          </div>
        </div>
      </div>

      <!-- کلاستر ۲: اطلاعات مسیر -->
      <div class="form-section">
        <div class="form-section-head">
          <span class="form-section-icon"><span class="iconify" data-icon="solar:routing-bold"></span></span>
          <div>
            <div class="form-section-title">
              اطلاعات مسیر
              <span class="iconify form-section-hint" data-icon="solar:question-circle-bold" tabindex="0"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    title="مبدا و مقصد نمی‌توانند یکسان باشند. منطقه هر مکان به‌صورت خودکار از همان مکان گرفته می‌شود. مسافت اختیاری است و در صورت خالی‌بودن صفر ثبت می‌شود."></span>
            </div>
            <div class="form-section-sub">مبدا، مقصد، مسافت و نوع فرآورده</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="origin_location_id">کد مبدا <span class="req-mark">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:point-on-map-bold"></span></span>
              <select class="form-select" id="origin_location_id" name="origin_location_id" required>
                <option value="">— انتخاب کنید —</option>
                <?php foreach ($locations as $l): ?>
                  <option value="<?= e((string)$l['id']) ?>"
                          data-region="<?= e((string)$l['region_id']) ?>"
                          data-lat="<?= e((string)$l['lat']) ?>"
                          data-lon="<?= e((string)$l['lon']) ?>"
                          <?= (string)$l['id'] === $old['origin_location_id'] ? 'selected' : '' ?>>
                    <?= e($l['title']) ?> (<?= e($l['location_code']) ?>) — <?= e($l['region_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="destination_location_id">کد مقصد <span class="req-mark">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:point-on-map-bold"></span></span>
              <select class="form-select" id="destination_location_id" name="destination_location_id" required>
                <option value="">— انتخاب کنید —</option>
                <?php foreach ($locations as $l): ?>
                  <option value="<?= e((string)$l['id']) ?>"
                          data-region="<?= e((string)$l['region_id']) ?>"
                          data-lat="<?= e((string)$l['lat']) ?>"
                          data-lon="<?= e((string)$l['lon']) ?>"
                          <?= (string)$l['id'] === $old['destination_location_id'] ? 'selected' : '' ?>>
                    <?= e($l['title']) ?> (<?= e($l['location_code']) ?>) — <?= e($l['region_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="distance_km">مسافت (کیلومتر) <span class="text-muted small">(اختیاری)</span></label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:ruler-bold"></span></span>
              <input type="number" step="0.01" min="0.01" class="form-control ltr-text" id="distance_km" name="distance_km"
                     value="<?= e($old['distance_km']) ?>" placeholder="مثلاً 120.50">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="product_type">نوع فرآورده <span class="req-mark">*</span></label>
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
      </div>

      <!-- کلاستر ۳: اطلاعات ناوگان و لجستیک -->
      <div class="form-section">
        <div class="form-section-head">
          <span class="form-section-icon"><span class="iconify" data-icon="solar:users-group-rounded-bold"></span></span>
          <div>
            <div class="form-section-title">
              اطلاعات ناوگان و لجستیک
              <span class="iconify form-section-hint" data-icon="solar:question-circle-bold" tabindex="0"
                    data-bs-toggle="tooltip" data-bs-placement="top"
                    title="متصدی مبدا هنگام ثبت الزامی است. راننده و پلمپ اختیاری هستند و می‌توانند بعداً تخصیص/الصاق شوند. متصدی مقصد پس از ثبت، از فهرست بارنامه‌ها تعیین می‌شود."></span>
            </div>
            <div class="form-section-sub">متصدی مبدا، راننده و پلمپ</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="origin_operator_user_id">متصدی مبدا <span class="req-mark">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:user-id-bold"></span></span>
              <select class="form-select" id="origin_operator_user_id" name="origin_operator_user_id" required>
                <option value="">— انتخاب کنید —</option>
                <?php foreach ($operators as $u): ?>
                  <option value="<?= e((string)$u['id']) ?>" <?= (string)$u['id'] === $old['origin_operator_user_id'] ? 'selected' : '' ?>>
                    <?= e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= e($u['national_code']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (!$operators): ?>
              <div class="form-text text-warning">هیچ کاربر متصدی‌ای در سامانه ثبت نشده است.</div>
            <?php endif; ?>
          </div>

          <div class="col-md-6">
            <label class="form-label" for="driver_user_id">راننده <span class="text-muted small">(اختیاری)</span></label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:bus-bold"></span></span>
              <select class="form-select" id="driver_user_id" name="driver_user_id">
                <option value="">— بدون راننده —</option>
                <?php foreach ($drivers as $u): ?>
                  <option value="<?= e((string)$u['id']) ?>" <?= (string)$u['id'] === $old['driver_user_id'] ? 'selected' : '' ?>>
                    <?= e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= e($u['national_code']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <?php if ($canAttachSeal): ?>
          <div class="col-md-6">
            <label class="form-label" for="seal_id">پلمپ <span class="text-muted small">(اختیاری)</span></label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:lock-password-unlocked-bold"></span></span>
              <select class="form-select" id="seal_id" name="seal_id">
                <option value="">— بدون پلمپ —</option>
                <?php foreach ($seals as $s): ?>
                  <option value="<?= e((string)$s['id']) ?>" <?= (string)$s['id'] === $old['seal_id'] ? 'selected' : '' ?>>
                    <?= e($s['seal_id']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (!$seals): ?>
              <div class="form-text text-warning">هیچ پلمپ آماده الصاق در منطقه شما یافت نشد.</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="col-12">
            <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
              <span class="iconify fs-5" data-icon="solar:info-circle-bold"></span>
              <span>متصدی مقصد پس از ثبت بارنامه، از طریق فهرست بارنامه‌ها تخصیص داده می‌شود.</span>
            </div>
          </div>
        </div>
      </div>

      <?php if ($myRegionId !== null): ?>
        <input type="hidden" id="my_region_id" value="<?= e((string)$myRegionId) ?>">
      <?php endif; ?>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <span class="iconify" data-icon="solar:add-circle-bold"></span> ثبت بارنامه
        </button>
        <a href="<?= BASE_URL ?>/waybills/list.php" class="btn btn-outline-secondary">انصراف</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
