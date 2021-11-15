<?php

use App\Enums\Product\ProductTransactionType;
use App\Enums\Spot\SpotProductQuoteStatus;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Seller\SellerProductRatioRepository;
use kriss\bcmath\BCS;

/**
 * @property ModelAccountProductQuoteswkproductquotes $model_account_product_quotes_wk_product_quotes
 *
 * Class ModelExtensionTotalwkproquote
 */
class ModelExtensionTotalwkproquote extends Model
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

    private function spot($products, $total)
    {
        $this->load->language('account/product_quotes/wk_product_quotes');
        $this->load->model('account/product_quotes/wk_product_quotes');

        /**
         * @var float $amount 欧洲地区：商品展示价格的总折扣；非欧洲地区：商品价格的总折扣
         * @var float $amount_service_fee 欧洲地区：商品的服务费的总折扣；非欧洲地区：无服务费，该值为0
         */
        $amount = 0;
        $amount_service_fee = 0;

        foreach ($products as $product) {
            if ($product['type_id'] != ProductTransactionType::SPOT) {
                continue;
            }

            $productQuoteDetail = $this->model_account_product_quotes_wk_product_quotes->getProductQuoteDetail($product['agreement_id']);
            if (!$productQuoteDetail) {
                continue;
            }
            /**
             * @var float $amount_per 每个商品的减去的折扣数
             * @var float $amount_price_per 如果为欧洲地区，则为商品展示价格的折扣数；如果非欧洲地区 等于 $amount_per
             * @var float $amount_service_fee_per 如果为欧洲地区，则为商品服务费的折扣数；如果非欧洲地区该值为0
             */
            $amount_per = bcsub(round($product['normal_price'], $this->precision), $productQuoteDetail['price'], $this->precision);
            // 如果为欧洲 需要把 议价折扣 按照服务费比例拆分
            if ($this->customer->isEurope()) {
                $amount_price_per = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($product['seller_id'], $amount_per, customer()->getCountryId());
                $amount_service_fee_per = bcsub($amount_per, $amount_price_per, $this->precision);

                $service_fee_per = $product['service_fee_per'] - $amount_service_fee_per;//欧洲议价后的货值拆分的服务费
                $product_price_per = $productQuoteDetail['price'] - $service_fee_per;

                #31737 下单针对于非复杂交易的价格需要判断是否需免税 走议价协议时 unit price未计算免税价
                $amount_price_per = BCS::create($product['price'], ['scale' => 2])->sub($service_fee_per, $product_price_per, $amount_service_fee_per)->getResult();

                $amount = bcadd($amount, bcmul($amount_price_per, $product['quantity'], $this->precision), $this->precision);
                $amount_service_fee = bcadd($amount_service_fee, bcmul($amount_service_fee_per, $product['quantity'], $this->precision), $this->precision);
            } else {
                $amount = bcadd($amount, bcmul($amount_per, $product['quantity'], $this->precision), $this->precision);
            }
        }

        if ($amount || $amount_service_fee) {
            if ($amount + $amount_service_fee > $total) {
                $total['total'] = 0;
            } else {
                $total['total'] -= $amount;
                $total['total'] -= $amount_service_fee;
            }

            if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
                $total['totals'][] = array(
                    'code' => 'wk_pro_quote',
                    'title' => $this->language->get('text_product_quote_amount'),
                    'value' => -$amount,
                    'sort_order' => $this->config->get('total_wk_pro_quote_sort_order')
                );
                $total['totals'][] = array(
                    'code' => 'wk_pro_quote_service_fee',
                    'title' => $this->language->get('text_product_quote_service_fee'),
                    'value' => -$amount_service_fee,
                    'sort_order' => $this->config->get('total_wk_pro_quote_sort_order')
                );
            } else {
                $total['totals'][] = array(
                    'code' => 'wk_pro_quote',
                    'title' => $this->language->get('text_product_quote'),
                    'value' => -$amount,
                    'sort_order' => $this->config->get('total_wk_pro_quote_sort_order')
                );
            }
        }
    }

    public function getTotal($total)
    {
        $deliveryType = $this->session->has('delivery_type') ? $this->session->get('delivery_type') : -1;
        $products = $this->cart->getProducts(null, $deliveryType);

        $this->spot($products, $total);
    }

    public function getTotalByCartId($total, $products = [], $params = [])
    {
        $this->spot($products, $total);
    }

    public function getTotalByProducts($total, $products = [], $params = [])
    {
        $formatProducts = array_map(function ($product) {
            $product['type_id'] = $product['transaction_type'];
            $product['price'] = $product['current_price'];
            return $product;
        }, $products);

        $this->spot($formatProducts, $total);
    }

    public function getTotalByOrderId($total, $orderId)
    {
        $this->load->language('account/product_quotes/wk_product_quotes');
        $amount = $this->orm->table('oc_order_total')
            ->where([
                'order_id' => $orderId,
                'code' => 'wk_pro_quote'
            ])
            ->value('value');
        if ($amount) {

            $total['totals'][] = array(
                'code' => 'wk_pro_quote',
                'title' => $this->language->get('text_product_quote'),
                'value' => $amount,
                'sort_order' => $this->config->get('total_wk_pro_quote_sort_order')
            );

            $amountServiceFee = $this->orm->table('oc_order_total')
                ->where([
                    'order_id' => $orderId,
                    'code' => 'wk_pro_quote_service_fee'
                ])
                ->value('value');
            if ($amountServiceFee) {
                $total['totals'][] = array(
                    'code' => 'wk_pro_quote_service_fee',
                    'title' => $this->language->get('text_product_quote_service_fee'),
                    'value' => $amountServiceFee,
                    'sort_order' => $this->config->get('total_wk_pro_quote_sort_order')
                );
            }

        }
    }

    public function confirm($order_info, $order_total)
    {
        $products = $this->orm->table(DB_PREFIX . 'order_product as oc')
            ->join(DB_PREFIX . 'product_quote as pq', 'oc.agreement_id', '=', 'pq.id')
            ->join(DB_PREFIX . 'product as p', 'oc.product_id', '=', 'p.product_id')
            ->where('oc.order_id', $order_total['order_id'])
            ->where('oc.type_id', 4)
            ->selectRaw('oc.order_id, pq.quantity, p.price as base_price, pq.price, pq.id')
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            $amount = bcmul(bcsub(round($product->base_price, $this->precision), $product->price, $this->precision), $product->quantity, $this->precision);
            $this->db->query("UPDATE `" . DB_PREFIX . "product_quote` SET status = '3', amount = '" . $amount . "', order_id = '" . $order_total['order_id'] . "', date_used = NOW() WHERE id = '" . $product->id . "' AND customer_id = '" . $this->customer->getId() . "'");
        }
    }

    public function unconfirm($order_id)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "product_quote` SET status = '2' WHERE order_id = '" . (int)$order_id . "'");
    }

}
