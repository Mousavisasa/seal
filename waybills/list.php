<?php
/**
 * فهرست بارنامه‌های سوخت با جستجو و فیلتر وضعیت/فرآورده
 * منطقه مبدا/مقصد از طریق locations.region_id به‌دست می‌آید (بدون ستون جداگانه در fuel_waybills)
 * تاریخ صدور به شمسی نمایش داده می‌شود
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/jalali.php';

require_waybill_access();

$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$productFilter = trim((string)($_GET['product'] ?? ''));
$waybills = [];

try {
    $sql = 'SELECT w.*,
                   ol.title AS origin_title, dl.title AS destination_title,
                   opU.first_name AS operator_first, opU.last_name AS operator_last,
                   drU.first_name AS driver_first, drU.last_name AS driver_last,
                   orRg.region_name AS origin_region_name, dsRg.region_name AS destination_region_name
            FROM fuel_waybills w
            INNER JOIN locations ol ON ol.id = w.origin_location_id
            INNER JOIN locations dl ON dl.id = w.destination_location_id
            INNER JOIN regions orRg ON orRg.region_code = ol.region_id
            INNER JOIN regions dsRg ON dsRg.region_code = dl.region_id
            LEFT JOIN users opU ON opU.id = w.sender_operator_user_id
            LEFT JOIN users drU ON drU.id = w.driver_user_id
            WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND w.waybill_number LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($statusFilter !== '' && in_array($statusFilter, SEND_STATUSES, true)) {
        $sql .= ' AND w.send_status = ?';
        $params[] = $statusFilter;
    }
    if ($productFilter !== '' && in_array($productFilter, PRODUCT_TYPES, true)) {
        $sql .= ' AND w.product_type = ?';
        $params[] = $productFilter;
    }
    $sql .= ' ORDER BY w.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $waybills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Waybills list error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در دریافت فهرست بارنامه‌ها رخ داد.');
}

$statusClassMap = [
    'ثبت شده'   => 'status-registered',
    'ارسال شده' => 'status-sent',
    'تحویل شده' => 'status-delivered',
    'لغو شده'   => 'status-cancelled',
];

$page_title = 'مدیریت بارنامه سوخت';
$active = 'waybills';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h4 fw-bold mb-1">مدیریت بارنامه سوخت</h1>
    <p class="text-muted small mb-0">تعداد کل: <?= e((string)count($waybills)) ?> بارنامه</p>
  </div>
  <a href="<?= BASE_URL ?>/waybills/create.php" class="btn btn-primary d-flex align-items-center gap-2">
    <span class="iconify" data-icon="solar:add-circle-bold"></span> بارنامه جدید
  </a>
</div>

<?= render_flash() ?>

<div class="filter-bar mb-4">
  <form method="get" action="<?= BASE_URL ?>/waybills/list.php" class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label" for="q">جستجو در شماره بارنامه</label>
      <div class="input-group">
        <span class="input-group-text"><span class="iconify" data-icon="solar:magnifer-bold"></span></span>
        <input type="text" class="form-control ltr-text" id="q" name="q" value="<?= e($search) ?>" placeholder="مثلاً WB-1001">
      </div>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="status">وضعیت ارسال</label>
      <select class="form-select" id="status" name="status">
        <option value="">همه وضعیت‌ها</option>
        <?php foreach (SEND_STATUSES as $s): ?>
          <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="product">نوع فرآورده</label>
      <select class="form-select" id="product" name="product">
        <option value="">همه فرآورده‌ها</option>
        <?php foreach (PRODUCT_TYPES as $p): ?>
          <option value="<?= e($p) ?>" <?= $productFilter === $p ? 'selected' : '' ?>><?= e($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-soft-purple d-flex align-items-center gap-2">
        <span class="iconify" data-icon="solar:magnifer-bold"></span> اعمال
      </button>
      <a href="<?= BASE_URL ?>/waybills/list.php" class="btn btn-outline-secondary">پاک‌کردن</a>
    </div>
  </form>
</div>

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
            <th>مسافت (کیلومتر)</th>
            <th>فرآورده</th>
            <th>تاریخ صدور</th>
            <th>وضعیت</th>
            <th>متصدی</th>
            <th>راننده</th>
            <th class="text-start">عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($waybills as $w): ?>
          <tr>
            <td class="ltr-text fw-bold"><?= e($w['waybill_number']) ?></td>
            <td><?= e($w['origin_title']) ?> <span class="text-muted small">(<?= e($w['origin_region_name']) ?>)</span></td>
            <td><?= e($w['destination_title']) ?> <span class="text-muted small">(<?= e($w['destination_region_name']) ?>)</span></td>
            <td class="ltr-text"><?= e(number_format((float)$w['distance_km'], 2)) ?></td>
            <td><span class="product-badge"><?= e($w['product_type']) ?></span></td>
            <td class="ltr-text text-muted small"><?= e(to_jalali_display($w['issue_date'])) ?></td>
            <td><span class="status-badge <?= e($statusClassMap[$w['send_status']] ?? '') ?>"><?= e($w['send_status']) ?></span></td>
            <td><?= $w['operator_first'] ? e($w['operator_first'] . ' ' . $w['operator_last']) : '<span class="text-muted">—</span>' ?></td>
            <td><?= $w['driver_first'] ? e($w['driver_first'] . ' ' . $w['driver_last']) : '<span class="text-muted">—</span>' ?></td>
            <td class="text-start">
              <div class="d-flex gap-1 justify-content-start flex-wrap">
                <a class="btn btn-sm btn-soft-purple d-inline-flex align-items-center gap-1"
                   href="<?= BASE_URL ?>/waybills/assign_operator.php?id=<?= e((string)$w['id']) ?>">
                  <span class="iconify" data-icon="solar:user-id-bold"></span> متصدی
                </a>
                <a class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                   href="<?= BASE_URL ?>/waybills/edit.php?id=<?= e((string)$w['id']) ?>">
                  <span class="iconify" data-icon="solar:pen-bold"></span> ویرایش
                </a>
                <form method="post" action="<?= BASE_URL ?>/waybills/delete.php"
                      onsubmit="return confirm('آیا از حذف بارنامه «<?= e($w['waybill_number']) ?>» مطمئن هستید؟');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= e((string)$w['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                    <span class="iconify" data-icon="solar:trash-bin-trash-bold"></span> حذف
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="text-center text-muted p-5">
        <span class="iconify fs-1 d-block mb-2" data-icon="solar:fuel-line-duotone"></span>
        هیچ بارنامه‌ای یافت نشد.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
