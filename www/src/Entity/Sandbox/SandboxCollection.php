<?php

namespace Intervolga\DockerSandboxManager\Entity\Sandbox;

use Intervolga\DockerSandboxManager\Entity\AbstractCollection;

/**
 * @method ISandbox next()
 * @method ISandbox current()
 */
class SandboxCollection extends AbstractCollection {
	protected function getClassNameForCheck(): string {
		return ISandbox::class;
	}
}