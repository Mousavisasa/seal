<?php
/**
 * تنظیمات برنامه — فقط ادمین
 * در حال حاضر شامل مقادیر پیش‌فرض BLE (Service UUID و Characteristic UUID)
 * است که در فرم «تعریف پلمپ جدید» به‌عنوان مقدار پیشنهادی نمایش داده می‌شود.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/settings.php';

require_admin();

$errors = [];

$current = get_settings([
    SETTING_DEFAULT_SERVICE_UUID        => '',
    SETTING_DEFAULT_CHARACTERISTIC_UUID => '',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $current[SETTING_DEFAULT_SERVICE_UUID]        = trim((string)($_POST['default_service_uuid'] ?? ''));
        $current[SETTING_DEFAULT_CHARACTERISTIC_UUID] = trim((string)($_POST['default_characteristic_uuid'] ?? ''));

        if (mb_strlen($current[SETTING_DEFAULT_SERVICE_UUID]) > 100) {
            $errors[] = 'Service UUID پیش‌فرض باید حداکثر ۱۰۰ کاراکتر باشد.';
        }
        if (mb_strlen($current[SETTING_DEFAULT_CHARACTERISTIC_UUID]) > 100) {
            $errors[] = 'Characteristic UUID پیش‌فرض باید حداکثر ۱۰۰ کاراکتر باشد.';
        }

        if (!$errors) {
            $ok = set_setting(SETTING_DEFAULT_SERVICE_UUID, $current[SETTING_DEFAULT_SERVICE_UUID])
                && set_setting(SETTING_DEFAULT_CHARACTERISTIC_UUID, $current[SETTING_DEFAULT_CHARACTERISTIC_UUID]);

            if ($ok) {
                set_flash('success', 'تنظیمات با موفقیت ذخیره شد.');
                header('Location: ' . BASE_URL . '/settings/index.php');
                exit;
            }
            $errors[] = 'خطایی در ذخیره تنظیمات رخ داد. لطفاً دوباره تلاش کنید.';
        }
    }
}

$page_title = 'تنظیمات برنامه';
$active = 'settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">تنظیمات برنامه</h1>
  <p class="text-muted small mb-0">مقادیر پیش‌فرض این صفحه، هنگام تعریف پلمپ جدید به‌عنوان پیشنهاد در فرم نمایش داده می‌شود و شما می‌توانید در همان‌جا آن را ویرایش کنید.</p>
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
    <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
      <span class="iconify fs-5" data-icon="mdi:bluetooth"></span> مقادیر پیش‌فرض بلوتوث پلمپ (BLE)
    </h2>
    <form method="post" action="<?= BASE_URL ?>/settings/index.php" novalidate>
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="default_service_uuid">Service UUID پیش‌فرض</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="mdi:bluetooth"></span></span>
            <input type="text" class="form-control ltr-text" id="default_service_uuid" name="default_service_uuid"
                   maxlength="100" value="<?= e($current[SETTING_DEFAULT_SERVICE_UUID]) ?>"
                   placeholder="مثلاً 0000180f-0000-1000-8000-00805f9b34fb">
          </div>
          <div class="form-text">در فرم «پلمپ جدید» به‌عنوان مقدار پیشنهادی Service UUID نمایش داده می‌شود.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="default_characteristic_uuid">Characteristic UUID پیش‌فرض</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="mdi:bluetooth-audio"></span></span>
            <input type="text" class="form-control ltr-text" id="default_characteristic_uuid" name="default_characteristic_uuid"
                   maxlength="100" value="<?= e($current[SETTING_DEFAULT_CHARACTERISTIC_UUID]) ?>"
                   placeholder="مثلاً 00002a19-0000-1000-8000-00805f9b34fb">
          </div>
          <div class="form-text">در فرم «پلمپ جدید» به‌عنوان مقدار پیشنهادی Characteristic UUID نمایش داده می‌شود.</div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <span class="iconify" data-icon="solar:diskette-bold"></span> ذخیره تنظیمات
        </button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
