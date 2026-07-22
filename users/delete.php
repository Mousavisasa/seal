<?php
/**
 * حذف کاربر (فقط POST، با تایید CSRF) — فقط ادمین
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/users/list.php');
    exit;
}

if (!verify_csrf()) {
    set_flash('danger', 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.');
    header('Location: ' . BASE_URL . '/users/list.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'کاربر مورد نظر یافت نشد.');
    header('Location: ' . BASE_URL . '/users/list.php');
    exit;
}

if ($id === (int)$_SESSION['user_id']) {
    set_flash('danger', 'شما نمی‌توانید حساب کاربری خودتان را حذف کنید.');
    header('Location: ' . BASE_URL . '/users/list.php');
    exit;
}

try {
    $stmt = db()->prepare('SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        set_flash('danger', 'کاربر مورد نظر یافت نشد.');
    } else {
        $del = db()->prepare('DELETE FROM users WHERE id = ?');
        $del->execute([$id]);
        set_flash('success', 'کاربر «' . $user['first_name'] . ' ' . $user['last_name'] . '» با موفقیت حذف شد.');
    }
} catch (PDOException $e) {
    error_log('Delete user error: ' . $e->getMessage());
    set_flash('danger', 'این کاربر در بارنامه‌ها یا پلمپ‌های ثبت‌شده استفاده شده و قابل حذف نیست.');
}

header('Location: ' . BASE_URL . '/users/list.php');
exit;
