<?php

namespace App\Catalog\Forms\SalesOrder\Import;

use App\Enums\Product\ProductType;
use App\Helper\AddressHelper;
use App\Helper\StringHelper;
use App\Models\Freight\InternationalOrderConfig;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Repositories\SalesOrder\Validate\salesOrderSkuValidate;

class EbayRowValidate
{
    /**
     * 用户上传数据
     * @var array
     */
    protected $data = [];

    /**
     * 校验完的数据--不包含头部
     * @var array
     */
    protected $newData = [];

    /**
     * @var array
     */
    protected $transactionIds = [];

    /**
     * @var int
     */
    protected $customerId;

    /**
     * @var int
     */
    protected $countryId;

    /**
     * @var string
     */
    protected $runId;

    /**
     * 国际单配置
     *
     * @var array
     */
    protected $inactiveInternationalCountryList = [];

    public function __construct(array $data, string $runId)
    {
        $this->data = $data;
        $this->runId = $runId;
        $this->customerId = (int)customer()->getId();
        $this->countryId = (int)customer()->getCountryId();
        $this->inactiveInternationalCountryList = InternationalOrderConfig::query()->where('status', 0)->pluck('country_id')->toArray();
    }

    /**
     * @return string[]
     */
    protected function getRules(): array
    {
        return [
            'platform_sku' => ['required', 'integer', 'min:1', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 18) {
                    $fail('"eBayItemNumber" field length exceeds the entry limit of 18 characters, please check and re-upload.');
                    return;
                }
            }],
            'transaction_id' => ['required', 'integer', 'min:1', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 18) {
                    $fail('"eBayTransactionID" field length exceeds the entry limit of 18 characters, please check and re-upload.');
                    return;
                }
            }],
            'orders_from' => ['string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                $limitLen = $this->countryId == AMERICAN_COUNTRY_ID ? 30 : 20;
                $columnName = $this->countryId == AMERICAN_COUNTRY_ID ? 'ShipFrom' : 'SalesPlatform';
                if ($stringCharactersLen > $limitLen) {
                    $fail('"' . $columnName . '" field length exceeds the entry limit of ' . $limitLen . 'characters, please check and re-upload.');
                    return;
                }
            }],
            'order_id' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 40) {
                    $fail('"OrderId" field length exceeds the entry limit of 40 characters, please check and re-upload.');
                    return;
                }
            }],
            'line_item_number' => ['required', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 50) {
                    $fail('"LineItemNumber" field length exceeds the entry limit of 50 characters, please check and re-upload.');
                    return;
                }
            }],
            'order_date' => ['string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen > 25) {
                    $fail('"OrderDate" field length exceeds the entry limit of 25 characters, please check and re-upload.');
                    return;
                }
            }],
            'brand_id' => ['string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen > 30) {
                    $fail('"BuyerBrand" field length exceeds the entry limit of 30 characters, please check and re-upload.');
                    return;
                }
            }],
            'BuyerPlatformSku' => ['string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen > 25) {
                    $fail('"BuyerPlatformSku" field length exceeds the entry limit of 25 characters, please check and re-upload.');
                    return;
                }
            }],
            'item_code' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 30) {
                    $fail('"B2BItemCode" field length exceeds the entry limit of 30 characters, please check and re-upload.');
                    return;
                }
            }],
            'product_name' => ['string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen > 100) {
                    $fail('"BuyerSkuDescription" field length exceeds the entry limit of 100 characters, please check and re-upload.');
                    return;
                }
            }],
            'item_price' => ['string', function ($attribute, $value, $fail) {
                $reg = '/^(([1-9][0-9]*)|(([0]\.\d{1,2}|[1-9][0-9]*\.\d{1,2})))$/';
                if ($value != '' && !preg_match($reg, $value)) {
                    $fail('"BuyerSkuCommercialValue" format error,Please see the instructions.');
                    return;
                }
            }],
            'alt_item_id' => ['string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen > 50) {
                    $fail('"BuyerSkuLink" field length exceeds the entry limit of 50 characters, please check and re-upload.');
                    return;
                }
            }],
            'qty' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!preg_match('/^[0-9]*$/', $value) || $value <= 0 || $value == '') {
                    $fail('"ShipToQty" field does not meet the requirement.');
                    return;
                }
            }],
            'ship_method' => ['string', function ($attribute, $value, $fail) {
                if (!DISABLE_SHIP_TO_SERVICE && trim($value) != '' && strtoupper(trim($value)) != 'ASR') {
                    $fail('"ShipToService" Type ASR here to require the signature upon delivery, Please see the instructions.');
                    return;
                }
            }],
            'delivery_to_fba' => ['string', function ($attribute, $value, $fail) {
                // 欧洲和日本 需要校验 DeliveryToFBAWarehouse （是否FBA送仓）-- 可以不填，如果填写 只能是 yes or no
                if ($value && !in_array(strtoupper($value), ['NO', 'YES'])) {
                    $fail('"DeliveryToFBAWarehouse" only supports \'Yes\' or \'No\' or do not fill in.');
                    return;
                }
            }],
            'ship_service_level' => ['string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen > 50) {
                    $fail('"ShipToServiceLevel" field length exceeds the entry limit of 50 characters, please check and re-upload.');
                    return;
                }
            }],
            'ship_to_attachment_url' => ['string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen > 800) {
                    $fail('"ShipToAttachmentUrl" field length exceeds the entry limit of 800 characters, please check and re-upload.');
                    return;
                }
            }],
            'ship_name' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 40) {
                    $fail('"ShipToName" field length exceeds the entry limit of 40 characters, please check and re-upload.');
                    return;
                }
            }],
            'email' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 90) {
                    $fail('"ShipToEmail" field length exceeds the entry limit of 90 characters, please check and re-upload.');
                    return;
                }
            }],
            'ship_phone' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 45) {
                    $fail('"ShipToPhone" field length exceeds the entry limit of 45 characters, please check and re-upload.');
                    return;
                }
            }],
            'ship_zip_code' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 18) {
                    $fail('"ShipToPostalCode" field length exceeds the entry limit of 18 characters, please check and re-upload.');
                    return;
                }
                if (!preg_match('/^[0-9-]{1,18}$/i', $value) && $this->countryId == AMERICAN_COUNTRY_ID) {
                    $fail('"ShipToPostalCode" must Include only numbers,-., please check and re-upload.');
                    return;
                }
            }],
            'ship_address1' => ['required', 'string'],
            'ship_city' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 30) {
                    $fail('"ShipToCity" field length exceeds the entry limit of 30 characters, please check and re-upload.');
                    return;
                }
            }],
            'ship_state' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen == 0 || $stringCharactersLen > 30) {
                    $fail('"ShipToState" field length exceeds the entry limit of 30 characters, please check and re-upload.');
                    return;
                }
                if (AddressHelper::isRemoteRegion($value) && $this->countryId == AMERICAN_COUNTRY_ID) {
                    $fail('"ShipToState" in PR, AK, HI, GU, AA, AE, AP doesn\'t support delivery,Please see the instructions.');
                    return;
                }
            }],
            'ship_country' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!in_array(strtoupper($value), ['US', 'UNITED STATES']) && $this->countryId == AMERICAN_COUNTRY_ID) {
                    $fail('"ShipToCountry" format error,Please see the instructions.');
                    return;
                }

                $inactiveInternationalCountryList = $this->inactiveInternationalCountryList;
                if (in_array($this->countryId, $inactiveInternationalCountryList)) {
                    if ($this->countryId == UK_COUNTRY_ID) {
                        if (!in_array(strtoupper($value), ['UK', 'GB'])) {
                            $fail('This order may only be shipped within its country of origin.');
                            return;
                        }
                    }
                    if ($this->countryId == DE_COUNTRY_ID) {
                        if (strtoupper(strtoupper($value)) != 'DE') {
                            $fail('This order may only be shipped within its country of origin.');
                            return;
                        }
                    }
                }
            }],
            'customer_comments' => ['string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen($value);
                if ($stringCharactersLen > 1500) {
                    $fail('"OrderComments" field length exceeds the entry limit of 1500 characters, please check and re-upload.');
                    return;
                }
            }],
        ];
    }

    /**
     * @return string[]
     */
    protected function getRuleMessages(): array
    {
        return [
            'required' => '":attribute" field cannot be empty.',
            'transaction_id.integer' => '"eBayTransactionID" field does not meet the requirement.',
            'transaction_id.min' => '"eBayTransactionID" field does not meet the requirement.',
            'platform_sku.integer' => '"eBayItemNumber" field does not meet the requirement.',
            'platform_sku.min' => '"eBayItemNumber" field does not meet the requirement.',
        ];
    }

    /**
     * @return string[]
     */
    protected function getCustomAttributes(): array
    {
        return [
            'platform_sku' => 'eBayItemNumber',
            'transaction_id' => 'eBayTransactionID',
            'orders_from' => $this->countryId == AMERICAN_COUNTRY_ID ? 'ShipFrom' : 'SalesPlatform',
            'order_id' => 'OrderId',
            'line_item_number' => 'LineItemNumber',
            'order_date' => 'OrderDate',
            'brand_id' => 'BuyerBrand',
            'BuyerPlatformSku' => 'BuyerPlatformSku',
            'item_code' => 'B2BItemCode',
            'product_name' => 'BuyerSkuDescription',
            'item_price' => 'BuyerSkuCommercialValue',
            'alt_item_id' => 'BuyerSkuLink',
            'qty' => 'ShipToQty',
            'ship_method' => 'ShipToService',
            'delivery_to_fba' => 'DeliveryToFBAWarehouse',
            'ship_service_level' => 'ShipToServiceLevel',
            'ship_to_attachment_url' => 'ShipToAttachmentUrl',
            'ship_name' => 'Ship To Name',
            'email' => 'ShipToEmail',
            'ship_phone' => 'ShipToPhone',
            'ship_zip_code' => 'ShipToPostalCode',
            'ship_address1' => 'ShipToAddressDetail',
            'ship_city' => 'ShipToCity',
            'ship_state' => 'ShipToState',
            'ship_country' => 'ShipToCountry',
            'customer_comments' => 'OrderComments',
        ];
    }

    /**
     * 校验数据
     * @return string
     */
    public function validate()
    {
        // 数据行数等于2行，证明为空数据，需要进行处理
        if (empty($this->data) || !isset($this->data) || count($this->data) < 3) {
            return 'No data was found in the file.';
        }
        //首行头部校验
        $header = $this->data[0];
        $ordersFromColumn = $this->countryId == AMERICAN_COUNTRY_ID ? 'ShipFrom' : 'SalesPlatform';
        $serviceColumn = $this->countryId == AMERICAN_COUNTRY_ID ? 'ShipToService' : 'DeliveryToFBAWarehouse';
        $templateHeader = [
            'eBayItemNumber', 'eBayTransactionID', $ordersFromColumn, 'OrderId', 'LineItemNumber', 'OrderDate', 'BuyerBrand', 'BuyerPlatformSku', 'B2BItemCode',
            'BuyerSkuDescription', 'BuyerSkuCommercialValue', 'BuyerSkuLink', 'ShipToQty', $serviceColumn, 'ShipToServiceLevel', 'ShippedDate', 'ShipToAttachmentUrl',
            'ShipToName', 'ShipToEmail', 'ShipToPhone', 'ShipToPostalCode', 'ShipToAddressDetail', 'ShipToCity', 'ShipToState', 'ShipToCountry', 'OrderComments'
        ];
        foreach ($header as $key => $val) {
            $header[$key] = trim(str_replace('*', '', $val));
        }
        // 验证第一行数据与给出数据是否相等
        if ($header != $templateHeader) {
            return 'The columns of the uploaded file are inconsistent with the template,please check and re-upload.';
        }

        //去掉首行标题
        unset($this->data[0]);
        //产品让与保持和通用模板导入一致，去掉标题说明行
        unset($this->data[1]);
        $data = [];
        //将对应的表头名称作为key
        foreach ($this->data as $key => $value) {
            $data[$key - 1] = array_combine($templateHeader, $value);
        }

        $orderAndLineItemNumberArr = [];
        $orderIdAndSkuArr = [];
        $orderNumberArr = [];
        $skuArr = [];
        $judgeIsLtlArr = [];
        $deliveryToFbaArr = [];

        // 所有行相同的orderId 提取B2BItemCode 后面用于判断是否LTL
        $orderIdItemCode = [];
        foreach ($data as $key => $value) {
            if (isset($value['OrderId']) && isset($value['B2BItemCode'])) {
                $orderId = trim($value['OrderId']);
                $orderIdItemCode[$orderId][] = strtoupper(trim(str_replace(chr(194) . chr(160), '', $value['B2BItemCode'])));
            }
        }

        //地址限制长度
        $limitAddressLen = $this->getAddressLen();
        $limitLtlAddressLen = $this->getLtlAddressLen();

        foreach ($data as $key => $value) {
            $temp = [];
            $temp['platform_sku'] = trim($value['eBayItemNumber']);//eBay 平台的sku
            $temp['transaction_id'] = trim($value['eBayTransactionID']);//交易号
            $temp['orders_from'] = $this->countryId == AMERICAN_COUNTRY_ID ? trim($value['ShipFrom']) : trim($value['SalesPlatform']);
            $temp['order_id'] = trim($value['OrderId']);
            $temp['line_item_number'] = trim($value['LineItemNumber']);
            $temp['order_date'] = trim($value['OrderDate']);
            $temp['brand_id'] = trim($value['BuyerBrand']);
            $temp['BuyerPlatformSku'] = trim($value['BuyerPlatformSku']);
            $temp['item_code'] = strtoupper(trim(str_replace(chr(194) . chr(160), '', $value['B2BItemCode'])));
            $temp['product_name'] = trim($value['BuyerSkuDescription']);
            $temp['item_price'] = trim($value['BuyerSkuCommercialValue']);
            $temp['alt_item_id'] = trim($value['BuyerSkuLink']);
            $temp['qty'] = trim($value['ShipToQty']);
            $temp['ship_method'] = $this->countryId == AMERICAN_COUNTRY_ID ? DISABLE_SHIP_TO_SERVICE ? '' : trim($value['ShipToService']) : '';//#1170 Fedex签收服务相关功能禁用
            $temp['delivery_to_fba'] = $this->countryId == AMERICAN_COUNTRY_ID ? '' : trim($value['DeliveryToFBAWarehouse']);
            $temp['ship_service_level'] = trim($value['ShipToServiceLevel']);
            $temp['ship_to_attachment_url'] = trim($value['ShipToAttachmentUrl']);
            $temp['ship_name'] = trim($value['ShipToName']);
            $temp['email'] = trim($value['ShipToEmail']);
            $temp['ship_phone'] = trim($value['ShipToPhone']);
            $temp['ship_zip_code'] = trim($value['ShipToPostalCode']);
            $temp['ship_address1'] = trim($value['ShipToAddressDetail']);
            $temp['ship_city'] = trim($value['ShipToCity']);
            $temp['ship_state'] = trim($value['ShipToState']);
            $temp['ship_country'] = trim($value['ShipToCountry']);
            $temp['customer_comments'] = trim($value['OrderComments']);
            //校验导单
            $validator = validator($temp, $this->getRules(), $this->getRuleMessages(), $this->getCustomAttributes());
            if ($validator->fails()) {
                return 'Line' . ($key + 2) . ', ' . $validator->errors()->first();
            }

            //delivery_to_fba在订单中取第一个值
            if (!isset($deliveryToFbaArr[$temp['order_id']])) {
                $deliveryToFbaArr[$temp['order_id']] = empty($temp['delivery_to_fba']) ? 0 : (strtoupper($temp['delivery_to_fba']) == 'YES' ? 1 : 0);
            }
            $temp['ship_country'] = $this->countryId == AMERICAN_COUNTRY_ID ? 'US' : $temp['ship_country'];//因兼容了Unite States 大小写的导入
            $temp['bill_name'] = $temp['ship_name'];
            $temp['bill_address'] = $temp['ship_address1'];
            $temp['bill_city'] = $temp['ship_city'];
            $temp['bill_state'] = $temp['ship_state'];
            $temp['bill_zip_code'] = $temp['ship_zip_code'];
            $temp['bill_country'] = $temp['ship_country'];
            $temp['order_total'] = 1;
            $temp['delivery_to_fba'] = $deliveryToFbaArr[$temp['order_id']];
            $temp['buyer_id'] = $this->customerId;
            $temp['run_id'] = $this->runId;
            $temp['create_user_name'] = $this->customerId;
            $temp['create_time'] = date('Y-m-d H:i:s');
            $temp['update_user_name'] = PROGRAM_CODE;
            if (!isset($this->transactionIds[$temp['order_id']]) || empty($this->transactionIds[$temp['order_id']])) {
                $this->transactionIds[$temp['order_id']] = $temp['transaction_id'];
            }

            unset($temp['BuyerPlatformSku']);
            unset($temp['transaction_id']);

            //一个订单下不能有多个相同的line_item_number
            $orderAndLineItemNumber = $temp['order_id'] . '_' . $temp['line_item_number'];
            if (in_array($orderAndLineItemNumber, $orderAndLineItemNumberArr)) {
                return 'Line' . ($key + 2) . ', "OrderId" is duplicate,please check the uploaded file.';
            }
            array_push($orderAndLineItemNumberArr, $orderAndLineItemNumber);

            //一个订单下不能有多个相同的item_code
            $orderAndSku = $temp['order_id'] . '_' . $temp['item_code'];
            if (in_array($orderAndSku, $orderIdAndSkuArr)) {
                return 'Line' . ($key + 2) . ', This file has the same order details';
            }
            array_push($orderIdAndSkuArr, $orderAndSku);

            //地址校验
            if (!isset($judgeIsLtlArr[$temp['order_id']])) {
                $isLTL = false;
                if ($this->countryId == AMERICAN_COUNTRY_ID) {//只有US区分LTL与非LTL
                    $isLTL = app(CustomerSalesOrderRepository::class)->isLTL($this->countryId, $orderIdItemCode[$temp['order_id']] ?? []);
                }
                $judgeIsLtlArr[$temp['order_id']] = $isLTL;
            }
            $limitShipAddressChar = $judgeIsLtlArr[$temp['order_id']] ? $limitLtlAddressLen : $limitAddressLen;
            if (empty($temp['ship_address1']) || StringHelper::stringCharactersLen($value['ShipToAddressDetail']) > $limitShipAddressChar) {
                return 'Line' . ($key + 2) . ' , "ShipToAddressDetail" field length exceeds the entry limit of ' . $limitShipAddressChar . ' characters, please check and re-upload.';
            }
            if ($this->countryId == AMERICAN_COUNTRY_ID && $judgeIsLtlArr[$temp['order_id']] && AddressHelper::isPoBox($value['ShipToAddressDetail'])) {
                return 'Line' . ($key + 2) . ' , "ShipToAddressDetail" in P.O.BOX doesn\'t support delivery,Please see the instructions.';
            }

            //sku的校验
            if (!in_array($temp['item_code'], $skuArr)) {
                $verifyRet = app(salesOrderSkuValidate::class)->withSkus([$temp['item_code']])->validateSkus();
                if (!$verifyRet['code']) {
                    return 'Line' . ($key + 2) . ' , "B2BItemCode" is a service that cannot be shipped as a regular item. Please check it. ';
                }
                //在欧洲国别中运费产品导入的时候需要报错
                if (in_array($this->countryId, EUROPE_COUNTRY_ID)) {
                    $flag = Product::query()->where('sku', $temp['item_code'])->where('product_type', ProductType::COMPENSATION_FREIGHT)->exists();
                    if ($flag) {
                        return 'Line' . ($key + 2) . ' ,The Additional Fulfillment Fee is automatically calculated by the Marketplace system and added to your order total.';
                    }
                }
                array_push($skuArr, $temp['item_code']);
            }

            //order_id的校验
            if (!in_array($temp['order_id'], $orderNumberArr)) {
                //校验order_id 是否存在
                if (CustomerSalesOrder::query()->where('order_id', $temp['order_id'])->where('buyer_id', $this->customerId)->exists()) {
                    return 'Line' . ($key + 2) . ', "OrderId" is already exist ,please check the uploaded file.';
                }
                array_push($orderNumberArr, $temp['order_id']);
            }

            //将校验完的数据重新存储
            $this->newData[$key] = $temp;
        }
        return '';
    }

    /**
     * 获取导单校验完的新数据
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->newData;
    }

    /**
     * 获取transaction_id
     *
     * @return array  key为order_id value为transaction_id
     */
    public function getTransactionIds(): array
    {
        return $this->transactionIds;
    }

    /**
     * 导单地址限制长度（正常的，非LTL订单）
     *
     * @return string
     */
    public function getAddressLen(): string
    {
        switch ($this->countryId) {
            case UK_COUNTRY_ID:
                $len = configDB('config_b2b_address_len_uk');
                break;
            case DE_COUNTRY_ID:
                $len = configDB('config_b2b_address_len_de');
                break;
            default:
                $len = configDB('config_b2b_address_len_us1');
                break;
        }
        return $len;
    }

    /**
     * 导单地址限制长度（LTL订单）
     *
     * @return string
     */
    public function getLtlAddressLen(): string
    {
        return configDB('config_b2b_address_len');
    }
}
