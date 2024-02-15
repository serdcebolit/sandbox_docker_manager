<?php


if ($_SERVER['HTTP_HOST'] == getenv('SITE_HOST'))
{
	header('Location: /');
	die();
}
http_response_code(404);
// соре, костыль
$GLOBALS['IS_404'] = true;
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
$title = '404';
require_once $_SERVER['DOCUMENT_ROOT'] . '/template/header.php';
$siteHost = $_SERVER['HTTP_HOST'];
$sandboxRepository = \Local\DockerSandboxManager\Application::getSandboxRepository()->findByDomain($siteHost);
?>

    <div class="row d-flex justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="alert alert-danger d-flex justify-content-center mt-3" role="alert">
				<?php if ($sandboxRepository && $sandboxRepository->getId()): ?>
                    <span>Песочница в данный момент выключена</span>
				<?php else: ?>
                    <span>Песочница с доменом <b><?=$siteHost?></b> не найдена</span>
				<?php endif; ?>
            </div>
        </div>
    </div>


<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/template/footer.php';