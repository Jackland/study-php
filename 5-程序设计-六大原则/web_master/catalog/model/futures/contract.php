<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Future\FutureMarginContractStatus;
use App\Enums\Future\FuturesMarginApplyType;
use App\Enums\Future\FuturesMarginPayRecordBillType;
use App\Enums\Future\FuturesMarginPayRecordType;
use App\Repositories\Product\ProductPriceRepository;
use Catalog\model\futures\credit;

/**
 * @property ModelFuturesAgreement $model_futures_agreement
 *
 * Class ModelFuturesContract
 */
class ModelFuturesContract extends Model
{

    const DELIVERY_TYPES = [
        'N/A',
        'Direct settlement',
        'Transfer to margin transaction',
        'Direct settlement <br> Transfer to Margin transaction'
    ];

    /**
     * 根据sellerID和产品id和交货日期获取合约
     * @param int $sellerId
     * @param int $productId
     * @param string $deliveryDate
     * @return array
     */
    public function contractBySellerIdAndProductIdAndDeliveryDate(int $sellerId, int $productId, string $deliveryDate)
    {
        $result = $this->orm->table(DB_PREFIX . 'futures_contract')
            ->where('seller_id', $sellerId)
            ->where('product_id', $productId)
            ->where('is_deleted', 0)
            ->whereRaw("DATE(delivery_date) = ?", date('Y-m-d', strtotime($deliveryDate)))
            ->first();

        return obj2array($result);
    }

    /**
     * 根据合约id获取合约
     * @param int $contractId
     * @param int $status
     * @return array
     */
    public function contractById(int $contractId, $status = 0)
    {
        $result = $this->orm->table(DB_PREFIX . 'futures_contract')
            ->where('id', $contractId)
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->where('is_deleted', 0)
            ->first();

        return obj2array($result);
    }

    /**
     * 是否存在合约no获取合约
     * @param string $contractNo
     * @return bool
     */
    public function existContractByContractNo(string $contractNo)
    {
        return $this->orm->table(DB_PREFIX . 'futures_contract')
            ->where('contract_no', $contractNo)
            ->exists();
    }

    /**
     * 新增合约数据库操作逻辑
     * @param int $sellerId
     * @param int $productId
     * @param array $params
     * @return bool
     * @throws Exception
     */
    public function insertContract(int $sellerId, int $productId, array $params)
    {
        // 计算当前合约所占的抵押物金额
        $collateralBalance = 0;
        if ($params['pay_type'] == FuturesMarginPayRecordType::SELLER_BILL && $params['status'] != FutureMarginContractStatus::DISABLE) {
           $collateralBalance = $params['available_balance'] > $params['collateral_amount'] ? $params['collateral_amount'] : $params['available_balance'];
        }

        $con = $this->orm->getConnection();
        try {
            $con->beginTransaction();

            $contractId = $con->table('oc_futures_contract')->insertGetId([
                'contract_no' => $params['contract_no'],
                'seller_id' => $sellerId,
                'product_id' => $productId,
                'payment_ratio' => $params['payment_ratio'],
                'delivery_date' => $params['delivery_date'],
                'num' => $params['num'],
                'min_num' => $params['min_num'],
                'delivery_type' => $params['delivery_type'],
                'margin_unit_price' => $params['margin_unit_price'],
                'last_unit_price' => $params['last_unit_price'],
                'available_balance' => $params['status'] == 2 ? 0 : $params['available_balance'],
                'collateral_balance' => $collateralBalance,
                'is_bid' => $params['is_bid'],
                'status' => $params['status'],
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            if (!$contractId) {
                throw new Exception(__FILE__ . '[insert error.]');
            }

            if ($params['status'] == 2) {
                $con->table('oc_futures_contract_margin_apply')->insert([
                    'contract_id' => $contractId,
                    'old_payment_ratio' => $params['old_payment_ratio'],
                    'new_payment_ratio' => $params['payment_ratio'],
                    'operator' => $params['customer_name'],
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
            } else {
                if ($params['pay_type'] == 1) {
                    credit::insertCreditBill($sellerId, $params['available_balance'], 1);
                }

                if ($params['available_balance'] - $collateralBalance > 0) {
                    $con->table('oc_futures_contract_margin_pay_record')->insert([
                        'contract_id' => $contractId,
                        'customer_id' => $sellerId,
                        'type' => $params['pay_type'],
                        'amount' => $params['available_balance'] - $collateralBalance,
                        'bill_type' => 1,
                        'bill_status' => $params['pay_type'] == 1 ? 1 : 0,
                        'create_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                        'operator' => $params['customer_name'],
                    ]);
                }

                // 使用了抵押物金额
                if ($collateralBalance > 0) {
                    $con->table('oc_futures_contract_margin_pay_record')->insert([
                        'contract_id' => $contractId,
                        'customer_id' => $sellerId,
                        'type' => FuturesMarginPayRecordType::SELLER_COLLATERAL,
                        'amount' => $collateralBalance,
                        'bill_type' => FuturesMarginPayRecordBillType::EXPEND,
                        'bill_status' => 1,
                        'create_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                        'operator' => $params['customer_name'],
                    ]);
                }
            }

            $con->table('oc_futures_contract_log')->insert([
                'contract_id' => $contractId,
                'customer_id' => $sellerId,
                'type' => 1,
                'content' => $this->formatContractLogContent($params),
                'operator' => $params['customer_name'],
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            $con->commit();
            return true;
        } catch (Exception $e) {
            $con->rollBack();
            return false;
        }
    }

    /**
     * 编辑合约数据库操作逻辑
     * @param $contract
     * @param array $updateParams
     * @param string $operator
     * @param array $amountParams
     * @param bool $isJapan
     * @param array $depositPercentages
     * @return bool
     * @throws Exception
     */
    public function editContract($contract, array $updateParams, string $operator, array $amountParams = [], $isJapan = false, $depositPercentages = [])
    {
        $updateParams['update_time'] = date('Y-m-d H:i:s');

        $lastMarginApply = $this->lastMarginApplyByContractId($contract['id']);
        if (!empty($lastMarginApply) && $lastMarginApply['status'] != 1) {
            $radio = $updateParams['payment_ratio'] ?? $contract['payment_ratio'];
            $formatRadio = $isJapan ? floor($radio) : sprintf("%.2f", round($radio, 2));
            if (!in_array($formatRadio, $depositPercentages)) {
                $updateParams['status'] = 2;
            }
        }

        // 计算当前合约所占的抵押物金额
        $collateralBalance = 0;
        if ($amountParams['pay_type'] == FuturesMarginPayRecordType::SELLER_BILL && $updateParams['status'] != FutureMarginContractStatus::DISABLE) {
            $collateralBalance = $amountParams['remain_available_balance'] > $amountParams['collateral_amount'] ? $amountParams['collateral_amount'] : $amountParams['remain_available_balance'];
        }

        // 授信额度或者应收款还需的金额
        $remainAvailableBalance = $amountParams['remain_available_balance'] - $collateralBalance;

        $con = $this->orm->getConnection();
        try {
            $con->beginTransaction();

            $con->table('oc_futures_contract')
                ->where('id', $contract['id'])
                ->update($updateParams);

            if ($updateParams['status'] == 2 && (empty($lastMarginApply) || $lastMarginApply['status'] != 0)) {
                if (!empty($lastMarginApply) && $lastMarginApply['status'] == 2) {
                    $oldPaymentRatio = $lastMarginApply['old_payment_ratio'];
                    $newPaymentRatio = $updateParams['payment_ratio'] ?? $contract['payment_ratio'];
                } else {
                    $oldPaymentRatio = $contract['payment_ratio'];
                    $newPaymentRatio = $updateParams['payment_ratio'];
                }
                $con->table('oc_futures_contract_margin_apply')->insert([
                    'contract_id' => $contract['id'],
                    'old_payment_ratio' => $oldPaymentRatio,
                    'new_payment_ratio' => $newPaymentRatio,
                    'operator' => $operator,
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
            }

            if ($updateParams['status'] == 1 && isset($amountParams['remain_available_balance']) && isset($amountParams['pay_type']) && $amountParams['remain_available_balance'] > 0) {
                if ($amountParams['pay_type'] == 1) {
                    credit::insertCreditBill($contract['seller_id'], $amountParams['remain_available_balance'], 1);
                }

                if ($remainAvailableBalance > 0) {
                    $con->table('oc_futures_contract_margin_pay_record')->insert([
                        'contract_id' => $contract['id'],
                        'customer_id' => $contract['seller_id'],
                        'type' => $amountParams['pay_type'],
                        'amount' => $remainAvailableBalance,
                        'bill_type' => 1,
                        'bill_status' => $amountParams['pay_type'] == 1 ? 1 : 0,
                        'create_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                        'operator' => $operator,
                    ]);
                }

                // 使用了抵押物金额
                if ($collateralBalance > 0) {
                    $con->table('oc_futures_contract_margin_pay_record')->insert([
                        'contract_id' => $contract['id'],
                        'customer_id' => $contract['seller_id'],
                        'type' => FuturesMarginPayRecordType::SELLER_COLLATERAL,
                        'amount' => $collateralBalance,
                        'bill_type' => FuturesMarginPayRecordBillType::EXPEND,
                        'bill_status' => 1,
                        'create_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                        'operator' => $operator,
                    ]);

                    $con->table('oc_futures_contract')
                        ->where('id', $contract['id'])
                        ->increment('collateral_balance', $collateralBalance);
                }

                $con->table('oc_futures_contract')
                    ->where('id', $contract['id'])
                    ->increment('available_balance', $amountParams['remain_available_balance']);
            }

            $con->table('oc_futures_contract_log')->insert([
                'contract_id' => $contract['id'],
                'customer_id' => $contract['seller_id'],
                'type' => 3,
                'content' => $this->formatContractLogContent($updateParams,$contract),
                'operator' => $operator,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            $con->commit();
            return true;
        } catch (Exception $e) {
            $con->rollBack();
            return false;
        }
    }

    /**
     * 格式化合约变更记录的内容
     * @param array $contractData
     * @param array $oldContractInfo
     * @return false|string
     */
    private function formatContractLogContent(array $contractData, array $oldContractInfo = [])
    {
        $contentKeys = ['status', 'is_bid', 'min_num', 'delivery_type', 'margin_unit_price', 'last_unit_price', 'payment_ratio'];
        $content = [];
        foreach ($contentKeys as $contentKey) {
            $content[$contentKey] = isset($contractData[$contentKey]) ? strval($contractData[$contentKey]) : '';
        }

        if (!empty($oldContractInfo)) {
            foreach ($content as $k => $v) {
                if (!isset($oldContractInfo[$k])) {
                    continue;
                }
                if ($v !== '' && strval($oldContractInfo[$k]) != $v) {
                    $content[$k] = $oldContractInfo[$k] . '->' . $v;
                    continue;
                }
                $content[$k] = $oldContractInfo[$k];
            }
        }

        return json_encode($content);
    }

    /**
     * seller的合约列表（根据某些过滤条件）
     * @param int $sellerId
     * @param array $data
     * @return array
     */
    public function sellerContracts(int $sellerId, array $data = []): array
    {
        $ret = $this->querySelectContracts(...func_get_args());
        $ret->orderBy('c.' . ($data['sort_column'] ?? 'id'), ($data['sort'] ?? 'desc'));
        if (isset($data['page']) && isset($data['page_limit'])) {
            $ret->forPage(($data['page']), ($data['page_limit']));
        }

        return $ret->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     *  seller的合约列表总数据（根据某些过滤条件）
     * @param int $sellerId
     * @param array $data
     * @return int
     */
    public function sellerContractsTotal(int $sellerId, array $data = []): int
    {
        return $this->querySelectContracts(...func_get_args())->count('c.id');
    }

    /**
     * seller合约列表查询sql
     * @param int $sellerId
     * @param array $data
     * @return \Illuminate\Database\Concerns\BuildsQueries|\Illuminate\Database\Query\Builder|mixed
     */
    private function querySelectContracts(int $sellerId, array $data = [])
    {
        $contractNoFilter = $data['filter_contract_no'] ?? '';
        $codeOrMpnFilter = $data['filter_code_mpn'] ?? '';
        $deliveryDateBeginFilter = $data['delivery_date_begin'] ?? '';
        $deliveryDateEndFilter = $data['delivery_date_end'] ?? '';
        $statusFilter = $data['status'] ?? [];
        $statusFilter = is_array($statusFilter) ? $statusFilter : [$statusFilter];

        return $this->orm->table(DB_PREFIX . 'futures_contract as c')
            ->select(['c.*', 'p.sku', 'p.mpn', 'p.image', 'p.combo_flag'])
            ->join(DB_PREFIX . 'product as p', 'c.product_id', '=', 'p.product_id')
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', 'c.product_id', '=', 'c2p.product_id')
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'c2p.customer_id' => $sellerId,
                'p.product_type' => 0,
                'c.seller_id' => $sellerId,
                'c.is_deleted' => 0,
            ])
            ->when(!empty($contractNoFilter), function ($q) use ($contractNoFilter) {
                $q->where('c.contract_no', 'like', '%' . trim($contractNoFilter) . '%');
            })
            ->when(!empty($codeOrMpnFilter), function ($q) use ($codeOrMpnFilter) {
                $codeOrMpnFilter = trim($codeOrMpnFilter);
                $q->where(function ($query) use ($codeOrMpnFilter) {
                    $query->where('p.sku', 'like', '%' . $codeOrMpnFilter . '%')->orWhere('p.mpn', 'like', '%' . $codeOrMpnFilter . '%');
                });
            })
            ->when(!empty($deliveryDateBeginFilter), function ($q) use ($deliveryDateBeginFilter) {
                $q->where('c.delivery_date', '>=', $deliveryDateBeginFilter);
            })
            ->when(!empty($deliveryDateEndFilter), function ($q) use ($deliveryDateEndFilter) {
                $q->where('c.delivery_date', '<=', $deliveryDateEndFilter);
            })
            ->when(!empty($statusFilter), function ($q) use ($statusFilter) {
                $q->whereIn('c.status', $statusFilter);
            });
    }

    /**
     * 根据某些合约id获取最近一条合约支付比例变更申请记录
     * @param array $contractIds
     * @return array
     */
    public function lastMarginAppliesByContractIds(array $contractIds)
    {
        $applyIds = $this->orm->table(DB_PREFIX . 'futures_contract_margin_apply')
            ->whereIn('contract_id', $contractIds)
            ->groupBy(['contract_id'])
            ->selectRaw('max(id) as apply_id')
            ->pluck('apply_id')
            ->toArray();
        if (empty($applyIds)) {
            return [];
        }

        return $this->orm->table(DB_PREFIX . 'futures_contract_margin_apply')
            ->whereIn('id', $applyIds)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * 更新某些合约
     * @param int $sellerId
     * @param array $ids
     * @param array $data
     * @return int
     */
    public function batchUpdateContracts(int $sellerId, array $ids, array $data)
    {
        $data['update_time'] = date('Y-m-d H:i:s');

        return $this->orm->table(DB_PREFIX . 'futures_contract')
            ->where('seller_id', $sellerId)
            ->whereIn('id', $ids)
            ->update($data);
    }

    /**
     * 获取某一个方式某个seller的未完成的账单金额总和
     * @param int $customerId
     * @param int $type
     * @return int|mixed
     */
    public function getUnfinishedPayRecordAmount(int $customerId, int $type)
    {
        $expendAmount = $this->orm->table(DB_PREFIX . 'futures_contract_margin_pay_record')
            ->where([
                'customer_id' => $customerId,
                'bill_status' => 0,
                'type' => $type,
                'bill_type' => 1,
            ])
            ->sum('amount');
        $incomeAmount = $this->orm->table(DB_PREFIX . 'futures_contract_margin_pay_record')
            ->where([
                'customer_id' => $customerId,
                'bill_status' => 0,
                'type' => $type,
                'bill_type' => 2,
            ])
            ->sum('amount');

        return $expendAmount - $incomeAmount;
    }

    /**
     * 获取seller的所有未审核的合约
     * @param int $sellerId
     * @return float|int|mixed
     */
    public function getDisableContractUncheckApplyContracts(int $sellerId)
    {
        $precision = customer()->isJapan() ? 0 : 2;

        return $this->orm->table(DB_PREFIX . 'futures_contract as c')
            ->join(DB_PREFIX . 'futures_contract_margin_apply as cma', 'c.id', '=', 'cma.contract_id')
            ->where('c.seller_id', $sellerId)
            ->where('c.status', 2)
            ->where('cma.status', 0)
            ->where('c.is_deleted', 0)
            ->selectRaw("c.*, (c.num * round(c.payment_ratio * 0.01 * GREATEST(c.margin_unit_price, c.last_unit_price), {$precision})) as amount")
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * 根据seller和支付方式获取某些合约已付的保证金总和
     * @param array $contractIds
     * @param int $customerId
     * @param $type
     * @return int|mixed
     */
    public function totalExpandAmountByContractIds(array $contractIds, int $customerId, $type)
    {
        if (empty($contractIds)) {
            return 0;
        }

        $type = is_array($type) ? $type : (array)$type;

        return $this->orm->table(DB_PREFIX . 'futures_contract_margin_pay_record')
            ->whereIn('contract_id', $contractIds)
            ->where('customer_id', $customerId)
            ->where('bill_type', 1)
            ->whereIn('type', $type)
            ->sum('amount');
    }

    /**
     * 首次新建合约的保证金支付记录
     * @param int $sellerId
     * @param array $contractIds
     * @return array
     */
    public function firstPayRecordContracts(int $sellerId, array $contractIds)
    {
        return $this->orm->table(DB_PREFIX . 'futures_contract as c')
            ->leftJoin(DB_PREFIX. 'futures_contract_margin_pay_record as r', [['c.id', '=', 'r.contract_id'], ['c.seller_id', '=', 'r.customer_id']])
            ->where('c.seller_id', $sellerId)
            ->whereIn('c.id', $contractIds)
            ->groupBy(['c.id'])
            ->select(['c.*', 'r.type as pay_type'])
            ->get()
            ->map(function ($item) {
                // 第一笔合约支付是抵押物的也是使用应收款方式支付
                if ($item->pay_type == FuturesMarginPayRecordType::SELLER_COLLATERAL) {
                    $item->pay_type = FuturesMarginPayRecordType::SELLER_BILL;
                }
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * 某个合约最近一条申请变更比例记录
     * @param int $contractId
     * @return array
     */
    public function lastMarginApplyByContractId(int $contractId)
    {
        $res = $this->orm->table(DB_PREFIX . 'futures_contract_margin_apply')
            ->where('contract_id', $contractId)
            ->orderBy('id', 'desc')
            ->first();

        return obj2array($res);
    }

    /**
     * 某个合约某些状态下会占用的seller保证金
     * @param int $contractId
     * @param array $status
     * @param int $isBid
     * @param int $precision
     * @return int|mixed
     */
    public function agreementSellerUnitAmountByContractIdAndStatus(int $contractId, array $status, int $isBid = 0, $precision = 2)
    {
        return $this->orm->table(DB_PREFIX . 'futures_margin_agreement')
            ->where('contract_id', $contractId)
            ->whereIn('agreement_status', $status)
            ->where('is_bid', $isBid)
            ->selectRaw("round(unit_price * seller_payment_ratio * 0.01, " . $precision . ") * num as amount")
            ->get()
            ->sum('amount');
    }

    /**
     * 退还seller保证金
     * @param int $sellerId
     * @param array $contractIds
     * @param string $operator
     * @param string $execOperate
     * @param int $precision
     */
    public function refundSellerAvailableBalance(int $sellerId, array $contractIds, string $operator, string $execOperate = '', $precision = 2)
    {
        $firstPayRecordContracts = $this->firstPayRecordContracts($sellerId, $contractIds);

        $logs = [];
        $records = [];
        foreach ($firstPayRecordContracts as $firstPayRecordContract) {
            if ($firstPayRecordContract['status'] == 4 && $execOperate == 'terminate') {
                continue;
            }

            $backAmount = $firstPayRecordContract['available_balance'];
            $amount = 0;
            if ($execOperate == 'terminate') {
                //在Buyer支付协议头款期间 Seller设置合约终止，退的seller保证金，不包含待付头款的Seller保证金
                $amount = $this->agreementSellerUnitAmountByContractIdAndStatus($firstPayRecordContract['id'], [3], 1, $precision);
                $backAmount = $firstPayRecordContract['available_balance'] - $amount;
            }

            $collateralBalance = 0;
            if ($backAmount > 0 && !empty($firstPayRecordContract['pay_type'])) {
                // 在Buyer支付协议头款期间 Seller设置合约终止，退的seller保证金，不包含待付头款的Seller保证金

                if ($firstPayRecordContract['pay_type'] == 1) {
                    credit::insertCreditBill($sellerId, $backAmount, 2);
                }

                // 处理应收款中包含抵押物的情况
                if ($firstPayRecordContract['collateral_balance'] > 0 && $firstPayRecordContract['pay_type'] == FuturesMarginPayRecordType::SELLER_BILL) {
                    // 合约中剩余应收款的金额
                    $billAmount = $firstPayRecordContract['available_balance'] - $firstPayRecordContract['collateral_balance'];
                    // 实际可退应收款的金额
                    $backRealAmount = $backAmount > $billAmount ? $billAmount : $backAmount;
                    // 需要退的抵押物金额
                    $backCollateralBalance = $backAmount - $backRealAmount;
                    // 合约剩余抵押物金额
                    $collateralBalance = $firstPayRecordContract['collateral_balance'] - $backCollateralBalance;
                    // 应收款实际退还金额
                    $backAmount = $backRealAmount;
                    if ($backCollateralBalance > 0) {
                        $records[] = [
                            'contract_id' => $firstPayRecordContract['id'],
                            'customer_id' => $sellerId,
                            'type' => FuturesMarginPayRecordType::SELLER_COLLATERAL,
                            'amount' => $backCollateralBalance,
                            'bill_type' => FuturesMarginPayRecordBillType::INCOME,
                            'bill_status' => YesNoEnum::YES,
                            'create_time' => date('Y-m-d H:i:s'),
                            'update_time' => date('Y-m-d H:i:s'),
                            'operator' => $operator,
                        ];
                    }
                }

                if ($backAmount > 0) {
                    $records[] = [
                        'contract_id' => $firstPayRecordContract['id'],
                        'customer_id' => $sellerId,
                        'type' => $firstPayRecordContract['pay_type'],
                        'amount' => $backAmount,
                        'bill_type' => 2,
                        'bill_status' => $firstPayRecordContract['pay_type'] == 1 ? 1 : 0,
                        'create_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                        'operator' => $operator,
                    ];
                }
            }

            if ($execOperate == 'terminate') {
                $logs[] = [
                    'contract_id' => $firstPayRecordContract['id'],
                    'customer_id' => $sellerId,
                    'type' => 3,
                    'content' => $this->formatContractLogContent(['status' => 4], $firstPayRecordContract),
                    'operator' => $operator,
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ];

                $this->batchUpdateContracts($sellerId, [$firstPayRecordContract['id']], ['status' => 4, 'available_balance' => $amount, 'collateral_balance' => $collateralBalance]);
            }
        }

        if (!empty($records)) {
            $this->orm->table(DB_PREFIX . 'futures_contract_margin_pay_record')->insert($records);
        }

        if (!empty($logs)) {
            $this->orm->table('oc_futures_contract_log')->insert($logs);
        }
    }

    public function getContractsByProductId($product_id,$is_bid=0)
    {
        $res = $this->orm->table('oc_futures_contract')
            ->where([
                'product_id' => $product_id,
                'status' => 1,
                'is_deleted' => 0
            ])
            ->when($is_bid, function ($query) {
                $query->where('is_bid', 1);
            })
            ->orderBy('delivery_date','asc')
            ->get();

        //region #31737 免税buyer 价格显示修改
        if ($res = obj2array($res)) {
            foreach ($res as $index => $item) {
                $res[$index]['margin_unit_price'] = app(ProductPriceRepository::class)
                    ->getProductActualPriceByBuyer($item['seller_id'], customer()->getModel(), $item['margin_unit_price']);
                $res[$index]['last_unit_price'] = app(ProductPriceRepository::class)
                    ->getProductActualPriceByBuyer($item['seller_id'], customer()->getModel(), $item['last_unit_price']);
            }
        }
        //endregion
        return $res;
    }

    /**
     * @param int $contractId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function contractLogs(int $contractId, int $page = 1, int $perPage = 4)
    {
        return $this->orm->table('oc_futures_contract_log')
            ->where('contract_id', $contractId)
            ->orderBy('id', 'desc')
            ->forPage($page, $perPage)
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * @param int $customerId
     * @return bool
     */
    public function existFuturesQuestionnaire(int $customerId)
    {
        return $this->orm->table(DB_PREFIX . 'futures_questionnaire')
            ->where('customer_id', $customerId)
            ->exists();
    }

    /**
     * @param int $customerId
     * @return bool
     */
    public function saveFuturesQuestionnaire(int $customerId)
    {
        return $this->orm->table(DB_PREFIX . 'futures_questionnaire')
            ->insert(['customer_id' => $customerId]);
    }

    /**
     * @param int $contractId
     * @param array $deliveryStatus
     * @param array $selectParams
     * @return array
     */
    public function agreements(int $contractId, array $deliveryStatus = [], array $selectParams = []): array
    {
        $ret = $this->queryAgreements(...func_get_args());
        if (isset($selectParams['page']) && isset($selectParams['page_limit'])) {
            $ret->forPage(($selectParams['page']), ($selectParams['page_limit']));
        }

        return $ret->get()->toArray();
    }

    /**
     * @param int $contractId
     * @param array $deliveryStatus
     * @param array $selectParams
     * @return int|mixed
     */
    public function agreementQuantityStat(int $contractId, array $deliveryStatus = [], array $selectParams = [])
    {
        return $this->queryAgreements(...func_get_args())->sum('ma.num');
    }

    /**
     * @param int $contractId
     * @param array $deliveryStatus
     * @param array $selectParams
     * @return int
     */
    public function agreementsTotal(int $contractId, array $deliveryStatus = [], array $selectParams = []): int
    {
        return $this->queryAgreements(...func_get_args())->count('ma.id');
    }

    /**
     * @param int $contractId
     * @param array $deliveryStatus
     * @param array $selectParams
     * @return \Illuminate\Database\Concerns\BuildsQueries|\Illuminate\Database\Query\Builder|mixed
     */
    private function queryAgreements(int $contractId, array $deliveryStatus = [], array $selectParams = [])
    {
        $agreementIds = $selectParams['agreement_ids'] ?? [];

        return $this->orm->table('oc_futures_margin_agreement as ma')
            ->join('oc_futures_margin_delivery as md', 'ma.id', '=', 'md.agreement_id')
            ->join('oc_customer as c', 'ma.buyer_id', '=', 'c.customer_id')
            ->leftJoin('oc_futures_agreement_margin_pay_record as mpr', function ($q) {
                $q->on('ma.id', '=', 'mpr.agreement_id')->on('ma.seller_id', '=', 'mpr.customer_id')
                    ->where('mpr.flow_type', 2)
                    ->where('mpr.bill_type', 2)
                    ->where(function ($query) {
                        $query->where('mpr.type', 1)
                            ->orwhere([['mpr.type', '=', 3], ['mpr.amount', '=', 0]])
                            ->orwhere([['mpr.type', '=', 3], ['mpr.bill_status', '=', 1]], ['mpr.amount', '!=', 0]);
                    });
            })
            ->leftJoin('oc_futures_agreement_apply as a', function ($q) {
                $q->on('ma.id', '=', 'a.agreement_id')
                    ->where('a.status', 0)
                    ->where('a.apply_type', '!=', FuturesMarginApplyType::APPEAL)
                    ->where('a.apply_type', '!=', 5);
            })
            ->where('ma.contract_id', $contractId)
            ->where('ma.agreement_status', 7)
            ->when(!empty($deliveryStatus), function ($q) use ($deliveryStatus) {
                $q->whereIn('md.delivery_status', $deliveryStatus);
            })
            ->when(!empty($agreementIds), function ($q) use ($agreementIds) {
                $q->whereIn('ma.id', $agreementIds);
            })
            ->select(['c.nickname', 'c.user_number', 'c.customer_group_id', 'ma.*', 'md.*', 'ma.id', 'md.id as delivery_id', 'mpr.id as pay_record_id', 'mpr.update_time as pay_record_update_time', 'a.id as agreement_apply_id']);
    }

    /**
     * 超时交付的所有合约协议
     * @return \Illuminate\Support\Collection
     */
    public function deliveryTimeoutAllContractAgreements()
    {
        return $this->orm->table('oc_futures_margin_agreement as ma')
            ->select(['ma.*', 'md.*', 'ma.id', 'md.id as delivery_id', 'cub.country_id as buyer_country_id', 'cub.nickname', 'cub.user_number', 'p.sku', 'p.mpn', 'ctc.screenname', 'cus.country_id as seller_country_id', 'c.delivery_date', 'cus.accounting_type'])
            ->join('oc_futures_contract as c', function ($q) {
                $q->on('c.id', '=', 'ma.contract_id')->where('c.delivery_date', '<', date('Y-m-d H:i:s', strtotime('1 day')));
            })
            ->join('oc_futures_margin_delivery as md', function ($q) {
                $q->on('md.agreement_id', '=', 'ma.id')->where('md.delivery_status', 1);
            })
            ->leftJoin('oc_customer as cub', 'ma.buyer_id', '=', 'cub.customer_id')
            ->leftJoin('oc_customer as cus', 'ma.seller_id', '=', 'cus.customer_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ma.seller_id', '=', 'ctc.customer_id')
            ->leftJoin('oc_product as p','ma.product_id' ,'=', 'p.product_id')
            ->where('ma.agreement_status', 7)
            ->orderBy('ma.contract_id')
            ->orderBy('ma.update_time')
            ->get();
    }
}
