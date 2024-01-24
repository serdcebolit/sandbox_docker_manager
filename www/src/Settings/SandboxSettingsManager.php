<?php

namespace Intervolga\DockerSandboxManager\Settings;

use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\Entity\Sandbox\ISandbox;
use Intervolga\DockerSandboxManager\Repository\SandboxRepository;
use Intervolga\DockerSandboxManager\Util\DockerComposeProject;

class SandboxSettingsManager {
	protected static ?self $instance = null;
	protected string $domain = "";
	protected DockerComposeProject $dockerContainer;
	protected SandboxRepository $sandboxRepository;
	protected ISandbox $sandbox;
	protected ?bool $successful;
	protected ?string $message;

	/** @var Settings[] */
	protected array $customSettings = [];

	private function __construct() {}

	public static function getInstance(): self {
		if (!static::$instance) {
			static::$instance = new self();
		}

		return static::$instance;
	}

	public function init(string $domain): void {
		$this->domain = $domain;
		$this->dockerContainer = new DockerComposeProject($domain);
		$this->sandboxRepository = Application::getSandboxRepository();
		$this->sandbox = $this->sandboxRepository->findByDomain($domain);
		$this->successful = $_REQUEST['error'] == 'N';
		$this->message = $_REQUEST['message'];

		$this->initCustomSettings();
	}

	public function doFormsLogic(array $request): void {
		$messageForRedirect = "";
		$successfulForRedirect = true;
		$needRedirect = false;
		foreach ($this->customSettings as $settings) {
			$this->successful &= $settings->InitData(
				$this->dockerContainer,
				$this->sandboxRepository,
				$this->sandbox,
			);
			$this->message = trim($this->message . PHP_EOL . $settings->GetMessage());

			if (isset($request[$settings->GetCode()])) {
				$needRedirect = true;
				$successfulForRedirect &= $settings->Save($request[$settings->GetCode()]);
				$messageForRedirect = trim($messageForRedirect . PHP_EOL . $settings->GetMessage());
			}
		}

		if ($needRedirect) {
			$responseData['domain'] = $this->domain;
			if ($messageForRedirect) {
				$responseData['error'] = $successfulForRedirect ? 'N' : 'Y';
				$responseData['message'] = $messageForRedirect;
			}
			header('Location: ' . $_SERVER['SCRIPT_URL'].'?'.http_build_query($responseData));
			die();
		}
	}

	public function showForms(): void {
		$resultHtml = $this->message ? $this->getMessageHtml($this->message, $this->successful) : '';

		foreach ($this->customSettings as $settings) {
			$formHtml = $settings->GetFormHtml();
			$formHtml = $this->prepareForm($formHtml, $settings->GetName());
			$formHtml = $this->makeFormIndependent($formHtml, $settings->GetCode());

			$resultHtml .= $formHtml;
		}
		echo $resultHtml;
	}

	protected function initCustomSettings(string $dir = "CustomSettings"): void {
		$dir = __DIR__ . DIRECTORY_SEPARATOR . $dir;
		$before = get_declared_classes();

		foreach (scandir($dir) as $item) {
			if (in_array($item, array('.', '..'))) {
				continue;
			}

			include_once($dir . DIRECTORY_SEPARATOR . $item);
		}

		foreach (array_diff(get_declared_classes(), $before) as $class) {
			if (is_subclass_of($class, Settings::class)) {
				$this->customSettings[] = new $class();
			}
		}
	}

	protected function makeFormIndependent(string $formHtml, string $formCode): string {
		//Превращает в html все    name="smth_name" в name="$formCode[smth_name]"
		//                    и    name="smth_name[any_key_or_empty]" в name="$formCode[smth_name][any_key_or_empty]"
		//для обеспечения уникальности имен в рамках страницы из нескольких форм
		return preg_replace("/name=\"([^(\[\]\")]*)(\[.*\])?\"/", "name=\"{$formCode}[$1]$2\"", $formHtml);
	}

	protected function prepareForm(string $formHtml, string $formName): string {
		return <<<HTML
			<div class="w-100 mt-5">
			    <hr>
			    <h3>$formName</h3>
			    <hr>
			    $formHtml
			</div>
			HTML;
	}

	protected function getMessageHtml(string $message, bool $success = true): string {
		$messageClass = $success ? 'alert-success' : 'alert-danger';
		$message = str_replace(PHP_EOL, '<br/>', $message);
		return  <<<HTML
			<div class="alert $messageClass d-flex justify-content-center mt-3 w-100" role="alert">
				$message
			</div>
			HTML;
	}
}