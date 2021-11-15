<?php

namespace App\Repositories\Buyer;

use App\Enums\Buyer\BuyerType;
use App\Models\Buyer\Buyer;
use App\Models\Customer\Customer;
use App\Models\Buyer\BuyerAccountBindingLine;
use Carbon\Carbon;

class BuyerRepository
{
    /**
     * 获取 buyer 的账户类型
     * @param int $buyerId
     * @return int|null
     */
    public function getTypeById(int $buyerId): ?int
    {
        $model = Customer::query()
            ->select('customer_group_id')
            ->where('customer_id', $buyerId)
            ->first();
        if (!$model) {
            return null;
        }
        return in_array($model->customer_group_id, COLLECTION_FROM_DOMICILE)
            ? BuyerType::PICK_UP
            : BuyerType::DROP_SHIP;
    }

    /**
     * 获取 buyer 的账户类型
     * @param array $buyerIds
     * @return array
     */
    public function getTypesByIds(array $buyerIds): array
    {
        $models = Customer::query()
            ->select(['customer_id', 'customer_group_id'])
            ->whereIn('customer_id', $buyerIds)
            ->pluck('customer_group_id', 'customer_id')
            ->toArray();
        $result = [];
        foreach ($buyerIds as $buyerId) {
            $type = null;
            if (isset($models[$buyerId])) {
                $type = in_array($models[$buyerId], COLLECTION_FROM_DOMICILE)
                    ? BuyerType::PICK_UP
                    : BuyerType::DROP_SHIP;
            }
            $result[$buyerId] = $type;
        }
        return $result;
    }

    /**
     * 共同履约人信息查询
     * @param $performerCode
     * @param int $sellerId
     * @return array
     */
    public function getPerformerInfo($performerCode, $sellerId)
    {
        $performerDetail = Customer::query()->alias('a')
            ->leftJoinRelations('seller as c2c')
            ->leftJoin('oc_buyer_to_seller as b2s', function ($join) use ($sellerId) {
                $join->on('a.customer_id', '=', 'b2s.buyer_id')
                    ->where(function ($query) use ($sellerId) {
                        $query->where('b2s.seller_id', $sellerId);
                    });
            })
            ->select([
                'a.*',
                'b2s.id AS b2s_id',
                'c2c.is_partner',
                'b2s.buy_status',
                'b2s.price_status',
                'b2s.buyer_control_status',
                'b2s.seller_control_status'
            ])
            ->where(function ($query) use ($performerCode) {
                $query->where('a.email', $performerCode)
                    ->orWhere('a.user_number', $performerCode);
            })
            ->first();
        return obj2array($performerDetail);
    }

    /**
     * 验证2个buyer是否有正在存在的有效的绑定关系
     * @param int $buyerId
     * @param int $performerBuyerId
     * @return bool
     */
    public function checkBuyersIsBinded($buyerId, $performerBuyerId)
    {
        $lineResult = BuyerAccountBindingLine::query()->alias('a')
            ->leftJoin('tb_sys_buyer_account_binding as b', 'a.head_id', '=', 'b.id')
            ->where('b.status', '=', 1)
            ->where('b.effect_time', '<', Carbon::now())
            ->where('b.expire_time', '>=', Carbon::now())
            ->whereIn('a.customer_id', [$buyerId, $performerBuyerId])
            ->get();
        $flag = false;
        $checkResult = [];
        if ($lineResult->isNotEmpty()) {
            foreach ($lineResult as $key => $item) {
                $checkResult[$item->head_id][] = $item->customer_id;
                if (count($checkResult[$item->head_id]) >= 2) {
                    $flag = true;
                    break;
                }
            }
        }
        return $flag;
    }

    /**
     * description:获取buyer的继承信息
     * @param int $buyerId
     * @param array $field 查询字段
     * @param array $condition 开放条件
     * @return object
     */
    public function getBuyerByIdList(int $buyerId,array $field = ['*'], array $condition = [])
    {
        return Buyer::query()
            ->select($field)
            ->with(['telephone_country_code:id,code'])
            ->where('buyer_id', $buyerId)
            ->first();
    }


}
