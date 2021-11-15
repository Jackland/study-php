<?php
/**
 * Class ModelCustomerPartnerBuyerToSeller
 */
class ModelCustomerPartnerBuyerToSeller extends Model
{
    /**
     * @var string
     */
    public $table;

    /**
     * ModelBuyerToSeller constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->table = DB_PREFIX . 'buyer_to_seller';
    }

    /**
     * 判断 Buyer 是否和 Seller 建立联系
     *
     * @param int $buyerID
     * @param int $sellerID
     * @return mixed
     */
    public function checkShowPrice($buyerID, $sellerID)
    {
        return $this->orm::table($this->table)
            ->where([
                ['seller_id', '=', $sellerID],
                ['buyer_id', '=', $buyerID],
                ['buy_status', '=', 1],
                ['buyer_control_status', '=', 1],
                ['seller_control_status', '=', 1],
            ])
            ->count("*");
    }

    /**
     * 判断 Buyer 是否和至少一个 Seller 建立联系
     *
     * @param int $buyerID
     * @return int
     */
    public function checkConnection($buyerID)
    {
        return $this->orm::table($this->table . ' as b2s')
            ->join(DB_PREFIX . 'customerpartner_to_customer as c2c', 'c2c.customer_id', '=', 'b2s.seller_id')
            ->where([
                ['b2s.buyer_id', '=', $buyerID],
                ['b2s.buy_status', '=', 1],
                ['b2s.buyer_control_status', '=', 1],
                ['b2s.seller_control_status', '=', 1],
            ])
            ->count('*');
    }

    /**
     * 获取该buyer享受的折扣
     *
     * @param int $buyer_id
     * @param int $product_id
     * @return int|float
     */
    public function getBuyerDiscount($buyer_id, $product_id)
    {
        $obj = $this->orm::table($this->table . ' as b2s')
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', 'c2p.customer_id', '=', 'b2s.seller_id')
            ->select(['c2p.customer_id as seller_id', 'b2s.discount'])
            ->where([
                ['b2s.buyer_id', $buyer_id],
                ['c2p.product_id', $product_id]
            ])
            ->first();
        return !isset($obj->discount) ? 1 : (float)$obj->discount;
    }
}
