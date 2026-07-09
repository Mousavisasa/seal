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

-- =====================================================
-- ماژول بارنامه سوخت: مناطق، مبادی/مقاصد، بارنامه‌ها
-- =====================================================

-- ---------- جدول مناطق ----------
-- region_code خودش کلید اصلی است (کد عددی منطقه)
CREATE TABLE IF NOT EXISTS `regions` (
  `region_code` INT NOT NULL,
  `region_name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`region_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- جدول مبادی/مقاصد ----------
CREATE TABLE IF NOT EXISTS `locations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `location_code` VARCHAR(10) NOT NULL,
  `region_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `lat` FLOAT NOT NULL DEFAULT 0,
  `lon` FLOAT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_location_code` (`location_code`),
  KEY `idx_location_title` (`title`),
  KEY `idx_location_region` (`region_id`),
  CONSTRAINT `fk_location_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`region_code`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- جدول بارنامه‌های سوخت ----------
-- کد منطقه مبدا/مقصد از طریق origin_location_id / destination_location_id → locations.region_id
-- به‌دست می‌آید و نیازی به ستون جداگانه در این جدول نیست.
-- انتخاب متصدی ارسال و راننده حمل‌کننده اختیاری است (NULL مجاز).
CREATE TABLE IF NOT EXISTS `fuel_waybills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `origin_location_id` INT UNSIGNED NOT NULL,
  `destination_location_id` INT UNSIGNED NOT NULL,
  `distance_km` DECIMAL(8,2) NOT NULL,
  `product_type` ENUM('بنزین','نفتگاز','سوپر','نفت') NOT NULL,
  `waybill_number` VARCHAR(50) NOT NULL,
  `issue_date` DATE NOT NULL,
  `send_status` ENUM('ثبت شده','ارسال شده','تحویل شده','لغو شده') NOT NULL DEFAULT 'ثبت شده',
  `sender_operator_user_id` INT UNSIGNED NULL DEFAULT NULL,
  `driver_user_id` INT UNSIGNED NULL DEFAULT NULL,
  `trip_started_at` TIMESTAMP NULL DEFAULT NULL,
  `trip_ended_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_waybill_number` (`waybill_number`),
  KEY `idx_waybill_issue_date` (`issue_date`),
  KEY `idx_waybill_status` (`send_status`),
  KEY `idx_waybill_product` (`product_type`),
  KEY `idx_waybill_origin_loc` (`origin_location_id`),
  KEY `idx_waybill_dest_loc` (`destination_location_id`),
  KEY `idx_waybill_operator` (`sender_operator_user_id`),
  KEY `idx_waybill_driver` (`driver_user_id`),
  KEY `idx_waybill_created_by` (`created_by`),
  CONSTRAINT `fk_waybill_origin_loc` FOREIGN KEY (`origin_location_id`) REFERENCES `locations` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_waybill_dest_loc` FOREIGN KEY (`destination_location_id`) REFERENCES `locations` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_waybill_operator` FOREIGN KEY (`sender_operator_user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_waybill_driver` FOREIGN KEY (`driver_user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_waybill_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_waybill_distance_positive` CHECK (`distance_km` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- داده نمونه: مناطق ----------
INSERT INTO `regions` (`region_code`, `region_name`) VALUES
  (1, 'تهران'),
  (2, 'البرز'),
  (3, 'قم'),
  (4, 'لرستان'),
  (5, 'همدان'),
  (6, 'قزوین'),
  (7, 'زنجان'),
  (8, 'اردبیل'),
  (9, 'مرکزی'),
  (10, 'گلستان'),
  (11, 'چالوس'),
  (12, 'تربت حیدریه'),
  (13, 'فارس'),
  (14, 'کهگیلویه و بویراحمد'),
  (15, 'چهارمحال و بختیاری'),
  (16, 'ساری'),
  (17, 'کردستان'),
  (18, 'یزد'),
  (19, 'هرمزگان'),
  (20, 'ایلام'),
  (21, 'آبادان'),
  (22, 'شاهرود'),
  (23, 'آذربایجان شرقی'),
  (24, 'خراسان شمالی'),
  (25, 'سبزوار'),
  (26, 'ارومیه'),
  (27, 'میاندوآب'),
  (28, 'کرمانشاه'),
  (29, 'گیلان'),
  (30, 'اصفهان'),
  (31, 'اهواز'),
  (32, 'چابهار'),
  (33, 'بوشهر'),
  (34, 'زاهدان'),
  (35, 'خراسان جنوبی'),
  (36, 'کرمان'),
  (37, 'خراسان رضوی');

-- ---------- داده نمونه: مبادی/مقاصد ----------
INSERT INTO `locations` (`location_code`, `region_id`, `title`, `lat`, `lon`) VALUES
  ('LOC-001', 1, 'انبار نفت شهید تندگویان', 35.6892, 51.3890),
  ('LOC-002', 1, 'پایانه سوخت جنوب تهران', 35.6120, 51.4020),
  ('LOC-003', 2, 'انبار نفت کرج', 35.8400, 50.9391),
  ('LOC-004', 13, 'انبار نفت شیراز', 29.5918, 52.5837);

-- ---------- کاربران نمونه برای تست ماژول بارنامه ----------
-- رمز هر دو: Admin@123
INSERT INTO `users` (`national_code`, `first_name`, `last_name`, `password`, `user_type`) VALUES
  ('2222222222', 'رضا', 'متصدی', '$6$seedadminsalt$5XijZnjPea8KqKpz3rGJ9vsQkFtlkgFRS6Josf3ejEtMpWtZSjdPOm8BhFpDBJEw9TGHkQYOcAedkGym1h2cy/', 'operator'),
  ('3333333333', 'حسین', 'راننده', '$6$seedadminsalt$5XijZnjPea8KqKpz3rGJ9vsQkFtlkgFRS6Josf3ejEtMpWtZSjdPOm8BhFpDBJEw9TGHkQYOcAedkGym1h2cy/', 'driver'),
  ('4444444444', 'سارا', 'منطقه‌ای', '$6$seedadminsalt$5XijZnjPea8KqKpz3rGJ9vsQkFtlkgFRS6Josf3ejEtMpWtZSjdPOm8BhFpDBJEw9TGHkQYOcAedkGym1h2cy/', 'region');

-- =====================================================
-- مهاجرت برای نصب‌های قبلی: اگر جدول fuel_waybills از قبل
-- بدون ستون‌های trip_started_at / trip_ended_at ساخته شده،
-- خطوط زیر را یک‌بار اجرا کنید (در صورت نبود ستون‌ها):
-- =====================================================
-- ALTER TABLE `fuel_waybills`
--   ADD COLUMN `trip_started_at` TIMESTAMP NULL DEFAULT NULL AFTER `driver_user_id`,
--   ADD COLUMN `trip_ended_at` TIMESTAMP NULL DEFAULT NULL AFTER `trip_started_at`;


