<?php

use App\Components\Storage\StorageCloud;
use App\Helper\CountryHelper;

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountInventoryManagement $model_account_inventory_management
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelToolCsv $model_tool_csv
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountInventoryManagement extends Controller
{
    //入库类型（Receiving Category） - Order ID
    //Receiving Order - Incoming Shipment ID ：入库单/入库单号
    //RMA - RMA ID ：针对已同意的采购订单和Canceled销售订单的返金的RMA ID
    //Goods-in - 保证金协议ID ：包销调入，目前保证金业务还没有开发，保证金协议ID可以空着
    //Increase Inventory - 空 ：手动上调库存，没有Order ID
    const STOCK_IN_TYPE = [
        'RECEIVING_ORDER' => 'Receiving Order',
        'RMA' => 'RMA',
        'DEPOSIT' => 'Goods-in',
        'INCREASE_INVENTORY' => 'Increase Inventory',
    ];

    //出库类型（Dispatch Category） - Order ID
    //Order Dispatched - Order ID ：销售订单/ID（Seller的销售订单，Buyer的采购订单）
    //RMA - RMA ID ：针对已同意的重发单的RMA ID
    //Goods-out - 保证金协议ID ：包销调出，目前保证金业务还没有开发，保证金协议ID可以空着
    //Reduce Inventory - 空 ：手动下调库存，没有Order ID
    const STOCK_OUT_TYPE = [
        'PURCHASE_ORDER' => 'Order Dispatched',
        'RMA' => 'RMA',
        'DEPOSIT' => 'Goods-out',
        'REDUCE_INVENTORY' => 'Reduce Inventory',
    ];

    public function index()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/inventory_management', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->language('account/inventory_management');

        $this->document->setTitle($this->language->get('heading_title_inventory_management'));

        $this->load->model('account/inventory_management');
        $this->load->model('account/customerpartner');
        if ($this->customer->isPartner()) {
            // SellerN
            $this->getSellerList();
        } else {
            // Buyer
            $this->getBuyerList();
        }
    }

    public function getBuyerList()
    {
        $this->load->language('account/inventory_management');

        $this->load->model('account/inventory_management');

        $this->document->setTitle($this->language->get('heading_title_inventory_management'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_title_inventory_management'),
            'href' => $this->url->link('account/inventory_management', '', true)
        );
        $customer_id = $this->customer->getId();
        $url = "";
        if (isset($this->request->get['filter_sku']) && trim($this->request->get['filter_sku']) != '') {
            $sku = trim($this->request->get['filter_sku']);
            $url .= "&filter_sku=" . $this->request->get['filter_sku'];
        } else {
            $sku = null;
        }
        $data['filter_sku'] = $sku;
        if (isset($this->request->get['filter_stockNumberFlag']) && trim($this->request->get['filter_stockNumberFlag']) != '') {
            $stockNumberFlag = trim($this->request->get['filter_stockNumberFlag']);
            $url .= "&filter_stockNumberFlag=" . $this->request->get['filter_stockNumberFlag'];
        } else {
            $stockNumberFlag = null;
        }
        $data['filter_stockNumberFlag'] = $stockNumberFlag;
        /* 分页 */
        if (isset($this->request->get['page_num'])) {
            $page_num = $this->request->get['page_num'];
        } else {
            $page_num = 1;
        }
        $url .= "&page_num=" . $page_num;
        $data['page_num'] = $page_num;
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 20;
        }
        $data['page_limit'] = $page_limit;
        $url .= "&page_limit=" . $page_limit;
        //过滤参数
        $filter_data = array(
            'sku' => $sku,
            'stockNumberFlag' => $stockNumberFlag,
            'customer_id' => $customer_id,
            'start' => ($page_num - 1) * $page_limit,
            'limit' => $page_limit,
        );
        // 计算总数
        $countNum = $this->model_account_inventory_management->getBuyerProductCostCount($filter_data);
        $results = $this->model_account_inventory_management->getBuyerProductCost($filter_data);
        $products = array();

        $this->load->model('tool/image');
        if (isset($this->request->get['backUrlKey'])) {
            $this->cache->delete($this->request->get['backUrlKey']);
        }
        $key = time() . "BatchStock";
        $backUrl = $this->url->link('account/inventory_management' . '&backUrlKey=' . $key . $url ,false);
        $this->cache->set($key, $backUrl);
        $data['backUrlKey'] = $key;

        $this->load->model('catalog/product');
        foreach ($results as $result) {
            if (!(count(explode('http', $result['image'])) > 1)) {
                if ($result['image'] && StorageCloud::image()->fileExists($result['image'])) {
                    $image = $this->model_tool_image->resize($result['image']);
                    $image2 = $this->model_tool_image->resize($result['image'], 500, 500);
                } else {
                    $image = $this->model_tool_image->resize('no_image.png');
                    $image2 = $this->model_tool_image->resize('no_image.png', 500, 500);
                }
                $result['image'] = $image;
                $result['image2'] = $image2;
            }
            //累计出库数
            $outStockQty = $this->model_account_inventory_management->getOutStockQty($result['sku'], $customer_id);
            $result['outStockQty'] = $outStockQty['outStockQty'];
            //可用库存数
            $productCostQty = $this->model_account_inventory_management->getProductCostBySku($result['sku'], $customer_id);
            $result['productCostQty'] = get_value_or_default($productCostQty,'orginalQty',0)
                -get_value_or_default($productCostQty,'associatedQty',0)
                -get_value_or_default($productCostQty,'qty',0);
            $result['batchStockUrl'] = $this->url->link('account/inventory_management/batchStockBuyer', "&customer_id=" . $customer_id . "&item_code=" . $result['sku'] . "&backUrlKey=" . $key ,false);
            // 获取已售未发的商品数量
            $soldOutInfo = $this->model_account_inventory_management->getSoldOutCount($result['sku'], $customer_id);
            $result['sold_out'] = get_value_or_default($soldOutInfo, 'qty', 0);
            //冻结库存
            $avilableQty = $result['productCostQty'];
            $result['blocked_stock'] = $result['onhandQty'] - $avilableQty- $result['sold_out'];

            $tag_array = $this->model_catalog_product->getProductSpecificTag($result['product_id']);
            $tags = array();
            if(isset($tag_array)){
                foreach ($tag_array as $tag){
                    if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                        //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                        $tags[] = '<img data-toggle="tooltip" class="'.$tag['class_style']. '"  title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                    }
                }
            }
            $result['tag'] = $tags;
            $products[] = $result;
        }
        $data['products'] = $products;

        $total_pages = ceil($countNum / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['total_num'] = $countNum;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($countNum) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($countNum - $page_limit)) ? $countNum : ((($page_num - 1) * $page_limit) + $page_limit), $countNum, $total_pages);

        $this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

        $data['column_left'] = $this->load->controller('common/column_left');
//        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $this->response->setOutput($this->load->view('account/inventory_management_buyer', $data));
    }

    public function getSellerList($data = array())
    {
        $this->load->language('account/inventory_management');

        $this->load->model('account/inventory_management');

        $this->document->setTitle($this->language->get('heading_title_inventory_management'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_title_inventory_management'),
            'href' => $this->url->link('account/inventory_management', '', true)
        );

        $customer_id = $this->customer->getId();
        $url = "";
        if (isset($this->request->get['filter_mpn']) && trim($this->request->get['filter_mpn']) != '') {
            $mpn = trim($this->request->get['filter_mpn']);
            $url .= "&filter_mpn=" . $this->request->get['filter_mpn'];
        } else {
            $mpn = null;
        }
        $data['filter_mpn'] = $mpn;
        //Contains 0 pro...复选框 (勾选 1,不勾选 0,默认勾选)不勾选时过滤无库存的产品
        if (!isset($this->request->get['filter_stockNumberFlag'])) {
            $stockNumberFlag = '1';
        } else {
            $stockNumberFlag = trim($this->request->get['filter_stockNumberFlag']);
        }
        $url .= "&filter_stockNumberFlag=" . $stockNumberFlag;
        $data['filter_stockNumberFlag'] = $stockNumberFlag;
        /* 分页 */
        if (isset($this->request->get['page_num'])) {
            $page_num = $this->request->get['page_num'];
        } else {
            $page_num = 1;
        }
        $url .= "&page_num=" . $page_num;
        $data['page_num'] = $page_num;
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 20;
        }
        $data['page_limit'] = $page_limit;
        $url .= "&page_limit=" . $page_limit;
        //过滤参数
        $filter_data = array(
            'mpn' => $mpn,
            'stockNumberFlag' => $stockNumberFlag,
            'customer_id' => $customer_id,
            'start' => ($page_num - 1) * $page_limit,
            'limit' => $page_limit,
        );
        list($results, $countNum) = $this->model_account_inventory_management->getProducts($filter_data);
        $data['csv_filter_data'] = [
            'mpn' => $mpn,
            'stockNumberFlag' => $stockNumberFlag,
            'customer_id' => $customer_id,
            'start' => 0,
        ];

        $this->load->model('tool/image');
        if (isset($this->request->get['backUrlKey'])) {
            $this->cache->delete($this->request->get['backUrlKey']);
        }
        $key = time() . "BatchStock";
        $backUrl = $this->url->link('account/inventory_management' . '&backUrlKey=' . $key . $url, false);
        $this->cache->set($key, $backUrl);
        $data['backUrlKey'] = $key;
        $this->load->model('catalog/product');
        foreach ($results as $result) {
            if (!(count(explode('http', $result['image'])) > 1)) {
                if ($result['image'] && StorageCloud::image()->fileExists($result['image'])) {
                    $image = $this->model_tool_image->resize($result['image']);
                    $image2 = $this->model_tool_image->resize($result['image'], 500, 500);
                } else {
                    $image = $this->model_tool_image->resize('no_image.png');
                    $image2 = $this->model_tool_image->resize('no_image.png', 500, 500);
                }
                $result['image'] = $image;
                $result['image2'] = $image2;
            }
            $result['batchStockUrl'] = $this->url->link('account/inventory_management/batchStock', "&customer_id=" . $customer_id . "&product_id=" . $result['product_id'] . "&backUrlKey=" . $key, false);
            $data['products'][] = $result;
        }

        $total_pages = ceil($countNum / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($countNum) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($countNum - $page_limit)) ? $countNum : ((($page_num - 1) * $page_limit) + $page_limit), $countNum, $total_pages);

        $this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

        $data['column_left'] = $this->load->controller('common/column_left');
        //$data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $this->response->setOutput($this->load->view('account/inventory_management', $data));
    }

    public function batchStockBuyer()
    {

        $this->load->language('account/inventory_management');
        $this->load->language('common/cwf');

        $this->load->model('account/inventory_management');

        $this->document->setTitle($this->language->get('heading_title_inventory_management'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_title_inventory_management'),
            'href' => $this->url->link('account/inventory_management', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_title_inventory_Batch_management'),
            'href' => $this->url->link('account/inventory_management/batchStockBuyer', '', true)
        );

        $url = "";
        $customer_id = $this->customer->getId();
        $data['customer_id'] = $customer_id;
        $url .= "&customer_id=" . $customer_id;
        if (isset($this->request->get['item_code'])) {
            $item_code = $this->request->get['item_code'];
            $url .= "&item_code=" . $this->request->get['item_code'];
        }else{
            $item_code = null;
        }
        $data['item_code'] = $item_code;
        if (isset($this->request->get['backUrlKey'])) {
            $data['backUrlKey'] = $this->request->get['backUrlKey'];
            $backUrl = $this->cache->get($data['backUrlKey']);
            $data['backUrl'] = $backUrl;
            $url .= "&backUrlKey=" . $this->request->get['backUrlKey'];
        }
        // 类型
        if (!empty($this->request->get['filter_type'])) {
            $type = $this->request->get['filter_type'];
            $url .= "&filter_type=" . $this->request->get['filter_type'];
        } else {
            $type = null;
        }
        $data['filter_type'] = $type;
        // 入库日期
        if (!empty($this->request->get['filter_createTimeStart'])) {
            $createTimeStart = $this->request->get['filter_createTimeStart'];
            $url .= "&filter_createTimeStart=" . $this->request->get['filter_createTimeStart'];
        } else {
            $createTimeStart = null;
        }
        $data['filter_createTimeStart'] = $createTimeStart;
        if (!empty($this->request->get['filter_createTimeEnd'])) {
            $createTimeEnd = $this->request->get['filter_createTimeEnd'];
            $url .= "&filter_createTimeEnd=" . $this->request->get['filter_createTimeEnd'];
        } else {
            $createTimeEnd = null;
        }
        $data['filter_createTimeEnd'] = $createTimeEnd;
        $page = $this->request->get['page'] ?? 1;
        $perPage = $this->request->get['page_limit'] ?? 20;
        $filter_data = array(
            "type" => $type,
            "createTimeStart" => $createTimeStart,
            "createTimeEnd" => $createTimeEnd,
            "customer_id" => $customer_id,
            "item_code" => $item_code,
            "page_num" => $page,
            "page_limit" => $perPage
        );
        // 获取库存记录
        $recordData = $this->model_account_inventory_management->getBuyStorageRecord($filter_data);
        $total = $this->model_account_inventory_management->getBuyStorageRecordCount($filter_data);
        $data['recordData'] = $recordData;
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $perPage;
        $pagination->url = $this->url->link('account/inventory_management/batchStockBuyer'.$url, '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $perPage) + 1 : 0, ((($page - 1) * $perPage) > ($total - $perPage)) ? $total : ((($page - 1) * $perPage) + $perPage), $total, ceil($total / $perPage));
        $this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');


        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['isCollectionFromDomicile'] = $this->customer->isCollectionFromDomicile();
        $this->response->setOutput($this->load->view('account/inventory_management_batch_stock_buyer', $data));
    }

    public function batchStock()
    {
        $this->load->language('account/inventory_management');

        $this->load->model('account/inventory_management');

        $this->document->setTitle($this->language->get('text_title_inventory_stock_in_record'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_title_inventory_management'),
            'href' => $this->url->link('account/inventory_management', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_title_inventory_stock_in_record'),
            'href' => $_SERVER["REQUEST_URI"],
        );

        $product_id = $this->request->get['product_id'];
        $data['product_id'] = $product_id;
        $customer_id = $this->customer->getId();

        // 获取产品
        $product = $this->model_account_inventory_management->getProductById($product_id, $customer_id);
        if ($product) {
            $this->load->model('tool/image');
            if (!(count(explode('http', $product['image'])) > 1)) {
                if (is_file(DIR_IMAGE . $product['image'])) {
                    $image = $this->model_tool_image->resize($product['image']);
                } else {
                    $image = $this->model_tool_image->resize('no_image.png');
                }
                $product['image'] = $image;
            }
            $data['product'] = $product;
            if (isset($product['sku'])) {
                $data['itemCode'] = $product['sku'];
            } else {
                $data['itemCode'] = $product['mpn'];
            }
        }

        if (isset($this->request->get['backUrlKey'])) {
            $data['backUrlKey'] = $this->request->get['backUrlKey'];
            $backUrl = $this->cache->get($data['backUrlKey']);
            $data['backUrl'] = $backUrl;
        }

        // 入库单号
        if (!empty($this->request->get['filter_receivingOrderNumber'])) {
            $receivingOrderNumber = $this->request->get['filter_receivingOrderNumber'];
        } else {
            $receivingOrderNumber = null;
        }
        $data['filter_receivingOrderNumber'] = $receivingOrderNumber;
        // 集装箱号
        if (!empty($this->request->get['filter_containerNumber'])) {
            $containerNumber = $this->request->get['filter_containerNumber'];
        } else {
            $containerNumber = null;
        }
        $data['filter_containerNumber'] = $containerNumber;
        // 入库日期
        if (!empty($this->request->get['filter_receiptDateStart'])) {
            $receiptDateStart = $this->request->get['filter_receiptDateStart'];
        } else {
            $receiptDateStart = null;
        }
        $data['filter_receiptDateStart'] = $receiptDateStart;
        if (!empty($this->request->get['filter_receiptDateEnd'])) {
            $receiptDateEnd = $this->request->get['filter_receiptDateEnd'];
        } else {
            $receiptDateEnd = null;
        }
        $data['filter_receiptDateEnd'] = $receiptDateEnd;
        if (!empty($this->request->get['filter_stockInType'])) {
            $filter_stockInType = $this->request->get['filter_stockInType'];
        } else {
            $filter_stockInType = null;
        }
        $data['filter_stockInType'] = $filter_stockInType;
        // 入库天数
        $inStockDaysStart = get_value_or_default($this->request->get, 'filter_inStockDaysStart', null);
        $inStockDaysEnd = get_value_or_default($this->request->get, 'filter_inStockDaysEnd', null);
        $data['filter_inStockDaysStart'] = $inStockDaysStart;
        $data['filter_inStockDaysEnd'] = $inStockDaysEnd;

        /* 分页 */
        if (isset($this->request->get['page_num'])) {
            $page_num = $this->request->get['page_num'];
        } else {
            $page_num = 1;
        }
        $data['page_num'] = $page_num;
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 20;
        }
        $data['page_limit'] = $page_limit;
        $filter_data = array(
            "receivingOrderNumber" => $receivingOrderNumber,
            "containerNumber" => $containerNumber,
            "receiptDateStart" => $receiptDateStart,
            "receiptDateEnd" => $receiptDateEnd,
            "inStockDaysStart" => $inStockDaysStart,
            "inStockDaysEnd" => $inStockDaysEnd,
            'start' => ($page_num - 1) * $page_limit,
            'limit' => $page_limit,
            'stockInType' => $filter_stockInType,
        );
        //入库类型
        $data['STOCK_IN_TYPE'] = self::STOCK_IN_TYPE;
        $countNum = $this->model_account_inventory_management->getBatchStockInfoCount($filter_data, $customer_id, $product_id);
        $data['batchStockInfo'] = $this->model_account_inventory_management->getBatchStockInfo($filter_data, $customer_id, $product_id);
        $data['csv_filter_data'] = [
            "receivingOrderNumber" => $receivingOrderNumber,
            "containerNumber" => $containerNumber,
            "receiptDateStart" => $receiptDateStart,
            "receiptDateEnd" => $receiptDateEnd,
            "inStockDaysStart" => $inStockDaysStart,
            "inStockDaysEnd" => $inStockDaysEnd,
            'stockInType' => $filter_stockInType,
            'product_id' => $product_id,
            'start' => 0,
        ];

        $total_pages = ceil($countNum / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($countNum) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($countNum - $page_limit)) ? $countNum : ((($page_num - 1) * $page_limit) + $page_limit), $countNum, $total_pages);

        $this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $this->response->setOutput($this->load->view('account/inventory_management_batch_stock', $data));
    }

    public function outStockRecord()
    {
        $this->load->language('account/inventory_management');
        $this->load->model('account/inventory_management');

        $this->document->setTitle($this->language->get('text_out_stock_record'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_title_inventory_management'),
            'href' => $this->url->link('account/inventory_management', '', true)
        );

        if (isset($_POST['stock_in_url'])) {
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_title_inventory_stock_in_record'),
                'href' => $_POST['stock_in_url']
            );
            $data['stock_in_url'] = $this->request->post['stock_in_url'];
        }

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_out_stock_record'),
            'href' => 'javascript:showOutStockRecord();'
        );

        $data['current'] = $_SERVER["REQUEST_URI"];
        $data['itemCode'] = $this->request->get['itemCode'];
        $data['batch_id'] = $this->request->get['batch_id'];
        //出库类型
        $data['STOCK_OUT_TYPE'] = self::STOCK_OUT_TYPE;
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $this->response->setOutput($this->load->view('account/seller_out_stock_detail', $data));
    }

    public function outStockRecordFilter()
    {
        $this->load->language('account/inventory_management');
        $this->load->language('common/cwf');
        $this->load->model('account/inventory_management');
        if (!empty($this->request->get['filter_orderId'])) {
            $order_id = $this->request->get['filter_orderId'];
        } else {
            $order_id = null;
        }
        if (!empty($this->request->get['filter_nickname'])) {
            $filter_nickname = $this->request->get['filter_nickname'];
        } else {
            $filter_nickname = null;
        }
        if (!empty($this->request->get['filter_stockOutType'])) {
            $filter_stockOutType = $this->request->get['filter_stockOutType'];
        } else {
            $filter_stockOutType = null;
        }
        if (!empty($this->request->get['filter_orderDateStart'])) {
            $filter_orderDateStart = $this->request->get['filter_orderDateStart'];
        } else {
            $filter_orderDateStart = null;
        }
        if (!empty($this->request->get['filter_orderDateEnd'])) {
            $filter_orderDateEnd = $this->request->get['filter_orderDateEnd'];
        } else {
            $filter_orderDateEnd = null;
        }

        /* 分页 */
        if (isset($this->request->get['page_num'])) {
            $page_num = $this->request->get['page_num'];
        } else {
            $page_num = 1;
        }
        $data['page_num'] = $page_num;
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 20;
        }
        $data['page_limit'] = $page_limit;

        $filter_data = array(
            "nickname" => $filter_nickname,
            "stockOutType" => $filter_stockOutType,
            "order_id" => $order_id,
            "orderDateStart" => $filter_orderDateStart,
            "orderDateEnd" => $filter_orderDateEnd,
            'start' => ($page_num - 1) * $page_limit,
            'limit' => $page_limit,
        );
        //出库类型
        $data['STOCK_OUT_TYPE'] = self::STOCK_OUT_TYPE;
        $batch_id = $this->request->get['batch_id'];
        $data['out_stocks'] = $this->model_account_inventory_management->getSellerDeliveryLine($batch_id, $filter_data);
        $data['csv_filter_data'] = array(
            "nickname" => $filter_nickname,
            "stockOutType" => $filter_stockOutType,
            "order_id" => $order_id,
            "orderDateStart" => $filter_orderDateStart,
            "orderDateEnd" => $filter_orderDateEnd,
            'start' => 0,
            'batch_id' => $batch_id,
            'itemCode' => $this->request->get['itemCode'],
        );
        $countNum = $this->model_account_inventory_management->getSellerDeliveryLineCount($batch_id, $filter_data);
        $data['total_num'] = $countNum;
        $total_pages = ceil($countNum / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($countNum) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($countNum - $page_limit)) ? $countNum : ((($page_num - 1) * $page_limit) + $page_limit), $countNum, $total_pages);

        $this->response->setOutput($this->load->view('account/seller_out_stock_result', $data));
    }

    public function exportInventoryCsv()
    {
        try {
            $csvTitle = ['No.', 'Item Code', 'MPN', 'Cumulative Number of Entries', 'Cumulative Number of Exits', 'Number of Remaining Inventory', 'Date of Next Arrival', 'Estimated Quantity of Next Arrival'];
            $csv_filter_data = $this->request->post['csv_filter_data'];
            $this->load->model('account/inventory_management');
            list($results, $countNum) = $this->model_account_inventory_management->getProducts($csv_filter_data);
            $csvBody = [];
            foreach ($results as $i => $result) {
                $csvBody[] = [$i + 1,
                    get_value_or_default($result, 'sku', $result['mpn']) . $result['tag_str'],
                    get_value_or_default($result, 'mpn', ''),
                    get_value_or_default($result, 'total_original_qty', ''),
                    get_value_or_default($result, 'total_out_qty', ''),
                    get_value_or_default($result, 'total_onhand_qty', ''),
                    get_value_or_default($result, 'expected_date', ''),
                    get_value_or_default($result, 'expected_qty', ''),
                ];
            }

            $this->load->model('tool/csv');
            $download_href = $this->model_tool_csv->createCsvFile($csvTitle, $csvBody);
            $download_name = sprintf('InventoryReport%s.csv',
                date('Ymd'));
            $json = [
                'success' => true,
                'download_href' => $download_href,
                'download_name' => $download_name
            ];
        } catch (Exception $e) {
            $this->log->write('exportInventoryCsv 导出库存记录失败' . $e->getMessage());
            $json = ['success' => false, 'msg' => 'Operate Failed!'];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function exportStockInCsv()
    {
        try {
            $csv_filter_data = $this->request->post['csv_filter_data'];
            $this->load->model('account/inventory_management');
            $results = $this->model_account_inventory_management->getBatchStockInfo($csv_filter_data, $this->customer->getId(), $csv_filter_data['product_id']);

            $csvTitle = ['No.', 'Item Code', 'Mpn', 'Inventory Batch Quantity', 'Number of Exits', 'Container Number', 'Date of Received', 'Receiving Category', 'Order ID'];
            $csvBody = [];
            $original_qty_sum = 0;
            $exit_qty_sum = 0;
            foreach ($results as $index => $result) {
                $csvBody[] = [
                    $index + 1,
                    get_value_or_default($result, 'sku', $result['mpn']),
                    get_value_or_default($result, 'mpn', ''),
                    $result['original_qty'],
                    $result['original_qty'] - $result['onhand_qty'],
                    get_value_or_default($result, 'container_code', ''),
                    date('Y-m-d', strtotime($result['receive_date'])),
                    self::STOCK_IN_TYPE[$result['stock_in_type']],
                    get_value_or_default($result, 'order_id_name', ''),
                ];
                $original_qty_sum += $result['original_qty'];
                $exit_qty_sum += $result['original_qty'] - $result['onhand_qty'];
            }
            $csvBody[] = ['sum','','',$original_qty_sum,$exit_qty_sum];

            $this->load->model('tool/csv');
            $download_href = $this->model_tool_csv->createCsvFile($csvTitle, $csvBody);
            $download_name = sprintf('Receiving record of %s %s.csv',
                $this->request->post['itemCode'],
                date('Ymd'));
            $json = [
                'success' => true,
                'download_href' => $download_href,
                'download_name' => $download_name
            ];
        } catch (Exception $e) {
            $this->log->write('exportStockInCsv 导出入库记录失败' . $e->getMessage());
            $json = ['success' => false, 'msg' => 'Operate Failed!'];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function exportStockOutCsv()
    {
        try {
            $csv_filter_data = $this->request->post['csv_filter_data'];
            $this->load->model('account/inventory_management');
            $out_stocks = $this->model_account_inventory_management->getSellerDeliveryLine($csv_filter_data['batch_id'], $csv_filter_data);

            $csvTitle = ['No.','Item Code','MPN','Price','Quantity','Dispatch Category','Order ID','Creation Time','Nickname'];
            $csvBody = [];
            $qty_sum = 0;
            foreach ($out_stocks as $index => $out_stock){
                $csvBody[] = [
                    $index + 1,
                    get_value_or_default($out_stock, 'sku', $out_stock['mpn']),
                    get_value_or_default($out_stock, 'mpn', ''),
                    get_value_or_default($out_stock, 'price', ''),
                    get_value_or_default($out_stock, 'qty', ''),
                    self::STOCK_OUT_TYPE[$out_stock['stock_out_type']],
                    get_value_or_default($out_stock, 'order_id_name', ''),
                    get_value_or_default($out_stock, 'CreateTime', ''),
                    get_value_or_default($out_stock, 'nickname', ''),
                ];
                if(!empty($out_stock['qty'])){
                    $qty_sum += (int)$out_stock['qty'];
                }
            }
            $csvBody[] = ['sum','','','',$qty_sum];
            $this->load->model('tool/csv');
            $download_href = $this->model_tool_csv->createCsvFile($csvTitle, $csvBody);
            $download_name = sprintf('Dispatch record of %s %s.csv',
                $csv_filter_data['itemCode'],
                date('Ymd'));
            $json = [
                'success' => true,
                'download_href' => $download_href,
                'download_name' => $download_name
            ];
        } catch (Exception $e) {
            $this->log->write('exportStockOutCsv 导出出库记录失败' . $e->getMessage());
            $json = ['success' => false, 'msg' => 'Operate Failed!'];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    public function soldNotShipedOrder(){

        $this->load->model('account/inventory_management');
        $results = $this->model_account_inventory_management->getSoldOutOrder($this->request->get('sku'), $this->customer->getId());
        $total = 0;
        $backUrlKey= $this->request->get['backUrlKey'];
        //101592 一件代发 taixing
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if($isCollectionFromDomicile){
            $order_string = 'index.php?route=account/customer_order/customerOrderSalesOrderDetails&id=';
        }else{
            $order_string = 'index.php?route=account/sales_order/sales_order_management/customerOrderSalesOrderDetails&id=';
        }
        foreach ($results as $result){
            if(!in_array($result['seller_id'],SERVICE_STORE_ARRAY)) {
                $total++;
                $data['data'][] = array(
                    'salesOrderId' => $result['order_type'] == 'sales_order' ?
                        "<a href='".$order_string.$result['id'] . "'>" . $result['order_id'] . " <i class='giga icon-table-link'></i></a>" :
                        ($result['order_type'] == 'cwf_order'?
                            "<a href='index.php?route=Account/Sales_Order/CloudWholesaleFulfillment/info&id=" . $result['ocl_id']."'>" . $result['order_id'] . " <i class='giga icon-table-link'></i></a>":
                        "<a href='index.php?route=account/rma_order_detail&rma_id=" . $result['id'] ."&backUrlKey=".$backUrlKey."'>" . $result['order_id'] . " <i class='giga icon-table-link'></i></a>"),
                    'checkoutTime' => $result['checkoutTime'],
                    'quantity' => $result['qty']
                );
            }
        }
        $data['total'] = $total;
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['total' => $data['total'], 'rows' => $data['data']]));
    }

    public function downloadInventory(){
        set_time_limit(0);
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/inventory_management', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        if (isset($this->request->get['filter_sku']) && trim($this->request->get['filter_sku']) != '') {
            $sku = trim($this->request->get['filter_sku']);
        } else {
            $sku = null;
        }
        if (isset($this->request->get['filter_stockNumberFlag']) && trim($this->request->get['filter_stockNumberFlag']) != '') {
            $stockNumberFlag = trim($this->request->get['filter_stockNumberFlag']);
        } else {
            $stockNumberFlag = null;
        }
        $customer_id = $this->customer->getId();
        $filter_data = array(
            'sku' => $sku,
            'stockNumberFlag' => $stockNumberFlag,
            'customer_id' => $customer_id
        );
        $this->load->language('account/inventory_management');
        $this->load->model('account/inventory_management');
        $results = $this->model_account_inventory_management->getBuyerProductCost($filter_data);
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'YmdHis');
        //12591 end
        $fileName = "InventoryReport" . $time . ".csv";
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo chr(239) . chr(187) . chr(191);

        $fp = fopen('php://output', 'a');

        $head = array('Item Code', 'Cumulative Number of Entries', 'Cumulative Number of Exits', 'Number of Remaining Inventory', 'Sold but not Shipped','Blocked Stock', 'Number of Available Inventory');

        foreach ($head as $i => $v) {
            // CSV的Excel支持GBK编码，一定要转换，否则乱码
            $head [$i] = iconv('utf-8', 'gbk', $v);
        }
        fputcsv($fp, $head);

        if (isset($results) && !empty($results)) {
            foreach ($results as $result) {
                //累计出库数
                $outStockQty = $this->model_account_inventory_management->getOutStockQty($result['sku'], $customer_id);
                $result['outStockQty'] = $outStockQty['outStockQty'];
                //可用库存数
                $productCostQty = $this->model_account_inventory_management->getProductCostBySku($result['sku'], $customer_id);
                $result['productCostQty'] = get_value_or_default($productCostQty,'orginalQty',0)
                    -get_value_or_default($productCostQty,'associatedQty',0)
                    -get_value_or_default($productCostQty,'qty',0);
                // 获取已售未发的商品数量
                $soldOutInfo = $this->model_account_inventory_management->getSoldOutCount($result['sku'], $customer_id);
                $result['sold_out'] = get_value_or_default($soldOutInfo, 'qty', 0);
                //冻结库存
                $avilableQty = $result['productCostQty'];
                $result['blocked_stock'] = $result['onhandQty'] - $avilableQty- $result['sold_out'];
                $content = array(
                    $result['sku'],
                    $result['originalQty'],
                    $result['outStockQty'],
                    $result['onhandQty'],
                    $result['sold_out'],
                    $result['blocked_stock'],
                    $result['productCostQty']
                );
                fputcsv($fp, $content);
            }
        } else {
            $content = array($this->language->get('error_no_record'));
            fputcsv($fp, $content);
        }

        //rewind($fp);
        $output = stream_get_contents($fp);
        fclose($fp);
        return $output;

    }

    public function downloadInventoryRecord(){
        set_time_limit(0);
        $customer_id = $this->customer->getId();
        $data['customer_id'] = $customer_id;
        if (isset($this->request->get['item_code'])) {
            $item_code = $this->request->get['item_code'];
        }else{
            $item_code = null;
        }
        $data['item_code'] = $item_code;
        if (isset($this->request->get['backUrlKey'])) {
            $data['backUrlKey'] = $this->request->get['backUrlKey'];
            $backUrl = $this->cache->get($data['backUrlKey']);
            $data['backUrl'] = $backUrl;
        }
        // 类型
        if (!empty($this->request->get['filter_type'])) {
            $type = $this->request->get['filter_type'];
        } else {
            $type = null;
        }
        $data['filter_type'] = $type;
        // 入库日期
        if (!empty($this->request->get['filter_createTimeStart'])) {
            $createTimeStart = $this->request->get['filter_createTimeStart'];
        } else {
            $createTimeStart = null;
        }
        $data['filter_createTimeStart'] = $createTimeStart;
        if (!empty($this->request->get['filter_createTimeEnd'])) {
            $createTimeEnd = $this->request->get['filter_createTimeEnd'];
        } else {
            $createTimeEnd = null;
        }
        $data['filter_createTimeEnd'] = $createTimeEnd;
        $filter_data = array(
            "type" => $type,
            "createTimeStart" => $createTimeStart,
            "createTimeEnd" => $createTimeEnd,
            "customer_id" => $customer_id,
            "item_code" => $item_code
        );
        $this->load->language('account/inventory_management');
        // 获取库存记录
        $this->load->model('account/inventory_management');
        $results = $this->model_account_inventory_management->getBuyStorageRecord($filter_data);
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'Ymd');
        //12591 end
        $fileName = $item_code."_Record" .$time . ".csv";
        $head = array('Item Code', 'Creation Time', 'Type', 'Store Name', 'Quantity', 'Reason','Order ID');
        if (isset($results) && !empty($results)) {
            foreach ($results as $result) {
                $content[] = array(
                    $result['sku'],
                    $result['creationTime'],
                    $result['type'],
                    $result['screenname'],
                    $result['type'] == 'Receiving'?$result['quantity']:'-'.$result['quantity'],
                    $result['reason'],
                    $result['orderID']
                );
            }
            //12591 B2B记录各国别用户的操作时间
            outputCsv($fileName,$head,$content,$this->session);
            //12591 end
        }else{
            $content[] = array($this->language->get('error_no_record'));
            //12591 B2B记录各国别用户的操作时间
            outputCsv($fileName,$head,$content,$this->session);
            //12591 end
        }
    }
}
