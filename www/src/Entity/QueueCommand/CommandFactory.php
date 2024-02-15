<?php

namespace Local\DockerSandboxManager\Entity\QueueCommand;

use Local\DockerSandboxManager\Application;
use Local\DockerSandboxManager\Entity\Sandbox\ISandbox;

class CommandFactory {
	public static function createFromArray(array $item): ?IQueueCommand {
		/** @var IQueueCommand::class $commandFullClass */
		$commandFullClass = __NAMESPACE__.'\\'.$item['command'];

		return class_exists($commandFullClass)
			? new $commandFullClass(
				id: $item['id'],
				sandbox: $item['site_id'] ? Application::getSandboxRepository()->findById($item['site_id']) : null,
				dateCreate: $item['date_create'] ? new \DateTime($item['date_create']) : null,
				dateExec: $item['date_exec'] ? new \DateTime($item['date_exec']) : null,
				status: $item['status'],
				params: is_array($item['params']) ? $item['params'] : (json_decode($item['params'], true) ?? []),
			)
			: null;
	}

	public static function createSandboxCreationCommand(
		ISandbox $sandbox,
		array $params = [],
	): CreateSandboxCommand {
		return new CreateSandboxCommand(
			sandbox: $sandbox,
			dateCreate: new \DateTime(),
			status: IQueueCommand::STATUS_NEW,
			params: $params,
		);
	}
}