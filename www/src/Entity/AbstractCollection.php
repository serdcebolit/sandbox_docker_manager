<?php

namespace Intervolga\DockerSandboxManager\Entity;

abstract class AbstractCollection implements \Iterator, \Countable {

	public function __construct(
		private array $objects = []
	) {
		foreach ($objects as $item) {
			$this->checkItem($item);
		}
	}

	public function add($item) {
		$this->checkItem($item);
		if (!in_array($item, $this->objects))
		{
			$this->objects[] = $item;
		}
	}

	public function count() {
		return count($this->objects);
	}

	public function current() {
		return current($this->objects);
	}

	public function next() {
		return next($this->objects);
	}

	public function key() {
		return key($this->objects);
	}

	public function valid() {
		return key($this->objects) !== null;
	}

	public function rewind() {
		reset($this->objects);
	}

	public function toArray(): array {
		return $this->objects;
	}

	protected function getClassNameForCheck(): string {
		return __CLASS__;
	}

	protected function checkItem($item) {
		$class = $this->getClassNameForCheck();

		!($item instanceof $class)
			&& throw new \Exception(
				sprintf('Can not add objects of %s to this collection', gettype($item))
		);
	}
}