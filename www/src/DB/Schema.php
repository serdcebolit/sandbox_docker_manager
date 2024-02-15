<?php

namespace Local\DockerSandboxManager\DB;

class Schema {
	public static function migrate(): void {
		Connection::getInstance()->getSchemaManager()->migrateSchema(static::get());
	}

	public static function get(): \Doctrine\DBAL\Schema\Schema {
		$schema = new \Doctrine\DBAL\Schema\Schema();
		$sitesTable = $schema->createTable('sites');
		$sitesTable->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
		$sitesTable->setPrimaryKey(['id']);
		$sitesTable->addColumn('domain', 'string');
		$sitesTable->addColumn('owner_email', 'string');
		$sitesTable->addColumn('sleep_exec_datetime', 'datetime');
		$sitesTable->addColumn('date_create', 'datetime');
		$sitesTable->addColumn('is_permanent_working', 'boolean');
		$sitesTable->addColumn('files_volume', 'bigint', ['unsigned' => true]);
		$sitesTable->addColumn('db_volume', 'bigint', ['unsigned' => true]);

		$siteQueue = $schema->createTable('site_queue');
		$siteQueue->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
		$siteQueue->setPrimaryKey(['id']);
		$siteQueue->addColumn('site_id', 'integer');
		$siteQueue->addColumn('command', 'string', ['length' => 50]);
		$siteQueue->addColumn('date_exec', 'datetime', ['notnull' => false]);
		$siteQueue->addColumn('date_create', 'datetime');
		$siteQueue->addColumn('status', 'string');
		$siteQueue->addColumn('params', 'json');


		return $schema;
	}
}