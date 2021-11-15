<?php

use App\Enums\Warehouse\ReceiptOrderStatus;

/**
 * Class ModelFuturesTemplate
 */
class ModelFuturesTemplate extends Model
{


    /*
     * 获取商品对应的期货模板信息
     * */
    public function getFuturesTemplateForProduct($productId)
    {
        $select = [
            't.buyer_payment_ratio',
            't.seller_payment_ratio',
            't.min_expected_storage_days',
            't.max_expected_storage_days',
            'ti.id as item_id',
            'ti.min_num',
            'ti.max_num',
            'ti.exclusive_price',
            'ti.is_default'
        ];
        $info = $this->orm->table('oc_futures_margin_template as t')
            ->leftJoin('oc_futures_margin_template_item as ti', 't.id', 'ti.template_id')
            ->where(['status'=>1, 'is_deleted'=>0, 'product_id'=>$productId])
            ->select($select)
            ->orderBy('ti.min_num')
            ->get();
        $data = [];
        foreach ($info as $i => $item){

            if (0 == $i){
                $data = [
                    'buyer_payment_ratio'       => $item->buyer_payment_ratio,
                    'seller_payment_ratio'      => $item->seller_payment_ratio,
                    'min_expected_storage_days' => $item->min_expected_storage_days,
                    'max_expected_storage_days' => $item->max_expected_storage_days,
                    'default_template'          => $item->item_id
                ];
            }elseif ($item->is_default){
                $data['default_template'] = $item->item_id;
            }

            $data['template_list'][$item->item_id] = [
                'min_num'   => $item->min_num,
                'max_num'   => $item->max_num,
                'exclusive_price'   => $item->exclusive_price
            ];
        }

        return $data;
    }


    /*
     * 预计入库数量
     * */
    public function getExpectedQty($productId)
    {
        $isCombo = $this->isCombo($productId);
        if ($isCombo){
            $childProduct = $this->orm->table('tb_sys_product_set_info')
                ->select([
                    'product_id',
                    'set_product_id',
                    'qty'
                ])
                ->where('product_id', $productId)
                ->whereNotNull('set_product_id')
                ->get();
            $qty = [];
            $expectedQty = 0;
            foreach ($childProduct as $k=>$v)
            {
                //计算每一批可组成的combo数量
                $one = $this->orm->table('tb_sys_receipts_order_detail as od')
                    ->leftJoin('tb_sys_receipts_order as o', 'od.receive_order_id', 'o.receive_order_id')
                    ->where('o.status', ReceiptOrderStatus::TO_BE_RECEIVED)
                    ->where('od.product_id', $v->set_product_id)
                    ->select('od.expected_qty','od.product_id','od.receive_order_id')
                    ->get();
                foreach ($one as $kk=>$vv){
                    $qty[$vv->receive_order_id][] = intval($vv->expected_qty/$v->qty);
                }
            }
            foreach ($qty as $roId => $q){
                $expectedQty += min($q);//合计每一批combo数量
            }

        }else{
            $expectedQty = $this->orm->table('tb_sys_receipts_order_detail as od')
                ->leftJoin('tb_sys_receipts_order as o', 'od.receive_order_id', 'o.receive_order_id')
                ->where('o.status', ReceiptOrderStatus::TO_BE_RECEIVED)
                ->where('od.product_id', $productId)
                ->sum('od.expected_qty');
        }

        return $expectedQty;
    }

    /*
     * 获取货值
     * */
    public function getProductPrice($productId)
    {
        return $this->orm->table('oc_product')
            ->where('product_id', $productId)
            ->value('price');
    }

    /*
     * 是否是combo品
     * */
    public function isCombo($productId)
    {
        return $this->orm->table('oc_product')
            ->where('product_id', $productId)
            ->value('combo_flag');
    }


    /*
     * 判断该商品可否进行期货交易
     * */
    public function isFutures($productId)
    {
        return $this->orm->table('oc_futures_margin_template')
            ->where([
                'product_id'    => $productId,
                'status'        => 1,
                'is_deleted'    => 0,
            ])
            ->exists();
    }
}
