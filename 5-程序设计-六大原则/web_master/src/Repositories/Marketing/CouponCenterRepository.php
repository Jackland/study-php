<?php

namespace App\Repositories\Marketing;

use App\Enums\Marketing\CouponAuditStatus;
use App\Enums\Marketing\CouponStatus;
use App\Enums\Marketing\CouponTemplateAuditStatus;
use App\Enums\Marketing\CouponTemplateStatus;
use App\Enums\Marketing\CouponTemplateType;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\CouponTemplate;
use App\Enums\Common\YesNoEnum;
use Illuminate\Support\Collection;

class CouponCenterRepository
{
    /**
     * 获取优惠券模板列表
     *
     * @param int $countryId
     * @param int $customerId
     * @param int $page
     * @param int $pageSize
     * @return CouponTemplate[]|Collection
     */
    public function getValidCouponTemplateList(int $countryId, int $customerId = 0, int $page = 1, int $pageSize = 16)
    {
        $where = [];

        $nowDate = date('Y-m-d H:i:s');
        $where[] = ['mct.country_id', '=', $countryId];
        $where[] = ['mct.grant_start_time', '<=', $nowDate];
        $where[] = ['mct.grant_end_time', '>', $nowDate];
        $where[] = ['mct.status', '=', CouponTemplateStatus::START];
        $where[] = ['mct.type', '=', CouponTemplateType::BUYER_DRAW];
        $where[] = ['mct.audit_status', '=', CouponTemplateAuditStatus::PASS];
        if ($customerId) {
            $couponList = $this->getCouponTemplateListLogin($customerId, $page, $pageSize, $where);
        } else {
            $couponList = $this->getCouponTemplateList($page, $pageSize, $where);
        }

        return $couponList;
    }

    /**
     * 分页获取 优惠券模板列表 - 未登入
     *
     * @param int $page
     * @param int $pageSize
     * @param array $where
     * @return CouponTemplate[]||Collection
     */
    public function getCouponTemplateList(int $page = 1, int $pageSize = 16, $where = [])
    {
        return CouponTemplate::query()->alias('mct')
            ->select(['id', 'effective_time', 'expiration_time', 'expiration_days', 'denomination', 'qty', 'remain_qty', 'buyer_scope', 'order_amount', 'per_limit'])
            ->where('mct.is_deleted', YesNoEnum::NO)
            ->when(!empty($where), function ($query) use ($where) {
                $query->where($where);
            })
            ->orderBy('id', 'desc')
            ->forpage($page, $pageSize)
            ->get();
    }

    /**
     * 分页获取 优惠券模板列表 - 已登入
     *
     * @param int $customerId
     * @param int $page
     * @param int $pageSize
     * @param array $where
     * @return CouponTemplate[]|Collection
     */
    public function getCouponTemplateListLogin(int $customerId, int $page = 1, int $pageSize = 20, $where = [])
    {
        return CouponTemplate::query()->alias('mct')
            ->leftJoin(DB_PREFIX . 'marketing_coupon as mc', function ($query) use ($customerId) {
                $query->on('mct.id', '=', 'mc.coupon_template_id')
                ->where('mc.customer_id', $customerId);
            })
            ->selectRaw('mct.id,mct.effective_time,mct.expiration_time, mct.expiration_days,mct.denomination,mct.per_limit,qty,remain_qty,buyer_scope,mct.order_amount,count(mc.id) as num')
            ->where('mct.is_deleted', YesNoEnum::NO)
            ->when(!empty($where), function ($query) use ($where) {
                $query->where($where);
            })
            ->groupBy('mct.id')
            ->orderBy('mct.id', 'desc')
            ->forpage($page, $pageSize)
            ->get();
    }

    /**
     * @param int $customerId
     * @param int $status
     * @param int $page
     * @param int $pageSize
     * @return Coupon[]
     */
    public function getMyCouponList(int $customerId, int $status = 1, int $page = 1, int $pageSize = 20)
    {
        return Coupon::query()->alias('mc')
            ->leftJoin(DB_PREFIX . 'marketing_coupon_template as mct', 'mc.coupon_template_id', '=', 'mct.id')
            ->select('mct.buyer_scope', 'mc.denomination', 'mc.order_amount', 'mc.status', 'mc.effective_time', 'mc.expiration_time')
            ->where('mc.is_deleted', YesNoEnum::NO)
            ->where('mc.audit_status', CouponAuditStatus::PASS)
            ->where('mc.customer_id', $customerId)
            ->when($status == CouponStatus::USED, function ($query){
                $query->where('mc.status', CouponStatus::USED);
            })
            ->when($status == CouponStatus::UNUSED, function ($query){
                $query->where('mc.status', CouponStatus::UNUSED)->where('mc.expiration_time', '>', date('Y-m-d H:i:s'));
            })
            ->when($status == CouponStatus::INVALID, function ($query){
                $query->where(function ($query) {
                    $query->where('mc.status', '=', CouponStatus::UNUSED)->where('mc.expiration_time', '<=', date('Y-m-d H:i:s'));
                });
            })
            ->orderBy('mc.create_time', 'desc')
            ->forpage($page, $pageSize)
            ->get();
    }

    /**
     * 获取用户优惠券总数
     *
     * @param int $customerId
     * @param int $status
     * @return mixed
     */
    public function getMyCouponNumByStatus(int $customerId, int $status)
    {
        return Coupon::where('customer_id', $customerId)
            ->where('is_deleted', YesNoEnum::NO)
            ->where('audit_status', CouponAuditStatus::PASS)
            ->when($status == CouponStatus::USED, function ($query){
                $query->where('status', CouponStatus::USED);
            })
            ->when($status == CouponStatus::UNUSED, function ($query){
                $query->where('status', CouponStatus::UNUSED)->where('expiration_time', '>', date('Y-m-d H:i:s'));
            })
            ->when($status == CouponStatus::INVALID, function ($query){
                $query->where(function ($query) {
                    $query->where('status', '=', CouponStatus::UNUSED)->where('expiration_time', '<=', date('Y-m-d H:i:s'));
                });
            })
            ->count();
    }

}
