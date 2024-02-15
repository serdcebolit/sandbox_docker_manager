<?php

namespace Local\DockerSandboxManager\Entity\Sandbox;

use DateTime;

interface ISandbox {
	public static function fromArray(array $row): static;
	public function toArray(): array;
	public function getId(): ?int;
	public function getDomain(): ?string;
	public function getOwner(): ?string;
	public function getSleepDate(): ?DateTime;
	public function getDateCreate(): ?DateTime;
	public function isSleep(): bool;
	public function getStatus(): string;
	public function getFilesVolume(): ?int;
	public function getDbVolume(): ?int;
	public function setId(int $id): void;
	public function setDomain(string $domain): void;
	public function setOwner(string $owner): void;
	public function setSleepDate(?DateTime $date): void;
	public function setStatus(string $status): void;
    public function setFilesVolume(?int $filesVolume): void;
    public function setDbVolume(?int $dbVolume): void;
	public function sleep(): void;
	public function wakeUp(): void;
	public function restart(): void;
	public function getServicesStatus(): array;
	public function isNeedSleep(): bool;
	public function isCreated(): bool;
}