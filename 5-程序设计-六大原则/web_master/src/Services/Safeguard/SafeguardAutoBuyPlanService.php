<?php

namespace App\Services\Safeguard;

use App\Helper\CountryHelper;
use App\Helper\DateHelper;
use App\Models\Safeguard\SafeguardAutoBuyPlan;
use App\Models\Safeguard\SafeguardAutoBuyPlanLog;
use App\Models\Safeguard\SafeguardAutoBuyPlanDetail;
use App\Enums\Safeguard\SafeguardAutoBuyPlanStatus;
use App\Enums\Safeguard\SafeguardAutoBuyPlanLogType;
use Carbon\Carbon;
use Exception;

class SafeguardAutoBuyPlanService
{
    /**
     * 创建/编辑自动购买投保保障方案
     * @param array $data
     * exm: [
     *       0 => [
     *              planDetail=>''    //编辑的时候传入 oc_safeguard_auto_buy_plan_detail.id
     *              startTime => '2021-04-21 00:00:00',
     *              endTime => '2021-04-22 23:59:59',
     *              safeguardConfigIdList => [1,2,.....], //oc_safeguard_config.id
     *            ],
     *      ....
     *    ]
     * @param int $customerId
     * @param int $planId 编辑的时候传  oc_safeguard_auto_buy_plan.id
     * @return bool
     * @throws Exception
     */
    public function saveSafeguardAutoBuyPlan(array $data, int $customerId, int $planId = 0)
    {
        //转为美国时间
        $timezone = CountryHelper::getTimezone(AMERICAN_COUNTRY_ID);
        if (!is_array($data) || !$timezone) {
            return false;
        }
        try {
            db()->getConnection()->beginTransaction();
            $currentDatetime = date('Y-m-d H:i:s');
            $detailIdArr=[];

            //1.记录主表
            $plan = SafeguardAutoBuyPlan::query()->find($planId);
            if ($plan) {//编辑
                $type = SafeguardAutoBuyPlanLogType::EDIT;
                $plan->update_time = $currentDatetime;
                //查询明细
                $detailIdArr = array_flip(SafeguardAutoBuyPlanDetail::query()->where('plan_id', $planId)->pluck('id')->toArray());
            } else {//新建
                $type = SafeguardAutoBuyPlanLogType::CREATE;
                $plan = new SafeguardAutoBuyPlan();
                $plan->create_time = $currentDatetime;
                $plan->update_time = $currentDatetime;
                $plan->buyer_id = $customerId;
                $plan->status = SafeguardAutoBuyPlanStatus::EFFECTIVE;
            }
            if (!$plan->save()) {
                return false;
            }

            //2.方案明细
            $content = [];
            foreach ($data as $item) {
                if (!is_array($item['safeguardConfigIdList']) || !isset($item['safeguardConfigIdList'])) {
                    return false;
                }
                if (!isset($item['effectiveTime']) || !DateHelper::isCorrectDateFormat(trim($item['effectiveTime']), ['Y-m-d H:i:s'])) {
                    return false;
                }
                if ($item['expirationTime'] && !DateHelper::isCorrectDateFormat(trim($item['expirationTime']), ['Y-m-d H:i:s'])) {
                    return false;
                }

                //生效与失效时间转为美国时间（失效时间可能为空）
                $effective_time = Carbon::createFromFormat('Y-m-d H:i:s', trim($item['effectiveTime']))->setTimezone($timezone)->toDateTimeString();
                $expiration_time = trim($item['expirationTime']) ? Carbon::createFromFormat('Y-m-d H:i:s', trim($item['expirationTime']))->setTimezone($timezone)->toDateTimeString() : NULL;
                //记录明细 如果有就编辑，没有就新增
                $planDetailId = $item['planDetail'] ?? 0;
                $planDetail = SafeguardAutoBuyPlanDetail::query()->find($planDetailId);
                if ($planDetail) {//编辑
                    $planDetail->update_time = $currentDatetime;
                    unset($detailIdArr[$planDetailId]);
                } else {//新增
                    $planDetail = new SafeguardAutoBuyPlanDetail();
                    $planDetail->create_time = $currentDatetime;
                    $planDetail->plan_id = $plan->id;
                }
                $planDetail->effective_time = $effective_time;
                $planDetail->expiration_time = $expiration_time;
                $planDetail->safeguard_config_id = implode(',', $item['safeguardConfigIdList']);
                if (!$planDetail->save()) {
                    return false;
                }

                //log内容
                $content[] = [
                    'effective_time' => $effective_time,
                    'expiration_time' => $expiration_time,
                    'safeguard_config_id' => $planDetail->safeguard_config_id,
                ];
            }
            //编辑方案 本次传递过来detailId,就将之前的删除，保留本次最新的
            if (!empty(array_flip($detailIdArr)) && $planId) {
                SafeguardAutoBuyPlanDetail::query()->where('plan_id', $planId)->whereIn('id', array_flip($detailIdArr))->delete();
            }

            //3.记录log
            $planLog = SafeguardAutoBuyPlanLog::query()->insert([
                'plan_id' => $plan->id,
                'type' => $type,
                'content' => json_encode($content),
                'create_time' => $currentDatetime,
                'operator_id' => $customerId,
            ]);
            if (!$planLog) {
                return false;
            }

            db()->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            db()->getConnection()->rollBack();
            return false;
        }
    }

    /**
     * 终止自动购买投保方案
     * @param int $planID
     * @param int $customerId
     * @return bool
     * @throws Exception
     */
    public function terminateSafeguardAutoBuyPlan(int $planID, int $customerId)
    {
        if (!$plan = SafeguardAutoBuyPlan::query()->where('id', '=', $planID)->where('buyer_id', $customerId)->first()) {
            return false;
        }
        try {
            db()->getConnection()->beginTransaction();
            $contentLog = ['status' => ['old' => $plan->status, 'new' => SafeguardAutoBuyPlanStatus::TERMINATION]];
            $updateContent = ['status' => SafeguardAutoBuyPlanStatus::TERMINATION, 'update_time' => date('Y-m-d H:i:s')];
            //终止
            if (!$plan->update($updateContent)) {
                return false;
            }
            //记录日志
            $planLog = SafeguardAutoBuyPlanLog::query()->insert([
                'plan_id' => $planID,
                'type' => SafeguardAutoBuyPlanLogType::EDIT,
                'content' => json_encode($contentLog),
                'operator_id' => $customerId,
            ]);
            if (!$planLog) {
                return false;
            }
            db()->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            db()->getConnection()->rollBack();
            return false;
        }
    }
}
