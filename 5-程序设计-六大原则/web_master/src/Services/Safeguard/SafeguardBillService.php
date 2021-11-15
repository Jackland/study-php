<?php

namespace App\Services\Safeguard;

use App\Models\Safeguard\SafeguardBill;
use App\Models\Safeguard\SafeguardConfig;
use App\Enums\Safeguard\BillOrderType;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Components\UniqueGenerator;
use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Helper\CountryHelper;
use Carbon\Carbon;
use Exception;

class SafeguardBillService
{
    /**
     * 取消保单
     * @param int $safeguardBillId
     * @return int
     */
    public function cancelSafeguardBill(int $safeguardBillId)
    {
        $date = Carbon::now();
        return SafeguardBill::where('id', $safeguardBillId)->update([
            'status' => SafeguardBillStatus::CANCELED,
            'expiration_time' => $date,
            'cancel_time' => $date,
            'update_time' => $date
        ]);
    }

    /**
     * @param array $data
     * exm: [
     *           safeguard_config_id=>1                  //oc_safeguard_auto_buy_plan_detail.id
     *           order_type => 1,                        //订单类型
     *           buyer_id => 1,
     *           country_id => 1,
     *           order_id=>123                           //订单id(对应订单类型的id)
     *           effective_time =>'2021-04-22 23:59:59', //生效时间(目前是订单BP时间)
     *       ]
     * @return SafeguardBill|bool
     * @throws Exception
     */
    public function createSafeguardBill(array $data)
    {
        //订单类型
        if (!in_array($data['order_type'], BillOrderType::getValues())) {
            return false;
        }
        //计算失效时间
        $config = SafeguardConfig::query()->find($data['safeguard_config_id']);
        if (!$config || !is_int($config->duration) || $config->duration < 0) {
            return false;
        }
        //创建保单
        $safeguardBill = new SafeguardBill();
        $safeguardBill->safeguard_no = $this->createBillNo();
        $safeguardBill->safeguard_config_id = $data['safeguard_config_id'];
        $safeguardBill->safeguard_config_rid = $config->rid;
        $safeguardBill->buyer_id = $data['buyer_id'];
        $safeguardBill->country_id = $data['country_id'];
        $safeguardBill->order_id = $data['order_id'];
        $safeguardBill->order_type = $data['order_type'];
        $safeguardBill->effective_time = null;
        $safeguardBill->expiration_time = null;
        $safeguardBill->status = SafeguardBillStatus::PENDING;
        $safeguardBill->create_time = $data['effective_time'];
        $safeguardBill->save();
        return $safeguardBill;
    }

    /**
     * 创建一个新的safeguard_no
     * @return string
     */
    private function createBillNo()
    {
        return UniqueGenerator::date()
            ->service(ServiceEnum::SAFEGUARD_BILL_NO)
            ->random();
    }
}
