<?php
use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\Entity\QueueCommand\CommandFactory;
use Intervolga\DockerSandboxManager\Entity\Sandbox\DockerSandbox;
use Intervolga\DockerSandboxManager\Util\Config;
use Intervolga\DockerSandboxManager\Util\DockerComposeProject;

require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';
$title = 'Создание песочницы';
require_once $_SERVER['DOCUMENT_ROOT'].'/template/header.php';

const PHP_MIN_VERSION = 80;
const PHP_MIN_VERSION_TEXT = '8.0';

function processRequest(
	?string $email = null,
    ?string $domain = null,
    ?string $creationMode = null,
    ?string $backupLink = null,
    ?string $repoLink = null,
    ?string $requestSubmit = null,
    ?string $phpVersion = null,
    ?string $bxRedaction = null,
): void {
	if ($requestSubmit) {
		if (!mb_strlen($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		    throw new Exception('Неправильно заполнено поле с email\'ом');
		}

		$email = mb_strtolower(trim($email));

		!mb_strlen($domain)
            && throw new Exception('Неправильно заполнено поле с названием сайта');

		$config = Config::getMainConfig();
		$domain = mb_strtolower(trim($domain));
		$domain = str_replace('.'.$config['main_domain'], '', $domain);

        count(explode('.', $domain)) > 1
            && throw new Exception('Домены больше 3 уровня не поддерживаются');

        //Первый символ не должен быть цифрой из-за линуксовых ограничений на имя пользователя
        //(используются маленькие латинские буквы, цифры, знаки - и _, имя должно начинаться на букву или на _, общая длина — до 31 включительно)
		!preg_match('/^[^0-9][a-z0-9-]+$/m', $domain)
            && throw new Exception('Неправильно заполнено поле с названием сайта. Оно не должно начинаться с цифры, может состоять из строчных латинских букв, цифр и дефисов');

        !array_key_exists($phpVersion, DockerComposeProject::PHP_VERSIONS)
            && throw new Exception('Неизвестная версия php');

		$sandboxRepository = Application::getSandboxRepository();
        $commandRepository = Application::getQueueCommandRepository();
        $dbalConnection = Application::getConection()->getOriginalConnection();

		$sandbox = $sandboxRepository->findByDomain($domain.'.'.$config['main_domain']);
		$traefikApi = Application::getTraefikApi();
        //Проверяем не только наличие песочницы в БД, но и занятость домена в траефике
		($sandbox || in_array($domain.'.'.$config['main_domain'], $traefikApi->getDomains()))
            && throw new Exception('Песочница с таким адресом уже существует');

		$sandbox = DockerSandbox::fromArray([
            'domain' => $domain.'.'.$config['main_domain'],
            'owner_email' => mb_strtolower($email),
            'sleep_exec_datetime' => (new DateTime())->modify('+ '.$config['sandbox_active_days_period'].' days'),
        ]);
		$dbalConnection->beginTransaction();

		try {
			$sandboxRepository->save($sandbox);
			switch ($creationMode) {
				case 'empty':
					break;
				case 'backup_restore':
					!mb_strlen($backupLink)
                        && throw new Exception('Не указана ссылка на архив с резервной копией');
					str_ends_with($backupLink, ".enc.gz")
                        && throw new Exception('Не поддерживаются резервные копии формата .enc.gz. Создайте новую резервную копию со снятой галочкой "Шифровать данные резервной копии"');
					break;
				case 'repo_deploy':
					!mb_strlen($repoLink)
                        && throw new Exception('Не указана ссылка на репозиторий');
					break;
				case 'install_bx':
					!mb_strlen($bxRedaction)
                        && throw new Exception('Не указана редакция');
					!(intval($phpVersion) >= PHP_MIN_VERSION)
                        && throw new Exception('Версия PHP не может быть ниже ' . PHP_MIN_VERSION_TEXT);
					break;
				default:
					throw new Exception('Неизвестный тип песочницы');
			}
			$createCommand = CommandFactory::createSandboxCreationCommand(
				$sandbox,
                [
                    'mode' => $creationMode,
                    'php_version' => $phpVersion,
                    'repo_link' => $repoLink,
                    'backup_link' => $backupLink,
                    'bx_redaction' => $bxRedaction,
                ]
			);
			$commandRepository->save($createCommand);
			$dbalConnection->commit();
        } catch (Throwable $e) {
			$dbalConnection->rollBack();
            throw $e;
        }
		Header('Location: /?success=Y');
	}
}

$config = Config::getMainConfig();
$email = $_REQUEST['email'];
$domain = $_REQUEST['domain'];
$creationMode = $_REQUEST['creation_mode'];
$backupLink = $_REQUEST['backup_link'];
$repoLink = $_REQUEST['repo_link'];
$requestSubmit = $_REQUEST['submit'];
$phpVersion = $_REQUEST['php_version'];
$bxRedaction = $_REQUEST['bx_redaction'];
$errors = [];
try {
	processRequest(
		email: $email,
		domain: $domain,
		creationMode: $creationMode,
		backupLink: $backupLink,
		repoLink: $repoLink,
		requestSubmit: $requestSubmit,
		phpVersion: $phpVersion,
		bxRedaction: $bxRedaction,
    );
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}


$sandboxes = Application::getSandboxRepository()->getAllWithLastCommandStatus(excludeProcessing: true);
?>
<div class="row justify-content-center">
	<form action="/" class="form-horizontal col-8" method="post">
		<?php if (count($errors)):?>
			<div class="alert alert-danger" role="alert">
				<?=implode('<br>', $errors)?>
			</div>
		<?php elseif ($_REQUEST['success'] == 'Y'):?>
			<div class="alert alert-success" role="alert">
				Песочница будет создана в ближайшие 5-10 минут. Как только она будет готова - вы получите письмо на указанную почту.
				<br>
				<strong>Не забудьте разместить правильный файл robots.txt на новой песочнице (можно взять <a href="/robots.txt">отсюда</a>)</strong>
			</div>
		<?php endif;?>
		<div class="form-group row">
			<label class="col-4">Ваш email</label>
			<div class="col-8">
				<input type="text" class="form-control" placeholder="напр. hero@example.ru" name="email" value="<?=htmlspecialchars($email)?>" autocomplete="off" required>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-4">Название сайта</label>
			<div class="col-8 input-group">
				<input type="text" class="form-control" placeholder="напр. example-15" name="domain" value="<?=htmlspecialchars($domain)?>" autocomplete="off" required>
				<div class="input-group-append">
					<div class="input-group-text">.<?=$config['main_domain']?></div>
				</div>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-4">Версия PHP</label>
			<div class="col-8">
				<select class="form-control" name="php_version" required>
					<?php foreach (DockerComposeProject::PHP_VERSIONS as $code => $name):?>
						<option value="<?=$code?>"<?=($phpVersion == $code ? ' selected': '')?>><?=$name?></option>
					<?php endforeach;?>
				</select>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-4">Дополнительные действия</label>
			<div class="col-8">
				<select class="form-control" name="creation_mode" required>
					<?php foreach (DockerComposeProject::CREATION_MODES as $code => $name):?>
						<option value="<?=$code?>"<?=($creationMode == $code ? ' selected': '')?>><?=$name?></option>
					<?php endforeach;?>
				</select>
			</div>
		</div>
		<div class="d-none" data-creation-mode-block="backup_restore">
			<div class="form-group row">
				<label class="col-4">Ссылка на резервную копию</label>
				<div class="col-8">
					<input type="text" class="form-control" placeholder="http://site.ru/bitrix/backup/site.ru_20211208_full.tar.gz" name="backup_link" value="<?=htmlspecialchars($backupLink)?>" autocomplete="off">
					<small class="form-text text-muted">Скопируйте из админки сайта, нажав на кнопку "Получить ссылку для переноса" в списке резервных копий</small>
					<small class="form-text text-muted"><strong>Важно!</strong> При создании резервной копии в админке нужно снять галочку "Шифровать данные резервной копии"</small>
				</div>
			</div>
		</div>
		<div class="d-none" data-creation-mode-block="repo_deploy">
			<div class="form-group row">
				<label class="col-4">Ссылка для клонирования репозитория</label>
				<div class="col-8">
					<input type="text" class="form-control" placeholder="git@github.com:project/site.git" name="repo_link" value="<?=htmlspecialchars($repoLink)?>" autocomplete="off">
					<small class="form-text text-muted">Поддерживаются только ссылки для клонирования по ssh!</small>
				</div>
			</div>
		</div>
		<div class="d-none" data-creation-mode-block="clone_from_exists">
			<div class="form-group row">
				<label class="col-4">Песочница, которую нужно склонировать</label>
				<div class="col-8">
					<select class="form-control" name="sandbox_for_clone">
						<?php foreach ($sandboxes as $sandbox):?>
							<option value="<?=$sandbox->getId()?>"><?=$sandbox->getDomain()?> (<?=$sandbox->getOwner()?>)</option>
						<?php endforeach;?>
					</select>
				</div>
			</div>
		</div>
        <div class="d-none" data-creation-mode-block="install_bx">
            <div class="form-group row">
                <label class="col-4">Редакция</label>
                <div class="col-8">
                    <select class="form-control" name="bx_redaction">
						<?php foreach (DockerComposeProject::BX_INSTALL_REDACTIONS as $code=>$redaction):?>
                            <option value="<?=$code?>"<?= $code == DockerComposeProject::DEFAULT_BX_INSTALL_REDACTION ? ' selected' : ''?>><?=$redaction?></option>
						<?php endforeach;?>
                    </select>
                </div>
            </div>
        </div>
		<div class="form-group text-center">
			<input type="submit" class="btn btn-red" name="submit" value="Создать песочницу">
		</div>
		<div class="alert alert-info">
			Документация по созданию и работе с песочницами доступна по ссылке <a href="https://github.com/serdcebolit/sandbox_docker_env/blob/master/readme.md" target="_blank">https://github.com/serdcebolit/sandbox_docker_env/blob/master/readme.md</a>
			<br>
			Все доступы к песочнице приходят на почту, указанную при создании песочницы.
		</div>
	</form>
</div>
    <script>
        function setAdditionalOptionsForCreationMode(mode) {
          $('[data-creation-mode-block]').addClass('d-none');
          $('[data-creation-mode-block] input').each(function (index, item) {
            $(item).prop('required', false);
          });
          $('[data-creation-mode-block="' + mode + '"]').removeClass('d-none');
          $('[data-creation-mode-block="' + mode + '"] input').each(function (index, item) {
            $(item).prop('required', true);
          });
        }

        $(document).ready(function () {
          setAdditionalOptionsForCreationMode($('select[name="creation_mode"]').val());
        });
        $(document).on('change', 'select[name="creation_mode"]', function () {
          setAdditionalOptionsForCreationMode($(this).val());
        });
    </script>
<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/template/footer.php';