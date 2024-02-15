<?php

namespace Local\DockerSandboxManager\Entity\Sandbox;

use DateTime;
use Local\DockerSandboxManager\Application;
use Local\DockerSandboxManager\Util\Config;
use Local\DockerSandboxManager\Util\DockerComposeProject;
use Throwable;

class DockerSandbox implements ISandbox {
	const MAP_FIELD_TO_DB_NAMES = [
		'owner' => 'owner_email',
		'sleepDate' => 'sleep_exec_datetime',
		'dateCreate' => 'date_create',
		'isPermanentWorking' => 'is_permanent_working',
		'filesVolume' => 'files_volume',
		'dbVolume' => 'db_volume',
	];

	const STATUS_WORKING = 'WORKING';
	const STATUS_STOPPED = 'STOPPED';
	const STATUS_PROCESSING = 'PROCESSING';
	const STATUS_FAILED = 'FAILED';
	const STATUS_UNKNOWN = 'UNKNOWN';

	private ?DockerComposeProject $composeProject = null;

	private array $servicesStatusCache = [];
	private ?string $status = null;


	public function __construct(
		private ?int      $id = null,
		private ?string   $domain = null,
		private ?string   $owner = null,
		private ?DateTime $sleepDate = null,
		private ?DateTime $dateCreate = null,
		private ?bool $isPermanentWorking = false,
		private ?int $filesVolume = null,
		private ?int $dbVolume = null,
	) {
		if (!$this->id && !$this->dateCreate) {
			$this->dateCreate = new DateTime();
		}
	}

	public static function fromArray(array $row): static {
		return new static(
			$row['id'],
			$row['domain'],
			$row['owner_email'],
			$row['sleep_exec_datetime'] instanceof DateTime
				? $row['sleep_exec_datetime']
				: new DateTime($row['sleep_exec_datetime']),
			$row['date_create'] instanceof DateTime
				? $row['date_create']
				: new DateTime($row['date_create']),
			boolval($row['is_permanent_working']),
			intval($row['files_volume']),
			intval($row['db_volume'])
		);
	}

	public function toArray(): array {
		return [
			'id' => $this->getId(),
			'domain' => $this->getDomain(),
			'owner' => $this->getOwner(),
			'sleepDate' => $this->getSleepDate(),
			'dateCreate' => $this->getDateCreate(),
			'isPermanentWorking' => $this->isPermanentWorking,
			'filesVolume' => $this->getFilesVolume(),
			'dbVolume' => $this->getDbVolume(),
		];
	}

	public function toArrayForDb(): array {
		$item = $this->toArray();
		foreach (static::MAP_FIELD_TO_DB_NAMES as $field => $dbfield) {
			if (array_key_exists($field, $item)) {
				$item[$dbfield] = $item[$field];
				unset($item[$field]);
			}
		}

		return $item;
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function getDomain(): ?string	{
		return $this->domain;
	}

	public function getOwner(): ?string {
		return $this->owner;
	}

	public function getSleepDate(): ?DateTime {
		return $this->sleepDate;
	}

	public function getDateCreate(): ?DateTime	{
		return $this->dateCreate;
	}

	public function isSleep(): bool {
		return $this->sleepDate->getTimestamp() < (new DateTime())->getTimestamp();
	}

	public function getFilesVolume(): ?int
	{
		return $this->filesVolume;
	}

	public function getDbVolume(): ?int
	{
		return $this->dbVolume;
	}

	public function setId(int $id): void {
		$this->id = $id;
	}

	public function setDomain(string $domain): void {
		$this->domain = $domain;
	}

	public function setOwner(string $owner): void {
		$this->owner = $owner;
	}

	public function setSleepDate(?DateTime $date): void {
		$this->sleepDate = $date;
	}

	public function setStatus(string $status): void {
		if (in_array($status, [static::STATUS_WORKING, static::STATUS_STOPPED, static::STATUS_PROCESSING, static::STATUS_FAILED])) {
			$this->status = $status;
		}
	}

	public function setFilesVolume(?int $filesVolume): void {
		$this->filesVolume = $filesVolume;
	}

	public function setDbVolume(?int $dbVolume): void {
		$this->dbVolume = $dbVolume;
	}

	public function sleep(): void {
		$this->setComposeProject();
		$activeContainers = DockerComposeProject::filterWorkingServices($this->getServicesStatus());
		if (count($activeContainers)) {
			$this->composeProject->stop();
		}
		$this->status = null;
	}

	public function wakeUp(): void {
		$this->setComposeProject();
		$this->composeProject->start();
		$this->status = null;
	}

	public function restart(): void {
		$this->setComposeProject();
		$this->composeProject->restart();
		$this->status = null;
	}

	public function getServicesStatus(): array {
		$this->setComposeProject();
		if (!count($this->servicesStatusCache)) {
			$this->servicesStatusCache = $this->composeProject->getActiveServices();
		}
		return $this->servicesStatusCache;
	}

	public function getStatus(): string {
		try {
			return $this->status
				?: (
				!count(DockerComposeProject::filterWorkingServices(Application::getSandboxServicesCollector()->getForSandbox($this) ?? []))
					? static::STATUS_STOPPED
					: static::STATUS_WORKING
				);
		} catch (Throwable) {
			$this->status = static::STATUS_UNKNOWN;
			return $this->status;
		}
	}

	public function isNeedSleep(): bool {
		$this->setComposeProject();
		try {
			//Предотвращаем остановку песочниц, которые должны работать постоянно
			if ($this->isPermanentWorking)
				return false;

			//Не трогаем песочницы, которые уже остановлены или сейчас разворачиваются
			if (in_array($this->getStatus(), [static::STATUS_STOPPED, static::STATUS_PROCESSING]))
				return false;

			$statsProvider = Application::getDockerStatsProvider();

			$config = Config::getMainConfig();

			//Ищем среди запущенных сервисов nginx. Если он работает меньше срока из конфига, то не останавливаем песочницу
			$activeServices = $this->composeProject->getActiveServices();
			foreach ($activeServices as $service) {
				if ($service['Service'] == 'nginx') {
					$containerInfo = $statsProvider->getContainerData($service['ID']);
					preg_match(
						'/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?<secondFloat>\.\d*)Z/m',
						$containerInfo['State']['StartedAt'],
						$dateMatches
					);
					$fixedDateStr = $containerInfo['State']['StartedAt'];
					if ($dateMatches['secondFloat']) {
						$fixedDateStr = str_replace($dateMatches['secondFloat'], '', $fixedDateStr);
					}
					$startDate = DateTime::createFromFormat(DateTime::ISO8601, $fixedDateStr);
					if (
						$containerInfo['State']['Running']
						&& $startDate->getTimestamp() >= (new DateTime())->modify('-'. $config['sandbox_active_days_period'].' days')->getTimestamp()
					) {
						return false;
					}
				}
			}
			$lastAccessLogs = $this->composeProject->getAccessLogs(
				timeFilter: ($config['sandbox_active_days_period'] * 24).'h'
			);
			//Ищем хиты с IP из конфига
			$accessLogsWithAllowedIps = array_filter(
				$lastAccessLogs,
				static function ($log) use ($config): bool {
					[$realIp] = array_reverse(explode(" ", $log));
					$realIp = str_replace('"', '', $realIp);
					return $realIp && in_array($realIp, $config['ip_addresses_for_access_logs']);
				}
			);
			//Хиты с положительным статусом ответа
			$successHits = array_filter(
				$lastAccessLogs,
				static function ($log) use ($config): bool {
					$responseStatusCode = intval(explode(" ", $log)[8]);
					return $responseStatusCode >= 200 && $responseStatusCode < 300;
				}
			);
			//Хиты на /bitrix/
			$bxAdminHits = array_filter(
				$lastAccessLogs,
				fn ($log) => str_starts_with(trim(explode(' ', $log)[10]), '/bitrix/admin/'),
			);
			//Останавливаем песочницу либо если нет вообще хитов, либо если нет хитов с офисных IP и не 2ХХ статусов ответа и не хитов в админке битрикса
			return !count($lastAccessLogs)
				|| (!count($accessLogsWithAllowedIps) && !count($successHits))
				|| (!count($accessLogsWithAllowedIps) && !count($bxAdminHits));
		} catch (Throwable) {
			return false;
		}
	}

	public function isCreated(): bool
	{
		$this->setComposeProject();
		try {
			return $this->composeProject->checkExists();
		} catch (Throwable) {
			return false;
		}
	}

	protected function setComposeProject(): void {
		if (!$this->composeProject) {
			$this->composeProject = new DockerComposeProject($this->domain);
		}
	}
}