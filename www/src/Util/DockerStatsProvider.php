<?php

namespace Intervolga\DockerSandboxManager\Util;

use DateTime;
use Exception;
use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\Exception\DockerNotAvailableException;
use Intervolga\DockerSandboxManager\ShellCommands\ICommandsExecutor;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DockerStatsProvider {
	public function __construct(
		private AbstractAdapter $cache,
		private int $cacheTime = 60 * 60,
		private ?ICommandsExecutor $commandsExecutor = null,
	) {
		$this->commandsExecutor ??= Application::getCommandExecutor();
		$this->checkDockerAvailable();
	}

	private function checkDockerAvailable(): void {
		try {
			$this->commandsExecutor->execute(['docker', '-v']);
		} catch (ProcessFailedException $e) {
			if ($e->getProcess()->getExitCodeText() == 'Command not found') {
				throw new DockerNotAvailableException(message: 'Docker недоступен в системеы', previous: $e);
			} else {
				throw $e;
			}
		}
	}

	/**
	 * Возвращает массив по песочницам с занимаемым местом
	 *
	 * @return array
	 */
	public function getVolumesStatsForSandboxes(array $domains): array {
		$result = [];

		$config = Config::getMainConfig();

		$containers = $this->getContainersData(
			[
				'name=httpd',
				'name=mysql',
			],
			'{"ID": "{{.ID}}", "Dir": "{{.Label "com.docker.compose.project.working_dir"}}"}'
		);
		$containers = array_map(
			fn ($cInfo) => json_decode($cInfo, true)['ID'],
			array_filter($containers, static function ($c) use ($domains) {
				$cData = json_decode($c, true);
				return in_array(basename($cData['Dir']), $domains);
			}),
		);
		$volumeStatsResponse = $this->commandsExecutor->execute([
			'docker',
			'system',
			'df',
			'-v',
			'--format',
			'{{json .Volumes}}',
		]);
		if ($volumeStatsResponse->stderr) {
			throw new \Exception($volumeStatsResponse['stderr']);
		}
		$volumeStats = json_decode(
			$volumeStatsResponse->stdout,
			true
		);
		$volumeStats = array_combine(
			array_column($volumeStats ?? [], 'Name') ?? [],
			$volumeStats ?? []
		);
		foreach ($domains as $domain)
		{
			$composeProject = str_replace('.', '', $domain);
			$domainVolumeStats = $volumeStats[$composeProject.'_mysql_data'];
			if ($domainVolumeStats) {
				$result[$domain]['mysql'] = FileSizeHelper::formatHumanToBytes($domainVolumeStats['Size'] ?? '');
			}
			$httpdPath = '/'.trim($config['sandboxes_root_path'], '/.').'/'.$domain;
			if (file_exists($httpdPath)) {
				[$size, ] = explode("\t", Application::getCommandExecutor()->execute([
					'du',
					'-sb',
					$httpdPath.'/www/'
				], ['sudo' => true])->stdout);
				$result[$domain]['httpd'] = $size;
			}
		}

		return $result;
	}

	public function getContainerData(string $containerId): array {
		!mb_strlen($containerId) && throw new Exception('Empty container id');

		$result = json_decode(
			$this->commandsExecutor->execute(['docker', 'inspect', $containerId])->stdout,
			true
		);

		return array_shift($result) ?? [];
	}

	public function getContainersData(array $filter = [], string $format = ''): array {
		return array_filter(explode(
			PHP_EOL,
			$this->commandsExecutor->execute([
				'docker',
				'ps',
				'-a',
				...$this->getFilter($filter),
				'--format',
				$format,
			])->stdout,
		));
	}

	protected function getCacheKey(string $key): string {
		return sprintf(
			'%s.docker_stats.%s',
			Application::CACHE_ROOT_KEY,
			$key
		);
	}

	protected function getFilterStr(array $filter): string {
		return implode(
			' ',
			array_map(fn ($val) => sprintf('--filter "%s"', $val), $filter)
		);
	}

	protected function getFilter(array $filter): array {
		return array_reduce(
			$filter,
			fn ($acc, $val) => [...$acc, '--filter', $val],
			[]
		);
	}
}