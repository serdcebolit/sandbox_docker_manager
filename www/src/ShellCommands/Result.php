<?php

namespace Intervolga\DockerSandboxManager\ShellCommands;

readonly class Result
{
	public function __construct(
		public ?string $stdout = null,
		public ?string $stderr = null,
	) {
	}
}