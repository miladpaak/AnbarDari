<?php $flash = flash(); ?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e(app_config()['app_name']) ?></title><link rel="stylesheet" href="<?= e(base_url('assets/app.css')) ?>"></head><body>
<div class="layout"><aside class="sidebar"><div class="brand">📦 <?= e(app_config()['app_name']) ?></div><div class="muted">کاربر: <?= e($_SESSION['user']['name'] ?? '') ?></div><nav class="nav">
<a href="<?= e(base_url('dashboard')) ?>" class="<?= $currentRoute==='dashboard'?'active':'' ?>">داشبورد</a>
<a href="<?= e(base_url('items')) ?>">کالا و موجودی</a>
<a href="<?= e(base_url('movement?type=in')) ?>">ثبت ورود</a>
<a href="<?= e(base_url('movement?type=out')) ?>">ثبت خروج</a>
<a href="<?= e(base_url('movement?type=transfer')) ?>">انتقال بین انبار</a>
<a href="<?= e(base_url('reports')) ?>">گزارش‌ها</a>
<a href="<?= e(base_url('contacts')) ?>">تامین‌کنندگان/مشتریان</a>
<a href="<?= e(base_url('settings')) ?>">تنظیمات و کاربران</a>
<a href="<?= e(base_url('logout')) ?>">خروج</a>
</nav></aside><main class="content"><div class="topbar"><h1><?= e($title ?? 'انبارداری') ?></h1><span class="muted">آخرین بروزرسانی آنلاین: <?= date('Y/m/d H:i') ?></span></div><?php if($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?><?php require __DIR__ . '/' . $view . '.php'; ?></main></div><script src="<?= e(base_url('assets/app.js')) ?>"></script></body></html>
