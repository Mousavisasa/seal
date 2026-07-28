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

  /* ---------- راهنمای ابزار (Tooltip) بخش‌های فرم ---------- */
  if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      new window.bootstrap.Tooltip(el);
    });
  }

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

/* ---------- صفحه تست وب‌سرویس شروع/پایان سفر ---------- */
(function () {
  'use strict';
  var tripSendBtn = document.getElementById('tripSendBtn');
  var tripApiUrl = window.API_TRIP_ACTION_URL;
  var mintTokenUrl = window.API_TEST_TRIP_TOKEN_URL;

  // نمایش آدرس و نمونه‌کدهای مستندات با آدرس واقعی سرور (این بخش صرف‌نظر از
  // اینکه بارنامه‌ای برای تست زنده موجود باشد یا نه، همیشه اجرا می‌شود)
  var tripEndpointText = document.getElementById('tripEndpointText');
  if (tripEndpointText && tripApiUrl) tripEndpointText.textContent = 'POST ' + tripApiUrl;

  var tripCurlSample = document.getElementById('tripCurlSample');
  if (tripCurlSample && tripApiUrl) tripCurlSample.textContent =
    'curl -X POST "' + tripApiUrl + '" \\\n' +
    '  -H "Content-Type: application/x-www-form-urlencoded" \\\n' +
    '  --data-urlencode "token=<TOKEN>"';

  var tripJsSample = document.getElementById('tripJsSample');
  if (tripJsSample && tripApiUrl) tripJsSample.textContent =
    'const res = await fetch("' + tripApiUrl + '", {\n' +
    '  method: "POST",\n' +
    '  headers: { "Content-Type": "application/x-www-form-urlencoded" },\n' +
    '  body: "token=" + encodeURIComponent(token)\n' +
    '});\n' +
    'const data = await res.json();\n' +
    'if (data.success) {\n' +
    '  console.log("ثبت شد:", data.message);\n' +
    '} else {\n' +
    '  console.log("خطا:", data.message);\n' +
    '}';

  var tripPhpSample = document.getElementById('tripPhpSample');
  if (tripPhpSample && tripApiUrl) tripPhpSample.textContent =
    '$ch = curl_init("' + tripApiUrl + '");\n' +
    'curl_setopt($ch, CURLOPT_POST, true);\n' +
    'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\n' +
    'curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([\n' +
    '    "token" => $token,\n' +
    ']));\n' +
    '$response = curl_exec($ch);\n' +
    '$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);\n' +
    'curl_close($ch);\n' +
    '$data = json_decode($response, true);';

  if (!tripSendBtn) return; // بارنامه‌ای برای تست زنده وجود ندارد یا در این زیرصفحه نیستیم

  var waybillSelect = document.getElementById('tripWaybillSelect');
  var tripClearBtn  = document.getElementById('tripClearBtn');
  var tripResultBox = document.getElementById('tripResultBox');
  var tripTokenText = document.getElementById('tripTokenText');
  var tripStatusBadge = document.getElementById('tripStatusBadge');
  var tripTimeBadge = document.getElementById('tripTimeBadge');
  var tripResponseEl = document.getElementById('tripResponse');

  function showTripResult(status, body, ms, isNetworkError) {
    if (!tripResultBox || !tripStatusBadge || !tripTimeBadge || !tripResponseEl) return;
    tripResultBox.classList.remove('d-none');
    tripStatusBadge.className = 'badge rounded-pill ' +
      (isNetworkError ? 'badge-status-err'
        : status >= 200 && status < 300 ? 'badge-status-ok'
        : status >= 400 && status < 500 ? 'badge-status-warn'
        : 'badge-status-err');
    tripStatusBadge.textContent = isNetworkError ? 'خطای اتصال' : ('HTTP ' + status);
    tripTimeBadge.textContent = 'زمان پاسخ: ' + ms + ' میلی‌ثانیه';
    tripResponseEl.textContent = body;
  }

  tripSendBtn.addEventListener('click', function () {
    var opt = waybillSelect.options[waybillSelect.selectedIndex];
    if (!opt) return;
    var waybillId = opt.value;
    var action = opt.getAttribute('data-action');

    tripSendBtn.disabled = true;
    if (tripTokenText) tripTokenText.textContent = 'در حال ساخت توکن…';
    var start = (window.performance && performance.now) ? performance.now() : Date.now();

    fetch(mintTokenUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'waybill_id=' + encodeURIComponent(waybillId) + '&action=' + encodeURIComponent(action)
    })
      .then(function (r) { return r.json(); })
      .then(function (mintData) {
        if (!mintData.success) {
          if (tripTokenText) tripTokenText.textContent = '—';
          var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
          showTripResult(0, 'خطا در ساخت توکن آزمایشی: ' + mintData.message, elapsed, true);
          tripSendBtn.disabled = false;
          return;
        }

        if (tripTokenText) tripTokenText.textContent = mintData.token;

        fetch(tripApiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'token=' + encodeURIComponent(mintData.token)
        })
          .then(function (res) {
            return res.text().then(function (text) {
              var pretty = text;
              try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
              var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
              showTripResult(res.status, pretty, elapsed, false);
              tripSendBtn.disabled = false;
            });
          })
          .catch(function (err) {
            var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
            showTripResult(0, 'اتصال به سرور برقرار نشد.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
            tripSendBtn.disabled = false;
          });
      })
      .catch(function (err) {
        var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
        showTripResult(0, 'اتصال به سرور برقرار نشد.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
        tripSendBtn.disabled = false;
      });
  });

  if (tripClearBtn) {
    tripClearBtn.addEventListener('click', function () {
      if (tripResultBox) tripResultBox.classList.add('d-none');
      if (tripResponseEl) tripResponseEl.textContent = '';
      if (tripTokenText) tripTokenText.textContent = '';
    });
  }
})();

/* ---------- صفحه تست وب‌سرویس بارنامه ی انتخاب شده ---------- */
(function () {
  'use strict';
  var activeSendBtn = document.getElementById('activeSendBtn');
  var activeApiUrl = window.API_ACTIVE_WAYBILL_URL;
  var mintDriverTokenUrl = window.API_TEST_DRIVER_TOKEN_URL;

  // نمایش آدرس و نمونه‌کدهای مستندات با آدرس واقعی سرور (صرف‌نظر از اینکه
  // راننده‌ای برای تست زنده موجود باشد یا نه، همیشه اجرا می‌شود)
  var activeEndpointText = document.getElementById('activeEndpointText');
  if (activeEndpointText && activeApiUrl) activeEndpointText.textContent = 'GET ' + activeApiUrl + '?token=...';

  var activeCurlSample = document.getElementById('activeCurlSample');
  if (activeCurlSample && activeApiUrl) activeCurlSample.textContent =
    'curl -X GET "' + activeApiUrl + '?token=<TOKEN>"';

  var activeJsSample = document.getElementById('activeJsSample');
  if (activeJsSample && activeApiUrl) activeJsSample.textContent =
    'const res = await fetch("' + activeApiUrl + '?token=" + encodeURIComponent(token));\n' +
    'const data = await res.json();\n' +
    'if (data.success && data.waybill) {\n' +
    '  console.log("بارنامه ی انتخاب شده:", data.waybill);\n' +
    '} else if (data.success) {\n' +
    '  console.log("بارنامه ی انتخاب شدهی وجود ندارد.");\n' +
    '} else {\n' +
    '  console.log("خطا:", data.message);\n' +
    '}';

  var activePhpSample = document.getElementById('activePhpSample');
  if (activePhpSample && activeApiUrl) activePhpSample.textContent =
    '$ch = curl_init("' + activeApiUrl + '?token=" . urlencode($token));\n' +
    'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\n' +
    '$response = curl_exec($ch);\n' +
    '$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);\n' +
    'curl_close($ch);\n' +
    '$data = json_decode($response, true);';

  if (!activeSendBtn) return; // راننده‌ای برای تست زنده وجود ندارد یا در این زیرصفحه نیستیم

  var driverSelect = document.getElementById('activeDriverSelect');
  var activeClearBtn = document.getElementById('activeClearBtn');
  var activeResultBox = document.getElementById('activeResultBox');
  var activeTokenText = document.getElementById('activeTokenText');
  var activeStatusBadge = document.getElementById('activeStatusBadge');
  var activeTimeBadge = document.getElementById('activeTimeBadge');
  var activeResponseEl = document.getElementById('activeResponse');

  function showActiveResult(status, body, ms, isNetworkError) {
    if (!activeResultBox || !activeStatusBadge || !activeTimeBadge || !activeResponseEl) return;
    activeResultBox.classList.remove('d-none');
    activeStatusBadge.className = 'badge rounded-pill ' +
      (isNetworkError ? 'badge-status-err'
        : status >= 200 && status < 300 ? 'badge-status-ok'
        : status >= 400 && status < 500 ? 'badge-status-warn'
        : 'badge-status-err');
    activeStatusBadge.textContent = isNetworkError ? 'خطای اتصال' : ('HTTP ' + status);
    activeTimeBadge.textContent = 'زمان پاسخ: ' + ms + ' میلی‌ثانیه';
    activeResponseEl.textContent = body;
  }

  activeSendBtn.addEventListener('click', function () {
    var driverId = driverSelect.value;
    if (!driverId) return;

    activeSendBtn.disabled = true;
    if (activeTokenText) activeTokenText.textContent = 'در حال ساخت توکن…';
    var start = (window.performance && performance.now) ? performance.now() : Date.now();

    fetch(mintDriverTokenUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'driver_id=' + encodeURIComponent(driverId)
    })
      .then(function (r) { return r.json(); })
      .then(function (mintData) {
        if (!mintData.success) {
          if (activeTokenText) activeTokenText.textContent = '—';
          var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
          showActiveResult(0, 'خطا در ساخت توکن آزمایشی: ' + mintData.message, elapsed, true);
          activeSendBtn.disabled = false;
          return;
        }

        if (activeTokenText) activeTokenText.textContent = mintData.token;

        fetch(activeApiUrl + '?token=' + encodeURIComponent(mintData.token))
          .then(function (res) {
            return res.text().then(function (text) {
              var pretty = text;
              try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
              var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
              showActiveResult(res.status, pretty, elapsed, false);
              activeSendBtn.disabled = false;
            });
          })
          .catch(function (err) {
            var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
            showActiveResult(0, 'اتصال به سرور برقرار نشد.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
            activeSendBtn.disabled = false;
          });
      })
      .catch(function (err) {
        var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
        showActiveResult(0, 'اتصال به سرور برقرار نشد.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
        activeSendBtn.disabled = false;
      });
  });

  if (activeClearBtn) {
    activeClearBtn.addEventListener('click', function () {
      if (activeResultBox) activeResultBox.classList.add('d-none');
      if (activeResponseEl) activeResponseEl.textContent = '';
      if (activeTokenText) activeTokenText.textContent = '';
    });
  }
})();

/* ---------- صفحه تست وب‌سرویس پلمپ بر اساس شماره بارنامه ---------- */
(function () {
  'use strict';
  var sealSendBtn = document.getElementById('sealSendBtn');
  var sealApiUrl = window.API_SEAL_BY_WAYBILL_URL;
  var mintDriverTokenUrl = window.API_TEST_DRIVER_TOKEN_URL;
  var mintOperatorTokenUrl = window.API_TEST_OPERATOR_TOKEN_URL;

  // نمایش آدرس و نمونه‌کدهای مستندات با آدرس واقعی سرور (صرف‌نظر از اینکه
  // راننده‌ای برای تست زنده موجود باشد یا نه، همیشه اجرا می‌شود)
  var sealEndpointText = document.getElementById('sealEndpointText');
  if (sealEndpointText && sealApiUrl) sealEndpointText.textContent = 'GET ' + sealApiUrl + '?token=...&waybill_number=...';

  var sealCurlSample = document.getElementById('sealCurlSample');
  if (sealCurlSample && sealApiUrl) sealCurlSample.textContent =
    'curl -X GET "' + sealApiUrl + '?token=<TOKEN>&waybill_number=<WAYBILL_NUMBER>"';

  var sealJsSample = document.getElementById('sealJsSample');
  if (sealJsSample && sealApiUrl) sealJsSample.textContent =
    'const url = new URL("' + sealApiUrl + '");\n' +
    'url.searchParams.set("token", token);\n' +
    'url.searchParams.set("waybill_number", waybillNumber);\n' +
    'const res = await fetch(url);\n' +
    'const data = await res.json();\n' +
    'if (data.success) {\n' +
    '  console.log("اطلاعات پلمپ:", data.seal);\n' +
    '} else {\n' +
    '  console.log("خطا:", data.message);\n' +
    '}';

  var sealPhpSample = document.getElementById('sealPhpSample');
  if (sealPhpSample && sealApiUrl) sealPhpSample.textContent =
    '$url = "' + sealApiUrl + '?token=" . urlencode($token) . "&waybill_number=" . urlencode($waybillNumber);\n' +
    '$ch = curl_init($url);\n' +
    'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\n' +
    '$response = curl_exec($ch);\n' +
    '$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);\n' +
    'curl_close($ch);\n' +
    '$data = json_decode($response, true);';

  if (!sealSendBtn) return; // راننده/متصدی فعالی برای تست زنده وجود ندارد یا در این زیرصفحه نیستیم

  var sealRoleRadios = document.getElementsByName('sealRoleRadio');
  var sealDriverSelect = document.getElementById('sealDriverSelect');
  var sealOperatorSelect = document.getElementById('sealOperatorSelect');
  var sealDriverSelectWrap = document.getElementById('sealDriverSelectWrap');
  var sealOperatorSelectWrap = document.getElementById('sealOperatorSelectWrap');
  var sealWaybillNumberInput = document.getElementById('sealWaybillNumberInput');
  var sealClearBtn = document.getElementById('sealClearBtn');
  var sealResultBox = document.getElementById('sealResultBox');
  var sealTokenLabel = document.getElementById('sealTokenLabel');
  var sealTokenText = document.getElementById('sealTokenText');
  var sealStatusBadge = document.getElementById('sealStatusBadge');
  var sealTimeBadge = document.getElementById('sealTimeBadge');
  var sealResponseEl = document.getElementById('sealResponse');

  function getSealRole() {
    for (var i = 0; i < sealRoleRadios.length; i++) {
      if (sealRoleRadios[i].checked) return sealRoleRadios[i].value;
    }
    return 'driver';
  }

  function updateSealRoleUi() {
    var role = getSealRole();
    if (sealDriverSelectWrap) sealDriverSelectWrap.style.display = role === 'driver' ? '' : 'none';
    if (sealOperatorSelectWrap) sealOperatorSelectWrap.style.display = role === 'operator' ? '' : 'none';
  }

  for (var ri = 0; ri < sealRoleRadios.length; ri++) {
    sealRoleRadios[ri].addEventListener('change', updateSealRoleUi);
  }
  updateSealRoleUi();

  function showSealResult(status, body, ms, isNetworkError) {
    if (!sealResultBox || !sealStatusBadge || !sealTimeBadge || !sealResponseEl) return;
    sealResultBox.classList.remove('d-none');
    sealStatusBadge.className = 'badge rounded-pill ' +
      (isNetworkError ? 'badge-status-err'
        : status >= 200 && status < 300 ? 'badge-status-ok'
        : status >= 400 && status < 500 ? 'badge-status-warn'
        : 'badge-status-err');
    sealStatusBadge.textContent = isNetworkError ? 'خطای اتصال' : ('HTTP ' + status);
    sealTimeBadge.textContent = 'زمان پاسخ: ' + ms + ' میلی‌ثانیه';
    sealResponseEl.textContent = body;
  }

  sealSendBtn.addEventListener('click', function () {
    var role = getSealRole();
    var isOperator = role === 'operator';
    var userId = isOperator
      ? (sealOperatorSelect ? sealOperatorSelect.value : '')
      : (sealDriverSelect ? sealDriverSelect.value : '');
    var waybillNumber = sealWaybillNumberInput ? sealWaybillNumberInput.value.trim() : '';
    if (!userId || !waybillNumber) return;

    var mintUrl = isOperator ? mintOperatorTokenUrl : mintDriverTokenUrl;
    var mintBodyField = isOperator ? 'operator_id' : 'driver_id';

    sealSendBtn.disabled = true;
    if (sealTokenLabel) sealTokenLabel.textContent = (isOperator ? 'توکن متصدی مورد استفاده:' : 'توکن راننده مورد استفاده:');
    if (sealTokenText) sealTokenText.textContent = 'در حال ساخت توکن…';
    var start = (window.performance && performance.now) ? performance.now() : Date.now();

    fetch(mintUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: mintBodyField + '=' + encodeURIComponent(userId)
    })
      .then(function (r) { return r.json(); })
      .then(function (mintData) {
        if (!mintData.success) {
          if (sealTokenText) sealTokenText.textContent = '—';
          var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
          showSealResult(0, 'خطا در ساخت توکن آزمایشی: ' + mintData.message, elapsed, true);
          sealSendBtn.disabled = false;
          return;
        }

        if (sealTokenText) sealTokenText.textContent = mintData.token;

        var url = sealApiUrl + '?token=' + encodeURIComponent(mintData.token) +
          '&waybill_number=' + encodeURIComponent(waybillNumber);

        fetch(url)
          .then(function (res) {
            return res.text().then(function (text) {
              var pretty = text;
              try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
              var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
              showSealResult(res.status, pretty, elapsed, false);
              sealSendBtn.disabled = false;
            });
          })
          .catch(function (err) {
            var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
            showSealResult(0, 'اتصال به سرور برقرار نشد.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
            sealSendBtn.disabled = false;
          });
      })
      .catch(function (err) {
        var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
        showSealResult(0, 'اتصال به سرور برقرار نشد.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
        sealSendBtn.disabled = false;
      });
  });

  if (sealClearBtn) {
    sealClearBtn.addEventListener('click', function () {
      if (sealResultBox) sealResultBox.classList.add('d-none');
      if (sealResponseEl) sealResponseEl.textContent = '';
      if (sealTokenText) sealTokenText.textContent = '';
    });
  }
})();

/* ---------- صفحه مستندات وب‌سرویس حصار جغرافیایی (متصدی) ---------- */
(function () {
  'use strict';
  var geofenceApiUrl = window.API_GEOFENCE_CHECK_URL;

  var geofenceEndpointText = document.getElementById('geofenceEndpointText');
  if (geofenceEndpointText && geofenceApiUrl) geofenceEndpointText.textContent = 'GET ' + geofenceApiUrl + '?id=...&lat=...&lon=...';

  var geofenceCurlSample = document.getElementById('geofenceCurlSample');
  if (geofenceCurlSample && geofenceApiUrl) geofenceCurlSample.textContent =
    'curl -X GET "' + geofenceApiUrl + '?id=<LOCATION_ID>&lat=<LAT>&lon=<LON>"';
})();

/* ---------- صفحه تست وب‌سرویس تایید حضور متصدی ---------- */
(function () {
  'use strict';
  var opTripSendBtn = document.getElementById('opTripSendBtn');
  var opTripApiUrl = window.API_OPERATOR_ACTION_URL;
  var mintTokenUrl = window.API_TEST_OPERATOR_ACTION_TOKEN_URL;

  var opTripEndpointText = document.getElementById('opTripEndpointText');
  if (opTripEndpointText && opTripApiUrl) opTripEndpointText.textContent = 'POST ' + opTripApiUrl;

  var opTripCurlSample = document.getElementById('opTripCurlSample');
  if (opTripCurlSample && opTripApiUrl) opTripCurlSample.textContent =
    'curl -X POST "' + opTripApiUrl + '" \\\n' +
    '  -H "Content-Type: application/x-www-form-urlencoded" \\\n' +
    '  --data-urlencode "token=<TOKEN>"';

  var opTripJsSample = document.getElementById('opTripJsSample');
  if (opTripJsSample && opTripApiUrl) opTripJsSample.textContent =
    'const res = await fetch("' + opTripApiUrl + '", {\n' +
    '  method: "POST",\n' +
    '  headers: { "Content-Type": "application/x-www-form-urlencoded" },\n' +
    '  body: "token=" + encodeURIComponent(token)\n' +
    '});\n' +
    'const data = await res.json();\n' +
    'if (data.success) {\n' +
    '  console.log("ثبت شد:", data.message);\n' +
    '} else {\n' +
    '  console.log("خطا:", data.message);\n' +
    '}';

  var opTripPhpSample = document.getElementById('opTripPhpSample');
  if (opTripPhpSample && opTripApiUrl) opTripPhpSample.textContent =
    '$ch = curl_init("' + opTripApiUrl + '");\n' +
    'curl_setopt($ch, CURLOPT_POST, true);\n' +
    'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\n' +
    'curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([\n' +
    '    "token" => $token,\n' +
    ']));\n' +
    '$response = curl_exec($ch);\n' +
    '$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);\n' +
    'curl_close($ch);\n' +
    '$data = json_decode($response, true);';

  if (!opTripSendBtn) return;

  var opWaybillSelect = document.getElementById('opTripWaybillSelect');
  var opTripClearBtn  = document.getElementById('opTripClearBtn');
  var opTripResultBox = document.getElementById('opTripResultBox');
  var opTripTokenText = document.getElementById('opTripTokenText');
  var opTripStatusBadge = document.getElementById('opTripStatusBadge');
  var opTripTimeBadge = document.getElementById('opTripTimeBadge');
  var opTripResponseEl = document.getElementById('opTripResponse');

  function showOpTripResult(status, body, ms, isNetworkError) {
    if (!opTripResultBox || !opTripStatusBadge || !opTripTimeBadge || !opTripResponseEl) return;
    opTripResultBox.classList.remove('d-none');
    opTripStatusBadge.className = 'badge rounded-pill ' +
      (isNetworkError ? 'badge-status-err'
        : status >= 200 && status < 300 ? 'badge-status-ok'
        : status >= 400 && status < 500 ? 'badge-status-warn'
        : 'badge-status-err');
    opTripStatusBadge.textContent = isNetworkError ? 'خطای اتصال' : ('HTTP ' + status);
    opTripTimeBadge.textContent = 'زمان پاسخ: ' + ms + ' میلی‌ثانیه';
    opTripResponseEl.textContent = body;
  }

  opTripSendBtn.addEventListener('click', function () {
    var opt = opWaybillSelect.options[opWaybillSelect.selectedIndex];
    if (!opt) return;
    var waybillId = opt.value;
    var role = opt.getAttribute('data-role');

    opTripSendBtn.disabled = true;
    if (opTripTokenText) opTripTokenText.textContent = 'در حال ساخت توکن…';
    var start = (window.performance && performance.now) ? performance.now() : Date.now();

    fetch(mintTokenUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'waybill_id=' + encodeURIComponent(waybillId) + '&role=' + encodeURIComponent(role)
    })
      .then(function (r) { return r.json(); })
      .then(function (mintData) {
        if (!mintData.success) {
          if (opTripTokenText) opTripTokenText.textContent = '—';
          var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
          showOpTripResult(0, 'خطا در ساخت توکن آزمایشی: ' + mintData.message, elapsed, true);
          opTripSendBtn.disabled = false;
          return;
        }

        if (opTripTokenText) opTripTokenText.textContent = mintData.token;

        fetch(opTripApiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'token=' + encodeURIComponent(mintData.token)
        })
          .then(function (res) {
            return res.text().then(function (text) {
              var pretty = text;
              try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
              var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
              showOpTripResult(res.status, pretty, elapsed, false);
              opTripSendBtn.disabled = false;
            });
          })
          .catch(function (err) {
            var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
            showOpTripResult(0, 'اتصال به سرور برقرار نشد.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
            opTripSendBtn.disabled = false;
          });
      })
      .catch(function (err) {
        var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
        showOpTripResult(0, 'اتصال به سرور برقرار نشد.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
        opTripSendBtn.disabled = false;
      });
  });

  if (opTripClearBtn) {
    opTripClearBtn.addEventListener('click', function () {
      if (opTripResultBox) opTripResultBox.classList.add('d-none');
      if (opTripResponseEl) opTripResponseEl.textContent = '';
      if (opTripTokenText) opTripTokenText.textContent = '';
    });
  }
})();

/* ---------- صفحه تست وب‌سرویس بارنامه‌های ایستگاه متصدی ---------- */
(function () {
  'use strict';
  var opActiveSendBtn = document.getElementById('opActiveSendBtn');
  var opActiveApiUrl = window.API_OPERATOR_WAYBILLS_URL;
  var mintOperatorTokenUrl = window.API_TEST_OPERATOR_TOKEN_URL;

  var opActiveEndpointText = document.getElementById('opActiveEndpointText');
  if (opActiveEndpointText && opActiveApiUrl) opActiveEndpointText.textContent = 'GET ' + opActiveApiUrl + '?token=...';

  var opActiveCurlSample = document.getElementById('opActiveCurlSample');
  if (opActiveCurlSample && opActiveApiUrl) opActiveCurlSample.textContent =
    'curl -X GET "' + opActiveApiUrl + '?token=<TOKEN>"';

  var opActiveJsSample = document.getElementById('opActiveJsSample');
  if (opActiveJsSample && opActiveApiUrl) opActiveJsSample.textContent =
    'const res = await fetch("' + opActiveApiUrl + '?token=" + encodeURIComponent(token));\n' +
    'const data = await res.json();\n' +
    'if (data.success) {\n' +
    '  console.log("بارنامه‌های ایستگاه:", data.waybills);\n' +
    '} else {\n' +
    '  console.log("خطا:", data.message);\n' +
    '}';

  var opActivePhpSample = document.getElementById('opActivePhpSample');
  if (opActivePhpSample && opActiveApiUrl) opActivePhpSample.textContent =
    '$ch = curl_init("' + opActiveApiUrl + '?token=" . urlencode($token));\n' +
    'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\n' +
    '$response = curl_exec($ch);\n' +
    '$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);\n' +
    'curl_close($ch);\n' +
    '$data = json_decode($response, true);';

  if (!opActiveSendBtn) return;

  var operatorSelect = document.getElementById('opActiveOperatorSelect');
  var opActiveClearBtn = document.getElementById('opActiveClearBtn');
  var opActiveResultBox = document.getElementById('opActiveResultBox');
  var opActiveTokenText = document.getElementById('opActiveTokenText');
  var opActiveStatusBadge = document.getElementById('opActiveStatusBadge');
  var opActiveTimeBadge = document.getElementById('opActiveTimeBadge');
  var opActiveResponseEl = document.getElementById('opActiveResponse');

  function showOpActiveResult(status, body, ms, isNetworkError) {
    if (!opActiveResultBox || !opActiveStatusBadge || !opActiveTimeBadge || !opActiveResponseEl) return;
    opActiveResultBox.classList.remove('d-none');
    opActiveStatusBadge.className = 'badge rounded-pill ' +
      (isNetworkError ? 'badge-status-err'
        : status >= 200 && status < 300 ? 'badge-status-ok'
        : status >= 400 && status < 500 ? 'badge-status-warn'
        : 'badge-status-err');
    opActiveStatusBadge.textContent = isNetworkError ? 'خطای اتصال' : ('HTTP ' + status);
    opActiveTimeBadge.textContent = 'زمان پاسخ: ' + ms + ' میلی‌ثانیه';
    opActiveResponseEl.textContent = body;
  }

  opActiveSendBtn.addEventListener('click', function () {
    var operatorId = operatorSelect.value;
    if (!operatorId) return;

    opActiveSendBtn.disabled = true;
    if (opActiveTokenText) opActiveTokenText.textContent = 'در حال ساخت توکن…';
    var start = (window.performance && performance.now) ? performance.now() : Date.now();

    fetch(mintOperatorTokenUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'operator_id=' + encodeURIComponent(operatorId)
    })
      .then(function (r) { return r.json(); })
      .then(function (mintData) {
        if (!mintData.success) {
          if (opActiveTokenText) opActiveTokenText.textContent = '—';
          var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
          showOpActiveResult(0, 'خطا در ساخت توکن آزمایشی: ' + mintData.message, elapsed, true);
          opActiveSendBtn.disabled = false;
          return;
        }

        if (opActiveTokenText) opActiveTokenText.textContent = mintData.token;

        fetch(opActiveApiUrl + '?token=' + encodeURIComponent(mintData.token))
          .then(function (res) {
            return res.text().then(function (text) {
              var pretty = text;
              try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
              var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
              showOpActiveResult(res.status, pretty, elapsed, false);
              opActiveSendBtn.disabled = false;
            });
          })
          .catch(function (err) {
            var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
            showOpActiveResult(0, 'اتصال به سرور برقرار نشد.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
            opActiveSendBtn.disabled = false;
          });
      })
      .catch(function (err) {
        var elapsed = Math.round(((window.performance && performance.now) ? performance.now() : Date.now()) - start);
        showOpActiveResult(0, 'اتصال به سرور برقرار نشد.\n\n' + (err && err.message ? err.message : ''), elapsed, true);
        opActiveSendBtn.disabled = false;
      });
  });

  if (opActiveClearBtn) {
    opActiveClearBtn.addEventListener('click', function () {
      if (opActiveResultBox) opActiveResultBox.classList.add('d-none');
      if (opActiveResponseEl) opActiveResponseEl.textContent = '';
      if (opActiveTokenText) opActiveTokenText.textContent = '';
    });
  }
})();

/* ---------- تقویم شمسی برای فیلدهای تاریخ ---------- */
(function () {
  'use strict';
  if (!window.jQuery || !jQuery.fn || !jQuery.fn.persianDatepicker) return;

  jQuery('[data-jalali-datepicker]').each(function () {
    var $input = jQuery(this);
    var rawValue = ($input.val() || '').trim();
    // ارقام فارسی احتمالی را به انگلیسی تبدیل می‌کنیم تا الگوی زیر همیشه تطبیق پیدا کند
    var normalized = rawValue.replace(/[۰-۹]/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
    });
    var match = normalized.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
    // فرمت مورد قبول این نسخه از کتابخانه با صفر ابتدایی است؛ عدد را مستقیماً پد می‌کنیم
    var selectedDateStr = match
      ? match[1] + '/' + ('0' + match[2]).slice(-2) + '/' + ('0' + match[3]).slice(-2)
      : null;

    // نکته مهم: نام صحیح گزینه‌های این کتابخانه formatDate و selectedDate است،
    // نه format و initialValue (که در نسخه‌های دیگر کتابخانه‌های مشابه استفاده می‌شود
    // و در این نسخه به‌سادگی نادیده گرفته می‌شدند و باعث محاسبه اشتباه تاریخ می‌شدند).
    $input.persianDatepicker({
      formatDate: 'YYYY/MM/DD',
      selectedDate: selectedDateStr,
      autoClose: true,
      isRTL: true
    });

    // مطمئن می‌شویم مقدار نمایشی input هرگز توسط کتابخانه بازنویسی نشده باشد
    if (rawValue) {
      $input.val(rawValue);
    }
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

/* ---------- جلوگیری از انتخاب مبدا و مقصد یکسان (برای همه کاربران، از جمله ادمین) ---------- */
(function () {
  'use strict';
  var originSel = document.getElementById('origin_location_id');
  var destSel = document.getElementById('destination_location_id');
  if (!originSel || !destSel) return;

  var form = originSel.closest('form');

  function checkSameLocation() {
    var same = originSel.value !== '' && originSel.value === destSel.value;
    var existingWarning = document.getElementById('sameLocationWarning');

    if (same) {
      if (!existingWarning) {
        var div = document.createElement('div');
        div.id = 'sameLocationWarning';
        div.className = 'alert alert-danger d-flex align-items-center gap-2 mt-3';
        div.innerHTML = '<span class="iconify" data-icon="solar:danger-triangle-bold"></span> مبدا و مقصد نمی‌توانند یکسان باشند.';
        var actionsRow = form.querySelector('.d-flex.gap-2.mt-4');
        if (actionsRow) {
          form.insertBefore(div, actionsRow);
        } else {
          form.appendChild(div);
        }
      }
    } else if (existingWarning) {
      existingWarning.remove();
    }
  }

  function blockSubmitIfSame(e) {
    if (originSel.value !== '' && originSel.value === destSel.value) {
      e.preventDefault();
      checkSameLocation();
    }
  }

  originSel.addEventListener('change', checkSameLocation);
  destSel.addEventListener('change', checkSameLocation);
  if (form) form.addEventListener('submit', blockSubmitIfSame);
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

/* ---------- بارگذاری مطمئن ECharts (رفع مشکل نمایش‌نیافتن نمودار بدون رفرش) ---------- */
/**
 * بعضی مواقع اسکریپت CDN کتابخانه ECharts کمی دیرتر از اجرای اسکریپت صفحه لود می‌شود،
 * یا اندازه واقعی ظرف نمودار هنوز توسط مرورگر محاسبه نشده است (به‌خصوص در بارگذاری اول صفحه).
 * این تابع مطمئن می‌شود که echarts کاملاً آماده است و صفحه رندر نهایی خودش را انجام داده
 * قبل از این‌که هر نموداری ساخته شود؛ در غیر این صورت چند بار امتحان می‌کند.
 */
window.whenEChartsReady = function (callback) {
  var attempts = 0;
  var maxAttempts = 50; // حداکثر ۵ ثانیه انتظار (۵۰ × ۱۰۰ میلی‌ثانیه)

  function tryRun() {
    attempts++;
    if (typeof echarts !== 'undefined') {
      // یک فریم دیگر صبر می‌کنیم تا چیدمان (layout) صفحه کامل شود و اندازه واقعی ظرف مشخص باشد
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          callback();
        });
      });
      return;
    }
    if (attempts < maxAttempts) {
      setTimeout(tryRun, 100);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tryRun);
  } else {
    tryRun();
  }
};

/* بازرسم خودکار همه نمودارهای صفحه هنگام تغییر اندازه پنجره یا نمایان‌شدن مجدد تب */
window.addEventListener('resize', function () {
  if (window.__registeredCharts) {
    window.__registeredCharts.forEach(function (c) {
      if (c && typeof c.resize === 'function') {
        try { c.resize(); } catch (e) { /* نادیده گرفتن خطای نمودار حذف‌شده */ }
      }
    });
  }
});

document.addEventListener('visibilitychange', function () {
  if (!document.hidden && window.__registeredCharts) {
    window.__registeredCharts.forEach(function (c) {
      if (c && typeof c.resize === 'function') {
        try { c.resize(); } catch (e) { /* نادیده گرفتن خطای نمودار حذف‌شده */ }
      }
    });
  }
});

/* ---------- محاسبه خودکار مسافت بین مبدا و مقصد (فرم بارنامه) ---------- */
/* الگوریتم مطابق distance_calc/distance_calculator.html:
   ۱) تلاش برای مسیر جاده‌ای واقعی از API نقشه (map.ir routing)
   ۲) در صورت شکست، فاصله مستقیم هوایی با فرمول هاورساین (معادل turf.distance) به‌عنوان جایگزین
   فیلد مسافت دستی باقی می‌ماند و کاربر می‌تواند مقدار محاسبه‌شده را ویرایش کند. */
(function () {
  'use strict';

  var originSelect = document.getElementById('origin_location_id');
  var destSelect = document.getElementById('destination_location_id');
  var distanceInput = document.getElementById('distance_km');
  if (!originSelect || !destSelect || !distanceInput) return;

  var MAPIR_API_KEY = window.MAPIR_API_KEY || '';

  function getCoords(select) {
    var opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return null;
    var lat = parseFloat(opt.getAttribute('data-lat'));
    var lon = parseFloat(opt.getAttribute('data-lon'));
    if (!isFinite(lat) || !isFinite(lon) || (lat === 0 && lon === 0)) return null;
    return { lat: lat, lon: lon };
  }

  function haversineKm(a, b) {
    var R = 6371;
    var dLat = (b.lat - a.lat) * Math.PI / 180;
    var dLon = (b.lon - a.lon) * Math.PI / 180;
    var lat1 = a.lat * Math.PI / 180;
    var lat2 = b.lat * Math.PI / 180;
    var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
    return R * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
  }

  var hint = document.createElement('div');
  hint.className = 'form-text';
  hint.id = 'distanceAutoHint';
  var distanceCol = distanceInput.closest('.col-md-6') || distanceInput.parentElement;
  distanceCol.appendChild(hint);

  function setHint(text, isWarn) {
    hint.textContent = text;
    hint.classList.toggle('text-warning', Boolean(isWarn));
  }

  async function autoFillDistance() {
    var origin = getCoords(originSelect);
    var destination = getCoords(destSelect);
    if (!origin || !destination) return;

    setHint('در حال محاسبه خودکار مسافت...');

    try {
      if (!MAPIR_API_KEY) throw new Error('no api key');
      var coordinates = origin.lon + ',' + origin.lat + ';' + destination.lon + ',' + destination.lat;
      var url = 'https://map.ir/routes/route/v1/driving/' + encodeURIComponent(coordinates) +
                 '?geometries=geojson&overview=false&steps=false&alternatives=false';
      var response = await fetch(url, { headers: { 'x-api-key': MAPIR_API_KEY } });
      if (!response.ok) throw new Error('bad status');
      var data = await response.json();
      if (!data.routes || !data.routes.length) throw new Error('no route');

      var km = data.routes[0].distance / 1000;
      distanceInput.value = km.toFixed(2);
      setHint('مسافت به‌صورت خودکار از مسیر جاده‌ای محاسبه شد (' + km.toFixed(2) + ' کیلومتر). در صورت نیاز می‌توانید آن را ویرایش کنید.', false);
    } catch (e) {
      var straightKm = haversineKm(origin, destination);
      distanceInput.value = straightKm.toFixed(2);
      setHint('مسیر جاده‌ای در دسترس نبود؛ فاصله مستقیم هوایی محاسبه شد (' + straightKm.toFixed(2) + ' کیلومتر). در صورت نیاز می‌توانید آن را ویرایش کنید.', true);
    }
  }

  originSelect.addEventListener('change', autoFillDistance);
  destSelect.addEventListener('change', autoFillDistance);
})();

/* ---------- کادر کد: افزودن نوار زبان برنامه‌نویسی + دکمه کپی به تمام کادرهای کد ---------- */
(function () {
  'use strict';

  var LANG_LABELS = {
    curl: 'cURL',
    javascript: 'JavaScript',
    php: 'PHP',
    json: 'JSON'
  };

  // نگاشت زبان‌های صفحه به زبان‌های Prism برای رنگ‌آمیزی کد
  var PRISM_LANGS = {
    curl: 'bash',
    javascript: 'javascript',
    php: 'php',
    json: 'json'
  };

  var COPY_ICON_SVG =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">' +
    '<path d="M384 336H192c-8.8 0-16-7.2-16-16V64c0-8.8 7.2-16 16-16l140.1 0L400 115.9V320c0 8.8-7.2 16-16 16zM192 384H384c35.3 0 64-28.7 64-64V115.9c0-12.7-5.1-24.9-14.1-33.9L366.1 14.1c-9-9-21.2-14.1-33.9-14.1H192c-35.3 0-64 28.7-64 64V320c0 35.3 28.7 64 64 64zM64 128c-35.3 0-64 28.7-64 64V448c0 35.3 28.7 64 64 64H256c35.3 0 64-28.7 64-64V416H272v32c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V192c0-8.8 7.2-16 16-16H96V128H64z"/>' +
    '</svg>';

  var CHECK_ICON_SVG =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">' +
    '<path d="M441 103c9.4 9.4 9.4 24.6 0 33.9L177 401c-9.4 9.4-24.6 9.4-33.9 0L7 265c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l119 119L407 103c9.4-9.4 24.6-9.4 33.9 0z"/>' +
    '</svg>';

  // متن داخل pre را به یک عنصر <code class="language-…"> منتقل و با Prism رنگ‌آمیزی می‌کند.
  // اگر جای دیگری از برنامه textContent خود pre را عوض کند (مثلاً پاسخ تست زنده)،
  // MutationObserver پایین دوباره همین تابع را صدا می‌زند.
  function highlightPre(pre, prismLang) {
    var codeEl = pre.querySelector('code');
    if (!codeEl) {
      codeEl = document.createElement('code');
      codeEl.textContent = pre.textContent;
      pre.textContent = '';
      pre.appendChild(codeEl);
    }
    codeEl.className = 'language-' + prismLang;
    if (window.Prism && window.Prism.highlightElement) {
      window.Prism.highlightElement(codeEl);
    }
  }

  function observePre(pre, prismLang) {
    if (!window.MutationObserver) return;
    var busy = false;
    var observer = new MutationObserver(function () {
      if (busy) return;
      // فقط وقتی متن مستقیماً داخل pre ریخته شده (بدون <code>) دوباره بپیچیم
      var needsRewrap = !pre.querySelector('code') ||
        Array.prototype.some.call(pre.childNodes, function (n) {
          return n.nodeType === 3 && n.textContent.trim() !== '';
        });
      if (!needsRewrap) return;
      busy = true;
      highlightPre(pre, prismLang);
      busy = false;
    });
    observer.observe(pre, { childList: true });
  }

  function copyToClipboard(text, onDone) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () { onDone(true); }).catch(function () { onDone(false); });
      return;
    }
    // جایگزین برای مرورگرهایی که از Clipboard API پشتیبانی نمی‌کنند
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(ta);
    onDone(ok);
  }

  document.querySelectorAll('pre[data-lang]').forEach(function (pre) {
    if (pre.closest('.code-block')) return; // قبلاً پیچیده شده

    var lang = pre.getAttribute('data-lang') || '';
    var label = LANG_LABELS[lang] || lang.toUpperCase();
    var prismLang = PRISM_LANGS[lang] || 'none';

    var wrapper = document.createElement('div');
    wrapper.className = 'code-block';

    var toolbar = document.createElement('div');
    toolbar.className = 'code-block-toolbar';

    var langSpan = document.createElement('span');
    langSpan.className = 'code-block-lang';
    langSpan.textContent = label;

    var copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'code-block-copy-btn';

    var copyIcon = document.createElement('span');
    copyIcon.className = 'code-block-copy-icon';
    copyIcon.innerHTML = COPY_ICON_SVG;

    var copyText = document.createElement('span');
    copyText.className = 'code-block-copy-text';
    copyText.textContent = 'کپی کد';

    copyBtn.appendChild(copyIcon);
    copyBtn.appendChild(copyText);

    copyBtn.addEventListener('click', function () {
      copyToClipboard(pre.textContent || '', function (ok) {
        copyBtn.classList.toggle('copied', ok);
        copyIcon.innerHTML = ok ? CHECK_ICON_SVG : COPY_ICON_SVG;
        copyText.textContent = ok ? 'کپی شد!' : 'کپی کد';
        if (ok) {
          setTimeout(function () {
            copyBtn.classList.remove('copied');
            copyIcon.innerHTML = COPY_ICON_SVG;
            copyText.textContent = 'کپی کد';
          }, 1500);
        }
      });
    });

    toolbar.appendChild(langSpan);
    toolbar.appendChild(copyBtn);

    pre.parentNode.insertBefore(wrapper, pre);
    wrapper.appendChild(toolbar);
    wrapper.appendChild(pre);

    highlightPre(pre, prismLang);
    observePre(pre, prismLang);
  });
})();
