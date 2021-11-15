<?php

namespace App\Repositories\Pay;

use App\Models\Pay\CreditLinePlatformType;

class PlatformTypeRepository
{
    /**
     * 获取额度费用类型
     *
     * @param int $collectionPaymentType
     * @param int $accountType
     * @param int $status
     * @return array
     */
    public function getCreditLinePlatformTypeMap(int $collectionPaymentType, int $accountType, int $status)
    {
        $list = CreditLinePlatformType::query()
            ->where('collection_payment_type', $collectionPaymentType)
            ->where('account_type', $accountType)
            ->where('status', $status)
            ->select(['name', 'type', 'id', 'parent_id'])
            ->get()
            ->toArray();
        $result = [];
        if ($list) {
            $result = $this->dealTypeLevel($list);
        }

        return $result;
    }

    /**
     * 递归处理分类展示（指定递归层级）
     *
     * @param array $list 需要递归遍历的列表
     * @param int $parentId 父级ID
     * @param int $deep 当前深度
     * @param int $showDeep 需要展示的深度
     * @return array
     */
    private function dealTypeLevel($list, $parentId = 0, $deep = 1, $showDeep = 2)
    {
        $typeList = [];
        foreach ($list as $key => $item) {
            if ($item['parent_id'] == $parentId) {
                $typeList[$key] = $item;
                $deep++;
                if ($deep <= $showDeep) {
                    $typeList[$key]['children'] = $this->dealTypeLevel($list, $item['id'], $deep);
                    $deep--;
                }
            }
        }

        return $typeList;
    }
}