<?php
/**
 * ویرایش منطقه
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$region = null;
$errors = [];

try {
    $stmt = db()->prepare('SELECT * FROM regions WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $region = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Region fetch error: ' . $e->getMessage());
}

if (!$region) {
    set_flash('danger', 'منطقه مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/regions/list.php');
    exit;
}

$old = ['region_code' => $region['region_code'], 'region_name' => $region['region_name']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'نشست شما منقضی شده است. لطفاً فرم را دوباره ارسال کنید.';
    } else {
        $old['region_code'] = trim((string)($_POST['region_code'] ?? ''));
        $old['region_name'] = trim((string)($_POST['region_name'] ?? ''));

        if ($old['region_code'] === '' || mb_strlen($old['region_code']) > 20) {
            $errors[] = 'کد منطقه الزامی است و باید حداکثر ۲۰ کاراکتر باشد.';
        }
        if (mb_strlen($old['region_name']) < 2) {
            $errors[] = 'نام منطقه باید حداقل ۲ حرف باشد.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare('SELECT id FROM regions WHERE region_code = ? AND id <> ? LIMIT 1');
                $stmt->execute([$old['region_code'], $region['id']]);
                if ($stmt->fetch()) {
                    $errors[] = 'منطقه دیگری با این کد قبلاً ثبت شده است.';
                } else {
                    $stmt = db()->prepare('UPDATE regions SET region_code = ?, region_name = ? WHERE id = ?');
                    $stmt->execute([$old['region_code'], $old['region_name'], $region['id']]);
                    set_flash('success', 'منطقه با موفقیت ویرایش شد.');
                    header('Location: ' . BASE_URL . '/regions/list.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Update region error: ' . $e->getMessage());
                $errors[] = 'خطایی در ویرایش منطقه رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

$page_title = 'ویرایش منطقه';
$active = 'regions';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h4 fw-bold mb-1">ویرایش منطقه</h1>
  <p class="text-muted small mb-0">اطلاعات منطقه انتخاب‌شده را ویرایش کنید.</p>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <div class="d-flex align-items-center gap-2 fw-bold mb-2">
      <span class="iconify" data-icon="solar:danger-triangle-bold"></span> فرم دارای خطا است:
    </div>
    <ul class="mb-0 pe-4">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card panel-card">
  <div class="card-body p-4">
    <form method="post" action="<?= BASE_URL ?>/regions/edit.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e((string)$region['id']) ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="region_code">کد منطقه</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:hashtag-square-bold"></span></span>
            <input type="text" class="form-control ltr-text" id="region_code" name="region_code" required maxlength="20"
                   value="<?= e($old['region_code']) ?>">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="region_name">نام منطقه</label>
          <div class="input-group">
            <span class="input-group-text"><span class="iconify" data-icon="solar:map-point-bold"></span></span>
            <input type="text" class="form-control" id="region_name" name="region_name" required minlength="2"
                   value="<?= e($old['region_name']) ?>">
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <span class="iconify" data-icon="solar:diskette-bold"></span> ذخیره تغییرات
        </button>
        <a href="<?= BASE_URL ?>/regions/list.php" class="btn btn-outline-secondary">انصراف</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
