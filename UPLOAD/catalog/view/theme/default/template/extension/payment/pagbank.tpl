<div class="well" id="pagbank-form">
    <script src="https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js"></script>
    
    <div class="form-group col-md-6 required">
		<label class="control-label">Como deseja pagar?</label>
		<select id="pb-choose-method" class="form-control" style="margin-bottom: 15px;">
			<option value="">Selecione uma opção...</option>
			<option value="card">Cartão de Crédito</option>
			<option value="boleto">Boleto Bancário</option>
		</select>
    </div>

    <div class="form-group col-md-6 required">
		<label class="control-label">CPF do Titular</label>
		<input type="text" id="pb-cpf" class="form-control" placeholder="000.000.000-00" value="<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', $order_full['custom_field'][3]), ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div id="section-card" style="display: none;">
        <hr>
        <div class="col-md-12">
		<h3>Dados do Cartão</h3>
		</div>
		<div class="form-group col-md-6 required">
			<input type="text" id="pb-n" placeholder="Número do Cartão" class="form-control" style="margin-bottom:10px">
			<input type="text" id="pb-h" placeholder="Nome impresso no cartão" class="form-control" style="margin-bottom:10px">
			
			<div class="row">
				<div class="col-xs-4">
					<input type="text" id="pb-m" placeholder="MM" class="form-control" maxlength="2">
				</div>
				<div class="col-xs-4">
					<input type="text" id="pb-y" placeholder="AAAA" class="form-control" maxlength="4">
				</div>
				<div class="col-xs-4">
					<input type="text" id="pb-v" placeholder="CVV" class="form-control" maxlength="4">
				</div>
			</div>
		</div>

		<div class="form-group col-md-6 required">
			<label class="control-label">Parcelamento</label>
			<select id="pb-installments" class="form-control">
				<?php 
				$valor_minimo_parcela = 5.00; // Defina o mínimo exigido pelo PagBank
				$max_p = (isset($max_installments) && $max_installments > 0) ? $max_installments : 1;
				
				for ($i = 1; $i <= $max_p; $i++) { 
					$valor_parcela = $total_pedido / $i;
					
					// Só exibe a opção de parcelamento se o valor da parcela for maior ou igual ao mínimo
					if ($i == 1 || $valor_parcela >= $valor_minimo_parcela) {
						echo '<option value="' . $i . '">' . $i . 'x de R$ ' . number_format($valor_parcela, 2, ',', '.') . ' sem juros</option>';
					}
				} 
				?>
			</select>
		</div>
    </div>

    <div id="section-boleto" style="display: none;">
        <hr>
        <div class="row">
            <div class="col-md-12">
                <div class="alert-info" style="padding: 10px;">
                    <i class="fa fa-info-circle"></i> O boleto bancário será gerado após a confirmação do pedido.
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="pb-type" value="">

    <div class="row">
        <div class="buttons col-md-12" style="margin-top:20px">
            <div class="pull-right">
                <button type="button" id="pb-confirm" class="btn btn-primary" data-loading-text="Processando..." disabled>Confirmar Pedido</button>
            </div>
        </div>
    </div>
</div>

<script>
// Impede a digitação de letras e caracteres especiais no campo CPF
$('#pb-cpf').on('input', function() {
    // Substitui qualquer caractere que NÃO seja número por "nada"
    this.value = this.value.replace(/\D/g, '');

    // Garante que não ultrapasse 11 dígitos
    if (this.value.length > 11) {
        this.value = this.value.slice(0, 11);
    }
});

// Máscara para o Número do Cartão (Agrupamento de 4 em 4)
$('#pb-n').on('input', function() {
    var v = this.value.replace(/\D/g, ''); // Remove tudo que não é dígito
    
    // Limita a 16 dígitos (padrão da maioria das bandeiras)
    if (v.length > 16) v = v.slice(0, 16);
    
    // Aplica o espaçamento: 0000 0000 0000 0000
    this.value = v.replace(/(\d{4})(?=\d)/g, '$1 ');
});

// Máscara para Validade (Mês/Ano) - Opcional, mas ajuda muito o usuário
$('#pb-m').on('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 2);
});

$('#pb-y').on('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
});

// Máscara para CVV
$('#pb-v').on('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
});

// Lógica de Troca de Campos
$('#pb-choose-method').on('change', function() {
    var method = $(this).val();
    
    // Atualiza o pb-type
    $('#pb-type').val(method);
    
    // Limpa campos de cartão sempre que trocar
    $('#section-card input').val('');
    
    if (method === 'card') {
        $('#section-boleto').hide();
        $('#section-card').fadeIn();
        $('#pb-confirm').prop('disabled', false);
    } else if (method === 'boleto') {
        $('#section-card').hide();
        $('#section-boleto').fadeIn();
        $('#pb-confirm').prop('disabled', false);
    } else {
        $('#section-card, #section-boleto').hide();
        $('#pb-confirm').prop('disabled', true);
    }
});

$('#pb-confirm').on('click', function() {
    const $btn = $(this);
    const method = $('#pb-type').val();
    
    let data = { 
        method: method, 
        cpf: $('#pb-cpf').val().replace(/\D/g, ''), // Limpa CPF aqui também
        installments: $('#pb-installments').val() || 1
    };

    if (method === 'card') {
        if (!$('#pb-n').val() || !$('#pb-h').val() || !$('#pb-v').val()) {
            alert('Por favor, preencha todos os dados do cartão.');
            return;
        }

        try {
            const card = PagSeguro.encryptCard({
				publicKey: "<?php echo htmlspecialchars($public_key, ENT_QUOTES, 'UTF-8'); ?>",
				holder: $('#pb-h').val(),
				// O replace(/\s/g, '') remove todos os espaços em branco da máscara
				number: $('#pb-n').val().replace(/\s/g, ''), 
				expMonth: $('#pb-m').val(),
				expYear: $('#pb-y').val(),
				securityCode: $('#pb-v').val()
			});
            data.hash = card.encryptedCard;
        } catch (e) {
            alert('Dados do cartão inválidos. Verifique e tente novamente.');
            return;
        }
    }

    $.ajax({
        url: 'index.php?route=extension/payment/pagbank/confirm',
        type: 'post',
        data: data,
        dataType: 'json',
        beforeSend: function() { 
            $btn.button('loading'); 
        },
        complete: function() { 
            $btn.button('reset'); 
        },
        success: function(json) {
			if (json['success']) {
				// 1. Verifica se existe a URL do boleto
				if (json['payment_link']) {
					const win = window.open(json['payment_link'], '_blank');
					if (win) {
						win.focus();
					} else {
						// Se o navegador bloquear o popup, avisa o usuário
						alert('O boleto foi gerado, mas o seu navegador bloqueou a abertura da aba. Por favor, permita popups para este site.');
					}
				}
				
				// 2. Redireciona (O pequeno delay de 500ms ajuda o navegador a processar o window.open primeiro)
				setTimeout(function() {
					location = 'index.php?route=checkout/success';
				}, 500);

			} else {
				alert(json['error'] || 'Erro ao processar pagamento.');
			}
		},
        error: function(xhr, ajaxOptions, thrownError) {
            console.log("Status: " + xhr.status);
            console.log("Resposta: " + xhr.responseText);
            alert('Erro detectado! Verifique o console para detalhes.');
        }
    });
});
</script>