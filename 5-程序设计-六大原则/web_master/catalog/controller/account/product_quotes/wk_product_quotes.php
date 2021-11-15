<?php

use App\Repositories\Product\ProductPriceRepository;

/**
 * Class ControllerAccountProductQuoteswkproductquotes
 * @property ModelAccountProductQuoteswkproductquotes $model_account_product_quotes_wk_product_quotes
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCustomerPartnerBuyerToSeller $model_customerpartner_BuyerToSeller
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 */
class ControllerAccountProductQuoteswkproductquotes extends Controller
{

    private $error = array();
    private $data = array();

    /**
     * 如果为日本取值0, 其他为 2
     * @var int $precision 精度
     */
    private $precision;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
    }

    /**
     * @deprecated 已作废，使用 controller\product\product.php showQuoteBidModal()方法
     */
    public function quoteProduct()
    {

        $this->language->load('account/product_quotes/wk_product_quotes');

        $data['text_tooltip']=$this->language->get('text_tooltip');
        $data['text_add_quote']=$this->language->get('text_add_quote');
        $data['text_close']=$this->language->get('text_close');
        $data['text_price']=$this->language->get('text_price');
        $data['text_quanity']=$this->language->get('text_quanity');
        $data['text_ask']=$this->language->get('text_request_message');
        $data['text_send']=$this->language->get('text_send');
        $data['text_error_mail']=$this->language->get('text_error_mail');
        $data['text_success_mail']=$this->language->get('text_success_mail');
        $data['text_error_option']=$this->language->get('text_error_option');
        $data['login'] = $this->url->link('account/login', '', true);
        $data['customer_id'] = $this->customer->getId();

        $data['quantity_placeholder'] = 'Min '.$this->config->get('wk_pro_quote_quantity');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        $data['action'] = $this->url->link('account/product_quotes/wk_product_quotes/add', '', true);

        $data['is_japan'] = false;
        if (!empty($this->customer->getCountryId()) && $this->customer->getCountryId() == JAPAN_COUNTRY_ID) {
            $data['is_japan'] = true;
        }

        $data['is_drop_ship_buyer'] = !$this->customer->isCollectionFromDomicile();

        $this->response->setOutput($this->load->view('account/product_quotes/add_product_quotes', $data));
    }

    public function add()
    {
        if ($this->request->serverBag->get('REQUEST_METHOD') == 'POST' AND $this->customer->getId()) {
            $json = array();

            $post = $this->request->post;
            $productId = $this->request->input->getInt('product_id');

            $this->load->language('account/product_quotes/wk_product_quotes');
            $this->load->language('checkout/cart');
            $this->load->model('account/product_quotes/wk_product_quotes');
            $this->load->model('catalog/product');
            $this->load->model('customerpartner/DelicacyManagement');
            //$productInfo = $this->model_catalog_product->getProduct($productId,$this->customer->getId());
            /**
             * @var float $productInfo['current_price'] 当前价格(已对是否需要减运费做过处理)
             */
            $productInfo = $this->model_customerpartner_DelicacyManagement->getProductInfoAndFreight($productId,$this->customer->getId());
            if (empty($productInfo)) {
                $response['error']['message'] = $this->language->get('text_error_add_to_cart');
                $this->response->returnJson($response);
            }

            //#31737 议价协议免税buyer当前价格不包含税价
            $productInfo['current_price'] = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($productInfo['seller_id'], $this->customer->getId(), $productInfo['current_price']);
            $productInfo['price'] = $productInfo['current_price'];

            if ($productInfo) {
                if (isset($post['option'])) {
                    $option = array_filter($post['option']);
                } else {
                    $option = array();
                }

                $productOptions = $this->model_catalog_product->getProductOptions($productId);

                foreach ($productOptions as $productOption) {
                    if ($productOption['required'] && empty($option[$productOption['product_option_id']])) {
                        $json['error']['option'][$productOption['product_option_id']] = sprintf($this->language->get('error_required'), $productOption['name']);
                    }
                }
                //recurring_id空表无记录
                //if (isset($post['recurring_id'])) {
                //    $recurringId = $post['recurring_id'];
                //} else {
                //    $recurringId = 0;
                //}
                //
                //$recurrings = $this->model_catalog_product->getProfiles($productInfo['product_id']);
                //
                //if ($recurrings) {
                //    $recurringIds = array();
                //
                //    foreach ($recurrings as $recurring) {
                //        $recurringIds[] = $recurring['recurring_id'];
                //    }
                //
                //    if (!in_array($recurringId, $recurringIds)) {
                //        $json['error']['recurring'] = $this->language->get('error_recurring_required');
                //    }
                //}
                // 校验库存
                if ((int)$post['quote_quantity'] > $productInfo['quantity'] ?? 0){
                    $json['error']['quantity'] = sprintf(
                        $this->language->get('text_quantity_over_in_stock'),
                        $productInfo['quantity'] ?? 0
                    );
                }

                if (!$json) {

                    $addkey = array();
                    $addkey['product_id'] = (int)$productId;

                    if ($option) {
                        $addkey['option'] = $option;
                    }

                    //if ($recurringId) {
                    //    $addkey['recurring_id'] = (int)$recurringId;
                    //}

                    $post['key'] = base64_encode(serialize($addkey));

                    /**
                     * 原价：计算discount
                     */
                    $this->load->model('customerpartner/BuyerToSeller');
                    $discount = $this->model_customerpartner_BuyerToSeller->getBuyerDiscount($this->customer->getId(), $productInfo['product_id']);
                    $post['origin_price'] = $productInfo['price'];
                    $post['discount'] = $discount;
                    $post['discount_price'] = round($discount * $productInfo['price'], $this->precision);
                    $post['quote_message']  = $post['quote_message'] ?? '';

                    $this->model_account_product_quotes_wk_product_quotes->insertQuoteData($post);

                    $json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . $productId), 'this product', $this->url->link('checkout/cart'));
                } else {
                    $json['redirect'] = $this->url->to(['product/product', 'product_id' => $productId]);
                }
            }

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));

        }
    }

}

