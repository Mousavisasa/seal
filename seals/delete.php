<?php
/**
 * حذف پلمپ (فقط POST، با تایید CSRF) — فقط ادمین
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_seals_master_access();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/seals/list.php');
    exit;
}

if (!verify_csrf()) {
    set_flash('danger', 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.');
    header('Location: ' . BASE_URL . '/seals/list.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'پلمپ مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/seals/list.php');
    exit;
}

try {
    $stmt = db()->prepare('SELECT seal_id FROM seals WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $seal = $stmt->fetch();

    if (!$seal) {
        set_flash('danger', 'پلمپ مورد نظر یافت نشد.');
    } else {
        $del = db()->prepare('DELETE FROM seals WHERE id = ?');
        $del->execute([$id]);
        set_flash('success', 'پلمپ «' . $seal['seal_id'] . '» با موفقیت حذف شد.');
    }
} catch (PDOException $e) {
    error_log('Delete seal error: ' . $e->getMessage());
    set_flash('danger', 'این پلمپ دارای سوابق حرکتی است و قابل حذف نیست.');
}

header('Location: ' . BASE_URL . '/seals/list.php');
exit;
