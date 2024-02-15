<?php

namespace Local\DockerSandboxManager\Entity\QueueCommand;

use Local\DockerSandboxManager\Entity\Sandbox\ISandbox;

interface IQueueCommand {
	const STATUS_NEW = 'NEW';
	const STATUS_DONE = 'DONE';
	const STATUS_EXECUTING = 'EXECUTING';
	const STATUS_FAILED = 'FAILED';

	public function isNeedExec(): bool;
	public function isRunning(): bool;
	public function getId(): ?int;
	public function getSandbox(): ?ISandbox;
	public function getDateExec(): ?\DateTime;
	public function getStatus(): ?string;
	public function getParams(): array;
	public function setId(int $id): void;
	public function setParams(array $params): void;
	public function setStatus(string $status): void;
	public function exec(): bool;
	public function toArray(): array;
}