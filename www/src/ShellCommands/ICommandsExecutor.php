<?php

namespace Local\DockerSandboxManager\ShellCommands;

interface ICommandsExecutor
{
	public function execute(
		array $command,
		array $env = [],
		string $cwd = null,
		int $timeout = 30,
	): Result;
}