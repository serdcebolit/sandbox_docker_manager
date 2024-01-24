<?php

namespace Intervolga\DockerSandboxManager\Sandbox;

use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\Entity\Sandbox\ISandbox;
use Intervolga\DockerSandboxManager\Util\Config;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Сервис для сбора данных о сервисах, запущенных в песочницах
 * Кеширует результаты, чтобы не делать запросы к докеру каждый раз
 */
readonly class DockerSandboxServicesCollector implements ISandboxServicesCollector {

	public function __construct(
		protected AdapterInterface $cache,
		protected int $cacheTime = 60,
	) {
	}

	/**
	 * @param ISandbox $sandbox
	 * @return array|null
	 */
	public function getForSandbox(ISandbox $sandbox): ?array {
		return $this->getAllCached()[$sandbox->getDomain()];
	}

	/**
	 * @param string $domain
	 * @return SandboxService[]|null
	 */
	public function getForDomain(string $domain): ?array {
		return $this->getAllCached()[$domain];
	}

	/**
	 * @return SandboxService[][]
	 */
	protected function getAllCached(): array {
		return $this->cache->get('docker_sandboxes_services', fn(ItemInterface $item) => $this->cacheHandler($item));
	}

	/**
	 * @return SandboxService[][]
	 */
	protected function cacheHandler(ItemInterface $item): array {
		$item->expiresAfter($this->cacheTime);
		return $this->getAllServices();
	}

	/**
	 * @return SandboxService[][]
	 */
	protected function getAllServices(): array {
		$result = [];
		$servicesRaw = array_map(static function (string $row): array {
			if (mb_substr($row, 0, 1) == "'" && mb_substr($row, mb_strlen($row) - 1, 1) == "'") {
				$row = mb_substr($row, 1, mb_strlen($row) - 2);
			}
			$row = json_decode($row, true);
			$labels = [];
			foreach (explode(',', $row['Labels']) ?: [] as $label) {
				$label = explode('=', $label);
				$labels[$label[0]] = implode('=', array_slice($label, 1));
			}
			$row['Labels'] = $labels;
			return $row;
		}, Application::getDockerStatsProvider()->getContainersData([], "'{{json .}}'"));

		$config = Config::getMainConfig();
		foreach ($servicesRaw as $s) {
			if (stripos($s['Labels']['com.docker.compose.project.working_dir'], '/'.ltrim($config['sandboxes_root_path'], '/.')) === false)
				continue;

			$domain = basename($s['Labels']['com.docker.compose.project.working_dir']);
			if (!filter_var($domain, FILTER_VALIDATE_DOMAIN))
				continue;

			$result[$domain] = $result[$domain] ?? [];
			$result[$domain][] = new SandboxService(
				$s['ID'],
				$s['Labels']['com.docker.compose.service'],
				$s['State'],
				$domain
			);
		}

		return $result;
	}
}