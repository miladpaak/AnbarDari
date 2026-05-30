<?php $title = 'تاریخچه ویرایش و حذف کالا'; ?>
<div class="card">
    <h2>گزارش کارهای مدیر انبار</h2>
    <p class="muted">این بخش فقط برای ادمین اصلی نمایش داده می‌شود و ویرایش/حذف کالا توسط مدیر انبار را ثبت می‌کند.</p>
    <div class="table-wrap">
        <table class="table">
            <tr>
                <th>تاریخ</th>
                <th>کاربر</th>
                <th>عملیات</th>
                <th>کالا</th>
                <th>IP</th>
                <th>قبل از تغییر</th>
                <th>بعد از تغییر</th>
            </tr>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e(jalali_like_datetime($row['created_at'])) ?></td>
                    <td><?= e(($row['user_name'] ?: '-') . ($row['username'] ? ' (' . $row['username'] . ')' : '')) ?></td>
                    <td><span class="badge <?= $row['action'] === 'edit_item' ? 'warn' : 'danger' ?>"><?= e($row['action']) ?></span></td>
                    <td><?= e(($row['item_name'] ?: 'کالای حذف‌شده') . ($row['sku'] ? ' - ' . $row['sku'] : '')) ?></td>
                    <td><?= e($row['ip_address']) ?></td>
                    <td><pre class="audit-json"><?= e($row['old_data']) ?></pre></td>
                    <td><pre class="audit-json"><?= e($row['new_data']) ?></pre></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
