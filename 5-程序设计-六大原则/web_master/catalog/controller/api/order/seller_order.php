<?php

use App\Logging\Logger;
use Catalog\model\customerpartner\SalesOrderManagement as sales_model;

/**
 * @property ModelToolExcel $model_tool_excel
 */

class ControllerApiOrderSellerOrder extends ControllerApiBase{
    private $sales_model;
    private $condition;
    const import_mode = 0;
    private $seller_err = [
        0 =>'customer_id value error',
    ];
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->sales_model = new sales_model($registry);
        $this->condition['run_id'] = (int)msectime(); //毫秒级别的时间戳进行处理
        $this->condition['import_mode'] = self::import_mode;
    }

    /**
     * [getDealData description] 获取数据 array
     */
    public function getDealData()
    {
        //这里处理数据
        $bodyData = @file_get_contents('php://input');
        Logger::app($bodyData);
        $bodyData = json_decode($bodyData);
        $header = [
            [
                "Sales Platform","Order Number","Order Detail Number",
                "Order Time","Seller's Brand","Seller's Platform SKU",
                "Product code on B2B Platform","Seller's Product Description",
                "Commercial Value of Seller's Product Per Unit","Seller's Product Link",
                "Dispatch Quantity","Dispatch Logistics Service","Dispatch Logistics Service Process",
                "Expected Dispatch Date","Link of Shipping Attachments","Receiver",
                "Receiver's E-mail","Receiver's Phone Number","Receiver's Zipcode","Detailed Shipping Address",
                "Shipping City","Shipping State/Region","Shipping Country","Order Note"],
            [
                "SalesPlatform","OrderId","LineItemNumber","OrderDate","SellerBrand","SellerPlatformSku",
                "B2BItemCode","SkuDescription","SkuCommercialValue","SkuLink","ShipToQty","ShipToService",
                "ShipToServiceLevel","ShippedDate","ShipToAttachmentUrl","ShipToName","ShipToEmail","ShipToPhone",
                "ShipToPostalCode","ShipToAddressDetail","ShipToCity","ShipToState","ShipToCountry","OrderComments"
            ],
        ];
        $items = [];
        foreach ($bodyData as $data) {
            $items[$data->orderId.'_'.$data->b2bItemCode] = [
                get_value_or_default($data, 'salesPlatform', ''),
                get_value_or_default($data, 'orderId', ''),
                get_value_or_default($data, 'lineItemNumber', ''),
                get_value_or_default($data, 'orderDate', ''),
                get_value_or_default($data, 'sellerBrand', ''),
                get_value_or_default($data, 'sellerPlatformSku', ''),
                get_value_or_default($data, 'b2bItemCode', ''),
                get_value_or_default($data, 'skuDescription', ''),
                get_value_or_default($data, 'skuCommercialValue', ''),
                get_value_or_default($data, 'skuLink', ''),
                get_value_or_default($data, 'shipToQty', ''),
                get_value_or_default($data, 'shipToService', ''),
                get_value_or_default($data, 'shipToServiceLevel', ''),
                get_value_or_default($data, 'shippedDate', ''),
                get_value_or_default($data, 'shipToAttachmentUrl', ''),
                get_value_or_default($data, 'shipToName', ''),
                get_value_or_default($data, 'shipToEmail', ''),
                get_value_or_default($data, 'shipToPhone', ''),
                get_value_or_default($data, 'shipToPostalCode', ''),
                get_value_or_default($data, 'shipToAddressDetail', ''),
                get_value_or_default($data, 'shipToCity', ''),
                get_value_or_default($data, 'shipToState', ''),
                get_value_or_default($data, 'shipToCountry', ''),
                get_value_or_default($data, 'orderComments', '')
            ];
        }
        return array_merge($header,array_values($items));


    }

    public function index(){
        set_time_limit(0);
        $structure = [
            'customer_id' => '',
        ];
        $this->setRequestDataStructure($structure);
        $input = $this->getParsedJson();
        if(isset($input['result_code'])){
            $json = $input;
            $this->response->returnJson($json);
        }
        trim_strings($this->request->get);
        $get = $this->request->get;
        // 1. 获取buyerId
        $customer_info = $this->sales_model->getApiBuyerInfo($get['customer_id']);
        if(!$customer_info){
            $this->apiError('','1001',$this->seller_err[0]);
            $this->response->returnJson($this->apiResponse([]));
        }
        // 2. 处理数据格式
        $data = $this->getDealData();
        Logger::app('纯物流api:');
        Logger::app($get);
        Logger::app($data);
        // 3. 处理格式正确的数据 首先condition 中包含了默认值 run_id 和 import_mode
        try {
            $ret = $this->sales_model->dealWithFileData($data, $this->condition, $customer_info[0]['country_id'], $customer_info[0]['customer_id']);
        } catch (\Exception $e){
            $ret = $e;
            Logger::app($e);
        }
        // save Order 成功之后会进行购买or订单状态更改
        if ($ret === true) {
            $this->orderPurchase($this->condition['run_id'],$customer_info[0]['customer_id']);
        }else{
            $this->apiError('','1001',$ret);
            $this->response->returnJson($this->apiResponse([]));
        }

        $this->setResultCode(200);
        $this->setResultMessage('success');
        $this->response->returnJson($this->apiResponse([]));


    }

    /**
     * [orderPurchase description]
     * @param int|string $run_id
     * @param int $customer_id
     */
    public function orderPurchase($run_id,$customer_id)
    {
        $this->sales_model->updateOriginalSalesOrderStatus($run_id, $customer_id);
    }

}
