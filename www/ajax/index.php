<?php

use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\Entity\Sandbox\DockerSandbox;
use Intervolga\DockerSandboxManager\Entity\Sandbox\ISandbox;
use Intervolga\DockerSandboxManager\Repository\SandboxRepository;
use Intervolga\DockerSandboxManager\Util\Ajax;
use Intervolga\DockerSandboxManager\Util\Config;
use Intervolga\DockerSandboxManager\Util\DockerComposeProject;

require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

if (!Ajax::isAjaxRequest()) {
	die('Its not ajax request');
}

function getSandboxFromRequest(int $sandboxId, SandboxRepository $sandboxRepository): ISandbox {
	!$sandboxId && throw new Exception('Передан пустой id песочницы');
	$sandbox = $sandboxRepository->findById($sandboxId);
	!$sandbox && throw new Exception('Передан пустой id песочницы');

	return $sandbox;
}

$action = $_REQUEST['action'];

$response = ['status' => 'success'];
$sandboxRepository = Application::getSandboxRepository();
$config = Config::getMainConfig();

try {
	switch ($action) {
		case 'start_sandbox':
			$sandbox = getSandboxFromRequest(intval($_REQUEST['sandboxId']), $sandboxRepository);

			$sandboxLock = Application::getSandboxLock($sandbox->getDomain());
			if ($sandboxLock->check()) {
				throw new Exception('Сейчас нельзя изменять состояние этой песочницы');
			}
			try {
				//Лочим песочницу на минуту, т.к. команда запуска выполняется быстро, но нужно время на поднятие контейнеров
				$sandboxLock->set();

				$sandbox->wakeUp();
				if ($sandbox->isSleep()) {
					$sandbox->setSleepDate(
						(new DateTime())
							->modify('+ '.$config['sandbox_active_days_period'].' days')
					);
					$sandboxRepository->save($sandbox);
				}
			} catch (Throwable $e) {
				$sandboxLock->release();
				throw $e;
			}
			break;
		case 'stop_sandbox':
			$sandbox = getSandboxFromRequest(intval($_REQUEST['sandboxId']), $sandboxRepository);
			$sandboxLock = Application::getSandboxLock($sandbox->getDomain());
			if ($sandboxLock->check()) {
				throw new Exception('Сейчас нельзя изменять состояние этой песочницы');
			}
			try {
				//Лочим песочницу на минуту, т.к. команда остановки выполняется быстро, но нужно время на остановку контейнеров
				$sandboxLock->set();

				$sandbox->sleep();
			} catch (Throwable $e) {
				$sandboxLock->release();
				throw $e;
			}
			break;
		case 'renew_sandbox':
			$sandbox = getSandboxFromRequest(intval($_REQUEST['sandboxId']), $sandboxRepository);
			if (
				$sandbox->getStatus() == DockerSandbox::STATUS_WORKING
				&& (clone $sandbox->getSleepDate())->modify('-1 day')->getTimestamp() <= (new DateTime())->getTimestamp()
			) {
				$sandbox->setSleepDate(
					(clone $sandbox->getSleepDate())
						->modify('+ '.$config['sandbox_active_days_period'].' days')
				);
				$sandboxRepository->save($sandbox);
			}
			break;
		case 'restart_sandbox':
			$sandbox = getSandboxFromRequest(intval($_REQUEST['sandboxId']), $sandboxRepository);
			$sandboxLock = Application::getSandboxLock($sandbox->getDomain());
			if ($sandboxLock->check()) {
				throw new Exception('Сейчас нельзя изменять состояние этой песочницы');
			}
			try {
				//Лочим песочницу на минуту, т.к. команда запуска выполняется быстро, но нужно время на поднятие контейнеров
				$sandboxLock->set();

				$sandbox->restart();
				if ($sandbox->isSleep()) {
					$sandboxRepository->save($sandbox);
				}
			} catch (Throwable $e) {
				$sandboxLock->release();
				throw $e;
			}
			break;
		case 'show_logs':
			$sandbox = getSandboxFromRequest(intval($_REQUEST['sandboxId']), $sandboxRepository);
			$composeProject = new DockerComposeProject($sandbox->getDomain());
			$composeProject->checkExists();
			$logsType = $_REQUEST['type'];
			if (!in_array($logsType, ['access', 'error']))
			{
				$logsType = 'access';
			}
			$response['logs'] = implode("\n", $composeProject->getLogs($_REQUEST['service'], $_REQUEST['period'] ?: '24h', $logsType)) ?: "Нет записей";
			break;
		default:
			throw new Exception('Неизвестное действие ' . $action);
	}
} catch (Throwable $e) {
	header('HTTP/1.1 500 Internal Server Error');
	$debugInfo = $config['containers_env'] != 'prod'
		? [
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'trace' => explode(PHP_EOL, $e->getTraceAsString()),
		]
		: [];
	$response = ['status' => 'error', 'message' => $e->getMessage(), ...$debugInfo];
}

die(json_encode($response));
