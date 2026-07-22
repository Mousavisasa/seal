<?php
/**
 * حذف مبدا/مقصد (فقط POST، با تایید CSRF) — ادمین یا منطقه
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/locations/list.php');
    exit;
}

if (!verify_csrf()) {
    set_flash('danger', 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.');
    header('Location: ' . BASE_URL . '/locations/list.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'مکان مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/locations/list.php');
    exit;
}

$myRegionId = session_region_id();

try {
    $stmt = db()->prepare('SELECT title, region_id FROM locations WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $location = $stmt->fetch();

    if (!$location) {
        set_flash('danger', 'مکان مورد نظر یافت نشد.');
    } elseif ($myRegionId !== null && (int)$location['region_id'] !== $myRegionId) {
        set_flash('danger', 'شما مجاز به حذف این مکان نیستید.');
    } else {
        $del = db()->prepare('DELETE FROM locations WHERE id = ?');
        $del->execute([$id]);
        set_flash('success', 'مکان «' . $location['title'] . '» با موفقیت حذف شد.');
    }
} catch (PDOException $e) {
    error_log('Delete location error: ' . $e->getMessage());
    set_flash('danger', 'این مکان در بارنامه‌های ثبت‌شده استفاده شده و قابل حذف نیست.');
}

header('Location: ' . BASE_URL . '/locations/list.php');
exit;
