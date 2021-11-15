<?php

namespace App\Helper;


use App\Repositories\Setup\SetupRepository;

class AddressHelper
{
    /**
     * 地址是否包含po box 关键词
     * @param $addr
     * @return bool
     */
    public static function isPoBox(string $addr)
    {
        if (empty($addr)) {
            return false;
        }
        $str = preg_replace('/[^a-zA-Z0-9]/i', '', $addr);
        $res = stripos($str, 'pobox');
        $res_other = stripos($str, 'poboxes');
        if ($res !== false || $res_other !== false) {
            return true;
        }
        return false;
    }

    /**
     * 州 是否为偏远地区
     * @param string $state
     * @return bool
     */
    public static function isRemoteRegion(string $state)
    {
        if (empty($state)) {
            return false;
        }
        $state = strtoupper(trim($state));
        $fixState = app(SetupRepository::class)->getValueByKey("REMOTE_ARES");
        $fixState = !is_null($fixState) ? $fixState : 'PR,AK,HI,GU,AA,AE,AP,ALASKA,ARMED FORCES AMERICAS,ARMED FORCES EUROPE,ARMED FORCES PACIFIC,GUAM,HAWAII,PUERTO RICO';
        $stateArray = explode(',', $fixState);
        if (in_array($state, $stateArray)) {
            return true;
        }
        return false;
    }
}
