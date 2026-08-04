<?php $title = 'داشبورد حسابداری'; ?>
<div class="stats">
    <div class="stat">فروش روزانه<strong><?= e(number_format((float) $salesToday)) ?></strong></div>
    <div class="stat">فروش ماهانه<strong><?= e(number_format((float) $salesMonth)) ?></strong></div>
    <div class="stat">سود ناخالص<strong><?= e(number_format((float) $gross)) ?></strong></div>
    <div class="stat">سود خالص<strong><?= e(number_format((float) $pl['net'])) ?></strong></div>
</div>
<div class="card">
    <h2>دسترسی سریع</h2>
    <div class="actions">
        <a class="btn" href="<?= e(base_url('accounting/invoices?type=purchase')) ?>">ثبت فاکتور خرید</a>
        <a class="btn" href="<?= e(base_url('accounting/invoices?type=sale')) ?>">ثبت فاکتور فروش</a>
        <a class="btn secondary" href="<?= e(base_url('accounting/invoices?type=purchase_return')) ?>">برگشت از خرید</a>
        <a class="btn light" href="<?= e(base_url('accounting/journal')) ?>">ثبت سند دستی</a>
        <a class="btn light" href="<?= e(base_url('accounting/customer')) ?>">پرونده مشتری</a>
        <a class="btn light" href="<?= e(base_url('items')) ?>">موجودی کالا</a>
    </div>
</div>
<div class="grid">
    <div class="col-6 card">
        <h2>سود و زیان</h2>
        <form class="searchbar" method="get" action="<?= e(base_url('accounting')) ?>">
            <div><label>از تاریخ</label><input type="date" name="from" value="<?= e($from) ?>"></div>
            <div><label>تا تاریخ</label><input type="date" name="to" value="<?= e($to) ?>"></div>
            <button class="btn">اعمال</button>
        </form>
        <div class="table-wrap"><table class="table compact-table"><tr><th>حساب</th><th>نوع</th><th>مانده</th></tr><?php foreach($pl['rows'] as $r): ?><tr><td><?= e($r['code'].' - '.$r['name']) ?></td><td><?= e($r['type']==='income'?'درآمد':'هزینه') ?></td><td><?= e(number_format(abs((float)$r['balance']))) ?></td></tr><?php endforeach; ?><tr><th colspan="2">سود/زیان خالص</th><th><?= e(number_format((float)$pl['net'])) ?></th></tr></table></div>
    </div>
    <div class="col-6 card">
        <h2>بدهکاران و بستانکاران</h2>
        <h3>بدهکاران (مشتریان)</h3>
        <div class="table-wrap"><table class="table compact-table"><tr><th>مشتری</th><th>مانده</th></tr><?php foreach($debtors as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e(number_format((float)$r['balance'])) ?></td></tr><?php endforeach; ?></table></div>
        <h3>بستانکاران (تامین‌کنندگان)</h3>
        <div class="table-wrap"><table class="table compact-table"><tr><th>تامین‌کننده</th><th>مانده</th></tr><?php foreach($creditors as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e(number_format((float)$r['balance'])) ?></td></tr><?php endforeach; ?></table></div>
    </div>
</div>
