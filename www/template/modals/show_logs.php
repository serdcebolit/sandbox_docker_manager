<?php
$logPeriods = [
	'24h' => '1 день',
	'72h' => '3 дня',
	'168h' => '7 дней',
];
$logTypes = [
	'access' => 'Access',
	'error' => 'Error',
];
?>
<script>
    window.logPeriods = <?= json_encode($logPeriods) ?>;
    window.logTypes = <?= json_encode($logTypes) ?>;
</script>
<div class="modal fade" id="showLogsModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 100%;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logModalLabel"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6>Показывать логи за:</h6>
                <div class="btn-group mr-2" role="group">
					<?php foreach ($logPeriods as $k => $v):?>
                        <div class="form-check mr-3">
                            <input class="form-check-input" type="radio" name="logs-period" data-ajax="1" id="logs-period-<?=$k?>" value="<?=$k?>"<?=(array_key_first($logPeriods) == $k ? ' checked' : '')?>>
                            <label class="form-check-label" for="logs-period-<?=$k?>"><?=$v?></label>
                        </div>
					<?php endforeach;?>
                </div>
                <h6>Тип логов:</h6>
                <div class="btn-group mr-2" role="group">
                    <?php foreach ($logTypes as $k => $v):?>
                        <div class="form-check mr-3">
                            <input class="form-check-input" type="radio" name="logs-type" data-ajax="1" id="logs-type-<?=$k?>" value="<?=$k?>"<?=(array_key_first($logTypes) == $k ? ' checked' : '')?>>
                            <label class="form-check-label" for="logs-type-<?=$k?>"><?=$v?></label>
                        </div>
                    <?php endforeach;?>
                </div>
                <hr/>
                <pre id="logModalContainer">

                </pre>
            </div>
        </div>
        <div id="logModalData"></div>
    </div>
</div>