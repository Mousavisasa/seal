<?php
/**
 * تابع کمکی مشترک برای رسم نمودار ECharts همراه با جدول عددی زیر آن
 * در تمام داشبوردهای نقش‌محور استفاده می‌شود تا ظاهر و رفتار یکسان باشد.
 */

/**
 * چاپ یک کارت شامل: عنوان + نمودار (div برای ECharts) + جدول عددی مقادیر
 *
 * @param string $chartId       شناسه DOM برای div نمودار (باید یکتا باشد)
 * @param string $title         عنوان کارت
 * @param string $icon          آیکون Iconify برای عنوان
 * @param array  $labels        برچسب‌های محور/دسته (به همان ترتیب values)
 * @param array  $values         مقادیر عددی متناظر با هر برچسب
 * @param string $valueLabel    عنوان ستون مقدار در جدول (مثلاً «تعداد»)
 * @param string $colorAccent   رنگ اصلی هدر کارت (jade|purple)
 */
function render_chart_card_with_table(
    string $chartId,
    string $title,
    string $icon,
    array $labels,
    array $values,
    string $valueLabel = 'تعداد'
): void {
    $total = array_sum($values);
    ?>
    <div class="card panel-card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="iconify fs-5" data-icon="<?= e($icon) ?>"></span>
        <span class="fw-bold"><?= e($title) ?></span>
      </div>
      <div class="card-body">
        <div id="<?= e($chartId) ?>" class="chart-box"></div>
        <div class="table-responsive mt-3">
          <table class="table table-sm chart-data-table mb-0">
            <thead>
              <tr>
                <th>عنوان</th>
                <th class="text-start"><?= e($valueLabel) ?></th>
                <th class="text-start">سهم</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($labels as $i => $label): ?>
                <?php
                  $v = $values[$i] ?? 0;
                  $percent = $total > 0 ? round(($v / $total) * 100, 1) : 0;
                ?>
                <tr>
                  <td><?= e((string)$label) ?></td>
                  <td class="text-start ltr-text fw-bold"><?= e((string)$v) ?></td>
                  <td class="text-start ltr-text text-muted small"><?= e((string)$percent) ?>%</td>
                </tr>
              <?php endforeach; ?>
              <tr class="table-total-row">
                <td class="fw-bold">جمع کل</td>
                <td class="text-start ltr-text fw-bold"><?= e((string)$total) ?></td>
                <td class="text-start ltr-text">100%</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
}
