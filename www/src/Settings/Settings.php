<?php

namespace Local\DockerSandboxManager\Settings;

use Local\DockerSandboxManager\Entity\Sandbox\ISandbox;
use Local\DockerSandboxManager\Repository\SandboxRepository;
use Local\DockerSandboxManager\Util\DockerComposeProject;

abstract class Settings {
	protected DockerComposeProject $dockerContainer;
	protected SandboxRepository $sandboxRepository;
	protected ISandbox $sandbox;
	protected string $message = "";
	protected const SETTINGS_NAME = "";
	protected const SETTINGS_CODE = "";

	public function initData(
		DockerComposeProject $dockerComposeProject,
		SandboxRepository $sandboxRepository,
		ISandbox $sandbox
	): bool {
		$this->dockerContainer =$dockerComposeProject;
		$this->sandboxRepository = $sandboxRepository;
		$this->sandbox = $sandbox;
		$this->message = "";
		return true;
	}

	abstract public function save(array $params): bool;

	abstract public function getFormHtml(): string;

	public function getMessage(): string {
		return $this->message ? $this->message . ' (' . static::getName() . ')' : $this->message;
	}

	final public function getName(): string {
		return static::SETTINGS_NAME;
	}

	final public function getCode(): string {
		return static::SETTINGS_CODE;
	}
}