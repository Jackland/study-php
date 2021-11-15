<?php

namespace App\Enums\Marketing;

use Framework\Enum\BaseEnum;

/**
 * 数据库 string 类型， find_in_set() 例如： ['0,4', '0']
 * Class CampaignTransactionType
 * @package App\Enums\Marketing
 */
class CampaignTransactionType extends BaseEnum
{
    const ALL = -1; //全部
    const NORMAL = 0; // 普通和议价
    const REBATE = 1; // 返点
    const MARGIN = 2; // 现货
    const FUTURE = 3; // 期货

    /**
     * 交易类型的活动标题
     * @param int $type
     * @return string[]
     */
    public static function transactionsNameMap(int $type)
    {
        switch ($type) {
            case CampaignType::FULL_REDUCTION:
                return [
                    self::ALL => '',
                    self::NORMAL => 'Normal & Spot',
                    self::REBATE => 'Rebate',
                    self::MARGIN => 'Margin due amount',
                    self::FUTURE => 'Futures due amount',
                ];
            case CampaignType::FULL_DELIVERY:
                return [
                    self::ALL => '',
                    self::NORMAL => 'Normal & Spot',
                    self::REBATE => 'Rebate',
                    self::MARGIN => 'Margin',
                    self::FUTURE => 'Futures',
                ];
        }
    }
}
