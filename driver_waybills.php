<?php
/**
 * صفحه عمومی مشاهده بارنامه‌های راننده (بدون نیاز به سشن/ورود به پنل)
 *
 * دو روش استفاده:
 * ۱) با توکن: driver_waybills.php?token=...
 *    توکن از طریق وب‌سرویس ورود (api/login.php) گرفته می‌شود و حداکثر
 *    ۱۰ دقیقه اعتبار دارد. این روش رمز عبور را در URL قرار نمی‌دهد.
 * ۲) با فرم: اگر توکن ارسال نشود یا نامعتبر/منقضی باشد، فرم ساده کد ملی
 *    و رمز عبور نمایش داده می‌شود (برای استفاده مستقیم در مرورگر).
 *
 * در هر دو حالت فقط بارنامه‌های با وضعیت «ثبت شده» و «ارسال شده» نمایش داده می‌شود.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/jalali.php';
require_once __DIR__ . '/helpers/tokens.php';

$errors = [];
$driver = null;
$waybills = [];
$submittedUsername = '';
$viaToken = false;

// ---------- روش ۱: ورود با توکن (GET) ----------
$tokenParam = trim((string)($_GET['token'] ?? ''));
if ($tokenParam !== '') {
    $viaToken = true;
    try {
        $driver = validate_access_token($tokenParam, 'driver_waybills');
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
        error_log('Driver waybills token validate error: ' . $e->getMessage());
        $errors[] = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
    }
}

// ---------- روش ۲: ورود مستقیم با فرم (POST) ----------
if (!$viaToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
                unset($user['password']);
                $driver = $user;
            }
        } catch (PDOException $e) {
            error_log('Driver waybills lookup auth error: ' . $e->getMessage());
            $errors[] = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
        }
    }
}

// ---------- دریافت بارنامه‌ها در صورت احراز هویت موفق (هر دو روش) ----------
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
             WHERE w.driver_user_id = ? AND w.send_status IN ('ثبت شده', 'ارسال شده')
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
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>مشاهده بارنامه‌های راننده | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
</head>
<body class="public-page-body">

<div class="public-page-wrap">

  <div class="text-center mb-4">
    <span class="iconify fs-1 text-jade" data-icon="solar:bus-bold-duotone"></span>
    <h1 class="h4 fw-bold mt-2 mb-1">مشاهده بارنامه‌های راننده</h1>
    <p class="text-muted small mb-0">
      <?php if ($viaToken && $driver): ?>
        ورود با توکن موقت انجام شد.
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
          تعداد بارنامه در جریان: <span class="fw-bold"><?= e((string)count($waybills)) ?></span>
        </div>
      </div>
    </div>

    <?php if ($waybills): ?>
    <div class="waybill-card-list">
      <?php foreach ($waybills as $w): ?>
      <div class="waybill-card">
          <div class="waybill-card-head">
            <span class="waybill-card-number"><?= e($w['waybill_number']) ?></span>
            <span class="status-badge <?= e($statusClassMap[$w['send_status']] ?? '') ?>"><?= e($w['send_status']) ?></span>
          </div>

          <div class="waybill-card-route">
            <span class="waybill-card-route-point">
              <span class="iconify" data-icon="solar:point-on-map-bold"></span>
              <span class="waybill-card-route-text"><?= e($w['origin_title']) ?></span>
            </span>
            <span class="iconify waybill-card-route-arrow" data-icon="solar:arrow-left-bold"></span>
            <span class="waybill-card-route-point">
              <span class="iconify" data-icon="solar:flag-bold"></span>
              <span class="waybill-card-route-text"><?= e($w['destination_title']) ?></span>
            </span>
          </div>

          <div class="waybill-card-meta">
            <div class="waybill-card-meta-item">
              <span class="iconify" data-icon="solar:fuel-bold"></span>
              <span class="waybill-card-meta-label">فرآورده</span>
              <span class="product-badge <?= e(product_badge_class($w['product_type'])) ?>"><?= e($w['product_type']) ?></span>
            </div>
            <div class="waybill-card-meta-item">
              <span class="iconify" data-icon="solar:ruler-bold"></span>
              <span class="waybill-card-meta-label">مسافت</span>
              <span class="waybill-card-meta-value ltr-text"><?= e(number_format((float)$w['distance_km'], 0)) ?> کیلومتر</span>
            </div>
            <div class="waybill-card-meta-item">
              <span class="iconify" data-icon="solar:calendar-bold"></span>
              <span class="waybill-card-meta-label">تاریخ صدور</span>
              <span class="waybill-card-meta-value ltr-text"><?= e(to_jalali_display($w['issue_date'])) ?></span>
            </div>
            <div class="waybill-card-meta-item">
              <span class="iconify" data-icon="solar:shield-keyhole-bold"></span>
              <span class="waybill-card-meta-label">پلمپ</span>
              <span class="waybill-card-meta-value ltr-text"><?= $w['attached_seal_id'] ? e($w['attached_seal_id']) : '—' ?></span>
            </div>
          </div>

          <?php if ($w['origin_operator_first'] || $w['dest_operator_first']): ?>
          <div class="waybill-card-footer">
            <?php if ($w['origin_operator_first']): ?>
              <span class="text-muted small d-flex align-items-center gap-1">
                <span class="iconify" data-icon="solar:user-id-bold"></span>
                متصدی مبدا: <?= e($w['origin_operator_first'] . ' ' . $w['origin_operator_last']) ?>
              </span>
            <?php endif; ?>
            <?php if ($w['dest_operator_first']): ?>
              <span class="text-muted small d-flex align-items-center gap-1">
                <span class="iconify" data-icon="solar:user-id-bold"></span>
                متصدی مقصد: <?= e($w['dest_operator_first'] . ' ' . $w['dest_operator_last']) ?>
              </span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
        <div class="text-center text-muted p-5">
          <span class="iconify fs-1 d-block mb-2" data-icon="solar:fuel-line-duotone"></span>
          در حال حاضر هیچ بارنامه‌ای با وضعیت «ثبت شده» یا «ارسال شده» برای شما ثبت نشده است.
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="text-center text-muted small mt-4">
    <span class="iconify" data-icon="solar:info-circle-bold"></span>
    این صفحه عمومی است و نیازی به ورود به پنل ندارد.
    <?php if ($viaToken): ?>
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
