<?php

namespace Local\DockerSandboxManager\Util;

class Password {
	public static function generate(int $length = 8): string {
		$password = '';
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$limit = strlen($characters) - 1;
		for ($i = 0; $i < $length; $i++) {
			$password .= $characters[rand(0, $limit)];
		}
		return $password;
	}
}