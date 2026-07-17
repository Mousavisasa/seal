<?php
/**
 * صفحه تست وب‌سرویس‌ها: تست زنده + مستندات کامل استفاده
 * شامل سه زیرصفحه که از بالای صفحه قابل انتخاب هستند:
 *  ۱) وب‌سرویس ورود (api/login.php)
 *  ۲) وب‌سرویس شروع/پایان سفر (api/trip_action.php)
 *  ۳) وب‌سرویس بارنامه فعال (api/active_waybill.php)
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

// رانندگانی که همین الان یک بارنامه فعال («ارسال شده») دارند — برای تست زنده
// وب‌سرویس بارنامه فعال؛ همان بارنامه‌های $endableWaybills هستند (چون «فعال»
// دقیقاً یعنی وضعیت «ارسال شده»)، فقط بدون تکرار راننده
$activeDrivers = [];
$seenDriverIds = [];
foreach ($endableWaybills as $row) {
    if (!in_array((int)$row['driver_user_id'], $seenDriverIds, true)) {
        $seenDriverIds[] = (int)$row['driver_user_id'];
        $activeDrivers[] = $row;
    }
}

// انتخاب زیرصفحه از URL (?service=login|trip|active) — یک ری‌لود واقعی صفحه،
// نه فقط جابه‌جایی نمایشی با جاوااسکریپت، تا همیشه و بدون وابستگی به اجرای
// اسکریپت سمت مرورگر کار کند.
$serviceParam = (string)($_GET['service'] ?? '');
$service = in_array($serviceParam, ['trip', 'active'], true) ? $serviceParam : 'login';

$page_title = 'مستندات و تست وب‌سرویس‌ها';
$active = 'api';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">مستندات و تست وب‌سرویس‌ها</h1>
  <p class="text-muted small mb-0">اتصال به هر وب‌سرویس را همین‌جا آزمایش کنید و مستندات کامل آن را ببینید.</p>
</div>

<?= render_flash() ?>

<!-- ================= انتخاب وب‌سرویس ================= -->
<ul class="nav nav-pills gap-2 mb-4" id="apiServiceTabs">
  <li class="nav-item">
    <a href="?service=login" class="nav-link <?= $service === 'login' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:login-3-bold"></span> وب‌سرویس ورود
    </a>
  </li>
  <li class="nav-item">
    <a href="?service=trip" class="nav-link <?= $service === 'trip' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:routing-2-bold"></span> وب‌سرویس شروع/پایان سفر
    </a>
  </li>
  <li class="nav-item">
    <a href="?service=active" class="nav-link <?= $service === 'active' ? 'active' : '' ?>">
      <span class="iconify" data-icon="solar:document-text-bold"></span> وب‌سرویس بارنامه فعال
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
<!-- ==================== ۳) وب‌سرویس بارنامه فعال ==================== -->
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
        <div class="text-muted small">در حال حاضر هیچ راننده‌ای بارنامه فعال («ارسال شده») ندارد.</div>
      <?php else: ?>
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label" for="activeDriverSelect">راننده واقعی برای تست</label>
            <select class="form-select" id="activeDriverSelect">
              <?php foreach ($activeDrivers as $d): ?>
                <option value="<?= e((string)$d['driver_user_id']) ?>">
                  راننده <?= e($d['first_name'] . ' ' . $d['last_name']) ?> (<?= e($d['national_code']) ?>) — بارنامه فعال: <?= e($d['waybill_number']) ?>
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
      <span class="fw-bold">مستندات وب‌سرویس بارنامه فعال</span>
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
        <span class="iconify text-jade" data-icon="solar:server-square-bold"></span> کدهای وضعیت HTTP
      </h2>
      <div class="table-responsive mb-4">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>کد</th><th>وضعیت</th><th>توضیح</th></tr>
          </thead>
          <tbody>
            <tr><td><span class="badge role-badge role-driver">200</span></td><td>موفق</td><td>پاسخ برگردانده شد؛ اگر بارنامه فعالی نباشد باز هم <code>200</code> است و <code>waybill</code> برابر <code>null</code> خواهد بود</td></tr>
            <tr><td><span class="badge role-badge role-operator">401</span></td><td>عدم احراز</td><td>توکن نامعتبر است یا منقضی شده است</td></tr>
            <tr><td><span class="badge role-badge role-operator">403</span></td><td>عدم دسترسی</td><td>توکن متعلق به راننده نیست یا حساب غیرفعال است</td></tr>
            <tr><td><span class="badge role-badge role-region">405</span></td><td>متد غیرمجاز</td><td>فقط GET یا POST پذیرفته می‌شود</td></tr>
            <tr><td><span class="badge role-badge role-operator">422</span></td><td>ورودی ناقص</td><td>توکن ارسال نشده است</td></tr>
            <tr><td><span class="badge role-badge role-admin">500</span></td><td>خطای سرور</td><td>خطای داخلی؛ جزئیات فنی هرگز افشا نمی‌شود</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="h6 fw-bold d-flex align-items-center gap-2">
        <span class="iconify text-jade" data-icon="solar:check-circle-bold"></span> نمونه پاسخ موفق — بارنامه فعال وجود دارد (200)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "message": "بارنامه فعال با موفقیت دریافت شد.",
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
        <span class="iconify text-jade" data-icon="solar:info-circle-bold"></span> نمونه پاسخ موفق — بارنامه فعالی وجود ندارد (200)
      </h2>
      <pre data-lang="json" class="api-doc-code ltr-code">{
  "success": true,
  "message": "در حال حاضر هیچ بارنامه فعالی برای شما وجود ندارد.",
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
          <strong>نکات:</strong> «فعال» یعنی بارنامه‌ای که سفرش شروع شده اما هنوز پایان نیافته
          (<code>send_status = 'ارسال شده'</code>). این وب‌سرویس فقط خواندنی است و هیچ داده‌ای را
          تغییر نمی‌دهد؛ می‌توان آن را هر چند بار که لازم است (مثلاً هر چند ثانیه از اپلیکیشن
          موبایل راننده) فراخوانی کرد.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// آدرس کامل هر وب‌سرویس برای تست و نمونه‌های مستندات
window.API_LOGIN_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api/login.php') ?>;
window.API_TRIP_ACTION_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api/trip_action.php') ?>;
window.API_TEST_TRIP_TOKEN_URL = <?= json_encode(BASE_URL . '/api_test_trip_token.php') ?>;
window.API_ACTIVE_WAYBILL_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api/active_waybill.php') ?>;
window.API_TEST_DRIVER_TOKEN_URL = <?= json_encode(BASE_URL . '/api_test_driver_token.php') ?>;
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
