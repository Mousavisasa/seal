-- =====================================================
-- سامانه مدیریت کاربران - فایل پایگاه داده
-- اجرا در phpMyAdmin یا خط فرمان MySQL
-- =====================================================

CREATE DATABASE IF NOT EXISTS `user_panel`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `user_panel`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `national_code` VARCHAR(10) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `user_type` ENUM('admin','driver','operator','region') NOT NULL DEFAULT 'driver',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_national_code` (`national_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- کاربر ادمین پیش‌فرض
-- نام کاربری: 1111111111
-- رمز عبور:  Admin@123
-- هش زیر با تابع crypt ساخته شده و با password_verify سازگار است
-- برای ساخت هش جدید می‌توانید فایل install.php را یک بار اجرا کنید
INSERT INTO `users` (`national_code`, `first_name`, `last_name`, `password`, `user_type`)
VALUES (
  '1111111111',
  'مدیر',
  'سیستم',
  '$6$seedadminsalt$5XijZnjPea8KqKpz3rGJ9vsQkFtlkgFRS6Josf3ejEtMpWtZSjdPOm8BhFpDBJEw9TGHkQYOcAedkGym1h2cy/',
  'admin'
);
