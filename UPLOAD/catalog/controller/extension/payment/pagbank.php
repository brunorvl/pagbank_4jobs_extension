<?php
class ControllerExtensionPaymentPagBank extends Controller {
    public function index() {
        $this->load->model('checkout/order');
        $data['total_pedido'] = $this->cart->getTotal();
        $data['max_installments'] = (int)$this->config->get('pagbank_card_max_installments');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data['order_full'] = $order_info;

        $mode = $this->config->get('pagbank_mode');
        $base_url = ($mode == 'sandbox') ? 'https://sandbox.api.pagseguro.com/' : 'https://api.pagseguro.com/';
        
        $ch = curl_init($base_url . 'public-keys');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config->get('pagbank_token'), 
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['type' => 'card']));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = json_decode(curl_exec($ch), true);
        
        $data['public_key'] = isset($res['public_key']) ? $res['public_key'] : '';

        return $this->load->view('extension/payment/pagbank', $data);
    }
	
	public function confirm() {
		$this->load->model('checkout/order');
		$order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		// 1. Captura e limpeza do CPF/CNPJ
		$tax_id = preg_replace('/[^0-9]/', '', (isset($this->request->post['cpf']) ? $this->request->post['cpf'] : ''));

		if (empty($tax_id) && !empty($order['custom_field'])) {
			foreach ($order['custom_field'] as $value) {
				$clean_val = preg_replace('/[^0-9]/', '', $value);
				if (strlen($clean_val) == 11 || strlen($clean_val) == 14) {
					$tax_id = $clean_val;
					break;
				}
			}
		}

		$mode = $this->config->get('pagbank_mode');
		
		// Fallback Sandbox para testes internos
		if (empty($tax_id) && $mode == 'sandbox') {
			$tax_id = "00161381049"; 
		}
		
		$post_method = isset($this->request->post['method']) ? $this->request->post['method'] : 'boleto'; 
		$customer_email = ($mode == 'sandbox') ? 'comprador_teste@email.com' : $order['email'];
		
		$soft_descriptor = preg_replace('/[^A-Za-z0-9]/', '', $this->config->get('config_name'));
		$soft_descriptor = substr(strtoupper($soft_descriptor), 0, 13);
		if (empty($soft_descriptor)) { $soft_descriptor = "LIVRARIA"; }

		// Montagem do Payload
		$payload = array(
			"reference_id" => (string)$order['order_id'],
			"customer" => array(
				"name"   => substr($order['firstname'] . ' ' . $order['lastname'], 0, 30), 
				"email"  => $customer_email, 
				"tax_id" => $tax_id,
				"phones" => array(array(
					"type"    => "MOBILE",
					"country" => "55",
					"area"    => "81",
					"number"  => "999999999"
				))
			),
			"items" => array(array(
				"reference_id" => "ref_" . $order['order_id'],
				"name"         => "Pedido #" . $order['order_id'],
				"quantity"     => 1,
				"unit_amount"  => (int)round($this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false) * 100)
			)),
			"shipping" => array(
				"address" => array(
					"street"      => substr($order['shipping_address_1'], 0, 160),
					"number"      => "SN",
					"locality"    => substr($order['shipping_address_2'], 0, 60),
					"city"        => $order['shipping_city'],
					"region_code" => strtoupper($order['shipping_zone_code']),
					"country"     => "BRA",
					"postal_code" => preg_replace('/[^0-9]/', '', $order['shipping_postcode'])
				)
			),
			"notification_urls" => array($this->url->link('extension/payment/pagbank/callback', '', true)),
			"charges" => array(array(
				"reference_id" => "charge_" . $order['order_id'],
				"amount" => array(
					"value"    => (int)round($this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false) * 100), 
					"currency" => "BRL"
				)
			))
		);

		// Lógica Dinâmica de Método de Pagamento
		if ($post_method == 'card') {
			$payload['charges'][0]['payment_method'] = array(
				"type"            => "CREDIT_CARD",
				"installments"    => (int)$this->request->post['installments'],
				"capture"         => true,
				"soft_descriptor" => $soft_descriptor,
				"card" => array(
					"encrypted" => !empty($this->request->post['hash']) ? $this->request->post['hash'] : '',
					"store"     => false
				)
			);
		} else {
			$payload['charges'][0]['payment_method'] = array(
				"type"   => "BOLETO",
				"boleto" => array(
					"due_date" => date('Y-m-d', strtotime('+3 days')),
					"instruction_lines" => array(
						"line_1" => "Pagavel em qualquer banco ate o vencimento.",
						"line_2" => "Nao receber apos o vencimento."
					),
					"holder" => array(
						"name"    => substr($order['firstname'] . ' ' . $order['lastname'], 0, 30),
						"email"   => $customer_email,
						"tax_id"  => $tax_id,
						"address" => array(
							"street"      => substr($order['payment_address_1'], 0, 160),
							"number"      => "SN",
							"locality"    => substr($order['payment_address_2'], 0, 60),
							"city"        => $order['payment_city'],
							"region"      => strtoupper($order['payment_zone_code']),
							"region_code" => strtoupper($order['payment_zone_code']),
							"country"     => "BRA",
							"postal_code" => preg_replace('/[^0-9]/', '', $order['payment_postcode'])
						)
					)
				)
			);
		}
		
		$base_url = ($mode == 'sandbox') ? 'https://sandbox.api.pagseguro.com/' : 'https://api.pagseguro.com/';
		$ch = curl_init($base_url . 'orders');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $this->config->get('pagbank_token'), 
			'Content-Type: application/json'
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
		curl_setopt($ch, CURLOPT_POST, true); 
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

		$response_raw = curl_exec($ch);
		$http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		$res = json_decode($response_raw, true);

		if (ob_get_length()) ob_clean();
		$this->response->addHeader('Content-Type: application/json');

		if ($http_code == 201 || $http_code == 200) {
			$payment_link = '';
			
			// Se for boleto, captura o link para abrir em nova aba
			if ($post_method == 'boleto' && isset($res['charges'][0]['links'])) {
				foreach ($res['charges'][0]['links'] as $link) {
					if ($link['rel'] == 'SELF') {
						$payment_link = $link['href'];
						break;
					}
				}
			}

			// Grava Transação e Histórico
			$comment = "PagBank: Transação aceita. Método: " . strtoupper($post_method);
			if ($payment_link) {
				$comment .= " | Link Boleto: " . $payment_link;
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order['order_id'] . "', order_status_id = '" . (int)$this->config->get('config_order_status_id') . "', notify = '0', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");
			
			// 2. Prepara o comentário para o histórico
			$comment = "Pagamento via PagBank (" . strtoupper($post_method) . ").";
			
			if (!empty($payment_link)) {
				// Adiciona o link clicável para o Admin e Cliente
				$comment .= "<br><b>Link do Boleto:</b> <a href='" . $payment_link . "' target='_blank'>" . $payment_link . "</a>";
			}

			// 3. Atualiza o histórico com o comentário (ID do status, Comentário, Notificar Cliente)
			$this->model_checkout_order->addOrderHistory(
				$order['order_id'], 
				$this->config->get('config_order_status_id'), 
				$comment, 
				true // Define como true se quiser que o cliente receba o e-mail com o link
			);

			$this->response->setOutput(json_encode(array(
				'success'      => true,
				'redirect'     => $this->url->link('checkout/success', '', true),
				'payment_link' => $payment_link // Enviado para o JS abrir a aba
			)));
		} else {
			$error_msg = isset($res['error_messages'][0]['description']) ? $res['error_messages'][0]['description'] : 'Erro desconhecido na API.';
			$this->response->setOutput(json_encode(array(
				'error' => 'Falha no processamento: ' . $error_msg
			)));
		}
	}
    
	/* modo depuração caso necessario
	public function confirm() {
		$this->load->model('checkout/order');
		$order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		// 1. Limpa o CPF/CNPJ vindo do checkout
		$tax_id = preg_replace('/[^0-9]/', '', (isset($this->request->post['cpf']) ? $this->request->post['cpf'] : ''));

		// 2. Se falhar, tenta buscar nos dados do pedido (OpenCart custom fields)
		if (empty($tax_id) && !empty($order['custom_field'])) {
			foreach ($order['custom_field'] as $value) {
				$clean_val = preg_replace('/[^0-9]/', '', $value);
				if (strlen($clean_val) == 11 || strlen($clean_val) == 14) {
					$tax_id = $clean_val;
					break;
				}
			}
		}

		// 3. SE AINDA ESTIVER VAZIO (Apenas para não travar o teste em Sandbox)
		$mode = $this->config->get('pagbank_mode');
		if (empty($tax_id) && $mode == 'sandbox') {
			$tax_id = "04220973494"; 
		}
		
		$post_method = isset($this->request->post['method']) ? $this->request->post['method'] : 'boleto'; 
		$mode = $this->config->get('pagbank_mode');
		$customer_email = ($mode == 'sandbox') ? 'comprador_teste@email.com' : $order['email'];
		
		$soft_descriptor = preg_replace('/[^A-Za-z0-9]/', '', $this->config->get('config_name'));
		$soft_descriptor = substr(strtoupper($soft_descriptor), 0, 13);
		if (empty($soft_descriptor)) { $soft_descriptor = "LIVRARIA"; }

		// Montagem do Payload Base
		$payload = array(
			"reference_id" => (string)$order['order_id'],
			"customer" => array(
				"name"   => substr($order['firstname'] . ' ' . $order['lastname'], 0, 30), 
				"email"  => $customer_email, 
				"tax_id" => $tax_id,
				"phones" => array(array(
					"type"    => "MOBILE",
					"country" => "55",
					"area"    => "81",
					"number"  => "999999999"
				))
			),
			"items" => array(array(
				"reference_id" => "ref_" . $order['order_id'],
				"name"         => "Pedido #" . $order['order_id'],
				"quantity"     => 1,
				"unit_amount"  => (int)round($this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false) * 100)
			)),
			"shipping" => array(
				"address" => array(
					"street"      => substr($order['shipping_address_1'], 0, 160),
					"number"      => "SN",
					"locality"    => substr($order['shipping_address_2'], 0, 60),
					"city"        => $order['shipping_city'],
					"region_code" => strtoupper($order['shipping_zone_code']),
					"country"     => "BRA",
					"postal_code" => preg_replace('/[^0-9]/', '', $order['shipping_postcode'])
				)
			),
			"notification_urls" => array(
				$this->url->link('extension/payment/pagbank/callback', '', true)
			),
			"charges" => array(array(
				"reference_id" => "charge_" . $order['order_id'],
				"amount" => array(
					"value"    => (int)round($this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false) * 100), 
					"currency" => "BRL"
				),
				"payment_method" => array() // Será preenchido abaixo
			))
		);

		// Lógica para separar CARTÃO de BOLETO (Evita enviar chaves vazias)
		if ($post_method == 'card') {
			$payload['charges'][0]['payment_method'] = array(
				"type"            => "CREDIT_CARD",
				"installments"    => (int)$this->request->post['installments'],
				"capture"         => true,
				"soft_descriptor" => $soft_descriptor,
				"card" => array(
					"encrypted" => !empty($this->request->post['hash']) ? $this->request->post['hash'] : '',
					"store"     => false
				)
			);
		} else {
			$payload['charges'][0]['payment_method'] = array(
				"type"   => "BOLETO",
				"boleto" => array(
					"due_date" => date('Y-m-d', strtotime('+3 days')),
					"instruction_lines" => array(
						"line_1" => "Pagavel em qualquer banco ate o vencimento.",
						"line_2" => "Nao receber apos o vencimento."
					),
					"holder" => array(
						"name"    => substr($order['firstname'] . ' ' . $order['lastname'], 0, 30),
						"email"   => $customer_email,
						"tax_id"  => $tax_id,
						"address" => array(
							"street"      => substr($order['payment_address_1'], 0, 160),
							"number"      => "SN",
							"locality"    => substr($order['payment_address_2'], 0, 60),
							"city"        => $order['payment_city'],
							"region"      => strtoupper($order['payment_zone_code']),
							"region_code" => strtoupper($order['payment_zone_code']),
							"country"     => "BRA",
							"postal_code" => preg_replace('/[^0-9]/', '', $order['payment_postcode'])
						)
					)
				)
			);
		}
		
		// EXECUÇÃO DO CURL
		$base_url = ($mode == 'sandbox') ? 'https://sandbox.api.pagseguro.com/' : 'https://api.pagseguro.com/';
		$ch = curl_init($base_url . 'orders');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $this->config->get('pagbank_token'), 
			'Content-Type: application/json'
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
		curl_setopt($ch, CURLOPT_POST, true); 
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

		$response_raw = curl_exec($ch);
		$http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		$res = json_decode($response_raw, true);

		if (ob_get_length()) ob_clean();
		$this->response->addHeader('Content-Type: application/json');

		if ($http_code == 201) {
			$status_label = "SUCESSO";
		} else {
			$status_label = "ERRO";
		}

		$this->response->setOutput(json_encode(array(
			'error' => "--- DADOS PARA TESTE NO POSTMAN $status_label ---",
			'endpoint' => $base_url . 'orders',
			'token' => $this->config->get('pagbank_token'),
			'payload_json' => $payload,
			'response' => $res,
			'http_code' => $http_code
		)));
		
		$this->response->output();
		exit;
	}
	*/
	
    public function callback() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (isset($data['id']) && isset($data['reference_id'])) {
            $status = isset($data['charges'][0]['status']) ? $data['charges'][0]['status'] : '';
            $reference = $data['reference_id']; 

            $this->load->model('checkout/order');

            if ($status == 'PAID') {
                $this->model_checkout_order->addOrderHistory($reference, 19, "PagBank: Pagamento confirmado.", true);
                $this->model_checkout_order->addOrderHistory($reference, 2, "PagBank: Pedido em processamento.", true);
            } elseif (in_array($status, ['DECLINED', 'CANCELED'])) {
                $this->model_checkout_order->addOrderHistory($reference, 7, "PagBank: Pagamento recusado ou cancelado.", true);
            } else {
                $this->model_checkout_order->addOrderHistory($reference, 1, "PagBank: Status atualizado para " . $status, false);
            }

            header("HTTP/1.1 200 OK");
            exit;
        }
    }
}