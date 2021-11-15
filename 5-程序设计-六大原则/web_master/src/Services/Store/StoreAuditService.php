<?php

namespace App\Services\Store;

use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use Carbon\Carbon;
use App\Models\Store\StoreAudit;
use App\Enums\Store\StoreAuditStatus;

class StoreAuditService
{
    /**
     * 获取关联订单信息
     * @param array $data
     * @return bool|int
     */
    public function insertStoreAudit($data)
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

        $saveData = [
            'customer_id' => customer()->getId(),
            'store_name' => $data['screenName'] ?? '',
            'logo_url' => $data['avatar'] ?? '',
            'banner_url' => $data['companybanner'] ?? '',
            'description' => $data['companyDescription'] ?? '',
            'return_warranty' => json_encode($returnWarranty),
            'create_time' => Carbon::now(),
        ];

        $this->delStoreAudit(); //删除未审核的记录
        $auditId = StoreAudit::query()->insertGetId($saveData);
        if ($auditId) {
            CustomerPartnerToCustomer::query()
                ->where('customer_id', customer()->getId())
                ->update(['store_audit_id' => $auditId]);
        }
        return true;
    }

    /**
     * 软删除店铺审核记录
     * @return bool
     */
    public function delStoreAudit()
    {
        return StoreAudit::query()
            ->where('customer_id', customer()->getId())
            ->where('status', StoreAuditStatus::PENDING)
            ->update(['is_delete' => 1, 'update_time' => Carbon::now()]);
    }

}
