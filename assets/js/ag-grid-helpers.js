/* تابع کمکی مشترک برای رسم جدول‌های AG Grid در صفحات فهرست (فارسی/راست‌به‌راست، جستجو و فیلتر داخلی) */
(function (global) {
  var FA_LOCALE = {
    noRowsToShow: 'داده‌ای برای نمایش وجود ندارد',
    page: 'صفحه',
    of: 'از',
    to: 'تا',
    itemsPerPage: 'ردیف در هر صفحه',
    firstPage: 'صفحه اول',
    previousPage: 'صفحه قبل',
    nextPage: 'صفحه بعد',
    lastPage: 'صفحه آخر',
    filterOoo: 'فیلتر...',
    equals: 'برابر است با',
    notEqual: 'برابر نیست با',
    contains: 'شامل',
    notContains: 'شامل نیست',
    startsWith: 'شروع می‌شود با',
    endsWith: 'پایان می‌یابد با',
    blank: 'خالی',
    notBlank: 'غیرخالی',
    applyFilter: 'اعمال',
    resetFilter: 'پاک‌کردن',
    clearFilter: 'پاک‌کردن',
    selectAll: 'انتخاب همه',
    searchOoo: 'جستجو...'
  };

  // چاپ یک جدول AG Grid داخل کانتینر مشخص‌شده با جستجو/فیلتر داخلی خود AG Grid.
  // columnDefs: هر ستون می‌تواند html:true داشته باشد تا مقدار سلول به‌صورت HTML رندر شود (برای بج‌ها و دکمه‌ها)
  // opts.searchInputId: شناسه اینپوت جستجوی سراسری (اختیاری) — به quickFilter وصل می‌شود
  global.sealInitDataGrid = function (containerId, columnDefs, rowData, opts) {
    var el = document.getElementById(containerId);
    if (!el || typeof agGrid === 'undefined') return null;

    columnDefs.forEach(function (col) {
      if (col.html) {
        col.cellRenderer = function (params) { return params.value; };
      }
      if (col.filter === undefined) {
        col.filter = true;
        col.floatingFilter = true;
      }
    });

    var pageSize = (opts && opts.pageSize) || 15;
    var gridOptions = Object.assign({
      theme: global.sealAgGridTheme || 'legacy',
      enableRtl: true,
      domLayout: 'autoHeight',
      suppressCellFocus: true,
      defaultColDef: {
        resizable: true,
        sortable: true,
        suppressMovable: true
      },
      columnDefs: columnDefs,
      rowData: rowData,
      pagination: rowData.length > pageSize,
      paginationPageSize: pageSize,
      paginationPageSizeSelector: false,
      localeText: FA_LOCALE
    }, opts && opts.gridOptions ? opts.gridOptions : {});

    var api = agGrid.createGrid(el, gridOptions);

    if (opts && opts.searchInputId) {
      var input = document.getElementById(opts.searchInputId);
      if (input) {
        input.addEventListener('input', function () {
          api.setGridOption('quickFilterText', input.value);
        });
      }
    }

    return api;
  };

  // تایید حذف برای فرم‌های تولیدشده داخل سلول‌های HTML جدول (data-delete-form + data-confirm)
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (form && form.matches && form.matches('form[data-delete-form]')) {
      var message = form.getAttribute('data-confirm') || 'آیا از حذف این مورد مطمئن هستید؟';
      if (!confirm(message)) {
        ev.preventDefault();
      }
    }
  });
})(window);
