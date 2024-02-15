<?php

namespace Local\DockerSandboxManager\Util;

class Ajax {
	public static function isAjaxRequest(): bool {
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
	}
}