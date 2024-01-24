<?php
/**
 * @var $mainHost string
 * @var $sites array
 * @var $needShowInfo bool
 */

$subject = 'Сообщение от '.$mainHost.': Проверка robots.txt ('.date('d.m.Y').')';

$sitesBlock = '';

foreach ($sites as $item)
	$sitesBlock .= sprintf(
		'<a href="https://%s/robots.txt">%s</a> (%s): %s<br>',
		$item['domain'], $item['domain'], $item['owner_email'], $item['message']
	);

$info = '';
if ($needShowInfo)
	$info = sprintf(
		'Вам нужно проверить файл robots.txt на вашей песочнице и исправить. Для примера можно взять правильный файл отсюда <a href="https://%s/robots.txt">%s/robots.txt</a>',
		$mainHost, $mainHost
	);

$body = <<<E
$sitesBlock
<br><br>
$info
<br><br>
<hr>
С любовью ваш $mainHost
<br>
E;
