<?php
/**
 * سربرگ مشترک صفحات پنل (بعد از ورود)
 * قبل از include این فایل، متغیر $page_title و $active را تعریف کنید.
 */
require_once __DIR__ . '/../helpers/auth.php';
$page_title = $page_title ?? APP_NAME;
$active = $active ?? '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
</head>
<body class="panel-body">

<!-- نوار بالایی موبایل -->
<nav class="navbar panel-topbar d-lg-none px-3">
  <button class="btn btn-icon" id="sidebarToggle" aria-label="باز و بسته کردن منو">
    <span class="iconify fs-3" data-icon="solar:hamburger-menu-linear"></span>
  </button>
  <span class="navbar-brand fw-bold mb-0"><?= e(APP_NAME) ?></span>
</nav>

<div class="panel-wrapper">
  <!-- منوی کناری -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <span class="brand-mark"><span class="iconify" data-icon="solar:users-group-two-rounded-bold"></span></span>
      <div>
        <div class="brand-title"><?= e(APP_NAME) ?></div>
        <div class="brand-sub">پنل مدیریت</div>
      </div>
    </div>

    <div class="sidebar-user">
      <span class="iconify fs-4" data-icon="solar:user-circle-bold"></span>
      <div class="small">
        <div class="fw-bold"><?= e($_SESSION['full_name'] ?? '') ?></div>
        <div class="text-dim"><?= e(user_type_label($_SESSION['user_type'] ?? '')) ?></div>
      </div>
    </div>

    <ul class="sidebar-nav">
      <li>
        <a href="<?= BASE_URL ?>/dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:widget-5-bold"></span> داشبورد
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>/users/list.php" class="<?= $active === 'list' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:users-group-rounded-bold"></span> فهرست کاربران
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>/users/create.php" class="<?= $active === 'create' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:user-plus-rounded-bold"></span> ایجاد کاربر
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>/api_test.php" class="<?= $active === 'api' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:code-square-bold"></span> تست وب‌سرویس
        </a>
      </li>
      <li class="mt-auto">
        <a href="<?= BASE_URL ?>/logout.php" class="text-danger-link">
          <span class="iconify" data-icon="solar:logout-2-bold"></span> خروج
        </a>
      </li>
    </ul>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <!-- محتوای اصلی -->
  <main class="panel-content">
