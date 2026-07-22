/* تابع کمکی مشترک برای رسم جدول‌های AG Grid در صفحات فهرست (فارسی/راست‌به‌چپ) */
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
    lastPage: 'صفحه آخر'
  };

  // چاپ یک جدول AG Grid داخل کانتینر مشخص‌شده.
  // columnDefs: هر ستون می‌تواند html:true داشته باشد تا مقدار سلول به‌صورت HTML رندر شود (برای بج‌ها و دکمه‌ها)
  global.sealInitDataGrid = function (containerId, columnDefs, rowData, opts) {
    var el = document.getElementById(containerId);
    if (!el || typeof agGrid === 'undefined') return null;

    columnDefs.forEach(function (col) {
      if (col.html) {
        col.cellRenderer = function (params) { return params.value; };
      }
    });

    var pageSize = (opts && opts.pageSize) || 15;
    var gridOptions = Object.assign({
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

    return agGrid.createGrid(el, gridOptions);
  };
})(window);
