<?php

namespace App\Services\Warehouse;

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductType;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Enums\Warehouse\ReceiptShipping;
use App\Enums\Warehouse\ReceiptsOrderHistoryType;
use App\Enums\Warehouse\ShippingOrderBookSpecialProductType;
use App\Enums\Warehouse\ShippingOrderBookTermsOfDelivery;
use App\Helper\CountryHelper;
use App\Helper\SummernoteHtmlEncodeHelper;
use App\Logging\Logger;
use App\Models\Product\Product;
use App\Models\Stock\ReceiptsOrder;
use App\Models\Stock\ReceiptsOrderDetail;
use App\Models\Stock\ReceiptsOrderHistory;
use App\Models\Warehouse\ReceiptsOrderShippingOrderBook;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Warehouse\ReceiptRepository;
use Framework\Exception\Exception;
use Symfony\Component\HttpClient\HttpClient;
use Throwable;

class ReceiptService
{
    protected $country;
    protected $currency;

    public function __construct()
    {
        $this->country = session('country', 'USA');
        $this->currency = session('currency', 3);
    }

    /**
     * 同步入库单到海运系统
     * @param $params
     * @param $scene
     * @return array|false
     */
    public function syncOceanShipping($params, $scene = 'add')
    {
        // 测试帐号不同步到海运系统
        if (in_array(customer()->getId(), explode(',', configDB('warehouse_receipt_test_account')))) {
            return true;
        }
        try {
            $client = HttpClient::create();
            switch ($scene) {
                case 'add':
                    $api = '/api/logisticsOrder/b2bSupportAdd';
                    break;
                case 'edit':
                    $api = '/api/logisticsOrder/b2bSupportUpdate';
                    break;
                // 客户自发,填写发船信息
                case 'ship_launch':
                    $api = '/api/logisticsOrder/updateB2bSpontaneousComeBackInfo';
                    break;
                // 取消入库单
                case 'cancel':
                    $api = '/api/logisticsOrder/b2bSupportRecall';
                    break;
            }
            $url = get_env('URL_MARITIME') . $api;
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Content-Type: application/json; charset=utf-8',
                ],
                'json' => $params,
            ]);
            $res = $response->toArray(false);
            if (empty($res['status'])) {
                Logger::receiptOrder('同步入库单到海运系统失败:' . $url, 'error', [
                    Logger::CONTEXT_VAR_DUMPER => ['response' => $res, 'formData' => $params],
                ]);
                return false;
            }
            return true;
        } catch (Throwable $e) {
            Logger::receiptOrder('同步入库单到海运系统失败:' . $url, 'error', [
                Logger::CONTEXT_VAR_DUMPER => ['response' => $res, 'formData' => $params, 'Exception' => $e->getMessage()],
            ]);
            return false;
        }
    }


    /**
     * 组装海运接口需要的数据
     * @param $receipt
     * @param $params
     * @return array
     */
    public function makeOceanData($receipt, $params = [])
    {
        $methods = [
            1 => 'B2B',
            2 => 'B2B_SPONTANEOUS',
            3 => 'B2B_LOCAL',
        ];
        $data['uuid'] = $receipt->receive_order_id;
        $data['customerCode'] = app(CustomerRepository::class)->getCustomerNumber($receipt->customer_id);
        $data['receiptOrderNumber'] = $receipt->receive_number;
        $data['applyShipStartDate'] = isset($params['expected_shipping_date_start']) ? date('Y-m-d', strtotime($params['expected_shipping_date_start'])) : $receipt->expected_shipping_date_start->toDateString();
        $data['applyShipEndDate'] = isset($params['expected_shipping_date_end']) ? date('Y-m-d', strtotime($params['expected_shipping_date_end'])) : $receipt->expected_shipping_date_end->toDateString();
        $data['storeApplyTime'] = $receipt->apply_date->toDateTimeString();
        $data['accountManager'] = app(CustomerRepository::class)->getAccountManager($receipt->customer_id);
        $data['typeName'] = $methods[$receipt->shipping_way];
        $data['shipmentPortName'] = $params['port_start'] ?? $receipt->port_start;
        $data['partRemark'] = $params['remark'] ?? $receipt->remark;
        $data['equipment'] = $params['container_size'] ?? $receipt->container_size;

        // 委托海运 需要添加托书信息
        if (! empty($params['need_book']) && $params['need_book'] == true && ! empty($params['bookData'])) {
            $data['bookingInfo'] = $this->makeShippingBookData($params['bookData']);
        }

        $tmp = [];
        if (!empty($params['products'])) {
            $productsCollect = collect($params['products'])->keyBy('product_id');
            $products = Product::query()->alias('op')
                ->select(['op.length', 'op.sku', 'op.mpn', 'op.product_id','op.width', 'op.height', 'op.weight', 'pd.name'])
                ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'op.product_id')
                ->whereIn('op.product_id', $productsCollect->pluck('product_id'))
                ->get()->toArray();
            $productsArr = $productsCollect->toArray();
            foreach ($products as $key => $product) {
                $tmp[$key]['lineId'] = $receipt->receive_order_id . '-' . $product['sku'];
                $tmp[$key]['itemCode'] = $product['sku'];
                $tmp[$key]['mpn'] = $product['mpn'];
                $tmp[$key]['productName'] = SummernoteHtmlEncodeHelper::decode($product['name'], true);
                $tmp[$key]['itemLong'] = $product['length'];
                $tmp[$key]['itemWidth'] = $product['width'];
                $tmp[$key]['itemHigh'] = $product['height'];
                $tmp[$key]['itemWeight'] = $product['weight'];
                $tmp[$key]['itemQty'] = $productsArr[$product['product_id']]['expected_qty'];
                $tmp[$key]['hsCode'] = $productsArr[$product['product_id']]['hscode'] ?? '';
                $tmp[$key]['hsCode301'] = $productsArr[$product['product_id']]['301_hscode'] ?? '';
            }
            $data['orderDetailList'] = $tmp;
            return $data;
        }
        $receiptDetails = ReceiptsOrderDetail::query()->alias('rd')
            ->select(['rd.*', 'pd.name'])
            ->leftJoin('oc_product_description as pd', 'pd.product_id', '=', 'rd.product_id')
            ->where('receive_order_id', $receipt->receive_order_id)
            ->get()->toArray();
        foreach ($receiptDetails as $key => $detail) {
            $tmp[$key]['lineId'] = $receipt->receive_order_id . '-' . $detail['sku'];
            $tmp[$key]['itemCode'] = $detail['sku'];
            $tmp[$key]['mpn'] = $detail['mpn'];
            $tmp[$key]['productName'] = SummernoteHtmlEncodeHelper::decode($detail['name'], true);
            $tmp[$key]['itemLong'] = $detail['length'];
            $tmp[$key]['itemWidth'] = $detail['width'];
            $tmp[$key]['itemHigh'] = $detail['height'];
            $tmp[$key]['itemWeight'] = $detail['weight'];
            $tmp[$key]['itemQty'] = $detail['expected_qty'];
            $tmp[$key]['hsCode'] = $detail['hscode'];
            $tmp[$key]['hsCode301'] = $detail['301_hscode'];
        }
        $data['orderDetailList'] = $tmp;

        return $data;
    }

    /**
     * 拼装海运托书信息
     *
     * @param array $data
     * @return array
     */
    private function makeShippingBookData(array $data)
    {
        $bookData = [];

        $mainData['shipperCompanyName'] = SummernoteHtmlEncodeHelper::decode($data['company_name'], true);
        $mainData['shipperAddress'] = $data['address'];
        $mainData['shipperConcat'] = $data['contacts'];
        $mainData['shipperPhone'] = $data['contact_number'];
        $mainData['consignee'] = $data['consignee'];
        $mainData['notifyParty'] = $data['notify_party'];
        $mainData['bondFlag'] = $data['is_self_bond'] == YesNoEnum::YES ? true : false;
        $mainData['bondAddress'] = $data['bond_address'];
        $mainData['bondHead'] = $data['bond_title'];
        $mainData['bondNumber'] = $data['bond_cin'];
        $mainData['boxQty'] = $data['container_load'];
        $mainData['marks'] = $data['marks_numbers'];
        $mainData['tradeMethod'] = ShippingOrderBookTermsOfDelivery::getDescription($data['terms_of_delivery']);
        $mainData['shipmentTrailerFlag'] = $data['is_use_trailer'] == YesNoEnum::YES ? true : false;
        $mainData['trailerAddress'] = $data['trailer_address'];
        $mainData['trailerConcatMethod'] = $data['trailer_contact'];
        $mainData['trailerProductExplain'] = ShippingOrderBookSpecialProductType::getOceanDesc($data['special_product_type']);
        $mainData['remark'] = $data['remark'];

        $shippingList = json_decode($data['shipping_list'], true);
        $lineData = [];
        foreach ($shippingList as $item) {
            $lineDataItem['description'] = $item['description'];
            $lineDataItem['hsCode'] = $item['hscode'];
            $lineDataItem['itemQty'] = $item['qty'];
            $lineDataItem['itemWeight'] = $item['weight'];
            $lineDataItem['itemVolume'] = $item['volume'];

            $lineData[] = $lineDataItem;
        }

        $bookData['logisticsBooking'] = $mainData;
        $bookData['bookingLine'] = $lineData;

        return $bookData;
    }

    /**
     * 判断集装箱尺寸，起运港，期望船期，到库时间是否修改
     * @param $newData
     * @param $oldData
     * @return bool
     */
    public function checkDataIsChanged($newData, $oldData)
    {
        if ($newData['container_size'] != $oldData->container_size) {
            return true;
        }
        if ($oldData->shipping_way == ReceiptShipping::ENTRUSTED_SHIPPING) {

            if (isset($newData['expected_shipping_date_start']) && $newData['expected_shipping_date_start'] != $oldData->expected_shipping_date_start) {
                return true;
            }
            if (isset($newData['expected_shipping_date_end']) && $newData['expected_shipping_date_end'] != $oldData->expected_shipping_date_end) {
                return true;
            }
        }
        if ($oldData->shipping_way == ReceiptShipping::MY_SELF) {

            if (isset($newData['expected_arrival_date_start']) && $newData['expected_arrival_date_start'] != $oldData->expected_arrival_date_start) {
                return true;
            }
            if (isset($newData['expected_arrival_date_end']) && $newData['expected_arrival_date_end'] != $oldData->expected_arrival_date_end) {
                return true;
            }
        }
        if ($newData['port_start'] != $oldData->port_start) {
            return true;
        }
        return false;
    }

    /**
     * 更新入库单的发船信息
     * @param int $customerId
     * @param $data
     * @return bool
     */
    public function updateShipLaunch($customerId, $data)
    {
        $receipt = app(ReceiptRepository::class)->getReceiptByReceiveOrderId($customerId, $data['receive_order_id']);
        if (empty($receipt)) {
            return false;
        }
        if ($receipt->status != ReceiptOrderStatus::DIVIDED || $receipt->shipping_way != ReceiptShipping::MY_SELF) {
            return false;
        }
        db()::beginTransaction();
        try {
            $map['status'] = ReceiptOrderStatus::TO_BE_RECEIVED;
            $map['update_time'] = date('Y-m-d H:i:s');
            $map['container_code'] = $data['container_code'];
            $map['shipping_company'] = $data['shipping_company'];
            $map['etd_date'] = $data['etd_date'];
            $map['eta_date'] = $data['eta_date'];
            ReceiptsOrder::query()->where('receive_order_id', $data['receive_order_id'])->update($map);
            $syncData['uuid'] = $receipt->receive_order_id;
            $syncData['container'] = $data['container_code'];
            $syncData['carrier'] = $data['shipping_company'];
            $syncData['etdPort'] = $data['etd_date'];
            $syncData['etaPort'] = $data['eta_date'];
            if (!$this->syncOceanShipping($syncData, 'ship_launch')) {
                throw new Exception('update Receipt Ship Launch Failed');
            }
            db()::commit();
            return true;
        } catch (Throwable $e) {
            db()::rollback();
            return false;
        }
    }

    /**
     * 更新入库单
     * @param int $customerId
     * @param $data
     * @return bool
     */
    public function updateReceipt($customerId, $data)
    {
        date_default_timezone_set(CountryHelper::getTimezoneByCode('USA'));
        $receipt = app(ReceiptRepository::class)->getReceiptByReceiveOrderId($customerId, $data['receive_order_id']);
        if (empty($receipt)) {
            return false;
        }
        $this->convertTimezone($data);

        // 1.入库单状态为已申请，海运头程不能修改
        if ($receipt['status'] == ReceiptOrderStatus::APPLIED) {
            $data['shipping_way'] = $receipt->shipping_way;
        }
        if ($receipt->status == ReceiptOrderStatus::TO_SUBMIT && in_array($data['status'], ReceiptOrderStatus::appliedStatus())) {
            $data['apply_date'] = date('Y-m-d H:i:s');
        }
        // 2.ETD+3入库单不可以编辑
        if ($receipt->etd_date && (strtotime($receipt->etd_date) + 3600 * 24 * 4) < time()) {
            return 'disable_etd3';
        }

        // 3.入库单状态为已分仓[非seller自发]，待订舱，已订舱，则B2B入库单状态置为“修改待确认”，申请修改的信息暂不更新，只记录到修改记录中,修改信息需要同步到海运系统，若海运系统确认可修改，则系统更新入库单修改时间，入库单状态重置为已申请，若海运系统驳回，则入库单状态置为已取消
        if (in_array($receipt->status, ReceiptOrderStatus::reDivisionStatus())) {
            return $this->asyncUpdateReceipt($receipt, $data);
        }

        // 4.入库单状态为待收货，已发货，已废弃，已取消,入库单信息不能修改,待收货状态和已发货状态不能取消入库单。
        if (in_array($receipt->status, ReceiptOrderStatus::disableEditStatus())) {
            return 'disable_edit';
        }

        $data['last_status'] = $receipt->status;

        return $this->syncUpdateReceipt($receipt, $data);
    }

    /**
     * 直接更新入库单信息
     * @param $data
     * @param $receipt
     * @return false
     */
    public function syncUpdateReceipt($receipt, $data)
    {
        db()::beginTransaction();
        try {
            $nowDate = date('Y-m-d H:i:s');

            $bookData = $data['bookData'] ?? [];
            unset($data['bookData']);
            // 更新托书信息
            if ($receipt->status == ReceiptOrderStatus::TO_SUBMIT && $data['shipping_way'] == ReceiptShipping::ENTRUSTED_SHIPPING) {
                // 判断是否存在托书信息
                $bookExist = ReceiptsOrderShippingOrderBook::where('receive_order_id', $receipt->receive_order_id)->first();
                unset($bookData['id']);unset($bookData['receive_order_id']);unset($bookData['create_time']);
                $bookData['update_time'] = $nowDate;
                if ($bookExist) {
                    ReceiptsOrderShippingOrderBook::where('receive_order_id', $receipt->receive_order_id)->update($bookData);
                } else {
                    $bookData['create_time'] = $nowDate;
                    $bookData['receive_order_id'] = $receipt->receive_order_id;
                    $saveBook = ReceiptsOrderShippingOrderBook::insert($bookData);
                    if (! $saveBook) {
                        throw new Exception('Save Shipping Order Book Error.');
                    }
                }
            }

            $dataOrder = $data;
            $dataOrder['update_time'] = $nowDate;
            unset($dataOrder['products']);

            // 记录入库单的修改历史
            $this->addReceiptsOrderHistory($receipt->toArray(), $data, ReceiptsOrderHistoryType::PENDING);
            ReceiptsOrder::query()->where('receive_order_id', $data['receive_order_id'])->update($dataOrder);
            ReceiptsOrderDetail::query()->where('receive_order_id', $data['receive_order_id'])->delete();
            $this->addReceiptDetail($receipt, $data['products']);
            if ($receipt->status == ReceiptOrderStatus::APPLIED || (isset($data['status']) && $data['status'] == ReceiptOrderStatus::APPLIED)) {
                $param = [];
                // 第一次提交需要提交托书信息
                if ($receipt->status == ReceiptOrderStatus::TO_SUBMIT
                    && (isset($data['status']) && $data['status'] == ReceiptOrderStatus::APPLIED)
                    && $data['shipping_way'] == ReceiptShipping::ENTRUSTED_SHIPPING
                ) {
                    $param['need_book'] = true;
                    $param['bookData'] = $bookData;
                }

                // 同步海运系统
                $scene = $receipt->status == ReceiptOrderStatus::TO_SUBMIT ? 'add' : 'edit';
                $receipt = ReceiptsOrder::query()->find($receipt->receive_order_id);
                if (!$this->syncOceanShipping($this->makeOceanData($receipt, $param), $scene)) {
                    throw new Exception('update Receipt Failed');
                }
            }
            db()::commit();

            return true;
        } catch (Throwable $e) {
            db()::rollback();
            Logger::receiptOrder($e->getMessage());
            return false;
        }
    }


    /**
     * 不更新入库单信息，同步信息给海运审核
     * @param $data
     * @param $receipt
     * @return false|string
     */
    public function asyncUpdateReceipt($receipt, $data)
    {
        db()::beginTransaction();
        try {
            $status = $this->addReceiptsOrderHistory($receipt->toArray(), $data, ReceiptsOrderHistoryType::PENDING);
            // 如果未修改入库单信息直接返回
            if (!$status) {
                return true;
            }
            $dataOrder['update_time'] = date('Y-m-d H:i:s');
            $dataOrder['status'] = ReceiptOrderStatus::EDIT_PENDING;
            $dataOrder['last_status'] = $receipt->status;
            ReceiptsOrder::query()->where('receive_order_id', $data['receive_order_id'])->update($dataOrder);
            // 同步海云系统
            if (!$this->syncOceanShipping($this->makeOceanData($receipt,$data), 'edit')) {
                throw new Exception('update Receipt Failed');
            }
            $flag = 'edit_pending';
            // 记录入库单的修改历史
            db()::commit();
            return $flag;
        } catch (Throwable $e) {
            db()::rollback();
            Logger::receiptOrder($e->getMessage());
            return false;
        }
    }

    /**
     * 添加入库单
     * @param int $customerId
     * @param $data
     * @return bool
     */
    public function addReceipt($customerId, $data)
    {
        date_default_timezone_set(CountryHelper::getTimezoneByCode('USA'));

        $logType = $data['status'] == ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING ? ReceiptsOrderHistoryType::CREATE_AND_APPLY : ReceiptsOrderHistoryType::CREATE;
        db()::beginTransaction();
        try {
            $nowTime = date('Y-m-d H:i:s');

            $data['receive_number'] = ReceiptRepository::generateReceiptNumber($customerId);
            $data['customer_id'] = $customerId;
            $data['currency'] = $this->currency;
            $data['create_time'] = $nowTime;
            $data['update_time'] = $nowTime;
            if (in_array($data['status'], ReceiptOrderStatus::appliedStatus())) {
                $data['apply_date'] = $nowTime;
            }
            $this->convertTimezone($data);
            $newData = $data;
            $newData['program_code'] = '3.0';
            $receipt = ReceiptsOrder::query()->create($newData);
            $this->addReceiptDetail($receipt, $data['products']);
            if ($data['status'] == ReceiptOrderStatus::APPLIED) {
                $logType = ReceiptsOrderHistoryType::CREATE_AND_APPLY;

                $data['need_book'] = true;
                // 同步海云系统
                if (!$this->syncOceanShipping($this->makeOceanData($receipt, $data), 'add')) {
                    throw new Exception('add Receipt Failed');
                }
            }

            if ($data['shipping_way'] == ReceiptShipping::ENTRUSTED_SHIPPING) { // 委托海运需保存托书信息
                $bookData = $data['bookData'];
                unset($data['bookData']);
                $bookData['create_time'] = $nowTime;
                $bookData['update_time'] = $nowTime;
                $bookData['receive_order_id'] = $receipt->receive_order_id;
                $saveBook = ReceiptsOrderShippingOrderBook::insert($bookData);
                if (! $saveBook) {
                    throw new Exception('Save Shipping Order Book Error.');
                }
            }

            // 记录入库单的修改历史
            $this->addReceiptsOrderHistory($receipt->toArray(), $data, $logType);
            db()::commit();
            return $data['receive_number'];
        } catch (Throwable $e) {
            db()::rollback();
            return false;
        }
    }

    /**
     * 时区转换
     * @param $data
     */
    public function convertTimezone(&$data)
    {
        date_default_timezone_set(CountryHelper::getTimezoneByCode($this->country));
        if (isset($data['expected_shipping_date_start']) && $data['expected_shipping_date_start']) {
            $start = strtotime($data['expected_shipping_date_start'] . '00:00:00');
            $end = strtotime($data['expected_shipping_date_end'] . '23:59:59');
            date_default_timezone_set(CountryHelper::getTimezoneByCode('USA'));
            $data['expected_shipping_date_start'] = date('Y-m-d H:i:s', $start);
            $data['expected_shipping_date_end'] = date('Y-m-d H:i:s', $end);
        }
        if (isset($data['expected_arrival_date_start']) && $data['expected_arrival_date_start']) {
            $start = strtotime($data['expected_arrival_date_start'] . '00:00:00');
            $end = strtotime($data['expected_arrival_date_end'] . '23:59:59');
            date_default_timezone_set(CountryHelper::getTimezoneByCode('USA'));
            $data['expected_arrival_date_start'] = date('Y-m-d H:i:s', $start);
            $data['expected_arrival_date_end'] = date('Y-m-d H:i:s', $end);
        }
    }

    /**
     * 添加入库单明细
     * @param object $receipt
     * @param array $products
     * @throws Exception
     */
    public function addReceiptDetail($receipt, $products)
    {
        if (!$products) {
            return;
        }
        $productsIds = array_column($products, 'product_id');
        $productInfos = Product::query()
            ->select(['product_id', 'sku', 'mpn', 'price', 'weight', 'length', 'width', 'height'])
            ->with('description:product_id,name')
            ->whereIn('product_id', $productsIds)
            ->where([
                'is_deleted' => YesNoEnum::NO,
                'combo_flag' => 0,
                'product_type' => ProductType::NORMAL,
            ])
            ->get()
            ->keyBy('product_id')
            ->toArray();
        if (count($productsIds) != count($productInfos)) {
            throw new Exception('product list count error');
        }
        $date = date('Y-m-d H:i:s');
        foreach ($products as &$item) {
            $item['create_time'] = $date;
            $item['update_time'] = $date;
            $item['expected_qty'] = $item['expected_qty'] ?? null;
            $item['301_hscode'] = $item['301_hscode'] ?? null;
            $item['hscode'] = $item['hscode'] ?? null;
            $item['receive_order_id'] = $receipt->receive_order_id;
            $item['receive_number'] = $receipt->receive_number;
            $item = array_merge($item, $productInfos[$item['product_id']]);
            $item['item_price'] = $item['price'];
            unset($item['price']);
            unset($item['description']);
        }
        ReceiptsOrderDetail::query()->insert($products);
    }

    /**
     * 取消入库单
     * @param int $customerId
     * @param int $receiveOrderId
     * @return bool
     */
    public function cancelReceiptStatus(int $customerId, int $receiveOrderId)
    {
        $receipt = app(ReceiptRepository::class)->getReceiptByReceiveOrderId($customerId, $receiveOrderId);
        if (empty($receipt)) {
            return false;
        }
        //状态为待提交申请 直接取消
        if ($receipt->status == ReceiptOrderStatus::TO_SUBMIT || $receipt->status == ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING) {
            ReceiptsOrder::query()->where('receive_order_id', $receiveOrderId)->update([
                'update_time' => date('Y-m-d H:i:s'),
                'status' => ReceiptOrderStatus::ABANDONED,
                'last_status' => $receipt->status
            ]);
            //记录log
            ReceiptsOrderHistory::query()->insert([
                'receive_order_id' => $receiveOrderId,
                'update_content' => json_encode(['status' => [
                    'oldValue' => $receipt->status,
                    'newValue' => ReceiptOrderStatus::CANCEL
                ]]),
                'type' => ReceiptsOrderHistoryType::PENDING,
            ]);
            return true;
        }
        if (!in_array($receipt->status, ReceiptOrderStatus::needSyncOceanCancelStatus())) {
            return false;
        }
        db()::beginTransaction();
        try {
            $status = ReceiptOrderStatus::CANCEL_PENDING;
            //如果是已申请，直接取消，将消息告知海运
            if ($receipt->status == ReceiptOrderStatus::APPLIED) {
                $status = ReceiptOrderStatus::CANCEL;
            }
            //记录log
            ReceiptsOrderHistory::query()->insert([
                'receive_order_id' => $receiveOrderId,
                'update_content' => json_encode(['status' => [
                    'oldValue' => $receipt->status,
                    'newValue' => $status
                ]]),
                'type' => ReceiptsOrderHistoryType::PENDING,
            ]);
            ReceiptsOrder::query()->where('receive_order_id', $receiveOrderId)->update([
                'update_time' => date('Y-m-d H:i:s'),
                'status' => $status,
                'last_status' => $receipt->status
            ]);
            $syncData = [];
            $syncData['uuid'] = $receipt->receive_order_id;
            if (!$this->syncOceanShipping($syncData, 'cancel')) {
                throw new Exception('Cancel Receipt Failed');
            }
            db()::commit();
            return true;
        } catch (Throwable $e) {
            db()::rollback();
            return false;
        }
    }

    /**
     * 添加 用户 确认入库商品包装规范说明
     * @param int $customerId
     * @return bool
     */
    public function addReceiptsProductPacking(int $customerId)
    {
        return db('tb_sys_receipts_order_remind')->insert([
            'customer_id' => $customerId,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 记录入库单的修改历史
     * @param $oldData
     * @param $newData
     * @param $type
     * @param bool $isInsert
     * @return bool
     */
    public function addReceiptsOrderHistory($oldData, $newData, $type, $isInsert = true)
    {
        if (!$isInsert) {
            $this->convertTimezone($newData);
        }
        $data['receive_order_id'] = $oldData['receive_order_id'];
        $except = ['last_status', 'status', 'receive_order_id', 'apply_date', 'currency', 'receive_number', 'customer_id'];
        $json = [];
        if ($oldData['status'] == ReceiptOrderStatus::APPLIED && $newData['status'] == ReceiptOrderStatus::APPLIED && $type != ReceiptsOrderHistoryType::CREATE_AND_APPLY) {
            $newType = ReceiptsOrderHistoryType::NO_AUDITNO_AUDIT;
        }
        if ($oldData['status'] == ReceiptOrderStatus::TO_SUBMIT && $newData['status'] == ReceiptOrderStatus::TO_SUBMIT && $type != ReceiptsOrderHistoryType::CREATE) {
            $newType = ReceiptsOrderHistoryType::NO_AUDITNO_AUDIT;
        }
        //待收货修改不用审核
        if ($oldData['status'] == ReceiptOrderStatus::TO_BE_RECEIVED && $newData['status'] == ReceiptOrderStatus::TO_BE_RECEIVED) {
            $type = ReceiptsOrderHistoryType::NO_AUDITNO_AUDIT;
        }
        if ($oldData['status'] == ReceiptOrderStatus::TO_SUBMIT && $newData['status'] == ReceiptOrderStatus::APPLIED) {
            $oldData = [];
            $type = ReceiptsOrderHistoryType::CREATE_AND_APPLY;
        }
        if (isset($oldData['status']) && $oldData['status'] == ReceiptOrderStatus::TO_SUBMIT && $newData['status'] == ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING) {
            $type = ReceiptsOrderHistoryType::CREATE_AND_APPLY;
        }
        $oldProducts = ReceiptsOrderDetail::query()
            ->select('product_id', 'expected_qty', 'hscode', '301_hscode')
            ->where('receive_order_id', $data['receive_order_id'])
            ->get()->toArray();

        if (!empty($newData['products'])) {
            $newDataProducts = array_combine(array_column($newData['products'], 'product_id'), $newData['products']);
            $oldKeyProducts = array_combine(array_column($oldProducts, 'product_id'), $oldProducts);
            $isDiff = false;
            if (count($oldProducts) == count($newData['products'])) {
                foreach ($oldKeyProducts as $key => $oldProduct) {
                    if (!isset($newDataProducts[$key])) {
                        $isDiff = true;
                        continue;
                    }
                    if ($oldProduct['product_id'] != $newDataProducts[$key]['product_id']) {
                        $isDiff = true;
                    }
                    if ($oldProduct['expected_qty'] != $newDataProducts[$key]['expected_qty']) {
                        $isDiff = true;
                    }
                    //非2.0版本不需要校验hscode 与301_hscode
                    if (isset($oldData['program_code']) && $oldData['program_code'] != '2.0') {
                        continue;
                    }
                    if ($oldProduct['hscode'] != $newDataProducts[$key]['hscode']) {
                        $isDiff = true;
                    }
                    if ($oldProduct['301_hscode'] != $newDataProducts[$key]['301_hscode']) {
                        $isDiff = true;
                    }
                }
            } else {
                $isDiff = true;
            }
            if ($isDiff) {
                $json['products'] = ['oldValue' => $oldProducts, 'newValue' => $newData['products']];
            }
        }
        if (isset($oldData['status']) && $oldData['status'] == ReceiptOrderStatus::TO_SUBMIT && !empty($oldProducts) && empty($newData['products'])) {
            $json['products'] = ['oldValue' => $oldProducts, 'newValue' => []];
        }
        foreach ($newData as $key => $value) {
            if (in_array($key, $except)) {
                continue;
            }
            if ($type == ReceiptsOrderHistoryType::PENDING && isset($oldData[$key]) && $oldData[$key] != $newData[$key]) {
                $json[$key] = ['oldValue' => $oldData[$key], 'newValue' => $newData[$key]];
            }
            if ($type == ReceiptsOrderHistoryType::CREATE_AND_APPLY) {
                $json[$key] = ['oldValue' => '', 'newValue' => $newData[$key]];
            }
        }
        if ($isInsert === false) {
            return empty($json) ? false : true;
        }
        if ($json || $type == ReceiptsOrderHistoryType::CREATE) {
            $json && $data['update_content'] = json_encode($json, JSON_UNESCAPED_UNICODE);
            $data['type'] = ($newType ?? $type);
            ReceiptsOrderHistoryType::CREATE && $data['create_time'] = ReceiptsOrder::where('receive_order_id', $data['receive_order_id'])->value('create_time');
            return ReceiptsOrderHistory::query()->insert($data);
        }

        return false;
    }

}
