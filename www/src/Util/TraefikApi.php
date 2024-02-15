<?php

namespace Local\DockerSandboxManager\Util;

class TraefikApi {
	public function __construct(
		private string $baseUrl = '',
		private ?string $login = null,
		private ?string $password = null,
	) {
		!mb_strlen($this->baseUrl)
			&& throw new \Exception('Empty base url');
	}

	protected function sendGet(string $method, ?array $data = null): array {
		!mb_strlen($method)
			&& throw new \Exception('Empty api method');

		$authHeader = "Authorization: Basic ".base64_encode($this->login.':'.$this->password);
		$opts = [
			'http' => [
				'method'  => 'GET',
				'header'  => "Content-Type: application/json\n$authHeader",
			]
		];

		$context  = stream_context_create($opts);

		return json_decode(
			file_get_contents($this->baseUrl.$method.($data ? '?'.http_build_query($data) : ''), false, $context),
			true
		) ?? [];
	}

	public function getHttpRouters(): array {
		return $this->sendGet('/api/http/routers');
	}

	public function getDomains(): array {
		$result = $this->getHttpRouters();
		return array_filter(
			array_map(static function ($item) {
				if (preg_match('/Host\(`(?<host>[a-z0-9\.]*)`\)/m', $item, $matches)) {
					return $matches['host'];
				}
				return null;
			}, array_column($result, 'rule') ?? [])
		);
	}
}