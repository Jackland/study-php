<?php

use App\Enums\Cart\CartAddCartType;
use App\Enums\Common\CountryEnum;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Enums\Spot\SpotProductQuoteStatus;
use App\Helper\CurrencyHelper;
use App\Helper\MoneyHelper;
use App\Models\CWF\OrderCloudLogistics;
use App\Models\Futures\FuturesMarginAgreement;
use App\Repositories\Marketing\CampaignRepository;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use App\Repositories\Product\ProductPriceRepository;
use App\Repositories\Seller\SellerProductRatioRepository;
use App\Services\Marketing\CampaignService;
use kriss\bcmath\BCS;

/**
 * Class ModelAccountCartCart
 * Created by IntelliJ IDEA.
 * User: xxl
 * Date: 2019/12/14
 * Time: 13:34
 * @property ModelToolImage $model_tool_image
 * @property ModelToolUpload $model_tool_upload
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountProductQuotesMargin $model_account_product_quotes_margin
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelExtensionModulePrice $model_extension_module_price
 * @property ModelExtensionModuleCartHome model_extension_module_cart_home
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelSettingExtension $model_setting_extension
 * @property ModelAccountProductQuoteswkproductquotes $model_account_product_quotes_wk_product_quotes
 */

class ModelAccountCartCart extends Model {

    /**
     * 购物车逻辑统一展示
     * @param int $delivery_type 送货类型,默认为-1,drop shipping 0,home pick 1,cwf 2
     * @param array $cartIdArr
     * @return mixed
     * @throws Exception
     * @author xxl
     */
    public function cartShow($delivery_type = -1,$cartIdArr = [])
    {
        $this->load->model('tool/image');
        $imageModel = $this->model_tool_image;
        $this->load->model('tool/upload');
        $uploadModel = $this->model_tool_upload;
        $this->load->model('account/product_quotes/margin');
        $this->load->model('futures/agreement');
        $this->load->model('catalog/product');
        $this->load->model('extension/module/price');
        $this->load->model('account/customerpartner');
        $this->load->language('checkout/cart');
        $this->load->model('account/product_quotes/wk_product_quotes');

        //判断是否为欧洲
        if (!empty($this->customer->getCountryId()) && $this->country->isEuropeCountry($this->customer->getCountryId())) {
            $data['isEuropean'] = true;
        } else {
            $data['isEuropean'] = false;
        }

        //是否启用议价
        $data['enableQuote'] = false;
        if (!empty($this->customer->getCountryId()) && in_array($this->customer->getCountryId(), QUOTE_ENABLE_COUNTRY)) {
            $data['enableQuote'] = true;
        }
        $margin_expire_type_error = true;
        $products = $this->cart->getProducts(null,$delivery_type,$cartIdArr);
        $productIdArr = array_column($products, 'product_id');
        $productsCampaignsMap = app(CampaignRepository::class)->getProductsCampaignsMap($productIdArr); // 获取某些产品(包含定金产品)参加的满减或满送活动
        $priceChangeInfo = $this->priceChangeInfo($productIdArr);//价格变动信息
        $fineChangeInfo = $this->finePriceChangeInfo($productIdArr);//精细化价格变动信息
        $wkProQuoteDetailsByProductIds = $this->wkProQuoteDetailsByProductIds($productIdArr);//某个产品的阶梯价展示
        $freightAndPackageFeeArr = $this->freight->getFreightAndPackageFeeByProducts($productIdArr);
        //购物车产品的展示
        foreach ($products as $product) {
            $campaigns = [];
            if ($product['minimum'] > $product['quantity']) {
                $data['error_warning'] = sprintf($this->language->get('error_minimum'), $product['name'], $product['minimum']);
            }
            $image = '';
            if ($product['image']) {
                $image = $imageModel->resize($product['image'], configDB('theme_' . configDB('config_theme') . '_image_cart_width'), configDB('theme_' . configDB('config_theme') . '_image_cart_height'));
                $popupImage = $imageModel->resize($product['image'], configDB('theme_' . configDB('config_theme') . '_image_popup_width'), configDB('theme_' . configDB('config_theme') . '_image_popup_height'));
            }
            if(!$image){
                $image = $imageModel->resize('no_image.png', configDB('theme_' . configDB('config_theme') . '_image_cart_width'), configDB('theme_' . configDB('config_theme') . '_image_cart_height'));
            }

            $can_buy = $product['buyer_flag'] && $product['product_status'] && $product['store_status'] && !$product['fine_cannot_buy'] ? 1:0;
            if (!$can_buy) {
                goto end;
            }

            //产品option暂时未使用
            $option_data = array();
            foreach ($product['option'] as $option) {
                if ($option['type'] != 'file') {
                    $value = $option['value'];
                } else {
                    $upload_info = $uploadModel->getUploadByCode($option['value']);

                    if ($upload_info) {
                        $value = $upload_info['name'];
                    } else {
                        $value = '';
                    }
                }

                $option_data[] = array(
                    'name' => $option['name'],
                    'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value),
                    'type' => $option['type']
                );
            }
            /*
             * 购物车具体产品价格价格展示
             * 议价对货值
             * 进行议价,$product['price']进行议价换算
             * 1.美国(两位小数)/日本(没有小数)
             * 1-1：美国一件代发,上门取货价+运费 (oc_product.price+oc_product.freight+oc_product.package_fee),$product['price']+$product['freight_per']+$product['package_fee']
             * 1-2：美国上门取货，上门取货价 (oc_product.price),$product['price']+$product['package_fee']
             * 2.欧洲(服务费的拆分针对于货值)
             * 2-1:欧洲一件代发价,拆分服务费,已上门取货价+运费(oc_product.price),$product['price']进行拆分
             * 2-2：欧洲上门取货价,拆分服务费,上门取货价 (oc_product.price),$product['price']进行拆分
             */
            $showTieredPricing = false;
            $tieredPrices = [];
            $serviceTieredPrices = [];
            $quoteFlag = false;
            $futuresFlag = false;
            if ($this->customer->isLogged() || !configDB('config_customer_price')) {

                if (isset($product['commission_amount']) && $product['commission_amount']) {
                    $product['price'] += $product['commission_amount'];
                }

                $unit_price = $this->tax->calculate(isset($product['price'])?$product['price']:0, $product['tax_class_id'], configDB('config_tax'));
                $origin_price = $this->currency->formatCurrencyPrice($unit_price, $this->session->data['currency']);//原始货值价格
                $quote_amount = 0;  //议价总折扣数
                $quote_amount_per = 0;    //每件商品的折扣数
                //议价针对于货值,是否议价存在session中

                if ($product['type_id'] == ProductTransactionType::SPOT) {
                    $productQuoteDetail = $this->model_account_product_quotes_wk_product_quotes->getProductQuoteDetail($product['agreement_id']);
                    if ($productQuoteDetail) {
                        /**
                         * @var float $quote_per 每个商品的折扣金额
                         * @var float $quote_amount_per 如果为欧洲地区，则为商品折扣展示金额(去除服务费的折扣金额)；如果非欧洲地区 等于 $quote_per
                         * @var float $quote_service_fee_per 如果为欧洲地区，则为商品服务费的折扣数；如果非欧洲地区该值为0
                         */
//                        $productQuoteDetail['price'] = app(MarketingDiscountRepository::class)->getPriceAfterDiscount($productQuoteDetail['customer_id'], $product['product_id'], $productQuoteDetail['price']);
                        $quote_per = bcsub(round($product['normal_price'], $this->customer->isJapan() ? 0 : 2), $productQuoteDetail['price'], $this->customer->isJapan() ? 0 : 2);
                        $quote_amount_per = $quote_per;
                        $quote_service_fee_per = 0;
                        if ($this->customer->isEurope()) {
                            $quote_amount_per = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($product['seller_id'], $quote_per, $this->customer->getCountryId());
                            $quote_service_fee_per = bcsub($quote_per, $quote_amount_per, 2);
                            $quote_service_fee = $quote_service_fee_per * $product['quantity'];
                            $quoteServiceFee = $this->currency->formatCurrencyPrice(-$quote_service_fee, $this->session->get('currency'));
                            $product['service_fee_per'] = $product['service_fee_per'] - $quote_service_fee_per;//欧洲议价后的货值拆分的服务费
                            $product['product_price_per'] = $productQuoteDetail['price'] - $product['service_fee_per'];

                            #31737 下单针对于非复杂交易的价格需要判断是否需免税 走议价协议时 unit price未计算免税价
                            $quote_amount_per = BCS::create($unit_price, ['scale' => 2])->sub($product['service_fee_per'], $product['product_price_per'], $quote_service_fee_per)->getResult();
                        }
                        $quote_amount = bcmul($quote_amount_per, $product['quantity'], $this->customer->isJapan() ? 0 : 2);
                        $quoteAmount = $this->currency->formatCurrencyPrice(-$quote_amount, $this->session->get('currency'));
                        if (stripos($quoteAmount, '-', 0) === false) {
                            $quoteAmount = '+' . $quoteAmount;
                        }

                        $unit_price = $productQuoteDetail['price'];
                        if ($product['quantity'] == $productQuoteDetail['quantity'] && $productQuoteDetail['status'] != SpotProductQuoteStatus::TIMEOUT) {
                            $quoteFlag = true;
                        }

                        if ($productQuoteDetail['status'] == SpotProductQuoteStatus::TIMEOUT) {
                            $this->updateToNormalCart($product['cart_id']);
                            $product['type_id'] = ProductTransactionType::NORMAL;
                            $product['agreement_id'] = null;
                        }
                    }
                }
                if ($product['type_id'] == ProductTransactionType::FUTURE) {
                    $version = FuturesMarginAgreement::query()->where('id', '=', $product['agreement_id'])->value('version');
                    if ($version == 3) {
                        $futuresFlag = true;
                    }
                }
                if (configDB('module_marketplace_status')) {
                    $checkSellerProduct = $this->model_account_customerpartner->getProductSellerDetails($product['product_id']);
                } else {
                    $checkSellerProduct = [];
                }

                // #3099 获取非协议价格选中的价格（普通，精细化，阶梯价）
                if ($product['type_id'] == ProductTransactionType::NORMAL && isset($wkProQuoteDetailsByProductIds[$product['product_id']])) {
                    $showTieredPricing = true;
                    list($tieredPrices, $serviceTieredPrices) = $this->tieredPrices($wkProQuoteDetailsByProductIds[$product['product_id']], $product['is_delicacy_effected'], $unit_price, $product['quantity'], empty($product['refine_price']) ? $product['defaultPrice'] : $product['refine_price'], $product['add_cart_type'],$product['product_id']);
                }

                $price = $this->currency->formatCurrencyPrice($unit_price, $this->session->data['currency']);
                $quoteAmountPer = $this->currency->formatCurrencyPrice(-$quote_amount_per, $this->session->data['currency']);
                //欧洲展示服务费 xxl
                if($data['isEuropean']) {
                    $service_fee_per = $this->currency->formatCurrencyPrice($product['service_fee_per'], $this->session->data['currency']);
                    $product_price_per = $this->currency->formatCurrencyPrice($product['product_price_per'], $this->session->data['currency']);
                }
                //上门取货的运费仅为打包费
                $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
                if($isCollectionFromDomicile) {
                    $total = $this->currency->formatCurrencyPrice(($unit_price+$product['package_fee_per']) * $product['quantity'], $this->session->data['currency']);
                }else{
                    $total = $this->currency->formatCurrencyPrice(($unit_price + $product['freight_per'] + $product['package_fee_per']) * $product['quantity'], $this->session->data['currency']);
                }
            }else{
                //未登录的价格展示
                $price = false;
                $total = false;
                $origin_price = false;
            }

            //产品的tag标签
            $tag_array = $this->model_catalog_product->getTag($product['product_id']);
            $tags = array();
            if(isset($tag_array)){
                foreach ($tag_array as $tag){
                    if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                        //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                        $tags[] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '"  title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                    }
                }
            }

            $stock = $product['stock'] ? true : !(!configDB('config_stock_checkout') || configDB('config_stock_warning'));
            //是否为保证金头款商品
            $isMarginAdvanceProduct = boolval($product['product_type'] == ProductType::MARGIN_DEPOSIT);

            //是否为期货头款商品 N-294
            $isFuturesAdvanceProduct = boolval($product['product_type'] == ProductType::FUTURE_MARGIN_DEPOSIT);

            if($margin_expire_type_error && empty($product['margin_expire_alert']) && !$stock){
                $margin_expire_type_error = false;
            }

            /*
             * 产品运费的规则
             * 1.上门取货：运费 = 打包费
             * 2.一件代发：运费 = 产品运费+打包费
             */
            if ($this->customer->isCollectionFromDomicile()) {//上门取货
                $freight_per = $product['package_fee_per'];
            } else {
                $freight_per = $product['freight_per']+$product['package_fee_per'];
            }
            $freight_total = $freight_per*$product['quantity'];
            // 获取每个产品的不同交易类型的价格 各种交易形式的产品数量
            $transaction_info = $this->model_extension_module_price->getProductPriceInfo($product['product_id'], $this->customer->getId(), [], false, true, ['qty' => $product['quantity'], 'use_wk_pro_quote_price' => $product['use_wk_pro_quote_price']]);

            //普通交易形式
            $currentDiscount = null;
            if ($product['type_id'] == ProductTransactionType::NORMAL) {
                $qtyTypeStr = $transaction_info['base_info']['qty_type_str'];
                $currentDiscount = $transaction_info['base_info']['discount'] ?? null;
            }
            $transactionTypeQty[$transaction_info['base_info']['type']] = $transaction_info['base_info']['quantity'];
            foreach ($transaction_info['transaction_type'] as $transactionQty){
                if($transactionQty['type'] == ProductTransactionType::REBATE){
                    $transactionTypeQty[$transactionQty['type']."_".$transactionQty['agreement_code']] = $transaction_info['base_info']['quantity'];
                } elseif($transactionQty['type'] == ProductTransactionType::SPOT) {
                    $transactionTypeQty[$transactionQty['type']."_".$transactionQty['agreement_code']] = $transactionQty['left_qty'] < $transactionQty['qty'] ? $transactionQty['left_qty'] : $transactionQty['qty'];
                } else {
                    $transactionTypeQty[$transactionQty['type']."_".$transactionQty['agreement_code']] = $transactionQty['left_qty'];
                }
            }
            //应对下单未支付 购物车未清理 库存不足时，购物车显示问题
            if ($product['type_id'] && $product['agreement_code']){
                $key = $product['type_id'].'_'.$product['agreement_code'];
                $keyArr = array_keys($transactionTypeQty);
                if (!in_array($key, $keyArr)){
                    $transactionTypeQty[$key] = 0;
                    if ($product['type_id'] == ProductTransactionType::MARGIN) {
                        $transaction_info['transaction_type'][] = [
                            'id' => $product['agreement_id'],
                            'type' => $product['type_id'],
                            'agreement_code' => $product['agreement_code'],
                            'price_show' => $origin_price,
                            'left_qty' => 0,
                        ];
                    }
                }
            }

            if ($product['type_id'] == ProductTransactionType::SPOT && empty($transaction_info['transaction_type']) && isset($productQuoteDetail)) {
                $transaction_info['transaction_type'][] = [
                    'id' => $productQuoteDetail['id'],
                    'type' => $product['type_id'],
                    'agreement_code' => empty($productQuoteDetail['agreement_no']) ? $productQuoteDetail['id'] : $productQuoteDetail['agreement_no'],
                    'price_show' => $this->currency->format($productQuoteDetail['price'], $this->session->get('currency')),
                    'left_qty' => $productQuoteDetail['quantity'],
                ];
            }

            //测试店铺、服务店铺、保证金店铺的产品、期货/现货保证金定金类产品在购物车中不显示转移到云送仓购物车的按钮
            //101867删除测试店铺限制
            $can_change_cart = true;
            if((isset($product['product_type']) && $product['product_type']!=ProductType::NORMAL && $product['product_type']!=ProductType::COMPENSATION_FREIGHT)
                || in_array($product['seller_id'],array(340,491,631,838))){
                $can_change_cart = false;
            }

            //select商品属性
            $colorArr = [];
            $color = $this->model_catalog_product->getAssociateProduct($product['product_id']);
            foreach ($color as $k=>$v)
            {
                $colorArr[$v['associate_product_id']] = $v['name'];
            }

            //100828 购物车商品涨价提醒
            $priceUp['status'] = 0;
            $normalPrice = $transaction_info['base_info']['price'];
            if ((isset($priceChangeInfo[$product['product_id']]) && $normalPrice < $priceChangeInfo[$product['product_id']]['new_price'])
            || (isset($fineChangeInfo[$product['product_id']]))
            ){
                $new_price = 0;
                $effect_time = 0;
                $current_price = $this->tax->calculate($product['defaultPrice'], $product['tax_class_id'], configDB('config_tax'));
                if (isset($fineChangeInfo[$product['product_id']]) && $fineChangeInfo[$product['product_id']]['product_display']){//精细化价格变动
                    if (isset($fineChangeInfo[$product['product_id']]['new_price'])
                        && isset($fineChangeInfo[$product['product_id']]['effect_time'])
                        && $normalPrice < $fineChangeInfo[$product['product_id']]['new_price']){//参与精细化管理的，不受普通价格变动影响
                        $new_price = $fineChangeInfo[$product['product_id']]['new_price'];
                        $effect_time = $fineChangeInfo[$product['product_id']]['effect_time'];
                        $current_price = $fineChangeInfo[$product['product_id']]['current_price'];
                    }elseif($fineChangeInfo[$product['product_id']]['is_rebate'] && isset($priceChangeInfo[$product['product_id']])){
                        //返点的实现方式走了精细化价格，但是普通涨价对此有效
                        $new_price = $priceChangeInfo[$product['product_id']]['new_price'];
                        $effect_time = $priceChangeInfo[$product['product_id']]['effect_time'];
                    }
                }else{
                    $new_price = $priceChangeInfo[$product['product_id']]['new_price'];
                    $effect_time = $priceChangeInfo[$product['product_id']]['effect_time'];
                }

                if ($new_price && $effect_time){
                    $priceUp['effect_time'] = strtotime($effect_time) - time();
                    $priceUp['add_price'] = $this->currency->formatCurrencyPrice(
                        max(0,$new_price - $current_price),session('currency'));
                    if ($data['isEuropean']){
                        $product_price_per_cur = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($product['seller_id'], $current_price, customer()->getCountryId());
                        $service_fee_per_cur = $current_price - $product_price_per_cur;

                        $product_price_per_new = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($product['seller_id'], $new_price, customer()->getCountryId());
                        $service_fee_per_new = $new_price - $product_price_per_new;
                        $priceUp['add_product_price'] = $this->currency->formatCurrencyPrice(
                            max(0,$product_price_per_new - $product_price_per_cur),session('currency'));
                        $priceUp['add_service_fee'] = $this->currency->formatCurrencyPrice(
                            max(0,$service_fee_per_new - $service_fee_per_cur), $this->session->data['currency']);
                    }
                }

                if (ProductTransactionType::NORMAL == $product['type_id'] && !$quoteFlag && $new_price){
                    $priceUp['status'] = 1;
                }

                // 当展示了精细化价格时，才展示升价提醒标志; 当存在精细化价格，且设置了升价，但购物车中没有展示精细化价格，此时也不展示升价提醒
                if ($priceUp['status'] == 1 && !empty($tieredPrices) && empty($tieredPrices[0]['selected'])) {
                    $priceUp['status'] = 0;
                }
            }

            //获取重量，详细长宽高等信息
            $weightTotal = 0;
            $tmpIndex = 1;
            $weightListStr = '';
            $wthStr = '';
            if ($product['combo'] == 1) {
                //combo 总重量 长宽高
                foreach ($freightAndPackageFeeArr[$product['product_id']] as $set_product_id => $freightAndPackage) {
                    $actualWeight = round($freightAndPackage['actual_weight'], 2);
                    $weightTotal += $actualWeight * $freightAndPackage['qty'];
                    $weightListStr .= sprintf($this->language->get('weight_detail_tip'),$tmpIndex,$actualWeight,$freightAndPackage['qty']);
                    $wthStr .= sprintf($this->language->get('volume_combo_detail_tip'),$tmpIndex,$freightAndPackage['length_inch'],$freightAndPackage['width_inch'],$freightAndPackage['height_inch'],$freightAndPackage['qty']);
                    $tmpIndex++;

                }
            } else {
                $weightTotal = $freightAndPackageFeeArr[$product['product_id']]['actual_weight'];
                $wthStr = sprintf($this->language->get('volume_detail_tip'),
                                  $freightAndPackageFeeArr[$product['product_id']]['length_inch'],
                                  $freightAndPackageFeeArr[$product['product_id']]['width_inch'],
                                  $freightAndPackageFeeArr[$product['product_id']]['height_inch']);
            }
            $weightTotal = sprintf('%.2f', $weightTotal);

            // Transaction Type中多重展示时，normal Transaction的价格需要筛选出最低的
            $format_default_price = $product['defaultPrice'];
            if (!$isMarginAdvanceProduct && !$isFuturesAdvanceProduct && !empty($transaction_info['transaction_type'])) {
                $format_default_price = $this->normalLowestPrice($product['refine_price'], $format_default_price, ($wkProQuoteDetailsByProductIds[$product['product_id']] ?? ''), $product['quantity'], $product['seller_id'], $product['product_id']);
            }
            // 促销活动
            $checkTransactionType = ($isMarginAdvanceProduct || $isFuturesAdvanceProduct) ? $product['type_id'] : null;
            $campaigns = app(CampaignService::class)->formatPromotionContentForCampaigns($productsCampaignsMap[$product['product_id']] ?? [], $checkTransactionType);

            end:

            $data['products'][$product['cart_id']] = array(
                'cart_id'   => $product['cart_id'],
                'type_id'         => $product['type_id'], //区分普通交易，rebate margin的
                'agreement_code'  => $product['agreement_code'],
                'agreement_id'  => $product['agreement_id'],
                'product_type'  => $product['product_type'],
                'transaction_info' => $transaction_info ?? [],
                'thumb'     => $image,
                'popupImage'=> isset($popupImage)?$popupImage:$image,
                'name'      => $product['name'],
                'model'     => $product['model'],
                'option'    => $option_data ?? [],
                'recurring' => (isset($product['recurring']) ? $product['recurring']['name'] : ''),
                'quantity'  => $product['quantity'],
                'price'     => $price ?? 0, //实际成交的货值
                'discount'          => $currentDiscount ?? null,
                'total'     => $total ?? 0,
                'stock' => $stock ?? 0,
                'seller_name' => $product['screenname'],
                'seller_href' => !empty($checkSellerProduct) ? $this->url->link('customerpartner/profile', 'id=' . $checkSellerProduct['customer_id']) : '',
                'href'         => $this->url->link('product/product', 'product_id=' . $product['product_id']),
                'quote_amount' => $quote_amount??false,     //数值
                'str_quote_amount'  => isset($quoteAmount) ? $quoteAmount : false,   //格式化后的金钱字符串
                'str_quote_service_fee'  => $quoteServiceFee??false,          //格式化后的金钱字符串
                'str_service_fee'  => isset($service_fee) ? '+' . $service_fee : false,     //格式化后的金钱字符串
                'quoteFlag'    => $quoteFlag ?? false,
                'futuresFlag' => $futuresFlag ?? false,
                'sku' =>$product['sku'],
                'tag' => $tags ?? [],
                'quote_amount_per' => $quote_amount_per ?? false,
                'str_quote_amount_per' => $quoteAmountPer ?? false,
                'quote_service_fee' => $quote_service_fee ?? false,
                'service_fee' => isset($service_fee) ? $service_fee : false,
                'isMarginAdvanceProduct'=>$isMarginAdvanceProduct ?? false,
                'isFuturesAdvanceProduct'=>$isFuturesAdvanceProduct ?? false,
                'product_id'=>$product['product_id'],
                'margin_expire_alert' => $product['margin_expire_alert'],
                'rebate_expire_alert' => $product['rebate_expire_alert'],
                'margin_batch_out_stock' => $product['margin_batch_out_stock'],
                'freight_per' =>$this->currency->formatCurrencyPrice($freight_per ?? 0,session('currency')),
                'freight_total' =>$freight_total ?? 0,
                'origin_price' =>$origin_price ?? 0, //原始货值,
                'service_fee_per'=>isset($service_fee_per)?$service_fee_per:0, //欧洲最后展示的服务费
                'product_price_per'=>isset($product_price_per)?$product_price_per:0, //欧洲最后展示的产品价格
                'quote_price_per' =>isset($quote_per)?$this->currency->formatCurrencyPrice(-$quote_per,$this->session->get('currency')):0, //议价折扣
                'quote_service_fee_per' =>isset($quote_service_fee_per)?$this->currency->formatCurrencyPrice(-$quote_service_fee_per,$this->session->get('currency')):false,
                'freight' =>$this->currency->formatCurrencyPrice(!empty($isCollectionFromDomicile)?0.00:$product['freight_per'],$this->session->get('currency')),
                'freight_rate' =>$product['freight_rate'],
                'package_fee' => $this->currency->formatCurrencyPrice($product['package_fee_per'],$this->session->get('currency')),
                'base_freight' =>$this->currency->formatCurrencyPrice($product['base_freight'],$this->session->get('currency')),
                'overweight_surcharge' => $product['overweight_surcharge'],//超重附加费
                'overweight_surcharge_show' => $this->currency->formatCurrencyPrice($product['overweight_surcharge'],$this->session->get('currency')),
                'transaction_type_qty' => $transactionTypeQty ?? 0, //不同交易方式的可售数量
                'qty_type_str' => $qtyTypeStr ?? 'Available',
                'volume' => $product['volume'],
                'volume_inch' => $product['volume_inch'],
                'can_change_cart' => $can_change_cart ?? false,
                'can_buy'   => $can_buy,
                'color' => $colorArr ?? [],
                'color_str' => isset($colorArr[$product['product_id']])?$colorArr[$product['product_id']]:'',
                'priceUp'   => $priceUp ?? [],
                'weight_total'   =>$weightTotal ?? 0,
                'weight_list_str'   =>$weightListStr ?? '',
                'wth_str'   => $wthStr ?? '',
                'combo' => $product['combo'],
                'show_tiered_pricing' => $showTieredPricing ?? false,
                'tiered_prices' => $tieredPrices ?? [],
                'service_tiered_prices' => $serviceTieredPrices ?? [],
                'format_default_price' => $this->currency->formatCurrencyPrice($format_default_price ?? 0, $this->session->get('currency')),
                'campaigns' => $campaigns ?? [],
            );
        }
        if($margin_expire_type_error && !empty($data['error_warning'])){
            $data['error_warning'] = 'Products marked with <i class="text-danger fa fa-times-circle"></i> are unavailable at the moment! The margin agreement was expired.';
        }
        return $data;
    }

    /**
     * 非协议的最低价
     * @param $refinePrice
     * @param $normalLowestPrice
     * @param $tieredPrices
     * @param $quantity
     * @param $sellerId
     * @param $productId
     * @return mixed
     */
    private function normalLowestPrice($refinePrice, $normalLowestPrice, $tieredPrices, $quantity, $sellerId, $productId)
    {
        if (!empty($refinePrice)) {
            $normalLowestPrice = $refinePrice;
        }
        [,$normalLowestPrice,] = app(ProductPriceRepository::class)->getProductTaxExemptionPrice($sellerId, $normalLowestPrice);

        // 获取大客户折扣
        $discountInfo = app(MarketingDiscountRepository::class)->getMaxDiscount(customer()->getId(), $productId, $quantity, ProductTransactionType::NORMAL);
        $discountValue = $discountInfo->discount ?? null;
        $discountRate = $discountValue ? intval($discountValue) / 100 : 1;
        $precision = customer()->getCountryId() == CountryEnum::JAPAN ? 0 : 2;

        if (empty($refinePrice)) {
            $normalLowestPrice =  MoneyHelper::upperAmount($normalLowestPrice * $discountRate, $precision);
        }

        $discountSpotInfo = app(MarketingDiscountRepository::class)->getMaxDiscount(customer()->getId(), $productId, $quantity, ProductTransactionType::SPOT);
        $discountSpotValue = $discountSpotInfo->discount ?? null;
        $discountSpotRate = $discountSpotValue ? intval($discountSpotValue) / 100 : 1;

        if ($tieredPrices) {
            foreach ($tieredPrices as $tieredPrice) {
                [,$tieredPrice['price'],] = app(ProductPriceRepository::class)->getProductTaxExemptionPrice($sellerId, $tieredPrice['price']);
                $tieredPrice['price'] =  MoneyHelper::upperAmount($tieredPrice['price'] * $discountSpotRate, $precision);
                if ($tieredPrice['min_quantity'] <= $quantity && $tieredPrice['max_quantity'] >= $quantity && $tieredPrice['price'] < $normalLowestPrice) {
                    $normalLowestPrice = $tieredPrice['price'];
                    break;
                }
            }
        }

        return $normalLowestPrice;
    }

    /**
     * 获取非协议价格选中的价格（普通，精细化，阶梯价）
     * @param $tieredPrices
     * @param $delicacy
     * @param $unitPrice
     * @param $quantity
     * @param $basePrice
     * @param int $addCartType
     * @param int $productId
     * @return mixed
     */
    private function tieredPrices($tieredPrices, $delicacy, $unitPrice, $quantity, $basePrice, $addCartType = 0, $productId = 0)
    {
        $normalTransaction = [
            'format_price' => $this->currency->formatCurrencyPrice($basePrice, $this->session->get('currency')),
            'price' => $basePrice,
            'msg' => $delicacy ? 'Custom' : 'Price',
            'seller_id' => $tieredPrices[0]['seller_id'] ?? 0,
        ];

        array_unshift($tieredPrices, $normalTransaction);
        // 德国/英国国别，有阶梯价的购物车，展示阶梯价的小弹窗中，拆分服务费
        $serviceTieredPrices = [];

        // 获取大客户折扣
        $discountInfo = app(MarketingDiscountRepository::class)->getMaxDiscount(customer()->getId(), $productId, $quantity, ProductTransactionType::NORMAL);
        $discount = $discountInfo->discount ?? null;
        $discountRate = $discount ? intval($discount) / 100 : 1;
        $precision = CurrencyHelper::getCurrentCode() == 'JPY' ? 0 : 2;

        $discountSpotInfo = app(MarketingDiscountRepository::class)->getMaxDiscount(customer()->getId(), $productId, $quantity, ProductTransactionType::SPOT);
        $discountSpot = $discountSpotInfo->discount ?? null;
        $discountSpotRate = $discountSpot ? intval($discountSpot) / 100 : 1;

        $useTieredPrice = false;
        foreach ($tieredPrices as $key => &$tieredPrice) {
            //region #31737 免税欧洲buyer的价格展示(阶梯价)
            [$vatUnitPrice, $europeTieredPrice, $europeServiceTieredPrice] = app(ProductPriceRepository::class)->getProductTaxExemptionPrice($tieredPrice['seller_id'], $tieredPrice['price']);

            //大客户折扣的价格
            if (!$delicacy || $key > 0) {
                if ($key > 0) {
                    $discountRate = $discountSpotRate;
                }
                $vatUnitPrice = MoneyHelper::upperAmount($vatUnitPrice * $discountRate, $precision);
                $europeTieredPrice = MoneyHelper::upperAmount($europeTieredPrice * $discountRate, $precision);
                $europeServiceTieredPrice = $vatUnitPrice - $europeTieredPrice;
            }
            $tieredPrice['price'] = $vatUnitPrice;
            $tieredPrice['selected'] = false;

            // 判断选中
            if ($addCartType != CartAddCartType::NORMAL && $key > 0) {
                if ($tieredPrice['min_quantity'] <= $quantity && $tieredPrice['max_quantity'] >= $quantity && $tieredPrice['price'] == $unitPrice) {
                    $tieredPrice['selected'] = true;
                    $useTieredPrice = true;
                }
            }

            if ($this->customer->isEurope()) {
                //不包含的服务费的价格
                $serviceTieredPrices[] = [
                    'max_quantity' => $tieredPrice['max_quantity'] ?? 0,
                    'min_quantity' => $tieredPrice['min_quantity'] ?? 0,
                    'msg' => $tieredPrice['msg'],
                    'price' => $europeServiceTieredPrice,
                    'format_price' => $this->currency->formatCurrencyPrice($europeServiceTieredPrice, $this->session->get('currency')),
                    'selected' => $tieredPrice['selected'] ?? false,
                ];
                $tieredPrice['price'] = $europeTieredPrice;
            }

            $tieredPrice['format_price'] = $this->currency->formatCurrencyPrice($tieredPrice['price'], $this->session->get('currency'));
        }
        unset($tieredPrice);

        $tieredPrices[0]['selected'] = !$useTieredPrice;
        $serviceTieredPrices[0]['selected'] = $tieredPrices[0]['selected'];

        return [$tieredPrices, $serviceTieredPrices];
    }

    /**
     * 购物车/采购订单的total列展示
     * @param array $cartIdsOrProducts $isBuyNow 为true 代表的是products 否则代表购物车ids
     * @param mixed $isBuyNow 区分购物车和buy now
     * @param array $params ['coupon_ids' => null|array] coupon_ids为null使用默认优惠券，为空数组不使用优惠券，为ids使用该些优惠券
     * @return mixed
     * @throws Exception
     * @author xxl
     */
    public function orderTotalShow($cartIdsOrProducts = [], $isBuyNow = false, $params = [])
    {
        $this->load->model('setting/extension');
        $totals = array();

        $delivery_type = $this->session->has('delivery_type') ? $this->session->get('delivery_type') : -1;
        $products = (!$isBuyNow && count($cartIdsOrProducts) == count($cartIdsOrProducts,1)) ? $this->cart->getProducts(null, $delivery_type, $cartIdsOrProducts) : $cartIdsOrProducts;
        $products =  array_map(function ($product) use ($isBuyNow) {
            if ($isBuyNow) {
                $product['quote_amount'] = $product['spot_price'];
                $product['price'] = $product['current_price'];
            } else {
                $product['current_price'] = $product['price'];
                $product['transaction_type'] = $product['type_id'];
            }
            return $product;
        }, $products);

        //现系统未使用该方法
        //$taxes = $this->cart->getTaxes([], $products);
        $taxes = [];

        $total = 0;
        $gifts = []; // 满送
        $discounts = []; // 满减
        $selectCouponIds = []; //选择的优惠券
        $total_data = array(
            'totals' => &$totals,
            'taxes' => &$taxes,
            'total' => &$total,
            'gifts' => &$gifts,
            'discounts' => &$discounts,
        );
        if ($this->customer->isLogged() || !configDB('config_customer_price')) {
            $sort_order = array();

            $results = $this->model_setting_extension->getExtensions('total');

            foreach ($results as $key => $value) {
                $sort_order[$key] = configDB('total_' . $value['code'] . '_sort_order');
            }

            array_multisort($sort_order, SORT_ASC, $results);

            foreach ($results as $result) {
                if (configDB('total_' . $result['code'] . '_status')) {
                    $this->load->model('extension/total/' . $result['code']);

                    // We have to put the totals in an array so that they pass by reference.
                    $method = $isBuyNow ? 'getTotalByProducts' : 'getTotalByCartId';
                    $this->{'model_extension_total_' . $result['code']}->$method($total_data, $products, $params);
                }
            }

            $sort_order = array();

            foreach ($totals as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $totals);
        }

        //议价的total相关数组
        $quoteArr = [];
        if (($params['limit_quote'] ?? true)) {
            // 是否需要限制返回议价相关数据，默认限制
            $quoteArr = [$this->language->get('text_product_quote_service_fee'), $this->language->get('text_product_quote_amount'), $this->language->get('text_product_quote')];
        }

        $item_quote = 0;
        $service_fee_quote = 0;
        foreach ($totals as $total) {
            if ($total['title'] == $this->language->get('text_product_quote_service_fee')) {
                $service_fee_quote = $total['value'];
            }
            if ($total['title'] == $this->language->get('text_product_quote_amount')) {
                $item_quote = $total['value'];
            }
            if ($total['title'] == $this->language->get('text_product_quote')) {
                $item_quote = $total['value'];
            }
        }

        // 优化券加满减的优惠金额
        $currency = $this->session->get('currency');
        $discountsAmount = 0;
        foreach ($totals as $total) {
            $itemTotal = [
                'code' => $total['code'],
                'value' => $total['value'],
                'title' => $total['title'],
                'text' =>  $this->currency->formatCurrencyPrice($total['value'], $currency),
            ];
            if ($total['title'] == 'Sub-Total') {
                $itemTotal['title'] = 'Item(s)';
                $itemTotal['text'] = $this->currency->formatCurrencyPrice($total['value'] + $item_quote, $currency);
            } elseif ($total['title'] == 'Service Fee') {
                $itemTotal['title'] = 'General Service Fee';
                $itemTotal['text'] =  $this->currency->formatCurrencyPrice($total['value'] + $service_fee_quote, $currency);
            } elseif ($total['title'] == 'Freight') {
                $itemTotal['title'] = 'Fulfillment Fee';
            } elseif (in_array($total['title'], $quoteArr)) {
                //议价相关的total不展示
                continue;
            } elseif ($total['title'] == 'Giga Coupon') {
                if ($total['coupon_ids']) {
                    $selectCouponIds = $total['coupon_ids'];
                }
            } elseif ($total['title'] == 'Promotion Discount') {
                array_map(function ($discount) {
                    $discount->format_price =  $this->currency->formatCurrencyPrice($discount->conditions[$discount->id]->minus_amount, $this->session->get('currency'));
                }, $discounts);
                $itemTotal['discounts'] = $discounts;
            }
            $data['totals'][] = $itemTotal;

            if ($total['title'] == 'Giga Coupon' || $total['title'] == 'Promotion Discount') {
                $discountsAmount += $total['value'];
            }
        }
        $data['total'] = $total;
        $data['all_totals'] = $totals;
        $data['discounts_amount'] = [
            'amount' => $discountsAmount,
            'amount_text' => $this->currency->formatCurrencyPrice($discountsAmount, $this->session->get('currency')),
        ];

        array_map(function ($gift) {
            $gift->is_coupon = !empty($gift->conditions[$gift->id]->couponTemplate);
            $gift->condition_remark = $gift->conditions[$gift->id]->remark;
            if ($gift->is_coupon) {
                $gift->format_coupon_price =  $this->currency->formatCurrencyPrice($gift->conditions[$gift->id]->couponTemplate->denomination, $this->session->get('currency'));
            }
        }, $gifts);
        $data['gifts'] = $gifts;
        $data['select_coupon_ids'] = $selectCouponIds;

        return $data;
    }


    public function countCartQtyByDeliveryType(){
        $customer_id = $this->customer->getId();
        $cartResult = $this->orm->table('oc_cart')
            ->where('customer_id','=',$customer_id)
            ->selectRaw('sum(quantity) as allQty,delivery_type')
            ->groupBy('delivery_type')
            ->get()
            ->toArray();
        return $cartResult;
    }

    //购物车单条数据展示刷新
    public function cartShowSingle($cartId)
    {
        $this->load->model('account/customerpartner');
        $this->load->model('account/product_quotes/margin');
        $this->load->model('futures/agreement');
        $this->load->model('catalog/product');
        $this->load->model('extension/module/price');
        $this->load->model('extension/module/cart_home');

        $cartInfo = $this->cart->getInfoByCartId($cartId);
        //判断是否为欧洲
        if (!empty($this->customer->getCountryId()) && $this->country->isEuropeCountry($this->customer->getCountryId())) {
            $data['isEuropean'] = true;
        } else {
            $data['isEuropean'] = false;
        }
        //是否启用议价
        $data['enableQuote'] = false;
        if (!empty($this->customer->getCountryId()) && in_array($this->customer->getCountryId(), QUOTE_ENABLE_COUNTRY)) {
            $data['enableQuote'] = true;
        }
        $margin_expire_type_error = true;
        $products = $this->cart->getProducts(null,$cartInfo['delivery_type'],[$cartId]);
        $product = current($products);

        $can_buy = $product['buyer_flag'] && $product['product_status'] && $product['store_status'] && !$product['fine_cannot_buy'] ? 1:0;
        if (!$can_buy) {
            goto end;
        }

        $wkProQuoteDetailsByProductIds = $this->wkProQuoteDetailsByProductIds([$product['product_id']]);//某个产品的阶梯价展示

        /*
         * 购物车具体产品价格价格展示
         * 议价对货值
         * 进行议价,$product['price']进行议价换算
         * 1.美国(两位小数)/日本(没有小数)
         * 1-1：美国一件代发,上门取货价+运费 (oc_product.price+oc_product.freight+oc_product.package_fee),$product['price']+$product['freight_per']+$product['package_fee']
         * 1-2：美国上门取货，上门取货价 (oc_product.price),$product['price']+$product['package_fee']
         * 2.欧洲(服务费的拆分针对于货值)
         * 2-1:欧洲一件代发价,拆分服务费,已上门取货价+运费(oc_product.price),$product['price']进行拆分
         * 2-2：欧洲上门取货价,拆分服务费,上门取货价 (oc_product.price),$product['price']进行拆分
         */
        $showTieredPricing = false;
        $tieredPrices = [];
        $serviceTieredPrices = [];
        $quoteFlag = false;
        $futuresFlag = false;
        if ($this->customer->isLogged() || !configDB('config_customer_price')) {

            $unit_price = $this->tax->calculate(isset($product['price'])?$product['price']:0, $product['tax_class_id'], configDB('config_tax'));
            $origin_price = $this->currency->formatCurrencyPrice($unit_price, $this->session->data['currency']);//原始货值价格
            $quote_amount = 0;  //议价总折扣数
            $quote_amount_per = 0;    //每件商品的折扣数
            //议价针对于货值,是否议价存在session中
            if ($product['type_id'] == ProductTransactionType::SPOT) {
                $this->load->model('account/product_quotes/wk_product_quotes');
                $productQuoteDetail = $this->model_account_product_quotes_wk_product_quotes->getProductQuoteDetail($product['agreement_id']);
                if ($productQuoteDetail) {
                    /**
                     * @var float $quote_per 每个商品的减去的折扣数
                     * @var float $quote_amount_per 如果为欧洲地区，则为商品展示价格的折扣数；如果非欧洲地区 等于 $quote_per
                     * @var float $quote_service_fee_per 如果为欧洲地区，则为商品服务费的折扣数；如果非欧洲地区该值为0
                     */
//                    $productQuoteDetail['price'] = app(MarketingDiscountRepository::class)->getPriceAfterDiscount($this->customer->getId(), $product['product_id'], $productQuoteDetail['price']);
                    $quote_per = bcsub(round($product['normal_price'], $this->customer->isJapan() ? 0 : 2), $productQuoteDetail['price'], $this->customer->isJapan() ? 0 : 2);
                    $quote_amount_per = $quote_per;
                    $quote_service_fee_per = 0;
                    if ($this->customer->isEurope()) {
                        $quote_amount_per = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($product['seller_id'], $quote_per, customer()->getCountryId());
                        $quote_service_fee_per = bcsub($quote_per, $quote_amount_per, 2);
                        $quote_service_fee = $quote_service_fee_per * $product['quantity'];
                        $quoteServiceFee = $this->currency->formatCurrencyPrice(-$quote_service_fee, $this->session->get('currency'));
                        $product['service_fee_per'] = $product['service_fee_per'] - $quote_service_fee_per;//欧洲议价后的货值拆分的服务费
                        $product['product_price_per'] = $productQuoteDetail['price'] - $product['service_fee_per'];

                        #31737 下单针对于非复杂交易的价格需要判断是否需免税 走议价协议时 unit price未计算免税价
                        $quote_amount_per = BCS::create($unit_price, ['scale' => 2])->sub($product['service_fee_per'], $product['product_price_per'], $quote_service_fee_per)->getResult();
                    }
                    $quote_amount = bcmul($quote_amount_per, $product['quantity'], $this->customer->isJapan() ? 0 : 2);
                    $quoteAmount = $this->currency->formatCurrencyPrice(-$quote_amount, $this->session->get('currency'));
                    if (stripos($quoteAmount, '-', 0) === false) {
                        $quoteAmount = '+' . $quoteAmount;
                    }

                    $unit_price = $productQuoteDetail['price'];
                    if ($product['quantity'] == $productQuoteDetail['quantity'] && $productQuoteDetail['status'] != SpotProductQuoteStatus::TIMEOUT) {
                        $quoteFlag = true;
                    }

                    if ($productQuoteDetail['status'] == SpotProductQuoteStatus::TIMEOUT) {
                        $this->updateToNormalCart($product['cart_id']);
                        $product['type_id'] = ProductTransactionType::NORMAL;
                        $product['agreement_id'] = null;
                    }
                }
            }
            if ($product['type_id'] == ProductTransactionType::FUTURE) {
                $cartInfo['agreement_id'];
                $version = FuturesMarginAgreement::query()->where('id', '=', $cartInfo['agreement_id'])->value('version');
                if ($version == 3) {
                    $futuresFlag = true;
                }
            }
            // #3099 获取非协议价格选中的价格（普通，精细化，阶梯价）
            if ($product['type_id'] == ProductTransactionType::NORMAL && isset($wkProQuoteDetailsByProductIds[$product['product_id']])) {
                $showTieredPricing = true;
                list($tieredPrices, $serviceTieredPrices) = $this->tieredPrices($wkProQuoteDetailsByProductIds[$product['product_id']], $product['is_delicacy_effected'], $unit_price, $product['quantity'], empty($product['refine_price']) ? $product['defaultPrice'] : $product['refine_price'], $product['add_cart_type'],$product['product_id']);
            }

            $price = $this->currency->formatCurrencyPrice($unit_price, $this->session->data['currency']);
            $quoteAmountPer = $this->currency->formatCurrencyPrice(-$quote_amount_per, $this->session->data['currency']);
            //欧洲展示服务费 xxl
            if($data['isEuropean']) {
                $service_fee_per = $this->currency->formatCurrencyPrice($product['service_fee_per'], $this->session->data['currency']);
                $product_price_per = $this->currency->formatCurrencyPrice($product['product_price_per'], $this->session->data['currency']);
            }
            //上门取货的运费仅为打包费
            $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
            if($isCollectionFromDomicile) {
                $total = $this->currency->formatCurrencyPrice(($unit_price+$product['package_fee_per']) * $product['quantity'], $this->session->data['currency']);
            }else{
                $total = $this->currency->formatCurrencyPrice(($unit_price + $product['freight_per'] + $product['package_fee_per']) * $product['quantity'], $this->session->data['currency']);
            }
        }else{
            //未登录的价格展示
            $price = false;
            $total = false;
            $origin_price = false;
        }

        $stock = $product['stock'] ? true : !(!configDB('config_stock_checkout') || configDB('config_stock_warning'));
        //是否为保证金头款商品
        $isMarginAdvanceProduct = boolval($product['product_type'] == ProductType::MARGIN_DEPOSIT);

        //是否为期货头款商品 N-294
        $isFuturesAdvanceProduct = boolval($product['product_type'] == ProductType::FUTURE_MARGIN_DEPOSIT);

        if($margin_expire_type_error && empty($product['margin_expire_alert']) && !$stock){
            $margin_expire_type_error = false;
        }

        /*
         * 产品运费的规则
         * 1.上门取货：运费 = 打包费
         * 2.一件代发：运费 = 产品运费+打包费
         */
        if ($this->customer->isCollectionFromDomicile()) {//上门取货
            $freight_per = $product['package_fee_per'];
        } else {
            $freight_per = $product['freight_per']+$product['package_fee_per'];
        }
        $freight_total = $freight_per*$product['quantity'];
        // 获取每个产品的不同交易类型的价格 各种交易形式的产品数量
        $transaction_info = $this->model_extension_module_price->getProductPriceInfo($product['product_id'], $this->customer->getId(), [], false, true, ['qty' => $product['quantity'],'use_wk_pro_quote_price' => $product['use_wk_pro_quote_price']]);
        //普通交易形式
        $currentDiscount = null;
        if ($product['type_id'] == ProductTransactionType::NORMAL) {
            $qtyTypeStr = $transaction_info['base_info']['qty_type_str'];
            $currentDiscount = $transaction_info['base_info']['discount'] ?? null;
        }
        $transactionTypeQty[$transaction_info['base_info']['type']] = $transaction_info['base_info']['quantity'];
        foreach ($transaction_info['transaction_type'] as $transactionQty){
            if($transactionQty['type'] == ProductTransactionType::REBATE){
                $transactionTypeQty[$transactionQty['type']."_".$transactionQty['agreement_code']] = $transaction_info['base_info']['quantity'];
            } elseif($transactionQty['type'] == ProductTransactionType::SPOT) {
                $transactionTypeQty[$transactionQty['type']."_".$transactionQty['agreement_code']] = $transactionQty['left_qty'] < $transactionQty['qty'] ? $transactionQty['left_qty'] : $transactionQty['qty'];
            } else {
                $transactionTypeQty[$transactionQty['type']."_".$transactionQty['agreement_code']] = $transactionQty['left_qty'];
            }
        }

        $availableQty = $transaction_info['base_info']['quantity'];
        //应对下单未支付 购物车未清理 库存不足时，购物车显示问题
        if ($product['type_id'] && $product['agreement_code']){
            $key = $product['type_id'].'_'.$product['agreement_code'];
            $keyArr = array_keys($transactionTypeQty);
            if (!in_array($key, $keyArr)){
                $transactionTypeQty[$key] = 0;
                if ($product['type_id'] == ProductTransactionType::MARGIN) {
                    $transaction_info['transaction_type'][] = [
                        'id' => $product['agreement_id'],
                        'type' => $product['type_id'],
                        'agreement_code' => $product['agreement_code'],
                        'price_show' => $origin_price,
                        'left_qty' => 0,
                    ];
                }
            }
            if (!$isMarginAdvanceProduct && !$isFuturesAdvanceProduct){
                $availableQty = $transactionTypeQty[$key];
            }
        }
        if ($product['type_id'] == ProductTransactionType::SPOT && empty($transaction_info['transaction_type']) && isset($productQuoteDetail)) {
            $transaction_info['transaction_type'][] = [
                'id' => $productQuoteDetail['id'],
                'type' => $product['type_id'],
                'agreement_code' => empty($productQuoteDetail['agreement_no']) ? $productQuoteDetail['id'] : $productQuoteDetail['agreement_no'],
                'price_show' => $this->currency->format($productQuoteDetail['price'], $this->session->get('currency')),
                'left_qty' => $productQuoteDetail['quantity'],
            ];
        }

        //测试店铺、服务店铺、保证金店铺的产品、期货/现货保证金定金类产品在购物车中不显示转移到云送仓购物车的按钮
        //101867删除测试店铺限制
        $can_change_cart = true;
        if((isset($product['product_type']) && $product['product_type']!=ProductType::NORMAL && $product['product_type']!=ProductType::COMPENSATION_FREIGHT)
            || in_array($product['seller_id'],array(340,491,631,838))){
            $can_change_cart = false;
        }
        //购物车涨价提醒
        $priceUp['status'] = 0;
        $normalCurrentPrice = $transaction_info['base_info']['price'];
        if (ProductTransactionType::NORMAL == $product['type_id'] && !$isFuturesAdvanceProduct && !$isMarginAdvanceProduct && !$quoteFlag){
            $change = $this->finePriceChangeInfo([$product['product_id']]);//精细化价格变动信息
            $priceChangeInfo = $this->priceChangeByProductId($product['product_id']);
            if (isset($change[$product['product_id']]) && $change[$product['product_id']]['product_display']) {
                if (isset($change[$product['product_id']]['new_price']) && isset($change[$product['product_id']]['effect_time']) && $normalCurrentPrice < $change[$product['product_id']]['new_price']) {//参与精细化管理的，不受普通价格变动影响
                    $change = $change[$product['product_id']];
                } elseif ($change[$product['product_id']]['is_rebate'] && !empty($priceChangeInfo)) {
                    $change = $priceChangeInfo;
                }
            } else {
                $change = $priceChangeInfo;
            }
            $up = (isset($change['new_price']) && $change['new_price'] > $normalCurrentPrice) ? 1 : 0;
            if (isset($change['new_price']) && $up){
                $new_price = $change['new_price'];
                $effect_time = $change['effect_time'];

                $priceUp['effect_time'] = strtotime($effect_time) - time();
                $priceUp['add_price'] = $this->currency->formatCurrencyPrice(max(0,$new_price - $normalCurrentPrice),session('currency'));
                if ($data['isEuropean']){
                    $product_price_per_new = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($product['seller_id'], $new_price, customer()->getCountryId());
                    $service_fee_per_new = $new_price - $product_price_per_new;
                    $priceUp['add_product_price'] = $this->currency->formatCurrencyPrice(max(0,$product_price_per_new - $product['product_price_per']),session('currency'));
                    $priceUp['add_service_fee'] = $this->currency->formatCurrencyPrice(max(0,$service_fee_per_new - $product['service_fee_per']), $this->session->data['currency']);
                }
                if (ProductTransactionType::NORMAL == $product['type_id'] && !$quoteFlag){
                    $priceUp['status'] = 1;
                }

                // 当展示了精细化价格时，才展示升价提醒标志; 当存在精细化价格，且设置了升价，但购物车中没有展示精细化价格，此时也不展示升价提醒
                if ($priceUp['status'] == 1 && !empty($tieredPrices) && !isset($tieredPrices[0]['selected'])) {
                    $priceUp['status'] = 0;
                }
            }
        }

        // Transaction Type中多重展示时，normal Transaction的价格需要筛选出最低的
        $format_default_price = $product['defaultPrice'];
        if (!$isMarginAdvanceProduct && !$isFuturesAdvanceProduct && !empty($transaction_info['transaction_type'])) {
            $format_default_price = $this->normalLowestPrice($product['refine_price'], $format_default_price, ($wkProQuoteDetailsByProductIds[$product['product_id']] ?? ''), $product['quantity'], $product['seller_id'], $product['product_id']);
        }

        end:

        $data['product'] = array(
            'cart_id'           => $product['cart_id'],
            'delivery_type'     => $product['delivery_type'],
            'type_id'           => $product['type_id'], //区分普通交易，rebate margin的
            'agreement_code'    => $product['agreement_code'],
            'agreement_id'      => $product['agreement_id'],
            'product_type'      => $product['product_type'],
            'transaction_info'  => $transaction_info ?? [],
            'name'              => $product['name'],
            'model'             => $product['model'],
            'quantity'          => $product['quantity'],
            'discount'          => $currentDiscount,
            'price'             => $price ?? 0, //实际成交的货值
            'total'             => $total ?? 0,
            'stock'             => $stock ?? 0,
            'href'              => $this->url->link('product/product', 'product_id=' . $product['product_id']),
            'quote_amount'      => $quote_amount??false,     //数值
            'str_quote_amount'  => isset($quoteAmount) ? $quoteAmount : false,   //格式化后的金钱字符串
            'str_quote_service_fee'      => $quoteServiceFee??false,          //格式化后的金钱字符串
            'str_service_fee'   => isset($service_fee) ? '+' . $service_fee : false,     //格式化后的金钱字符串
            'quoteFlag'         => $quoteFlag ?? false,
            'futuresFlag'         => $futuresFlag ?? false,
            'sku'               =>$product['sku'],
            'quote_amount_per'  => $quote_amount_per ?? false,
            'str_quote_amount_per'  => $quoteAmountPer ?? false,
            'quote_service_fee'     => $quote_service_fee ?? false,
            'service_fee'           => isset($service_fee) ? $service_fee : false,
            'isMarginAdvanceProduct'=>$isMarginAdvanceProduct ?? false,
            'isFuturesAdvanceProduct'=>$isFuturesAdvanceProduct ??false,
            'product_id'            =>$product['product_id'],
            'margin_expire_alert'   => $product['margin_expire_alert'],
            'rebate_expire_alert'   => $product['rebate_expire_alert'],
            'margin_batch_out_stock'=> $product['margin_batch_out_stock'],
            'freight_per'       =>$this->currency->formatCurrencyPrice($freight_per ?? 0,session('currency')),
            'freight_total'     =>$freight_total ?? 0,
            'origin_price'      =>$origin_price ?? 0, //原始货值,
            'service_fee_per'   =>isset($service_fee_per)?$service_fee_per:0, //欧洲最后展示的服务费
            'product_price_per' =>isset($product_price_per)?$product_price_per:0, //欧洲最后展示的产品价格
            'quote_price_per'   =>isset($quote_per)?$this->currency->formatCurrencyPrice(-$quote_per,session('currency')):0, //议价折扣
            'quote_service_fee_per'     =>isset($quote_service_fee_per)?$this->currency->formatCurrencyPrice(-$quote_service_fee_per,session('currency')):false,
            'freight'           =>$this->currency->formatCurrencyPrice(!empty($isCollectionFromDomicile)?0.00:$product['freight_per'],session('currency')),
            'package_fee'       => $this->currency->formatCurrencyPrice($product['package_fee_per'],session('currency')),
            'transaction_type_qty'  => $transactionTypeQty ?? 0, //不同交易方式的可售数量
            'qty_type_str' => $qtyTypeStr ?? 'Available',
            'available_qty'         => $availableQty ?? 0,
            'volume'                => $product['volume'],
            'can_change_cart'       => $can_change_cart ?? true,
            'can_buy'               => $can_buy,
            'priceUp'               => $priceUp ?? [],
            'show_tiered_pricing' => $showTieredPricing ?? false,
            'tiered_prices' => $tieredPrices ?? [],
            'service_tiered_prices' => $serviceTieredPrices ?? [],
            'format_default_price' => $this->currency->formatCurrencyPrice($format_default_price ?? 0, $this->session->get('currency')),
        );
        if($margin_expire_type_error && !empty($data['error_warning'])){
            $data['error_warning'] = 'Products marked with <i class="text-danger fa fa-times-circle"></i> are unavailable at the moment! The margin agreement was expired.';
        }

        $carts_info =  $this->model_extension_module_cart_home->getCartInfo($this->customer->getId());
        $totalMoney = $this->currency->format($carts_info['total_price'], $this->session->data['currency']);
        $data['total'] = [
            'totalNum'  => $carts_info['quantity'],
            'totalMoney'=> $totalMoney
        ];
        return $data;
    }

    //按店铺划分购物车商品
    public function cartShowByStore($delivery_type = -1)
    {
        $stores = $this->cart->storeProduct($delivery_type);
        $cartShow = $this->cartShow($delivery_type);

        // 可以购买有效的购物车IDs
        $validCartIds = array_keys(array_column($cartShow['products'], 'can_buy', 'cart_id'), 1);

        $data = [];
        $data['isEuropean'] = $cartShow['isEuropean'];
        $data['enableQuote'] = $cartShow['enableQuote'];
        foreach ($stores as $store_id => $value)
        {
            $hasContact = $this->hasContact($this->customer->getId(), $store_id);
            $stores[$store_id]['url'] = $this->url->link('customerpartner/profile', '&id='.$store_id);
            $stores[$store_id]['contract_url'] = $this->url->link('message/seller/addMessage', '&receiver_id='.$store_id);
            foreach ($value['cart_id_arr'] as $cart_id)
            {
                if (isset($cartShow['products'][$cart_id])){
                    if (!$hasContact){
                        $cartShow['products'][$cart_id]['can_buy'] = 0;
                        // 购物不能购买的购物车id
                        $validCartIds = array_diff($validCartIds, [$cart_id]);
                    }
                    $stores[$store_id]['products'][] = $cartShow['products'][$cart_id];
                }
            }
        }
        $data['stores'] = $stores;
        $data = array_merge($data, empty($validCartIds) ? $this->emptyTotalShow() : $this->orderTotalShow($validCartIds));

        return $data;
    }

    //初始状态 购物车全不选中
    public function emptyTotalShow()
    {
        $this->load->model('setting/extension');
        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;
        $total_data = array(
            'totals' => &$totals,
            'taxes' => &$taxes,
            'total' => &$total
        );
        if ($this->customer->isLogged() || !configDB('config_customer_price')) {
            $sort_order = array();
            $results = $this->model_setting_extension->getExtensions('total');
            foreach ($results as $key => $value) {
                $sort_order[$key] = configDB('total_' . $value['code'] . '_sort_order');
            }
            array_multisort($sort_order, SORT_ASC, $results);

            foreach ($results as $result) {
                if (in_array($result['code'], ['balance', 'poundage', 'wk_pro_quote'])){
                    continue;
                }
                if (configDB('total_' . $result['code'] . '_status')) {
                    $this->load->model('extension/total/' . $result['code']);

                    // We have to put the totals in an array so that they pass by reference.
                    $this->{'model_extension_total_' . $result['code']}->getDefaultTotal($total_data);
                }
            }

            $sort_order = array();

            foreach ($totals as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $totals);
        }
        $item_quote = 0;
        $service_fee_quote = 0;
        foreach ($totals as $total) {
            if ($total['title'] == $this->language->get('text_product_quote_service_fee')) {
                $service_fee_quote = $total['value'];
            }
            if ($total['title'] == $this->language->get('text_product_quote_amount')) {
                $item_quote = $total['value'];
            }
            if ($total['title'] == $this->language->get('text_product_quote')) {
                $item_quote = $total['value'];
            }
        }

        foreach ($totals as $total) {
            if ($total['title'] == 'Sub-Total') {
                $data['totals'][] = array(
                    'code'  => $total['code'],
                    'title' => 'Item(s)',
                    'value' => 0,
                    'text' => $this->currency->formatCurrencyPrice($total['value']+$item_quote, $this->session->data['currency']),
                );
            } elseif ($total['title'] == 'Service Fee') {
                $data['totals'][] = array(
                    'code'  => $total['code'],
                    'title' => 'General Service Fee',
                    'value' => 0,
                    'text' => $this->currency->formatCurrencyPrice($total['value']+$service_fee_quote, $this->session->data['currency']),
                );
            } elseif ($total['title'] == 'Freight'){
                $data['totals'][] = array(
                    'code'  => $total['code'],
                    'title' => 'Fulfillment Fee',
                    'value' => 0,
                    'text' => $this->currency->formatCurrencyPrice($total['value'], $this->session->data['currency']),
                );
            } else {
                $data['totals'][] = array(
                    'code'  => $total['code'],
                    'title' => $total['title'],
                    'value' => 0,
                    'text' => $this->currency->formatCurrencyPrice($total['value'], $this->session->data['currency']),
                );
            }
        }
        $data['total'] = $total;
        $data['gifts'] = [];
        return $data;
    }

    //是否是返点商品 返点协议通过后会生成一条精细化记录
    public function rebateProduct($buyerId,$productIdArr = [])
    {
        return $this->orm->table('oc_rebate_agreement_item as i')
            ->leftJoin('oc_rebate_agreement as r', 'r.id', '=', 'i.agreement_id')
            ->where('r.buyer_id', '=', $buyerId)
            ->where('r.status', '>=', 3)
            ->whereIn('i.product_id', $productIdArr)
            ->pluck('i.product_id')
            ->toArray();
    }

    /**
     * @param array $productIds
     * @return array
     */
    public function wkProQuoteDetailsByProductIds($productIds)
    {
        $wkProQuoteDetails = $this->orm->table('oc_wk_pro_quote_details')
            ->whereIn('product_id', $productIds)
            ->orderBy('sort_order')
            ->get();

        if ($wkProQuoteDetails->isEmpty()) {
            return [];
        }

        $currencyCode = $this->session->get('currency');
        $precision = $this->currency->getDecimalPlace($currencyCode);

        $productDetailMap = [];
        foreach ($wkProQuoteDetails as $wkProQuoteDetail) {
            if (!array_key_exists($wkProQuoteDetail->product_id, $productDetailMap)) {
                $productDetailMap[$wkProQuoteDetail->product_id] = [];
            }

            $productDetailMap[$wkProQuoteDetail->product_id][] = [
                'format_price' => $this->currency->format(round($wkProQuoteDetail->home_pick_up_price, $precision), $currencyCode),
                'msg' => ($wkProQuoteDetail->max_quantity == $wkProQuoteDetail->min_quantity ? $wkProQuoteDetail->min_quantity : $wkProQuoteDetail->min_quantity . ' - ' . $wkProQuoteDetail->max_quantity) . ' PCS',
                'max_quantity' => $wkProQuoteDetail->max_quantity,
                'min_quantity' => $wkProQuoteDetail->min_quantity,
                'price' => $wkProQuoteDetail->home_pick_up_price,
                'seller_id' => $wkProQuoteDetail->seller_id,
            ];
        }

        return $productDetailMap;
    }

    //精细化价格变动信息
    public function finePriceChangeInfo($productIdArr, $buyerId=0)
    {
        !$buyerId && $buyerId = $this->customer->getId();
        $info = [];
        $rebateProduct = $this->rebateProduct($buyerId, $productIdArr);
        foreach ($productIdArr as $productId)
        {

            $fine = $this->cart->getDelicacyManagementInfoByNoView($productId, $buyerId);
            if (!empty($fine)){
                if ($fine['product_display'] && $fine['effective_time'] > date('Y-m-d H:i:s')){
                    $info[$productId] = [
                        'product_display'   => $fine['product_display'],
                        'current_price' => $fine['current_price'],
                        'new_price'     => $fine['price'],
                        'effect_time'   => $fine['effective_time']
                    ];
                }else{
                    $info[$productId]['product_display'] = $fine['product_display'];
                }

                $info[$productId]['is_rebate'] = in_array($productId, $rebateProduct)?1:0;
            }
        }
        return $info;
    }

    //价格变动信息
    public function priceChangeInfo($productIdArr)
    {
        $data = $this->orm->table('oc_seller_price')
            ->whereIn('product_id', $productIdArr)
            ->where('status', '=', 1)
            ->where('effect_time', '>', date('Y-m-d H:i:s'))
            ->select('product_id','new_price','effect_time')
            ->get();
        $info = [];
        foreach ($data as $v)
        {
            $info[$v->product_id] = [
                'new_price'     => $v->new_price,
                'effect_time'   => $v->effect_time
            ];
        }

        return $info;
    }

    //单条价格变动信息
    public function priceChangeByProductId($productId)
    {
        return obj2array($this->orm->table('oc_seller_price')
            ->where('product_id', $productId)
            ->where('status', '=', 1)
            ->where('effect_time', '>', date('Y-m-d H:i:s'))
            ->select('new_price','effect_time')
            ->first());
    }

    public function getOrderCloudLogistics($id)
    {
        return OrderCloudLogistics::query()
            ->where('id', $id)
            ->first();
    }

    //buyer与seller是否建立联系
    public function hasContact($buyerId,$sellerId)
    {
        return $this->orm->table('oc_buyer_to_seller')
            ->where([
                'buyer_id'  => $buyerId,
                'seller_id' => $sellerId,
                'buy_status'=> 1,
                'buyer_control_status'  => 1,
                'seller_control_status' => 1
            ])
            ->exists();
    }

    /**
     * 更新为普通类型
     * @param int $id
     * @return int
     */
    public function updateToNormalCart($id)
    {
        return $this->orm->table('oc_cart')->where('cart_id', $id)->update(['type_id' => ProductTransactionType::NORMAL, 'agreement_id' => null, 'add_cart_type' => CartAddCartType::DEFAULT_OR_OPTIMAL]);
    }
}
