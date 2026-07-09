<?php
/**
 * الصاق پلمپ به بارنامه (یا جداکردن از بارنامه فعلی)
 * دسترسی: ادمین یا کاربر منطقه (فقط پلمپ‌های منطقه خودش و بارنامه‌های مرتبط با منطقه خودش)
 * قانون: فقط پلمپ در وضعیت «در انبار منطقه» قابل الصاق است.
 * هر بارنامه حداکثر یک پلمپ فعال دارد (uq_seal_waybill در دیتابیس این را تضمین می‌کند).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';
require_once __DIR__ . '/../helpers/seals.php';

require_seal_attach_access();

$myRegionId = session_region_id();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$seal = null;
$errors = [];
$waybills = [];

try {
    $stmt = db()->prepare('SELECT * FROM seals WHERE id = ? LIMIT 1');
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

// کاربر منطقه فقط مجاز به الصاق پلمپ‌های تخصیص‌یافته به منطقه خودش است
if ($myRegionId !== null && (int)$seal['region_id'] !== $myRegionId) {
    set_flash('danger', 'این پلمپ متعلق به منطقه شما نیست.');
    header('Location: ' . BASE_URL . '/seals/list.php');
    exit;
}

if (!in_array($seal['seal_status'], ['در انبار منطقه', 'الصاق شده'], true)) {
    set_flash('danger', 'این پلمپ در وضعیت قابل‌الصاق قرار ندارد.');
    header('Location: ' . BASE_URL . '/seals/list.php');
    exit;
}

// فهرست بارنامه‌های مرتبط با منطقه پلمپ که هنوز پلمپی ندارند (یا همین پلمپ را دارند)
try {
    $sql = 'SELECT w.id, w.waybill_number, w.issue_date, ol.title AS origin_title, dl.title AS destination_title
            FROM fuel_waybills w
            INNER JOIN locations ol ON ol.id = w.origin_location_id
            INNER JOIN locations dl ON dl.id = w.destination_location_id
            WHERE (ol.region_id = ? OR dl.region_id = ?)
              AND (w.id NOT IN (SELECT fuel_waybill_id FROM seals WHERE fuel_waybill_id IS NOT NULL) OR w.id = ?)
            ORDER BY w.id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute([(int)$seal['region_id'], (int)$seal['region_id'], (int)($seal['fuel_waybill_id'] ?? 0)]);
    $waybills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Waybills for seal attach fetch error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست بارنامه‌ها رخ داد.');
}

$selectedWaybill = $seal['fuel_waybill_id'] !== null ? (string)$seal['fuel_waybill_id'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $selectedWaybill = trim((string)($_POST['fuel_waybill_id'] ?? ''));
        $waybillId = $selectedWaybill !== '' ? (int)$selectedWaybill : null;

        if ($waybillId !== null) {
            try {
                $stmt = db()->prepare(
                    'SELECT w.id FROM fuel_waybills w
                     INNER JOIN locations ol ON ol.id = w.origin_location_id
                     INNER JOIN locations dl ON dl.id = w.destination_location_id
                     WHERE w.id = ? AND (ol.region_id = ? OR dl.region_id = ?) LIMIT 1'
                );
                $stmt->execute([$waybillId, (int)$seal['region_id'], (int)$seal['region_id']]);
                if (!$stmt->fetch()) {
                    $errors[] = 'بارنامه انتخاب‌شده معتبر نیست یا مرتبط با منطقه این پلمپ نیست.';
                } else {
                    $stmt = db()->prepare('SELECT id FROM seals WHERE fuel_waybill_id = ? AND id <> ? LIMIT 1');
                    $stmt->execute([$waybillId, $seal['id']]);
                    if ($stmt->fetch()) {
                        $errors[] = 'این بارنامه قبلاً پلمپ دیگری دارد.';
                    }
                }
            } catch (PDOException $e) {
                error_log('Waybill validate error: ' . $e->getMessage());
                $errors[] = 'خطایی در بررسی بارنامه رخ داد.';
            }
        }

        if (!$errors) {
            $newStatus = $waybillId !== null ? 'الصاق شده' : 'در انبار منطقه';
            $actionLabel = $waybillId !== null ? 'الصاق به بارنامه' : 'جدا شدن از بارنامه';

            try {
                $stmt = db()->prepare(
                    'UPDATE seals SET fuel_waybill_id = ?, seal_status = ?, attached_waybill_at = ? WHERE id = ?'
                );
                $stmt->execute([
                    $waybillId,
                    $newStatus,
                    $waybillId !== null ? date('Y-m-d H:i:s') : null,
                    $seal['id'],
                ]);
                log_seal_movement(
                    $seal['id'], $actionLabel, $seal['seal_status'], $newStatus,
                    (int)$seal['region_id'], $waybillId, (int)($_SESSION['user_id'] ?? 0)
                );
                set_flash('success', 'پلمپ «' . $seal['seal_id'] . '» با موفقیت به‌روزرسانی شد.');
                header('Location: ' . BASE_URL . '/seals/list.php');
                exit;
            } catch (PDOException $e) {
                error_log('Attach seal error: ' . $e->getMessage());
                $errors[] = 'خطایی در الصاق پلمپ رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'الصاق پلمپ به بارنامه';
$active = 'seals';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">الصاق پلمپ به بارنامه</h1>
  <p class="text-muted small mb-0">بارنامه‌ای که این پلمپ باید روی آن نصب شود را انتخاب کنید.</p>
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
        <div class="fw-bold fs-5 ltr-text mb-2"><?= e($seal['seal_id']) ?></div>
        <span class="status-badge <?= e(seal_status_class($seal['seal_status'])) ?>"><?= e($seal['seal_status']) ?></span>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card panel-card h-100">
      <div class="card-body p-4">
        <form method="post" action="<?= BASE_URL ?>/seals/attach_waybill.php" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((string)$seal['id']) ?>">

          <label class="form-label" for="fuel_waybill_id">بارنامه</label>
          <div class="input-group mb-3">
            <span class="input-group-text"><span class="iconify" data-icon="solar:document-text-bold"></span></span>
            <select class="form-select" id="fuel_waybill_id" name="fuel_waybill_id">
              <option value="">— جدا از بارنامه —</option>
              <?php foreach ($waybills as $w): ?>
                <option value="<?= e((string)$w['id']) ?>" <?= (string)$w['id'] === $selectedWaybill ? 'selected' : '' ?>>
                  <?= e($w['waybill_number']) ?> (<?= e($w['origin_title']) ?> ← <?= e($w['destination_title']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (!$waybills): ?>
            <div class="text-warning small mb-3">هیچ بارنامه بدون پلمپ در این منطقه یافت نشد.</div>
          <?php endif; ?>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
              <span class="iconify" data-icon="solar:diskette-bold"></span> ذخیره
            </button>
            <a href="<?= BASE_URL ?>/seals/list.php" class="btn btn-outline-secondary">بازگشت</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
