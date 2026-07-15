<?php
/**
 * تخصیص/تغییر پلمپ بارنامه از صفحه بارنامه
 * دسترسی: ادمین یا کاربر منطقه (فقط بارنامه‌های مرتبط با منطقه خودش)
 * قانون: فقط پلمپ در وضعیت «در انبار منطقه» (یا پلمپ فعلی همین بارنامه) قابل انتخاب است.
 * جهت رابطه در دیتابیس از سمت seals.fuel_waybill_id است.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';
require_once __DIR__ . '/../helpers/seals.php';

require_seal_attach_access();

$myRegionId = session_region_id();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$waybill = null;
$errors = [];
$seals = [];

try {
    $stmt = db()->prepare(
        'SELECT w.*, ol.title AS origin_title, ol.region_id AS origin_region_id,
                dl.title AS destination_title, dl.region_id AS destination_region_id
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         WHERE w.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $waybill = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Assign seal fetch error: ' . $e->getMessage());
}

if (!$waybill) {
    set_flash('danger', 'بارنامه مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/waybills/list.php');
    exit;
}

// کاربر منطقه فقط برای بارنامه مرتبط با منطقه خودش مجاز است
if ($myRegionId !== null) {
    $inMyRegion = ((int)$waybill['origin_region_id'] === $myRegionId) || ((int)$waybill['destination_region_id'] === $myRegionId);
    if (!$inMyRegion) {
        set_flash('danger', 'شما مجاز به مدیریت این بارنامه نیستید.');
        header('Location: ' . BASE_URL . '/waybills/list.php');
        exit;
    }
}

$waybillRegionIds = array_values(array_unique([(int)$waybill['origin_region_id'], (int)$waybill['destination_region_id']]));

$currentSeal = null;
try {
    $stmt = db()->prepare('SELECT * FROM seals WHERE fuel_waybill_id = ? LIMIT 1');
    $stmt->execute([$waybill['id']]);
    $currentSeal = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Current seal fetch error: ' . $e->getMessage());
}
$selectedSeal = $currentSeal ? (string)$currentSeal['id'] : '';

try {
    $placeholders = implode(',', array_fill(0, count($waybillRegionIds), '?'));
    $stmt = db()->prepare(
        "SELECT id, seal_id, region_id, fuel_waybill_id FROM seals
         WHERE (seal_status = 'در انبار منطقه' AND region_id IN ($placeholders)) OR fuel_waybill_id = ?
         ORDER BY seal_id"
    );
    $stmt->execute([...$waybillRegionIds, $waybill['id']]);
    $seals = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Seals list fetch error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست پلمپ‌ها رخ داد.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $selectedSeal = trim((string)($_POST['seal_id'] ?? ''));
        $sealId = $selectedSeal !== '' ? (int)$selectedSeal : null;
        $newSeal = null;

        if ($sealId !== null) {
            try {
                $stmt = db()->prepare('SELECT * FROM seals WHERE id = ? LIMIT 1');
                $stmt->execute([$sealId]);
                $newSeal = $stmt->fetch();
                if (!$newSeal) {
                    $errors[] = 'پلمپ انتخاب‌شده معتبر نیست.';
                } elseif (
                    !in_array((int)$newSeal['region_id'], $waybillRegionIds, true)
                    && (int)$newSeal['fuel_waybill_id'] !== (int)$waybill['id']
                ) {
                    $errors[] = 'پلمپ انتخاب‌شده مرتبط با منطقه این بارنامه نیست.';
                } elseif (!in_array($newSeal['seal_status'], ['در انبار منطقه', 'الصاق شده'], true)) {
                    $errors[] = 'پلمپ انتخاب‌شده در وضعیت قابل‌الصاق قرار ندارد.';
                }
            } catch (PDOException $e) {
                error_log('Seal validate error: ' . $e->getMessage());
                $errors[] = 'خطایی در بررسی پلمپ رخ داد.';
            }
        }

        if (!$errors) {
            try {
                $pdo = db();
                $pdo->beginTransaction();

                if ($currentSeal && (int)$currentSeal['id'] !== $sealId) {
                    $stmt = $pdo->prepare(
                        "UPDATE seals SET fuel_waybill_id = NULL, seal_status = 'در انبار منطقه', attached_waybill_at = NULL WHERE id = ?"
                    );
                    $stmt->execute([$currentSeal['id']]);
                    log_seal_movement(
                        (int)$currentSeal['id'], 'جدا شدن از بارنامه', $currentSeal['seal_status'], 'در انبار منطقه',
                        (int)$currentSeal['region_id'], null, (int)($_SESSION['user_id'] ?? 0)
                    );
                }

                if ($sealId !== null && (!$currentSeal || (int)$currentSeal['id'] !== $sealId)) {
                    $stmt = $pdo->prepare(
                        "UPDATE seals SET fuel_waybill_id = ?, seal_status = 'الصاق شده', attached_waybill_at = ? WHERE id = ?"
                    );
                    $stmt->execute([$waybill['id'], date('Y-m-d H:i:s'), $sealId]);
                    log_seal_movement(
                        $sealId, 'الصاق به بارنامه', $newSeal['seal_status'], 'الصاق شده',
                        (int)$newSeal['region_id'], $waybill['id'], (int)($_SESSION['user_id'] ?? 0)
                    );
                }

                $pdo->commit();
                set_flash('success', 'پلمپ بارنامه «' . $waybill['waybill_number'] . '» با موفقیت به‌روزرسانی شد.');
                header('Location: ' . BASE_URL . '/waybills/list.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Assign seal update error: ' . $e->getMessage());
                $errors[] = 'خطایی در تخصیص پلمپ رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'تخصیص پلمپ';
$active = 'waybills';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">تخصیص پلمپ بارنامه</h1>
  <p class="text-muted small mb-0">پلمپی که باید روی این بارنامه الصاق شود را انتخاب کنید.</p>
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
        <form method="post" action="<?= BASE_URL ?>/waybills/assign_seal.php" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((string)$waybill['id']) ?>">

          <label class="form-label" for="seal_id">پلمپ</label>
          <div class="input-group mb-3">
            <span class="input-group-text"><span class="iconify" data-icon="solar:lock-password-unlocked-bold"></span></span>
            <select class="form-select" id="seal_id" name="seal_id">
              <option value="">— بدون پلمپ —</option>
              <?php foreach ($seals as $s): ?>
                <option value="<?= e((string)$s['id']) ?>" <?= (string)$s['id'] === $selectedSeal ? 'selected' : '' ?>>
                  <?= e($s['seal_id']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (!$seals): ?>
            <div class="text-warning small mb-3">هیچ پلمپ آماده الصاقی در منطقه این بارنامه یافت نشد.</div>
          <?php endif; ?>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
              <span class="iconify" data-icon="solar:diskette-bold"></span> ذخیره
            </button>
            <a href="<?= BASE_URL ?>/waybills/edit.php?id=<?= e((string)$waybill['id']) ?>" class="btn btn-outline-secondary">بازگشت</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
