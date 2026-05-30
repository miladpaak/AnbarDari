<?php
$title = 'تامین‌کنندگان و مشتریان';
$editSupplier = $editSupplier ?? null;
$editCustomer = $editCustomer ?? null;
$editContact = $editSupplier ?: $editCustomer;
$editKind = $editCustomer ? 'customer' : 'supplier';
?>
<div class="grid">
    <div class="col-6 card" id="contact-form">
        <h2><?= $editContact ? 'ویرایش طرف حساب' : 'ثبت طرف حساب' ?></h2>
        <?php if ($editContact): ?>
            <p class="muted">در حال ویرایش: <?= e($editContact['name']) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('contacts/save')) ?>" data-autosave="<?= $editContact ? 'contact-edit-' . $editKind . '-' . (int) $editContact['id'] : 'contact' ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e($editContact['id'] ?? '') ?>">
            <label>نوع</label>
            <?php if ($editContact): ?>
                <input type="hidden" name="kind" value="<?= e($editKind) ?>">
            <?php endif; ?>
            <select name="kind" <?= $editContact ? 'disabled' : '' ?>>
                <option value="supplier" <?= $editKind === 'supplier' ? 'selected' : '' ?>>تامین‌کننده</option>
                <option value="customer" <?= $editKind === 'customer' ? 'selected' : '' ?>>مشتری</option>
            </select>
            <label>نام</label>
            <input name="name" value="<?= e($editContact['name'] ?? '') ?>" required>
            <label>تلفن</label>
            <input name="phone" value="<?= e($editContact['phone'] ?? '') ?>">
            <label>آدرس</label>
            <textarea name="address"><?= e($editContact['address'] ?? '') ?></textarea>
            <div class="actions">
                <button class="btn"><?= $editContact ? 'ذخیره تغییرات' : 'ذخیره' ?></button>
                <?php if ($editContact): ?><a class="btn light" href="<?= e(base_url('contacts')) ?>">انصراف</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-6 card">
        <h2>لیست تامین‌کنندگان</h2>
        <div class="table-wrap">
            <table class="table compact-table">
                <tr><th>نام</th><th>تلفن</th><th>آخرین ویرایش</th><th>عملیات</th></tr>
                <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><?= e($s['name']) ?></td>
                        <td><?= e($s['phone']) ?></td>
                        <td><?= e(jalali_like_datetime($s['updated_at'])) ?></td>
                        <td><a class="btn light" href="<?= e(base_url('contacts?edit_supplier=' . (int) $s['id'] . '#contact-form')) ?>">ویرایش</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <h2>لیست مشتریان</h2>
        <div class="table-wrap">
            <table class="table compact-table">
                <tr><th>نام</th><th>تلفن</th><th>آخرین ویرایش</th><th>عملیات</th></tr>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?= e($c['name']) ?></td>
                        <td><?= e($c['phone']) ?></td>
                        <td><?= e(jalali_like_datetime($c['updated_at'])) ?></td>
                        <td><a class="btn light" href="<?= e(base_url('contacts?edit_customer=' . (int) $c['id'] . '#contact-form')) ?>">ویرایش</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
