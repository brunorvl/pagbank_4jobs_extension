<?php
class ControllerExtensionPaymentPagBank extends Controller {
    private $error = array();

    public function install() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "pagbank_transaction` (
          `pagbank_transaction_id` INT(11) NOT NULL AUTO_INCREMENT,
          `order_id` INT(11) NOT NULL,
          `charge_id` VARCHAR(100) NOT NULL,
          `status` VARCHAR(50) NOT NULL,
          `payload` TEXT NOT NULL,
          `date_added` DATETIME NOT NULL,
          PRIMARY KEY (`pagbank_transaction_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
    }

    public function index() {
        $this->load->language('extension/payment/pagbank');
        $this->document->setTitle("PagBank V4 4job Extension");
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('pagbank', $this->request->post);
            $this->session->data['success'] = "Configurações salvas com sucesso!";
            $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true));
        }

        $data['token'] = $this->session->data['token'];
        
        // Campos de configuração
        $fields = [
            'pagbank_status', 
            'pagbank_token', 
            'pagbank_sort_order',
            'pagbank_card_status', 
            'pagbank_card_max_installments',
            'pagbank_boleto_status',
			'pagbank_mode'
        ];

        foreach ($fields as $f) {
            if (isset($this->request->post[$f])) {
                $data[$f] = $this->request->post[$f];
            } else {
                $data[$f] = $this->config->get($f);
            }
        }

        // Listagem de Transações para a aba
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "pagbank_transaction ORDER BY date_added DESC LIMIT 20");
        $data['transactions'] = $query->rows;

        $data['action'] = $this->url->link('extension/payment/pagbank', 'token=' . $this->session->data['token'], true);
        $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true);
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/pagbank', $data));
    }

    public function checkStatus() {
        if (!$this->user->hasPermission('modify', 'extension/payment/pagbank')) {
            $this->response->setOutput(json_encode(['error' => 'Sem permissão'])); return;
        }
        $order_id = $this->request->get['order_id'];
        $query = $this->db->query("SELECT charge_id FROM " . DB_PREFIX . "pagbank_transaction WHERE order_id = '" . (int)$order_id . "' LIMIT 1");
		
		$mode = $this->config->get('pagbank_mode');
		$base_url = ($mode == 'sandbox') ? 'https://sandbox.api.pagseguro.com/' : 'https://api.pagseguro.com/';
        
        if ($query->num_rows) {
            $ch = curl_init($base_url . 'charges/' . $query->row['charge_id']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->config->get('pagbank_token')]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = json_decode(curl_exec($ch), true);
            
            if (isset($res['status'])) {
                $status_id = ($res['status'] == 'PAID') ? 15 : 1; 
                $this->load->model('sale/order');
                $this->model_sale_order->addOrderHistory($order_id, ['order_status_id' => $status_id, 'comment' => 'Sincronização Manual: ' . $res['status'], 'notify' => 0]);
                $this->db->query("UPDATE " . DB_PREFIX . "pagbank_transaction SET status = '".$res['status']."' WHERE order_id = '".(int)$order_id."'");
                $json['success'] = "Status do Pedido #" . $order_id . " atualizado para " . $res['status'];
            } else { $json['error'] = "Não foi possível obter o status na API."; }
        } else { $json['error'] = "Transação não encontrada."; }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/pagbank')) {
            return false;
        }
        if (!isset($this->request->post['token']) || $this->request->post['token'] != $this->session->data['token']) {
            return false;
        }
        return true;
    }
}