<form method="get" class="card analytics-filters" data-workload-filters>
<?php if(($filters['classroom_id']??'')!==''):?><input type="hidden" name="classroom_id" value="<?=e($filters['classroom_id'])?>"><?php endif;?>
<?php foreach(Workload::FIELDS as $key=>$label):?>
<label><?=e($label)?><select name="<?=e($key)?>"><option value="">Todas las opciones autorizadas</option><?php foreach($options[$key] as $value):?><option value="<?=e((string)$value)?>" <?=(string)$value===$filters[$key]?'selected':''?>><?=e((string)$value)?></option><?php endforeach;?></select></label>
<?php endforeach;?>
<?php if($showStatus??true):?><label>Estado<select name="status"><option value="">Todos</option><?php foreach(Workload::STATES as $key=>$label):?><option value="<?=e($key)?>" <?=$filters['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><?php endif;?>
<button class="btn btn-primary">Aplicar filtros</button><a href="<?=e(base_url($filterReset))?>">Limpiar filtros</a>
</form>
<script src="<?=e(asset('js/workload-filters.js'))?>" defer></script>
