<?php

namespace Local\DockerSandboxManager\Repository;

use Local\DockerSandboxManager\DB\Connection;
use Local\DockerSandboxManager\Entity\QueueCommand\CommandFactory;
use Local\DockerSandboxManager\Entity\QueueCommand\IQueueCommand;

class QueueCommandRepository {

	public function __construct(
		private Connection $connection,
		private string $tableName = 'site_queue',
	) {

	}

	public function getTableName(): string {
		return $this->tableName;
	}

	public function getFirstForExec(array $select = ['*']): ?IQueueCommand {
		if (!in_array('*', $select) && !in_array('command', $select)) {
			$select[] = 'command';
		}
		$item = $this->connection->builder()
			->select(...$select)
			->from($this->tableName)
			->where('status = :status')
			->setParameter('status', IQueueCommand::STATUS_NEW)
			->orderBy('date_create', 'ASC')
			->executeQuery()
			->fetchAssociative();

		return CommandFactory::createFromArray($item ?: []);
	}

	public function save(IQueueCommand $item): void {
		$itemArr = $item->toArray();
		$query = $this->connection->builder();
		if ($item->getId()) {
			$query->update($this->tableName);
		} else {
			$query->insert($this->tableName);
		}
		foreach ($itemArr as $field => $value) {
			if (isset($value)) {
				if ($item->getId()) {
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
				if ($field == 'params') {
					$value = json_encode($value);
				}

				if ($value instanceof \DateTime) {
					$value = $value->format('Y-m-d H:i:s');
				}
				$query->setParameter($field, $value);
			}
		}

		$query->executeQuery();
		if (!$item->getId() && ($lastId = $this->connection->getOriginalConnection()->lastInsertId())) {
			$item->setId($lastId);
		}
	}
}