<?php

use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\Entity\Sandbox\ISandbox;

$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__, 2);

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

/**
 * Скрипт подсчитывает количество памяти, которое использует каждая песочница
 */

$logger = Application::getMainLogger();

$logger->info('Начало подсчета количества используемой памяти', []);

$sandboxRepository = Application::getSandboxRepository();
$sandboxCollection = $sandboxRepository->getAllWithLastCommandStatus(['*'], true);
// Подсчет идет по частям. Массив песочниц разбивается на 7 частей, и каждый день недели берется своя часть
$currentWeekNumber = (int)date('N');
$sandboxesForProcess = array_chunk($sandboxCollection->toArray(), ceil($sandboxCollection->count() / 7))[$currentWeekNumber];

$attemptsCnt = 2;
//Пробуем несколько раз получить данные, т.к. иногда может быть ошибка "Error response from daemon: a disk usage operation is already running"
for ($i = 0; $i < $attemptsCnt; $i++) {
	try {
		$sandboxVolumesStats = Application::getDockerStatsProvider()->getVolumesStatsForSandboxes(
			array_map(fn (ISandbox $sandbox) => $sandbox->getDomain(), $sandboxesForProcess),
		);
		$sandboxRepository = Application::getSandboxRepository();
		break;
	} catch (Throwable $e) {
		$logger->error('Ошибка во время получения списка песочниц', [
			'MESSAGE' => $e->getMessage(),
			'FILE' => $e->getFile(),
			'LINE' => $e->getLine(),
			'TRACE' => $e->getTraceAsString(),
		]);
		sleep(5);
	}
}

foreach ($sandboxVolumesStats as $domain => $volumeStats)
{
	try {
		if (!$domain) {
			continue;
		}
		$logger->info($domain, $volumeStats);

		$sandbox = $sandboxRepository->findByDomain($domain);
		if ($sandbox) {
			intval($volumeStats["httpd"]) && $sandbox->setFilesVolume(intval($volumeStats["httpd"]));
			intval($volumeStats["mysql"]) && $sandbox->setDbVolume(intval($volumeStats["mysql"]));

			$sandboxRepository->save($sandbox);
		}
	} catch (Throwable $e) {
		$logger->error('Ошибка во время подсчета количества используемой памяти', [
			'MESSAGE' => $e->getMessage(),
			'FILE' => $e->getFile(),
			'LINE' => $e->getLine(),
			'TRACE' => $e->getTraceAsString(),
		]);
	}
}

$logger->info('Конец подсчета количества используемой памяти', []);