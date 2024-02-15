<?php

namespace Local\DockerSandboxManager\Repository;

use Local\DockerSandboxManager\Application;
use Local\DockerSandboxManager\DB\Connection;
use Local\DockerSandboxManager\Entity\QueueCommand\IQueueCommand;
use Local\DockerSandboxManager\Entity\Sandbox\SandboxCollection;
use Local\DockerSandboxManager\Entity\Sandbox\DockerSandbox;
use Local\DockerSandboxManager\Entity\Sandbox\ISandbox;

class SandboxRepository {

	public function __construct(
		private Connection $connection,
		private string $tableName = 'sites',
	) {

	}

	public function findById(int $id, array $select = ['*']): ?ISandbox {
		$item = $this->connection->builder()
			->select(...$select)
			->from($this->tableName)
			->where('id = :id')
			->setParameter('id', $id)
			->executeQuery()
			->fetchAssociative();

		return $item
			? DockerSandbox::fromArray($item)
			: null;
	}

	public function findByDomain(string $domain, array $select = ['*']): ?ISandbox {
		$item = $this->connection->builder()
			->select(...$select)
			->from($this->tableName)
			->where('domain = :domain')
			->setParameter('domain', $domain)
			->executeQuery()
			->fetchAssociative();

		return $item
			? DockerSandbox::fromArray($item)
			: null;
	}

	public function getAll(array $select = ['*']): SandboxCollection {
		$items = $this->connection->builder()
			->select(...$select)
			->from($this->tableName)
			->executeQuery()
			->fetchAllAssociative();
		return new SandboxCollection(
			array_map(static fn($item) => DockerSandbox::fromArray($item), $items)
		);
	}

	public function getAllWithLastCommandStatus(
		array $select = ['*'],
		bool $excludeProcessing = false,
		array $sort = ['id' => 'ASC'],
	): SandboxCollection {
		$commandTableName = Application::getQueueCommandRepository()->getTableName();
		$queryBuilder = $this->connection->builder();

		$sitesTableAlias = 's';
		$commandTableAlias = 'q';

		$subQuery = $this->connection->builder()
			->select('MAX(id)')
			->from($commandTableName)
			->groupBy('site_id');

		$itemsQuery = $this->connection->builder()
			->select(
				$commandTableAlias.'.status as last_command_status',
				$sitesTableAlias.'.files_volume + '.$sitesTableAlias.'.db_volume as volume_summary',
				...array_map(
					static fn ($item) => $sitesTableAlias.'.'.$item,
					$select
				)
			)
			->from($this->tableName, $sitesTableAlias)
			->leftJoin(
				$sitesTableAlias,
				$commandTableName,
				$commandTableAlias,
				sprintf('%s.id = %s.site_id', $sitesTableAlias, $commandTableAlias)
			)
			->where(
				$queryBuilder->expr()->or(
					$queryBuilder->expr()->isNull($commandTableAlias.'.id'),
					$queryBuilder->expr()->in($commandTableAlias.'.id', $subQuery->getSQL())
				)
			);
		foreach ($sort as $column => $direction) {
			$itemsQuery->addOrderBy($column, $direction);
		}
		if ($excludeProcessing) {
			$itemsQuery
				->andWhere($commandTableAlias.'.status <> :status')
				->orWhere($commandTableAlias.'.status IS NULL')
				->setParameter('status', IQueueCommand::STATUS_EXECUTING);
		}
		$items = $itemsQuery
			->executeQuery()
			->fetchAllAssociative();
		$items = array_map(static function ($item) {
			$sandbox = DockerSandbox::fromArray($item);
			$sandboxStatus = match ($item['last_command_status']) {
				IQueueCommand::STATUS_EXECUTING => DockerSandbox::STATUS_PROCESSING,
				IQueueCommand::STATUS_FAILED => DockerSandbox::STATUS_FAILED,
				default => null,
			};
			$sandboxStatus
				&& $sandbox->setStatus($sandboxStatus);
			return $sandbox;
		}, $items);
		return new SandboxCollection($items);
	}

	public function save(ISandbox $sandbox): void {
		$sandboxArr = $sandbox->toArrayForDb();
		$query = $this->connection->builder();
		if ($sandbox->getId()) {
			$query->update($this->tableName);
		} else {
			$query->insert($this->tableName);
		}
		foreach ($sandboxArr as $field => $value) {
			if (isset($value)) {
				if ($sandbox->getId()) {
					if ($field != 'id') {
						$query->set($field, ':'.$field);
					} else {
						$query->where($field.' = '.$value);
					}
				} else {
					if ($field != 'id') {
						$query->setValue($field, ':'.$field);
					}
				}

				if ($value instanceof \DateTime) {
					$value = $value->format('Y-m-d H:i:s');
				}
				$query->setParameter($field, $value);
			}
		}

		$query->executeQuery();
		if (!$sandbox->getId() && ($lastId = $this->connection->getOriginalConnection()->lastInsertId())) {
			$sandbox->setId($lastId);
		}
	}
}