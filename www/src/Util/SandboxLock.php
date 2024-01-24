<?php

namespace Intervolga\DockerSandboxManager\Util;

use DateTime;
use Intervolga\DockerSandboxManager\Application;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use InvalidArgumentException;
use Symfony\Component\Cache\CacheItem;

/**
 * Класс для установки локов, связанных с песочницами
 */
class SandboxLock {

	public function __construct(
		private ?string $sandboxDomain,
		private AbstractAdapter $cache,
		private int $cacheTime = 60,
	) {
	}

	public function setSandboxDomain(string $domain): void {
		!mb_strlen($domain) && throw new InvalidArgumentException('Неверно указан домен');

		$this->sandboxDomain = $domain;
	}

	public function setLockTime(int $time): void {
		$this->cacheTime = $time;
	}

	public function check(?string $key = 'any'): bool {
		return boolval($this->getCacheItem($key)->get());
	}

	public function set(?string $key = 'any'): void {
		$cacheItem = $this->getCacheItem($key);
		$cacheItem->expiresAt(
			(new DateTime())
				->modify(sprintf('+ %d seconds', $this->cacheTime))
		);
		$cacheItem->set(1);
		$this->cache->save($cacheItem);
	}

	public function release(?string $key = 'any'): void {
		$cacheItem = $this->getCacheItem($key);
		if ($cacheItem->isHit()) {
			$this->cache->deleteItem($cacheItem->getKey());
		}
	}

	protected function getCacheItemKey(string $key): string {
		return sprintf(
			'%s.sandbox_lock.%s.%s',
			Application::CACHE_ROOT_KEY,
			str_replace('.', '_', $this->sandboxDomain),
			$key
		);
	}

	protected function getCacheItem(string $key): CacheItem {
		return $this->cache->getItem($this->getCacheItemKey($key));
	}
}