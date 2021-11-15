<?php

namespace App\Repositories\Safeguard;

use App\Models\Safeguard\SafeguardAutoBuyPlanDetail;
use App\Enums\Safeguard\SafeguardAutoBuyPlanStatus;
use Carbon\Carbon;

class SafeguardAutoBuyPlanRepository
{
    /**
     * 终止日期前几天提示即将到期
     * @param int $customerId
     * @param int $day 默认7天
     * @return bool
     */
    public function isAboutToExpireByDays(int $customerId, int $day = 7)
    {
        //生效中
        $effectivePlan = $this->getEffectivePlan($customerId);
        if (!$effectivePlan) {
            return false;
        }
        //生效中明细最晚失效时间
        $expireTime = $this->getPlanStatusAndLatestExpirationTime($effectivePlan->id);
        if (!$expireTime || $expireTime->expiration_time == '' || $expireTime->expiration_time == '9999-12-31 23:59:59') {
            return false;
        }
        return Carbon::now()->between(
            $expireTime->expiration_time->addDay("-" . $day),
            $expireTime->expiration_time,
            false);
    }

    /**
     * 获取生效中的自动购买投保方案(一个用户只会有一个生效中的)
     * @param int $customerId
     * @return \Framework\Model\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getEffectivePlan(int $customerId)
    {
        return SafeguardAutoBuyPlanDetail::query()->alias('pd')
            ->join('oc_safeguard_auto_buy_plan as p', 'p.id', '=', 'pd.plan_id')
            ->where('p.buyer_id', '=', $customerId)
            ->where('p.status', '=', SafeguardAutoBuyPlanStatus::EFFECTIVE)
            ->where(function ($query) {
                $query->whereNull('pd.expiration_time')
                    ->orWhere('pd.expiration_time', '>', Carbon::now());
            })
            ->first();
    }

    /**
     * 获取用户当前可以投保的方案
     * @param int $customerId
     * @param string|null $bpTime
     * @return \Framework\Model\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getAvailablePlan(int $customerId,?string $bpTime = null)
    {
        $bpTime = $bpTime ?: Carbon::now()->toDateTimeString();
        return SafeguardAutoBuyPlanDetail::query()->alias('pd')
            ->join('oc_safeguard_auto_buy_plan as p', 'p.id', '=', 'pd.plan_id')
            ->where('p.buyer_id', '=', $customerId)
            ->where('p.status', '=', SafeguardAutoBuyPlanStatus::EFFECTIVE)
            ->where(function ($query) use ($bpTime) {
                $query->whereNull('pd.effective_time')
                    ->orWhere('pd.effective_time', '<=', $bpTime);
            })
            ->where(function ($query) use ($bpTime) {
                $query->whereNull('pd.expiration_time')
                    ->orWhere('pd.expiration_time', '>', $bpTime);
            })
            ->get(['pd.*']);
    }

    /**
     * 获取投保方案中最晚失效的记录明细
     * @param int $planId
     * @return SafeguardAutoBuyPlanDetail|\Framework\Model\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getPlanStatusAndLatestExpirationTime(int $planId)
    {
        return SafeguardAutoBuyPlanDetail::query()
            ->with(['plan'])
            ->where('plan_id', $planId)
            ->orderBy(SafeguardAutoBuyPlanDetail::raw('ISNULL(expiration_time)'), 'desc')
            ->orderBy('expiration_time', 'desc')
            ->first();
    }
}
