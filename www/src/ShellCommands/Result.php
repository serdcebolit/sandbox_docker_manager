<?php

namespace Local\DockerSandboxManager\ShellCommands;

readonly class Result
{
	public function __construct(
		public ?string $stdout = null,
		public ?string $stderr = null,
	) {
	}
}