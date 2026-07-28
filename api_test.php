<?php
/**
 * صفحه تست وب‌سرویس‌ها: تست زنده + مستندات کامل استفاده
 * شامل سه زیرصفحه که از بالای صفحه قابل انتخاب هستند:
 *  ۱) وب‌سرویس ورود (api/login.php)
 *  ۲) وب‌سرویس شروع/پایان سفر (api/trip_action.php)
 *  ۳) وب‌سرویس بارنامه ی انتخاب شده (api/active_waybill.php)
 *  ۴) وب‌سرویس پلمپ بر اساس شماره بارنامه (api/seal_by_waybill.php)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/tokens.php';

require_admin();

// بارنامه‌های واقعی و راننده‌شان برای تست زنده وب‌سرویس شروع/پایان سفر
$startableWaybills = [];
$endableWaybills   = [];
try {
    $rows = db()->query(
        "SELECT w.id, w.waybill_number, w.send_status, w.driver_user_id, u.first_name, u.last_name, u.national_code
         FROM fuel_waybills w
         INNER JOIN users u ON u.id = w.driver_user_id
         WHERE w.send_status IN ('ثبت شده', 'ارسال شده')
         ORDER BY w.id DESC"
    )->fetchAll();
    foreach ($rows as $row) {
        if ($row['send_status'] === 'ثبت شده') {
            $startableWaybills[] = $row;
        } else {
            $endableWaybills[] = $row;
        }
    }
} catch (PDOException $e) {
    error_log('api_test waybills fetch error: ' . $e->getMessage());
}

// رانندگانی که همین الان یک بارنامه ی انتخاب شده دارند (تریگر داخلی
// users.selected_waybill_id که با کلیک روی «شروع سفر» ست می‌شود) — برای تست زنده
// وب‌سرویس بارنامه ی انتخاب شده؛ مستقل از send_status بارنامه
$activeDrivers = [];
try {
    $activeDrivers = db()->query(
        "SELECT u.id AS driver_user_id, u.first_name, u.last_name, u.national_code,
                w.waybill_number, w.send_status
         FROM users u
         INNER JOIN fuel_waybills w ON w.id = u.selected_waybill_id
         WHERE u.user_type = 'driver'
         ORDER BY u.id DESC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('api_test active drivers fetch error: ' . $e->getMessage());
}

// بارنامه‌هایی که پلمپ به آن‌ها متصل شده — برای تست زنده وب‌سرویس دریافت پلمپ بر اساس شماره بارنامه
$waybillsWithSeal = [];
try {
    $waybillsWithSeal = db()->query(
        "SELECT w.waybill_number, s.seal_id
         FROM fuel_waybills w
         INNER JOIN seals s ON s.fuel_waybill_id = w.id
         ORDER BY w.id DESC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('api_test waybills with seal fetch error: ' . $e->getMessage());
}

// رانندگان فعال — برای ساخت توکن آزمایشی در تست وب‌سرویس دریافت پلمپ بر اساس شماره بارنامه
// (این وب‌سرویس هر توکن معتبر راننده را می‌پذیرد و مالکیت بارنامه را بررسی نمی‌کند)
$allActiveDrivers = [];
try {
    $allActiveDrivers = db()->query(
        "SELECT id, first_name, last_name, national_code
         FROM users
         WHERE user_type = 'driver' AND is_active = 1
         ORDER BY id DESC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('api_test active drivers list fetch error: ' . $e->getMessage());
}

// متصدیان فعال — برای ساخت توکن آزمایشی در تست وب‌سرویس دریافت پلمپ بر اساس شماره بارنامه
// (این وب‌سرویس توکن متصدی را هم می‌پذیرد، به‌شرطی که بارنامه به همان متصدی تخصیص یافته باشد)
$allActiveOperators = [];
try {
    $allActiveOperators = db()->query(
        "SELECT id, first_name, last_name, national_code
         FROM users
         WHERE user_type = 'operator' AND is_active = 1
         ORDER BY id DESC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('api_test active operators list fetch error: ' . $e->getMessage());
}

// بارنامه‌هایی که یک متصدی (مبدا یا مقصد) دارند و هنوز حضورش تایید نشده — برای
// تست زنده وب‌سرویس تایید حضور متصدی (معادل شروع/پایان سفر راننده)
$operatorPendingConfirm = [];
try {
    ensure_operator_confirm_columns();
    $rows = db()->query(
        "SELECT w.id, w.waybill_number, w.origin_operator_user_id, w.destination_operator_user_id,
                w.origin_confirmed_at, w.destination_confirmed_at,
                opOrig.first_name AS origin_first, opOrig.last_name AS origin_last, opOrig.national_code AS origin_nc,
                opDest.first_name AS dest_first, opDest.last_name AS dest_last, opDest.national_code AS dest_nc
         FROM fuel_waybills w
         LEFT JOIN users opOrig ON opOrig.id = w.origin_operator_user_id
         LEFT JOIN users opDest ON opDest.id = w.destination_operator_user_id
         WHERE (w.origin_operator_user_id IS NOT NULL AND w.origin_confirmed_at IS NULL)
            OR (w.destination_operator_user_id IS NOT NULL AND w.destination_confirmed_at IS NULL)
         ORDER BY w.id DESC"
    )->fetchAll();
    foreach ($rows as $row) {
        if ($row['origin_operator_user_id'] && !$row['origin_confirmed_at']) {
            $operatorPendingConfirm[] = ['role' => 'origin', 'row' => $row];
        }
        if ($row['destination_operator_user_id'] && !$row['destination_confirmed_at']) {
            $operatorPendingConfirm[] = ['role' => 'destination', 'row' => $row];
        }
    }
} catch (PDOException $e) {
    error_log('api_test operator pending confirm fetch error: ' . $e->getMessage());
}

// متصدی‌هایی که حداقل یک بارنامه به آن‌ها تخصیص یافته — برای تست زنده وب‌سرویس بارنامه‌های ایستگاه متصدی
$operatorsWithWaybills = [];
try {
    $operatorsWithWaybills = db()->query(
        "SELECT DISTINCT u.id, u.first_name, u.last_name, u.national_code
         FROM users u
         WHERE u.user_type = 'operator'
           AND EXISTS (SELECT 1 FROM fuel_waybills w WHERE w.origin_operator_user_id = u.id OR w.destination_operator_user_id = u.id)
         ORDER BY u.id DESC"
    )->fetchAll();
} catch (PDOException $e) {
    error_log('api_test operators with waybills fetch error: ' . $e->getMessage());
}

// انتخاب نقش از URL (?role=driver|operator) — سوییچ بین مستندات راننده و متصدی
$roleParam = (string)($_GET['role'] ?? '');
$role = $roleParam === 'operator' ? 'operator' : 'driver';

// انتخاب زیرصفحه از URL (?service=login|trip|active|webview|geofence|trip_action|active_waybill)
// یک ری‌لود واقعی صفحه، نه فقط جابه‌جایی نمایشی با جاوااسکریپت، تا همیشه و
// بدون وابستگی به اجرای اسکریپت سمت مرورگر کار کند.
$serviceParam = (string)($_GET['service'] ?? '');
if ($role === 'operator') {
    $service = in_array($serviceParam, ['webview', 'geofence', 'trip_action', 'active_waybill'], true) ? $serviceParam : 'login';
} else {
    $service = in_array($serviceParam, ['trip', 'active', 'seal'], true) ? $serviceParam : 'login';
}

// اپراتورهای واقعی برای اطلاع‌رسانی در مستندات (اولین کاربر operator فعال، برای نمونه آدرس‌دهی)
$operatorTokenSampleUser = null;
try {
    $operatorTokenSampleUser = db()->query(
        "SELECT id, first_name, last_name, national_code FROM users WHERE user_type = 'operator' AND is_active = 1 ORDER BY id ASC LIMIT 1"
    )->fetch();
} catch (PDOException $e) {
    error_log('api_test operator sample fetch error: ' . $e->getMessage());
}

$page_title = 'مستندات و تست وب‌سرویس‌ها';
$active = 'api';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">مستندات و تست وب‌سرویس‌ها</h1>
  <p class="text-muted small mb-0">اتصال به هر وب‌سرویس را همین‌جا آزمایش کنید و مستندات کامل آن را ببینید.</p>
</div>

<?= render_flash() ?>

<!-- ================= انتخاب نقش (راننده / متصدی) ================= -->
<ul class="nav nav-pills gap-2 mb-3" id="apiRoleTabs">
  <li class="nav-item">
    <a href="?role=driver" class="nav-link <?= $role === 'driver' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:bus-bold-duotone"></span> راننده
    </a>
  </li>
  <li class="nav-item">
    <a href="?role=operator" class="nav-link <?= $role === 'operator' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:buildings-3-bold-duotone"></span> متصدی
    </a>
  </li>
</ul>

<?php if ($role === 'driver'): ?>
<!-- ================= انتخاب وب‌سرویس (راننده) ================= -->
<ul class="nav nav-pills gap-2 mb-4" id="apiServiceTabs">
  <li class="nav-item">
    <a href="?role=driver&service=login" class="nav-link <?= $service === 'login' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:login-3-bold"></span> وب‌سرویس ورود
    </a>
  </li>
  <li class="nav-item">
    <a href="?role=driver&service=trip" class="nav-link <?= $service === 'trip' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:routing-2-bold"></span> وب‌سرویس شروع/پایان سفر
    </a>
  </li>
  <li class="nav-item">
    <a href="?role=driver&service=active" class="nav-link <?= $service === 'active' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:document-text-bold"></span> وب‌سرویس بارنامه ی انتخاب شده
    </a>
  </li>
  <li class="nav-item">
    <a href="?role=driver&service=seal" class="nav-link <?= $service === 'seal' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:lock-keyhole-bold"></span> وب‌سرویس پلمپ بر اساس بارنامه
    </a>
  </li>
</ul>

<!-- ================================================================= -->
<!-- ========================= ۱) وب‌سرویس ورود ========================= -->
<!-- ================================================================= -->
<div class="api-service-panel <?= $service === 'login' ? '' : 'd-none' ?>" data-panel="login">

  <!-- ================= تست زنده ================= -->
  <div class="card panel-card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-jade fs-5" data-icon="solar:test-tube-bold"></span>
      <span class="fw-bold">تست زنده اتصال</span>
    </div>
    <div class="card-body p-4">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label" for="apiUsername">نام کاربری (کد ملی)</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:card-2-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="apiUsername" inputmode="numeric" maxlength="10" value="1111111111">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="apiPassword">رمز عبور</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:lock-password-bold"></span></span>
            <input type="password" class="form-control" id="apiPassword" placeholder="رمز عبور">
            <button type="button" class="input-group-text toggle-pass" data-target="apiPassword" aria-label="نمایش رمز">
              <span class="iconify" data-icon="solar:eye-bold"></span>
            </button>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="apiFormat">نوع ارسال داده</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:code-bold"></span></span>
            <select class="form-select" id="apiFormat">
              <option value="json" selected>JSON</option>
              <option value="form">form-data</option>
            </select>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="apiSendBtn">
          <span class="iconify fs-5" data-icon="solar:play-circle-bold"></span> ارسال درخواست
        </button>
        <button type="button" class="btn btn-soft-purple d-flex align-items-center gap-2" id="apiWrongBtn">
          <span class="iconify" data-icon="solar:close-circle-bold"></span> تست با رمز اشتباه
        </button>
        <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" id="apiClearBtn">
          <span class="iconify" data-icon="solar:eraser-bold"></span> پاک‌کردن نتیجه
        </button>
      </div>

      <!-- نتیجه -->
      <div id="apiResultBox" class="mt-4 d-none">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
          <span class="fw-bold">نتیجه:</span>
          <span class="badge rounded-pill" id="apiStatusBadge"></span>
          <span class="text-muted small" id="apiTimeBadge"></span>
        </div>
        <pre data-lang="json" class="api-response ltr-code mb-0" id="apiResponse"></pre>
      </div>
    </div>
  </div>

  <!-- ================= مستندات ================= -->
  <div class="card panel-card">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-purple fs-5" data-icon="solar:book-2-bold"></span>
      <span class="fw-bold">مستندات وب‌سرویس ورود</span>
    </div>
    <div class="card-body p-4">

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:link-circle-bold"></span> آدرس و متد
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <tbody>
            <tr>
              <th class="w-25">آدرس (Endpoint)</th>
              <td><code class="ltr-code" id="apiEndpointText"></code></td>
            </tr>
            <tr>
              <th>متد</th>
              <td><span class="badge role-badge role-admin">POST</span> (سایر متدها با کد <code>405</code> رد می‌شوند)</td>
            </tr>
            <tr>
              <th>نوع ورودی</th>
              <td><code>application/json</code> یا <code>form-data</code> / <code>x-www-form-urlencoded</code></td>
            </tr>
            <tr>
              <th>خروجی</th>
              <td>همیشه JSON با ساختار ثابت <code class="ltr-code">{ success, message, user? }</code></td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-add-bold"></span> پارامترهای ورودی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>username</code></td><td>رشته</td><td>بله</td>
              <td>کد ملی کاربر (ارقام فارسی هم پذیرفته و تبدیل می‌شود)</td>
            </tr>
            <tr>
              <td><code>password</code></td><td>رشته</td><td>بله</td>
              <td>رمز عبور کاربر</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-text-bold"></span> پارامترهای خروجی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>همیشه؟</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>success</code></td><td>boolean</td><td>بله</td>
              <td><code>true</code> در ورود موفق، در غیر این صورت <code>false</code></td>
            </tr>
            <tr>
              <td><code>message</code></td><td>رشته</td><td>بله</td>
              <td>پیام فارسی نتیجه (موفقیت یا علت خطا)</td>
            </tr>
            <tr>
              <td><code>user</code></td><td>شیء</td><td>فقط در موفقیت</td>
              <td>اطلاعات کاربر بدون هش رمز عبور؛ فیلدهای زیر را دارد</td>
            </tr>
            <tr>
              <td><code>user.id</code></td><td>عدد</td><td>—</td>
              <td>شناسه یکتای کاربر</td>
            </tr>
            <tr>
              <td><code>user.national_code</code></td><td>رشته</td><td>—</td>
              <td>کد ملی (نام کاربری)</td>
            </tr>
            <tr>
              <td><code>user.first_name</code> / <code>user.last_name</code></td><td>رشته</td><td>—</td>
              <td>نام و نام خانوادگی کاربر</td>
            </tr>
            <tr>
              <td><code>user.user_type</code></td><td>رشته</td><td>—</td>
              <td>نقش کاربر (<code>admin</code>، <code>region</code>، <code>operator</code>، <code>driver</code>)</td>
            </tr>
            <tr>
              <td><code>user.user_type_label</code></td><td>رشته</td><td>—</td>
              <td>برچسب فارسی نقش (مثلاً «ادمین»)</td>
            </tr>
            <tr>
              <td><code>user.created_at</code> / <code>user.updated_at</code></td><td>رشته (تاریخ‌ساعت)</td><td>—</td>
              <td>زمان ایجاد و آخرین ویرایش حساب</td>
            </tr>
            <tr>
              <td><code>token</code></td><td>رشته</td><td>فقط در موفقیت</td>
              <td>توکن موقت <code>driver_waybills</code> (۶۴ کاراکتر) برای فراخوانی وب‌سرویس‌های راننده بدون ارسال دوباره رمز؛ اگر ساخت توکن ممکن نباشد در پاسخ نمی‌آید</td>
            </tr>
            <tr>
              <td><code>token_expires_in_minutes</code></td><td>عدد</td><td>همراه <code>token</code></td>
              <td>مدت اعتبار توکن به دقیقه (حداکثر <?= e((string)ACCESS_TOKEN_TTL_MINUTES) ?> دقیقه)</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:server-square-bold"></span> کدهای وضعیت HTTP
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>کد</th><th>وضعیت</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr><td><span class="badge role-badge role-driver">200</span></td><td>موفق</td><td>ورود موفق؛ شیء <code>user</code> بدون هش رمز برگردانده می‌شود</td></tr>
            <tr><td><span class="badge role-badge role-region">400</span></td><td>درخواست نامعتبر</td><td>ساختار JSON ارسالی خراب است</td></tr>
            <tr><td><span class="badge role-badge role-operator">401</span></td><td>عدم احراز</td><td>نام کاربری یا رمز عبور نادرست است</td></tr>
            <tr><td><span class="badge role-badge role-operator">405</span></td><td>متد غیرمجاز</td><td>فقط POST پذیرفته می‌شود</td></tr>
            <tr><td><span class="badge role-badge role-operator">422</span></td><td>ورودی ناقص</td><td>نام کاربری یا رمز ارسال نشده است</td></tr>
            <tr><td><span class="badge role-badge role-admin">500</span></td><td>خطای سرور</td><td>خطای داخلی؛ جزئیات فنی هرگز افشا نمی‌شود</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:check-circle-bold"></span> نمونه پاسخ موفق (200)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "message": "ورود با موفقیت انجام شد.",
  "user": {
    "id": 1,
    "national_code": "1111111111",
    "first_name": "مدیر",
    "last_name": "سیستم",
    "user_type": "admin",
    "user_type_label": "ادمین",
    "created_at": "2026-07-09 10:00:00",
    "updated_at": "2026-07-09 10:00:00"
  }
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:close-circle-bold"></span> نمونه پاسخ ناموفق (401)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": false,
  "message": "نام کاربری یا رمز عبور نادرست است."
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:command-bold"></span> نمونه فراخوانی با cURL
      </h2>
      <pre data-lang="curl" class="api-doc-code ltr-code" id="curlSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:javascript"></span> نمونه فراخوانی با JavaScript (fetch)
      </h2>
      <pre data-lang="javascript" class="api-doc-code ltr-code" id="jsSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:php"></span> نمونه فراخوانی با PHP (cURL)
      </h2>
      <pre data-lang="php" class="api-doc-code ltr-code" id="phpSample"></pre>

      <div class="alert alert-info d-flex align-items-start gap-2 mt-4 mb-0">
        <span class="iconify fs-5 mt-1" data-icon="solar:info-circle-bold"></span>
        <div>
          <strong>نکات:</strong> این وب‌سرویس فقط اعتبار کاربر را بررسی می‌کند و سشن نمی‌سازد؛ برای اپلیکیشن موبایل یا سامانه‌های دیگر مناسب است.
          هش رمز عبور هرگز در پاسخ برگردانده نمی‌شود و همه پیام‌ها فارسی و امن هستند.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ================================================================= -->
<!-- ================ ۲) وب‌سرویس شروع/پایان سفر ================ -->
<!-- ================================================================= -->
<div class="api-service-panel <?= $service === 'trip' ? '' : 'd-none' ?>" data-panel="trip">

  <!-- ================= تست زنده ================= -->
  <div class="card panel-card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-jade fs-5" data-icon="solar:test-tube-bold"></span>
      <span class="fw-bold">تست زنده اتصال</span>
    </div>
    <div class="card-body p-4">

      <div class="alert alert-warning d-flex align-items-start gap-2">
        <span class="iconify fs-5 mt-1" data-icon="solar:danger-triangle-bold"></span>
        <div>
          این تست روی <strong>بارنامه‌های واقعی</strong> اجرا می‌شود و وضعیت آن‌ها را واقعاً تغییر می‌دهد
          (بارنامهٔ «ثبت شده» به «ارسال شده»، یا «ارسال شده» به «تحویل شده» تبدیل می‌شود). فقط
          روی بارنامه‌های تستی استفاده کنید.
        </div>
      </div>

      <?php if (!$startableWaybills && !$endableWaybills): ?>
        <div class="text-muted small">در حال حاضر هیچ بارنامهٔ «ثبت شده» یا «ارسال شده»‌ای برای تست زنده وجود ندارد.</div>
      <?php else: ?>
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label" for="tripWaybillSelect">بارنامه واقعی برای تست</label>
            <select class="form-select" id="tripWaybillSelect">
              <?php foreach ($startableWaybills as $w): ?>
                <option value="<?= e((string)$w['id']) ?>" data-action="start">
                  شروع سفر — بارنامه <?= e($w['waybill_number']) ?> — راننده <?= e($w['first_name'] . ' ' . $w['last_name']) ?> (<?= e($w['national_code']) ?>)
                </option>
              <?php endforeach; ?>
              <?php foreach ($endableWaybills as $w): ?>
                <option value="<?= e((string)$w['id']) ?>" data-action="end">
                  پایان سفر — بارنامه <?= e($w['waybill_number']) ?> — راننده <?= e($w['first_name'] . ' ' . $w['last_name']) ?> (<?= e($w['national_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
          <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="tripSendBtn">
            <span class="iconify fs-5" data-icon="solar:play-circle-bold"></span> ساخت توکن و ارسال درخواست
          </button>
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" id="tripClearBtn">
            <span class="iconify" data-icon="solar:eraser-bold"></span> پاک‌کردن نتیجه
          </button>
        </div>

        <!-- نتیجه -->
        <div id="tripResultBox" class="mt-4 d-none">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-bold">توکن یک‌بارمصرف ساخته‌شده:</span>
            <code class="ltr-code" id="tripTokenText"></code>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-bold">نتیجه:</span>
            <span class="badge rounded-pill" id="tripStatusBadge"></span>
            <span class="text-muted small" id="tripTimeBadge"></span>
          </div>
          <pre data-lang="json" class="api-response ltr-code mb-0" id="tripResponse"></pre>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ================= مستندات ================= -->
  <div class="card panel-card">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-purple fs-5" data-icon="solar:book-2-bold"></span>
      <span class="fw-bold">مستندات وب‌سرویس شروع/پایان سفر</span>
    </div>
    <div class="card-body p-4">

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:link-circle-bold"></span> آدرس و متد
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <tbody>
            <tr>
              <th class="w-25">آدرس (Endpoint)</th>
              <td><code class="ltr-code" id="tripEndpointText"></code></td>
            </tr>
            <tr>
              <th>متد</th>
              <td><span class="badge role-badge role-admin">GET</span> یا <span class="badge role-badge role-admin">POST</span> (سایر متدها با کد <code>405</code> رد می‌شوند)</td>
            </tr>
            <tr>
              <th>نوع ورودی</th>
              <td><code>application/json</code>، <code>form-data</code> یا پارامتر GET</td>
            </tr>
            <tr>
              <th>خروجی</th>
              <td>همیشه JSON با ساختار ثابت <code class="ltr-code">{ success, message }</code></td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-add-bold"></span> پارامترهای ورودی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>token</code></td><td>رشته</td><td>بله</td>
              <td>
                <strong>تنها ورودی این وب‌سرویس.</strong> یک توکن یک‌بارمصرف ۶۴کاراکتری است که
                هم هویت/نقش راننده و هم عملیات (شروع یا پایان سفر) و شناسه بارنامه را در خودش دارد؛
                هیچ پارامتر دیگری (مثل <code>id</code> یا <code>action</code>) پذیرفته نمی‌شود.
                این توکن هنگام کلیک روی دکمهٔ «شروع سفر»/«پایان سفر» در صفحهٔ
                <code>driver_waybills.php</code> ساخته می‌شود (تابع
                <code>create_trip_action_token()</code>) و حداکثر <?= e((string)ACCESS_TOKEN_TTL_MINUTES) ?>
                دقیقه و فقط یک‌بار قابل استفاده است.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-text-bold"></span> پارامترهای خروجی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>همیشه؟</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>success</code></td><td>boolean</td><td>بله</td>
              <td><code>true</code> اگر شروع/پایان سفر با موفقیت ثبت شد، در غیر این صورت <code>false</code></td>
            </tr>
            <tr>
              <td><code>message</code></td><td>رشته</td><td>بله</td>
              <td>پیام فارسی نتیجه؛ در موفقیت مشخص می‌کند سفر «شروع» شده یا «به پایان رسیده» و در خطا علت را می‌گوید</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:server-square-bold"></span> کدهای وضعیت HTTP
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>کد</th><th>وضعیت</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr><td><span class="badge role-badge role-driver">200</span></td><td>موفق</td><td>سفر با موفقیت شروع یا به پایان رسید</td></tr>
            <tr><td><span class="badge role-badge role-operator">401</span></td><td>عدم احراز</td><td>توکن نامعتبر است یا منقضی شده است</td></tr>
            <tr><td><span class="badge role-badge role-operator">403</span></td><td>عدم دسترسی</td><td>توکن متعلق به راننده نیست، حساب غیرفعال است، یا بارنامه به این راننده تخصیص ندارد</td></tr>
            <tr><td><span class="badge role-badge role-operator">404</span></td><td>یافت نشد</td><td>بارنامهٔ داخل توکن دیگر وجود ندارد</td></tr>
            <tr><td><span class="badge role-badge role-region">405</span></td><td>متد غیرمجاز</td><td>فقط GET یا POST پذیرفته می‌شود</td></tr>
            <tr><td><span class="badge role-badge role-operator">409</span></td><td>ناسازگاری وضعیت</td><td>بارنامه از قبل شروع/پایان یافته یا در وضعیت دیگری است</td></tr>
            <tr><td><span class="badge role-badge role-operator">422</span></td><td>ورودی ناقص</td><td>توکن ارسال نشده است</td></tr>
            <tr><td><span class="badge role-badge role-admin">500</span></td><td>خطای سرور</td><td>خطای داخلی؛ جزئیات فنی هرگز افشا نمی‌شود</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:check-circle-bold"></span> نمونه پاسخ موفق — شروع سفر (200)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "message": "سفر با موفقیت شروع شد."
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:check-circle-bold"></span> نمونه پاسخ موفق — پایان سفر (200)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "message": "سفر با موفقیت به پایان رسید."
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:close-circle-bold"></span> نمونه پاسخ ناموفق — توکن نامعتبر (401)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": false,
  "message": "توکن نامعتبر است یا منقضی شده است."
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:close-circle-bold"></span> نمونه پاسخ ناموفق — ناسازگاری وضعیت (409)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": false,
  "message": "این بارنامه قبلاً شروع شده یا در وضعیت دیگری قرار دارد."
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:command-bold"></span> نمونه فراخوانی با cURL
      </h2>
      <pre data-lang="curl" class="api-doc-code ltr-code" id="tripCurlSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:javascript"></span> نمونه فراخوانی با JavaScript (fetch)
      </h2>
      <pre data-lang="javascript" class="api-doc-code ltr-code" id="tripJsSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:php"></span> نمونه فراخوانی با PHP (cURL)
      </h2>
      <pre data-lang="php" class="api-doc-code ltr-code" id="tripPhpSample"></pre>

      <div class="alert alert-info d-flex align-items-start gap-2 mt-4 mb-0">
        <span class="iconify fs-5 mt-1" data-icon="solar:info-circle-bold"></span>
        <div>
          <strong>نکات:</strong> این وب‌سرویس عمداً فقط یک ورودی دارد تا در سمت کلاینت هیچ پارامتری
          (شناسه بارنامه، نوع عملیات) قابل دستکاری نباشد — همه‌چیز از داخل خودِ توکن که سرور ساخته
          استخراج می‌شود. توکن یک‌بار مصرف است؛ بعد از فراخوانی موفق (یا شکست به‌دلیل ناسازگاری
          وضعیت)، دیگر قابل استفادهٔ دوباره نیست و باید توکن تازه از <code>driver_waybills.php</code>
          گرفته شود.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ================================================================= -->
<!-- ==================== ۳) وب‌سرویس بارنامه ی انتخاب شده ==================== -->
<!-- ================================================================= -->
<div class="api-service-panel <?= $service === 'active' ? '' : 'd-none' ?>" data-panel="active">

  <!-- ================= تست زنده ================= -->
  <div class="card panel-card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-jade fs-5" data-icon="solar:test-tube-bold"></span>
      <span class="fw-bold">تست زنده اتصال</span>
    </div>
    <div class="card-body p-4">

      <?php if (!$activeDrivers): ?>
        <div class="text-muted small">
          در حال حاضر هیچ راننده‌ای بارنامه ی انتخاب شده ندارد. این تریگر داخلی
          (<code>users.selected_waybill_id</code>) فقط با کلیک واقعی روی «شروع سفر» در
          <code>driver_waybills.php</code> ست می‌شود؛ برای تست این وب‌سرویس ابتدا باید
          حداقل یک راننده واقعی روی «شروع سفر» یکی از بارنامه‌هایش کلیک کند.
        </div>
      <?php else: ?>
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label" for="activeDriverSelect">راننده واقعی برای تست</label>
            <select class="form-select" id="activeDriverSelect">
              <?php foreach ($activeDrivers as $d): ?>
                <option value="<?= e((string)$d['driver_user_id']) ?>">
                  راننده <?= e($d['first_name'] . ' ' . $d['last_name']) ?> (<?= e($d['national_code']) ?>) — بارنامه ی انتخاب شده: <?= e($d['waybill_number']) ?> (<?= e($d['send_status']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
          <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="activeSendBtn">
            <span class="iconify fs-5" data-icon="solar:play-circle-bold"></span> دریافت توکن و ارسال درخواست
          </button>
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" id="activeClearBtn">
            <span class="iconify" data-icon="solar:eraser-bold"></span> پاک‌کردن نتیجه
          </button>
        </div>

        <!-- نتیجه -->
        <div id="activeResultBox" class="mt-4 d-none">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-bold">توکن راننده مورد استفاده:</span>
            <code class="ltr-code" id="activeTokenText"></code>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-bold">نتیجه:</span>
            <span class="badge rounded-pill" id="activeStatusBadge"></span>
            <span class="text-muted small" id="activeTimeBadge"></span>
          </div>
          <pre data-lang="json" class="api-response ltr-code mb-0" id="activeResponse"></pre>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ================= مستندات ================= -->
  <div class="card panel-card">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-purple fs-5" data-icon="solar:book-2-bold"></span>
      <span class="fw-bold">مستندات وب‌سرویس بارنامه ی انتخاب شده</span>
    </div>
    <div class="card-body p-4">

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:link-circle-bold"></span> آدرس و متد
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <tbody>
            <tr>
              <th class="w-25">آدرس (Endpoint)</th>
              <td><code class="ltr-code" id="activeEndpointText"></code></td>
            </tr>
            <tr>
              <th>متد</th>
              <td><span class="badge role-badge role-admin">GET</span> یا <span class="badge role-badge role-admin">POST</span> (سایر متدها با کد <code>405</code> رد می‌شوند)</td>
            </tr>
            <tr>
              <th>نوع ورودی</th>
              <td><code>application/json</code>، <code>form-data</code> یا پارامتر GET</td>
            </tr>
            <tr>
              <th>خروجی</th>
              <td>همیشه JSON با ساختار ثابت <code class="ltr-code">{ success, message, waybill }</code></td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-add-bold"></span> پارامترهای ورودی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>token</code></td><td>رشته</td><td>بله</td>
              <td>همان توکن استاندارد <code>driver_waybills</code> (از وب‌سرویس ورود یا صفحه
                <code>driver_waybills.php</code>). راننده از روی همین توکن شناسایی می‌شود.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-text-bold"></span> پارامترهای خروجی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>همیشه؟</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>success</code></td><td>boolean</td><td>بله</td>
              <td><code>true</code> در پاسخ موفق (حتی وقتی بارنامه ی انتخاب شدهی وجود ندارد)، در خطا <code>false</code></td>
            </tr>
            <tr>
              <td><code>message</code></td><td>رشته</td><td>بله</td>
              <td>پیام فارسی نتیجه</td>
            </tr>
            <tr>
              <td><code>waybill</code></td><td>شیء یا <code>null</code></td><td>فقط در موفقیت</td>
              <td>بارنامه ی انتخاب شده راننده؛ اگر بارنامه ی انتخاب شدهی نباشد <code>null</code> است. فیلدهای زیر را دارد</td>
            </tr>
            <tr>
              <td><code>waybill.id</code></td><td>عدد</td><td>—</td>
              <td>شناسه یکتای بارنامه</td>
            </tr>
            <tr>
              <td><code>waybill.waybill_number</code></td><td>رشته</td><td>—</td>
              <td>شماره بارنامه</td>
            </tr>
            <tr>
              <td><code>waybill.issue_date</code> / <code>waybill.issue_date_jalali</code></td><td>رشته</td><td>—</td>
              <td>تاریخ صدور میلادی (<code>Y-m-d</code>) و معادل شمسی آن</td>
            </tr>
            <tr>
              <td><code>waybill.distance_km</code></td><td>عدد</td><td>—</td>
              <td>مسافت مسیر به کیلومتر</td>
            </tr>
            <tr>
              <td><code>waybill.product_type</code></td><td>رشته</td><td>—</td>
              <td>نوع فرآورده (مثلاً «نفتگاز»)</td>
            </tr>
            <tr>
              <td><code>waybill.send_status</code></td><td>رشته</td><td>—</td>
              <td>وضعیت فعلی بارنامه («ثبت شده»، «ارسال شده» و ...)؛ چون انتخاب‌شدن مستقل از این وضعیت است، می‌تواند هر مقداری باشد</td>
            </tr>
            <tr>
              <td><code>waybill.trip_started_at</code></td><td>رشته (تاریخ‌ساعت)</td><td>—</td>
              <td>زمان شروع سفر</td>
            </tr>
            <tr>
              <td><code>waybill.origin_title</code> / <code>waybill.destination_title</code></td><td>رشته</td><td>—</td>
              <td>عنوان مبدأ و مقصد</td>
            </tr>
            <tr>
              <td><code>waybill.origin_lat</code> / <code>waybill.origin_lon</code></td><td>عدد</td><td>—</td>
              <td>مختصات جغرافیایی مبدأ</td>
            </tr>
            <tr>
              <td><code>waybill.destination_lat</code> / <code>waybill.destination_lon</code></td><td>عدد</td><td>—</td>
              <td>مختصات جغرافیایی مقصد</td>
            </tr>
            <tr>
              <td><code>waybill.origin_operator</code> / <code>waybill.destination_operator</code></td><td>رشته یا <code>null</code></td><td>—</td>
              <td>نام اپراتور مبدأ/مقصد؛ اگر تعیین نشده باشد <code>null</code></td>
            </tr>
            <tr>
              <td><code>waybill.seal_id</code></td><td>رشته یا <code>null</code></td><td>—</td>
              <td>شناسه پلمپ تخصیص‌یافته به بارنامه</td>
            </tr>
            <tr>
              <td><code>waybill.seal_password</code></td><td>رشته یا <code>null</code></td><td>—</td>
              <td>رمز/کد پلمپ (هش‌شده، ذخیره‌شده روی سرور)</td>
            </tr>
            <tr>
              <td><code>waybill.service_uuid</code></td><td>رشته یا <code>null</code></td><td>—</td>
              <td>Service UUID پلمپ (برای ارتباط BLE)</td>
            </tr>
            <tr>
              <td><code>waybill.characteristic_uuid</code></td><td>رشته یا <code>null</code></td><td>—</td>
              <td>Characteristic UUID پلمپ (برای خواندن/نوشتن روی دستگاه)</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:server-square-bold"></span> کدهای وضعیت HTTP
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>کد</th><th>وضعیت</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr><td><span class="badge role-badge role-driver">200</span></td><td>موفق</td><td>پاسخ برگردانده شد؛ اگر بارنامه ی انتخاب شدهی نباشد باز هم <code>200</code> است و <code>waybill</code> برابر <code>null</code> خواهد بود</td></tr>
            <tr><td><span class="badge role-badge role-operator">401</span></td><td>عدم احراز</td><td>توکن نامعتبر است یا منقضی شده است</td></tr>
            <tr><td><span class="badge role-badge role-operator">403</span></td><td>عدم دسترسی</td><td>توکن متعلق به راننده نیست یا حساب غیرفعال است</td></tr>
            <tr><td><span class="badge role-badge role-region">405</span></td><td>متد غیرمجاز</td><td>فقط GET یا POST پذیرفته می‌شود</td></tr>
            <tr><td><span class="badge role-badge role-operator">422</span></td><td>ورودی ناقص</td><td>توکن ارسال نشده است</td></tr>
            <tr><td><span class="badge role-badge role-admin">500</span></td><td>خطای سرور</td><td>خطای داخلی؛ جزئیات فنی هرگز افشا نمی‌شود</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:check-circle-bold"></span> نمونه پاسخ موفق — بارنامه ی انتخاب شده وجود دارد (200)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "message": "بارنامه ی انتخاب شده با موفقیت دریافت شد.",
  "waybill": {
    "id": 17,
    "waybill_number": "5",
    "issue_date": "2026-07-10",
    "issue_date_jalali": "1405/04/19",
    "distance_km": 82.5,
    "product_type": "نفتگاز",
    "send_status": "ارسال شده",
    "trip_started_at": "2026-07-17 09:12:00",
    "origin_title": "انبار نفت مرکزی",
    "origin_lat": 35.6892,
    "origin_lon": 51.389,
    "destination_title": "پایانه سوخت جنوب",
    "destination_lat": 35.5122,
    "destination_lon": 51.442,
    "origin_operator": "علی رضایی",
    "destination_operator": null,
    "seal_id": "SL-1042"
  }
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:info-circle-bold"></span> نمونه پاسخ موفق — بارنامه ی انتخاب شدهی وجود ندارد (200)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "message": "در حال حاضر هیچ بارنامه ی انتخاب شدهی برای شما وجود ندارد.",
  "waybill": null
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:close-circle-bold"></span> نمونه پاسخ ناموفق — توکن نامعتبر (401)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": false,
  "message": "توکن نامعتبر است یا منقضی شده است."
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:command-bold"></span> نمونه فراخوانی با cURL
      </h2>
      <pre data-lang="curl" class="api-doc-code ltr-code" id="activeCurlSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:javascript"></span> نمونه فراخوانی با JavaScript (fetch)
      </h2>
      <pre data-lang="javascript" class="api-doc-code ltr-code" id="activeJsSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:php"></span> نمونه فراخوانی با PHP (cURL)
      </h2>
      <pre data-lang="php" class="api-doc-code ltr-code" id="activePhpSample"></pre>

      <div class="alert alert-info d-flex align-items-start gap-2 mt-4 mb-0">
        <span class="iconify fs-5 mt-1" data-icon="solar:info-circle-bold"></span>
        <div>
          <strong>نکات:</strong> «انتخاب‌شده» یک تریگر داخلی است (ستون <code>users.selected_waybill_id</code>)
          و مستقل از <code>send_status</code> بارنامه؛ همین که راننده روی «شروع سفر» یک بارنامه در
          <code>driver_waybills.php</code> کلیک کند (چه بررسی حصار را کامل کند چه نه)، همان بارنامه
          به‌عنوان انتخاب‌شدهٔ او ثبت می‌شود، و با «پایان سفر» موفق پاک می‌شود. این وب‌سرویس فقط
          خواندنی است و هیچ داده‌ای را تغییر نمی‌دهد؛ می‌توان آن را هر چند بار که لازم است (مثلاً هر
          چند ثانیه از اپلیکیشن موبایل راننده) فراخوانی کرد.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ================================================================= -->
<!-- ================= ۴) وب‌سرویس پلمپ بر اساس بارنامه ================= -->
<!-- ================================================================= -->
<div class="api-service-panel <?= $service === 'seal' ? '' : 'd-none' ?>" data-panel="seal">

  <!-- ================= تست زنده ================= -->
  <div class="card panel-card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-jade fs-5" data-icon="solar:test-tube-bold"></span>
      <span class="fw-bold">تست زنده اتصال</span>
    </div>
    <div class="card-body p-4">

      <?php if (!$allActiveDrivers && !$allActiveOperators): ?>
        <div class="text-muted small">
          در حال حاضر هیچ راننده یا متصدی فعالی در سیستم وجود ندارد. برای تست این وب‌سرویس ابتدا باید
          حداقل یک حساب راننده یا متصدی فعال ثبت شده باشد.
        </div>
      <?php else: ?>
        <div class="mb-3">
          <label class="form-label d-block">نوع توکن آزمایشی</label>
          <div class="btn-group" role="group" aria-label="نوع توکن">
            <input type="radio" class="btn-check" name="sealRoleRadio" id="sealRoleDriver" value="driver" <?= $allActiveDrivers ? 'checked' : 'disabled' ?>>
            <label class="btn btn-outline-primary" for="sealRoleDriver">راننده (<code>driver_waybills</code>)</label>

            <input type="radio" class="btn-check" name="sealRoleRadio" id="sealRoleOperator" value="operator" <?= (!$allActiveDrivers && $allActiveOperators) ? 'checked' : '' ?> <?= !$allActiveOperators ? 'disabled' : '' ?>>
            <label class="btn btn-outline-primary" for="sealRoleOperator">متصدی (<code>operator_waybills</code>)</label>
          </div>
        </div>

        <div class="row g-3 align-items-end">
          <div class="col-md-6" id="sealDriverSelectWrap" <?= $allActiveDrivers ? '' : 'style="display:none"' ?>>
            <label class="form-label" for="sealDriverSelect">راننده برای ساخت توکن آزمایشی</label>
            <select class="form-select" id="sealDriverSelect">
              <?php foreach ($allActiveDrivers as $d): ?>
                <option value="<?= e((string)$d['id']) ?>">
                  راننده <?= e($d['first_name'] . ' ' . $d['last_name']) ?> (<?= e($d['national_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6" id="sealOperatorSelectWrap" <?= ($allActiveOperators && !$allActiveDrivers) ? '' : 'style="display:none"' ?>>
            <label class="form-label" for="sealOperatorSelect">متصدی برای ساخت توکن آزمایشی</label>
            <select class="form-select" id="sealOperatorSelect">
              <?php foreach ($allActiveOperators as $o): ?>
                <option value="<?= e((string)$o['id']) ?>">
                  متصدی <?= e($o['first_name'] . ' ' . $o['last_name']) ?> (<?= e($o['national_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="sealWaybillNumberInput">شماره بارنامه</label>
            <?php if ($waybillsWithSeal): ?>
              <input type="text" class="form-control ltr-text" id="sealWaybillNumberInput"
                     list="sealWaybillNumberList" placeholder="مثلاً 5" value="<?= e((string)$waybillsWithSeal[0]['waybill_number']) ?>">
              <datalist id="sealWaybillNumberList">
                <?php foreach ($waybillsWithSeal as $w): ?>
                  <option value="<?= e((string)$w['waybill_number']) ?>">پلمپ: <?= e((string)$w['seal_id']) ?></option>
                <?php endforeach; ?>
              </datalist>
              <div class="form-text">شماره بارنامه‌هایی که هم‌اکنون پلمپ دارند به‌عنوان پیشنهاد نمایش داده می‌شود.</div>
            <?php else: ?>
              <input type="text" class="form-control ltr-text" id="sealWaybillNumberInput" placeholder="مثلاً 5">
              <div class="form-text text-muted">
                در حال حاضر هیچ بارنامه‌ای پلمپ متصل ندارد؛ می‌توانید یک شماره بارنامه دلخواه وارد کنید
                (پاسخ ۴۰۴ «یافت نشد» خواهد بود).
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
          <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="sealSendBtn">
            <span class="iconify fs-5" data-icon="solar:play-circle-bold"></span> دریافت توکن و ارسال درخواست
          </button>
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" id="sealClearBtn">
            <span class="iconify" data-icon="solar:eraser-bold"></span> پاک‌کردن نتیجه
          </button>
        </div>

        <!-- نتیجه -->
        <div id="sealResultBox" class="mt-4 d-none">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-bold" id="sealTokenLabel">توکن مورد استفاده:</span>
            <code class="ltr-code" id="sealTokenText"></code>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-bold">نتیجه:</span>
            <span class="badge rounded-pill" id="sealStatusBadge"></span>
            <span class="text-muted small" id="sealTimeBadge"></span>
          </div>
          <pre data-lang="json" class="api-response ltr-code mb-0" id="sealResponse"></pre>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ================= مستندات ================= -->
  <div class="card panel-card">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-purple fs-5" data-icon="solar:book-2-bold"></span>
      <span class="fw-bold">مستندات وب‌سرویس پلمپ بر اساس شماره بارنامه</span>
    </div>
    <div class="card-body p-4">

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:link-circle-bold"></span> آدرس و متد
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <tbody>
            <tr>
              <th class="w-25">آدرس (Endpoint)</th>
              <td><code class="ltr-code" id="sealEndpointText"></code></td>
            </tr>
            <tr>
              <th>متد</th>
              <td><span class="badge role-badge role-admin">GET</span> یا <span class="badge role-badge role-admin">POST</span> (سایر متدها با کد <code>405</code> رد می‌شوند)</td>
            </tr>
            <tr>
              <th>نوع ورودی</th>
              <td><code>application/json</code>، <code>form-data</code> یا پارامتر GET</td>
            </tr>
            <tr>
              <th>خروجی</th>
              <td>همیشه JSON با ساختار ثابت <code class="ltr-code">{ success, message, seal }</code></td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-add-bold"></span> پارامترهای ورودی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>token</code></td><td>رشته</td><td>بله</td>
              <td>یک توکن معتبر و فعال، یا <code>driver_waybills</code> (راننده) یا
                <code>operator_waybills</code> (متصدی) — از وب‌سرویس ورود یا صفحات
                <code>driver_waybills.php</code> / <code>operator_waybills_public.php</code>.
                برای توکن راننده، مالکیت بارنامه بررسی نمی‌شود؛ برای توکن متصدی، بارنامه باید
                یکی از بارنامه‌های تخصیص‌یافته به همان متصدی (مبدا یا مقصد) باشد.</td>
            </tr>
            <tr>
              <td><code>waybill_number</code></td><td>رشته</td><td>بله</td>
              <td>شماره بارنامه‌ای که پلمپ آن مورد نظر است</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-text-bold"></span> پارامترهای خروجی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>همیشه؟</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>success</code></td><td>boolean</td><td>بله</td>
              <td><code>true</code> در پاسخ موفق، در خطا (شامل «بارنامه/پلمپ یافت نشد») <code>false</code></td>
            </tr>
            <tr>
              <td><code>message</code></td><td>رشته</td><td>بله</td>
              <td>پیام فارسی نتیجه</td>
            </tr>
            <tr>
              <td><code>seal</code></td><td>شیء</td><td>فقط در موفقیت</td>
              <td>اطلاعات پلمپ متصل به بارنامه؛ فیلدهای زیر را دارد</td>
            </tr>
            <tr>
              <td><code>seal.id</code></td><td>عدد</td><td>—</td>
              <td>شناسه یکتای رکورد پلمپ در جدول <code>seals</code></td>
            </tr>
            <tr>
              <td><code>seal.seal_id</code></td><td>رشته</td><td>—</td>
              <td>شناسه پلمپ</td>
            </tr>
            <tr>
              <td><code>seal.seal_password</code></td><td>رشته</td><td>—</td>
              <td>رمز/کد پلمپ</td>
            </tr>
            <tr>
              <td><code>seal.service_uuid</code></td><td>رشته یا <code>null</code></td><td>—</td>
              <td>Service UUID پلمپ (برای ارتباط BLE)</td>
            </tr>
            <tr>
              <td><code>seal.characteristic_uuid</code></td><td>رشته یا <code>null</code></td><td>—</td>
              <td>Characteristic UUID پلمپ (برای خواندن/نوشتن روی دستگاه)</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:server-square-bold"></span> کدهای وضعیت HTTP
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>کد</th><th>وضعیت</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr><td><span class="badge role-badge role-driver">200</span></td><td>موفق</td><td>پلمپ متصل به این بارنامه برگردانده شد</td></tr>
            <tr><td><span class="badge role-badge role-operator">401</span></td><td>عدم احراز</td><td>توکن نامعتبر است یا منقضی شده است</td></tr>
            <tr><td><span class="badge role-badge role-operator">403</span></td><td>عدم دسترسی</td><td>توکن متعلق به راننده/متصدی نیست یا حساب غیرفعال است</td></tr>
            <tr><td><span class="badge role-badge role-region">404</span></td><td>یافت نشد</td><td>بارنامه‌ای با این شماره وجود ندارد، پلمپی به آن متصل نیست، یا (برای توکن متصدی) بارنامه به آن متصدی تخصیص نیافته</td></tr>
            <tr><td><span class="badge role-badge role-region">405</span></td><td>متد غیرمجاز</td><td>فقط GET یا POST پذیرفته می‌شود</td></tr>
            <tr><td><span class="badge role-badge role-operator">422</span></td><td>ورودی ناقص</td><td>توکن یا شماره بارنامه ارسال نشده است</td></tr>
            <tr><td><span class="badge role-badge role-admin">500</span></td><td>خطای سرور</td><td>خطای داخلی؛ جزئیات فنی هرگز افشا نمی‌شود</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:check-circle-bold"></span> نمونه پاسخ موفق (200)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "message": "اطلاعات پلمپ با موفقیت دریافت شد.",
  "seal": {
    "id": 12,
    "seal_id": "SL-1042",
    "seal_password": "AB12CD34",
    "service_uuid": "0000180a-0000-1000-8000-00805f9b34fb",
    "characteristic_uuid": "00002a29-0000-1000-8000-00805f9b34fb"
  }
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:close-circle-bold"></span> نمونه پاسخ ناموفق — یافت نشد (404)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": false,
  "message": "پلمپی برای این بارنامه یافت نشد."
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:close-circle-bold"></span> نمونه پاسخ ناموفق — توکن نامعتبر (401)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": false,
  "message": "توکن نامعتبر است یا منقضی شده است."
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:command-bold"></span> نمونه فراخوانی با cURL
      </h2>
      <pre data-lang="curl" class="api-doc-code ltr-code" id="sealCurlSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:javascript"></span> نمونه فراخوانی با JavaScript (fetch)
      </h2>
      <pre data-lang="javascript" class="api-doc-code ltr-code" id="sealJsSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:php"></span> نمونه فراخوانی با PHP (cURL)
      </h2>
      <pre data-lang="php" class="api-doc-code ltr-code" id="sealPhpSample"></pre>

      <div class="alert alert-info d-flex align-items-start gap-2 mt-4 mb-0">
        <span class="iconify fs-5 mt-1" data-icon="solar:info-circle-bold"></span>
        <div>
          <strong>نکات:</strong> این وب‌سرویس فقط خواندنی است و هیچ داده‌ای را تغییر نمی‌دهد. برخلاف
          وب‌سرویس بارنامه ی انتخاب شده، این وب‌سرویس بررسی نمی‌کند که بارنامه متعلق به همان راننده‌ای
          باشد که توکن از آن است؛ هر توکن معتبر <code>driver_waybills</code> برای هر شماره بارنامه‌ای
          که پلمپ داشته باشد پاسخ می‌دهد. اما برای توکن <code>operator_waybills</code>، برخلاف راننده،
          مالکیت بررسی می‌شود: فقط بارنامه‌ای که همان متصدی به‌عنوان متصدی مبدا یا مقصد آن تخصیص یافته
          پاسخ می‌گیرد؛ در غیر این صورت پاسخ ۴۰۴ «یافت نشد» برمی‌گردد.
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; // پایان بخش راننده (role === 'driver') ?>

<?php if ($role === 'operator'): ?>
<!-- ================= انتخاب وب‌سرویس (متصدی) ================= -->
<ul class="nav nav-pills gap-2 mb-4" id="apiOperatorServiceTabs">
  <li class="nav-item">
    <a href="?role=operator&service=login" class="nav-link <?= $service === 'login' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:login-3-bold"></span> وب‌سرویس ورود
    </a>
  </li>
  <li class="nav-item">
    <a href="?role=operator&service=webview" class="nav-link <?= $service === 'webview' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:widget-5-bold"></span> صفحهٔ عمومی (Webview)
    </a>
  </li>
  <li class="nav-item">
    <a href="?role=operator&service=geofence" class="nav-link <?= $service === 'geofence' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:map-point-search-bold"></span> وب‌سرویس حصار جغرافیایی
    </a>
  </li>
  <li class="nav-item">
    <a href="?role=operator&service=trip_action" class="nav-link <?= $service === 'trip_action' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:routing-2-bold"></span> وب‌سرویس تایید حضور متصدی
    </a>
  </li>
  <li class="nav-item">
    <a href="?role=operator&service=active_waybill" class="nav-link <?= $service === 'active_waybill' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:document-text-bold"></span> وب‌سرویس بارنامه‌های ایستگاه
    </a>
  </li>
</ul>

<!-- ================================================================= -->
<!-- ==================== ۱) وب‌سرویس ورود (متصدی) ==================== -->
<!-- ================================================================= -->
<div class="api-service-panel <?= $service === 'login' ? '' : 'd-none' ?>" data-panel="op-login">
  <div class="card panel-card">
    <div class="card-body p-4">
      <div class="alert alert-info d-flex align-items-start gap-2 mb-0">
        <span class="iconify fs-5 mt-1" data-icon="solar:info-circle-bold"></span>
        <div>
          وب‌سرویس ورود متصدی همان <code class="ltr-code">api/login.php</code> است که در تب «راننده ← وب‌سرویس ورود»
          مستند شده — این وب‌سرویس مستقل از نقش کاربر است و هر سه نقش (<code>driver</code>، <code>operator</code>، <code>region</code>)
          را می‌پذیرد. تنها تفاوت، مقدار <code>user.user_type</code> در پاسخ موفق است که برای متصدی برابر
          <code class="ltr-code">"operator"</code> خواهد بود. توکن صادرشده برای متصدی با پارامتر
          <code>purpose = operator_waybills</code> ساخته و در سایر وب‌سرویس‌های این تب معتبر است (نه
          <code>driver_waybills</code>).
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ================================================================= -->
<!-- =============== ۲) صفحهٔ عمومی متصدی (Webview) =============== -->
<!-- ================================================================= -->
<div class="api-service-panel <?= $service === 'webview' ? '' : 'd-none' ?>" data-panel="op-webview">
  <div class="card panel-card">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-purple fs-5" data-icon="solar:book-2-bold"></span>
      <span class="fw-bold">مستندات صفحهٔ عمومی «Webview»</span>
    </div>
    <div class="card-body p-4">

      <div class="alert alert-info d-flex align-items-start gap-2">
        <span class="iconify fs-5 mt-1" data-icon="solar:info-circle-bold"></span>
        <div>
          «Webview» به صفحاتی گفته می‌شود که <strong>خارج از پنل اصلی ادمین</strong> قرار دارند، نیازی به
          سشن/ورود به پنل ندارند، و معمولاً داخل یک WebView از اپلیکیشن موبایل (یا مرورگر گوشی) با یک
          <strong>توکن</strong> در URL باز می‌شوند — نه با کوکی سشن. نمونهٔ اصلی این الگو برای راننده
          <code class="ltr-code">driver_waybills.php</code> است؛ معادل آن برای متصدی
          <code class="ltr-code">operator_waybills_public.php</code> است.
        </div>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:link-circle-bold"></span> آدرس‌ها
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <tbody>
            <tr>
              <th class="w-25">صفحهٔ عمومی متصدی</th>
              <td><code class="ltr-code"><?= BASE_URL ?>/operator_waybills_public.php?token=...</code></td>
            </tr>
            <tr>
              <th>صفحهٔ عمومی راننده (برای مقایسه)</th>
              <td><code class="ltr-code"><?= BASE_URL ?>/driver_waybills.php?token=...</code></td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:routing-2-bold"></span> دو روش ورود
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>روش</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>۱) با توکن در URL</td>
              <td>
                توکن از طریق وب‌سرویس ورود (<code>api/login.php</code>، با <code>purpose = operator_waybills</code>)
                گرفته می‌شود و به‌صورت <code class="ltr-code">?token=...</code> در URL ارسال می‌شود؛ حداکثر
                <?= e((string)ACCESS_TOKEN_TTL_MINUTES) ?> دقیقه اعتبار دارد. این روش برای بازکردن صفحه از داخل
                WebView اپلیکیشن مناسب است — چون رمز عبور در URL قرار نمی‌گیرد.
              </td>
            </tr>
            <tr>
              <td>۲) با فرم کد ملی/رمز عبور</td>
              <td>
                اگر توکن معتبر نباشد یا وجود نداشته باشد، فرم ورود مستقیم نمایش داده می‌شود. بعد از ورود موفق
                (POST)، یک توکن تازه ساخته و کاربر با ری‌دایرکت به همان آدرس با <code>?token=...</code> هدایت
                می‌شود تا از آن پس رمز عبور در هیچ درخواستی (از جمله تایید حضور) استفاده نشود.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:widget-5-bold"></span> رفتار صفحه
      </h2>
      <ul class="mb-4">
        <li>فهرست بارنامه‌هایی که متصدی به‌عنوان متصدی مبدا یا مقصد آن تخصیص یافته را نشان می‌دهد.</li>
        <li>برای هر بارنامه که هنوز حضور متصدی در آن تایید نشده، دکمهٔ «تایید حضور» نمایش داده می‌شود.</li>
        <li>
          کلیک روی «تایید حضور» کاربر را با یک توکن یک‌بارمصرف مخصوص همان عملیات
          (<code>helpers/tokens.php::create_operator_action_token</code>) به صفحهٔ بررسی حصار جغرافیایی
          (<code>waybill_operator_geofence_check.php</code>) می‌فرستد؛ همان الگویی که راننده برای شروع/پایان
          سفر با <code>waybill_geofence_check.php</code> طی می‌کند.
        </li>
        <li>این صفحه هیچ کوکی/سشنی نمی‌سازد و مستقل از پنل ادمین قابل استفاده است.</li>
      </ul>

      <div class="text-center">
        <a href="<?= BASE_URL ?>/operator_waybills_public.php" target="_blank" class="btn btn-outline-secondary d-flex align-items-center gap-2 d-inline-flex">
          <span class="iconify" data-icon="solar:square-top-down-bold"></span> بازکردن صفحهٔ عمومی متصدی
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ================================================================= -->
<!-- =============== ۳) وب‌سرویس حصار جغرافیایی =============== -->
<!-- ================================================================= -->
<div class="api-service-panel <?= $service === 'geofence' ? '' : 'd-none' ?>" data-panel="op-geofence">
  <div class="card panel-card">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-purple fs-5" data-icon="solar:book-2-bold"></span>
      <span class="fw-bold">مستندات وب‌سرویس حصار جغرافیایی</span>
    </div>
    <div class="card-body p-4">

      <div class="alert alert-info d-flex align-items-start gap-2">
        <span class="iconify fs-5 mt-1" data-icon="solar:info-circle-bold"></span>
        <div>
          این وب‌سرویس عمومی و مشترک بین راننده و متصدی است (به‌طور مستقیم به نقش کاربر وابسته نیست)؛
          صفحهٔ <code class="ltr-code">waybill_operator_geofence_check.php</code> پیش از تایید نهاییِ
          «تایید حضور متصدی» از همین وب‌سرویس برای بررسی داخل‌بودن موقعیت فعلی کاربر در محدودهٔ مکان
          استفاده می‌کند (دقیقاً مانند <code>waybill_geofence_check.php</code> برای راننده).
        </div>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:link-circle-bold"></span> آدرس و متد
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <tbody>
            <tr>
              <th class="w-25">آدرس (Endpoint)</th>
              <td><code class="ltr-code" id="geofenceEndpointText"></code></td>
            </tr>
            <tr>
              <th>متد</th>
              <td><span class="badge role-badge role-admin">GET</span></td>
            </tr>
            <tr>
              <th>نیازمند کلید؟</th>
              <td>بله — این وب‌سرویس مانند بقیهٔ وب‌سرویس‌های داخلی نقشه با <code>require_key()</code> محافظت می‌شود.</td>
            </tr>
            <tr>
              <th>خروجی</th>
              <td>همیشه JSON با ساختار ثابت <code class="ltr-code">{ success, id, region_id, name, inside, point }</code></td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-add-bold"></span> پارامترهای ورودی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr><td><code>id</code></td><td>عدد</td><td>بله</td><td>شناسهٔ مکان (locations.id) — مبدا یا مقصد بارنامه</td></tr>
            <tr><td><code>lat</code></td><td>اعشاری</td><td>بله</td><td>عرض جغرافیایی موقعیت فعلی کاربر</td></tr>
            <tr><td><code>lon</code></td><td>اعشاری</td><td>بله</td><td>طول جغرافیایی موقعیت فعلی کاربر</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:check-circle-bold"></span> نمونه پاسخ موفق
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "id": 12,
  "region_id": 3,
  "name": "انبار نفت مرکزی",
  "inside": true,
  "point": { "lat": 35.6892, "lon": 51.389 }
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:command-bold"></span> نمونه فراخوانی با cURL
      </h2>
      <pre data-lang="curl" class="api-doc-code ltr-code" id="geofenceCurlSample"></pre>
    </div>
  </div>
</div>

<!-- ================================================================= -->
<!-- =============== ۴) وب‌سرویس تایید حضور متصدی =============== -->
<!-- ================================================================= -->
<div class="api-service-panel <?= $service === 'trip_action' ? '' : 'd-none' ?>" data-panel="op-trip-action">

  <!-- ================= تست زنده ================= -->
  <div class="card panel-card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-jade fs-5" data-icon="solar:test-tube-bold"></span>
      <span class="fw-bold">تست زنده اتصال</span>
    </div>
    <div class="card-body p-4">

      <div class="alert alert-warning d-flex align-items-start gap-2">
        <span class="iconify fs-5 mt-1" data-icon="solar:danger-triangle-bold"></span>
        <div>
          این تست روی <strong>بارنامه‌های واقعی</strong> اجرا می‌شود و «تایید حضور» متصدی را واقعاً ثبت می‌کند.
          فقط روی بارنامه‌های تستی استفاده کنید.
        </div>
      </div>

      <?php if (!$operatorPendingConfirm): ?>
        <div class="text-muted small">در حال حاضر هیچ بارنامهٔ منتظر تایید حضور متصدی وجود ندارد.</div>
      <?php else: ?>
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label" for="opTripWaybillSelect">بارنامه و نقش متصدی برای تست</label>
            <select class="form-select" id="opTripWaybillSelect">
              <?php foreach ($operatorPendingConfirm as $item): $row = $item['row']; $r = $item['role']; ?>
                <option value="<?= e((string)$row['id']) ?>" data-role="<?= e($r) ?>">
                  <?= $r === 'origin' ? 'تایید حضور مبدا' : 'تایید حضور مقصد' ?> — بارنامه <?= e($row['waybill_number']) ?> —
                  متصدی <?= $r === 'origin' ? e($row['origin_first'] . ' ' . $row['origin_last'] . ' (' . $row['origin_nc'] . ')') : e($row['dest_first'] . ' ' . $row['dest_last'] . ' (' . $row['dest_nc'] . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
          <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="opTripSendBtn">
            <span class="iconify fs-5" data-icon="solar:play-circle-bold"></span> ساخت توکن و ارسال درخواست
          </button>
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" id="opTripClearBtn">
            <span class="iconify" data-icon="solar:eraser-bold"></span> پاک‌کردن نتیجه
          </button>
        </div>

        <div id="opTripResultBox" class="mt-4 d-none">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-bold">توکن یک‌بارمصرف ساخته‌شده:</span>
            <code class="ltr-code" id="opTripTokenText"></code>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-bold">نتیجه:</span>
            <span class="badge rounded-pill" id="opTripStatusBadge"></span>
            <span class="text-muted small" id="opTripTimeBadge"></span>
          </div>
          <pre data-lang="json" class="api-response ltr-code mb-0" id="opTripResponse"></pre>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ================= مستندات ================= -->
  <div class="card panel-card">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-purple fs-5" data-icon="solar:book-2-bold"></span>
      <span class="fw-bold">مستندات وب‌سرویس تایید حضور متصدی</span>
    </div>
    <div class="card-body p-4">

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:link-circle-bold"></span> آدرس و متد
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <tbody>
            <tr>
              <th class="w-25">آدرس (Endpoint)</th>
              <td><code class="ltr-code" id="opTripEndpointText"></code></td>
            </tr>
            <tr>
              <th>متد</th>
              <td><span class="badge role-badge role-admin">GET</span> یا <span class="badge role-badge role-admin">POST</span> (سایر متدها با کد <code>405</code> رد می‌شوند)</td>
            </tr>
            <tr>
              <th>نوع ورودی</th>
              <td><code>application/json</code>، <code>form-data</code> یا پارامتر GET</td>
            </tr>
            <tr>
              <th>خروجی</th>
              <td>همیشه JSON با ساختار ثابت <code class="ltr-code">{ success, message }</code></td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-add-bold"></span> پارامترهای ورودی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>token</code></td><td>رشته</td><td>بله</td>
              <td>
                <strong>تنها ورودی این وب‌سرویس.</strong> یک توکن یک‌بارمصرف ۶۴کاراکتری است که هم هویت/نقش
                متصدی و هم نقش او در بارنامه (<code>origin</code> یا <code>destination</code>) و شناسه بارنامه
                را در خودش دارد. این توکن هنگام کلیک روی دکمهٔ «تایید حضور» در صفحهٔ
                <code>operator_waybills_public.php</code> ساخته می‌شود (تابع
                <code>create_operator_action_token()</code>) و حداکثر <?= e((string)ACCESS_TOKEN_TTL_MINUTES) ?>
                دقیقه و فقط یک‌بار قابل استفاده است.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:server-square-bold"></span> کدهای وضعیت HTTP
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>کد</th><th>وضعیت</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr><td><span class="badge role-badge role-driver">200</span></td><td>موفق</td><td>حضور متصدی با موفقیت تایید شد</td></tr>
            <tr><td><span class="badge role-badge role-operator">401</span></td><td>عدم احراز</td><td>توکن نامعتبر است یا منقضی شده است</td></tr>
            <tr><td><span class="badge role-badge role-operator">403</span></td><td>عدم دسترسی</td><td>توکن متعلق به متصدی نیست، حساب غیرفعال است، یا بارنامه به این متصدی تخصیص ندارد</td></tr>
            <tr><td><span class="badge role-badge role-operator">404</span></td><td>یافت نشد</td><td>بارنامهٔ داخل توکن دیگر وجود ندارد</td></tr>
            <tr><td><span class="badge role-badge role-region">405</span></td><td>متد غیرمجاز</td><td>فقط GET یا POST پذیرفته می‌شود</td></tr>
            <tr><td><span class="badge role-badge role-operator">409</span></td><td>ناسازگاری وضعیت</td><td>حضور متصدی قبلاً تایید شده است</td></tr>
            <tr><td><span class="badge role-badge role-operator">422</span></td><td>ورودی ناقص</td><td>توکن ارسال نشده است</td></tr>
            <tr><td><span class="badge role-badge role-admin">500</span></td><td>خطای سرور</td><td>خطای داخلی؛ جزئیات فنی هرگز افشا نمی‌شود</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:check-circle-bold"></span> نمونه پاسخ موفق (200)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "message": "حضور شما در مبدا با موفقیت تایید شد."
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:close-circle-bold"></span> نمونه پاسخ ناموفق — ناسازگاری وضعیت (409)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": false,
  "message": "حضور شما برای این بارنامه قبلاً تایید شده است."
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:command-bold"></span> نمونه فراخوانی با cURL
      </h2>
      <pre data-lang="curl" class="api-doc-code ltr-code" id="opTripCurlSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:javascript"></span> نمونه فراخوانی با JavaScript (fetch)
      </h2>
      <pre data-lang="javascript" class="api-doc-code ltr-code" id="opTripJsSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:php"></span> نمونه فراخوانی با PHP (cURL)
      </h2>
      <pre data-lang="php" class="api-doc-code ltr-code" id="opTripPhpSample"></pre>

      <div class="alert alert-info d-flex align-items-start gap-2 mt-4 mb-0">
        <span class="iconify fs-5 mt-1" data-icon="solar:info-circle-bold"></span>
        <div>
          <strong>نکات:</strong> برخلاف شروع/پایان سفر راننده، تایید حضور متصدی وضعیت (<code>send_status</code>)
          بارنامه را تغییر نمی‌دهد؛ فقط زمان تایید حضور را در ستون <code>origin_confirmed_at</code> یا
          <code>destination_confirmed_at</code> ثبت می‌کند. توکن یک‌بار مصرف است و بعد از فراخوانی موفق (یا
          شکست به‌دلیل ناسازگاری وضعیت)، دیگر قابل استفادهٔ دوباره نیست.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ================================================================= -->
<!-- =============== ۵) وب‌سرویس بارنامه‌های ایستگاه متصدی =============== -->
<!-- ================================================================= -->
<div class="api-service-panel <?= $service === 'active_waybill' ? '' : 'd-none' ?>" data-panel="op-active-waybill">

  <!-- ================= تست زنده ================= -->
  <div class="card panel-card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-jade fs-5" data-icon="solar:test-tube-bold"></span>
      <span class="fw-bold">تست زنده اتصال</span>
    </div>
    <div class="card-body p-4">

      <?php if (!$operatorsWithWaybills): ?>
        <div class="text-muted small">در حال حاضر هیچ متصدی‌ای بارنامهٔ تخصیص‌یافته ندارد.</div>
      <?php else: ?>
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label" for="opActiveOperatorSelect">متصدی واقعی برای تست</label>
            <select class="form-select" id="opActiveOperatorSelect">
              <?php foreach ($operatorsWithWaybills as $op): ?>
                <option value="<?= e((string)$op['id']) ?>">
                  متصدی <?= e($op['first_name'] . ' ' . $op['last_name']) ?> (<?= e($op['national_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
          <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="opActiveSendBtn">
            <span class="iconify fs-5" data-icon="solar:play-circle-bold"></span> دریافت توکن و ارسال درخواست
          </button>
          <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" id="opActiveClearBtn">
            <span class="iconify" data-icon="solar:eraser-bold"></span> پاک‌کردن نتیجه
          </button>
        </div>

        <div id="opActiveResultBox" class="mt-4 d-none">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-bold">توکن متصدی مورد استفاده:</span>
            <code class="ltr-code" id="opActiveTokenText"></code>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-bold">نتیجه:</span>
            <span class="badge rounded-pill" id="opActiveStatusBadge"></span>
            <span class="text-muted small" id="opActiveTimeBadge"></span>
          </div>
          <pre data-lang="json" class="api-response ltr-code mb-0" id="opActiveResponse"></pre>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ================= مستندات ================= -->
  <div class="card panel-card">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="iconify text-purple fs-5" data-icon="solar:book-2-bold"></span>
      <span class="fw-bold">مستندات وب‌سرویس بارنامه‌های ایستگاه متصدی</span>
    </div>
    <div class="card-body p-4">

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:link-circle-bold"></span> آدرس و متد
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <tbody>
            <tr>
              <th class="w-25">آدرس (Endpoint)</th>
              <td><code class="ltr-code" id="opActiveEndpointText"></code></td>
            </tr>
            <tr>
              <th>متد</th>
              <td><span class="badge role-badge role-admin">GET</span> یا <span class="badge role-badge role-admin">POST</span> (سایر متدها با کد <code>405</code> رد می‌شوند)</td>
            </tr>
            <tr>
              <th>نوع ورودی</th>
              <td><code>application/json</code>، <code>form-data</code> یا پارامتر GET</td>
            </tr>
            <tr>
              <th>خروجی</th>
              <td>همیشه JSON با ساختار ثابت <code class="ltr-code">{ success, message, waybills }</code></td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-add-bold"></span> پارامترهای ورودی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>token</code></td><td>رشته</td><td>بله</td>
              <td>توکن استاندارد <code>operator_waybills</code> (از وب‌سرویس ورود یا صفحه
                <code>operator_waybills_public.php</code>). متصدی از روی همین توکن شناسایی می‌شود.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:document-text-bold"></span> پارامترهای خروجی
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>نام</th><th>نوع</th><th>همیشه؟</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>success</code></td><td>boolean</td><td>بله</td>
              <td><code>true</code> در پاسخ موفق (حتی وقتی هیچ بارنامه‌ای وجود ندارد)، در خطا <code>false</code></td>
            </tr>
            <tr>
              <td><code>message</code></td><td>رشته</td><td>بله</td>
              <td>پیام فارسی نتیجه</td>
            </tr>
            <tr>
              <td><code>waybills</code></td><td>آرایه</td><td>بله</td>
              <td>فهرست بارنامه‌های تخصیص‌یافته به متصدی (مبدا یا مقصد)؛ اگر خالی باشد آرایهٔ تهی است</td>
            </tr>
            <tr>
              <td><code>waybills[].my_role</code></td><td>رشته</td><td>—</td>
              <td>نقش متصدی در همین بارنامه: <code>origin</code> یا <code>destination</code></td>
            </tr>
            <tr>
              <td><code>waybills[].confirmed_at</code></td><td>رشته (تاریخ‌ساعت) یا <code>null</code></td><td>—</td>
              <td>زمان تایید حضور متصدی برای همین بارنامه؛ اگر هنوز تایید نشده <code>null</code></td>
            </tr>
            <tr>
              <td><code>waybills[].driver</code></td><td>رشته یا <code>null</code></td><td>—</td>
              <td>نام کامل راننده؛ اگر تخصیص‌نیافته <code>null</code></td>
            </tr>
            <tr>
              <td><code>waybills[].seal_id</code></td><td>رشته یا <code>null</code></td><td>—</td>
              <td>شناسه پلمپ تخصیص‌یافته به بارنامه</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:server-square-bold"></span> کدهای وضعیت HTTP
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>کد</th><th>وضعیت</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr><td><span class="badge role-badge role-driver">200</span></td><td>موفق</td><td>پاسخ برگردانده شد؛ اگر بارنامه‌ای نباشد آرایهٔ <code>waybills</code> تهی خواهد بود</td></tr>
            <tr><td><span class="badge role-badge role-operator">401</span></td><td>عدم احراز</td><td>توکن نامعتبر است یا منقضی شده است</td></tr>
            <tr><td><span class="badge role-badge role-operator">403</span></td><td>عدم دسترسی</td><td>توکن متعلق به متصدی نیست یا حساب غیرفعال است</td></tr>
            <tr><td><span class="badge role-badge role-region">405</span></td><td>متد غیرمجاز</td><td>فقط GET یا POST پذیرفته می‌شود</td></tr>
            <tr><td><span class="badge role-badge role-operator">422</span></td><td>ورودی ناقص</td><td>توکن ارسال نشده است</td></tr>
            <tr><td><span class="badge role-badge role-admin">500</span></td><td>خطای سرور</td><td>خطای داخلی؛ جزئیات فنی هرگز افشا نمی‌شود</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:check-circle-bold"></span> نمونه پاسخ موفق (200)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "message": "بارنامه‌های ایستگاه با موفقیت دریافت شد.",
  "waybills": [
    {
      "id": 17,
      "waybill_number": "5",
      "issue_date": "2026-07-10",
      "issue_date_jalali": "1405/04/19",
      "distance_km": 82.5,
      "product_type": "نفتگاز",
      "send_status": "ارسال شده",
      "my_role": "origin",
      "origin_title": "انبار نفت مرکزی",
      "destination_title": "پایانه سوخت جنوب",
      "driver": "علی رضایی",
      "seal_id": "SL-1042",
      "confirmed_at": null
    }
  ]
}</pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="solar:command-bold"></span> نمونه فراخوانی با cURL
      </h2>
      <pre data-lang="curl" class="api-doc-code ltr-code" id="opActiveCurlSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:javascript"></span> نمونه فراخوانی با JavaScript (fetch)
      </h2>
      <pre data-lang="javascript" class="api-doc-code ltr-code" id="opActiveJsSample"></pre>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
        <span class="iconify text-jade" data-icon="logos:php"></span> نمونه فراخوانی با PHP (cURL)
      </h2>
      <pre data-lang="php" class="api-doc-code ltr-code" id="opActivePhpSample"></pre>

      <div class="alert alert-info d-flex align-items-start gap-2 mt-4 mb-0">
        <span class="iconify fs-5 mt-1" data-icon="solar:info-circle-bold"></span>
        <div>
          <strong>نکات:</strong> معادل <code>api/active_waybill.php</code> راننده است، با این تفاوت که چون
          متصدی می‌تواند هم‌زمان چند بارنامه (به‌عنوان متصدی مبدا و/یا مقصد) داشته باشد، این وب‌سرویس یک
          <strong>فهرست</strong> برمی‌گرداند نه یک بارنامهٔ تکی. این وب‌سرویس فقط خواندنی است و هیچ داده‌ای
          را تغییر نمی‌دهد.
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; // پایان بخش متصدی (role === 'operator') ?>

<script>
// آدرس کامل هر وب‌سرویس برای تست و نمونه‌های مستندات
window.API_LOGIN_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api/login.php') ?>;
window.API_TRIP_ACTION_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api/trip_action.php') ?>;
window.API_TEST_TRIP_TOKEN_URL = <?= json_encode(BASE_URL . '/api_test_trip_token.php') ?>;
window.API_ACTIVE_WAYBILL_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api/active_waybill.php') ?>;
window.API_SEAL_BY_WAYBILL_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api/seal_by_waybill.php') ?>;
window.API_TEST_DRIVER_TOKEN_URL = <?= json_encode(BASE_URL . '/api_test_driver_token.php') ?>;
window.API_GEOFENCE_CHECK_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/map/geofence/check.php') ?>;
window.API_OPERATOR_ACTION_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api/operator_action.php') ?>;
window.API_TEST_OPERATOR_ACTION_TOKEN_URL = <?= json_encode(BASE_URL . '/api_test_operator_action_token.php') ?>;
window.API_OPERATOR_WAYBILLS_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api/operator_waybills.php') ?>;
window.API_TEST_OPERATOR_TOKEN_URL = <?= json_encode(BASE_URL . '/api_test_operator_token.php') ?>;
</script>

<script>
(function(){
  function addLineNumbersTo(pre){
    if(!pre || pre.dataset.hasLineNums) return;
    pre.dataset.hasLineNums = '1';
    var codeText = pre.textContent || '';
    var lines = codeText.split('\n');
    var wrapper = document.createElement('div');
    wrapper.className = 'code-with-lines';
    var gutter = document.createElement('div');
    gutter.className = 'line-numbers';
    var ol = document.createElement('ol');
    for(var i=1;i<=lines.length;i++){ var li = document.createElement('li'); li.textContent = i; ol.appendChild(li); }
    gutter.appendChild(ol);
    // move pre into wrapper after gutter
    var parent = pre.parentNode;
    parent.insertBefore(wrapper, pre);
    wrapper.appendChild(gutter);
    wrapper.appendChild(pre);
    // ensure monospace
    pre.style.fontFamily = "Consolas, 'Courier New', monospace";
    // observe changes to update line numbers
    var mo = new MutationObserver(function(){
      var newLines = (pre.textContent || '').split('\n').length;
      if(ol.children.length !== newLines){
        ol.innerHTML = '';
        for(var j=1;j<=newLines;j++){ var li2=document.createElement('li'); li2.textContent=j; ol.appendChild(li2); }
      }
    });
    mo.observe(pre, {characterData:true, childList:true, subtree:true});
  }
  document.addEventListener('DOMContentLoaded', function(){
    var pres = document.querySelectorAll('pre.ltr-code, pre.api-response, pre.api-doc-code');
    pres.forEach(addLineNumbersTo);
    // observe new pre elements dynamically
    var bodyMo = new MutationObserver(function(muts){
      muts.forEach(function(m){
        m.addedNodes.forEach(function(node){
          if(node.nodeType===1){
            if(node.matches && node.matches('pre.ltr-code, pre.api-response, pre.api-doc-code')) addLineNumbersTo(node);
            var inners = node.querySelectorAll && node.querySelectorAll('pre.ltr-code, pre.api-response, pre.api-doc-code');
            inners && inners.forEach(addLineNumbersTo);
          }
        });
      });
    });
    bodyMo.observe(document.body, {childList:true, subtree:true});
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
