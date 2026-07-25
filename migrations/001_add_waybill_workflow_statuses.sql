-- =====================================================
-- Migration: اضافه کردن وضعیت‌های جدید برای جریان کاری بارنامه
-- تاریخ: 2026-07-25
-- توضیح: اضافه کردن وضعیت‌های "بارگیری شده" و "پایان پیمایش"
-- =====================================================

-- ستون‌های جدید برای ثبت زمان تایید‌های متصدی
ALTER TABLE `fuel_waybills` 
ADD COLUMN `origin_operator_approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `trip_ended_at`,
ADD COLUMN `destination_operator_delivered_at` TIMESTAMP NULL DEFAULT NULL AFTER `origin_operator_approved_at`,
ADD INDEX `idx_waybill_origin_approved` (`origin_operator_approved_at`),
ADD INDEX `idx_waybill_dest_delivered` (`destination_operator_delivered_at`);

-- تغییر ENUM برای send_status
ALTER TABLE `fuel_waybills`
MODIFY COLUMN `send_status` ENUM('ثبت شده','بارگیری شده','ارسال شده','پایان پیمایش','تحویل شده','لغو شده') 
NOT NULL DEFAULT 'ثبت شده';

-- ایجاد trigger برای ثبت کننده‌ی updated_at خودکار
-- توجه: Trigger ممکن است قبلاً وجود داشته باشد
DROP TRIGGER IF EXISTS `update_fuel_waybills_timestamp`;
CREATE TRIGGER `update_fuel_waybills_timestamp`
BEFORE UPDATE ON `fuel_waybills`
FOR EACH ROW
BEGIN
    SET NEW.`updated_at` = NOW();
END;
