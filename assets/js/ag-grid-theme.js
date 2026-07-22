/* تم اختصاصی AG Grid — برگرفته از AG_grid_template/ag-grid-theme-builder.js (Theming API) */
(function (global) {
  if (typeof agGrid === 'undefined' || !agGrid.themeQuartz) return;

  global.sealAgGridTheme = agGrid.themeQuartz.withParams({
    accentColor: '#6C08D1',
    backgroundColor: '#ffffff',
    borderRadius: 8,
    browserColorScheme: 'light',
    columnBorder: true,
    fontFamily: 'inherit',
    foregroundColor: 'rgb(46, 55, 66)',
    headerBackgroundColor: '#E8E8E8',
    headerFontWeight: 600,
    headerTextColor: '#6E6E6E',
    headerVerticalPaddingScale: 0.7,
    oddRowBackgroundColor: '#F1F1F1',
    rowBorder: true,
    sidePanelBorder: true,
    spacing: '8.2px',
    wrapperBorder: true,
    wrapperBorderRadius: 8
  });
})(window);
