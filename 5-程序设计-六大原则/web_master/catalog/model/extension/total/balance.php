<?php

class ModelExtensionTotalBalance extends Model
{
    public function getTotal($total)
    {
        $this->load->language('extension/total/balance');
        if ($this->customer->isLogged()) {
            if (isset($this->session->data['useBalance'])) {
                $useBalance = session('useBalance');
                if ($useBalance) {
                    // 余额取负数
                    $useBalance = 0 - $useBalance;
                    $total['total'] = $total['total'] + $useBalance;
                    $total['totals'][] = array(
                        'code' => 'balance',
                        'title' => $this->language->get('text_balance'),
                        'value' => $useBalance,
                        'sort_order' => $this->config->get('total_balance_sort_order')
                    );
                }
            }
        }
    }

    public function getTotalByCartId($total, $products = [], $params = [])
    {
        //购物车、下单、支付流程已变动, 先下单后选择支付方式，故不存在下单时存在部分或全部扣减信用额度的情况，保留此方法是因为循环调用
    }
    public function getTotalByProducts($total, $products = [], $params = [])
    {

    }

    public function getTotalByOrderId($total, $orderId)
    {
        $this->load->language('extension/total/balance');
        $useBalance = $this->orm->table('oc_order_total')
            ->where([
                'order_id'  => $orderId,
                'code'      => 'balance'
            ])
            ->value('value');
        if ($useBalance){
            $total['totals'][] = array(
                'code' => 'balance',
                'title' => $this->language->get('text_balance'),
                'value' => $useBalance,
                'sort_order' => $this->config->get('total_balance_sort_order')
            );
        }
    }
}
