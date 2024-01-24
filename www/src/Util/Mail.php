<?php

namespace Intervolga\DockerSandboxManager\Util;

use Exception;
use Intervolga\DockerSandboxManager\Application;
use PHPMailer\PHPMailer\PHPMailer;

class Mail {
	private string $_templatesDir = '';
	private ?PHPMailer $_mailer;
	private ?string $_templateFile;

	public function __construct(
		private string $_template,
		private array $_params = [],
	) {
		$this->_templatesDir = $_SERVER['DOCUMENT_ROOT'].'/template/mail';
		$this->_templateFile = $this->_templatesDir.'/'.$this->_template.'.php';

		!file_exists($this->_templateFile)
			&& throw new Exception('Файл шаблона не существует');

		$this->_mailer = Application::getMailer();

		if (array_key_exists('bcc', $this->_params) && is_array($this->_params['bcc']))
			foreach ($this->_params['bcc'] as $email)
				$this->_mailer->addBCC($email);
	}

	public function send(array $emails): void {
		$emails = array_filter($emails, fn ($email) => !!filter_var($email, FILTER_VALIDATE_EMAIL));
		!count($emails)
			&& throw new Exception('Не указан список получателей');

		$this->_mailer->clearAddresses();
		foreach ($emails as $email) {
			$this->_mailer->addAddress($email);
		}
		$this->_mailer->isHTML(true);

		[$subject, $body] = $this->getTemplateData();
		$this->_mailer->Subject = $subject;
		$this->_mailer->Body  = $body;

		$this->_mailer->send();
	}

	protected function getTemplateData(): array {
		extract($this->_params);
		include $this->_templateFile;

		return [$subject, $body];
	}
}