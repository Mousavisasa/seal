<?php
/**
 * توابع کمکی عمومی: خروجی امن، پیام فلش، اعتبارسنجی کد ملی، CSRF
 */

require_once __DIR__ . '/../config/config.php';

/** خروجی امن HTML */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** ثبت پیام فلش (success | danger | warning | info) */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** دریافت و پاک‌کردن پیام فلش */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** نمایش پیام فلش به‌صورت آلرت بوت‌استرپ */
function render_flash(): string
{
    $flash = get_flash();
    if (!$flash) {
        return '';
    }
    $icons = [
        'success' => 'solar:check-circle-bold',
        'danger'  => 'solar:close-circle-bold',
        'warning' => 'solar:danger-triangle-bold',
        'info'    => 'solar:info-circle-bold',
    ];
    $icon = $icons[$flash['type']] ?? $icons['info'];
    return '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert">'
        . '<span class="iconify fs-5" data-icon="' . $icon . '"></span>'
        . '<div>' . e($flash['message']) . '</div>'
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="بستن"></button>'
        . '</div>';
}

/**
 * تبدیل ارقام فارسی/عربی به انگلیسی
 */
function normalize_digits(string $value): string
{
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($ar, $en, str_replace($fa, $en, trim($value)));
}

/**
 * اعتبارسنجی کد ملی ایران (طول ۱۰ رقم + رقم کنترل)
 */
function is_valid_national_code(string $code): bool
{
    $code = normalize_digits($code);

    if (!preg_match('/^\d{10}$/', $code)) {
        return false;
    }
    if (preg_match('/^(\d)\1{9}$/', $code)) {
        return false; // همه ارقام یکسان
    }

    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int)$code[$i] * (10 - $i);
    }
    $remainder = $sum % 11;
    $check = (int)$code[9];

    return ($remainder < 2 && $check === $remainder)
        || ($remainder >= 2 && $check === 11 - $remainder);
}

/** ساخت/دریافت توکن CSRF */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** فیلد مخفی CSRF برای فرم‌ها */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** بررسی توکن CSRF در درخواست POST */
function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** برچسب فارسی نقش کاربر */
function user_type_label(string $type): string
{
    return USER_TYPES[$type] ?? $type;
}

/** بررسی معتبربودن تاریخ به فرمت Y-m-d */
function is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/** بررسی عدد مثبت (برای مسافت و مقادیر مشابه) */
function is_positive_number($value): bool
{
    return is_numeric($value) && (float)$value > 0;
}

/** کلاس CSS نشان رنگی نوع فرآورده */
function product_badge_class(string $productType): string
{
    return 'product-badge-' . $productType;
}

/** رنگ اختصاصی نوع فرآورده (برای استفاده در نمودارها) */
function product_color(string $productType): string
{
    return PRODUCT_COLORS[$productType] ?? '#c7ccd1';
}

/**
 * ایجاد جدول لاگ وضعیت بارنامه (برای نصب‌های قدیمی که این جدول را ندارند)
 */
function ensure_waybill_status_logs_table(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    db()->exec(
        "CREATE TABLE IF NOT EXISTS `waybill_status_logs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `fuel_waybill_id` INT UNSIGNED NOT NULL,
            `from_status` VARCHAR(50) NULL DEFAULT NULL,
            `to_status` VARCHAR(50) NOT NULL,
            `source_section` VARCHAR(150) NOT NULL,
            `performed_by` INT UNSIGNED NULL DEFAULT NULL,
            `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_waybill_status_logs_waybill` (`fuel_waybill_id`),
            KEY `idx_waybill_status_logs_changed_at` (`changed_at`),
            KEY `idx_waybill_status_logs_performed_by` (`performed_by`),
            CONSTRAINT `fk_waybill_status_logs_waybill` FOREIGN KEY (`fuel_waybill_id`) REFERENCES `fuel_waybills` (`id`)
                ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `fk_waybill_status_logs_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
                ON UPDATE CASCADE ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        $col = db()->query("SHOW COLUMNS FROM `waybill_status_logs` LIKE 'seal_number'")->fetch();
        if (!$col) {
            db()->exec("ALTER TABLE `waybill_status_logs` ADD COLUMN `seal_number` VARCHAR(50) NULL DEFAULT NULL AFTER `to_status`");
        }
    } catch (PDOException $e) {
        error_log('ensure_waybill_status_logs_table seal_number error: ' . $e->getMessage());
    }

    $ensured = true;
}

/**
 * دریافت شماره پلمپِ متصل به بارنامه
 */
function resolve_waybill_seal_number(int $waybillId, ?PDO $pdo = null): ?string
{
    $conn = $pdo ?: db();
    $stmt = $conn->prepare(
        'SELECT s.seal_id
         FROM seals s
         WHERE s.fuel_waybill_id = ?
         LIMIT 1'
    );
    $stmt->execute([$waybillId]);
    $seal = $stmt->fetch();

    if (!$seal || trim((string)($seal['seal_id'] ?? '')) === '') {
        return null;
    }

    return trim((string)$seal['seal_id']);
}

/**
 * ثبت لاگ تغییر وضعیت بارنامه
 */
function log_waybill_status_change(
    int $waybillId,
    ?string $fromStatus,
    string $toStatus,
    string $sourceSection,
    ?int $performedBy = null,
    ?PDO $pdo = null,
    ?string $sealNumber = null
): void {
    $sourceSection = trim($sourceSection);
    if ($sourceSection === '') {
        throw new InvalidArgumentException('sourceSection نمی‌تواند خالی باشد.');
    }

    ensure_waybill_status_logs_table();
    $conn = $pdo ?: db();
    $sealNumber = $sealNumber !== null ? trim($sealNumber) : null;
    if ($sealNumber === '') {
        $sealNumber = null;
    }
    $stmt = $conn->prepare(
        'INSERT INTO waybill_status_logs (fuel_waybill_id, from_status, to_status, seal_number, source_section, performed_by)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $waybillId,
        $fromStatus,
        $toStatus,
        $sealNumber,
        $sourceSection,
        $performedBy !== null ? (int)$performedBy : null,
    ]);
}

/**
 * تغییر وضعیت بارنامه به‌همراه ثبت لاگ (اتمیک)
 */
function update_waybill_status_with_log(
    int $waybillId,
    string $toStatus,
    string $sourceSection,
    ?int $performedBy = null,
    array $extraAssignments = [],
    ?string $sealNumber = null
): void {
    ensure_waybill_status_logs_table();
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT send_status FROM fuel_waybills WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$waybillId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('بارنامه مورد نظر یافت نشد.');
        }

        $fromStatus = $row['send_status'];
        if ($sealNumber === null) {
            $sealNumber = resolve_waybill_seal_number($waybillId, $pdo);
        }
        $setParts = array_merge(['send_status = ?'], $extraAssignments);
        $sql = 'UPDATE fuel_waybills SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $params = [$toStatus, $waybillId];

        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        log_waybill_status_change($waybillId, $fromStatus, $toStatus, $sourceSection, $performedBy, $pdo, $sealNumber);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
