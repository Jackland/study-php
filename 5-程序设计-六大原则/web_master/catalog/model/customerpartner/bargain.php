<?php

/**
 * 议价部分
 * Class ModelCustomerpartnerBargain
 */
class ModelCustomerpartnerBargain extends Model
{
    /**
     * 检查产品是否参与议价
     * @param int $product_id
     * @return bool
     */
    public function checkProductsIsBargain(int $product_id): bool
    {
        $res = $this->orm->table(DB_PREFIX . 'customerpartner_to_product as ctp')
            ->select(['pq.*', 'pq.seller_id'])
            ->join(DB_PREFIX . 'wk_pro_quote as pq', ['ctp.customer_id' => 'pq.seller_id'])
            ->where(['ctp.product_id' => $product_id])
            ->first();
        if (!$res) {
            return false;
        }
        $res = get_object_vars($res);
        // 全部参与议价
        if ($res['status'] == 1) {
            return true;
        }
        $product_ids = $this->getBargainProductIds($res['seller_id']);

        return in_array($product_id, $product_ids);
    }

    /**
     * 获取议价商品id
     *
     * @param int $seller_id
     * @return array
     * user：wangjinxin
     * date：2019/11/6 10:57
     */
    public function getBargainProductIds(int $seller_id): array
    {
        static $sellerProducts = [];
        if (isset($sellerProducts[$seller_id])) {
            return $sellerProducts[$seller_id];
        }
        $ret = $this->orm->table(DB_PREFIX . 'wk_pro_quote_list')
            ->where('seller_id', $seller_id)
            ->pluck('product_id')
            ->toArray();
        $sellerProducts[$seller_id] = $ret;

        return $ret;
    }
}