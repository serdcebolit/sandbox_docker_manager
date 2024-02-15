<?php

namespace Local\DockerSandboxManager\Settings\CustomSettings;

use Local\DockerSandboxManager\Application;
use Local\DockerSandboxManager\Entity\Sandbox\DockerSandbox;
use Local\DockerSandboxManager\Entity\Sandbox\ISandbox;
use Local\DockerSandboxManager\Repository\SandboxRepository;
use Local\DockerSandboxManager\Settings\Settings;
use Local\DockerSandboxManager\Util\DockerComposeProject;
use Throwable;

class PhpVersionSettings extends Settings {
	protected const SETTINGS_NAME = "Настройки версии PHP песочницы";
	protected const SETTINGS_CODE = "php_version";

	protected array $envConfig;
	protected ?string $phpVersion;
	protected ?string $isXdebug;

	public function initData(
		DockerComposeProject $dockerComposeProject,
		SandboxRepository $sandboxRepository,
		ISandbox $sandbox
	): bool {
		parent::initData($dockerComposeProject, $sandboxRepository, $sandbox);
		try {
			$this->envConfig = $this->dockerContainer->getEnvConfig($this->dockerContainer->getPath() . '/.env');
		} catch (Throwable $e) {
			$this->message = $e->getMessage();
			return false;
		}

		[$this->phpVersion, $this->isXdebug] = explode("-", $this->envConfig['PHP_VERSION']);
		return true;
	}

	public function save(array $params): bool {
		if (isset($params['save'])) {
			$sandboxLock = Application::getSandboxLock($this->sandbox->getDomain());
			$status = $this->sandbox->getStatus();

			$canDoActions = !in_array($status, [DockerSandbox::STATUS_UNKNOWN, DockerSandbox::STATUS_PROCESSING])
				&& !$sandboxLock->check();
			if ($canDoActions) {
				$this->envConfig['PHP_VERSION'] = $params['php_version'] . ($params['is_xdebug'] ? '-xdebug' : '');
				$this->dockerContainer->updateEnvConfig($this->dockerContainer->getPath() . '/.env', $this->envConfig);

				try {
					//Лочим песочницу на минуту, т.к. команда запуска выполняется быстро, но нужно время на поднятие контейнеров
					$sandboxLock->set();
					$this->sandbox->wakeUp();
					$this->message = "Песочница перезапущена. Действия с песочницей временно заблокированы. Попробуйте через 1-2 минуты.";
				} catch (Throwable $e) {
					$sandboxLock->release();
					$this->message = $e->getMessage();
					return false;
				}
			} else {
				$this->message = "Действия с песочницей временно заблокированы. Попробуйте через 1-2 минуты.";
				return false;
			}
		}
		return true;
	}

	public function getFormHtml(): string {
		$optionsHtml = "";
		foreach (DockerComposeProject::PHP_VERSIONS as $code => $name) {
			$optionsHtml .= "<option value=\"$code\"" . ($this->phpVersion == $code ? ' selected' : '') . ">$name</option>";
		}

		$isXdebugChecked = ($this->isXdebug ? ' checked' : '');

		return <<<HTML
			<form method="post" name="change_php_version" class="w-100">
				<div class="form-group">
					<label>Версия PHP</label>
					<select class="form-control" name="php_version" required>
						$optionsHtml
					</select>
				</div>
				<div class="form-group">
					<div class="form-check">
						<input type="checkbox" name="is_xdebug" id="is_xdebug" class="form-check-input"$isXdebugChecked/>
						<label class="form-check-label" for="is_xdebug">Включить x-Debug</label>
					</div>
				</div>
				<div class="d-flex justify-content-center mt-3">
					<button type="submit" class="btn btn-danger" name="save">Сохранить</button>
				</div>
			</form>
			HTML;
	}
}