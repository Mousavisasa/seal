<?php
/**
 * صفحهٔ فقط-لودینگ برای ثبت «تایید حضور متصدی» — معادل waybill_trip_loading.php برای متصدی
 *
 * ورودی (GET): فقط و فقط «token» (همان توکن یک‌بارمصرف عملیات که در
 * waybill_operator_geofence_check.php پس از تایید حصار جغرافیایی ساخته شده است).
 *
 * این صفحه خودش هیچ اعتبارسنجی یا منطقی ندارد؛ صرفاً یک اسپینر تمام‌صفحه
 * نشان می‌دهد و از طریق جاوااسکریپت با همان توکن به وب‌سرویس
 * api/operator_action.php درخواست می‌زند.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';

$token = trim((string)($_GET['token'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>در حال ثبت درخواست… | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/vazirmatn.css">
<style>
  html, body { height: 100%; margin: 0; background: #fff; font-family: Vazirmatn, Tahoma, sans-serif; }
  .loading-page { height: 100%; display: flex; align-items: center; justify-content: center; }
  .loading-box { display: flex; flex-direction: column; align-items: center; }
  .loader-shell { width: 90px; height: 90px; display: flex; align-items: center; justify-content: center; }
  .loader {
    --color-1: #179F2E; --color-2: #7C00FF; --size: 1px;
    transform: rotateZ(45deg); perspective: calc(1000 * var(--size));
    border-radius: 50%; width: calc(48 * var(--size)); height: calc(48 * var(--size));
    color: var(--color-1); position: relative;
  }
  .loader:before, .loader:after {
    content: ''; display: block; position: absolute; top: 0; left: 0;
    width: inherit; height: inherit; border-radius: 50%;
    transform: rotateX(70deg); animation: 1s spin linear infinite;
  }
  .loader:after { color: var(--color-2); transform: rotateY(70deg); animation-delay: 0.4s; }
  @keyframes spin {
    0%, 100% { box-shadow: 0.2em 0 0 0 currentcolor; }
    12% { box-shadow: 0.2em 0.2em 0 0 currentcolor; }
    25% { box-shadow: 0 0.2em 0 0 currentcolor; }
    37% { box-shadow: -0.2em 0.2em 0 0 currentcolor; }
    50% { box-shadow: -0.2em 0 0 0 currentcolor; }
    62% { box-shadow: -0.2em -0.2em 0 0 currentcolor; }
    75% { box-shadow: 0 -0.2em 0 0 currentcolor; }
    87% { box-shadow: 0.2em -0.2em 0 0 currentcolor; }
  }
  #loaderMessage { margin-top: 1.5rem; color: #666; font-size: .95rem; text-align: center; padding: 0 1.5rem; }
</style>
</head>
<body>

<div class="loading-page">
  <div class="loading-box">
    <div class="loader-shell"><span class="loader"></span></div>
    <div id="loaderMessage">در حال ثبت درخواست…</div>
  </div>
</div>

<script>
(function () {
  var TOKEN = <?= json_encode($token) ?>;
  var OPERATOR_ACTION_URL = '<?= BASE_URL ?>/api/operator_action.php';
  var loaderMessage = document.getElementById('loaderMessage');

  if (!TOKEN) {
    loaderMessage.textContent = 'توکن نامعتبر است.';
    return;
  }

  fetch(OPERATOR_ACTION_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'token=' + encodeURIComponent(TOKEN)
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      loaderMessage.textContent = data.message || (data.success ? 'با موفقیت ثبت شد.' : 'خطایی رخ داد.');
    })
    .catch(function () {
      loaderMessage.textContent = 'خطای ارتباط با سرور. لطفاً بعداً با پشتیبانی تماس بگیرید.';
    });
})();
</script>
</body>
</html>