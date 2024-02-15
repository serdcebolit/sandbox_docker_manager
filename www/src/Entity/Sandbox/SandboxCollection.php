<?php

namespace Local\DockerSandboxManager\Entity\Sandbox;

use Local\DockerSandboxManager\Entity\AbstractCollection;

/**
 * @method ISandbox next()
 * @method ISandbox current()
 */
class SandboxCollection extends AbstractCollection {
	protected function getClassNameForCheck(): string {
		return ISandbox::class;
	}
}