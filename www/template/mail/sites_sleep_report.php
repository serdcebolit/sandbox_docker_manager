<?php
/**
 * @var $mainHost string
 * @var $sites array
 */

$subject = 'Сообщение от '.$mainHost.': Отчет о выключенных сайтах';
$sitesBlock = '';

foreach ($sites as $item)
	$sitesBlock .= sprintf(
		'<a href="https://%s">%s</a> (%s): %s<br>',
		$item['domain'], $item['domain'], $item['owner_email'], $item['message']
	);

$body = <<<E
$sitesBlock
<br><br>
<hr>
С любовью ваш $mainHost
<br>
E;
