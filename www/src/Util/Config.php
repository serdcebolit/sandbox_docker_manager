<?php

namespace Local\DockerSandboxManager\Util;

class Config {
	const MAIN_CONFIG_PATH = '/config/main.php';

	public static function getMainConfig(): array {
		return require $_SERVER['DOCUMENT_ROOT'].static::MAIN_CONFIG_PATH;
	}
}