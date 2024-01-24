<?php

use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\Entity\Sandbox\DockerSandbox;
use Intervolga\DockerSandboxManager\Exception\WrongRobotsFileException;
use Intervolga\DockerSandboxManager\Util\Config;
use Intervolga\DockerSandboxManager\Util\DockerComposeProject;
use Intervolga\DockerSandboxManager\Util\Mail;

$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__, 2);

require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

/**
 * Скрипт проверяет наличие файла robots.txt у песочниц и соответствие его эталонному файлу
 */

$logger = Application::getMainLogger();

$logger->info('Начало проверки robots.txt', []);

try {
	$sandboxRepository = Application::getSandboxRepository();
	$sandboxCollection = $sandboxRepository->getAllWithLastCommandStatus(['*'], true);
} catch (Throwable $e) {
	$logger->error('Ошибка во время получения списка песочниц', [
		'MESSAGE' => $e->getMessage(),
		'FILE' => $e->getFile(),
		'LINE' => $e->getLine(),
		'TRACE' => $e->getTraceAsString(),
	]);
}

$logger->info('Получен список песочниц для проверки robots.txt', [
	'COUNT' => $sandboxCollection->count(),
]);

$sandboxesWithProblems = [];
foreach ($sandboxCollection as $sandbox) {
	try {
		if ($sandbox->getStatus() != DockerSandbox::STATUS_WORKING)
			continue;
		$composeProject = new DockerComposeProject($sandbox->getDomain());
		$composeProject->checkRobotsFileOrFail();
	} catch (WrongRobotsFileException $e) {
		$sandboxesWithProblems[] = [
			'domain' => $sandbox->getDomain(),
			'owner_email' => $sandbox->getOwner(),
			'message' => $e->getMessage(),
		];
	} catch (Throwable $e) {
		$logger->error('Ошибка во время получения списка песочниц для проверки robots.txt', [
			'MESSAGE' => $e->getMessage(),
			'FILE' => $e->getFile(),
			'LINE' => $e->getLine(),
			'TRACE' => $e->getTraceAsString(),
		]);
	}
}
try {
	$config = Config::getMainConfig();

	//Отправляем письмо каждому
	foreach ($sandboxesWithProblems as $problem) {
		$mail = new Mail(
			'wrong_robots_file_report',
			[
				'mainHost' => $config['main_domain'],
				'sites' => [$problem],
				'needShowInfo' => true,
			]
		);
		$mail->send([$problem['owner_email']]);
	}

	if (count($sandboxesWithProblems)) {
		//Отправляем общее письмо админам
		$mail = new Mail(
			'wrong_robots_file_report',
			[
				'mainHost' => $config['main_domain'],
				'sites' => $sandboxesWithProblems,
			]
		);
		$mail->send($config['mail']['bcc_emails']);
	}
} catch (Throwable $e) {
	$logger->error('Ошибка по время отправки письма для проверки robots.txt', [
		'MESSAGE' => $e->getMessage(),
		'FILE' => $e->getFile(),
		'LINE' => $e->getLine(),
		'TRACE' => $e->getTraceAsString(),
	]);
}

$logger->info('Конец проверки robots.txt', [
	'COUNT_WITH_PROBLEMS' => count($sandboxesWithProblems),
]);