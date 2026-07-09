/**
 * اسکریپت‌های سمت کلاینت:
 * - منوی کناری موبایل
 * - نمایش/مخفی‌کردن رمز
 * - اعتبارسنجی کد ملی و تطابق رمز در سمت کلاینت
 */
(function () {
  'use strict';

  /* ---------- منوی کناری موبایل ---------- */
  var toggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('sidebar');
  var backdrop = document.getElementById('sidebarBackdrop');

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('show');
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (backdrop) backdrop.classList.toggle('show');
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeSidebar);

  /* ---------- نمایش/مخفی‌کردن رمز ---------- */
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

  /* ---------- ابزارهای اعتبارسنجی ---------- */

  // تبدیل ارقام فارسی/عربی به انگلیسی
  function normalizeDigits(value) {
    var fa = '۰۱۲۳۴۵۶۷۸۹', ar = '٠١٢٣٤٥٦٧٨٩';
    return value.replace(/[۰-۹٠-٩]/g, function (ch) {
      var i = fa.indexOf(ch);
      if (i > -1) return String(i);
      i = ar.indexOf(ch);
      return i > -1 ? String(i) : ch;
    }).trim();
  }

  // اعتبارسنجی کد ملی ایران
  function isValidNationalCode(code) {
    code = normalizeDigits(code);
    if (!/^\d{10}$/.test(code)) return false;
    if (/^(\d)\1{9}$/.test(code)) return false;
    var sum = 0;
    for (var i = 0; i < 9; i++) sum += Number(code[i]) * (10 - i);
    var r = sum % 11, check = Number(code[9]);
    return (r < 2 && check === r) || (r >= 2 && check === 11 - r);
  }

  function setError(name, message) {
    var box = document.querySelector('[data-error-for="' + name + '"]');
    if (box) box.textContent = message || '';
    var input = document.getElementById(name);
    if (input) input.classList.toggle('is-invalid', Boolean(message));
  }

  /* ---------- اعتبارسنجی زندهٔ کد ملی ---------- */
  var ncInput = document.querySelector('[data-validate="national-code"]');
  if (ncInput) {
    ncInput.addEventListener('input', function () {
      ncInput.value = normalizeDigits(ncInput.value).replace(/\D/g, '').slice(0, 10);
      if (ncInput.value.length === 10) {
        setError('national_code', isValidNationalCode(ncInput.value) ? '' : 'کد ملی وارد شده معتبر نیست.');
      } else {
        setError('national_code', '');
      }
    });
  }

  /* ---------- اعتبارسنجی فرم‌ها قبل از ارسال ---------- */
  function validatePasswordPair(form) {
    var pass = form.querySelector('#password');
    var confirm = form.querySelector('#password_confirm');
    var ok = true;
    if (pass && pass.value.length < 6) {
      pass.classList.add('is-invalid');
      ok = false;
    } else if (pass) {
      pass.classList.remove('is-invalid');
    }
    if (pass && confirm) {
      if (pass.value !== confirm.value) {
        setError('password_confirm', 'رمز عبور و تکرار آن یکسان نیستند.');
        ok = false;
      } else {
        setError('password_confirm', '');
      }
    }
    return ok;
  }

  var createForm = document.getElementById('createUserForm');
  if (createForm) {
    createForm.addEventListener('submit', function (e) {
      var ok = true;
      if (!isValidNationalCode(createForm.national_code.value)) {
        setError('national_code', 'کد ملی وارد شده معتبر نیست.');
        ok = false;
      }
      if (!validatePasswordPair(createForm)) ok = false;
      if (!createForm.checkValidity()) ok = false;
      if (!ok) {
        e.preventDefault();
        createForm.classList.add('was-validated');
      }
    });
  }

  var resetForm = document.getElementById('resetPasswordForm');
  if (resetForm) {
    resetForm.addEventListener('submit', function (e) {
      if (!validatePasswordPair(resetForm) || !resetForm.checkValidity()) {
        e.preventDefault();
        resetForm.classList.add('was-validated');
      }
    });
  }

  var loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      var u = loginForm.username, p = loginForm.password;
      u.value = normalizeDigits(u.value);
      if (!u.value || !p.value) {
        e.preventDefault();
        loginForm.classList.add('was-validated');
      }
    });
  }
})();

/* ---------- صفحه تست وب‌سرویس ---------- */
(function () {
  'use strict';
  var sendBtn = document.getElementById('apiSendBtn');
  if (!sendBtn) return; // فقط در صفحه تست اجرا شود

  var apiUrl = window.API_LOGIN_URL;
  if (!apiUrl) {
    // اگر به هر دلیلی مقدار سرور تنظیم نشد، از آدرس جاری صفحه بسازیم
    apiUrl = window.location.origin + window.location.pathname.replace(/api_test\.php$/, 'api/login.php');
  }

  // نمایش آدرس و نمونه‌کدهای مستندات با آدرس واقعی سرور
  var endpointText = document.getElementById('apiEndpointText');
  if (endpointText) endpointText.textContent = 'POST ' + apiUrl;

  var curlSample = document.getElementById('curlSample');
  if (curlSample) curlSample.textContent =
    'curl -X POST "' + apiUrl + '" \\\n' +
    '  -H "Content-Type: application/json" \\\n' +
    '  -d \'{"username": "1111111111", "password": "Admin@123"}\'';

  var jsSample = document.getElementById('jsSample');
  if (jsSample) jsSample.textContent =
    'const res = await fetch("' + apiUrl + '", {\n' +
    '  method: "POST",\n' +
    '  headers: { "Content-Type": "application/json" },\n' +
    '  body: JSON.stringify({ username: "1111111111", password: "Admin@123" })\n' +
    '});\n' +
    'const data = await res.json();\n' +
    'if (data.success) {\n' +
    '  console.log("ورود موفق:", data.user);\n' +
    '} else {\n' +
    '  console.log("خطا:", data.message);\n' +
    '}';

  var phpSample = document.getElementById('phpSample');
  if (phpSample) phpSample.textContent =
    '$ch = curl_init("' + apiUrl + '");\n' +
    'curl_setopt($ch, CURLOPT_POST, true);\n' +
    'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\n' +
    'curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);\n' +
    'curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([\n' +
    '    "username" => "1111111111",\n' +
    '    "password" => "Admin@123",\n' +
    ']));\n' +
    '$response = curl_exec($ch);\n' +
    '$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);\n' +
    'curl_close($ch);\n' +
    '$data = json_decode($response, true);';

  var resultBox   = document.getElementById('apiResultBox');
  var statusBadge = document.getElementById('apiStatusBadge');
  var timeBadge   = document.getElementById('apiTimeBadge');
  var responseEl  = document.getElementById('apiResponse');
  var usernameEl  = document.getElementById('apiUsername');
  var passwordEl  = document.getElementById('apiPassword');
  var formatEl    = document.getElementById('apiFormat');
  var wrongBtn    = document.getElementById('apiWrongBtn');
  var clearBtn    = document.getElementById('apiClearBtn');

  function showResult(status, body, ms, isNetworkError) {
    if (!resultBox || !statusBadge || !timeBadge || !responseEl) return;
    resultBox.classList.remove('d-none');
    statusBadge.className = 'badge rounded-pill ' +
      (isNetworkError ? 'badge-status-err'
        : status >= 200 && status < 300 ? 'badge-status-ok'
        : status >= 400 && status < 500 ? 'badge-status-warn'
        : 'badge-status-err');
    statusBadge.textContent = isNetworkError ? 'خطای اتصال' : ('HTTP ' + status);
    timeBadge.textContent = 'زمان پاسخ: ' + ms + ' میلی‌ثانیه';
    responseEl.textContent = body;
  }

  function callApi(username, password) {
    var format = formatEl ? formatEl.value : 'json';
    var options = { method: 'POST' };

    if (format === 'json') {
      options.headers = { 'Content-Type': 'application/json' };
      options.body = JSON.stringify({ username: username, password: password });
    } else {
      var fd = new FormData();
      fd.append('username', username);
      fd.append('password', password);
      options.body = fd;
    }

    sendBtn.disabled = true;
    if (wrongBtn) wrongBtn.disabled = true;
    var start = (window.performance && performance.now) ? performance.now() : Date.now();

    fetch(apiUrl, options)
      .then(function (res) {
        return res.text().then(function (text) {
          var pretty = text;
          try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
          var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
          showResult(res.status, pretty, elapsed, false);
          sendBtn.disabled = false;
          if (wrongBtn) wrongBtn.disabled = false;
        });
      })
      .catch(function (err) {
        var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
        showResult(0, 'اتصال به سرور برقرار نشد. از روشن‌بودن Apache و درست‌بودن آدرس مطمئن شوید.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
        sendBtn.disabled = false;
        if (wrongBtn) wrongBtn.disabled = false;
      });
  }

  sendBtn.addEventListener('click', function () {
    var u = usernameEl ? usernameEl.value.trim() : '';
    var p = passwordEl ? passwordEl.value : '';
    if (!u || !p) {
      showResult(0, 'نام کاربری و رمز عبور را برای تست وارد کنید.', 0, true);
      return;
    }
    callApi(u, p);
  });

  if (wrongBtn) {
    wrongBtn.addEventListener('click', function () {
      var u = (usernameEl && usernameEl.value.trim()) || '1111111111';
      callApi(u, 'wrong-password-123');
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (resultBox) resultBox.classList.add('d-none');
      if (responseEl) responseEl.textContent = '';
    });
  }
})();

/* ---------- تقویم شمسی برای فیلدهای تاریخ ---------- */
(function () {
  'use strict';
  if (!window.jQuery || !jQuery.fn || !jQuery.fn.persianDatepicker) return;

  jQuery('[data-jalali-datepicker]').each(function () {
    jQuery(this).persianDatepicker({
      format: 'YYYY/MM/DD',
      autoClose: true,
      initialValue: false,
      observer: true,
      toolbox: {
        calendarSwitch: { enabled: false }
      }
    });
  });
})();

/* ---------- راهنمای انتخاب مبدا/مقصد برای کاربر منطقه (فقط هشدار بصری، نه اعتبارسنجی نهایی) ---------- */
(function () {
  'use strict';
  var myRegionInput = document.getElementById('my_region_id');
  var originSel = document.getElementById('origin_location_id');
  var destSel = document.getElementById('destination_location_id');
  if (!myRegionInput || !originSel || !destSel) return;

  var myRegion = myRegionInput.value;

  function checkRegionMatch() {
    var originRegion = originSel.selectedOptions[0] ? originSel.selectedOptions[0].getAttribute('data-region') : '';
    var destRegion = destSel.selectedOptions[0] ? destSel.selectedOptions[0].getAttribute('data-region') : '';
    var ok = !originRegion && !destRegion ? true : (originRegion === myRegion || destRegion === myRegion);

    var existingWarning = document.getElementById('regionMismatchWarning');
    if (!ok) {
      if (!existingWarning) {
        var div = document.createElement('div');
        div.id = 'regionMismatchWarning';
        div.className = 'alert alert-warning d-flex align-items-center gap-2 mt-3';
        div.innerHTML = '<span class="iconify" data-icon="solar:danger-triangle-bold"></span> مبدا یا مقصد انتخاب‌شده باید در منطقه شما باشد.';
        destSel.closest('form').insertBefore(div, destSel.closest('form').querySelector('.d-flex.gap-2.mt-4'));
      }
    } else if (existingWarning) {
      existingWarning.remove();
    }
  }

  originSel.addEventListener('change', checkRegionMatch);
  destSel.addEventListener('change', checkRegionMatch);
})();

/* ---------- نمایش/مخفی‌کردن فیلد منطقه بر اساس نقش کاربر در فرم ایجاد/ویرایش کاربر ---------- */
(function () {
  'use strict';
  var userTypeSel = document.getElementById('user_type');
  var regionWrapper = document.getElementById('regionFieldWrapper');
  if (!userTypeSel || !regionWrapper) return;

  function toggleRegionField() {
    var show = userTypeSel.value === 'region';
    regionWrapper.style.display = show ? '' : 'none';
    var regionSelect = document.getElementById('region_id');
    if (regionSelect) {
      regionSelect.required = show;
      if (!show) regionSelect.value = '';
    }
  }

  userTypeSel.addEventListener('change', toggleRegionField);
  toggleRegionField();
})();
