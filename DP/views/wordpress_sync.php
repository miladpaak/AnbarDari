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
    <div class="col-4 card">
        <h2>افزودن انبوه محصولات به کالا</h2>
        <p class="muted">محصولات ووکامرس با ستون‌های نام، کد، دسته، واحد، موجودی، حداقل و آخرین ویرایش وارد بخش کالا می‌شوند. هر مقدار که در وردپرس موجود نباشد خالی می‌ماند؛ فقط برای کد داخلی، در صورت نبود SKU مقدار امن WP-ID ساخته می‌شود.</p>
        <form method="post" action="<?= e(base_url('accounting/wordpress/import-products')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label>انبار مقصد موجودی</label>
            <select name="warehouse_id" required><option value="">انتخاب کنید</option><?php foreach($warehouses as $w): ?><option value="<?= e($w['id']) ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select>
            <button class="btn" <?= $status['ok'] ? '' : 'disabled' ?>>وارد کردن انبوه محصولات</button>
        </form>
    </div>
    <div class="col-4 card">
        <h2>افزودن مشتریان وردپرس</h2>
        <p class="muted">کاربران وردپرس که نقش customer دارند با نام، تلفن و تاریخ بروزرسانی به لیست مشتریان سیستم اضافه/بروزرسانی می‌شوند.</p>
        <form method="post" action="<?= e(base_url('accounting/wordpress/import-customers')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button class="btn light" <?= $status['ok'] ? '' : 'disabled' ?>>وارد کردن مشتریان وردپرس</button>
        </form>
    </div>
    <div class="col-4 card">
        <h2>وارد کردن فروش‌ها</h2>
        <p class="muted">سفارش‌های ووکامرس به مشتری، فاکتور فروش، سند حسابداری و کسر خودکار از انبار تبدیل می‌شوند. سفارش‌های قبلاً واردشده دوباره ثبت نمی‌شوند.</p>
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
    <h2>پیش‌نمایش محصولات قابل ورود</h2>
    <div class="table-wrap">
        <table class="table">
            <tr><th>نام</th><th>کد</th><th>دسته</th><th>واحد</th><th>موجودی</th><th>حداقل</th><th>آخرین ویرایش</th></tr>
            <?php foreach (($wpProducts ?? []) as $p): ?>
                <tr>
                    <td><?= e($p['post_title'] ?: '') ?></td>
                    <td><?= e($p['sku'] ?: '') ?></td>
                    <td><?= e($p['category_name'] ?: '') ?></td>
                    <td><?= e($p['unit_name'] ?: '') ?></td>
                    <td><?= e($p['stock'] !== null && $p['stock'] !== '' ? moneyless_number($p['stock']) : '') ?></td>
                    <td><?= e($p['min_stock'] !== null && $p['min_stock'] !== '' ? moneyless_number($p['min_stock']) : '') ?></td>
                    <td><?= e($p['post_modified'] ? jalali_like_datetime($p['post_modified']) : '') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<div class="card">
    <h2>راهنما</h2>
    <ul>
        <li>ابتدا محصولات را از وردپرس وارد کنید تا کالا، قیمت و موجودی در سیستم انبارداری ساخته شود.</li>
        <li>سپس مشتریان وردپرس را وارد کنید تا کاربران دارای نقش customer در لیست مشتریان قابل مشاهده باشند.</li>
        <li>در پایان فروش‌ها را وارد کنید تا مشتری ساخته/بروزرسانی شود، فاکتور فروش ثبت شود و موجودی همان کالا از انبار کم شود.</li>
        <li>اگر برای یک سفارش موجودی کافی نباشد، آن سفارش با پیام خطا رد می‌شود تا موجودی را اصلاح کنید.</li>
    </ul>
</div>
