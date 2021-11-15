<?php
class ModelExtensionTotalTotal extends Model {
	public function getTotal($total) {
		$this->load->language('extension/total/total');

		$total['totals'][] = array(
			'code'       => 'total',
			'title'      => $this->language->get('text_total'),
			'value'      => max(0, $total['total']),
			'sort_order' => $this->config->get('total_total_sort_order')
		);
	}

    public function getTotalByCartId($total, $products = [], $params = []) {
        $this->load->language('extension/total/total');

        $total['totals'][] = array(
            'code'       => 'total',
            'title'      => $this->language->get('text_total'),
            'value'      => max(0, $total['total']),
            'sort_order' => $this->config->get('total_total_sort_order')
        );
    }

    public function getTotalByProducts($total, $products = [], $params = [])
    {
        $this->load->language('extension/total/total');
        $total['totals'][] = array(
            'code'       => 'total',
            'title'      => $this->language->get('text_total'),
            'value'      => max(0, $total['total']),
            'sort_order' => $this->config->get('total_total_sort_order')
        );
    }

    public function getTotalByOrderId($total, $orderId) {
        $this->load->language('extension/total/total');
        $total['total'] = $this->orm->table('oc_order_total')
            ->where([
                'order_id'  => $orderId,
                'code'      => 'total'
            ])
            ->value('value');

        $total['totals'][] = array(
            'code'       => 'total',
            'title'      => $this->language->get('text_total'),
            'value'      => max(0, $total['total']),
            'sort_order' => $this->config->get('total_total_sort_order')
        );
    }

    public function getDefaultTotal($total) {
        $this->load->language('extension/total/total');

        $total['totals'][] = array(
            'code'       => 'total',
            'title'      => $this->language->get('text_total'),
            'value'      => 0,
            'sort_order' => $this->config->get('total_total_sort_order')
        );
    }
}
