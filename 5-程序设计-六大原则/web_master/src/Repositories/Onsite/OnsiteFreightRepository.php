<?php

namespace App\Repositories\Onsite;

use App\Enums\Onsite\OnsiteFreightConfig;
use App\Enums\Onsite\OnsiteFreightType;
use App\Models\Onsite\OnsiteFreightDetail;
use App\Models\Onsite\OnsiteFreightVersion;

class OnsiteFreightRepository
{
    /**
     * 获取seller配置报价信息
     * @param int $sellerId
     * @return array
     */
    public function calculateOnsiteFreightInfo(int $sellerId): array
    {
        $onsiteFreightVersion = OnsiteFreightVersion::query()
            ->where('seller_id', $sellerId)
            ->where('effect_end_time', OnsiteFreightConfig::LASTED_RECORD_DATE)
            ->where('status', 1)
            ->first();

        $result = [
            'seller_quote_flag' => -1,
            'parcel_carrier_quote' => [],
            'ltl_quote' => [],
        ];
        if (empty($onsiteFreightVersion)) {
            return $result;
        }
        $result['seller_quote_flag'] = 1;
        if ($onsiteFreightVersion->ltl_provide == 1) {
            $result['ltl_quote'] = OnsiteFreightDetail::query()
                ->where('version_id', $onsiteFreightVersion->id)
                ->where('type', OnsiteFreightType::LTL_QUOTE)
                ->get()
                ->keyBy('key')
                ->toArray();
            $result['seller_quote_flag'] = 2;
        }

        $result['parcel_carrier_quote'] = OnsiteFreightDetail::query()
            ->where('version_id', $onsiteFreightVersion->id)
            ->where('type', OnsiteFreightType::PARCEL_CARRIER_QUOTE)
            ->get()
            ->keyBy('key')
            ->toArray();

        return $result;
    }


}
