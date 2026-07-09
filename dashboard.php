<?php
/**
 * داشبورد ادمین: کارت‌های خلاصه آمار کاربران
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/jalali.php';

require_admin();

$counts = ['total' => 0, 'admin' => 0, 'driver' => 0, 'operator' => 0, 'region' => 0];
$latest = [];
$waybillCounts = ['total' => 0, 'ثبت شده' => 0, 'ارسال شده' => 0, 'تحویل شده' => 0, 'لغو شده' => 0];

try {
    $rows = db()->query('SELECT user_type, COUNT(*) AS c FROM users GROUP BY user_type')->fetchAll();
    foreach ($rows as $row) {
        $counts[$row['user_type']] = (int)$row['c'];
        $counts['total'] += (int)$row['c'];
    }
    $latest = db()->query('SELECT national_code, first_name, last_name, user_type, created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll();

    $wRows = db()->query('SELECT send_status, COUNT(*) AS c FROM fuel_waybills GROUP BY send_status')->fetchAll();
    foreach ($wRows as $row) {
        $waybillCounts[$row['send_status']] = (int)$row['c'];
        $waybillCounts['total'] += (int)$row['c'];
    }
} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت اطلاعات رخ داد.');
}

$page_title = 'داشبورد';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">داشبورد</h1>
    <p class="text-muted small mb-0">نمای کلی کاربران سامانه</p>
  </div>
  <a href="<?= BASE_URL ?>/users/create.php" class="btn btn-primary d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:user-plus-rounded-bold"></span> کاربر جدید
  </a>
</div>

<?= render_flash() ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:users-group-two-rounded-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$counts['total']) ?></div>
          <div class="stat-label">کل کاربران</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:bus-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$counts['driver']) ?></div>
          <div class="stat-label">راننده</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:user-id-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$counts['operator']) ?></div>
          <div class="stat-label">متصدی</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$counts['region']) ?></div>
          <div class="stat-label">منطقه</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 mt-2">
  <h2 class="h6 fw-bold mb-0">ماژول بارنامه سوخت</h2>
  <a href="<?= BASE_URL ?>/waybills/create.php" class="btn btn-soft-purple d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:add-circle-bold"></span> بارنامه جدید
  </a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:fuel-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['total']) ?></div>
          <div class="stat-label">کل بارنامه‌ها</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:delivery-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['ارسال شده']) ?></div>
          <div class="stat-label">ارسال شده</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-purple h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:check-circle-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['تحویل شده']) ?></div>
          <div class="stat-label">تحویل شده</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card stat-jade h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon"><span class="iconify" data-icon="solar:close-circle-bold"></span></span>
        <div>
          <div class="stat-number"><?= e((string)$waybillCounts['لغو شده']) ?></div>
          <div class="stat-label">لغو شده</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/regions/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--purple-soft); color: var(--purple-dark);"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
          <div>
            <div class="fw-bold">مدیریت مناطق</div>
            <div class="text-muted small">افزودن و ویرایش مناطق</div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/locations/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--jade-soft); color: var(--jade-dark);"><span class="iconify" data-icon="solar:signpost-bold"></span></span>
          <div>
            <div class="fw-bold">مدیریت مبادی و مقاصد</div>
            <div class="text-muted small">افزودن و ویرایش مکان‌ها</div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="<?= BASE_URL ?>/waybills/list.php" class="text-decoration-none">
      <div class="card panel-card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon" style="background: var(--purple-soft); color: var(--purple-dark);"><span class="iconify" data-icon="solar:fuel-bold"></span></span>
          <div>
            <div class="fw-bold">مدیریت بارنامه سوخت</div>
            <div class="text-muted small">فهرست و ثبت بارنامه‌ها</div>
          </div>
        </div>
      </div>
    </a>
  </div>
</div>

<div class="card panel-card">
  <div class="card-header d-flex align-items-center gap-2">
    <span class="iconify text-jade fs-5" data-icon="solar:clock-circle-bold"></span>
    <span class="fw-bold">آخرین کاربران ثبت‌شده</span>
  </div>
  <div class="card-body p-0">
    <?php if ($latest): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>نام و نام خانوادگی</th>
            <th>کد ملی</th>
            <th>نقش</th>
            <th>تاریخ ثبت</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($latest as $u): ?>
          <tr>
            <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
            <td class="ltr-text"><?= e($u['national_code']) ?></td>
            <td><span class="badge role-badge role-<?= e($u['user_type']) ?>"><?= e(user_type_label($u['user_type'])) ?></span></td>
            <td class="ltr-text text-muted small"><?= e(to_jalali_datetime_display($u['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted p-4 mb-0">هنوز کاربری ثبت نشده است. از دکمه «کاربر جدید» شروع کنید.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
