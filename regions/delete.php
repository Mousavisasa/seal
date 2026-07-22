<?php
/**
 * حذف منطقه (فقط POST، با تایید CSRF) — ادمین یا منطقه
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_waybill_access();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/regions/list.php');
    exit;
}

if (!verify_csrf()) {
    set_flash('danger', 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.');
    header('Location: ' . BASE_URL . '/regions/list.php');
    exit;
}

$code = (int)($_POST['region_code'] ?? 0);

if ($code <= 0) {
    set_flash('danger', 'منطقه مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/regions/list.php');
    exit;
}

try {
    $stmt = db()->prepare('SELECT region_name FROM regions WHERE region_code = ? LIMIT 1');
    $stmt->execute([$code]);
    $region = $stmt->fetch();

    if (!$region) {
        set_flash('danger', 'منطقه مورد نظر یافت نشد.');
    } else {
        $del = db()->prepare('DELETE FROM regions WHERE region_code = ?');
        $del->execute([$code]);
        set_flash('success', 'منطقه «' . $region['region_name'] . '» با موفقیت حذف شد.');
    }
} catch (PDOException $e) {
    error_log('Delete region error: ' . $e->getMessage());
    set_flash('danger', 'این منطقه دارای کاربر، مکان یا پلمپ وابسته است و قابل حذف نیست.');
}

header('Location: ' . BASE_URL . '/regions/list.php');
exit;
