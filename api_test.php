<?php
/**
 * صفحه تست وب‌سرویس: تست زنده API ورود + مستندات کامل استفاده
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';

require_admin();

$page_title = 'تست وب‌سرویس';
$active = 'api';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">تست وب‌سرویس ورود</h1>
  <p class="text-muted small mb-0">اتصال به API را همین‌جا آزمایش کنید و پاسخ خام سرور را ببینید.</p>
</div>

<?= render_flash() ?>

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
      <pre class="api-response ltr-code mb-0" id="apiResponse"></pre>
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
    <pre class="api-doc-code ltr-code">{
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
    <pre class="api-doc-code ltr-code">{
  "success": false,
  "message": "نام کاربری یا رمز عبور نادرست است."
}</pre>

    <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
      <span class="iconify text-jade" data-icon="solar:command-bold"></span> نمونه فراخوانی با cURL
    </h2>
    <pre class="api-doc-code ltr-code" id="curlSample"></pre>

    <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
      <span class="iconify text-jade" data-icon="logos:javascript"></span> نمونه فراخوانی با JavaScript (fetch)
    </h2>
    <pre class="api-doc-code ltr-code" id="jsSample"></pre>

    <h2 class="h6 fw-bold d-flex align-items-center gap-2 mt-4">
      <span class="iconify text-jade" data-icon="logos:php"></span> نمونه فراخوانی با PHP (cURL)
    </h2>
    <pre class="api-doc-code ltr-code" id="phpSample"></pre>

    <div class="alert alert-info d-flex align-items-start gap-2 mt-4 mb-0">
      <span class="iconify fs-5 mt-1" data-icon="solar:info-circle-bold"></span>
      <div>
        <strong>نکات:</strong> این وب‌سرویس فقط اعتبار کاربر را بررسی می‌کند و سشن نمی‌سازد؛ برای اپلیکیشن موبایل یا سامانه‌های دیگر مناسب است.
        هش رمز عبور هرگز در پاسخ برگردانده نمی‌شود و همه پیام‌ها فارسی و امن هستند.
      </div>
    </div>
  </div>
</div>

<script>
// آدرس کامل API برای تست و نمونه‌های مستندات
window.API_LOGIN_URL = <?= json_encode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api/login.php') ?>;
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
