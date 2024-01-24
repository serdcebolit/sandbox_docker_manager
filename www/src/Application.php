<?php

namespace Intervolga\DockerSandboxManager;

use Intervolga\DockerSandboxManager\DB\Connection;
use Intervolga\DockerSandboxManager\Repository\QueueCommandRepository;
use Intervolga\DockerSandboxManager\Repository\SandboxRepository;
use Intervolga\DockerSandboxManager\Sandbox\DockerSandboxServicesCollector;
use Intervolga\DockerSandboxManager\Sandbox\ISandboxServicesCollector;
use Intervolga\DockerSandboxManager\Util\Config;
use Intervolga\DockerSandboxManager\Util\DockerStatsProvider;
use Intervolga\DockerSandboxManager\Util\SandboxLock;
use Intervolga\DockerSandboxManager\Util\TraefikApi;
use Monolog\Registry;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Application {
	const COMMANDS_LOGGER_NAME = 'commands_logger';
	const SANDBOX_SLEEP_CHECK_LOGGER_NAME = 'sandbox_sleep_check_logger';
	const MAIN_LOGGER_NAME = 'main_logger';
	const CACHE_ROOT_KEY = 'ivdev_sandbox_manager';

	protected static ?ArrayAdapter $inMemoryCacheInstance = null;

	public static function getConection(): Connection {
		return Connection::getInstance();
	}

	public static function getSandboxRepository(): SandboxRepository {
		return new SandboxRepository(static::getConection());
	}

	public static function getQueueCommandRepository(): QueueCommandRepository {
		return new QueueCommandRepository(static::getConection());
	}

	public static function getLogger(string $loggerName): LoggerInterface {
		return Registry::getInstance($loggerName);
	}

	public static function getCommandsLogger(): LoggerInterface {
		return static::getLogger(static::COMMANDS_LOGGER_NAME);
	}

	public static function getSandboxSleepCheckLogger(): LoggerInterface {
		return static::getLogger(static::SANDBOX_SLEEP_CHECK_LOGGER_NAME);
	}

	public static function getMainLogger(): LoggerInterface {
		return static::getLogger(static::MAIN_LOGGER_NAME);
	}

	public static function getSandboxLock(string $domain): SandboxLock {
		return new SandboxLock(
			$domain,
			new FilesystemAdapter('', 0, $_SERVER['DOCUMENT_ROOT'].'/cache'),
		);
	}

	public static function getDockerStatsProvider(): DockerStatsProvider {
		return new DockerStatsProvider(
			new FilesystemAdapter('', 0, $_SERVER['DOCUMENT_ROOT'].'/cache'),
		);
	}

	public static function getMailer(): PHPMailer {
		$mailer = new PHPMailer(true);

		$config = Config::getMainConfig()['mail'];

		$mailer->isSMTP();
		$mailer->Host = $config['host'];
		$mailer->SMTPAuth = true;
		$mailer->Username = $config['login'];
		$mailer->Password = $config['password'];
		$mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		$mailer->Port = $config['port'];
		$mailer->CharSet = 'UTF-8';

		$mailer->setFrom($config['login'], $config['from_name']);

		return $mailer;
	}

	public static function getTraefikApi(): TraefikApi {
		$config = Config::getMainConfig()['traefik'];
		return new TraefikApi(
			$config['baseUrl'],
			$config['login'],
			$config['password'],
		);
	}

	public static function getInMemoryCache(): ArrayAdapter {
		if (is_null(static::$inMemoryCacheInstance)) {
			static::$inMemoryCacheInstance = new ArrayAdapter(60);
		}
		return static::$inMemoryCacheInstance;
	}

	public static function getSandboxServicesCollector(): ISandboxServicesCollector {
		return new DockerSandboxServicesCollector(static::getInMemoryCache());
	}

	public static function getCommandExecutor(): ShellCommands\ICommandsExecutor {
		return new ShellCommands\SymfonyProcessExecutor();
	}
}