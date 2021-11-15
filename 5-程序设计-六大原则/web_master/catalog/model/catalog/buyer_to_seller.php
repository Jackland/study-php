<?php

use App\Models\Buyer\BuyerToSeller;

class ModelCatalogBuyertoseller extends Model
{
    public function getTest()
    {
        echo 1;
    }

    /**
     * [getIsConnected description] 确认buyer和seller 联系
     * @param int $buyer_id
     * @param int $seller_id
     * @return float|null
     */
    public function getIsConnected($buyer_id, $seller_id)
    {
        static $buyerSellerMap = [];
        $key = $buyer_id . '_' . $seller_id;
        if (isset($buyerSellerMap[$key])) {
            return $buyerSellerMap[$key];
        }
        $buyerSellerMap[$key] = 0;
        $data = BuyerToSeller::query()->where(['buyer_id' => $buyer_id, 'seller_id' => $seller_id])->first();
        if (
            $data
            && $data->buy_status == 1
            && $data->buyer_control_status == 1
            && $data->seller_control_status == 1
        ) {
            $buyerSellerMap[$key] = $data->discount;
        }

        return $buyerSellerMap[$key];
    }
}
