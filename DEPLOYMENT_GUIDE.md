# راهنمای استقرار پروژه (Deployment Guide)

## ✅ مشکل حل شد

پروژه اکنون **هر جایی** قرار بگیرد بدون نیاز به تغییر کد:
- ✓ `/seal`
- ✓ `/app/myapp`  
- ✓ `/`  (root)
- ✓ یا هر مسیر دیگری

## تغییر انجام‌شده

### `config/config.php` (خطوط 28-45)

```php
if (!defined('BASE_URL')) {
    $project_root = realpath(dirname(__DIR__));
    $document_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    
    if ($document_root && $project_root) {
        $relative_path = str_replace('\\', '/', substr($project_root, strlen($document_root)));
        define('BASE_URL', $relative_path ?: '');
    } else {
        $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        define('BASE_URL', $script_dir !== '/' ? rtrim($script_dir, '/') : '');
    }
}
```

## نحوه کار

۱. **مسیر فیزیکی پروژه:** `realpath(dirname(__DIR__))`
   - `__DIR__` = `/seal/config`
   - `dirname(__DIR__)` = `/seal`

۲. **مسیر document root:** `$_SERVER['DOCUMENT_ROOT']`
   - مثال: `/xampp/htdocs`

۳. **محاسبه BASE_URL:** تفریق document_root از project_root
   - `/seal` - `/xampp/htdocs` = `/seal` ✓

۴. **Windows سازگاری:** تبدیل backslash به forward slash

## نمونه‌های عملی

| موقعیت | DOCUMENT_ROOT | Project Root | BASE_URL |
|--------|--------------|--------------|----------|
| `/seal` | `/xampp/htdocs` | `/xampp/htdocs/seal` | `/seal` |
| `/app/myapp` | `/xampp/htdocs` | `/xampp/htdocs/app/myapp` | `/app/myapp` |
| Root `/` | `/xampp/htdocs` | `/xampp/htdocs` | `` (خالی) |

## بخش‌های بررسی‌شده ✓

- ✓ HTML links و navigation
- ✓ Form actions (حتی از nested pages مثل `/dashboard/admin.php`)
- ✓ CSS/JS assets (حتی از subdirectories)
- ✓ Image paths
- ✓ API endpoints
- ✓ Header redirects
- ✓ File includes (`__DIR__` based)

## تست کردن

### روش ۱: ورود به صفحه
```
http://localhost/seal/dashboard/admin.php
```
- کنسول Developer Tools باید بدون 404 errors باشد
- تمام CSS و JS فایل‌ها بارگذاری شوند

### روش ۲: صفحات مختلف
- `http://localhost/seal/index.php` ✓
- `http://localhost/seal/login.php` ✓  
- `http://localhost/seal/dashboard/admin.php` ✓
- `http://localhost/seal/users/list.php` ✓

## استقرار در سرور

```bash
# کپی کنید هر جایی که بخواهید
cp -r seal /var/www/html/myapp/

# یا
cp -r seal /opt/xampp/htdocs/

# یا
cp -r seal /path/to/anywhere/
```

**هیچ فایلی نیاز به تغییر ندارد!**

## اگر مشکلی پیش آمد

### 404 errors برای CSS/JS
```php
// در یک صفحه آزمایش بنویسید:
echo "BASE_URL: " . BASE_URL;
```
مطمئن شوید که خروجی مقدار صحیح است.

### Redirects کار نمی‌کنند
تمام `header('Location:` استفاده‌ای شامل `BASE_URL` هستند ✓

### Relative includes شکست می‌خورند
تمام includes از `__DIR__` استفاده می‌کنند ✓
