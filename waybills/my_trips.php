<?php
/**
 * صفحه راننده: بارنامه‌های تخصیص‌یافته به راننده جاری
 * راننده می‌تواند برای هر بارنامه «شروع سفر» و «پایان سفر» را ثبت کند.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';

require_driver_access();

$errors = [];

// عملیات شروع/پایان سفر
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $waybillId = (int)($_POST['id'] ?? 0);

    if (!verify_csrf()) {
        set_flash('danger', 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.');
    } elseif ($waybillId <= 0 || !in_array($action, ['start_trip', 'end_trip'], true)) {
        set_flash('danger', 'درخواست نامعتبر است.');
    } else {
        try {
            $stmt = db()->prepare('SELECT * FROM fuel_waybills WHERE id = ? LIMIT 1');
            $stmt->execute([$waybillId]);
            $target = $stmt->fetch();

            if (!$target) {
                set_flash('danger', 'بارنامه مورد نظر یافت نشد.');
            } elseif (!is_admin() && (int)$target['driver_user_id'] !== (int)$_SESSION['user_id']) {
                set_flash('danger', 'این بارنامه به شما تخصیص داده نشده است.');
            } elseif ($action === 'start_trip') {
                if ($target['send_status'] !== 'ثبت شده') {
                    set_flash('warning', 'این بارنامه قبلاً شروع شده یا در وضعیت دیگری قرار دارد.');
                } else {
                    $upd = db()->prepare("UPDATE fuel_waybills SET send_status = 'ارسال شده', trip_started_at = NOW() WHERE id = ?");
                    $upd->execute([$waybillId]);
                    set_flash('success', 'شروع سفر برای بارنامه «' . $target['waybill_number'] . '» ثبت شد.');
                }
            } elseif ($action === 'end_trip') {
                if ($target['send_status'] !== 'ارسال شده') {
                    set_flash('warning', 'این بارنامه هنوز شروع نشده یا قبلاً تحویل داده شده است.');
                } else {
                    $upd = db()->prepare("UPDATE fuel_waybills SET send_status = 'تحویل شده', trip_ended_at = NOW() WHERE id = ?");
                    $upd->execute([$waybillId]);
                    set_flash('success', 'پایان سفر برای بارنامه «' . $target['waybill_number'] . '» ثبت شد.');
                }
            }
        } catch (PDOException $e) {
            error_log('Driver trip action error: ' . $e->getMessage());
            set_flash('danger', 'خطایی در ثبت عملیات رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    header('Location: ' . BASE_URL . '/waybills/my_trips.php');
    exit;
}

$waybills = [];
$statusClassMap = [
    'ثبت شده'   => 'status-registered',
    'ارسال شده' => 'status-sent',
    'تحویل شده' => 'status-delivered',
    'لغو شده'   => 'status-cancelled',
];

try {
    $driverFilter = is_admin() ? null : (int)$_SESSION['user_id'];
    $sql = 'SELECT w.*, ol.title AS origin_title, dl.title AS destination_title,
                   opU.first_name AS operator_first, opU.last_name AS operator_last
            FROM fuel_waybills w
            INNER JOIN locations ol ON ol.id = w.origin_location_id
            INNER JOIN locations dl ON dl.id = w.destination_location_id
            LEFT JOIN users opU ON opU.id = w.sender_operator_user_id';
    $params = [];
    if ($driverFilter !== null) {
        $sql .= ' WHERE w.driver_user_id = ?';
        $params[] = $driverFilter;
    }
    $sql .= ' ORDER BY w.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $waybills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('My trips error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست سفرها رخ داد.');
}

$page_title = 'سفرهای من';
$active = 'my-trips';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">سفرهای من</h1>
  <p class="text-muted small mb-0">بارنامه‌های تخصیص‌یافته به شما — تعداد: <?= e((string)count($waybills)) ?></p>
</div>

<?= render_flash() ?>

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

        <?php if ($w['operator_first']): ?>
        <div class="small text-muted d-flex align-items-center gap-1">
          <span class="iconify" data-icon="solar:user-id-bold"></span> متصدی: <?= e($w['operator_first'] . ' ' . $w['operator_last']) ?>
        </div>
        <?php endif; ?>

        <div class="mt-auto pt-2 d-flex gap-2">
          <?php if ($w['send_status'] === 'ثبت شده'): ?>
            <form method="post" action="<?= BASE_URL ?>/waybills/my_trips.php" class="flex-fill">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= e((string)$w['id']) ?>">
              <input type="hidden" name="action" value="start_trip">
              <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                <span class="iconify" data-icon="solar:play-circle-bold"></span> شروع سفر
              </button>
            </form>
          <?php elseif ($w['send_status'] === 'ارسال شده'): ?>
            <form method="post" action="<?= BASE_URL ?>/waybills/my_trips.php" class="flex-fill">
              <?= csrf_field() ?>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
