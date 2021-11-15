<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Enums\Product\ProductStatus;
use App\Components\Storage\StorageCloud;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use App\Helper\CountryHelper;
use App\Repositories\Seller\SellerRepository;
use Carbon\Carbon;

/**
 * Class ControllerAccountCustomerpartnerProductManage
 * @property ModelCatalogMarginProductLock $model_catalog_margin_product_lock
 * @property ModelCatalogFuturesProductLock $model_catalog_futures_product_lock
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCatalogSalesOrderProductLock $model_catalog_sales_order_product_lock
 * @property ModelCustomerpartnerProductManage $model_customerpartner_product_manage
 * @property ModelCommonProduct $model_common_product
 * @property ModelToolUpload $model_tool_upload
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountCustomerpartnerProductManage extends AuthSellerController
{
    const PRODUCT_TYPE_GENERAL = 1;
    const PRODUCT_TYPE_COMBO = 2;
    const PRODUCT_TYPE_LTL = 4;
    const PRODUCT_TYPE_PART = 8;

    private $error = array();
    private $data = array();

    /**
     * 关于金钱的精度
     *
     * @var int $precision
     */
    private $precision;

    public function __construct($registry)
    {
        parent::__construct($registry);
        if ($this->customer->getCountryId() == AMERICAN_COUNTRY_ID) {
            return $this->redirect(['account/account'])->send();
        }
        $this->precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
    }

    public function index()
    {
        // 加载Language
        $this->language->load('account/account');
        $this->load->language('account/customerpartner/product_manage');
        // 设置文档标题
        $this->document->setTitle($this->language->get('heading_title_product_manage'));
        // 加载Model层
        $this->load->model('customerpartner/product_manage');
        $this->load->model('catalog/margin_product_lock');
        $this->load->model('common/product');
        // 获取查询结果
        $this->getList();
    }

    private function getList()
    {
        // 检查账户状态
        $this->checkAccount();
        // 加载面包屑导航
        $this->setHeadLink();
        // 加载页面显示架构
        $this->loadFramework();
        $country = session('country', 'USA');
        // 查询数据
        if (isset($this->request->get['filter_sku_mpn'])) {
            $filter_sku_mpn = trim($this->request->get['filter_sku_mpn']);
        } else {
            $filter_sku_mpn = null;
        }
        if (isset($this->request->get['filter_sku'])) {
            $filter_sku = trim($this->request->get['filter_sku']);
        } else {
            $filter_sku = null;
        }
        if (isset($this->request->get['filter_mpn'])) {
            $filter_mpn = trim($this->request->get['filter_mpn']);
        } else {
            $filter_mpn = null;
        }
        if (isset($this->request->get['filter_effectTimeFrom'])) {
            $filter_effectTimeFrom = $this->request->get['filter_effectTimeFrom'];
        } else {
            $filter_effectTimeFrom = null;
        }
        if (isset($this->request->get['filter_effectTimeTo'])) {
            $filter_effectTimeTo = $this->request->get['filter_effectTimeTo'];
        } else {
            $filter_effectTimeTo = null;
        }
        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = null;
        }
        if (isset($this->request->get['filter_buyer_flag'])) {
            $filter_buyer_flag = $this->request->get['filter_buyer_flag'];
        } else {
            $filter_buyer_flag = null;
        }
        if (isset($this->request->get['filter_available_qty'])) {
            $filter_available_qty = $this->request->get['filter_available_qty'];
        } else {
            $filter_available_qty = null;
        }

        if (isset($this->request->get['filter_product_type'])) {
            $filter_product_type = $this->request->get['filter_product_type'] ?? [0];
            if (is_string($filter_product_type) || is_int($filter_product_type)) {
                $filter_product_type = [$filter_product_type];
            }
            $filter_product_type = array_sum($filter_product_type);
        } else {
            $filter_product_type = 1 + 2 + 4 + 8;
        }

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'pd.name';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $limit = get_value_or_default(
            $this->request->request,
            'page_limit',
            $this->config->get('theme_yzcTheme_product_limit')
        );
        $param = array(
            'filter_sku' => trim($filter_sku),
            'filter_mpn' => trim($filter_mpn),
            'filter_effectTimeFrom' => $filter_effectTimeFrom,
            'filter_effectTimeTo' => $filter_effectTimeTo,
            'filter_status' => $filter_status,
            'filter_buyer_flag' => $filter_buyer_flag,
            'filter_sku_mpn' => trim($filter_sku_mpn),
            'filter_available_qty' => $filter_available_qty,
            'filter_product_type' => $filter_product_type,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit
        );
        $customer_id = (int)$this->customer->getId();
        $product_total = $this->model_customerpartner_product_manage->querySellerProductNum($param, $customer_id);

        $results = $this->model_customerpartner_product_manage->querySellerProducts($param, $customer_id);

        //N-457********
        $product_id_list = array_column($results, 'product_id');
        $sell_count = $this->model_customerpartner_product_manage->get_sell_count($product_id_list);
        foreach ($results as $k => $v) {
            $results[$k]['day_sell'] = isset($sell_count['days'][$k]) ? $sell_count['days'][$k] : 0;
            $results[$k]['all_sell'] = isset($sell_count['all'][$k]) ? $sell_count['all'][$k] : 0;
        }
        //*****************

        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $num = $param['start'] + 1;
        foreach ($results as $key => $result) {
            $productId = (int)$result['product_id'];
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
            $freight = empty($result['freight']) ? 0 : $result['freight'];
            $result['curr_home_pickup_price'] = $result['current_price'] - $freight <= 0 ? 0 : bcsub($result['current_price'], $freight, $this->precision);
            $result['confirmed_home_pickup_price'] = $result['pickup_price'];
            $result['mod_home_pickup_price'] = '';
            if (isset($result['modified_price']) && $result['modified_price']) {
                if ($result['modified_price'] - $freight <= 0) {
                    $result['mod_home_pickup_price'] = 0;
                } else {
                    $result['mod_home_pickup_price'] = bcsub($result['modified_price'], $freight, $this->precision);
                }
            }
            $result['current_price'] = round($result['current_price'], $this->precision);
            !empty($result['modified_price']) && $result['modified_price'] = round($result['modified_price'], $this->precision);

            $result['freight_package_fee'] = $result['freight'] + $result['package_fee'];
            $result['freight'] = is_null($result['freight']) ? 'N/A' : $result['freight'];

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
            // 获取报警价格
            $result['alarm_price'] = $this->model_common_product->getAlarmPrice($productId);
            $results[$key] = $result;
        }

        $this->data['products'] = $results;

        $this->setPanination($product_total, $page, $limit);
        $this->data['filter_sku'] = trim($filter_sku);
        $this->data['filter_mpn'] = trim($filter_mpn);
        $this->data['filter_effectTimeFrom'] = $filter_effectTimeFrom;
        $this->data['filter_effectTimeTo'] = $filter_effectTimeTo;
        $this->data['filter_status'] = $filter_status;
        $this->data['filter_sku_mpn'] = trim($filter_sku_mpn);
        $this->data['filter_product_type'] = $filter_product_type;
        $this->data['filter_buyer_flag'] = $filter_buyer_flag;
        $this->data['filter_available_qty'] = $filter_available_qty;

        $this->data['country_id'] = $this->customer->getCountryId();

        $this->data['sort'] = $sort;
        $this->data['order'] = $order;
        $this->data['back'] = $this->url->link('account/account', '', true);
        $this->data['isMember'] = true;
        $this->data['current'] = $this->url->link('account/customerpartner/product_manage', '', true);
        $this->data['downloadTemplateHref'] = "index.php?route=account/customerpartner/product_manage/downloadTemplateFile";
        $this->data['url_delicacy_management'] = $this->url->link('account/customerpartner/delicacymanagement', '', true);
        $this->data['url_download_freight_template'] = $this->url->link('account/customerpartner/delicacyManagement/downloadTemplate', '', true);
        $this->data['isAmerica'] = $this->customer->getCountryId() == AMERICAN_COUNTRY_ID;
        $this->data['isJapan'] = $this->customer->getCountryId() == JAPAN_COUNTRY_ID;
        $this->data['isEurope'] = in_array($this->customer->getCountryId(),EUROPE_COUNTRY_ID);
        // 商品上下架状态
        $this->data['product_status'] = ProductStatus::getViewItems();
        $this->data['is_non_inner_account'] = $this->customer->isNonInnerAccount() ? 1 : 0;

        // tips 时区跟随当前国别
        if (in_array($country, array_keys(COUNTRY_TIME_ZONES))) {
            $this->data['help_effect_time'] = str_replace('_time_zone_', CountryHelper::getTimezoneByCode($country), $this->language->get('help_effect_time'));
        }
        // 是否显示云送仓提醒
        $this->data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $this->response->setOutput($this->load->view('account/customerpartner/product_manage', $this->data));
    }

    /**
     * 虽然有遍历逻辑，但是只有一条数据，本方法不用于批量更新
     */
    public function update()
    {
        // 加载Model层
        $this->load->model('common/product');
        $this->load->model('customerpartner/product_manage');
        $products = $this->request->post["products"];
        $this->load->language('account/customerpartner/product_manage');
        //非负整数
        $qty_reg = '/^\d+$/';
        if (isset($products)) {
            //虽然有遍历逻辑，但是只有一条数据，本方法不用于批量更新
            foreach ($products as $product) {
                if ($product['quantity_display'] !== "0" && $product['quantity_display'] !== "1") {
                    $json['error'] = $this->language->get('error_qty_display');
                    goto end;
                }
                if (!isset($product['quantity']) || !preg_match($qty_reg, $product['quantity'])) {
                    $json['error'] = $this->language->get('error_qty_positive');
                    goto end;
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
                                $this->model_common_product->getProductComputeLockQty($product_id)
                            );
                        } else {
                            $error_arr[] = $this->language->get('error_stock_warning');
                        }
                    }
                    $json['error'] = join('<br>', $error_arr);
                    goto end;
                }
            }
        }
        if (!isset($json['error'])) {
            $this->load->model('account/customerpartner');
            $this->model_customerpartner_product_manage->updateSellerProduct($products);

            $json['success'] = true;
        }

        end:
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    const MPN = 'MPN';
    const ITEM_CODE = 'Item Code';
    const AVAILABLE_QUANTITY = 'Available quantity';
    const DISPLAY_QUANTITY = 'Display quantity';

    /**
     * 处理修改产品信息文件  #6446 修改后只能修改产品库存
     * @param string $file_path
     * @param int $customer_id
     * @param false $skip_check_price
     * @return array
     * @throws Exception
     */
    private function updateByCsvFile($file_path, $customer_id ,$skip_check_price = false)
    {
        //2019-01-31 23  年-月-日 小时
        $this->load->language('account/customerpartner/product_manage');
        $this->load->model('customerpartner/product_manage');

        $json = array();
        $offset = 1;
        $realFilePath = StorageCloud::priceCsv()->getLocalTempPath(StorageCloud::priceCsv()->getRelativePath($file_path));
        $csvDatas = $this->csv_get_lines($realFilePath, $offset);
        StorageCloud::priceCsv()->deleteLocalTempFile($realFilePath);
        $csvHeader = [
            static::MPN,                    // Seller MPN
            static::ITEM_CODE,              // SKU
            static::AVAILABLE_QUANTITY,       //商品数量
            static::DISPLAY_QUANTITY,        //是否不建立联系就可见数量
        ];
        if (isset($csvDatas['keys']) && $csvDatas['keys'] == $csvHeader) {
            // CSV读取到的订单数据
            $csvDataValues = $csvDatas['values'];
            // 包装数据
            $productArr = array();
            $product_id_line = array();
            if (isset($csvDataValues) && count($csvDataValues) > 0) {
                $lineCount = $offset + 1;
                $unique_record = array();
                //已存在的订单OrderId
                foreach ($csvDataValues as $csvData) {
                    $lineCount = $lineCount + 1;

                    if (
                        (!isset($csvData[static::MPN]) || $csvData[static::MPN] == '')
                        && (!isset($csvData[static::ITEM_CODE]) || $csvData[static::ITEM_CODE] == '')
                    ) {
                        //mpn和sku都为空，无法确认数据
                        $json['error'] = $this->language->get('error_product_code_miss') . " Number of error lines: " . $lineCount . ".";
                        goto end;
                    }

                    if (isset($csvData[static::AVAILABLE_QUANTITY]) && $csvData[static::AVAILABLE_QUANTITY] != '') {
                        if (!is_numeric($csvData[static::AVAILABLE_QUANTITY])) {
                            $json['error'] = $this->language->get('error_qty_nan');
                            goto end;
                        } elseif ($csvData[static::AVAILABLE_QUANTITY] < 0) {
                            $json['error'] = $this->language->get('error_qty_negative');
                            goto end;
                        }

                    }
                    $csvData[static::DISPLAY_QUANTITY] = $this->checkYesOrNo($csvData[static::DISPLAY_QUANTITY]);
                    if (null === $csvData[static::DISPLAY_QUANTITY]) {
                        $json['error'] = $this->language->get('error_qty_display');
                        goto end;
                    }


                    $p_info = $this->model_customerpartner_product_manage
                        ->getProductIdByMpnOrSku($customer_id, $csvData[static::MPN], $csvData[static::ITEM_CODE]);
                    $product_id = $p_info ? $p_info[0] : null;  // 商品id
                    $product_type = $p_info ? $p_info[1] : null; // 商品类型id 用于区分是否为保证金产品
                    if ($product_type !== null && $product_type != 0 && $product_type != 3) {
                        $json['error'] = 'Deposit product can not be edited.Line:' . $lineCount;
                        goto end;
                    }
                    if ($product_id) {
                        $productArr[] = array(
                            "product_id" => $product_id,
                            'item_code' => $p_info ? $p_info[2] : null,
                            "quantity" => $csvData[static::AVAILABLE_QUANTITY] == '' ? null : $csvData[static::AVAILABLE_QUANTITY],
                            "quantity_display" => $csvData[static::DISPLAY_QUANTITY] == '' ? null : $csvData[static::DISPLAY_QUANTITY],
                        );
                        $product_id_line[$product_id] = $csvData[static::AVAILABLE_QUANTITY] == ''
                            ? null
                            : $csvData[static::AVAILABLE_QUANTITY];

                        if (isset($unique_record[$product_id])) {
                            $json['error'] = $this->language->get('error_product_duplicate') . $unique_record[$product_id] . ' and ' . $lineCount . '.';
                            goto end;
                        }
                        $unique_record[$product_id] = $lineCount;
                    } else {
                        $errorLine[] = $lineCount;
                    }
                }
                if (isset($errorLine)) {
                    $json['fail_ids'] = $this->language->get('error_no_product') . implode(",", $errorLine);
                    goto end;
                }
                //校验上架数不能大于库存数
                $invalid_product = $this->model_customerpartner_product_manage->validateStock($product_id_line);
                if (!empty($invalid_product)) {
                    $error_arr = [];
                    foreach ($invalid_product as $product_id) {
                        $combo = $this->model_common_product->getComboProduct($product_id);
                        // combo品和普通商品的提示信息不一样
                        if (!empty($combo)) {
                            $error_arr[] = dprintf(
                                    $this->language->get('error_stock_warning_lock'),
                                    $this->model_common_product->getProductComputeLockQty($product_id)
                                ) . 'Line:' . $unique_record[$product_id];;
                        } else {
                            $error_arr[] = $this->language->get('error_stock_warning');
                        }
                    }
                    $json['fail_ids'] = join('<br>', $error_arr);
                    goto end;
                }

                //执行更新
                try {
                    $this->model_customerpartner_product_manage->updateSellerProduct($productArr);
                } catch (Exception $e) {
                    $this->log->write("batch updateSellerProduct" . $e->getMessage());
                }
            }
        } else {
            // 上传的CSV内容格式与模板格式不符合！
            $json['error'] = $this->language->get('error_file_content');
        }

        end:
        return $json;
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
        $pagination->url = $this->url->link('account/customerpartner/product_manage', '' . $this->getUrlParam() . '&page={page}', true);

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
            'href' => $this->url->link('account/customerpartner/product_manage', '', true),
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

    public function uploadPriceCsv()
    {
        $this->load->language('account/customerpartner/product_manage');
        $this->load->model('customerpartner/product_manage');
        $this->load->model('common/product');
        $json = [];
        $json_detail = [];
        // 获取当前登录用户
        if ($this->customer->isLogged()) {
            // 检查文件名以及文件类型
            $validator = $this->request->validate([
                // 验证参数必须存在，并且是文件，最小10kb，最大1024kb，文件后缀名碧玺是xls、csv、xlsx
                'file' => 'required|file|extension:csv',
            ]);

            if ($validator->fails()) {
                $json['error'] = $validator->errors()->first();
                return  $this->response->json($json);
            }

            // 获取登录用户信息
            $customer = $this->customer;
            $customer_id = $customer->getId();
            // 上传订单文件，以用户ID进行分类
            if (!isset($json['error'])) {
                $fileInfo = $this->request->file('file');
                $run_id = time();
                $fileType = $fileInfo->getClientOriginalExtension();
                $realFileName = $run_id . '.' . $fileType;
                $filePath = StorageCloud::priceCsv()->writeFile($fileInfo, $customer_id ,$realFileName);
                // 记录上传文件数据
                $fileData = [
                    "file_name" => $fileInfo->getClientOriginalName(),
                    "size" => $fileInfo->getSize(),
                    "file_path" => $filePath,
                    "customer_id" => $customer_id,
                    "run_id" => $run_id,
                    "create_user_name" => $customer_id,
                    "create_time" => Carbon::now()
                ];
                $this->load->model('tool/upload');
                $this->model_tool_upload->saveUploadFileRecord($fileData);
                $json_detail = $this->updateByCsvFile($filePath, $customer_id);
            }
        }
        if (isset($json_detail) && !empty($json_detail)) {
            $this->response->json($json_detail);
        } else {

            $this->response->json($json);
        }
    }

    public function uploadPriceCsvSkipCheck()
    {
        $file_path = $this->request->request['file'] ?? [];
        $json_detail = $this->updateByCsvFile($file_path, (int)$this->customer->getId(), true);
        if (!empty($json_detail)) {
            $this->response->returnJson($json_detail);
        }
        $this->response->returnJson(['success' => 1]);
    }

    public function csv_get_lines($csvFile, $offset = 0)
    {
        if (!$fp = fopen($csvFile, 'r')) {
            return false;
        }
        $i = $j = 0;
        $line = null;
        while (false !== ($line = fgets($fp))) {
            if ($i++ < $offset) {
                continue;
            }
            break;
        }
        $data = array();
        while (!feof($fp)) {
            $data[] = fgetcsv($fp);
        }
        fclose($fp);
        $values = array();
        $line = preg_split("/,/", $line);
        $keys = array();
        $flag = true;
        foreach ($data as $d) {
            $entity = array();
            if (empty($d)) {
                continue;
            }
            for ($i = 0; $i < count($line); $i++) {
                if ($i < count($d)) {
                    $entity[trim($line[$i])] = $d[$i];
                    if ($flag) {
                        $keys[] = trim($line[$i]);
                    }
                }
            }
            if ($flag) {
                $flag = false;
            }
            $values[] = $entity;
        }
        $result = array(
            "keys" => $keys,
            "values" => $values
        );
        return $result;
    }

    /**
     * 下载订单模板文件
     */
    public function downloadTemplateFile()
    {
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/product_manage/downloadTemplateFile', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $file = DIR_DOWNLOAD . "AvailableQuantityTemplate.csv";
        if (!headers_sent()) {
            if (file_exists($file)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));

                if (ob_get_level()) {
                    ob_end_clean();
                }

                readfile($file, 'rb');

                exit();
            } else {
                exit('Error: Could not find file ' . $file . '!');
            }
        } else {
            exit('Error: Headers already sent out!');
        }
    }

    public function checkProductQuantity()
    {
        $this->load->model('customerpartner/product_manage');
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/product_manage/downloadProductInformation', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }

        //获取查询条件
        // 查询数据
        if (isset($this->request->get['filter_sku'])) {
            $filter_sku = $this->request->get['filter_sku'];
        } else {
            $filter_sku = null;
        }
        if (isset($this->request->get['filter_mpn'])) {
            $filter_mpn = $this->request->get['filter_mpn'];
        } else {
            $filter_mpn = null;
        }
        if (isset($this->request->get['filter_effectTimeFrom'])) {
            $filter_effectTimeFrom = $this->request->get['filter_effectTimeFrom'];
        } else {
            $filter_effectTimeFrom = null;
        }
        if (isset($this->request->get['filter_effectTimeTo'])) {
            $filter_effectTimeTo = $this->request->get['filter_effectTimeTo'];
        } else {
            $filter_effectTimeTo = null;
        }
        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = null;
        }
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'pd.name';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        $param = array(
            'filter_sku' => $filter_sku,
            'filter_mpn' => $filter_mpn,
            'filter_effectTimeFrom' => $filter_effectTimeFrom,
            'filter_effectTimeTo' => $filter_effectTimeTo,
            'filter_status' => $filter_status,
            'sort' => $sort,
            'order' => $order
        );
        $customer_id = (int)$this->customer->getId();

        $results = $this->model_customerpartner_product_manage->getCommodityStatistics($param, $customer_id);

        if (isset($results) && !empty($results)) {
            $json['success'] = sizeof($results);
        } else {
            $json['success'] = 0;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function downloadProductInformation()
    {
        $this->load->language('account/customerpartner/product_manage');
        $this->load->model('customerpartner/product_manage');
        $this->load->model('catalog/margin_product_lock');
        $this->load->model('catalog/futures_product_lock');
        $this->load->model('catalog/sales_order_product_lock');
        $this->load->model('common/product');
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/product_manage/downloadProductInformation', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }

        //获取查询条件
        // 查询数据
        if (isset($this->request->get['filter_sku'])) {
            $filter_sku = $this->request->get['filter_sku'];
        } else {
            $filter_sku = null;
        }
        if (isset($this->request->get['filter_mpn'])) {
            $filter_mpn = $this->request->get['filter_mpn'];
        } else {
            $filter_mpn = null;
        }
        if (isset($this->request->get['filter_effectTimeFrom'])) {
            $filter_effectTimeFrom = $this->request->get['filter_effectTimeFrom'];
        } else {
            $filter_effectTimeFrom = null;
        }
        if (isset($this->request->get['filter_effectTimeTo'])) {
            $filter_effectTimeTo = $this->request->get['filter_effectTimeTo'];
        } else {
            $filter_effectTimeTo = null;
        }
        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = null;
        }
        if (isset($this->request->get['filter_sku_mpn'])) {
            $filter_sku_mpn = $this->request->get['filter_sku_mpn'];
        } else {
            $filter_sku_mpn = null;
        }
        if (isset($this->request->get['filter_buyer_flag'])) {
            $filter_buyer_flag = $this->request->get['filter_buyer_flag'];
        } else {
            $filter_buyer_flag = null;
        }
        if (isset($this->request->get['filter_available_qty'])) {
            $filter_available_qty = $this->request->get['filter_available_qty'];
        } else {
            $filter_available_qty = null;
        }
        if (isset($this->request->get['filter_product_type'])) {
            $filter_product_type = $this->request->get['filter_product_type'] ?? [0];
            if (is_string($filter_product_type) || is_int($filter_product_type)) {
                $filter_product_type = [$filter_product_type];
            }
            $filter_product_type = array_sum($filter_product_type);
        } else {
            $filter_product_type = 1 + 2 + 4 + 8;
        }
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'pd.name';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        $param = array(
            'filter_sku' => trim($filter_sku),
            'filter_mpn' => trim($filter_mpn),
            'filter_effectTimeFrom' => $filter_effectTimeFrom,
            'filter_effectTimeTo' => $filter_effectTimeTo,
            'filter_status' => $filter_status,
            'filter_sku_mpn' => trim($filter_sku_mpn),
            'filter_buyer_flag' => $filter_buyer_flag,
            'filter_available_qty' => $filter_available_qty,
            'filter_product_type' => $filter_product_type,
            'sort' => $sort,
            'order' => $order
        );
        $customer_id = (int)$this->customer->getId();
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'YmdHis');
        //12591 end
        $fileName = "Product List" . $time . ".csv";
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo chr(239) . chr(187) . chr(191);

        $fp = fopen('php://output', 'a');
        $head = array(
            'MPN',
            'Item Code',
            'Product Name',
            'Product Type',
            'Sold Separately',
            'MPN in Details',
            '30-Day Sales Volume',
            'Total Sales Volume',
            'In stock Quantity',
            'Lock Quantity',
            'Lock Reason',
            'Available Quantity',
            'Promotional Quantity',
            'Current Price',
            'Check Product Information are Complete',
            'Check Material Package are Complete',
            'Status'
        );
        foreach ($head as $i => $v) {
            // CSV的Excel支持GBK编码，一定要转换，否则乱码
            $head [$i] = iconv('utf-8', 'gbk', $v);
        }
        fputcsv($fp, $head);

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
            foreach ($results as $productId => $detail) {
                // productType 解析
                /** @see ModelCustomerpartnerProductManage::getProductType() */
                $product_type = $detail['product_type'];
                $temp_type_name = [];
                array_map(function ($k, $v) use ($product_type, &$temp_type_name) {
                    ($k & $product_type) && $temp_type_name[] = $v;
                }, array_keys($productTypeMap), $productTypeMap);
                // end productType
                // start计算锁定库存
                $lock_qty = [];
                // 现货锁定库存
                $margin_lock_qty = $this->model_catalog_margin_product_lock->getProductMarginComputeQty($productId);
                $margin_lock_qty > 0 && $lock_qty[] = "{$margin_lock_qty}[Margin agreement locked inventory]";
                // 期货锁定库存
                $futures_lock_qty = $this->model_catalog_futures_product_lock->getProductFuturesComputeQty($productId);
                $futures_lock_qty > 0 && $lock_qty[] = "{$futures_lock_qty}[Future goods agreement locked inventory]";
                // 纯物流锁定库存
                $sales_order_lock_qty = $this->model_catalog_sales_order_product_lock->getProductSalesOrderComputeQty($productId);
                $sales_order_lock_qty > 0 && $lock_qty[] = "{$sales_order_lock_qty}[Sales Order locked inventory]";
                // 获取限时限量折扣
                $detail['time_limit_qty'] = 0;
                $timeLimitDiscount = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountInfo($productId);
                if ($timeLimitDiscount) {
                    $detail['time_limit_qty'] = intval($timeLimitDiscount->qty) + intval($timeLimitDiscount->other_time_limit_qty);
                }
                // end
                $content = array(
                    $detail['mpn'],
                    $detail['item_code'],
                    $detail['product_name'],
                    join(',', $temp_type_name),
                    $detail['buyer_flag'] ? 'Yes' : 'No',
                    $detail['mpn_in_detail'],
                    $detail['day_sell'],
                    $detail['all_sell'],
                    $detail['instock_qty'],
                    $this->model_common_product->getProductComputeLockQty($productId),
                    join(PHP_EOL, $lock_qty),
                    $detail['on_shelf_qty'] > 0 ? $detail['on_shelf_qty'] : '0',  //Available Quantity
                    $detail['time_limit_qty'],                                    //Promotional Quantity
                    round($detail['current_price'], $this->precision),
                    $detail['is_info_complete'],
                    $detail['is_Material_complete'],
                    $detail['status'],
                );
                fputcsv($fp, $content);
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
        $output = stream_get_contents($fp);
        fclose($fp);
        return $output;
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
}
