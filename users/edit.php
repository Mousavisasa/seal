<?php
/**
 * ویرایش کاربر توسط ادمین
 * امکانات: تغییر نام، نقش، منطقه (فقط برای نقش «منطقه»)، و فعال/غیرفعال‌سازی حساب
 * تغییر رمز عبور از این صفحه انجام نمی‌شود؛ برای آن از «بازنشانی رمز» استفاده کنید.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$user = null;
$errors = [];
$regions = [];

try {
    $regions = db()->query('SELECT region_code, region_name FROM regions ORDER BY region_name')->fetchAll();
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('User fetch error: ' . $e->getMessage());
}

if (!$user) {
    set_flash('danger', 'کاربر مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/users/list.php');
    exit;
}

$old = [
    'national_code' => $user['national_code'],
    'first_name'    => $user['first_name'],
    'last_name'     => $user['last_name'],
    'user_type'     => $user['user_type'],
    'region_id'     => $user['region_id'] !== null ? (string)$user['region_id'] : '',
    'is_active'     => (int)$user['is_active'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $old['first_name'] = trim((string)($_POST['first_name'] ?? ''));
        $old['last_name']  = trim((string)($_POST['last_name'] ?? ''));
        $old['user_type']  = (string)($_POST['user_type'] ?? '');
        $old['region_id']  = trim((string)($_POST['region_id'] ?? ''));
        $old['is_active']  = isset($_POST['is_active']) ? 1 : 0;

        if (mb_strlen($old['first_name']) < 2) {
            $errors[] = 'نام باید حداقل ۲ حرف باشد.';
        }
        if (mb_strlen($old['last_name']) < 2) {
            $errors[] = 'نام خانوادگی باید حداقل ۲ حرف باشد.';
        }
        if (!array_key_exists($old['user_type'], USER_TYPES)) {
            $errors[] = 'نقش انتخاب‌شده معتبر نیست.';
        }

        // جلوگیری از غیرفعال‌کردن یا خارج‌کردن آخرین/تنها ادمین از نقش ادمین توسط خودش
        if ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0)) {
            if ($old['user_type'] !== 'admin') {
                $errors[] = 'شما نمی‌توانید نقش حساب خودتان را از ادمین خارج کنید.';
            }
            if ($old['is_active'] === 0) {
                $errors[] = 'شما نمی‌توانید حساب خودتان را غیرفعال کنید.';
            }
        }

        $regionId = null;
        if ($old['user_type'] === 'region') {
            if ($old['region_id'] === '' || (int)$old['region_id'] <= 0) {
                $errors[] = 'برای کاربر با نقش «منطقه»، انتخاب منطقه الزامی است.';
            } else {
                $regionId = (int)$old['region_id'];
            }
        }
        // اگر نقش «منطقه» نیست، منطقه همیشه پاک می‌شود (حتی اگر قبلاً مقداری داشته)

        if (!$errors && $regionId !== null) {
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
                $stmt = db()->prepare(
                    'UPDATE users SET first_name = ?, last_name = ?, user_type = ?, region_id = ?, is_active = ? WHERE id = ?'
                );
                $stmt->execute([
                    $old['first_name'],
                    $old['last_name'],
                    $old['user_type'],
                    $regionId,
                    $old['is_active'],
                    $user['id'],
                ]);

                // اگر ادمین حساب خودش را ویرایش کرده، اطلاعات سشن را هم به‌روزرسانی کن
                if ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0)) {
                    $_SESSION['user_type'] = $old['user_type'];
                    $_SESSION['full_name'] = $old['first_name'] . ' ' . $old['last_name'];
                    $_SESSION['region_id'] = $regionId;
                }

                set_flash('success', 'کاربر «' . $old['first_name'] . ' ' . $old['last_name'] . '» با موفقیت ویرایش شد.');
                header('Location: ' . BASE_URL . '/users/list.php');
                exit;
            } catch (PDOException $e) {
                error_log('Update user error: ' . $e->getMessage());
                $errors[] = 'خطایی در ویرایش کاربر رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'ویرایش کاربر';
$active = 'users-list';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">ویرایش کاربر</h1>
  <p class="text-muted small mb-0">اطلاعات، نقش، منطقه و وضعیت فعال‌بودن کاربر را ویرایش کنید.</p>
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
  <div class="col-lg-4">
    <div class="card panel-card h-100">
      <div class="card-body text-center p-4">
        <span class="iconify fs-1 text-purple d-block mb-2" data-icon="solar:user-circle-bold-duotone"></span>
        <div class="fw-bold fs-5"><?= e($old['first_name'] . ' ' . $old['last_name']) ?></div>
        <div class="text-muted ltr-text small mb-2"><?= e($old['national_code']) ?></div>
        <?php if ((int)$old['is_active'] === 1): ?>
          <span class="badge role-badge role-driver">فعال</span>
        <?php else: ?>
          <span class="badge role-badge role-operator">غیرفعال</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card panel-card h-100">
      <div class="card-body p-4">
        <form method="post" action="<?= BASE_URL ?>/users/edit.php" id="editUserForm" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((string)$user['id']) ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">کد ملی (نام کاربری)</label>
              <div class="input-group">
                <span class="input-group-text"><span class="iconify" data-icon="solar:card-2-bold"></span></span>
                <input type="text" class="form-control ltr-text" value="<?= e($old['national_code']) ?>" disabled>
              </div>
              <div class="form-text">کد ملی پس از ایجاد کاربر قابل تغییر نیست.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="user_type">نقش کاربر</label>
              <div class="input-group">
                <span class="input-group-text"><span class="iconify" data-icon="solar:shield-user-bold"></span></span>
                <select class="form-select" id="user_type" name="user_type" required
                        <?= (int)$user['id'] === (int)($_SESSION['user_id'] ?? 0) ? 'disabled' : '' ?>>
                  <?php foreach (USER_TYPES as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $old['user_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php if ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0)): ?>
                <input type="hidden" name="user_type" value="<?= e($old['user_type']) ?>">
                <div class="form-text">نقش حساب خودتان قابل تغییر نیست.</div>
              <?php endif; ?>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="first_name">نام</label>
              <div class="input-group">
                <span class="input-group-text"><span class="iconify" data-icon="solar:user-bold"></span></span>
                <input type="text" class="form-control" id="first_name" name="first_name" required minlength="2"
                       value="<?= e($old['first_name']) ?>">
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="last_name">نام خانوادگی</label>
              <div class="input-group">
                <span class="input-group-text"><span class="iconify" data-icon="solar:user-bold"></span></span>
                <input type="text" class="form-control" id="last_name" name="last_name" required minlength="2"
                       value="<?= e($old['last_name']) ?>">
              </div>
            </div>

            <div class="col-md-6" id="regionFieldWrapper" style="display:none;">
              <label class="form-label" for="region_id">منطقه</label>
              <div class="input-group">
                <span class="input-group-text"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
                <select class="form-select" id="region_id" name="region_id">
                  <option value="">— انتخاب کنید —</option>
                  <?php foreach ($regions as $r): ?>
                    <option value="<?= e((string)$r['region_code']) ?>" <?= (string)$r['region_code'] === $old['region_id'] ? 'selected' : '' ?>>
                      <?= e($r['region_name']) ?> (<?= e((string)$r['region_code']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-text">فقط برای کاربر با نقش «منطقه» الزامی است.</div>
            </div>

            <div class="col-md-6 d-flex align-items-center">
              <div class="form-check form-switch mt-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                       <?= (int)$old['is_active'] === 1 ? 'checked' : '' ?>
                       <?= (int)$user['id'] === (int)($_SESSION['user_id'] ?? 0) ? 'disabled' : '' ?>>
                <label class="form-check-label" for="is_active">حساب کاربری فعال باشد</label>
              </div>
              <?php if ((int)$user['id'] === (int)($_SESSION['user_id'] ?? 0)): ?>
                <input type="hidden" name="is_active" value="1">
              <?php endif; ?>
            </div>
          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
              <span class="iconify" data-icon="solar:diskette-bold"></span> ذخیره تغییرات
            </button>
            <a href="<?= BASE_URL ?>/users/reset_password.php?id=<?= e((string)$user['id']) ?>" class="btn btn-soft-purple d-flex align-items-center gap-2">
              <span class="iconify" data-icon="solar:key-bold"></span> بازنشانی رمز
            </a>
            <a href="<?= BASE_URL ?>/users/list.php" class="btn btn-outline-secondary">انصراف</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
