<?php

/**
 * Class ModelExtensionTotalSubTotal
 * @property \Cart\Customer $customer 用户
 * @property \Cart\Country $country 国家
 * @property \Cart\Cart $cart 购物车
 * @property \Session $session Session
 * @property \Language $language Language
 * @property \Config $config
 * @property ModelCheckoutPreOrder $model_checkout_pre_order
 */
class ModelExtensionTotalSubTotal extends Model
{
    public function getTotal($total, $buyer_id = null)
    {
        $this->load->language('extension/total/sub_total');

        if (isset($buyer_id) && !$this->country->isEuropeCountry($this->customer->getCountryId())) {
            // 不属于欧洲国家
            $sub_total = $this->cart->getSubTotalWithBuyerId($buyer_id);
        } else {
            $sub_total = $this->cart->getSubTotal();
        }


        if (!empty($this->session->data['vouchers'])) {
            foreach ($this->session->data['vouchers'] as $voucher) {
                $sub_total += $voucher['amount'];
            }
        }
        if ($this->customer->isLogged()) {
            if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
                /*$total['total'] += $sub_total / (0.85 / 2);*/
                $total['total'] += $this->cart->getRealTotal();
            } else {
                $total['total'] += $sub_total;
            }
        } else {
            $total['total'] += $sub_total;
        }

        //sub-total重新计算为各个产品total之和
        if (isset($buyer_id) && !$this->country->isEuropeCountry($this->customer->getCountryId())) {
            $sub_total = $this->cart->getRealSubTotalWithBuyerId($buyer_id);
        } else {
            $sub_total = $this->cart->getRealSubTotal();
        }

        $total['totals'][] = array(
            'code' => 'sub_total',
            'title' => $this->language->get('text_sub_total'),
            'value' => $sub_total,
            'sort_order' => $this->config->get('total_sub_total_sort_order')
        );

    }

    public function getTotalByCartId($total, $products = [], $params = [])
    {
        $this->load->language('extension/total/sub_total');

        $sub_total = $this->cart->getSubTotal([], $products);
        if ($this->customer->isLogged()) {
            if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
                /*$total['total'] += $sub_total / (0.85 / 2);*/
                $total['total'] += $this->cart->getRealTotal([], $products);
            } else {
                $total['total'] += $sub_total;
            }
        } else {
            $total['total'] += $sub_total;
        }

        //sub-total重新计算为各个产品total之和
        $sub_total = $this->cart->getRealSubTotal([], $products);

        $total['totals'][] = array(
            'code' => 'sub_total',
            'title' => $this->language->get('text_sub_total'),
            'value' => $sub_total,
            'sort_order' => $this->config->get('total_sub_total_sort_order')
        );
    }

    public function getTotalByProducts($total, $products = [], $params = [])
    {
        $this->load->language('extension/total/sub_total');
        $this->load->model('checkout/pre_order');
        $sub_total = $this->model_checkout_pre_order->getSubTotal($products);
        if ($this->customer->isLogged()) {
            if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
                /*$total['total'] += $sub_total / (0.85 / 2);*/
                $total['total'] += $this->model_checkout_pre_order->getRealTotal($products);
            } else {
                $total['total'] += $sub_total;
            }
        } else {
            $total['total'] += $sub_total;
        }
        //sub-total重新计算为各个产品total之和
        $sub_total = $this->model_checkout_pre_order->getRealSubTotal($products);

        $total['totals'][] = array(
            'code' => 'sub_total',
            'title' => $this->language->get('text_sub_total'),
            'value' => $sub_total,
            'sort_order' => $this->config->get('total_sub_total_sort_order')
        );
    }

    public function getTotalByOrderId($total, $orderId)
    {
        $this->load->language('extension/total/sub_total');

        $sub_total = $this->orm->table('oc_order_total')
            ->where([
                'order_id'  => $orderId,
                'code'      => 'sub_total'
            ])
            ->value('value');

        $total['totals'][] = array(
            'code' => 'sub_total',
            'title' => $this->language->get('text_sub_total'),
            'value' => $sub_total,
            'sort_order' => $this->config->get('total_sub_total_sort_order')
        );

    }

    public function getDefaultTotal($total)
    {
        $this->load->language('extension/total/sub_total');

        $total['total'] += 0;
        $total['totals'][] = array(
            'code' => 'sub_total',
            'title' => $this->language->get('text_sub_total'),
            'value' => 0,
            'sort_order' => $this->config->get('total_sub_total_sort_order')
        );
    }

}
