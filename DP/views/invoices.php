<?php
$title = ['sale' => 'فاکتور فروش', 'purchase' => 'فاکتور خرید', 'purchase_return' => 'برگشت از خرید'][$type] ?? 'فاکتور';
$meta = [];
foreach ($itemMeta as $m) { $meta[(int)$m['item_id']] = $m; }
?>
<div class="card">
    <h2><?= e($title) ?></h2>
    <p class="muted">در فاکتور خرید موجودی کالا به انبار اضافه می‌شود؛ در فاکتور فروش و برگشت از خرید موجودی از انبار انتخابی کسر می‌شود.</p>
    <form method="post" action="<?= e(base_url('accounting/invoices/save')) ?>" data-autosave="invoice-<?= e($type) ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="invoice_type" value="<?= e($type) ?>">
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
        <div class="table-wrap"><table class="table"><tr><th>کالا</th><th>تعداد</th><th><?= $type==='sale'?'قیمت فروش':'قیمت خرید' ?></th><th>بهای تمام‌شده</th></tr><?php for($n=0;$n<8;$n++): ?><tr><td><select name="items[<?= $n ?>][item_id]"><option value="">-</option><?php foreach($items as $i): $m=$meta[(int)$i['id']] ?? ['last_purchase_price'=>0,'default_sale_price'=>0]; ?><option value="<?= e($i['id']) ?>"><?= e($i['name'].' - '.$i['sku'].' | خرید: '.moneyless_number($m['last_purchase_price']).' | فروش: '.moneyless_number($m['default_sale_price'])) ?></option><?php endforeach; ?></select></td><td><input type="number" step="0.001" name="items[<?= $n ?>][quantity]"></td><td><input type="number" step="0.01" name="items[<?= $n ?>][unit_price]"></td><td><input type="number" step="0.01" name="items[<?= $n ?>][cost_price]" placeholder="برای سود ناخالص"></td></tr><?php endfor; ?></table></div>
        <div class="grid"><div class="col-3"><label>تخفیف</label><input type="number" step="0.01" name="discount" value="0"></div><div class="col-3"><label>مالیات/اضافات</label><input type="number" step="0.01" name="tax" value="0"></div><div class="col-6"><label>توضیحات</label><input name="notes"></div></div>
        <button class="btn">ثبت فاکتور و بروزرسانی انبار</button>
    </form>
</div>
<div class="card"><h2>آخرین <?= e($title) ?>ها</h2><div class="table-wrap"><table class="table"><tr><th>تاریخ</th><th>شماره</th><th>طرف حساب</th><th>انبار</th><th>جمع کل</th><th>پرداختی</th><th>مانده</th></tr><?php foreach($rows as $r): ?><tr><td><?= e(jalali_like_date($r['invoice_date'])) ?></td><td><?= e($r['invoice_no']) ?></td><td><?= e($r['customer_name'] ?: $r['supplier_name'] ?: '-') ?></td><td><?= e($r['warehouse_name']) ?></td><td><?= e(number_format((float)$r['total'])) ?></td><td><?= e(number_format((float)$r['paid_amount'])) ?></td><td><?= e(number_format((float)$r['total'] - (float)$r['paid_amount'])) ?></td></tr><?php endforeach; ?></table></div></div>
