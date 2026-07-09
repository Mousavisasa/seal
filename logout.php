<?php
/**
 * خروج از حساب و پاک‌کردن سشن
 */
require_once __DIR__ . '/helpers/auth.php';

logout_user();

// شروع سشن جدید فقط برای پیام فلش
session_start();
set_flash('success', 'با موفقیت از حساب خارج شدید.');
header('Location: ' . BASE_URL . '/login.php');
exit;
