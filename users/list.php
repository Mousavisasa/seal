<?php
/**
 * فهرست همه کاربران با جدول واکنش‌گرا
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_admin();

$users = [];
try {
    $users = db()->query('SELECT id, national_code, first_name, last_name, user_type, created_at FROM users ORDER BY id DESC')->fetchAll();
} catch (PDOException $e) {
    error_log('List users error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست کاربران رخ داد.');
}

$page_title = 'فهرست کاربران';
$active = 'users-list';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">فهرست کاربران</h1>
    <p class="text-muted small mb-0">تعداد کل: <?= e((string)count($users)) ?> نفر</p>
  </div>
  <a href="<?= BASE_URL ?>/users/create.php" class="btn btn-primary d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:user-plus-rounded-bold"></span> کاربر جدید
  </a>
</div>

<?= render_flash() ?>

<div class="card panel-card">
  <div class="card-body p-0">
    <?php if ($users): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>نام و نام خانوادگی</th>
            <th>کد ملی</th>
            <th>نقش</th>
            <th>تاریخ ثبت</th>
            <th class="text-start">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td class="text-muted"><?= e((string)$u['id']) ?></td>
            <td class="fw-bold"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
            <td class="ltr-text"><?= e($u['national_code']) ?></td>
            <td><span class="badge role-badge role-<?= e($u['user_type']) ?>"><?= e(user_type_label($u['user_type'])) ?></span></td>
            <td class="ltr-text text-muted small"><?= e($u['created_at']) ?></td>
            <td class="text-start">
              <a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1"
                 href="<?= BASE_URL ?>/users/reset_password.php?id=<?= e((string)$u['id']) ?>">
                <span class="iconify" data-icon="solar:key-bold"></span> بازنشانی رمز
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="text-center text-muted p-5">
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:users-group-rounded-line-duotone"></span>
        هنوز کاربری ثبت نشده است.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
