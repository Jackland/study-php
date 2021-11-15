<?php


/**
 * 只用来获取保证金订单相关数据
 * Class ModelAccountCustomerpartnerMarginOrder
 */
class ModelAccountCustomerpartnerMarginOrder extends Model
{
    /**
     * 获取orderid 里参与保证金业务的product_id
     * 如果没有参与保证金业务的 返回空数组
     * @param int $order_id 订单id
     * @return array
     * user：wangjinxin
     * date：2020/3/19 16:11
     */
    public function getMarginProducts(int $order_id): array
    {
        return array_keys($this->getMarginAgreeInfo($order_id));
    }

    /**
     * @param int $order_id
     * user：wangjinxin
     * date：2020/3/19 16:56
     * @return array
     */
    public function getMarginAgreeInfo(int $order_id): array
    {
        // 头款产品销售id获取
        $queryHead = $this->orm
            ->table('tb_sys_margin_process')
            ->select(['advance_product_id as product_id', 'margin_id as agreement_id'])
            ->where('advance_order_id', $order_id);
        // 尾款产品销售product_id获取
        $queryTail = $this->orm
            ->table('tb_sys_margin_order_relation as mor')
            ->leftJoin('tb_sys_margin_process as mp', 'mp.id', '=', 'mor.margin_process_id')
            ->select(['mp.rest_product_id as product_id', 'mp.margin_id as agreement_id'])
            ->where('mor.rest_order_id', $order_id)
            ->whereNotNull('mp.rest_product_id');
        return $queryHead->union($queryTail)
            ->get()
            ->keyBy('product_id')
            ->map(function ($item) {
                return $item->agreement_id;
            })
            ->toArray();
    }

    /**
     * 获取协议信息
     * @param int $id
     * user：wangjinxin
     * date：2020/3/23 11:25
     * @return array|null
     */
    public function getAgreementInfo(int $id)
    {
        $ret = $this->orm->table('tb_sys_margin_agreement as sma')
            ->leftJoin('tb_sys_margin_process as smp', 'smp.margin_id', '=', 'sma.id')
            ->select(['sma.*', 'smp.advance_product_id', 'smp.advance_order_id', 'smp.rest_product_id', 'smp.process_status'])
            ->where('sma.id', $id)
            ->first();
        return $ret ? (array)$ret : null;
    }

    /**
     * 根据订单 和 订单的product id获取协议信息  如果没有协议 返回null
     * @param int $order_id 订单id
     * @param int $product_id 订单商品id
     * user：wangjinxin
     * date：2020/3/24 17:46
     * @return array|null
     */
    public function getAgreementInfoByOrderProduct(int $order_id, int $product_id)
    {
        $agree_info = $this->getMarginAgreeInfo($order_id);
        if (isset($agree_info[$product_id])) {
            return $this->getAgreementInfo($agree_info[$product_id]);
        }
        return null;
    }
}