<?php $title = 'اتصال و همگام‌سازی وردپرس/ووکامرس'; ?>
<div class="card">
    <h2>وضعیت اتصال</h2>
    <?php if ($status['ok']): ?>
        <p><span class="badge ok">متصل</span> <?= e($status['message']) ?> تعداد محصولات قابل مشاهده: <?= e((string) ($status['products'] ?? 0)) ?></p>
    <?php else: ?>
        <p><span class="badge danger">غیرفعال</span> <?= e($status['message']) ?></p>
        <p class="muted">برای فعال‌سازی، بخش <code>wordpress_db</code> را در فایل config.php با مشخصات دیتابیس وردپرس/ووکامرس تکمیل کنید.</p>
    <?php endif; ?>
</div>
<div class="grid">
    <div class="col-6 card">
        <h2>وارد کردن محصولات</h2>
        <p class="muted">محصولات ووکامرس با SKU، نام، قیمت فروش و موجودی وارد یا بروزرسانی می‌شوند. موجودی هر محصول در انبار انتخابی با مقدار موجودی وردپرس تنظیم می‌شود.</p>
        <form method="post" action="<?= e(base_url('accounting/wordpress/import-products')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label>انبار مقصد موجودی</label>
            <select name="warehouse_id" required><option value="">انتخاب کنید</option><?php foreach($warehouses as $w): ?><option value="<?= e($w['id']) ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select>
            <button class="btn" <?= $status['ok'] ? '' : 'disabled' ?>>همگام‌سازی محصولات</button>
        </form>
    </div>
    <div class="col-6 card">
        <h2>وارد کردن فروش‌ها</h2>
        <p class="muted">سفارش‌های در حال پردازش/تکمیل‌شده/در انتظار پرداخت ووکامرس به مشتری، فاکتور فروش، سند حسابداری و کسر خودکار از انبار تبدیل می‌شوند. سفارش‌های قبلاً واردشده دوباره ثبت نمی‌شوند.</p>
        <form method="post" action="<?= e(base_url('accounting/wordpress/import-orders')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label>انبار کسر موجودی</label>
            <select name="warehouse_id" required><option value="">انتخاب کنید</option><?php foreach($warehouses as $w): ?><option value="<?= e($w['id']) ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select>
            <label>تعداد آخرین سفارش‌ها</label>
            <input type="number" name="limit" min="1" max="500" value="50">
            <button class="btn secondary" <?= $status['ok'] ? '' : 'disabled' ?>>همگام‌سازی فروش‌های وردپرس</button>
        </form>
    </div>
</div>
<div class="card">
    <h2>راهنما</h2>
    <ul>
        <li>ابتدا محصولات را از وردپرس وارد کنید تا کالا، قیمت و موجودی در سیستم انبارداری ساخته شود.</li>
        <li>سپس فروش‌ها را وارد کنید تا مشتری ساخته/بروزرسانی شود، فاکتور فروش ثبت شود و موجودی همان کالا از انبار کم شود.</li>
        <li>اگر برای یک سفارش موجودی کافی نباشد، آن سفارش با پیام خطا رد می‌شود تا موجودی را اصلاح کنید.</li>
    </ul>
</div>
