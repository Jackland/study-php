<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Enums\Customer\CustomerAccountingType;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use App\Repositories\Product\ProductRepository;
use App\Enums\Warehouse\ReceiptOrderStatus;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Repositories\ProductLock\ProductLockRepository;
use App\Components\Storage\StorageCloud;
use Carbon\Carbon;

/**
 * 根据ControllerAccountCustomerpartnerProductManage改版而来，因为需求开始做的是全国别，后又把US单拿出来，当时Product Management又有新需求改动且还没上线，所以只能新写一个针对US
 * Class ControllerCustomerpartnerWarehouseInventory
 *
 * @property ModelCatalogFuturesProductLock $model_catalog_futures_product_lock
 * @property ModelCatalogMarginProductLock $model_catalog_margin_product_lock
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCatalogSalesOrderProductLock $model_catalog_sales_order_product_lock
 * @property ModelCommonProduct $model_common_product
 * @property ModelCustomerpartnerProductManage $model_customerpartner_product_manage
 * @property ModelToolImage $model_tool_image
 * @property ModelToolUpload $model_tool_upload
 * @property ModelToolExcel $model_tool_excel
 */
class ControllerCustomerpartnerWarehouseInventory extends AuthSellerController
{
    const PRODUCT_TYPE_GENERAL = 1;
    const PRODUCT_TYPE_COMBO = 2;
    const PRODUCT_TYPE_LTL = 4;
    const PRODUCT_TYPE_PART = 8;

    private $data = array();

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        if ($this->customer->getCountryId() != AMERICAN_COUNTRY_ID) {
            // 非美国Seller 不能操作
            return $this->redirect(['account/account'])->send();
        }
    }

    /**
     * @throws Exception
     */
    public function index()
    {
        // 加载Language
        $this->language->load('account/account');
        $this->load->language('account/customerpartner/product_manage');
        // 设置文档标题
        $this->document->setTitle($this->language->get('heading_title_product_manage'));
        // 加载Model层
        $this->load->model('customerpartner/product_manage');
        $this->load->model('common/product');
        $this->load->model('catalog/product');
        // 获取查询结果
        $this->getList();
    }

    /**
     * @throws Exception
     */
    private function getList()
    {
        // 检查账户状态
        $this->checkAccount();
        // 加载面包屑导航
        $this->setHeadLink();
        // 加载页面显示架构
        $this->loadFramework();
        // 查询数据
        $filter_product_type = $this->request->get('filter_product_type');
        if (isset($filter_product_type)) {
            $filter_product_type = $filter_product_type ?? [0];
            if (is_string($filter_product_type) || is_int($filter_product_type)) {
                $filter_product_type = [$filter_product_type];
            }
            $filter_product_type = array_sum($filter_product_type);
        } else {
            $filter_product_type = 1 + 2 + 4 + 8;
        }

        $page = $this->request->get('page', 1);
        $limit = $this->request->get('page_limit', $this->config->get('theme_yzcTheme_product_limit'));
        $param = [
            'filter_sku_mpn' => $this->request->get('filter_sku_mpn', null),
            'filter_status' => $this->request->get('filter_status', null),
            'filter_buyer_flag' => $this->request->get('filter_buyer_flag', null),
            'filter_available_qty' => $this->request->get('filter_available_qty', null),
            'filter_product_type' => $filter_product_type,
            'filter_deposit' => true,
            'sort' => $this->request->get('sort', 'pd.name'),
            'order' => $this->request->get('sort', 'ASC'),
            'start' => ($page - 1) * $limit,
            'limit' => $limit
        ];
        $customer_id = (int)$this->customer->getId();
        $product_total = $this->model_customerpartner_product_manage->querySellerProductNum($param, $customer_id);

        $results = $this->model_customerpartner_product_manage->querySellerProducts($param, $customer_id);

        //N-457********
        $product_id_list = array_column($results, 'product_id');
        $sell_count = $this->model_customerpartner_product_manage->get_sell_count($product_id_list);
        foreach ($results as $k => $v) {
            $results[$k]['day_sell'] = isset($sell_count['days'][$k]) ? $sell_count['days'][$k] : 0;
        }
        //*****************

        //在途数量
        $transitNum = app(ProductRepository::class)->countProductQtyInReceiptsStatus($product_id_list, ReceiptOrderStatus::TO_BE_RECEIVED);
        $transitNumArr = array_column($transitNum, 'expectedQtySum', 'product_id');


        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $num = $param['start'] + 1;
        foreach ($results as $key => $result) {
            $productId = intval($result['product_id']);
            $tag_array = $this->model_catalog_product->getProductSpecificTag($productId);
            $tags = array();
            if (isset($tag_array)) {
                foreach ($tag_array as $tag) {
                    if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                        //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                        $tags[] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '"  title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                    }
                }
            }

            $result['tag'] = $tags;
            $result['num'] = $num++;
            // 锁定库存 (现货保证金，期货保证金)
            $result['lock_qty'] = $this->model_common_product->getProductLockQty($productId);
            // 获取限时限量折扣
            $result['time_limit_qty'] = 0;
            $timeLimitDiscount = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountInfo($productId);
            if ($timeLimitDiscount) {
                $result['time_limit_qty'] = intval($timeLimitDiscount->qty) + intval($timeLimitDiscount->other_time_limit_qty);
            }
            //在途数量
            $result['transitNum'] = $transitNumArr[$result['product_id']] ?? '';
            //出入库数量内部显示N/A
            $result['receiveNum'] = 'N/A';
            $result['deliveryNum'] = 'N/A';
            if ($this->customer->getAccountType() == CustomerAccountingType::OUTER) {
                $stock_query = $this->model_catalog_product->queryStockByProductId($result['product_id']);
                $result['receiveNum'] = $stock_query['total_original_qty'];
                $deliveryNum = $stock_query['total_original_qty'] - $stock_query['total_onhand_qty'];
                $result['deliveryNum'] = $deliveryNum < 0 ? 0 : $deliveryNum;
            }
            $results[$key] = $result;
        }
        $this->data['products'] = $results;
        $this->data = array_merge($this->data, $param);

        $this->setPanination($product_total, $page, $limit);

        $this->data['country_id'] = $this->customer->getCountryId();

        $this->response->setOutput($this->load->view('customerpartner/warehouse/inventory', $this->data));
    }

    /**
     * 虽然有遍历逻辑，但是只有一条数据，本方法不用于批量更新
     */
    public function update()
    {
        // 加载Model层
        $this->load->model('common/product');
        $this->load->model('customerpartner/product_manage');
        $this->load->language('account/customerpartner/product_manage');
        $products = $this->request->post('products');
        //非负整数
        $qty_reg = '/^\d+$/';
        if (isset($products)) {
            //虽然有遍历逻辑，但是只有一条数据，本方法不用于批量更新
            foreach ($products as $product) {
                if ($product['quantity_display'] !== "0" && $product['quantity_display'] !== "1") {
                    return $this->jsonFailed($this->language->get('error_qty_display'));
                }
                if (!isset($product['quantity']) || !preg_match($qty_reg, $product['quantity'])) {
                    return $this->jsonFailed($this->language->get('error_qty_positive'));
                }
                //校验上架数不能大于现有库存(不使用前台在库库存数，防止数据是被篡改的假数据)
                $product_id_line[$product['product_id']] = $product['quantity'];
                $invalid_product = $this->model_customerpartner_product_manage->validateStock($product_id_line);
                if (!empty($invalid_product)) {
                    $error_arr = [];
                    foreach ($invalid_product as $product_id) {
                        $combo = $this->model_common_product->getComboProduct($product_id);
                        // combo品和普通商品的提示信息不一样
                        if (!empty($combo)) {
                            $error_arr[] = dprintf(
                                $this->language->get('error_stock_warning_lock'),
                                $this->getLockQtyInfo($product_id)['totalLockQty']
                            );
                        } else {
                            $error_arr[] = $this->language->get('error_stock_warning');
                        }
                    }
                    return $this->jsonFailed(join('<br>', $error_arr));
                }
            }
        }
        //执行更新
        $this->model_customerpartner_product_manage->updateSellerProduct($products);
        return $this->jsonSuccess();
    }

    private function getUrlParam()
    {
        $urlParam = [];
        $filter_object = array(
            'filter_sku_mpn',
            'filter_sku',
            'filter_mpn',
            'filter_effectTimeFrom',
            'filter_effectTimeTo',
            'filter_status',
            'sort',
            'order',
            'filter_product_type',
            'filter_buyer_flag',
            'filter_available_qty'
        );

        foreach ($filter_object as $item) {
            if (isset($this->request->get[$item])) {
                $urlParam[$item] = $this->request->get[$item];
            }
        }
        return http_build_query($urlParam);
    }

    /**
     * @param int $product_total
     * @param int $page
     * @param int $pageSize
     */
    private function setPanination($product_total, $page, $pageSize)
    {
        $pagination = new Pagination();
        $pagination->total = $product_total;
        $pagination->page = $page;
        $pagination->limit = $pageSize;
        $pagination->url = $this->url->link('customerpartner/warehouse/inventory', '' . $this->getUrlParam() . '&page={page}', true);

        $this->data['pagination'] = $pagination->render();

        $this->data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $pageSize) + 1 : 0, ((($page - 1) * $pageSize) > ($product_total - $pageSize)) ? $product_total : ((($page - 1) * $pageSize) + $pageSize), $product_total, ceil($product_total / $pageSize));
    }

    private function loadFramework()
    {
        $this->data['column_left'] = $this->load->controller('common/column_left');
        $this->data['column_right'] = $this->load->controller('common/column_right');
        $this->data['content_top'] = $this->load->controller('common/content_top');
        $this->data['content_bottom'] = $this->load->controller('common/content_bottom');
        $this->data['footer'] = $this->load->controller('common/footer');
        $this->data['header'] = $this->load->controller('common/header');

        $this->data['separate_view'] = false;

        $this->data['separate_column_left'] = '';

        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $this->data['separate_view'] = true;
            $this->data['column_left'] = '';
            $this->data['column_right'] = '';
            $this->data['content_top'] = '';
            $this->data['content_bottom'] = '';
            $this->data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $this->data['margin'] = "margin-left: 18%";
            $this->data['footer'] = $this->load->controller('account/customerpartner/footer');
            $this->data['header'] = $this->load->controller('account/customerpartner/header');
        }
    }

    // 加载面包屑导航
    private function setHeadLink()
    {
        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title_product_manage'),
            'href' => $this->url->link('customerpartner/warehouse/inventory', '', true),
            'separator' => $this->language->get('text_separator')
        );
        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title_available_inventory'),
            'href' => 'javascript:void(0);',
            'separator' => $this->language->get('text_separator')
        );

    }

    // 检查账户状态
    private function checkAccount()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/productmanage', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->load->model('account/customerpartner');
        $this->data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();
        if (!$this->data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode'])) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }
    }

    public function importFile()
    {
        $this->load->language('account/customerpartner/product_manage');
        $this->load->model('customerpartner/product_manage');
        $this->load->model('tool/upload');
        $this->load->model('tool/excel');

        $validation = $this->request->validate([
            'file' => [
                'required',
                'extension:xls,xlsx'
            ]
        ]);
        if ($validation->fails()) {
            return $this->jsonFailed($validation->errors()->first());
        }

        $fileInfo = $this->request->file('file');
        //放置文件
        $customerId = $this->customer->getId();
        $runId = time();
        $fileType = $fileInfo->getClientOriginalExtension();
        $realFileName = $runId . '.' . $fileType;
        $filePath = StorageCloud::priceCsv()->writeFile($fileInfo, $customerId, $realFileName);

        // 记录上传文件数据
        $fileData = array(
            "file_name" => $fileInfo->getClientOriginalName(),
            "size" => $fileInfo->getSize(),
            "file_path" => $filePath,
            "customer_id" => $customerId,
            "run_id" => $runId,
            "create_user_name" => $customerId,
            "create_time" => Carbon::now()
        );
        $this->model_tool_upload->saveUploadFileRecord($fileData);

        $realFilePath = StorageCloud::priceCsv()->getLocalTempPath(StorageCloud::priceCsv()->getRelativePath($filePath));
        $excelData = $this->model_tool_excel->getExcelData($realFilePath);
        StorageCloud::priceCsv()->deleteLocalTempFile($realFilePath);

        $header = [
            $this->language->get('column_mpn'),// Seller MPN
            $this->language->get('column_quantity'),//商品数量
            $this->language->get('column_quantity_display'),//是否不建立联系就可见数量
        ];
        //校验是否为空、表头
        if (!isset($excelData) || $excelData[1] != $header || count($excelData) <= 2) {
            return $this->jsonFailed($this->language->get('error_inventory_file_content'));
        }

        $uniqueMpn = [];
        $uniqueProductId = [];
        $productIdAndQty = [];
        $products = [];
        foreach ($excelData as $key => $val) {
            //从第二行开始校验内容
            if ($key <= 1) {
                continue;
            }

            $mpn = trim($val[0]) ?? '';
            $qty = trim($val[1]) ?? '';
            $qtyDisplay = trim($val[2]) ?? '';
            $line = 'Line' . ($key + 1) . ',';
            //数量
            if ($qty == '') {
                return $this->jsonFailed($line . $this->language->get('column_quantity') . $this->language->get('error_upload_column_content_air'));
            }
            if (!is_numeric($qty)) {
                return $this->jsonFailed($line . $this->language->get('error_qty_nan'));
            }
            if ($qty < 0) {
                return $this->jsonFailed($line . $this->language->get('error_qty_negative'));
            }
            if ($qtyDisplay == '') {
                return $this->jsonFailed($line . $this->language->get('column_quantity_display') . $this->language->get('error_upload_column_content_air'));
            }
            if (null === $this->checkYesOrNo($qtyDisplay)) {
                return $this->jsonFailed($line . $this->language->get('error_qty_display'));
            }

            //mpn
            if ($mpn == '') {
                return $this->jsonFailed($line . $this->language->get('column_mpn') . $this->language->get('error_upload_column_content_air'));
            }

            //查询数据是否存在
            $productInfo = $this->model_customerpartner_product_manage->getProductIdByMpnOrSku($customerId, $mpn);
            $productId = $productInfo ? $productInfo[0] : null;  // 商品id
            $productType = $productInfo ? $productInfo[1] : null; // 商品类型id 用于区分是否为保证金产品
            if ($productType !== null && $productType != 0 && $productType != 3) {
                return $this->jsonFailed($line . $this->language->get('error_column_deposit'));
            }
            if ($productId == '' || $productId === null) {
                return $this->jsonFailed($line . sprintf($this->language->get('error_upload_column_content'), $this->language->get('column_mpn'), $mpn));
            }
            if (in_array($productId, $uniqueProductId) || in_array($mpn, $uniqueMpn)) {
                return $this->jsonFailed($line . $this->language->get('error_column_duplicate'));
            }
            array_push($uniqueMpn, $mpn);
            array_push($uniqueProductId, $productId);

            $productIdAndQty[$productId] = $qty;
            //校验上架数不能大于库存数
            $invalidProduct = $this->model_customerpartner_product_manage->validateStock($productIdAndQty);
            if (!empty($invalidProduct)) {
                $error_arr = [];
                foreach ($invalidProduct as $pId) {
                    $combo = $this->model_common_product->getComboProduct($pId);
                    // combo品和普通商品的提示信息不一样
                    if (!empty($combo)) {
                        $error_arr[] = dprintf(
                                $this->language->get('error_stock_warning_lock'),
                                $this->getLockQtyInfo($pId)['totalLockQty']
                            ) . 'Line:' . ($key + 1);
                    } else {
                        $error_arr[] = $this->language->get('error_stock_warning');
                    }
                }
                if ($error_arr) {
                    return $this->jsonFailed($line . join('<br>', $error_arr));
                }
            }

            $products[] = [
                "product_id" => $productId,
                'item_code' => $productId ? $productId[2] : null,
                "quantity" => $qty ?? null,
                "quantity_display" => $this->checkYesOrNo($qtyDisplay) ?? null,
            ];
        }
        //执行更新
        $this->model_customerpartner_product_manage->updateSellerProduct($products);
        return $this->jsonSuccess($this->language->get('success_upload'));
    }

    /**
     * 下载订单模板文件
     */
    public function downloadTemplateFile()
    {
        $file = DIR_DOWNLOAD . "Available Quantity.xlsx";
        return $this->response->download($file);
    }

    /**
     * 下载列表
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws Exception
     */
    public function downloadProductInformation()
    {
        $this->load->language('account/customerpartner/product_manage');
        $this->load->model('customerpartner/product_manage');
        $this->load->model('common/product');
        $this->load->model('catalog/product');

        //获取查询条件
        // 查询数据
        $filter_product_type = $this->request->get('filter_product_type');
        if (isset($filter_product_type)) {
            $filter_product_type = $filter_product_type ?? [0];
            if (is_string($filter_product_type) || is_int($filter_product_type)) {
                $filter_product_type = [$filter_product_type];
            }
            $filter_product_type = array_sum($filter_product_type);
        } else {
            $filter_product_type = 1 + 2 + 4 + 8;
        }
        $param = [
            'filter_sku_mpn' => $this->request->get('filter_sku_mpn', null),
            'filter_status' => $this->request->get('filter_status', null),
            'filter_buyer_flag' => $this->request->get('filter_buyer_flag', null),
            'filter_available_qty' => $this->request->get('filter_available_qty', null),
            'filter_product_type' => $filter_product_type,
            'filter_deposit' => true,
            'sort' => $this->request->get('sort', 'pd.name'),
            'order' => $this->request->get('sort', 'ASC')
        ];
        $customer_id = (int)$this->customer->getId();
        $fileName = "inventory download.xlsx";
        $spresadsheet = new Spreadsheet();
        $spresadsheet->setActiveSheetIndex(0);
        $sheet = $spresadsheet->getActiveSheet();
        $sheet->getDefaultColumnDimension()->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('I')->setWidth(38);
        $sheet->setTitle('inventory download');

        $head = array(
            'Item Code',
            'MPN',
            'Product Name',
            'Product Type',
            'Sold Separately',
            'MPN of Sub-items',
            'In stock Quantity',
            'Lock Quantity',
            'Lock Reason',
            'Cumulative Number of Entries',
            'Cumulative Number of Exits',
            '30-Day Sales Volume',
            'Total Sales Volume',
            'Available Quantity',
            'Promotional Quantity',
            'Estimated Quantity of Next Arrival',
            'Status'
        );
        //表头
        $sheet->fromArray($head, null, 'A1');
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        //居中样式
        $styleArray = array(
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER, // 水平居中
                'vertical' => Alignment::VERTICAL_CENTER // 垂直居中
            ]
        );

        $results = $this->model_customerpartner_product_manage->getCommodityStatistics($param, $customer_id);
        //N-457********
        $product_id_list = array_keys($results);
        $sell_count = $this->model_customerpartner_product_manage->get_sell_count($product_id_list);
        foreach ($results as $k => $v) {
            $results[$k]['day_sell'] = isset($sell_count['days'][$k]) ? $sell_count['days'][$k] : 0;
            $results[$k]['all_sell'] = isset($sell_count['all'][$k]) ? $sell_count['all'][$k] : 0;
        }
        //*****************
        if (isset($results) && !empty($results)) {
            // productType
            $productTypeMap = [
                static::PRODUCT_TYPE_GENERAL => 'General',
                static::PRODUCT_TYPE_COMBO => 'Combo',
                static::PRODUCT_TYPE_LTL => 'LTL',
                static::PRODUCT_TYPE_PART => 'Part',
            ];

            //在途数量
            $transitNum = app(ProductRepository::class)->countProductQtyInReceiptsStatus($product_id_list, ReceiptOrderStatus::TO_BE_RECEIVED);
            $transitNumArr = array_column($transitNum, 'expectedQtySum', 'product_id');

            foreach ($results as $productId => $detail) {
                // productType 解析
                /** @see ModelCustomerpartnerProductManage::getProductType() */
                $product_type = $detail['product_type'];
                $temp_type_name = [];
                array_map(function ($k, $v) use ($product_type, &$temp_type_name) {
                    ($k & $product_type) && $temp_type_name[] = $v;
                }, array_keys($productTypeMap), $productTypeMap);
                // end productType

                //出入库数量内部显示N/A
                $receiveNum = 'N/A';
                $deliveryNum = 'N/A';
                if ($this->customer->getAccountType() == CustomerAccountingType::OUTER) {
                    $stock_query = $this->model_catalog_product->queryStockByProductId($productId);
                    $receiveNum = (int)$stock_query['total_original_qty'];
                    $deliveryNum = $stock_query['total_original_qty'] - $stock_query['total_onhand_qty'];
                    $deliveryNum = (int)$deliveryNum < 0 ? 0 : $deliveryNum;
                }

                $lockQuantity = max($detail['instock_qty'], 0) - $this->model_common_product->getProductAvailableQuantity($productId);

                // 获取限时限量折扣
                $detail['time_limit_qty'] = 0;
                $timeLimitDiscount = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountInfo($productId);
                if ($timeLimitDiscount) {
                    $detail['time_limit_qty'] = intval($timeLimitDiscount->qty) + intval($timeLimitDiscount->other_time_limit_qty);
                }
                
                $content[] = array(
                    $detail['item_code'],
                    $detail['mpn'],
                    $detail['product_name'],
                    join(',', $temp_type_name),
                    $detail['buyer_flag'] ? 'Yes' : 'No',
                    $detail['mpn_in_detail'],
                    $detail['instock_qty'] > 0 ? $detail['instock_qty'] : '0',
                    $lockQuantity > 0 ? $lockQuantity : '',
                    join(PHP_EOL, $this->getLockQtyInfo($productId)['msgLockQty']),
                    is_string($receiveNum) ? 'N/A' : ($receiveNum > 0 ? $receiveNum : '0'),
                    is_string($receiveNum) ? 'N/A' : ($deliveryNum > 0 ? $deliveryNum : '0'),
                    $detail['day_sell'] > 0 ? $detail['day_sell'] : '0',
                    $detail['all_sell'] > 0 ? $detail['all_sell'] : '0',
                    $detail['on_shelf_qty'] > 0 ? $detail['on_shelf_qty'] : '0',      //Available Quantity
                    $detail['time_limit_qty'],                                        //Promotional Quantity
                    $transitNumArr[$productId] ?? '',
                    $detail['status'],
                );
            }
            //处理并填充数据
            $sheet->fromArray($content, null, 'A2');
            $sheet->getStyle('A1:P' . (count($content) + 2))->applyFromArray($styleArray);
            $sheet->getStyle('C2:C' . (count($content) + 2))->getAlignment()->setWrapText(true);
            $sheet->getStyle('F2:F' . (count($content) + 2))->getAlignment()->setWrapText(true);
            $sheet->getStyle('I2:I' . (count($content) + 2))->getAlignment()->setWrapText(true);

        } else {
            $content = array($this->language->get('error_no_record'));
            $sheet->mergeCells('A2:p2');
            $sheet->fromArray($content, null, 'A2');
            $sheet->getStyle('A1:P2')->applyFromArray($styleArray);
        }

        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($spresadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    /**
     * 校验一个字符是否为 yes 或者 no 大小写不限
     * @param string $str
     * @return null|int 1: $str为yes 0: $str为no null:无效：
     */
    private function checkYesOrNo(string $str = ''): ?int
    {
        $str = strtolower($str);
        $ret = null;
        switch ($str) {
            case 'yes':
                $ret = 1;
                break;
            case 'no':
                $ret = 0;
                break;
        }
        return $ret;
    }

//    //锁库存信息
//    public function getLockQtyInfo($productId)
//    {
//        $this->load->model('catalog/margin_product_lock');
//        $this->load->model('catalog/futures_product_lock');
//        $this->load->model('catalog/sales_order_product_lock');
//        $msgLockQty = [];
//        // 现货锁定库存
//        $marginLockQty = $this->model_catalog_margin_product_lock->getProductMarginComputeQty($productId);
//        $marginLockQty > 0 && $msgLockQty[] = "{$marginLockQty}[Margin agreement locked inventory]";
//        // 期货锁定库存
//        $futuresLockQty = $this->model_catalog_futures_product_lock->getProductFuturesComputeQty($productId);
//        $futuresLockQty > 0 && $msgLockQty[] = "{$futuresLockQty}[Future goods agreement locked inventory]";
//        // 纯物流锁定库存
//        $salesOrderLockQty = $this->model_catalog_sales_order_product_lock->getProductSalesOrderComputeQty($productId);
//        $salesOrderLockQty > 0 && $msgLockQty[] = "{$salesOrderLockQty}[Sales Order locked inventory]";
//        //库存调整锁定库存
//        $sellerInventoryAdjustLockQty = app(ProductLockRepository::class)->getProductSellerInventoryAdjustComputeQty($productId);
//        $sellerInventoryAdjustLockQty > 0 && $msgLockQty[] = "{$sellerInventoryAdjustLockQty}[Inventory Adjustment locked inventory]";
//        return [
//            'totalLockQty' => intval($marginLockQty + $futuresLockQty + $salesOrderLockQty + $sellerInventoryAdjustLockQty),
//            'msgLockQty' => $msgLockQty
//        ];
//    }

    /**
     * 锁库存信息
     *
     * #25219 Combo的锁定数量为子SKU锁定数量取高，Lock Reason相为对应子SKU的 Lock Reason
     *
     * @param int $productId
     * @return array
     * @throws Exception
     */
    private function getLockQtyInfo(int $productId)
    {
        $this->load->model('common/product');
        $availableQty = $this->model_common_product->getProductAvailableQuantity($productId);
        $inStockQty = $this->model_common_product->getProductInStockQuantity($productId);
        if ($inStockQty == $availableQty) {
            return [
                'totalLockQty' => 0,
                'msgLockQty' => [],
            ];
        }

        $this->load->model('catalog/margin_product_lock');
        $this->load->model('catalog/futures_product_lock');
        $this->load->model('catalog/sales_order_product_lock');
        $marginLockQty = $this->model_catalog_margin_product_lock->getProductMarginQty($productId);
        $futuresLockQty = $this->model_catalog_futures_product_lock->getProductFuturesQty($productId);
        $salesOrderLockQty = $this->model_catalog_sales_order_product_lock->getProductSalesOrderQty($productId);
        $sellerInventoryAdjustLockQty = app(ProductLockRepository::class)->getProductSellerInventoryAdjustQty($productId);

        // 子产品
        $childProducts = $this->model_common_product->getComboProduct($productId);
        foreach ($childProducts as $childProduct) {
            $realMarginQty = (int)$this->model_catalog_margin_product_lock->getProductMarginQty($childProduct['product_id'], null, [$productId]);
            $realFuturesQty = (int)$this->model_catalog_futures_product_lock->getProductFuturesQty($childProduct['product_id'], null, [$productId]);
            $realSalesOrderQty = (int)$this->model_catalog_sales_order_product_lock->getProductSalesOrderQty($childProduct['product_id'], null, [$productId]);
            $realSellerInventoryAdjustQty = (int)app(ProductLockRepository::class)->getProductSellerInventoryAdjustQty($childProduct['product_id'], [$productId]);

            $childInStockQty = $this->model_common_product->getProductInStockQuantity($childProduct['product_id']);
            $childSumLockQty = $realMarginQty + $realFuturesQty + $realSalesOrderQty + $realSellerInventoryAdjustQty;

            if (floor(($childInStockQty - $childSumLockQty) /  $childProduct['qty']) == ($availableQty + $marginLockQty + $futuresLockQty + $sellerInventoryAdjustLockQty + $salesOrderLockQty)) {
                $marginLockQty +=  (int)ceil($realMarginQty / $childProduct['qty']);
                $futuresLockQty +=  (int)ceil($realFuturesQty / $childProduct['qty']);
                $salesOrderLockQty +=  (int)ceil($realSalesOrderQty / $childProduct['qty']);
                $sellerInventoryAdjustLockQty +=  (int)ceil($realSellerInventoryAdjustQty / $childProduct['qty']);
                break;
            }
        }

        $msgLockQty = [];
        $totalLockQty = $inStockQty - $availableQty;
        if ($marginLockQty > 0 && $totalLockQty > 0) {
            if ($totalLockQty > $marginLockQty) {
                $msgLockQty[] = "{$marginLockQty}[Margin agreement locked inventory]";
            } else {
                $msgLockQty[] = "{$totalLockQty}[Margin agreement locked inventory]";
                $totalLockQty = 0;
            }
        }
        if ($futuresLockQty > 0 && $totalLockQty > 0) {
            if ($totalLockQty > $futuresLockQty) {
                $msgLockQty[] = "{$futuresLockQty}[Future goods agreement locked inventory]";
            } else {
                $msgLockQty[] = "{$totalLockQty}[Future goods agreement locked inventory]";
                $totalLockQty = 0;
            }
        }
        if ($salesOrderLockQty > 0 && $totalLockQty > 0) {
            if ($totalLockQty > $salesOrderLockQty) {
                $msgLockQty[] = "{$salesOrderLockQty}[Sales Order locked inventory]";
            } else {
                $msgLockQty[] = "{$totalLockQty}[Sales Order locked inventory]";
                $totalLockQty = 0;
            }
        }
        if ($sellerInventoryAdjustLockQty > 0 && $totalLockQty > 0) {
            if ($totalLockQty > $sellerInventoryAdjustLockQty) {
                $msgLockQty[] = "{$sellerInventoryAdjustLockQty}[Inventory Adjustment locked inventory]";
            } else {
                $msgLockQty[] = "{$totalLockQty}[Inventory Adjustment locked inventory]";
            }
        }

        return [
            'totalLockQty' => $inStockQty - $availableQty,
            'msgLockQty' => $msgLockQty
        ];
    }
}
