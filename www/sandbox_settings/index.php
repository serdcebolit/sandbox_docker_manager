<?php

use Intervolga\DockerSandboxManager\Sandbox\Authorization;
use Intervolga\DockerSandboxManager\Settings\SandboxSettingsManager;
use Intervolga\DockerSandboxManager\Util\DockerComposeProject;
require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';
$title = 'Настройки песочницы ' . $_REQUEST['domain'];
if (!isset($_GET['domain']) || !mb_strlen($_GET['domain'])) {
	header('Location: /');
    die();
}
$isSuccessAuth = Authorization::getInstance()->isHasPermission($_REQUEST['ssh_pass'] ?? '', $_REQUEST['domain']);
if ($isSuccessAuth) {
	Authorization::getInstance()->addUserSandbox($_REQUEST['domain']);
}
$dockerContainer = new DockerComposeProject($_GET['domain']);
$responseData = ['error' => 'N', 'domain' => $_REQUEST['domain']];

$manager = SandboxSettingsManager::getInstance();
$manager->init($_GET['domain']);
$manager->doFormsLogic($_REQUEST);

if (isset($_POST['ssh_pass']) && $isSuccessAuth) {
	header('Location: ' . $_SERVER['REQUEST_URI']);
	die();
}
if (isset($_REQUEST['error']))
    $responseData['error'] = $_REQUEST['error'];
if (isset($_REQUEST['msg']))
    $responseData['msg'] = $_REQUEST['msg'];
require_once $_SERVER['DOCUMENT_ROOT'].'/template/header.php';
?>
<div class="row d-flex justify-content-center">
<?php if (!$isSuccessAuth && $_SERVER['REQUEST_METHOD'] == 'GET' || (isset($_POST['ssh_pass']) && !$isSuccessAuth)):?>
	<form name="auth" method="post" class="w-75 d-flex justify-content-center mt-5">
		<label for="password" class="form-label mr-3">SSH пароль от песочницы</label>
		<input type="password" <?=(isset($_POST['ssh_pass']))? 'placeholder="Неверный пароль"' : ''?> name="ssh_pass" class="form-control mr-3 w-50 <?=(isset($_POST['ssh_pass']))? 'is-invalid' : ''?>" id="password" required>
		<button type="submit" class="btn btn-danger">Войти</button>
	</form>
    <div class="alert alert-info d-flex justify-content-center mt-3 w-75" role="alert">
        Пароль можно найти в письме, которое пришло на почту, указанную при создании песочницы.
    </div>
<?php else:?>
	<?php $manager->showForms();?>
<?php endif;?>
</div>
<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/template/footer.php';