<?php
/**
 * فهرست بارنامه‌های تخصیص‌یافته به کاربر متصدی جاری
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';

require_login();

if (!is_operator() && !is_admin()) {
    set_flash('danger', 'شما دسترسی لازم برای این بخش را ندارید.');
    header('Location: ' . BASE_URL . '/' . redirect_path_for_role($_SESSION['user_type'] ?? ''));
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
    $stmt = db()->prepare(
        'SELECT w.*, ol.title AS origin_title, dl.title AS destination_title,
                drU.first_name AS driver_first, drU.last_name AS driver_last
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         LEFT JOIN users drU ON drU.id = w.driver_user_id
         WHERE w.sender_operator_user_id = ?
         ORDER BY w.id DESC'
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    $waybills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('My waybills error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست بارنامه‌ها رخ داد.');
}

$page_title = 'بارنامه‌های من';
$active = 'my-waybills';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">بارنامه‌های تخصیص‌یافته به من</h1>
  <p class="text-muted small mb-0">تعداد کل: <?= e((string)count($waybills)) ?> بارنامه</p>
</div>

<?= render_flash() ?>

<div class="card panel-card">
  <div class="card-body p-0">
    <?php if ($waybills): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>شماره بارنامه</th>
            <th>مبدا</th>
            <th>مقصد</th>
            <th>فرآورده</th>
            <th>تاریخ صدور</th>
            <th>وضعیت</th>
            <th>راننده</th>
            <th class="text-start">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($waybills as $w): ?>
          <tr>
            <td class="ltr-text fw-bold"><?= e($w['waybill_number']) ?></td>
            <td><?= e($w['origin_title']) ?></td>
            <td><?= e($w['destination_title']) ?></td>
            <td><span class="product-badge"><?= e($w['product_type']) ?></span></td>
            <td class="ltr-text text-muted small"><?= e(to_jalali_display($w['issue_date'])) ?></td>
            <td><span class="status-badge <?= e($statusClassMap[$w['send_status']] ?? '') ?>"><?= e($w['send_status']) ?></span></td>
            <td><?= $w['driver_first'] ? e($w['driver_first'] . ' ' . $w['driver_last']) : '<span class="text-muted">تخصیص‌نیافته</span>' ?></td>
            <td class="text-start">
              <a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1"
                 href="<?= BASE_URL ?>/waybills/assign_driver.php?id=<?= e((string)$w['id']) ?>">
                <span class="iconify" data-icon="solar:bus-bold"></span> تخصیص راننده
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="text-center text-muted p-5">
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:fuel-line-duotone"></span>
        هیچ بارنامه‌ای به شما تخصیص داده نشده است.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
