<?php

namespace App\Services\Quote;

use App\Enums\Product\ProductTransactionType;
use ModelAccountProductQuoteswkproductquotes;
use ModelCheckoutPreOrder;

class QuoteService
{
    /**
     * 添加订单内产品使用议价协议购买的数据
     *
     * @param array $product
     * @param int $customerId
     * @param int $orderId
     * @param int $countryId
     * @param bool $isEuropean
     * @throws \Exception
     */
    public function addOrderQuote($product, $customerId, $orderId, $countryId, $isEuropean)
    {
        $currency =  session('currency');
        /** @var ModelAccountProductQuoteswkproductquotes $modelAccountProductQuotesWkProductQuotes */
        $modelAccountProductQuotesWkProductQuotes = load()->model('account/product_quotes/wk_product_quotes');
        /** @var ModelCheckoutPreOrder $modelPreOrder */
        $modelPreOrder = load()->model('checkout/pre_order');
        foreach ($product as $item) {
            if ($item['transaction_type'] == ProductTransactionType::SPOT) {
                $item = $modelPreOrder->calculateSpotDiscountAmount($item, $countryId, $isEuropean, $currency);
                $amount_data = [
                    'isEuropean'             => $isEuropean,
                    'amount_price_per'       => $item['quote_discount_amount_per'],// 每件商品的折扣
                    'amount_total' => bcmul(($item['quote_discount_amount_per'] + $item['quote_discount_service_per']), $item['quantity'], 2),   //议价总折扣
                    'amount_service_fee_per' => $item['quote_discount_service_per'],
                    'amount_service_fee'     => bcmul($item['quote_discount_service_per'], $item['quantity'], 2) // // 如果为欧洲地区，则为商品服务费的折扣金额；如果非欧洲地区该值为0
                ];
                $modelAccountProductQuotesWkProductQuotes->addOrderQuote(
                    $orderId,
                    $item['agreement_id'],
                    $customerId,
                    $amount_data,
                    $item['product_id']
                );
            }
        }
    }
}
