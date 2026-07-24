# خلاصه تغییرات (Summary of Changes)

## مشکلات حل‌شده

### 1️⃣ BASE_URL تغییر (Fixed ✓)

**مشکل:** وقتی از صفحات nested مثل `/seal/dashboard/admin.php` درخواست می‌شد، CSS/JS فایل‌ها 404 می‌خوردند.

**علت:** `BASE_URL` از `dirname($_SERVER['SCRIPT_NAME'])` محاسبه می‌شد که به مسیر صفحه‌ی فعلی اشاره می‌کرد، نه root پروژه.

**راه‌حل:** `BASE_URL` اکنون از مسیر فیزیکی پروژه محاسبه می‌شود:
- **فایل:** `config/config.php` (خطوط 28-45)
- **روش:** `realpath(dirname(__DIR__))` + `$_SERVER['DOCUMENT_ROOT']`
- **نتیجه:** `BASE_URL` همیشه root پروژه است، صرف‌نظر از صفحه‌ی جاری

### 2️⃣ AG Grid HTML Rendering (Fixed ✓)

**مشکل:** AG Grid error درباره invalid property `html`:
```
invalid colDef property 'html' did you mean any of these: 
headerTooltip, headerComponentParams, ...
```

**علت:** AG Grid نسخه جدید دیگر از property `html` پشتیبانی نمی‌کند.

**راه‌حل:** تبدیل `html: true` به `cellRenderer` درست:
- **فایل:** `assets/js/ag-grid-helpers.js` (خطوط 37-47)
- **روش:** `cellRenderer` یک div با `innerHTML` برمی‌گرداند
- **نتیجه:** HTML محتوا (buttons, forms, badges) درست رندر می‌شود

### 3️⃣ AG Grid Button Styling (Fixed ✓)

**مشکل:** دکمه‌های داخل جدول‌ها:
- ✗ اندازه یکسانی ندارند
- ✗ ارتفاع نامتناسب است
- ✗ فونت خیلی بزرگ است

**علت:** دکمه‌ها از Bootstrap `btn` و `btn-sm` استفاده می‌کنند، اما Bootstrap styling برای سلول‌های جدول مناسب نیست.

**راه‌حل:** CSS rules خاص برای دکمه‌های داخل grid cells:
- **فایل:** `assets/css/style.css` (خطوط 465-495)
- **تغییرات:** 
  - Font size: `0.75rem` (ریزتر)
  - Height: `28px` (یکسان)
  - Padding: `0.35rem 0.65rem` (فشرده‌تر)
- **نتیجه:** دکمه‌های یکسان و متناسب

## فایل‌های تغییر‌یافته

### 1. `config/config.php`
```diff
- define('BASE_URL', '/seal');
+ if (!defined('BASE_URL')) {
+     $project_root = realpath(dirname(__DIR__));
+     $document_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
+     if ($document_root && $project_root) {
+         $relative_path = str_replace('\\', '/', substr($project_root, strlen($document_root)));
+         define('BASE_URL', $relative_path ?: '');
+     } else {
+         $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
+         define('BASE_URL', $script_dir !== '/' ? rtrim($script_dir, '/') : '');
+     }
+ }
```

### 2. `assets/js/ag-grid-helpers.js`
```diff
  if (col.html) {
-   col.cellRenderer = function (params) { return params.value; };
+   col.cellRenderer = function (params) {
+     var div = document.createElement('div');
+     if (params.value) {
+       div.innerHTML = params.value;
+     }
+     return div;
+   };
+   delete col.html;
  }
```

### 3. `assets/css/style.css`
```css
/* دکمه‌های داخل سلول‌های گرید — استاندارد و یکنواخت */
.seal-ag-grid .ag-cell .btn {
  padding: 0.35rem 0.65rem !important;
  font-size: 0.75rem !important;
  height: 28px;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  flex-shrink: 0;
}
```

## صفحات تحت تأثیر

### BASE_URL Fix تأثیر می‌گذارد بر:
- ✓ تمام صفحات با CSS/JS assets
- ✓ صفحات nested مثل `/dashboard/*`, `/waybills/*`, `/users/*` و غیره
- ✓ Form actions و redirects
- ✓ API endpoint calls

### AG Grid Fixes تأثیر می‌گذارند بر:
- `waybills/list.php`
- `waybills/my_waybills.php`
- `locations/list.php`
- `seals/list.php`
- `users/list.php`
- `regions/list.php`

## تست‌کردن

### ✓ BASE_URL Tests
```
http://localhost/seal/index.php
http://localhost/seal/login.php
http://localhost/seal/dashboard/admin.php
http://localhost/seal/users/list.php
```
→ CSS/JS بدون 404 errors بارگذاری شود

### ✓ AG Grid HTML Rendering Tests
```
http://localhost/seal/waybills/list.php
http://localhost/seal/users/list.php
http://localhost/seal/locations/list.php
```
→ Developer Console خالی از AG Grid errors باشد  
→ دکمه‌های delete/edit درست رندر شوند

### ✓ Button Styling Tests
```
http://localhost/seal/waybills/list.php
```
→ تمام دکمه‌ها ارتفاع یکسان داشته باشند ✓
→ فونت ریز باشد ✓
→ فاصله‌ی مناسب بین دکمه‌ها باشد ✓

## توثیق

- `DEPLOYMENT_GUIDE.md` - راهنمای استقرار پروژه
- `AG_GRID_FIX_SUMMARY.md` - توضیح تغییر AG Grid HTML Rendering
- `GRID_BUTTON_STYLING_FIX.md` - توضیح تغییر Button Styling

## Notes

1. هیچ تغییری در database schema نلازم
2. هیچ تغییری در دیگر فایل‌های PHP یا JS نلازم
3. پروژه اکنون portable است (هر جای قرار بگیرد کار می‌کند)
4. تمام features قدیمی کار می‌کنند
5. دکمه‌های جدول اکنون متناسب و یکنواخت هستند
