<?php
/**
 * @var $sshHost string
 * @var $sshPort int
 * @var $sshLogin string
 * @var $sshPassword string
 * @var $domain string
 * @var $ownerEmail string
 * @var $mailDomain string
 * @var $errorMessage string
 * @var $lastLogs string
 */

$subject = 'Сообщение от '.$sshHost.': Ошибка при создании сайта '.$domain;

$body = <<<E
Адрес сайта: <a href="https://$domain">$domain</a><br><br>
Создатель: <a href="mailto:$ownerEmail">$ownerEmail</a><br><br>
<strong>Ошибка:</strong><br>
<pre>$errorMessage</pre><br>
<strong>Логи сервиса deploy_manager:</strong><br>
<pre>$lastLogs</pre><br><br>
<strong>Перехват писем:</strong><br>
Страница перехвата: <a href="https://$mailDomain">$mailDomain</a><br>
Логин: intervolga<br>
Пароль: intervolga34
<br><br>
<strong>Доступы для подключения по SSH:</strong><br>
Хост: $sshHost<br>
Порт: $sshPort<br>
Логин: $sshLogin<br>
Пароль: $sshPassword<br>
<br>
<strong>Доступы для подключения к БД:</strong><br>
Хост: mysql<br>
БД: bitrix<br>
Логин: bitrix<br>
Пароль: password
<br><br>
<b>Перед началом использования песочницы обязательно <a href="https://gitlab.intervolga.ru/common/ivdev_docker_env/-/blob/master/readme.md">ознакомьтесь с документацией</a></b>
<hr>
С любовью ваш $sshHost
<br>
E;
