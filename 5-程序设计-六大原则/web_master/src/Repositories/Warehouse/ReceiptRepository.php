<?php

namespace App\Repositories\Warehouse;

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductType;
use App\Enums\Warehouse\ReceiptShipping;
use App\Enums\Warehouse\ReceiptsOrderHistoryType;
use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Stock\ReceiptsOrder;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Models\Stock\ReceiptsOrderHistory;
use App\Repositories\Product\ProductRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReceiptRepository
{
    protected $country;
    protected $currency;

    public function __construct()
    {
        $this->country = session('country', 'USA');
        $this->currency = session('currency', 3);
    }
    /**
     * 起运港列表
     *
     * @return Collection
     */
    public function getDepartureList()
    {
        return db('tb_sys_dictionary')
            ->select('DicValue')
            ->where('DicCategory', 'PORT_OF_DEPARTURE')
            ->where('status',YesNoEnum::YES)
            ->get()->pluck('DicValue');
    }

    /**
     * 集装箱尺寸列表
     *
     * @return Collection
     */
    public function getContainerSizeList()
    {
        return db('tb_sys_dictionary')
            ->select('DicValue')
            ->where('DicCategory', 'CONTAINER_SIZE')
            ->where('status',YesNoEnum::YES)
            ->get()->pluck('DicValue');
    }

    /**
     * 获取入库单详情
     *
     * @param int $customerId SellerId
     * @param int $id 入库单ID
     * @return ReceiptsOrder|null
     */
    public function getReceiptById($customerId, $id)
    {
        $receipt = ReceiptsOrder::query()->with([
            'receiptDetails' => function ($q) {$q->orderBy('sku', 'ASC');},
            'receiptDetails.productDesc:product_id,name',
            'shippingOrderBook'
        ])->where('customer_id', $customerId)->find($id);
        if (!empty($receipt->area_warehouse)) {
            $receipt->area_warehouse = db('tb_warehouses')->where('WarehouseID', $receipt->area_warehouse)->value('WarehouseCode');
        }
        return $receipt;
    }

    /**
     * 获取入库单详情 ByReceiveOrderId
     *
     * @param int $customerId 用户ID
     * @param int $receiveOrderId  入库单ID
     * @return ReceiptsOrder|null
     */
    public function getReceiptByReceiveOrderId($customerId, $receiveOrderId)
    {
        return ReceiptsOrder::query()
            ->where('customer_id', $customerId)
            ->find($receiveOrderId);
    }

    /**
     * 生成入库单编号
     *
     * @param int $customerId 用户ID
     * @return string
     */
    public static function generateReceiptNumber($customerId): string
    {
        $customer = Customer::query()->find($customerId);
        $customerNumber = $customer->firstname . $customer->lastname;
        $number = ReceiptsOrder::query()
            ->where('customer_id', $customerId)
            ->whereBetween('create_time', [date('Y-m-d'), date('Y-m-d', strtotime('+1 day'))])
            ->count();
        return $customerNumber . '-' . date('Ymd') . '-' . str_pad(($number + 1), 3, 0, STR_PAD_LEFT);
    }

    /**
     * 查询入库单列表与个数
     *
     * @param array $filter 过滤参数数组
     * @param int $customerId 用户ID
     * @return array （count为个数  list为列表）
     */
    public function getReceiptOrderListAndCount(array $filter, int $customerId)
    {
        $receiptOrderQuery = receiptsOrder::query()->where('customer_id', $customerId);

        //筛选条件
        if (isset($filter['filter_receiving_order_number']) && trim($filter['filter_receiving_order_number']) != '') {
            $receive_number = str_replace(['%', '_'], ['\%', '\_'], trim($filter['filter_receiving_order_number']));
            $receiptOrderQuery = $receiptOrderQuery->where('receive_number', 'like', "%{$receive_number}%");
        }
        if (isset($filter['filter_container_code']) && trim($filter['filter_container_code']) != '') {
            $filter['filter_container_code'] = html_entity_decode(trim($filter['filter_container_code']));
            $receiptOrderQuery = $receiptOrderQuery->where('container_code', '=', $filter['filter_container_code']);
        }
        if (isset($filter['filter_status']) && trim($filter['filter_status']) != '') {
            //现在的已取消 包含已废弃
            if (trim($filter['filter_status']) == ReceiptOrderStatus::CANCEL || trim($filter['filter_status']) == ReceiptOrderStatus::ABANDONED) {
                $receiptOrderQuery = $receiptOrderQuery->whereIn('status', [ReceiptOrderStatus::ABANDONED, ReceiptOrderStatus::CANCEL]);
            } else {
                $receiptOrderQuery = $receiptOrderQuery->where('status', trim($filter['filter_status']));
            }
        }
        if (isset($filter['filter_received_from']) && trim($filter['filter_received_from']) != '') {
            $filter_received_from = trim($filter['filter_received_from']) . ' 00:00:00';
            $receiptOrderQuery = $receiptOrderQuery->where('receive_date', '>=', $filter_received_from);
        }
        if (isset($filter['filter_received_to']) && trim($filter['filter_received_to']) != '') {
            $filter_received_to = trim($filter['filter_received_to']) . ' 23:59:59';
            $receiptOrderQuery = $receiptOrderQuery->where('receive_date', '<=', $filter_received_to);
        }
        if (isset($filter['filter_shipping_way']) && trim($filter['filter_shipping_way']) != '') {
            $receiptOrderQuery = $receiptOrderQuery->where('shipping_way', trim($filter['filter_shipping_way']));
        }

        //个数
        $receiptOrderCount = $receiptOrderQuery->count();

        $receiptOrderQuery = $receiptOrderQuery->select($filter['column'] ?? ['*']);
        $receiptOrderQuery = $receiptOrderQuery->selectRaw('ifnull(apply_date,create_time) as apply_sort_date');

        //排序与分页等
        $receiptOrderQuery = $receiptOrderQuery->orderByDesc('apply_sort_date');
        if (isset($filter['page']) && trim($filter['page']) != '' && isset($filter['limit']) && trim($filter['limit'])) {
            $receiptOrderQuery = $receiptOrderQuery->forPage(trim($filter['page']), trim($filter['limit']));
        }
        $receiptOrderList = $receiptOrderQuery->get()->toArray();
        return [
            'receiptOrderCount' => $receiptOrderCount,
            'receiptOrderList' => $receiptOrderList
        ];
    }

    /**
     * 是否确认 入库商品包装规范
     *
     * @param int $customerId 用户ID
     * @return bool
     */
    public function existConfirmReceiptsProductPacking(int $customerId)
    {
        return db('tb_sys_receipts_order_remind')
            ->where('customer_id', $customerId)
            ->exists();
    }

    /**
     * 联想&检索 入库单
     *
     * @param int $custoerId
     * @param int $type  0：实际查询 1：模糊匹配
     * @param string $receiveNumber 入库单编号
     * @param string $mpn MPN
     * @param string $itemCode
     * @param int $checkReceipt 是否检索入库单
     * @return CustomerPartnerToProduct|ReceiptsOrder|Collection
     */
    public function getReceiptAutocompleteList(int $custoerId, int $type = 1, $receiveNumber = '', $mpn = '', $itemCode = '', $checkReceipt = 0)
    {
        $receiveNumber = escape_like_str($receiveNumber);
        if ($receiveNumber !== '' || $checkReceipt) { // 有入库单号过滤条件-则检索入库单表
            return ReceiptsOrder::query()->alias('ro')
                ->leftJoin('tb_sys_receipts_order_detail as rod', 'ro.receive_order_id', '=', 'rod.receive_order_id')
                ->select(['rod.receive_number as batch_number', 'rod.sku', 'rod.mpn', 'rod.expected_qty as estimated_quantity', 'rod.receive_order_detail_id as id', 'rod.product_id'])
                ->where('ro.customer_id', $custoerId)
                ->whereNotNull('rod.sku')
                ->when($receiveNumber !== '', function ($query) use ($receiveNumber) {
                    return $query->whereRaw('instr(ro.receive_number, ?)', [$receiveNumber]);
                })
                ->when($mpn !== '', function ($query) use ($mpn, $type) {
                    if ($type == 1) {
                        return $query->whereRaw('instr(rod.mpn, ?)', [$mpn]);
                    }
                    return $query->where('rod.mpn', $mpn);
                })
                ->when($itemCode !== '', function ($query) use ($itemCode, $type) {
                    if ($type == 1) {
                        return $query->whereRaw('instr(rod.sku, ?)', [$itemCode]);
                    }
                    return $query->where('rod.sku', $itemCode);
                })
                ->get();
        } else {
            return CustomerPartnerToProduct::query()->alias('c2p')
                ->leftJoin('oc_product as p', function ($query) use ($custoerId) {
                    return $query->on('c2p.product_id', '=', 'p.product_id')
                        ->where('c2p.customer_id', $custoerId);
                })
                ->where('p.is_deleted', YesNoEnum::NO)
                ->where('p.combo_flag', YesNoEnum::NO)
                ->where('p.product_type', ProductType::NORMAL)
                ->select(['p.sku', 'p.mpn', 'p.product_id'])
                ->when($mpn !== '', function ($query) use ($mpn, $type) {
                    if ($type == 1) {
                        return $query->whereRaw('instr(p.mpn, ?)', [$mpn]);
                    }
                    return $query->where('p.mpn', $mpn);
                })
                ->when($itemCode !== '', function ($query) use ($itemCode, $type) {
                    if ($type == 1) {
                        return $query->whereRaw('instr(p.sku, ?)', [$itemCode]);
                    }
                    return $query->where('p.sku', $itemCode);
                })
                ->get();
        }

    }

    /**
     * 计算给定日期之后的n个工作日
     *
     * @param $date
     * @param $workdays
     * @return false|string
     */
    public function getWorkDate($date, $workdays)
    {
        date_default_timezone_set(CountryHelper::getTimezoneByCode($this->country));
        $i = 1;
        $j = 1;
        while ($i <= $workdays) {
            $startDate = date('Y-m-d', strtotime('+' . $j . ' days', strtotime($date)));
            if (!$this->isHoliday($startDate)) {
                $i++;
            }
            $j++;
        }
        return $startDate;
    }

    /**
     * 判断是否美国节假日
     *
     * 1、1月1日：新年（元旦）
     * 2、2月份第三个星期一：总统日，放假1天 
     * 3、5月份的最后一个星期一：阵亡将士纪念，放假1天 
     * 4、7月4日：美国独立纪念日，放假2天
     * 5、9月份第一个的星期一：劳动节，放假1天
     * 6、11月份的最后一个星期四：感恩节，放假1天 
     * 7、12月25日：圣诞节
     *
     * @param string $date 格式化日期
     * @return bool
     */
    public function isHoliday($date)
    {
        if ((date('w', strtotime($date)) == 6) || (date('w', strtotime($date)) == 0)) {
            return true;
        }
        $holidays = explode(',', configDB('usa_hoilday'));
        if (in_array($date, $holidays)) {
            return true;
        }
        return false;
    }

    /**
     *　获取期望发货时间:当前日期之后的4个工作日为第一个可选周期的起始时间，时间间隔为4个工作日，含起始时间，可选择的周期为5个
     * @return array
     */
    public function getDeliveryDate()
    {
        date_default_timezone_set(CountryHelper::getTimezoneByCode($this->country));
        $workdays = 4;
        $currentDate = app(ReceiptRepository::class)->getWorkDate(date('Y-m-d'), $workdays);
        for ($i = 0; $i < 6; $i++) {
            $end =  app(ReceiptRepository::class)->getWorkDate($currentDate, $workdays - 1);
            $data[] = [
                'start' => $currentDate,
                'end' => $end,
            ];
            $currentDate =  app(ReceiptRepository::class)->getWorkDate(date('Y-m-d', strtotime($end)), 1);
        }
        return $data;
    }

    /**
     * 默认展示7个周期
     * 中国时间周二（含周二）之前，选择最早期望船期为当前时间的第四个周四到下周三，过了周二，最早可选周期则推移一周
     * @example 当前时间为中国时间6月1号，即周二，则可选择的期望船期为6月24日-6月30日，过了6月1号，则能选择的最早周期为7月1日-7月7日
     *
     * @param boolean $isSelf 是否客户自发
     *
     * @return array
     */
    public function getShippingDate($isSelf = false)
    {
        $timezoneId = 'Asia/Shanghai';
        date_default_timezone_set($timezoneId);

        $tagWeek = 4;
        if ($isSelf) {
            $nowWeek  = Carbon::now()->timezone($timezoneId)->addDays(8)->format('N');
            if ($nowWeek <= $tagWeek) {
                $addDays = $tagWeek - $nowWeek;
            } else {
                $addDays = 7 - $nowWeek + $tagWeek;
            }

            $defaultAddDays = 8;
        } else {
            $nowWeek = date('N');
            if ($nowWeek <= $tagWeek) {
                $addDays = $tagWeek - $nowWeek;
            } else {
                $addDays = 7 - $nowWeek + $tagWeek;
            }

            $defaultAddDays = 21;
            if ($nowWeek > 2 && $nowWeek <= $tagWeek) {
                $defaultAddDays += 7;
            }
        }

        $data = [];
        for ($i = 0; $i < 6; $i++) {
            $data[] = [
                'start' => Carbon::now()->timezone($timezoneId)->addDays($defaultAddDays)->addDays($addDays + (7 * $i))->toDateString(),
                'end' => Carbon::now()->timezone($timezoneId)->addDays($defaultAddDays)->addDays($addDays + (7 * $i) + 6)->toDateString(),
            ];
        }
        return $data;
    }

    /**
     * 获取预计到库时间列表
     *
     * @return array
     */
    public function getExpectedWarehouseDate()
    {
        $startTimestamp = strtotime('+30 day');
        $data = [];
        for ($i = 0; $i < 4; $i++) {
            $data[] = [
                'start' => date('Y-m-d', $startTimestamp + 3600 * 24 * 15 * $i),
                'end' => date('Y-m-d', $startTimestamp + 3600 * 24 * 15 * ($i + 1) - 1),
            ];
        }
        return $data;
    }

    /**
     * 获取入库单的修改历史
     * @param $receiveOrderId
     * @return array
     */
    public function getReceiptsOrderHistory($receiveOrderId)
    {
        $records = ReceiptsOrderHistory::query()->where(['receive_order_id' => $receiveOrderId])->orderBy('id', 'DESC')->get();
        $shippingWay = ReceiptsOrder::query()->where(['receive_order_id' => $receiveOrderId])->value('shipping_way');
        $data = [];
        if ($shippingWay == ReceiptShipping::B2B_LOCAL) {
            $start = __('期望发货起始时间', [], 'service/receipt');
            $end = __('期望发货截止时间', [], 'service/receipt');
            $port = __('起运城市', [], 'service/receipt');
        } else {
            $start = __('期望船期起始时间', [], 'service/receipt');
            $end = __('期望船期截止时间', [], 'service/receipt');
            $port = __('起运港', [], 'service/receipt');
        }
        $column = [
            'expected_shipping_date_start' => $start,
            'expected_shipping_date_end' => $end,
            'shipping_company' => __('船公司', [], 'service/receipt'),
            'eta_date' => 'ETA',
            'etd_date' => 'ETD',
            'shipping_way' => __('运输方式', [], 'service/receipt'),
            'container_code' => __('集装箱号', [], 'service/receipt'),
            'container_size' => __('集装箱尺寸', [], 'service/receipt'),
            'port_start' => $port,
            'status' => __('状态', [], 'service/receipt'),
            'area_warehouse' => __('收货区域', [], 'service/receipt'),
            'remark' => __('备注', [], 'service/receipt'),
            'products' => __('产品', [], 'service/receipt'),
        ];
        foreach ($records as $k => $record) {
            // 海运回传过滤
            if ($record['type'] == ReceiptsOrderHistoryType::RETURN) {
                continue;
            }
            $data[$k]['create_time'] = $record['create_time'];
            $data[$k]['type'] = ReceiptsOrderHistoryType::getDescription($record['type']);
            $action = __('改成', [], 'service/receipt');
            $data[$k]['content'][0] = '';
            if ($record['type'] == ReceiptsOrderHistoryType::CREATE) {
//                $data[$k]['content'][0] = __('创建入库单并保存', [], 'service/receipt');
                continue;
            }
            if ($record['type'] == ReceiptsOrderHistoryType::CREATE_AND_APPLY) {
                // 运营顾问确认成功
                $recordContent = json_decode($record['update_content'], true);
                if (isset($recordContent['status']) && $recordContent['status']['oldValue'] == ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING && $recordContent['status']['newValue'] == ReceiptOrderStatus::APPLIED) {
                    $data[$k]['type'] = __('运营顾问确认成功，入库单状态更新为已申请', [], 'service/receipt');
                } else {
                    continue;
                }
//                $data[$k]['content'][0] = __('创建入库单并提交申请', [], 'service/receipt');
            }

            if ($record['type'] == ReceiptsOrderHistoryType::FAILED || $record['type'] == ReceiptsOrderHistoryType::APPROVD) {
                $action = __('更新为', [], 'service/receipt');
                $recordContent = json_decode($record['update_content'], true);
                // 取消申请的审核记录
                if ($record['parent_id']) {
                    $parentRecord = ReceiptsOrderHistory::query()->where('id', $record['parent_id'])->first()->toArray();
                    $parentRecordContent = json_decode($parentRecord['update_content'], true);
                    if (!empty($parentRecordContent['status'])) {
                        if ($parentRecord['type'] == ReceiptsOrderHistoryType::PENDING && $recordContent['status']['oldValue'] == ReceiptOrderStatus::CANCEL_PENDING) {
                            if ($record['type'] == ReceiptsOrderHistoryType::FAILED) {
                                $data[$k]['type'] = __('入库单取消失败', [], 'service/receipt');
                                $data[$k]['content'][0] .= __('，原因：', [], 'service/receipt') . ($record['remark'] ?? '');
                            }
                            if ($record['type'] == ReceiptsOrderHistoryType::APPROVD) {
                                $data[$k]['type'] = __('入库单取消成功', [], 'service/receipt');
                            }
                            $data[$k]['content'][0] = __('状态更新为', [], 'service/receipt') . ReceiptOrderStatus::getViewItems()[$recordContent['status']['newValue']];
                            continue;
                        }
                    }
                }

                if ($record['type'] == ReceiptsOrderHistoryType::FAILED) {
                    $data[$k]['type'] = __('入库单修改失败', [], 'service/receipt');
                    isset($recordContent['status']['newValue']) && $data[$k]['content'][0] = __('状态更新为', [], 'service/receipt') . ReceiptOrderStatus::getViewItems()[$recordContent['status']['newValue']];
                    if ($record['remark']) {
                        $data[$k]['content'][0] .= __('，原因：', [], 'service/receipt') . $record['remark'];
                    }
                    continue;
                }
                if ($record['type'] == ReceiptsOrderHistoryType::APPROVD) {
                    $data[$k]['type'] = __('入库单修改成功', [], 'service/receipt');
                    $data[$k]['content'][0] = __('状态更新为', [], 'service/receipt') . ReceiptOrderStatus::getViewItems()[$recordContent['status']['newValue']].__('， ', [], 'service/receipt');
                }
            }

            $i = 0;
            $arr = json_decode($record['update_content'], true);
            if (empty($arr)) {
                continue;
            }
            foreach ($arr as $key => $item) {
                if ($key == 'shipping_way') {
                    $item['oldValue'] = ReceiptShipping::getDescription($item['oldValue']);
                    $item['newValue'] = ReceiptShipping::getDescription($item['newValue']);
                }
                if ($key == 'status') {
                    if ($record['type'] == ReceiptsOrderHistoryType::PENDING && $item['newValue'] == ReceiptOrderStatus::CANCEL_PENDING) {
                        $data[$k]['type'] = __('取消申请', [], 'service/receipt');
                    }
                    if ($record['type'] == ReceiptsOrderHistoryType::PENDING && $item['newValue'] == ReceiptOrderStatus::CANCEL) {
                        $data[$k]['type'] = __('入库单取消成功，状态更新为已取消', [], 'service/receipt');
                    }
                    continue;
                }
                //格式化时间
                if (is_string($item['oldValue']) && strpos($item['oldValue'], ':')) {
                    $item['newValue'] = date('Y-m-d', strtotime($item['newValue']));
                    $item['oldValue'] = date('Y-m-d', strtotime($item['oldValue']));
                }
                if (!isset($column[$key])) {
                    continue;
                }
                if ($key != 'products') {
                    if ($record['type'] == ReceiptsOrderHistoryType::APPROVD) {
                        if ($i == 0) {
                            is_string($item['newValue']) && $data[$k]['content'][0] .= $column[$key] . $action . '[' . $item['newValue'] . ']';
                        } else {
                            $data[$k]['content'][$i] = $column[$key] . $action . '[' . $item['newValue'] . ']';
                        }
                    }else{
                        if ($i == 0) {
                            is_string($item['newValue']) && $data[$k]['content'][0] .= __('修改', [], 'service/receipt') . $column[$key] . ': [' . $item['oldValue'] . ']' . $action . '[' . $item['newValue'] . ']';
                        } else {
                            $data[$k]['content'][$i] = $column[$key] . ': [' . $item['oldValue'] . ']' . $action . '[' . $item['newValue'] . ']';
                        }
                    }
                }
                if ($key == 'products') {
                    $newProductIds = array_column($item['newValue'] ?: [], 'product_id');
                    $oldProductIds = array_column($item['oldValue'] ?: [], 'product_id');
                    $newProducts = array_combine($newProductIds, $item['newValue'] ?: []);
                    $oldProducts = array_combine($oldProductIds, $item['oldValue'] ?: []);
                    // 算出删除的产品
                    $intersectIds = array_intersect($newProductIds, $oldProductIds);
                    $diffIds = array_diff($oldProductIds, $intersectIds);
                    foreach ($diffIds as $productId) {
                        if ($i == 0) {
                            $data[$k]['content'][$i] .= __('删除SKU ', [], 'service/receipt') . app(ProductRepository::class)->getSkuByProductId($productId);
                        } else {
                            $data[$k]['content'][$i] = __('删除SKU ', [], 'service/receipt') . app(ProductRepository::class)->getSkuByProductId($productId);
                        }
                        $i++;
                        continue;
                    }
                    // 新增和修改的sku明细
                    foreach ($newProducts as $productId => $product) {
                        $sku = app(ProductRepository::class)->getSkuByProductId($productId);
                        $flag = false;
                        if (empty($oldProducts[$productId])) {
                            if ($i == 0) {
                                $data[$k]['content'][$i] .= __('新增SKU ', [], 'service/receipt') . $sku;
                            } else {
                                $data[$k]['content'][$i] = __('新增SKU ', [], 'service/receipt') . $sku;
                            }
                            $i++;
                            continue;
                        }
                        if (!isset($product['expected_qty']) || !isset($product['hscode']) || !isset($product['301_hscode'])) {
                            continue;
                        }
                        if ($product['expected_qty'] == $oldProducts[$productId]['expected_qty'] && ReceiptShipping::B2B_LOCAL == $shippingWay) {
                            continue;
                        }

                        if ($product['expected_qty'] != $oldProducts[$productId]['expected_qty'] || $product['hscode'] != $oldProducts[$productId]['hscode'] || $product['301_hscode'] != $oldProducts[$productId]['301_hscode']) {
                            if ($i == 0) {
                                $data[$k]['content'][$i] .= __('修改SKU ', [], 'service/receipt') . $sku . ' : ';
                            } else {
                                $data[$k]['content'][$i] = __('修改SKU ', [], 'service/receipt') . $sku . ' : ';
                            }
                            $flag = true;
                        }
                        if ($product['expected_qty'] != $oldProducts[$productId]['expected_qty']) {
                            $data[$k]['content'][$i] .= __('预计入库数量', [], 'service/receipt') . '[' . intval($oldProducts[$productId]['expected_qty']) . ']' . $action . '[' . intval($product['expected_qty']) . ']' . '&nbsp&nbsp';
                        }
                        if ($product['hscode'] != $oldProducts[$productId]['hscode'] && ReceiptShipping::B2B_LOCAL != $shippingWay) {
                            $data[$k]['content'][$i] .= __('HS编码', [], 'service/receipt') . '[' . $oldProducts[$productId]['hscode'] . ']' . $action . '[' . $product['hscode'] . ']' . '&nbsp&nbsp';
                        }
                        if ($product['301_hscode'] != $oldProducts[$productId]['301_hscode'] && ReceiptShipping::B2B_LOCAL != $shippingWay) {
                            $data[$k]['content'][$i] .= __('301 HS编码', [], 'service/receipt') . '[' . $oldProducts[$productId]['301_hscode'] . ']' . $action . '[' . $product['301_hscode'] . ']' . '&nbsp&nbsp';
                        }
                        $flag && $i++;
                    }
                }
                !empty($data[$k]['content'][$i]) && $i++;
            }
        }
        return $data;
    }

    /**
     * 某个seller是否有入库单已同步到海运系统
     * @param int $sellerId
     * @return bool
     */
    public function existSynchronizedReceivesBySellerId(int $sellerId): bool
    {
        return ReceiptsOrder::query()
            ->where('customer_id', $sellerId)
            ->whereIn('status', ReceiptOrderStatus::synchronizedOceanShippingStatus())
            ->exists();
    }

}
