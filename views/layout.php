<?php $isAuthPage = str_starts_with($view, 'auth/'); ?>
<!doctype html>
<html lang="en" data-theme="<?=e($_SESSION['theme']??'light')?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#00CEC8"><title><?=e($title)?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?=e(asset_url('assets/css/taskhaat.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('assets/css/manual-payments.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('assets/css/app.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('assets/css/dashboard.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('assets/css/reference-theme.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('assets/css/section-pages.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('assets/css/worker-workspace.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('assets/css/publisher-workspace.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('assets/css/admin-workspace.css'))?>"><link rel="stylesheet" href="<?=e(asset_url('assets/css/site-experience.css'))?>">
<script src="https://unpkg.com/lucide@latest" defer></script>
</head>
<body class="<?=$isAuthPage ? 'page-auth' : ''?>">
<?php if(!$isAuthPage) require __DIR__.'/partials/header.php'; ?>
<main>
<?php foreach($flashes as $f): ?><div class="container flash-wrap"><div class="flash <?=e($f['type'])?>"><?=e($f['message'])?></div></div><?php endforeach; ?>
<?php require __DIR__.'/'.$view.'.php'; ?>
</main>
<?php if(!$isAuthPage) require __DIR__.'/partials/footer.php'; ?>
<script src="<?=e(asset_url('assets/js/app.js'))?>" defer></script>
</body></html>
