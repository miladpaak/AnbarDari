<?php $title='گزارش‌ها'; ?>
<div class="card">
    <form class="grid" method="get">
        <div class="col-4"><label>کالا</label><select name="item_id" data-searchable-select><option value="">همه</option><?php foreach($items as $i): ?><option value="<?= e($i['id']) ?>" <?= (($_GET['item_id']??'')==$i['id'])?'selected':'' ?>><?= e($i['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-3"><label>از تاریخ</label><input type="date" name="from" value="<?= e($_GET['from']??'') ?>"></div>
        <div class="col-3"><label>تا تاریخ</label><input type="date" name="to" value="<?= e($_GET['to']??'') ?>"></div>
        <div class="col-2"><label>&nbsp;</label><button class="btn">گزارش</button></div>
    </form>
    <div class="actions no-print" style="margin-top:12px">
        <a class="btn secondary" href="<?= e(base_url('reports/export-xlsx?' . http_build_query($_GET))) ?>">خروجی Excel XLSX</a>
        <a class="btn light" href="<?= e(base_url('reports/export-pdf?' . http_build_query($_GET))) ?>" target="_blank">خروجی PDF</a>
    </div>
</div>
<div class="card"><h2>گردش کالا</h2><div class="table-wrap"><table class="table"><tr><th>تاریخ شمسی</th><th>شماره</th><th>نوع</th><th>کالا</th><th>مقدار</th><th>از</th><th>به</th></tr><?php foreach($rows as $r): ?><tr><td><?= e(jalali_like_datetime($r['created_at'])) ?></td><td><?= e($r['reference_no']) ?></td><td><?= e($r['type']) ?></td><td><?= e($r['item_name']) ?></td><td><?= e(moneyless_number($r['quantity'])) ?></td><td><?= e($r['from_name']) ?></td><td><?= e($r['to_name']) ?></td></tr><?php endforeach; ?></table></div></div>
<div class="grid"><div class="col-6 card"><h2>کسری/هشدار موجودی</h2><?php foreach($lowItems as $i): ?><p><span class="badge danger"><?= e(moneyless_number($i['quantity'])) ?></span> <?= e($i['name']) ?> - حداقل <?= e(moneyless_number($i['min_stock'])) ?></p><?php endforeach; ?></div><div class="col-6 card"><h2>کالاهای راکد (۹۰ روز)</h2><?php foreach($stale as $i): ?><p><?= e($i['name']) ?> <span class="muted">آخرین گردش: <?= e(jalali_like_datetime($i['last_move'])) ?></span></p><?php endforeach; ?></div></div>
