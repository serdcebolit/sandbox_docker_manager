<?php

use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\Util\Config;
use Intervolga\DockerSandboxManager\Util\Mail;

$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__, 2);

require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

/**
 * Скрипт останавливает песочницы, у которых не было ни одного хита в access-логах за последние n дней
 * n - берется из конфига sandbox_active_days_period
 */

$logger = Application::getSandboxSleepCheckLogger();

$logger->info('Начало выполнения скрипта', []);

try {
	$sandboxRepository = Application::getSandboxRepository();
	$sandboxCollection = $sandboxRepository->getAllWithLastCommandStatus(['*'], true);
} catch (Throwable $e) {
	$logger->error('Ошибка по время получения списка песочниц', [
		'MESSAGE' => $e->getMessage(),
		'FILE' => $e->getFile(),
		'LINE' => $e->getLine(),
		'TRACE' => $e->getTraceAsString(),
	]);
}

$logger->info('Получен список песочниц', [
	'COUNT' => $sandboxCollection->count(),
]);

$sleptSandboxes = [];
foreach ($sandboxCollection as $sandbox) {
	try {
		if ($sandbox->isNeedSleep()) {
			$sandboxLock = Application::getSandboxLock($sandbox->getDomain());
			$sandboxLock->set();
			$sandbox->sleep();
			$logger->info('Остановлена песочница', [
				'ID' => $sandbox->getId(),
				'DOMAIN' => $sandbox->getDomain(),
			]);
			$sleptSandboxes[] = [
				'domain' => $sandbox->getDomain(),
				'owner_email' => $sandbox->getOwner(),
				'message' => 'Сайт остановлен',
			];
		}
	} catch (Throwable $e) {
		$logger->error('Ошибка по время обработки песочницы', [
			'ID' => $sandbox->getId(),
			'DOMAIN' => $sandbox->getDomain(),
			'MESSAGE' => $e->getMessage(),
			'FILE' => $e->getFile(),
			'LINE' => $e->getLine(),
			'TRACE' => $e->getTraceAsString(),
		]);
	}
}

if (count($sleptSandboxes)) {
	try {
		$config = Config::getMainConfig();
		$mail = new Mail(
			'sites_sleep_report',
			[
				'mainHost' => $config['main_domain'],
				'sites' => $sleptSandboxes,
			]
		);
		$mail->send($config['mail']['bcc_emails']);
	} catch (Throwable $e) {
		$logger->error('Ошибка по время отправки письма для отчета об остановленных песочницах', [
			'MESSAGE' => $e->getMessage(),
			'FILE' => $e->getFile(),
			'LINE' => $e->getLine(),
			'TRACE' => $e->getTraceAsString(),
		]);
	}
}

$logger->info('Конец выполнения скрипта', [
	'SLEPT_COUNT' => count($sleptSandboxes),
]);