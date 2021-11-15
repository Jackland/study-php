<?php

namespace App\Repositories\Margin;


use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Margin\MarginAgreementPayRecordBillType;
use App\Enums\Margin\MarginAgreementPayRecordType;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Order\OcOrderTypeId;
use App\Enums\Product\ProductType;
use App\Enums\Product\ProductTransactionType;
use App\Models\Futures\FuturesMarginDelivery;
use App\Models\Margin\MarginAgreement;
use App\Models\Margin\MarginAgreementPayRecord;
use App\Models\Margin\MarginMessage;
use App\Models\Margin\MarginPerformerApply;
use App\Models\Margin\MarginProcess;
use App\Models\Order\Order;
use App\Repositories\Futures\ContractRepository;
use Carbon\Carbon;
use Framework\Model\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use App\Models\Order\OrderProduct;
use App\Models\Margin\MarginAgreementStatus as MarginAgreementStatusModel;
use App\Models\Margin\MarginTemplate;

class MarginRepository
{
    use RequestCachedDataTrait;
    /**
     * 返还协议详情信息
     *
     * @param int $id tb_sys_margin_agreement.id
     * @param int $sellerId tb_sys_margin_agreement.seller_id
     * @return array
     */
    public function getMarginAgreementInfo(int $id, $sellerId = 0)
    {
        $result = MarginAgreement::query()
            ->alias('ma')
            ->leftJoinRelations(['product as p', 'buyer as b', 'seller as c', 'marginStatus as mas', 'process as mp'])
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'c.customer_id')
            ->leftJoin('tb_sys_margin_order_relation AS mor', 'mor.margin_process_id', '=', 'mp.id')
            ->leftJoin('tb_sys_margin_performer_apply AS mpa', function (JoinClause $join) {
                $join->on('mpa.agreement_id', '=', 'ma.id')
                    ->whereIn('mpa.check_result', [0, 1]);
            })
            ->leftJoin('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'mp.advance_product_id')
            ->where('ma.id', '=', $id)
            ->when($sellerId > 0, function(Builder $q) use($sellerId) {
                $q->where('ma.seller_id', '=', $sellerId);
            })
            ->selectRaw('ma.`id`,
                          ma.`agreement_id`,
                          ma.`seller_id`,
                          ma.buyer_id as agreement_buyer_id,
                          ctc.screenname AS seller_name,
                          ctc.customer_id as seller_store_id,
                          ma.`day`,
                          ma.`num`,
                          ma.`price` AS unit_price,
                          ma.`money` AS sum_price,
                          ma.`create_time` AS applied_time,
                          ma.`update_time` AS update_time,
                          ma.`status`,
                          ma.`effect_time`,
                          ma.`expire_time`,
                          ma.`program_code` as agreement_program_code,
                          ma.is_bid,
                          ma.payment_ratio,
                          ma.deposit_per,
                          ma.discount,
                          mas.`name` AS status_name,
                          mas.`color` AS status_color,
                          GROUP_CONCAT(mor.rest_order_id) AS rest_order_ids,
                          p.`product_id`,
                          p.`sku`,
                          p.`mpn`,
                          p.`quantity` AS available_qty,
                          p.image as product_image,
                          mp.advance_product_id,
                          mp.advance_order_id,
                          mp.rest_product_id,
                          IFNULL(SUM(mor.purchase_quantity),0) AS sum_purchase_qty,
                          COUNT(mpa.id) AS count_performer,
                          b.`customer_id` AS buyer_id,
                          b.`nickname`,
                          b.`user_number`,
                          b.`customer_group_id`,
                          c.`country_id`,
                          c2p.customer_id AS advance_seller_id
                          ')
            ->groupBy('ma.id')
            ->first();
        if ($result) {
            return $result->toArray();
        } else {
            return [];
        }
    }

    /**
     * 校验一个现货协议是否是期货转现货协议
     * @param int $marginId
     * @return bool
     */
    public function checkMarginIsFuture2Margin(int $marginId): bool
    {
        return FuturesMarginDelivery::query()->where('margin_agreement_id', $marginId)->exists();
    }

    /**
     * 校验一个现货协议是否是期货转现货协议，并返回期货协议ID
     * @param int $marginId
     * @return integer|null
     */
    public function checkMarginIsFuture2MarginWithReturn(int $marginId)
    {
        return FuturesMarginDelivery::query()->where('margin_agreement_id', $marginId)->value('agreement_id');
    }

    /**
     * [getMarginCheckDetail description] 根据协议ID获取保证金缴纳证明信息
     * @param array $marginIdList
     * @return array
     */
    public function getMarginCheckDetail(array $marginIdList): array
    {
        return MarginProcess::query()
            ->alias('mp')
            ->joinRelations(['relateOrders as mor'])
            ->join('oc_order as o','o.order_id','=','mor.rest_order_id')
            ->join('oc_order_product as op',[['op.order_id','=','o.order_id'],['mp.rest_product_id','=','op.product_id']])
            ->leftJoin('oc_product as p','p.product_id','=','op.product_id')
            ->leftJoin('oc_product_description as pd','p.product_id','=','pd.product_id')
            ->leftJoin('oc_customer as buyer','buyer.customer_id','=','o.customer_id')
            ->leftJoin('oc_customerpartner_to_product as ctp','ctp.product_id','=','op.product_id')
            ->whereIn('mp.margin_id',$marginIdList)
            ->selectRaw(
                'mp.`margin_agreement_id` AS agreement_id,
                ctp.customer_id AS seller_id,
                buyer.`customer_id` AS buyer_id,
                buyer.`nickname` AS buyer_nickname,
                buyer.`user_number` AS buyer_user_number,
                buyer.`customer_group_id`,
                p.`sku`,
                p.`mpn`,
                pd.`name` AS product_name,
                mor.`rest_order_id`,
                o.`total` AS op_total,
                o.`order_status_id`,
                o.`date_added` AS purchase_date,
                o.delivery_type,
                op.`price` AS unit_price,
                op.service_fee_per,
                op.`order_product_id`,
                op.`product_id`,
                op.`quantity`,
                op.`tax`,
                op.freight_per,
                op.package_fee,
                ifnull(op.freight_per,0)+ifnull(op.package_fee,0) as freight_per_unit,
                round(op.price,2)*op.quantity c2oprice'
            )
            ->get()
            ->toArray();
    }

    /**
     * 获取保证金订单的合伙人申请
     * @version 期货保证金四期
     * @param int $agreementId
     * @param bool $isFail 是否获取失败的
     * @return \App\Models\Margin\MarginOrderRelation|Builder|\Illuminate\Database\Query\Builder|mixed
     * @see ModelAccountProductQuotesMarginContract::getPerformerApply()
     */
    public function getPerformerApply($agreementId, $isFail = false)
    {
        //同一时间只可能有一个审核请求，所以就不做其他考虑了
        return MarginPerformerApply::query()->alias('mpa')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'mpa.performer_buyer_id')
            ->where('mpa.agreement_id', '=', $agreementId)
            ->when($isFail, function (Builder $q) {
                $q->whereIn('mpa.check_result', [0, 1, 2])
                    ->whereIn('mpa.seller_approval_status', [0, 1, 2]);
            })
            ->when(!$isFail, function (Builder $q) {
                $q->whereIn('mpa.check_result', [0, 1])//平台未审核和已审核过的
                ->whereIn('mpa.seller_approval_status', [0, 1]);//seller审核和审核通过的
            })
            ->orderBy('id', 'desc')
            ->selectRaw('
            mpa.id
            ,mpa.performer_buyer_id
            ,mpa.check_result
            ,mpa.seller_approval_status
            ,mpa.reason
            ,mpa.seller_check_reason
            ,mpa.program_code
            ,c.nickname
            ,c.user_number
            ,c.customer_group_id
            ')->first();
    }

    /**
     * 获取buyer和seller针对某个协议的留言，各一条  现货四期
     * @param int $agreementId
     * @param int $isBid 0:quickView 1:bid
     * @param int $buyerId
     * @param int $sellerId
     * @return array
     */
    public function getMessagesWithBuyerAndSeller($agreementId, $isBid, $buyerId, $sellerId)
    {
        $buyerMessage = MarginMessage::query()
            ->where('margin_agreement_id', $agreementId)
            ->where('customer_id', $buyerId)
            ->orderBy('create_time', 'asc')
            ->first();

        if ($isBid === 0) {
            return [
                'buyer_message' => ($buyerMessage && empty($buyerMessage->memo)) ? trim($buyerMessage->message) : '',
                'buyer_message_time' => $buyerMessage ? trim($buyerMessage->create_time) : '',
                'seller_message' => '',
                'seller_message_time' => ''
            ];
        }

        $sellerMessage = MarginMessage::query()
            ->where('margin_agreement_id', $agreementId)
            ->where('customer_id', $sellerId)
            ->orderBy('create_time', 'asc')
            ->first();

        return [
            'buyer_message' => $buyerMessage ? trim($buyerMessage->message) : '',
            'buyer_message_time' => $buyerMessage ? trim($buyerMessage->create_time) : '',
            'seller_message' => $sellerMessage ? trim($sellerMessage->message) : '',
            'seller_message_time' => $sellerMessage ? trim($sellerMessage->create_time) : '',
        ];

    }


    /**
     * 获取协议尾款支付状态，基于协议头款商品购买完成
     * @param int $marginId
     * @return array
     */
    public function getAgreementDuePayInfo($marginId)
    {
        $marginInfo = MarginAgreement::query()->alias('a')
            ->leftJoinRelations('process as b')
            ->where('a.id', $marginId)
            ->selectRaw("a.*,b.process_status")
            ->first();
        if ($marginInfo->process_status <= 1) {
            return [
                'process_status' => -1, //在前端不展示，因为没购买头款商品
                'message' => '',
                'days' => 0,
            ];
        }
        //头款购买完成以后
        //完成 注:process表里面的数据不足以判断此协议是否完成
        if ($marginInfo->process_status == 4 && $marginInfo->status == MarginAgreementStatus::COMPLETED ) {
            return [
                'process_status' => 1,
                'message' => 'Due Payment Completed',
                'days' => 0,
            ];
        }
        //过期
        if ($marginInfo->expire_time < date('Y-m-d H:i:s', time())) {
            return [
                'process_status' => 2,
                'message' => 'Due Payment Timed Out',
                'days' => '',
            ];
        }
        //倒计时
        $days = ceil((strtotime($marginInfo->expire_time) - time()) / 86400);
        return [
            'process_status' => 3,
            'message' => $days > 1 ? min($days,$marginInfo->day) . ' Days Left' : $days . ' Day Left',
            'days' => '',
        ];
    }

    /**
     * 获取现货协议状态列表，现货四期
     * @param bool $needIgnore
     * @return array
     */
    public function getAgreementStatus($needIgnore = true)
    {
        $agreementStatusList = MarginAgreementStatusModel::query()
            ->where('language_id', 1)
            ->selectRaw('margin_agreement_status_id as id,name,color')
            ->orderBy('sort', 'asc')
            ->get()
            ->toArray();
        if ($needIgnore) {
            $agreementStatusList[] = ['id' => -1, 'name' => 'Ignore', 'color' => '#999999'];
        }

        return $agreementStatusList;
    }


    /**
     * 查询seller所在现货合约的复杂交易
     * @param int $sellerId
     * @return array
     */
    public function getMarginProductsBySellerId(int $sellerId):array
    {
        return MarginTemplate::query()
            ->where([
                'is_del' => 0,
                'seller_id' => $sellerId,
            ])
            ->groupBy('product_id')
            ->pluck('product_id')
            ->toArray();
    }

    /**
     * 对应Onsite Seller 判断是否存在账户金额不足而导致Buyer不能购买的现货头款协议（排除期货转现货） -- 通过现货协议ID
     *
     * @param array $agreementIds margin_agreement.id
     * @param int $countryId 所属国家ID
     * @return array|bool
     */
    public function checkOnsiteSellerAmountByAgreementIds(array $agreementIds, int $countryId)
    {
        $precision = JAPAN_COUNTRY_ID == $countryId ? 0 : 2;

        /*
         * 对于同一个Onsite Seller，如果存在多个现货头款，按加入购物车顺序，最新加入的可以购买,以此类推，提示出不能够买的协议编号
         * 不同Onsite Seller如此，需要提示出所有不能购买的现货协议编号
         * 需要排除由期货转现货
         */
        $subSql = FuturesMarginDelivery::whereIn('margin_agreement_id', $agreementIds)->pluck('margin_agreement_id');
        $marginAgreements = MarginAgreement::whereIn('id', $agreementIds)->whereNotIn('id', $subSql)->orderBy('id', 'desc')->get();

        $sellerAmounts = [];
        $notBuyList = [];
        $contractRepo = app(ContractRepository::class);
        foreach ($marginAgreements as $item) {
            $needActiveAmount = round($item->price * $item->payment_ratio / 100, $precision) * $item->num;
            if (! isset($sellerAmounts[$item->seller_id])) {
                $sellerAmounts[$item->seller_id] = $contractRepo->getSellerActiveAmount($item->seller_id, CustomerAccountingType::GIGA_ONSIDE);
            }
            $sellerAmounts[$item->seller_id] = round($sellerAmounts[$item->seller_id] - $needActiveAmount, $precision);
            if (bccomp($sellerAmounts[$item->seller_id], 0) < 0) {
                $notBuyList[] = $item->agreement_id;
            }
        }

        return $notBuyList;
    }

    /**
     * 对应Onsite Seller 判断是否存在账户金额不足而导致Buyer不能购买的现货头款协议（排序由期货转现货） -- 通过采购订单ID
     *
     * @param int $orderId 订单ID
     * @param int $countryId 所属国家ID
     * @return array|bool
     */
    public function checkOnsiteSellerAmountByOrderId(int $orderId, int $countryId)
    {
        $marginList = OrderProduct::query()->alias('op')
            ->leftJoinRelations('product as p', 'customerPartnerToProduct as ctp')
            ->leftJoin('oc_customer as c', 'ctp.customer_id', 'c.customer_id')
            ->where('p.product_type', ProductType::MARGIN_DEPOSIT)
            ->where('op.order_id', $orderId)
            ->where('op.type_id', OcOrderTypeId::TYPE_MARGIN)
            ->where('c.accounting_type', CustomerAccountingType::GIGA_ONSIDE)
            ->whereNotNull('op.agreement_id')
            ->pluck('op.agreement_id')
            ->toArray();

        $notBuyList = [];
        if ($marginList) {
            $notBuyList = $this->checkOnsiteSellerAmountByAgreementIds($marginList, $countryId);
        }

        return $notBuyList;
    }

    /**
     * 获取现货保证金未入账金额（总支出未入账金额）
     *
     * @param int $customerId 用户ID
     * @param int $type 记账类型（1为授信额度,3应收款,4抵押物）
     * @return int|float
     */
    public function getUnfinishedPayRecordAmount(int $customerId, int $type = MarginAgreementPayRecordType::ACCOUNT_RECEIVABLE)
    {
        $amount = MarginAgreementPayRecord::where('customer_id', $customerId)
            ->where('type', $type)
            ->where('bill_status', YesNoEnum::NO)
            ->groupBy('bill_type')
            ->selectRaw('sum(amount) as amount,bill_type')
            ->get()
            ->toArray();
        $amount = array_column($amount, 'amount', 'bill_type');

        $expendAmount = isset($amount[MarginAgreementPayRecordBillType::EXPEND]) ? $amount[MarginAgreementPayRecordBillType::EXPEND] : 0;
        $incomeAmount = isset($amount[MarginAgreementPayRecordBillType::INCOME]) ? $amount[MarginAgreementPayRecordBillType::INCOME] : 0;

        return $expendAmount - $incomeAmount;
    }

    /**
     * 获取现货协议头款订单
     *
     * @param $agreementId
     * @return Order|null
     */
    public function getAdvanceOrderByAgreementId($agreementId): ?Order
    {
        $cacheKey = [__CLASS__, __FUNCTION__, $agreementId];
        $order = $this->getRequestCachedData($cacheKey);
        if ($order) {
            return $order;
        }
        $marginProcess = MarginProcess::query()->where('margin_id', '=', $agreementId)->first();
        if ($marginProcess && $marginProcess->advanceOrder) {
            $order = $marginProcess->advanceOrder;
            $this->setRequestCachedData($cacheKey, $order);
        }
        return $order;
    }

    /**
     * 获取头款order product
     *
     * @param $agreementId
     * @return OrderProduct|null
     */
    public function getAdvanceOrderProductByAgreementId($agreementId): ?OrderProduct
    {
        $cacheKey = [__CLASS__, __FUNCTION__, $agreementId];
        $orderProduct = $this->getRequestCachedData($cacheKey);
        if ($orderProduct) {
            return $orderProduct;
        }
        $marginProcess = MarginProcess::where('margin_id', '=', $agreementId)->first();
        if (!$marginProcess) {
            return null;
        }
        // 获取头款order product
        $orderProduct = OrderProduct::query()
            ->where('order_id', '=', $marginProcess->advance_order_id)
            ->where('type_id', '=', ProductTransactionType::MARGIN)
            ->where('agreement_id', '=', $agreementId)
            ->first();
        $this->setRequestCachedData($cacheKey, $orderProduct);
        return $orderProduct;
    }

    /**
     * 判断协议是否过了有效期
     *
     * @param int|MarginAgreement $agreement 协议
     * @return bool
     */
    public function checkAgreementIsExpired($agreement)
    {
        if (!($agreement instanceof MarginAgreement)) {
            $agreement = MarginAgreement::find($agreement);
        }
        return Carbon::now()->gt($agreement->expire_time);
    }

    /**
     * 验证协议是否有效
     * @param int $agreementId
     * @param int $productId
     * @return bool
     */
    public function checkAgreementIsValid(int $agreementId, int $productId): bool
    {
        return MarginAgreement::query()
            ->where('id',$agreementId)
            ->where('product_id', $productId)
            ->where('expire_time', '>', Carbon::now()->toDateTimeString())
            ->where('status', MarginAgreementStatus::SOLD)
            ->exists();
    }
}
