<?php

namespace Local\DockerSandboxManager\Entity\QueueCommand;

use DateTime;
use Exception;
use Local\DockerSandboxManager\Application;
use Local\DockerSandboxManager\Util\Config;
use Local\DockerSandboxManager\Util\DockerComposeProject;
use Local\DockerSandboxManager\Util\Mail;
use Local\DockerSandboxManager\Util\Password;
use Psr\Log\LoggerInterface;
use Throwable;

class CreateSandboxCommand extends BaseCommand {

	public function exec(): bool
	{
		$this->logger->info('command_started', [
			'sandbox_domain' => $this->sandbox->getDomain(),
			'command_id' => $this->id,
		]);
		$sandboxLock = Application::getSandboxLock($this->sandbox->getDomain());

		$config = Config::getMainConfig();
		try {
			$sshPassword = Password::generate(16);
			$bxPassword = Password::generate(16);
			$sandboxLock->setLockTime(60 * 10);
			$this->setStatus(static::STATUS_EXECUTING);
			$this->repository?->save($this);
			$this->logger->info('command_status_changed', [
				'sandbox_domain' => $this->sandbox->getDomain(),
				'command_id' => $this->id,
				'new_status' => static::STATUS_EXECUTING,
			]);

			!$this->sandbox && throw new Exception('Неизвестная песочница');

			$sandboxLock->set();
			$composeProject = new DockerComposeProject($this->sandbox->getDomain());
			$composeProject->create(
				sandbox: $this->sandbox,
				mode: $this->params['mode'],
				phpVersion: $this->params['php_version'],
				repoLink: $this->params['repo_link'],
				backupLink: $this->params['backup_link'],
				bxRedaction: $this->params['bx_redaction'],
				sshPassword: $sshPassword,
				bxPassword: $bxPassword,
			);
			$this->logger->info('sandbox_created', [
				'sandbox_domain' => $this->sandbox->getDomain(),
				'command_id' => $this->id,
			]);

			$composeProject->start();
			$this->logger->info('sandbox_started', [
				'sandbox_domain' => $this->sandbox->getDomain(),
				'command_id' => $this->id,
			]);

			$composeProject->updateEnvConfig($composeProject->getPath().'/.env', ['NEED_EXEC_MANAGER' => 0]);

			$sleepSeconds = 30;
			$hasntServicesCnt = 0;
			//Сколько раз может проходить проверка что сервисы недоступны
			$maxAttemptsWithoutServices = 5;
			$timeBeforeCheck = new DateTime();
			$needCancelCheck = false;
			while (!$needCancelCheck) {
				$services = $composeProject->getActiveServices();
				if (!count($services)) {
					if ($hasntServicesCnt >= $maxAttemptsWithoutServices)
					{
						throw new \RuntimeException('Ни один сервис не запустился за первые '.($hasntServicesCnt * $sleepSeconds).' секунд после старта песочницы');
					}
					$this->logger->info('sandbox_hasnt_services_after_start', [
						'sandbox_domain' => $this->sandbox->getDomain(),
						'command_id' => $this->id,
						'services' => json_encode($services),
					]);
					$hasntServicesCnt++;
					continue;
				}

				$services = array_combine(
					array_column($services, 'Service'),
					$services
				);

				//Если deploy_manager и bx_installer уже завершили работу и есть хоть один запущенный сервис, то выходим
				if (
					(
						((!$services['deploy_manager']['State'] || $services['deploy_manager']['State'] == 'exited')
						|| (!$services['bx_installer']['State'] || $services['bx_installer']['State'] == 'exited'))
						&& count(array_filter($services, fn ($service) => $service['State'] == 'running'))
					)
					|| $timeBeforeCheck->getTimestamp() < (new DateTime())->modify('-30 minutes')->getTimestamp()
				) {
					$needCancelCheck = true;
					$this->logger->info('sandbox_waiting_for_services_end', [
						'sandbox_domain' => $this->sandbox->getDomain(),
						'command_id' => $this->id,
						'services' => json_encode($services),
						'time_in_minutes_passed' => ((new DateTime())->getTimestamp() - $timeBeforeCheck->getTimestamp()) / 60,
					]);
				}
				sleep($sleepSeconds);
			}

			$mail = new Mail(
				'site_created',
				[
					'sshHost' => $config['main_domain'],
					'sshPort' => 2222,
					'sshLogin' => str_replace('.'.$config['main_domain'], '', $this->sandbox->getDomain()),
					'sshPassword' => $sshPassword,
					'mode' => $this->params['mode'],
					'bxLogin' => $config['bx_installer']['login'],
					'bxPassword' => $bxPassword,
					'domain' => $this->sandbox->getDomain(),
					'mailDomain' => 'mail.'.$this->sandbox->getDomain(),
					'ownerEmail' => $this->sandbox->getOwner(),
					'bcc' => $config['mail']['bcc_emails'],
				]
			);
			$mail->send([$this->sandbox->getOwner()]);

			$this->setStatus(static::STATUS_DONE);
			$this->repository?->save($this);
			$sandboxLock->release();
			return true;
		} catch (Throwable $e) {
			$this->logger->error(
				'exec_error',
				[
					'sandbox_domain' => $this->sandbox->getDomain(),
					'command_id' => $this->id,
					'message' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'trace' => $e->getTraceAsString(),
				]
			);

			$composeProject?->updateEnvConfig($composeProject->getPath().'/.env', ['NEED_EXEC_MANAGER' => 0]);

			try {
				$deployManagerErrorLogs = $composeProject?->getLogs(
					'deploy_manager',
					'5m',
					'error') ?? [];
			} catch (Throwable $e) {
				$deployManagerErrorLogs = [$e->getMessage()];
			}
			$mail = new Mail(
				'site_creation_failed',
				[
					'sshHost' => $config['main_domain'],
					'sshPort' => 2222,
					'sshLogin' => str_replace('.'.$config['main_domain'], '', $this->sandbox->getDomain()),
					'sshPassword' => $sshPassword,
					'domain' => $this->sandbox->getDomain(),
					'mailDomain' => 'mail.'.$this->sandbox->getDomain(),
					'ownerEmail' => $this->sandbox->getOwner(),
					'errorMessage' => $e->getMessage(),
					'lastLogs' => implode(PHP_EOL, array_slice($deployManagerErrorLogs, 0, 20)),
					'bcc' => $config['mail']['bcc_emails'],
				]
			);
			$mail->send([$this->sandbox->getOwner()]);

			$sandboxLock->release();
			$this->setStatus(static::STATUS_FAILED);
			$this->repository?->save($this);
			return false;
		}
	}
}