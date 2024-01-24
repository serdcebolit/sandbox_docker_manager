<?php

namespace Intervolga\DockerSandboxManager\ShellCommands;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SymfonyProcessExecutor implements ICommandsExecutor
{
	public function execute(array $command, array $env = [], string $cwd = null, int $timeout = 30): Result
	{
		// По умолчанию симфони пробрасывает env текущего процесса в дочерний, из-за чего может не подхватиться .env файл у песочницы
		// Решение из документации - передать false для тех env, которые не нужны. Поэтому делаем так для всех текущих
		$defaultEnv = array_map(fn ($value) => false, getenv());
		$process = new Process(
			command: array_filter($command),
			cwd: $cwd,
			env: array_merge($defaultEnv, $env, ['DOCKER_HOST' => getenv('DOCKER_HOST')]),
			timeout: $timeout,
		);

		$process->run();
		if (!$process->isSuccessful()) {
			throw new ProcessFailedException($process);
		}

		return new Result(
			stdout: $process->getOutput(),
			stderr: $process->getErrorOutput(),
		);
	}
}