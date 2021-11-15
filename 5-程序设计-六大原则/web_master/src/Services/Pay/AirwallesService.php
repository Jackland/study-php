<?php

namespace App\Services\Pay;

use App\Logging\Logger;
use App\Models\Buyer\Buyer;

class AirwallesService
{
    /**
     * 更新用户airwallerx绑定信息
     *
     * @param $airwallexIdentifier
     * @param $airwallexId
     * @return bool
     */
    public function updateAirwallexId($airwallexIdentifier, $airwallexId)
    {
        $airId = Buyer::where('airwallex_identifier', $airwallexIdentifier)->value('airwallex_id');
        if (! $airId) { // 已经存在了目前不予处理(就算是替换也不予处理)
            $res = Buyer::where('airwallex_identifier', $airwallexIdentifier)->update(['airwallex_id' => $airwallexId]);
            if (! $res) {
                Logger::airwallex("云汇绑定|新增绑定失败：identifier:{$airwallexIdentifier};id:{$airwallexId}");
                return false;
            } else {
                Logger::airwallex("云汇绑定|新增绑定成功：identifier:{$airwallexIdentifier};id:{$airwallexId}");
            }
        } else {
            Logger::airwallex("云汇绑定|已经存在云汇绑定信息，本次不予更新操作:identifier:{$airwallexIdentifier};id:{$airwallexId}");
        }

        return true;
    }
}