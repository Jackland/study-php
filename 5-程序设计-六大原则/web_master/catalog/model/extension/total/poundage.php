<?php

use App\Enums\Pay\PayCode;

class ModelExtensionTotalPoundage extends Model
{
    public function getTotal($total)
    {
        $this->load->language('extension/total/poundage');
        if ($paymentMethod = $this->request->attributes->get('payment_method')) {
            $poundagePercent = PayCode::getPoundage($paymentMethod);
            if ($poundagePercent > 0) {
                if ($this->customer->getCountryId() == 107) {
                    $poundage = round($total['total'] * $poundagePercent);
                } else {
                    $poundage = round($total['total'] * $poundagePercent, 2);
                }

                $total['total'] = $total['total'] + $poundage;

                $total['totals'][] = array(
                    'code' => 'poundage',
                    'title' => $this->language->get('text_poundage'),
                    'value' => max(0, $poundage),
                    'sort_order' => $this->config->get('total_poundage_sort_order')
                );
            }
        }
    }

    public function getTotalByCartId($total, $products = [], $params = [])
    {
        $this->getTotal($total);
    }
    public function getTotalByProducts($total, $products = [], $params = [])
    {

    }
    // 这个方法好像已经废弃，里面逻辑也不对，payment_method都是驼峰写法的
    public function getTotalByOrderId($total, $orderId)
    {
        $this->load->language('extension/total/poundage');
        $paymentMethod = $this->orm->table('oc_order')
            ->where('order_id', '=', $orderId)
            ->value('payment_method');

        if ($paymentMethod) {
            $poundagePercent = PayCode::getPoundage($paymentMethod);
            if ($poundagePercent > 0) {
                if ($this->customer->getCountryId() == 107) {
                    $poundage = round($total['total'] * $poundagePercent);
                } else {
                    $poundage = round($total['total'] * $poundagePercent, 2);
                }

                $total['total'] = $total['total'] + $poundage;

                $total['totals'][] = array(
                    'code' => 'poundage',
                    'title' => $this->language->get('text_poundage'),
                    'value' => max(0, $poundage),
                    'sort_order' => $this->config->get('total_poundage_sort_order')
                );
            }
        }
    }

}
