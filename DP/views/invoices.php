<?php
$title = ['sale' => 'فاکتور فروش', 'purchase' => 'فاکتور خرید', 'purchase_return' => 'برگشت از خرید'][$type] ?? 'فاکتور';
$meta = [];
foreach ($itemMeta as $m) {
    $meta[(int) $m['item_id']] = $m;
}
$itemOptions = '';
foreach ($items as $i) {
    $m = $meta[(int) $i['id']] ?? ['last_purchase_price' => 0, 'default_sale_price' => 0];
    $label = $i['name'] . ' - ' . $i['sku'] . ' | خرید: ' . moneyless_number($m['last_purchase_price']) . ' | فروش: ' . moneyless_number($m['default_sale_price']);
    $itemOptions .= '<option value="' . e($i['id']) . '" data-sale-price="' . e((string) $m['default_sale_price']) . '" data-cost-price="' . e((string) $m['last_purchase_price']) . '">' . e($label) . '</option>';
}
?>
<div class="card">
    <h2><?= e($title) ?></h2>
    <p class="muted">در فاکتور خرید موجودی کالا به انبار اضافه می‌شود؛ در فاکتور فروش و برگشت از خرید موجودی از انبار انتخابی کسر می‌شود.</p>
    <form method="post" action="<?= e(base_url('accounting/invoices/save')) ?>" data-autosave="invoice-<?= e($type) ?>" data-invoice-form data-invoice-type="<?= e($type) ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="invoice_type" value="<?= e($type) ?>">
        <div class="grid">
            <div class="col-3"><label>شماره فاکتور</label><input name="invoice_no" value="<?= e($invoiceNo) ?>" required></div>
            <div class="col-3"><label>تاریخ</label><input type="date" name="invoice_date" value="<?= e(date('Y-m-d')) ?>" required></div>
            <div class="col-3"><label>انبار</label><select name="warehouse_id" required><option value="">انتخاب کنید</option><?php foreach($warehouses as $w): ?><option value="<?= e($w['id']) ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
            <?php if($type === 'sale'): ?><div class="col-3"><label>مشتری</label><select name="customer_id"><option value="">مشتری نقدی</option><?php foreach($customers as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div><?php else: ?><div class="col-3"><label>تامین‌کننده</label><select name="supplier_id"><option value="">-</option><?php foreach($suppliers as $s): ?><option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="col-3"><label>وضعیت پرداخت</label><select name="payment_status"><option value="credit">نسیه</option><option value="cash">نقدی</option><option value="partial">بخشی پرداخت شده</option></select></div>
            <div class="col-3"><label>حساب بانکی</label><select name="bank_account_id"><option value="">صندوق/نقد</option><?php foreach($banks as $b): ?><option value="<?= e($b['id']) ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-3"><label>مبلغ پرداخت/دریافت شده</label><input type="number" step="0.01" name="paid_amount" value="0"></div>
            <div class="col-3"><label>هزینه حمل</label><input type="number" step="0.01" name="shipping_cost" value="0"></div>
        </div>
        <div class="table-wrap">
            <table class="table invoice-items-table">
                <thead><tr><th>کالا</th><th>تعداد</th><th><?= $type==='sale'?'قیمت فروش':'قیمت خرید' ?></th><th>بهای تمام‌شده</th><th class="no-print">عملیات</th></tr></thead>
                <tbody data-invoice-rows>
                    <tr data-invoice-row>
                        <td><select name="items[0][item_id]" data-searchable-select data-invoice-item><option value="">جستجو و انتخاب کالا</option><?= $itemOptions ?></select></td>
                        <td><input type="number" step="0.001" name="items[0][quantity]"></td>
                        <td><input type="number" step="0.01" name="items[0][unit_price]" data-unit-price <?= $type === 'purchase' ? 'placeholder="دستی وارد شود"' : '' ?>></td>
                        <td><input type="number" step="0.01" name="items[0][cost_price]" data-cost-price placeholder="برای سود ناخالص"></td>
                        <td class="no-print"><button type="button" class="btn light" data-remove-invoice-row>حذف</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="actions no-print" style="margin:12px 0"><button type="button" class="btn light" data-add-invoice-row>+ افزودن سطر کالا</button></div>
        <template data-invoice-row-template>
            <tr data-invoice-row>
                <td><select name="items[__INDEX__][item_id]" data-searchable-select data-invoice-item><option value="">جستجو و انتخاب کالا</option><?= $itemOptions ?></select></td>
                <td><input type="number" step="0.001" name="items[__INDEX__][quantity]"></td>
                <td><input type="number" step="0.01" name="items[__INDEX__][unit_price]" data-unit-price <?= $type === 'purchase' ? 'placeholder="دستی وارد شود"' : '' ?>></td>
                <td><input type="number" step="0.01" name="items[__INDEX__][cost_price]" data-cost-price placeholder="برای سود ناخالص"></td>
                <td class="no-print"><button type="button" class="btn light" data-remove-invoice-row>حذف</button></td>
            </tr>
        </template>
        <div class="grid"><div class="col-3"><label>تخفیف</label><input type="number" step="0.01" name="discount" value="0"></div><div class="col-3"><label>مالیات/اضافات</label><input type="number" step="0.01" name="tax" value="0"></div><div class="col-6"><label>توضیحات</label><input name="notes"></div></div>
        <button class="btn">ثبت فاکتور و بروزرسانی انبار</button>
    </form>
</div>
<div class="card"><h2>آخرین <?= e($title) ?>ها</h2><div class="table-wrap"><table class="table"><tr><th>تاریخ</th><th>شماره</th><th>طرف حساب</th><th>انبار</th><th>جمع کل</th><th>پرداختی</th><th>مانده</th></tr><?php foreach($rows as $r): ?><tr><td><?= e(jalali_like_date($r['invoice_date'])) ?></td><td><?= e($r['invoice_no']) ?></td><td><?= e($r['customer_name'] ?: $r['supplier_name'] ?: '-') ?></td><td><?= e($r['warehouse_name']) ?></td><td><?= e(number_format((float)$r['total'])) ?></td><td><?= e(number_format((float)$r['paid_amount'])) ?></td><td><?= e(number_format((float)$r['total'] - (float)$r['paid_amount'])) ?></td></tr><?php endforeach; ?></table></div></div>
