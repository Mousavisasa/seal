<?php
/**
 * صفحه ورود به پنل (همه نقش‌ها: ادمین، منطقه، متصدی، راننده)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.';
    } else {
        $username = normalize_digits((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'نام کاربری و رمز عبور را وارد کنید.';
        } else {
            try {
                $stmt = db()->prepare('SELECT * FROM users WHERE national_code = ? LIMIT 1');
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    login_user($user);
                    set_flash('success', 'خوش آمدید، ' . $user['first_name'] . ' ' . $user['last_name'] . ' عزیز.');
                    header('Location: ' . BASE_URL . '/' . redirect_path_for_role($user['user_type']));
                    exit;
                }
                $error = 'نام کاربری یا رمز عبور نادرست است.';
            } catch (PDOException $e) {
                error_log('Login error: ' . $e->getMessage());
                $error = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
            }
        }
    }
}
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود به پنل | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
</head>
<body class="auth-body">

<div class="auth-card card border-0">
  <div class="auth-side d-none d-md-flex">
    <span class="iconify auth-side-icon" data-icon="solar:shield-user-bold-duotone"></span>
    <h2 class="fw-bold text-white mt-3"><?= e(APP_NAME) ?></h2>
    <p class="text-white-50 mb-0">مدیریت راننده‌ها، متصدی‌ها و مناطق در یک پنل ساده و امن</p>
  </div>
  <div class="auth-form p-4 p-md-5">
    <h1 class="h4 fw-bold mb-1">ورود به پنل مدیریت</h1>
    <p class="text-muted small mb-4">برای ادامه، کد ملی و رمز عبور خود را وارد کنید.</p>

    <?php if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> d-flex align-items-center gap-2">
        <span class="iconify" data-icon="solar:info-circle-bold"></span><?= e($flash['message']) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <span class="iconify" data-icon="solar:close-circle-bold"></span><?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/login.php" id="loginForm" novalidate>
      <?= csrf_field() ?>

      <label class="form-label" for="username">نام کاربری (کد ملی)</label>
      <div class="input-group mb-3">
        <span class="input-group-text"><span class="iconify" data-icon="solar:card-2-bold"></span></span>
        <input type="text" class="form-control" id="username" name="username"
               inputmode="numeric" maxlength="10" placeholder="مثلاً 1111111111" required
               value="<?= e($_POST['username'] ?? '') ?>">
      </div>

      <label class="form-label" for="password">رمز عبور</label>
      <div class="input-group mb-4">
        <span class="input-group-text"><span class="iconify" data-icon="solar:lock-password-bold"></span></span>
        <input type="password" class="form-control" id="password" name="password" required placeholder="********">
        <button type="button" class="input-group-text toggle-pass" data-target="password" aria-label="نمایش رمز">
          <span class="iconify" data-icon="solar:eye-bold"></span>
        </button>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2">
        <span class="iconify fs-5" data-icon="solar:login-3-bold"></span> ورود به پنل
      </button>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
