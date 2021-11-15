<?php

/**
 * Class ModelExtensionTotalFreight
 * @property ModelCommonProduct $model_common_product
 */
class ModelExtensionTotalFreight extends Model
{
    /**
     * 四舍五入精度
     * 日本不保留小数位，其他国家保留两位
     *
     * @var int $precision
     */
    private $precision;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
    }

    public function getTotal($total,$delivery_type = null)
    {
        $this->load->language('extension/total/freight');
        if ($this->customer->isLogged()) {
            /**
             * 获取购物车总产品的运费
             * $freightAmount 购物车产品总运费(运费+打包费)
             */
            $freightAmount = 0;
            $shippingRate = $this->config->get('cwf_base_cloud_freight_rate');
            $totalPackageFee =0;
            $totalVolume = 0;
            $overweightSurcharge = 0;//超重附加费
            if($delivery_type == null) {
                $delivery_type = isset($this->session->data['delivery_type']) ? $this->session->data['delivery_type'] : -1;
            }
            foreach ($this->cart->getProducts(null, $delivery_type) as $product){

                if($product['delivery_type'] !=2) {
                    //非云送仓业务,上门取货的运费仅为打包费
                    $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
                    if ($isCollectionFromDomicile) {
                        $freightAmount += $product['package_fee_per'] * $product['quantity'];
                    } else {
                        $freightAmount += ($product['freight_per'] + $product['package_fee_per']) * $product['quantity'];
                    }
                }else{
                    $freightAndPackageFeeArr = $this->freight->getFreightAndPackageFeeByProducts(array($product['product_id']));
                    if($product['combo'] == 1){
                        //combo产品运费展示
                        foreach ($freightAndPackageFeeArr[$product['product_id']]  as $set_product_id => $freightAndPackage){
                            $shippingRate = $freightAndPackage['freight_rate'];
                            $this->load->model('common/product');
                            /**
                             * @var ModelCheckoutCart $cart_model
                             */
                            $product_model = $this->model_common_product;
                            $setProductInfo = $product_model->getComboProductBySetProductId($product['product_id'],$set_product_id);
                            $setProductQty = $setProductInfo[0]['qty']*$product['quantity'];
                            $totalVolume += $freightAndPackage['volume_inch']*$setProductQty;
                            $totalPackageFee += $freightAndPackage['package_fee']*$setProductQty;
                            $overweightSurcharge += ($freightAndPackage['overweight_surcharge'] ?? 0);
                        }
                    }else{
                        $shippingRate = $freightAndPackageFeeArr[$product['product_id']]['freight_rate'];
                        $totalVolume +=$freightAndPackageFeeArr[$product['product_id']]['volume_inch']*$product['quantity'];
                        $totalPackageFee += $freightAndPackageFeeArr[$product['product_id']]['package_fee']*$product['quantity'];
                        $overweightSurcharge += ($freightAndPackageFeeArr[$product['product_id']]['overweight_surcharge'] ?? 0);
                    }
                }
            }
            if(in_array($delivery_type,[-1,2])) {
                if ($this->cart->countProducts(2)>0) {
                    //云送仓体积不足，按规定体积计算
                    //1363 加上超重附加费
                    $freightAmount += (double)($totalVolume * $shippingRate) + $totalPackageFee + $overweightSurcharge;
                }
            }
            $total['total'] = $total['total'] + $freightAmount;
            $total['totals'][] = array(
                'code'       => 'freight',
                'title'      => $this->language->get('text_freight'),
                'value'      => $freightAmount,
                'sort_order' => $this->config->get('total_freight_sort_order')
            );
        }
    }

    public function getTotalByCartId($total, $products = [], $params = [])
    {
        $this->load->language('extension/total/freight');
        $this->load->model('common/product');
        if ($this->customer->isLogged()) {
            /**
             * 获取购物车总产品的运费
             * $freightAmount 购物车产品总运费(运费+打包费)
             */
            $freightAmount = 0;
            $shippingRate = $this->config->get('cwf_base_cloud_freight_rate');
            $totalPackageFee =0;
            $totalVolume = 0;
            $overweightSurcharge = 0;//超重附加费
            foreach ($products as $product){
                $delivery_type = $product['delivery_type'];
                session()->set('delivery_type', $delivery_type);
                if($product['delivery_type'] !=2) {
                    //非云送仓业务,上门取货的运费仅为打包费
                    $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
                    if ($isCollectionFromDomicile) {
                        $freightAmount += $product['package_fee_per'] * $product['quantity'];
                    } else {
                        $freightAmount += ($product['freight_per'] + $product['package_fee_per']) * $product['quantity'];
                    }
                }else{
                    $freightAndPackageFeeArr = $this->freight->getFreightAndPackageFeeByProducts(array($product['product_id']));
                    if($product['combo'] == 1){
                        //combo产品运费展示
                        foreach ($freightAndPackageFeeArr[$product['product_id']]  as $set_product_id => $freightAndPackage){
                            $shippingRate = $freightAndPackage['freight_rate'];

                            $product_model = $this->model_common_product;
                            $setProductInfo = $product_model->getComboProductBySetProductId($product['product_id'],$set_product_id);
                            $setProductQty = $setProductInfo[0]['qty']*$product['quantity'];
                            $totalVolume += $freightAndPackage['volume_inch']*$setProductQty;
                            $totalPackageFee += $freightAndPackage['package_fee']*$setProductQty;
                            $overweightSurcharge += ($freightAndPackage['overweight_surcharge'] ?? 0) * $product['quantity'];
                        }
                    }else{
                        $shippingRate = $freightAndPackageFeeArr[$product['product_id']]['freight_rate'];
                        $totalVolume +=$freightAndPackageFeeArr[$product['product_id']]['volume_inch']*$product['quantity'];
                        $totalPackageFee += $freightAndPackageFeeArr[$product['product_id']]['package_fee']*$product['quantity'];
                        $overweightSurcharge += ($freightAndPackageFeeArr[$product['product_id']]['overweight_surcharge'] ?? 0) * $product['quantity'];
                    }
                }
            }
            if(!empty($delivery_type) && in_array($delivery_type,[-1,2])) {
                $deliveryTypes = array_column($products, 'delivery_type');
                $countProducts = in_array(2, $deliveryTypes) ? 1 : 0;
                if ($countProducts > 0) {
                    //云送仓体积不足，按规定体积计算
                    //1363 加上超重附加费
                    $freightAmount += (double)($totalVolume * $shippingRate) + $totalPackageFee + $overweightSurcharge;
                }
            }
            $total['total'] = $total['total'] + $freightAmount;
            $total['totals'][] = array(
                'code'       => 'freight',
                'title'      => $this->language->get('text_freight'),
                'value'      => $freightAmount,
                'sort_order' => $this->config->get('total_freight_sort_order')
            );
        }
    }

    public function getTotalByProducts($total, $products = [], $params = [])
    {
        $this->getTotalByCartId(...func_get_args());
    }

    public function getTotalByOrderId($total, $orderId)
    {
        $this->load->language('extension/total/freight');

        $freightAmount = $this->orm->table('oc_order_total')
            ->where([
                'order_id'  => $orderId,
                'code'      => 'freight'
            ])
            ->value('value');

        $total['totals'][] = array(
            'code'       => 'freight',
            'title'      => $this->language->get('text_freight'),
            'value'      => $freightAmount,
            'sort_order' => $this->config->get('total_freight_sort_order')
        );
    }

    public function getDefaultTotal($total)
    {
        $this->load->language('extension/total/freight');
        if ($this->customer->isLogged()) {

            $total['total'] = $total['total'] + 0;
            $total['totals'][] = array(
                'code'       => 'freight',
                'title'      => $this->language->get('text_freight'),
                'value'      => 0,
                'sort_order' => $this->config->get('total_freight_sort_order')
            );
        }
    }

}
