import { themeQuartz } from 'ag-grid-community';

// to use myTheme in an application, pass it to the theme grid option
export const myTheme = themeQuartz
    .withParams({
        accentColor: "#6C08D1",
        backgroundColor: "#ffffff",
        borderRadius: 8,
        browserColorScheme: "light",
        columnBorder: true,
        fontFamily: "inherit",
        foregroundColor: "rgb(46, 55, 66)",
        headerBackgroundColor: "#E8E8E8",
        headerFontWeight: 600,
        headerTextColor: "#6E6E6E",
        headerVerticalPaddingScale: 0.7,
        oddRowBackgroundColor: "#F1F1F1",
        rowBorder: true,
        sidePanelBorder: true,
        spacing: "8.2px",
        wrapperBorder: true,
        wrapperBorderRadius: 8
    });
