<?php
/**
 * تعریف پلمپ جدید (انبار مرکزی) — فقط ادمین
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/seals.php';
require_once __DIR__ . '/../helpers/settings.php';

require_seals_master_access();

$errors = [];
// مقادیر پیش‌فرض UUID از بخش «تنظیمات» به‌عنوان پیشنهاد اولیه در فرم نمایش داده می‌شود
$old = [
    'seal_id' => '',
    'seal_password' => '',
    'service_uuid' => get_setting(SETTING_DEFAULT_SERVICE_UUID, ''),
    'characteristic_uuid' => get_setting(SETTING_DEFAULT_CHARACTERISTIC_UUID, ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $old['seal_id'] = trim((string)($_POST['seal_id'] ?? ''));
        $old['service_uuid'] = trim((string)($_POST['service_uuid'] ?? ''));
        $old['characteristic_uuid'] = trim((string)($_POST['characteristic_uuid'] ?? ''));
        $sealPassword = (string)($_POST['seal_password'] ?? '');

        if ($old['seal_id'] === '' || mb_strlen($old['seal_id']) > 50) {
            $errors[] = 'شناسه پلمپ الزامی است و باید حداکثر ۵۰ کاراکتر باشد.';
        }
        if (strlen($sealPassword) < 4) {
            $errors[] = 'رمز پلمپ باید حداقل ۴ کاراکتر باشد.';
        }
        if ($old['service_uuid'] === '' || mb_strlen($old['service_uuid']) > 100) {
            $errors[] = 'Service UUID الزامی است و باید حداکثر ۱۰۰ کاراکتر باشد.';
        }
        if ($old['characteristic_uuid'] === '' || mb_strlen($old['characteristic_uuid']) > 100) {
            $errors[] = 'Characteristic UUID الزامی است و باید حداکثر ۱۰۰ کاراکتر باشد.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare('SELECT id FROM seals WHERE seal_id = ? LIMIT 1');
                $stmt->execute([$old['seal_id']]);
                if ($stmt->fetch()) {
                    $errors[] = 'پلمپی با این شناسه قبلاً ثبت شده است.';
                } else {
                    $stmt = db()->prepare(
                        'INSERT INTO seals (seal_id, seal_password, service_uuid, characteristic_uuid, seal_status, created_by)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $old['seal_id'],
                        password_hash($sealPassword, PASSWORD_DEFAULT),
                        $old['service_uuid'],
                        $old['characteristic_uuid'],
                        'در انبار مرکزی',
                        (int)($_SESSION['user_id'] ?? 0),
                    ]);
                    $newSealId = (int)db()->lastInsertId();
                    log_seal_movement(
                        $newSealId, 'ایجاد', null, 'در انبار مرکزی',
                        null, null, (int)($_SESSION['user_id'] ?? 0), 'ثبت اولیه پلمپ در انبار مرکزی'
                    );
                    set_flash('success', 'پلمپ «' . $old['seal_id'] . '» با موفقیت ثبت شد.');
                    header('Location: ' . BASE_URL . '/seals/list.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Create seal error: ' . $e->getMessage());
                $errors[] = 'خطایی در ثبت پلمپ رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'پلمپ جدید';
$active = 'seals';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">تعریف پلمپ جدید</h1>
  <p class="text-muted small mb-0">پلمپ جدید در انبار مرکزی ثبت می‌شود و بعداً می‌توانید آن را به یک منطقه تخصیص دهید.</p>
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
    <form method="post" action="<?= BASE_URL ?>/seals/create.php" novalidate>
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="seal_id">شناسه پلمپ</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:tag-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="seal_id" name="seal_id" required maxlength="50"
                   value="<?= e($old['seal_id']) ?>" placeholder="مثلاً SEAL-0004">
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="seal_password">رمز پلمپ</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:lock-password-bold"></span></span>
            <input type="password" class="form-control ltr-text" id="seal_password" name="seal_password" required minlength="4" placeholder="حداقل ۴ کاراکتر">
            <button type="button" class="input-group-text toggle-pass" data-target="seal_password" aria-label="نمایش رمز">
              <span class="iconify" data-icon="solar:eye-bold"></span>
            </button>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="service_uuid">شناسه سرویس (Service UUID)</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="mdi:bluetooth"></span></span>
            <input type="text" class="form-control ltr-text" id="service_uuid" name="service_uuid" required maxlength="100" value="<?= e($old['service_uuid']) ?>" placeholder="مثلاً 0000180f-0000-1000-8000-00805f9b34fb">
          </div>
          <div class="form-text">مقدار پیشنهادی از <a href="<?= BASE_URL ?>/settings/index.php" target="_blank">تنظیمات برنامه</a> پر شده؛ در صورت نیاز آن را ویرایش کنید.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="characteristic_uuid">شناسه مشخصه (Characteristic UUID)</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="mdi:bluetooth-audio"></span></span>
            <input type="text" class="form-control ltr-text" id="characteristic_uuid" name="characteristic_uuid" required maxlength="100" value="<?= e($old['characteristic_uuid']) ?>" placeholder="مثلاً 00002a19-0000-1000-8000-00805f9b34fb">
          </div>
          <div class="form-text">مقدار پیشنهادی از <a href="<?= BASE_URL ?>/settings/index.php" target="_blank">تنظیمات برنامه</a> پر شده؛ در صورت نیاز آن را ویرایش کنید.</div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <span class="iconify" data-icon="solar:add-circle-bold"></span> ثبت پلمپ
        </button>
        <a href="<?= BASE_URL ?>/seals/list.php" class="btn btn-outline-secondary">انصراف</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
