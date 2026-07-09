<?php
/**
 * تخصیص پلمپ به منطقه (یا بازگشت به انبار مرکزی) — فقط ادمین
 * قانون: پلمپ الصاق‌شده به بارنامه، قابل تخصیص مجدد یا بازگشت نیست
 * مگر اینکه ابتدا از بارنامه جدا شود.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';
require_once __DIR__ . '/../helpers/seals.php';

require_seals_master_access();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$seal = null;
$errors = [];
$regions = [];

try {
    $regions = db()->query('SELECT region_code, region_name FROM regions ORDER BY region_name')->fetchAll();
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

if ($seal['seal_status'] === 'الصاق شده') {
    set_flash('danger', 'این پلمپ به یک بارنامه الصاق شده و باید ابتدا جدا شود.');
    header('Location: ' . BASE_URL . '/seals/view.php?id=' . $seal['id']);
    exit;
}

$selectedRegion = $seal['region_id'] !== null ? (string)$seal['region_id'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $selectedRegion = trim((string)($_POST['region_id'] ?? ''));
        $regionId = $selectedRegion !== '' ? (int)$selectedRegion : null;

        if ($regionId !== null) {
            try {
                $stmt = db()->prepare('SELECT region_code FROM regions WHERE region_code = ? LIMIT 1');
                $stmt->execute([$regionId]);
                if (!$stmt->fetch()) {
                    $errors[] = 'منطقه انتخاب‌شده معتبر نیست.';
                }
            } catch (PDOException $e) {
                error_log('Region validate error: ' . $e->getMessage());
                $errors[] = 'خطایی در بررسی منطقه رخ داد.';
            }
        }

        if (!$errors) {
            $newStatus = $regionId !== null ? 'در انبار منطقه' : 'در انبار مرکزی';
            $actionLabel = $regionId !== null ? 'تخصیص به منطقه' : 'بازگشت به انبار مرکزی';

            try {
                $stmt = db()->prepare(
                    'UPDATE seals SET region_id = ?, seal_status = ?, assigned_region_at = ? WHERE id = ?'
                );
                $stmt->execute([
                    $regionId,
                    $newStatus,
                    $regionId !== null ? date('Y-m-d H:i:s') : null,
                    $seal['id'],
                ]);
                log_seal_movement(
                    $seal['id'], $actionLabel, $seal['seal_status'], $newStatus,
                    $regionId, null, (int)($_SESSION['user_id'] ?? 0)
                );
                set_flash('success', 'پلمپ «' . $seal['seal_id'] . '» با موفقیت به‌روزرسانی شد.');
                header('Location: ' . BASE_URL . '/seals/list.php');
                exit;
            } catch (PDOException $e) {
                error_log('Assign seal region error: ' . $e->getMessage());
                $errors[] = 'خطایی در تخصیص منطقه رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'تخصیص پلمپ به منطقه';
$active = 'seals';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">تخصیص پلمپ به منطقه</h1>
  <p class="text-muted small mb-0">منطقه مقصد این پلمپ را انتخاب کنید یا آن را به انبار مرکزی بازگردانید.</p>
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
        <div class="text-muted small mt-2">تاریخ ایجاد: <?= e(to_jalali_display($seal['created_at'])) ?></div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card panel-card h-100">
      <div class="card-body p-4">
        <form method="post" action="<?= BASE_URL ?>/seals/assign_region.php" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((string)$seal['id']) ?>">

          <label class="form-label" for="region_id">منطقه</label>
          <div class="input-group mb-3">
            <span class="input-group-text"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
            <select class="form-select" id="region_id" name="region_id">
              <option value="">— بازگشت به انبار مرکزی —</option>
              <?php foreach ($regions as $r): ?>
                <option value="<?= e((string)$r['region_code']) ?>" <?= (string)$r['region_code'] === $selectedRegion ? 'selected' : '' ?>>
                  <?= e($r['region_name']) ?> (<?= e((string)$r['region_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

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
