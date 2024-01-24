<?php

use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\DB\Connection;
use Intervolga\DockerSandboxManager\DB\Schema;
use Intervolga\DockerSandboxManager\Util\Config;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Registry;

ini_set('display_errors', 'on');
error_reporting(E_ERROR);
session_start();

$config = Config::getMainConfig();
if (
	$_SERVER['HTTP_HOST']
	&& $_SERVER['HTTP_HOST'] != getenv('SITE_HOST')
	&& !isset($GLOBALS['IS_404'])
	&& $config['containers_env'] == 'prod'
) {
	http_response_code(404);
	die();
}
Connection::getInstance($config['db'] ?? []);
//TODO: вынести в отдельный скрипт, который будет запускаться при старте контейнера
Schema::migrate();

$commandsLogger = new Logger(Application::COMMANDS_LOGGER_NAME);
$commandsLogger->pushHandler(
	(new RotatingFileHandler(
		$config['logs_dir'].'/'.Application::COMMANDS_LOGGER_NAME.'/log.log',
		10
	))->setFormatter(new \Monolog\Formatter\JsonFormatter())
);
Registry::addLogger($commandsLogger);

$sandboxSleepCheckerLogger = new Logger(Application::SANDBOX_SLEEP_CHECK_LOGGER_NAME);
$sandboxSleepCheckerLogger->pushHandler(
	(new RotatingFileHandler(
		$config['logs_dir'].'/'.Application::SANDBOX_SLEEP_CHECK_LOGGER_NAME.'/log.log',
		10
	))->setFormatter(new \Monolog\Formatter\JsonFormatter())
);
Registry::addLogger($sandboxSleepCheckerLogger);

$mailLogger = new Logger(Application::MAIN_LOGGER_NAME);
$mailLogger->pushHandler(
	(new RotatingFileHandler(
		$config['logs_dir'].'/'.Application::MAIN_LOGGER_NAME.'/log.log',
		10
	))->setFormatter(new \Monolog\Formatter\JsonFormatter())
);
Registry::addLogger($mailLogger);
