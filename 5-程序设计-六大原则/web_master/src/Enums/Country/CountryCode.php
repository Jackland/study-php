<?php

namespace App\Enums\Country;

use Framework\Enum\BaseEnum;

class CountryCode extends BaseEnum
{
    const BELGIUM = 'BE'; // 比利时 Belgium
    const BULGARIA = 'BG'; // 保加利亚 Bulgaria
    const CZECH_REPUBLIC = 'CZ'; // 捷克 Czech Republic
    const DENMARK = 'DK';// 丹麦 Denmark
    const GERMANY = 'DE';// 德国 Germany
    const ESTONIA = 'EE';// 爱沙尼亚 Estonia
    const IRELAND = 'IE';// 爱尔兰 Ireland
    const GREECE = 'GR';// 希腊 Greece
    const SPAIN = 'ES';// 西班牙 Spain
    const FRANCE = 'FR';// 法国 France
    const CROATIA = 'HR';// 克罗地亚 Croatia
    const ITALY = 'IT';// 意大利 Italy
    const CYPRUS = 'CY';// 塞浦路斯 Cyprus
    const LATVIA = 'LV';// 拉脱维亚 Latvia
    const LITHUANIA = 'LT';// 立陶宛 Lithuania
    const LUXEMBOURG = 'LU';// 卢森堡 Luxembourg
    const HUNGARY = 'HU';// 匈牙利 Hungary
    const MALTA = 'MT';// 马耳他 Malta
    const NETHERLANDS = 'NL';// 荷兰 Netherlands
    const AUSTRIA = 'AT';// 奥地利 Austria
    const POLAND = 'PL';// 波兰 Poland
    const PORTUGAL = 'PT';// 葡萄牙 Portugal
    const ROMANIA = 'RO';// 罗马尼亚 Romania
    const SLOVENIA = 'SI';// 斯洛文尼亚 Slovenia
    const SLOVAKIA = 'SK';// 斯洛伐克 Slovakia
    const FINLAND = 'FI';// 芬兰 Finland
    const SWEDEN = 'SE';// 瑞典 Sweden

    public static function getViewItems()
    {
        return [
            static::BELGIUM => 'Belgium',  //英文名称
            static::BULGARIA => 'Bulgaria',
            static::CZECH_REPUBLIC => 'Czech Republic',
            static::DENMARK => 'Denmark',
            static::GERMANY => 'Germany',
            static::ESTONIA => 'Estonia',
            static::IRELAND => 'Ireland',
            static::GREECE => 'Greece',
            static::SPAIN => 'Spain',
            static::FRANCE => 'France',
            static::CROATIA => 'Croatia',
            static::ITALY => 'Italy',
            static::CYPRUS => 'Cyprus',
            static::LATVIA => 'Latvia',
            static::LITHUANIA => 'Lithuania',
            static::LUXEMBOURG => 'Luxembourg',
            static::HUNGARY => 'Hungary',
            static::MALTA => 'Malta',
            static::NETHERLANDS => 'Netherlands',
            static::AUSTRIA => 'Austria',
            static::POLAND => 'Poland',
            static::PORTUGAL => 'Portugal',
            static::ROMANIA => 'Romania',
            static::SLOVENIA => 'Slovenia',
            static::SLOVAKIA => 'Slovakia',
            static::FINLAND => 'Finland',
            static::SWEDEN => 'Sweden',
        ];
    }

    /**
     * 欧盟成员国
     * @return array
     */
    public static function getEuropeanUnionMemberCountry()
    {
        return [
            static::BELGIUM,
            static::BULGARIA,
            static::CZECH_REPUBLIC,
            static::DENMARK,
            static::GERMANY,
            static::ESTONIA,
            static::IRELAND,
            static::GREECE,
            static::SPAIN,
            static::FRANCE,
            static::CROATIA,
            static::ITALY,
            static::CYPRUS,
            static::LATVIA,
            static::LITHUANIA,
            static::LUXEMBOURG,
            static::HUNGARY,
            static::MALTA,
            static::NETHERLANDS,
            static::AUSTRIA,
            static::POLAND,
            static::PORTUGAL,
            static::ROMANIA,
            static::SLOVENIA,
            static::SLOVAKIA,
            static::FINLAND,
            static::SWEDEN,
        ];
    }
}
