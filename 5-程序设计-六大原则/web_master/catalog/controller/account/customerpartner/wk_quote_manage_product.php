<?php

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountwkquotesadmin $model_account_wk_quotes_admin
 * @property ModelCatalogProduct $model_catalog_product
 */
class ControllerAccountCustomerpartnerWkQuoteManageProduct extends Controller
{
    private $error = array();
    private $data = array();

    public function index()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/wk_quote_manage_product', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->load->model('account/customerpartner');

        $data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

        if (!$data['chkIsPartner']) {
            $this->response->redirect($this->url->link('account/account'));
        }
        $this->load->model('account/wk_quotes_admin');
        $this->load->model('catalog/product');
        $this->load->language('account/customerpartner/wk_quotes_admin');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('manage_heading_title'),
            'href' => $this->url->link('account/customerpartner/wk_quote_manage_product', true)
        );

        // js css
        $this->document->addScript('catalog/view/javascript/layer/layer.js');

        // 获取钱币符号
        /** @var \Cart\Currency $currency */
        $currency = $this->currency;
        $currencyCode = session('currency');
        $data['money_currency'] = $currency->getSymbolLeft($currencyCode) ?: $currency->getSymbolRight($currencyCode);
        $data['money_currency_format'] = $currency->getSymbolLeft($currencyCode) . '%s' . $currency->getSymbolRight($currencyCode);
        // 日元需要特殊处理
        $data['isJapan'] = $currencyCode == 'JPY' ? 1 : 0;

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }
        $quote_products = $this->model_account_wk_quotes_admin->getSellerQuoteProducts();
        $data['isClosed'] = empty($quote_products) ? 1 : 0;
        $data['action'] = $this->url->link('account/customerpartner/wk_quote_manage_product');
        $data['post_action'] = $this->url->link('account/customerpartner/wk_quote_manage_product/saveQuoteSettings');
        $data['info_get_action'] = $this->url->link('account/customerpartner/wk_quote_manage_product/getQuoteProductsInfo');
        $data['info_get_action_ids'] = $this->url->link('account/customerpartner/wk_quote_manage_product/getQuoteProductsInfoByProductIds');
        $data['cancel'] = $this->url->link('account/account');
        $this->document->setTitle($this->language->get('manage_heading_title'));
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        if (
            $this->config->get('marketplace_separate_view') &&
            isset($this->session->data['marketplace_separate_view']) &&
            $this->session->data['marketplace_separate_view'] == 'separate'
        ) {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['margin'] = "margin-left: 18%";
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        }

        $this->response->setOutput($this->load->view('account/customerpartner/wk_quote_manage_product', $data));
    }

    // 获取议价商品信息
    public function getQuoteProductsInfo()
    {
        $this->load->model('account/wk_quotes_admin');
        /** @var ModelAccountwkquotesadmin $modelAccountWkQuotesAdmin */
        $modelAccountWkQuotesAdmin = $this->model_account_wk_quotes_admin;
        $this->load->model('catalog/product');
        /** @var ModelCatalogProduct $modelCatalogProduct */
        $modelCatalogProduct = $this->model_catalog_product;

        $data['products'] = [];
        $data['quantity'] = '1';
        $data['product_status'] = 0;
        $data['settings'] = '{}';
        $quote_products = $modelAccountWkQuotesAdmin->getSellerQuoteProducts();
        // details
        $quote_products_details = $modelAccountWkQuotesAdmin->getSellerQuoteDetails((int)$this->customer->getId());
        $ret = [];
        if (!empty($quote_products)) {
            $data['quantity'] = $quote_products['quantity'];
            $data['product_status'] = $quote_products['status'];
            // resolve details
            if (count($quote_products_details) > 0) {
                $temp = [];
                foreach ($quote_products_details as $item) {
                    $key = 'product_' . $item['product_id'];
                    if (isset($temp[$key])) {
                        array_push($temp[$key], [
                            'min_quantity' => $item['min_quantity'],
                            'max_quantity' => $item['max_quantity'],
                            'price' => $item['price'],
                        ]);
                    } else {
                        $temp[$key] = [
                            [
                                'min_quantity' => $item['min_quantity'],
                                'max_quantity' => $item['max_quantity'],
                                'price' => $item['price'],
                            ]
                        ];
                    }
                }
                $data['settings'] = json_encode($temp);
            }
            $products = $quote_products['product_ids'] ? explode(',', $quote_products['product_ids']) : [];
            foreach ($products as $key => $product_id) {
                if ($product_info = $modelCatalogProduct->getProductBaseInfo($product_id)) {
                    $ret[] = [
                        'product_id' => $product_info['product_id'],
                        'name' => $product_info['name'],
                        'sku' => $product_info['sku'],
                        'mpn' => $product_info['mpn'],
                        'price' => floatval($product_info['price'] ?: 0.00),
                    ];
                }
            }
            $data['products'] = $ret;
        }

        // 获取配置
        $data['product_quote_allowd'] = [
            'allowed_add' => $this->config->get('total_wk_pro_quote_seller_add'),
            'allowed_quantity' => $this->config->get('total_wk_pro_quote_seller_quantity')
        ];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function getQuoteProductsInfoByProductIds()
    {
        $this->load->model('catalog/product');
        /** @var ModelCatalogProduct $modelCatalogProduct */
        $modelCatalogProduct = $this->model_catalog_product;
        $productIds = $this->request->post['ids'] ?? [];
        $ret = [];
        foreach ($productIds as $key => $product_id) {
            if ($product_info = $modelCatalogProduct->getProductBaseInfo($product_id)) {
                $ret["product_{$product_id}"] = [
                    'product_id' => $product_info['product_id'],
                    'name' => $product_info['name'],
                    'sku' => $product_info['sku'],
                    'mpn' => $product_info['mpn'],
                    'price' => floatval($product_info['price'] ?: 0.00),
                ];
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($ret));
    }

    /**
     * 处理settings 替换到新表里面 后续删除代码
     * user：wangjinxin
     * date：2019/10/14 15:45
     * @throws Throwable
     */
    public function resolveQuoteSettings()
    {
        $db = $this->orm->getConnection();
        $db->transaction(function () use ($db) {
            $db->table('oc_wk_pro_quote')->delete();
            $db->table('oc_wk_pro_quote_details')->delete();
            $list = $db->table('oc_wk_pro_quote_seller')->get();
            $list->map(function ($item) use ($db) {
                $seller_id = $item->seller_id;
                $productIds = [];
                if (!empty($item->products) && @json_decode($item->products, true)) {
                    $productIds = json_decode($item->products, true);
                }
                $productSettings = [];
                if (!empty($item->settings) && @json_decode($item->settings, true)) {
                    $productSettings = json_decode($item->settings, true);
                }
                $detailsList = [];
                if (count($productSettings) > 0) {
                    array_map(function ($id) use ($productSettings, $seller_id, &$detailsList) {
                        $key = "product_{$id}";
                        if (!isset($productSettings[$key]) || empty($productSettings[$key])) return;
                        $info = $productSettings[$key];
                        array_walk($info, function (&$item) use ($id, $seller_id) {
                            $item['seller_id'] = $seller_id;
                            $item['sort_order'] = 0;
                            $item['product_id'] = $id;
                        });
                        $detailsList = array_merge($detailsList, $info);
                    }, $productIds);
                }
                $db->table('oc_wk_pro_quote')->insert([
                    'seller_id' => $seller_id,
                    'product_ids' => join(',', $productIds),
                    'quantity' => $item->quantity,
                    'status' => $item->status,
                ]);
                $db->table('oc_wk_pro_quote_details')->insert($detailsList);
            });
        });
    }

    // 存储quote设置
    public function saveQuoteSettings()
    {
        $this->load->language('account/customerpartner/wk_quotes_admin');
        $this->load->model('account/wk_quotes_admin');
        /** @var ModelAccountwkquotesadmin $modelAccountWkQuotesAdmin */
        $modelAccountWkQuotesAdmin = $this->model_account_wk_quotes_admin;
        $this->load->model('catalog/product');
        /** @var ModelCatalogProduct $modelCatalogProduct */
        $modelCatalogProduct = $this->model_catalog_product;

        $product_quote = $this->request->post['ids'] ?? [];
        $status = $this->request->post['status'] ?? $this->config->get('total_wk_pro_quote_product_status');
        $quantity = $this->request->post['min_quantity'] ?? $this->config->get('total_wk_pro_quote_quantity');
        $settings = $this->request->post['settings'] ?? [];
        $res = true;

        if ($this->config->get('total_wk_pro_quote_seller_quantity') && $quantity < 1) {
            $this->error['warning'] = $this->language->get('invalid_quantity');
            $res = false;
        } else {
            // 校验base price
            foreach ($product_quote as $productId) {
                $productKey = "product_{$productId}";
                if (!isset($settings[$productKey])) {
                    continue;
                }
                $productSetting = $settings[$productKey];
                $productInfo = $modelCatalogProduct->getProductBaseInfo((int)$productId);
                foreach ($productSetting as $val) {
                    if (floatval($val['price']) >= floatval($productInfo['price'])) {
                        $this->error['warning'] = $this->language->get('text_price_check_fail');
                        $res = false;
                    }
                }
            }
            if ($res) {
                $res = $modelAccountWkQuotesAdmin->editSellerProduct([
                    'product_quote' => $product_quote,
                    'quantity' => $quantity,
                    'status' => $status,
                    'settings' => $settings
                ]);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        if ($res) {
            $ret = ['code' => 0, 'message' => $this->language->get('text_success'),];
        } else {
            $ret = ['code' => 1, 'message' => $this->error['warning'] ?? $this->language->get('text_fail'),];
        }
        $this->response->setOutput(json_encode($ret));
    }

    /**
     * 关闭议价
     */
    public function closeQuote()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/wk_quote_manage_product'));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('account/customerpartner');
        $data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();
        if (!$data['chkIsPartner']) {
            $this->response->redirect($this->url->link('account/account'));
        }
        $this->load->model('account/wk_quotes_admin');
        /** @var ModelAccountwkquotesadmin $modelAccountWkQuotesAdmin */
        $modelAccountWkQuotesAdmin = $this->model_account_wk_quotes_admin;
        $modelAccountWkQuotesAdmin->delete($this->customer->getId());
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode([
            'message' => 'Quote service is closed!',
            'code' => 1
        ]));
    }
}

