<?php
/**
 * صفحه نمایش فهرست بارنامه‌های «ثبت شده» راننده — از طریق وب‌سرویس api/waybills.php
 * (بدون نیاز به سشن/ورود به پنل)
 *
 * دو روش ورود:
 * ۱) با توکن: api_waybills_view.php?token=...
 *    توکن از طریق وب‌سرویس ورود (api/login.php) گرفته می‌شود و حداکثر ۱۰ دقیقه اعتبار دارد.
 * ۲) با فرم کد ملی/رمز عبور: بعد از احراز هویت موفق، یک توکن تازه ساخته می‌شود
 *    و کاربر به همان صفحه با آدرس ?token=... هدایت می‌شود.
 *
 * این صفحه صرفاً یک نمایش‌دهنده (viewer) برای وب‌سرویس api/waybills.php است:
 * خودش مستقیم به دیتابیس وصل نمی‌شود، بلکه همان وب‌سرویس را فراخوانی و نتیجه JSON آن را
 * به‌صورت کارت نمایش می‌دهد. فقط بارنامه‌های با وضعیت «ثبت شده» نمایش داده می‌شوند
 * (چون خودِ وب‌سرویس همین را برمی‌گرداند) و هیچ عملیات شروع/پایان سفر در این صفحه نیست.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/tokens.php';

$errors = [];
$driver = null;
$waybills = [];
$submittedUsername = '';
$token = trim((string)($_GET['token'] ?? ''));

// ---------- روش ۱: ورود با توکن (GET) ----------
if ($token !== '') {
    try {
        $driver = validate_access_token($token, 'driver_waybills');
        if (!$driver) {
            $errors[] = 'توکن نامعتبر است یا منقضی شده است. لطفاً دوباره وارد شوید.';
        } elseif ($driver['user_type'] !== 'driver') {
            $driver = null;
            $errors[] = 'این توکن متعلق به یک حساب راننده نیست.';
        } elseif ((int)($driver['is_active'] ?? 1) === 0) {
            $driver = null;
            $errors[] = 'حساب کاربری شما غیرفعال شده است. برای اطلاعات بیشتر با مدیر سامانه تماس بگیرید.';
        }
    } catch (PDOException $e) {
        error_log('API waybills view token validate error: ' . $e->getMessage());
        $errors[] = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
    }
}

// ---------- روش ۲: ورود مستقیم با فرم کد ملی/رمز عبور (POST) ----------
if (!$driver && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedUsername = normalize_digits((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($submittedUsername === '' || $password === '') {
        $errors[] = 'نام کاربری (کد ملی) و رمز عبور را وارد کنید.';
    } else {
        try {
            $stmt = db()->prepare("SELECT * FROM users WHERE national_code = ? AND user_type = 'driver' LIMIT 1");
            $stmt->execute([$submittedUsername]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'نام کاربری یا رمز عبور نادرست است.';
            } elseif ((int)($user['is_active'] ?? 1) === 0) {
                $errors[] = 'حساب کاربری شما غیرفعال شده است. برای اطلاعات بیشتر با مدیر سامانه تماس بگیرید.';
            } else {
                // ورود موفق: یک توکن تازه می‌سازیم و به همان آدرس با توکن هدایت می‌کنیم
                $newToken = create_access_token((int)$user['id'], 'driver_waybills');
                header('Location: ' . BASE_URL . '/api_waybills_view.php?token=' . rawurlencode($newToken));
                exit;
            }
        } catch (PDOException $e) {
            error_log('API waybills view auth error: ' . $e->getMessage());
            $errors[] = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
        }
    }
}

// ---------- فراخوانی خودِ وب‌سرویس api/waybills.php با همان توکن ----------
$apiStatus = null;
if ($driver && $token !== '') {
    $apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/api/waybills.php?token=' . rawurlencode($token);

    $response = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        if ($response !== false) {
            $apiStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);
    }

    if ($response === false && ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
        $response = @file_get_contents($apiUrl, false, $context);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $apiStatus = (int)$m[1];
        }
    }

    $decoded = $response !== false ? json_decode((string)$response, true) : null;

    if (!is_array($decoded)) {
        $errors[] = 'ارتباط با وب‌سرویس بارنامه‌ها برقرار نشد.';
    } elseif (empty($decoded['success'])) {
        $errors[] = $decoded['message'] ?? 'خطایی از سمت وب‌سرویس دریافت شد.';
        $driver = null; // اگر خودِ وب‌سرویس توکن را رد کرد، دیگر کاربر را واردشده در نظر نگیریم
    } else {
        $waybills = $decoded['waybills'] ?? [];
    }
}

$statusClassMap = [
    'ثبت شده' => 'status-registered',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>فهرست بارنامه‌های ثبت‌شده (وب‌سرویس) | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
</head>
<body class="public-page-body">

<div class="public-page-wrap">

  <div class="text-center mb-4">
    <span class="iconify fs-1 text-jade" data-icon="solar:code-square-bold"></span>
    <h1 class="h4 fw-bold mt-2 mb-1">فهرست بارنامه‌های ثبت‌شده</h1>
    <p class="text-muted small mb-0">
      <?php if ($driver): ?>
        نتیجه فراخوانی وب‌سرویس <code class="ltr-text">api/waybills.php</code>
        <?php if ($apiStatus !== null): ?>
          — کد پاسخ: <span class="badge role-badge role-driver ltr-text">HTTP <?= e((string)$apiStatus) ?></span>
        <?php endif; ?>
      <?php else: ?>
        با کد ملی و رمز عبور خود وارد شوید تا فهرست بارنامه‌های «ثبت شده» را از طریق وب‌سرویس ببینید.
      <?php endif; ?>
    </p>
  </div>

  <?php if (!$driver): ?>
  <div class="card border-0 shadow-sm mb-4" style="border-radius: 1rem;">
    <div class="card-body p-4">
      <form method="post" action="<?= BASE_URL ?>/api_waybills_view.php" novalidate>
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label" for="username">کد ملی (نام کاربری)</label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:card-2-bold"></span></span>
              <input type="text" class="form-control ltr-text" id="username" name="username"
                     inputmode="numeric" maxlength="10" required
                     value="<?= e($submittedUsername) ?>" placeholder="مثلاً 3333333333">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="password">رمز عبور</label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:lock-password-bold"></span></span>
              <input type="password" class="form-control" id="password" name="password" required placeholder="********">
              <button type="button" class="input-group-text toggle-pass" data-target="password" aria-label="نمایش رمز">
                <span class="iconify" data-icon="solar:eye-bold"></span>
              </button>
            </div>
          </div>
          <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
              <span class="iconify fs-5" data-icon="solar:magnifer-bold"></span> مشاهده فهرست
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <span class="iconify fs-5" data-icon="solar:danger-triangle-bold"></span>
      <div>
        <?php foreach ($errors as $err): ?>
          <div><?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($driver): ?>
    <div class="card border-0 shadow-sm mb-3" style="border-radius: 1rem;">
      <div class="card-body p-4 d-flex align-items-center gap-3">
        <span class="iconify fs-1 text-purple" data-icon="solar:user-circle-bold-duotone"></span>
        <div>
          <div class="fw-bold fs-5"><?= e($driver['first_name'] . ' ' . $driver['last_name']) ?></div>
          <div class="text-muted small ltr-text"><?= e($driver['national_code']) ?></div>
        </div>
        <div class="ms-auto text-muted small">
          تعداد بارنامه «ثبت شده»: <span class="fw-bold"><?= e((string)count($waybills)) ?></span>
        </div>
      </div>
    </div>

    <?php if ($waybills): ?>
    <div class="row g-3">
      <?php foreach ($waybills as $w): ?>
      <div class="col-md-6 col-xl-4">
        <div class="card panel-card h-100">
          <div class="card-body d-flex flex-column gap-2">
            <div class="d-flex justify-content-between align-items-start">
              <div class="fw-bold ltr-text"><?= e($w['waybill_number']) ?></div>
              <span class="status-badge <?= e($statusClassMap[$w['send_status']] ?? '') ?>"><?= e($w['send_status']) ?></span>
            </div>

            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:point-on-map-bold"></span>
              <?= e($w['origin_title']) ?> <span class="iconify" data-icon="solar:arrow-left-bold"></span> <?= e($w['destination_title']) ?>
            </div>

            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:fuel-bold"></span>
              <span class="product-badge <?= e(product_badge_class($w['product_type'])) ?>"><?= e($w['product_type']) ?></span>
              <span class="mx-1">•</span>
              <span class="iconify" data-icon="solar:ruler-bold"></span> <?= e(number_format((float)$w['distance_km'], 0)) ?> کیلومتر
            </div>

            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:calendar-bold"></span> تاریخ صدور: <?= e($w['issue_date_jalali'] ?? $w['issue_date']) ?>
            </div>

            <?php if (!empty($w['origin_operator'])): ?>
            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:user-id-bold"></span> متصدی مبدا: <?= e($w['origin_operator']) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($w['destination_operator'])): ?>
            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:user-id-bold"></span> متصدی مقصد: <?= e($w['destination_operator']) ?>
            </div>
            <?php endif; ?>

            <div class="small text-muted d-flex align-items-center gap-1">
              <span class="iconify" data-icon="solar:shield-keyhole-bold"></span> پلمپ:
              <?= !empty($w['seal_id']) ? e($w['seal_id']) : '<span class="text-muted">—</span>' ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="card panel-card">
        <div class="card-body text-center text-muted p-5">
          <span class="iconify fs-1 d-block mb-2" data-icon="solar:fuel-line-duotone"></span>
          در حال حاضر هیچ بارنامه «ثبت شده»‌ای برای شما وجود ندارد.
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="text-center text-muted small mt-4">
    <span class="iconify" data-icon="solar:info-circle-bold"></span>
    این صفحه صرفاً نمایش‌دهنده خروجی وب‌سرویس <code class="ltr-text">api/waybills.php</code> است و نیازی به ورود به پنل ندارد.
    <?php if ($driver): ?>
      توکن استفاده‌شده حداکثر <?= e((string)ACCESS_TOKEN_TTL_MINUTES) ?> دقیقه از زمان صدور معتبر است.
    <?php endif; ?>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.toggle-pass').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.getAttribute('data-target'));
    if (!input) return;
    var isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    var icon = btn.querySelector('.iconify');
    if (icon) icon.setAttribute('data-icon', isPass ? 'solar:eye-closed-bold' : 'solar:eye-bold');
  });
});
</script>
</body>
</html>
