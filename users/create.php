<?php
/**
 * ایجاد کاربر جدید توسط ادمین
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_admin();

$errors = [];
$old = ['national_code' => '', 'first_name' => '', 'last_name' => '', 'user_type' => 'driver'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $old['national_code'] = normalize_digits((string)($_POST['national_code'] ?? ''));
        $old['first_name']    = trim((string)($_POST['first_name'] ?? ''));
        $old['last_name']     = trim((string)($_POST['last_name'] ?? ''));
        $old['user_type']     = (string)($_POST['user_type'] ?? '');
        $password             = (string)($_POST['password'] ?? '');
        $password_confirm     = (string)($_POST['password_confirm'] ?? '');

        if (!is_valid_national_code($old['national_code'])) {
            $errors[] = 'کد ملی وارد شده معتبر نیست.';
        }
        if (mb_strlen($old['first_name']) < 2) {
            $errors[] = 'نام باید حداقل ۲ حرف باشد.';
        }
        if (mb_strlen($old['last_name']) < 2) {
            $errors[] = 'نام خانوادگی باید حداقل ۲ حرف باشد.';
        }
        if (!array_key_exists($old['user_type'], USER_TYPES)) {
            $errors[] = 'نقش انتخاب‌شده معتبر نیست.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        }
        if ($password !== $password_confirm) {
            $errors[] = 'رمز عبور و تکرار آن یکسان نیستند.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare('SELECT id FROM users WHERE national_code = ? LIMIT 1');
                $stmt->execute([$old['national_code']]);
                if ($stmt->fetch()) {
                    $errors[] = 'کاربری با این کد ملی قبلاً ثبت شده است.';
                } else {
                    $stmt = db()->prepare(
                        'INSERT INTO users (national_code, first_name, last_name, password, user_type) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $old['national_code'],
                        $old['first_name'],
                        $old['last_name'],
                        password_hash($password, PASSWORD_DEFAULT),
                        $old['user_type'],
                    ]);
                    set_flash('success', 'کاربر «' . $old['first_name'] . ' ' . $old['last_name'] . '» با موفقیت ایجاد شد.');
                    header('Location: ' . BASE_URL . '/users/list.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Create user error: ' . $e->getMessage());
                $errors[] = 'خطایی در ثبت کاربر رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'ایجاد کاربر';
$active = 'users-create';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">ایجاد کاربر جدید</h1>
  <p class="text-muted small mb-0">اطلاعات کاربر را کامل و دقیق وارد کنید.</p>
</div>

<?= render_flash() ?>

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
    <form method="post" action="<?= BASE_URL ?>/users/create.php" id="createUserForm" novalidate>
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="national_code">کد ملی (نام کاربری)</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:card-2-bold"></span></span>
            <input type="text" class="form-control" id="national_code" name="national_code"
                   inputmode="numeric" maxlength="10" required
                   data-validate="national-code"
                   value="<?= e($old['national_code']) ?>" placeholder="کد ملی ۱۰ رقمی">
          </div>
          <div class="invalid-feedback d-block small" data-error-for="national_code"></div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="user_type">نقش کاربر</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:shield-user-bold"></span></span>
            <select class="form-select" id="user_type" name="user_type" required>
              <?php foreach (USER_TYPES as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $old['user_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="first_name">نام</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:user-bold"></span></span>
            <input type="text" class="form-control" id="first_name" name="first_name" required minlength="2"
                   value="<?= e($old['first_name']) ?>" placeholder="مثلاً علی">
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="last_name">نام خانوادگی</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:user-bold"></span></span>
            <input type="text" class="form-control" id="last_name" name="last_name" required minlength="2"
                   value="<?= e($old['last_name']) ?>" placeholder="مثلاً رضایی">
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="password">رمز عبور</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:lock-password-bold"></span></span>
            <input type="password" class="form-control" id="password" name="password" required minlength="6" placeholder="حداقل ۶ کاراکتر">
            <button type="button" class="input-group-text toggle-pass" data-target="password" aria-label="نمایش رمز">
              <span class="iconify" data-icon="solar:eye-bold"></span>
            </button>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="password_confirm">تکرار رمز عبور</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:lock-password-bold"></span></span>
            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="6" placeholder="تکرار رمز عبور">
          </div>
          <div class="invalid-feedback d-block small" data-error-for="password_confirm"></div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <span class="iconify" data-icon="solar:add-circle-bold"></span> ثبت کاربر
        </button>
        <a href="<?= BASE_URL ?>/users/list.php" class="btn btn-outline-secondary">انصراف</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
