<?php

namespace Local\DockerSandboxManager\Settings\CustomSettings;

use Exception;
use Local\DockerSandboxManager\Entity\Sandbox\ISandbox;
use Local\DockerSandboxManager\Repository\SandboxRepository;
use Local\DockerSandboxManager\Settings\Settings;
use Local\DockerSandboxManager\Util\DockerComposeProject;

class SshKeysSettings extends Settings {
	protected const SETTINGS_NAME = "Настройки ssh-ключей";
	protected const SETTINGS_CODE = "ssh_keys";

	protected string $sshKeys = "";

	public function initData(
		DockerComposeProject $dockerComposeProject,
		SandboxRepository $sandboxRepository,
		ISandbox $sandbox
	): bool {
		parent::initData($dockerComposeProject, $sandboxRepository, $sandbox);

		try {
			$this->sshKeys = implode(PHP_EOL, $this->dockerContainer->getAuthorizedKeys());
		} catch (Exception $e) {
			$this->message = $e->getMessage();
			return false;
		}

		return true;
	}

	public function save(array $params): bool {
		if (isset($params['save']) && isset($params['ssh_keys'])) {
			try {
				$this->dockerContainer->saveAuthorizedKeys(explode(PHP_EOL, $params['ssh_keys']));
				$this->dockerContainer->checkAndCopyPublicKey();
				$this->message = 'Ключи успешно сохранены';
			} catch (Exception $e) {
				$this->message = $e->getMessage();
				return false;
			}
		}

		return true;
	}

	public function getFormHtml(): string {
		$domain = $this->sandbox->getDomain();
		$this->sshKeys = mb_strlen($this->sshKeys) ? $this->sshKeys : '';

		return <<<HTML
			<form method="post" name="save_file" class="w-100">
				<div class="alert alert-info">
					<h5 class="d-flex justify-content-center">Файл authorized_keys для песочницы <strong
								class="ml-1">$domain</strong></h5>
					<hr>
					<p class="d-flex justify-content-center">Файл хранит в себе публичные ключи. Добавление новых ключей
						осуществляется с новой строки.</p>
				</div>
				<textarea class="form-control mt-3" aria-multiline="true" name="ssh_keys" cols="50" rows="13"
						  aria-label="With textarea"
						  style="font-size: small; white-space: pre;">$this->sshKeys</textarea>
				<div class="d-flex justify-content-center mt-3">
					<button type="submit" class="btn btn-danger" name="save">Сохранить</button>
				</div>
			</form>
			HTML;
	}
}