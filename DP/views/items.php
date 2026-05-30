<?php
$title = 'تعریف کالا و موجودی لحظه‌ای';
$lists = lists();
$editItem = $editItem ?? null;
$isEdit = $editItem !== null;
?>
<div class="card">
    <form class="searchbar" method="get">
        <input name="q" placeholder="جستجوی سریع نام، کد یا بارکد" value="<?= e($q) ?>">
        <button class="btn">جستجو</button>
        <a class="btn light" href="<?= e(base_url('items')) ?>">نمایش همه</a>
    </form>
</div>

<?php if (can('manage_items')): ?>
    <div class="card" id="item-form">
        <h2><?= $isEdit ? 'ویرایش کالا' : 'کالای جدید' ?></h2>
        <?php if ($isEdit): ?>
            <p class="muted">در حال ویرایش: <?= e($editItem['name']) ?> - <?= e($editItem['sku']) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('items/save')) ?>" data-autosave="<?= $isEdit ? 'item-edit-' . (int) $editItem['id'] : 'item' ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e($editItem['id'] ?? '') ?>">
            <div class="grid">
                <div class="col-3">
                    <label>کد کالا</label>
                    <input name="sku" value="<?= e($editItem['sku'] ?? '') ?>" required>
                </div>
                <div class="col-3">
                    <label>بارکد</label>
                    <input name="barcode" value="<?= e($editItem['barcode'] ?? '') ?>">
                </div>
                <div class="col-6">
                    <label>نام کالا</label>
                    <input name="name" value="<?= e($editItem['name'] ?? '') ?>" required>
                </div>
                <div class="col-3">
                    <label>دسته‌بندی</label>
                    <select name="category_id">
                        <option value="">-</option>
                        <?php foreach ($lists['categories'] as $c): ?>
                            <option value="<?= e($c['id']) ?>" <?= (string) ($editItem['category_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-3">
                    <label>واحد</label>
                    <select name="unit_id">
                        <option value="">-</option>
                        <?php foreach ($lists['units'] as $u): ?>
                            <option value="<?= e($u['id']) ?>" <?= (string) ($editItem['unit_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-3">
                    <label>حداقل موجودی</label>
                    <input name="min_stock" type="number" step="0.001" value="<?= e((string) ($editItem['min_stock'] ?? '0')) ?>">
                </div>
                <div class="col-12">
                    <label>توضیحات</label>
                    <textarea name="description"><?= e($editItem['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="actions">
                <button class="btn"><?= $isEdit ? 'ذخیره تغییرات' : 'ذخیره کالا' ?></button>
                <?php if ($isEdit): ?>
                    <a class="btn light" href="<?= e(base_url('items')) ?>">انصراف از ویرایش</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="card">
    <h2>لیست کالاها</h2>
    <div class="table-wrap">
        <table class="table">
            <tr>
                <th>نام</th>
                <th>کد</th>
                <th>دسته</th>
                <th>واحد</th>
                <th>موجودی</th>
                <th>حداقل</th>
                <th>بارکد</th>
                <th>آخرین ویرایش</th>
                <?php if (is_warehouse_manager()): ?><th>عملیات</th><?php endif; ?>
            </tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e($r['sku']) ?></td>
                    <td><?= e($r['category_name']) ?></td>
                    <td><?= e($r['unit_name']) ?></td>
                    <td><span class="badge <?= $r['quantity'] <= $r['min_stock'] ? 'danger' : 'ok' ?>"><?= e(moneyless_number($r['quantity'])) ?></span></td>
                    <td><?= e(moneyless_number($r['min_stock'])) ?></td>
                    <td>
                        <?php $barcodeId = 'item-barcode-' . (int) $r['id']; ?>
                        <div class="barcode-card">
                            <button type="button" class="barcode-trigger" data-barcode-toggle aria-label="نمایش دکمه‌های بارکد">
                                <span id="<?= e($barcodeId) ?>"><?= Barcode::svg($r['barcode'] ?: $r['sku'], 45) ?></span>
                            </button>
                            <div class="barcode-actions">
                                <button type="button" class="btn secondary" data-download-svg="#<?= e($barcodeId) ?> svg" data-filename="<?= e($r['barcode'] ?: $r['sku']) ?>">دانلود</button>
                                <button type="button" class="btn light" data-print-svg="#<?= e($barcodeId) ?> svg">پرینت</button>
                            </div>
                        </div>
                    </td>
                    <td><?= e(jalali_like_datetime($r['updated_at'])) ?></td>
                    <?php if (is_warehouse_manager()): ?>
                        <td>
                            <div class="actions">
                                <a class="btn light" href="<?= e(base_url('items?edit=' . (int) $r['id'] . '#item-form')) ?>">ویرایش</a>
                                <form method="post" action="<?= e(base_url('items/delete')) ?>" onsubmit="return confirm('آیا از حذف این کالا از لیست فعال مطمئن هستید؟ سوابق گردش کالا باقی می‌ماند.');">
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                                    <button class="btn danger" type="submit">حذف</button>
                                </form>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
