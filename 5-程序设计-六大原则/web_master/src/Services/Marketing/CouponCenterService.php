<?php

namespace App\Services\Marketing;

use App\Enums\Marketing\CouponAuditStatus;
use App\Enums\Marketing\CouponLogType;
use App\Enums\Marketing\CouponStatus;
use App\Enums\Marketing\CouponTemplateType;
use App\Logging\Logger;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\CouponLog;
use App\Models\Marketing\CouponTemplate;
use Carbon\Carbon;
use App\Helper\CountryHelper;
use Exception;

class CouponCenterService
{
    /**
     * Buyer领取优惠券
     *
     * @param int $couponId
     * @param int $coustomerId
     * @param CouponTemplate|null $couponTemplateInfo
     * @return bool|int
     */
    public function drawCoupon(int $couponId, int $coustomerId, CouponTemplate $couponTemplateInfo = null)
    {
        if (empty($couponTemplateInfo)) {
            $couponTemplateInfo = CouponTemplate::find($couponId);
            if (!$couponTemplateInfo) {
                return false;
            }
        }

        $orm = db();
        $orm::beginTransaction();
        try {
            // 只需对优惠券类型为 领取型 的优惠券进行模板数量扣除
            if ($couponTemplateInfo->type == CouponTemplateType::BUYER_DRAW) {
                // 扣除可领取数量
                $updateTemplate = CouponTemplate::where('id', $couponId)->update(['remain_qty' => $orm::raw('remain_qty-1')]);
                if (!$updateTemplate) {
                    throw new Exception('扣除模板数量异常');
                }
            }

            // 组合插入数据
            $data['coupon_template_id'] = $couponTemplateInfo->id;
            $data['coupon_no'] = 0;
            $data['customer_id'] = $coustomerId;
            if ($couponTemplateInfo->expiration_days) {
                $sourceTimeZone = CountryHelper::getTimezone($couponTemplateInfo->country_id);
                $startDateStr = Carbon::now($sourceTimeZone)->format('Y-m-d 00:00:00');
                $endDateStr = Carbon::now($sourceTimeZone)->setTimeFromTimeString($startDateStr)->addDay($couponTemplateInfo->expiration_days - 1)->format('Y-m-d 23:59:59');

                $data['effective_time'] = dateFormat($sourceTimeZone, date_default_timezone_get(), $startDateStr);
                $data['expiration_time'] = dateFormat($sourceTimeZone, date_default_timezone_get(), $endDateStr);
            } else {
                $data['effective_time'] = $couponTemplateInfo->effective_time;
                $data['expiration_time'] = $couponTemplateInfo->expiration_time;
            }
            $data['denomination'] = $couponTemplateInfo->denomination;
            $data['order_amount'] = $couponTemplateInfo->order_amount;
            $data['status'] = CouponStatus::UNUSED;
            $data['audit_status'] = CouponAuditStatus::PASS;

            $insetCoupon = Coupon::insertGetId($data);
            if (!$insetCoupon) {
                throw new Exception('插入用户领取优惠券异常');
            }
            $couponNo = $this->generateCouponNo($couponId, $insetCoupon);
            $updateCouponNo = Coupon::where('id', $insetCoupon)->update(['coupon_no' => $couponNo]);
            if (!$updateCouponNo) {
                throw new Exception('更新优惠券编号异常');
            }

            // 记录领取日志
            $logData['coupon_id'] = $insetCoupon;
            $logData['operator_id'] = $coustomerId;
            $logData['type'] = CouponLogType::CREATED;
            $insertCouponLog = CouponLog::insert($logData);
            if (!$insertCouponLog) {
                throw new Exception('插入用户领取优惠券日志异常');
            }

            $orm::commit();
            return $insetCoupon;
        } catch (Exception $e){
            $orm::rollback();

            $msg = 'Buyer领取优惠券异常：' . $e->getMessage();
            Logger::marketing($msg, 'error');
        }

        return false;
    }

    /**
     * 生成优惠券No
     *
     * @param int $couponId 优惠券模板ID
     * @param int $insetCouponId 优惠券ID
     * @return string
     */
    private function generateCouponNo(int $couponId, int $insetCouponId)
    {
        $noHeadStr = str_pad(base_convert($couponId, 10, 36), 3, 0, STR_PAD_LEFT);
        $waitDealStr = mt_rand(10, 99) . str_pad($insetCouponId, 11, 0, STR_PAD_LEFT);
        $noMiddleStr = str_pad(base_convert(substr($waitDealStr, 0, 9), 10, 36), 6, 0, STR_PAD_LEFT);
        $noLastStr = str_pad(base_convert(substr($waitDealStr, -4), 10, 36), 3, 0, STR_PAD_LEFT);
        $couponNo = strtoupper($noHeadStr . $noMiddleStr . $noLastStr);

        return $couponNo;
    }
}
