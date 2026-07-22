<?php
/**
 * فهرست همه کاربران با جدول واکنش‌گرا
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';

require_admin();

$users = [];
try {
    $users = db()->query(
        'SELECT u.id, u.national_code, u.first_name, u.last_name, u.user_type, u.region_id, u.is_active, u.created_at, r.region_name
         FROM users u LEFT JOIN regions r ON r.region_code = u.region_id
         ORDER BY u.id DESC'
    )->fetchAll();
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

<?php if ($users): ?>
<div class="filter-bar mb-3">
  <div class="input-group" style="max-width: 340px;">
    <span class="input-group-text"><span class="iconify" data-icon="solar:magnifer-bold"></span></span>
    <input type="text" class="form-control" id="usersSearch" placeholder="جستجو در نام، کد ملی، نقش و...">
  </div>
</div>
<?php endif; ?>

<div class="card panel-card">
  <div class="card-body p-0">
    <?php if ($users): ?>
    <div id="gridUsers" class="seal-ag-grid"></div>
    <?php else: ?>
      <div class="text-center text-muted p-5">
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:users-group-rounded-line-duotone"></span>
        هنوز کاربری ثبت نشده است.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($users): ?>
<script>
(function () {
  var csrfField = <?= json_encode(csrf_field(), JSON_UNESCAPED_UNICODE) ?>;
  var currentUserId = <?= (int)$_SESSION['user_id'] ?>;
  var rows = <?= json_encode(array_map(function ($u) {
      $actions = '<div class="d-flex gap-1 flex-wrap">'
          . '<a class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" href="' . BASE_URL . '/users/edit.php?id=' . (int)$u['id'] . '"><span class="iconify" data-icon="solar:pen-bold"></span> ویرایش</a>'
          . '<a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1" href="' . BASE_URL . '/users/reset_password.php?id=' . (int)$u['id'] . '"><span class="iconify" data-icon="solar:key-bold"></span> بازنشانی رمز</a>';
      if ((int)$u['id'] !== (int)$_SESSION['user_id']) {
          $actions .= '<form method="post" action="' . BASE_URL . '/users/delete.php" class="d-inline" data-delete-form data-confirm="آیا از حذف کاربر «' . e($u['first_name'] . ' ' . $u['last_name']) . '» مطمئن هستید؟">'
              . '<input type="hidden" name="id" value="' . (int)$u['id'] . '">'
              . '<button type="submit" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1"><span class="iconify" data-icon="solar:trash-bin-trash-bold"></span> حذف</button>'
              . '</form>';
      }
      $actions .= '</div>';
      return [
          'id' => (int)$u['id'],
          'name' => $u['first_name'] . ' ' . $u['last_name'],
          'national_code' => $u['national_code'],
          'role' => '<span class="badge role-badge role-' . e($u['user_type']) . '">' . e(user_type_label($u['user_type'])) . '</span>',
          'region' => $u['region_name'] ? e($u['region_name']) : '<span class="text-muted">—</span>',
          'status' => (int)$u['is_active'] === 1
              ? '<span class="status-badge status-delivered">فعال</span>'
              : '<span class="status-badge status-cancelled">غیرفعال</span>',
          'created_at' => to_jalali_datetime_display($u['created_at']),
          'actions' => $actions,
      ];
  }, $users), JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT) ?>;
  var columnDefs = [
    { field: 'id', headerName: '#', width: 70, cellClass: 'text-muted' },
    { field: 'name', headerName: 'نام و نام خانوادگی', flex: 2, cellClass: 'fw-bold' },
    { field: 'national_code', headerName: 'کد ملی', flex: 1, cellClass: 'ltr-text' },
    { field: 'role', headerName: 'نقش', flex: 1, html: true },
    { field: 'region', headerName: 'منطقه', flex: 1, html: true },
    { field: 'status', headerName: 'وضعیت', flex: 1, html: true },
    { field: 'created_at', headerName: 'تاریخ ثبت', flex: 1, cellClass: 'ltr-text text-muted small', filter: false },
    { field: 'actions', headerName: 'عملیات', flex: 2, html: true, sortable: false, filter: false }
  ];
  function boot() {
    if (typeof sealInitDataGrid === 'undefined') { setTimeout(boot, 30); return; }
    sealInitDataGrid('gridUsers', columnDefs, rows, { searchInputId: 'usersSearch' });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
