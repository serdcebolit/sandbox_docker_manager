<?php
$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__, 2);

require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';

$command = \Local\DockerSandboxManager\Application::getQueueCommandRepository()->getFirstForExec();
$command?->exec();

echo "Команда выполнена";