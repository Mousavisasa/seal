<?php
/**
 * ایجاد مبدا/مقصد جدید
 * locations.region_id به regions.region_code ارجاع دارد
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

$errors = [];
$old = ['location_code' => '', 'region_id' => '', 'title' => '', 'lat' => '', 'lon' => ''];
$regions = [];

try {
    $regions = db()->query('SELECT region_code, region_name FROM regions ORDER BY region_name')->fetchAll();
} catch (PDOException $e) {
    error_log('Regions fetch error: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $old['location_code'] = trim((string)($_POST['location_code'] ?? ''));
        $old['region_id']     = (string)($_POST['region_id'] ?? '');
        $old['title']         = trim((string)($_POST['title'] ?? ''));
        $old['lat']           = trim((string)($_POST['lat'] ?? ''));
        $old['lon']           = trim((string)($_POST['lon'] ?? ''));

        if ($old['location_code'] === '' || mb_strlen($old['location_code']) > 10) {
            $errors[] = 'کد مکان الزامی است و باید حداکثر ۱۰ کاراکتر باشد.';
        }
        if (mb_strlen($old['title']) < 2) {
            $errors[] = 'عنوان مکان باید حداقل ۲ حرف باشد.';
        }
        $regionId = (int)$old['region_id'];
        if ($regionId <= 0) {
            $errors[] = 'انتخاب منطقه الزامی است.';
        }
        $lat = $old['lat'] !== '' ? $old['lat'] : '0';
        $lon = $old['lon'] !== '' ? $old['lon'] : '0';
        if (!is_numeric($lat) || (float)$lat < -90 || (float)$lat > 90) {
            $errors[] = 'مقدار عرض جغرافیایی (lat) معتبر نیست.';
        }
        if (!is_numeric($lon) || (float)$lon < -180 || (float)$lon > 180) {
            $errors[] = 'مقدار طول جغرافیایی (lon) معتبر نیست.';
        }

        if (!$errors) {
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
            try {
                $stmt = db()->prepare('SELECT id FROM locations WHERE location_code = ? LIMIT 1');
                $stmt->execute([$old['location_code']]);
                if ($stmt->fetch()) {
                    $errors[] = 'مکانی با این کد قبلاً ثبت شده است.';
                } else {
                    $stmt = db()->prepare(
                        'INSERT INTO locations (location_code, region_id, title, lat, lon) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $old['location_code'],
                        $regionId,
                        $old['title'],
                        (float)$lat,
                        (float)$lon,
                    ]);
                    set_flash('success', 'مکان «' . $old['title'] . '» با موفقیت ایجاد شد.');
                    header('Location: ' . BASE_URL . '/locations/list.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Create location error: ' . $e->getMessage());
                $errors[] = 'خطایی در ثبت مکان رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'مبدا/مقصد جدید';
$active = 'locations';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">ایجاد مبدا/مقصد جدید</h1>
  <p class="text-muted small mb-0">اطلاعات مکان را کامل و دقیق وارد کنید.</p>
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
    <form method="post" action="<?= BASE_URL ?>/locations/create.php" novalidate>
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="location_code">کد مکان</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:hashtag-square-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="location_code" name="location_code" required maxlength="10"
                   value="<?= e($old['location_code']) ?>" placeholder="مثلاً LOC-005">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="region_id">منطقه</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
            <select class="form-select" id="region_id" name="region_id" required>
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($regions as $r): ?>
                <option value="<?= e((string)$r['region_code']) ?>" <?= (string)$r['region_code'] === $old['region_id'] ? 'selected' : '' ?>>
                  <?= e($r['region_name']) ?> (<?= e((string)$r['region_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="title">عنوان مکان</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:signpost-bold"></span></span>
            <input type="text" class="form-control" id="title" name="title" required minlength="2"
                   value="<?= e($old['title']) ?>" placeholder="مثلاً انبار نفت مرکزی">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="lat">عرض جغرافیایی (lat) <span class="text-muted small">(اختیاری)</span></label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:global-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="lat" name="lat"
                   value="<?= e($old['lat']) ?>" placeholder="مثلاً 35.6892">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="lon">طول جغرافیایی (lon) <span class="text-muted small">(اختیاری)</span></label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:global-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="lon" name="lon"
                   value="<?= e($old['lon']) ?>" placeholder="مثلاً 51.3890">
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <span class="iconify" data-icon="solar:add-circle-bold"></span> ثبت مکان
        </button>
        <a href="<?= BASE_URL ?>/locations/list.php" class="btn btn-outline-secondary">انصراف</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
