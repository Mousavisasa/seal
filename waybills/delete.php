<?php
/**
 * حذف بارنامه سوخت (فقط POST، با تایید CSRF)
 * کاربر منطقه فقط می‌تواند بارنامه‌ای را حذف کند که مبدا یا مقصد آن در منطقه خودش باشد.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

$myRegionId = session_region_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/waybills/list.php');
    exit;
}

if (!verify_csrf()) {
    set_flash('danger', 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.');
    header('Location: ' . BASE_URL . '/waybills/list.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'بارنامه مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/waybills/list.php');
    exit;
}

try {
    $stmt = db()->prepare(
        'SELECT w.waybill_number, ol.region_id AS origin_region_id, dl.region_id AS destination_region_id
         FROM fuel_waybills w
         INNER JOIN locations ol ON ol.id = w.origin_location_id
         INNER JOIN locations dl ON dl.id = w.destination_location_id
         WHERE w.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $waybill = $stmt->fetch();

    if (!$waybill) {
        set_flash('danger', 'بارنامه مورد نظر یافت نشد.');
    } elseif ($myRegionId !== null
        && (int)$waybill['origin_region_id'] !== $myRegionId
        && (int)$waybill['destination_region_id'] !== $myRegionId
    ) {
        set_flash('danger', 'شما مجاز به حذف این بارنامه نیستید.');
    } else {
        $stmt = db()->prepare('DELETE FROM fuel_waybills WHERE id = ?');
        $stmt->execute([$id]);
        set_flash('success', 'بارنامه «' . $waybill['waybill_number'] . '» با موفقیت حذف شد.');
    }
} catch (PDOException $e) {
    error_log('Delete waybill error: ' . $e->getMessage());
    set_flash('danger', 'خطایی در حذف بارنامه رخ داد. لطفاً دوباره تلاش کنید.');
}

header('Location: ' . BASE_URL . '/waybills/list.php');
exit;
