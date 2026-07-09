<?php
/**
 * حذف بارنامه سوخت (فقط POST، با تایید CSRF)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

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
    $stmt = db()->prepare('SELECT waybill_number FROM fuel_waybills WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $waybill = $stmt->fetch();

    if (!$waybill) {
        set_flash('danger', 'بارنامه مورد نظر یافت نشد.');
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
