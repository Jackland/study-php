<?php

namespace App\Services\Safeguard;

use App\Enums\Common\YesNoEnum;
use App\Models\Safeguard\SafeguardBill;
use App\Models\Safeguard\SafeguardClaim;
use App\Models\Safeguard\SafeguardClaimDetail;
use App\Models\Safeguard\SafeguardClaimAudit;
use App\Enums\Safeguard\SafeguardClaimConfig;
use App\Models\Safeguard\SafeguardClaimReason;
use App\Enums\Safeguard\SafeguardClaimStatus;
use App\Enums\Safeguard\SafeguardClaimAuditType;
use App\Models\Safeguard\SafeguardClaimDetailTracking;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use Exception;
use Illuminate\Database\Query\Expression;

class SafeguardClaimService
{

    /**
     * 申请理赔
     * @param int $billId
     * @param int $reasonId
     * @param string $salesPlatform
     * @param string $problemDescripition
     * @param array $templates
     * @param int $confirmMenuId
     * @param array $products
     * @throws Exception
     * @return integer
     */
    public function applyClaim(int $billId, int $reasonId, string $salesPlatform, string $problemDesc, int $confirmMenuId, array $products)
    {
        $claimId = SafeguardClaim::query()->insertGetId([
            'claim_no' => $this->createAvailableOrderNo($billId),
            'safeguard_bill_id' => $billId,
            'buyer_id' => customer()->getId(),
            'status' => SafeguardClaimStatus::CLAIM_IN_PROGRESS,
            'is_viewed' => 1, //默认已查看，因为处于理赔中的数据不需要展示标志
        ]);

        $reasonDetail = SafeguardClaimReason::query()->find($reasonId);
        $auditId = SafeguardClaimAudit::query()->insertGetId([
            'buyer_id' => customer()->getId(),
            'claim_id' => $claimId,
            'reason_id' => $reasonId,
            'reason_name' => $reasonDetail->reason_en,
            'reason_name_zh' => $reasonDetail->reason_zh,
            'sales_platform' => trim($salesPlatform),
            'content' => trim($problemDesc),
            'attach_menu_id' => $confirmMenuId,
        ]);

        SafeguardClaim::query()->where('id', $claimId)->update(['audit_id' => $auditId]);

        foreach ($products as $product) {
            $claimDetailId = SafeguardClaimDetail::query()->insertGetId([
                'claim_id' => $claimId,
                'product_id' => $product['product_id'],
                'item_code' => $product['item_code'],
                'sale_order_id' => $product['sale_order_id'],
                'sale_order_line_id' => $product['sale_order_line_id'],
                'qty' => $product['qty'],
            ]);

            if (isset($product['tracking_infos']) && $product['tracking_infos'] && is_array($product['tracking_infos'])) {
                foreach ($product['tracking_infos'] as $tracking) {
                    SafeguardClaimDetailTracking::query()->insert([
                        'claim_id' => $claimId,
                        'claim_detail_id' => $claimDetailId,
                        'item_code' => $product['item_code'],
                        'carrier_id' => $tracking['carrier_id'],
                        'carrier' => $tracking['carrier'],
                        'tracking_number' => $tracking['tracking_number'],
                    ]);
                }
            }
        }

        return $claimId;
    }

    /**
     * 完善资料（重新提交理赔）
     * @param int $claimId
     * @param string $problemDesc
     * @param int|null $confirmMenuId
     * @return bool
     */
    public function reApplyClaim(int $claimId, string $problemDesc, ?int $confirmMenuId = 0)
    {
        $claimDetail = SafeguardClaim::query()->alias('a')
            ->join('oc_safeguard_claim_audit as b', 'b.id', '=', 'a.audit_id')
            ->where('a.id', $claimId)
            ->orderByDesc('b.id')
            ->select(['b.*'])
            ->first();

        $auditId = SafeguardClaimAudit::query()->insertGetId([
            'picked_user_id' => $claimDetail->picked_user_id,
            'buyer_id' => customer()->getId(),
            'claim_id' => $claimId,
            'reason_id' => !empty($claimDetail->reason_id) ? $claimDetail->reason_id : 0,
            'reason_name' => !empty($claimDetail->reason_name) ? $claimDetail->reason_name : '',
            'reason_name_zh' => !empty($claimDetail->reason_name_zh) ? $claimDetail->reason_name_zh : '',
            'sales_platform' => !empty($claimDetail->sales_platform) ? $claimDetail->sales_platform : '',
            'content' => trim($problemDesc),
            'attach_menu_id' => (int)$confirmMenuId,
            'status' => SafeguardClaimAuditType::AUDIT_BACKED_TO_CHECK,
        ]);

        SafeguardClaim::query()->where('id', $claimId)
            ->update([
                'audit_id' => $auditId,
                'is_viewed' => YesNoEnum::YES,
                'expired_type' => YesNoEnum::NO, // 超时标记：0-正常，1-即将超时，2-已经超时，java定时脚本会操作此字段
                'status' => SafeguardClaimStatus::CLAIM_IN_PROGRESS
            ]);

        return true;
    }

    /**
     * 判断是否可以申请理赔 且直接返回不可申请原因
     * @param int $billId
     * @param int $saleSorderLineId
     * @param string $itemCode
     * @return array
     */
    public function checkCanApplyClaim(int $billId,int $saleSorderLineId, string $itemCode):array
    {
        $currentSkuCheck = SafeguardClaim::query()->alias('a')
            ->leftJoin('oc_safeguard_claim_detail as b', 'a.id', '=', 'b.claim_id')
            ->where('a.safeguard_bill_id', $billId)
            ->where('sale_order_line_id', $saleSorderLineId)
            ->where('item_code', $itemCode)
            ->whereIn('a.status', SafeguardClaimStatus::canNotApplyClaimStatus())
            ->exists();

        //理赔数量不能超过剩余可理赔数量 商品剩余可理赔数量=销售订单中的该商品总数量 - 销售订单中该商品所有理赔处理中/已成功理赔的数量
        $countClaimNumber = CustomerSalesOrderLine::query()->alias('line')
            ->leftJoin('oc_safeguard_claim_detail as detail', 'line.id', '=', 'detail.sale_order_line_id')
            ->leftJoin('oc_safeguard_claim as claim', 'detail.claim_id', '=', 'claim.id')
            ->where('line.id', $saleSorderLineId)
            ->whereIn('claim.status', [SafeguardClaimStatus::CLAIM_IN_PROGRESS, SafeguardClaimStatus::CLAIM_SUCCEED, SafeguardClaimStatus::CLAIM_BACKED])
            ->select(['line.id', 'line.item_code', 'line.qty as qty', new Expression('sum(detail.qty) as claim_qty')])
            ->first();

        if ($currentSkuCheck) {
            return [
                'can_apply' => 0,
                'claim_qty' => $countClaimNumber->claim_qty ?? 0,
                'reason' => 'There exists a claim in progress for this item, and an additional claim application cannot be submitted until the existing one is completed.',
            ];
        }

        if ($countClaimNumber && $countClaimNumber->claim_qty >= $countClaimNumber->qty) {
            return [
                'can_apply' => 0,
                'claim_qty' => $countClaimNumber->claim_qty ?? 0,
                'reason' => 'No remaining quantity is available for this SKU to apply for a claim. For any questions, please contact the Marketplace Customer Service.',
            ];
        }

        return ['can_apply' => 1, 'reason' => '', 'claim_qty' => $countClaimNumber->claim_qty ?? 0];
    }

    /**
     * 获取一个新的claim_no
     * @param int $billId
     * @return string
     * @throws Exception
     */
    public function createAvailableOrderNo(int $billId)
    {
        return $this->generateClaimNo($billId);
    }

    /**
     * 生成一个新的claim no
     * @param int $billId
     * @return string
     * @throws Exception
     */
    private function generateClaimNo(int $billId)
    {
        $prefix = SafeguardClaimConfig::CLAIM_PREFIX_CHAR;
        $count = SafeguardClaim::query()->where('safeguard_bill_id', $billId)->count();
        if ($count >= SafeguardClaimConfig::CLAIM_MAX_NUMBER) {
            throw new Exception('Claim No maxed.');
        }
        $billInfo = SafeguardBill::query()->find($billId);
        $count = ($count == 0 ? '01' : ($count < 9 ? ('0' . ($count + 1)) : $count + 1));

        return $billInfo->safeguard_no . $prefix . $count;
    }

    /**
     * 理赔单设置已读
     * @param int $claimId
     * @return bool
     */
    public function resetClaimViewed(int $claimId)
    {
        $claimDetail = SafeguardClaim::query()->find($claimId);
        $handleStatus = [SafeguardClaimStatus::CLAIM_SUCCEED, SafeguardClaimStatus::CLAIM_FAILED];
        if ($claimDetail && $claimDetail->is_viewed == 0 && in_array($claimDetail->status, $handleStatus)) {
            $claimDetail->is_viewed = 1;
            $claimDetail->save();
        }
        return true;
    }

}
