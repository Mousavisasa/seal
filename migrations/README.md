# Database Migrations

این پوشه شامل تمام SQL migration‌های پروژه است.

## چگونه اجرا کنیم

### روش ۱: phpMyAdmin
1. وارد phpMyAdmin شوید
2. پایگاه‌داده `idtoir_smart_seal` را انتخاب کنید
3. به تب "SQL" بروید
4. محتوای فایل migration را کپی کنید
5. کلیک "Go" کنید

### روش ۲: Command Line (MySQL)
```bash
mysql -h localhost -u root -p idtoir_smart_seal < 001_add_waybill_workflow_statuses.sql
```

### روش ۳: PHP Script (اختیاری)
```php
$sql = file_get_contents('001_add_waybill_workflow_statuses.sql');
db()->exec($sql);
```

## Migrations موجود

### 001_add_waybill_workflow_statuses.sql
- **توضیح:** اضافه کردن وضعیت‌های جدید برای جریان کاری بارنامه
- **تاریخ:** 2026-07-25
- **تغییرات:**
  - اضافه کردن ستون `origin_operator_approved_at` برای ثبت زمان تایید متصدی مبدا
  - اضافه کردن ستون `destination_operator_delivered_at` برای ثبت زمان تایید متصدی مقصد
  - تغییر ENUM `send_status` برای پذیرش وضعیت‌های جدید:
    - `بارگیری شده` (Loading)
    - `پایان پیمایش` (Trip Completed)

## توجهات مهم

1. **بکاپ:** قبل از اجرای migration، یک بکاپ از پایگاه‌داده خود بگیرید
2. **ترتیب:** Migration‌ها باید به ترتیب عددی اجرا شوند
3. **تست:** در محیط تجربی اول تست کنید

## FAQ

**سوال:** اگر migration با خطا مواجه شود چه کنیم؟
**جواب:** بکاپ را بازیابی کنید و مشکل را بررسی کنید

**سوال:** آیا می‌توان migration را بازگرداند؟
**جواب:** نه، migration‌ها یک‌طرفه هستند. بکاپ نگاه‌دارید.
