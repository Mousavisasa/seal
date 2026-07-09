<?php
/**
 * بازنشانی رمز عبور کاربر توسط ادمین (تعیین رمز جدید به‌صورت دستی)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$user = null;
$errors = [];

try {
    $stmt = db()->prepare('SELECT id, national_code, first_name, last_name, user_type FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Reset password fetch error: ' . $e->getMessage());
}

if (!$user) {
    set_flash('danger', 'کاربر مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/users/list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $password         = (string)($_POST['password'] ?? '');
        $password_confirm = (string)($_POST['password_confirm'] ?? '');

        if (strlen($password) < 6) {
            $errors[] = 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.';
        }
        if ($password !== $password_confirm) {
            $errors[] = 'رمز عبور و تکرار آن یکسان نیستند.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                set_flash('success', 'رمز عبور کاربر «' . $user['first_name'] . ' ' . $user['last_name'] . '» با موفقیت بازنشانی شد.');
                header('Location: ' . BASE_URL . '/users/list.php');
                exit;
            } catch (PDOException $e) {
                error_log('Reset password update error: ' . $e->getMessage());
                $errors[] = 'خطایی در بازنشانی رمز رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'بازنشانی رمز عبور';
$active = 'list';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">بازنشانی رمز عبور</h1>
  <p class="text-muted small mb-0">تعیین رمز عبور جدید برای کاربر انتخاب‌شده</p>
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
        <div class="fw-bold fs-5"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
        <div class="text-muted ltr-text small mb-2"><?= e($user['national_code']) ?></div>
        <span class="badge role-badge role-<?= e($user['user_type']) ?>"><?= e(user_type_label($user['user_type'])) ?></span>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card panel-card h-100">
      <div class="card-body p-4">
        <form method="post" action="<?= BASE_URL ?>/users/reset_password.php" id="resetPasswordForm" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((string)$user['id']) ?>">

          <label class="form-label" for="password">رمز عبور جدید</label>
          <div class="input-group mb-3">
            <span class="input-group-text"><span class="iconify" data-icon="solar:lock-password-bold"></span></span>
            <input type="password" class="form-control" id="password" name="password" required minlength="6" placeholder="حداقل ۶ کاراکتر">
            <button type="button" class="input-group-text toggle-pass" data-target="password" aria-label="نمایش رمز">
              <span class="iconify" data-icon="solar:eye-bold"></span>
            </button>
          </div>

          <label class="form-label" for="password_confirm">تکرار رمز عبور جدید</label>
          <div class="input-group mb-2">
            <span class="input-group-text"><span class="iconify" data-icon="solar:lock-password-bold"></span></span>
            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="6" placeholder="تکرار رمز عبور">
          </div>
          <div class="invalid-feedback d-block small mb-3" data-error-for="password_confirm"></div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
              <span class="iconify" data-icon="solar:key-bold"></span> بازنشانی رمز
            </button>
            <a href="<?= BASE_URL ?>/users/list.php" class="btn btn-outline-secondary">بازگشت</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
