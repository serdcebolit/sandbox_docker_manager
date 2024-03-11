<?php

return [
	'db' => [
		'dbname' => getenv('DB_NAME') ?: 'bitrix',
		'user' => getenv('DB_LOGIN') ?: 'bitrix',
		'password' => getenv('DB_PASSWORD') ?: 'password',
		'host' => getenv('DB_HOST') ?: 'mysql',
		'driver' => 'pdo_mysql'
	],
	'containers_env' => getenv('CONTAINERS_ENV') ?: 'prod',
	'sandbox_active_days_period' => 7,
	'ip_addresses_for_access_logs' => [''],
	'main_domain' => getenv('MAIN_DOMAIN') ?: 'serdcebolit.ru',
	'logs_dir' => $_SERVER['DOCUMENT_ROOT'].'/log',
	'mail' => [
		'host' => getenv('SMTP_HOST') ?: 'mail',
		'login' => getenv('SMTP_LOGIN') ?: 'serdcebolit@serdcebolit.ru',
		'password' => getenv('SMTP_PASSWORD') ?: 'password',
		'port' => getenv('SMTP_PORT') ?: 25,
		'from_name' => getenv('SMTP_FROM_NAME') ?: 'serdcebolit@serdcebolit.ru',
		'smtp_auth' => getenv('SMTP_AUTH') == 'true',
		'bcc_emails' => ['d97510795@gmail.com']
	],
	'traefik' => [
		'baseUrl' => getenv('TRAEFIK_BASEURL') ?: 'https://traefik.serdcebolit.ru',
		'login' => getenv('TRAEFIK_LOGIN'),
		'password' => getenv('TREFIK_PASSWORD'),
	],
	'bx_installer' => [
		'login' => getenv('BX_ADMIN_LOGIN') ?: 'admin',
		'password' => getenv('BX_ADMIN_PASSWORD'),
	],
	'sandboxes_root_path' => getenv('SANDBOXES_ROOT_PATH') ?: '/home/bitrix/ext_www',
];