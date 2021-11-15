<?php

namespace Catalog\model\account\sales_order;

use App\Components\Storage\StorageCloud;
use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Country\CountryCode;
use App\Enums\ModifyLog\CommonOrderActionStatus;
use App\Enums\Common\YesNoEnum;
use App\Enums\ModifyLog\CommonOrderProcessCode;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Pay\PayCode;
use App\Enums\Product\ProductType;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\JapanSalesOrder;
use App\Helper\AddressHelper;
use App\Helper\StringHelper;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Product\Product;
use App\Enums\SalesOrder\JoyBuyOrderStatus;
use App\Logging\Logger;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\Freight\InternationalOrderConfig;
use App\Models\Track\CountryState;
use App\Repositories\Order\CountryStateRepository;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Repositories\SalesOrder\Validate\salesOrderSkuValidate;
use App\Repositories\Setup\SetupRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\SalesOrder\CancelOrder\DropshipCancelOrderService;
use App\Services\Stock\BuyerStockService;
use Carbon\Carbon;
use Exception;
use Framework\App;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Database\Capsule\Manager as DB;
use App\Repositories\Customer\CustomerRepository;
use App\Enums\Customer\CustomerAccountingType;
use App\Helper\GigaOnsiteHelper;
use Model;
use ModelAccountCustomerOrder;
use ModelAccountCustomerOrderImport;
use ModelAccountOrder;
use ModelCatalogProduct;
use ModelCatalogSalesOrderProductLock;
use ModelCommonProduct;
use ModelCustomerPartnerDelicacyManagement;
use ModelExtensionModuleEuropeFreight;
use ModelMessageMessage;
use ModelToolImage;

/**
 * Class SalesOrderManagement
 *
 * @property ModelToolImage $model_tool_image
 * @property ModelAccountOrder $model_account_order
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelAccountCustomerOrderImport $model_account_customer_order_import
 * @property ModelAccountCustomerOrder $model_account_customer_order
 * @property ModelCommonProduct $model_common_product
 * @property ModelCatalogSalesOrderProductLock $model_catalog_sales_order_product_lock
 * @property ModelMessageMessage $model_message_message
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelExtensionModuleEuropeFreight $model_extension_module_europe_freight
 * @method cancelOrder($order_id, $country_id)
 */
class SalesOrderManagement extends Model
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
    const ORDER_ABNORMAL_ORDER = 256;
    const OMD_ORDER_CANCEL_UUID = 'c1996d6f-30df-46dc-b8ba-68a28efb83d7';
    const OMD_ORDER_ADDRESS_UUID = 'c9cedfd2-a209-4ece-be77-fb3915bdca0c';
    const BRITAIN_COUNTRY_ID = 222;
    const GERMANY_COUNTRY_ID = 81;
    const BRITAIN_ALIAS_NAME = ['UK', 'GB'];
    const PRODUCT_TYPE_FREIGHT = 3;
    protected $error = [
        'The oversize order needs to be confirmed again, please deal with it in time. ', //LTL
        'You do not have permission to purchase this product, please contact Seller. ',
        'This product does not exist in the Marketplace and cannot be purchased. ',
        'Address flagged as incorrect, please return to the list  to modify the address. ',//地址有误：
        'This product is temporarily out of stock and cannot be purchased. ', //全平台无库存：
        'The oversize order needs to be confirmed again, please deal with it in time. Close the current page the popover and query the LTL Check status. ',
        'This item is not available for purchase. ',
        'This order may only be shipped within its country of origin. ',
        'An auto-generated fulfillment quote estimate is currently not available for the country selected. Please contact Customer Service for a fulfillment quote after successfully importing the sales order.',
        'The shipping address of this Sales Order is beyond the range of countries/regions covered by the VAT exemption policy. For the countries/regions covered, please contact Customer Service for details.',
    ];


    /**
     * [getUploadHistory description]
     * @param int $customer_id
     * @return array
     */
    public function getUploadHistory($customer_id)
    {
        return DB::table(DB_PREFIX . 'customer_order_file')
            ->where([
                'customer_id' => $customer_id,
            ])
            ->limit(5)
            ->orderBy('create_time', 'desc')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
    }

    /**
     * [getSuccessfullyUploadHistoryTotal description]
     * @param array $param
     * @param int $customer_id
     * @return int
     */
    public function getSuccessfullyUploadHistoryTotal($param, $customer_id)
    {
        $map = [
            ['customer_id', '=', $customer_id],
            //['handle_status' ,'=',1] ,
        ];
        if (isset($param['filter_orderDate_from'])) {
            $timeList[] = $param['filter_orderDate_from'] . ' 00:00:00';
        } else {
            //这里可以写为最初成功时间
            $timeList[] = date('Y-m-d 00:00:00', 0);
        }
        if (isset($param['filter_orderDate_to'])) {
            $timeList[] = $param['filter_orderDate_to'] . ' 23:59:59';
        } else {
            $timeList[] = date('Y-m-d 23:59:59', time());
        }
        return DB::table(DB_PREFIX . 'customer_order_file')
            ->where($map)
            ->whereBetween('create_time', $timeList)
            ->count();

    }

    /**
     * [getSuccessfullyUploadHistory description]
     * @param array $param
     * @param int $customer_id
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getSuccessfullyUploadHistory($param, $customer_id, $page, $perPage = 2)
    {
        $mapHistory = [
            ['customer_id', '=', $customer_id],
        ];
        if (isset($param['filter_orderDate_from'])) {
            //默认当天
            $timeList[] = $param['filter_orderDate_from'] . ' 00:00:00';
        } else {
            //这里可以写为最初成功时间
            $timeList[] = date('Y-m-d 00:00:00', 0);
        }
        if (isset($param['filter_orderDate_to'])) {
            $timeList[] = $param['filter_orderDate_to'] . ' 23:59:59';
        } else {
            $timeList[] = date('Y-m-d 23:59:59', time());
        }
        return DB::table('oc_customer_order_file')
            ->where($mapHistory)
            ->whereBetween('create_time', $timeList)
            ->forPage($page, $perPage)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
    }

    /**
     * [verifyUploadFile description]
     * @param array $files
     * @param int $upload_type
     * @param int $method 1 shopify 2 other
     * @return string
     */
    public function verifyUploadFile($files, $upload_type = 0, int $method)
    {
        // 检查文件名以及文件类型
        $json['error'] = '';
        if (isset($files['name'])) {
            $fileName = $files['name'];
            $fileType = strrchr($fileName, '.');
            //默认先是一件代发的验证xls 和 xlsx
            if ($method == 2) {
                if (!in_array($fileType, ['.xls', '.xlsx']) || $upload_type != 0) {
                    $json['error'] = $this->language->get('error_filetype');
                }
            }
            if ($method == 1) {
                if (!in_array($fileType, ['.csv']) || $upload_type != 0) {
                    $json['error'] = $this->language->get('error_file_type_Shopify');
                }
            }

            if ($files['error'] != UPLOAD_ERR_OK) {
                $json['error'] = $this->language->get('error_upload_' . $files['error']);
            }
        } else {
            $json['error'] = $this->language->get('error_upload');
        }
        // 检查文件短期之内是否重复上传(首次提交文件后5s之内不能提交文件)
        //$files = glob(DIR_UPLOAD . '*.tmp');
        //foreach ($files as $file) {
        //    if (is_file($file) && (filectime($file) < (time() - 5))) {
        //        unlink($file);
        //    }
        //}
        return $json['error'];
    }

    /**
     * [saveUploadFile description]
     * @param $files
     * @param int $customer_id
     * @param int $upload_type
     * @return array
     */
    public function saveUploadFile($files, $customer_id, $upload_type)
    {
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $fileInfo */
        $fileInfo = $this->request->file('file');
        // 获取登录用户信息
        $import_mode = $upload_type;

        // 上传订单文件，以用户ID进行分类
        $fileName = $fileInfo->getClientOriginalName();
        $fileType = $fileInfo->getClientOriginalExtension();
        // 复制上传的文件到orderCSV路径下
        $run_id = msectime();
        $realFileName = str_replace('.' . $fileType, '_', $fileName) . $run_id . '.' . $fileType;
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
            "create_time" => Carbon::now(),
        ];
        $file_id = DB::table(DB_PREFIX . 'customer_order_file')->insertGetId($file_data);
        //返回上传文件的内容信息
        return [
            'run_id' => $run_id,
            'import_mode' => $import_mode,
            'file_id' => $file_id,
        ];
    }

    /**
     * [getUploadFileInfo description]
     * @param array $get
     * @return array
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
     * [dealWithFileData description] save order 核心数据
     * @param $data
     * @param array $get
     * @param int $country_id
     * @param int $customer_id
     * @return mixed|string
     * @throws Exception
     */
    public function dealWithFileData($data, $get, $country_id, $customer_id)
    {
        $run_id = $get['run_id'];
        $import_mode = $get['import_mode'];
        //验证首行和第二行数据是否正确
        $verify_ret = $this->verifyFileDataFirstLine($data, $import_mode, $country_id);
        if ($verify_ret) {
            return $verify_ret;
        }
        //验证订单是否含有重复的order_id 和 sku 组合
        $unique_ret = $this->verifyFileDataOrderUnique($data, $import_mode);
        if ($unique_ret['error']) {
            return $unique_ret['error'];
        }
        //验证订单插入的数据是否是有效的
        $column_ret = $this->saveFileData($unique_ret['data'], $import_mode, $run_id, $country_id, $customer_id);
        if ($column_ret !== true) {
            return $column_ret;
        }
        return true;


    }


    /**
     * 处理shopify上传处理
     * @param array $data
     * @param array $get
     * @param int $country_id
     * @param int $customer_id
     * @return array|bool|string
     */
    public function dealWithFileShopifyData(array $data, array $get, int $country_id, int $customer_id)
    {
        $run_id = $get['run_id'];
        $import_mode = $get['import_mode'];
        //验证首行
        $verify_ret = $this->verifyFileShopifyDataFirstLine($data, $import_mode);
        if ($verify_ret) {
            return $verify_ret;
        }


        //验证订单是否含有重复的order_id 和 sku 组合
        $unique_ret = $this->verifyFileDataShopifyOrderUnique($data['values'], $import_mode);
        if ($unique_ret['error']) {
            return $unique_ret['error'];
        }

        //验证订单插入的数据是否是有效的
        $column_ret = $this->verifyFileShopifyDataColumn($unique_ret['data'], $import_mode, $run_id, $country_id, $customer_id);
        if ($column_ret !== true) {
            return $column_ret;
        }
        return true;
    }

    /**
     * shopfiy上传首行校验
     * @param array $data
     * @param int $import_mode
     * @return string
     */
    public function verifyFileShopifyDataFirstLine(array $data, int $import_mode)
    {
        $error = '';
        if ($import_mode == 0) {
            $excel_header = [
                'Name',                            // 编号
                'Email',                           // 收件人邮箱
                'Financial Status',                 // 支付状态
                'Paid at',                          // 支付时间
                'Fulfillment Status',               // 发货状态
                'Fulfilled at',                     // 发货时间
                'Accepts Marketing',                // 同意邮件订阅
                'Currency',                         // 货币
                'Subtotal',                         // 产品售价（优惠后）
                'Shipping',                         // 运费
                'Taxes',                            // 税费
                'Total',                            // 总支付金额
                'Discount Code',                    // 优惠码
                'Discount Amount',                  // 优惠金额
                'Shipping Method',                  // 运输方式
                'Created at',                       // 下单时间
                'Lineitem quantity',               // 同一个SKU的购买数量
                'Lineitem name',                    // 产品标题
                'Lineitem price',                   // 产品标价（优惠前）
                'Lineitem compare at price',        // 价格对比
                'Lineitem sku',                    // 产品SKU
                'Lineitem requires shipping',       // 是否与要运输
                'Lineitem taxable',                 // 是否需要交税
                'Lineitem fulfillment status',      // 发货状态
                'Billing Name',                    // 账单上客户姓名
                'Billing Street',                  // 账单上街道
                'Billing Address1',                 // 账单上具体地址1
                'Billing Address2',                 // 账单上具体地址2
                'Billing Company',                  // 账单上公司
                'Billing City',                     // 账单上城市
                'Billing Zip',                      // 账单上区号
                'Billing Province',                 // 账单上州
                'Billing Country',                  // 账单上国家
                'Billing Phone',                    // 账单上联系方式
                'Shipping Name',                   // 收件人姓名
                'Shipping Street',                 // 收件人街道
                'Shipping Address1',                // 收件人具体地址1
                'Shipping Address2',                // 收件人具体地址2
                'Shipping Company',                 // 收件人公司
                'Shipping City',                   // 收件人城市
                'Shipping Zip',                    // 收件人区号
                'Shipping Province',               // 收件人州
                'Shipping Country',                // 收件人国家
                'Shipping Phone',                  // 收件人联系方式
                'Notes',                            // 留言
                'Note Attributes',                  // 留言属性
                'Cancelled at',                     // 取消时间
                'Payment Method',                   // 付款方式
                'Payment Reference',                // 付款凭据
                'Refunded Amount',                  // 退款金额
                'Vendor',                           // 供应商
                'Id',                               // 订单号（后台无法看到）
                'Tags',                             // 标签
                'Risk Level',                       // 客户欺诈分析
                'Source',                           // 来源
                'Lineitem discount',                // 未知
                'Tax 1 Name',                       // 城市税名称
                'Tax 1 Value',                      // 金额
                'Tax 2 Name',                       // 州税名称
                'Tax 2 Value',                      // 金额
                'Tax 3 Name',                       //
                'Tax 3 Value',                      //
                'Tax 4 Name',                       //
                'Tax 4 Value',                      //
                'Tax 5 Name',                       //
                'Tax 5 Value',                      //
                'Phone',                            // 电话
                'Receipt Number',                    // 收据号
            ];
            //表头列最后面会有空值
            $data['keys']=array_filter($data['keys']);
            //去除标题前面的*（必填标记）
            foreach ($data['keys'] as $key => $val) {
                $data['keys'][$key] = trim($data['keys'][$key], '*');
                if ($data['keys'][$key] == 'Duties') {
                    array_push($excel_header, 'Duties');
                }
                if ($data['keys'][$key] == 'Billing Province Name') {
                    array_push($excel_header, 'Billing Province Name');
                }
                if ($data['keys'][$key] == 'Shipping Province Name') {
                    array_push($excel_header, 'Shipping Province Name');
                }
            }
            //表头重复
            $excel_header = array_unique($excel_header);

            // 验证第一行数据与给出数据是否相等
            if (!isset($data['keys']) || $data['keys'] != $excel_header) {
                $error = $this->language->get('error_file_content');
            }
            // 数据行数等于1行，证明为空数据，需要进行处理
            if (count($data['values']) < 1) {
                $error = $this->language->get('error_file_empty');
            }
            return $error;
        }
        return $error;
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

    /**
     * @param $run_id
     * @param $customer_id
     * @param array $order_status
     * @return array
     * @see CustomerSalesOrderStatus::LTL_CHECK
     */
    public function getOriginalSalesOrder($run_id, $customer_id, $order_status = [64, 256, 2, 4, 16])
    {
        $map = [
            'run_id' => $run_id,
            'buyer_id' => $customer_id,

        ];
        return DB::table('tb_sys_customer_sales_order')
            ->where($map)
            ->whereIn('order_status', $order_status)
            ->get()
            ->map(function ($v) {
                $v->ship_address1 = app('db-aes')->decrypt($v->ship_address1);
                $v->ship_address2 = app('db-aes')->decrypt($v->ship_address2);
                $v->ship_name = app('db-aes')->decrypt($v->ship_name);
                $v->ship_city = app('db-aes')->decrypt($v->ship_city);
                $v->ship_phone = app('db-aes')->decrypt($v->ship_phone);
                $v->email = app('db-aes')->decrypt($v->email);
                $v->bill_address = app('db-aes')->decrypt($v->bill_address);
                $v->bill_name = app('db-aes')->decrypt($v->bill_name);
                $v->bill_city = app('db-aes')->decrypt($v->bill_city);
                return (array)$v;
            })
            ->toArray();
    }

    /**
     * [updateSalesOrderLTL description]
     * @param string $run_id
     * @param int $customer_id
     */
    public function updateSalesOrderLTL($run_id, $customer_id)
    {
        //初始的时候order_status 为 1
        $countryId = Customer()->getCountryId() ?? AMERICAN_COUNTRY_ID;
        $order_id_str = DB::table('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
            ->leftJoin(DB_PREFIX . 'product as p', 'p.sku', '=', 'l.item_code')
            ->leftJoin(DB_PREFIX . 'customerpartner_to_product as ctp', 'ctp.product_id',  'p.product_id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id',  'ctp.customer_id')
            ->leftJoin(DB_PREFIX . 'product_to_tag as ptt', 'ptt.product_id', '=', 'p.product_id')
            ->where([
                'o.run_id' => $run_id,
                'o.buyer_id' => $customer_id,
                'ptt.tag_id' => 1,
                'c.country_id' => $countryId,
            ])
            ->selectRaw('group_concat(distinct l.header_id) as order_id_str')
            ->first();
        if ($order_id_str) {
            DB::table('tb_sys_customer_sales_order')
                ->whereIn('id', explode(',', $order_id_str->order_id_str))
                ->update([
                    'order_status' => CustomerSalesOrderStatus::LTL_CHECK,
                    'update_time' => date('Y-m-d H:i:s'),
                    'update_user_name' => $customer_id,
                ]);
        }
    }

    /**
     * [judgeIsAllLtlSku description]
     * @param int|string $run_id
     * @param int $customer_id
     * @return array
     */
    public function judgeIsAllLtlSku($run_id, $customer_id)
    {
        $countryId = Customer()->getCountryId() ?? AMERICAN_COUNTRY_ID;
        $order_id_ltl = DB::table('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', 'l.header_id')
            ->leftJoin(DB_PREFIX . 'product as p', 'p.sku',  'l.item_code')
            ->leftJoin(DB_PREFIX . 'customerpartner_to_product as ctp', 'ctp.product_id',  'p.product_id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id',  'ctp.customer_id')
            ->leftJoin(DB_PREFIX . 'product_to_tag as ptt', 'ptt.product_id',  'p.product_id')
            ->where([
                'o.run_id' => $run_id,
                'o.buyer_id' => $customer_id,
                'ptt.tag_id' => 1,
                'c.country_id'=> $countryId,
            ])
            ->groupBy(['l.id'])
            ->select('l.id', 'l.header_id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $order_id_all = DB::table('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
            ->where([
                'o.run_id' => $run_id,
                'o.buyer_id' => $customer_id,
            ])
            ->groupBy(['l.id'])
            ->select('l.id', 'l.header_id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if (count($order_id_ltl) == count($order_id_all) && count($order_id_all)) {
            // 所以明细都是ltl
            // 返回所有的order_id
            $ret['is_all_ltl'] = 1;
            return $ret;
        }
        $ret['is_all_ltl'] = 0;
        return $ret;
    }

    public function updateSingleOriginalSalesOrderStatus($id, $pre_status = 256, $type = 1, $customer_id = 0)
    {
        // 订单之前是LTL已经被check过了，之后修改SKU还是LTL的产品，还要需要再变回LTL check状态
        // $ltl_process_status = DB::table('tb_sys_customer_sales_order')->where('id', $id)->value('ltl_process_status');
        $list = DB::table('tb_sys_customer_sales_order_line')
            ->where('header_id', $id)
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $status = CustomerSalesOrderStatus::TO_BE_PAID;
        //上门取货Buyer不需要考虑LTL_CHECK
        if( customer()->isPartner() || !customer()->isCollectionFromDomicile() ) {
            foreach ($list as $key => $value) {
                $product_id = $this->getFirstProductId($value['item_code'], $customer_id);
                $exists = DB::table(DB_PREFIX . 'product_to_tag')
                    ->where([
                        'tag_id' => 1,
                        'product_id' => $product_id,
                    ])
                    ->exists();
                if ($exists) {
                    $status = CustomerSalesOrderStatus::LTL_CHECK;
                    break;
                }
            }
            // 存在ltl 1,或者是64
        }
        return $this->salesOrderStatusUpdate($pre_status, $status, $id, $type, $customer_id);
    }

    public function getReleaseOrderInfo($order_id)
    {
        $order_status = DB::table('tb_sys_customer_sales_order')
            ->where('id', $order_id)
            ->value('order_status');

        $type = 3;

        return [
            'type' => $type,
            'order_status' => $order_status,
        ];
    }

    public function getBatchReleaseOrderInfo($order_id)
    {
        $order_status = DB::table('tb_sys_customer_sales_order')
            ->whereIn('id', $order_id)
            ->select('id', 'order_status', 'ltl_process_status')
            ->get()
            ->keyBy('id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();

        $type = 3;

        return [
            'type' => $type,
            'order_status' => $order_status,
        ];
    }

    public function releaseOrder($order_id, $pre_status = 256, $type = 1, $customer_id = 0)
    {
        // 1 初次导单 需要验证 以及库存是否LTL
        // 2 sku更改 需要验证  以及库存是否LTL
        // 3 on hold 订单release
        // 4 abnormal order 订单release
        if ($type == 1 || $type == 2 || $type == 3 || $type == 4) {
            // $pre_status 4
            // on hold 前 256 直接更改
            // on hold 前 64 库存不够256 库存够 LTL 验证通过 2 LTL验证没通过64
            return $this->updateSingleOriginalSalesOrderStatus($order_id, $pre_status, $type, $customer_id);
        }
        return false;
    }

    public function batchReleaseOrder($order_info, $customer_id = 0)
    {
        // 1 初次导单 需要验证 以及库存是否LTL
        // 2 sku更改 需要验证  以及库存是否LTL
        // 3 on hold 订单release
        // 4 abnormal order 订单release
        if ($order_info['type'] == 1 || $order_info['type'] == 2 || $order_info['type'] == 3 || $order_info['type'] == 4) {
            // $pre_status 4
            // on hold 前 256 直接更改
            // on hold 前 64 库存不够256 库存够 LTL 验证通过 2 LTL验证没通过64
            $this->updateBatchOriginalSalesOrderStatus($order_info, $customer_id);
        }
    }

    public function updateBatchOriginalSalesOrderStatus($order_info, $customer_id)
    {
        // 检验库存是否足够
        // 检验product_id 是否存在 是否是seller产品
        // 检验是否是LTL
        // 暂时release order 改成256
        //$status = 2;
        $isSeller = Customer()->isPartner();
        $isCollectionFromDomicile = Customer()->isCollectionFromDomicile();
        $item_code = [];
        foreach ($order_info['order_status'] as $ks => $vs) {
            $status = CustomerSalesOrderStatus::TO_BE_PAID;
            //上门取货Buyer不需要考虑LTL_CHECK
            if ($isSeller || !$isCollectionFromDomicile) {
                $list = DB::table('tb_sys_customer_sales_order_line')
                    ->where('header_id', $ks)
                    ->get()
                    ->map(function ($v) {
                        return (array)$v;
                    })
                    ->toArray();
                foreach ($list as $key => $value) {
                    if (isset($item_code[$value['item_code']])) {
                        $exists = $item_code[$value['item_code']]['exists'];
                        $product_id = $item_code[$value['item_code']]['product_id'];
                    } else {
                        $product_id = $this->getFirstProductId($value['item_code'], $customer_id);
                        $exists = DB::table(DB_PREFIX . 'product_to_tag')
                            ->where([
                                'tag_id' => 1,
                                'product_id' => $product_id,
                            ])
                            ->exists();
                        $item_code[$value['item_code']]['product_id'] = $product_id;
                        $item_code[$value['item_code']]['exists'] = $exists;
                    }

                    if ($exists && !$order_info['order_status'][$value['header_id']]['ltl_process_status']) {
                        $status = CustomerSalesOrderStatus::LTL_CHECK;
                        break;
                    }
                }
                // 存在ltl 1,或者是64
            }
            $this->salesOrderStatusUpdate($vs['order_status'], $status, $ks, $order_info['type']);
        }


    }

    /**
     * [updateSalesOrderLineSku description]
     * @param int $headerId
     * @param int $line_id
     * @param int $product_id
     * @return array
     */
    public function updateSalesOrderLineSku($headerId, $line_id, $product_id)
    {
        $this->load->language('account/customer_order_import');
        $pre_status = DB::table('tb_sys_customer_sales_order')->where('id', $headerId)->value('order_status');
        $itemCode = DB::table(DB_PREFIX . 'product')->where('product_id', $product_id)->value('sku');
        $currentOrderInfo = $this->model_account_customer_order->getCurrentOrderInfo($line_id);
        $orderInfo = current($currentOrderInfo);
        $runId = time();
        $orderId = $orderInfo['order_id'];
        $lineItemNum = $orderInfo['line_item_number'];
        $oldSku = $orderInfo['item_code'];
        $omdOrderSkuUuid = "c87f0069-e386-486e-a07d-f78e0e962a7c";
        if (in_array($pre_status, [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::LTL_CHECK])) {
            //region 订单是否能修改基础判断
            //是否在同步中
            $isSyncing = $this->model_account_customer_order->checkOrderIsSyncing($headerId);
            if ($isSyncing) {
                return [false, $this->language->get('error_is_syncing')];
            }
            $omdStoreId = $this->model_account_customer_order->getOmdStoreId($headerId);
            if (!isset($omdStoreId)) {
                return [false, $this->language->get('error_invalid_param')];
            }
            //endregion
            //判断订单是否是OMD
            $isInOmd = $this->model_account_customer_order->checkOrderShouldInOmd($headerId);
            //日志数据
            $beforeRecord = "Order_Id:{$headerId}, Line_item_number:{$lineItemNum}, ItemCode:{$oldSku}";
            $modifiedRecord = "Order_Id:{$headerId}, Line_item_number:{$lineItemNum}, ItemCode:{$itemCode}";
            $recordData = [
                'process_code' => CommonOrderProcessCode::CHANGE_SKU,
                'status' => CommonOrderActionStatus::PENDING,
                'run_id' => $runId,
                'before_record' => $beforeRecord,
                'modified_record' => $modifiedRecord,
                'header_id' => $headerId,
                'order_id' => $orderInfo['order_id'],
                'line_id' => $orderInfo['line_id'],
                'order_type' => 1,
                'remove_bind' => 0,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            if ($isInOmd) {
                $logId = $this->model_account_customer_order->saveSalesOrderModifyRecord($recordData);
                //拼装请求OMD数据
                $postData = [
                    'uuid' => $omdOrderSkuUuid,
                    'runId' => $runId,
                    'storeId' => $omdStoreId,
                    'orderId' => $orderId,
                    'oldItemCode' => $oldSku,
                    'newItemCode' => $itemCode,
                    'qty' => $orderInfo['qty'],
                ];
                $postParam = [
                    'apiKey' => OMD_POST_API_KEY,
                    'postValue' => json_encode($postData)
                ];
                $response = $this->sendCurl(OMD_POST_URL, $postParam);
                if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                    return [true, $this->language->get('text_autobuyer_modify_sku_success')];
                } else {
                    $newStatus = CommonOrderActionStatus::FAILED;
                    $failReason = $this->language->get('error_response');
                    $this->model_account_customer_order->updateSalesOrderModifyLog($logId, $newStatus, $failReason);
                    return [false, $response];
                }
            } else {
                DB::table('tb_sys_customer_sales_order_line')
                    ->where('id', $line_id)
                    ->update([
                        'item_code' => $itemCode,
                    ]);
                $changeResult = $this->releaseOrder($headerId, $pre_status, 2);
                //region 写入日志
                if ($changeResult) {
                    $recordData['status'] = CommonOrderActionStatus::SUCCESS;
                    //保存修改记录
                    $this->model_account_customer_order->saveSalesOrderModifyRecord($recordData);
                    return [true, ''];
                } else {
                    $recordData['status'] = CommonOrderActionStatus::FAILED;
                    //保存修改记录
                    $this->model_account_customer_order->saveSalesOrderModifyRecord($recordData);
                    return [false, $this->language->get('text_change_sku_failed')];
                }
                //endregion
            }
        } else {
            return [false, $this->language->get('text_change_sku_failed')];
        }
    }

    /**
     * [salesOrderStatusUpdate description] 更新订单状态
     * @param int $pre_status
     * @param int $suf_status
     * @param int $order_id
     * @param int $type 1: 初次导单 256 => 2 ,2:更改sku 256 => 2 ,3 on hold 更改 ，release order 4 => 2
     * @param int $customer_id
     * @return bool
     */
    public function salesOrderStatusUpdate($pre_status, $suf_status, $order_id, $type, $customer_id = 0)
    {
        //记录log
        $this->log->write('order_id：' . $order_id . ',type_id:' . $type . ',pre_status:' . $pre_status . ',suf_status:' . $suf_status);
        switch ($type) {
            case 4:
            case 3:
            case 1:
                $update = [
                    'order_status' => $suf_status,
                    'update_time' => date('Y-m-d H:i:s'),
                    'update_user_name' => $customer_id,
                ];
                if ($pre_status == CustomerSalesOrderStatus::ON_HOLD && $suf_status == CustomerSalesOrderStatus::TO_BE_PAID) {
                    $update['to_be_paid_time'] = Carbon::now()->toDateTimeString();
                }
                DB::table('tb_sys_customer_sales_order')
                    ->where('id', $order_id)
                    ->update($update);
                DB::table('tb_sys_customer_sales_order_line')
                    ->where('header_id', $order_id)
                    ->update(['item_status' => CustomerSalesOrderLineItemStatus::PENDING]);
                break;
            case 2:
                // ltl变成to be paid或者是to be paid 变成ltl都要清空ltl_process_status和ltl_process_time
                $update = [
                    'order_status' => $suf_status,
                    'ltl_process_status' => null,
                    'ltl_process_time' => null,
                    'update_time' => date('Y-m-d H:i:s'),
                    'update_user_name' => $customer_id,
                ];
                if ($pre_status == CustomerSalesOrderStatus::ON_HOLD && $suf_status == CustomerSalesOrderStatus::TO_BE_PAID) {
                    $update['to_be_paid_time'] = Carbon::now()->toDateTimeString();
                }

                DB::table('tb_sys_customer_sales_order')
                    ->where('id', $order_id)
                    ->update($update);
                DB::table('tb_sys_customer_sales_order_line')
                    ->where('header_id', $order_id)
                    ->update(['item_status' => CustomerSalesOrderLineItemStatus::PENDING]);
            default:

        }
        return true;
    }

    /**
     * [verifyFileDataFirstLine description] 验证excel数据合法性
     * @param $data
     * @param int $import_mode
     * @param int $country_id
     * @return string
     */

    public function verifyFileDataFirstLine($data, $import_mode, $country_id = AMERICAN_COUNTRY_ID): string
    {
        $error = '';
        if ($import_mode == HomePickImportMode::IMPORT_MODE_NORMAL) {
            if ($country_id == AMERICAN_COUNTRY_ID) {
                $serviceOrFba = 'ShipToService';
            } else {
                $serviceOrFba = 'DeliveryToFBAWarehouse'; // 欧洲和日本 为本字段
            }

            $excel_header = [
                $country_id == AMERICAN_COUNTRY_ID ? 'ShipFrom' : 'SalesPlatform',   // 销售平台
                'OrderId',                          // 订单号
                'LineItemNumber',                   // 订单明细号
                'OrderDate',                        // 订单时间
                'BuyerBrand',                       // Buyer的品牌
                'BuyerPlatformSku',                 // Buyer平台Sku
                'B2BItemCode',                      // B2B平台商品Code
                'BuyerSkuDescription',              // Buyer商品描述
                'BuyerSkuCommercialValue',          // Buyer商品的商业价值/件
                'BuyerSkuLink',                     // Buyer商品的购买链接
                'ShipToQty',                        // 发货数量
                $serviceOrFba,                    // 发货物流服务（美国）| 是否FBA送仓（欧洲和日本）
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
            //去除标题前面的*（必填标记）
            foreach ($data[0] as $key => $val) {
                $data[0][$key] = trim($data[0][$key], '*');
            }
            // 验证第二行数据与给出数据是否相等
            if (!isset($data[0]) || $data[0] != $excel_header) {
                $error = $this->language->get('error_file_content');
            }
            // 数据行数等于2行，证明为空数据，需要进行处理
            if (count($data) == 2) {
                $error = $this->language->get('error_file_empty');
            }
            return $error;
        }
        return $error;


    }

    /**
     * [verifyFileDataOrderUnique description] 验证订单和sku 是否唯一
     * @param $data
     * @param int $import_mode
     * @return mixed
     */
    public function verifyFileDataOrderUnique($data, $import_mode): array
    {
        $order = [];
        $error = '';
        if ($import_mode == HomePickImportMode::IMPORT_MODE_NORMAL) {
            //excel 需要处理数据
            unset($data[1]);
            // 数组结构重组
            $data = $this->formatFileData($data);
            $lineTransferSku = [];
            foreach ($data as $key => &$value) {
                if (isset($value['*OrderId'])) {
                    $value['OrderId'] = $value['*OrderId'];
                }
                if (isset($value['*B2BItemCode'])) {
                    $value['*B2BItemCode'] = str_replace(' ', ' ', $value['*B2BItemCode']); // 替换非法字符为空格
                    $value['B2BItemCode'] = $value['*B2BItemCode'];
                    $lineTransferSku[$key + 3] = $value['*B2BItemCode'];
                    // 导单限制服务产品
                }
                $value['OrderId'] = get_need_string($value['OrderId'], ["'", '"', ' ']);
                $value['B2BItemCode'] = strtoupper(str_replace(' ', ' ', $value['B2BItemCode'])); // 替换非法字符为空格
                $order_sku_key = trim($value['OrderId']) . '_' . trim($value['B2BItemCode']);
                $order[$order_sku_key][] = $key + 3;
            }

            $skus = array_values($lineTransferSku);
            $verifyRet = app(salesOrderSkuValidate::class)->withSkus($skus)->validateSkus();
            if(!$verifyRet['code']){
                $error .= sprintf($verifyRet['msg'],array_search($verifyRet['errorSku'],$lineTransferSku));
            }
        }

        foreach ($order as $ks => $vs) {
            if (count($vs) > 1) {
                $error .= 'This file has the same order details. Number of lines:' . implode(',', $vs) . '<br/>';
            }
        }
        $ret['error'] = $error;
        $ret['data'] = $data;
        return $ret;
    }

    /**
     * 检验shopify 上传中 是否有name与sku的重复
     * @param array $data
     * @param int $import_mode
     * @return mixed
     */
    public function verifyFileDataShopifyOrderUnique(array $data, int $import_mode)
    {
        $order = [];
        $error = '';
        $lineTransferSku = [];
        if ($import_mode == 0) {
            //excel 需要处理数据
            foreach ($data as $key => &$value) {
                if (isset($value['*Name'])) {
                    $value['Name'] = $value['*Name'];
                }
                if (isset($value['*Lineitem sku'])) {
                    $value['Lineitem sku'] = $value['*Lineitem sku'];
                }
                $value['Name'] = get_need_string($value['Name'], ["'", '"', ' ']);
                $value['B2BItemCode'] = strtoupper($value['Lineitem sku']);
                $order_sku_key = trim($value['Name']) . '_' . trim($value['B2BItemCode']);
                $order[$order_sku_key][] = $key + 2;
                $lineTransferSku[$key + 2] = $value['B2BItemCode'];
            }
        }
        foreach ($order as $ks => $vs) {
            if (count($vs) > 1) {
                $error .= 'This file has the same order details. Number of lines:' . implode(',', $vs) . '<br/>';
            }
        }

        if($lineTransferSku){
            $skus = array_values($lineTransferSku);
            $verifyRet = app(salesOrderSkuValidate::class)->withSkus($skus)->validateSkus();
            if(!$verifyRet['code']){
                $error .= sprintf($verifyRet['msg'],array_search($verifyRet['errorSku'],$lineTransferSku));
            }
        }
        $ret['error'] = $error;
        $ret['data'] = $data;
        return $ret;
    }

    public function formatFileData($data)
    {
        $first_key = 0;
        $ret = [];
        foreach ($data as $key => $value) {
            if ($first_key != $key) {
                $ret[] = array_combine($data[$first_key], $value);
            }
        }
        return $ret;
    }

    /**
     * [verifyFileDataColumn description]
     * @param array $data
     * @param int $import_mode
     * @param int|string $run_id
     * @param int $country_id
     * @param int $customer_id
     * @return bool|string
     * @throws Exception
     */
    public function saveFileData($data, $import_mode, $run_id, $country_id, $customer_id)
    {
        if ($import_mode == HomePickImportMode::IMPORT_MODE_NORMAL) {
            $order_mode = CustomerSalesOrderMode::DROP_SHIPPING;
            //已存在的订单OrderId
            $existentOrderIdArray = [];
            $order_column_info = [];
            $verify_order = [];
            $orderArr = [];

            // 所有行相同的orderId 提取B2BItemCode 后面用于判断是否LTL
            $orderIdItemCode = [];
            foreach ($data as $key => $value) {
                if (isset($value['*OrderId']) && isset($value['*B2BItemCode'])) {
                    $orderIdItemCode[$value['*OrderId']][] = $value['*B2BItemCode'];
                }
            }

            foreach ($data as $key => $value) {
                // 美国国别SalesPlatform需要更改成ShipFrom
                if (isset($value['ShipFrom']) && $country_id == AMERICAN_COUNTRY_ID) {
                    $value['SalesPlatform'] = $value['ShipFrom'];
                }
                if (isset($value['*OrderId'])) {
                    $value['OrderId'] = $value['*OrderId'];
                }
                if (isset($value['*LineItemNumber'])) {
                    $value['LineItemNumber'] = $value['*LineItemNumber'];
                }
                if (!isset($value['B2BItemCode']) && isset($value['*B2BItemCode'])) {
                    $value['B2BItemCode'] = strtoupper($value['*B2BItemCode']);
                }
                if (isset($value['*ShipToQty'])) {
                    $value['ShipToQty'] = $value['*ShipToQty'];
                }
                if (isset($value['*ShipToName'])) {
                    $value['ShipToName'] = $value['*ShipToName'];
                }
                if (isset($value['*ShipToEmail'])) {
                    $value['ShipToEmail'] = $value['*ShipToEmail'];
                }
                if (isset($value['*ShipToPhone'])) {
                    $value['ShipToPhone'] = $value['*ShipToPhone'];
                }
                if (isset($value['*ShipToPostalCode'])) {
                    $value['ShipToPostalCode'] = $value['*ShipToPostalCode'];
                }
                if (isset($value['*ShipToAddressDetail'])) {
                    $value['ShipToAddressDetail'] = $value['*ShipToAddressDetail'];
                }
                if (isset($value['*ShipToCity'])) {
                    $value['ShipToCity'] = $value['*ShipToCity'];
                }
                if (isset($value['*ShipToState'])) {
                    $value['ShipToState'] = $value['*ShipToState'];
                }
                if (isset($value['*ShipToCountry'])) {
                    $value['ShipToCountry'] = $value['*ShipToCountry'];
                }
                $flag = true;
                foreach ($value as $k => $v) {
                    if ($v != '') {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    continue;
                }
                $column_ret = $this->verifyCommonOrderFileDataColumn($value, $key + 3, $country_id, $orderIdItemCode[$value['OrderId'] ?? ''] ?? []);
                //对于签收服务费，只有美国有签收服务费
                if ($country_id != AMERICAN_COUNTRY_ID) {
                    $value['ShipToService'] = '';
                } else {
                    $value['DeliveryToFBAWarehouse'] = '';
                    if (strtoupper(trim($value['ShipToService'])) == 'ASR') {
                        $value['ShipToService'] = 'ASR';
                    }
                }

                if (DISABLE_SHIP_TO_SERVICE) {
                    $value['ShipToService'] = '';
                }

                if ($column_ret !== true) {
                    return $column_ret;
                }
                // 校验是否已经是插入的订单了
                $checkResult = $this->judgeCommonOrderIsExist(trim($value['OrderId']), $customer_id);
                if ($checkResult) {
                    $existentOrderIdArray[] = trim($value['OrderId']);
                }
                //$judge_column = ['ShipToPostalCode', 'ShipToAddressDetail', 'ShipToCity', 'ShipToState'];
                //foreach ($judge_column as $ks => $vs) {
                //    $judge_ret = $this->dealErrorCode($value[$vs]);
                //    if ($judge_ret != false) {
                //        $order_column_info[trim($value['OrderId'])] = true;
                //    }
                //}
                if ($country_id == AMERICAN_COUNTRY_ID) { // 美国 一件代发 国别转大写
                    $value['ShipToCountry'] = strtoupper($value['ShipToCountry']);
                    // 29569 回退
//                    $value['ShipToCountry'] = strtoupper(trim($value['ShipToCountry']));
//
//                    $stateArr = app(CountryStateRepository::class)->getUsaSupportState(); // 美国一件代发州保存缩写
//                    $value['ShipToState'] = isset($stateArr[trim($value['ShipToState'])]) ? $stateArr[trim($value['ShipToState'])] : trim($value['ShipToState']);
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
                    // 29569 回退
//                    "bill_address" => $value['ShipToAddressDetail'] == '' ? null : trim($value['ShipToAddressDetail']),
//                    "bill_city" => $value['ShipToCity'] == '' ? null : trim($value['ShipToCity']),
//                    "bill_state" => $value['ShipToState'] == '' ? null : trim($value['ShipToState']),
//                    "bill_zip_code" => $value['ShipToPostalCode'] == '' ? null : trim($value['ShipToPostalCode']),
//                    "bill_country" => $value['ShipToCountry'] == '' ? null : trim($value['ShipToCountry']),
                    "ship_name" => $value['ShipToName'] == '' ? null : $value['ShipToName'],
                    "ship_address1" => $value['ShipToAddressDetail'] == '' ? null : $value['ShipToAddressDetail'],
                    // 29569 回退
//                    "ship_address1" => $value['ShipToAddressDetail'] == '' ? null : trim($value['ShipToAddressDetail']),
                    "ship_address2" => null,
                    "ship_city" => $value['ShipToCity'] == '' ? null : $value['ShipToCity'],
                    "ship_state" => $value['ShipToState'] == '' ? null : $value['ShipToState'],
                    "ship_zip_code" => $value['ShipToPostalCode'] == '' ? null : $value['ShipToPostalCode'],
                    "ship_country" => $value['ShipToCountry'] == '' ? null : $value['ShipToCountry'],
                    "ship_phone" => $value['ShipToPhone'] == '' ? null : $value['ShipToPhone'],
                    // 29569 回退
//                    "ship_city" => $value['ShipToCity'] == '' ? null : trim($value['ShipToCity']),
//                    "ship_state" => $value['ShipToState'] == '' ? null : trim($value['ShipToState']),
//                    "ship_zip_code" => $value['ShipToPostalCode'] == '' ? null : trim($value['ShipToPostalCode']),
//                    "ship_country" => $value['ShipToCountry'] == '' ? null : trim($value['ShipToCountry']),
//                    "ship_phone" => $value['ShipToPhone'] == '' ? null : trim($value['ShipToPhone']),
                    "item_code" => $value['B2BItemCode'] == '' ? null : trim($value['B2BItemCode']),
                    "alt_item_id" => $value['BuyerSkuLink'] == '' ? null : $value['BuyerSkuLink'],
                    "product_name" => $value['BuyerSkuDescription'] == '' ? 'product name' : $value['BuyerSkuDescription'],
                    "qty" => $value['ShipToQty'] == '' ? null : $value['ShipToQty'],
                    "item_price" => $value['BuyerSkuCommercialValue'] == '' ? 1 : $value['BuyerSkuCommercialValue'],
                    "item_unit_discount" => null,
                    "item_tax" => null,
                    "discount_amount" => null,
                    "tax_amount" => null,
                    "ship_amount" => null,
                    "order_total" => 1,
                    "payment_method" => null,
                    "ship_company" => null,
                    "ship_method" => $value['ShipToService'] == '' ? null : strtoupper($value['ShipToService']),
                    "delivery_to_fba" => empty(trim($value['DeliveryToFBAWarehouse'])) ? 0 : (strtoupper(trim($value['DeliveryToFBAWarehouse'])) == 'YES' ? 1 : 0),
                    "ship_service_level" => $value['ShipToServiceLevel'] == '' ? null : $value['ShipToServiceLevel'],
                    "brand_id" => $value['BuyerBrand'] == '' ? null : $value['BuyerBrand'],
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
                return "OrderId Duplicate,please check the uploaded file.";
            }
            if (!empty($existentOrderIdArray)) {
                return 'OrderId:' . implode('、', array_unique($existentOrderIdArray) ) . ' is already exist ,please check the uploaded file.';
            }
            try {
                $this->orm->getConnection()->beginTransaction();
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
                $this->updateYzcOrderIdNumber($yzc_order_id_number);
                // 新增站内信地址不对的提醒
                $ret = $this->insertCustomerSalesOrderAndLine($customerSalesOrderArr);
                $this->orm->getConnection()->commit();

            } catch (Exception $e) {
                $this->log->write('导入订单错误，something wrong happened. Please try it again.');
                $this->log->write($e);
                $this->orm->getConnection()->rollBack();
                $ret = $this->language->get('error_happened');
            }
            return $ret;
        }

    }

    /**
     * shopify 检验数据
     * @param array $data
     * @param int $import_mode
     * @param string $run_id
     * @param int $country_id
     * @param int $customer_id
     * @return array|bool|string
     * @throws Exception
     */
    public function verifyFileShopifyDataColumn(array $data, int $import_mode, string $run_id, int $country_id, int $customer_id)
    {
        if ($import_mode == 0) {
            $order_mode = CustomerSalesOrderMode::DROP_SHIPPING;
            $orderArr = [];
            //检验如果*Name相同，处理LineItemNumber
            $same_name = [];
            $same_first_key = [];
            foreach ($data as $key_name => $value_name) {
                if (in_array($value_name['Name'], $same_name)) {
                    $count_arr = array_count_values($same_name);
                    $data[$key_name]['LineItemNumber'] = $count_arr[$value_name['Name']] + 1;
                    $data[$key_name]['parent_key'] = $same_first_key[$value_name['Name']];
                } else {
                    $data[$key_name]['LineItemNumber'] = 1;
                    $same_first_key[$value_name['Name']] = $key_name;
                    $data[$key_name]['parent_key'] = '-1';
                }
                array_push($same_name, $value_name['Name']);
            }

            foreach ($data as $key => &$value) {
                if (isset($value['*Name'])) {
                    $value['Name'] = $value['*Name'];
                }
                if (isset($value['*Email'])) {
                    $value['Email'] = $value['*Email'];
                }
                if (isset($value['*Lineitem quantity'])) {
                    $value['Lineitem quantity'] = $value['*Lineitem quantity'];
                }
                if (isset($value['*Lineitem sku'])) {
                    $value['Lineitem sku'] = $value['*Lineitem sku'];
                }
                $value['Lineitem sku'] = strtoupper(trim($value['Lineitem sku']));
                if (isset($value['*Shipping Name'])) {
                    $value['Shipping Name'] = $value['*Shipping Name'];
                }
                if (isset($value['*Shipping Street'])) {
                    $value['Shipping Street'] = $value['*Shipping Street'];
                }
                if (isset($value['*Shipping City'])) {
                    $value['Shipping City'] = $value['*Shipping City'];
                }
                if (isset($value['*Shipping Zip'])) {
                    $value['Shipping Zip'] = $value['*Shipping Zip'];
                }
                if (isset($value['*Shipping Province'])) {
                    $value['Shipping Province'] = $value['*Shipping Province'];
                }
                if (isset($value['*Shipping Country'])) {
                    $value['Shipping Country'] = $country_id == AMERICAN_COUNTRY_ID ? strtoupper($value['*Shipping Country']) : $value['*Shipping Country'];
                }
                if (isset($value['*Shipping Phone'])) {
                    $value['Shipping Phone'] = $value['*Shipping Phone'];
                }
                $flag = true;
                foreach ($value as $k => $v) {
                    if ($v != '') {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    continue;
                }
                //只能是英文、数字、_、-  不是则替换成-
                if (!preg_match('/^[_0-9-a-zA-Z]{1,40}$/i', trim($value['Name']))) {
                    $temp_name_arr = str_split($value['Name']);
                    $temp_name = '';
                    foreach ($temp_name_arr as $kn => $val_name) {
                        if (trim($val_name) != '') {
                            if (!preg_match('/^[_0-9-a-zA-Z]$/i', trim($val_name))) {
                                $temp_name = $temp_name . '-';
                            } else {
                                $temp_name .= $val_name;
                            }
                        }
                    }
                    $value['Name'] = $temp_name;
                    $data[$key]['Name'] = $temp_name;
                }
                // 校验是否已经是插入的订单了
                if ($value['parent_key'] == '-1') {
                    $compare_name = trim($value['Name']);
                    $checkResult = $this->judgeCommonOrderIsExist($compare_name, $customer_id);
                    if ($checkResult) {
                        $similar_order_id_count = DB::table('tb_sys_customer_sales_order_temp')->where([
                            ['buyer_id', '=', $customer_id],
                            ['order_id', 'like', $compare_name . '%']
                        ])->count();
                        $value['Name'] = $compare_name . '-' . $similar_order_id_count;
                        $data[$key]['Name'] = $compare_name . '-' . $similar_order_id_count;
                    }
                } else {
                    $value['Name'] = $data[$value['parent_key']]['Name'];
                    $data[$key]['Name'] = $data[$value['parent_key']]['Name'];
                    //判断如果没有写的话，就用上一条的
                    if (empty(trim($value['Created at'])) || !isset($value['Created at'])) {
                        $value['Created at'] = $data[$value['parent_key']]['Created at'];
                        $data[$key]['Created at'] = $data[$value['parent_key']]['Created at'];
                    }
                    if (empty(trim($value['Total'])) || !isset($value['Total'])) {
                        $value['Total'] = $data[$value['parent_key']]['Total'];
                        $data[$key]['Total'] = $data[$value['parent_key']]['Total'];
                    }
                    if (empty(trim($value['Shipping Name'])) || !isset($value['Shipping Name'])) {
                        $value['Shipping Name'] = $data[$value['parent_key']]['Shipping Name'];
                        $data[$key]['Shipping Name'] = $data[$value['parent_key']]['Shipping Name'];
                    }
                    if (empty(trim($value['Email'])) || !isset($value['Email'])) {
                        $value['Email'] = $data[$value['parent_key']]['Email'];
                        $data[$key]['Email'] = $data[$value['parent_key']]['Email'];
                    }
                    if (empty(trim($value['Shipping Phone'])) || !isset($value['Shipping Phone'])) {
                        $value['Shipping Phone'] = $data[$value['parent_key']]['Shipping Phone'];
                        $data[$key]['Shipping Phone'] = $data[$value['parent_key']]['Shipping Phone'];
                    }
                    if (empty(trim($value['Shipping Zip'])) || !isset($value['Shipping Zip'])) {
                        $value['Shipping Zip'] = $data[$value['parent_key']]['Shipping Zip'];
                        $data[$key]['Shipping Zip'] = $data[$value['parent_key']]['Shipping Zip'];
                    }
                    if (empty(trim($value['Shipping Street'])) || !isset($value['Shipping Street'])) {
                        $value['Shipping Street'] = $data[$value['parent_key']]['Shipping Street'];
                        $data[$key]['Shipping Street'] = $data[$value['parent_key']]['Shipping Street'];
                    }
                    if (empty(trim($value['Shipping Company'])) || !isset($value['Shipping Company'])) {
                        $value['Shipping Company'] = $data[$value['parent_key']]['Shipping Company'];
                        $data[$key]['Shipping Company'] = $data[$value['parent_key']]['Shipping Company'];
                    }
                    if (empty(trim($value['Shipping City'])) || !isset($value['Shipping City'])) {
                        $value['Shipping City'] = $data[$value['parent_key']]['Shipping City'];
                        $data[$key]['Shipping City'] = $data[$value['parent_key']]['Shipping City'];
                    }
                    if (empty(trim($value['Shipping Province'])) || !isset($value['Shipping Province'])) {
                        $value['Shipping Province'] = $data[$value['parent_key']]['Shipping Province'];
                        $data[$key]['Shipping Province'] = $data[$value['parent_key']]['Shipping Province'];
                    }
                    if (empty(trim($value['Shipping Country'])) || !isset($value['Shipping Country'])) {
                        $value['Shipping Country'] = $data[$value['parent_key']]['Shipping Country'];
                        $data[$key]['Shipping Country'] = $data[$value['parent_key']]['Shipping Country'];
                    }
                    if (empty(trim($value['Notes'])) || !isset($value['Notes'])) {
                        $value['Notes'] = $data[$value['parent_key']]['Notes'];
                        $data[$key]['Notes'] = $data[$value['parent_key']]['Notes'];
                    }
                }

                $column_ret = $this->verifyCommonShopifyOrderFileDataColumn($value, $key + 2, $country_id);
                if ($column_ret !== true) {
                    return $column_ret;
                }
                $ship_address1 = trim($value['Shipping Company']) == '' ? '' : ',' . trim($value['Shipping Company']);
                $ship_address1 = trim($value['Shipping Street']) . $ship_address1;
                $orderArr[] = [
                    "orders_from" => "Shopify",
                    "order_id" => $data[$key]['Name'] == '' ? null : trim($data[$key]['Name']),
                    "line_item_number" => $value['LineItemNumber'] == '' ? null : $value['LineItemNumber'],
                    "email" => $value['Email'] == '' ? null : $value['Email'],
                    "order_date" => $value['Created at'] == '' ? date('Y-m-d H:i:s') : $value['Created at'],
                    "bill_name" => $value['Shipping Name'] == '' ? null : $value['Shipping Name'],
                    "bill_address" => $ship_address1 == '' ? null : $ship_address1,
                    "bill_city" => $value['Shipping City'] == '' ? null : $value['Shipping City'],
                    "bill_state" => $value['Shipping Province'] == '' ? null : $value['Shipping Province'],
                    "bill_state_name" => $value['Billing Province Name'] ?? null,
                    "bill_zip_code" => $value['Shipping Zip'] == '' ? null : $value['Shipping Zip'],
                    "bill_country" => $value['Shipping Country'] == '' ? null : $value['Shipping Country'],
                    "ship_name" => $value['Shipping Name'] == '' ? null : $value['Shipping Name'],
                    "ship_address1" => $ship_address1 == '' ? null : $ship_address1,
                    "ship_address2" => null,
                    "ship_city" => $value['Shipping City'] == '' ? null : $value['Shipping City'],
                    "ship_state" => $value['Shipping Province'] == '' ? null : $value['Shipping Province'],
                    "ship_state_name" => $value['Shipping Province Name'] ?? null,
                    "ship_zip_code" => $value['Shipping Zip'] == '' ? null : $value['Shipping Zip'],
                    "ship_country" => $value['Shipping Country'] == '' ? null : $value['Shipping Country'],
                    "ship_phone" => $value['Shipping Phone'] == '' ? null : $value['Shipping Phone'],
                    "item_code" => $value['Lineitem sku'] == '' ? null : trim($value['Lineitem sku']),
                    "alt_item_id" => null,
                    "product_name" => 'product name',
                    "qty" => $value['Lineitem quantity'] == '' ? null : $value['Lineitem quantity'],
                    "item_price" => $value['Total'] == '' ? 1 : $value['Total'],
                    "item_unit_discount" => null,
                    "item_tax" => null,
                    "discount_amount" => null,
                    "tax_amount" => null,
                    "ship_amount" => null,
                    "order_total" => 1,
                    "payment_method" => null,
                    "ship_company" => trim($value['Shipping Company']) == '' ? null : trim($value['Shipping Company']),
                    "ship_method" => null,
                    "ship_service_level" => null,
                    "brand_id" => null,
                    "customer_comments" => $value['Notes'] == '' ? null : $value['Notes'],
                    "shipped_date" => null,//13195OrderFulfillment订单导入模板调优
                    "ship_to_attachment_url" => null,
                    //"seller_id"          => $sellerId,
                    "buyer_id" => $customer_id,
                    "run_id" => $run_id,
                    "create_user_name" => $customer_id,
                    "create_time" => date('Y-m-d H:i:s'),
                    "update_user_name" => PROGRAM_CODE
                ];
            }

            try {
                $this->orm->getConnection()->beginTransaction();
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
                    }

                }
                $this->updateYzcOrderIdNumber($yzc_order_id_number);
                // 新增站内信地址不对的提醒
                $ret = $this->insertCustomerSalesOrderAndLine($customerSalesOrderArr);
                $this->orm->getConnection()->commit();

            } catch (Exception $e) {
                $this->log->write('导入订单错误，something wrong happened. Please try it again.');
                $this->log->write($e);
                $this->orm->getConnection()->rollBack();
                $ret = $this->language->get('error_happened');
            }
            return $ret;
        }
    }

    public function checkIsExistAssociate($order_id)
    {
        $ret = db('tb_sys_order_associated_pre as p')
            ->leftJoin('oc_order as oo', 'oo.order_id', '=', 'p.order_id')
            ->where([
                'p.sales_order_id' => $order_id,
                'p.status' => 0,
                'oo.order_status_id' => OcOrderStatus::TO_BE_PAID,
            ])
            ->groupBy('order_id')
            ->select('p.*')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if (count($ret) == 0) {
            $json = [
                'error' => 0,
            ];
        } elseif (count($ret) == 1) {
            $json = [
                'error' => 1,
                'order_num' => 1,
                'order_id' => $ret[0]['order_id'],
            ];
        } else {
            $json = [
                'error' => 1,
                'order_num' => count($ret),
            ];
        }
        return $json;
    }

    public function getCommonOrderColumnNameConversion($data, $order_mode, $customer_id, $country_id = AMERICAN_COUNTRY_ID, $import_mode = 0)
    {
        $res = [];
        if ($order_mode == CustomerSalesOrderMode::DROP_SHIPPING && $country_id) {
            // 增加了一个逻辑欧洲的国家需要注意ship_country,
            //非本国的情况下is_international 是否为国际单，0：非国际单，1：国际单
            if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                if (
                    (!in_array(strtoupper($data['ship_country']), self::BRITAIN_ALIAS_NAME) && $country_id == self::BRITAIN_COUNTRY_ID)
                    || (strtoupper($data['ship_country']) != 'DE' && $country_id == self::GERMANY_COUNTRY_ID)
                ) {
                    $res['is_international'] = 1;
                }
            }
            $res['order_id'] = $data['order_id'];
            $res['order_date'] = $data['order_date'];
            $res['email'] = $data['email'];
            $res['ship_name'] = $data['ship_name'];
            $res['ship_address1'] = trim($data['ship_address1']);
            $res['ship_address2'] = trim($data['ship_address2']);
            $res['ship_city'] = $data['ship_city'];
            $res['ship_state'] = $data['ship_state'];
            $res['ship_state_name'] = $data['ship_state_name'];
            $res['ship_zip_code'] = $data['ship_zip_code'];
            $res['ship_country'] = $data['ship_country'];
            $res['ship_phone'] = $data['ship_phone'];
            $res['ship_method'] = $data['ship_method'];
            $res['delivery_to_fba'] = $data['delivery_to_fba'];
            $res['ship_service_level'] = $data['ship_service_level'];
            $res['ship_company'] = $data['ship_company'];
            $res['shipped_date'] = $data['shipped_date'];
            $res['bill_name'] = $res['ship_name'];
            $res['bill_address'] = $res['ship_address1'];
            $res['bill_city'] = $res['ship_city'];
            $res['bill_state'] = $res['ship_state'];
            $res['bill_state_name'] = $data['bill_state_name'];
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
            $res['order_status'] = 1; //Abnormal order
            $res['order_mode'] = $order_mode;
            $res['create_user_name'] = $data['create_user_name'];
            $res['create_time'] = $data['create_time'];
            $res['update_user_name'] = $data['create_user_name'];
            $res['update_time'] = $data['create_time'];
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
                'create_user_name' => $data['create_user_name'],
                'create_time' => $data['create_time'],
                'program_code' => $data['program_code'],
            ];


        }
        return $res;
    }


    public function insertCustomerSalesOrderAndLine($data)
    {
        $order_id_list = [];
        foreach ($data as $key => $value) {
            $tmp = $data[$key]['product_info'];
            unset($data[$key]['product_info']);
            $insertId = DB::table('tb_sys_customer_sales_order')->insertGetId($data[$key]);
            if ($insertId) {
                foreach ($tmp as $k => $v) {
                    $tmp[$k]['header_id'] = $insertId;
                    DB::table('tb_sys_customer_sales_order_line')->insertGetId($tmp[$k]);
                }
            }
            $order_id_list[] = $insertId;

        }
        return $order_id_list;

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

    public function verifyCommonShopifyOrderFileDataColumn($data, $index, $country_id)
    {
        if ($data['Name'] == '' || strlen($data['Name']) > 40) {
            return 'Line' . $index . ",Name must be between 1 and 40 characters!";
        }
        //校验orderId只能是英文、数字、_、-
        if (!preg_match('/^[_0-9-a-zA-Z]{1,40}$/i', trim($data['Name']))) {
            return 'Line' . $index . ",Name format error,Please see the instructions!";
        }
        if (strlen($data['Created at']) > 25) {
            return 'Line' . $index . ",Created at must be between 0 and 25 characters!";
        }
        if (trim($data['Lineitem sku']) == '' || strlen($data['Lineitem sku']) > 30) {
            return 'Line' . $index . ",Lineitem sku must be between 1 and 30 characters!";

        }
        //        if (strlen($data['Lineitem name']) > 100) {
        //            return 'Line' . $index . ",Lineitem name must be between 0 and 100 characters!";
        //
        //        }
        $reg3 = '/^(([1-9][0-9]*)|(([0]\.\d{1,2}|[1-9][0-9]*\.\d{1,2})))$/';
        if ($data['Total'] != '') {
            if (!preg_match($reg3, $data['Total'])) {
                return 'Line' . $index . ",Total format error,Please see the instructions.";
            }
        }

        if ($data['Lineitem quantity'] == '' || !preg_match('/^[0-9]*$/', $data['Lineitem quantity']) || $data['Lineitem quantity'] <= 0) {
            return 'Line' . $index . ",Lineitem quantity format error,Please see the instructions.";
        }
        if (trim($data['Shipping Name']) == '' || strlen($data['Shipping Name']) > 40) {
            return 'Line' . $index . ",Shipping Name must be between 1 and 40 characters!";
        }
        if (trim($data['Email']) == '' || strlen($data['Email']) > 90) {
            return 'Line' . $index . ",Email must be between 1 and 90 characters!";
        }
        if (trim($data['Shipping Phone']) == '' || strlen($data['Shipping Phone']) > 45) {
            return 'Line' . $index . ",Shipping Phone must be between 1 and 45 characters!";
        }

        if (trim($data['Shipping Zip']) == '' || strlen($data['Shipping Zip']) > 18) {
            return 'Line' . $index . ",Shipping Zip must be between 1 and 18 characters!";
        }

        //102730需求德国国别，StreetAddress字符限制在35个字符，超过时文案提醒：Maximum 35 characters, please fill in again
        if ($country_id == AMERICAN_COUNTRY_ID) {
            $limit_shipping_street_char = 56;
        } elseif ($country_id == self::GERMANY_COUNTRY_ID) {
            $limit_shipping_street_char = 35;
        } else {
            $limit_shipping_street_char = 80;
        }
        $ShipToAddressDetail = trim($data['Shipping Street']) . trim($data['Shipping Company']);
        if (trim($data['Shipping Street']) == '' || trim($ShipToAddressDetail) == '' || mb_strlen($ShipToAddressDetail) > $limit_shipping_street_char) {
            return 'Line' . $index . ", Ship To Address Detail must be between 1 and " . $limit_shipping_street_char . " characters!";
        } else {
            //验证P.O.BOXES；PO BOX；PO BOXES；P.O.BOX
            //13573 需求，销售订单地址校验，包含P.O.Box 的地址，不能导入到B2B，提示这个是一个邮箱地址，不能送到
            if ($country_id == AMERICAN_COUNTRY_ID) {
                if (AddressHelper::isPoBox($ShipToAddressDetail)) {
                    return 'Line' . $index . ", Ship To Address Detail in P.O.BOX doesn't support delivery,Please see the instructions.";
                }
            }
        }
        if (trim($data['Shipping City']) == '' || strlen($data['Shipping City']) > 30) {
            return 'Line' . $index . ",Shipping City must be between 1 and 30 characters!";

        }
        if ($country_id == JAPAN_COUNTRY_ID) {
            //日本国别吗，导单时，增加校验ShipToCity和ShipToState两个字段不可重复
            if ($data['Shipping Province'] == $data['Shipping City']) {
                return 'Line' . $index . ",Shipping Province must be different from Shipping City!";
            }
        }

        if ($country_id == AMERICAN_COUNTRY_ID) {
            if (trim($data['Shipping Province']) == '' || strlen($data['Shipping Province']) > 30) {
                return 'Line' . $index . ",Shipping Province must be between 1 and 30 characters!";
            } else {
                if (AddressHelper::isRemoteRegion($data['Shipping Province'] ?? '')) {
                    return 'Line' . $index . ",Shipping Province in PR, AK, HI, GU, AA, AE, AP doesn't support delivery,Please see the instructions.";
                }

            }
        } else {

            if (trim($data['Shipping Province']) == '' || strlen($data['Shipping Province']) > 30) {
                return 'Line' . $index . ",Shipping Province must be between 0 and 30 characters!";

            }
        }

        if ($country_id == AMERICAN_COUNTRY_ID) {
            if (strtoupper($data['Shipping Country']) != 'US') {
                return 'Line' . $index . ",Shipping Country format error,Please see the instructions.";
            }
        }

        if ($country_id == JAPAN_COUNTRY_ID) {
            if (strtoupper($data['Shipping Country']) != 'JP') {
                return 'Line' . $index . ",Shipping Country format error,Please see the instructions.";
            }
        }

        if (strlen($data['Notes']) > 1500) {
            return 'Line' . $index . ",Notes must be between 0 and 1500 characters!";

        }

        return true;

    }


    /**
     * [verifyCommonOrderFileDataColumn description]
     * @param $data
     * @param $index
     * @param int $country_id
     * @param array $itemCodes
     * @return bool|string
     */
    public function verifyCommonOrderFileDataColumn($data, $index, $country_id, $itemCodes = [])
    {
        $isLTL = false;
        if ($country_id == AMERICAN_COUNTRY_ID) {
            // 29569 回退
//            $stateArr = app(CountryStateRepository::class)->getUsaSupportState(); // 美国一件代发支持的州

            $cacheKey = [__CLASS__, __FUNCTION__, $data['OrderId']];
            if ($this->getRequestCachedData($cacheKey) !== null) {
                $isLTL = $this->getRequestCachedData($cacheKey);
            } else {
                $isLTL = app(CustomerSalesOrderRepository::class)->isLTL($country_id, $itemCodes);
                $this->setRequestCachedData($cacheKey, $isLTL);
            }
        } else {
            // 欧洲和日本 需要校验 DeliveryToFBAWarehouse （是否FBA送仓）-- 可以不填，如果填写 只能是 yes or no
            if (trim($data['DeliveryToFBAWarehouse']) && !in_array(strtoupper(trim($data['DeliveryToFBAWarehouse'])), ['NO', 'YES'])) {
                return 'Line' . $index . ",DeliveryToFBAWarehouse only supports 'Yes' or 'No' or do not fill in.";
            }
        }

        $limitSalesPlatformChar = 20;
        $platformColumn = 'SalesPlatform';
        if ($country_id == AMERICAN_COUNTRY_ID) {
            $limitShipAddressChar = $this->config->get('config_b2b_address_len_us1');
            $limitSalesPlatformChar = 30;
            $platformColumn = 'ShipFrom';
        } else if ($country_id == UK_COUNTRY_ID) {
            $limitShipAddressChar = $this->config->get('config_b2b_address_len_uk');
        } else if ($country_id == DE_COUNTRY_ID) {
            $limitShipAddressChar = $this->config->get('config_b2b_address_len_de');
        } else {
            $limitShipAddressChar = $this->config->get('config_b2b_address_len_jp');
        }

        if ($isLTL) {
            $limitShipAddressChar = $this->config->get('config_b2b_address_len');
        }
        if (strlen($data['SalesPlatform']) > $limitSalesPlatformChar) {
            return sprintf($this->language->get('error_platform'), $index, $platformColumn, $limitSalesPlatformChar);
        }
        if ($data['OrderId'] == '' || strlen($data['OrderId']) > 40) {
            return 'Line' . $index . ",OrderId must be between 1 and 40 characters!";
        }
        //校验orderId只能是英文、数字、_、-
        if (!preg_match('/^[_0-9-a-zA-Z]{1,40}$/i', trim($data['OrderId']))) {
            return 'Line' . $index . ",OrderId format error,Please see the instructions!";
        }
        if ($data['LineItemNumber'] == '' || strlen($data['LineItemNumber']) > 50) {
            return 'Line' . $index . ",LineItemNumber must be between 1 and 50 characters!";
        }
        if (strlen($data['OrderDate']) > 25) {
            return 'Line' . $index . ",OrderDate must be between 0 and 25 characters!";
        }
        if (strlen($data['BuyerBrand']) > 30) {
            return 'Line' . $index . ",BuyerBrand must be between 0 and 30 characters!";
        }
        if (strlen($data['BuyerPlatformSku']) > 25) {
            //13584 需求，Buyer导入订单时校验ItemCode的录入itemCode去掉首尾的空格，
            //如果ITEMCODE不是由字母和数字组成的，那么提示BuyerItemCode有问题，这个文件不能导入
            //整个上传格式会发生变化
            return 'Line' . $index . ",BuyerPlatformSku must be between 0 and 25 characters!";
        }
        if (trim($data['B2BItemCode']) == '' || strlen($data['B2BItemCode']) > 30) {
            return 'Line' . $index . ",B2BItemCode must be between 1 and 30 characters!";
        }
        //在欧洲国别中运费产品导入的时候需要报错
        if (in_array($country_id, EUROPE_COUNTRY_ID)) {
            $flag = $this->verifyEuropeFreightSku(trim($data['B2BItemCode']));
            if ($flag) {
                return 'Line' . $index . ",The Additional Fulfillment Fee is automatically calculated by the Marketplace system and added to your order total.";
            }
        }

        if (strlen($data['BuyerSkuDescription']) > 100) {
            return 'Line' . $index . ",BuyerSkuDescription must be between 0 and 100 characters!";

        }
        $reg3 = '/^(([1-9][0-9]*)|(([0]\.\d{1,2}|[1-9][0-9]*\.\d{1,2})))$/';
        if ($data['BuyerSkuCommercialValue'] != '') {
            if (!preg_match($reg3, $data['BuyerSkuCommercialValue'])) {
                return 'Line' . $index . ",BuyerSkuCommercialValue format error,Please see the instructions.";
            }
        }
        if (strlen($data['BuyerSkuLink']) > 50) {
            return 'Line' . $index . ",BuyerSkuLink must be between 0 and 50 characters!";
        }
        if ($data['ShipToQty'] == '' || !preg_match('/^[0-9]*$/', $data['ShipToQty']) || $data['ShipToQty'] <= 0) {
            return 'Line' . $index . ",ShipToQty format error,Please see the instructions.";
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
        // #29569 回退 start
        if (trim($data['ShipToPhone']) == '' || strlen($data['ShipToPhone']) > 45) {
            return 'Line' . $index . ",ShipToPhone must be between 1 and 45 characters!";
        }

        if (trim($data['ShipToPostalCode']) == '' || strlen($data['ShipToPostalCode']) > 18) {
            return 'Line' . $index . ",Postal code must be between 1 and 18 characters!";
        }

        //#12959 美国区域邮编组成：数字 -
        if ($country_id == AMERICAN_COUNTRY_ID && !preg_match('/^[0-9-]{1,18}$/i', trim($data['ShipToPostalCode']))) {
            return 'Line' . $index . ",Postal code must Include only numbers,-.";
        }
        // #29569 回退 end

        //#7598 日本邮政编码只能是数字和-
        if ($country_id == JAPAN_COUNTRY_ID && !preg_match('/^[0-9-]{1,18}$/i', trim($data['ShipToPostalCode']))) {
            return 'Line' . $index . ",Postal code must Include only numbers,-";
        }

        //102730需求德国国别，ShipToAddressDetail字符限制在35个字符，超过时文案提醒：Maximum 35 characters, please fill in again
        if ($country_id == self::GERMANY_COUNTRY_ID && (trim($data['ShipToAddressDetail']) == '' || StringHelper::stringCharactersLen(trim($data['ShipToAddressDetail'])) > $limitShipAddressChar)) {
            return 'Line' . $index . ",ShipToAddressDetail maximum 35 characters, please fill in again";
        }

        if (trim($data['ShipToAddressDetail']) == '' || StringHelper::stringCharactersLen(trim($data['ShipToAddressDetail'])) > $limitShipAddressChar) {
            return "Line {$index}, ShipToAddressDetail must be between 1 and {$limitShipAddressChar} characters!";
        } else {
            //验证P.O.BOXES；PO BOX；PO BOXES；P.O.BOX
            //13573 需求，销售订单地址校验，包含P.O.Box 的地址，不能导入到B2B，提示这个是一个邮箱地址，不能送到
            if (!$isLTL && ($country_id == AMERICAN_COUNTRY_ID)) {
                if (AddressHelper::isPoBox($data['ShipToAddressDetail'])) {
                    return 'Line' . $index . ",ShipToAddressDetail in P.O.BOX doesn't support delivery,Please see the instructions.";
                }
            }
        }
        // #29569 回退 start
        if (trim($data['ShipToCity']) == '' || strlen($data['ShipToCity']) > 30) {
            return 'Line' . $index . ",ShipToCity must be between 1 and 30 characters!";
        }
        // #29569 回退 end

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

            //#7598 日本国别，State是否存在
            if (!$this->existsByCountyAndCountry(trim($data['ShipToState']), JAPAN_COUNTRY_ID)) {
                return 'Line' . $index . ",The State filled in is incorrect. It should be like '京都府', '滋賀県'.";
            }

            //日本国别吗，导单时，增加校验ShipToCity和ShipToState两个字段不可重复
            if ($data['ShipToState'] == $data['ShipToCity']) {
                return 'Line' . $index . ",City must differ from the state.";
            }

            //#7598 日本国别，City中不能包含State
            if (false !== strpos(trim($data['ShipToCity']), trim($data['ShipToState']))) {
                return 'Line' . $index . ",City must differ from the state.";
            }
        }

        // #29569 回退 start
        if ($country_id == AMERICAN_COUNTRY_ID) {
            if (trim($data['ShipToState']) == '' || strlen($data['ShipToState']) > 30) {
                return 'Line' . $index . ",ShipToState must be between 1 and 30 characters!";
            } else {
                if (!$isLTL) {
                    $shipToState = $data['ShipToState'] == null ? '' : strtoupper(trim($data['ShipToState']));
//                    $stateArray = ['PR', 'AK', 'HI', 'GU', 'AA', 'AE', 'AP', 'ALASKA', 'ARMED FORCES AMERICAS', 'ARMED FORCES EUROPE', 'ARMED FORCES PACIFIC', 'GUAM', 'HAWAII', 'PUERTO RICO'];
                    $state = app(SetupRepository::class)->getValueByKey("REMOTE_ARES");
                    $state = !is_null($state) ? $state : 'PR,AK,HI,GU,AA,AE,AP,ALASKA,ARMED FORCES AMERICAS,ARMED FORCES EUROPE,ARMED FORCES PACIFIC,GUAM,HAWAII,PUERTO RICO';
                    $stateArray = explode(',', $state);
                    if (in_array($shipToState, $stateArray)) {
                        return 'Line' . $index . ",ShipToState in PR, AK, HI, GU, AA, AE, AP doesn't support delivery,Please see the instructions.";
                    }
                }
            }
        } else {

            if (trim($data['ShipToState']) == '' || strlen($data['ShipToState']) > 30) {
                return 'Line' . $index . ",ShipToState must be between 0 and 30 characters!";

            }
        }

        // 若可发往国际，但出现了没有维护的国别，可以上传成功，无报错文案和提醒文案
        if ($country_id == AMERICAN_COUNTRY_ID) {
            if (strtoupper($data['ShipToCountry']) != 'US') {
                return 'Line' . $index . ",ShipToCountry must be 'US'.";
            }
        }

//        if (trim($data['ShipToCity']) == '' || strlen($data['ShipToCity']) > 30) {
//            return 'Line' . $index . ",ShipToCity must be between 1 and 30 characters!";
//        }


//        if ($country_id == AMERICAN_COUNTRY_ID) {
//            // 如果州为空 或者 不属于规定范围内缩写且不属于规定范围内全称
//            if (empty(trim($data['ShipToState'])) || (! in_array(trim($data['ShipToState']), array_keys($stateArr)) && ! in_array(trim($data['ShipToState']), array_values($stateArr)))) {
//                return 'Line' . $index . ",[ShipToState]:The 'State' should be in an abbreviation or the delivery service is not available for the State filled in.";
//            }
//            // 美国继续限制收件地址
//            if (! preg_match('/^[0-9a-zA-Z,\. ]{1,}$/i', trim($data['ShipToAddressDetail']))) {
//                return 'Line' . $index . ',[ShipToAddressDetail]:Only letters, digits, spaces, and commas or dots in English are allowed.';
//            }
//            // 美国继续限制邮编
//            if (trim($data['ShipToPostalCode']) == '' ||! preg_match('/^[0-9]{5}$/i', trim($data['ShipToPostalCode']))) {
//                return 'Line' . $index . ',[ShipToPostalCode]:Only a 5-digit number is allowed. You can delete ‘-’ and the last 4 digits.';
//            }
//            // 美国限制国家为US
//            if (strtoupper(trim($data['ShipToCountry'])) != 'US') {
//                return 'Line' . $index . ",[ShipToCountry]:Shipping Country must be 'US'.";
//             }
//            // 美国限制电话号码
//            if (trim($data['ShipToPhone']) == '' || ! preg_match('/^[0-9-]{10,15}$/i', trim($data['ShipToPhone']))) {
//                return 'Line' . $index . ',[ShipToPhone]:Limited to 10-15 characters, and only digits and ‘-’ are allowed.';
//            }
//        } else {
//            if (trim($data['ShipToState']) == '' || strlen($data['ShipToState']) > 30) {
//                return 'Line' . $index . ",ShipToState must be between 0 and 30 characters!";
//            }
//            if (trim($data['ShipToPostalCode']) == '' || strlen($data['ShipToPostalCode']) > 18) {
//                return 'Line' . $index . ",Postal code must be between 1 and 18 characters!";
//            }
//            if (trim($data['ShipToPhone']) == '' || strlen($data['ShipToPhone']) > 45) {
//                return 'Line' . $index . ",ShipToPhone must be between 1 and 45 characters!";
//            }
//        }
        // #29569 回退 end

        if ($country_id == JAPAN_COUNTRY_ID) {
            if (strtoupper($data['ShipToCountry']) != 'JP') {
                return 'Line' . $index . ",This order may only be shipped within its country of origin.";
            }
        }

        $inactiveInternationalCountryList = $this->getInactiveInternationalCountryList();
        if (in_array($country_id, $inactiveInternationalCountryList)) {
            if ($country_id == self::BRITAIN_COUNTRY_ID) {
                if (!in_array(strtoupper($data['ShipToCountry']), self::BRITAIN_ALIAS_NAME)) {
                    return 'Line' . $index . ",This order may only be shipped within its country of origin.";
                }
            }
            if ($country_id == self::GERMANY_COUNTRY_ID) {
                if (strtoupper($data['ShipToCountry']) != 'DE') {
                    return 'Line' . $index . ",This order may only be shipped within its country of origin.";
                }
            }
        }

        if (strlen($data['OrderComments']) > 1500) {
            return 'Line' . $index . ",OrderComments must be between 0 and 1500 characters!";
        }
        return true;

    }

    public function getInactiveInternationalCountryList()
    {
        return InternationalOrderConfig::query()
            ->where('status', 0)
            ->pluck('country_id')
            ->toArray();
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

    public function getCustomerOrderAllInformation($order_id)
    {
        $this->load->model('catalog/product');
        $this->load->model('account/customer_order_import');
        $this->load->model('tool/image');
        $mapOrder = [
            ['o.id', '=', $order_id],
        ];
        $base_info = DB::table('tb_sys_customer_sales_order as o')
            ->where($mapOrder)
            ->select('o.order_id', 'o.order_status', 'o.create_time', 'o.orders_from', 'o.id', 'o.ship_name', 'o.ship_phone', 'o.email', 'o.shipped_date', 'o.ship_method', 'o.ship_service_level', 'o.ship_address1', 'o.ship_city', 'o.ship_state', 'o.ship_zip_code', 'o.ship_country', 'o.order_mode', 'o.customer_comments', 'o.buyer_id')
            ->get()
            ->map(function ($v) {
                $v->ship_address1 = app('db-aes')->decrypt($v->ship_address1);
                $v->ship_name = app('db-aes')->decrypt($v->ship_name);
                $v->ship_city = app('db-aes')->decrypt($v->ship_city);
                $v->ship_phone = app('db-aes')->decrypt($v->ship_phone);
                $v->email = app('db-aes')->decrypt($v->email);
                return (array)$v;
            })
            ->toArray();
        $base_info = current($base_info);
        $base_info['customer_comments'] = trim($base_info['customer_comments']);

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
            } else {
                $item_list[$key]['freight_show'] = null;
                $item_list[$key]['freight_per_show'] = null;
                $item_list[$key]['pack_fee_show'] = null;
                $item_list[$key]['freight_per'] = 0;
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
                        $mapTrackingInfo['k.parent_sku'] = $value['sku'];
                        $mapTrackingInfo['k.ShipSku'] = $v['sku'];
                        $mapTrackingInfo['k.SalesOrderId'] = $base_info['order_id'];

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
                    $tracking_info = db('tb_sys_customer_sales_order_tracking as k')
                        ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                        ->where($mapTrackingInfo)->
                        select('c.CarrierName as carrier_name', 'k.TrackingNumber as tracking_number', 'k.status')
                        ->orderBy('k.status', 'desc')
                        ->get()
                        ->map(function ($v) {
                            return (array)$v;
                        })
                        ->toArray();
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
            ->selectRaw('sum(freight_total) as freight,sum(freight_unit) as freight_unit,sum(pack_fee) as pack_fee,logistics_type')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if ($info) {
            if ($info[0]['logistics_type'] == 2) {
                $freight_show = $this->currency->formatCurrencyPrice($info[0]['freight'] / $qty, $this->session->data['currency']);
                $pack_fee_show = $this->currency->formatCurrencyPrice($info[0]['pack_fee'], $this->session->data['currency']);
                $freight_per_show = $this->currency->formatCurrencyPrice(($info[0]['freight']) / $qty + $info[0]['pack_fee'], $this->session->data['currency']);
                $freight_per = $info[0]['freight'] + $info[0]['pack_fee'] * $qty;
            } else {
                $freight_show = $this->currency->formatCurrencyPrice($info[0]['freight_unit'], $this->session->data['currency']);
                $pack_fee_show = $this->currency->formatCurrencyPrice($info[0]['pack_fee'], $this->session->data['currency']);
                $freight_per_show = $this->currency->formatCurrencyPrice($info[0]['freight_unit'] + $info[0]['pack_fee'], $this->session->data['currency']);
                $freight_per = $info[0]['freight_unit'] * $qty + $info[0]['pack_fee'] * $qty;
            }
            return [
                'freight_show' => $freight_show,
                'pack_fee_show' => $pack_fee_show,
                'freight_per_show' => $freight_per_show,
                'freight_per' => $freight_per,
            ];
        } else {
            return false;
        }
    }

    /**
     * [dealErrorCode description] 根据
     * @param string $str
     * @return array|bool
     *
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

    /**r
     * @param int $customer_id
     * @param array $data
     * @param string $limit_date 时效时间，起始时间
     * @return Builder
     */
    private function querySalesOrder(int $customer_id, array $data = [], string $limit_date = ''): Builder
    {
        $co = new Collection($data);

        $sql = $this->orm
            ->table('tb_sys_customer_sales_order as c')
            ->select(['c.*'])
            ->leftJoin('tb_sys_customer_sales_order_line as l', ['l.header_id' => 'c.id']);
        //针对消息提示滚动的时效性，30天以内的
        if ($limit_date) {
            $sql = $sql->where([
                ['c.buyer_id', '=', $customer_id],
                ['c.order_mode', '=', CustomerSalesOrderMode::DROP_SHIPPING],
                ['c.update_time', '>=', $limit_date]
            ])->orWhere([
                ['c.buyer_id', '=', $customer_id],
                ['c.order_mode', '=', CustomerSalesOrderMode::DROP_SHIPPING],
                ['c.create_time', '>=', $limit_date]
            ]);
        } else {
            $sql = $sql->where(['c.buyer_id' => $customer_id, 'c.order_mode' => CustomerSalesOrderMode::DROP_SHIPPING]);
        }
        $result =  $sql->when(!empty($co->get('filter_orderId')), function (Builder $q) use ($co) {
            $q->where('c.order_id', 'like', '%' . trim($co->get('filter_orderId')) . '%');
        })
            ->when(
                !empty($co->get('filter_orderStatus')) && ($co->get('filter_orderStatus') != '*'),
                function (Builder $q) use ($co) {
                    $q->where('c.order_status', $co->get('filter_orderStatus'));
                }
            )
            ->when(!empty($co->get('filter_item_code')), function (Builder $q) use ($co) {
                $q->where('l.item_code', 'like', '%' . trim($co->get('filter_item_code')) . '%');
            })
            ->when(($co->has('filter_is_international') && (int)$co->get('filter_is_international') !== 2), function (Builder $q) use ($co) {
                $q->where('c.is_international', $co->get('filter_is_international'));
            })
            ->when(
                ($co->has('filter_tracking_number') && (int)$co->get('filter_tracking_number') !== 2),
                function (Builder $q) use ($co) {
                    $filter_tracking_number = (int)$co->get('filter_tracking_number');
                    $tracking_privilege = $co->get('tracking_privilege');
                    $q = $q->leftJoin('tb_sys_customer_sales_order_tracking as t',
                        [
                            'c.order_id' => 't.SalesOrderId',
                            'l.id' => 't.SalerOrderLineId'
                        ]
                    );

                    //关联物流可视化
                    $conditionDeliveryStatus = ($co->has('filter_delivery_status') && $co->get('filter_delivery_status') != -1) ? 1 : 0;
                    if ($conditionDeliveryStatus) {
                        $q = $q->leftJoin('tb_tracking_facts as ft', 't.SalesOrderId', '=', 'ft.sales_order_id');
                    }

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
                    if ($conditionDeliveryStatus) {
                        $q->where('ft.carrier_status', (int)$co->get('filter_delivery_status'))
                            ->where('ft.status', YesNoEnum::YES);
                    }
                }
            )
            ->when(($co->has('filter_tracking_number') && $co->get('filter_tracking_number') == 2) && ($co->has('filter_delivery_status') && $co->get('filter_delivery_status') != -1),
                function (Builder $q) use ($co){
                $q = $q->leftJoin('tb_sys_customer_sales_order_tracking as t',
                    [
                        'c.order_id' => 't.SalesOrderId',
                        'l.id' => 't.SalerOrderLineId'
                    ]
                );
                $q = $q->leftJoin('tb_tracking_facts as ft', 't.SalesOrderId', '=', 'ft.sales_order_id');
                $q->where('ft.carrier_status', (int)$co->get('filter_delivery_status'))
                    ->where('ft.status', YesNoEnum::YES);
            })
            ->when(!empty($co->get('filter_orderDate_from')), function (Builder $q) use ($co) {
                $q->where('c.create_time', '>=', $co->get('filter_orderDate_from'));
            })
            ->when(!empty($co->get('filter_orderDate_to')), function (Builder $q) use ($co) {
                $q->where('c.create_time', '<=', $co->get('filter_orderDate_to'));
            })
            ->orderBy('c.id', 'desc');

        return $result;
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

    public function getSalesOrderStatusList(int $customer_id): array
    {
        return db('tb_sys_customer_sales_order as c')
            ->where(['c.buyer_id' => $customer_id, 'order_mode' => CustomerSalesOrderMode::DROP_SHIPPING])
            ->groupBy('c.order_status')
            ->selectRaw('count(*) as count,c.order_status')
            ->get()
            ->keyBy('order_status')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
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
            $ret->forPage(($data['page'] ?? 1), ($data['page_limit'] ?? 20));
        }
        return $ret->groupBy(['c.id'])
            ->get()
            ->map(function ($item) {
                $item->ship_address1 = app('db-aes')->decrypt($item->ship_address1);
                $item->ship_address2 = app('db-aes')->decrypt($item->ship_address2);
                $item->ship_name = app('db-aes')->decrypt($item->ship_name);
                $item->ship_city = app('db-aes')->decrypt($item->ship_city);
                $item->ship_phone = app('db-aes')->decrypt($item->ship_phone);
                $item->email = app('db-aes')->decrypt($item->email);
                $item->bill_address = app('db-aes')->decrypt($item->bill_address);
                $item->bill_name = app('db-aes')->decrypt($item->bill_name);
                $item->bill_city = app('db-aes')->decrypt($item->bill_city);
                return (array)$item;
            })
            ->toArray();
    }

    public function getCommonOrderStatus($order_id, $run_id)
    {
        return db('tb_sys_customer_sales_order as o')
            ->leftJoin('tb_sys_dictionary as d', 'd.Dickey', '=', 'o.order_status')
            ->where([
                'o.order_id' => $order_id,
                'o.run_id' => $run_id,
                'd.DicCategory' => 'CUSTOMER_ORDER_STATUS',
            ])
            ->select('o.order_status', 'd.DicValue')
            ->get()
            ->map(
                function ($value) {
                    return (array)$value;
                })
            ->toArray();
    }

    public function onHoldSalesOrder($order_id, $country_id)
    {
        $this->load->language('account/customer_order_import');
        $this->load->model("account/customer_order");
        if ($country_id != AMERICAN_COUNTRY_ID) {
            $json['msg'] = 'This order does not allow onHold!';
            return $json;
        }
        $order_info = DB::table('tb_sys_customer_sales_order')->where('id', $order_id)
            ->get()
            ->map(function ($v) {
                $v->ship_address1 = app('db-aes')->decrypt($v->ship_address1);
                $v->ship_address2 = app('db-aes')->decrypt($v->ship_address2);
                $v->ship_name = app('db-aes')->decrypt($v->ship_name);
                $v->ship_city = app('db-aes')->decrypt($v->ship_city);
                $v->ship_phone = app('db-aes')->decrypt($v->ship_phone);
                $v->email = app('db-aes')->decrypt($v->email);
                $v->bill_address = app('db-aes')->decrypt($v->bill_address);
                $v->bill_name = app('db-aes')->decrypt($v->bill_name);
                $v->bill_city = app('db-aes')->decrypt($v->bill_city);
                return (array)$v;
            })
            ->toArray();
        $order_status = $order_info[0]['order_status'];
        $order_code = $order_info[0]['order_id'];
        $can_on_hold = [CustomerSalesOrderStatus::BEING_PROCESSED, CustomerSalesOrderStatus::LTL_CHECK];
        if (!in_array($order_status, $can_on_hold)) {
            $json['msg'] = 'This order does not allow onHold!';
            return $json;
        }
        $orderStatusName = CustomerSalesOrderStatus::getDescription($order_status);
        $process_code = CommonOrderProcessCode::CANCEL_ORDER;  //操作码 1:修改发货信息,2:修改SKU,3:取消订单
        $status = CommonOrderActionStatus::SUCCESS; //操作状态 1:操作中,2:成功,3:失败
        $run_id = time();
        //订单类型，暂不考虑重发单
        $order_type = 1;
        $create_time = date("Y-m-d H:i:s");
        $before_record = "Order_Id:" . $order_id . ", status:" . $orderStatusName;
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
            if ($is_syncing || $is_in_omd) {
                $json['msg'] = 'Action cannot be completed at this time.This order is being packed.Please send email to service@b2b.gigacloudlogistics.com for assistance.';
                return $json;
            }
            $this->onHoldOrderByOrderId($order_id);
            $this->saveSalesOrderModifyRecord($record_data);
        } else {
            $this->onHoldOrderByOrderId($order_id);
            $this->saveSalesOrderModifyRecord($record_data);
        }
        $json['msg'] = 'Order on hold successfully.';
        return $json;
    }

    public function cancelSalesOrder($order_id)
    {
        $json = [];
        load()->language('account/customer_order_import');
        load()->model("account/customer_order");
        $orderInfo = CustomerSalesOrder::find($order_id);
        $order_status = $orderInfo->order_status;
        $order_code = $orderInfo->order_id;
        $country_id = Customer()->getCountryId();
        // Being Processed 情况下需要和omd联动校验
        $can_cancel = [
            CustomerSalesOrderStatus::TO_BE_PAID,
            CustomerSalesOrderStatus::BEING_PROCESSED,
            CustomerSalesOrderStatus::ON_HOLD,
            CustomerSalesOrderStatus::LTL_CHECK,
            CustomerSalesOrderStatus::ASR_TO_BE_PAID,
        ];
        if (!in_array($order_status, $can_cancel)) {
            $json['error'] = 1;
            $json['msg'] = $this->language->get('error_is_syncing');
            return $json;
        }
        $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($order_id);
        if ($is_syncing) {
            $json['error'] = 1;
            $json['msg'] = $this->language->get('error_is_syncing');
            return $json;
        }
        $omd_store_id = $this->model_account_customer_order->getOmdStoreId($order_id);
        $order_status = CustomerSalesOrderStatus::getDescription($order_status);
        $hasVirtualPayment = $this->model_account_customer_order->hasVirtualPayment($order_id);
        $remove_bind = 1;
        if ($hasVirtualPayment) {
            $remove_bind = 0;
        }
        // 增加了对于jd订单的判断
        $run_id = time();
        $post_data['uuid'] = self::OMD_ORDER_CANCEL_UUID;
        $post_data['runId'] = $run_id;
        $post_data['orderId'] = $order_code;
        $post_data['storeId'] = $omd_store_id;
        $param['apiKey'] = OMD_POST_API_KEY;
        $param['postValue'] = json_encode($post_data);

        $isExportInfo = app(CustomerSalesOrderRepository::class)->calculateSalesOrderIsExportedNumber($order_id);
        if (customer()->isEurope() || customer()->isJapan()) {
            //欧洲和日本的单子只要同步过（在库），则不允许取消
            if ($isExportInfo['is_zk_sync_number'] > 0) {
                return ['error' => 4, 'msg' => $this->language->get('text_zk_cancel_failed')];
            }
        }
        Logger::salesOrder([$order_id, 'info',
            Logger::CONTEXT_VAR_DUMPER => [
                'isExportInfo' => $isExportInfo,
                'param' => $param,
            ],
        ]);
        if ($isExportInfo['is_export_number'] > 0 || $isExportInfo['joy_buyer_status']) {
            $sellerList = app(CustomerRepository::class)->calculateSellerListBySalesOrderId($order_id);
            Logger::salesOrder([$order_id, 'info',
                Logger::CONTEXT_VAR_DUMPER => [
                    'sellerList' => $sellerList,
                ],
            ]);
            $joyBuySellerId = db('tb_sys_setup')->where('parameter_key','JOY_BUY_SELLER_ID')->value('parameter_value');
            $haveJoyBuy = $haveGigaOnsite = $haveOmd = 0;
            foreach ($sellerList as $seller) {
                if ($seller['seller_id'] == $joyBuySellerId){
                    $haveJoyBuy = 1;
                } elseif ($seller['accounting_type'] == CustomerAccountingType::GIGA_ONSIDE) {
                    $haveGigaOnsite = 1; //有卖家需要on_site
                } else {
                    $haveOmd = 1; //有卖家需要omd
                }
            }
            if (($haveGigaOnsite + $haveJoyBuy + $haveOmd ) > 1) {
                $json['error'] = 1;
                $json['msg'] = $this->language->get('error_is_contact_service');
                return $json;
            } elseif ($haveGigaOnsite == 1) {
                $isInOnsite = $this->model_account_customer_order->checkOrderShouldInGigaOnsite($order_id);
                if ($isInOnsite) {
                    ob_end_clean();
                    //保存修改记录
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id'=> $order_id,
                        'order_status'=> $order_status,
                        'order_code' => $order_code,
                        'remove_bind'=>$remove_bind,
                        'run_id'=>$run_id,
                    ]);
                    $gigaResult = app(GigaOnsiteHelper::class)->cancelOrder($order_code,$run_id);
                    if ($gigaResult['code'] == 1) {
                        $json['error'] = 1;
                        $json['msg'] = $this->language->get('text_cancel_wait');
                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $fail_reason = $this->language->get('text_cancel_failed');
                        app(DropshipCancelOrderService::class)->updateCancelModifyLog($log_id, $new_status, $fail_reason);
                        $json['error'] = 2;
                        $json['msg'] = $this->language->get('text_cancel_failed');
                    }
                    return $json;
                }
            } elseif ($haveOmd == 1) {
                $isInOmd = $this->model_account_customer_order->checkOrderShouldInOmd($order_id);
                if ($isInOmd) {
                    // 超大件订单在omd已经存在的情况下，直接不允许用户取消
                    $overSizeTag = app(CustomerSalesOrderRepository::class)->checkOrderContainsOversizeProduct($order_id, $this->config->get('tag_id_oversize'));
                    if ($overSizeTag && $country_id == AMERICAN_COUNTRY_ID) {
                        $json['error'] = 4;
                        $json['msg'] = $this->language->get('text_ltl_cancel_failed');
                        return $json;
                    }
                    // 非美国的订单已经同步到omd
                    ob_end_clean();
                    //保存修改记录
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id'=> $order_id,
                        'order_status'=> $order_status,
                        'order_code' => $order_code,
                        'remove_bind'=>$remove_bind,
                        'run_id'=>$run_id,
                    ]);
                    //取消状态根据回调来
                    $response = $this->sendCurl(OMD_POST_URL, $param);
                    if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                        $json['error'] = 1;
                        $json['msg'] = $this->language->get('text_cancel_wait');
                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $fail_reason = $this->language->get('text_cancel_failed');
                        app(DropshipCancelOrderService::class)->updateCancelModifyLog($log_id, $new_status, $fail_reason);
                        $json['error'] = 2;
                        $json['msg'] = $this->language->get('text_cancel_failed');
                    }
                    return $json;
                }
            } elseif($haveJoyBuy == 1){
                // joy buy

                if(in_array($isExportInfo['joy_buyer_status'],
                    [
                        JoyBuyOrderStatus::ORDER_STATUS_WAIT_PAY,
                        JoyBuyOrderStatus::ORDER_STATUS_PROCESS,
                        JoyBuyOrderStatus::ORDER_STATUS_IN_TRANSIT,
                    ])
                ){
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id'=> $order_id,
                        'order_status'=> $order_status,
                        'order_code' => $order_code,
                        'remove_bind'=>$remove_bind,
                        'run_id'=>$run_id,
                    ]);
                    $joyBuyResult = app(DropshipCancelOrderService::class)->cancelJoyBuyOrder($order_id);
                    if ($joyBuyResult['code'] == 1) {
                        // 执行后续取消逻辑
                        //$json['error'] = 1;
                        //$json['msg'] = $this->language->get('text_cancel_wait');
                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $fail_reason = $this->language->get('text_cancel_failed');
                        app(DropshipCancelOrderService::class)->updateCancelModifyLog($log_id, $new_status, $fail_reason);
                        $json['error'] = 2;
                        $json['msg'] = $this->language->get('text_cancel_failed');
                        return $json;
                    }
                }else if($isExportInfo['joy_buyer_status'] ==  JoyBuyOrderStatus::ORDER_STATUS_COMPLETED){
                    $json['error'] = 2;
                    $json['msg'] = $this->language->get('text_cancel_failed');
                    return $json;
                }

            }else {
                if ($isExportInfo['is_omd_number'] > 0) {
                    // 此处为自动购买订单
                    // 超大件订单在omd已经存在的情况下，直接不允许用户取消
                    $overSizeTag = app(CustomerSalesOrderRepository::class)->checkOrderContainsOversizeProduct($order_id, $this->config->get('tag_id_oversize'));
                    if ($overSizeTag && $country_id == AMERICAN_COUNTRY_ID) {
                        $json['error'] = 4;
                        $json['msg'] = $this->language->get('text_ltl_cancel_failed');
                        return $json;
                    }
                    //保存修改记录
                    $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                        'order_id'=> $order_id,
                        'order_status'=> $order_status,
                        'order_code' => $order_code,
                        'remove_bind'=>$remove_bind,
                        'run_id'=>$run_id,
                    ]);
                    //取消状态根据回调来
                    $response = $this->sendCurl(OMD_POST_URL, $param);
                    if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                        $json['error'] = 1;
                        $json['msg'] = $this->language->get('text_cancel_wait');
                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $fail_reason = $this->language->get('text_cancel_failed');
                        app(DropshipCancelOrderService::class)->updateCancelModifyLog($log_id, $new_status, $fail_reason);
                        $json['error'] = 2;
                        $json['msg'] = $this->language->get('text_cancel_failed');
                    }
                    return $json;
                }
            }
        }

        //直接修改逻辑
        $con = $this->orm->getConnection();
        try {
            //  国别非美国的，切没有被omd同步的直接更改状态16，并且检查是否有绑定关系
            $this->cancelOrderByOrderId($order_id);
            //region 取消费用单
            /** @var FeeOrderService $feeOrderService */
            $feeOrderService = app(FeeOrderService::class);
            $feeOrderService->cancelFeeOrderBySalesOrderId($order_id);
            // 取消保障服务费用单
            $feeOrderService->cancelSafeguardFeeOrderBySalesOrderId($order_id);
            // 仓租费用单退款
            $feeOrderService->refundStorageFeeOrderBySalesOrderId($order_id);
            //endregion
            //  是否需要解除绑定
            if (in_array($order_status, ['Being Processed', 'ASR to be paid'])) {
                if (!$hasVirtualPayment) {
                    $remove_bind_result = $this->model_account_customer_order->removeSalesOrderBind($order_id);
                    // 解除仓租绑定
                    app(StorageFeeService::class)->unbindBySalesOrder([$order_id]);
                    if ($remove_bind_result) {
                        $new_status = CommonOrderActionStatus::SUCCESS;
                        $json['error'] = 0;
                        $json['msg'] = $this->language->get('text_order_cancel_success');
                    } else {
                        $new_status = CommonOrderActionStatus::FAILED;
                        $json['error'] = 2;
                        $json['msg'] = $this->language->get('text_cancel_failed');
                    }
                } else {
                    $new_status = CommonOrderActionStatus::SUCCESS;
                    $json['error'] = 0;
                    $json['msg'] = $this->language->get('text_order_cancel_success');
                }
            } else {
                $new_status = CommonOrderActionStatus::SUCCESS;
                $json['error'] = 0;
                $json['msg'] = $this->language->get('text_order_cancel_success');
            }

            // 释放销售订单囤货预绑定
            if ($orderInfo->order_status == CustomerSalesOrderStatus::TO_BE_PAID) {
                app(BuyerStockService::class)->releaseInventoryLockBySalesOrderPreAssociated([$orderInfo->id], (int)$orderInfo->buyer_id);
            }

            $log_id = app(DropshipCancelOrderService::class)->addCancelModifyLog([
                'order_id'=> $order_id,
                'order_status'=> $order_status,
                'order_code' => $order_code,
                'remove_bind'=>$remove_bind,
                'status'=> $new_status,
                'run_id'=>$run_id,
            ]);

        } catch (Exception $e) {
            $con->rollBack();
            Logger::salesOrder($e,'error');
            $json['error'] = 2;
            $json['msg'] = $e->getMessage();
        }
        return $json;

    }


    /**
     * 批量取消一件代发销售单
     * @param array $order_id_list tb_sys_customer_sales_order表的id数组
     * @return array
     * @throws \Framework\Exception\Exception
     */
    public function batchCancelSalesOrder($order_id_list): array
    {
        load()->model("account/customer_order");
        /** @var FeeOrderService $feeOrderService */
        $feeOrderService = app(FeeOrderService::class);
        $country_id = Customer()->getCountryId();
        // 增加了jd 一件代发的取消逻辑，bp之后批量取消无法取消，所以这部分的逻辑不需要更改
        $bpExists = CustomerSalesOrder::query()
            ->where(['order_status' => CustomerSalesOrderStatus::BEING_PROCESSED])
            ->whereIn('id', $order_id_list)
            ->exists();

        $first_sales_order_id = CustomerSalesOrder::query()
            ->select(['order_id'])
            ->whereIn('id', $order_id_list)
            ->orderBy('create_time', 'desc')
            ->value('order_id');
        $json = [];
        $json['error'] = 1;
        $json['first_sales_order_id'] = $first_sales_order_id;

        $isAutoBuyer = boolval(Customer()->getCustomerExt(1));
        // bp 或者是美国的自动购买需要不能够取消
        if ($bpExists
            || ($isAutoBuyer && $country_id == AMERICAN_COUNTRY_ID)) {
            $json['msg'] = $this->language->get('error_can_cancel');
            return $json;
        }

        //查询 同步状态
        if ($country_id == AMERICAN_COUNTRY_ID) {
            $exists = CustomerSalesOrderLine::query()
                ->whereNotNull('is_exported')
                ->whereIn('header_id', $order_id_list)
                ->exists();
        } else {
            $exists = CustomerSalesOrderLine::query()
                ->whereNotNull('is_synchroed')
                ->whereIn('header_id', $order_id_list)
                ->exists();
        }

        if ($exists) {
            $json['msg'] = $this->language->get('error_can_cancel');
            return $json;
        } else {
            $order_info = CustomerSalesOrder::query()
                ->whereIn('id', $order_id_list)
                ->get()
                ->keyBy('id')
                ->toArray();

            $toBePaidSalesOrderIds = [];
            foreach ($order_id_list as $ks => $vs) {
                $order_status = $order_info[$vs]['order_status'];
                $order_code = $order_info[$vs]['order_id'];
                $order_status = CustomerSalesOrderStatus::getDescription($order_status);
                $remove_bind = 1;
                $run_id = time();
                $status = CommonOrderActionStatus::SUCCESS;
                //  国别非美国的，切没有被omd同步的直接更改状态16，并且检查是否有绑定关系
                $this->cancelOrderByOrderId($vs);
                $feeOrderService->cancelFeeOrderBySalesOrderId($vs);
                // 取消保障服务费用单
                $feeOrderService->cancelSafeguardFeeOrderBySalesOrderId($vs);
                // 仓租费用单退款
                $feeOrderService->refundStorageFeeOrderBySalesOrderId($vs);
                //  是否需要解除绑定
                if (in_array($order_status, ['Being Processed', 'ASR to be paid'])) {
                    $remove_bind_result = $this->model_account_customer_order->removeSalesOrderBind($vs);
                    app(StorageFeeService::class)->unbindBySalesOrder([$vs]);
                    if (!$remove_bind_result) {
                        $status = CommonOrderActionStatus::FAILED;
                    }
                }
                app(DropshipCancelOrderService::class)->addCancelModifyLog([
                    'order_id'=> $vs,
                    'order_status'=> $order_status,
                    'order_code' => $order_code,
                    'remove_bind'=>$remove_bind,
                    'status'=> $status,
                    'run_id'=>$run_id,
                ]);

                if ($order_info[$vs]['order_status'] == CustomerSalesOrderStatus::TO_BE_PAID) {
                    $toBePaidSalesOrderIds[] = $vs;
                }
            }

            // 释放销售订单囤货预绑定
            if ($toBePaidSalesOrderIds) {
                app(BuyerStockService::class)->releaseInventoryLockBySalesOrderPreAssociated($toBePaidSalesOrderIds, customer()->getId());
            }

        }
        $json['error'] = 0;
        $json['msg'] = $this->language->get('text_order_cancel_success');
        return $json;
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
            ->update(['order_status' => CustomerSalesOrderStatus::CANCELED, 'update_time' => date('Y-m-d H:i:s'), 'update_user_name' => $this->customer->getId()]);
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

    public function getLastFailureLog($process_code, $order_id_list, $line_id = null)
    {
        return db('tb_sys_customer_order_modify_log as oml')
            //            ->where(
            //                [
            //                    'oml.status' => 3,
            //                ]
            //            )
            ->whereIn('oml.header_id', $order_id_list)
            ->whereIn('oml.process_code', $process_code)
            ->selectRaw('oml.status,oml.create_time AS operation_time,oml.process_code,oml.before_record AS previous_status,oml.modified_record AS target_status,oml.fail_reason,oml.header_id')
            ->orderBy('oml.process_code', 'desc')
            ->get()
            ->keyBy('header_id')
            ->map(function ($v) {
                if ($v->status == CommonOrderActionStatus::FAILED) {
                    return (array)$v;
                }
            })
            ->toArray();

    }

    public function getOrderModifyFailureLog($process_code, $order_id_list, $line_id = null)
    {
        $ret = [];
        $failure_log_array = $this->getLastFailureLog($process_code, $order_id_list, $line_id);
        if (!empty($failure_log_array)) {
            foreach ($failure_log_array as $key => $log_detail) {

                if (!empty($log_detail)) {
                    switch ($log_detail['process_code']) {
                        case CommonOrderProcessCode::CHANGE_ADDRESS:
                            $log_detail['process_code'] = $this->language->get('text_modify_shipping');
                            break;
                        case CommonOrderProcessCode::CHANGE_SKU:
                            $log_detail['process_code'] = $this->language->get('text_modify_sku');
                            break;
                        case CommonOrderProcessCode::CANCEL_ORDER:
                            $log_detail['process_code'] = $this->language->get('text_order_cancel');
                            break;
                        default:
                            break;
                    }
                    $failure_log_html = "<table class=\"table table-hover\" style=\"text-align: left\"><tbody>";
                    $failure_log_html .= "<tr><th class=\"text-left\">" . $this->language->get('text_table_head_time') . "</th><td class=\"text-left\">" . preg_replace("/\s/", " ", $log_detail['operation_time']) . "</td></tr>";
                    $failure_log_html .= "<tr><th class=\"text-left\">" . $this->language->get('text_table_head_type') . "</th><td class=\"text-left\">" . preg_replace("/\s/", " ", $log_detail['process_code']) . "</td></tr>";
                    $failure_log_html .= "<tr><th class=\"text-left\">" . $this->language->get('text_table_head_before') . "</th><td class=\"text-left\">" . preg_replace("/\s/", " ", $log_detail['previous_status']) . "</td></tr>";
                    $failure_log_html .= "<tr><th class=\"text-left\">" . $this->language->get('text_table_head_target') . "</th><td class=\"text-left\">" . preg_replace("/\s/", " ", $log_detail['target_status']) . "</td></tr>";
                    $failure_log_html .= "<tr><th class=\"text-left\">" . $this->language->get('text_table_head_reason') . "</th><td class=\"text-left\">" . preg_replace("/\s/", " ", $log_detail['fail_reason']) . "</td></tr>";
                    $failure_log_html .= "</tbody></table>";
                    $ret[$key] = htmlentities($failure_log_html);
                }
            }

        }

        return $ret;
    }

    public function getOrderLineInfo($order_id_list, $customer_id, $country_id, $error_info = [])
    {
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $this->load->model('account/order');
        // 由于欧洲补运费的关系 associated 关联通过groupBy会关联补运费产品
        $europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id')) ?? []; // 欧洲补运费产品不允许被更改sku
        $line_list = db('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order_temp as t', 't.id', '=', 'l.temp_id')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
            ->leftJoin('tb_sys_order_associated as a', 'a.sales_order_line_id', '=', 'l.id')
            ->whereIn('l.header_id', $order_id_list)
            ->where(function (Builder $q) use ($europe_freight_product_list) {
                $q->whereNotIn('a.product_id', $europe_freight_product_list)
                    ->orWhereNull('a.product_id');
            })
            ->selectRaw('l.*,o.order_status,a.product_id,t.orders_from')
            ->groupBy('l.id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $orders = [];
        if ($line_list) {
            foreach ($line_list as $key => $value) {
                $orders[$value['header_id']][] = $value;
            }
        }
        $product_info = [];
        foreach ($orders as $ks => $vs) {
            $sum = 0;
            $discountSum = 0;
            foreach ($vs as $key => &$value) {
                $value['is_asr'] = false;
                $value['rma_order_list'] = [];
                $value['purchase_order_list'] = [];
                $value['total_amount'] = [];
                if (intval($value['product_id']) < 1) {
                    $value['product_id'] = $this->getFirstProductId($value['item_code'], $customer_id);
                }
                $value['product_link'] = $this->url->link('product/product', 'product_id=' . $value['product_id']);
                if (isset($product_info[$value['product_id']])) {
                    $value['tag'] = $product_info[$value['product_id']]['tag'];
                    $value['image_show'] = $product_info[$value['product_id']]['image_show'];
                    $value['image_big'] = $product_info[$value['product_id']]['image_big'];
                    $value['product_name_get'] = $product_info[$value['product_id']]['product_name_get'];
                    $value['product_name_all'] = $product_info[$value['product_id']]['product_name_all'];
                } else {
                    $value['tag'] = $this->model_catalog_product->getProductTagHtmlForThumb($value['product_id']);
                    $tmp = db(DB_PREFIX . 'product as p')
                        ->leftJoin(DB_PREFIX . 'product_description as d', 'd.product_id', '=', 'p.product_id')
                        ->where('p.product_id', $value['product_id'])
                        ->select('d.name', 'p.image')
                        ->first();
                    if ($tmp) {
                        $value['product_name_get'] = $tmp->name;
                        $value['product_name_all'] = $tmp->name;
                        $value['image_show'] = $this->model_tool_image->resize($tmp->image, 40, 40);
                        $value['image_big'] = $this->model_tool_image->resize($tmp->image, 150, 150);
                    } else {
                        $value['product_name_get'] = '';
                        $value['product_name_all'] = '';
                        $value['image_show'] = $this->model_tool_image->resize($tmp, 40, 40);
                        $value['image_big'] = $this->model_tool_image->resize($tmp, 150, 150);
                    }
                    $product_info[$value['product_id']]['tag'] = $value['tag'];
                    $product_info[$value['product_id']]['image_show'] = $value['image_show'];
                    $product_info[$value['product_id']]['image_big'] = $value['image_big'];
                    $product_info[$value['product_id']]['product_name_get'] = $value['product_name_get'];
                    $product_info[$value['product_id']]['product_name_all'] = $value['product_name_all'];
                    $product_info[$value['product_id']]['item_code'] = $value['item_code'];
                }

                // 获取销售订单id
                if (in_array($value['order_status'], [CustomerSalesOrderStatus::BEING_PROCESSED, CustomerSalesOrderStatus::CANCELED, CustomerSalesOrderStatus::COMPLETED])) {
                    //有rma purchase id 以及 total amount
                    // rma 是针对于line的申请
                    $value['rma_order_list'] = db('tb_sys_customer_sales_order_line as l')
                        ->crossJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
                        ->crossJoin('tb_sys_order_associated as a', 'a.sales_order_line_id', '=', 'l.id')
                        ->crossJoin(DB_PREFIX . 'yzc_rma_order as r', function ($join) {
                            $join->on('r.from_customer_order_id', '=', 'o.order_id')->on('r.buyer_id', '=', 'o.buyer_id');
                        })
                        ->crossJoin(DB_PREFIX . 'yzc_rma_order_product as rp', function ($join) {
                            $join->on('rp.rma_id', '=', 'r.id')->on('a.product_id', '=', 'rp.product_id');
                        })
                        ->where('l.id', $value['id'])
                        ->groupBy('r.id')
                        ->select('r.id', 'r.rma_order_id')
                        ->get()
                        ->map(function ($vv) {
                            return (array)$vv;
                        })
                        ->toArray();

                    $purchase_info = $this->getPurchaseOrderInfoByLineId($value['id'], $customer_id, $country_id);
                    if ($purchase_info) {
                        $value['purchase_order_list'] = array_unique(array_column($purchase_info['purchase_list'], 'purchase_order_id'));
                        //根据剩余的purchase_order_list 获取是否是含有freight标志
                        $value['purchase_order_freight_tag'] = $this->model_account_order->getPurchaseOrderFreightTag($value['purchase_order_list'], $value['id']);
                        $value['total_amount'] = $purchase_info;
                    } else {
                        $value['purchase_order_list'] = [];
                        $value['purchase_order_freight_tag'] = [];
                        $value['total_amount'] = [];
                    }


                } else {
                    $value['rma_order_list'] = [];
                    $value['purchase_order_list'] = [];
                    $value['total_amount'] = [];
                }
                if ($value['total_amount']) {
                    $sum += $value['total_amount']['sum'];
                    $discountSum += $value['total_amount']['discount_amount'];
                }
                // 获取图片和描述
                if (isset($error_info[$value['header_id']][$value['id']])) {
                    $value['error'] = $error_info[$value['header_id']][$value['id']];
                } else {
                    $value['error'] = '';
                }
            }
            // 还是要获取asr的数据是否已经到位
            $asr = $this->getAsrLineByOrderId($ks, $product_info, $customer_id, $country_id);
            if ($asr) {
                if ($asr['total_amount']) {
                    $sum += $asr['total_amount']['sum'];
                    $discountSum += $asr['total_amount']['discount_amount'];
                }
                $vs[] = $asr;
            }
            $orders[$ks]['discount_sum'] = $discountSum;
            $orders[$ks]['line_list'] = $vs;
            if ($sum) {
                $orders[$ks]['sum'] = $this->currency->formatCurrencyPrice($sum, $this->session->get('currency'));
                $orders[$ks]['final_sum'] = $this->currency->formatCurrencyPrice($sum - $discountSum, $this->session->get('currency'));
            } else {
                $orders[$ks]['sum'] = '';
                $orders[$ks]['final_sum'] = '';
            }

        }
        return $orders;
    }

    public function getAsrLineByOrderId($ks, &$product_info, $customer_id, $country_id)
    {
        $value['rma_order_list'] = [];
        $value['purchase_order_list'] = [];
        $value['total_amount'] = [];
        $value['product_id'] = $this->config->get('signature_service_us_product_id');
        $value['product_link'] = $this->url->link('product/product', 'product_id=' . $value['product_id']);
        $value['id'] = $ks + $value['product_id'];
        $value['is_asr'] = true;
        $purchase_info = $this->getPurchaseOrderAsrInfo($ks, $customer_id, $country_id);
        if ($purchase_info) {
            $value['purchase_order_list'] = array_unique(array_column($purchase_info['purchase_list'], 'purchase_order_id'));
            $value['total_amount'] = $purchase_info;
            $value['qty'] = $purchase_info['qty'];
        } else {
            return false;
        }
        if (isset($product_info[$value['product_id']])) {
            $value['tag'] = $product_info[$value['product_id']]['tag'];
            $value['image_show'] = $product_info[$value['product_id']]['image_show'];
            $value['product_name_get'] = $product_info[$value['product_id']]['product_name_get'];
            $value['product_name_all'] = $product_info[$value['product_id']]['product_name_all'];
            $value['item_code'] = $product_info[$value['product_id']]['item_code'];
        } else {
            $value['tag'] = $this->model_catalog_product->getProductTagHtmlForThumb($value['product_id']);
            $tmp = db(DB_PREFIX . 'product as p')
                ->leftJoin(DB_PREFIX . 'product_description as d', 'd.product_id', '=', 'p.product_id')
                ->where('p.product_id', $value['product_id'])
                ->select('d.name', 'p.image', 'p.sku')
                ->first();
            $value['image_show'] = $this->model_tool_image->resize($tmp->image, 40, 40);
            if ($tmp) {
                //$value['product_name_get'] = truncate($tmp->name, 37);
                $value['product_name_get'] = $tmp->name;
                $value['item_code'] = $tmp->sku;
                $value['product_name_all'] = $tmp->name;
            } else {
                $value['product_name_get'] = '';
                $value['item_code'] = '';
                $value['product_name_all'] = '';
            }
            $product_info[$value['product_id']]['tag'] = $value['tag'];
            $product_info[$value['product_id']]['image_show'] = $value['image_show'];
            $product_info[$value['product_id']]['product_name_get'] = $value['product_name_get'];
            $product_info[$value['product_id']]['product_name_all'] = $value['product_name_all'];
            $product_info[$value['product_id']]['item_code'] = $value['item_code'];
        }
        return $value;

    }

    /**
     * [getRmaStatus description]
     * @param array $order_list 采购订单主键数组
     * @param int $order_status
     * @return bool
     */
    public function getRmaStatus($order_list, $order_status)
    {
        if (in_array($order_status, [
            CustomerSalesOrderStatus::CANCELED,
            CustomerSalesOrderStatus::COMPLETED,
        ])) {
            if (CustomerSalesOrderStatus::CANCELED == $order_status) {
                return db(DB_PREFIX . 'order')
                    ->where('payment_code', PayCode::PAY_VIRTUAL)
                    ->whereIn('order_id', $order_list)
                    ->exists();
            } else {
                return true;
            }

        } else {
            return false;
        }
    }

    public function getPurchaseOrderAsrInfo($order_id, $customer_id, $country_id)
    {
        //获取所有的采购订单的信息
        $tmp = db('tb_sys_order_associated as a')
            //->leftJoin('tb_sys_customer_sales_order_line as l','a.sales_order_line_id','=','l.id')
            ->leftJoin(DB_PREFIX . 'customerpartner_to_customer as c', 'c.customer_id', '=', 'a.seller_id') //店铺
            ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'a.product_id')
            ->leftJoin(DB_PREFIX . 'order as o', 'a.order_id', '=', 'o.order_id')
            ->leftJoin(DB_PREFIX . 'order_product as op', function ($join) {
                $join->on('op.order_id', '=', 'a.order_id')->on('op.product_id', '=', 'a.product_id');
            })
            ->leftJoin(DB_PREFIX . 'product_quote as pq', function ($join) {
                $join->on('pq.order_id', '=', 'a.order_id')->on('pq.product_id', '=', 'a.product_id');
            })
            ->where('a.sales_order_id', $order_id)
            ->where('a.product_id', $this->config->get('signature_service_us_product_id'))
            ->where('a.buyer_id', $customer_id)
            ->select('a.qty', 'a.order_id as purchase_order_id', 'c.screenname', 'op.price as op_price', 'pq.price as pq_price', 'op.poundage', 'op.quantity as op_quantity', 'p.sku', 'op.service_fee_per', 'op.freight_per', 'op.package_fee', 'pq.amount_price_per', 'pq.amount_service_fee_per'
                , 'p.image', 'op.type_id', 'op.agreement_id', 'op.order_product_id', 'a.coupon_amount', 'a.campaign_amount')
            ->groupBy('a.id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        //采购订单的价格需要通过国别来处理
        $isEurope = false;
        if ($this->country->isEuropeCountry($country_id)) {
            $isEurope = true;
        }
        $this->load->model('tool/image');
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if ($tmp) {
            $sum = 0;
            $subtotal = 0;
            $shipping_fee_all = 0;
            $packaging_fee_all = 0;
            $service_fee_all = 0;
            $qty_all = 0;
            $couponAmount = 0;
            $campaignAmount = 0;
            foreach ($tmp as $k => $v) {
                //仅仅美国有议价
                $tmp[$k]['unit_price'] = $v['op_price'] - $v['amount_price_per'];
                if ($isCollectionFromDomicile) {
                    $tmp[$k]['freight_per'] = 0;
                }
                if ($isEurope) {
                    $service_fee_per = $v['service_fee_per'];
                    //获取discount后的 真正的service fee
                    $service_fee_total = ($service_fee_per - (float)$v['amount_service_fee_per']) * $v['qty'];
                    $service_fee_total_pre = ($service_fee_per - (float)$v['amount_service_fee_per']);
                } else {
                    $service_fee_total = 0;
                    $service_fee_total_pre = 0;
                }
                if ($v['amount_price_per'] != null && $v['amount_price_per'] != '0.00') {
                    $tmp[$k]['amount_price_per_show'] = $this->currency->formatCurrencyPrice(-$v['amount_price_per'], $this->session->data['currency']);
                } else {
                    $tmp[$k]['amount_price_per'] = 0;
                }
                if ($v['amount_service_fee_per'] != null && $v['amount_service_fee_per'] != '0.00') {
                    $tmp[$k]['amount_service_fee_per_show'] = $this->currency->formatCurrencyPrice(-$v['amount_service_fee_per'], $this->session->data['currency']);
                } else {
                    $tmp[$k]['amount_service_fee_per'] = 0;
                }
                $tmp[$k]['service_fee'] = sprintf('%.2f', $service_fee_total_pre);
                $tmp[$k]['service_fee_show'] = $this->currency->formatCurrencyPrice($service_fee_total_pre, $this->session->data['currency']);
                $freight = $tmp[$k]['freight_per'] + $tmp[$k]['package_fee'];
                $tmp[$k]['package_fee_tips_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['package_fee'], $this->session->data['currency']);
                $tmp[$k]['freight_tips_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['freight_per'], $this->session->data['currency']);
                $tmp[$k]['freight_show'] = $this->currency->formatCurrencyPrice($freight, $this->session->data['currency']);
                $tmp[$k]['unit_price_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['unit_price'], $this->session->data['currency']);
                $tmp[$k]['total_price'] = sprintf('%.2f', ($tmp[$k]['unit_price'] + $freight) * $v['qty'] + $service_fee_total);
                $tmp[$k]['total_price_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['total_price'], $this->session->data['currency']);
                $tmp[$k]['poundage'] = sprintf('%.2f', $tmp[$k]['poundage'] / $tmp[$k]['op_quantity'] * $v['qty']);
                $tmp[$k]['poundage_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['poundage'], $this->session->data['currency']);
                $sum += $tmp[$k]['total_price'];
                $subtotal += $tmp[$k]['unit_price'] * $v['qty'];
                $shipping_fee_all += $tmp[$k]['freight_per'] * $v['qty'];
                $packaging_fee_all += $tmp[$k]['package_fee'] * $v['qty'];
                $service_fee_all += $service_fee_total;
                $qty_all += $v['qty'];
                $couponAmount += $v['coupon_amount'];
                $campaignAmount += $v['campaign_amount'];
            }
            $res['purchase_list'] = $tmp;
            $res['sum'] = $sum;
            $res['discount_amount'] = $couponAmount + $campaignAmount;
            $res['qty'] = $qty_all;
            $res['total_price'] = $this->currency->formatCurrencyPrice($sum, $this->session->data['currency']);
            $res['final_total_price'] = $this->currency->formatCurrencyPrice($sum - $res['discount_amount'], $this->session->data['currency']);
            $res['shipping_fee'] = $this->currency->formatCurrencyPrice($shipping_fee_all, $this->session->data['currency']);
            $res['packaging_fee'] = $this->currency->formatCurrencyPrice($packaging_fee_all, $this->session->data['currency']);
            $res['service_fee'] = $this->currency->formatCurrencyPrice($service_fee_all, $this->session->data['currency']);
            $res['subtotal'] = $this->currency->formatCurrencyPrice($subtotal, $this->session->data['currency']);
            return $res;

        } else {
            return [];
        }
    }


    public function getPurchaseOrderInfoByLineId($line_id, $customer_id, $country_id)
    {
        $this->load->model('account/order');
        //获取所有的采购订单的信息
        $tmp = db('tb_sys_order_associated as a')
            //->leftJoin('tb_sys_customer_sales_order_line as l','a.sales_order_line_id','=','l.id')
            ->leftJoin(DB_PREFIX . 'customerpartner_to_customer as c', 'c.customer_id', '=', 'a.seller_id') //店铺
            ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'a.product_id')
            ->leftJoin(DB_PREFIX . 'order as o', 'a.order_id', '=', 'o.order_id')
            ->leftJoin(DB_PREFIX . 'order_product as op', function ($join) {
                $join->on('op.order_id', '=', 'a.order_id')->on('op.product_id', '=', 'a.product_id');
            })
            ->leftJoin(DB_PREFIX . 'product_quote as pq', function ($join) {
                $join->on('pq.order_id', '=', 'a.order_id')->on('pq.product_id', '=', 'a.product_id');
            })
            ->where('a.sales_order_line_id', $line_id)
            ->whereNotIn('a.product_id', [$this->config->get('signature_service_us_product_id')])
            ->where('a.buyer_id', $customer_id)
            ->select('a.qty', 'a.order_id as purchase_order_id', 'c.screenname', 'op.price as op_price', 'pq.price as pq_price', 'op.poundage', 'op.quantity as op_quantity', 'p.sku', 'op.service_fee_per', 'op.freight_per', 'op.package_fee', 'pq.amount_price_per', 'pq.amount_service_fee_per'
                , 'p.image', 'op.type_id', 'op.agreement_id', 'op.order_product_id', 'op.product_id', 'a.coupon_amount', 'a.campaign_amount')
            ->groupBy('a.id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        //采购订单的价格需要通过国别来处理
        $isEurope = false;
        if ($this->country->isEuropeCountry($country_id)) {
            $isEurope = true;
        }
        $this->load->model('tool/image');
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if ($tmp) {
            $sum = 0;
            $subtotal = 0;
            $shipping_fee_all = 0;
            $packaging_fee_all = 0;
            $service_fee_all = 0;
            $couponAmount = 0;
            $campaignAmount = 0;
            foreach ($tmp as $k => $v) {
                //仅仅美国有议价
                $tmp[$k]['unit_price'] = $v['op_price'] - $v['amount_price_per'];
                if ($isCollectionFromDomicile) {
                    $tmp[$k]['freight_per'] = 0;
                }
                if ($isEurope) {
                    $service_fee_per = $v['service_fee_per'];
                    //获取discount后的 真正的service fee
                    $service_fee_total = ($service_fee_per - (float)$v['amount_service_fee_per']) * $v['qty'];
                    $service_fee_total_pre = ($service_fee_per - (float)$v['amount_service_fee_per']);
                } else {
                    $service_fee_total = 0;
                    $service_fee_total_pre = 0;
                }
                if ($v['amount_price_per'] != null && $v['amount_price_per'] != '0.00') {
                    $tmp[$k]['amount_price_per_show'] = $this->currency->formatCurrencyPrice(-$v['amount_price_per'], $this->session->data['currency']);
                } else {
                    $tmp[$k]['amount_price_per'] = 0;
                }
                if ($v['amount_service_fee_per'] != null && $v['amount_service_fee_per'] != '0.00') {
                    $tmp[$k]['amount_service_fee_per_show'] = $this->currency->formatCurrencyPrice(-$v['amount_service_fee_per'], $this->session->data['currency']);
                } else {
                    $tmp[$k]['amount_service_fee_per'] = 0;
                }
                $tmp[$k]['service_fee'] = sprintf('%.2f', $service_fee_total_pre);
                $tmp[$k]['service_fee_show'] = $this->currency->formatCurrencyPrice($service_fee_total_pre, $this->session->data['currency']);
                $freight = $tmp[$k]['freight_per'] + $tmp[$k]['package_fee'];
                $tmp[$k]['package_fee_tips_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['package_fee'], $this->session->data['currency']);
                $tmp[$k]['freight_tips_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['freight_per'], $this->session->data['currency']);
                $tmp[$k]['freight_show'] = $this->currency->formatCurrencyPrice($freight, $this->session->data['currency']);
                $tmp[$k]['unit_price_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['unit_price'], $this->session->data['currency']);
                $tmp[$k]['total_price'] = sprintf('%.2f', ($tmp[$k]['unit_price'] + $freight) * $v['qty'] + $service_fee_total);
                $tmp[$k]['total_price_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['total_price'], $this->session->data['currency']);
                $tmp[$k]['poundage'] = sprintf('%.2f', $tmp[$k]['poundage'] / $tmp[$k]['op_quantity'] * $v['qty']);
                $tmp[$k]['poundage_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['poundage'], $this->session->data['currency']);
                $sum += $tmp[$k]['total_price'];
                $subtotal += $tmp[$k]['unit_price'] * $v['qty'];
                $shipping_fee_all += $tmp[$k]['freight_per'] * $v['qty'];
                $packaging_fee_all += $tmp[$k]['package_fee'] * $v['qty'];
                $service_fee_all += $service_fee_total;
                $couponAmount += $v['coupon_amount'];
                $campaignAmount += $v['campaign_amount'];
            }
            $res['purchase_list'] = $tmp;
            $res['sum'] = $sum;
            $res['discount_amount'] = $couponAmount + $campaignAmount;
            $res['final_total_price'] = $this->currency->formatCurrencyPrice($sum - $res['discount_amount'], $this->session->data['currency']);
            $res['total_price'] = $this->currency->formatCurrencyPrice($sum, $this->session->data['currency']);
            $res['shipping_fee'] = $this->currency->formatCurrencyPrice($shipping_fee_all, $this->session->data['currency']);
            $res['packaging_fee'] = $this->currency->formatCurrencyPrice($packaging_fee_all, $this->session->data['currency']);
            $res['service_fee'] = $this->currency->formatCurrencyPrice($service_fee_all, $this->session->data['currency']);
            $res['subtotal'] = $this->currency->formatCurrencyPrice($subtotal, $this->session->data['currency']);
            return $res;

        } else {
            return [];
        }

    }

    public function getOrderErrorInfo($order_id_list, $customer_id, $country_id = AMERICAN_COUNTRY_ID, $boxFlag = null)
    {
        // bp completed 不需要检测
        // 1 to be paid  4 onhold  ltl 64 asr to be paid 128
        $list = db('tb_sys_customer_sales_order as o')
            ->leftJoin('tb_sys_customer_sales_order_line as l', 'o.id', '=', 'l.header_id')
            ->whereIn('o.id', $order_id_list)
            ->whereNotNull('l.id')
            ->select('o.id', 'o.order_id', 'l.id as line_id', 'l.item_code', 'o.ship_phone', 'o.ship_address1', 'o.ship_state', 'o.ship_city'
                , 'o.ship_zip_code', 'o.order_status', 'o.ship_country', 'o.is_international')
            ->get()
            ->map(function ($v) {
                $v->ship_address1 = app('db-aes')->decrypt($v->ship_address1);
                $v->ship_city = app('db-aes')->decrypt($v->ship_city);
                $v->ship_phone = app('db-aes')->decrypt($v->ship_phone);
                return (array)$v;
            })
            ->toArray();
        $ret = [];
        $item_code_privilege = [];
        $item_code_to_product = [];
        foreach ($list as $key => $value) {
            if (in_array($value['order_status'],
                [CustomerSalesOrderStatus::TO_BE_PAID,
                    CustomerSalesOrderStatus::ON_HOLD,
                    CustomerSalesOrderStatus::LTL_CHECK,
                    CustomerSalesOrderStatus::ASR_TO_BE_PAID
                ])) {
                if (isset($ret[$value['id']])) {
                    // 查是否有无产品
                    // 无权限购买
                    // ltl
                    // 全平台无库存 直接sku 查quantity
                    $ret[$value['id']][$value['line_id']] = $this->judgeOrderLineSku($value['item_code'], $customer_id, $value['order_status'], $item_code_privilege, $boxFlag, $item_code_to_product);
                } else {
                    // 检验address
                    $judge_column = ['ship_address1', 'ship_state', 'ship_city', 'ship_zip_code'];
                    foreach ($judge_column as $k => $v) {
                        $s = $this->dealErrorCode($value[$v]);
                        if ($s != false) {
                            $ret[$value['id']]['address_error'] = $this->error[3];
                            break;
                        }
                    }
                    $ret[$value['id']]['ship_country_error'] = '';
                    $ret[$value['id']]['freight_error'] = '';
                    if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                        if ($value['is_international'] && !$this->getInternationalOrder($value['ship_country'], $country_id, $value['ship_zip_code'])) {
                            $ret[$value['id']]['freight_error'] = $this->error[8];
                        }

                        // #31737 免税buyer限制销售订单的地址(是否为非德国的欧盟国家)
                        if ($this->customer->isEuVatBuyer() && $ret[$value['id']]['freight_error'] == ''
                            && (!in_array(strtoupper(trim($value['ship_country'])), CountryCode::getEuropeanUnionMemberCountry()) || strtoupper(trim($value['ship_country'])) == CountryCode::GERMANY)) {
                            $ret[$value['id']]['freight_error'] = $this->error[9];
                        }

                    } else {
                        if (
                            (strtoupper($value['ship_country']) != 'US' && $country_id == AMERICAN_COUNTRY_ID)
                            || (strtoupper($value['ship_country']) != 'JP' && $country_id == JAPAN_COUNTRY_ID)
                        ) {
                            $ret[$value['id']]['ship_country_error'] = $this->error[7];
                        }

                        $inactiveInternationalCountryList = $this->getInactiveInternationalCountryList();
                        if (in_array($country_id, $inactiveInternationalCountryList)) {
                            if ($country_id == self::BRITAIN_COUNTRY_ID) {
                                if (!in_array(strtoupper($value['ship_country']), self::BRITAIN_ALIAS_NAME)) {
                                    $ret[$value['id']]['ship_country_error'] = $this->error[7];
                                }
                            }
                            if ($country_id == self::GERMANY_COUNTRY_ID) {
                                if (strtoupper($value['ship_country']) != 'DE') {
                                    $ret[$value['id']]['ship_country_error'] = $this->error[7];
                                }
                            }
                        }

                    }
                    if (!isset($ret[$value['id']]['address_error'])) {
                        $ret[$value['id']]['address_error'] = '';
                    }
                    $ret[$value['id']][$value['line_id']] = $this->judgeOrderLineSku($value['item_code'], $customer_id, $value['order_status'], $item_code_privilege, $boxFlag, $item_code_to_product);


                }
            }
        }
        return $ret;
    }


    public function getFirstProductId($item_code, $customer_id)
    {
        $ret = db(DB_PREFIX . 'product as p')
            ->leftJoin(DB_PREFIX . 'customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin(DB_PREFIX . 'buyer_to_seller as bts', 'bts.seller_id', '=', 'ctp.customer_id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'bts.seller_id', '=', 'c.customer_id')
            ->where(
                [
                    'p.sku' => $item_code,
                    'bts.buyer_id' => $customer_id,
                    'p.status' => 1,
                    'p.is_deleted' => 0,
                    'c.status' => 1,
                    'p.buyer_flag' => 1,
                    'bts.buy_status' => 1,
                    'bts.buyer_control_status' => 1,
                    'bts.seller_control_status' => 1,
                ]
            )->whereIn('p.product_type', [0, 3])
            ->orderBy('p.product_id', 'desc')
            ->groupBy('p.product_id')
            ->pluck('p.product_id');
        $ret = obj2array($ret);
        if (count($ret) == 1) {
            return $ret[0];
        }
        $this->load->model('catalog/product');
        foreach ($ret as $key => $value) {
            $dm_info = $this->model_catalog_product->getDelicacyManagementInfoByNoView($value, $customer_id);
            if ($dm_info && $dm_info['product_display'] != 0) {
                return $value;
            }

            if ($dm_info == null) {
                return $value;
            }


        }

        return db(DB_PREFIX . 'product')
            ->where(
                [
                    'sku' => $item_code,
                ]
            )
            ->orderBy('product_id', 'desc')
            ->value('product_id');


    }

    public function updateAddressTips($order_id, $status)
    {
        db('tb_sys_customer_sales_order')
            ->where('id', $order_id)
            ->update(['address_tips' => $status]);
    }

    public function updateAddressChangeTips($order_id, $status)
    {
        db('tb_sys_customer_sales_order')
            ->where('id', $order_id)
            ->update(['address_change_tips' => $status]);
    }

    public function judgeOrderLineSku($item_code, $customer_id, $order_status, &$item_code_privilege, $boxFlag = null, &$item_code_to_product)
    {
        $ret['exists_error'] = '';
        $ret['quantity_error'] = '';
        $ret['ltl_error'] = '';
        $ret['purchase_error'] = '';
        load()->model('catalog/product');
        // 验证平台是否存在数据
        if (isset($item_code_to_product[$item_code])) {
            $product_id = $item_code_to_product[$item_code];
        } else {
            $product_id = $this->getFirstProductId($item_code, $customer_id);
            $item_code_to_product[$item_code] = $product_id;
        }
        if($product_id){
            load()->model('customerpartner/DelicacyManagement');
            $delicacy_model = $this->model_customerpartner_DelicacyManagement;
            if (!$delicacy_model->checkIsDisplay($product_id, $customer_id)) {
                $ret['purchase_error'] = $this->error[6];
            }
            //验证全平台数量
            $quantity = db(DB_PREFIX . 'product')->where('sku', $item_code)->count('quantity');
            if ($quantity == 0) {
                $ret['quantity_error'] = $this->error[4];
            }
            // 验证ltl check 下的 信息
            if ($order_status == CustomerSalesOrderStatus::LTL_CHECK) {
                $ltl_status = db(DB_PREFIX . 'product_to_tag')
                    ->where([
                    'product_id' => $product_id,
                    'tag_id' => 1
                    ])
                    ->exists();
                if ($ltl_status) {
                    $ret['ltl_error'] = $boxFlag == 1 ? $this->error[5] : $this->error[0];
                }
            }
            // 无权限购买
            if (isset($item_code_privilege[$item_code])) {
                $purchase_privilege = $item_code_privilege[$item_code];
            } else {
                $purchase_privilege = db(DB_PREFIX . 'product as p')
                    ->leftJoin(DB_PREFIX . 'customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
                    ->leftJoin(DB_PREFIX . 'buyer_to_seller as bts', 'bts.seller_id', '=', 'ctp.customer_id')
                    ->leftJoin(DB_PREFIX . 'customer as c', 'bts.seller_id', '=', 'c.customer_id')
                    ->where([
                        'p.sku' => $item_code,
                        'bts.buyer_id' => $customer_id,
                        'p.status' => 1,
                        'p.is_deleted' => 0,
                        'c.status' => 1,
                        'p.buyer_flag' => 1,
                        'bts.buy_status' => 1,
                        'bts.buyer_control_status' => 1,
                        'bts.seller_control_status' => 1,
                    ])
                    ->whereIn('p.product_type', [0, 3])
                    ->select('p.product_id', 'p.quantity')
                    ->exists();
                $item_code_privilege[$item_code] = $purchase_privilege;
            }
            if (!$purchase_privilege) {
                // 39053 列表页面不展示该错误提示 $boxFlag null时代表列表数据
                $ret['purchase_error'] = is_null($boxFlag) ? '' : $this->error[1];
            }

        }else{
            $ret['exists_error'] = $this->error[2];
        }

        return $ret;
    }

    /**
     * single order upload 验证上传数据
     * @param array $data input输入数据
     * @param string $country_id
     * @param string $scenes 运用场景 all用于校验全部,增加此判断，是用于修改地址的场景只修改部分
     * @return array
     */
    public function verifyOrderUploadInput(array $data, string $country_id, string $scenes = 'all'): array
    {
        $result = [];
        $isLTL = false;
        if ($scenes == 'all') {
            if (trim($data['OrderId']) == '' || strlen($data['OrderId']) > 40) {
                return [
                    'id' => 'OrderId',
                    'info' => 'OrderId must be between 1 and 40 characters!'
                ];
            }
            //校验orderId只能是英文、数字、_、-
            if (!preg_match('/^[_0-9-a-zA-Z]{1,40}$/i', trim($data['OrderId']))) {
                return [
                    'id' => 'OrderId',
                    'info' => 'Order Id error,Must include only letters,numbers , -, _'
                ];
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
                            return [
                                'id' => 'ShippedDate',
                                'info' => 'ShippedDate format error,Please see the instructions.'
                            ];
                        }

                    } else {
                        return [
                            'id' => 'ShippedDate',
                            'info' => 'ShippedDate format error,Please see the instructions.'
                        ];
                    }
                }
            }
            $itemCodes = []; // 搜集订单中的 ItemCode
            //因为有多个B2BItemCode与ShipToQty
            $lineTransferSku = [];
            foreach ($data as $key => $val) {
                if (strpos($key, 'B2BItemCode') !== false) {
                    if (trim($data[$key]) == '' || strlen($data[$key]) > 30) {
                        return [
                            'id' => $key,
                            'info' => 'B2BItemCode must be between 1 and 30 characters!',
                        ];
                    }
                    $lineTransferSku[$key] = $val;

                    if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                        $flag = $this->verifyEuropeFreightSku(trim($data[$key]));
                        if ($flag) {
                            return [
                                'id' => $key,
                                'info' => 'The Additional Fulfillment Fee is automatically calculated by the Marketplace system and added to your order total.',
                            ];
                        }
                    }
                    array_push($itemCodes, $val);
                }

                if (strpos($key, 'ShipToQty') !== false) {
                    if (trim($data[$key]) == '' || !preg_match('/^[0-9]*[1-9][0-9]*$/', trim($data[$key]))) {
                        return [
                            'id' => $key,
                            'info' => 'ShipToQty format error,Please see the instructions.'
                        ];
                    }
                }
            }

            $skus = array_values($lineTransferSku);
            $verifyRet = app(salesOrderSkuValidate::class)->withSkus($skus)->validateSkus();
            if(!$verifyRet['code']){
                return [
                    'id'=>array_search($verifyRet['errorSku'],$lineTransferSku),
                    'info'=> 'The service cannot be shipped as a regular item.'
                ];
            }


            if ($country_id == AMERICAN_COUNTRY_ID) { // 美国保持不变
                if (!DISABLE_SHIP_TO_SERVICE && trim($data['ShipToService']) != '' && strtoupper(trim($data['ShipToService'])) != 'ASR') {
                    return [
                        'id' => 'ShipToService',
                        'info' => 'Type ASR here to require the signature upon delivery.'
                    ];
                }
            } else { // 日本和欧洲取 DeliveryToFBA
                if (isset($data['DeliveryToFBA']) && !in_array($data['DeliveryToFBA'], [0, 1])) {
                    return [
                        'id' => 'DeliveryToFBA',
                        'info' => 'Only supports "Yes" or "No".'
                    ];
                }
            }

            if ($country_id == AMERICAN_COUNTRY_ID) {
                if (trim(strlen($data['ShipFrom'])) > 30) {
                    return [
                        'id' => 'ShipFrom',
                        'info' => 'ShipFrom must be between 0 and 30 characters!'
                    ];
                }
            } else {
                if (trim(strlen($data['SalesPlatform'])) > 20) {
                    return [
                        'id' => 'SalesPlatform',
                        'info' => 'SalesPlatform must be between 0 and 20 characters!'
                    ];
                }
            }

            if (trim(strlen($data['BuyerSkuLink'])) > 50) {
                return [
                    'id' => 'BuyerSkuLink',
                    'info' => 'BuyerSkuLink must be between 0 and 50 characters!'
                ];
            }
            if (trim(strlen($data['OrderDate'])) > 25) {
                return [
                    'id' => 'OrderDate',
                    'info' => 'OrderDate must be between 0 and 25 characters!'
                ];
            }
            if (trim(strlen($data['ShipToServiceLevel'])) > 50) {
                return [
                    'id' => 'ShipToServiceLevel',
                    'info' => 'ShipToServiceLevel must be between 0 and 50 characters!'
                ];
            }
            if (trim(strlen($data['BuyerPlatformSku'])) > 25) {
                //13584 需求，Buyer导入订单时校验ItemCode的录入itemCode去掉首尾的空格，
                //如果ITEMCODE不是由字母和数字组成的，那么提示BuyerItemCode有问题，这个文件不能导入
                //整个上传格式会发生变化
                return [
                    'id' => 'BuyerPlatformSku',
                    'info' => 'BuyerPlatformSku must be between 0 and 25 characters!'
                ];
            }
            if (trim(strlen($data['ShipToAttachmentUrl'])) > 800) {
                return [
                    'id' => 'ShipToAttachmentUrl',
                    'info' => 'ShipToAttachmentUrl must be between 0 and 800 characters!'
                ];
            }
            if (trim(strlen($data['BuyerSkuDescription'])) > 100) {
                return [
                    'id' => 'BuyerSkuDescription',
                    'info' => 'BuyerSkuDescription must be between 0 and 100 characters!'
                ];
            }
            if (trim(strlen($data['BuyerBrand'])) > 30) {
                return [
                    'id' => 'BuyerBrand',
                    'info' => 'BuyerBrand must be between 0 and 30 characters!'
                ];
            }

            $reg3 = '/^(([1-9][0-9]*)|(([0]\.\d{1,2}|[1-9][0-9]*\.\d{1,2})))$/';
            if (trim($data['BuyerSkuCommercial']) != '') {
                if (!preg_match($reg3, $data['BuyerSkuCommercial'])) {
                    return [
                        'id' => 'BuyerSkuCommercial',
                        'info' => 'BuyerSkuCommercial format error,Please see the instructions.'
                    ];
                }
            }
        } else { // 修改地址场景需要获取订单item_code 判断是否为LTL
            $itemCodes = app(CustomerSalesOrderRepository::class)->getItemCodesByHeaderId($data['ShipId']);
        }
        $isLTL = ($country_id == AMERICAN_COUNTRY_ID) ? app(CustomerSalesOrderRepository::class)->isLTL($country_id, $itemCodes) : $isLTL; // 只有美国才有LTL
        if (trim($data['ShipToName']) == '' || strlen($data['ShipToName']) > 40) {
            return [
                'id' => 'ShipToName',
                'info' => 'ShipToName must be between 1 and 40 characters!'
            ];
        }
        if (trim($data['ShipToEmail']) == '' || strlen($data['ShipToEmail']) > 90) {
            return [
                'id' => 'ShipToEmail',
                'info' => 'ShipToEmail must be between 1 and 90 characters!'
            ];
        }
        // #29569 回退 start
        if (trim($data['ShipToPhone']) == '' || strlen($data['ShipToPhone']) > 45) {
            return [
                'id' => 'ShipToPhone',
                'info' => 'ShipToPhone must be between 1 and 45 characters!'
            ];
        }
        // #29569 回退 end

        //102730需求德国国别，StreetAddress字符限制在35个字符，超过时文案提醒：Maximum 35 characters, please fill in again
        if (!$isLTL && $country_id == self::GERMANY_COUNTRY_ID && (trim($data['StreetAddress']) == '' || mb_strlen(trim($data['StreetAddress'])) > $this->config->get('config_b2b_address_len_de'))) {
            return [
                'id' => 'StreetAddress',
                'info' => 'Street Address maximum 35 characters, please fill in again!'
            ];
        }

        if ($country_id == AMERICAN_COUNTRY_ID) { // 美国
            $limit_address_detail_char = $this->config->get('config_b2b_address_len_us1');
        } else if ($country_id == UK_COUNTRY_ID) { // 英国
            $limit_address_detail_char = $this->config->get('config_b2b_address_len_uk');
        } else if ($country_id == DE_COUNTRY_ID) {
            $limit_address_detail_char = $this->config->get('config_b2b_address_len_de'); // 德国4071行已经判断了
        } else {
            $limit_address_detail_char = $this->config->get('config_b2b_address_len_jp');
        }

        if ($isLTL) {
            $limit_address_detail_char = $this->config->get('config_b2b_address_len');
        }
        if (trim($data['StreetAddress']) == '' || StringHelper::stringCharactersLen(trim($data['StreetAddress'])) > $limit_address_detail_char) {
            return [
                'id' => 'StreetAddress',
                'info' => 'Street Address must be between 1 and ' . $limit_address_detail_char . ' characters!'
            ];
        } else {
            //验证P.O.BOXES；PO BOX；PO BOXES；P.O.BOX
            //13573 需求，销售订单地址校验，包含P.O.Box 的地址，不能导入到B2B，提示这个是一个邮箱地址，不能送到
            if (!$isLTL && ($country_id == AMERICAN_COUNTRY_ID)) {
                if (AddressHelper::isPoBox($data['StreetAddress'])) {
                    return [
                        'id' => 'StreetAddress',
                        'info' => 'Street Address in P.O.BOX doesn\'t support delivery,Please see the instructions.'
                    ];
                }
            }
        }

        // #29569 回退 start
        if (trim($data['ShipToPostalCode']) == '' || strlen($data['ShipToPostalCode']) > 18) {
            return [
                'id' => 'ShipToPostalCode',
                'info' => 'Postal code must be between 1 and 18 characters!'
            ];
        }
        //#12959 美国区域邮编组成：数字 -
        if ($country_id == AMERICAN_COUNTRY_ID && !preg_match('/^[0-9-]{1,18}$/i', trim($data['ShipToPostalCode']))) {
            return [
                'id' => 'ShipToPostalCode',
                'info' => 'Postal code must Include only numbers,-.'
            ];
        }
        // #29569 回退 end

        //#7598 日本邮政编码只能是数字和-
        if ($country_id == JAPAN_COUNTRY_ID && !preg_match('/^[0-9-]{1,18}$/i', trim($data['ShipToPostalCode']))) {
            return [
                'id' => 'ShipToPostalCode',
                'info' => 'Must Include only numbers,-'
            ];
        }

        if (trim($data['ShipToCity']) == '' || strlen($data['ShipToCity']) > 30) {
            return [
                'id' => 'ShipToCity',
                'info' => 'ShipToCity must be between 1 and 30 characters!'
            ];
        }

        // #29569回退start
        if ($country_id == AMERICAN_COUNTRY_ID) {
            if (trim($data['ShipToState']) == '' || strlen($data['ShipToState']) > 30) {
                return [
                    'id' => 'ShipToState',
                    'info' => 'ShipToState must be between 1 and 30 characters!'
                ];
            } else {
                $shipToState = $data['ShipToState'] == null ? '' : strtoupper(trim($data['ShipToState']));
                $state = app(SetupRepository::class)->getValueByKey("REMOTE_ARES");
                $state = !is_null($state) ? $state : 'PR,AK,HI,GU,AA,AE,AP,ALASKA,ARMED FORCES AMERICAS,ARMED FORCES EUROPE,ARMED FORCES PACIFIC,GUAM,HAWAII,PUERTO RICO';
                $stateArray = explode(',', $state);
                if (!$isLTL && in_array($shipToState, $stateArray)) {
                    return [
                        'id' => 'ShipToState',
                        'info' => 'ShipToState in PR, AK, HI, GU, AA, AE, AP doesn\'t support delivery,Please see the instructions.'
                    ];
                }
            }
        } else {
            if (isset($data['ShipToState']) && (trim($data['ShipToState']) == '' || strlen($data['ShipToState']) > 30)) {
                return [
                    'id' => 'ShipToState',
                    'info' => 'ShipToState must be between 0 and 30 characters!'
                ];
            }
        }

//        if ($country_id == AMERICAN_COUNTRY_ID) {
//            $stateArr = CountryState::where('country_id', AMERICAN_COUNTRY_ID)->pluck('abbr')->toArray();
//            if (empty(trim($data['ShipToState'])) || ! in_array(trim($data['ShipToState']), $stateArr)) {
//                return [
//                    'id' => 'ShipToState',
//                    'info' => "The 'State' should be in an abbreviation or the delivery service is not available for the State filled in."
//                ];
//            }
//            // 美国继续限制收件地址
//            if (! preg_match('/^[0-9a-zA-Z,\. ]{1,}$/i', trim($data['StreetAddress']))) {
//                return [
//                    'id' => 'StreetAddress',
//                    'info' => 'Only letters, digits, spaces, and commas or dots in English are allowed.'
//                ];
//            }
//            // 美国继续限制邮编
//            if (trim($data['ShipToPostalCode']) == '' ||! preg_match('/^[0-9]{5}$/i', trim($data['ShipToPostalCode']))) {
//                return [
//                    'id' => 'ShipToPostalCode',
//                    'info' => 'Only a 5-digit number is allowed. You can delete ‘-’ and the last 4 digits.'
//                ];
//            }
//            // 美国限制国家为US
//            if (strtoupper(trim($data['ShipToCountry'])) != 'US') {
//                return [
//                    'id' => 'ShipToCountry',
//                    'info' => "Shipping Country must be 'US'."
//                ];
//            }
//            // 美国限制电话号码
//            if (trim($data['ShipToPhone']) == '' || ! preg_match('/^[0-9-]{10,15}$/i', trim($data['ShipToPhone']))) {
//                return [
//                    'id' => 'ShipToPhone',
//                    'info' => "Limited to 10-15 characters, and only digits and ‘-’ are allowed."
//                ];
//            }
//        } else {
//            if (isset($data['ShipToState']) && (trim($data['ShipToState']) == '' || strlen($data['ShipToState']) > 30)) {
//                return [
//                    'id' => 'ShipToState',
//                    'info' => 'ShipToState must be between 0 and 30 characters!'
//                ];
//            }
//            if (trim($data['ShipToPostalCode']) == '' || strlen($data['ShipToPostalCode']) > 18) {
//                return [
//                    'id' => 'ShipToPostalCode',
//                    'info' => 'Postal code must be between 1 and 18 characters!'
//                ];
//            }
//            if (trim($data['ShipToPhone']) == '' || strlen($data['ShipToPhone']) > 45) {
//                return [
//                    'id' => 'ShipToPhone',
//                    'info' => 'ShipToPhone must be between 1 and 45 characters!'
//                ];
//            }
//        }
        // #29569回退end

        if ($country_id == JAPAN_COUNTRY_ID) {
            //#7598 日本国别，State是否存在
            if (!$this->existsByCountyAndCountry(trim($data['ShipToState']), JAPAN_COUNTRY_ID)) {
                return [
                    'id' => 'ShipToState',
                    'info' => "The State filled in is incorrect. It should be like '京都府', '滋賀県'."
                ];
            }
            //校验ShipToCity和ShipToState两个字段不可重复
            if (trim($data['ShipToState']) == trim($data['ShipToCity'])) {
                return [
                    'id' => 'ShipToCity',
                    'info' => 'City must differ from the state.'
                ];
            }
            //#7598 日本国别，City中不能包含State
            if (false !== strpos(trim($data['ShipToCity']), trim($data['ShipToState']))) {
                return [
                    'id' => 'ShipToCity',
                    'info' => 'City must differ from the state.'
                ];
            }
        }

        // #29569回退start
        if ($country_id == AMERICAN_COUNTRY_ID) {
            if (strtoupper(trim($data['ShipToCountry'])) != 'US') {
                return [
                    'id' => 'ShipToCountry',
                    'info' => "Shipping Country must be 'US'."
                ];
            }
        }
        // #29569回退end

        if ($country_id == JAPAN_COUNTRY_ID) {
            if (strtoupper(trim($data['ShipToCountry'])) != 'JP') {
                return [
                    'id' => 'ShipToCountry',
                    'info' => 'This order may only be shipped within its country of origin.',
                ];
            }
        }

        $inactiveInternationalCountryList = $this->getInactiveInternationalCountryList();
        if (in_array($country_id, $inactiveInternationalCountryList)) {
            if ($country_id == self::BRITAIN_COUNTRY_ID) {
                if (!in_array(strtoupper($data['ShipToCountry']), self::BRITAIN_ALIAS_NAME)) {
                    return [
                        'id' => 'ShipToCountry',
                        'info' => 'This order may only be shipped within its country of origin.',
                    ];
                }
            }
            if ($country_id == self::GERMANY_COUNTRY_ID) {
                if (strtoupper($data['ShipToCountry']) != 'DE') {
                    return [
                        'id' => 'ShipToCountry',
                        'info' => 'This order may only be shipped within its country of origin.',
                    ];
                }
            }

        }

        if (isset($data['OrderComments']) && strlen($data['OrderComments']) > 1500) {
            return [
                'id' => 'OrderComments',
                'info' => 'OrderComments must be between 0 and 1500 characters!'
            ];
        }

        return [];
    }

    /**
     * [verifyEuropeFreightSku description]
     * @param string $sku
     * @return bool
     */
    public function verifyEuropeFreightSku(string $sku)
    {
        return db(DB_PREFIX . 'product')
            ->where([
                'sku' => $sku,
                'product_type' => self::PRODUCT_TYPE_FREIGHT,
            ])
            ->exists();
    }

    /**
     * 判断订单是否为LTL
     * @param array $itemCodes
     * @return bool
     */
    public function isLTL(array $itemCodes)
    {
        return Product::query()->alias('p')->joinRelations('tags as t')
            ->where(['t.tag_id' => 1])
            ->whereIn('p.sku', $itemCodes)->exists();
    }

    public function dropShipping($countryId, $streetAddress, $isLtl)
    {
        $res = [];
        $streetAddress = trim($streetAddress);
        $streetAddressLen = mb_strlen($streetAddress);
        if ($isLtl) {
            if ($streetAddressLen > 100) {
                $res = [
                    'id' => 'StreetAddress',
                    'info' => 'Street Address maximum 100 characters, please fill in again!'
                ];
            }
        } else {
            $stintAddressLenRelationCountry = $this->stintAddressLenRelationCountry($countryId, $streetAddressLen);
            if (!empty($stintAddressLenRelationCountry)) {
                return $stintAddressLenRelationCountry;
            }
        }
    }

    private function stintAddressLenRelationCountry($countryId, $streetAddressLen)
    {
        $res = [];
        switch ($countryId) {
            case AMERICAN_COUNTRY_ID:
                if ($streetAddressLen > 56) {
                    $res = [
                        'id' => 'StreetAddress',
                        'info' => 'US Street Address maximum 56 characters, please fill in again!'
                    ];
                }
                break;
            case UK_COUNTRY_ID :
                if ($streetAddressLen > 56) {
                    $res = [
                        'id' => 'StreetAddress',
                        'info' => 'UK Street Address maximum 56 characters, please fill in again!'
                    ];
                }
                break;
            case DE_COUNTRY_ID :
                if ($streetAddressLen > 35) {
                    $res = [
                        'id' => 'StreetAddress',
                        'info' => 'DE Street Address maximum 35 characters, please fill in again!'
                    ];
                }
                break;
            default:
                if ($streetAddressLen > 100) {
                    $res = [
                        'id' => 'StreetAddress',
                        'info' => 'JP Street Address maximum 100 characters, please fill in again!'
                    ];
                }
        }
        return $res;
    }

    /**
     * save single order upload
     * @param array $value 填写的字段
     * @param string $country_id
     * @param int $customer_id
     * @return array
     * @throws Exception
     */
    public function saveSingleOrderUpload($value, $country_id, int $customer_id): array
    {
        //检验传递过来的数据
        $verifyRet = $this->verifyOrderUploadInput($value, $country_id);
        if ($verifyRet) {
            $verify[] = $verifyRet;
            return ['error' => 1, 'info' => $verify];
        }

        if ($country_id == AMERICAN_COUNTRY_ID
            && isset($value['ShipFrom'])) {
            $value['SalesPlatform'] = $value['ShipFrom'];
        }

        //存在一条订单包含多个sku
        $itemCode_arr = [];
        $itemCode_value = [];
        $qty_arr = [];
        foreach ($value as $key => $val) {
            if (strpos($key, 'B2BItemCode') !== false) {
                if (in_array(strtoupper(trim($val)), $itemCode_value)) {
                    $exists_item_code[] = ['id' => $key, 'info' => 'The same B2BItemCode already exists'];
                    return ['error' => 1, 'info' => $exists_item_code];
                }
                array_push($itemCode_value, strtoupper(trim($val)));
                array_push($itemCode_arr, $key);
            }

            if (strpos($key, 'ShipToQty') !== false) {
                array_push($qty_arr, $key);
            }
        }
        $itemCode_arr = array_unique($itemCode_arr);
        $qty_arr = array_unique($qty_arr);
        if (count($itemCode_arr) != count($qty_arr)) {
            return ['error' => 2];
        }
        //对于签收服务费，只有美国有签收服务费
        if ($country_id != AMERICAN_COUNTRY_ID) {
            $value['ShipToService'] = '';
        } else {
            $value['DeliveryToFBA'] = ''; // 只有欧洲和日本才有此内容
            if (isset($value['ShipToService']) && strtoupper(trim($value['ShipToService'])) == 'ASR') {
                $value['ShipToService'] = 'ASR';
            }
        }

        if (DISABLE_SHIP_TO_SERVICE) {
            $value['ShipToService'] = '';
        }

        //检验订单号是否存在
        $checkResult = $this->judgeCommonOrderIsExist(trim($value['OrderId']), $customer_id);
        if ($checkResult) {
            $exists_order[] = ['id' => 'OrderId', 'info' => 'Order ID is already exist. Order ID cannot be duplicated.'];
            return ['error' => 1, 'info' => $exists_order];
        }

        try {
            $this->orm->getConnection()->beginTransaction();
            //处理并重新赋值
            $run_id = msectime();
            $date_time = date('Y-m-d H:i:s');
            $orderArr = [];
            if ($country_id == AMERICAN_COUNTRY_ID) { // 美国区域 国家字段 US 转为大写保存
                $value['ShipToCountry'] = strtoupper(trim($value['ShipToCountry']));
            }
            foreach ($itemCode_arr as $key => $val) {
                $orderArr[] = [
                    "orders_from" => $value['SalesPlatform'] == '' ? "" : $value['SalesPlatform'],
                    "order_id" => $value['OrderId'] == '' ? null : trim($value['OrderId']),
                    "email" => $value['ShipToEmail'] == '' ? null : $value['ShipToEmail'],
                    "order_date" => $value['OrderDate'] == '' ? date('Y-m-d H:i:s') : $value['OrderDate'],
                    "bill_name" => $value['ShipToName'] == '' ? null : $value['ShipToName'],
                    "bill_address" => $value['StreetAddress'] == '' ? null : trim($value['StreetAddress']),
                    "bill_city" => $value['ShipToCity'] == '' ? null : trim($value['ShipToCity']),
                    "bill_state" => $value['ShipToState'] == '' ? null : trim($value['ShipToState']),
                    "bill_zip_code" => $value['ShipToPostalCode'] == '' ? null : trim($value['ShipToPostalCode']),
                    "bill_country" => $value['ShipToCountry'] == '' ? null : trim($value['ShipToCountry']),
                    "ship_name" => $value['ShipToName'] == '' ? null : $value['ShipToName'],
                    "ship_address1" => $value['StreetAddress'] == '' ? null : trim($value['StreetAddress']),
                    "ship_address2" => null,
                    "line_item_number" => ($key + 1),
                    "ship_city" => $value['ShipToCity'] == '' ? null : trim($value['ShipToCity']),
                    "ship_state" => $value['ShipToState'] == '' ? null : trim($value['ShipToState']),
                    "ship_zip_code" => $value['ShipToPostalCode'] == '' ? null : trim($value['ShipToPostalCode']),
                    "ship_country" => $value['ShipToCountry'] == '' ? null : trim($value['ShipToCountry']),
                    "ship_phone" => $value['ShipToPhone'] == '' ? null : trim($value['ShipToPhone']),
                    "item_code" => $value[$itemCode_arr[$key]] == '' ? null : strtoupper(trim($value[$itemCode_arr[$key]])),
                    "alt_item_id" => $value['BuyerSkuLink'] == '' ? null : $value['BuyerSkuLink'],
                    "product_name" => $value['BuyerSkuDescription'] == '' ? 'product name' : $value['BuyerSkuDescription'],
                    "qty" => $value[$qty_arr[$key]] == '' ? null : $value[$qty_arr[$key]],
                    "item_price" => $value['BuyerSkuCommercial'] == '' ? 1 : $value['BuyerSkuCommercial'],
                    "item_unit_discount" => null,
                    "item_tax" => null,
                    "discount_amount" => null,
                    "tax_amount" => null,
                    "ship_amount" => null,
                    "order_total" => 1,
                    "payment_method" => null,
                    "ship_company" => null,
                    "ship_method" => $value['ShipToService'] == '' ? null : strtoupper($value['ShipToService']),
                    "delivery_to_fba" => empty($value['DeliveryToFBA']) ? 0 : 1,
                    "ship_service_level" => $value['ShipToServiceLevel'] == '' ? null : $value['ShipToServiceLevel'],
                    "brand_id" => $value['BuyerBrand'] == '' ? null : $value['BuyerBrand'],
                    "customer_comments" => (!isset($value['OrderComments']) || $value['OrderComments'] == '') ? null : $value['OrderComments'],
                    "shipped_date" => $value['ShippedDate'] == '' ? null : trim($value['ShippedDate']),//13195OrderFulfillment订单导入模板调优
                    "ship_to_attachment_url" => $value['ShipToAttachmentUrl'] == '' ? null : $value['ShipToAttachmentUrl'],
                    //"seller_id"          => $sellerId,
                    "buyer_id" => $customer_id,
                    "run_id" => $run_id,
                    "create_user_name" => $customer_id,
                    "create_time" => $date_time,
                    "update_user_name" => null
                ];
            }
            // 插入临时表
            $this->saveCustomerSalesOrderTemp($orderArr);
            // 根据run_id获取上步插入的临时表数据
            $orderTempArr = $this->findCustomerSalesOrderTemp($run_id, $customer_id);
            $customerSalesOrderArr = [];
            //获取seq的值(云资产OrderId)
            $yzc_order_id_number = $this->getYzcOrderIdNumber();
            foreach ($orderTempArr as $key => $value) {
                //根据order_id来进行合并
                $order_id = $value['order_id'];
                $salesOrder = $this->getCommonOrderColumnNameConversion($value, 1, $customer_id, $country_id, 0);
                if (!isset($customerSalesOrderArr[$order_id])) {
                    $yzc_order_id_number++;
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
            $this->updateYzcOrderIdNumber($yzc_order_id_number);
            $insert_order_id = $this->insertCustomerSalesOrderAndLine($customerSalesOrderArr);

            //初始的时候order_status 为 1
            $order_id_str = DB::table('tb_sys_customer_sales_order_line as l')
                ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
                ->leftJoin(DB_PREFIX . 'product as p', 'p.sku', '=', 'l.item_code')
                ->leftJoin(DB_PREFIX . 'product_to_tag as ptt', 'ptt.product_id', '=', 'p.product_id')
                ->where([
                    'o.run_id' => $run_id,
                    'o.buyer_id' => $customer_id,
                    //                    'o.id'=> $insert_order_id,
                    'ptt.tag_id' => 1,
                ])
                ->selectRaw('group_concat(distinct l.header_id) as order_id_str')
                ->first();
            if ($order_id_str) {
                DB::table('tb_sys_customer_sales_order')
                    ->whereIn('id', [$order_id_str->order_id_str])
                    ->update([
                        'order_status' => CustomerSalesOrderStatus::LTL_CHECK,
                        'update_time' => date('Y-m-d H:i:s'),
                        'update_user_name' => $customer_id,
                    ]);
            }

            $this->orm->getConnection()->commit();
        } catch (Exception $e) {
            $this->log->write('上传订单错误，something wrong happened. Please try it again.');
            $this->log->write($e);
            $this->orm->getConnection()->rollBack();
            return ['error' => 2];
        }

        $is_all_ltl_sku = $this->judgeIsAllLtlSku($run_id, $customer_id);
        // 校验zip_code 和 ship_to_country 是否有报价
        if (in_array($country_id, EUROPE_COUNTRY_ID) && isset($salesOrder['is_international']) && $salesOrder['is_international']) {
            $notice = $this->getInternationalOrder($salesOrder['ship_country'], $country_id, $salesOrder['ship_zip_code']) ? false : true;
        } else {
            $notice = false;
        }

        return [
            'error' => 0,
            'insert_order_id' => $insert_order_id,
            'is_all_ltl_sku' => $is_all_ltl_sku['is_all_ltl'],
            'notice' => $notice, // 国际单的提示
        ];
    }

    public function getInternationalOrder($country_code, $country_id, $zipCode)
    {
        if (in_array(strtoupper($country_code), self::BRITAIN_ALIAS_NAME)) {
            $country_code = self::BRITAIN_ALIAS_NAME[0];
        }

        /** @var ModelExtensionModuleEuropeFreight $europeFreightModel */
        $europeFreightModel = load()->model('extension/module/europe_freight');
        $toId = $europeFreightModel->getCountryIdByZipCode(get_need_string($zipCode, [' ', '-', '*', '_']), $country_id, $country_code);
        return db('tb_sys_international_order')
            ->where([
                'country_code' => $country_code,
                'country_id' => $country_id,
            ])
            ->when($toId, function ($query) use ($toId) {
                $query->where('country_code_mapping_id', $toId);
            })
            ->exists();
    }

    /**
     * 获取提示消息列表
     * @param int $customer_id
     * @return array 提示信息
     */
    public function getAllOrderError(int $customer_id): array
    {
        $limit_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        //获取所有订单消息
        $ret = $this->querySalesOrder($customer_id, $data = [], $limit_date);
        $list = $ret->select([
            'c.id', 'c.order_id', 'c.order_status', 'c.ship_phone', 'c.ship_address1', 'c.ship_state', 'c.ship_city', 'c.ship_zip_code', 'c.orders_from',
            't.status', 't.TrackingNumber',
            'l.id as line_id', 'l.item_code',
            'cn.CarrierName',
            'order.delivery_type'
        ])
            ->leftJoin('tb_sys_customer_sales_order_tracking as t',
                [
                    'c.order_id' => 't.SalesOrderId',
                    'l.id' => 't.SalerOrderLineId'
                ]
            )
            ->leftJoin('tb_sys_carriers as cn', 'cn.CarrierID', '=', 't.LogisticeId')
            ->leftJoin('tb_sys_order_associated as sos', 'sos.sales_order_id', '=', 'c.id')
            ->leftJoin('oc_order as order', 'order.order_id', '=', 'sos.order_id')
            ->groupBy(['c.id'])
            ->get()
            ->map(function ($item) {
                $item->ship_address1 = app('db-aes')->decrypt($item->ship_address1);
                $item->ship_city = app('db-aes')->decrypt($item->ship_city);
                $item->ship_phone = app('db-aes')->decrypt($item->ship_phone);
                return (array)$item;
            })
            ->toArray();

        //提示列表
        $all_error_arr = [];
        foreach ($list as $key => $value) {
            //判断是不是云送仓
            if ($value['delivery_type'] != 2) {
                $orders_form = $value['orders_from'] ? '(' . $value['orders_from'] . ')' : '';
                $trackingNumbe = $value['TrackingNumber'] ? $value['TrackingNumber'] : '';
                $carrierName = $value['CarrierName'] ? '(' . $value['CarrierName'] . ')' : '';
                //完成与取消不用提醒
                if ($value['order_status'] != CustomerSalesOrderStatus::COMPLETED && $value['order_status'] != CustomerSalesOrderStatus::CANCELED) {
                    //1.存在无效运单号的销售单,订单Complete后不再提醒
                    if ($value['status'] == 0 && $value['order_status'] > CustomerSalesOrderStatus::TO_BE_PAID && $trackingNumbe != '') {
                        //$all_error_arr[] = 'OrderID: ' . $value['order_id'] . $orders_form . ',Tracking Number' . $trackingNumbe . $carrierName . ' is invalid';
                        $all_error_arr[] = 'OrderID: ' . $value['order_id'] . $orders_form . ',Tracking Number' . $trackingNumbe . ' is invalid';
                    }
                    //2.LTL产品待发邮件 6-24新需求不做了
                    //                if ($value['order_status']==CustomerSalesOrderStatus::LTL_CHECK) {
                    //                    $all_error_arr[] = 'OrderID: ' . $value['order_id'] .$orders_form.',LTL prodauct must to be send confirmation Email to Gigacloud，then you can add your LTL products to your cart for purchase.';
                    //                }
                    //3.On Hold状态提醒
                    if ($value['order_status'] == CustomerSalesOrderStatus::ON_HOLD) {
                        //$all_error_arr[] = 'OrderID: ' . $value['order_id'] . $orders_form . ' be about to on hold so as to do nothing.Please release it first then to purchase it.';
                        $all_error_arr[] = 'OrderID: ' . $value['order_id'] . ' no activity in the past 7 days, changed status to On Hold. Eligible for purchase after the hold has been lifted.';
                    }
                    //4.修改地址提醒,检验address
                    if ($value['order_status'] > CustomerSalesOrderStatus::TO_BE_PAID) {
                        $judge_column = ['ship_address1', 'ship_state', 'ship_city', 'ship_zip_code'];
                        foreach ($judge_column as $k => $v) {
                            $s = $this->dealErrorCode($value[$v]);
                            if ($s != false) {
                                $all_error_arr[] = 'OrderID: ' . $value['order_id'] . $orders_form . ' Address abnormal. Please modification address in time';
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $all_error_arr;
    }

    /**
     * 获取订单地址
     * @param int $order_id
     * @return array
     */
    public function orderAddress(int $order_id): array
    {

        $info = db('tb_sys_customer_sales_order')
            ->select([
                'id',
                'ship_name',
                'email',
                'ship_phone',
                'ship_address1',
                'ship_zip_code',
                'ship_city',
                'ship_state',
                'ship_country',
                'customer_comments'
            ])
            ->where('id', $order_id)
            ->first();
        $info = obj2array($info);
        if (!empty($info)) {
            $info['ship_address1'] = app('db-aes')->decrypt($info['ship_address1']);
            $info['ship_city'] = app('db-aes')->decrypt($info['ship_city']);
            $info['ship_name'] = app('db-aes')->decrypt($info['ship_name']);
            $info['ship_phone'] = app('db-aes')->decrypt($info['ship_phone']);
            $info['email'] = app('db-aes')->decrypt($info['email']);
        }

        return $info;
    }

    public function getCountryList($keyword, $country_id)
    {
        $inactiveInternationalCountryList = $this->getInactiveInternationalCountryList();
        if (in_array($country_id, EUROPE_COUNTRY_ID)) {
            if (in_array($country_id, $inactiveInternationalCountryList)) {
                $list = [];
            } else {
                $list = db('tb_sys_international_order')
                    ->where('country_id', $country_id)
                    ->where('country_code', 'like', "%{$keyword}%")
                    ->selectRaw('country_code as name,country_code_mapping_id as country_id')
                    ->groupBy('country_code')
                    ->get()
                    ->map(function ($v) {
                        return (array)$v;
                    })
                    ->toArray();
            }

            $first = [];
            if ($country_id == self::GERMANY_COUNTRY_ID) {
                if (stripos('DE', $keyword) !== false) {
                    $first = [
                        [
                            'country_id' => $country_id,
                            'name' => 'DE',
                        ]
                    ];
                }

            } elseif ($country_id == self::BRITAIN_COUNTRY_ID) {
                foreach (self::BRITAIN_ALIAS_NAME as $key => $value) {
                    if (stripos($value, $keyword) !== false) {
                        $first = [
                            [
                                'country_id' => $country_id,
                                'name' => $value,
                            ],
                        ];
                    }
                }
            }
            return array_merge($first, $list);

        } else {
            if (AMERICAN_COUNTRY_ID == $country_id) {
                if (stripos('US', $keyword) !== false) {
                    return [
                        [
                            'country_id' => $country_id,
                            'name' => 'US',
                        ]
                    ];
                }
                return [];
            } elseif (JAPAN_COUNTRY_ID == $country_id) {
                if (stripos('JP', $keyword) !== false) {
                    return [
                        [
                            'country_id' => $country_id,
                            'name' => 'JP',
                        ]
                    ];
                }
                return [];
            }
        }
    }

    /**
     * @param $value
     * @param int $country_id
     * @param int $customer_id
     * @param int|null $group_id
     * @return array
     * @throws
     */
    public function updateOrderAddress($value, $country_id, $customer_id, $group_id)
    {
        //检验信息
        $this->load->language('account/customer_order_import');
        $omd_order_sku_uuid = self::OMD_ORDER_ADDRESS_UUID;
        if ($this->verifyOrderUploadInput($value, $country_id, '')) {
            $verify[] = $this->verifyOrderUploadInput($value, $country_id, '');
            return ['error' => 1, 'info' => $verify];
        }
        // 美国区域 国家字段 转换为大写
        if ($country_id == AMERICAN_COUNTRY_ID) {
            $value['ShipToCountry'] = strtoupper(trim($value['ShipToCountry']));
        }
        date_default_timezone_set('America/Los_Angeles');
        $order_date_hour = date("G");

        // 验证订单是否正在同步
        $is_syncing = $this->model_account_customer_order->checkOrderIsSyncing($value['ShipId']);
        if ($is_syncing) {
            return ['error' => 3, 'info' => $this->language->get('error_is_syncing')];
        }
        $param = [];
        $post_data = [];
        $record_data = [];
        $omd_store_id = $this->model_account_customer_order->getOmdStoreId($value['ShipId']);
        $current_order_info = $this->model_account_customer_order->getCurrentOrderInfoByHeaderId($value['ShipId']);
        if (isset($current_order_info) && sizeof($current_order_info) == 1) { // && isset($omd_store_id)
            $order_info = current($current_order_info);
            // group_id 获取不到，可能是由于外部调用接口，非buyer登录的情况
            if (empty($group_id)) {
                $buyer_info = $this->model_account_customer_order->getSalesOrderBuyerInfo($value['ShipId']);
                if (isset($buyer_info['customer_group_id'])) {
                    $group_id = $buyer_info['customer_group_id'];
                }
            }

            $process_code = CommonOrderProcessCode::CHANGE_ADDRESS;
            $status = CommonOrderActionStatus::PENDING;
            $run_id = time();
            $header_id = $order_info['header_id'];
            $order_id = $order_info['order_id'];
            $order_type = 1;
            $create_time = date("Y-m-d H:i:s");
            $before_record = "Order_Id:" . $order_id . " ShipToName:" . app('db-aes')->decrypt($order_info['ship_name'])
                . " ShipToEmail:" . app('db-aes')->decrypt($order_info['email']) . " ShipToPhone:" . app('db-aes')->decrypt($order_info['ship_phone']) . " ShipToAddressDetail:" . app('db-aes')->decrypt($order_info['ship_address1'])
                . " ShipToCity:" . $order_info['ship_city'] . " ShipToState:" . $order_info['ship_state'] . " ShipToPostalCode:" . $order_info['ship_zip_code']
                . " ShipToCountry:" . $order_info['ship_country'] . " OrderComments:" . $order_info['customer_comments'];
            $modified_record = "Order_Id:" . $order_id . " ShipToName:" . trim($value['ShipToName'])
                . " ShipToEmail:" . trim($value['ShipToEmail']) . " ShipToPhone:" . trim($value['ShipToPhone']) . " ShipToAddressDetail:" . trim($value['StreetAddress'])
                . " ShipToCity:" . trim($value['ShipToCity']) . " ShipToState:" . trim($value['ShipToState']) . " ShipToPostalCode:" . trim($value['ShipToPostalCode'])
                . " ShipToCountry:" . trim($value['ShipToCountry']) . " OrderComments:" . $value['OrderComments'];


            // omd 固定数据格式
            $shipData['name'] = trim($value['ShipToName']);
            $shipData['email'] = trim($value['ShipToEmail']);
            $shipData['phone'] = trim($value['ShipToPhone']);
            $shipData['address'] = trim($value['StreetAddress']);
            $shipData['city'] = trim($value['ShipToCity']);
            $shipData['state'] = trim($value['ShipToState']);
            $shipData['code'] = trim($value['ShipToPostalCode']);
            $shipData['country'] = trim($value['ShipToCountry']);
            $shipData['comments'] = $value['OrderComments'];

            $post_data['uuid'] = $omd_order_sku_uuid;
            $post_data['runId'] = $run_id;
            $post_data['orderId'] = $order_id;
            $post_data['storeId'] = $omd_store_id;
            $post_data['shipData'] = $shipData;
            $param['apiKey'] = OMD_POST_API_KEY;
            $param['postValue'] = json_encode($post_data);

            $record_data['process_code'] = $process_code;
            $record_data['status'] = $status;
            $record_data['run_id'] = $run_id;
            $record_data['before_record'] = $before_record;
            $record_data['modified_record'] = $modified_record;
            $record_data['header_id'] = $header_id;
            $record_data['order_id'] = $order_id;
            $record_data['order_type'] = $order_type;
            $record_data['remove_bind'] = 0;
            $record_data['create_time'] = $create_time;
            //部分以前逻辑，统一到下面了，以前的逻辑应该没有前后顺序关系。现在区分omd和giga onsite了，如有问题，从仓库中找到恢复
            if (($order_info['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED || $order_info['order_status'] == CustomerSalesOrderStatus::ON_HOLD)
                && !($group_id == 1 && ($order_date_hour < 4 || $order_date_hour >= 12))
                && !($group_id == 15 && $order_date_hour >= 13)) {
                return ['error' => 3, 'info' => $this->language->get('error_cancel_time_late')];
            }
            //是否完全没同步
            $isExportInfo = app(CustomerSalesOrderRepository::class)->calculateSalesOrderIsExportedNumber($value['ShipId']);
            if ($isExportInfo['is_export_number'] > 0) {
                //根据绑定关系，可能有不同的seller，不同seller可能属于不同分组，分开处理
                $sellerList = app(CustomerRepository::class)->calculateSellerListBySalesOrderId($value['ShipId']);
                $haveGigaOnsite = $haveOmd = 0;
                foreach ($sellerList as $seller) {
                    if ($seller['accounting_type'] == CustomerAccountingType::GIGA_ONSIDE) {
                        $haveGigaOnsite = 1; //有卖家需要on_site
                    } else {
                        $haveOmd = 1; //有卖家需要omd
                    }
                }
                if ($haveGigaOnsite == 1 && $haveOmd == 1) {
                    $json['error'] = 3;
                    $json['info'] = $this->language->get('error_is_contact_service'); //直接联系客服处理
                    return $json;
                } elseif ($haveGigaOnsite == 1) {
                    $isInOnsite = $this->model_account_customer_order->checkOrderShouldInGigaOnsite($value['ShipId']);
                    if ($isInOnsite) {
                        $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $gigaResult = app(GigaOnsiteHelper::class)->updateOrderAddress($order_id, $shipData,$run_id);
                        if ($gigaResult['code'] == 1) {
                            return ['error' => 2, 'info' => $this->language->get('text_update_address_wait')];
                        } else {
                            $new_status = CommonOrderActionStatus::FAILED;
                            $fail_reason = $this->language->get('error_response');
                            $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                            $this->updateAddressChangeTips($value['ShipId'], 0);
                            return ['error' => 3, 'info' => $this->language->get('error_response')];
                        }
                    }
                } elseif ($haveOmd == 1) {
                    if (!isset($omd_store_id)) {
                        return ['error' => 3, 'info' => $this->language->get('error_invalid_param')]; // 以前逻辑
                    }
                    $isInOmd = $this->model_account_customer_order->checkOrderShouldInOmd($value['ShipId']);
                    if ($isInOmd) {
                        //保存修改记录
                        $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $response = $this->sendCurl(OMD_POST_URL, $param);
                        if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                            return ['error' => 2, 'info' => $this->language->get('text_update_address_wait')];
                        } else {
                            $new_status = CommonOrderActionStatus::FAILED;
                            $fail_reason = $this->language->get('error_response');
                            $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                            $this->updateAddressChangeTips($value['ShipId'], 0);
                            return ['error' => 3, 'info' => $this->language->get('error_response')];
                        }
                    }
                } else { //OMD 同步到B2B  没处理成功处于to be paid状态，没有绑定关系，但是需要同步给OMD
                    if ($isExportInfo['is_omd_number'] > 0) {
                        //保存修改记录
                        $log_id = $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                        $response = $this->sendCurl(OMD_POST_URL, $param);
                        if (isset($response) && 'OMD PROCESS SUCCESS' == $response) {
                            return ['error' => 2, 'info' => $this->language->get('text_update_address_wait')];
                        } else {
                            $new_status = CommonOrderActionStatus::FAILED;
                            $fail_reason = $this->language->get('error_response');
                            $this->model_account_customer_order->updateSalesOrderModifyLog($log_id, $new_status, $fail_reason);
                            $this->updateAddressChangeTips($value['ShipId'], 0);
                            return ['error' => 3, 'info' => $this->language->get('error_response')];
                        }
                    }
                }
            }

            // 更改信息
            // 欧洲 to be paid 没有绑定关系时，可直接修改地址
            // BP状态下不允许修改地址
            $is_international = 0;
            if (in_array($country_id, EUROPE_COUNTRY_ID) && in_array($order_info['order_status'], [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::LTL_CHECK])) {
                $exists = $this->checkIsExistAssociate($header_id);
                if (!$exists['error']) {
                    if (
                        (!in_array(strtoupper(trim($value['ShipToCountry'])), self::BRITAIN_ALIAS_NAME) && $country_id == self::BRITAIN_COUNTRY_ID)
                        || (strtoupper(trim($value['ShipToCountry'])) != 'DE' && $country_id == self::GERMANY_COUNTRY_ID)
                    ) {
                        $is_international = 1;
                    } else {
                        $is_international = 0;
                    }
                } else {
                    return ['error' => 3, 'info' => $this->language->get('error_associate_to_order')];
                }
            }
            try {
                $update_result = $this->orm->table('tb_sys_customer_sales_order')
                    ->where('id', $value['ShipId'])
                    ->update([
                        "ship_name" => $value['ShipToName'] == '' ? null : trim($value['ShipToName']),
                        "email" => $value['ShipToEmail'] == '' ? null : trim($value['ShipToEmail']),
                        "ship_phone" => $value['ShipToPhone'] == '' ? null : trim($value['ShipToPhone']),
                        "ship_address1" => $value['StreetAddress'] == '' ? null : trim($value['StreetAddress']),
                        "ship_zip_code" => $value['ShipToPostalCode'] == '' ? null : trim($value['ShipToPostalCode']),
                        "ship_city" => $value['ShipToCity'] == '' ? null : trim($value['ShipToCity']),
                        "ship_state" => $value['ShipToState'] == '' ? null : trim($value['ShipToState']),
                        "ship_country" => $value['ShipToCountry'] == '' ? null : trim($value['ShipToCountry']),
                        "customer_comments" => $value['OrderComments'] == '' ? null : $value['OrderComments'],
                        "update_time" => date('Y-m-d H:i:s'),
                        "is_international" => $is_international,
                    ]);
            } catch (Exception $e) {
                $update_result = 0;
                $this->log->write('修改地址错误，something wrong happened. Please try it again.');
                $this->log->write($e);
            }
            if ($update_result) {
                // 欧洲的需要出一个是否有当前更改国别的报价
                if (in_array($country_id, EUROPE_COUNTRY_ID) && $is_international) {
                    $notice = $this->getInternationalOrder(trim($value['ShipToCountry']), $country_id, $value['ShipToPostalCode']) ? false : true;
                } else {
                    $notice = false;
                }
                $record_data['status'] = CommonOrderActionStatus::SUCCESS;
                //保存修改记录
                $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                $this->updateAddressChangeTips($value['ShipId'], 1);
                return ['error' => 0, 'tag_id' => 0, 'notice' => $notice]; // 欧洲国际单无报价需要提示buyer
            } else {
                $record_data['status'] = CommonOrderActionStatus::FAILED;
                //保存修改记录
                $this->model_account_customer_order->saveSalesOrderModifyRecord($record_data);
                $this->updateAddressChangeTips($value['ShipId'], 0);
                return ['error' => 3, 'info' => $this->language->get('text_change_ship_failed')];
            }

        } else {
            return ['error' => 3, 'info' => $this->language->get('error_invalid_param')];
        }
    }


    public function getTrackingNumber($id_list, $order_list)
    {

        return $this->orm->connection('read')
            ->table('tb_sys_customer_sales_order_line as ordline')
            ->leftJoin('tb_sys_customer_sales_order_tracking as trac', 'trac.SalerOrderLineId', '=', 'ordline.id')
            ->leftJoin('tb_sys_carriers as car', 'car.carrierID', '=', 'trac.LogisticeId')
            ->whereIn('ordline.header_id', $id_list)
            ->whereIn('trac.SalesOrderId', $order_list)
            ->orderBy('trac.status', 'desc')
            ->orderBy('trac.Id', 'asc')
            ->selectRaw('ordline.id as line_id,ordline.qty,trac.parent_sku,trac.ShipSku,trac.TrackingNumber as trackingNo,if(car.carrierName="Truck",trac.ServiceLevelId,car.carrierName) AS carrierName,trac.status,trac.SalesOrderId')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
    }

    public function getSearchSku($sku, $page, $line_id, $customer_id, $country_id)
    {
        // 获取 line_id 所在order_id的所有的sku
        $sku_info = db('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
            ->leftJoin('tb_sys_customer_sales_order_line as ll', 'o.id', '=', 'll.header_id')
            ->where(
                [
                    ['l.id', '=', $line_id],
                    ['ll.id', '!=', $line_id],
                ])
            ->selectRaw('group_concat(ll.item_code) as sku_str')
            ->first();
        //$europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id')); // 欧洲补运费产品不允许被更改sku
        $map = [
            ['p.sku', 'like', "%{$sku}%"],
            ['bts.buyer_id', '=', $customer_id],
            ['bts.buy_status', '=', 1],
            ['bts.buyer_control_status', '=', 1],
            ['bts.seller_control_status', '=', 1],
            ['p.status', '=', '1'],
            ['p.buyer_flag', '=', '1'],
            ['c.country_id', '=', $country_id],
        ];
        $builder = db(DB_PREFIX . 'product as p')
            ->leftJoin(DB_PREFIX . 'customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin(DB_PREFIX . 'buyer_to_seller as bts', 'bts.seller_id', '=', 'ctp.customer_id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id', '=', 'bts.seller_id')
            ->where($map)
            ->where('p.product_type', ProductType::NORMAL)
            ->whereNotIn('p.sku', explode(',', $sku_info->sku_str))
            ->whereNotNull('p.sku')
            ->whereNotIn('c.customer_id', salesOrderSkuValidate::SERVICES_STORE_ID)
            ->select('p.product_id as id', 'p.sku')
            ->groupBy(['p.sku']);
        $results['total_count'] = $builder->count('*');
        $results['items'] = $builder->forPage($page, 10)
            ->orderBy('p.product_id', 'asc')
            ->get();
        $results['items'] = obj2array($results['items']);
        return $results;
    }

    public function getLtlCheckInfoByOrderId($order_id_string)
    {
        $line_list = db('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
            ->whereIn('l.header_id', explode('_', $order_id_string))
            ->selectRaw('l.qty,l.item_code,o.order_id,o.id,l.header_id,o.ship_address1,o.ship_city,o.ship_zip_code,o.ship_state,o.ship_country,o.ship_phone,o.ship_name')
            ->get()
            ->map(function ($v) {
                $v->ship_address1 = app('db-aes')->decrypt($v->ship_address1);
                $v->ship_name = app('db-aes')->decrypt($v->ship_name);
                $v->ship_city = app('db-aes')->decrypt($v->ship_city);
                $v->ship_phone = app('db-aes')->decrypt($v->ship_phone);
                return (array)$v;
            })
            ->toArray();
        $orders = [];
        if ($line_list) {
            foreach ($line_list as $key => $value) {
                if ($value['ship_phone']) {
                    $ship_phone = '(' . $value['ship_phone'] . ') ';
                } else {
                    $ship_phone = ' ';
                }
                $value['detail_address'] = $value['ship_name'] . ' ' . $ship_phone
                    . $value['ship_address1']
                    . ',' . $value['ship_city'] . ',' . $value['ship_zip_code']
                    . ',' . $value['ship_state'] . ',' . $value['ship_country'];
                $value['detail_address'] = rtrim($value['detail_address'], ',');
                $orders[$value['header_id']]['list'][] = $value;
                $orders[$value['header_id']]['count'] = count($orders[$value['header_id']]['list']);
            }
        }
        return array_values($orders);
    }

    public function changeLtlStatus($order_id_list)
    {
        $other_exists = db('tb_sys_customer_sales_order')
            ->where([['order_status', '!=', CustomerSalesOrderStatus::LTL_CHECK]])
            ->whereIn('id', $order_id_list)
            ->exists();
        if ($other_exists) {
            $json['msg'] = $this->language->get('error_can_ltl');
        } else {
            $this->log->write('order_id：' . implode(',', $order_id_list) . ',pre_status:64' . ',suf_status:1');

            DB::table('tb_sys_customer_sales_order')
                ->whereIn('id', $order_id_list)
                ->update(
                    [
                        'order_status' => CustomerSalesOrderStatus::TO_BE_PAID,
                        'ltl_process_status' => 1,
                        'ltl_process_time' => date('Y-m-d H:i:s'),
                    ]
                );
            DB::table('tb_sys_customer_sales_order_line')
                ->where('header_id', $order_id_list)
                ->update(['item_status' => CustomerSalesOrderLineItemStatus::PENDING]);
            $json['msg'] = $this->language->get('text_change_status_success');
        }
        return $json;
    }


    /**
     * [sendSystemMessage description] 1.order hold 2.地址有误 3.无效运单号 4.LtL提醒 其中 1，3，4 都是在yzc_task_work
     * @param int $type
     * @param int $customer_id
     * @param $order_id
     * @param $order_code
     * @return array
     * @throws Exception
     */
    //public function sendSystemMessage($type,$customer_id,$order_id,$order_code)
    //{
    //    $ret = $this->setTemplateOfCommunication($type,$customer_id,$order_id,$order_code);
    //    $this->load->model('message/message');
    //    if($ret){
    //        $this->model_message_message->addSystemMessageToBuyer('sales_order',$ret['subject'],$ret['message'],$ret['received_id']);
    //    }
    //}

    //public function setTemplateOfCommunication($type,$customer_id,$order_id,$order_code)
    //{
    //    $subject = '';
    //    $message = '';
    //    $received_id = $customer_id;
    //    if($type == 1){
    //        $date = date('Y-m-d H:i:s');
    //        $subject .= 'Sales Order has been On Hold';
    //        $message = '<table   border="0" cellspacing="0" cellpadding="0">';
    //        $message .= '<tr><td align="left">Sales Order ID:&nbsp</td><td style="width: 650px">
    //                      <a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('account/sales_order/sales_order_management/customerOrderSalesOrderDetails', 'id=' . $order_id )). '">' .$order_code. '</a>
    //                      </td></tr> ';
    //        $message .= '<tr><td align="left">Create Date:&nbsp</td><td style="width: 650px">'.$date.'</td></tr>';
    //        $message .= '<tr><th align="left" colspan="2">There is no action to the order for 7 days, Order has been on hold. Please release it first then to purchase it.</th></tr>';
    //        $message .= '</table>';
    //    }elseif($type == 2){
    //        $subject .= 'Sales Order Address is questionable';
    //        $message .= 'Sales Order ID <a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('account/sales_order/sales_order_management/customerOrderSalesOrderDetails', 'id=' . $order_id )). '">' .$order_code. '</a>
    //            The address is incorrect. Please go back to the Sales Order List to modify address。If make sure the address is correct ，you can ignore the reminder.';
    //    }else{
    //        return false;
    //    }
    //    $ret['subject'] = $subject;
    //    $ret['message'] = $message;
    //    $ret['received_id']  = $received_id;
    //    return $ret;
    //}

    public function checkAssociateOrder($order_id_list)
    {
        $us_signature_service_pid = $this->config->get('signature_service_us_product_id');
        $result = db('tb_sys_order_associated as soa')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'soa.sales_order_id')
            ->where([
                ['cso.order_status', '=', CustomerSalesOrderStatus::CANCELED],
                ['soa.product_id', '<>', $us_signature_service_pid]
            ])
            ->whereIn('soa.sales_order_id', $order_id_list)
            ->selectRaw('soa.id,soa.sales_order_id,soa.order_id')
            ->groupBy('soa.sales_order_id')
            ->get()
            ->keyBy('sales_order_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return $result;
    }

    /**
     * 查看取消状态已删的绑定关系
     * @param $saleOrderIds
     * @return Collection
     */
    public function getOrderAssociatedCancelRecords($saleOrderIds)
    {
        $signatureServiceUsProductId = $this->config->get('signature_service_us_product_id');
        return db('tb_sys_order_associated_deleted_record as soa')
            ->join('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'soa.sales_order_id')
            ->where([
                ['cso.order_status', '=', CustomerSalesOrderStatus::CANCELED],
                ['soa.product_id', '<>', $signatureServiceUsProductId]
            ])
            ->whereIn('soa.sales_order_id', $saleOrderIds)
            ->selectRaw('soa.*')
            ->get();
    }

    /**
     * 删除自动购买的绑定和comboinfo
     * @param $salesOrderId
     */
    public function removeAssociateAndComboInfo($salesOrderId)
    {
        db('tb_sys_customer_sales_order as cso')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'csol.header_id', '=', 'cso.id')
            ->where('cso.id', '=', $salesOrderId)
            ->update([
                'csol.combo_info' => '',
                'cso.order_status' => CustomerSalesOrderStatus::TO_BE_PAID
            ]);

        db('tb_sys_order_associated')
            ->where('sales_order_id', '=', $salesOrderId)
            ->delete();
    }

    public function getPurchaseOrderInfoByOrderId($orderList)
    {
        $ret = db('tb_sys_order_associated')
            ->whereIn('sales_order_id', $orderList)
            ->groupBy('sales_order_line_id')
            ->selectRaw('group_concat(distinct order_id) as order_id_str,sales_order_line_id')
            ->get()
            ->keyBy('sales_order_line_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id'));
        $retFreight = db('tb_sys_order_associated')
            ->whereIn('sales_order_id', $orderList)
            ->whereIn('product_id', $europe_freight_product_list)
            ->groupBy('sales_order_line_id')
            ->selectRaw('group_concat(distinct order_id) as order_id_str,sales_order_line_id')
            ->get()
            ->keyBy('sales_order_line_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();

        return [
            'all' => $ret,
            'freight' => $retFreight,
        ];
    }

    /**
     * 根据国家ID获取对应的state
     * @param int $country_id
     * @return array
     */
    public function getStateByCountry(int $country_id)
    {
        $builder = db('tb_sys_country_state')
            ->where('country_id', intval($country_id))
            ->select('county')
            ->get();
        return obj2array($builder);
    }

    /**
     * 根据国家ID与洲判断是否存在
     * @param string $county 洲(ship state)
     * @param int $country_id
     * @return bool
     */
    public function existsByCountyAndCountry(string $county, int $country_id)
    {
        return db('tb_sys_country_state')
            ->where([
                ['country_id', '=', $country_id],
                ['county', '=', trim($county)],
            ])->exists();
    }
}
