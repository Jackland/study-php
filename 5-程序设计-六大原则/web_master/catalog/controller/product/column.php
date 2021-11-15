<?php

use App\Enums\Product\Channel\ChannelType;

/**
 * 产品频道
 * @property ModelAccountTicket $model_account_ticket
 * @property ModelCatalogCategory $model_catalog_category
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCatalogProductColumn $model_catalog_product_column
 * @property ModelCatalogProductPrice $model_catalog_ProductPrice
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelToolImage $model_tool_image
 */
class ControllerProductColumn extends Controller
{
    const PRODUCT_COLUMN_NAME = [
        'featured' => 'Featured Products',
        'promotion' => 'Promotion',
        'newArrival' => 'New Arrivals',
        'bestSell' => 'Best Sellers',
        'highStock' => 'High Stock Count',
        'lowStock' => 'Last Chance-Low Stock',
        'priceDrop' => 'Price Drop',
    ];

    private $page_list = 16;

    public function index()
    {
        $name = get_value_or_default($this->request->get, 'name', '');
        $category_id = get_value_or_default($this->request->get, 'category_id', 0);


        $isPartner = $this->customer->isPartner();
        $customFields = $this->customer->getId();
        $countryCode = session('country');


        $this->load->model('catalog/product_column');


        $categories = [];
        $categories[] = ['category_id' => 0, 'name' => 'All',];
        $tmp_others = ['category_id' => -1, 'name' => 'Others',];
        $had_others = 0;
        switch ($name) {
            case 'featured':
                //$setting['product'] 来源于oc_module表module_id=28的记录
                $sql = "SELECT setting FROM oc_module WHERE `code`='featured'";
                $query = $this->db->query($sql);
                $setting_json = $query->row['setting'];

                $setting = json_decode($setting_json, true);

                if ($setting['product']) {
                    $results = $this->model_catalog_product_column->recommendCategory($setting['product'], $countryCode);
                }
                break;
            case 'promotion':
                $results = $this->model_catalog_product_column->promotionCategory($countryCode);
                break;
            case 'newArrival':
                return $this->redirect(['product/channel/getChannelData', 'type' => ChannelType::NEW_ARRIVALS]);
                //$results = $this->model_catalog_product_column->newArrivalsCategory($countryCode);
                break;
            case 'bestSell':
                return $this->redirect(['product/channel/getChannelData', 'type' => ChannelType::BEST_SELLERS]);
            //$results = $this->model_catalog_product_column->bestSellerCategory($countryCode);
            //break;
            case 'highStock':
                //$results = $this->model_catalog_product_column->highStockCategory($countryCode);
                //break;
                return $this->redirect(['product/channel/getChannelData', 'type' => ChannelType::WELL_STOCKED]);
            case 'lowStock':
                $results = $this->model_catalog_product_column->lowStockCategory($countryCode);
                break;
            case 'priceDrop':
                if (in_array($countryCode, ['USA'])) {
                    //$results = $this->model_catalog_product_column->priceDropCategory($countryCode);
                    return $this->redirect(['product/channel/getChannelData', 'type' => ChannelType::DROP_PRICE]);
                }
                break;
            default:
                $results = [];
        }
        unset($value);
        foreach ($results as $key => $value) {
            if ($value['category_id'] == 0) {
                continue;
            }
            if ($value['category_id'] == -1) {//Others分类
                $had_others = 1;
                continue;
            }
            $categories[] = ['category_id' => $value['category_id'], 'name' => $value['name']];
        }
        unset($value);
        if (count($categories) > 1 && $had_others > 0) {//如果存在Others分类，则要显示
            $categories[] = $tmp_others;
        }


        $data['product_column_name'] = get_value_or_default(self::PRODUCT_COLUMN_NAME, $name, '');
        $this->document->setTitle($data['product_column_name']);


        $data['categories'] = $categories;
        $data['logged'] = $this->customer->isLogged();
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['url_column'] = str_replace('&amp;', '&', $this->url->link('product/column/column', 'name=' . $name . '&category_id=' . $category_id, true));

        //下载素材包的复选框 是否显示
        if (null != $customFields && false == $isPartner) {
            $data['download_csv_privilege'] = 1;
        } else {
            $data['download_csv_privilege'] = 0;
        }
        $data['products_to_csv'] = $this->url->link('/product/category/all_products_info&path=');
        $data['products_to_wish'] = $this->url->link('/account/wishlist/batchAdd&path=');
        $data['app_version'] = APP_VERSION;

        $this->response->setOutput($this->load->view('product/column', $data));
    }


    public function column()
    {
        $is_end = 1;
        $list = [];


        $name = get_value_or_default($this->request->get, 'name', '');
        $category_id = get_value_or_default($this->request->get, 'category_id', 0);
        $page = intval($this->request->get['page'] ?? 1);
        $page_limit = intval(get_value_or_default($this->request->get, 'page_limit', $this->page_list));

        $page = $page < 1 ? 1 : $page;
        $page_limit = $page_limit < 1 ? $this->page_list : $page_limit;


        if (!array_key_exists($name, self::PRODUCT_COLUMN_NAME)) {
            goto endcolumn;
        }


        $this->load->model('account/ticket');
        $this->load->model('catalog/product');
        $this->load->model('catalog/product_column');
        $this->load->model('catalog/ProductPrice');
        $this->load->model('tool/image');
        $this->load->model('extension/module/product_show');


        $customFields = $this->customer->getId();
        $countryCode = session('country');
        //获取预期入库的商品时间和数量
        $receipt_array = $this->model_catalog_product->getReceptionProduct();


        $is_end = 1;
        $list = [];
        switch ($name) {
            case 'featured':
                //$setting['product'] 来源于oc_module表module_id=28的记录

                $sql = "SELECT setting FROM oc_module WHERE `code`='featured'";
                $query = $this->db->query($sql);
                $setting_json = $query->row['setting'];

                $setting = json_decode($setting_json, true);

                if ($setting['product']) {
                    $products = $this->model_catalog_product_column->recommendFiledColumn($setting['product'], $countryCode, $category_id);
                    if ($products) {
                        foreach ($products as $value) {
                            $product_id = $value['product_id'];
                            $list[] = $this->model_extension_module_product_show->getIdealProductInfo($product_id, $customFields, $receipt_array);;
                        }
                    }
                }

                goto endcolumn;
                break;
            case 'promotion':
                $products = $this->model_catalog_product_column->promotionColumn($countryCode, $category_id);
                if ($products) {
                    foreach ($products as $value) {
                        $product_id = $value['product_id'];
                        $temp = $this->model_extension_module_product_show->getIdealProductInfo($product_id, $customFields, $receipt_array);;
                        $temp['horn_mark'] = 'sale';//角标
                        $list[] = $temp;
                    }
                }

                goto endcolumn;
                break;
            case 'newArrival':
                $products = $this->model_catalog_product_column->newArrivalsColumn($countryCode, $category_id, $page, $page_limit);
                if ($products) {
                    foreach ($products as $value) {
                        $product_id = $value['product_id'];
                        $list[] = $this->model_extension_module_product_show->getIdealProductInfo($product_id, $customFields, $receipt_array);
                    }
                    $is_end = 0;
                    if (count($products) < $page_limit) {
                        $is_end = 1;
                    }
                }
                goto endcolumn;
                break;
            case 'bestSell':
                //排名前100的产品
                $max_rows = 100;
                $max_page = ceil($max_rows / $page_limit);
                if ($page > $max_page) {
                    $is_end = 1;
                    $list = [];
                    goto endcolumn;
                }
                $db_limit = $page_limit;
                if ($page == $max_page) {
                    $db_limit = abs(($page - 1) * $page_limit - $max_rows);
                }
                $products = $this->model_catalog_product_column->bestSellerColumn($countryCode, $category_id, $page, $page_limit, $db_limit);
                if ($products) {
                    foreach ($products as $result) {
                        $product_id = $result['product_id'];
                        $list[] = $this->model_extension_module_product_show->getIdealProductInfo($product_id, $customFields, $receipt_array);
                    }

                    $is_end = 0;
                    if (count($products) < $page_limit) {
                        $is_end = 1;
                    }
                }
                goto endcolumn;
                break;
            case 'highStock':
                //排名前100的产品
                $max_rows = 100;
                $max_page = ceil($max_rows / $page_limit);
                if ($page > $max_page) {
                    $is_end = 1;
                    $list = [];
                    goto endcolumn;
                }
                $products = $this->model_catalog_product_column->highStockColumn($countryCode, $category_id, $page, $page_limit);
                if ($products) {
                    foreach ($products as $value) {
                        $product_id = $value['product_id'];
                        $list[] = $this->model_extension_module_product_show->getIdealProductInfo($product_id, $customFields, $receipt_array);
                    }

                    $is_end = 0;
                    if (count($products) < $page_limit) {
                        $is_end = 1;
                    }
                }
                goto endcolumn;
                break;
            case 'lowStock':
                $products = $this->model_catalog_product_column->lowStockColumn($countryCode, $category_id, $page, $page_limit);
                if ($products) {
                    foreach ($products as $value) {
                        $product_id = $value['product_id'];
                        $list[] = $this->model_extension_module_product_show->getIdealProductInfo($product_id, $customFields, $receipt_array);
                    }

                    $is_end = 0;
                    if (count($products) < $page_limit) {
                        $is_end = 1;
                    }
                }
                goto endcolumn;
                break;
            case 'priceDrop':
                if (in_array($countryCode, ['USA'])) {
                    //排名前100的产品
                    $max_rows = 100;
                    $max_page = ceil($max_rows / $page_limit);
                    if ($page > $max_page) {
                        $is_end = 1;
                        $list = [];
                        goto endcolumn;
                    }
                    $db_limit = $page_limit;
                    if ($page == $max_page) {
                        $db_limit = abs(($page - 1) * $page_limit - $max_rows);
                    }
                    $products = $this->model_catalog_product_column->priceDropColumn($countryCode, $category_id, $page, $page_limit, $db_limit);
                    if ($products) {
                        foreach ($products as $value) {
                            $product_id = $value['product_id'];
                            $list[] = $this->model_extension_module_product_show->getIdealProductInfo($product_id, $customFields, $receipt_array);
                        }

                        $is_end = 0;
                        if (count($products) < $page_limit) {
                            $is_end = 1;
                        }
                    }
                }
                goto endcolumn;
                break;
            default:
                $is_end = 1;
                $list = [];
                goto endcolumn;
                break;
        }


        endcolumn:
        $htmls = $this->load->controller('product/column/column_product', $list);
        $data = [
            "is_end" => $is_end,
            "htmls" => $htmls,
        ];


        $this->response->returnJson($data);
    }


    public function column_product($products)
    {
        $data['products'] = $products;

        $isPartner = $this->customer->isPartner();
        $customFields = $this->customer->getId();

        $data['is_column'] = 1;
        $data['is_partner'] = $isPartner;
        $data['isLogin'] = $this->customer->isLogged();
        $data['login'] = $this->url->link('account/login', '', true);
        $data['products_total'] = 0;

        //下载素材包的复选框 是否显示
        if (null != $customFields && false == $isPartner) {
            $data['download_csv_privilege'] = 1;
        } else {
            $data['download_csv_privilege'] = 0;
        }


        return $this->load->view('product/column_product', $data);
    }
}
