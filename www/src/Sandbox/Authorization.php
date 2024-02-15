<?php

namespace Local\DockerSandboxManager\Sandbox;

use Local\DockerSandboxManager\Util\DockerComposeProject;

class Authorization
{
	protected static ?self $instance = null;
	protected array $userSandboxes = [];

	private function __construct() {
		$this->userSandboxes = $_SESSION['USER_SANDBOXES'] ?? [];
	}

	public static function getInstance(): self {
		if (!static::$instance) {
			static::$instance = new self();
		}

		return static::$instance;
	}

	/**
	 * @throws \Exception
	 */
	public function isHasPermission(string $password, string $domain): bool {
		return in_array($domain, $this->userSandboxes) || $password == $this->getSandboxSshPassword($domain);
	}

	/**
	 * @throws \Exception
	 */
	protected function getSandboxSshPassword(string $domain): string {
		$container =  new DockerComposeProject($domain);
		$env = $container->getEnvConfig($container->getPath() . '/.env');

		return $env['SSH_PASSWORD'];
	}

	public function addUserSandbox(string $domain): void {
		if (!in_array($domain, $this->userSandboxes)) {
			$_SESSION['USER_SANDBOXES'][] = $domain;
			$this->userSandboxes[] = $domain;
		}
	}
}