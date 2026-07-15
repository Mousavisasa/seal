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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js"></script>
<script>window.MAPIR_API_KEY = <?= json_encode(MAPIR_API_KEY) ?>;</script>
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
      <span class="brand-mark"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 640 640">
	<path fill="currentColor" d="M288 96h-76.3c-29.4 0-55.1 20.1-62.1 48.6L65.4 484.5C57.9 514.7 80.8 544 112 544h175.9v-64c0-17.7 14.3-32 32-32c6.1 0 11.8 1.7 16.7 4.7c2.8-23.9 14.3-45.1 31.4-60.3V368c0-70.7 57.3-128 128-128c6.2 0 12.4.4 18.4 1.3l-23.9-96.7c-7.1-28.5-32.7-48.6-62.2-48.6h-76.4v64c0 17.7-14.3 32-32 32s-32-14.3-32-32V96zm64 192v64c0 17.7-14.3 32-32 32s-32-14.3-32-32v-64c0-17.7 14.3-32 32-32s32 14.3 32 32m176 80.1V416h-64v-47.9c0-17.7 14.3-32 32-32s32 14.3 32 32M384 464v96c0 26.5 21.5 48 48 48h128c26.5 0 48-21.5 48-48v-96c0-20.9-13.4-38.7-32-45.3v-50.6c0-44.2-35.8-80-80-80s-80 35.8-80 80v50.6c-18.6 6.6-32 24.4-32 45.3"></path>
</svg></span>
      <div>
        <div class="brand-title"><?= e(APP_NAME) ?></div>
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
        <a href="<?= BASE_URL ?>/<?= e(redirect_path_for_role($_SESSION['user_type'] ?? '')) ?>" class="<?= $active === 'dashboard' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:widget-5-bold"></span> داشبورد
        </a>
      </li>

      <?php if (is_admin()): ?>
      <li>
        <a href="<?= BASE_URL ?>/users/list.php" class="<?= $active === 'users-list' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:users-group-rounded-bold-duotone"></span> مدیریت کاربران
        </a>
      </li>
      <?php endif; ?>

      <?php if (can_access_waybill_module()): ?>
      <li>
        <a href="<?= BASE_URL ?>/regions/list.php" class="<?= $active === 'regions' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:point-on-map-perspective-bold-duotone"></span> مدیریت مناطق
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>/locations/list.php" class="<?= $active === 'locations' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:signpost-2-bold-duotone"></span> مدیریت مبادی و مقاصد
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>/waybills/list.php" class="<?= $active === 'waybills' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:fuel-bold"></span> مدیریت بارنامه سوخت
        </a>
      </li>
      <?php endif; ?>

      <?php if (is_admin() || is_region()): ?>
      <li>
        <a href="<?= BASE_URL ?>/seals/list.php" class="<?= $active === 'seals' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:lock-password-unlocked-bold-duotone"></span> فهرست پلمپ ها
        </a>
      </li>
      <?php endif; ?>

      <?php if (is_operator()): ?>
      <li>
        <a href="<?= BASE_URL ?>/waybills/my_waybills.php" class="<?= $active === 'my-waybills' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:fuel-bold"></span> بارنامه‌های من
        </a>
      </li>
      <?php endif; ?>

      <?php if (is_driver()): ?>
      <li>
        <a href="<?= BASE_URL ?>/waybills/my_trips.php" class="<?= $active === 'my-trips' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:bus-bold"></span> سفرهای من
        </a>
      </li>
      <?php endif; ?>

      <?php if (is_admin()): ?>
      <li>
        <a href="<?= BASE_URL ?>/api_test.php" class="<?= $active === 'api' ? 'active' : '' ?>">
          <span class="iconify" data-icon="solar:chat-square-code-bold-duotone"></span> تست وب‌سرویس
        </a>
      </li>
      <?php endif; ?>

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
