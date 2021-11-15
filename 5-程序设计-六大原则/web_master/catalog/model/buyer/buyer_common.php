<?php
/**
 * Class ModelBuyerBuyerCommon
 * Created by IntelliJ IDEA.
 * User: xxl
 * Date: 2019/7/19
 * Time: 17:32
 * @property ModelSettingExtension $model_setting_extension
 */
class ModelBuyerBuyerCommon extends Model{

    /**
     *  获取订单成交单个产品的实际价格（包含折扣,精细化管理,议价）
     * @param int $order_id
     * @param int|null $product_id
     * @return array
     */
    public function getOrderPrice($order_id,$product_id = null){
        if($product_id== null){
            $whereFiled = [['oop.order_id','=',$order_id]];
        }else{
            $whereFiled = [['oop.order_id','=',$order_id],['oop.product_id','=',$product_id]];
        }
        $results = $this->orm->table(DB_PREFIX.'order_product as oop')
            ->leftJoin(DB_PREFIX.'product_quote as opd', [['oop.order_id','=','opd.order_id'],['oop.product_id','=','opd.product_id']])
            ->where($whereFiled)
            ->select('oop.price as price','oop.service_fee_per as service_fee_per','oop.poundage','opd.price as quotePrice','oop.quantity','oop.product_id','oop.freight_per','oop.package_fee','oop.freight_difference_per')
            ->get();
        $resultArray = obj2array($results);
        $data = [];
        foreach ($resultArray as $result){
            if($result['quotePrice'] == null){
                $actual_price = $result['price'];
            }else{
                $actual_price = $result['quotePrice'];
            }
            $service_fee = $result['service_fee_per'];
            $poundage = $result['poundage']/$result['quantity'];
            $data[$result['product_id']] = array(
                'actual_price' =>$actual_price,
                'service_fee' =>$service_fee,
                'poundage' =>$poundage,
                'freight_per' =>$result['freight_per'],
                'package_fee' =>$result['package_fee'],
                'quote' => $result['quotePrice'] == null?false:true,
                'freight_difference_per' => $result['freight_difference_per']
            );
        }
        return $data;
    }

    /**
     * 计算 购物车里面的总的费用
     * @return mixed
     */
    public function getCartTotal()
    {
        $this->load->model('setting/extension');

        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        // Because __call can not keep var references so we put them into an array.
        $total_data = array(
            'totals' => &$totals,
            'taxes' => &$taxes,
            'total' => &$total
        );

        $sort_order = array();
        $results = $this->model_setting_extension->getExtensions('total');

        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }
        array_multisort($sort_order, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/total/' . $result['code']);
                // We have to put the totals in an array so that they pass by reference.
                $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
            }
        }

        return $total_data['total'];
    }

    /**
     * 获取产品的combo信息
     * @param int $product_id
     * @return array
     */
    //N-475
    public function  getComboInfoByProductId($product_id,$qty){
        $result = $this->orm->table('tb_sys_product_set_info as psi')
            ->whereRaw('psi.product_id='.$product_id)
            ->selectRaw('psi.qty*'.$qty.' as qty,psi.set_product_id')
            ->get();
        return obj2array($result);
    }

    public function getBatchByProductId($product_id){
        $result =$this->orm->table('tb_sys_batch as tsb')
            ->whereRaw('tsb.product_id ='.$product_id)
            ->selectRaw('sum(tsb.onhand_qty) as qty')
            ->groupBy('tsb.product_id')
            ->first();
        return obj2array($result);

    }

    public function getMarginProduct($margin_id){
        $result = $this->orm->table('tb_sys_margin_agreement as sma')
            ->leftJoin('oc_product as op','op.product_id','=','sma.product_id')
            ->where('sma.id','=',$margin_id)
            ->select('sma.product_id','sma.num','op.combo_flag')
            ->first();
        return obj2array($result);
    }

    public function getLockQtyByProductId($product_id){
        $result =$this->orm->table('oc_product_lock as opl')
            ->whereRaw('opl.product_id ='.$product_id)
            ->selectRaw('sum(opl.qty) as lock_qty')
            ->groupBy('opl.product_id')
            ->first();
        return obj2array($result);
    }
}
