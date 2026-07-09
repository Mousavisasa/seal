<?php
/**
 * تبدیل تاریخ میلادی/شمسی — بدون نیاز به افزونه یا کتابخانه خارجی
 * استفاده: در ثبت/ویرایش، تاریخ شمسی از کاربر گرفته و به میلادی (Y-m-d) تبدیل و ذخیره می‌شود.
 * در نمایش، تاریخ میلادی ذخیره‌شده به شمسی تبدیل می‌شود.
 */

/** تبدیل تاریخ شمسی (jY, jM, jD) به میلادی [Y, M, D] */
function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + ((int)($jy / 33) * 8) + (int)((($jy % 33) + 3) / 4) + $jd
        + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);

    $gy = 400 * (int)($days / 146097);
    $days %= 146097;

    if ($days > 36524) {
        $gy += 100 * (int)(--$days / 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }

    $gy += 4 * (int)($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    $gd = $days + 1;
    $monthDays = [31, ((($gy % 4 === 0) && ($gy % 100 !== 0)) || ($gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    $gm = 0;
    for ($i = 0; $i < 12; $i++) {
        if ($gd <= $monthDays[$i]) {
            $gm = $i + 1;
            break;
        }
        $gd -= $monthDays[$i];
    }

    return [$gy, $gm, $gd];
}

/** تبدیل تاریخ میلادی (gY, gM, gD) به شمسی [Y, M, D] */
function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $gDaysCumulative = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }

    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100))
        + ((int)(($gy2 + 399) / 400)) - 80 + $gd + $gDaysCumulative[$gm - 1];

    $jy += 33 * (int)($days / 12053);
    $days %= 12053;

    $jy += 4 * (int)($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return [$jy, $jm, $jd];
}

/** تبدیل تاریخ میلادی Y-m-d به رشته شمسی Y/m/d برای نمایش */
function to_jalali_display(?string $gregorianDate): string
{
    if (!$gregorianDate || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $gregorianDate, $m)) {
        return '';
    }
    [$jy, $jm, $jd] = gregorian_to_jalali((int)$m[1], (int)$m[2], (int)$m[3]);
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

/** تبدیل رشته شمسی (Y/m/d یا Y-m-d، ارقام فارسی هم پذیرفته می‌شود) به میلادی Y-m-d برای ذخیره در دیتابیس */
function jalali_to_gregorian_string(string $jalaliDate): ?string
{
    $jalaliDate = normalize_digits($jalaliDate);
    $jalaliDate = str_replace('-', '/', $jalaliDate);

    if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $jalaliDate, $m)) {
        return null;
    }

    $jy = (int)$m[1];
    $jm = (int)$m[2];
    $jd = (int)$m[3];

    if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
        return null;
    }

    [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd);
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

/** بررسی معتبربودن تاریخ شمسی ورودی کاربر */
function is_valid_jalali_date(string $jalaliDate): bool
{
    return jalali_to_gregorian_string($jalaliDate) !== null;
}

/** امروز به تاریخ شمسی، فرمت Y/m/d (برای مقدار پیش‌فرض فرم) */
function today_jalali(): string
{
    [$gy, $gm, $gd] = explode('-', date('Y-m-d'));
    [$jy, $jm, $jd] = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd);
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}
