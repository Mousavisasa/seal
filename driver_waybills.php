<?php
/**
 * صفحه عمومی مشاهده و مدیریت بارنامه‌های راننده (بدون نیاز به سشن/ورود به پنل)
 *
 * دو روش ورود:
 * ۱) با توکن: driver_waybills.php?token=...
 *    توکن از طریق وب‌سرویس ورود (api/login.php) گرفته می‌شود و حداکثر ۱۰ دقیقه اعتبار دارد.
 * ۲) با فرم کد ملی/رمز عبور: بعد از احراز هویت موفق، یک توکن تازه ساخته می‌شود
 *    و کاربر به همان صفحه با آدرس ?token=... هدایت می‌شود (redirect)، تا از این به بعد
 *    همه چیز — از جمله دکمه‌های شروع/پایان سفر — روی همان توکن کار کند، نه رمز عبور.
 *
 * دقیقاً مانند waybills/my_trips.php:
 * - فقط بارنامه‌های «ثبت شده» و «ارسال شده» نمایش داده می‌شود (به‌علاوه بارنامه‌ای که
 *   با «پایان سفر» به «تحویل شده» تبدیل شده، تا کاربر نتیجه عملش را ببیند)
 * - دکمه «شروع سفر» فقط برای بارنامه با وضعیت «ثبت شده» نمایش داده می‌شود
 * - دکمه «پایان سفر» فقط برای بارنامه با وضعیت «ارسال شده» نمایش داده می‌شود
 * - هر عملیات، مالکیت بارنامه (driver_user_id) را دوباره از دیتابیس بررسی می‌کند
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/jalali.php';
require_once __DIR__ . '/helpers/tokens.php';

$errors = [];
$driver = null;
$waybills = [];
$submittedUsername = '';
$token = trim((string)($_GET['token'] ?? ''));

/** بازگرداندن راننده معتبر از توکن، یا null در صورت نامعتبر/غیرمجازبودن */
function resolve_driver_from_token(string $token): array
{
    $driver = validate_access_token($token, 'driver_waybills');
    if (!$driver) {
        return [null, 'توکن نامعتبر است یا منقضی شده است. لطفاً دوباره وارد شوید.'];
    }
    if ($driver['user_type'] !== 'driver') {
        return [null, 'این توکن متعلق به یک حساب راننده نیست.'];
    }
    if ((int)($driver['is_active'] ?? 1) === 0) {
        return [null, 'حساب کاربری شما غیرفعال شده است. برای اطلاعات بیشتر با مدیر سامانه تماس بگیرید.'];
    }
    return [$driver, null];
}

// ---------- عملیات شروع/پایان سفر (POST همراه با توکن) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['start_trip', 'end_trip'], true)) {
    $actionToken = trim((string)($_POST['token'] ?? ''));
    $waybillId = (int)($_POST['id'] ?? 0);
    $action = (string)$_POST['action'];
    $actingDriver = null;

    try {
        [$actingDriver, $tokenError] = resolve_driver_from_token($actionToken);
        if (!$actingDriver) {
            $errors[] = $tokenError;
        } elseif ($waybillId <= 0) {
            $errors[] = 'درخواست نامعتبر است.';
        } else {
            $stmt = db()->prepare('SELECT * FROM fuel_waybills WHERE id = ? LIMIT 1');
            $stmt->execute([$waybillId]);
            $target = $stmt->fetch();

            if (!$target) {
                $errors[] = 'بارنامه مورد نظر یافت نشد.';
            } elseif ((int)$target['driver_user_id'] !== (int)$actingDriver['id']) {
                $errors[] = 'این بارنامه به شما تخصیص داده نشده است.';
            } elseif ($action === 'start_trip') {
                if ($target['send_status'] !== 'ثبت شده') {
                    $errors[] = 'این بارنامه قبلاً شروع شده یا در وضعیت دیگری قرار دارد.';
                } else {
                    $upd = db()->prepare("UPDATE fuel_waybills SET send_status = 'ارسال شده', trip_started_at = NOW() WHERE id = ?");
                    $upd->execute([$waybillId]);
                }
            } elseif ($action === 'end_trip') {
                if ($target['send_status'] !== 'ارسال شده') {
                    $errors[] = 'این بارنامه هنوز شروع نشده یا قبلاً تحویل داده شده است.';
                } else {
                    $upd = db()->prepare("UPDATE fuel_waybills SET send_status = 'تحویل شده', trip_ended_at = NOW() WHERE id = ?");
                    $upd->execute([$waybillId]);
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Driver waybills trip action error: ' . $e->getMessage());
        $errors[] = 'خطایی در ثبت عملیات رخ داد. لطفاً دوباره تلاش کنید.';
    }

    // بازگشت به همان صفحه با همان توکن (Post/Redirect/Get) تا رفرش صفحه دوباره فرم ارسال نکند
    if (!$errors) {
        header('Location: ' . BASE_URL . '/driver_waybills.php?token=' . rawurlencode($actionToken));
        exit;
    }
    // در صورت خطا، به‌جای اعتبارسنجی دوباره توکن، همان نتیجه را مستقیم استفاده می‌کنیم
    $token = $actionToken;
    if ($actingDriver) {
        $driver = $actingDriver;
    }
}

// ---------- روش ۱: ورود با توکن (GET یا نتیجه اقدام بالا) ----------
if (!$driver && $token !== '') {
    try {
        [$driver, $tokenError] = resolve_driver_from_token($token);
        if (!$driver && $tokenError && !in_array($tokenError, $errors, true)) {
            $errors[] = $tokenError;
        }
    } catch (PDOException $e) {
        error_log('Driver waybills token validate error: ' . $e->getMessage());
        $errors[] = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
    }
}

// ---------- روش ۲: ورود مستقیم با فرم کد ملی/رمز عبور (POST) ----------
if (!$driver && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === '') {
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
                // تا رمز عبور دیگر در هیچ درخواست بعدی (از جمله شروع/پایان سفر) لازم نباشد
                $newToken = create_access_token((int)$user['id'], 'driver_waybills');
                header('Location: ' . BASE_URL . '/driver_waybills.php?token=' . rawurlencode($newToken));
                exit;
            }
        } catch (PDOException $e) {
            error_log('Driver waybills lookup auth error: ' . $e->getMessage());
            $errors[] = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
        }
    }
}

// ---------- دریافت بارنامه‌ها در صورت احراز هویت موفق ----------
if ($driver) {
    try {
        $stmt = db()->prepare(
            "SELECT w.*, ol.title AS origin_title, dl.title AS destination_title,
                    opOrig.first_name AS origin_operator_first, opOrig.last_name AS origin_operator_last,
                    opDest.first_name AS dest_operator_first, opDest.last_name AS dest_operator_last,
                    sl.seal_id AS attached_seal_id
             FROM fuel_waybills w
             INNER JOIN locations ol ON ol.id = w.origin_location_id
             INNER JOIN locations dl ON dl.id = w.destination_location_id
             LEFT JOIN users opOrig ON opOrig.id = w.origin_operator_user_id
             LEFT JOIN users opDest ON opDest.id = w.destination_operator_user_id
             LEFT JOIN seals sl ON sl.fuel_waybill_id = w.id
             WHERE w.driver_user_id = ? AND w.send_status IN ('ثبت شده', 'ارسال شده', 'تحویل شده')
             ORDER BY w.id DESC"
        );
        $stmt->execute([$driver['id']]);
        $waybills = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Driver waybills lookup fetch error: ' . $e->getMessage());
        $errors[] = 'خطایی در دریافت فهرست بارنامه‌ها رخ داد.';
    }
}

$statusClassMap = [
    'ثبت شده'   => 'status-registered',
    'ارسال شده' => 'status-sent',
    'تحویل شده' => 'status-delivered',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>بارنامه‌های راننده | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
</head>
<body class="public-page-body">

<div class="public-page-wrap">

  <div class="text-center mb-4">
    <span class="iconify fs-1 text-jade" data-icon="solar:bus-bold-duotone"></span>
    <h1 class="h4 fw-bold mt-2 mb-1">بارنامه‌های راننده</h1>
    <p class="text-muted small mb-0">
      <?php if ($driver): ?>
        سفرهای در جریان خود را مشاهده و مدیریت کنید.
      <?php else: ?>
        با کد ملی و رمز عبور خود وارد شوید تا بارنامه‌های در جریان خود را ببینید.
      <?php endif; ?>
    </p>
  </div>

  <?php if (!$driver): ?>
  <div class="card border-0 shadow-sm mb-4" style="border-radius: 1rem;">
    <div class="card-body p-4">
      <form method="post" action="<?= BASE_URL ?>/driver_waybills.php" novalidate>
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
              <span class="iconify fs-5" data-icon="solar:magnifer-bold"></span> مشاهده بارنامه‌ها
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
          تعداد بارنامه: <span class="fw-bold"><?= e((string)count($waybills)) ?></span>
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
                                  <span class="iconify" data-icon="solar:fuel-bold"></span> <?= e($w['product_type']) ?>
                                  <span class="mx-1">•</span>
                                  <span class="iconify" data-icon="solar:ruler-bold"></span> <?= e(number_format((float)$w['distance_km'], 2)) ?> کیلومتر
                              </div>

                              <div class="small text-muted d-flex align-items-center gap-1">
                                  <span class="iconify" data-icon="solar:calendar-bold"></span> تاریخ صدور: <?= e(to_jalali_display($w['issue_date'])) ?>
                              </div>

                              <?php if ($w['origin_operator_first']): ?>
                                  <div class="small text-muted d-flex align-items-center gap-1">
                                      <span class="iconify" data-icon="solar:user-id-bold"></span> متصدی مبدا: <?= e($w['origin_operator_first'] . ' ' . $w['origin_operator_last']) ?>
                                  </div>
                              <?php endif; ?>

                              <?php if ($w['dest_operator_first']): ?>
                                  <div class="small text-muted d-flex align-items-center gap-1">
                                      <span class="iconify" data-icon="solar:user-id-bold"></span> متصدی مقصد: <?= e($w['dest_operator_first'] . ' ' . $w['dest_operator_last']) ?>
                                  </div>
                              <?php endif; ?>

                              <div class="mt-auto pt-2 d-flex gap-2">
                                  <?php if ($w['send_status'] === 'ثبت شده'): ?>
                                      <form method="post" action="<?= BASE_URL ?>/driver_waybills.php" class="flex-fill">
                                          <input type="hidden" name="token" value="<?= e($token) ?>">
                                          <input type="hidden" name="id" value="<?= e((string)$w['id']) ?>">
                                          <input type="hidden" name="action" value="start_trip">
                                          <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                              <span class="iconify" data-icon="solar:play-circle-bold"></span> شروع سفر
                                          </button>
                                      </form>
                                  <?php elseif ($w['send_status'] === 'ارسال شده'): ?>
                                      <form method="post" action="<?= BASE_URL ?>/driver_waybills.php" class="flex-fill">
                                          <input type="hidden" name="token" value="<?= e($token) ?>">
                                          <input type="hidden" name="id" value="<?= e((string)$w['id']) ?>">
                                          <input type="hidden" name="action" value="end_trip">
                                          <button type="submit" class="btn btn-soft-purple w-100 d-flex align-items-center justify-content-center gap-2">
                                              <span class="iconify" data-icon="solar:flag-bold"></span> پایان سفر
                                          </button>
                                      </form>
                                  <?php elseif ($w['send_status'] === 'تحویل شده'): ?>
                                      <div class="text-center w-100 text-muted small py-2">
                                          <span class="iconify" data-icon="solar:check-circle-bold"></span> این سفر با موفقیت به پایان رسیده است.
                                      </div>
                                  <?php else: ?>
                                      <div class="text-center w-100 text-muted small py-2">این بارنامه لغو شده است.</div>
                                  <?php endif; ?>
                              </div>
                          </div>
                      </div>
                  </div>
              <?php endforeach; ?>
          </div>
      <?php else: ?>
          <div class="card panel-card">
              <div class="card-body text-center text-muted p-5">
                  <span class="iconify fs-1 d-block mb-2" data-icon="solar:bus-line-duotone"></span>
                  در حال حاضر هیچ بارنامه‌ای به شما تخصیص داده نشده است.
              </div>
          </div>
      <?php endif; ?>

  <?php endif; ?>

  <div class="text-center text-muted small mt-4">
    <span class="iconify" data-icon="solar:info-circle-bold"></span>
    این صفحه عمومی است و نیازی به ورود به پنل ندارد.
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
