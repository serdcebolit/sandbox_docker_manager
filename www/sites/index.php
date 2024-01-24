<?php

use Intervolga\DockerSandboxManager\Application;
use Intervolga\DockerSandboxManager\Entity\Sandbox\DockerSandbox;
use Intervolga\DockerSandboxManager\Util\FileSizeHelper;

require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';
$title = 'Список песочниц';
require_once $_SERVER['DOCUMENT_ROOT'].'/template/header.php';

$repository = Application::getSandboxRepository();

$headers = [
	['name' => 'Домен', 'code' => 'domain',],
	['name' => 'Владелец', 'code' => 'owner_email',],
	['name' => 'Состояние', 'code' => '',],
	['name' => 'Дата создания', 'code' => 'date_create',],
	['name' => 'Файлы', 'code' => 'files_volume',],
	['name' => 'БД', 'code' => 'db_volume',],
	['name' => 'Итого', 'code' => 'volume_summary'],
	['name' => 'Статус сервисов', 'code' => '',],
	['name' => 'Действия', 'code' => '',],
];
$currentSort = $_REQUEST['sort'] ?: 'date_create';
$currentSortDirection = $_REQUEST['direction'] ?: 'desc';

$sandboxes = $repository->getAllWithLastCommandStatus(
    sort: $currentSort ? [$currentSort => $currentSortDirection] : [],
);
$sandboxStatusLang = [
    DockerSandbox::STATUS_WORKING => 'Работает',
    DockerSandbox::STATUS_STOPPED => 'Остановлена',
    DockerSandbox::STATUS_PROCESSING => 'Выполняется команда',
    DockerSandbox::STATUS_UNKNOWN => 'Неизвестно',
    DockerSandbox::STATUS_FAILED => 'Ошибка при выполнении команды',
];
$servicesCollector = Application::getSandboxServicesCollector();

$stats = [
    'all' => 0,
    'working' => 0,
    'stopped' => 0,
];
foreach ($sandboxes as $sandbox) {
	$status = $sandbox->getStatus();
	$stats['all']++;
    if ($status == DockerSandbox::STATUS_WORKING) {
        $stats['working']++;
    } elseif ($status == DockerSandbox::STATUS_STOPPED) {
        $stats['stopped']++;
    }
}
?>
</div>
<div class="container my-3">
    <div class="row text-center">
        <div class="col-12 col-md-4"><strong>Всего: <?=$stats['all']?></strong></div>
        <div class="col-12 col-md-4 text-success"><strong>Работают: <?=$stats['working']?></strong></div>
        <div class="col-12 col-md-4 text-danger"><strong>Остановлены: <?=$stats['stopped']?></strong></div>
    </div>
    <form class="d-flex">
        <input class="form-control me-2" type="search" placeholder="Найти песочницу" id="sandbox_search" aria-label="Search" autofocus>
    </form>
</div>
<div class="container-fuild">
	<table class="table table-hover">
		<thead>
        <?php foreach ($headers as $header):?>
            <?php if ($header['code']):
                $dir = $currentSort == $header['code'] && $currentSortDirection == 'desc' ? 'asc' : 'desc';
                ?>
                <th><a href="?sort=<?=$header['code']?>&direction=<?=$dir?>"><?=$header['name']?></a></th>
		    <?php else:?>
                <th><?=$header['name']?></th>
		    <?php endif;?>
        <?php endforeach;?>
		</thead>
		<?php
        /** @var DockerSandbox $sandbox */
		foreach ($sandboxes as $sandbox):
            try {
                $servicesStatus = $servicesCollector->getForSandbox($sandbox);
            } catch (Throwable $e) {
			echo '<pre>' . __FILE__ . ':' . __LINE__ . ':<br>' . print_r($e, true) . '</pre>';
				$servicesStatus = [];
            }
            $status = $sandbox->getStatus();
            $isLocked = !Application::getSandboxLock($sandbox->getDomain())->check();
            $canDoActions = !in_array($status, [DockerSandbox::STATUS_UNKNOWN, DockerSandbox::STATUS_PROCESSING, DockerSandbox::STATUS_FAILED])
				&& $sandbox->isCreated()
                && $isLocked;
            $sizeFiles = floatval($sandbox->getFilesVolume());
            $sizeDb = floatval($sandbox->getDbVolume());
            $sizeAll = $sizeFiles + $sizeDb;
        ?>
			<tr>
				<td><a href="https://<?=$sandbox->getDomain()?>" target="_blank"><?=$sandbox->getDomain()?></a></td>
				<td><?=$sandbox->getOwner()?></td>
				<td><?=$sandboxStatusLang[$status] ?? $status?></td>
				<td><?=$sandbox->getDateCreate()?->format('d.m.Y')?></td>
                <td><?= FileSizeHelper::formatBytesToHuman($sizeFiles, needRu: true)?></td>
                <td><?= FileSizeHelper::formatBytesToHuman($sizeDb, needRu: true)?></td>
                <td><?= FileSizeHelper::formatBytesToHuman($sizeAll, needRu: true)?></td>
                <td data-sandbox-id="<?= $sandbox->getId() ?>"><?= implode('<br>',
						array_map(static function ($service) {
							return '<a href="#" data-toggle="modal" data-target="#showLogsModal" data-action="show_logs" title="Посмотреть логи сервиса '
                                . $service->name . '">' . $service->name . '</a>' . ' <b>' . $service->status . '</b>';
						}, $servicesStatus ?? [])) ?></td>
                <td data-sandbox-id="<?=$sandbox->getId()?>">
					<?php if ($canDoActions):?>
						<?php if ($status == DockerSandbox::STATUS_STOPPED):?>
                            <button class="btn btn-block btn-success" data-action="start_sandbox">Запустить</button>
						<?php else:?>
                            <button class="btn btn-block btn-danger" data-action="stop_sandbox">Остановить</button>
                            <button class="btn btn-block btn-info" data-action="restart_sandbox">Перезапустить</button>
                            <a href="/sandbox_settings/?domain=<?=$sandbox->getDomain()?>" class="d-flex justify-content-center pt-2">Настройки песочницы</a>
						<?php endif;?>
					<?php else:?>
                        <small>Действия с песочницей временно заблокированы. <br>Попробуйте через 1-2 минуты</small>
					<?php endif;?>
                </td>
			</tr>
		<?php endforeach;?>
	</table>
    <script>
      $(document).on('click', 'button[data-action]', function () {
        const button = $(this);
        const action = button.data('action');
        const sandboxId = button.closest('td[data-sandbox-id]').data('sandboxId');
        const buttonText = button.text();
        button.text('Загрузка...');
        $('button[data-action]').prop('disabled', true);
        $.ajax({
          url: '/ajax/',
          data: {
            action: action,
            sandboxId: sandboxId,
          },
          type: 'POST',
          dataType: 'json',
        }).done(function (data) {
          $('button[data-action]').prop('disabled', false);
          button.text(buttonText);
          data.status !== 'success'
            ? alert(data.message || 'Произошла ошибка')
            : location.reload();
        });
      });

      $(document).on('click', 'a[data-action]', function () {
        const a = $(this);
        const action = a.data('action');
        const sandboxId = a.closest('td[data-sandbox-id]').data('sandboxId');
        const service = a.text();
        const period = '24h';
        $.ajax({
          url: '/ajax/',
          data: {
            action: action,
            sandboxId: sandboxId,
            service: service,
            period: period,
            type: 'success',
          },
          type: 'POST',
          dataType: 'json',
        }).done(function (data) {
            let message = '';
            message = data.status !== 'success'
                ? 'Произошла ошибка при получении лог-записей.'
                : data.logs;
            $('#logModalContainer').text(message);
            $('#logModalLabel').text('Логи для сервиса ' + service);
            const modalData = $('#logModalData').data();
            modalData.action= action;
            modalData.sandboxId= sandboxId;
            modalData.service= service;
            modalData.period= period;
        });
      });

      $(document).on('change', '[data-ajax]', function () {
        const modalData = $('#logModalData').data();
        modalData.period = $('[name="logs-period"]:checked').val();
        modalData.type = $('[name="logs-type"]:checked').val();
        $.ajax({
          url: '/ajax/',
          data: {
            action: modalData.action,
            sandboxId: modalData.sandboxId,
            service: modalData.service,
            period: modalData.period,
            type: modalData.type,
          },
          type: 'POST',
          dataType: 'json',
        }).done(function (data) {
          let message = '';
          message = data.status !== 'success'
            ? 'Произошла ошибка при получении лог-записей.'
            : data.logs;
          $('#logModalContainer').text(message);
          $('#logModalContainer').scrollTop($('#logModalContainer')[0].scrollHeight);
        });
      })

      $(document).on('shown.bs.modal', '#showLogsModal', function () {
        setTimeout(() => {
          $('#logModalContainer').scrollTop($('#logModalContainer')[0].scrollHeight);
        }, 500)
      })

      $(document).ready(function () {
          $('#sandbox_search').keyup(function () {
              const search = $(this).val();
              window.history.replaceState(null, null, `?s=${search}`);
              const rows = $('table tr');
              rows.each(function (index, row) {
                  if (index === 0) return;
                  const rowText = $.map($(row).find('td'), (item) => $(item).text()).join('').toLowerCase();
                  const showRow = rowText.indexOf(search.toLowerCase()) !== -1;
                  $(row).toggle(showRow);
              });
          });

          const searchParams = new URLSearchParams(window.location.search);
          const searchVal = searchParams.get('s');
          if (searchVal) {
                $('#sandbox_search').val(searchVal);
                $('#sandbox_search').trigger('keyup');
          }
      });
    </script>

<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/template/modals/show_logs.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/template/footer.php';
