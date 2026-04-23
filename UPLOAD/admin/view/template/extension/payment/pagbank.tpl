<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-pagbank" class="btn btn-primary"><i class="fa fa-save"></i> Salvar</button>
        <a href="<?php echo $cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
      </div>
      <h1>PagBank V4 4job Extension</h1>
    </div>
  </div>
  <div class="container-fluid">
    <ul class="nav nav-tabs">
      <li class="active"><a href="#tab-config" data-toggle="tab">Configurações</a></li>
      <li><a href="#tab-trans" data-toggle="tab">Transações/Logs</a></li>
    </ul>
    
    <div class="tab-content">
      <div class="tab-pane active" id="tab-config">
        <form action="<?php echo $action; ?>" method="post" id="form-pagbank" class="form-horizontal">
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" />
          <div class="form-group">
            <label class="col-sm-2 control-label">Módulo Geral</label>
            <div class="col-sm-10">
              <select name="pagbank_status" class="form-control">
                <option value="1" <?php if($pagbank_status) echo 'selected'; ?>>Habilitado</option>
                <option value="0" <?php if(!$pagbank_status) echo 'selected'; ?>>Desabilitado</option>
              </select>
            </div>
          </div>
          
		  <div class="form-group">
			<label class="col-sm-2 control-label"><span data-toggle="tooltip" title="Sandbox é o ambiente de testes do PagBank">Ambiente</span></label>
			<div class="col-sm-10">
				<select name="pagbank_mode" class="form-control">
					<option value="production" <?php if($pagbank_mode == 'production') echo 'selected'; ?>>Produção (Real)</option>
					<option value="sandbox" <?php if($pagbank_mode == 'sandbox') echo 'selected'; ?>>Sandbox (Testes)</option>
				</select>
			</div>
	      </div>
		  
		  <div class="form-group required">
            <label class="col-sm-2 control-label">Token de Acesso</label>
            <div class="col-sm-10"><input type="text" name="pagbank_token" value="<?php echo $pagbank_token; ?>" class="form-control" /></div>
          </div>
          
          <hr>
          <h3>Opções de Pagamento</h3>
          
          <div class="form-group">
            <label class="col-sm-2 control-label">Aceitar Cartão?</label>
            <div class="col-sm-10">
              <select name="pagbank_card_status" class="form-control">
                <option value="1" <?php if($pagbank_card_status) echo 'selected'; ?>>Sim</option>
                <option value="0" <?php if(!$pagbank_card_status) echo 'selected'; ?>>Não</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label">Máximo de Parcelas</label>
            <div class="col-sm-10">
              <select name="pagbank_card_max_installments" class="form-control">
                <?php for($i=1; $i<=12; $i++) { ?>
                <option value="<?php echo $i; ?>" <?php if($pagbank_card_max_installments == $i) echo 'selected'; ?>><?php echo $i; ?>x</option>
                <?php } ?>
              </select>
            </div>
          </div>

          <hr>
          <div class="form-group">
            <label class="col-sm-2 control-label">Aceitar Boleto?</label>
            <div class="col-sm-10">
              <select name="pagbank_boleto_status" class="form-control">
                <option value="1" <?php if($pagbank_boleto_status) echo 'selected'; ?>>Sim</option>
                <option value="0" <?php if(!$pagbank_boleto_status) echo 'selected'; ?>>Não</option>
              </select>
            </div>
          </div>
        </form>
      </div>
      
      <div class="tab-pane" id="tab-trans">
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead>
              <tr>
                <td>Pedido</td>
                <td>Charge ID</td>
                <td>Status PagBank</td>
                <td>Data</td>
                <td class="text-right">Ação</td>
              </tr>
            </thead>
            <tbody>
              <?php if ($transactions) { ?>
                <?php foreach($transactions as $t) { ?>
                <tr>
                  <td>#<?php echo htmlspecialchars($t['order_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($t['charge_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><span class="label label-info"><?php echo htmlspecialchars($t['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                  <td><?php echo htmlspecialchars($t['date_added'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="text-right"><button onclick="checkNow('<?php echo htmlspecialchars($t['order_id'], ENT_QUOTES, 'UTF-8'); ?>')" class="btn btn-warning btn-sm"><i class="fa fa-refresh"></i> Sincronizar</button></td>
                </tr>
                <?php } ?>
              <?php } else { ?>
                <tr><td colspan="5" class="text-center">Nenhuma transação registrada.</td></tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function checkNow(id) {
  $.ajax({
    url: 'index.php?route=extension/payment/pagbank/checkStatus&token=<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>&order_id=' + id,
    dataType: 'json',
    success: function(json) { 
        if(json.success) alert(json.success); 
        if(json.error) alert(json.error);
        location.reload(); 
    }
  });
}
</script>
<?php echo $footer; ?>