<?php

namespace App\Repositories\Margin;

use App\Catalog\Search\Margin\MarginSellerAgreementSearch;
use App\Enums\Common\YesNoEnum;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Margin\MarginPerformerApplyStatus;
use App\Models\Margin\MarginAgreement;
use App\Models\Margin\MarginPerformerApply;
use App\Models\Margin\MarginTemplate;

class AgreementRepository
{
    /**
     * 是否允许审批共同履约人
     * @param MarginAgreement $agreement
     * @param int $sellerId
     * @return array [ret,msg] ret 0代表不可以 1代表可以 msg是不可以的原因
     * @version 现货保证金四期
     */
    public function isCanPerformerAudit(MarginAgreement $agreement, int $sellerId) :array
    {
        if ($agreement->performerApplies->isEmpty()) {
            return ['ret' => 0, 'msg' => 'The partner of this Margin Agreement has been reviewed. Please refresh the page to view the result.'];
        }

        $date = date('Y-m-d H:i:s');
        if($date < $agreement->effect_time || $date > $agreement->expire_time){
            return ['ret' => 0, 'msg'=>'The margin agreement has expired. <br>A partner cannot be added to this margin agreement.'];
        }

        if ($agreement->seller_id != $sellerId) {
            return ['ret' => 0, 'msg'=>'No Access, no permission'];
        }

        if (!in_array($agreement->status, [MarginAgreementStatus::SOLD])) {
            return ['ret' => 0, 'msg'=>'Status of margin agreement has been changed. <br>A partner cannot be added to this margin agreement.'];
        }

        /** @var  MarginPerformerApply $performerApply */
        $performerApply = $agreement->performerApplies()
            ->whereIn('check_result', MarginPerformerApplyStatus::noRejected()) //平台未审核和已审核过的
            ->whereIn('seller_approval_status', MarginPerformerApplyStatus::noRejected()) //seller审核和审核通过的
            ->orderByDesc('id')
            ->first();

        if (empty($performerApply)) {
            return ['ret' => 0, 'msg' => 'The partner of this Margin Agreement has been reviewed. Please refresh the page to view the result.'];
        }
        //已有共同履约人
        if ($performerApply->check_result == MarginPerformerApplyStatus::APPROVED) {
            return ['ret' => 0, 'msg' => 'There is already a joint performer.'];
        }
        //已经审核过的不用再审核
        if ($performerApply->seller_approval_status == MarginPerformerApplyStatus::APPROVED) {
            return ['ret' => 0, 'msg' => 'Reviewed, waiting for system review.'];
        }

        return ['ret' => 1, 'msg' => 'OK', 'performerId' => $performerApply->id];
    }

    /**
     * 到期预警
     * @param MarginAgreement $agreement
     * @param int $advanceDays 提前天数
     * @return array
     */
    public function getDaysLeftWarning(MarginAgreement $agreement, int $advanceDays = 7) :array
    {
        $daysLeft = [
            'is_show' => false,
            'days' => 0,
        ];
        if (empty($agreement->expire_time) || $agreement->status != MarginAgreementStatus::SOLD) {
            return $daysLeft;
        }

        $days = ceil((strtotime($agreement->expire_time) - time()) / 86400);
        if ($days <= $advanceDays && $days > 0) {
            $daysLeft = [
                'is_show' => true,
                'days' => $days,
            ];
        }

        return $daysLeft;
    }

    /**
     * 获取倒计时
     * @param MarginAgreement $agreement
     * @param int $advanceSeconds 提前秒数
     * @return array
     */
    public function getAgreementCountDown(MarginAgreement $agreement, int $advanceSeconds = 3600)
    {
        $statusCountDown = [
            'is_show' => false,
            'minute' => 0,
            'second' => 0
        ];

        if (!in_array($agreement->status, MarginAgreementStatus::beforeApprovedStatus())) {
            return $statusCountDown;
        }

        $residueTime = strtotime($agreement->update_time) +  $agreement->period_of_application * 86400 - time();
        if ($residueTime >= $advanceSeconds) {
            return $statusCountDown;
        }

        $statusCountDown['is_show'] = true;

        if ($residueTime < 0) {
            return $statusCountDown;
        }

        $statusCountDown['minute'] = floor($residueTime / 60);
        $statusCountDown['second'] = $residueTime % 60;

        return $statusCountDown;
    }

    /**
     * seller端现货协议热区统计数量
     * @param int $sellerId
     * @return int
     */
    public function sellerMarginBidsHotspotCount($sellerId) :int
    {
        $search = new MarginSellerAgreementSearch($sellerId);
        $statAgreementIds = $search->getStatAgreementIds([], false);
        $marginNum = 0;
        foreach ($statAgreementIds as $stat) {
            $marginNum += count($stat);
        }

        return $marginNum;
    }

    /**
     * 获取现货模板
     * @param int $sellerId
     * @param int $productId
     * @param int $qty
     * @return MarginTemplate|null
     */
    public function getMarginTemplateByQty($sellerId, $productId, $qty)
    {
        return MarginTemplate::query()
            ->where('seller_id', $sellerId)
            ->where('product_id', $productId)
            ->where('is_del', YesNoEnum::NO)
            ->where('min_num', '<=', $qty)
            ->where('max_num', '>=', $qty)
            ->first();
    }
}
