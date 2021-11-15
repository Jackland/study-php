<?php

use App\Enums\Product\ProductType;
use App\Helper\CountryHelper;
use App\Models\Product\Product;
use App\Repositories\Product\ProductPriceRepository;
use App\Repositories\Seller\SellerProductRatioRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Helper\MoneyHelper;

/**
 * Class ControllerAccountWishList
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelAccountWishlist $model_account_wishlist
 * @property ModelCommonCategory $model_common_category
 * @property ModelExtensionModulePrice $model_extension_module_price
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountWishList extends Controller
{
    private $country_map = [
        'JPN'  => 107,
        'GBR'  => 222,
        'DEU'  => 81,
        'USA'  => 223
    ];

    /**
     * @var ModelAccountWishlist $model
     */
    private $model;

    public function __construct($registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            if (is_ajax()) {
                $this->response->returnJson(['redirect' => $this->url->link('account/login')]);
            } else {
                session()->set('redirect', $this->url->link('account/wishlist'));
                $this->response->redirect($this->url->link('account/login'));
            }
        }

        $this->load->language('account/wishlist');
        $this->load->model('common/category');
        $this->load->model('account/wishlist');
        $this->model = $this->model_account_wishlist;
    }

    public function index()
    {
        $data = $this->framework();
        $data['params'] = $this->request->get;
        $data['date'] = date('Y-m-d H:i:s', time());
        $data['current_group_id'] = get_value_or_default($this->request->request, 'group_id', 0);
        $this->response->setOutput($this->load->view('account/wishlist/index', $data));
    }

    /**
     * 库存订阅列表
     */
    public function list()
    {
        $customer_id = customer()->getId();
        $data['page'] = $this->request->get('page', 1);
        $data['page_limit'] = $this->request->get('page_limit', 15);
        $data['current_group_id'] = $this->request->get('group_id', 0);
        $data['flag'] = $this->request->get('flag') === 'unavailable' ? '1' : '0';
        // 获取所有的订阅数量
        $data['all_total'] = $this->model_account_wishlist->getWishTotal($customer_id);
        $filter = array_merge($data, $this->request->attributes->all());
        // 产品分类
        $categories = $this->model->getCategories($customer_id);
        // 判断是不是有效tab
        if ($this->request->get('flag') == 'unavailable') {
            $data['list'] = $this->model_account_wishlist->getUnavailableWishList($customer_id, $filter);
            // 获取各个分组的数量
            $data['group_count'] = $this->model_account_wishlist->getUnavailableWishList($customer_id, array_merge($filter, ['groups' => 1]));
            $data['list']['page_id'] = 'un-pagination';
            // 无效产品分类特殊处理
            $data['unavailable_categories'] = $this->resolveUnAvailableCategory($categories);
        } else {
            $data['list'] = $this->model_account_wishlist->getAvailableWishList($customer_id, $filter);
            // 获取各个分组的数量
            $data['group_count'] = $this->model_account_wishlist->getAvailableWishList($customer_id, array_merge($filter, ['groups' => 1]));
            $data['productList'] = $this->model->getProductSkuInWish();
            //获取收藏的店铺名称 + 产品列表API
            $data['storeList'] = $this->model->getStoreInWish();
            // 有效产品分类特殊处理
            $data['available_categories'] = $this->resolveAvailableCategory($categories);
        }
        //大客户折扣
        $marketingDiscountRepository = app(MarketingDiscountRepository::class);
        foreach ($data['list']['list'] as &$datum) {
            $datum['new_price_discount'] = 0;
            $datum['big_client_discount'] = $marketingDiscountRepository->getBuyerDiscountInfo(0, $datum['seller_id'], customer()->getId());
            //精细化价格不打折扣
            if ($datum['big_client_discount'] > 0 && $datum['is_delicacy'] == 0 && $datum['product_type'] == ProductType::NORMAL) {
                //日本小数点后进位，其他国家第三位小数点进位
                $rightOperand = (int)$datum['big_client_discount'];
                $datum['old_price_show'] = $datum['price_show'];
                $datum['price_show_discount'] = MoneyHelper::upperAmount(bcmul($datum['new_price'], $rightOperand / 100, 3), customer()->isJapan() ? 0 : 2);
                $datum['price_show'] = $this->currency->formatCurrencyPrice($datum['price_show_discount'], session('currency'));

                //下降 or 上升的价格，用折扣后的价格对比
                $datum['price_change'] = bcsub(round($datum['price_show_discount'], 2), $datum['price'], 2);
                $datum['price_change_show'] = $this->currency->formatCurrencyPrice(abs($datum['price_change']), session('currency'));

                //计算total
                $datum['discount_amount'] = MoneyHelper::upperAmount(bcsub($datum['new_price'], $datum['price_show_discount'], 3), customer()->isJapan() ? 0 : 2);
                $datum['total_price'] = MoneyHelper::upperAmount(bcsub($datum['total_price'], $datum['discount_amount'], 3), customer()->isJapan() ? 0 : 2);
                $datum['total_price_show'] = $this->currency->formatCurrencyPrice($datum['total_price'], session('currency'));
            } else {
                $datum['big_client_discount'] = 0; //页面上会根据这个值判断是否展示 xx OFF
            }
        }
        //获取所有库存订阅商品的店铺
        $data['seller_list'] = $this->model->getWishSellerList($customer_id, $data['flag']);

        $data['group_count'] = $this->model->getWishGroupCountList($data['group_count']);
        $data['group_count_all'] = $data['group_count'] ? array_sum(array_column($data['group_count'], 'count')) : 0;
        $data['page_view'] = $this->load->controller('common/pagination', $data['list']);
        $data['params'] = $this->request->attributes->all();
        // 是否是上门取货用户
        $data['isCollectionFromDomicile'] = $this->customer->isCollectionFromDomicile();
        // 是否是运送仓用户
        $data['has_cwf_freight'] = $this->customer->has_cwf_freight();
        // 是否是美国
        $data['is_usa'] = $this->customer->isUSA();
        $data['date'] =  date('Y-m-d H:i:s',time());
        $data['freight_rate'] = $this->config->get('cwf_base_cloud_freight_rate');//1363 参数废弃
        //1363 云送仓增加超重附加费
        $data['cwf_overweight_surcharge_rate'] = ($this->config->get('cwf_overweight_surcharge_rate') * 100) . '%';//超重附加费费率
        $data['cwf_overweight_surcharge_min_weight'] = $this->config->get('cwf_overweight_surcharge_min_weight');//超重附加费最低单位体积
        if ($this->customer->isPartner()) {
            $data['cwf_info_id'] = $this->config->get('cwf_help_id');
        } else {
            $data['cwf_info_id'] = $this->config->get('cwf_help_information_id');
        }
        // currency
        $data['currency'] = session('currency');

        return $this->render('account/wishlist/tab_list', $data);
    }

    // 获取有效收藏品的所有可用类别
    private function resolveAvailableCategory($categories)
    {
        $g = $this->model->buildAvailableWishListQuery($this->customer->getId())
            ->selectRaw('group_concat(ptc.category_id) as category_ids')
            ->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'cw.product_id')
            ->groupBy(['cw.product_id'])
            ->cursor();
        $resolved_ids = [];
        $re_categories = $categories;
        foreach ($g as $item) {
            if ($item->category_ids) {
                foreach (explode(',', $item->category_ids) as $c_id) {
                    $p_cats = $this->model_common_category->getParentCategories((int)$c_id);
                    foreach ($p_cats as $cat) {
                        if (!in_array($cat['category_id'], $resolved_ids)) {
                            $this->setResolveFlag($re_categories, $cat['category_id']);
                            $resolved_ids[] = (int)$cat['category_id'];
                        }
                    }
                }
            }
        }
        $this->resolveCats($re_categories);
        return $re_categories;
    }

    // 获取无效收藏品的所有可用类别
    private function resolveUnAvailableCategory($categories)
    {
        $g = $this->model->buildUnavailableWishListQuery($this->customer->getId())
            ->selectRaw('group_concat(ptc.category_id) as category_ids')
            ->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'cw.product_id')
            ->groupBy(['cw.product_id'])
            ->cursor();
        $resolved_ids = [];
        $re_categories = $categories;
        foreach ($g as $item) {
            if ($item->category_ids) {
                foreach (explode(',', $item->category_ids) as $c_id) {
                    $p_cats = $this->model_common_category->getParentCategories((int)$c_id);
                    foreach ($p_cats as $cat) {
                        if (!in_array($cat['category_id'], $resolved_ids)) {
                            $this->setResolveFlag($re_categories, $cat['category_id']);
                            $resolved_ids[] = (int)$cat['category_id'];
                        }
                    }
                }
            }
        }
        $this->resolveCats($re_categories);
        return $re_categories;
    }

    // 给每个类别加上特殊的标志
    private function setResolveFlag(&$categories, int $category_id)
    {
        foreach ($categories as $k => &$v) {
            if ($v['category_id'] == $category_id) {
                $v['resolve_flag'] = 1;
                break;
            } else {
                if (isset($v['children']) && !empty($v['children'])) {
                    $this->setResolveFlag($v['children'], $category_id);
                }
            }
        }
    }

    // 裁剪类别 去除为空的内别
    private function resolveCats(&$categories)
    {
        foreach ($categories as $k => &$v) {
            if ($v['resolve_flag'] == 0) {
                unset($categories[$k]);
            } else {
                if (isset($v['children']) && !empty($v['children'])) {
                    $this->resolveCats($v['children']);
                }
                if (isset($v['children']) && empty($v['children'])) {
                    unset($v['children']);
                }
            }
        }
    }

    public function framework()
    {
        $this->document->setTitle('My Saved Items');
        $this->document->addScript('catalog/view/javascript/echarts/echarts.min.js');
        $this->load->language('account/wishlist');
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home'),
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/wishlist')
            ]
        ];
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        return $data;
    }

    /**
     * #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
     * @param $price
     * @param $productType
     * @param $sellerId
     * @return float|mixed|string|void
     */
    private function generateActualPrice($price, $productType, $sellerId)
    {
        if ($productType != ProductType::NORMAL) {
            return $price;
        }

        return app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($sellerId), customer()->getModel(), $price);
    }

    /**
     * 处理欧洲特殊buyer的
     * @param $price
     * @param $productType
     * @param $sellerId
     * @param $countryId
     * @return float|mixed
     */
    private function generateSpecialGroupIdPrice($price, $productType, $sellerId, $countryId)
    {
        if ($productType != ProductType::NORMAL) {
            $price = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($sellerId, $price, $countryId);
        } else {
            [, $price,] = app(ProductPriceRepository::class)->getProductTaxExemptionPrice(intval($sellerId), $price);
        }

        return $price;
    }

    public function add()
    {
        $this->load->language('account/wishlist');
        $json = array();
        if (isset($this->request->post['product_id'])) {
            $product_id = $this->request->post['product_id'];
        } else {
            $product_id = 0;
        }
        $this->load->model('catalog/product');
        $customFields = $this->customer->getId();
        $customerCountry = $this->customer->getCountryId();
        $product_info = $this->model->getProduct($product_id, $customFields);
        if ($product_info) {
            if ($this->customer->isLogged()) {
                // Edit customers cart
                $this->load->model('account/wishlist');
                //只能订阅建立联系的库存
                $result = $this->model_account_wishlist->checkSellerConect($product_info['customer_id']);
                if ($result) {
                    // add by xxli
                    $discountResult = $this->model_catalog_product->getDiscount($customFields, $product_info['customer_id']);
                    if ($discountResult) {
                        $price = $this->model_catalog_product->getDiscountPrice($product_info['price'], $discountResult);
                        // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                        if ($customerCountry && $this->customer->getGroupId() == 13) {
                            $price = $this->generateSpecialGroupIdPrice($price, $product_info['product_type'], $product_info['customer_id'], $customerCountry);
                        } else {
                            $price = $this->generateActualPrice($price, $product_info['product_type'], $product_info['customer_id']);
                        }
                    } else {
                        // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                        $price = $this->generateActualPrice($product_info['price'], $product_info['product_type'], $product_info['customer_id']);
                    }
                    if (session('currency') == 'JPY') {
                        $price = round($price, 0);
                    }

                    //大客户折扣，精细化不参与大客户折扣
                    if ($product_info['product_type'] == ProductType::NORMAL) {
                        if ($product_info['is_delicacy'] == 0) {
                            $bigClientDiscount = app(MarketingDiscountRepository::class)->getBuyerDiscountInfo($product_id, 0, customer()->getId());
                            if ($bigClientDiscount > 0) {
                                $price = MoneyHelper::upperAmount(bcmul($price, $bigClientDiscount / 100, 3), customer()->isJapan() ? 0 : 2);
                            }
                        }
                    }

                    //获取额外的的信息
                    $extra['is_delicacy'] = $product_info['is_delicacy'];
                    $extra['original_price'] = $product_info['price'];
                    // 已经是付的包含打包费的
                    $extra['freight'] = $product_info['freight_per'];

                    // end xxli
                    $this->model_account_wishlist->addWishlist($this->request->post['product_id'], $price, $extra);

                    $json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . (int)$this->request->post['product_id']), $product_info['sku'], $this->url->link('product/product', 'product_id=' . (int)$this->request->post['product_id']), $product_info['name'], $this->url->link('account/wishlist'));
                    $json['totalNum'] = $this->model_account_wishlist->getTotalWishlist();
                    $json['total'] = sprintf($this->language->get('text_wishlist'), $json['totalNum']);
                } else {
                    $json['success'] = sprintf($this->language->get('wishList_error'));
                    $json['totalNum'] = $this->model_account_wishlist->getTotalWishlist();
                    $json['total'] = sprintf($this->language->get('text_wishlist'), $json['totalNum']);
                }
            } else {
                if (!isset($this->session->data['wishlist'])) {
                    session()->set('wishlist', array());
                }

                $this->session->data['wishlist'][] = $this->request->post['product_id'];

                session()->set('wishlist', array_unique($this->session->data['wishlist']));

                $json['success'] = sprintf($this->language->get('text_login'), $this->url->link('account/login', '', true), $this->url->link('account/register', '', true), $this->url->link('product/product', 'product_id=' . (int)$this->request->post['product_id']), $product_info['name'], $this->url->link('account/wishlist'));
                $json['totalNum'] = count(session('wishlist', []));
                $json['total'] = sprintf($this->language->get('text_wishlist'), $json['totalNum']);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /*
     * 批量添加
     * */
    public function batchAdd()
    {
        if (!$this->customer->isLogged()) {
            return $this->json('false');
        }

        $type = request('type');
        $product_str = request('product_str');
        if (0 == $type && null != $product_str) {
            $product_total_str = $product_str;
        } else {
            $sessionKey = 'search_' . request('path', '');
            $sessionData = session($sessionKey);
            if (!empty($sessionData) && !empty($sessionData['product_total_str'] ?? [])) {
                $product_total_str = $sessionData['product_total_str'];
            } else {
                return $this->json('false');
            }
        }

        $product_arr = explode(',', $product_total_str);
        $this->load->model('catalog/product');
        $this->load->model('account/wishlist');
        $customFields = $this->customer->getId();
        $customerCountry = $this->customer->getCountryId();
        $successNum = $failNum = 0;
        foreach ($product_arr as $product_id) {
            $product_info = $this->model->getProduct($product_id, $customFields);
            if ($product_info) {
                //只能订阅建立联系的库存
                $result = $this->model_account_wishlist->checkSellerConect($product_info['customer_id']);
                if ($result) {
                    $discountResult = $this->model_catalog_product->getDiscount($customFields, $product_info['customer_id']);
                    if ($discountResult) {
                        $price = $this->model_catalog_product->getDiscountPrice($product_info['price'], $discountResult);
                        // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                        if ($customerCountry && $this->customer->getGroupId() == 13) {
                            $price = $this->generateSpecialGroupIdPrice($price, $product_info['product_type'], $product_info['customer_id'], $customerCountry);
                        } else {
                            $price = $this->generateActualPrice($price, $product_info['product_type'], $product_info['customer_id']);
                        }
                    } else {
                        // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                        $price = $this->generateActualPrice($product_info['price'], $product_info['product_type'], $product_info['customer_id']);;
                    }
                    if (session('currency') == 'JPY') {
                        $price = round($price, 0);
                    }
                    //获取额外的的信息
                    $extra['is_delicacy'] = $product_info['is_delicacy'];
                    $extra['original_price'] = $product_info['price'];
                    $extra['freight'] = $product_info['freight_per'];
                    $this->model_account_wishlist->addWishlist($product_id, $price, $extra);
                    $successNum++;
                } else {
                    $failNum++;
                }
            } else {
                $failNum++;
            }
        }

        return $this->json( ['success_num' => $successNum, 'fail_num' => $failNum,]);
    }


    public function remove()
    {
        $this->load->language('account/wishlist');
        $this->load->language('account/wishlist');

        $this->load->model('account/wishlist');

        if (isset($this->request->request['productList'])) {
            $‌‌productList = $this->request->request['productList'];
            foreach ($‌‌productList as $product_id) {
                $this->model_account_wishlist->deleteWishlist($product_id);
            }
            session()->set('success', $this->language->get('text_remove'));
        } elseif (isset($this->request->request['product_id'])) {
            $this->model_account_wishlist->deleteWishlist($this->request->request['product_id']);
            $json['text'] = $this->language->get('text_remove');
            $json['totalNum'] = $this->model_account_wishlist->getTotalWishlist();
            $json['total'] = sprintf($this->language->get('text_wishlist'), $json['totalNum']);
        } else {
            session()->set('error', $this->language->get('text_warning'));
        }
        $json['success'] = true;
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    //修改用户库存提醒阀值
    public function remind()
    {
        $this->load->language('account/wishlist');
        $this->load->language('account/wishlist');

        $this->load->model('account/wishlist');
        if (isset($this->request->request['allProductsRemind'])) {
            //整个产品设置库存阀值
            $allProductsRemind = $this->request->request['allProductsRemind'];
            if ($allProductsRemind != '') {
                $allProductsRemind = $this->request->request['allProductsRemind'];
                $this->model_account_wishlist->addAllProductsRemind($allProductsRemind);
            } else {
                $this->model_account_wishlist->removeAllProductsRemind();
            }
        }
        if (isset($this->request->request['sellerNameRemind'])) {
            $sellerNameRemind = $this->request->request['sellerNameRemind'];
            //对seller设置库存阀值
            if ($sellerNameRemind != '') {
                $this->model_account_wishlist->deleteSellerStoreRemind();
                foreach ($sellerNameRemind as $sellerRemind) {
                    if ($sellerRemind['seller_id'] != '' && $sellerRemind['remindQty'] != '') {
                        $this->model_account_wishlist->addSellerStoreRemind($sellerRemind['seller_id'], $sellerRemind['remindQty']);
                    }
                }
            }
        }
        //对产品设置库存阀值
        if (isset($this->request->request['item_product_id'])) {
            $product_id = $this->request->request['item_product_id'];
        }
        if (isset($this->request->request['itemCode_remind_qty'])) {
            $remind_qty = $this->request->request['itemCode_remind_qty'];
        }
        if (isset($product_id) && $product_id != '' && isset($remind_qty)) {
            if ($remind_qty != '') {
                $this->model_account_wishlist->addItemCodeRemind($product_id, $remind_qty);
            } else {
                $this->model_account_wishlist->removeItemCodeRemind($product_id);
            }
        }
//        $this->response->redirect($this->url->link('account/wishlist', '', true));
        $json['success'] = true;
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // api 获取历史 调用
    public function getPriceHistory()
    {
        $this->load->language('account/wishlist');
        $this->load->model('account/wishlist');
        $this->load->model('catalog/product');
        $this->load->model('extension/module/price');
        $product_id = $this->request->input->get('product_id');
        $customer_id = (int)$this->customer->getId();
        $customerCountry = $this->customer->getCountryId();

        $product = Product::query()->find($product_id);

        //产品币制
        $currency = $this->session->get('currency');
        //获取最近的设置可见的修改时间
        $effective_time = $this->model_account_wishlist->getLastProductDisplayTime($product_id, $customer_id);
        //获取加入订阅库存的时间
        $wishlistAdd = $this->model_account_wishlist->getWishListAddTime($product_id, $customer_id);
        $wishlistAdd['is_participate_calculation'] = true;
        $result = $this->model_account_wishlist->getPriceHistory($product_id, $customer_id);
        //价格乘以折扣
        foreach ($result as $key => $resultInfo) {
            $discountResult = $this->model_account_wishlist->getDiscountByProductId($customer_id, $product_id);
            if ($discountResult) {
                $price = $this->model_catalog_product->getDiscountPrice($resultInfo['price'], $discountResult);
            } else {
                $price = $resultInfo['price'];
            }
            $result[$key]['price'] = $price;
            $result[$key]['is_participate_calculation'] = false;
        }
        $delicacyResult = $this->model_account_wishlist->getDelicacyPrice($product_id, $customer_id, $effective_time);
        $effectTime = strtotime($effective_time);
        array_push($result, $wishlistAdd);
        $resultShow = [];
        foreach ($result as $priceHistory) {
            if (
                isset($priceHistory['add_date'])
                && $priceHistory['add_date'] >= $wishlistAdd['add_date']
                && $priceHistory['add_date'] >= $effectTime
            ) {
                array_push($resultShow, $priceHistory);
            }
        }
        foreach ($delicacyResult as $delicacy) {
            $discountResult = $this->model_account_wishlist->getDiscountByProductId($customer_id, $product_id);
            if ($delicacy['type'] == 1 or $delicacy['type'] == 3) {
                $price = $discountResult
                    ? $this->model_catalog_product->getDiscountPrice($delicacy['current_price'], $discountResult)
                    : 0;
                $delicacyArray = [
                    'price' => $price,
                    'add_date' => $delicacy['add_time']
                ];
            } else {
                //查看精细化过期或删除时候价格
                $deletePrice = $this->model_account_wishlist->getDelicacyDeletePrice($product_id, $delicacy['add_time']);
                    $price = $discountResult
                        ? $this->model_catalog_product->getDiscountPrice($deletePrice, $discountResult)
                        : 0;
                $delicacyArray = [
                    'price' => $price,
                    'add_date' => $delicacy['add_time']
                ];
            }
            $delicacyArray['is_participate_calculation'] = false;
            array_push($resultShow, $delicacyArray);
        }
        // 加入当前价格
        $dm_info = $this->model_catalog_product->getDelicacyManagementInfoByNoView($product_id, $customer_id);
        $current_price = $this->orm
            ->table('oc_product')
            ->where('product_id', $product_id)
            ->value('price');
        if ($dm_info && $dm_info['product_display'] && isset($dm_info['current_price'])) {
            $current_price = floatval($dm_info['current_price']);
        }
        array_push($resultShow, [
            'price' => $current_price,
            'add_date' => time(),
            'is_participate_calculation' => false,
        ]);
        $european_price_change = false;
        if ($customerCountry) {
            if ($this->customer->getGroupId() == 13) {
                $european_price_change = true;
            }
        }
        //对数组排序
        foreach ($resultShow as $key => $row) {
            $time_add[$key] = $row['add_date'];
        }
        array_multisort($time_add, SORT_ASC, $resultShow);
        $sku = $this->model_account_wishlist->getProduct($product_id);
        foreach ($resultShow as $key => $row) {
            // 价格保留位数
            if ($currency == 'JPY') {
                $resultShow[$key]['price'] = round($row['price'], 0);
            }
            // 时区转换
            $resultShow[$key]['add_date_format'] = currentZoneDate(
                $this->session,
                date('Y-m-d H:i:s', $row['add_date'])
            );
            if ($key != 0 && $european_price_change) {
                // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                $resultShow[$key]['price'] = $this->generateSpecialGroupIdPrice($row['price'], $product->product_type, $sku['customer_id'], $customerCountry);
                $row['is_participate_calculation'] = true;
            }

            // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
            if (!$row['is_participate_calculation']) {
                $resultShow[$key]['price'] = $this->generateActualPrice($resultShow[$key]['price'], $product->product_type, $sku['customer_id']);
            }
        }
        $json['success'] = true;
        $json['data'] = $resultShow;
        $json['currency'] = $this->currency->getSymbolLeft($currency) ?: $this->currency->getSymbolRight($currency);
        $json['item_code'] = $sku['sku'];
        return $this->response->json($json);
    }


    public function downExcel()
    {
        $customer_id = $this->customer->getId();

        // 因库存订阅下载的数据是从getProductCategoryInfo（my seller一致），即这边只需获取所有排序好的产品id
        $filter = request()->query->all();
        $filter['fulfillment'] = 1;
        $query = $this->model_account_wishlist->buildAvailableWishListQuery($customer_id, $filter);
        if (isset($filter['order_by_store'])) {
            $query->orderBy('ctc.screenname', $filter['order_by_store']);
        } elseif (isset($filter['order_by_qty'])) {
            $query->orderBy('op.quantity', $filter['order_by_qty']);
        } else {
            $query->orderBy('cw.date_added', 'DESC');
        }
        $productIds = $query->pluck('product_id')->toArray();

        $file_name = "MySavedValidItems" . date("Ymd", time()) . ".csv";
        // #35558 库存订阅下载与myseller页面下载统一
        $map = [];
        if(!empty($productIds)){
            /** @var ModelCatalogProduct $modelCatalogProduct */
            $modelCatalogProduct = load()->model('catalog/product');
            $data = $modelCatalogProduct->getProductCategoryInfo($productIds, $customer_id);
            $data = array_column($data, null, 'product_id');
            foreach ($productIds as $productId) {
                if (isset($data[$productId])) {
                    $map[$productId] = $data[$productId];
                }
            }
        }
        /** @var ModelToolCsv $modelToolCsv */
        $modelToolCsv = load()->model('tool/csv');
        $modelToolCsv->getProductCategoryCsv($file_name, $map);
    }


    public function downloadWishListProduct()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/wishlist', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        if (isset($this->request->get['name'])) {
            $filter_input_name = $this->request->get['name'];
        } else {
            $filter_input_name = null;
        }
        if (isset($this->request->get['sort'])) {
            $filter_input_sort = $this->request->get['sort'];
        } else {
            $filter_input_sort = null;
        }
        if (isset($this->request->get['type'])) {
            $filter_input_type = $this->request->get['type'];
        } else {
            $filter_input_type = null;
        }
        //14408上门取货账号一键下载超大件库存分布列表
        if (isset($this->request->get['is_ltl'])) {
            $filter_input_ltl = $this->request->get['is_ltl'];
        } else {
            $filter_input_ltl = null;
        }
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        $country_id = $this->customer->getCountryId();
        $customer_id = $this->customer->getId();
        $this->load->language('account/wishlist');
        $this->load->model('catalog/product');
        $this->load->model('account/wishlist');
        $filter_data = array(
            "filter_input_sort" => $filter_input_sort,
            "filter_input_name" => $filter_input_name,
            "filter_input_ltl" => $filter_input_ltl,
        );
        $products = $this->model_account_wishlist->getDownloadWishListProduct($customer_id, $filter_data);
        //云送仓运费
        if ($this->customer->has_cwf_freight()) {
            $product_id_list = array_column($products, 'product_id');
            $cwf_freight = $this->freight->getFreightAndPackageFeeByProducts($product_id_list);
            foreach ($products as $k => &$v) {
                if (isset($cwf_freight[$v['product_id']])) {
                    if ($v['combo_flag'] == 0) {   //非combo
                        $v['cwf_freight'] = $cwf_freight[$v['product_id']]['freight'] + $cwf_freight[$v['product_id']]['package_fee'];
                    } else {    //combo
                        $v['cwf_freight'] = 0;
                        foreach ($cwf_freight[$v['product_id']] as $cwf_k => $cwf_v) {
                            $v['cwf_freight'] += $cwf_v['freight'] * $cwf_v['qty'] + $cwf_v['package_fee'] * $cwf_v['qty'];
                        }
                    }
                } else {
                    $v['cwf_freight'] = '';
                }
            }
        }
        $customFields = $this->customer->getId();
        if (isset($products) && !empty($products)) {
            foreach ($products as $key => $product) {
                // add by xxli
                $discountResult = $this->model_catalog_product->getDiscount($customFields, $product['customer_id']);

                if ($discountResult) {
                    $price = $this->model_catalog_product->getDiscountPrice($product['price'], $discountResult);
                    // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                    if ($country_id && $this->customer->getGroupId() == 13) {
                        $price_calc = $this->generateSpecialGroupIdPrice($price, $product['product_type'], $product['customer_id'], $country_id);
                    } else {
                        $price_calc = $this->generateActualPrice($price, $product['product_type'], $product['customer_id']);
                    }
                } else {
                    // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                    $price_calc = $this->generateActualPrice($product['price'], $product['product_type'], $product['customer_id']);
                }
                $price = $this->currency->formatCurrencyPrice($price_calc, session('currency'));
                // end xxli
                $products[$key]['price'] = $price;
                if ($isCollectionFromDomicile) {
                    $freight_per_calc = $product['package_fee'];
                } else {
                    $freight_per_calc = $product['freight'] + $product['package_fee'];
                }
                $products[$key]['freight_per'] = $this->currency->formatCurrencyPrice($freight_per_calc, session('currency'));
                $products[$key]['total_amount'] = $this->currency->formatCurrencyPrice($freight_per_calc + $price_calc, session('currency'));
                //测试店铺、服务店铺、保证金店铺的产品、期货/现货保证金定金类产品在购物车中不显示转移到云送仓购物车的按钮
                $productInfo = $this->model_account_wishlist->getProductInfo($product['product_id']);
                $can_add_cart = true;
                if($productInfo['product_type']!=0 && $productInfo['product_type']!=3){
                    $can_add_cart = false;
                } elseif (($productInfo['customer_group_id'] == 23 || in_array($productInfo['customer_id'], array(340, 491, 631, 838)))) {
                    $can_add_cart = false;
                }
                if ($this->customer->has_cwf_freight()) {
                    $products[$key]['cwf_freight_per'] = $this->currency->formatCurrencyPrice($can_add_cart ? $products[$key]['cwf_freight'] : 0, session('currency'));
                    $products[$key]['cwf_total_amount'] = $this->currency->formatCurrencyPrice(($can_add_cart ? $products[$key]['cwf_freight'] : 0) + $price_calc, session('currency'));
                }
            }
        }
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("Ymd", time()), 'YmdHis');
        //12591 end
        if ($filter_input_type == 1) {
            $fileName = "MySavedValidItems" . date("Ymd", time()) . ".csv";
            header("Content-Type: text/csv");
            header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
            header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
            header('Expires:0');
            header('Pragma:public');
            echo chr(239) . chr(187) . chr(191);

            $fp = fopen('php://output', 'a+');
            //14408上门取货账号一键下载超大件库存分布列表
            if ($isCollectionFromDomicile) {
                $head = ['Store Name', 'Item Code', 'Product Name', 'Brand', 'Oversized', 'Qty Available'];
                $warehouse_code_list = $this->orm->table('tb_warehouses')
                    ->where('country_id', $this->customer->getCountryId())
                    ->orderBy('WarehouseCode', 'asc')->pluck('WarehouseCode');
                if (count($warehouse_code_list)) {
                    foreach ($warehouse_code_list as $key => $value) {
                        $head[] = "\t" . $value;
                    }
                }
                $head[] = 'Unit Price';
                $head[] = 'Freight Per Unit';
                $head[] = 'Total';

            } else {
                $head = ['Store Name', 'Item Code', 'Product Name', 'Brand', 'Oversized', 'Qty Available', 'Unit Price', 'Drop Shipping Freight Per Unit', 'Drop Shipping Total Cost'];
                if ($this->customer->has_cwf_freight()) {
                    $head[] = 'Cloud Wholesale Fulfillment Freight Cost Per Unit ($USD)';
                    $head[] = 'Cloud Wholesale Fulfillment Total Cost';
                }
            }

            //foreach ($head as $i => $v) {
            //    // CSV的Excel支持GBK编码，一定要转换，否则乱码
            //    $head [$i] = iconv('utf-8', 'gb2312', $v);
            //}
            fputcsv($fp, $head);

            if (isset($products) && !empty($products)) {
                if ($isCollectionFromDomicile) {
                    $this->load->model('extension/module/product_show');
                    /** @var ModelExtensionModuleProductShow $productShowModel */
                    $productShowModel = $this->model_extension_module_product_show;
                }
                foreach ($products as $product) {

                    $country = session('country');
                    $checkResult = $this->model_catalog_product->checkProduct($product['product_id'], $country, $customFields);
                    if (!$checkResult) {
                        continue;
                    }
                    //获取是否超大件
                    $is_oversize = $this->model_catalog_product->checkIsOversizeItem($product['product_id']) == true ? 'Yes' : 'No';

                    //判断订阅的库存是否还和卖家关联
                    $customer_id = $product['customer_id'];
                    $sellerConect = $this->model_account_wishlist->checkSellerConect($customer_id);
                    if ($product['status'] == 1 && $sellerConect && $product['c_status'] == 1 && $product['buyer_flag'] == 1) {
                        if ($isCollectionFromDomicile) {
                            $content = [
                                html_entity_decode($product['screenname']),
                                $product['sku'],
                                html_entity_decode($product['productName']),
                                html_entity_decode($product['brand']),
                                $is_oversize,
                                $product['quantity'],
                            ];
                            $tmp_list = $productShowModel->getWarehouseDistributionByProductId($product['product_id']);
                            if (count($warehouse_code_list)) {
                                foreach ($warehouse_code_list as $value) {
                                    $content[] = isset($tmp_list[$value]) ? ($tmp_list[$value]['stock_qty']) : 0;
                                }
                            }
                            $content[] = $product['price'];
                            $content[] = $product['freight_per'];
                            $content[] = $product['total_amount'];
                            //$content[] = $productShowModel->getProductRealPrice($product['product_id']);

                        } else {
                            $content = [
                                html_entity_decode($product['screenname']),
                                $product['sku'],
                                html_entity_decode($product['productName']),
                                html_entity_decode($product['brand']),
                                $is_oversize,
                                $product['quantity'],
                                $product['price'],
                                $product['freight_per'],
                                $product['total_amount']
                            ];
                            if ($this->customer->has_cwf_freight()) {
                                $content[] = $product['cwf_freight_per'];
                                $content[] = $product['cwf_total_amount'];
                            }
                        }
                        fputcsv($fp, $content);
                    }
                }
            } else {
                $content = array($this->language->get('error_no_record'));
                fputcsv($fp, $content);
            }

            $meta = stream_get_meta_data($fp);
            if (!$meta['seekable']) {
                $new_data = fopen('php://temp', 'r+');
                stream_copy_to_stream($fp, $new_data);
                rewind($new_data);
                $fp = $new_data;
            } else {
                rewind($fp);
            }
        } else {
            $fileName = "MySavedInValidItems" . date("Ymd", time()) . ".csv";
            header("Content-Type: text/csv");
            header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
            header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
            header('Expires:0');
            header('Pragma:public');
            echo chr(239) . chr(187) . chr(191);

            $fp = fopen('php://output', 'a+');
            $head = array('Store Name', 'Item Code', 'Product Name', 'Brand', 'Oversized', 'Type');
            foreach ($head as $i => &$v) {
                // CSV的Excel支持GBK编码，一定要转换，否则乱码
                $v = iconv('utf-8', 'gbk', $v);
            }
            fputcsv($fp, $head);
            if (isset($products) && !empty($products)) {
                foreach ($products as $product) {
                    $country = session('country');
                    $checkResult = $this->model_catalog_product->checkProduct($product['product_id'], $country, $customFields);
                    //判断订阅的库存是否还和卖家关联
                    $customer_id = $product['customer_id'];
                    $sellerConect = $this->model_account_wishlist->checkSellerConect($customer_id);
                    if ($product['status'] == 0 || $sellerConect == null || $checkResult == false || $product['c_status'] == 0 || $product['buyer_flag'] == 0) {
                        //获取是否超大件
                        $is_oversize = $this->model_catalog_product->checkIsOversizeItem($product['product_id']) == true ? 'Yes' : 'No';
                        $content = array(
                            html_entity_decode($product['screenname']),
                            $product['sku'],
                            html_entity_decode($product['productName']),
                            html_entity_decode($product['brand']),
                            $is_oversize,
                            $sellerConect == true ? ($product['c_status'] == 0) ? 'Non-Coop' : 'Unavailable' : 'Non-Coop'
                        );
                        fputcsv($fp, $content);
                    }
                }
            } else {
                $content = array($this->language->get('error_no_record'));
                fputcsv($fp, $content);
            }

            $meta = stream_get_meta_data($fp);
            if (!$meta['seekable']) {
                $new_data = fopen('php://temp', 'r+');
                stream_copy_to_stream($fp, $new_data);
                rewind($new_data);
                $fp = $new_data;
            } else {
                rewind($fp);
            }
        }

        $output = stream_get_contents($fp);
        fclose($fp);
        return $output;
    }


    public function validItems()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/wishlist', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        if (isset($this->request->get['name'])) {
            $filter_input_name = $this->request->get['name'];
        } else {
            $filter_input_name = null;
        }
        if (isset($this->request->get['sort'])) {
            $filter_input_sort = $this->request->get['sort'];
        } else {
            $filter_input_sort = null;
        }

        //14408上门取货账号一键下载超大件库存分布列表
        if (isset($this->request->get['is_ltl'])) {
            $filter_input_ltl = $this->request->get['is_ltl'];
        } else {
            $filter_input_ltl = null;
        }
        $this->load->language('account/wishlist');

        $this->load->model('account/wishlist');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $data['products'] = array();
        $filter_data = array(
            "filter_input_sort" => $filter_input_sort,
            "filter_input_name" => $filter_input_name,
            "filter_input_ltl" => $filter_input_ltl,
        );
        $customFields = $this->customer->getId();
        $results = $this->model_account_wishlist->getWishlist($filter_data, $customFields);
        $this->load->model('catalog/product');

        $customerCountry = $this->customer->getCountryId();
        $i = 0; // valid数量
        foreach ($results as $result) {
            $country = session('country');
            $checkResult = $this->model_catalog_product->checkProduct($result['product_id'], $country, $customFields);
            if (!$checkResult) {
                continue;
            }
            $product_info = $this->model->getProduct($result['product_id'], $customFields);

            // 获取运送仓的运费
            $freightAndPackageFeeInfo = $this->freight->getFreightAndPackageFeeByProducts(array($result['product_id']));
            $cwf_freight = 0;
            $cwf_package_fee = 0;
            $volume = 0;
            if ($result['combo_flag'] == 1) {
                foreach ($freightAndPackageFeeInfo[$result['product_id']] as $comboInfo) {
                    $cwf_freight += $comboInfo['freight'] * $comboInfo['qty'];
                    $cwf_package_fee += $comboInfo['package_fee'] * $comboInfo['qty'];
                    $volume += $comboInfo['volume_inch'] * $comboInfo['qty'];//102497 换成立方英尺
                }
            } else {
                $cwf_freight = $freightAndPackageFeeInfo[$result['product_id']]['freight'];
                $cwf_package_fee = $freightAndPackageFeeInfo[$result['product_id']]['package_fee'];
                $volume = $freightAndPackageFeeInfo[$result['product_id']]['volume_inch'];//102497 换成立方英尺
            }
            //判断订阅的库存是否还和卖家关联
            $customer_id = $product_info['customer_id'];
            $sellerConect = $this->model_account_wishlist->checkSellerConect($customer_id);

            if ($product_info) {

                //测试店铺、服务店铺、保证金店铺的产品、期货/现货保证金定金类产品在购物车中不显示转移到云送仓购物车的按钮
                $productInfo = $this->model_account_wishlist->getProductInfo($product_info['product_id']);
                $can_add_cart = true;
                if($productInfo['product_type']!=0 && $productInfo['product_type']!=3){
                    $can_add_cart = false;
                } elseif (($productInfo['customer_group_id'] == 23 || in_array($productInfo['customer_id'], array(340, 491, 631, 838)))) {
                    $can_add_cart = false;
                }
                $data['has_cwf_freight'] = $this->customer->has_cwf_freight();
                if ($product_info['c_status'] == 1) {
                    if ($product_info['image']) {
                        $image = $this->model_tool_image->resize($product_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_height'));
                    } else {
                        $image = false;
                    }

                    if ($product_info['quantity'] <= 0) {
                        $stock = $product_info['stock_status'];
                    } elseif ($this->config->get('config_stock_display')) {
                        $stock = $product_info['quantity'];
                    } else {
                        $stock = $this->language->get('text_instock');
                    }

                    if ((float)$product_info['special']) {
                        $special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), session('currency'));
                    } else {
                        $special = false;
                    }
                    // add by xxli
                    $discountResult = $this->model_catalog_product->getDiscount($customFields, $product_info['customer_id']);

                    if ($discountResult) {
                        $price = $this->model_catalog_product->getDiscountPrice($product_info['price'], $discountResult);
                        // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                        if ($customerCountry && $this->customer->getGroupId() == 13) {
                            $price = $this->generateSpecialGroupIdPrice($price, $product_info['product_type'], $product_info['customer_id'], $customerCountry);
                        } else {
                            $price = $this->generateActualPrice($price, $product_info['product_type'], $product_info['customer_id']);
                        }
                        if (session('currency') == 'JPY') {
                            $price = round($price, 0);
                        }
                        //获取buyer订阅该产品的价格
                        // result 是查出来的  wishlist的
                        // $price 是货值
                        $priceChange = bcsub(round($price, 2), $result['price'], 2);
                        $priceChangeCurrency = $this->currency->formatCurrencyPrice($priceChange, session('currency'));
                        //获取total
                        $total_amount = $this->currency->formatCurrencyPrice($product_info['freight_per'] + $price, session('currency'));
                        //云送仓总价
                        if ($productInfo['customer_group_id'] == 23 || in_array($productInfo['customer_id'], array(340, 491, 631, 838))) {   //服务店铺 ---云送仓费用为0
                            $total_amount_cwf = $this->currency->formatCurrencyPrice($price, session('currency'));
                        } else {
                            $total_amount_cwf = $this->currency->formatCurrencyPrice($cwf_freight + $cwf_package_fee + $price, session('currency'));
                        }

                        $price = $this->currency->formatCurrencyPrice($price, session('currency'));
                    } else {
                        // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                        $product_info['price'] = $this->generateActualPrice($product_info['price'], $product_info['product_type'], $product_info['customer_id']);
                        if (session('currency') == 'JPY') {
                            $product_info['price'] = round($product_info['price'], 0);
                        }
                        $total_amount = $this->currency->formatCurrencyPrice($product_info['freight_per'] + $product_info['price'], session('currency'));
                        //云送仓总价
                        if (in_array($product_info['customer_id'], array(340, 491, 631, 838))) {  //服务店铺 ---云送仓费用为0
                            $total_amount_cwf = $this->currency->formatCurrencyPrice($product_info['price'], session('currency'));
                        } else {
                            $total_amount_cwf = $this->currency->formatCurrencyPrice($cwf_freight + $cwf_package_fee + $product_info['price'], session('currency'));
                        }

                        $price = $this->currency->formatCurrencyPrice($product_info['price'], session('currency'));
                        //获取buyer订阅该产品的价格
                        // result 是查出来的  wishlist的
                        // $price 是货值
                        $priceChange = bcsub(round($product_info['price'], 2), $result['price'], 2);
                        $priceChangeCurrency = $this->currency->formatCurrencyPrice($priceChange, session('currency'));
                    }
                    // end xxli

                    //获取freight_pre 的涨跌
                    $freightChange = bcsub(round($product_info['freight_per'], 2), $result['freight'], 2);
                    if (session('currency') == 'JPY') {
                        $freightChange = round($freightChange, 0);
                    }
                    $freightChangeCurrency = $this->currency->formatCurrencyPrice($freightChange, session('currency'));

                    //产品币制
                    $currencyResult = $this->model_account_wishlist->getCurrency(session('currency'));
                    $symbol_left = $currencyResult['symbol_left'];
                    $symbol_right = $currencyResult['symbol_right'];
                    if ($symbol_left == '' && $symbol_right != '') {
                        $curency = $symbol_right;
                    } else if ($symbol_left != '' && $symbol_right == '') {
                        $curency = $symbol_left;
                    }
                    //库存订阅预警值
                    //获取allproducts库存订阅预警值
                    $allProductsReminds = $this->model_account_wishlist->getAllProductsRemind();
                    if (isset($allProductsReminds['remind_qty'])) {
                        $allProductsRemind = $allProductsReminds['remind_qty'];
                    } else {
                        $allProductsReminds = null;
                    }
                    //获取各个店铺库存订阅预警值
                    $sellerStoreRemind = $this->model_account_wishlist->getSellerStoreRemind($product_info['customer_id']);
                    //获取系统默认库存订阅预警值
                    if (isset($result['remind_qty'])) {
                        $remind_qty = $result['remind_qty'];
                    } else if (isset($sellerStoreRemind['remind_qty'])) {
                        $remind_qty = $sellerStoreRemind['remind_qty'];
                    } else if (isset($allProductsRemind)) {
                        $remind_qty = $allProductsRemind;
                    } else {
                        $remind_qty = SUBSCRIBE_COST_QTY;
                    }
                    $tag_array = $this->model_catalog_product->getTag($product_info['product_id']);
                    $tags = array();
                    if (isset($tag_array)) {
                        foreach ($tag_array as $tag) {
                            if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                                //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                                $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                                $tags[] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '"   title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                            }
                        }
                    }

                    if ($product_info['status'] == 1 && $sellerConect && $product_info['c_status'] == 1 && $product_info['buyer_flag'] == 1) {

                        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
                        $data['isCollectionFromDomicile'] = $isCollectionFromDomicile;
                        if ($isCollectionFromDomicile) {
                            $product_info['freight'] = 0;
                            $this->load->model('extension/module/product_show');
                            /** @var ModelExtensionModuleProductShow $productShowModel */
                            $productShowModel = $this->model_extension_module_product_show;
                            $warehouse_list = $productShowModel->getWarehouseDistributionByProductId($product_info['product_id']);
                        } else {
                            $warehouse_list = [];
                        }
                        $data['on_shelf'][$product_info['screenname']][] = array(
                            'customer_href' => $this->url->link('customerpartner/profile', 'id=' . $product_info['customer_id']),
                            'concat_href' => $this->url->link('customerpartner/profile', 'id=' . $product_info['customer_id'] . '&contact=1'),
                            'product_id' => $product_info['product_id'],
                            'thumb' => $image,
                            'warehouse_list' => $warehouse_list,
                            'name' => $product_info['name'],
                            'model' => $product_info['model'],
                            'stock' => $stock,
                            'price' => $price,
                            'total_amount' => $total_amount,
                            'freight_per' => $this->currency->formatCurrencyPrice($product_info['freight_per'], session('currency')),
                            'freight_show' => $this->currency->formatCurrencyPrice($product_info['freight'], session('currency')),
                            'package_fee_show' => $this->currency->formatCurrencyPrice($product_info['package_fee'], session('currency')),
                            'special' => $special,
                            'href' => $this->url->link('product/product', 'product_id=' . $product_info['product_id']),
                            'remove' => $this->url->link('account/wishlist', 'remove=' . $product_info['product_id']),
                            'quantity' => $product_info['quantity'],
                            'screenname' => $product_info['screenname'],
                            'sku' => $product_info['sku'],
                            'remind_qty' => $result['remind_qty'],
                            'currency' => $curency,
                            'brand' => $product_info['manufacturer'],
                            'brand_id' => $product_info['manufacturer_id'],
                            'remind' => $remind_qty,
                            'tag' => $tags,
                            'priceChange' => $priceChange,
                            'priceChangeCurrency' => $priceChangeCurrency,
                            'freightChange' => $freightChange,
                            'freightChangeCurrency' => $freightChangeCurrency,
                            'freight_cwf_show' => !$can_add_cart ? $this->currency->formatCurrencyPrice(0, session('currency')) : $this->currency->formatCurrencyPrice($cwf_freight + $cwf_package_fee, session('currency')),
                            'package_fee_cwf' => !$can_add_cart ? $this->currency->formatCurrencyPrice(0, session('currency')) : $this->currency->formatCurrencyPrice($cwf_package_fee, session('currency')),
                            'freight_cwf' => !$can_add_cart ? $this->currency->formatCurrencyPrice(0, session('currency')) : $this->currency->formatCurrencyPrice($cwf_freight, session('currency')),
                            'volume' => $volume,
                            'total_amount_cwf' => $total_amount_cwf,
                            'can_add_cart' => $can_add_cart,
                            'has_cwf_freight' => $data['has_cwf_freight']
                        );
                        $i++;
                    }
                }
            } else {
//                $this->model_account_wishlist->deleteWishlist($result['product_id']);
            }
        }
        $data['service_type'] = SERVICE_TYPE;
        $data['isCollectionFromDomicile'] = $this->customer->isCollectionFromDomicile();
        $data['freight_rate'] = $this->config->get('cwf_base_cloud_freight_rate');
        $this->cache->set($this->customer->getId() . '_on_shelf', $i);
        if ($filter_input_name != null && isset($data['on_shelf']) == false) {
            $data['search_vaild_count'] = 0;
            $data['text_search'] = sprintf($this->language->get('text_no_result_for_search'), $filter_input_name);
        } else {
            $data['search_vaild_count'] = 1;
            $data['text_search'] = 'No results.';
        }
        //获取关联的所有seller
        $sellers = $this->model_account_wishlist->getSellers();
        $data['sellers'] = $sellers;
        //库存订阅预警值
        //获取allproducts库存订阅预警值
        $allProductsReminds = $this->model_account_wishlist->getAllProductsRemind();
        if (isset($allProductsReminds['remind_qty'])) {
            $data['allProductsRemind'] = $allProductsReminds['remind_qty'];
        } else {
            $data['allProductsRemind'] = null;
        }
        //获取各个店铺库存订阅预警值
        $sellerStoreReminds = $this->model_account_wishlist->getSellerStoreReminds();
        $data['sellerStoresRemind'] = $sellerStoreReminds;
        $this->response->setOutput($this->load->view('account/valid_item', $data));
    }

    public function expiredItems()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/wishlist', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        if (isset($this->request->get['name'])) {
            $filter_input_name = $this->request->get['name'];
        } else {
            $filter_input_name = null;
        }
        if (isset($this->request->get['sort'])) {
            $filter_input_sort = $this->request->get['sort'];
        } else {
            $filter_input_sort = null;
        }

        //14408上门取货账号一键下载超大件库存分布列表
        if (isset($this->request->get['is_ltl'])) {
            $filter_input_ltl = $this->request->get['is_ltl'];
        } else {
            $filter_input_ltl = null;
        }
        $this->load->language('account/wishlist');

        $this->load->model('account/wishlist');

        $this->load->model('catalog/product');

        $this->load->model('tool/image');
        $data['products'] = array();
        $filter_data = array(
            "filter_input_sort" => $filter_input_sort,
            "filter_input_name" => $filter_input_name,
            "filter_input_ltl" => $filter_input_ltl,
        );
        $customFields = $this->customer->getId();
        $results = $this->model_account_wishlist->getWishlist($filter_data, $customFields);
        $this->load->model('catalog/product');
        $i = 0; //invalid 数量
        foreach ($results as $result) {
            $product_info = $this->model->getProduct($result['product_id'], $customFields);
            //判断订阅的库存是否还和卖家关联
            $customer_id = $product_info['customer_id'];
            $sellerConect = $this->model_account_wishlist->checkSellerConect($customer_id);

            if ($product_info) {
                if ($product_info['image']) {
                    $image = $this->model_tool_image->resize($product_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_height'));
                } else {
                    $image = false;
                }
                $tag_array = $this->model_catalog_product->getTag($product_info['product_id']);
                $tags = array();
                if (isset($tag_array)) {
                    foreach ($tag_array as $tag) {
                        if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip"  class="' . $tag['class_style'] . '"  title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                        }
                    }
                }
                $country = session('country');
                $checkResult = $this->model_catalog_product->checkProduct($result['product_id'], $country, $customFields);
                if ($product_info['status'] == 0 || $sellerConect == null || $checkResult == false || $product_info['c_status'] == 0 || $product_info['buyer_flag'] == 0) {
                    //14408上门取货账号一键下载超大件库存分布列表
                    // 获取 product中的仓库
                    $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
                    $data['isCollectionFromDomicile'] = $isCollectionFromDomicile;
                    if ($isCollectionFromDomicile) {
                        $this->load->model('extension/module/product_show');
                        /** @var ModelExtensionModuleProductShow $productShowModel */
                        $productShowModel = $this->model_extension_module_product_show;
                        $warehouse_list = $productShowModel->getWarehouseDistributionByProductId($product_info['product_id']);
                    } else {
                        $warehouse_list = [];
                    }
                    $data['off_shelf'][$product_info['screenname']][] = array(
                        'customer_href' => $this->url->link('customerpartner/profile', 'id=' . $product_info['customer_id']),
                        'concat_href' => $this->url->link('customerpartner/profile', 'id=' . $product_info['customer_id'] . '&contact=1'),
                        'product_id' => $product_info['product_id'],
                        'thumb' => $image,
                        'warehouse_list' => $warehouse_list,
                        'name' => $product_info['name'],
                        'model' => $product_info['model'],
                        'href' => $this->url->link('product/product', 'product_id=' . $product_info['product_id']),
                        'remove' => $this->url->link('account/wishlist', 'remove=' . $product_info['product_id']),
                        'quantity' => $product_info['quantity'],
                        'screenname' => $product_info['screenname'],
                        'sku' => $product_info['sku'],
                        'brand' => $product_info['manufacturer'],
                        'brand_id' => $product_info['manufacturer_id'],
                        'type' => $sellerConect == true ? ($product_info['c_status'] == 0) ? 'Non-Coop' : 'Unavailable' : 'Non-Coop',
                        'tag' => $tags
                    );
                    $i++;
                }
            }
        }
        $this->cache->set($this->customer->getId() . '_off_shelf', $i);
        if ($filter_input_name != null && isset($data['off_shelf']) == false) {
            $data['search_invaild_count'] = 0;
            $data['text_search'] = sprintf($this->language->get('text_no_result_for_search'), $filter_input_name);
        } else {
            $data['search_invaild_count'] = 1;
            $data['text_search'] = 'No results.';
        }
        //获取关联的所有seller
        $sellers = $this->model_account_wishlist->getSellers();
        $data['sellers'] = $sellers;
        $this->response->setOutput($this->load->view('account/expired_item', $data));
    }

    public function getWishListAmount()
    {
        $json['off_shelf'] = $this->cache->get($this->customer->getId() . '_off_shelf');
        $json['on_shelf'] = $this->cache->get($this->customer->getId() . '_on_shelf');
        //$this->cache->delete($this->customer->getId().'_off_shelf');
        //$this->cache->delete($this->customer->getId().'_on_shelf');
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    //库存提醒设置API
    public function setProductAlertAll()
    {
        $row = -1;
        if (isset($this->request->request['allProductsRemind']) && $this->request->request['allProductsRemind'] == 1) {
            //整个产品设置库存阀值
            $all_qty = $this->request->request['all_qty'];
            if (strlen($all_qty) && is_numeric($all_qty)) {
                $row = $this->model->setAllProductsRemind(['type' => 'allProductsRemind'], $all_qty);
            }
        }
        if (
            isset($this->request->request['sellersRemind'])
            && $this->request->request['sellersRemind'] == 1
            && isset($this->request->request['seller_ids'])
        ) {
            //多个店铺设置库存阀值 seller_ids
            $seller_ids = $this->request->request['seller_ids'];
            $store_qty = $this->request->request['store_qty'];
            if (strlen($seller_ids) && is_numeric(str_replace(',', '', $seller_ids))) {
                $row = $this->model->setAllProductsRemind(['type' => 'sellersRemind', 'seller_ids' => $seller_ids], $store_qty);
            }
        }
        if (
            isset($this->request->request['productsRemind'])
            && $this->request->request['productsRemind'] == 1
            && isset($this->request->request['product_ids'])
        ) {
            //多个产品设置库存阀值 product_ids
            $product_ids = $this->request->request['product_ids'];
            $product_qty = $this->request->request['product_qty'];
            if (strlen($product_ids) && is_numeric(str_replace(',', '', $product_ids))) {
                $row = $this->model->setAllProductsRemind(['type' => 'productsRemind', 'product_ids' => $product_ids], $product_qty);
            }
        }
        if ($row < 0) {
            $json['success'] = false;
            $json['message'] = 'input invalid';
        } else {
            $json['success'] = true;
            if (
                $this->request->request['all_qty'] === '0'
                && $this->request->request['store_qty'] === '0'
                && $this->request->request['product_qty'] === '0'
            ) {
                $json['message'] = 'Save successfully';
            } else {
                $json['affects'] = $row;
                $json['message'] = 'Saved successfully';
                if (
                    ($this->request->request['seller_ids'] == '0' || $this->request->request['store_qty'] == '0')
                    || ($this->request->request['product_ids'] == '0' || $this->request->request['product_qty'] == '0')
                ) {
                    $json['message'] .= ', set  0 means there is no Low Inventory Alert';
                }
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    //删除、批量删除收藏API
    public function delProductsFromWishGroup()
    {
        $product_id_str = request('product_id_str');
        $this->load->language('account/wishlist');
        if (strlen($product_id_str) > 0 && is_numeric(str_replace(',', '', $product_id_str))) {
            $json['affects'] = $this->model->delProductsToWishGroup($product_id_str);
            $json['message'] = $json['affects'] . ' products deleted';
            $json['success'] = true;
        } else {
            $json['message'] = 'Input invalid';
            $json['success'] = false;
        }
        return $this->json($json);
    }

    //分组改名API
    public function renameWishGroup()
    {
        $group_id = $this->request->request['group_id'];
        $new_name = $this->request->request['new_name'];
        $this->load->language('account/wishlist');
        if (strlen($group_id) && strlen($new_name) && mb_strlen($new_name) <= 20) {
            $json['affects'] = $this->model->renameWishGroup($group_id, htmlspecialchars($new_name));
            $json['message'] = "Successful operation";
            $json['success'] = true;
        } else {
            $json['message'] = 'Submit failed, please enter the name of group';
            $json['success'] = false;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    //分组添加API
    public function addWishGroup()
    {
        $name = trim($this->request->request['name']);
        $this->load->language('account/wishlist');
        if (strlen($name) && mb_strlen($name) <= 20) {
            $json['group_id'] = $this->model->addWishGroup(htmlspecialchars($name));
            if ($json['group_id'] > 0) {
                $json['message'] = $name . ' add successed';
                $json['success'] = true;
            } elseif ($json['group_id'] == 0) {
                $json['message'] = 'Group already exists';
                $json['success'] = false;
            } else {
                $json['message'] = 'The group limit has been reached and a maximum of 6 groups can be created'; //分组已达上限，不可新建
                $json['success'] = false;
            }
        } else {
            $json['success'] = false;
            $json['message'] = 'Submit failed, please enter the name of group';
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    //分组删除API
    public function delWishGroup()
    {
        $group_id = $this->request->request['group_id'];
        $this->load->language('account/wishlist');
        if (is_numeric($group_id) && $group_id > 0) {
            $json['message'] = "Successful operation";
            $json['affects'] = $this->model->delWishGroup($group_id);
            $json['success'] = true;
        } elseif ($group_id == 0) {
            //无法删除all分组
            $json['message'] = "Unable to delete ungrouped group";
            $json['success'] = false;
        } else {
            $json['message'] = 'Input invalid';
            $json['success'] = false;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    //批量设置分组API
    public function setProductsToWishGroup()
    {
        $product_id_str = $this->request->request['product_id_str'];
        $group_id = $this->request->request['group_id'];
        if (strlen($product_id_str) && is_numeric(str_replace(',', '', $product_id_str)) && is_numeric($group_id)) {
            $json['affects'] = $this->model->setProductsToWishGroup($product_id_str, $group_id, null);
            if ($json['affects'] > 0) {
                $json['message'] = $json['affects'] . ' products saved';
                $json['success'] = true;
            } elseif ($json['affects'] == 0) {
                $json['message'] = "These products have moved to the target group";
                $json['success'] = false;
            } else {
                $json['message'] = "Can't move into group if it has been cancelled";
                $json['success'] = false;
            }
        } else {
            $json['message'] = 'Input invalid';
            $json['success'] = false;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function getSavedStoreAndProductInWish()
    {
        $customer_id = $this->customer->getId();
        $product = $this->model->getProductSkuInWish('', 'p.product_id,p.sku', ['product_id' => 'remind_qty>0']);
        $store = $this->orm->table('tb_sys_seller_storage AS s')
            ->leftJoin('oc_customerpartner_to_customer AS c2c', 'c2c.customer_id', '=', 's.seller_id')
            ->whereRaw("c2c.is_partner=1 AND s.remind_qty>0 AND s.buyer_id={$customer_id}")
            ->selectRaw("c2c.customer_id,c2c.screenname,s.remind_qty")
            ->orderByRaw('c2c.screenname ASC')
            ->groupBy('c2c.customer_id')
            ->get()->toArray();

        $query = $this->orm->table('tb_sys_buyer_storage')
            ->whereRaw("buyer_id={$customer_id}")
            ->selectRaw('0 AS q,remind_qty AS qty')
            ->unionAll(
            //当没有店铺设置过时
                $this->orm->table('tb_sys_seller_storage')
                    ->whereRaw("buyer_id={$customer_id}")
                    ->selectRaw('1 AS q,max(remind_qty) AS store')
            )
            ->unionAll(
                $this->orm->table('oc_customer_wishlist')
                    ->whereRaw("customer_id={$customer_id}")
                    ->selectRaw('2 AS q,max(remind_qty) AS product')
            )
            ->get();
        //echo PHP_EOL.get_complete_sql($query).PHP_EOL.$customer_id.PHP_EOL;
        if ($query) {
            $data = obj2array($query);
            $data = array_combine(array_column($data, 'q'), array_column($data, 'qty'));
        } else {
            $data = [];
        }
        $qty['all'] = $data[0] ?? 20;
        $qty['store'] = $data[1] ?? 0;
        $qty['product'] = $data[2] ?? 0;
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['product' => obj2array($product), 'store' => obj2array($store), 'qty' => $qty]));
    }

}
