<!DOCTYPE html>
<html>

<head>
	<title><?=$title?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<link rel="icon" href="/template/img/favicon.ico">

    <script src="/template/js/jquery-3.6.0.min.js"></script>
    <script src="/template/js/popper.min.js"></script>
    <script src="/template/js/bootstrap.min.js"></script>

	<link rel="stylesheet" href="/template/css/bootstrap.min.css">
	<link rel="stylesheet" href="/template/css/styles.css">
</head>
<body>
    <div class="container main-block">
        <div class="row logo logo-sites">
            <div class="col-sm-12">
                <img src="/template/img/logo.svg">
                <div class="title text-center">отдел веб-проектов</div>
            </div>
        </div>
        <?php if (!isset($GLOBALS['IS_404'])):?>
            <div class="row mb-3">
                <div class="col text-center"><a href="/">Главная</a></div>
                <div class="col text-center"><a href="/sites/">Песочницы</a></div>
            </div>
        <?php endif;?>
