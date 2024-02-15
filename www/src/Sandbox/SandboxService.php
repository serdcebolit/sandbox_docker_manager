<?php

namespace Local\DockerSandboxManager\Sandbox;

class SandboxService {
	public function __construct(
		public string $id,
		public string $name,
		public string $status,
		public string $sandboxDomain,
	) {
	}
}