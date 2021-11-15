<?php

namespace App\Repositories\Store;

/**
 * 店铺退返品
 *
 * Class StoreAuditRepository
 *
 */
class StoreAuditRepository
{
    /**
     * 获取提交数据中的退返品信息
     * @param $data
     * @return array
     */
    public function getPostReturnWarranty($data)
    {
        $conditions = [];
        if (isset($data['conditions']) && !empty($data['conditions'])) {
            foreach ($data['conditions'] as $condition) {
                if (trim($condition) != '') {
                    $conditions[] = $condition;
                }
            }
        }
        $returnWarranty = [
            'return' => [
                'undelivered' => [
                    'days' => isset($data['days']) ? (int)$data['days'] : configDB('store_default_return_not_delivery_days'),
                    'rate' => $data['rate'] ?? configDB('store_default_return_not_delivery_rate'),
                    'allow_return' => isset($data['allow_return']) ? (int)$data['allow_return'] : 0,
                ],
                'delivered' => [
                    'before_days' => isset($data['before_days']) ? (int)$data['before_days'] : configDB('store_default_return_delivery_days'),
                    'after_days' => isset($data['before_days']) ? (int)$data['before_days'] : configDB('store_default_return_delivery_days'), //2个值相同，前端disable传不过来，直接使用before_days即可
                    'delivered_checked' => isset($data['delivered_checked']) ? (int)$data['delivered_checked'] : 0,
                ],
            ],
            'warranty' => [
                'month' => isset($data['month']) ? (int)$data['month'] : 0,
                'conditions' => $conditions,
            ],
        ];
        return $returnWarranty;
    }

}
