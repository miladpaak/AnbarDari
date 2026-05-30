<?php $title = 'رسید/حواله چاپی'; if (!$row) { echo '<div class="card">یافت نشد.</div>'; return; } ?>
<div class="card">
    <h2>شماره ثبت: <?= e($row['reference_no']) ?></h2>
    <div class="barcode-card" id="receipt-barcode-card">
        <button type="button" class="barcode-trigger" data-barcode-toggle aria-label="نمایش دکمه‌های بارکد">
            <span id="receipt-barcode"><?= Barcode::svg($row['reference_no']) ?></span>
        </button>
        <div class="barcode-actions no-print">
            <button class="btn secondary" data-download-svg="#receipt-barcode svg" data-filename="<?= e($row['reference_no']) ?>">دانلود بارکد</button>
            <button class="btn light" data-print-svg="#receipt-barcode svg">پرینت بارکد</button>
        </div>
    </div>
    <p>کالا: <strong><?= e($row['item_name'] . ' - ' . $row['sku']) ?></strong></p>
    <p>نوع: <?= e($row['type']) ?> | مقدار: <?= e(moneyless_number($row['quantity'])) ?></p>
    <p>از انبار: <?= e($row['from_name'] ?: '-') ?> | به انبار: <?= e($row['to_name'] ?: '-') ?></p>
    <p>ثبت‌کننده: <?= e($row['user_name']) ?> | تاریخ: <?= e(jalali_like_datetime($row['created_at'])) ?></p>
    <p>توضیحات: <?= e($row['notes']) ?></p>
    <div class="actions no-print">
        <button class="btn" onclick="window.print()">چاپ رسید</button>
        <a class="btn light" href="<?= e(base_url('movement?type=in')) ?>">ثبت جدید</a>
    </div>
</div>
