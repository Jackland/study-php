<?php
/**
 * Created by IntelliJ IDEA.
 * User: or
 * Date: 2020/6/11
 * Time: 11:07
 */

use App\Enums\Country\CountryCode;
use App\Enums\Product\ProductTransactionType;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Logging\Logger;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Quote\ProductQuote;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Product\ProductPriceRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Repositories\Stock\BuyerStockRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\Order\OrderAssociatedService;
use App\Services\Order\OrderService;
use App\Services\Stock\BuyerStockService;
use Catalog\model\account\sales_order\SalesOrderManagement as sales_model;

/**
 * @property ModelAccountSalesOrderMatchInventoryWindow $model
 * @property ModelAccountSalesOrderMatchInventoryWindow $model_account_sales_order_match_inventory_window
 * @property ModelAccountCustomerOrderImport $model_account_customer_order_import
 * @property ModelExtensionModuleEuropeFreight $model_extension_module_europe_freight
 * @property ModelExtensionModulePrice $model_extension_module_price
 * @property ModelToolImage $model_tool_image
 *
 */
class ControllerAccountSalesOrderMatchInventoryWindow extends Controller
{

    const EUROPE_INTERNATIONAL = 1;
    const EUROPE_FREIGHT_SUCCESS_STATUS = 200;
    const EUROPE_FREIGHT_FAIL_PRODUCT_STATUS = 101;
    const EUROPE_FREIGHT_FAIL_COUNTRY_STATUS = 102;
    const NOT_VAT_ADDRESS_STATUS = 103;  //#31737 超出免税地区
    const EUROPE_FREIGHT_FAIL_NO_EXIST_STATUS = 108;
    const EUROPE_FREIGHT_PRODUCT_ERROR_MSG = 'This order is unable to be shipped overseas due to not meeting international shipping standards.';
    const EUROPE_FREIGHT_COUNTRY_ERROR_MSG = 'An auto-generated fulfillment quote estimate is currently not available for the country selected. Please contact Customer Service for a fulfillment quote after successfully importing the sales order.';
    const EUROPE_FREIGHT_FAIL_NO_EXIST_MSG = 'No item of additional shipping fee is set for this store, please contact the Customer Service.';
    const EUROPE_INTERNATION_NOTICE = 'This order has an international shipping address and additional shipping fee needed to be paid, please check.';
    const NOT_VAT_ADDRESS_NOTICE = 'The shipping address of this Sales Order is beyond the range of countries/regions covered by the VAT exemption policy. For the countries/regions covered, please contact Customer Service for details.';
    private $model;
    private $sales_model;
    private $europe_freight_model;
    private $country_name;

    /**
     * ControllerAccountSalesOrderCustomerOrder constructor.
     * @param Registry $registry
     * @throws Exception
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            $this->session->set('redirect',$this->url->link('account/customer_order', '', true));
            $this->response->redirectTo($this->url->link('account/login', '', true));
        }

        if ($this->customer->isPartner()) {
            $this->response->redirectTo($this->url->link('account/account', '', true));
        }

        if ($this->customer->isCollectionFromDomicile()) {
            $this->response->redirectTo($this->url->link('account/customer_order', '', true));
        }

        $this->load->model('account/sales_order/match_inventory_window');
        $this->model = $this->model_account_sales_order_match_inventory_window;
        $this->sales_model = new sales_model($registry);
        $this->load->model('extension/module/europe_freight');
        /** @var ModelExtensionModuleEuropeFreight $europeFreight */
        $this->europe_freight_model = $this->model_extension_module_europe_freight;
        $this->country_name = $this->session->get('country');
    }

    public function index()
    {
        $salesOrderIdArray = $this->request->post('sales_order_id', 0);
        $salesOrderInfo = $this->model->getSalesOrderLineInfo($salesOrderIdArray);
        $europeInternationArr = array_filter($salesOrderInfo,function ($item){
            return $item['is_international'] == 1;
        });
        $data['international_notice'] = count($europeInternationArr)>0?self::EUROPE_INTERNATION_NOTICE:'';
        $data['sales_order_id'] = json_encode($salesOrderIdArray);
        $data['init_url'] = $this->url->link('account/sales_order/match_inventory_window/initMatchData', '', true);
        $this->response->setOutput($this->load->view('account/sales_order/match_purchaseorder_window', $data));
    }

    public function toBuy(StorageFeeRepository $storageFeeRepository)
    {
        //弹框点击to_buy按钮
        $json_data = json_encode($this->request->post());
        $json_array = json_decode($json_data, true);
        if (!empty($json_array['salesOrder'])) {
            $orderLines = $json_array['salesOrder'];
            $result = $this->salesOrderCheck($orderLines);
            $json['status'] = $result['status'];
            $json['msg'] = isset($result['msg'])?$result['msg']:'';
        } else {
            //请求没有数据
            $json['status'] = false;
            $json['msg'] = 'Please select at least one sales order';
        }
        //数据校验成功将数据插入tb_purchase_pay_record，用于下单页的展示以及后续逻辑
        if($json['status']){
            $customer_id = $this->customer->getId();
            $runId = $this->saveMatchModalInfo($json_array['salesOrder']);
//            $purchaseRecords = $this->model->getPurchaseRecord($runId,$customer_id);
//            if(empty($purchaseRecords)){
//                //无需购买，判断是否有仓租
//                $purchaseRecords = $this->model->getPurchaseRecord($runId, $customer_id, false);
//                $salesOrderList = [];//sales order下所有的associated pre id
//                foreach ($purchaseRecords as $purchaseRecord) {
//                    $associatedPreList = $this->model->getSalesOrderAssociatedPre($purchaseRecord['order_id'], $purchaseRecord['line_id'], $runId, 1);
//                    foreach ($associatedPreList as $associatedPreItem) {
//                        $salesOrderList[] = $associatedPreItem->id;
//                    }
//                }
//                //循环查询每组订单是否有需要付仓租，不需要付仓租的销售单直接变BP，并且删除预绑信息
//                if ($storageFeeRepository->getAllCanBindNeedPay($salesOrderList, $purchaseRecords) > 0) {
//                    //需要仓租费，继续走支付流程
//                    $json['record_flag'] = true;
//                    $json['purchase_record_url'] = $this->url->link('account/sales_order/sales_order_management/salesOrderPurchaseOrderManagement&run_id=' . $runId);
//                } else {
//                    $json['record_flag'] = false;
//                    //全都匹配上了，无需购买
//                    $result =  $this->associateOrder($runId,$customer_id);
//                    if(!$result['status']){
//                        $json['status'] = false;
//                        $json['msg'] = $result['msg'];
//                    }else{
//                        $json['status'] = true;
//                        $json['msg'] = 'Sales order status has being processed.';
//                    }
//                }
//            } else {
//                $json['record_flag'] = true;
//                $json['purchase_record_url'] = $this->url->link('account/sales_order/sales_order_management/salesOrderPurchaseOrderManagement&run_id=' . $runId);
//            }
            $json['record_flag'] = true;
            $json['purchase_record_url'] = $this->url->link('account/sales_order/sales_order_management/salesOrderPurchaseOrderManagement&run_id=' . $runId);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    //一件代发销售订单是否需要支付的情况
    public function dropShipCheckNeedToPay()
    {
        $runId = $this->request->post('runId');
        $safeguards = $this->request->post('safeguards');
        $result = $this->associateOrder($runId, $this->customer->getId(), $safeguards);
        if (!$result['status']) {
            return $this->jsonFailed($result['msg']);
        } else {
            return $this->jsonSuccess([], 'Sales order status has being processed.');
        }
    }

    //上门取货销售订单是否需要支付的情况
    public function homePickCheckNeedToPay()
    {
        $saleOrderIds = $this->request->post('sale_order_ids');
        $feeOrderList = $this->request->post('fee_order_list');
        $saleOrderIdArr = explode(',', $saleOrderIds);
        $feeOrderList = explode(',', $feeOrderList);
        $feeTotal = FeeOrder::query()->whereIn('id', $feeOrderList)->sum('fee_total');
        if ($feeTotal <= 0) {
            $res = CustomerSalesOrder::query()
                ->whereIn('id', $saleOrderIdArr)
                ->update([
                    'order_status' => CustomerSalesOrderStatus::BEING_PROCESSED
                ]);
            if ($res) {
                return $this->jsonSuccess(['feeTotal' => $feeTotal]);
            } else {
                return $this->jsonFailed('Sales order status failed processed.', ['redirect_type' => 'refresh']);

            }
        }
        return $this->jsonSuccess(['feeTotal' => $feeTotal]);
    }


    public function salesOrderCheck($orderLines)
    {
        $lineIdArray = [];
        $result = [];
        foreach ($orderLines as $orderLine) {
            $lineIdArray[] = $orderLine['id'];
        }
        $lineInfos = $this->model->selectLineInfo($lineIdArray);
        /*
         * $skuArray:需要的sku数组
         * $needQty:订单需要的sku对应的数量
         * $needToBuyQty:需要购买的数量
         * $useCostQty:使用库存的数量
         * $tradeModeArr:交易方式的数组
         * $needQtyShow:页面展示的sku数组
         */
        $skuArray = [];
        $needQty = [];
        $needToBuyQty = [];
        $useCostQty = [];
        $tradeModeArr = [];
        $needQtyShow = [];
        //欧洲补运费产品数组
       /* $europeFreight = [];
        $freightToBuyQty = [];*/
        foreach ($lineInfos as $lineInfo) {
            //此时的订单状态不是new order
            if ($lineInfo['order_status'] != CustomerSalesOrderStatus::TO_BE_PAID) {
                $result['status'] = false;
                Logger::salesOrder("销售订单:".$lineInfo['order_id'] . "的状态已经发生改变，不可进行当前操作，请及时刷新订单.", 'warning');
                $result['msg'] = "The status of this order has been updated,the current action is now not allowed.Please refresh the order.";
                break;
            }
            //订单明细的状态不是pending
            if ($lineInfo['item_status'] != CustomerSalesOrderLineItemStatus::PENDING) {
                $result['status'] = false;
                Logger::salesOrder("销售订单".$lineInfo['order_id'] . "," . $lineInfo['item_code'] . "状态发送变化.", 'warning');
                $result['msg'] = "The status of this order has been updated,the current action is now not allowed.Please refresh the order.";
                break;
            }
            //需要购买的产品数量
            if (isset($needQty[$lineInfo['item_code']])) {
                $needQty[$lineInfo['item_code']] = $needQty[$lineInfo['item_code']] + $lineInfo['qty'];
            } else {
                $needQty[$lineInfo['item_code']] = $lineInfo['qty'];
            }
            $skuArray[] = $lineInfo['item_code'];
        }
        if(!isset($result['status'])) {
            foreach ($orderLines as $orderLine) {
                $itemCode = $orderLine['salesOrderLine']['item_code'];
                $lineId = $orderLine['id'];
                //需要购买的数量
                $toBuyQty = isset($orderLine['salesOrderLine']['to_buy_qty']) ? $orderLine['salesOrderLine']['to_buy_qty'] : 0;
                //使用库存的数量
                $costQty = isset($orderLine['salesOrderLine']['cost_qty']) ? $orderLine['salesOrderLine']['cost_qty'] : 0;
                //页面展示的数量
                $qty = isset($orderLine['salesOrderLine']['cost_qty']) ? $orderLine['salesOrderLine']['qty'] : 0;
                //支付方式
                $tradeModeArr[$lineId]['store'] = isset($orderLine['salesOrderLine']['store']) ? $orderLine['salesOrderLine']['store'] : '';
                $tradeModeArr[$lineId]['trade'] = isset($orderLine['salesOrderLine']['trade']) ? $orderLine['salesOrderLine']['trade'] : '';
                $tradeModeArr[$lineId]['buy'] = isset($orderLine['salesOrderLine']['to_buy_qty']) ? $orderLine['salesOrderLine']['to_buy_qty'] : 0;
                if (isset($needToBuyQty[$itemCode])) {
                    $needToBuyQty[$itemCode] = $needToBuyQty[$itemCode] + $toBuyQty;
                } else {
                    $needToBuyQty[$itemCode] = $toBuyQty;
                }
                if (isset($useCostQty[$itemCode])) {
                    $useCostQty[$itemCode] = $useCostQty[$itemCode] + $costQty;
                } else {
                    $useCostQty[$itemCode] = $costQty;
                }
                if (isset($needQtyShow[$itemCode])) {
                    $needQtyShow[$itemCode] = $needQtyShow[$itemCode] + $qty;
                } else {
                    $needQtyShow[$itemCode] = $qty;
                }
                $freightInfo = [];
                foreach ($orderLine['salesOrderLine'] as $key => $info){
                    $splitArr = explode('|',$key);
                    $name = $splitArr[0];
                    $count = isset($splitArr[1]) ? $splitArr[1] : '';
                    if(strstr($name,'freight') != false) {
                        $freightInfo[$count][$name] = $info;
                    }
                }
//                if(isset($orderLine['salesOrderLine']['freight_flag']) && $orderLine['salesOrderLine']['freight_flag'] == true){
//                    $europeFreight[$lineId]['product_id'] = $orderLine['salesOrderLine']['product_id'];
//                    $europeFreight[$lineId]['from'] = $this->country_name;
//                    $europeFreight[$lineId]['to'] = $lineInfos[$lineId]['ship_country'];
//                    $europeFreight[$lineId]['zip_code'] = $lineInfos[$lineId]['ship_zip_code'];
//                    $europeFreight[$lineId]['line_id'] = $lineId;
//                    $freightToBuyQty[$lineId]['qty'] = $orderLine['salesOrderLine']['freight_qty'];
//                    $freightToBuyQty[$lineId]['line_qty'] = $orderLine['salesOrderLine']['qty'];
//                    $freightToBuyQty[$lineId]['item_code'] = $orderLine['salesOrderLine']['item_code'];
//                    $freightToBuyQty[$lineId]['order_id'] =  $lineInfos[$lineId]['order_id'];
//                }
                // 28377 后面补运费重新计算-无需校验
               /* foreach ($freightInfo as $count=> $freight){
                    $europeFreight[$lineId][$count]['order_product_id'] = $freight['freight_order_product_id'];
                    $europeFreight[$lineId][$count]['per_qty'] = $freight['freight_per_qty'];
                    $europeFreight[$lineId][$count]['product_id'] =  $freight['freight_sku_product_id'];
                    $europeFreight[$lineId][$count]['from'] = $this->country_name;
                    $europeFreight[$lineId][$count]['to'] = $lineInfos[$lineId]['ship_country'];
                    $europeFreight[$lineId][$count]['zip_code'] = $lineInfos[$lineId]['ship_zip_code'];
                    $europeFreight[$lineId][$count]['line_id'] = $lineId;
                    $freightToBuyQty[$lineId][$count]['qty'] = $freight['freight_qty'];
                    $freightToBuyQty[$lineId][$count]['line_qty'] = $orderLine['salesOrderLine']['qty'];
                    $freightToBuyQty[$lineId][$count]['item_code'] = $orderLine['salesOrderLine']['item_code'];
                    $freightToBuyQty[$lineId][$count]['order_id'] =  $lineInfos[$lineId]['order_id'];
                }*/
            }
            //校验订单sku对应数量数量,需要的购买数量,囤货库存数量
            $skuArray = array_unique($skuArray);
            $result = $this->checkBuyQty($needQty, $needToBuyQty, $useCostQty, $skuArray, $needQtyShow);
            if(!$result['status']){
                return $result;
            }
            //校验补运费产品购买的数量 - 28377 后面补运费重新计算-无需校验
            /*if(!empty($freightToBuyQty)) {
                $freightResult = $this->checkFreightQty($freightToBuyQty, $europeFreight);
                if (!$freightResult['status']) {
                    return $freightResult;
                }
            }*/
            //校验选择的交易方式是否满足
            $tradeResult = $this->checkTradeMode($orderLines,$tradeModeArr);
            if (!$tradeResult['status']) {
                return $tradeResult;
            }
        }
        return $result;
    }

    public function getTradeModeList($product_id, $qty)
    {
        $this->load->model('extension/module/price');
        $this->load->model('tool/image');
        /** @var ModelExtensionModulePrice $priceModel */
//        $product_id = get_value_or_default($this->request->post, 'product_id', 0);
        $priceModel = $this->model_extension_module_price;
        $transaction_info = $priceModel->getProductPriceInfo($product_id, $this->customer->getId(), [], false, true, ['filter_future_v3' => 1, 'qty' => $qty]);
        $result['buyer_flag'] = $transaction_info['base_info']['buyer_flag'];
        $result['type'] = $transaction_info['first_get']['type'] ?? 0;
        $result['seller_id'] = isset($transaction_info['base_info']['customer_id']) ? $transaction_info['base_info']['customer_id']:0;
        $result['seller_name'] = isset($transaction_info['base_info']['screenname']) ? $transaction_info['base_info']['screenname']:'';
        $result['image'] = $this->model_tool_image->resize(isset($transaction_info['base_info']['image']) ?$transaction_info['base_info']['image'] : '', 60, 60);
        $selection = 0;
        $result['price_all'] = $transaction_info['first_get']['price_all'];
        $result['freight_num'] =  $transaction_info['base_info']['freight'];
        $result['is_delicacy'] =  $transaction_info['base_info']['is_delicacy'];
        $result['price_all_show'] = $this->currency->formatCurrencyPrice($transaction_info['first_get']['price_all'], $this->session->data['currency']);
        if ($transaction_info['first_get']['type'] == ProductTransactionType::NORMAL) {
            $result['price'] = $transaction_info['first_get']['price_show'];
            $result['freight'] = $transaction_info['first_get']['freight_show'];
            if ($transaction_info['base_info']['unavailable'] == 1 || $transaction_info['base_info']['buyer_flag'] == 0) {
                $transaction_info['base_info']['status'] = 0;
            }
            $result['p_status'] = $transaction_info['base_info']['status'];
            $result['status'] = $transaction_info['base_info']['status'];
            if ($result['status'] == 0) {
                $result['quantity'] = 0;
            } else {
                $result['quantity'] = $transaction_info['base_info']['quantity'];
            }
            if (isset($transaction_info['base_info']['time_limit_qty'])) {
                $result['time_limit_qty'] = $transaction_info['base_info']['time_limit_qty'] ?? 0;

                if ($result['time_limit_qty'] > $result['quantity']) {
                    $result['max_quantity'] = $result['time_limit_qty'];
                    $result['qty_type_str'] = 'Promotional';
                } else {
                    $result['max_quantity'] = $result['quantity'];
                    $result['qty_type_str'] = 'Non-promotional';
                }
            }

            $result['trade_mode'] = 0;
            $selection = 0;
        } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::REBATE) {

            $result['price'] = $transaction_info['first_get']['price_show'];
            $result['freight'] = $transaction_info['base_info']['freight_show'];
            $result['p_status'] = $transaction_info['base_info']['status'];
            $result['status'] = $transaction_info['base_info']['status'];
            if ($result['status'] == 0) {
                $result['quantity'] = 0;
            } else {
                $result['quantity'] = $transaction_info['base_info']['quantity'];
            }
            $result['trade_mode'] = $transaction_info['first_get']['id'] . '_' . $transaction_info['first_get']['type'];
            $selection = $transaction_info['first_get']['id'];
        } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::MARGIN) {
            $result['price'] = $transaction_info['first_get']['price_show'];
            $result['freight'] = $transaction_info['base_info']['freight_show'];
            $result['p_status'] = $transaction_info['base_info']['status'];
            $result['status'] = $transaction_info['base_info']['status'];
            if ($result['status'] == 0) {
                $result['quantity'] = 0;
            } else {
                $result['quantity'] = $transaction_info['first_get']['left_qty'];
            }
            $result['trade_mode'] = $transaction_info['first_get']['id'] . '_' . $transaction_info['first_get']['type'];
            $selection = $transaction_info['first_get']['id'];

        } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::FUTURE) {
            $result['price'] = $transaction_info['first_get']['price_show'];
            $result['freight'] = $transaction_info['base_info']['freight_show'];
            $result['p_status'] = $transaction_info['base_info']['status'];
            $result['status'] = $transaction_info['base_info']['status'];
            if ($result['status'] == 0) {
                $result['quantity'] = 0;
            } else {
                $result['quantity'] = $transaction_info['first_get']['left_qty'];
            }
            $result['trade_mode'] = $transaction_info['first_get']['id'] . '_' . $transaction_info['first_get']['type'];
            $selection = $transaction_info['first_get']['id'];
        } elseif ($transaction_info['first_get']['type'] == ProductTransactionType::SPOT) {
            // 议价协议不参与导单，做特殊化处理
            $result['price'] = $transaction_info['base_info']['price_show'];
            $result['freight'] = $transaction_info['base_info']['freight_show'];
            if ($transaction_info['base_info']['unavailable'] == 1 || $transaction_info['base_info']['buyer_flag'] == 0) {
                $transaction_info['base_info']['status'] = 0;
            }
            $result['p_status'] = $transaction_info['base_info']['status'];
            $result['status'] = $transaction_info['base_info']['status'];
            if ($result['status'] == 0) {
                $result['quantity'] = 0;
            } else {
                $result['quantity'] = $transaction_info['base_info']['quantity'];
            }
            $result['trade_mode'] = 0;
            $result['price_all'] = $transaction_info['base_info']['price_all'];
            $result['price_all_show'] = $this->currency->formatCurrencyPrice($transaction_info['base_info']['price_all'], $this->session->get('currency'));
            $selection = 0;
        }

        $selectItem = [];
        //构造一下select框

        $selectItem[] = [
            'key' => 0,
            'value' => 'Normal Transaction',
            'selected' => $selection == 0 ? 1 : 0,
        ];
        foreach ($transaction_info['transaction_type'] as $item) {
            $vItem = '';
            if ($item['type'] == ProductTransactionType::REBATE) {
                $vItem = 'Rebate:' . $item['agreement_code'];
            } elseif ($item['type'] == ProductTransactionType::MARGIN) {
                $vItem = 'Margin:' . $item['agreement_code'];
            } elseif ($item['type'] == ProductTransactionType::FUTURE) {
                $vItem = 'Future Goods:' . $item['agreement_code'];
            } elseif ($item['type'] == ProductTransactionType::SPOT) {
                // #35317 导单增加议价
                $vItem = 'Spot Price:' . $item['agreement_code'];
            }

            $selectItem[] = [
                'key' => $item['id'] . '_' . $item['type'],
                'type' => $item['type'],
                'version' => $item['version'],
                'value' => $vItem,
                'left_time_secs' => $item['left_time_secs'],
                'day' => $item['day'] ?? 0,
                'selected' => $selection == $item['id'] ? 1 : 0,
            ];
        }
        $result['transaction_type'] = $selectItem;
        return $result;
    }

    /**
     * 校验销售订单实际信息与页面数据
     * @param array $needQtyArr
     * @param array $needToBuyQtyArr
     * @param array $useCostQtyArr
     * @param array $skuArray
     * @param array $needQtyShow
     * @return mixed
     */
    public function checkBuyQty(array $needQtyArr, array $needToBuyQtyArr, array $useCostQtyArr, array $skuArray,array $needQtyShow)
    {
        $customer_id = $this->customer->getId();
        // 获取最新库存
        $productCostMap = $this->model->getCostBySkuArray($skuArray, $customer_id);
        //校验$useCostQtys
        foreach ($useCostQtyArr as $key => $useCostQty) {
            //该sku的库存
            $costQty = $productCostMap[$key] ?? 0;
            if ($costQty < $useCostQty) {
                //页面上库存大于现有库存
                $result['status'] = false;
                Logger::salesOrder($key."可用的囤货库存不足，请重新选择 ", 'warning');
                $result['msg'] = "The inventory you requested exceeds the inventory quantity reserved. Please edit and try again.";
                return $result;
            }
        }
        //校验需要的sku与页面显示的sku,及库存
        foreach ($needQtyArr as $sku => $needQty) {
            if (isset($needToBuyQtyArr[$sku])) {
                $needBuyQty = $needToBuyQtyArr[$sku];
            } else {
                $needBuyQty = 0;
            }
            if (isset($useCostQtyArr[$sku])) {
                $useCostQty = $useCostQtyArr[$sku];
            } else {
                $useCostQty = 0;
            }
            if ($needQty <> ($needBuyQty + $useCostQty)) {
                $result['status'] = false;
                Logger::salesOrder('销售订单数量变化，无法下单', 'warning');
                $result['msg'] = "This quantity is not available for purchase.";
                return $result;
            }
        }
        //检验sku
        foreach ($needQtyShow as $sku=> $needShow){
            if(!in_array($sku,$skuArray)){
                $result['status'] = false;
                Logger::salesOrder('sku异常，无法下单', 'warning');
                $result['msg'] = "This SKU is not available for purchase.";
                return $result;
            }
        }
        $result['status'] = true;
        return $result;
    }

    public function checkTradeMode(array $orderLines, $tradeModeArr)
    {
        $returnResult = [];
        $returnResult['status'] = true;
        foreach ($orderLines as $orderLine) {
            $salesOrderLine = $orderLine['salesOrderLine'];
            $product_id = isset($salesOrderLine['product_id']) ? $salesOrderLine['product_id'] : '';
            $to_buy_qty = $salesOrderLine['to_buy_qty'];
            $line_id = $orderLine['id'];
            $seller_id = $tradeModeArr[$line_id]['store'];
            $trade = $tradeModeArr[$line_id]['trade'];
            $item_code = $salesOrderLine['item_code'];
            if ($to_buy_qty > 0) {
                $result = $this->getTransactionTypeInfoByProductId($product_id, $trade, $seller_id, $to_buy_qty);
                $quantity = $result['quantity'];
                if ($quantity < $to_buy_qty) {
                    $returnResult['status'] = false;
                    Logger::salesOrder($item_code . ",的待购买的库存不足", 'warning');
                    $returnResult['msg'] = "Requested product quantity exceeds amount in-stock. Please enter a lower quantity.";
                    break;
                }
            }
        }
        return $returnResult;
    }

    private function saveMatchModalInfo($orderLines){
        $this->load->model('account/sales_order/match_inventory_window');
        $purchaseRecord = [];
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $run_id = $msectime;
        $buyer_id = $this->customer->getId();
        $associateArr = [];//预绑定库存
        $costInfosList = []; //暂存库存明细,避免多笔销售订单使用同一个sku导致分配一样的情况
        $orderInfoList = []; // 对应的销售订单详情
        $freightMap = []; //店铺的欧洲补运费产品
        foreach ($orderLines as $orderLine){
            $salesLineInfo = $orderLine['salesOrderLine'];
            $tradeArr = explode("_", isset($salesLineInfo['trade']) ? $salesLineInfo['trade'] : '');
            //交易方式
            $type_id =  count($tradeArr)>1?$tradeArr[1]:$tradeArr[0];
            //交易类型协议
            $agreement_id = count($tradeArr)>1?$tradeArr[0]:'';
            //如果有需要预分配库存
            $itemCode = $salesLineInfo['item_code'];
            $useStockQty = $salesLineInfo['qty'] - $salesLineInfo['to_buy_qty'];

            $costItemOrderList = [];
            $purchaseItemOrderList = [];
            if($useStockQty > 0){
                //使用了囤货库存
                if (!empty($costInfosList[$itemCode])) {
                    $costInfos = $costInfosList[$itemCode];
                } else {
                    $costInfos = $this->model->getPurchaseOrderInfo($itemCode, $buyer_id);
                }
                foreach ($costInfos as &$costInfo){
                    //该采购订单明细剩余可用库存
                    $leftQty = $costInfo['left_qty'];
                    if($leftQty>0) {
                        $associateItem = [
                            'sales_order_id' => $salesLineInfo['sales_order_id'],
                            'sales_order_line_id' => $salesLineInfo['id'],
                            'order_id' => $costInfo['order_id'],
                            'order_product_id' => $costInfo['order_product_id'],
                            'product_id' => $costInfo['product_id'],
                            'seller_id' => $costInfo['seller_id'],
                            'buyer_id' => $buyer_id,
                            'run_id' => $run_id,
                            'status' => 0,
                            'memo' => 'purchase record add',
                            'associate_type' => 1,
                            'CreateUserName' => 'admin',
                            'CreateTime' => date('Y-m-d H:i:s'),
                            'ProgramCode' => 'V1.0'
                        ];
                        if ($leftQty >= $useStockQty) {
                            $associateItem['qty'] = $useStockQty;
                            $costInfo['left_qty'] = $leftQty - $useStockQty;
                            $useStockQty = 0;
                            $associateArr[] = $associateItem;
                            $costItemOrderList[] = $associateItem;
                            break;
                        } else {
                            //该采购订单明细不够使用
                            $associateItem['qty'] = $leftQty;
                            //修改还需使用的数量
                            $useStockQty = $useStockQty - $leftQty;
                            $costInfo['left_qty'] = 0;
                            $associateArr[] = $associateItem;
                            $costItemOrderList[] = $associateItem;
                        }
                    }
                }
                if ($useStockQty > 0) {
                    //所有明细都用完了，但是还是不够使用
                    $msg = $itemCode . ' not has enough available inventory.';
                    throw new Exception($msg);
                }
                //暂存库存明细
                $costInfosList[$itemCode] = $costInfos;
            }

            $purchaseItem = array(
                'order_id'=>$salesLineInfo['sales_order_id'],
                'line_id'=>$salesLineInfo['id'],
                'item_code'=>$salesLineInfo['item_code'],
                'product_id'=> isset($salesLineInfo['product_id']) ? $salesLineInfo['product_id'] : null,
                'sales_order_quantity'=>$salesLineInfo['qty'],
                'quantity' =>$salesLineInfo['to_buy_qty'],
                'type_id'=>$type_id,
                'agreement_id'=>$agreement_id,
                'customer_id'=>$buyer_id,
                'seller_id' =>$salesLineInfo['store'],
                'run_id'=>$run_id,
                'memo'=>'purchase record',
                'create_user_name'=>$buyer_id,
                'create_time'=>date('Y-m-d H:i:s'),
                'program_code'=>'V1.0'
            );
            $purchaseRecord[] = $purchaseItem;
            $purchaseItemOrderList[] = $purchaseItem;

            // 处理补运费逻辑
            $this->dealSaveOrderFreight($purchaseRecord, $orderInfoList, $freightMap, $salesLineInfo['sales_order_id'], $costItemOrderList, $purchaseItemOrderList, $run_id);

            /*//保存欧洲运费数据
            $freightInfo = [];
            foreach ($orderLine['salesOrderLine'] as $key => $info){
                $splitArr = explode('|',$key);
                $name = $splitArr[0];
                $count = isset($splitArr[1]) ? $splitArr[1] : '';
                if(strstr($name,'freight') != false) {
                    $freightInfo[$count][$name] = $info;
                }
            }
            foreach ($freightInfo as $freight){
                $purchaseRecord[] = array(
                    'order_id' => $salesLineInfo['sales_order_id'],
                    'line_id' => $salesLineInfo['id'],
                    'item_code' => $freight['freight_item_code'],
                    'product_id' => $freight['freight_product_id'],
                    'sales_order_quantity' => $freight['freight_qty'],
                    'quantity' => $freight['freight_qty'],
                    'type_id' => 0,
                    'agreement_id' => '',
                    'customer_id' => $buyer_id,
                    'seller_id' => $freight['freight_seller_id'],
                    'run_id' => $run_id,
                    'memo' => 'purchase record',
                    'create_user_name' => $buyer_id,
                    'create_time' => date('Y-m-d H:i:s'),
                    'program_code' => 'V1.0'
                );
            }*/
        }


        $this->orm->getConnection()->transaction(function ()use($purchaseRecord,$associateArr){
            $this->model->savePurchaseRecord($purchaseRecord);
            //下单页预绑定
            if (!empty($associateArr)) {
                $this->model->associateAdvance($associateArr);
            }
        });
        return $run_id;
    }

    /**
     * 保存导入订单信息时 - 实时获取补运费信息
     *
     * @param $purchaseRecord
     * @param array $orderInfoList
     * @param $freightMap
     * @param $salesOrderId
     * @param $costItemOrderList
     * @param $purchaseItemOrderList
     * @param $runId
     * @return bool
     * @throws Exception
     */
    private function dealSaveOrderFreight(&$purchaseRecord, &$orderInfoList, &$freightMap, $salesOrderId, $costItemOrderList, $purchaseItemOrderList, $runId)
    {
        // 获取销售单信息
        if (!isset($orderInfoList[$salesOrderId])) {
            $orderInfoList[$salesOrderId] = CustomerSalesOrder::where('id', $salesOrderId)->first();
            if (empty($orderInfoList[$salesOrderId])) {
                // 没有找到对应的销售订单记录
                $msg = 'Invalid operation ';
                throw new Exception($msg);
            }
        }
        // #37317 免税buyer限制销售订单的地址(德国)
        if (strtoupper(trim($orderInfoList[$salesOrderId]['ship_country'])) == CountryCode::GERMANY && $this->customer->isEuVatBuyer()) {
            throw new Exception(self::NOT_VAT_ADDRESS_NOTICE);
        }

		// 判断是否欧洲国际单 - 不是不用进行补运费处理
        if ($orderInfoList[$salesOrderId]['is_international'] != self::EUROPE_INTERNATIONAL) {
            return true;
        }
        // 获取补运费产品信息
        if (empty($freightMap)) {
            $freightMap = $this->model->getFreightSku();
        }

        $europeFreightMap = [];
        // 使用库存
        foreach ($costItemOrderList as $costUseInfo) {
            $europeFreight = [];

            $europeFreight['product_id'] = $costUseInfo['product_id'];
            $europeFreight['from'] = $this->country_name;
            $europeFreight['to'] = $orderInfoList[$salesOrderId]->ship_country;
            $europeFreight['zip_code'] = $orderInfoList[$salesOrderId]->ship_zip_code;
            $europeFreight['line_id'] = $costUseInfo['sales_order_line_id'];
            $europeFreight['header_id'] = $salesOrderId;
            $europeFreight['order_product_id'] = $costUseInfo['order_product_id'];
            $europeFreight['qty'] = $costUseInfo['qty'];
            $europeFreight['seller_id'] =  $costUseInfo['seller_id'];

            $europeFreightMap[] = $europeFreight;
        }
        // 实时采购
        foreach ($purchaseItemOrderList as $purchaseInfo) {
            $europeFreight = [];
            if ($purchaseInfo['quantity'] > 0) { // 采购数量大于0处理
                $europeFreight['product_id'] = $purchaseInfo['product_id'];
                $europeFreight['from'] = $this->country_name;
                $europeFreight['to'] = $orderInfoList[$salesOrderId]->ship_country;
                $europeFreight['zip_code'] = $orderInfoList[$salesOrderId]->ship_zip_code;
                $europeFreight['line_id'] = $purchaseInfo['line_id'];
                $europeFreight['header_id'] = $salesOrderId;
                $europeFreight['seller_id'] = $purchaseInfo['seller_id'];
                $europeFreight['qty'] = $purchaseInfo['quantity'];

                $europeFreightMap[] = $europeFreight;
            }
        }

        $freightInfos = $this->europe_freight_model->getFreight($europeFreightMap);

        foreach ($freightInfos as $freightInfo) {
            if (empty($freightMap[$freightInfo['seller_id']])) { // 没有取到店铺的补运费产品
                throw new Exception('Invalid operation ');
            }

            switch ($freightInfo['code']) {
                case self::EUROPE_FREIGHT_SUCCESS_STATUS:
                    $freightQty = (int)(ceil($freightInfo['freight'])*$freightInfo['qty']);
                    $purchaseRecord[] = array(
                        'order_id' => $freightInfo['header_id'],
                        'line_id' => $freightInfo['line_id'],
                        'item_code' => $freightMap[$freightInfo['seller_id']]['sku'],
                        'product_id' => $freightMap[$freightInfo['seller_id']]['product_id'],
                        'sales_order_quantity' => $freightQty,
                        'quantity' => $freightQty,
                        'type_id' => 0,
                        'agreement_id' => '',
                        'customer_id' => $this->customer->getId(),
                        'seller_id' => $freightInfo['seller_id'],
                        'run_id' => $runId,
                        'memo' => 'purchase record',
                        'create_user_name' => $this->customer->getId(),
                        'create_time' => date('Y-m-d H:i:s'),
                        'program_code' => 'V1.0'
                    );
                    break;
                case self::EUROPE_FREIGHT_FAIL_PRODUCT_STATUS:
                    throw new Exception(self::EUROPE_FREIGHT_PRODUCT_ERROR_MSG);
                    break;
                case self::EUROPE_FREIGHT_FAIL_COUNTRY_STATUS:
                    throw new Exception(self::EUROPE_FREIGHT_COUNTRY_ERROR_MSG);
                    break;
                case self::NOT_VAT_ADDRESS_STATUS: // #37317 免税buyer限制销售订单的地址(是否为非德国的欧盟国家)
                    throw new Exception(self::NOT_VAT_ADDRESS_NOTICE);
                    break;
                default:
            }
        }

        return true;
    }

    private function associateOrder($run_id,$buyer_id, $safeguards = [])
    {
        $this->load->model('account/sales_order/match_inventory_window');
        $matchModel = $this->model_account_sales_order_match_inventory_window;
        try {
            $this->db->beginTransaction();
            //最后一次校验囤货库存
            //查询使用库存的数据
            $useStockRecords = $matchModel->getUseInventoryRecord($run_id, $buyer_id);
            //使用的sku的数组
            $stockSkuArr = [];
            $stockSkuAndQty = [];
            foreach ($useStockRecords as $useStockRecord) {
                $item_code = $useStockRecord['item_code'];
                $qty = $useStockRecord['useStockQty'];
                $stockSkuArr[] = $item_code;
                if (isset($stockSkuAndQty[$item_code])) {
                    $stockSkuAndQty[$item_code] = $stockSkuAndQty[$item_code] + $qty;
                } else {
                    $stockSkuAndQty[$item_code] = $qty;
                }
            }

            // 获取囤货产品的锁定数量
            $inventoryLockSkuQtyMap = app(BuyerStockRepository::class)->getInventoryLockSkuQtyMapBySalesOrderPreAssociated(strval($run_id), intval($buyer_id));

            //查看这些sku的现有库存
            $productCostMap = $matchModel->getCostBySkuArray($stockSkuArr, $buyer_id);
            foreach ($productCostMap as $sku => $productCost) {
                // 这边判断需要排查当前销售单已锁的囤货库存
                $productCost = $productCost + ($inventoryLockSkuQtyMap[$sku] ?? 0);
                if ($stockSkuAndQty[$sku] > $productCost) {
                    $msg = $sku . ' need use ' . $stockSkuAndQty[$sku] . ' available qty, but now available qty is ' . $productCost;
                    throw new Exception($msg);
                }
            }

            //下单页使用库存的情况
            $useStockResults = $this->model->getUseInventoryRecord($run_id,$buyer_id);
            $useStockSkuArr = [];
            foreach ($useStockResults as $useStockResult){
                $item_code = $useStockResult['item_code'];
                $useStockSkuArr[] = $item_code;
            }
            $useStockSkuArr = array_unique($useStockSkuArr);
            $costInfos = [];
            foreach ($useStockSkuArr as $itemCode) {
                $costInfos[$itemCode] = $this->model->getPurchaseOrderInfo($itemCode, $buyer_id);
            }
            //需要绑定的数组
            $associateArr = [];
            //将使用囤货的部分找出来进行绑定
            foreach ($useStockResults as $useStockResult){
                $item_code = $useStockResult['item_code'];
                $useStockQty = $useStockResult['useStockQty'];
                foreach ($costInfos[$item_code] as &$costInfo){
                    //该采购订单明细剩余可用库存
                    $left_qty = $costInfo['left_qty'];
                    if($left_qty>0) {
                        if ($left_qty >= $useStockQty) {
                            $associateArr[] = array(
                                'sales_order_id' => $useStockResult['order_id'],
                                'sales_order_line_id' => $useStockResult['line_id'],
                                'order_id' => $costInfo['order_id'],
                                'order_product_id' => $costInfo['order_product_id'],
                                'qty' => $useStockQty,
                                'product_id' => $costInfo['product_id'],
                                'seller_id' => $costInfo['seller_id'],
                                'buyer_id' => $useStockResult['customer_id'],
                                'memo' => 'purchase record add',
                                'CreateUserName' => 'admin',
                                'CreateTime' => date('Y-m-d H:i:s'),
                                'ProgramCode' => 'V1.0'
                            );
                            $costInfo['left_qty'] = $left_qty-$useStockQty;
                            $useStockQty = 0;
                            break;
                        } else {
                            //该采购订单明细不够使用
                            $associateArr[] = array(
                                'sales_order_id' => $useStockResult['order_id'],
                                'sales_order_line_id' => $useStockResult['line_id'],
                                'order_id' => $costInfo['order_id'],
                                'order_product_id' => $costInfo['order_product_id'],
                                'qty' => $left_qty,
                                'product_id' => $costInfo['product_id'],
                                'seller_id' => $costInfo['seller_id'],
                                'buyer_id' => $useStockResult['customer_id'],
                                'memo' => 'purchase record add',
                                'CreateUserName' => 'admin',
                                'CreateTime' => date('Y-m-d H:i:s'),
                                'ProgramCode' => 'V1.0'
                            );
                            //修改还需使用的数量
                            $useStockQty = $useStockQty - $left_qty;
                            $costInfo['left_qty'] = 0;
                        }
                    }
                }
                if($useStockQty>0){
                    //所有明细都用完了，但是还是不够使用
                    $msg = $item_code . ' not has enough available inventory.';
                    throw new Exception($msg);
                }
            }
            $associatedRecordId = [];
            $salesOrderList = [];// 暂存销售订单明细
            foreach ($associateArr as $associatedRecord) {
                //依次插入数据，并获取id，用于下一步修改仓租信息
                $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(intval($associatedRecord['order_product_id']), intval($associatedRecord['qty']), $this->customer->isJapan() ? 0 : 2);
                $associatedRecord = array_merge($associatedRecord, $discountsAmount);
                $associatedRecordId[] = $this->model->associatedOrderByRecordGetId($associatedRecord);
                $sales_order_id = $associatedRecord['sales_order_id'];
                $sales_order_line_id = $associatedRecord['sales_order_line_id'];
                if (empty($salesOrderList[$sales_order_id]) || !in_array($sales_order_line_id, $salesOrderList[$sales_order_id])) {
                    $salesOrderList[$sales_order_id][] = $sales_order_line_id;
                }
            }
            $this->load->model('account/customer_order_import');
            foreach ($salesOrderList as $salesOrderId => $salesOrderLineIds) {
                foreach ($salesOrderLineIds as $salesOrderLineId) {
                    // 更新订单明细信息
                    $this->model_account_customer_order_import->updateCustomerSalesOrderLine($salesOrderId, $salesOrderLineId);
                }
                // 更新订单状态
                $this->model_account_customer_order_import->updateCustomerSalesOrder($salesOrderId);
            }
            //修改仓租表信息
            app(StorageFeeService::class)->bindByOrderAssociated($associatedRecordId);

            // 创建仓租的费用单
            $feeOrderRunId = app(FeeOrderService::class)->generateFeeOrderRunId();
            // 创建保单的费用单
            if ($safeguards) {
                $safeguards = array_combine(array_keys($safeguards), array_column($safeguards, 'safeguard_config_id'));
                foreach ($safeguards as $salesOrderId => $safeguardIds) {
                    $res = app(SafeguardConfigRepository::class)->checkCanBuySafeguardBuSalesOrder($salesOrderId, $safeguardIds);
                    if (!$res['success']) {
                        throw new Exception('Information shown on this screen has been updated.  Please refresh this page.');
                    }
                }
                app(FeeOrderService::class)->createSafeguardFeeOrder($safeguards, $feeOrderRunId, $run_id);
            }

            // 释放销售单囤货库存
            app(BuyerStockService::class)->releaseInventoryLockBySalesOrderPreAssociated(array_keys($salesOrderList), (int)$buyer_id);

            $this->db->commit();
            $result['status'] = true;
            return $result;
        }catch (Exception $e){
            $this->db->rollback();
            Logger::salesOrder('销售订单绑定失败:run_id='.$run_id.",".$e->getMessage(), 'error');
            $result['status'] = false;
            $result['msg'] = $e->getMessage();
            return $result;
        }
    }

    public function getPriceAndQtyByProductId(){
        $product_id = $this->request->get('product_id', 0);
        $toBePaid = $this->request->get('toBePaid', 0);
        $lineId = $this->request->get('lineId', 0);
        $this->load->model('tool/image');
        $json = [];
        if($product_id !=0 ) {
            $json = $this->getTradeModeList($product_id, $toBePaid);
            //查看初始化展示的seller对应的所有的补运费产品
            $salesOrderInfo = $this->model->getSalesOrderInfoByLineId($lineId);
            $europeFreight[$lineId]['product_id'] = $product_id;
            $europeFreight[$lineId]['from'] = $this->country_name;
            $europeFreight[$lineId]['to'] = $salesOrderInfo['ship_country'];
            $europeFreight[$lineId]['zip_code'] = $salesOrderInfo['ship_zip_code'];
            $europeFreight[$lineId]['line_id'] = $lineId;
            $freightInfo = $this->europe_freight_model->getFreight(array_values($europeFreight));
            //获取该产品对于店铺的运费产品
            $freightSku = $this->model->getFreightSkuBySellertId($json['seller_id']);
            if ($freightSku) {
                $json['freight_image'] = $this->model_tool_image->resize($freightSku['image'], 60, 60);
                $json['freight_sku'] = $freightSku['sku'];
                $json['freight_product_id'] = $freightSku['product_id'];
            }
            $json['sales_order_id'] = $salesOrderInfo['order_id'];
            switch ($freightInfo[0]['code']) {
                case 200:
                    $freight = ceil($freightInfo[0]['freight']) *$toBePaid;
                    $json['freight_curr'] = $this->currency->formatCurrencyPrice((int)(ceil($freightInfo[0]['freight']) * $toBePaid),  $this->session->get('currency'));
                    $json['freight_qty'] = (int)$freight / 1;
                    break;
                default:
                    $json['freight_error_flag'] = true;
                    $json['freight_error_msg'] = array('freight_error' => $freightInfo[0]['msg']);
            }
            if(bccomp($json['price_all'] * $toBePaid,0) == 0) {
                $json['detail_show'] = false;
            }else{
                $json['detail_show'] = true;
            }
            $json['price_all'] = $this->currency->formatCurrencyPrice($json['price_all'] * $toBePaid,session('currency'));
        }
        return $this->response->json($json);
    }

    public function getPriceAndQtyByTransactionType()
    {
        $product_id = request('product_id', 0);
        $transaction_type = request('transaction_type');
        $seller_id = request('sellerId');
        $toBePaid = request('toBePaid', 0);
        $json = [];
        if ($product_id) {
            $json = $this->getTransactionTypeInfoByProductId($product_id, $transaction_type, $seller_id, $toBePaid);
            if (bccomp(($json['price_all'] * $toBePaid), 0) == 0) {
                $json['detail_show'] = false;
            } else {
                $json['detail_show'] = true;
            }
            $json['price_all'] = $this->currency->formatCurrencyPrice($json['price_all'] * $toBePaid, session('currency'));
        }
        $this->response->json($json);
    }

    public function getTransactionTypeInfoByProductId($product_id,$transaction_type,$seller_id,$quantity){
        $id = $seller_id;
        $this->load->language('account/customer_order_import');
        //目的为了查询出当前类型下的价格
        $this->load->model('extension/module/price');
        /** @var ModelExtensionModulePrice $priceModel */
        $priceModel = $this->model_extension_module_price;
        $transaction_type = explode('_', $transaction_type);
        $json = [];
        $transaction_info = $priceModel->getProductPriceInfo($product_id, $this->customer->getId(), [], false, true, ['qty' => $quantity]);
        $is_expire = false;
        $json['no_select'] = false;
        $json['is_expire'] = $is_expire;
        $unavailable = $transaction_info['base_info']['unavailable'];
        $endTransactionType = end($transaction_type);
        if($endTransactionType == ProductTransactionType::NORMAL){
            //普通的
            //导销售订单后购买库存，选择普通交易，如果购买数量触发阶梯价（无论是否议价，只要数量触发阶梯价），下单弹框和下单购买页面需要显示商品的阶梯价格
            $home_pick_up_price = db('oc_wk_pro_quote_details')
                ->where([
                    ['min_quantity','<=',$quantity],
                    ['max_quantity','>=',$quantity],
                    ['product_id','=',$product_id],
                ])
                ->value('home_pick_up_price');
            if ($home_pick_up_price && !$transaction_info['base_info']['is_delicacy']) {
                // 31737 导单阶梯价修改免税价
                $home_pick_up_price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($transaction_info['base_info']['customer_id']), $this->customer->getId(), $home_pick_up_price);
                $home_pick_up_price = app(MarketingDiscountRepository::class)->getPriceAfterDiscount($this->customer->getId(), $product_id, $home_pick_up_price, $quantity, ProductTransactionType::SPOT);
                $json['price_all'] = round(($home_pick_up_price + $transaction_info['base_info']['freight']), $transaction_info['base_info']['precision']);
                $json['price']      = $this->currency->format($home_pick_up_price, session('currency'));
            }else{
                $json['price_all']  = $transaction_info['base_info']['price_all'];
                $json['price']      = $transaction_info['base_info']['price_show'];
            }

            $json['freight']    = $transaction_info['base_info']['freight_show'];
            $json['p_status']   = $transaction_info['base_info']['status'];
            $json['status']     = $transaction_info['base_info']['status'];
            if ($json['status'] == 0) {
                $json['quantity'] = 0;
            } else {
                $json['quantity'] = $transaction_info['base_info']['quantity'];
            }
            $json['qty_type_str'] = $transaction_info['base_info']['qty_type_str'];

        } else {
            $transactionList = collect($transaction_info['transaction_type']);
            if ($transactionList->isEmpty() ||
                !($info = $transactionList
                    ->where('type', $endTransactionType)
                    ->where('id', $transaction_type[0])->first())
            ) {
                $is_expire = true;
                $json['expire_error'] = '';
                switch ($endTransactionType) {
                    case ProductTransactionType::REBATE:
                        $json['expire_error'] = sprintf($this->language->get('error_rebate_approve_expire'), $this->orm->table('oc_rebate_agreement')->where('id', current($transaction_type))->value('agreement_code'));
                        break;
                    case ProductTransactionType::MARGIN:
                        $json['expire_error'] = sprintf($this->language->get('error_margin_approve_expire'), $this->orm->table('tb_sys_margin_agreement')->where('id', current($transaction_type))->value('agreement_id'));
                        break;
                    case ProductTransactionType::FUTURE:
                        $json['expire_error'] = sprintf($this->language->get('error_future_margin_approve_expire'), $this->orm->table('tb_sys_margin_agreement')->where('id', $transaction_type[0])->value('agreement_id'));
                        break;
                    case ProductTransactionType::SPOT:
                        $json['expire_error'] = sprintf($this->language->get('error_spot_approve_expire'), ProductQuote::where('id', $transaction_type[0])->value('agreement_no'));
                        break;
                }
            } else {
                $json['price_all'] = $info['price_all'];
                $json['price'] = $info['price_show'];
                $json['freight'] = $transaction_info['base_info']['freight_show'];
                $json['p_status'] = $transaction_info['base_info']['status'];
                $json['status'] = $transaction_info['base_info']['status'];
                if ($json['status'] == 0) {
                    $json['quantity'] = 0;
                } else {
                    switch ($endTransactionType) {
                        case ProductTransactionType::REBATE:
                            $json['quantity'] = $transaction_info['base_info']['quantity'];
                            break;
                        case ProductTransactionType::MARGIN:
                        case ProductTransactionType::FUTURE:
                        case ProductTransactionType::SPOT:
                            $json['quantity'] = $info['left_qty'];
                            break;
                    }
                }
            }
        }

        if($transaction_info['base_info']['buyer_flag'] == 0){
            $json['p_status'] = 0;
            $json['status'] = 0;
        }
        if($unavailable == 1){
            $json['price'] = '-';
            $json['freight'] = '-';
        }
        if($json['status'] == '0' || $json['p_status'] == '0'){
            $json['price'] = '-';
            $json['freight'] = '-';
        }

        $json['id'] = $id;
        $json['product_id'] = $product_id;
        $json['combo_flag'] = $transaction_info['base_info']['combo_flag'];
        if( $json['status'] == 1){
            $json['status'] = 'Yes';
        }else{
            $json['status'] = 'No';
        }


        if($unavailable || $is_expire){
            $json['no_select'] = true;
            $json['id'] = $id;
            $json['is_expire'] = $is_expire;
            $json['product_id'] = '';
            $json['price'] = '';
            $json['quantity'] = '';
            $json['status'] = '';
            $json['freight'] = '';
            $json['price_all']  = '';
        }
        return $json;
    }

    public function checkFreightQty($freightToBuyQty,$europeFreight)
    {
        $result['msg'] = '';
        foreach ($europeFreight as $lineId => $freight){
            $freightInfos = $this->europe_freight_model->getFreight(array_values($freight));
            $freightToBuyCheck = [];
            foreach ($freightToBuyQty[$lineId] as $freightQty){
                $freightToBuyCheck['qty'] = isset($freightToBuyCheck['qty'])?($freightToBuyCheck['qty']+$freightQty['qty']):$freightQty['qty'];
                $freightToBuyCheck['line_qty'] = $freightQty['line_qty'];
                $freightToBuyCheck['item_code'] = $freightQty['item_code'];
                $freightToBuyCheck['order_id'] = $freightQty['order_id'];
            }
            $perFreight = 0;
            foreach ($freightInfos as $freightInfo){
                if ($freightInfo['code'] == 200) {
                    $perFreight += (int)($freightInfo['per_qty'] * ceil($freightInfo['freight']));
                } else {
                    $result['status'] = false;
                    $result['msg'] = 'Sales Order ID:'. $freightToBuyCheck['order_id'] .',Item Code:'. $freightToBuyCheck['item_code'] .','.$freightInfo['msg'];
                    break;
                }
            }
            if (! isset($result['status'])) {
                if ($perFreight == $freightToBuyCheck['qty']) {
                    $result['status'] = true;
                } else {
                    $result['status'] = false;
                    $result['msg'] = $result['status'] ? '' : 'Sales Order ID:' . $freightToBuyCheck['order_id'] . ',Item Code:' . $freightToBuyCheck['item_code'] . ',运费异常';
                }
            }
            if (! $result['status']) {
                break;
            }
        }
        return $result;
    }

    public function dropshipMatchBox(&$salesOrderLine,&$needCostMap,&$header_error_info,&$productCostMap,$storeArray,$error_info,$changeData=[])
    {
        $header_error_flag = false;
        //该明细最大使用库存数
        $costMap = ($productCostMap[$salesOrderLine['item_code']] ?? 0);
        $salesOrderLine['cost_map'] = $costMap > $salesOrderLine['qty'] ? $salesOrderLine['qty'] : $costMap;
        $error_flag = false;
        $address_error_flag = false;
        //明细错误信息梳理
        $salesOrderLine['address_error'] = isset($error_info[$salesOrderLine['header_id']]['address_error']) ? $error_info[$salesOrderLine['header_id']]['address_error'] : "";
        if (strlen($salesOrderLine['address_error']) > 0) {
            $address_error_flag = true;
        }
        $lineId = $salesOrderLine['id'];
        foreach ($changeData as $data){
            $changeLineId = $data['sales_order_line_id'];
            if($changeLineId == $lineId){
                $qty = isset($data['qty'])?max($data['qty'],0):0;
                $salesOrderLine['cost_map'] = $qty>$salesOrderLine['cost_map']?$salesOrderLine['cost_map']:$qty;
                break;
            }
        }
        $productCostMap[$salesOrderLine['item_code']] = $costMap - $salesOrderLine['cost_map'] ? $costMap - $salesOrderLine['cost_map'] : 0;
        //可以使用的库存,从上往下依次扣减
        $productCostQty = isset($productCostMap[$salesOrderLine['item_code']]) ? $productCostMap[$salesOrderLine['item_code']] : 0;
        $salesOrderLine['left_map'] = $productCostQty < $salesOrderLine['qty'] ? 0 : $productCostQty - $salesOrderLine['qty'];
        //该sku消耗的总数
        $needCostMap[$salesOrderLine['item_code']] = ($needCostMap[$salesOrderLine['item_code']] ?? 0) + $salesOrderLine['qty'];
        //销售订单error数组
        $headerErrorArr = isset($error_info[$salesOrderLine['header_id']]) ? $error_info[$salesOrderLine['header_id']] : [];
        //明细error数组
        $lineErrorArr = isset($error_info[$salesOrderLine['header_id']][$salesOrderLine['id']]) ? $error_info[$salesOrderLine['header_id']][$salesOrderLine['id']] : [];
        foreach ($headerErrorArr as $key => $headerError) {
            if ($key != 'address_error' && $key == $salesOrderLine['id']) {
                foreach ($headerError as $line) {
                    if (strlen($line) > 0 && (($salesOrderLine['qty'] - $salesOrderLine['cost_map']) > 0 || $salesOrderLine['order_status'] == CustomerSalesOrderStatus::LTL_CHECK)) {
                        $header_error_flag = true;
                        break 2;
                    }
                }
            }

        }
        foreach ($lineErrorArr as $key => $lineError) {
            //超大件LTL:64 无需购买的并且非超大件不校验错误
            if (strlen($lineError) > 0 && (($salesOrderLine['qty'] - $salesOrderLine['cost_map']) > 0 || $salesOrderLine['order_status'] == CustomerSalesOrderStatus::LTL_CHECK)) {
                $error_flag = true;
                break;
            }
        }
        $salesOrderLine['error_msg'] = $lineErrorArr;
        $salesOrderLine['error_flag'] = $error_flag;
        $salesOrderLine['address_error_flag'] = $address_error_flag;
        if (isset($header_error_info[$salesOrderLine['header_id']])) {
            if ($header_error_info[$salesOrderLine['header_id']] == false) {
                $header_error_info[$salesOrderLine['header_id']] = $header_error_flag;
            }
        } else {
            $header_error_info[$salesOrderLine['header_id']] = $header_error_flag;
        }
        $store_trade = [];
        $first_get = [];
        $first_trade = [];
        $toBePay = $salesOrderLine['qty'] - $salesOrderLine['cost_map'];

        foreach ($storeArray as &$store) {
            $store['trade_mode'] = $this->getTradeModeList($store['product_id'], $toBePay);
        }

        foreach ($storeArray as $storeTrade) {
            if (strtoupper($salesOrderLine['item_code']) == strtoupper($storeTrade['sku'])) {
                if($storeTrade['trade_mode']['trade_mode'] == 0 && !$storeTrade['trade_mode']['is_delicacy']){
                    $home_pick_up_price = db('oc_wk_pro_quote_details')
                        ->where([
                            ['min_quantity','<=',$toBePay],
                            ['max_quantity','>=',$toBePay],
                            ['product_id','=',$storeTrade['product_id']],
                        ])
                        ->value('home_pick_up_price');
                    if ($home_pick_up_price) {
                        // 31737 导单阶梯价修改免税价
                        $home_pick_up_price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($storeTrade['customer_id']), $this->customer->getId(), $home_pick_up_price);
                        $home_pick_up_price = app(MarketingDiscountRepository::class)->getPriceAfterDiscount($this->customer->getId(), $storeTrade['product_id'], $home_pick_up_price, $toBePay, ProductTransactionType::SPOT);
                        $storeTrade['trade_mode']['price_all'] = round(($home_pick_up_price + $storeTrade['trade_mode']['freight_num']), 2);
                        $storeTrade['trade_mode']['price'] = $this->currency->formatCurrencyPrice($home_pick_up_price, session('currency'));
                    }
                }
                $store_trade[] = $storeTrade;
                if (!isset($first_trade['trade_mode'])) {
                    //第一次遇到的交易方式
                    $first_trade['trade_mode'] = $storeTrade['trade_mode']['trade_mode'];
                    $first_trade['product_id'] = $storeTrade['product_id'];
                    $first_trade['seller_id'] = $storeTrade['customer_id'];
                    $first_trade['image_show'] = $storeTrade['image'];
                    if (bccomp($storeTrade['trade_mode']['price_all'] * $toBePay, 0) == 0) {
                        $first_trade['detail_show'] = false;
                    } else {
                        $first_trade['detail_show'] = true;
                    }
                    $first_trade['price_all_show'] = $this->currency->formatCurrencyPrice($storeTrade['trade_mode']['price_all'] * $toBePay, $this->session->data['currency']);
                }
                if (!isset($first_get['trade_mode'])) {
                    if ($storeTrade['trade_mode']['trade_mode'] != 0) {
                        //第一次复杂交易设置优先交易
                        $first_get['trade_mode'] = $storeTrade['trade_mode']['trade_mode'];
                        $first_get['product_id'] = $storeTrade['product_id'];
                        $first_get['seller_id'] = $storeTrade['customer_id'];
                        $first_get['image_show'] = $first_get['image_show'] = $this->model_tool_image->resize($storeTrade['image'],60,60);
                        if (bccomp($storeTrade['trade_mode']['price_all'] * $toBePay, 0) == 0) {
                            $first_get['detail_show'] = false;
                        } else {
                            $first_get['detail_show'] = true;
                        }
                        $first_get['price_all_show'] = $this->currency->formatCurrencyPrice($storeTrade['trade_mode']['price_all'] * $toBePay, $this->session->data['currency']);
                    }
                }
            }
        }
        if (!isset($first_get['trade_mode'])) {
            $first_get['trade_mode'] = isset($first_trade['trade_mode']) ? $first_trade['trade_mode'] : '';
            $first_get['product_id'] = isset($first_trade['product_id']) ? $first_trade['product_id'] : '';
            $first_get['seller_id'] = isset($first_trade['seller_id']) ? $first_trade['seller_id'] : '';
            $first_get['price_all_show'] = isset($first_trade['price_all_show']) ? $first_trade['price_all_show'] : '';
            $first_get['detail_show'] = isset($first_trade['detail_show']) ? $first_trade['detail_show'] : true;
            $first_get['image_show'] = $this->model_tool_image->resize(isset($first_trade['image_show']) ? $first_trade['image_show'] : '', 60, 60);
        }
        $salesOrderLine['first_get'] = $first_get;
        $salesOrderLine['store_trades'] = $store_trade;

        //如果囤的不够，也没有店铺购买，也没有提示错误的
        if (($salesOrderLine['qty'] - $salesOrderLine['cost_map']) > 0 && (!isset($store_trade) || empty($store_trade)) && !$salesOrderLine['error_flag']) {
            $salesOrderLine['error_msg']['purchase_error'] = 'This item is not available for purchase.';
            $salesOrderLine['error_flag'] = true;
        }

        //如果是国际单将first_get的数据放入补运费数组
//            if($salesOrderLine['is_international'] == 1){
//                //查询sku对于的最新的product_id
//                if(empty($first_get['product_id'])){
//                    $this->model->getFirstProductInfoBySku($salesOrderLine['item_code']);
//                }
//                $europeFreight[$salesOrderLine['id']]['product_id'] = $first_get['product_id'];
//                $europeFreight[$salesOrderLine['id']]['from'] = $this->country_name;
//                $europeFreight[$salesOrderLine['id']]['to'] = $salesOrderLine['ship_country'];
//                $europeFreight[$salesOrderLine['id']]['zip_code'] = $salesOrderLine['ship_zip_code'];
//                $europeFreight[$salesOrderLine['id']]['line_id'] = $salesOrderLine['id'];
//                $sellerShow[$salesOrderLine['id']]['seller_id'] = $first_get['seller_id'];
//                $sellerShow[$salesOrderLine['id']]['qty'] = $salesOrderLine['qty'];
//                $sellerShow[$salesOrderLine['id']]['order_id'] = $salesOrderLine['order_id'];
//                $sellerShow[$salesOrderLine['id']]['header_id'] = $salesOrderLine['header_id'];
//                $salesOrderLine['num'] = 2 * $salesOrderLine['num'];
//            }
    }

    public function europeInternationalMatchBox(&$salesOrderLine,&$needCostMap,&$header_error_info,&$productCostMap,$storeArray,$error_info,$changeData=[])
    {
        $header_error_flag = false;
        //该明细最大使用库存数
        $costMap = ($productCostMap[$salesOrderLine['item_code']] ?? 0);
        $salesOrderLine['cost_map'] = $costMap > $salesOrderLine['qty'] ? $salesOrderLine['qty'] : $costMap;
        $error_flag = false;
        $address_error_flag = false;
        //明细错误信息梳理
        $salesOrderLine['address_error'] = isset($error_info[$salesOrderLine['header_id']]['address_error']) ? $error_info[$salesOrderLine['header_id']]['address_error'] : "";
        if (strlen($salesOrderLine['address_error']) > 0) {
            $address_error_flag = true;
        }
        $lineId = $salesOrderLine['id'];
        foreach ($changeData as $data){
            $changeLineId = $data['sales_order_line_id'];
            if($changeLineId == $lineId){
                $qty = isset($data['qty'])?max($data['qty'],0):0;
                $salesOrderLine['cost_map'] = $qty>$salesOrderLine['cost_map']?$salesOrderLine['cost_map']:$qty;
                break;
            }
        }
        $productCostMap[$salesOrderLine['item_code']] = $costMap - $salesOrderLine['cost_map'] ? $costMap - $salesOrderLine['cost_map'] : 0;
        //可以使用的库存,从上往下依次扣减
        $productCostQty = isset($productCostMap[$salesOrderLine['item_code']]) ? $productCostMap[$salesOrderLine['item_code']] : 0;
        $salesOrderLine['left_map'] = $productCostQty < $salesOrderLine['qty'] ? 0 : $productCostQty - $salesOrderLine['qty'];
        //该sku消耗的总数
        $needCostMap[$salesOrderLine['item_code']] = ($needCostMap[$salesOrderLine['item_code']] ?? 0) + $salesOrderLine['qty'];
        //销售订单error数组
        $headerErrorArr = isset($error_info[$salesOrderLine['header_id']]) ? $error_info[$salesOrderLine['header_id']] : [];
        //明细error数组
        $lineErrorArr = isset($error_info[$salesOrderLine['header_id']][$salesOrderLine['id']]) ? $error_info[$salesOrderLine['header_id']][$salesOrderLine['id']] : [];
        foreach ($headerErrorArr as $key => $headerError) {
            if ($key != 'address_error' && $key == $salesOrderLine['id']) {
                foreach ($headerError as $line) {
                    if (strlen($line) > 0 && (($salesOrderLine['qty'] - $salesOrderLine['cost_map']) > 0 || $salesOrderLine['order_status'] == CustomerSalesOrderStatus::LTL_CHECK)) {
                        $header_error_flag = true;
                        break 2;
                    }
                }
            }

        }
        foreach ($lineErrorArr as $key => $lineError) {
            //超大件LTL:64 无需购买的并且非超大件不校验错误
            if (strlen($lineError) > 0 && (($salesOrderLine['qty'] - $salesOrderLine['cost_map']) > 0 || $salesOrderLine['order_status'] == CustomerSalesOrderStatus::LTL_CHECK)) {
                $error_flag = true;
                break;
            }
        }
        $salesOrderLine['error_msg'] = $lineErrorArr;
        $salesOrderLine['error_flag'] = $error_flag;
        $salesOrderLine['address_error_flag'] = $address_error_flag;
        if (isset($header_error_info[$salesOrderLine['header_id']])) {
            if ($header_error_info[$salesOrderLine['header_id']] == false) {
                $header_error_info[$salesOrderLine['header_id']] = $header_error_flag;
            }
        } else {
            $header_error_info[$salesOrderLine['header_id']] = $header_error_flag;
        }
        $store_trade = [];
        $first_get = [];
        $first_trade = [];
        $toBePay = $salesOrderLine['qty'] - $salesOrderLine['cost_map'];

        foreach ($storeArray as &$store) {
            $store['trade_mode'] = $this->getTradeModeList($store['product_id'], $toBePay);
        }
        foreach ($storeArray as $storeTrade) {
            if (strtoupper($salesOrderLine['item_code']) == strtoupper($storeTrade['sku'])) {
                if($storeTrade['trade_mode']['trade_mode'] == 0 && !$storeTrade['trade_mode']['is_delicacy']){
                    $home_pick_up_price = db('oc_wk_pro_quote_details')
                        ->where([
                            ['min_quantity','<=',$toBePay],
                            ['max_quantity','>=',$toBePay],
                            ['product_id','=',$storeTrade['product_id']],
                        ])
                        ->value('home_pick_up_price');
                    if ($home_pick_up_price) {
                        // 31737 导单阶梯价修改免税价
                        $home_pick_up_price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($storeTrade['customer_id']), $this->customer->getId(), $home_pick_up_price);
                        $home_pick_up_price = app(MarketingDiscountRepository::class)->getPriceAfterDiscount($this->customer->getId(), $storeTrade['product_id'], $home_pick_up_price, $toBePay, ProductTransactionType::SPOT);;
                        $storeTrade['trade_mode']['price_all'] = round(($home_pick_up_price + $storeTrade['trade_mode']['freight_num']), 2);
                        $storeTrade['trade_mode']['price'] = $this->currency->formatCurrencyPrice($home_pick_up_price, session('currency'));
                    }
                }
                $store_trade[] = $storeTrade;
                if (!isset($first_trade['trade_mode'])) {
                    //第一次遇到的交易方式
                    $first_trade['trade_mode'] = $storeTrade['trade_mode']['trade_mode'];
                    $first_trade['product_id'] = $storeTrade['product_id'];
                    $first_trade['seller_id'] = $storeTrade['customer_id'];
                    $first_trade['image_show'] = $storeTrade['image'];
                    if (bccomp($storeTrade['trade_mode']['price_all'] * $toBePay, 0) == 0) {
                        $first_trade['detail_show'] = false;
                    } else {
                        $first_trade['detail_show'] = true;
                    }
                    $first_trade['price_all_show'] = $this->currency->formatCurrencyPrice($storeTrade['trade_mode']['price_all'] * $toBePay, $this->session->data['currency']);
                }
                if (!isset($first_get['trade_mode'])) {
                    if ($storeTrade['trade_mode']['trade_mode'] != 0) {
                        //第一次复杂交易设置优先交易
                        $first_get['trade_mode'] = $storeTrade['trade_mode']['trade_mode'];
                        $first_get['product_id'] = $storeTrade['product_id'];
                        $first_get['seller_id'] = $storeTrade['customer_id'];
                        $first_get['image_show'] = $this->model_tool_image->resize($storeTrade['image'],60,60);
                        if (bccomp($storeTrade['trade_mode']['price_all'] * $toBePay, 0) == 0) {
                            $first_get['detail_show'] = false;
                        } else {
                            $first_get['detail_show'] = true;
                        }
                        $first_get['price_all_show'] = $this->currency->formatCurrencyPrice($storeTrade['trade_mode']['price_all'] * $toBePay, $this->session->data['currency']);
                    }
                }
            }
        }
        if (!isset($first_get['trade_mode'])) {
            $first_get['trade_mode'] = isset($first_trade['trade_mode']) ? $first_trade['trade_mode'] : '';
            $first_get['product_id'] = isset($first_trade['product_id']) ? $first_trade['product_id'] : '';
            $first_get['seller_id'] = isset($first_trade['seller_id']) ? $first_trade['seller_id'] : '';
            $first_get['price_all_show'] = isset($first_trade['price_all_show']) ? $first_trade['price_all_show'] : '';
            $first_get['detail_show'] = isset($first_trade['detail_show']) ? $first_trade['detail_show'] : true;
            $first_get['image_show'] = $this->model_tool_image->resize(isset($first_trade['image_show']) ? $first_trade['image_show'] : '', 60, 60);
        }
        $salesOrderLine['first_get'] = $first_get;
        $salesOrderLine['store_trades'] = $store_trade;

        //如果囤的不够，也没有店铺购买，也没有提示错误的
        if (($salesOrderLine['qty'] - $salesOrderLine['cost_map']) > 0 && (!isset($store_trade) || empty($store_trade)) && !$salesOrderLine['error_flag']) {
            $salesOrderLine['error_msg']['purchase_error'] = 'This item is not available for purchase.';
            $salesOrderLine['error_flag'] = true;
        }

        //如果是国际单将first_get的数据放入补运费数组
//            if($salesOrderLine['is_international'] == 1){
//                //查询sku对于的最新的product_id
//                if(empty($first_get['product_id'])){
//                    $this->model->getFirstProductInfoBySku($salesOrderLine['item_code']);
//                }
//                $europeFreight[$salesOrderLine['id']]['product_id'] = $first_get['product_id'];
//                $europeFreight[$salesOrderLine['id']]['from'] = $this->country_name;
//                $europeFreight[$salesOrderLine['id']]['to'] = $salesOrderLine['ship_country'];
//                $europeFreight[$salesOrderLine['id']]['zip_code'] = $salesOrderLine['ship_zip_code'];
//                $europeFreight[$salesOrderLine['id']]['line_id'] = $salesOrderLine['id'];
//                $sellerShow[$salesOrderLine['id']]['seller_id'] = $first_get['seller_id'];
//                $sellerShow[$salesOrderLine['id']]['qty'] = $salesOrderLine['qty'];
//                $sellerShow[$salesOrderLine['id']]['order_id'] = $salesOrderLine['order_id'];
//                $sellerShow[$salesOrderLine['id']]['header_id'] = $salesOrderLine['header_id'];
//                $salesOrderLine['num'] = 2 * $salesOrderLine['num'];
//            }
    }


    public function getCostDetailsBySku($skuArray, $customer_id)
    {
        $skuMap = $this->model->getCostDetailsBySkuArray($skuArray,$customer_id);
        $skuMap = array_filter($skuMap,function ($item){
            return $item['left_qty'] > 0;
        });

        return $skuMap;
    }


    public function dealCostProductMatch(&$salesOrderLine,&$skuCostMap,&$purchaseCostArr,$changeData=[])
    {
        //销售订单明细id
        $lineId = $salesOrderLine['id'];
        $changeLineId = 0;
        $changeQty = 0;
        foreach ($changeData as $data){
            $changeLineId = $data['sales_order_line_id'];
            if($changeLineId == $lineId){
                $changeQty = isset($data['qty'])?max($data['qty'],0):0;
                $salesOrderLine['cost_map'] = $changeQty>$salesOrderLine['cost_map']?$salesOrderLine['cost_map']:$changeQty;
                break;
            }
        }
        //销售订单明细对应的sku
        $sku = $salesOrderLine['item_code'];
        //销售订单明细需要数量
        $qty = ($changeLineId == $lineId ? min($changeQty,$salesOrderLine['qty']):$salesOrderLine['qty']);
        foreach ($skuCostMap as &$skuMap) {
            if ($sku == $skuMap['sku'] && $skuMap['left_qty']>0) {
                //该采购订单的使用
                $purchaseUse = [];
                //库存剩余可用数量
                $leftQty = $skuMap['left_qty'];
                $purchaseUse['useQty'] = min($leftQty,$qty);
                $purchaseUse['seller_id'] = $skuMap['seller_id'];
                $purchaseUse['sku'] = $skuMap['sku'];
                $purchaseUse['product_id'] = $skuMap['sku_id'];
                $purchaseUse['order_id'] = $skuMap['order_id'];
                $purchaseUse['order_product_id'] = $skuMap['order_product_id'];
                if ($leftQty >= $qty) {
                    $skuMap['left_qty'] = $leftQty - $qty;
                    $purchaseCostArr[$lineId][] = $purchaseUse;
                    break;
                }
                if ($leftQty < $qty) {
                    $skuMap['left_qty'] = 0;
                    $qty = $qty - $leftQty;
                    $purchaseCostArr[$lineId][] = $purchaseUse;
                }
            }
        }
    }

    public function dealFreightProduct($salesOrderLine, &$purchaseCostArr, &$europeFreightMap)
    {
        //销售订单明细id
        $lineId = $salesOrderLine['id'];
        //获取改明细的使用库存情况
        $costUseInfos = isset($purchaseCostArr[$lineId])?$purchaseCostArr[$lineId]:[];
        //该条销售订单的明细数量
        $qty = $salesOrderLine['qty'];
        //使用采购订单库存的数量
        $useCostQty = 0;
        //销售订单header_id
        $headerId = $salesOrderLine['header_id'];
        foreach ($costUseInfos as $costUseInfo) {
            if($costUseInfo['useQty']>0) {
                $europeFreight = [];
                $europeFreight['product_id'] = $costUseInfo['product_id'];
                $europeFreight['from'] = $this->country_name;
                $europeFreight['to'] = $salesOrderLine['ship_country'];
                $europeFreight['zip_code'] = $salesOrderLine['ship_zip_code'];
                $europeFreight['line_id'] = $lineId;
                $europeFreight['header_id'] = $headerId;
                $europeFreight['order_product_id'] = $costUseInfo['order_product_id'];
                $europeFreightMap[] = $europeFreight;
                $useCostQty += $costUseInfo['useQty'];
            }
        }

        //还需要采购
        $first_get = $salesOrderLine['first_get'];
        if ($qty > $useCostQty && !empty($first_get['product_id'])) {
            $europeFreight = [];
            $europeFreight['product_id'] = $first_get['product_id'];
            $europeFreight['from'] = $this->country_name;
            $europeFreight['to'] = $salesOrderLine['ship_country'];
            $europeFreight['zip_code'] = $salesOrderLine['ship_zip_code'];
            $europeFreight['line_id'] = $salesOrderLine['id'];
            $europeFreight['header_id'] = $headerId;
            $europeFreight['seller_id'] = $first_get['seller_id'];
            $europeFreight['needBuyQty'] = $qty - $useCostQty;
            $europeFreightMap[] = $europeFreight;
        }
        //获取
//        $freightInfo = $this->europe_freight_model->getFreight(array_values($europeFreight));
    }


    public function dealFreightInfo($europeFreightMap,$freightMap,$purchaseCostArr)
    {
        $this->load->model('tool/image');
        //用于展示的欧洲补运费数据
        $europeFreightResults = [];
        $freightInfos = $this->europe_freight_model->getFreight($europeFreightMap);

        //将line_id和order_product_id合并成key
        $europeFreightMapNew = [];
        foreach($europeFreightMap as $europeFreight){
            $lineId = $europeFreight['line_id'];
            $orderProductId = isset($europeFreight['order_product_id'])?$europeFreight['order_product_id']:'0';
            $europeFreightMapNew[$lineId.'_'.$orderProductId] = $europeFreight;
        }

        foreach ($freightInfos as $freightInfo){
            $europeFreightResult = [];
            $lineId = $freightInfo['line_id'];
            //该条明细的库存使用情况
            $purchaseCost = array_column(isset($purchaseCostArr[$lineId])?$purchaseCostArr[$lineId]:[],null,'order_product_id');
            if(empty($freightInfo['order_product_id'])){
                //未使用库存
                $useQty = $europeFreightMapNew[$lineId.'_0']['needBuyQty'];
                $sellerId = $europeFreightMapNew[$lineId.'_0']['seller_id'];
                $headerId = $europeFreightMapNew[$lineId.'_0']['header_id'];
                $europeFreightResult['order_product_id'] = '';
            }else{
                //使用了库存
                $orderProductId = $freightInfo['order_product_id'];
                $purchaseInfo = $purchaseCost[$orderProductId];
                $useQty = $purchaseInfo['useQty'];
                $sellerId = $purchaseInfo['seller_id'];
                $headerId = $europeFreightMapNew[$lineId.'_'.$freightInfo['order_product_id']]['header_id'];
                $europeFreightResult['order_product_id'] = $freightInfo['order_product_id'];
            }
            $europeFreightResult['per_qty'] = $useQty;
            switch ($freightInfo['code']) {
                case self::EUROPE_FREIGHT_SUCCESS_STATUS:
                    $freight = $freightInfo['freight'];
                    $freightAll = ceil($freight)*$useQty;
                    $qty = (int)$freightAll/1;
                    $europeFreightResult['freight_flag'] = true;
                    $europeFreightResult['status'] = true;
                    $europeFreightResult['header_id'] = $headerId;
                    $europeFreightResult['line_id'] = $freightInfo['line_id'];
                    $europeFreightResult['freight'] = $freightAll;
                    $europeFreightResult['freight_curr'] = $this->currency->formatCurrencyPrice($freightAll,  $this->session->get('currency'));
                    $europeFreightResult['freight_qty'] = $qty;
                    $europeFreightResult['seller_id'] = $sellerId;
                    if (empty($freightMap[$sellerId])) {
                        $europeFreightResult['status'] = false;
                        $europeFreightResult['freight_status'] = self::EUROPE_FREIGHT_FAIL_NO_EXIST_STATUS;
                        $europeFreightResult['msg'] = self::EUROPE_FREIGHT_FAIL_NO_EXIST_MSG;
                    } else {
                        $europeFreightResult['product_id'] = $freightMap[$sellerId]['product_id'];
                        $europeFreightResult['sku'] = $freightMap[$sellerId]['sku'];
                        $europeFreightResult['screenname'] = $freightMap[$sellerId]['screenname'];
                        $europeFreightResult['image'] = $this->model_tool_image->resize($freightMap[$sellerId]['image'],60,60);
                    }
                    //对应的库存采购订单的product_id
                    $europeFreightResult['sku_product_id'] = $freightInfo['product_id'];
                    break;
                case self::EUROPE_FREIGHT_FAIL_PRODUCT_STATUS:
                    $europeFreightResult['sku_product_id'] = $freightInfo['product_id'];
                    $europeFreightResult['freight_flag'] = true;
                    $europeFreightResult['header_id'] = $headerId;
                    $europeFreightResult['line_id'] = $freightInfo['line_id'];
                    $europeFreightResult['product_id'] = isset($freightMap[$sellerId]['product_id'])?$freightMap[$sellerId]['product_id']:'';
                    $europeFreightResult['sku'] = isset($freightMap[$sellerId]['sku'])?$freightMap[$sellerId]['sku']:'';
                    $europeFreightResult['screenname'] = isset($freightMap[$sellerId]['screenname'])?$freightMap[$sellerId]['screenname']:'';
                    $europeFreightResult['image'] = $this->model_tool_image->resize(isset($freightMap[$sellerId]['image'])?$freightMap[$sellerId]['image']:'',60,60);
                    $europeFreightResult['status'] = false;
                    $europeFreightResult['freight_status'] = self::EUROPE_FREIGHT_FAIL_PRODUCT_STATUS;
                    $europeFreightResult['msg'] = self::EUROPE_FREIGHT_PRODUCT_ERROR_MSG;
                    break;
                case self::EUROPE_FREIGHT_FAIL_COUNTRY_STATUS:
                case self::NOT_VAT_ADDRESS_STATUS: // #37317 免税buyer限制销售订单的地址(是否为非德国的欧盟国家)
                    $europeFreightResult['sku_product_id'] = $freightInfo['product_id'];
                    $europeFreightResult['freight_flag'] = true;
                    $europeFreightResult['header_id'] = $headerId;
                    $europeFreightResult['line_id'] = $freightInfo['line_id'];
                    $europeFreightResult['product_id'] = isset($freightMap[$sellerId]['product_id'])?$freightMap[$sellerId]['product_id']:'';
                    $europeFreightResult['sku'] = isset($freightMap[$sellerId]['sku'])?$freightMap[$sellerId]['sku']:'';
                    $europeFreightResult['screenname'] = isset($freightMap[$sellerId]['screenname'])?$freightMap[$sellerId]['screenname']:'';
                    $europeFreightResult['image'] = $this->model_tool_image->resize(isset($freightMap[$sellerId]['image'])?$freightMap[$sellerId]['image']:'',60,60);
                    $europeFreightResult['status'] = false;
                    $europeFreightResult['freight_status'] = self::EUROPE_FREIGHT_FAIL_COUNTRY_STATUS;
                    // #37317 免税buyer限制销售订单的地址(是否为非德国的欧盟国家)
                    $europeFreightResult['msg'] = $freightInfo['code'] == self::EUROPE_FREIGHT_FAIL_COUNTRY_STATUS ? self::EUROPE_FREIGHT_COUNTRY_ERROR_MSG : self::NOT_VAT_ADDRESS_NOTICE;
                    break;
                default:
            }
            $europeFreightResults[$lineId][] = $europeFreightResult;
        }

        return $europeFreightResults;
    }

    public function initMatchData(){
        $salesOrderIdArray = $this->request->post('sales_order_id', 0);
        $changeData = $this->request->post('changeData', []);
        $salesOrderInfo = $this->model->getSalesOrderLineInfo($salesOrderIdArray);
        $this->load->model('account/sales_order/match_inventory_window');
        $this->load->model('tool/image');
        $customer_id = $this->customer->getId();

        //获取每一个order的error信息
        $error_info = $this->sales_model->getOrderErrorInfo($salesOrderIdArray, $customer_id, $this->customer->getCountryId(), 1);
        $skuMap = [];
        foreach ($salesOrderInfo as $salesOrderLine) {
            $skuMap[] = $salesOrderLine['item_code'];
        }
        $skuArray = array_unique($skuMap);
        $storeArray = $this->model->getCanBuyProductInfoBySku($skuArray, $customer_id);
        $productCostMap = $this->model->getCostBySkuArray($skuArray, $customer_id);
        $data['productCostMap'] = $productCostMap;

        //查询剩余库存的所有采购订单明细
        $skuCostMap = $this->getCostDetailsBySku($skuArray, $customer_id);
        //查询店铺的欧洲补运费产品
        $freightMap = $this->model->getFreightSku();

        //所有订单消耗的sku
        $needCostMap = [];
        $header_error_info = [];
        //采购订单库存分配情况
        $purchaseCostArr = [];
        //采购订单分配库存对应的productId
        $europeFreightMap = [];
        foreach ($salesOrderInfo as &$salesOrderLine) {
            switch ($salesOrderLine['is_international']){
                case self::EUROPE_INTERNATIONAL:
                    //欧洲补运费逻辑
                    $this->europeInternationalMatchBox($salesOrderLine,$needCostMap,$header_error_info,$productCostMap,$storeArray,$error_info,$changeData);
                    //处理库存分配
                    $this->dealCostProductMatch($salesOrderLine,$skuCostMap,$purchaseCostArr,$changeData);
                    //处理运费产品
                    $this->dealFreightProduct($salesOrderLine,$purchaseCostArr,$europeFreightMap);
                    break;
                default:
                    //正常购买
                    $this->dropshipMatchBox($salesOrderLine,$needCostMap,$header_error_info,$productCostMap,$storeArray,$error_info,$changeData);
                    //处理库存分配
                    $this->dealCostProductMatch($salesOrderLine,$skuCostMap,$purchaseCostArr,$changeData);
                    //#31737 免税buyer限制销售订单的地址(发往德国)
                    if (strtoupper($salesOrderLine['ship_country']) == CountryCode::GERMANY && $this->customer->isEuVatBuyer()) {
                        $this->dealFreightProduct($salesOrderLine, $purchaseCostArr, $europeFreightMap);
                    }
            }
        }
        $freightInfos = $this->dealFreightInfo($europeFreightMap,$freightMap,$purchaseCostArr);
        $data['needCostMap'] = $needCostMap;
        $salesOrders = [];
        foreach ($header_error_info as $header_id =>$errorInfo){
            $salesInfo = [];
            $salesInfo['error_msg'] =  $errorInfo;
            foreach ($salesOrderInfo as $salesOrder){
                if($header_id == $salesOrder['header_id']) {
                    $freightStatus = self::EUROPE_FREIGHT_SUCCESS_STATUS;
                    $freightErrorMsg = '';
                    foreach ($freightInfos as $lineId => $freightInfo) {
                        if ($freightInfo[0]['header_id'] == $header_id && $lineId == $salesOrder['id']) {
                            foreach ($freightInfo as $freight) {
                                if ($freight['status'] == false) {
                                    $freightStatus = $freight['freight_status'];
                                    $freightErrorMsg = $freight['msg'];
                                    break;
                                }
                            }
                        }
                    }

                    $salesInfo['sales_order_id'] = $salesOrder['order_id'];
                    $salesInfo['international_flag'] = ($salesOrder['is_international_flag'] == 1 ? true : false);
                    $errorInfos = '';
                    foreach ($salesOrder['error_msg'] as $errorMsg){
                        $errorInfos .= $errorMsg;
                    }
                    // #37317 #31737 免税buyer限制销售订单的地址(是否为非德国的欧盟国家)
                    if (in_array($freightStatus, [self::EUROPE_FREIGHT_FAIL_PRODUCT_STATUS, self::EUROPE_FREIGHT_FAIL_COUNTRY_STATUS, self::NOT_VAT_ADDRESS_STATUS])) {
                        if($freightStatus == self::EUROPE_FREIGHT_FAIL_PRODUCT_STATUS){
                            $salesOrder['error_flag'] = true;
                            unset($salesOrder['error_msg']);
                            $salesOrder['error_msg']['freight_error'] = $freightErrorMsg;
                            $salesInfo['salesInfo'][] = $salesOrder;
                        }
                        // #37317 #31737 免税buyer限制销售订单的地址(是否为非德国的欧盟国家)
                        if ($freightStatus == self::EUROPE_FREIGHT_FAIL_COUNTRY_STATUS || $freightStatus == self::NOT_VAT_ADDRESS_STATUS) {
                            $salesInfo['error_msg'] = true;
                            $salesOrder['address_error_flag'] = true;
                            $salesOrder['address_error'] = $salesOrder['address_error'].$freightErrorMsg;
                            $salesInfo['salesInfo'][] = $salesOrder;
                        }
                    }else if(strlen($errorInfos)>0 && $salesOrder['error_flag'] == true){
                        $salesInfo['salesInfo'][] = $salesOrder;
                    }else{
                        $salesInfo['salesInfo'][] = $salesOrder;
                        foreach ($freightInfos as $lineId => $freightInfo) {
                            if ($freightInfo[0]['header_id'] == $header_id && $lineId == $salesOrder['id']) {
                                foreach ($freightInfo as $freight) {
                                    $salesInfo['salesInfo'][] = $freight;
                                }
                            }
                        }
                    }


                }
            }
            $salesOrders[] = $salesInfo;
        }
        $data['headerInfo'] = $salesOrders;
        return $this->response->json(json_encode($data));
    }
}
