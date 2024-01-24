<?php

namespace Intervolga\DockerSandboxManager\Entity\QueueCommand;

use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\Entity\Sandbox\ISandbox;
use Intervolga\DockerSandboxManager\Repository\QueueCommandRepository;
use Psr\Log\LoggerInterface;

abstract class BaseCommand implements IQueueCommand {
	protected ?QueueCommandRepository $repository;
	protected LoggerInterface $logger;

	public function __construct(
		protected ?int       $id = null,
		protected ?ISandbox  $sandbox = null,
		protected ?\DateTime $dateCreate = null,
		protected ?\DateTime $dateExec = null,
		protected ?string    $status = null,
		protected array      $params = [],
	) {
		$this->repository = Application::getQueueCommandRepository();
		$this->logger = Application::getCommandsLogger();
	}

	public function isNeedExec(): bool {
		return $this->status == static::STATUS_NEW;
	}

	public function isRunning(): bool {
		return $this->status == static::STATUS_EXECUTING;
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function getSandbox(): ?ISandbox {
		return $this->sandbox;
	}

	public function getDateExec(): ?\DateTime {
		return $this->dateExec;
	}

	public function getStatus(): ?string {
		return $this->status;
	}

	public function getParams(): array {
		return $this->params;
	}

	public function setId(int $id): void {
		$this->id = $id;
	}

	public function setParams(array $params): void {
		$this->params = $params;
	}
	public function setStatus(string $status): void {
		if (in_array($status, [static::STATUS_DONE, static::STATUS_FAILED])) {
			$this->dateExec = new \DateTime();
		}
		$this->status = $status;
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'site_id' => $this->sandbox->getId(),
			'date_create' => $this->dateCreate,
			'date_exec' => $this->dateExec,
			'status' => $this->status,
			'params' => $this->params,
			'command' => (new \ReflectionClass(get_called_class()))->getShortName(),
		];
	}
}