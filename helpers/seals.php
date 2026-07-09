<?php
/**
 * توابع کمکی ماژول انبارداری پلمپ
 */

/** کلاس CSS نشان وضعیت پلمپ */
function seal_status_class(string $status): string
{
    $map = [
        'در انبار مرکزی' => 'status-registered',
        'در انبار منطقه' => 'status-sent',
        'الصاق شده'      => 'status-delivered',
        'باطل شده'       => 'status-cancelled',
        'مفقود شده'      => 'status-cancelled',
    ];
    return $map[$status] ?? '';
}

/** ثبت یک رخداد در تاریخچه پلمپ (seal_movements) */
function log_seal_movement(
    int $sealId,
    string $action,
    ?string $fromStatus,
    string $toStatus,
    ?int $regionId,
    ?int $waybillId,
    int $performedBy,
    ?string $note = null
): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO seal_movements
                (seal_id, action, from_status, to_status, region_id, fuel_waybill_id, performed_by, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$sealId, $action, $fromStatus, $toStatus, $regionId, $waybillId, $performedBy, $note]);
    } catch (PDOException $e) {
        error_log('Seal movement log error: ' . $e->getMessage());
    }
}
