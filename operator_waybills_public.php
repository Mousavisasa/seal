<?php
/**
 * صفحه عمومی مشاهده و مدیریت بارنامه‌های ایستگاه متصدی (بدون نیاز به سشن/ورود به پنل)
 * معادل driver_waybills.php برای نقش متصدی.
 *
 * دو روش ورود:
 * ۱) با توکن: operator_waybills_public.php?token=...
 *    توکن از طریق وب‌سرویس ورود (api/login.php) گرفته می‌شود و حداکثر ۱۰ دقیقه اعتبار دارد.
 * ۲) با فرم کد ملی/رمز عبور: بعد از احراز هویت موفق، یک توکن تازه ساخته می‌شود
 *    و کاربر به همان صفحه با آدرس ?token=... هدایت می‌شود (redirect).
 *
 * برای هر بارنامه، اگر نوبت تایید بارگیری/تحویل رسیده باشد، همان‌جا دکمهٔ
 * ثبت وضعیت نمایش داده می‌شود؛ و برای مراحل حضور، دکمهٔ «تایید حضور» کاربر
 * را با یک توکن یک‌بارمصرف مخصوص همان عملیات
 * (helpers/tokens.php::create_operator_action_token) به
 * waybill_operator_geofence_check.php می‌فرستد.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/jalali.php';
require_once __DIR__ . '/helpers/tokens.php';

$errors = [];
$operator = null;
$waybills = [];
$waybillLogs = [];
$submittedUsername = '';
$token = trim((string)($_GET['token'] ?? ''));

/** بازگرداندن متصدی معتبر از توکن، یا null در صورت نامعتبر/غیرمجازبودن */
function resolve_operator_from_token(string $token): array
{
    $operator = validate_access_token($token, 'operator_waybills');
    if (!$operator) {
        return [null, 'توکن نامعتبر است یا منقضی شده است. لطفاً دوباره وارد شوید.'];
    }
    if ($operator['user_type'] !== 'operator') {
        return [null, 'این توکن متعلق به یک حساب متصدی نیست.'];
    }
    if ((int)($operator['is_active'] ?? 1) === 0) {
        return [null, 'حساب کاربری شما غیرفعال شده است. برای اطلاعات بیشتر با مدیر سامانه تماس بگیرید.'];
    }
    return [$operator, null];
}

// ---------- روش ۱: ورود با توکن ----------
if (!$operator && $token !== '') {
    try {
        [$operator, $tokenError] = resolve_operator_from_token($token);
        if (!$operator && $tokenError && !in_array($tokenError, $errors, true)) {
            $errors[] = $tokenError;
        }
    } catch (PDOException $e) {
        error_log('Operator waybills token validate error: ' . $e->getMessage());
        $errors[] = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
    }
}

// ---------- روش ۲: ورود مستقیم با فرم کد ملی/رمز عبور (POST) ----------
if (!$operator && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedUsername = normalize_digits((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($submittedUsername === '' || $password === '') {
        $errors[] = 'نام کاربری (کد ملی) و رمز عبور را وارد کنید.';
    } else {
        try {
            $stmt = db()->prepare("SELECT * FROM users WHERE national_code = ? AND user_type = 'operator' LIMIT 1");
            $stmt->execute([$submittedUsername]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'نام کاربری یا رمز عبور نادرست است.';
            } elseif ((int)($user['is_active'] ?? 1) === 0) {
                $errors[] = 'حساب کاربری شما غیرفعال شده است. برای اطلاعات بیشتر با مدیر سامانه تماس بگیرید.';
            } else {
                $newToken = create_access_token((int)$user['id'], 'operator_waybills');
                header('Location: ' . BASE_URL . '/operator_waybills_public.php?token=' . rawurlencode($newToken));
                exit;
            }
        } catch (PDOException $e) {
            error_log('Operator waybills lookup auth error: ' . $e->getMessage());
            $errors[] = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
        }
    }
}

// ---------- دریافت بارنامه‌ها در صورت احراز هویت موفق ----------
if ($operator) {
    try {
        ensure_operator_confirm_columns();
        ensure_waybill_status_logs_table();
        $stmt = db()->prepare(
            "SELECT w.*, ol.title AS origin_title, dl.title AS destination_title,
                    drU.first_name AS driver_first, drU.last_name AS driver_last,
                    sl.seal_id AS attached_seal_id
             FROM fuel_waybills w
             INNER JOIN locations ol ON ol.id = w.origin_location_id
             INNER JOIN locations dl ON dl.id = w.destination_location_id
             LEFT JOIN users drU ON drU.id = w.driver_user_id
             LEFT JOIN seals sl ON sl.fuel_waybill_id = w.id
             WHERE w.origin_operator_user_id = ? OR w.destination_operator_user_id = ?
             ORDER BY w.id DESC"
        );
        $stmt->execute([$operator['id'], $operator['id']]);
        $waybills = $stmt->fetchAll();

        if ($waybills) {
            $waybillIds = array_map(static function ($w) {
                return (int)$w['id'];
            }, $waybills);
            $placeholders = implode(',', array_fill(0, count($waybillIds), '?'));
            $logStmt = db()->prepare(
                "SELECT l.*, w.waybill_number,
                        u.first_name AS performed_first, u.last_name AS performed_last, u.national_code AS performed_nc
                 FROM waybill_status_logs l
                 INNER JOIN fuel_waybills w ON w.id = l.fuel_waybill_id
                 LEFT JOIN users u ON u.id = l.performed_by
                 WHERE l.fuel_waybill_id IN ($placeholders)
                 ORDER BY l.changed_at DESC, l.id DESC"
            );
            $logStmt->execute($waybillIds);
            foreach ($logStmt->fetchAll() as $logRow) {
                $waybillId = (int)$logRow['fuel_waybill_id'];
                if (!isset($waybillLogs[$waybillId])) {
                    $waybillLogs[$waybillId] = [];
                }
                $waybillLogs[$waybillId][] = [
                    'id' => (int)$logRow['id'],
                    'waybill_id' => $waybillId,
                    'waybill_number' => (string)$logRow['waybill_number'],
                    'from_status' => (string)($logRow['from_status'] ?? ''),
                    'to_status' => (string)$logRow['to_status'],
                    'seal_number' => (string)($logRow['seal_number'] ?? ''),
                    'source_section' => (string)$logRow['source_section'],
                    'changed_at' => (string)$logRow['changed_at'],
                    'changed_at_display' => to_jalali_datetime_display((string)$logRow['changed_at']),
                    'performed_by_label' => !empty($logRow['performed_first'])
                        ? trim((string)$logRow['performed_first'] . ' ' . (string)$logRow['performed_last'])
                        : 'سیستم',
                    'performed_nc' => (string)($logRow['performed_nc'] ?? ''),
                ];
            }
        }
    } catch (PDOException $e) {
        error_log('Operator waybills lookup fetch error: ' . $e->getMessage());
        $errors[] = 'خطایی در دریافت فهرست بارنامه‌ها رخ داد.';
    }
}

$statusClassMap = [
    'ثبت شده'   => 'status-registered',
    'بارگیری شده' => 'status-loading',
    'ارسال شده' => 'status-sent',
    'پایان پیمایش' => 'status-completed',
    'تحویل شده' => 'status-delivered',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>بارنامه‌های ایستگاه متصدی | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/vazirmatn.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="<?= BASE_URL ?>/assets/js/iconify.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/iconify-icons.js"></script>
<style>
.status-loading { background-color: #ffc107; color: #212529; }
.status-completed { background-color: #17a2b8; color: #fff; }
.loading-spinner { display: inline-block; animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
</head>
<body class="public-page-body">

<div class="public-page-wrap">

  <div class="text-center mb-4">
    <span class="iconify fs-1 text-jade" data-icon="solar:buildings-3-bold-duotone"></span>
    <h1 class="h4 fw-bold mt-2 mb-1">بارنامه‌های ایستگاه متصدی</h1>
    <p class="text-muted small mb-0">
      <?php if ($operator): ?>
        بارنامه‌های تخصیص‌یافته به ایستگاه خود را مشاهده و وضعیت/حضور را ثبت کنید.
      <?php else: ?>
        با کد ملی و رمز عبور خود وارد شوید تا بارنامه‌های ایستگاه خود را ببینید.
      <?php endif; ?>
    </p>
  </div>

  <?php if (!$operator): ?>
  <div class="card border-0 shadow-sm mb-4" style="border-radius: 1rem;">
    <div class="card-body p-4">
      <form method="post" action="<?= BASE_URL ?>/operator_waybills_public.php" novalidate>
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label" for="username">کد ملی (نام کاربری)</label>
            <div class="input-group">
              <span class="input-group-text"><span class="iconify" data-icon="solar:card-2-bold"></span></span>
              <input type="text" class="form-control ltr-text" id="username" name="username"
                     inputmode="numeric" maxlength="10" required
                     value="<?= e($submittedUsername) ?>" placeholder="مثلاً 2222222222">
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

  <?php if ($operator): ?>
    <div class="card border-0 shadow-sm mb-3" style="border-radius: 1rem;">
      <div class="card-body p-4 d-flex align-items-center gap-3">
        <span class="iconify fs-1 text-purple" data-icon="solar:user-circle-bold-duotone"></span>
        <div>
          <div class="fw-bold fs-5"><?= e($operator['first_name'] . ' ' . $operator['last_name']) ?></div>
          <div class="text-muted small ltr-text"><?= e($operator['national_code']) ?></div>
        </div>
        <div class="ms-auto text-muted small">
          تعداد بارنامه: <span class="fw-bold"><?= e((string)count($waybills)) ?></span>
        </div>
      </div>
    </div>

      <?php if ($waybills): ?>
          <div class="row g-3">
              <?php foreach ($waybills as $w): ?>
                  <?php
                    $isOrigin      = (int)$w['origin_operator_user_id'] === (int)$operator['id'];
                    $role          = $isOrigin ? 'origin' : 'destination';
                    $roleLabel     = $isOrigin ? 'متصدی مبدا' : 'متصدی مقصد';
                    $confirmedAt   = $isOrigin ? ($w['origin_confirmed_at'] ?? null) : ($w['destination_confirmed_at'] ?? null);
                    $actionApiUrl  = null;
                    $actionLabel   = null;
                    $actionMessage = null;

                    if ($isOrigin && $w['send_status'] === 'بارگیری شده') {
                        $actionApiUrl  = BASE_URL . '/api/operator_approve_loading.php';
                        $actionLabel   = 'تایید بارگیری';
                        $actionMessage = 'آیا مطمئن هستید که می‌خواهید این بارنامه را برای ارسال تایید کنید؟';
                    } elseif (!$isOrigin && $w['send_status'] === 'پایان پیمایش') {
                        $actionApiUrl  = BASE_URL . '/api/operator_approve_delivery.php';
                        $actionLabel   = 'تایید تحویل';
                        $actionMessage = 'آیا مطمئن هستید که می‌خواهید این بارنامه را به عنوان تحویل‌شده ثبت کنید؟';
                    }
                  ?>
                  <div class="col-md-6 col-xl-4">
                      <div class="card panel-card h-100">
                          <div class="card-body d-flex flex-column gap-2">
                              <div class="d-flex justify-content-between align-items-start">
                                  <div class="fw-bold ltr-text"><?= e($w['waybill_number']) ?></div>
                                  <span class="status-badge <?= e($statusClassMap[$w['send_status']] ?? '') ?>"><?= e($w['send_status']) ?></span>
                              </div>

                              <div class="d-flex flex-wrap gap-1">
                                  <span class="badge role-badge role-region"><?= e($roleLabel) ?></span>
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

                              <div class="small text-muted d-flex align-items-center gap-1">
                                  <span class="iconify" data-icon="solar:bus-bold"></span>
                                  راننده: <?= $w['driver_first'] ? e($w['driver_first'] . ' ' . $w['driver_last']) : '<span class="text-muted">تخصیص‌نیافته</span>' ?>
                              </div>

                              <div class="mt-auto pt-2 d-flex gap-2">
                                  <?php if ($actionApiUrl): ?>
                                      <div class="flex-fill d-grid gap-2">
                                        <button type="button"
                                                class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2 operator-status-btn"
                                                data-api-url="<?= e($actionApiUrl) ?>"
                                                data-waybill-id="<?= e((string)$w['id']) ?>"
                                                data-confirm-message="<?= e($actionMessage) ?>">
                                            <span class="iconify" data-icon="solar:check-circle-bold"></span> <?= e($actionLabel) ?>
                                        </button>
                                      </div>
                                  <?php elseif ($confirmedAt): ?>
                                      <div class="text-center w-100 text-muted small py-2 flex-fill">
                                          <span class="iconify" data-icon="solar:check-circle-bold"></span> حضور شما در این بارنامه تایید شده است.
                                      </div>
                                  <?php else: ?>
                                      <div class="flex-fill d-grid gap-2">
                                        <?php $opToken = create_operator_action_token((int)$operator['id'], $role, (int)$w['id']); ?>
                                        <a href="<?= BASE_URL ?>/waybill_operator_geofence_check.php?token=<?= e(rawurlencode($opToken)) ?>&myseal=<?= ($w['waybill_number']) ?>"
                                           class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                            <span class="iconify" data-icon="solar:check-circle-bold"></span> تایید حضور
                                        </a>
                                      </div>
                                  <?php endif; ?>
                                  <button type="button"
                                          class="btn btn-outline-secondary d-flex align-items-center justify-content-center gap-2 waybill-log-btn"
                                          data-waybill-id="<?= e((string)$w['id']) ?>"
                                          data-waybill-number="<?= e($w['waybill_number']) ?>">
                                      <span class="iconify" data-icon="solar:document-text-bold"></span> لاگ
                                  </button>
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
                  در حال حاضر هیچ بارنامه‌ای به ایستگاه شما تخصیص داده نشده است.
              </div>
          </div>
      <?php endif; ?>

  <?php endif; ?>

  <div class="text-center text-muted small mt-4">
    <span class="iconify" data-icon="solar:info-circle-bold"></span>
    این صفحه عمومی است و نیازی به ورود به پنل ندارد.
    <?php if ($operator): ?>
      توکن استفاده‌شده حداکثر <?= e((string)ACCESS_TOKEN_TTL_MINUTES) ?> دقیقه از زمان صدور معتبر است.
    <?php endif; ?>
  </div>

</div>

<div class="modal fade" id="waybillLogsModal" tabindex="-1" aria-labelledby="waybillLogsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="waybillLogsModalLabel">لاگ بارنامه</h5>
        <button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="بستن"></button>
      </div>
      <div class="modal-body">
        <div id="waybillLogsModalBody" class="d-grid gap-2"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const waybillStatusLogs = <?= json_encode($waybillLogs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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

var logsModalEl = document.getElementById('waybillLogsModal');
var logsModal = logsModalEl ? new bootstrap.Modal(logsModalEl) : null;
var logsModalTitle = document.getElementById('waybillLogsModalLabel');
var logsModalBody = document.getElementById('waybillLogsModalBody');

function createLogItem(log) {
  var item = document.createElement('div');
  item.className = 'border rounded-3 p-3 bg-light';

  var header = document.createElement('div');
  header.className = 'd-flex justify-content-between align-items-start gap-3 mb-2';

  var left = document.createElement('div');
  var title = document.createElement('div');
  title.className = 'fw-bold';
  title.textContent = (log.from_status ? log.from_status + ' → ' : '') + log.to_status;
  left.appendChild(title);

  var meta = document.createElement('div');
  meta.className = 'small text-muted';
  meta.textContent = log.changed_at_display || log.changed_at || '';
  left.appendChild(meta);

  var seal = document.createElement('span');
  seal.className = 'badge text-bg-secondary ltr-text';
  seal.textContent = log.seal_number && log.seal_number !== '' ? 'پلمپ: ' + log.seal_number : 'پلمپ: —';

  header.appendChild(left);
  header.appendChild(seal);
  item.appendChild(header);

  var details = document.createElement('div');
  details.className = 'small text-muted d-grid gap-1';

  var performer = document.createElement('div');
  performer.textContent = 'ثبت‌کننده: ' + (log.performed_by_label || 'سیستم');
  details.appendChild(performer);

  var section = document.createElement('div');
  section.textContent = 'منبع: ' + (log.source_section || '—');
  details.appendChild(section);

  item.appendChild(details);
  return item;
}

document.querySelectorAll('.waybill-log-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var waybillId = btn.getAttribute('data-waybill-id');
    var waybillNumber = btn.getAttribute('data-waybill-number') || '';
    var logs = waybillStatusLogs[waybillId] || [];

    if (logsModalTitle) {
      logsModalTitle.textContent = waybillNumber ? 'لاگ بارنامه ' + waybillNumber : 'لاگ بارنامه';
    }
    if (logsModalBody) {
      logsModalBody.innerHTML = '';
      if (!logs.length) {
        var empty = document.createElement('div');
        empty.className = 'alert alert-light border mb-0';
        empty.textContent = 'برای این بارنامه هنوز لاگی ثبت نشده است.';
        logsModalBody.appendChild(empty);
      } else {
        logs.forEach(function (log) {
          logsModalBody.appendChild(createLogItem(log));
        });
      }
    }

    if (logsModal) {
      logsModal.show();
    }
  });
});

document.querySelectorAll('.operator-status-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var apiUrl = btn.getAttribute('data-api-url');
    var waybillId = parseInt(btn.getAttribute('data-waybill-id') || '0', 10);
    var confirmMessage = btn.getAttribute('data-confirm-message') || 'آیا مطمئن هستید؟';

    if (!apiUrl || !waybillId) {
      return;
    }

    if (!confirm(confirmMessage)) {
      return;
    }

    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner iconify" data-icon="solar:refresh-bold"></span> درحال‌پردازش...';

    fetch(apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        token: <?= json_encode($token) ?>,
        waybill_id: waybillId
      })
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.success) {
          alert(data.message || 'عملیات با موفقیت انجام شد.');
          location.reload();
          return;
        }

        alert('خطا: ' + (data.message || 'عملیات ناموفق بود.'));
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      })
      .catch(function (err) {
        alert('خطایی رخ داد: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      });
  });
});
</script>
</body>
</html>