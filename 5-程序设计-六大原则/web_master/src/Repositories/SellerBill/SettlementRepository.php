<?php

namespace App\Repositories\SellerBill;

use App\Enums\Common\YesNoEnum;
use App\Enums\SellerBill\SellerAccountInfoAccountType;
use App\Enums\SellerBill\SellerBillDetailFrozenFlag;
use App\Enums\SellerBill\SellerBillSettlementStatus;
use App\Enums\SellerBill\SellerBillTypeCode;
use App\Enums\SellerBill\SettlementStatusAndSettleType;
use App\Enums\SellerBill\SellerBillSettleType;
use App\Models\File\FileUploadMenu;
use App\Models\SellerBill\SellerAccountInfo;
use App\Models\SellerBill\SellerBill;
use App\Models\SellerBill\SellerBillBuyerStorage;
use App\Models\SellerBill\SellerBillDetail;
use App\Models\SellerBill\SellerBillFile;
use App\Models\SellerBill\SellerBillFrozenRelease;
use App\Models\SellerBill\SellerBillTotal;
use App\Models\SellerBill\SellerBillType;
use App\Repositories\SellerBill\DealSettlement\DealSettlement;
use Carbon\Carbon;
use Framework\DataProvider\Paginator;
use Framework\Model\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class SettlementRepository
{
    const RESERVE_CHECK_CODE = ['reserve_check', 'reserve_interest_check', 'v3_reserve_check']; // 供应商支付平台欠款/平台支付供应商应收款

    /**
     * 获取Seller的所有结算账单周期
     *
     * @param int $customerId SellerID
     * @return SellerBill[]|Collection
     */
    public function getAllSettlementCycle($customerId)
    {
        return  SellerBill::where('seller_id', $customerId)
            ->select('id', 'start_date', 'end_date', 'settle_type', 'settlement_status', 'program_code', 'serial_number')
            ->orderBy('end_date', 'desc')
            ->get()
            ->map(function ($item) {
                return $item->setAttribute('date_format', $this->formatSettlementDate($item->start_date, $item->end_date));
            });
    }

    /**
     * 获取结算单列表
     *
     * @param int $customerId 用户ID
     * @param int $cycleId 结算单ID
     * @param string $cycleNo 结算单编号
     * @param string $cycleStatus 结算单状态（1,2,3）
     * @param boolean $isOnsiteSeller 是否Onsite Seller
     * @param int $page 分页（默认为1）
     * @param int $pageSize 分页大小（默认为20）
     * @return SellerBill[]|Collection
     */
    public function getSettlementList($customerId, $cycleId, $cycleNo, $cycleStatus, $isOnsiteSeller = false, $page = 1, $pageSize = 20)
    {
        return $this->getSettlementBuilder($customerId, $cycleId, $cycleNo, $cycleStatus)
            ->leftJoin('tb_seller_bill_total as bt', function ($q) {
                return $q->on('b.id', '=', 'bt.header_id')
                    ->whereIn('bt.code', self::RESERVE_CHECK_CODE);
            })
            ->when($isOnsiteSeller, function ($q) {
                $q->with(['frozen' => function ($qq) {
                    $qq->selectRaw('coalesce(sum(frozen_amount),0) as last_frozen,bill_id')->groupBy('bill_id');
                }]);
            })
            ->select('b.id', 'b.serial_number', 'b.start_date', 'b.end_date', 'b.settle_type', 'b.settlement_status', 'b.confirm_date', 'b.total', 'b.actual_settlement', 'b.program_code', 'b.frozen_total')
            ->selectRaw('sum(bt.value) as check_total')
            ->groupBy('b.id')
            ->orderBy('end_date', 'desc')
            ->forpage($page, $pageSize)
            ->get();
    }

    /**
     * 获取结算单总数
     *
     * @param int $customerId 用户ID
     * @param int $cycleId 结算单ID
     * @param string $cycleNo 结算单编号
     * @param string $cycleStatus 结算单状态（1,2,3）
     * @return int
     */
    public function getSettlementCount($customerId, $cycleId, $cycleNo, $cycleStatus)
    {
        return $this->getSettlementBuilder($customerId, $cycleId, $cycleNo, $cycleStatus)
            ->count();
    }

    /**
     * 获取对应的下一个结算单
     *
     * @param int $customerId 用户ID
     * @param string $startDate 上一个结算单的开始时间
     * @return SellerBill|null
     */
    public function getSettlementInfo($customerId, $startDate)
    {
        return SellerBill::query()->alias('b')
            ->leftJoin('tb_seller_bill_total as bt', function ($q) {
                return $q->on('b.id', '=', 'bt.header_id')
                    ->whereIn('bt.code', self::RESERVE_CHECK_CODE);
            })
            ->select('b.id', 'b.start_date', 'b.end_date', 'b.settle_type', 'b.settlement_status', 'b.confirm_date', 'b.total', 'b.actual_settlement', 'b.program_code')
            ->selectRaw('sum(bt.value) as check_total')
            ->where('b.seller_id', $customerId)
            ->where('b.start_date', '>', $startDate)
            ->groupBy('b.id')
            ->orderBy('b.start_date')
            ->first();
    }

    /**
     * 获取查询结算单Builder
     *
     * @param int $customerId 用户ID
     * @param int $cycleId 结算单ID
     * @param string $cycleNo 结算单编号
     * @param string $cycleStatus 结算单状态（1,2,3）
     * @return SellerBill|Builder
     */
    private function getSettlementBuilder($customerId, $cycleId, $cycleNo, $cycleStatus)
    {
        return  SellerBill::query()->alias('b')
            ->where('b.seller_id', $customerId)
            ->when($cycleId, function (Builder $q) use ($cycleId) {
                return $q->where('b.id', $cycleId);
            })
            ->when($cycleNo !== '', function (Builder $q) use ($cycleNo) {
                return $q->whereRaw('instr(b.serial_number, ?)', [$cycleNo]);
            })
            ->when($cycleStatus !== '', function (Builder $q) use ($cycleStatus) {
                $statusList = explode(',', $cycleStatus);
                return $q->where(function (Builder $nQ) use ($statusList) {
                    foreach ($statusList as $key => $value) {
                        if (in_array($value, SettlementStatusAndSettleType::getValues())) {
                            switch ($value) {
                                case SettlementStatusAndSettleType::GOING:
                                    $nQ->orWhere('b.settlement_status', SellerBillSettlementStatus::GOING);
                                    break;
                                case SettlementStatusAndSettleType::IN_THE_SETTLEMENT:
                                    $nQ->orWhere('b.settlement_status', SellerBillSettlementStatus::IN_THE_SETTLEMENT);
                                    break;
                                case SettlementStatusAndSettleType::ALREADY_SETTLED_DIRECT:
                                    $nQ->orWhere(function (Builder $nQ) {
                                        return $nQ->where('b.settlement_status', SellerBillSettlementStatus::ALREADY_SETTLED)
                                            ->where('b.settle_type', SellerBillSettleType::DIRECT_SETTLEMENT);
                                    });
                                    break;
                                case SettlementStatusAndSettleType::ALREADY_SETTLED_SWITCH:
                                    $nQ->orWhere(function (Builder $nQ) {
                                        return $nQ->where('b.settlement_status', SellerBillSettlementStatus::ALREADY_SETTLED)
                                            ->where('b.settle_type', SellerBillSettleType::SWITCH_BALANCE);
                                    });
                                    break;
                            }
                        }
                    }
                    return $nQ;
                });
            });
    }

    /**
     * 获取结算单Total
     *
     * @param int $customerId 用户ID
     * @param string $startDate 结算周期开始时间（Y-m-d H:i:s）
     * @param boolean $isOnsiteSeller 是否Onsite Seller
     * @return array
     */
    public function getBillTotalList($customerId, $startDate, $isOnsiteSeller = false)
    {
        $billList = SellerBill::query()->alias('b')
            ->select('b.*')
            ->when($isOnsiteSeller, function ($q) {
                $q->leftJoin('tb_seller_bill_frozen as bf', 'bf.bill_id', '=', 'b.id')
                    ->selectRaw('coalesce(sum(bf.frozen_amount),0) as last_frozen')
                    ->groupBy('b.id');
            })
            ->where('b.seller_id', $customerId)
            ->where('b.start_date', '>=', $startDate)
            ->orderBy('b.end_date', 'desc')
            ->get()
            ->toArray();
        if (! $billList) {
            return [[], []];
        }
        $totalList = SellerBillTotal::whereIn('header_id', array_column($billList, 'id'))
            ->get()
            ->toArray();

        return [$billList, $totalList];
    }

    /**
     * 获取结算单基本信息
     *
     * @param int $id 结算单ID
     * @param int $sellerId 用户ID
     * @param boolean $isOnsiteSeller 是否Onsite Seller
     * @return SellerBill|array
     */
    public function getBillInfo($id, $sellerId, $isOnsiteSeller = false)
    {
        $billInfo = SellerBill::query()->alias('b')
            ->leftJoin('oc_customer as c', 'b.seller_id', '=', 'c.customer_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'c.customer_id', '=', 'ctc.customer_id')
            ->select('b.*', 'c.user_number', 'c.firstname', 'c.lastname', 'c.email', 'ctc.screenname', 'c.logistics_customer_name')
            ->when($isOnsiteSeller, function ($q) {
                $q->leftJoin('tb_seller_bill_frozen as bf', 'b.id', '=', 'bf.bill_id')
                    ->selectRaw('coalesce(sum(bf.frozen_amount),0) as last_frozen')
                    ->groupBy('b.id');
            })
            ->where('b.id', $id)
            ->where('b.seller_id', $sellerId)
            ->first();
        if (! $billInfo) {
            return [];
        }
        $accountInfo = '';
        if ($billInfo->settlement_status == SellerBillSettlementStatus::ALREADY_SETTLED) { // 已结算取结算但是的关联收款账户信息
            if ($billInfo->seller_account_id) { // 绑定了结算账户
                $accountInfo = SellerAccountInfo::where('id', $billInfo->seller_account_id)->first();
            }
        } else { // 正在进行 && 结算中 取正在启用的收款账户信息
            $accountInfo = SellerAccountInfo::where('seller_id', $billInfo->seller_id)
                ->where('is_deleted', YesNoEnum::NO)
                ->where('status', YesNoEnum::YES)
                ->first();
        }
        if ($accountInfo) {
            $billInfo->setAttribute('account_type', $accountInfo->account_type);
            $billInfo->setAttribute('company', $accountInfo->company);
            $billInfo->setAttribute('bank_account', $accountInfo->account_type == SellerAccountInfoAccountType::P_CARD ? $accountInfo->p_email : $accountInfo->bank_account);
        }

        return $billInfo;
    }

    /**
     * 格式化结算周期
     *
     * @param string|Carbon $startDate 开始时间
     * @param string|Carbon $endDate 结束时间
     * @return string
     */
    public function formatSettlementDate($startDate, $endDate)
    {
        if (! is_object($startDate)) {
            $startDate = Carbon::createFromFormat('Y-m-d H:i:s', $startDate);
        }
        if (! is_object($endDate)) {
            $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $endDate);
        }
        return $startDate . '(' .$startDate->format('T') . ') - ' . $endDate . '(' . $endDate->format('T') . ')';
    }

    /**
     * 获取查询Builder
     *
     * @param int $customerId SellerId
     * @param array $params 筛选参数
     *  [
     *      'typeId' => int 结算类目一级ID,
     *      'billId' => int 结算单ID
     *      'start_date' => string 筛选开始时间,
     *      'end_date' => string 筛选结束时间,
     *      'costNo' => string 费用单号
     *      'frozen_status' => int 是否冻结中 1是 2否
     *      'sort' => string 排序(asc|desc)
     *      'page' => int 分页
     *      'pageSize' => int 分页大小
     *  ]
     *
     * @return Builder
     */
    private function getSettlementDetailBuilder($customerId, $params)
    {
        $billType = SellerBillType::select(['type_id', 'code'])->get()->toArray();
        $billTypeArr = array_column($billType, 'type_id', 'code');
        $futureContractTypeId = isset($billTypeArr[SellerBillTypeCode::V3_COMPLEX_FUTURE]) ? $billTypeArr[SellerBillTypeCode::V3_COMPLEX_FUTURE] : 0; // 期货保证金交易支出类型ID

        $subQuery = SellerBillType::where('parent_type_id', $params['typeId'])
            ->select('type_id');
        return SellerBillDetail::query()->alias('sbd')
            ->leftJoin('oc_order as o', 'sbd.order_id', 'o.order_id')
            ->leftJoin('oc_product_quote as pq', function (JoinClause $q) {
                $q->on('sbd.order_id', '=', 'pq.order_id')
                ->on('sbd.product_id', '=', 'pq.product_id');
            })
            ->leftJoin('oc_rebate_agreement as ra', 'sbd.rebate_id', 'ra.id')
            ->leftJoin('oc_yzc_rma_order as ro', 'sbd.rma_id', 'ro.id')
            ->leftJoin('tb_special_service_fee as ssf', 'sbd.special_id', 'ssf.id')
            ->leftJoin('oc_futures_contract as fc', 'sbd.future_contract_id', 'fc.id')
            ->selectRaw('sbd.*,o.delivery_type,pq.agreement_no as quote_no,ra.agreement_code as rebate_no,ro.rma_order_id as rma_no,ssf.fee_number,ssf.charge_detail,ssf.remark as special_remark,
            ssf.annexl_menu_id,ssf.service_project,ssf.inventory_id,fc.contract_no,if(sbd.frozen_flag = '. SellerBillDetailFrozenFlag::UNFROZEN .' and sbd.frozen_date is not null, sbd.frozen_date, sbd.produce_date) as show_date')
            ->when(customer()->isGigaOnsiteSeller(), function ($q) use ($params) {
                $q->leftJoin('tb_seller_bill_frozen as sbf', 'sbd.id', 'sbf.bill_detail_id')
                    ->addSelect(['sbf.frozen_amount as surplus_frozen_amount']);
            })
            ->where('sbd.seller_id', $customerId)
            ->where('sbd.header_id', $params['billId'])
            ->whereIn('sbd.bill_type_id', $subQuery)
            ->when($params['costNo'] != '', function ($q) use ($params, $futureContractTypeId) {
                $costNo = '%' . $params['costNo'] . '%';
                return $q->where(function ($q) use ($costNo, $futureContractTypeId) {
                    return $q->where('sbd.order_id', 'like', $costNo)
                        ->orWhere('pq.agreement_no', 'like', $costNo)
                        ->orWhere('ra.agreement_code', 'like', $costNo)
                        ->orWhere('ro.rma_order_id', 'like', $costNo)
                        ->orWhere('ssf.fee_number', 'like', $costNo)
                        ->orWhere('sbd.agreement_id', 'like', $costNo)
                        ->orWhere('sbd.future_agreement_no', 'like', $costNo)
                        ->orWhere('sbd.campaign_id', 'like', $costNo)
                        ->orWhere(function ($qr) use ($costNo, $futureContractTypeId) {
                            return $qr->whereNotNull('sbd.future_contract_id')
                                ->where('sbd.type', $futureContractTypeId)
                                ->where('fc.contract_no', 'like', $costNo);
                        });
                });
            })
            ->when($params['start_date'] != '', function ($q) use ($params) {
                return $q->where('produce_date', '>=', $params['start_date']);
            })
            ->when($params['end_date'] != '', function ($q) use ($params) {
                return $q->where('produce_date', '<=', $params['end_date']);
            })
            ->when($params['frozen_status'] != '', function ($q) use ($params) {
                if ($params['frozen_status'] == SellerBillDetailFrozenFlag::FROZEN) {
                    return $q->where('frozen_flag', SellerBillDetailFrozenFlag::FROZEN);
                } else {
                    return $q->whereIn('frozen_flag', [SellerBillDetailFrozenFlag::NOT_NEED_FROZEN, SellerBillDetailFrozenFlag::UNFROZEN]);
                }
            });
    }

    /**
     * 获取结算单明细里列表
     *
     * @param int $customerId SellerId
     * @param array $params 筛选参数
     *  [
     *      'typeId' => int 结算类目一级ID,
     *      'billId' => int 结算单ID
     *      'start_date' => string 筛选开始时间,
     *      'end_date' => string 筛选结束时间,
     *      'costNo' => string 费用单号
     *      'frozen_status' => int 是否冻结中 1.是 2.否
     *      'sort' => string 排序(asc|desc)
     *      'page' => int 分页
     *      'pageSize' => int 分页大小
     *  ]
     *
     * @return array
     */
    public function getSettlementDetailList($customerId, $params)
    {
        $data = $this->getSettlementDetailBuilder($customerId, $params)
            ->orderBy('show_date', $params['sort'])
            ->forPage($params['page'], $params['pageSize'])
            ->get();

        return app(DealSettlement::class)->formatData($data, $params['typeId'], $params['billId']);
    }

    /**
     * 获取结算单明细总数
     *
     * @param int $customerId SellerId
     * @param array $params 筛选参数
     *  [
     *      'typeId' => int 结算类目一级ID,
     *      'billId' => int 结算单ID
     *      'start_date' => string 筛选开始时间,
     *      'end_date' => string 筛选结束时间,
     *      'costNo' => string 费用单号
     *      'frozen_status' => int 是否冻结中 1.是 2.否
     *      'sort' => string 排序(asc|desc)
     *      'page' => int 分页
     *      'pageSize' => int 分页大小
     *  ]
     *
     * @return int
     */
    public function getSettlementDetailTotal($customerId, $params)
    {
        $builder = $this->getSettlementDetailBuilder($customerId, $params);
        $data = $builder->count();

        return $data;
    }

    /**
     * 通过分类IDS获取对应类目总金额
     *
     * @param int $customerId 用户ID
     * @param int $billId 结算单ID
     * @param array $typeIds 分类ID
     * @return SellerBillDetail[]|Collection
     */
    public function getDetailInfoByCode($customerId, $billId, $typeIds)
    {
        return SellerBillDetail::where('header_id', $billId)
            ->where('seller_id', $customerId)
            ->whereIn('type', $typeIds)
            ->groupBy(['type'])
            ->select(['header_id', 'bill_type_id', 'type', 'total'])
            ->selectRaw('sum(total) as total')
            ->get();
    }

    /**
     * 获取单个结算单明细对应文件列表
     *
     * @param int $customerId 用户ID
     * @param int $detailId 结算单明细ID
     * @return array [SellerBillDetail $billDetail, array $fileList]
     */
    public function getDetailFileList($customerId, $detailId)
    {
        $billDetail = SellerBillDetail::query()->alias('sbd')
            ->leftJoin('tb_seller_bill as sb', 'sbd.header_id', 'sb.id')
            ->leftJoin('tb_seller_bill_type as sbt', 'sbd.type', 'sbt.type_id')
            ->leftJoin('tb_special_service_fee as ssf', 'sbd.special_id', 'ssf.id')
            ->select('sbt.code', 'sb.settlement_status', 'sb.start_date', 'sb.end_date', 'sbd.file_menu_id', 'sb.id as bill_id', 'ssf.inventory_id', 'ssf.annexl_menu_id')
            ->where('sbd.seller_id', $customerId)
            ->where('sbd.id', $detailId)
            ->first();
        $fileList = [];
        if ($billDetail) {
            $fileList = app(DealSettlement::class)->getDetailFiles($billDetail->getAttribute('inventory_id'), $billDetail->getAttribute('annexl_menu_id'), $billDetail->file_menu_id);
        }

        return [$billDetail, $fileList];
    }

    /**
     * 获取文件列表
     *
     * @param  array $fileMenuIds tb_file_upload_menu.id数组
     * @return FileUploadMenu[]|Collection
     */
    public function getFileList($fileMenuIds)
    {
        return FileUploadMenu::query()->alias('fum')
            ->leftJoinRelations('details as fud')
            ->select('fud.id', 'fud.file_name', 'fud.file_path')
            ->whereIn('fum.id', $fileMenuIds)
            ->where('fum.status', YesNoEnum::YES)
            ->where('fud.file_status', YesNoEnum::NO)
            ->where('fud.delete_flag', YesNoEnum::NO)
            ->get();
    }

    /**
     * 获取冻结对应的解冻列表
     *
     * @param int $sellerId SellerId
     * @param int $billDetailId 账单明细ID
     * @param Paginator $paginator 分页器
     * @return array
     */
    public function getFrozenList(int $sellerId, int $billDetailId, Paginator $paginator)
    {
        $list = SellerBillFrozenRelease::query()->alias('fr')
            ->leftJoinRelations('orderSettlerType as st')
            ->where('fr.seller_id', $sellerId)
            ->where('fr.frozen_detail_id', $billDetailId)
            ->orderBy('fr.release_time', 'desc')
            ->select(['fr.*', 'st.cn_name', 'st.en_name'])
            ->offset($paginator->getOffset())
            ->limit($paginator->getLimit())
            ->get();

        return app(DealSettlement::class)->formatFrozenList($list);
    }

    /**
     * 获取Onsite Seller的仓租费列表
     *
     * @param int $billId 结算单ID
     * @return SellerBillBuyerStorage[]|Collection
     */
    public function getOnsiteSellerStorageList($billId)
    {
        return SellerBillBuyerStorage::query()->alias('sbbs')
            ->leftJoinRelations('customerToBuyer as c')
            ->select(['sbbs.*', 'c.nickname', 'c.user_number'])
            ->where('bill_id', $billId)
            ->get();
    }
}
