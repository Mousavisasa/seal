<?php
/**
 * صفحه عمومی مشاهده و تایید بارنامه‌های بارگیری‌شده برای متصدی مبدا
 * (بدون نیاز به سشن/ورود به پنل)
 *
 * دو روش ورود:
 * ۱) با توکن: operator_loading_public.php?token=...
 * ۲) با فرم کد ملی/رمز عبور
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/jalali.php';
require_once __DIR__ . '/helpers/tokens.php';

$errors = [];
$operator = null;
$waybills = [];
$submittedUsername = '';
$token = trim((string)($_GET['token'] ?? ''));

/** بازگرداندن متصدی معتبر از توکن، یا null در صورت نامعتبر */
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
        error_log('Operator loading token validate error: ' . $e->getMessage());
        $errors[] = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
    }
}

// ---------- روش ۲: ورود مستقیم با فرم ----------
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
                $errors[] = 'حساب کاربری شما غیرفعال شده است.';
            } else {
                $newToken = create_access_token((int)$user['id'], 'operator_waybills');
                header('Location: ' . BASE_URL . '/operator_loading_public.php?token=' . rawurlencode($newToken));
                exit;
            }
        } catch (PDOException $e) {
            error_log('Operator loading auth error: ' . $e->getMessage());
            $errors[] = 'خطایی رخ داد. لطفاً بعداً تلاش کنید.';
        }
    }
}

// ---------- دریافت بارنامه‌های درحال‌بارگذاری ----------
if ($operator) {
    try {
        $stmt = db()->prepare(
            "SELECT w.*, ol.title AS origin_title, dl.title AS destination_title,
                    d.first_name AS driver_first, d.last_name AS driver_last
             FROM fuel_waybills w
             INNER JOIN locations ol ON ol.id = w.origin_location_id
             INNER JOIN locations dl ON dl.id = w.destination_location_id
             LEFT JOIN users d ON d.id = w.driver_user_id
             WHERE w.origin_operator_user_id = ? AND w.send_status = 'بارگیری شده'
             ORDER BY w.id DESC"
        );
        $stmt->execute([$operator['id']]);
        $waybills = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Operator loading fetch error: ' . $e->getMessage());
        $errors[] = 'خطایی در دریافت فهرست بارنامه‌ها رخ داد.';
    }
}

$statusClassMap = [
    'بارگیری شده' => 'status-loading',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تایید بارگذاری | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/vazirmatn.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="<?= BASE_URL ?>/assets/js/iconify.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/iconify-icons.js"></script>
<style>
.status-loading { background-color: #ffc107; color: #f1770d; }
.panel-card { border-radius: 12px; overflow: hidden; }
.waybill-card { transition: all 0.3s ease; }
.waybill-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
.loading-spinner { display: inline-block; animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
</head>
<body class="public-page-body">

<div class="public-page-wrap">
    <div class="container py-5">
        <?php if (!$operator): ?>
            <!-- فرم ورود -->
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="card panel-card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="mb-4 text-center">
                                <span class="iconify" data-icon="solar:box-bold" style="font-size: 28px;"></span>
                                ورود متصدی مبدا
                            </h3>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php foreach ($errors as $error): ?>
                                        <div><?= e($error) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">کد ملی</label>
                                    <input type="text" class="form-control" id="username" name="username"
                                           value="<?= e($submittedUsername) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">رمز عبور</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">ورود</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- لیست بارنامه‌های درحال‌بارگذاری -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>
                            <span class="iconify" data-icon="solar:box-bold"></span>
                            بارنامه‌های در‌حال‌بارگذاری
                        </h2>
                        <a href="?token=" class="btn btn-sm btn-outline-secondary">خروج</a>
                    </div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?= e($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($waybills)): ?>
                <div class="row g-3">
                    <?php foreach ($waybills as $w): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card panel-card border-0 shadow-sm waybill-card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="mb-2">
                                        <span class="badge <?= e($statusClassMap[$w['send_status']] ?? 'bg-secondary') ?>">
                                            <?= e($w['send_status']) ?>
                                        </span>
                                    </div>

                                    <h5 class="card-title">بارنامه #<?= e($w['id']) ?></h5>

                                    <div class="small text-muted mb-2">
                                        <span class="iconify" data-icon="solar:routing-bold"></span>
                                        از: <?= e($w['origin_title']) ?> → <?= e($w['destination_title']) ?>
                                    </div>

                                    <?php if ($w['driver_first']): ?>
                                        <div class="small text-muted mb-2">
                                            <span class="iconify" data-icon="solar:user-bold"></span>
                                            راننده: <?= e($w['driver_first'] . ' ' . $w['driver_last']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="small text-muted mb-2">
                                        <span class="iconify" data-icon="solar:calendar-bold"></span>
                                        <?= date_jymd($w['created_at']) ?>
                                    </div>

                                    <div class="mt-auto pt-2 d-flex gap-2">
                                        <button class="btn btn-success w-100 approve-btn"
                                                data-waybill-id="<?= e($w['id']) ?>">
                                            <span class="iconify" data-icon="solar:check-circle-bold"></span>
                                            تایید و ارسال
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card panel-card border-0 shadow-sm">
                    <div class="card-body text-center text-muted p-5">
                        <div style="font-size: 48px; margin-bottom: 16px;">
                            <span class="iconify" data-icon="solar:inbox-bold"></span>
                        </div>
                        <p>بارنامه‌ای در حال‌انتظار برای تایید وجود ندارد.</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($operator): ?>
<script>
const token = '<?= e($token) ?>';

document.querySelectorAll('.approve-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const waybillId = parseInt(btn.dataset.waybillId);

        if (!confirm('آیا مطمئن هستید که می‌خواهید این بارنامه را برای ارسال تایید کنید؟')) {
            return;
        }

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner iconify" data-icon="solar:refresh-bold"></span> درحال‌پردازش...';

        try {
            const response = await fetch('<?= BASE_URL ?>/api/operator_approve_loading.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    token: token,
                    waybill_id: waybillId
                })
            });

            const data = await response.json();

            if (data.success) {
                alert('بارنامه با موفقیت تایید شد!');
                location.reload();
            } else {
                alert('خطا: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (err) {
            alert('خطایی رخ داد: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
});
</script>
<?php endif; ?>

</body>
</html>
