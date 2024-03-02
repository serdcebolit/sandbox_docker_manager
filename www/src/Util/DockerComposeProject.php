<?php

namespace Local\DockerSandboxManager\Util;

use Exception;
use Local\DockerSandboxManager\Application;
use Local\DockerSandboxManager\Entity\Sandbox\DockerSandbox;
use Local\DockerSandboxManager\Exception\WrongRobotsFileException;
use Throwable;

class DockerComposeProject {

	const SANDBOXES_ROOT_DIR = '/home/bitrix/ext_www';
	const SANDBOX_DOCKER_ENV_PATH = '/home/bitrix/sandbox_docker_env';
	const CONTAINER_STATUSES_WORKING = ['restarting', 'running'];
	const CONTAINER_STATUSES_NOT_WORKING = ['created', 'removing', 'paused', 'exited', 'dead'];
	const LOCAL_DOCKER_COMPOSE_FILE_NAME = 'docker-compose.local.yml';

	const CREATION_MODES = [
		'empty' => 'Создать пустой сайт',
		'backup_restore' => 'Восстановить из резервной копии',
//		'repo_deploy' => 'Развернуть из репозитория',
//		'clone_from_exists' => 'Клонировать существующую',
		'install_bx' => 'Установить 1С-Битрикс Управление сайтом',
	];
	const PHP_VERSIONS = [
		'74' => '7.4',
		'73' => '7.3',
		'72' => '7.2',
		'71' => '7.1',
		'70' => '7.0',
		'80' => '8.0',
		'81' => '8.1',
		'82' => '8.2',
		'83' => '8.3',
	];

	const BX_INSTALL_REDACTIONS = [
		'business' => 'Бизнес',
		'small_business' => 'Малый бизнес',
		'standard' => 'Стандарт',
		'start' => 'Старт'
	];

	const DEFAULT_BX_INSTALL_REDACTION = 'standard';

	private ?string $projectDir;
	private array $mainConfig = [];

	public function __construct(
		private string $domain,
		private array $composeFiles = ['docker-compose.yml'],
	) {
		$this->projectDir = static::SANDBOXES_ROOT_DIR.'/'.$this->domain;

		$this->mainConfig = Config::getMainConfig();
		if ($this->mainConfig['containers_env'] == 'local' && !in_array(static::LOCAL_DOCKER_COMPOSE_FILE_NAME, $this->composeFiles)) {
			$this->composeFiles[] = static::LOCAL_DOCKER_COMPOSE_FILE_NAME;
		}
	}

	public static function filterWorkingServices(array $services): array {
		return array_filter(
			$services,
			static fn($container) => in_array(
				preg_replace('/\s\(.*?\)$/m', '', is_array($container) ? $container['State'] : $container->status),
				static::CONTAINER_STATUSES_WORKING
			)
		);
	}

	public function checkExists(): bool {
		if (!file_exists($this->projectDir)) {
			throw new Exception('Директория с сайтом не существует');
		}
		foreach ($this->composeFiles as $file) {
			if (!file_exists($this->projectDir.'/'.$file)) {
				throw new Exception(sprintf('Файл %s отсутствует в директории сайта', $file));
			}
		}

		return true;
	}

	public function create(
		DockerSandbox $sandbox,
		?string $mode,
		?string $phpVersion,
		?string $repoLink = null,
		?string $backupLink = null,
		?string $bxRedaction = null,
		?string $sshPassword = null,
		?string $bxPassword = null
	): void {
		try {
			$isExists = $this->checkExists();
		} catch (Throwable) {
			$isExists = false;
		}
		$isExists && throw new Exception('Проект уже существует');

		shell_exec(sprintf('cp -a %s %s', static::SANDBOX_DOCKER_ENV_PATH, $this->projectDir));

		!mb_strlen($sshPassword) && throw new Exception('Не указан пароль для ssh');

		$mainConfig = Config::getMainConfig();

		$configExample = $this->getEnvConfig($this->projectDir.'/.env');
		$configExample['SITE_HOST'] = str_replace('.'.$this->mainConfig['main_domain'], '', $this->domain);
		$configExample['OWNER_EMAIL'] = $sandbox->getOwner();
		$configExample['PHP_VERSION'] = $phpVersion;
		$configExample['MODE'] = $mode;
		$configExample['SSH_PASSWORD'] = $sshPassword;
		switch ($mode) {
			case 'empty':
				$newSiteStub = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/template/new_site_index.html');
				$newSiteStub = str_replace('#MAIN_DOMAIN#', sprintf('https://%s', $mainConfig['main_domain']), $newSiteStub);
				$robotsExample = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/robots.txt');

				!file_exists($this->projectDir.'/www')
					&& mkdir($this->projectDir.'/www');

				!file_put_contents($this->projectDir.'/www/index.html', $newSiteStub)
					&& throw new Exception('Не удалось создать файл index.html');
				!file_put_contents($this->projectDir.'/www/robots.txt', $robotsExample)
					&& throw new Exception('Не удалось создать файл robots.txt');
				break;
			case 'backup_restore':
				!mb_strlen($backupLink) && throw new Exception('Не указана ссылка на архив с резервной копией');
				$configExample['BACKUP_LINK'] = $backupLink;
				$configExample['NEED_EXEC_MANAGER'] = 1;
				break;
			case 'repo_deploy':
				!mb_strlen($repoLink) && throw new Exception('Не указана ссылка на репозиторий');
				$configExample['REPO'] = $repoLink;
				$configExample['NEED_EXEC_MANAGER'] = 1;
				break;
			case 'install_bx':
				!mb_strlen($bxRedaction) && throw new Exception('Не указана редакция Битрикса');
				!mb_strlen($bxPassword) && throw new Exception('Не указан пароль для админа сайта');
				$configExample['BX_REDACTION'] = $bxRedaction;
				$configExample['BX_ADMIN_PASSWORD'] = $bxPassword;
				$configExample['NEED_EXEC_MANAGER'] = 1;
				break;
		}
		$this->updateEnvConfig($this->projectDir.'/.env', $configExample);
	}

	public function updateEnvConfig(string $path, array $values): void {
		try {
			$config = $this->getEnvConfig($path);
		} catch (Throwable) {
			$config = [];
		}
		foreach ($values as $key => $val) {
			$config[$key] = $val;
		}
		!file_put_contents(
			$path,
			implode(PHP_EOL, array_map(
				static fn ($key, $val) => mb_strlen(trim($key)) ? "$key=$val" : '',
				array_keys($config), $config)
			)
		)
			&& throw new Exception('Не удалось произвести запись в файл '.$path);
	}

	public function getEnvConfig(string $path): array {
		!file_exists($path)
			&& throw new Exception('Указанный файл не существует: '.$path);
		$configFileRes = fopen($path, 'r');
		$config = [];
		while (!feof($configFileRes)) {
			[$key, $val] = explode('=', fgets($configFileRes));
			$config[trim($key)] = trim($val);
		}
		fclose($configFileRes);

		return $config;
	}

	public function start(array $containers = []): string {
		$this->checkExists();
		return $this->executeCommands([
			...explode(' ', $this->getCommandPrefix()),
			'up',
			'-d',
			'--build',
			...$containers
		]);
	}

	public function restart(array $containers = []): void {
		$this->checkExists();
		$this->executeCommands([
			...explode(' ', $this->getCommandPrefix()),
			'restart',
			...$containers
		]);
	}

	public function stop(array $containers = []): void {
		$this->checkExists();
		$this->executeCommands([
			...explode(' ', $this->getCommandPrefix()),
			'down',
			...$containers
		]);
	}

	public function getAccessLogs(string $serviceName = 'nginx', string $timeFilter = '24h'): array {
		$this->checkExists();
		$nginxContainer = current(array_filter($this->getActiveServices(), fn ($service) => $service['Service'] == $serviceName));

		!$nginxContainer && throw new Exception('Не удалось найти контейнер с сервисом nginx');

		return array_filter(
			explode(
				PHP_EOL,
				$this->executeCommands([
					'docker',
					'logs',
					'--timestamps',
					'--since',
					$timeFilter,
					$nginxContainer['ID']
				]),
			)
		);
	}

	public function getLogs(string $serviceName = 'nginx', string $timeFilter = '24h', string $type = 'access'): array {
		$this->checkExists();
		$container = current(array_filter($this->getAllServices(), fn ($service) => $service['Service'] == $serviceName));

		!$container && throw new Exception('Не удалось найти контейнер с сервисом '.$serviceName);

		$commandResult = Application::getCommandExecutor()->execute(command: [
			'docker',
			'logs',
			'--timestamps',
			'--since',
			$timeFilter,
			$container['ID']
		], cwd: $this->projectDir, timeout: 120);

		return array_filter(
			explode(
				PHP_EOL,
				$type == 'access' ? $commandResult->stdout : $commandResult->stderr,
			)
		);
	}

	public function getActiveServices(): array {
		$this->checkExists();
		return json_decode($this->executeCommands([
			...explode(' ', $this->getCommandPrefix()),
			'ps',
			'--format',
			'json',
		]), true) ?? [];
	}

	public function getAllServices(): array {
		$this->checkExists();
		return json_decode($this->executeCommands([
			...explode(' ', $this->getCommandPrefix()),
			'ps',
			'-a',
			'--format',
			'json',
		]), true) ?? [];
	}

	public function getPath(): string {
		return $this->projectDir;
	}

	/**
	 * Проверяет файл robots.txt
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function checkRobotsFileOrFail(): bool {
		$siteRoot = $this->projectDir.'/www';

		!file_exists($siteRoot.'/robots.txt')
			&& throw new WrongRobotsFileException('Файл robots.txt отсутствует');

		!preg_match('/^User-agent:\s*\*\s*Disallow:\s*\/\s*(Sitemap:\s*http[s]?:\/\/(www\.)?(?<host>[A-z0-9\.-]+?)\/sitemap\.xml\s*)?$/i', trim(file_get_contents($siteRoot.'/robots.txt')), $matches)
		&& throw new WrongRobotsFileException('Файл robots.txt не соответствует эталонному');

		if (trim($matches['host']) && $matches['host'] == $this->domain)
		{
			throw new WrongRobotsFileException('Домен в блоке Sitemap совпадает с доменом песочницы');
		}

		return true;
	}

	/**
	 * Копирует в папку песочницы правильный файл robots.txt
	 *
	 * @return void
	 * @throws Exception
	 */
	public function setRightRobotsFile(): void {
		$siteRoot = $this->projectDir.'/www';
		!copy($_SERVER['DOCUMENT_ROOT'].'/robots.txt', $siteRoot.'/')
			&& throw new \Exception('Не удалось скопировать файл robots.txt');
	}

	private function executeCommands(array $commands): string {
		return Application::getCommandExecutor()->execute(command: $commands, cwd: $this->projectDir, timeout: 60 * 30)->stdout ?? '';
	}

	private function getCommandPrefix(): string {
		return 'docker-compose '.implode(' ', array_map(static function ($file) {
			return '-f '.$file;
			}, $this->composeFiles));
	}

	/**
	 * @throws Exception
	 */
	public function getAuthorizedKeys(): array {
		$bxHomeDir = dirname($_SERVER['DOCUMENT_ROOT']);
		$sandboxDir = str_replace('.' . $this->getEnvConfig($this->getPath() . '/.env')['MAIN_DOMAIN'], '', $this->domain);

		if (!file_exists($bxHomeDir . "/sshpiper/${sandboxDir}/")) {
			throw new Exception('Директория с настройками ssh не найдена');
		}

		$filePath = $bxHomeDir . "/sshpiper/${sandboxDir}/authorized_keys";

		if(!file_exists($filePath)) {
			throw new Exception('Файл с ключами не найден');
		}

		return explode(PHP_EOL, file_get_contents($filePath));
	}

	/**
	 * @throws Exception
	 */
	public function saveAuthorizedKeys(array $keys): void {
		$bxHomeDir = dirname($_SERVER['DOCUMENT_ROOT']);

		$sandboxDir = str_replace('.' . $this->getEnvConfig($this->getPath() . '/.env')['MAIN_DOMAIN'], '', $this->domain);
		$keys = array_filter(array_map(fn ($key) => trim($key), $keys));

		if (!file_exists($bxHomeDir . "/sshpiper/${sandboxDir}/")) {
			mkdir($bxHomeDir . "/sshpiper/${sandboxDir}");
		}

		if (file_put_contents($bxHomeDir . "/sshpiper/${sandboxDir}/authorized_keys", implode(PHP_EOL, $keys)) === false) {
			throw new Exception('Не удалось сохранить файл');
		}
	}

	/**
	 * Проверяет наличие публичного ключа в файла authorized_keys внутри песочницы
	 *
	 * @return void
	 * @throws Exception
	 */
	public function checkAndCopyPublicKey(): void {
		$bxHomeDir = dirname($_SERVER['DOCUMENT_ROOT']);

		$sandboxDir = str_replace('.' . $this->getEnvConfig($this->getPath() . '/.env')['MAIN_DOMAIN'], '', $this->domain);

		$authorizedKeysInSandbox = array_filter(explode(PHP_EOL, file_get_contents($this->getPath() . '/ssh_keys/authorized_keys')));

		if (!file_exists($bxHomeDir . "/sshpiper/${sandboxDir}/")) {
			mkdir($bxHomeDir . "/sshpiper/${sandboxDir}");
		}

		$publicKeyPath = $bxHomeDir . "/sshpiper/${sandboxDir}/id_rsa.pub";

		if (!file_exists($publicKeyPath))
			return;

		$publicKeyContent = file_get_contents($publicKeyPath);

		if (!in_array($publicKeyContent, $authorizedKeysInSandbox)) {
			$authorizedKeysInSandbox[] = $publicKeyContent;
			if (file_put_contents($this->getPath() . '/ssh_keys/authorized_keys', implode(PHP_EOL, $authorizedKeysInSandbox)) === false) {
				throw new Exception('Не удалось сохранить файл ' . $this->getPath() . '/ssh_keys/authorized_keys');
			}
		}
	}
}