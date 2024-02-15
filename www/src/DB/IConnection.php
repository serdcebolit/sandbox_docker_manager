<?php

namespace Local\DockerSandboxManager\DB;

interface IConnection {
	public static function getInstance(?array $params = null): static;
	public function query(string $sql, array $params = []);
	public function prepare(string $sql);
}