<?php

namespace App\Catalog\Forms\SalesOrder;

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductTransactionType;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Logging\Logger;
use App\Models\Link\OrderAssociatedPre;
use App\Models\SalesOrder\PurchasePayRecord;
use App\Repositories\SalesOrder\WillCallMatchRepository;
use App\Repositories\Stock\StockManagementRepository;
use Framework\Model\RequestForm\RequestForm;
use Illuminate\Validation\Rule;
use ModelAccountSalesOrderMatchInventoryWindow;

class MatchSubmitForm extends RequestForm
{
    public $importMode;
    public $salesOrder;
    public $costRecord;
    private $countryId;
    private $buyerId;
    private $willCallMatchRepo;
    private $costList = []; // 实际囤货库存
    private $storeList = []; // 可采购店铺及采购方式
    private $storeListBark = []; // 可采购店铺及采购方式
    private $costUseBindList = []; // 使用囤货库存预绑定部分
    private $buyBindList = []; // 需要采购预绑定部分
    private $runId;
    public $result = [
        'status' => 110, // 状态  100 错误-需要刷新弹窗,110 错误-不需要刷新弹窗,120 错误-关闭弹窗,200 正确
        'errorMsg' => '', // 错误提示
        'data' => []
    ];

    public function __construct()
    {
        parent::__construct();
        $this->countryId = customer()->getCountryId();
        $this->buyerId = customer()->getId();
        $this->willCallMatchRepo = app(WillCallMatchRepository::class);
    }

    protected function getRules(): array
    {
        return [
            'salesOrder' => 'required|array',
            'salesOrder.*.sales_order_id' => 'required|int|min:1',
            'salesOrder.*.sales_order_line_id' => 'required|int|min:1',
            'salesOrder.*.item_code' => 'required',
            'salesOrder.*.qty' => 'required|int|min:1',
            'salesOrder.*.cost_qty' => 'required|int|min:0',
            'salesOrder.*.buy_qty' => 'required|int|min:0',
            'salesOrder.*.store_id' => 'required|int|min:0',
            'salesOrder.*.type' => [
                'required',
                'int',
                Rule::in(ProductTransactionType::getValues())
            ],
            'salesOrder.*.left_qty' => 'required|int|min:0',
            'salesOrder.*.product_id' => 'required|int|min:0',
        ];
    }

    protected function getRuleMessages(): array
    {
        return [
            '*' => 'Data exception.'
        ];
    }

    public function save()
    {
        $this->baseValidated();

        // 保存囤货匹配记录、需要采购记录
        $this->saveBindRecord();
    }

    /**
     * 数据校验
     *
     * @throws \Throwable
     */
    private function baseValidated()
    {
        // 基本请求数据校验
        if (! $this->isValidated()) {
            $this->result['status'] = 100;
            $this->result['errorMsg'] = 'Data exception.';

            throw new \Exception($this->getFirstError());
        }

        // 对于同一SKU的采购方式的校验
        $this->checkPurchaseType();

        // SalesOrder合法性校验-囤货库存、采购库存、商品有效性
        $this->checkDealSalesOrder();
    }

    /**
     * 对于同一SKU的采购方式校验
     *  1. 对于同一批次订单，对于同一SKU的采购，一次采购只能存在一种交易方式
     *  2. 提示出所有不满的SKU信息
     */
    private function checkPurchaseType()
    {
        $missSku = [];
        $existSku = [];

        foreach ($this->salesOrder as $item) {
            if (! $item['buy_qty']) {
                continue;
            }
            $typeValue = $item['type'] . '|' . $item['agreement_id'];
            if (isset($existSku[$item['item_code']][$item['store_id']])) {
                $existSku[$item['item_code']][$item['store_id']] != $typeValue && $missSku[] = $item['item_code'];
            } else {
                $existSku[$item['item_code']][$item['store_id']] = $typeValue;
            }
        }

        if ($missSku) {
            $this->result['errorMsg'] = sprintf('The transaction type of %s is inconsistent in the selected Sales Orders.', implode('、', array_unique($missSku)));
            throw new \Exception($this->result['errorMsg']);
        }
    }

    /**
     * 校验处理销售单数据
     *
     * @throws \Throwable
     */
    private function checkDealSalesOrder()
    {
        // 获取能够提交预绑定的对应销售单
        $salesOrderIds = array_column($this->salesOrder, 'sales_order_id');
        $realSalesList = $this->willCallMatchRepo->getNewOrderList($this->buyerId, $salesOrderIds, $this->countryId)->toArray();
        if (! $realSalesList) {
            $this->result['status'] = 120;
            $this->result['errorMsg'] =  sprintf('The order (ID: %s) status has changed and the current operation is unavailable.', implode('、', array_unique(array_column($this->salesOrder, 'order_id'))));
            throw new \Exception($this->result['errorMsg']);
        }

        // 生成唯一处理标识
        list($ms, $sec) = explode(' ', microtime());
        $this->runId = (float)sprintf('%.0f', (floatval($ms) + floatval($sec)) * 1000);
        $nowTime = date('Y-m-d H:i:s');

        // 获取囤货库存
        $skuList = array_unique(array_column($realSalesList, 'item_code'));
        $this->costList = app(StockManagementRepository::class)->getBuyerCostBySku($this->buyerId, $skuList);

        $realCostRecord = [];
        foreach ($this->costList as $item) {
            isset($realCostRecord[$item['sku']]) ? $realCostRecord[$item['sku']] += $item['left_qty'] : $realCostRecord[$item['sku']] = $item['left_qty'];
        }
        // 获取能够采购列表
        $this->getPurchaseList($skuList);

        $needDealSalesOrderList = array_combine(array_column($this->salesOrder, 'sales_order_line_id'), $this->salesOrder);
        $costNotEnough = []; // 囤货库存不足SKU列表
        $buyNotEnough = []; // 采购库存不足列表
        $spotBuyList = []; // 议价采购数量

        foreach ($realSalesList as $item) {
            // 单条数据的基本匹配比对
            if (empty($needDealSalesOrderList[$item['line_id']]) ||
                $item['item_code'] != $needDealSalesOrderList[$item['line_id']]['item_code'] ||
                $item['id'] != $needDealSalesOrderList[$item['line_id']]['sales_order_id'] ||
                $item['qty'] != $needDealSalesOrderList[$item['line_id']]['qty'] ||
                $needDealSalesOrderList[$item['line_id']]['qty'] != $needDealSalesOrderList[$item['line_id']]['cost_qty'] + $needDealSalesOrderList[$item['line_id']]['buy_qty']
            ) {
                $this->result['status'] = 100;
                $this->result['errorMsg'] =  'Invalid Request.';
                throw new \Exception($this->result['errorMsg']);
            }
            $needDealSalesOrderItem = $needDealSalesOrderList[$item['line_id']];
            unset($needDealSalesOrderList[$item['line_id']]);

            $this->dealSalesOrderQty($needDealSalesOrderItem, $costNotEnough, $buyNotEnough, $spotBuyList, $nowTime);
        }
        if ($needDealSalesOrderList) { // 说明和实际销售单不匹配，非人为捏造前提下只会存在于订单状态放生改变
            $this->result['status'] = 100;
            $this->result['errorMsg'] = sprintf('The order (ID: %s) status has changed and the current operation is unavailable.', implode('、', array_unique(array_column(array_values($needDealSalesOrderList), 'order_id'))));
            throw new \Exception($this->result['errorMsg']);
        }

        // 可采购库存不足
        if ($buyNotEnough) {
            $this->result['status'] = 100;
            if (! empty($buyNotEnough['nonexistent'])) { // 品台不存在
                $this->result['errorMsg'] =  implode('、',  array_unique($buyNotEnough['nonexistent'])) . ' does not exist in the Marketplace and cannot be purchased.';
            } elseif (! empty($buyNotEnough['delicacy'])) { // 未绑定或者精细化不可见
                $this->result['errorMsg'] =  sprintf('You do not have permission to purchase %s, please contact Seller.', implode('、',  array_unique($buyNotEnough['delicacy'])));
            } elseif (! empty($buyNotEnough['noCostChange'])) { // 库存不足-库存发生变化
                $this->result['errorMsg'] =  'The available inventory is insufficient for your purchase. Please select a lower quantity.';
            } elseif (! empty($buyNotEnough['noCostNotChange'])) { // 库存不足-库存未发生变化
                $this->result['status'] = 110;
                $this->result['errorMsg'] =  sprintf('The available inventory of %s is insufficient. Please select again.', implode('、',  array_unique($buyNotEnough['noCostNotChange'])));
            }
            throw new \Exception($this->result['errorMsg']);
        }
        // 使用议价-购买数量不匹配
        if (! empty($spotBuyList)) {
            $spotNotBuy = [];
            foreach ($spotBuyList as $k => $v) {
                $relSpotQty = $this->storeListBark[$v['item_code']]['list'][$v['store_id']]['transaction_type'][ProductTransactionType::SPOT][$k]['left_qty'];
                if ($v['qty'] != $relSpotQty && ! isset($spotNotBuy[$v['item_code']])) {
                    $spotNotBuy[] = $v['item_code'];
                    $this->result['errorMsg'] .=  "The total purchase quantity of {$v['item_code']} does not meet the quantity specified as {$relSpotQty} in the Spot Price Agreement. Please select again.\n";
                }
            }
            if ($spotNotBuy) {
                throw new \Exception($this->result['errorMsg']);
            }
        }
        // 囤货库存不足
        if ($costNotEnough) {
            $this->result['errorMsg'] =  'The inventory in your account is insufficient for the order(s). Please select a lower quantity.';
            foreach ($costNotEnough as $item) {
                if (empty($this->costRecord[$item]) || empty($realCostRecord[$item]) || $this->costRecord[$item] != $realCostRecord[$item]) {
                    $this->result['status'] = 100; // 囤货库存不足 - 囤货库存发生了改变，需要刷新弹窗
                    break;
                }
            }
            throw new \Exception($this->result['errorMsg']);
        }
    }

    /**
     * 获取格式化可采购列表
     *
     * @param array $skuList 需要采购的SKU数组
     */
    private function getPurchaseList(array $skuList)
    {
        $this->storeList = app(WillCallMatchRepository::class)->getCanBuyProductInfoBySku($this->buyerId, $this->countryId, $skuList);
        foreach ($this->storeList as $key => $item) {
            foreach ($item['list'] as $subKey => $subItem) {
                $transactionType = [];
                foreach ($subItem['transaction_type'] as $secondSubItem) {
                    $id = $secondSubItem['id'] ?? 0;
                    $transactionType[$secondSubItem['type']][$id] = $secondSubItem;
                }
                $item['list'][$subKey]['transaction_type'] = $transactionType;
            }
            $this->storeList[$key]['list'] = array_combine(array_column($item['list'], 'customer_id'), $item['list']);
        }
        $this->storeListBark = $this->storeList;
    }

    /**
     * 保存库存匹配、需要采购记录
     *
     * @throws \Exception
     */
    public function saveBindRecord()
    {
        db()->getConnection()->beginTransaction();
        try {
            if ($this->costUseBindList) {
                $saveBindCost = OrderAssociatedPre::insert($this->costUseBindList);
                if (! $saveBindCost) {
                    throw new \Exception('保存匹配库存绑定失败');
                }
            }
            if ($this->buyBindList) {
                $saveBuy = PurchasePayRecord::insert($this->buyBindList);
                if (! $saveBuy) {
                    throw new \Exception('保存需要购买绑定失败');
                }
            }

            $this->result['status'] = 200;
            $this->result['errorMsg'] = '';
            $this->result['data']['runId'] = $this->runId;
            db()->getConnection()->commit();
        } catch (\Exception $e) {
            Logger::salesOrder('上门取货，匹配订单保存匹配记录发生错误：' . $e->getMessage(),'error');
            db()->getConnection()->rollBack();

            $this->result['status'] = 110;
            $this->result['errorMsg'] = 'Error(s) detected in your order submission. Please try again.';
        }
    }

    /**
     * 校验销售单的实际数量、使用囤货库存数量、采购数量
     *
     * @param array $salesOrder 传递销售单信息
     * @param array $costNotEnough 囤货库库存不足
     * @param array $buyNotEnough 购买数量不足
     * @param array $spotBuyList 议价采购数量
     * @param string $nowTime 统一时间
     * @throws \Exception
     */
    private function dealSalesOrderQty($salesOrder, &$costNotEnough, &$buyNotEnough, &$spotBuyList, $nowTime)
    {
        // 囤货库存处理
        if ($salesOrder['cost_qty']) {
            $needCostQty = $salesOrder['cost_qty'];
            foreach ($this->costList as $key => &$costInfo) {
                if ($costInfo['sku'] != $salesOrder['item_code']) {
                    continue;
                }
                $useQty = $costInfo['left_qty'] > $needCostQty ? $needCostQty : $costInfo['left_qty'];
                $this->costUseBindList[] = [
                    'sales_order_id' => $salesOrder['sales_order_id'],
                    'sales_order_line_id' => $salesOrder['sales_order_line_id'],
                    'order_id' => $costInfo['order_id'],
                    'order_product_id' => $costInfo['order_product_id'],
                    'product_id' => $costInfo['product_id'],
                    'seller_id' => $costInfo['seller_id'],
                    'buyer_id' => $this->buyerId,
                    'run_id' => $this->runId,
                    'status' => 0,
                    'memo' => 'purchase record add',
                    'associate_type' => 1,
                    'CreateUserName' => 'admin',
                    'CreateTime' => $nowTime,
                    'ProgramCode' => 'V1.0',
                    'qty' => $useQty
                ];
                $costInfo['left_qty'] -= $useQty;
                $needCostQty -= $useQty;
                if ($costInfo['left_qty'] <= 0) {
                    unset($this->costList[$key]);
                }
                if ($needCostQty <=0 ) {
                    break;
                }
            }
            if ($needCostQty > 0) { // 说明囤货库存不足
                $costNotEnough[] = $salesOrder['item_code'];
            }
        }

        $productId = null;
        $sellerId = 0;
        // 需要采购处理
        if ($salesOrder['buy_qty'] > 0) {
            if (empty($salesOrder['store_id']) ||
                ($salesOrder['type'] != ProductTransactionType::NORMAL && empty($salesOrder['agreement_id'])) ||
                empty($this->storeList[$salesOrder['item_code']]['status']) || $this->storeList[$salesOrder['item_code']]['status'] == 140) { // 异常情况
                throw new \Exception('Invalid Request');
            }
            $agreementId = $salesOrder['type'] == ProductTransactionType::NORMAL ? 0 : $salesOrder['agreement_id']; // 使用复杂交易的协议ID(普通购买则为0)
            if ($this->storeList[$salesOrder['item_code']]['status'] == 110) {
                // 平台不存在此SKU的商品
                $buyNotEnough['nonexistent'][] = $salesOrder['item_code'];
                return;
            }
            if ($this->storeList[$salesOrder['item_code']]['status'] == 120) {
                // 无权限购买
                $buyNotEnough['delicacy'][] = $salesOrder['item_code'];
                return;
            }
            if ($this->storeList[$salesOrder['item_code']]['status'] == 130 || empty($this->storeList[$salesOrder['item_code']]['list'][$salesOrder['store_id']]['transaction_type'][$salesOrder['type']][$agreementId])) {
                // 无库存-库存发生改变
                $buyNotEnough['noCostChange'][] = $salesOrder['item_code'];
                return;
            }

            // 采购数量处理
            $realLeftQty =  $this->storeList[$salesOrder['item_code']]['list'][$salesOrder['store_id']]['transaction_type'][$salesOrder['type']][$agreementId]['left_qty'];
            $useQty = $salesOrder['buy_qty'] > $realLeftQty ? $realLeftQty : $salesOrder['buy_qty'];
            $realLeftQty -= $useQty;
            $this->storeList[$salesOrder['item_code']]['list'][$salesOrder['store_id']]['transaction_type'][$salesOrder['type']][$agreementId]['left_qty'] = $realLeftQty;
            if ($salesOrder['buy_qty'] > $useQty) { // 说明可采购库存不足
                if (empty($salesOrder['left_qty']) || $salesOrder['left_qty'] != $this->storeListBark[$salesOrder['item_code']]['list'][$salesOrder['store_id']]['transaction_type'][$salesOrder['type']][$agreementId]['left_qty']) {
                    // 可采购数量不足 -- 可采购数量发生改变
                    $buyNotEnough['noCostChange'][] = $salesOrder['item_code'];
                } else {
                    // 可采购数量不足 -- 可采购数量未发生改变
                    $buyNotEnough['noCostNotChange'][] = $salesOrder['item_code'];
                }
                return;
            }
            if ($salesOrder['type'] == ProductTransactionType::SPOT) { // 对于议价需要判断整个批次订单所有的采购库存是否满足
                $spotBuyList[$agreementId]['item_code'] = $salesOrder['item_code'];
                $spotBuyList[$agreementId]['store_id'] = $salesOrder['store_id'];
                $spotBuyList[$agreementId]['qty'] = ($spotBuyList[$agreementId]['qty'] ?? 0) + $salesOrder['buy_qty'];
            }
            $productId = $this->storeList[$salesOrder['item_code']]['list'][$salesOrder['store_id']]['product_id'];
            $sellerId = $salesOrder['store_id'];
        }

        $this->buyBindList[] = [
            'order_id' => $salesOrder['sales_order_id'],
            'line_id' => $salesOrder['sales_order_line_id'],
            'item_code' => $salesOrder['item_code'],
            'product_id' => $productId,
            'sales_order_quantity' => $salesOrder['qty'],
            'quantity' => $salesOrder['buy_qty'],
            'type_id' => $salesOrder['type'],
            'agreement_id' => empty($salesOrder['agreement_id']) || $salesOrder['type'] == ProductTransactionType::NORMAL ? 0 : $salesOrder['agreement_id'],
            'customer_id' => $this->buyerId,
            'seller_id' => $sellerId,
            'run_id' => $this->runId,
            'memo' => 'purchase record',
            'create_user_name' => $this->buyerId,
            'create_time' => $nowTime,
            'program_code' => 'V1.0'
        ];
    }
}