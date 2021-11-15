<?php

class ModelFuturesProduct extends Model
{
    /**
     * @param int $customerId
     * @param string $codeOrMpnFilter
     * @param int $limit
     * @return array
     */
    public function validProductsByCustomerIdAndCodeMpn(int $customerId, string $codeOrMpnFilter = '', int $limit = 5)
    {
        $result = $this->orm->table(DB_PREFIX . 'product as p')
            ->select([
                'p.product_id', 'p.mpn', 'p.sku', 'pd.name', 'p.image',
            ])
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', 'c2p.product_id',  '=', 'p.product_id')
            ->leftJoin(DB_PREFIX . 'product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'c2p.customer_id' => $customerId,
                'p.product_type' => 0,
            ])
            ->when(!empty($codeOrMpnFilter), function ($q) use ($codeOrMpnFilter) {
                    $q->where(function ($q) use ($codeOrMpnFilter) {
                        $filter = htmlspecialchars(trim($codeOrMpnFilter));
                        $q->orWhere('p.mpn', 'like', "%{$filter}%")->orWhere('p.sku', 'like', "%{$filter}%");
                    });
                }
            )
            ->orderBy('p.product_id', 'desc')
            ->when($limit > 0, function ($q) use ($limit) {
                $q->limit($limit);
            })
            ->get();

        return obj2array($result);
    }

    /**
     * @param int $customerId
     * @param int $productId
     * @return array
     */
    public function productById(int $customerId, int $productId)
    {
        $result = $this->orm->table(DB_PREFIX . 'product as p')
            ->select(['p.*', 'pd.name'])
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', 'c2p.product_id',  '=', 'p.product_id')
            ->leftJoin(DB_PREFIX . 'product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->where([
                'p.product_id' =>$productId,
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'c2p.customer_id' => $customerId,
                'p.product_type' => 0,
            ])
            ->first();

        return obj2array($result);
    }

    /**
     * @param int $productId
     * @param string $historyTime
     * @return array
     */
    public function historyOpenPricesByProductId(int $productId, string $historyTime = '')
    {
        $sql = "SELECT date(o.date_added) as format_date, MIN(p.price) as min_price FROM oc_order_product AS p LEFT JOIN oc_product_quote AS q ON p.order_id = q.order_id and p.product_id = q.product_id join oc_order	as o on o.order_id = p.order_id";
        if (!empty($historyTime)) {
            $sql .= " and o.date_added > '{$historyTime}' ";
        }
        $sql .= "WHERE p.product_id = {$productId} AND p.type_id = 0 AND q.id IS NULL GROUP BY date(o.date_added) ORDER BY o.date_added asc";

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * @param int $productId
     * @param string $date
     * @param $price
     * @return mixed|null
     */
    public function orderCreatedAtByProductIdDatePrice(int $productId, string $date, $price)
    {
        return $this->orm->table(DB_PREFIX . 'order as o')
            ->join(DB_PREFIX . 'order_product as p', 'o.order_id', '=', 'p.order_id')
            ->where('p.product_id', $productId)
            ->whereRaw("date(o.date_added) = ?", $date)
            ->where('p.price', $price)
            ->value('o.date_added');
    }
}
