# AG Grid Button Styling Fix

## مشکل

دکمه‌های داخل جدول‌های AG Grid:
- ✗ اندازه یکسانی ندارند
- ✗ ارتفاع نامتناسب است
- ✗ فونت خیلی بزرگ است

## علت

دکمه‌ها از Bootstrap `btn` و `btn-sm` استفاده می‌کنند، اما اندازه Bootstrap برای محل بندی داخل سلول‌های جدول مناسب نیست.

## حل

**فایل:** `assets/css/style.css` (خطوط 465-495)

```css
/* دکمه‌های داخل سلول‌های گرید — استاندارد و یکنواخت */
.seal-ag-grid .ag-cell .btn {
  padding: 0.35rem 0.65rem !important;
  font-size: 0.75rem !important;        /* فونت ریز */
  height: 28px;                         /* ارتفاع یکسان */
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  flex-shrink: 0;
}

.seal-ag-grid .ag-cell .btn.btn-sm {
  padding: 0.35rem 0.65rem !important;
  font-size: 0.75rem !important;
  line-height: 1 !important;
}

.seal-ag-grid .ag-cell .btn .iconify {
  font-size: 0.75rem;
  width: 0.85rem;
  height: 0.85rem;
}

/* فاصله بین دکمه‌های متوالی در یک سلول */
.seal-ag-grid .ag-cell .d-flex.gap-1 {
  gap: 0.5rem !important;
}
```

## تغییرات اعمال‌شده

| خصوصیت | قبل | بعد | هدف |
|-------|-----|-----|-----|
| Font Size | Bootstrap default | `0.75rem` | فونت ریز‌تر |
| Height | متفاوت | `28px` | ارتفاع یکسان |
| Padding | `0.375rem 0.75rem` | `0.35rem 0.65rem` | فشرده‌تر |
| Icon Size | بزرگ | `0.75rem` | متناسب |
| Gap | `0.25rem` | `0.5rem` | فاصله مناسب |

## نتیجه

✓ **دکمه‌ها اکنون:**
- ارتفاع یکسان دارند (28px)
- فونت ریز است (0.75rem = 12px)
- با یکدیگر هماهنگ هستند
- فضای کمتری در سلول‌ها اشغال می‌کنند

## صفحات تحت تأثیر

تمام صفحات list:
- `waybills/list.php`
- `waybills/my_waybills.php`
- `locations/list.php`
- `seals/list.php`
- `users/list.php`
- `regions/list.php`

## تست‌کردن

```
1. به یکی از صفحات list برید
2. دکمه‌های داخل جدول را مشاهده کنید
3. باید:
   - همه دکمه‌ها ارتفاع یکسان داشته باشند ✓
   - فونت ریز باشد ✓
   - فاصله‌ی مناسب بین دکمه‌ها باشد ✓
```

## نکات فنی

### !important استفاده
استفاده از `!important` برای اطمینان از اینکه این تنظیمات Bootstrap defaults را override کنند.

### Flex Properties
```css
display: inline-flex;     /* دکمه‌های inline */
align-items: center;      /* متن و icon عمودی مرکز */
gap: 0.35rem;            /* فاصله بین icon و text */
flex-shrink: 0;          /* جلوگیری از فشردگی بیش‌ازحد */
```

### Icon Sizing
```css
.iconify {
  font-size: 0.75rem;    /* برابر دکمه */
  width: 0.85rem;        /* مربع */
  height: 0.85rem;
}
```
