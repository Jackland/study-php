<?php

namespace Catalog\model\customerpartner;

use App\Components\RemoteApi\B2BManager\DTO\Freight\FreightDTO;
use App\Components\Storage\StorageCloud;
use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Country\Country;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\ModifyLog\CommonOrderActionStatus;
use App\Enums\ModifyLog\CommonOrderProcessCode;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\JapanSalesOrder;
use App\Helper\AddressHelper;
use App\Helper\GigaOnsiteHelper;
use App\Helper\CountryHelper;
use App\Helper\StringHelper;
use App\Logging\Logger;
use App\Models\Freight\InternationalOrderConfig;
use App\Models\Product\ProductLock;
use App\Models\Product\ProductToTag;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Repositories\Product\ProductRepository;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Services\SalesOrder\SalesOrderService;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use ModelExtensionModuleEuropeFreight;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class SalesOrderManagement
 *
 * @property \ModelToolImage $model_tool_image
 * @property \ModelCatalogProduct $model_catalog_product
 * @property \ModelAccountCustomerOrderImport $model_account_customer_order_import
 * @property \ModelAccountCustomerOrder $model_account_customer_order
 * @property \ModelCommonProduct $model_common_product
 * @property \ModelCatalogSalesOrderProductLock $model_catalog_sales_order_product_lock
 * @property \ModelExtensionModuleEuropeFreight  $model_extension_module_europe_freight;
 * @method cancelOrder($order_id, $country_id)
 */
class SalesOrderManagement extends \Model
{
    use RequestCachedDataTrait;
    const ORDER_NEW_ORDER = 1;
    const ORDER_BEING_PROCESSED = 2;
    const ORDER_ON_HOLD = 4;
    const ORDER_PART_OUT = 8;
    const ORDER_CANCELED = 16;
    const ORDER_COMPLETED = 32;
    const ORDER_LTL_CHECK = 64;
    const ORDER_ASR_TO_BE_PAID = 128;
    const ORDER_CHECK_LABEL = 129;
    const ORDER_ABNORMAL_ORDER = CustomerSalesOrderStatus::ABNORMAL_ORDER;
    const OMD_ORDER_CANCEL_UUID = 'c1996d6f-30df-46dc-b8ba-68a28efb83d7';

    /**
     * [getUploadHistory description]
     * @param int $customer_id
     * @return array
     */
    public function getUploadHistory($customer_id): array
    {
        return DB::table(DB_PREFIX . 'customer_order_file')
            ->where([
                'customer_id' => $customer_id,
            ])
            ->limit(5)
            ->orderBy('create_time', 'desc')
            ->get()
            ->map(function ($v) {
                if (StorageCloud::orderCsv()->fileExists($v->file_path)) {
                    // 需要处理数据库里存储的path
                    $v->file_path = StorageCloud::orderCsv()->getUrl($v->file_path);
                }
                return (array)$v;
            })
            ->toArray();
    }

    /**
     * [getTrackingPrivilege description]
     * @param int $customer_id
     * @param bool $is_partner
     * @return bool
     */
    public function getTrackingPrivilege($customer_id, $is_partner): bool
    {
        if ($is_partner) {
            $account = $this->config->get('php_account_management');
            // 与account 建立联系的seller_id 才是 美国纯物流Seller名单
            return DB::table(DB_PREFIX . 'buyer_to_seller as bts')
                ->leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id', '=', 'bts.buyer_id')
                ->where([
                    'bts.seller_id' => $customer_id,
                    'bts.buy_status' => 1,
                    'bts.seller_control_status' => 1,
                    'bts.buyer_control_status' => 1,
                ])
                ->whereIn('c.email', $account)
                ->exists();
        }
        return false;

    }

    private function getSuccessfullyUploadHistoryBuilder(array $param, int $customer_id): Builder
    {
        $country = session('country', 'USA');
        $usaZone = CountryHelper::getTimezoneByCode('USA');
        $countryZone = CountryHelper::getTimezoneByCode($country);
        $map = [
            ['customer_id', '=', $customer_id],
            //['handle_status' ,'=',1] ,
        ];
        if (isset($param['filter_orderDate_from'])) {
            $timeList[] = Carbon::parse($param['filter_orderDate_from'] . ' 00:00:00', $countryZone)->setTimezone($usaZone);
        } else {
            //这里可以写为最初成功时间
            $timeList[] = date('Y-m-d 00:00:00', 0);
        }
        if (isset($param['filter_orderDate_to'])) {
            $timeList[] = Carbon::parse($param['filter_orderDate_to'] . ' 23:59:59', $countryZone)->setTimezone($usaZone);
        } else {
            $timeList[] = date('Y-m-d', time()) . ' 23:59:59';
        }

        return DB::table(DB_PREFIX . 'customer_order_file')
            ->where($map)
            ->whereBetween('create_time', $timeList);
    }

    /**
     * [getSuccessfullyUploadHistoryTotal description]
     * @param $param
     * @param int $customer_id
     * @return int
     */
    public function getSuccessfullyUploadHistoryTotal($param, $customer_id): int
    {
        $builder = $this->getSuccessfullyUploadHistoryBuilder(...func_get_args());
        return $builder->count();
    }

    /**
     * [getSuccessfullyUploadHistory description]
     * @param $param
     * @param int $customer_id
     * @param $page
     * @param int $perPage
     * @return array
     */
    public function getSuccessfullyUploadHistory($param, $customer_id, $page, $perPage = 2): array
    {
        $builder = $this->getSuccessfullyUploadHistoryBuilder($param, $customer_id);
        return $builder->forPage($page, $perPage)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($v) {
                if (StorageCloud::orderCsv()->fileExists($v->file_path)) {
                    // 需要处理数据库里存储的path
                    $v->file_path = StorageCloud::orderCsv()->getUrl($v->file_path);
                }
                return (array)$v;
            })
            ->toArray();
    }

    /**
     * [verifyUploadFile description]
     * @param $files
     * @param $upload_type
     * @return string
     */
    public function verifyUploadFile($files, $upload_type): string
    {
        // 检查文件名以及文件类型
        $json['error'] = '';
        if (isset($files['name'])) {
            $fileName = $files['name'];
            $fileType = strrchr($fileName, '.');
            if (!in_array($fileType, ['.xls', '.xlsx']) || $upload_type != 0) {
                $json['error'] = 'error_filetype';
            }
            if ($files['error'] != UPLOAD_ERR_OK) {
                $json['error'] = 'error_upload_' . $files['error'];
            }
        } else {
            $json['error'] = 'error_upload';
        }
        // 检查文件短期之内是否重复上传(首次提交文件后5s之内不能提交文件)
        $files = glob(DIR_UPLOAD . '*.tmp');
        foreach ($files as $file) {
            if (is_file($file) && (filectime($file) < (time() - 5))) {
                unlink($file);
            }
        }

        return $json['error'];
    }

    /**
     * [saveUploadFile description]
     * @param UploadedFile $fileInfo
     * @param int $customer_id
     * @param $upload_type
     * @return array
     */
    public function saveUploadFile(UploadedFile $fileInfo, $customer_id, $upload_type): array
    {
        // 获取登录用户信息
        $import_mode = $upload_type;
        // 上传订单文件，以用户ID进行分类
        $fileName = $fileInfo->getClientOriginalName();
        $fileType = $fileInfo->getClientOriginalExtension();
        // 复制上传的文件到orderCSV路径下
        $run_id = msectime();
        $dateTime = date("Y-m-d_His");
        $realFileName = str_replace('.' . $fileType, '_', $fileName) . $dateTime . '.' . $fileType;
        StorageCloud::orderCsv()->writeFile($fileInfo, $customer_id, $realFileName);
        // 记录上传文件数据
        $file_data = [
            "file_name" => $fileInfo->getClientOriginalName(),
            "size" => $fileInfo->getSize(),
            "file_path" => $customer_id . "/" . $realFileName,
            "customer_id" => $customer_id,
            "import_mode" => $import_mode,
            "run_id" => $run_id,
            "create_user_name" => $customer_id,
            "create_time" => Carbon::now()
        ];
        $file_id = DB::table(DB_PREFIX . 'customer_order_file')->insertGetId($file_data);
        return [
            'run_id' => $run_id,
            'import_mode' => $import_mode,
            'file_id' => $file_id,
        ];
    }

    /**
     * [getUploadFileInfo description]
     * @param $get
     * @return mixed
     */
    public function getUploadFileInfo($get)
    {
        $ret = DB::table(DB_PREFIX . 'customer_order_file')
            ->where('id', $get['file_id'])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return current($ret);
    }

    /**
     * [dealWithFileData description]
     * @param $data
     * @param array $get
     * @param int $country_id
     * @param int $customer_id
     * @return mixed|string
     */
    public function dealWithFileData($data, $get, $country_id, $customer_id)
    {
        $run_id = $get['run_id'];
        $import_mode = $get['import_mode'];
        //验证首行数据是否正确
        $verify_ret = $this->verifyFileDataFirstLine($data, $import_mode, $country_id);
        if ($verify_ret) {
            return $verify_ret;
        }
        //验证订单是否含有重复的order_id 和 sku 组合
        $unique_ret = $this->verifyFileDataOrderUnique($data, $import_mode);
        if ($unique_ret['error']) {
            return $unique_ret['error'];
        }
        $column_ret = $this->addSalesOrderInfo($unique_ret['data'], $import_mode, $run_id, $country_id, $customer_id);
        if ($column_ret !== true) {
            return $column_ret;
        }
        return true;
    }

    public function getApiBuyerInfo(int $customer_id): array
    {
        return DB::table(DB_PREFIX . 'customer as c')
            ->leftJoin(DB_PREFIX . 'customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'c.customer_id')
            ->where('ctc.customer_id', $customer_id)
            ->selectRaw('c.*')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
    }

    public function updateUploadInfoStatus($run_id, $customer_id, $update_info)
    {
        $map = [
            ['run_id', '=', $run_id],
            ['customer_id', '=', $customer_id],
        ];
        DB::table(DB_PREFIX . 'customer_order_file')->where($map)->update($update_info);

    }

    public function updateOriginalSalesOrderStatus($run_id, $customer_id)
    {
        $list = DB::table('tb_sys_customer_sales_order')
            ->where([
                'run_id' => $run_id,
                'buyer_id' => $customer_id,
            ])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        foreach ($list as $key => $value) {
            $this->releaseOrder($value['id'], CustomerSalesOrderStatus::ABNORMAL_ORDER, 1);
        }
    }

    public function getOriginalSalesOrder($run_id, $customer_id, $order_status = [CustomerSalesOrderStatus::LTL_CHECK, CustomerSalesOrderStatus::ABNORMAL_ORDER, CustomerSalesOrderStatus::BEING_PROCESSED, CustomerSalesOrderStatus::ON_HOLD, CustomerSalesOrderStatus::CANCELED])
    {
        $map = [
            'run_id' => $run_id,
            'buyer_id' => $customer_id,

        ];
        return CustomerSalesOrder::query()
            ->where($map)
            ->whereIn('order_status', $order_status)
            ->get()
            ->toArray();
    }

    public function getUndoOrder(array $list): array
    {
        $skuList = [];
        $stockList = [];
        $ltlList = [];
        $onHoldList = [];
        $allList = [];
        $internationalList = [];
        $internationalRuleInvalid = [];
        load()->model('common/product');
        load()->model('extension/module/europe_freight');
        $isEurope = Customer()->isEurope();
        foreach ($list as $key => $value) {

            // 是否可以满足发货 不满足的情况标注abnormal
            $salesService = app(SalesOrderService::class);
            // 该国别允许国际单但是校验的国际单非法
            if ($isEurope
                && $value['is_international']
                && !$salesService->checkSalesOrderShipValid(
                    Customer()->getCountryId(),
                    $value['ship_country'],
                    $value['ship_zip_code']
                )
            ) {
                $internationalList[] = [
                    'ship_country' => $value['ship_country'],
                    'ship_zip_code' => $value['ship_zip_code'],
                    'order_id' => $value['order_id'],
                ];
            }
            $tmp = CustomerSalesOrderLine::query()
                ->where('header_id', $value['id'])
                ->get()
                ->toArray();
            foreach ($tmp as $ks => $vs) {
                $match['sku'] = $vs['item_code'];
                $match['order_id'] = $value['order_id'];
                $match['qty'] = $vs['qty'];
                if (!$vs['product_id']) {
                    $skuList[] = $match;
                } else {
                    //验证是否是库存问题
                    // 检验是否是ltl
                    if ($value['order_status'] == CustomerSalesOrderStatus::ON_HOLD) {
                        // 目前导入订单变成on hold的只有因为资产风控，所以不做判断，如果有了其他可能需要另外加判断
                        $onHoldList[] = $match;

                    } else {
                        $exists = ProductToTag::query()
                            ->where([
                                'tag_id' => 1,
                                'product_id' => $vs['product_id'],
                            ])
                            ->exists();
                        if ($value['order_status'] == CustomerSalesOrderStatus::LTL_CHECK) {
                            if ($exists) {
                                $ltlList[] = $match;
                            } else {
                                $allList[] = $match;
                            }
                        }

                        if ($value['order_status'] == CustomerSalesOrderStatus::ABNORMAL_ORDER) {
                            $flag = ($this->customer->isGigaOnsiteSeller() || $this->model_common_product->checkProductQtyIsAvailable($vs['product_id'], $vs['qty']));
                            //校验是否是纯物流尺寸问题
                            $sizeFlag = true;
                            if($isEurope  && $value['is_international']){
                                $isInternationalInfo['from'] = customer()->getCountryId() == Country::BRITAIN ? 'GBR':'DEU';
                                $isInternationalInfo['to'] = $value['ship_country'];
                                $isInternationalInfo['zip_code'] =  $value['ship_zip_code'];
                                $isInternationalInfo['qty'] =  $vs['qty'];
                                $isInternationalInfo['delivery_to_fba'] =  $value['delivery_to_fba'];
                                $isInternationalInfo['product_id'] =  $vs['product_id'];
                                $isInternationalInfo['line_id'] =  $vs['id'];
                                $verify[0] = $isInternationalInfo;
                                $ret = $this->model_extension_module_europe_freight->getFreight($verify,true);
                                if($ret[0]['code'] != ModelExtensionModuleEuropeFreight::CODE_SUCCESS){
                                    $sizeFlag = false;
                                    if($ret[0]['code'] == ModelExtensionModuleEuropeFreight::CODE_PRODUCT_ERROR){
                                        $internationalRuleInvalid[] = [
                                            'sku' => $vs['item_code'],
                                            'order_id' => $value['order_id'],
                                            'qty' => $vs['qty'],
                                            'msg'=>$ret[0]['msg'],
                                        ];
                                    }
                                }
                            }
                            //  状态非超大件 数量满足
                            if ($flag && !$exists && $sizeFlag) {
                                $allList[] = $match;
                            } elseif ($flag && $exists) {
                                $ltlList[] = $match;
                            } else if(!$flag){
                                $stockList[] = $match;
                            }
                        }
                    }
                }
            }
        }
        return [
            'skuList' => $skuList, // sku 不正确
            'stockList' => $stockList, // 库存不足
            'ltlList' => $ltlList, // 超大件处理
            'onHoldList' => $onHoldList, // 风控被on hold
            'allList' => $allList,
            'internationalList' => $internationalList, // 国际单问题
            'internationalRuleInvalid' => $internationalRuleInvalid, // 国际单问题
        ];
    }

    public function getDoOrder($list)
    {
        $stock_list = [];
        foreach ($list as $key => $value) {
            $tmp = CustomerSalesOrderLine::query()
                ->where('header_id', $value['id'])
                ->get()
                ->toArray();
            foreach ($tmp as $ks => $vs) {
                //验证是否是库存问题
                $match['sku'] = $vs['item_code'];
                $match['order_id'] = $value['order_id'];
                $match['qty'] = $vs['qty'];
                $stock_list[] = $match;

            }
        }
        $ret['stock_list'] = $stock_list;
        return $ret;
    }

    public function updateSingleOriginalSalesOrderStatus($id, $pre_status = CustomerSalesOrderStatus::ABNORMAL_ORDER, $type = 1)
    {
        // 返回信息
        $return = [
            'success' => false,// 成功与否
            // 错误码，成功是 200,其他都是错的，用于外层校验状态
            // 现在104是风控限制订单变为BP，如果外面还需要别的状态，自行增加
            'code' => 0,
            'msg' => 'BP Error!'
        ];
        // 检验库存是否足够
        // 检验product_id 是否存在 是否是seller产品
        // 检验是否是LTL
        // 暂时release order 改成CustomerSalesOrderStatus::ABNORMAL_ORDER
        //$status = 2;
        $status = CustomerSalesOrderStatus::BEING_PROCESSED;
        load()->model('common/product');
        load()->model('catalog/sales_order_product_lock');
        load()->model('extension/module/europe_freight');
        $orderInfo = CustomerSalesOrder::query()->find($id);
        $ltl_process_status = $orderInfo->ltl_process_status;
        $list = CustomerSalesOrderLine::query()->alias('l')
            ->leftJoinRelations(['customerSalesOrder as o'])
            ->leftJoin('oc_customerpartner_to_product as cp', 'l.product_id', '=', 'cp.product_id')
            ->where('l.header_id', $id)
            ->selectRaw('l.*,o.buyer_id,cp.customer_id as cp_seller_id')
            ->get()
            ->toArray();
        $ltl_status = 2;
        $lock_status = 0;
        if ($type == 1) {
            // 默认的api调用和上传
            $customer_info = $this->getApiBuyerInfo($list[0]['buyer_id']);
            $isGigaOnsiteSeller = $customer_info[0]['accounting_type'] == 6;
            $isEurope = in_array($customer_info[0]['country_id'], EUROPE_COUNTRY_ID);
        } else {
            $isGigaOnsiteSeller = Customer()->isGigaOnsiteSeller();
            $isEurope = Customer()->isEurope();
        }
        $sellerLines = [];// 其实就是seller本身，这里做成数组是为了今后扩展是否方便些？
        foreach ($list as $key => $value) {
            if ($value['product_id']) {
                //验证是否lock表存在
                //验证库存
                //验证LTL
                //欧洲不验证ltl (需要验证国际单)
                $lock_exists = ProductLock::query()
                    ->where([
                        'type_id' => 5,
                        'agreement_id' => $value['id'],
                    ])
                    ->exists();
                if ($lock_exists) {
                    // 已锁库存
                    $lock_status = 1;
                    //break;
                } else {
                    $flag = ($isGigaOnsiteSeller || $this->model_common_product->checkProductQtyIsAvailable($value['product_id'], $value['qty']));
                    if (!$flag) {
                        $status = CustomerSalesOrderStatus::ABNORMAL_ORDER;
                        $return['msg'] = $this->language->get('text_error_release_inventory');
                        $return['code'] = 101;
                        break;
                    }
                }
                // 非欧洲需要验证超大件和风控
                if (!$isEurope) {
                    $exists = ProductToTag::query()
                        ->where([
                            'tag_id' => 1,
                            'product_id' => $value['product_id'],
                        ])
                        ->exists();
                    if ($exists && !$ltl_process_status) {
                        $ltl_status = CustomerSalesOrderStatus::LTL_CHECK;
                    }

                    $sellerLines[$value['cp_seller_id']][] = [
                        'product_id' => $value['product_id'],
                        'qty' => $value['qty'],
                        'estimate_freight' => $value['estimate_freight']
                    ];
                }else{
                    // 是否可以满足发货 不满足的情况标注abnormal
                    $salesService = app(SalesOrderService::class);
                    $isInternational = $salesService->checkInternationalActive($orderInfo->buyer->country_id);
                    // 该国别不允许国际单但是校验是国际单
                    if (
                        !$isInternational
                        && $salesService->checkIsInternationalSalesOrder($orderInfo->buyer->country_id, $orderInfo->ship_country)
                    ) {
                        $status = CustomerSalesOrderStatus::ABNORMAL_ORDER;
                        $return['msg'] = $this->language->get('text_error_release_international_order');
                        $return['code'] = 201;
                        break;
                    }
                    // 该国别允许国际单但是校验的国际单非法
                    if (
                        $isInternational
                        && $orderInfo->is_international
                        && !$salesService->checkSalesOrderShipValid(
                            $orderInfo->buyer->country_id,
                            $orderInfo->ship_country,
                            $orderInfo->ship_zip_code
                        )
                    ) {
                        $status = CustomerSalesOrderStatus::ABNORMAL_ORDER;
                        $return['msg'] = $this->language->get('text_error_release_international_order');
                        $return['code'] = 201;
                        break;
                    }
                    // 国际单尺寸不符合
                    if($isInternational && $orderInfo->is_international){
                        if(in_array($orderInfo->buyer->country_id,EUROPE_COUNTRY_ID)){
                            $isInternationalInfo['from'] = $orderInfo->buyer->country_id == Country::BRITAIN ? 'GBR':'DEU';
                        }
                        $isInternationalInfo['to'] = $orderInfo->ship_country;
                        $isInternationalInfo['zip_code'] =  $orderInfo->ship_zip_code;
                        $isInternationalInfo['qty'] =  $value['qty'];
                        $isInternationalInfo['delivery_to_fba'] =  $orderInfo->delivery_to_fba;
                        $isInternationalInfo['product_id'] =  $value['product_id'];
                        $isInternationalInfo['line_id'] =  $value['id'];
                        $verify[0] = $isInternationalInfo;
                        $ret = $this->model_extension_module_europe_freight->getFreight($verify,true);
                        if($ret[0]['code'] != 200){
                            $status = CustomerSalesOrderStatus::ABNORMAL_ORDER;
                            $return['msg'] = sprintf($this->language->get('text_error_release_international_size'),$value['item_code']);
                            $return['code'] = $ret[0]['code'];
                            break;
                        }

                    }

                }

            } else {
                $status = CustomerSalesOrderStatus::ABNORMAL_ORDER;
                $return['msg'] =  sprintf($this->language->get('text_error_release_sku'),$value['item_code']);
                $return['code'] = 101;
                break;
            }
        }

        if (!$lock_status) {
            if ($status == CustomerSalesOrderStatus::BEING_PROCESSED
                || ($ltl_status == CustomerSalesOrderStatus::LTL_CHECK && $status != CustomerSalesOrderStatus::ABNORMAL_ORDER)) {
                // 已经锁过库存了
                if ($ltl_status == CustomerSalesOrderStatus::LTL_CHECK && $status != CustomerSalesOrderStatus::ABNORMAL_ORDER) {
                    $status = CustomerSalesOrderStatus::LTL_CHECK;
                }

                try {
                    $this->model_catalog_sales_order_product_lock->TailSalesOrderIn($id);
                } catch (\Exception $e) {
                    Logger::salesOrder($e, 'error');
                    $status = CustomerSalesOrderStatus::ABNORMAL_ORDER;
                    $return['msg'] = $this->language->get('text_error_release_inventory');
                    $return['code'] = 101;

                }
            }
        }

        // 按照订单锁库存，一旦库存不足够的情况发生 直接状态CustomerSalesOrderStatus::ABNORMAL_ORDER abnormal order
        // seller资产风控
        if ($status == CustomerSalesOrderStatus::BEING_PROCESSED && !empty($sellerLines)) {
            $fulfillmentFeeRes = app(CustomerSalesOrderRepository::class)->checkFulfillmentFee($sellerLines);
            if (!$fulfillmentFeeRes) {
                // 如果没通过，订单状态变为On hold
                $status = CustomerSalesOrderStatus::ON_HOLD;
                $return['msg'] = $this->language->get('error_can_bp_fulfillment_fee');
                $return['code'] = 104;
            }
        }
        // 不存在abnormal且存在ltl
        $this->salesOrderStatusUpdate($pre_status, $status, $id, $type);
        if ($status == CustomerSalesOrderStatus::BEING_PROCESSED) {
            $return['success'] = true;
            $return['code'] = 200;
        }
        return $return;
    }

    public function getReleaseOrderInfo($order_id)
    {
        $order_status = DB::table('tb_sys_customer_sales_order')
            ->where('id', $order_id)
            ->value('order_status');
        if ($order_status == CustomerSalesOrderStatus::ABNORMAL_ORDER) {
            $type = 4;
        } else {
            $type = 3;
        }
        return [
            'type' => $type,
            'order_status' => $order_status,
        ];
    }

    public function releaseOrder($order_id, $pre_status = CustomerSalesOrderStatus::ABNORMAL_ORDER, $type = 1)
    {
        // 1 初次导单 需要验证 以及库存是否LTL
        // 2 sku更改 需要验证  以及库存是否LTL
        // 3 on hold 订单release
        // 4 abnormal order 订单release
        if ($type == 1
            || $type == 2
            || $type == 3
            || $type == 4)
        {
            // $pre_status 4
            // on hold 前 CustomerSalesOrderStatus::ABNORMAL_ORDER 直接更改
            // on hold 前 64 库存不够CustomerSalesOrderStatus::ABNORMAL_ORDER 库存够 LTL 验证通过 2 LTL验证没通过64
            return $this->updateSingleOriginalSalesOrderStatus($order_id, $pre_status, $type);
        }
        return false;
    }

    public function getLtlCheckInfoByOrderId($order_id_string)
    {
        $line_list = CustomerSalesOrder::query()->alias('o')
            ->leftJoinRelations(['lines as l'])
            ->whereIn('l.header_id', explode('_', $order_id_string))
            ->selectRaw('l.qty,l.item_code,o.order_id,o.id,l.header_id,o.ship_address1,o.ship_city,o.ship_zip_code,o.ship_state,o.ship_country,o.ship_phone,o.ship_name')
            ->get()
            ->toArray();
        $orders = [];
        if ($line_list) {
            foreach ($line_list as $key => $value) {
                if ($value['ship_phone']) {
                    $ship_phone = '(' . $value['ship_phone'] . ') ';
                } else {
                    $ship_phone = ' ';
                }
                $value['detail_address'] = app('db-aes')->decrypt($value['ship_name'])
                    . ' ' . $ship_phone
                    . app('db-aes')->decrypt($value['ship_address1'])
                    . ',' . app('db-aes')->decrypt($value['ship_city'])
                    . ',' . $value['ship_zip_code']
                    . ',' . $value['ship_state'] .
                    ',' . $value['ship_country'];
                $value['detail_address'] = rtrim($value['detail_address'], ',');
                $orders[$value['header_id']]['list'][] = $value;
                $orders[$value['header_id']]['count'] = count($orders[$value['header_id']]['list']);
            }
        }
        return array_values($orders);
    }

    public function changeLtlStatusToBP($order_id_list)
    {
        $other_exists = $this->orm->table('tb_sys_customer_sales_order')
            ->where([['order_status', '!=', CustomerSalesOrderStatus::LTL_CHECK]])
            ->whereIn('id', $order_id_list)
            ->exists();
        if ($other_exists) {
            $json['msg'] = $this->language->get('error_can_ltl');
        } else {
            $list = DB::table('tb_sys_customer_sales_order_line as l')
                ->leftJoin('tb_sys_customer_sales_order as o', 'l.header_id', '=', 'o.id')
                ->leftJoin('oc_customerpartner_to_product as cp', 'l.product_id', '=', 'cp.product_id')
                ->whereIn('l.header_id', $order_id_list)
                ->selectRaw('l.*,o.buyer_id,cp.customer_id as cp_seller_id')
                ->get()
                ->map(function ($v) {
                    return (array)$v;
                })
                ->toArray();
            foreach ($list as $value) {
                $sellerLines[$value['cp_seller_id']][] = [
                    'product_id' => $value['product_id'],
                    'qty' => $value['qty'],
                    'estimate_freight' => $value['estimate_freight']
                ];
            }
            $status = CustomerSalesOrderStatus::BEING_PROCESSED;
            $fulfillmentFeeRes = app(CustomerSalesOrderRepository::class)->checkFulfillmentFee($sellerLines);
            if (!$fulfillmentFeeRes) {
                $status = CustomerSalesOrderStatus::ON_HOLD;
            }
            $this->log->write('order_id：' . implode(',', $order_id_list) . ',pre_status:64' . ',suf_status:' . $status);

            DB::table('tb_sys_customer_sales_order')
                ->whereIn('id', $order_id_list)
                ->update(
                    [
                        'order_status' => $status,
                        'ltl_process_status' => 1,
                        'ltl_process_time' => date('Y-m-d H:i:s'),
                    ]
                );
            DB::table('tb_sys_customer_sales_order_line')
                ->whereIn('header_id', $order_id_list)
                ->update(['item_status' => CustomerSalesOrderLineItemStatus::PENDING]);
            if ($fulfillmentFeeRes) {
                $json['msg'] = $this->language->get('text_change_status_success');
            } else {
                $json['msg'] = $this->language->get('error_can_bp_fulfillment_fee');
            }
        }
        return $json;
    }

    /**
     * [updateSalesOrderLineSku description]
     * @param int $order_id
     * @param int $line_id
     * @param int $product_id
     * @param int $customer_id
     * @return bool
     */
    public function updateSalesOrderLineSku($order_id, $line_id, $product_id, $customer_id)
    {
        // 1.更改完成后的sku 是否为combo 需要更新combo info
        // 2.实时获取订单的状态 CustomerSalesOrderStatus::ABNORMAL_ORDER abnormal order 才能够更改
        // 3.更新订单状态

        $qty = DB::table('tb_sys_customer_sales_order_line')->where('id', $line_id)->value('qty');
        $item_code = DB::table(DB_PREFIX . 'product')->where('product_id', $product_id)->value('sku');
        $combo_info = $this->getSalesOrderLineComboInfo($item_code, $customer_id, $qty);
        if ($combo_info['combo_info']) {
            $combo_info['combo_info'] = json_encode($combo_info['combo_info']);
        }

        if ($product_id == $combo_info['product_id']) {
            DB::table('tb_sys_customer_sales_order_line')
                ->where('id', $line_id)
                ->update([
                    'item_code' => $item_code,
                    'combo_info' => $combo_info['combo_info'],
                    'product_id' => $combo_info['product_id'],
                ]);
            $pre_status = DB::table('tb_sys_customer_sales_order')->where('id', $order_id)->value('order_status');
            $this->releaseOrder($order_id, $pre_status, 2);
            return true;
        }
        return false;
    }

    /**
     * [salesOrderStatusUpdate description] 更新订单状态
     * @param int $pre_status
     * @param int $suf_status
     * @param int $order_id
     * @param int $type 1: 初次导单 CustomerSalesOrderStatus::ABNORMAL_ORDER => 2 ,2:更改sku CustomerSalesOrderStatus::ABNORMAL_ORDER => 2 ,3 on hold 更改 ，release order 4 => 2
     * @return bool
     */
    public function salesOrderStatusUpdate($pre_status, $suf_status, $order_id, $type)
    {
        //记录log
        $this->log->write('order_id：' . $order_id . ',type_id:' . $type . ',pre_status:' . $pre_status . ',suf_status:' . $suf_status);
        switch ($type) {
            case 4:
            case 3:
            case 2:
            case 1:
                DB::table('tb_sys_customer_sales_order')
                    ->where('id', $order_id)
                    ->update(['order_status' => $suf_status]);
                DB::table('tb_sys_customer_sales_order_line')
                    ->where('header_id', $order_id)
                    ->update(['item_status' => CustomerSalesOrderLineItemStatus::PENDING]);
            default:

        }
        return true;
    }

    private function getSellerSalesOrderHeader($country_id, $import_mode)
    {
        if ($import_mode == 0) {
            if ($country_id == AMERICAN_COUNTRY_ID) {
                return [
                    'SalesPlatform',                    // 销售平台
                    'OrderId',                          // 订单号
                    'LineItemNumber',                   // 订单明细号
                    'OrderDate',                        // 订单时间
                    'SellerBrand',                       // Buyer的品牌
                    'SellerPlatformSku',                 // Buyer平台Sku
                    'B2BItemCode',                      // B2B平台商品Code
                    'SkuDescription',              // Buyer商品描述
                    'SkuCommercialValue',          // Buyer商品的商业价值/件
                    'SkuLink',                     // Buyer商品的购买链接
                    'ShipToQty',                        // 发货数量
                    'ShipToService',                    // 发货物流服务
                    'ShipToServiceLevel',               // 发货物流服务等级
                    'ShippedDate',                      // 希望发货日期
                    'ShipToAttachmentUrl',              // 发货附件链接地址
                    'ShipToName',                       // 收货人
                    'ShipToEmail',                      // 收货人邮箱
                    'ShipToPhone',                      // 收货人电话
                    'ShipToPostalCode',                 // 收货邮编
                    'ShipToAddressDetail',              // 收货详细地址
                    'ShipToCity',                       // 收货城市
                    'ShipToState',                      // 收货州/地区
                    'ShipToCountry',                    // 收货国家
                    'OrderComments'                     // 订单备注
                ];
            }

            return [
                'SalesPlatform',                    // 销售平台
                'OrderId',                          // 订单号
                'LineItemNumber',                   // 订单明细号
                'OrderDate',                        // 订单时间
                'SellerBrand',                       // Buyer的品牌
                'SellerPlatformSku',                 // Buyer平台Sku
                'B2BItemCode',                      // B2B平台商品Code
                'SkuDescription',              // Buyer商品描述
                'SkuCommercialValue',          // Buyer商品的商业价值/件
                'SkuLink',                     // Buyer商品的购买链接
                'ShipToQty',                        // 发货数量
                'DeliveryToFBAWarehouse',           // 是否是FBA发货
                //'ShipToService',                    // 发货物流服务
                'ShipToServiceLevel',               // 发货物流服务等级
                'ShippedDate',                      // 希望发货日期
                'ShipToAttachmentUrl',              // 发货附件链接地址
                'ShipToName',                       // 收货人
                'ShipToEmail',                      // 收货人邮箱
                'ShipToPhone',                      // 收货人电话
                'ShipToPostalCode',                 // 收货邮编
                'ShipToAddressDetail',              // 收货详细地址
                'ShipToCity',                       // 收货城市
                'ShipToState',                      // 收货州/地区
                'ShipToCountry',                    // 收货国家
                'OrderComments'                     // 订单备注
            ];

        }

        return [];
    }


    public function verifyFileDataFirstLine($data, $import_mode, $country_id = AMERICAN_COUNTRY_ID)
    {
        $error = '';
        $excel_header = $this->getSellerSalesOrderHeader($country_id, $import_mode);
        if (!isset($data[1]) || $data[1] != $excel_header) {
            $error = 'The columns of the uploaded file are inconsistent with the template,please check and re-upload.';
        }
        if (count($data) == 2) {
            $error = 'No data was found in the file.';
        }
        return $error;


    }

    public function verifyFileDataOrderUnique($data, $import_mode)
    {
        $order = [];
        $error = '';
        if ($import_mode == 0) {
            //excel 需要处理数据
            unset($data[0]);
            // 数组结构重组
            $data = $this->formatFileData($data);
            foreach ($data as $key => &$value) {
                //去除所有空格 包括中文空格圆角空格
                //$value['OrderId'] = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","",$value['OrderId']);
                $value['B2BItemCode'] = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", $value['B2BItemCode']);
                $order_sku_key = trim($value['OrderId']) . '_' . trim($value['B2BItemCode']);
                $order[$order_sku_key][] = $key + 3;
            }
        }
        foreach ($order as $k => $v) {
            if (count($v) > 1) {
                $error .= 'This file has the same order details. Number of lines:' . implode(',', $v) . '<br/>';
            }
        }
        $ret['error'] = $error;
        $ret['data'] = $data;
        return $ret;
    }

    public function formatFileData($data)
    {
        $first_key = key($data);
        $ret = [];
        foreach ($data as $key => $value) {
            if ($first_key != $key) {
                $ret[] = array_combine($data[$first_key], $value);
            }
        }
        return $ret;
    }

    public function addSalesOrderInfo($data, $import_mode, $run_id, $country_id, $customer_id)
    {
        if ($import_mode == 0) {
            $order_mode = CustomerSalesOrderMode::PURE_LOGISTICS;
            //已存在的订单OrderId
            $existentOrderIdArray = [];
            $order_column_info = [];
            $verify_order = [];
            $orderArr = [];
            // 所有行相同的orderId 提取B2BItemCode 后面用于判断是否LTL
            $orderIdItemCode = [];
            foreach ($data as $key => $value) {
                if (isset($value['OrderId']) && isset($value['B2BItemCode'])) {
                    $orderIdItemCode[trim($value['OrderId'])][] = trim($value['B2BItemCode']);
                }
            }

            foreach ($data as $key => $value) {
                $flag = true;

                foreach ($value as $k => $v) { // check 每行数据是否有全部空的数据，则跳过
                    if ($v) {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    continue;
                }
                $column_ret = $this->verifyCommonOrderFileDataColumn($value, $key + 3, $country_id, $orderIdItemCode[trim($value['OrderId'])] ?? []);
                if (($country_id != AMERICAN_COUNTRY_ID) || (DISABLE_SHIP_TO_SERVICE && $value['ShipToService'] == 'ASR')) {
                    $value['ShipToService'] = '';
                } else {
                    if (strtoupper(trim($value['ShipToService'])) == 'ASR') {
                        $value['ShipToService'] = 'ASR';
                    }
                }

                if ($column_ret !== true) {
                    return $column_ret;
                }
                $checkResult = $this->judgeCommonOrderIsExist(trim($value['OrderId']), $customer_id);
                if ($checkResult) {
                    $existentOrderIdArray[] = trim($value['OrderId']);
                }
                $judge_column = ['ShipToPhone', 'ShipToPostalCode', 'ShipToAddressDetail', 'ShipToCity', 'ShipToState'];
                foreach ($judge_column as $ks => $vs) {
                    $judge_ret = $this->dealErrorCode($value[$vs]);
                    if ($judge_ret != false) {
                        $order_column_info[trim($value['OrderId'])][$vs]['string'] = $value[$vs];
                        $order_column_info[trim($value['OrderId'])][$vs]['position'] = $judge_ret;
                    }
                }
                $orderArr[] = [
                    "orders_from" => $value['SalesPlatform'] == '' ? "" : $value['SalesPlatform'],
                    "order_id" => $value['OrderId'] == '' ? null : trim($value['OrderId']),
                    "line_item_number" => $value['LineItemNumber'] == '' ? null : $value['LineItemNumber'],
                    "email" => $value['ShipToEmail'] == '' ? null : $value['ShipToEmail'],
                    "order_date" => $value['OrderDate'] == '' ? date('Y-m-d H:i:s') : $value['OrderDate'],
                    "bill_name" => $value['ShipToName'] == '' ? null : $value['ShipToName'],
                    "bill_address" => $value['ShipToAddressDetail'] == '' ? null : $value['ShipToAddressDetail'],
                    "bill_city" => $value['ShipToCity'] == '' ? null : $value['ShipToCity'],
                    "bill_state" => $value['ShipToState'] == '' ? null : $value['ShipToState'],
                    "bill_zip_code" => $value['ShipToPostalCode'] == '' ? null : $value['ShipToPostalCode'],
                    "bill_country" => $value['ShipToCountry'] == '' ? null : $value['ShipToCountry'],
                    "ship_name" => $value['ShipToName'] == '' ? null : $value['ShipToName'],
                    "ship_address1" => $value['ShipToAddressDetail'] == '' ? null : $value['ShipToAddressDetail'],
                    "ship_address2" => null,
                    "ship_city" => $value['ShipToCity'] == '' ? null : $value['ShipToCity'],
                    "ship_state" => $value['ShipToState'] == '' ? null : $value['ShipToState'],
                    "ship_zip_code" => $value['ShipToPostalCode'] == '' ? null : $value['ShipToPostalCode'],
                    "ship_country" => $value['ShipToCountry'] == '' ? null : $value['ShipToCountry'],
                    "ship_phone" => $value['ShipToPhone'] == '' ? null : $value['ShipToPhone'],
                    "item_code" => $value['B2BItemCode'] == '' ? null : strtoupper(trim($value['B2BItemCode'])),
                    "alt_item_id" => $value['SkuLink'] == '' ? null : $value['SkuLink'],
                    "product_name" => $value['SkuDescription'] == '' ? 'product name' : $value['SkuDescription'],
                    "qty" => $value['ShipToQty'] == '' ? null : $value['ShipToQty'],
                    "item_price" => $value['SkuCommercialValue'] == '' ? 1 : $value['SkuCommercialValue'],
                    "item_unit_discount" => null,
                    "item_tax" => null,
                    "discount_amount" => null,
                    "tax_amount" => null,
                    "ship_amount" => null,
                    "order_total" => 1,
                    "payment_method" => null,
                    "ship_company" => null,
                    "delivery_to_fba" => strtoupper($value['DeliveryToFBAWarehouse']) == 'YES' ? 1 : 0,
                    "ship_method" => $value['ShipToService'] == '' ? null : strtoupper($value['ShipToService']),
                    "ship_service_level" => $value['ShipToServiceLevel'] == '' ? null : $value['ShipToServiceLevel'],
                    "brand_id" => $value['SellerBrand'] == '' ? null : $value['SellerBrand'],
                    "customer_comments" => $value['OrderComments'] == '' ? null : $value['OrderComments'],
                    "shipped_date" => $value['ShippedDate'] == '' ? null : trim($value['ShippedDate']),//13195OrderFulfillment订单导入模板调优
                    "ship_to_attachment_url" => $value['ShipToAttachmentUrl'] == '' ? null : $value['ShipToAttachmentUrl'],
                    //"seller_id"          => $sellerId,
                    "buyer_id" => $customer_id,
                    "run_id" => $run_id,
                    "create_user_name" => $customer_id,
                    "create_time" => date('Y-m-d H:i:s'),
                    "update_user_name" => PROGRAM_CODE
                ];
                //order_id+lineItemNo不能相同
                $verify_order[] = trim($value['OrderId']) . '_' . $value['LineItemNumber'];

            }
            if (count($verify_order) != count(array_unique($verify_order))) {
                return "Order_id Duplicate,please check the uploaded file.";
            }
            if (!empty($existentOrderIdArray)) {
                return 'OrderId:' . implode('、', array_unique($existentOrderIdArray) ) . ' is already exist ,please check the uploaded file.';
            }

            // 插入临时表
            $this->saveCustomerSalesOrderTemp($orderArr);
            // 根据run_id获取上步插入的临时表数据
            $orderTempArr = $this->findCustomerSalesOrderTemp($run_id, $customer_id);
            // 订单头表数据
            $customerSalesOrderArr = [];
            $yzc_order_id_number = $this->getYzcOrderIdNumber();
            foreach ($orderTempArr as $key => $value) {
                //导入订单根据order_id来进行合并
                $order_id = $value['order_id'];
                $salesOrder = $this->getCommonOrderColumnNameConversion($value, $order_mode, $customer_id, $country_id, $import_mode);
                if (!isset($customerSalesOrderArr[$order_id])) {
                    $yzc_order_id_number++;
                    // 新订单头
                    //获取一个插入dropship和tb_sys_customer_sales_order的映射关系
                    $salesOrder['yzc_order_id'] = 'YC-' . $yzc_order_id_number;
                    $customerSalesOrderArr[$order_id] = $salesOrder;
                } else {
                    //订单信息有变动需要更改
                    // line_count
                    // order_total
                    // line_item_number
                    $tmp = $salesOrder['product_info'][0];
                    $tmp['line_item_number'] = count($customerSalesOrderArr[$order_id]['product_info']) + 1;
                    $customerSalesOrderArr[$order_id]['line_count'] = count($customerSalesOrderArr[$order_id]['product_info']) + 1;
                    $customerSalesOrderArr[$order_id]['order_total'] += $tmp['item_price'] * $tmp['qty'];
                    $customerSalesOrderArr[$order_id]['order_total'] = sprintf('%.2f', $customerSalesOrderArr[$order_id]['order_total']);
                    $customerSalesOrderArr[$order_id]['product_info'][] = $tmp;

                    if (!empty($salesOrder['delivery_to_fba'])) { // 同一个销售单，存在一个销售明细是送FBA，则整个销售单都送FBA
                        $customerSalesOrderArr[$order_id]['delivery_to_fba'] = $salesOrder['delivery_to_fba'];
                    }
                }
            }
            unset($salesOrder);
            // 获取纯物流预估运费 欧洲国别不参与风控
            if ($country_id == AMERICAN_COUNTRY_ID) {
                $productRepo = app(ProductRepository::class);
                foreach ($customerSalesOrderArr as $orderId => &$salesOrder) {
                    if (empty($salesOrder['product_info'])) {
                        continue;
                    }
                    // 构造请求数据
                    $requestFreightData = [];
                    foreach ($salesOrder['product_info'] as $product) {
                        if (empty($product['product_id'])) {
                            // 没有product id 跳过
                            continue;
                        }
                        $requestFreightData[] = [
                            'product_id' => $product['product_id'],
                            'qty' => $product['qty']
                        ];
                    }
                    if (empty($requestFreightData)) {
                        // 没有数据跳过
                        continue;
                    }
                    $estimateFreightData = $productRepo->getB2BManageProductFreightByProductList($requestFreightData, $customer_id);
                    if ($estimateFreightData && !empty($estimateFreightData['data'])) {
                        if ($estimateFreightData['ltl_flag']) {
                            // 超大件
                            /** @var FreightDTO $freightDTO */
                            $freightDTO = $estimateFreightData['data'];
                            $salesOrder['product_info'][0]['estimate_freight'] = ($freightDTO->dropShip->totalPure ?? 0);
                        } else {
                            foreach ($salesOrder['product_info'] as &$product) {
                                if (!empty($product['product_id']) && !empty($estimateFreightData['data'][$product['product_id']])) {
                                    /** @var FreightDTO $freightDTO */
                                    $freightDTO = $estimateFreightData['data'][$product['product_id']];
                                    $product['estimate_freight'] = $freightDTO->dropShip->totalPure;
                                }
                            }
                            unset($product);
                        }
                    }
                }
                unset($salesOrder);
            }
            $this->updateYzcOrderIdNumber($yzc_order_id_number);
            $this->insertCustomerSalesOrderAndLine($customerSalesOrderArr);
            $this->cache->set($customer_id . '_' . $run_id . '_column_exception', $order_column_info);
            return true;
        }

        return true;
    }

    public function getCommonOrderColumnNameConversion($data, $order_mode, $customer_id, $country_id = 223, $import_mode = 0)
    {
        // 更新combo info
        $combo_info = $this->getSalesOrderLineComboInfo($data['item_code'], $customer_id, $data['qty']);
        if ($combo_info['combo_info']) {
            $combo_info['combo_info'] = json_encode($combo_info['combo_info']);
        }
        $res = [];

        if (in_array($country_id, EUROPE_COUNTRY_ID)) {
            $res['is_international'] = app(SalesOrderService::class)
                ->checkIsInternationalSalesOrder($country_id, $data['ship_country']) ? 1 : 0;
        }
        if ($order_mode && $country_id) {
            $res['order_id'] = $data['order_id'];
            $res['order_date'] = $data['order_date'];
            $res['email'] = $data['email'];
            $res['ship_name'] = app('db-aes')->decrypt($data['ship_name']);
            $res['ship_address1'] = trim(app('db-aes')->decrypt(($data['ship_address1'])));
            $res['ship_address2'] = trim(app('db-aes')->decrypt($data['ship_address2']));
            $res['ship_city'] = app('db-aes')->decrypt($data['ship_city']);
            $res['ship_state'] = $data['ship_state'];
            $res['ship_zip_code'] = $data['ship_zip_code'];
            $res['ship_country'] = $data['ship_country'];
            $res['ship_phone'] = $data['ship_phone'];
            $res['ship_method'] = $data['ship_method'];
            $res['ship_service_level'] = $data['ship_service_level'];
            $res['delivery_to_fba'] = $data['delivery_to_fba'];
            $res['ship_company'] = $data['ship_company'];
            $res['shipped_date'] = $data['shipped_date'];
            $res['bill_name'] = app('db-aes')->decrypt($res['ship_name']);
            $res['bill_address'] = app('db-aes')->decrypt($res['ship_address1']);
            $res['bill_city'] = app('db-aes')->decrypt($res['ship_city']);
            $res['bill_state'] = $res['ship_state'];
            $res['bill_zip_code'] = $res['ship_zip_code'];
            $res['bill_country'] = $res['ship_country'];
            $res['orders_from'] = $data['orders_from']; //default
            $res['discount_amount'] = $data['discount_amount'];
            $res['tax_amount'] = $data['tax_amount'];
            $res['order_total'] = $data['order_total'];
            $res['payment_method'] = $data['payment_method'];
            $res['store_name'] = 'yzc';
            $res['store_id'] = 888;
            $res['buyer_id'] = $data['buyer_id'];
            $res['customer_comments'] = $data['customer_comments'];
            $res['run_id'] = $data['run_id'];
            $res['order_status'] = CustomerSalesOrderStatus::ABNORMAL_ORDER; //Abnormal order
            $res['order_mode'] = $order_mode;
            $res['create_user_name'] = $data['create_user_name'];
            $res['create_time'] = $data['create_time'];
            $res['program_code'] = $data['program_code'];
            $res['line_count'] = 1;
            $res['update_temp_id'] = $data['id'];
            $res['import_mode'] = $import_mode;
            $res['product_info'][0] = [
                'temp_id' => $data['id'],
                'line_item_number' => 1,
                'product_name' => $data['product_name'] == null ? 'product name' : $data['product_name'],
                'qty' => $data['qty'],
                'item_price' => sprintf('%.2f', $data['item_price']),
                'item_unit_discount' => $data['item_unit_discount'],
                'item_tax' => $data['item_tax'],
                'item_code' => $data['item_code'],
                'alt_item_id' => $data['alt_item_id'],
                'run_id' => $data['run_id'],
                'ship_amount' => $data['ship_amount'],
                'line_comments' => $data['customer_comments'],
                'image_id' => $data['brand_id'],
                'seller_id' => $data['seller_id'],
                'item_status' => 1,
                'combo_info' => $combo_info['combo_info'],
                'product_id' => $combo_info['product_id'],
                'create_user_name' => $data['create_user_name'],
                'create_time' => $data['create_time'],
                'program_code' => $data['program_code'],
            ];


        }
        return $res;
    }

    /**
     * [getSalesOrderLineComboInfo description]
     * @param int|string $item_code | $product_id
     * @param int $customer_id 0
     * @param int $qty
     * @return array
     */
    public function getSalesOrderLineComboInfo($item_code, $customer_id, $qty)
    {
        if ($customer_id) {
            $map = [
                'ctp.customer_id' => $customer_id,
                'p.sku' => $item_code,
            ];
        } else {
            $map = [
                'p.product_id' => $item_code,
            ];
        }
        $ret = DB::table(DB_PREFIX . 'customerpartner_to_product as ctp')
            ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
            ->leftJoin('tb_sys_product_set_info as s', 's.product_id', '=', 'p.product_id')
            ->leftJoin(DB_PREFIX . 'product as pc', 'pc.product_id', '=', 's.set_product_id')
            ->where($map)
            ->select('p.product_id', 's.qty', 's.set_product_id', 'pc.sku as pc_sku', 'p.sku')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if ($ret) {
            $combo_info[0] = [];
            $product_id = null;

            foreach ($ret as $key => $value) {
                $product_id = $value['product_id'];
                if ($key == 0 && $value['set_product_id']) {
                    $combo_info[0][$value['sku']] = $qty;
                }
                if ($value['set_product_id']) {
                    $combo_info[0][$value['pc_sku']] = $value['qty'];
                }
            }
            if ($combo_info[0]) {
                $final['combo_info'] = $combo_info;
            } else {
                $final['combo_info'] = null;
            }
            $final['product_id'] = $product_id;
            return $final;
        } else {
            $final['combo_info'] = null;
            $final['product_id'] = null;
            return $final;
        }
    }

    public function insertCustomerSalesOrderAndLine($data)
    {
        foreach ($data as $key => $value) {
            $tmp = $data[$key]['product_info'];
            unset($data[$key]['product_info']);
            $insertId = DB::table('tb_sys_customer_sales_order')->insertGetId($data[$key]);
            if ($insertId) {
                foreach ($tmp as $k => $v) {
                    $tmp[$k]['header_id'] = $insertId;
                    $insertChildId = DB::table('tb_sys_customer_sales_order_line')->insertGetId($tmp[$k]);
                }
            }
        }

    }

    public function updateYzcOrderIdNumber($id)
    {
        DB::table('tb_sys_sequence')
            ->where('seq_key', 'tb_sys_customer_sales_order|YzcOrderId')
            ->update([
                'seq_value' => $id,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
    }

    public function getYzcOrderIdNumber()
    {
        return DB::table('tb_sys_sequence')->where('seq_key', 'tb_sys_customer_sales_order|YzcOrderId')->value('seq_value');
    }

    public function judgeCommonOrderIsExist($order_id, $buyer_id)
    {
        $map['order_id'] = $order_id;
        $map['buyer_id'] = $buyer_id;
        return DB::table('tb_sys_customer_sales_order_temp')->where($map)->value('id');
    }

    /**
     * [verifyCommonOrderFileDataColumn description]
     * @param $data
     * @param $index
     * @param int $country_id
     * @param $itemCodes
     * @return bool|string
     */
    public function verifyCommonOrderFileDataColumn($data, $index, $country_id, $itemCodes = [])
    {
        $isLTL = false;
        if ($country_id == AMERICAN_COUNTRY_ID) {
            $cacheKey = [__CLASS__, __FUNCTION__, $data['OrderId']];
            if ($this->getRequestCachedData($cacheKey) !== null) {
                $isLTL = $this->getRequestCachedData($cacheKey);
            } else {
                $isLTL = app(CustomerSalesOrderRepository::class)->isLTL($country_id, $itemCodes);
                $this->setRequestCachedData($cacheKey, $isLTL);
            }
        }
        if (strlen($data['SalesPlatform']) > 20) {
            return 'Line' . $index . ",SalesPlatform must be between 0 and 20 characters!";
        }
        if ($data['OrderId'] == '' || strlen($data['OrderId']) > 40) {
            return 'Line' . $index . ",OrderId must be between 1 and 40 characters!";
        }
        if ($data['LineItemNumber'] == '' || strlen($data['LineItemNumber']) > 50) {
            return 'Line' . $index . ",LineItemNumber must be between 1 and 50 characters!";
        }
        if (strlen($data['OrderDate']) > 25) {
            return 'Line' . $index . ",OrderDate must be between 0 and 25 characters!";
        }
        if (strlen($data['SellerBrand']) > 30) {
            return 'Line' . $index . ",SellerBrand must be between 0 and 30 characters!";
        }
        if (strlen($data['SellerPlatformSku']) > 25) {
            //13584 需求，Buyer导入订单时校验ItemCode的录入itemCode去掉首尾的空格，
            //如果ITEMCODE不是由字母和数字组成的，那么提示BuyerItemCode有问题，这个文件不能导入
            //整个上传格式会发生变化
            return 'Line' . $index . ",SellerPlatformSku must be between 0 and 25 characters!";
        }
        if (trim($data['B2BItemCode']) == '' || strlen($data['B2BItemCode']) > 30) {
            return 'Line' . $index . ",B2BItemCode must be between 1 and 30 characters!";

        }
        if (strlen($data['SkuDescription']) > 100) {
            return 'Line' . $index . ",SkuDescription must be between 0 and 100 characters!";

        }
        $reg3 = '/^(([1-9][0-9]*)|(([0]\.\d{1,2}|[1-9][0-9]*\.\d{1,2})))$/';
        if ($data['SkuCommercialValue'] != '') {
            if (!preg_match($reg3, $data['SkuCommercialValue'])) {
                return 'Line' . $index . ",SkuCommercialValue format error,Please see the instructions.";
            }
        }
        if (strlen($data['SkuLink']) > 50) {
            return 'Line' . $index . ",BuyerSkuLink must be between 0 and 50 characters!";
        }
        if ($data['ShipToQty'] == '' || !preg_match('/^[0-9]*$/', $data['ShipToQty']) || $data['ShipToQty'] <= 0) {
            return 'Line' . $index . ",ShipToQty format error,Please see the instructions.";
        }

        if(trim($data['DeliveryToFBAWarehouse']) && !in_array(strtolower(trim($data['DeliveryToFBAWarehouse'])),['yes','no'])){
            return 'Line' . $index . ",DeliveryToFBAWarehouse format error,Please see the instructions.";
        }

        if (strlen($data['ShipToServiceLevel']) > 50) {
            return 'Line' . $index . ",ShipToServiceLevel must be between 0 and 50 characters!";
        }
        if (strlen($data['ShipToAttachmentUrl']) > 800) {
            return 'Line' . $index . ",ShipToAttachmentUrl must be between 0 and 800 characters!";
        }
        if (trim($data['ShipToName']) == '' || strlen($data['ShipToName']) > 40) {
            return 'Line' . $index . ",ShipToName must be between 1 and 40 characters!";
        }
        if (trim($data['ShipToEmail']) == '' || strlen($data['ShipToEmail']) > 90) {
            return 'Line' . $index . ",ShipToEmail must be between 1 and 90 characters!";
        }
        if (trim($data['ShipToPhone']) == '' || strlen($data['ShipToPhone']) > 45) {
            return 'Line' . $index . ",ShipToPhone must be between 1 and 45 characters!";
        }

        if (trim($data['ShipToPostalCode']) == '' || strlen($data['ShipToPostalCode']) > 18) {
            return 'Line' . $index . ",ShipToPostalCode must be between 1 and 18 characters!";
        }

        $shipToAddressDetail = trim($data['ShipToAddressDetail']);
        if (empty($shipToAddressDetail)) {
            return 'Line' . $index . ",ShipToAddressDetail Can not be empty!";
        }
        $str = "Line %d,ShipToAddressDetail must be between 1 and %d characters!";
        if (!$isLTL) {
            if (($country_id == AMERICAN_COUNTRY_ID)) {
                $len = $this->config->get('config_b2b_address_len_us1');
                if (StringHelper::stringCharactersLen($shipToAddressDetail) > $len) {
                    return sprintf($str, $index, $len);
                }
                if (AddressHelper::isPoBox($shipToAddressDetail)) {
                    return 'Line' . $index . ",ShipToAddressDetail in P.O.BOX doesn't support delivery,Please see the instructions.";
                }
            } else if ($country_id == UK_COUNTRY_ID) {
                $len = $this->config->get('config_b2b_address_len_uk');
                if (StringHelper::stringCharactersLen($shipToAddressDetail) > $len) {
                    return sprintf($str, $index, $len);
                }
            } else if ($country_id == DE_COUNTRY_ID) {
                $len = $this->config->get('config_b2b_address_len_de');
                if (StringHelper::stringCharactersLen($shipToAddressDetail) > $len) {
                    return sprintf($str, $index, $len);
                }
            } else if ($country_id == JAPAN_COUNTRY_ID) {
                $len = $this->config->get('config_b2b_address_len_jp');
                if (StringHelper::stringCharactersLen($shipToAddressDetail) > $len) {
                    return sprintf($str, $index, $len);
                }
            }
        } else {
            $len = $this->config->get('config_b2b_address_len');
            if (StringHelper::stringCharactersLen($shipToAddressDetail) > $len) {
                return sprintf($str, $index, $len);
            }
        }

        if (trim($data['ShipToCity']) == '' || strlen($data['ShipToCity']) > 30) {
            return 'Line' . $index . ",ShipToCity must be between 1 and 30 characters!";

        }
        if ($country_id == JAPAN_COUNTRY_ID) {
            //验证日本的ShippedDate
            $time_period = JapanSalesOrder::getShipDateList();
            $shippedDateRegex = JapanSalesOrder::SHIP_DATE_REGEX;
            $shippedDate = trim($data['ShippedDate']);
            //验证是否相等
            if ($shippedDate != '') {
                $period_time = substr($shippedDate, -12);
                if (in_array($period_time, $time_period) && preg_match($shippedDateRegex, $shippedDate)) {
                    $ship_time = substr($shippedDate, 0, strpos($shippedDate, 'T'));
                    $timestamp = strtotime($ship_time);
                    if ($timestamp == false) {
                        return 'Line' . $index . ",ShippedDate format error,Please see the instructions.";
                    }

                } else {
                    return 'Line' . $index . ",ShippedDate format error,Please see the instructions.";

                }
            }
        }

        if ($country_id == AMERICAN_COUNTRY_ID) {
            if (trim($data['ShipToState']) == '' || strlen($data['ShipToState']) > 30) {
                return 'Line' . $index . ",ShipToState must be between 1 and 30 characters!";
            }

            if (!$isLTL) {
                if (AddressHelper::isRemoteRegion($data['ShipToState'] ?? '')) {
                    return 'Line' . $index . ",ShipToState in PR, AK, HI, GU, AA, AE, AP doesn't support delivery,Please see the instructions.";
                }
            }
        } else {

            if (trim($data['ShipToState']) == '' || strlen($data['ShipToState']) > 30) {
                return 'Line' . $index . ",ShipToState must be between 1 and 30 characters!";

            }
        }

        // 若可发往国际，但出现了没有维护的国别，可以上传成功，无报错文案和提醒文案
        if ($country_id == AMERICAN_COUNTRY_ID) {
            if (strtoupper($data['ShipToCountry']) != 'US') {
                return 'Line' . $index . ",ShipToCountry must be 'US'.";
            }
        }
        if ($country_id == JAPAN_COUNTRY_ID) {
            if (strtoupper($data['ShipToCountry']) != 'JP') {
                return 'Line' . $index . ",This order may only be shipped within its country of origin.";
            }
        }

        $inactiveInternationalCountryList = InternationalOrderConfig::query()
            ->where('status', 0)
            ->pluck('country_id')
            ->toArray();

        if (in_array($country_id, $inactiveInternationalCountryList)) {
            if ($country_id == Country::BRITAIN) {
                if (!in_array(strtoupper($data['ShipToCountry']), ['UK', 'GB'])) {
                    return 'Line' . $index . ",This order may only be shipped within its country of origin.";
                }
            }
            if ($country_id == Country::GERMANY) {
                if (strtoupper($data['ShipToCountry']) != 'DE') {
                    return 'Line' . $index . ",This order may only be shipped within its country of origin.";
                }
            }
        }else{
            if(!trim($data['ShipToCountry'])){
                return 'Line' . $index . ",ShipToCountry format error,Please see the instructions.";
            }
        }

        if (strlen($data['OrderComments']) > 1500) {
            return 'Line' . $index . ",OrderComments must be between 0 and 1500 characters!";

        }

        return true;

    }

    public function saveCustomerSalesOrderTemp($data)
    {
        if ($data) {
            DB::table('tb_sys_customer_sales_order_temp')->insert($data);
        }
    }

    public function saveCustomerSalesOrders($data)
    {
        if ($data) {
            DB::table('tb_sys_customer_sales_order')->insert($data);
        }
    }

    public function findCustomerSalesOrderTemp($run_id, $customer_id)
    {
        return DB::table('tb_sys_customer_sales_order_temp')
            ->where([
                'run_id' => $run_id,
                'buyer_id' => $customer_id,
            ])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
    }

    public function getCustomerOrderAllInformation($order_id, $tracking_privilege = false)
    {
        $this->load->model('catalog/product');
        $this->load->model('account/customer_order_import');
        $this->load->model('tool/image');
        $mapOrder = [
            ['o.id', '=', $order_id],
        ];
        $base_info = CustomerSalesOrder::query()->alias('o')
            ->where($mapOrder)
            ->select(['o.order_id', 'o.order_status', 'o.create_time', 'o.orders_from', 'o.id', 'o.ship_name', 'o.ship_phone', 'o.email', 'o.shipped_date', 'o.ship_method', 'o.ship_service_level', 'o.ship_address1', 'o.ship_city', 'o.ship_state', 'o.ship_zip_code', 'o.ship_country', 'o.order_mode', 'o.customer_comments', 'o.buyer_id'])
            ->get()
            ->toArray();
        $base_info = current($base_info);
        $base_info['customer_comments'] = trim($base_info['customer_comments']);
        $base_info['order_status_name'] = CustomerSalesOrderStatus::getDescription($base_info['order_status']);
        if ($base_info['order_mode'] == CustomerSalesOrderMode::PURE_LOGISTICS) {
            //标红 且处理
            $base_info['ship_name_tips'] = '';
            $base_info['ship_phone_tips'] = '';
            $base_info['ship_address_tips'] = '';
            $judge_column = ['ship_phone', 'ship_address1', 'ship_state', 'ship_city', 'ship_zip_code'];
            foreach ($judge_column as $ks => $vs) {
                $s = $this->dealErrorCode($base_info[$vs]);
                if ($s != false) {
                    $base_info[$vs] = $s;
                    $column = 'text_error_column_' . $base_info['order_status'];
                    if ($ks == 0) {
                        $base_info['ship_phone_tips'] = '<i style="cursor:pointer;color:red" class="giga  icon-action-warning" data-toggle="tooltip" title="' . sprintf($this->language->get($column), 'Recipient Phone#') . '"></i>';
                    } else {
                        $base_info['ship_address_tips'] = '<i style="cursor:pointer;color:red" class="giga  icon-action-warning" data-toggle="tooltip" title="' . sprintf($this->language->get($column), 'Shipping Address') . '"></i>';
                    }
                }
            }
        }

        //item_list 获取item_list
        $item_list = DB::table('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
            ->leftJoin('tb_sys_salesperson as sa', 'sa.id', '=', 'l.sales_person_id')
            ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'l.product_id')
            ->leftJoin('tb_sys_customer_sales_order_temp as t', 't.id', '=', 'l.temp_id')
            ->leftJoin('tb_sales_order_logistics_fee_detail as fd', 'fd.order_line_id', '=', 'l.id')
            ->where($mapOrder)
            ->orderBy('fd.id', 'asc')
            ->groupBy('l.id')
            ->select(
                'l.item_code',
                'l.qty',
                'o.sell_manager',
                'l.line_comments',
                'l.id as line_id',
                'sa.name as sales_person_name',
                'l.product_id',
                'l.item_price as price',
                'p.image',
                't.brand_id',
                'o.buyer_id'
            )
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $item_tag_list = [];
        $item_total_price = 0;
        $settleFlag = 0;
        $over_specification_flag = null;
        foreach ($item_list as $key => $value) {
            $item_list[$key]['tag'] = $this->model_catalog_product->getProductTagHtmlForThumb($value['product_id']);
            $item_list[$key]['image_show'] = $this->model_tool_image->resize($value['image'], 40, 40);
            //标记，加入
            $item_tag_list[$value['item_code']] = $item_list[$key]['tag'];
            $item_list[$key]['product_link'] = $this->url->link('product/product', 'product_id=' . $value['product_id']);
            $item_list[$key]['price_show'] = $this->currency->formatCurrencyPrice($value['price'], $this->session->data['currency']);
            // 获取每一个的 freight  和 package fee
            $freight_tmp = $this->getSalesOrderDetailsFreight($value['line_id'], $value['buyer_id'], $value['qty']);
            if ($freight_tmp) {
                $item_list[$key]['freight_show'] = $freight_tmp['freight_show'];
                $item_list[$key]['freight_per_show'] = $freight_tmp['freight_per_show'];
                $item_list[$key]['pack_fee_show'] = $freight_tmp['pack_fee_show'];
                $item_list[$key]['freight_per'] = $freight_tmp['freight_per'];
                $item_list[$key]['settle_flag'] = $settleFlag = $freight_tmp['settle_flag'];
                $item_list[$key]['over_specification_flag'] = $over_specification_flag = $freight_tmp['over_specification_flag'];
            } else {
                $item_list[$key]['freight_show'] = null;
                $item_list[$key]['freight_per_show'] = null;
                $item_list[$key]['pack_fee_show'] = null;
                $item_list[$key]['freight_per'] = 0;
                $item_list[$key]['settle_flag'] = 0;
                $item_list[$key]['over_specification_flag'] = $over_specification_flag;
            }

            $item_total_price += $item_list[$key]['freight_per'];
        }

        //获取shipping information 放在base_info 里了
        //获取 sku的服务信息
        $signature_sub_item_list = [];
        $result = [];
        if ($base_info['order_mode'] == CustomerSalesOrderMode::PURE_LOGISTICS) {
            //获取信息
            //通过order_mode = 1 的查询方式获取
            $shipping_information = DB::table('tb_sys_customer_sales_order_line as l')
                ->where('l.header_id', $order_id)
                ->select('l.id', 'l.item_status', 'l.item_code as sku', 'l.qty', 'l.combo_info', 'l.product_id')
                ->get()
                ->map(function ($v) {
                    return (array)$v;
                })
                ->toArray();
            foreach ($shipping_information as $key => $value) {
                $shipping_information[$key]['item_status_name'] = CustomerSalesOrderLineItemStatus::getDescription($value['item_status']);
                $shipping_information[$key]['tag'] = $item_tag_list[$value['sku']];
                //验证是否为combo
                $item_code = DB::table(DB_PREFIX . 'product as ts')
                    ->where('ts.sku', $value['sku'])->where('ts.is_deleted', 0)
                    ->orderBy('ts.product_id', 'desc')
                    ->value('ts.product_id');
                if ($item_code == null) {
                    $comboInfo = null;
                } else {
                    $comboInfo = DB::table('tb_sys_product_set_info as s')
                        ->where('p.product_id', $item_code)
                        ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.product_id')
                        ->leftJoin(DB_PREFIX . 'product as pc', 'pc.product_id', '=', 's.set_product_id')
                        ->whereNotNull('s.set_product_id')->select('p.product_id', 's.set_product_id', 's.qty', 'pc.sku')
                        ->get()
                        ->map(function ($v) {
                            return (array)$v;
                        })
                        ->toArray();
                    if ($value['combo_info']) {
                        $comboInfo = [];
                        $info = json_decode($value['combo_info'], true);
                        $k_i = 0;
                        foreach ($info[0] as $ki => $vi) {
                            if ($k_i != 0) {
                                $temp_k_i['product_id'] = $value['product_id'];
                                $temp_k_i['set_product_id'] = DB::table(DB_PREFIX . 'customerpartner_to_product as ctp')
                                    ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'ctp.product_id')
                                    ->where([
                                        'p.sku' => $ki,
                                        'ctp.customer_id' => $base_info['buyer_id'],
                                    ])
                                    ->value('p.product_id');
                                $temp_k_i['qty'] = $vi;
                                $temp_k_i['sku'] = $ki;
                                $comboInfo[] = $temp_k_i;
                            }
                            $k_i++;
                        }
                    }

                }

                if ($comboInfo) {
                    $length = count($comboInfo);
                    foreach ($comboInfo as $k => $v) {
                        //首先获取tacking_number
                        $mapTrackingInfo['k.SalerOrderLineId'] = $value['id'];
                        $mapTrackingInfo['k.ShipSku'] = $v['sku'];
                        $mapTrackingInfo['k.SalesOrderId'] = $base_info['order_id'];
                        if (($base_info['order_status'] == CustomerSalesOrderStatus::COMPLETED && $tracking_privilege) || !$tracking_privilege) {
                            $tracking_info = DB::table('tb_sys_customer_sales_order_tracking as k')
                                ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                                ->where($mapTrackingInfo)
                                ->select('c.CarrierName as carrier_name', 'k.TrackingNumber as tracking_number', 'k.status')
                                ->orderBy('k.status', 'desc')
                                ->get()
                                ->map(function ($v) {
                                    return (array)$v;
                                })
                                ->toArray();
                        } else {
                            $tracking_info = [];
                        }
                        unset($mapTrackingInfo);
                        $tracking_info = $this->model_account_customer_order_import->dealTrackingNumberToShow($tracking_info, $base_info['order_mode']);

                        $shipping_information[$key]['tracking_number'] = $tracking_info['tracking_number'];
                        $shipping_information[$key]['carrier_name'] = $tracking_info['carrier_name'];
                        $shipping_information[$key]['tracking_status'] = $tracking_info['status'];
                        $tmp_all = $shipping_information[$key];
                        if ($k == 0) {
                            $tmp_all['cross_row'] = $length;
                        }
                        $tmp_all['child_sku'] = $v['sku'];
                        $tmp_all['all_qty'] = $v['qty'] * $value['qty'];
                        $tmp_all['child_qty'] = $v['qty'];
                        //获取default_qty
                        if (isset($signature_sub_item_list[$value['sku']])) {
                            $signature_sub_item_list[$value['sku']] += $tmp_all['child_qty'];
                        } else {
                            $signature_sub_item_list[$value['sku']] = $tmp_all['child_qty'];
                        }
                        $result[] = $tmp_all;
                        unset($tmp_all);
                    }


                } else {
                    //获取tracking_number
                    $mapTrackingInfo['k.SalerOrderLineId'] = $value['id'];
                    $mapTrackingInfo['k.ShipSku'] = $value['sku'];
                    $mapTrackingInfo['k.SalesOrderId'] = $base_info['order_id'];
                    if (($base_info['order_status'] == CustomerSalesOrderStatus::COMPLETED && $tracking_privilege) || !$tracking_privilege) {
                        $tracking_info = $this->orm->table('tb_sys_customer_sales_order_tracking as k')
                            ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                            ->where($mapTrackingInfo)->
                            select('c.CarrierName as carrier_name', 'k.TrackingNumber as tracking_number', 'k.status')
                            ->orderBy('k.status', 'desc')
                            ->get()
                            ->map(function ($v) {
                                return (array)$v;
                            })
                            ->toArray();
                    } else {
                        $tracking_info = [];
                    }
                    //一个处理tracking_info 的方法
                    $tracking_info = $this->model_account_customer_order_import->dealTrackingNumberToShow($tracking_info, $base_info['order_mode']);
                    unset($mapTrackingInfo);
                    $shipping_information[$key]['tracking_number'] = $tracking_info['tracking_number'];
                    $shipping_information[$key]['carrier_name'] = $tracking_info['carrier_name'];
                    $shipping_information[$key]['tracking_status'] = $tracking_info['status'];
                    $shipping_information[$key]['cross_row'] = 1;
                    $shipping_information[$key]['tag'] = $item_tag_list[$value['sku']];
                    $signature_sub_item_list[$value['sku']] = 0;
                    $result[] = $shipping_information[$key];
                }
            }
            unset($shipping_information);
        }

        //获取的
        //获取ASR 服务费
        $sub_total = 0;
        $all_total = 0;
        $signature_list = [];
        if ($base_info['ship_method'] == 'ASR') {
            foreach ($item_list as $key => $value) {
                $ret = $this->getSalesOrderDetailsSignature($value['line_id'], $value['buyer_id'], $value['qty']);
                if ($ret) {
                    $signature_list[$key]['item_code'] = $value['item_code'];
                    $signature_list[$key]['tag'] = $value['tag'];
                    $signature_list[$key]['package_qty'] = $ret['package_qty'];
                    $signature_list[$key]['sub_item_qty'] = $ret['sub_item_qty'];
                    $signature_list[$key]['qty'] = $value['qty'];
                    $signature_list[$key]['price'] = $ret['price'];
                    $signature_list[$key]['total'] = $this->currency->formatCurrencyPrice($signature_list[$key]['price'] * $signature_list[$key]['package_qty'], $this->session->data['currency']);
                    $signature_list[$key]['price_show'] = $this->currency->formatCurrencyPrice($signature_list[$key]['price'], $this->session->data['currency']);
                    $sub_total += $signature_list[$key]['price'] * $signature_list[$key]['package_qty'];
                    $all_total += $signature_list[$key]['price'] * $signature_list[$key]['package_qty'];
                }
            }
        }
        $res['sub_total'] = $this->currency->formatCurrencyPrice($sub_total, $this->session->data['currency']);
        $res['all_total'] = $this->currency->formatCurrencyPrice($all_total, $this->session->data['currency']);
        $res['shipping_information'] = $result;
        $res['signature_list'] = $signature_list;
        $res['item_list'] = $item_list;
        $res['base_info'] = $base_info;
        $res['item_total_price'] = $item_total_price;
        $res['settle_flag'] = $settleFlag;
        $res['over_specification_flag'] = $over_specification_flag;
        return $res;
    }

    public function getSalesOrderDetailsSignature($line_id, $buyer_id, $qty)
    {
        $info = DB::table('tb_sales_order_logistics_fee_detail')
            ->where([
                'order_line_id' => $line_id,
                'customer_id' => $buyer_id,
            ])
            ->groupBy('order_line_id')
            ->selectRaw('sum(qty) as all_qty,sign_service_fee,c_product_id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if ($info) {
            if ($info[0]['c_product_id']) {
                $sub_item_qty = $info['0']['all_qty'] / $qty;
            } else {
                $sub_item_qty = 0;
            }
            $package_qty = $info[0]['all_qty'];
            return [
                'package_qty' => $package_qty,
                'sub_item_qty' => $sub_item_qty,
                'price' => $info[0]['sign_service_fee'],
            ];
        } else {
            return false;
        }
    }

    public function getSalesOrderDetailsFreight($line_id, $buyer_id, $qty)
    {
        $info = DB::table('tb_sales_order_logistics_fee_detail')
            ->where([
                'order_line_id' => $line_id,
                'customer_id' => $buyer_id,
            ])
            ->groupBy('order_line_id')
            ->select('settle_flag', 'over_specification_flag')
            ->selectRaw('sum(freight_total) as freight,sum(freight_unit * qty) as freight_unit,sum(pack_fee * qty) as pack_fee,sum(dangerous_fee * qty) as dangerous_fee,logistics_type')
            ->selectRaw('sum(ahc_fee * qty) as ahc_fee,sum(oversize_fee * qty) as oversize_fee,sum(forfeit_fee * qty) as forfeit_fee,sum(surcharge_fee_ltl * qty) as surcharge_fee_ltl, sum(over_specification_fee * qty) as over_specification_fee')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if ($info) {
            $over_ltl_fee = $over_ltl_fee = $info[0]['surcharge_fee_ltl'];
            if (!is_null($info[0]['over_specification_flag']) && $info[0]['over_specification_flag'] == 0) {
                $over_ltl_fee = max($info[0]['surcharge_fee_ltl'], $info[0]['over_specification_fee']);
            }


            $surcharge = max($info[0]['ahc_fee'], $info[0]['oversize_fee'], $info[0]['forfeit_fee'], $over_ltl_fee);//加上附加费
            $pack_fee_show = $this->currency->formatCurrencyPrice($info[0]['pack_fee'] / $qty, $this->session->get('currency'));
            if ($info[0]['logistics_type'] == 2) {
                $freight = $info[0]['freight'] / $qty;
                $freight_per_show = $this->currency->formatCurrencyPrice($freight + $info[0]['pack_fee'] / $qty + $surcharge / $qty, $this->session->get('currency'));
                $freight_per = $info[0]['freight'] + $info[0]['pack_fee'] + $surcharge;
                $freight_show = $this->currency->formatCurrencyPrice($freight + ($surcharge) / $qty, $this->session->get('currency'));
            } else {
                $freight = $info[0]['freight_unit'];
                $freight_per_show = $this->currency->formatCurrencyPrice(($freight + $info[0]['pack_fee'] + $surcharge + $info[0]['dangerous_fee']) / $qty, $this->session->get('currency'));
                $freight_per = $freight + $info[0]['pack_fee'] + $surcharge + $info[0]['dangerous_fee'];
                $freight_show = $this->currency->formatCurrencyPrice(($freight + $surcharge + $info[0]['dangerous_fee']) / $qty, $this->session->get('currency'));
            }

            return [
                'freight_show' => $freight_show,
                'pack_fee_show' => $pack_fee_show,
                'freight_per_show' => $freight_per_show,
                'freight_per' => $freight_per,
                'settle_flag' => $info[0]['settle_flag'],
                'over_specification_flag' => $info[0]['over_specification_flag'],
            ];
        } else {
            return false;
        }
    }

    /**
     * [dealErrorCode description] 根据
     * @param $str
     * @return array|bool
     */
    public function dealErrorCode($str)
    {
        $ret = [];
        $string_ret = '';
        $error_code = ['?', '？'];
        $length = mb_strlen($str);
        for ($i = 0; $i < $length; $i++) {
            if (in_array(mb_substr($str, $i, 1), $error_code)) {
                $ret[] = $i;
                $string_ret .= '<span style="color: red">' . mb_substr($str, $i, 1) . '</span>';
            } else {
                $string_ret .= mb_substr($str, $i, 1);
            }
        }
        if (count($ret)) {
            return $string_ret;
        } else {
            return false;
        }

    }

    /**
     * @param int $customer_id
     * @param array $data
     * @return CustomerSalesOrder|\Framework\Model\Eloquent\Builder|\Illuminate\Database\Concerns\BuildsQueries|\Illuminate\Database\Eloquent\Builder|Builder|mixed
     */
    private function querySalesOrder(int $customer_id, array $data = [])
    {
        $co = new Collection($data);
        return
            CustomerSalesOrder::query()->alias('c')
                ->select(['c.*'])
                ->leftJoinRelations(['lines as l'])
                ->where(['c.buyer_id' => $customer_id])
                ->when(!empty($co->get('filter_orderId')), function ($q) use ($co) {
                    $q->where('c.order_id', 'like', '%' . trim($co->get('filter_orderId')) . '%');
                })
                ->when(
                    !empty($co->get('filter_orderStatus')) && ($co->get('filter_orderStatus') != '*'),
                    function ($q) use ($co) {
                        $q->where('c.order_status', $co->get('filter_orderStatus'));
                    }
                )
                ->when(!empty($co->get('filter_item_code')), function ($q) use ($co) {
                    $q->where('l.item_code', 'like', '%' . trim($co->get('filter_item_code')) . '%');
                })
                ->when(
                    ($co->has('filter_tracking_number') && (int)$co->get('filter_tracking_number') !== 2),
                    function ($q) use ($co) {
                        $filter_tracking_number = (int)$co->get('filter_tracking_number');
                        $tracking_privilege = $co->get('tracking_privilege');
                        $q = $q->leftJoin(
                            'tb_sys_customer_sales_order_tracking as t',
                            ['c.order_id' => 't.SalesOrderId', 'l.id' => 't.SalerOrderLineId']
                        );
                        if ($tracking_privilege) {
                            if ($filter_tracking_number === 1) {
                                $q->whereNotNull('t.TrackingNumber')->where('c.order_status', CustomerSalesOrderStatus::COMPLETED);
                            } else {
                                $q->whereRaw(new Expression('(t.TrackingNumber is null or (t.TrackingNumber is not null and c.order_status !=' . CustomerSalesOrderStatus::COMPLETED . '))'));
                            }
                        } else {
                            if ($filter_tracking_number === 1) {
                                $q->whereNotNull('t.TrackingNumber');
                            } else {
                                $q->whereNull('t.TrackingNumber');
                            }
                        }
                    }
                )
                ->when(!empty($co->get('filter_orderDate_from')), function ($q) use ($co) {
                    $q->where('c.create_time', '>=', $co->get('filter_orderDate_from'));
                })
                ->when(!empty($co->get('filter_orderDate_to')), function ($q) use ($co) {
                    $q->where('c.create_time', '<=', $co->get('filter_orderDate_to') . ' 23:59:59');
                })
                ->orderBy('c.id', 'desc');
    }

    /**
     * 获取纯物流销售订单总数
     * @param int $customer_id
     * @param array $data
     * @return int
     */
    public function getSalesOrderTotal(int $customer_id, array $data = []): int
    {
        return $this->querySalesOrder(...func_get_args())->distinct()->count('c.id');
    }

    /**
     * 获取纯物流销售订单列表
     * @param int $customer_id
     * @param array $data
     * @return array
     */
    public function getSalesOrderList(int $customer_id, array $data = []): array
    {
        $ret = $this->querySalesOrder(...func_get_args());
        if (isset($data['page']) && isset($data['page_limit'])) {
            $ret->forPage(($data['page'] ?? 1), ($data['page_limit'] ?? 10));
        }
        return $ret->groupBy(['c.id'])
            ->get()
            ->toArray();
    }

    public function getCommonOrderStatus($order_id, $run_id)
    {
        return CustomerSalesOrder::query()
            ->where([
                'order_id' => $order_id,
                'run_id' => $run_id,
            ])->value('order_status');
    }

    public function onHoldSalesOrder($order_id, $country_id)
    {
        load()->language('account/customer_order_import');
        load()->model("account/customer_order");
        if ($country_id == JAPAN_COUNTRY_ID) {
            $json['msg'] = 'This order does not allow onHold!';
            return $json;
        }

        $orderInfo = CustomerSalesOrder::find($order_id);
        $order_status = $orderInfo->order_status;
        $order_code = $orderInfo->order_id;
        $can_on_hold = [CustomerSalesOrderStatus::BEING_PROCESSED, CustomerSalesOrderStatus::LTL_CHECK];
        if (!in_array($order_status, $can_on_hold)) {
            $json['msg'] = 'This order does not allow onHold!';
            return $json;
        }
        $order_status = CustomerSalesOrderStatus::getDescription($order_status);
        $process_code = 4;  //操作码 1:修改发货信息,2:修改SKU,3:取消订单
        $status = CommonOrderActionStatus::SUCCESS; //操作状态 1:操作中,2:成功,3:失败
        $run_id = time();
        //订单类型，暂不考虑重发单
        $order_type = 1;
        $create_time = date("Y-m-d H:i:s");
        $before_record = "Order_Id:" . $order_id . ", status:" . $order_status;
        $modified_record = "Order_Id:" . $order_id . ", status:On Hold";
        $record_data['process_code'] = $process_code;
        $record_data['status'] = $status;
        $record_data['run_id'] = $run_id;
        $record_data['before_record'] = $before_record;
        $record_data['modified_record'] = $modified_record;
        $record_data['header_id'] = $order_id;
        $record_data['order_id'] = $order_code;
        $record_data['order_type'] = $order_type;
        $record_data['remove_bind'] = 1;
        $record_data['create_time'] = $create_time;

        if ($order_status == 'Being Processed') {
            $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($order_id);
            $is_in_omd = $this->model_account_customer_order->checkOrderShouldInOmd($order_id);
            $europeExported = CustomerSalesOrderLine::query()
                ->whereNotNull('is_synchroed')
                ->where('header_id', $order_id)
                ->exists();
            if ($is_syncing || $is_in_omd || $europeExported) {
                $json['msg'] = 'Action cannot be completed at this time.This order is being packed.Please submit a request via Customer Service.';
                return $json;
            }
        }
        $this->onHoldOrderByOrderId($order_id);
        $this->saveSalesOrderModifyRecord($record_data);
        $json['msg'] = 'Order on hold successfully.';
        return $json;
    }


    /**
     * @param int $order_id
     * @param int $country_id
     * @return array
     * @throws
     */
    public function cancelSalesOrder($order_id, $country_id)
    {
        $json = [];
        $this->load->language('account/customer_order_import');
        $this->load->model("account/customer_order");
        $this->load->model('catalog/sales_order_product_lock');
        if ($country_id == JAPAN_COUNTRY_ID) {
            $json['msg'] = $this->language->get('error_can_cancel');
            return $json;
        }
        //检测订单状态
        $orderInfo = CustomerSalesOrder::find($order_id);
        $order_status = $orderInfo->order_status;
        $order_code = $orderInfo->order_id;
        // Being Processed 情况下需要和omd联动校验
        $can_cancel = [
            CustomerSalesOrderStatus::BEING_PROCESSED,
            CustomerSalesOrderStatus::ON_HOLD,
            CustomerSalesOrderStatus::LTL_CHECK,
            CustomerSalesOrderStatus::ABNORMAL_ORDER
        ];
        if (!in_array($order_status, $can_cancel)) {
            $json['msg'] = $this->language->get('error_is_syncing');
            return $json;
        }
        $omd_store_id = $this->model_account_customer_order->getOmdStoreId($order_id);
        $order_status = CustomerSalesOrderStatus::getDescription($order_status);
        $process_code = CommonOrderProcessCode::CANCEL_ORDER;  //操作码 1:修改发货信息,2:修改SKU,3:取消订单
        $status = CommonOrderActionStatus::SUCCESS; //操作状态 1:操作中,2:成功,3:失败
        $run_id = time();
        //订单类型，暂不考虑重发单
        $order_type = 1;
        $create_time = date("Y-m-d H:i:s");
        $before_record = "Order_Id:" . $order_id . ", status:" . $order_status;
        $modified_record = "Order_Id:" . $order_id . ", status:Cancelled";

        $post_data['uuid'] = self::OMD_ORDER_CANCEL_UUID;
        $post_data['runId'] = $run_id;
        $post_data['orderId'] = $order_code;
        $post_data['storeId'] = $omd_store_id;
        $param['apiKey'] = OMD_POST_API_KEY;
        $param['postValue'] = json_encode($post_data);

        $record_data['process_code'] = $process_code;
        $record_data['status'] = $status;
        $record_data['run_id'] = $run_id;
        $record_data['before_record'] = $before_record;
        $record_data['modified_record'] = $modified_record;
        $record_data['header_id'] = $order_id;
        $record_data['order_id'] = $order_code;
        $record_data['order_type'] = $order_type;
        $record_data['remove_bind'] = 1;
        $record_data['create_time'] = $create_time;
        //$record_data['cancel_reason']  = $data['reason'];
        if ($order_status == 'Being Processed') {
            // 增加欧洲bp的逻辑
            $europeExported = false;
            if (in_array($country_id,EUROPE_COUNTRY_ID)) {
                $europeExported = CustomerSalesOrderLine::query()
                    ->whereNotNull('is_synchroed')
                    ->where('header_id', $order_id)
                    ->exists();
                if ($europeExported) {
                    $json['msg'] = $this->language->get('error_is_syncing');
                    return $json;
                } else {
                    return $this->cancelNotSynchronizedOrder($order_id, $record_data);
                }
            }

            //检验line明细状态   公用的验证，提取出来(omd和onsite)
            $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($order_id);
            if ($is_syncing) {
                $json['msg'] = $this->language->get('error_is_syncing');
                return $json;
            }
            //分组为giga onsite的seller,omd和onsite互斥
            if (customer()->getAccountType() == CustomerAccountingType::GIGA_ONSIDE) {
                $isInOnsite = $this->model_account_customer_order->checkOrderShouldInGigaOnsite($order_id);
                if ($isInOnsite) {
                    ob_end_clean();
                    //保存修改记录
                    $log_id = $this->saveSalesOrderModifyRecord($record_data);
                    $gigaResult = app(GigaOnsiteHelper::class)->cancelOrder($order_code,$run_id);
                    if ($gigaResult['code'] == 1) {
                        $json['msg'] = $this->language->get('text_cancel_seller_wait');
                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $fail_reason = $this->language->get('text_cancel_failed');
                        $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                        $json['msg'] = $this->language->get('text_cancel_failed');
                    }
                    //$json['msg'] = $this->language->get('text_cancel_failed');
                    return $json;
                } else {
                    // 更改订单状态
                    // 回退锁定库存
                    // 记录log
                    return $this->cancelNotSynchronizedOrder($order_id, $record_data);
                }
            }

            $is_in_omd = $this->model_account_customer_order->checkOrderShouldInOmd($order_id);
            if ($is_in_omd) {
                ob_end_clean();
                //保存修改记录
                $log_id = $this->saveSalesOrderModifyRecord($record_data);
                //取消状态根据回调来
                $response = $this->sendCurl(OMD_POST_URL, $param);
                if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                    $json['msg'] = $this->language->get('text_cancel_seller_wait');
                } else {
                    $new_status = CommonOrderActionStatus::FAILED;
                    $fail_reason = $this->language->get('text_cancel_failed');
                    $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                    $json['msg'] = $this->language->get('text_cancel_failed');
                }
                //$json['msg'] = $this->language->get('text_cancel_failed');
                return $json;
            } else {
                // 更改订单状态
                // 回退锁定库存
                // 记录log
                return $this->cancelNotSynchronizedOrder($order_id, $record_data);
            }

        } else {
            // 更改订单状态
            // 记录log
            $lockFlag = $this->judgeIsProductLock($order_id);
            return $this->cancelNotSynchronizedOrder($order_id, $record_data,$lockFlag);
        }


    }

    private function cancelNotSynchronizedOrder($order_id, $record_data,$lockFlag = true): array
    {
        load()->language('account/customer_order_import');
        load()->model('catalog/sales_order_product_lock');
        $con = $this->orm->getConnection();
        try {
            $con->beginTransaction();
            $this->cancelOrderByOrderId($order_id);
            if($lockFlag){
                $this->model_catalog_sales_order_product_lock->TailSalesOrderOut($order_id, 2);
            }
            $this->saveSalesOrderModifyRecord($record_data);
            $json['msg'] = $this->language->get('text_cancel_success');
            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();
            Logger::salesorder($e, 'error');
            $json['msg'] = $e->getMessage();
        }

        return $json;
    }

    public function judgeIsProductLock($order_id)
    {
        return CustomerSalesOrderLine::query()->alias('l')
            ->leftJoin('oc_product_lock as opl',function ($join) use($order_id){
                return $join->on('opl.agreement_id','=','l.id')->where('type_id',5);
            })
            ->where('l.header_id',$order_id)
            ->whereNotNull('opl.id')
            ->exists();

    }


    public function onHoldOrderByOrderId($order_id)
    {
        DB::table('tb_sys_customer_sales_order as cso')
            ->where('cso.id', $order_id)
            ->update(['order_status' => CustomerSalesOrderStatus::ON_HOLD]);
        DB::table('tb_sys_customer_sales_order_line as csol')
            ->where('header_id', $order_id)
            ->update(['item_status' => CustomerSalesOrderLineItemStatus::ON_HOLD]);
    }

    public function cancelOrderByOrderId($order_id)
    {
        DB::table('tb_sys_customer_sales_order as cso')
            ->where('cso.id', $order_id)
            ->update(['order_status' => CustomerSalesOrderStatus::CANCELED]);
        DB::table('tb_sys_customer_sales_order_line as csol')
            ->where('csol.header_id', $order_id)
            ->update(['item_status' => CustomerSalesOrderLineItemStatus::CANCELED]);
    }

    public function saveSalesOrderModifyRecord($data)
    {
        $data['update_time'] = date('Y-m-d H:i:s');
        return DB::table('tb_sys_customer_order_modify_log')->insertGetId($data);

    }


    public function sendCurl($url, $post_data)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));
        $response_data = curl_exec($curl);
        curl_close($curl);

        return $response_data;
    }


    /**
     * 验证上传的销售单是不是LTL Check状态
     * @param int $buyer_id
     * @param int|string $run_id
     * @return bool
     */
    public function verifySalesOrderIsLTLCheck($buyer_id, $run_id)
    {
        return CustomerSalesOrder::query()
            ->where([
                ['buyer_id', '=', $buyer_id],
                ['run_id', '=', $run_id],
                ['order_status', '=', CustomerSalesOrderStatus::LTL_CHECK]
            ])->exists();
    }
}
