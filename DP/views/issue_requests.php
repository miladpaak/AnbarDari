<?php $title = 'حواله‌های خروج از انبار'; ?>
<div class="card issue-toolbar">
    <div><h2>پیگیری و آرشیو حواله‌ها</h2><p class="muted">هر حواله با کد یکتا بایگانی می‌شود. خروج کالا تنها بعد از تأیید انباردار انجام می‌شود.</p></div>
    <div class="actions">
        <?php if (can('issue_requests')): ?><a class="btn" href="<?= e(base_url('issue-requests/new')) ?>">ثبت حواله خروج جدید</a><?php endif; ?>
        <a class="btn light" href="<?= e(base_url('issue-requests')) ?>">همه</a>
        <a class="btn light" href="<?= e(base_url('issue-requests?status=pending')) ?>">در انتظار</a>
        <a class="btn light" href="<?= e(base_url('issue-requests?status=approved')) ?>">تأییدشده</a>
        <a class="btn light" href="<?= e(base_url('issue-requests?status=rejected')) ?>">ردشده</a>
    </div>
</div>
<div class="issue-card-grid">
<?php foreach ($issues as $issue): ?>
    <article class="card issue-card issue-<?= e($issue['status']) ?>">
        <div class="issue-card-head"><strong><?= e($issue['issue_code']) ?></strong><span class="badge <?= $issue['status'] === 'approved' ? 'ok' : ($issue['status'] === 'rejected' ? 'danger' : 'warn') ?>"><?= e(['pending' => 'در انتظار بررسی', 'approved' => 'تأیید خروج', 'rejected' => 'رد شده'][$issue['status']]) ?></span></div>
        <p>درخواست‌دهنده: <?= e($issue['requester_name']) ?></p><p>انبار: <?= e($issue['warehouse_name']) ?> · <?= e($issue['item_count']) ?> قلم</p>
        <p class="muted">ثبت: <?= e(jalali_like_datetime($issue['created_at'])) ?></p>
        <a class="btn light" href="<?= e(base_url('issue-requests/view?id=' . $issue['id'])) ?>">مشاهده و بررسی</a>
    </article>
<?php endforeach; ?>
<?php if (!$issues): ?><div class="card"><p class="muted">حواله‌ای در این بخش وجود ندارد.</p></div><?php endif; ?>
</div>
