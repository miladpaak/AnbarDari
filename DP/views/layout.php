<?php $flash = flash(); ?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e(app_config()['app_name']) ?></title><link rel="stylesheet" href="<?= e(base_url('assets/app.css')) ?>"></head><body>
<div class="layout"><aside class="sidebar"><div class="sidebar-head"><div class="brand">📦 <?= e(app_config()['app_name']) ?></div><button class="menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="main-nav">☰</button></div><div class="muted">کاربر: <?= e($_SESSION['user']['name'] ?? '') ?></div><nav class="nav" id="main-nav">
<a href="<?= e(base_url('dashboard')) ?>" class="<?= $currentRoute==='dashboard'?'active':'' ?>">داشبورد</a>
<a href="<?= e(base_url('items')) ?>">کالا و موجودی</a>
<a href="<?= e(base_url('movement?type=in')) ?>">ثبت ورود</a>
<a href="<?= e(base_url('movement?type=out')) ?>">ثبت خروج</a>
<a href="<?= e(base_url('movement?type=transfer')) ?>">انتقال بین انبار</a>
<a href="<?= e(base_url('reports')) ?>">گزارش‌ها</a>
<?php if (can('accounting')): ?>
<a href="<?= e(base_url('accounting')) ?>" class="<?= str_starts_with($currentRoute,'accounting')?'active':'' ?>">حسابداری</a>
<a href="<?= e(base_url('accounting/invoices?type=purchase')) ?>">فاکتور خرید</a>
<a href="<?= e(base_url('accounting/invoices?type=sale')) ?>">فاکتور فروش</a>
<a href="<?= e(base_url('accounting/invoices?type=purchase_return')) ?>">برگشت از خرید</a>
<a href="<?= e(base_url('accounting/journal')) ?>">ثبت سند دستی</a>
<a href="<?= e(base_url('accounting/sales-report')) ?>">گزارش فروش</a>
<a href="<?= e(base_url('accounting/banks')) ?>">حساب‌های بانکی</a>
<?php endif; ?>
<?php if (is_admin()): ?><a href="<?= e(base_url('audit-log')) ?>" class="<?= $currentRoute==='audit-log'?'active':'' ?>">تاریخچه ویرایش و حذف</a><?php endif; ?>
<a href="<?= e(base_url('contacts')) ?>">تامین‌کنندگان/مشتریان</a>
<a href="<?= e(base_url('settings')) ?>">تنظیمات و کاربران</a>
<a href="<?= e(base_url('logout')) ?>">خروج</a>
</nav></aside><main class="content"><div class="topbar"><h1><?= e($title ?? 'انبارداری') ?></h1><span class="muted">آخرین بروزرسانی آنلاین: <?= jalali_like_datetime(date('Y-m-d H:i:s')) ?></span></div><?php if($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?><?php require __DIR__ . '/' . $view . '.php'; ?></main></div><script src="<?= e(base_url('assets/app.js')) ?>"></script></body></html>
