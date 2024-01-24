<?php

namespace Intervolga\DockerSandboxManager\Util;

use Throwable;

class FileSizeHelper {
	const SYMBOLS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
	const SYMBOLS_RU = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ', 'ПБ', 'ЭБ', 'ЗБ', 'ЙБ'];

	public static function formatBytesToHuman(float $bytes, ?string $symbol = null, bool $needRu = false): string {
		try {
			$exp = floor(log($bytes)/log(1024));
			if ($symbol)
				$exp = array_search($symbol, static::SYMBOLS);

			return sprintf('%.1f'.($needRu ? static::SYMBOLS_RU[$exp] : static::SYMBOLS[$exp]), ($bytes/pow(1024, $exp)));
		} catch (Throwable) {
			return "0";
		}
	}

	public static function formatHumanToBytes(string $human): float {
		$val = floatval($human);
		$strPart = str_replace($val, '', $human);
		$exp = array_search($strPart, static::SYMBOLS);
		return $val * pow(1024, $exp);
	}
}