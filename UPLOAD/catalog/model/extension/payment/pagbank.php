<?php
class ModelExtensionPaymentPagBank extends Model {
    public function getMethod($address, $total) {
        $this->load->language('extension/payment/pagbank');

        // Verifica se o módulo global e o boleto estão ativos
        if (!$this->config->get('pagbank_status') || !$this->config->get('pagbank_boleto_status')) {
            return array();
        }

        $method_data = array(
            'code'       => 'pagbank',
            'title'      => 'PagBank: Boleto Bancário ou Cartão de Credito',
            'terms'      => '',
                // Somamos 1 na ordem para o boleto aparecer logo após o cartão
            'sort_order' => 1
        );

        return $method_data;
    }
}