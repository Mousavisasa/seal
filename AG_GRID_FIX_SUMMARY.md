# AG Grid HTML Rendering Fix

## مشکل

AG Grid نسخه جدیدتر دیگر از property `html` در column definitions پشتیبانی نمی‌کند:

```
Error: invalid colDef property 'html' did you mean any of these: 
headerTooltip, headerGroupComponentParams, ...
```

## علت

کد قدیمی سعی می‌کرد از `html: true` استفاده کند تا HTML در سلول‌ها رندر شود، اما AG Grid این را به رسمیت نمی‌شناسد.

## حل

**فایل:** `assets/js/ag-grid-helpers.js` (خطوط 37-47)

### قبل:
```javascript
if (col.html) {
  col.cellRenderer = function (params) { return params.value; };
  // ⚠️ این صرفاً مقدار را برمی‌گرداند، HTML رندر نمی‌کند!
}
```

### بعد:
```javascript
if (col.html) {
  col.cellRenderer = function (params) {
    var div = document.createElement('div');
    if (params.value) {
      div.innerHTML = params.value;  // ✓ HTML رندر می‌شود
    }
    return div;
  };
  delete col.html;  // ✓ Property غیرمعتبر حذف می‌شود
}
```

## نتیجه

✓ AG Grid warning/error نمی‌دهد  
✓ HTML content (buttons, badges, forms) درست رندر می‌شود  
✓ تمام جداول list صفحات کار می‌کنند:
- `/waybills/list.php`
- `/locations/list.php`
- `/seals/list.php`
- `/users/list.php`
- `/regions/list.php`
- و غیره...

## تکنیکی جزئیات

### cellRenderer در AG Grid

AG Grid از `cellRenderer` برای customize کردن محتوای سلول استفاده می‌کند. می‌تواند:

1. **String برگردند:** سادگی
2. **HTML Element برگردند:** برای رندر کردن HTML

```javascript
cellRenderer: function (params) {
  var div = document.createElement('div');
  div.innerHTML = '<button>حذف</button>';
  return div;  // ✓ کار می‌کند
}
```

### innerHTML vs textContent

- `innerHTML`: HTML کد رندر می‌کند → `<button>حذف</button>`
- `textContent`: فقط متن می‌دهد → `&lt;button&gt;حذف&lt;/button&gt;`

ما از `innerHTML` استفاده می‌کنیم تا فرم‌ها و دکمه‌های داخل سلول‌ها کار کنند.

## Affected Pages

تمام صفحات list از `html: true` استفاده می‌کنند:

- `waybills/list.php` - 12 column با html content
- `waybills/my_waybills.php` - 8 columns
- `locations/list.php` - 4 columns
- `seals/list.php` - 5 columns
- `users/list.php` - 4 columns
- `regions/list.php` - 3 columns

## تست

```
بعد از تغییر:
1. به یکی از صفحات list برید
2. کنسول Developer Tools را چک کنید → هیچ error
3. دکمه‌های حذف و ویرایش کار کنند
4. بج‌های وضعیت درست نمایش داده شوند
```
