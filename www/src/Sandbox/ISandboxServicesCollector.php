<?php

namespace Intervolga\DockerSandboxManager\Sandbox;

use Intervolga\DockerSandboxManager\Entity\Sandbox\ISandbox;

interface ISandboxServicesCollector {


	/**
	 * @param ISandbox $sandbox
	 * @return array|null
	 */
	public function getForSandbox(ISandbox $sandbox): ?array;

	public function getForDomain(string $domain): ?array;
}