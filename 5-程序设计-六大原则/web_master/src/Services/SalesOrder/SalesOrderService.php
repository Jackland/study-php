<?php

namespace App\Services\SalesOrder;


use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Enums\SalesOrder\HomePickUploadType;
use App\Logging\Logger;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Models\Freight\InternationalOrder;
use App\Models\Freight\InternationalOrderConfig;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\SalesOrder\CustomerSalesOrderPickUp;
use App\Models\SalesOrder\CustomerSalesOrderTemp;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\Order\OrderAssociatedService;
use Carbon\Carbon;
use Exception;
use ModelExtensionModuleEuropeFreight;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SalesOrderService
{
    /**
     * @param int $salesOrderId
     * @param int $status
     **@author xxl
     * @description 修改销售订单状态
     * @date 22:12 2021/1/17
     */
    public function updateSalesOrderStatus(int $salesOrderId, int $status)
    {
        CustomerSalesOrder::where('id', '=', $salesOrderId)
            ->update(['order_status' => $status]);
    }

    static $countryIdCodeMap = [
        222 => ['UK', 'GB'], // 英国
        81 => ['DE'], // 德国
        223 => ['US'], // 美国
        107 => ['JP'], // 日本
    ];

    /**
     * 校验一个订单是否是国际单
     * 注意:在salesOrder里有is_international字段用来表征该销售单是否是国际单
     * 该方法主要用来进行校验
     * @param int $countryId
     * @param string $shipCountryCode
     * @return bool
     */
    public function checkIsInternationalSalesOrder(int $countryId, string $shipCountryCode): bool
    {
        $checkArr = self::$countryIdCodeMap;
        if (isset($checkArr[$countryId]) && !in_array($shipCountryCode, $checkArr[$countryId])) {
            return true;
        }

        return false;
    }

    /**
     * 校验当前销售单运送地区是否合法
     * @param int $countryId 当前用户国别（buyer or seller）
     * @param string $shipCountryCode 销售单运输国别代码
     * @param string $shipZipCode
     * @return bool
     * @throws Exception
     */
    public function checkSalesOrderShipValid(int $countryId, string $shipCountryCode, string $shipZipCode): bool
    {
        $shipCountryCode = strtoupper($shipCountryCode);
        foreach (self::$countryIdCodeMap as $countryCodes) {
            if (in_array($shipCountryCode, $countryCodes)) {
                [$shipCountryCode] = $countryCodes;
            }
        }
        /** @var ModelExtensionModuleEuropeFreight $europeFreightModel */
        $europeFreightModel = load()->model('extension/module/europe_freight');
        $toId = $europeFreightModel->getCountryIdByZipCode(
            get_need_string($shipZipCode, [' ', '-', '*', '_']),
            $countryId, $shipCountryCode
        );
        return InternationalOrder::query()
            ->where(['country_code' => $shipCountryCode, 'country_id' => $countryId,])
            ->when($toId, function ($query) use ($toId) {
                $query->where('country_code_mapping_id', $toId);
            })
            ->exists();
    }

    /**
     * 校验当前国别是否允许国际单
     * @param int $countryId
     * @return bool
     */
    public function checkInternationalActive(int $countryId): bool
    {
        return !InternationalOrderConfig::query()
            ->where('status', 0)
            ->where('country_id', $countryId)
            ->exists();
    }

    /**
     * 校验当前销售单修改地址的信息能否通过校验
     * @param int $countryId
     * @param string $shipCountryCode
     * @param string $shipZipCode
     * @return bool
     * @throws Exception
     */
    public function checkSalesOrderCanEditAddress(int $countryId, string $shipCountryCode, string $shipZipCode): bool
    {
        if (!$this->checkIsInternationalSalesOrder($countryId, $shipCountryCode)) {
            return true;
        }
        // 校验到这里 说明为国际单
        if (!$this->checkInternationalActive($countryId)) {
            return false;
        }
        return $this->checkSalesOrderShipValid(...func_get_args());
    }

    /**
     * 调整销售单的明细
     * 减少个别 sku 的数量，或者删除某个 sku
     * 当原销售单的明细不在 $newLines 信息中时，表示需要删除该明细
     *
     * 注意：调整前不会判断销售单的状态和明细的状态
     *
     * @param int $salesOrderId 原销售单
     * @param array $newLines 格式如下，其中 item_code 是唯一的
     * [
     *   ['itemCode' => 'A', 'qty' => 1], // 非 combo
     *   ['itemCode' => 'B', 'qty' => 3, 'comboInfo' => [['B' => 2, 'B1' => 1, 'B2' => 2], ['B' => 1, 'B1' => 1, 'B3' => 1]]] // combo
     * ]
     */
    public function adjustLines(int $salesOrderId, array $newLines)
    {
        Logger::salesOrder(['销售单明细调整' => func_get_args()]);

        $lines = CustomerSalesOrderLine::query()
            ->where('header_id', $salesOrderId)
            ->where('item_status', '!=', CustomerSalesOrderLineItemStatus::DELETED) // 非删除的
            ->get();
        if ($lines->isEmpty()) {
            throw new InvalidArgumentException('明细不存在');
        }
        Logger::salesOrder(['原line信息' => $lines->map(function ($item) {
            return $item->only(['item_code', 'qty', 'item_status', 'combo_info']);
        })->all()]);

        $newLines = collect($newLines)->keyBy('itemCode');
        $needChangeLines = []; // 需要调整的明细
        foreach ($lines as $line) {
            if (!$newLines->has($line->item_code)) {
                // item_code 不存在时表示删除
                $needChangeLines[] = [
                    'id' => $line->id,
                    'qty_old' => $line->qty,
                    'qty_new' => 0,
                ];
                continue;
            }
            $newLine = $newLines->get($line->item_code);
            if ($line->qty != $newLine['qty']) {
                // 数量不等
                if ($line->qty < $newLine['qty']) {
                    // 只能少于原数据（即减少数量）
                    throw new Exception('新明细数量不能大于原数量');
                }
                $needChangeLines[] = [
                    'id' => $line->id,
                    'qty_old' => $line->qty,
                    'qty_new' => $newLine['qty'],
                    'combo_info' => array_filter($newLine['comboInfo'] ?? [], function ($item) use ($line) {
                        return $item[$line->item_code] > 0;
                    }),
                ];
            }
        }

        if (!$needChangeLines) {
            Logger::salesOrder(['无需调整明细']);
            return;
        }

        Logger::salesOrder(['实际调整明细' => $needChangeLines]);
        dbTransaction(function () use ($needChangeLines, $salesOrderId) {
            // 重新修改 line 信息，包括 combo_info 信息
            /** @var $needCancel Collection */
            list($needCancel, $qtyChange) = collect($needChangeLines)->partition(function ($item) {
               return $item['qty_new'] <= 0;
            });
            if (!$needCancel->isEmpty()) {
                // 销售单明细置为已删除
                CustomerSalesOrderLine::query()->whereIn('id', $needCancel->pluck('id')->all())
                    ->update(['item_status' => CustomerSalesOrderLineItemStatus::DELETED]);
            }
            foreach ($qtyChange as $item) {
                // 减少明细数据
                CustomerSalesOrderLine::query()->where('id', $item['id'])
                    ->update([
                        'qty' => $item['qty_new'],
                        'combo_info' => $item['combo_info'] ? json_encode($item['combo_info']) : null,
                    ]);
            }
            // 重新处理绑定关系
            app(OrderAssociatedService::class)->unbindBySalesOrderNewLines($salesOrderId);
            // 重新处理仓租绑定关系
            app(StorageFeeService::class)->unbindBySalesOrderNewAssociated($salesOrderId);
        }, 3);
    }

    /**
     * 保存销售单的自提货订单
     * @param array $data
     * @param int $importMode
     * @param string $runId
     * @param int $customerId
     * @return bool
     * @throws Exception
     */
    public function saveBuyerPickUpOrder(array $data, int $importMode, string $runId, int $customerId)
    {
        try {
            $createTime = Carbon::now();
            db()->getConnection()->beginTransaction();
            $salesOrderArr = [];
            $seqValue = db('tb_sys_sequence')->where('seq_key', 'tb_sys_customer_sales_order|YzcOrderId')->value('seq_value');
            foreach ($data as $key => $value) {
                //导入订单根据order_id来进行合并
                $orderId = trim($value['order_id']);
                $salesOrder = [];//封装数据
                $weekDay = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $salesOrder['order_id'] = $orderId;
                $salesOrder['order_date'] = $weekDay[(int)date('w', time())] . ',' . date('F j Y h:i A', time());
                $salesOrder['email'] = customer()->getEmail();
                $salesOrder['ship_name'] = '';
                $salesOrder['ship_address1'] = '';
                $salesOrder['ship_city'] = '';
                $salesOrder['ship_state'] = '';
                $salesOrder['ship_zip_code'] = '';
                $salesOrder['ship_country'] = '';
                $salesOrder['ship_phone'] = '';
                $salesOrder['ship_method'] = '';
                $salesOrder['ship_service_level'] = '';
                $salesOrder['bill_name'] = '';
                $salesOrder['bill_address'] = '';
                $salesOrder['bill_city'] = '';
                $salesOrder['bill_state'] = '';
                $salesOrder['bill_zip_code'] = '';
                $salesOrder['bill_country'] = '';
                $salesOrder['orders_from'] = HomePickImportMode::getDescription(HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP);
                $salesOrder['discount_amount'] = '0.0000';
                $salesOrder['tax_amount'] = '0.0000';
                $salesOrder['order_total'] = '0.00';
                $salesOrder['store_name'] = 'yzc';
                $salesOrder['store_id'] = 888;
                $salesOrder['buyer_id'] = $customerId;
                $salesOrder['customer_comments'] = '';
                $salesOrder['run_id'] = $runId;
                $salesOrder['order_status'] = CustomerSalesOrderStatus::TO_BE_PAID;
                $salesOrder['to_be_paid_time'] = $createTime;
                $salesOrder['order_mode'] = HomePickUploadType::ORDER_MODE_HOMEPICK;
                $salesOrder['import_mode'] = $importMode;
                $salesOrder['create_user_name'] = $customerId;
                $salesOrder['create_time'] = $createTime;
                $salesOrder['program_code'] = PROGRAM_CODE;
                $salesOrder['line_count'] = 1;
                $salesOrder['update_temp_id'] = '';
                $salesOrder['pick_up_info'] = [
                    'warehouse_id' => $value['warehouse_id'],
                    'apply_date' => $value['apply_date'],
                    'user_name' => $value['user_name'],
                    'user_phone' => $value['user_phone'],
                    'need_tray' => $value['need_tray'],
                    'can_adjust' => $value['can_adjust'],
                ];
                $salesOrder['product_info'][0] = [
                    'temp_id' => '',
                    'line_item_number' => 1,
                    'product_name' => trim($value['item_code']),
                    'qty' => $value['quantity'],
                    'item_price' => '0.00',
                    'item_tax' => 0,
                    'item_code' => trim($value['item_code']),
                    'alt_item_id' => trim($value['item_code']),
                    'run_id' => $runId,
                    'ship_amount' => 0,
                    'line_comments' => '',
                    'image_id' => 1,
                    'item_status' => 1,
                    'create_user_name' => $customerId,
                    'create_time' => $createTime,
                    'program_code' => PROGRAM_CODE,
                ];
                if (!isset($salesOrderArr[$orderId])) {
                    $seqValue++;
                    $salesOrder['yzc_order_id'] = 'YC-' . $seqValue;
                    $salesOrderArr[$orderId] = $salesOrder;
                } else {//一条订单多条明细
                    $tmp = $salesOrder['product_info'][0];
                    $tmp['line_item_number'] = count($salesOrderArr[$orderId]['product_info']) + 1;
                    $salesOrderArr[$orderId]['line_count'] = count($salesOrderArr[$orderId]['product_info']) + 1;
                    $salesOrderArr[$orderId]['order_total'] += $tmp['item_price'] * $tmp['qty'];
                    $salesOrderArr[$orderId]['order_total'] = sprintf('%.2f', $salesOrderArr[$orderId]['order_total']);
                    $salesOrderArr[$orderId]['product_info'][] = $tmp;//用于存明细
                }
            }
            //更新YzcOrderId
            db('tb_sys_sequence')->where('seq_key', 'tb_sys_customer_sales_order|YzcOrderId')
                ->update(['seq_value' => $seqValue, 'update_time' => Carbon::now()]);
            foreach ($salesOrderArr as $key => $value) {
                $lineTmp = $salesOrderArr[$key]['product_info'];
                $pickUpTmp = $salesOrderArr[$key]['pick_up_info'];
                unset($salesOrderArr[$key]['product_info']);
                unset($salesOrderArr[$key]['pick_up_info']);
                //写入tb_sys_customer_sales_order
                $insertId = CustomerSalesOrder::insertGetId($salesOrderArr[$key]);
                if ($insertId) {
                    foreach ($lineTmp as $k => $v) {
                        $lineTmp[$k]['header_id'] = $insertId;
                        //写入明细
                        CustomerSalesOrderLine::insert($lineTmp[$k]);
                    }
                    //写入tb_sys_customer_sales_order_pick_up
                    $pickUpTmp['sales_order_id'] = $insertId;
                    $pickUpTmp['create_time'] = $createTime;
                    CustomerSalesOrderPickUp::insert($pickUpTmp);
                }
            }
            db()->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            Logger::salesOrder('自提货导入订单错误');
            Logger::salesOrder($e);
            db()->getConnection()->rollBack();
            return false;
        }
    }

    /**
     * eBay导单
     * @param array $data
     * @param array $transactionIds
     * @param string $runId
     * @param int $customerId
     * @return array
     * [
     *  'success'=>1,  1成功
     *  'insertList'=>[]  导单id
     * ]
     * @throws Exception
     */
    public function saveEbayOrder(array $data, array $transactionIds, string $runId, int $customerId)
    {
        try {
            $countryId = (int)customer()->getCountryId();
            $createTime = Carbon::now();
            db()->getConnection()->beginTransaction();
            //写入临时表
            CustomerSalesOrderTemp::insert($data);
            $orderTempArr = CustomerSalesOrderTemp::query()->where('run_id', $runId)->where('buyer_id', $customerId)->get();
            if ($orderTempArr->isEmpty()) {
                return ['success' => 0, 'insertList' => []];
            }
            $salesOrderArr = [];
            $seqValue = db('tb_sys_sequence')->where('seq_key', 'tb_sys_customer_sales_order|YzcOrderId')->value('seq_value');
            foreach ($orderTempArr as $order) {
                //导入订单根据order_id来进行合并
                $orderId = trim($order->order_id);
                $salesOrder = [];
                //国际单情况1: UK国别的ship_country不为UK或者GB
                if (!in_array(strtoupper($order->ship_country), ['UK', 'GB']) && $countryId == UK_COUNTRY_ID) {
                    $salesOrder['is_international'] = 1;
                }
                //国际单情况2: DE国别的ship_country不为DE
                if (strtoupper($order->ship_country) != 'DE' && $countryId == DE_COUNTRY_ID) {
                    $salesOrder['is_international'] = 1;
                }
                $salesOrder['order_id'] = $orderId;
                $salesOrder['transaction_id'] = $transactionIds[$orderId];
                $salesOrder['order_date'] = $order->order_date;
                $salesOrder['email'] = $order->email;
                $salesOrder['ship_name'] = $order->ship_name;
                $salesOrder['ship_address1'] = $order->ship_address1;
                $salesOrder['ship_address2'] = $order->ship_address2;
                $salesOrder['ship_city'] = $order->ship_city;
                $salesOrder['ship_state'] = $order->ship_state;
                $salesOrder['ship_state_name'] = $order->ship_state_name;
                $salesOrder['ship_zip_code'] = $order->ship_zip_code;
                $salesOrder['ship_country'] = $order->ship_country;
                $salesOrder['ship_phone'] = $order->ship_phone;
                $salesOrder['ship_method'] = $order->ship_method;
                $salesOrder['delivery_to_fba'] = $order->delivery_to_fba;
                $salesOrder['ship_service_level'] = $order->ship_service_level;
                $salesOrder['ship_company'] = $order->ship_company;
                $salesOrder['shipped_date'] = $order->shipped_date;
                $salesOrder['bill_name'] = $order->bill_name;
                $salesOrder['bill_address'] = $order->bill_address;
                $salesOrder['bill_city'] = $order->bill_city;
                $salesOrder['bill_state'] = $order->bill_state;
                $salesOrder['bill_state_name'] = $order->bill_state_name;
                $salesOrder['bill_zip_code'] = $order->bill_zip_code;
                $salesOrder['bill_country'] = $order->bill_country;
                $salesOrder['orders_from'] = $order->orders_from;
                $salesOrder['sales_chanel'] = HomePickImportMode::getDescription(HomePickImportMode::IMPORT_MODE_EBAY);
                $salesOrder['discount_amount'] = $order->discount_amount;
                $salesOrder['tax_amount'] = $order->tax_amount;
                $salesOrder['order_total'] = $order->order_total;
                $salesOrder['payment_method'] = $order->payment_method;
                $salesOrder['store_name'] = 'yzc';
                $salesOrder['store_id'] = 888;
                $salesOrder['buyer_id'] = $order->buyer_id;
                $salesOrder['customer_comments'] = $order->customer_comments;
                $salesOrder['run_id'] = $order->run_id;
                $salesOrder['order_status'] = CustomerSalesOrderStatus::TO_BE_PAID;
                $salesOrder['order_mode'] = CustomerSalesOrderMode::DROP_SHIPPING;
                $salesOrder['import_mode'] = HomePickImportMode::IMPORT_MODE_EBAY;
                $salesOrder['create_user_name'] = $order->create_user_name;
                $salesOrder['create_time'] = $order->create_time;
                $salesOrder['program_code'] = $order->program_code;
                $salesOrder['line_count'] = 1;
                $salesOrder['update_temp_id'] = $order->id;
                $salesOrder['product_info'][0] = [
                    'temp_id' => $order->id,
                    'line_item_number' => 1,
                    'product_name' => $order->product_name,
                    'qty' => $order->qty,
                    'item_price' => $order->item_price,
                    'item_tax' => $order->item_tax,
                    'item_code' => $order->item_code,
                    'alt_item_id' => $order->alt_item_id,
                    'platform_sku' => $order->platform_sku,
                    'run_id' => $order->run_id,
                    'ship_amount' => $order->ship_amount,
                    'line_comments' => $order->customer_comments,
                    'image_id' => $order->brand_id,
                    'seller_id' => $order->seller_id,
                    'item_status' => 1,
                    'create_user_name' => $order->create_user_name,
                    'create_time' => $order->create_time,
                    'program_code' => $order->program_code,
                ];
                if (!isset($salesOrderArr[$orderId])) {
                    $seqValue++;
                    $salesOrder['yzc_order_id'] = 'YC-' . $seqValue;
                    $salesOrderArr[$orderId] = $salesOrder;
                } else {//一条订单多条明细
                    $tmp = $salesOrder['product_info'][0];
                    $tmp['line_item_number'] = count($salesOrderArr[$orderId]['product_info']) + 1;
                    $salesOrderArr[$orderId]['line_count'] = count($salesOrderArr[$orderId]['product_info']) + 1;
                    $salesOrderArr[$orderId]['order_total'] += $tmp['item_price'] * $tmp['qty'];
                    $salesOrderArr[$orderId]['order_total'] = sprintf('%.2f', $salesOrderArr[$orderId]['order_total']);
                    $salesOrderArr[$orderId]['product_info'][] = $tmp;//用于存明细
                }
            }
            //更新YzcOrderId
            db('tb_sys_sequence')->where('seq_key', 'tb_sys_customer_sales_order|YzcOrderId')
                ->update(['seq_value' => $seqValue, 'update_time' => Carbon::now()]);

            $insertList = [];
            //写入
            foreach ($salesOrderArr as $key => $value) {
                $lineTmp = $salesOrderArr[$key]['product_info'];
                unset($salesOrderArr[$key]['product_info']);
                //写入tb_sys_customer_sales_order
                $insertId = CustomerSalesOrder::insertGetId($salesOrderArr[$key]);
                if ($insertId) {
                    foreach ($lineTmp as $k => $v) {
                        $lineTmp[$k]['header_id'] = $insertId;
                        //写入明细
                        CustomerSalesOrderLine::insert($lineTmp[$k]);
                    }
                }
                array_push($insertList, $insertId);
            }
            db()->getConnection()->commit();
            return ['success' => 1, 'insertList' => $insertList];
        } catch (Exception $e) {
            Logger::salesOrder('一件代发eBay导入订单错误');
            Logger::salesOrder($e);
            db()->getConnection()->rollBack();
            return ['success' => 0, 'insertList' => []];
        }
    }
}
