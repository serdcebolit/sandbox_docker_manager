<?php

namespace Intervolga\DockerSandboxManager\DB;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

class Connection implements IConnection {
	private static ?self $instance = null;

	private ?\Doctrine\DBAL\Connection $connection = null;

	private function __construct(array $params) {
		$this->connection = DriverManager::getConnection($params);
	}

	public static function getInstance(?array $params = null): static {
		if (!static::$instance) {
			static::$instance = new static($params);
		}
		return static::$instance;
	}

	public function getOriginalConnection(): \Doctrine\DBAL\Connection {
		if (!$this->connection) {
			throw new \Exception('Application has not connection with db');
		}

		return $this->connection;
	}

	public function query(string $sql, array $params = []) {
		return $this->connection->executeQuery($sql, $params);
	}

	public function prepare(string $sql) {
		return $this->connection->prepare($sql);
	}

	public function builder(): QueryBuilder {
		return $this->connection->createQueryBuilder();
	}

	public function getSchemaManager(): AbstractSchemaManager {
		return $this->connection->createSchemaManager();
	}
}