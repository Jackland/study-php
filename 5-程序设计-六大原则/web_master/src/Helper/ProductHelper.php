<?php

namespace App\Helper;

use App\Logging\Logger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class ProductHelper
{
    const SYNC_SYSTEM_CODE_ALL = 0; // 所有系统
    const SYNC_SYSTEM_CODE_OMD = 1; // OMD系统
    const SYNC_SYSTEM_CODE_GIGA = 2; // 大健云

    /**
     * 获取ltl提醒等级 2为系统直接修改ltl 1为需客户确认 0不是ltl
     * @param float $width
     * @param float $length
     * @param float $height
     * @param float $weight
     * @param string $fromPage 'calculatorPage'运费计算器页面
     * @return int
     */
    public static function getProductLtlRemindLevel($width, $length, $height, $weight, $fromPage=''): int
    {
        // 按照长度大小排序从小到大
        $tmp = [floatval($width), floatval($length), floatval($height)];
        sort($tmp);
        [$mintLength, $mediumLength, $maxLength] = $tmp;

        // 周长
        $perimeter = 2 * ($mintLength + $mediumLength);

        // 系统按照产品尺寸判断是否为LTL发货产品并标记;最长边>108 inches；实际重量>150 lbs；最长边+周长>165 inches【周长=2*（次长边+最短边）】;符合以上任何一个条件的产品都需要标记为LTL，运费使用LTL卡车运费体系
        if ($perimeter + $maxLength > 165) {
            return 2;
        }
        if ($weight > 150) {
            return 2;
        }
        if ($maxLength > 108) {
            return 2;
        }
        if( $fromPage == 'calculatorPage'){
            return 0;
        }

        // 产品尺寸符合以下任何一个条件，触发确认弹窗。 153 inches＜【最长边+周长】≤165 inches 148 lbs＜【实际重量】≤150 lbs 105 inches＜【最长边】≤108 inches
        if (($perimeter + $maxLength <= 165) && ($perimeter + $maxLength > 153)) {
            return 1;
        }
        if ($weight <= 150 && $weight > 148) {
            return 1;
        }
        if ($maxLength <= 108 && $maxLength > 105) {
            return 1;
        }

        return 0;
    }

    //获取转换标准
    public static function getTranslateStandard()
    {
        return [
            //长宽高
            'inch_to_cm' => '2.54',
            'cm_to_inch' => '0.39370079',
            //重量
            'kg_to_pound' => '2.2046226',
            'pound_to_kg' => '0.45359237',
        ];
    }

    /**
     * 批量获取运费
     * @param array $productIds
     * @return array
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public static function sendProductsFreightRequest(array $productIds,int $fullReturn = 0): array
    {
        if (empty($productIds)) {
            return [];
        }

        $client = HttpClient::create();

        $url = B2B_MANAGEMENT_BASE_URL . '/api/itemCodesAccountFreight';
        $token = B2B_MANAGEMENT_AUTH_TOKEN;
        $response = $client->request('POST', $url, [
            'headers' => [
                'Content-Type: application/json; charset=utf-8',
                "Authorization: Bearer {$token}",
            ],
            'json' => $productIds,
        ]);

        $result = $response->toArray();
        if ($result['code'] != 200) {
            return [];
        }

        if ($fullReturn == 0) {
            return $result['data']['productFreights'] ?? [];
        }

        return $result ?? []; // 直接返回接口的全部信息

    }

    /**
     * utf8转gbk
     * @param string $str
     * @return false|string
     */
    public static function stringUTF8ToGBK(string $str)
    {
        return iconv("UTF-8", "gbk//TRANSLIT", $str);
    }

    /**
     * 同步产品信息
     * @param array $productIds
     * @param array|int[] $syncSystemCodes
     * @return bool
     * @throws ExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Throwable
     */
    public static function sendSyncProductsRequest(array $productIds, array $syncSystemCodes = [self::SYNC_SYSTEM_CODE_OMD])
    {
        if (empty($productIds)) {
            return true;
        }

        $client = HttpClient::create();

        $url = B2B_MANAGEMENT_BASE_URL . '/api/syncProductInfo';
        $token = B2B_MANAGEMENT_AUTH_TOKEN;
        $response = $client->request('POST', $url, [
            'headers' => [
                'Content-Type: application/json; charset=utf-8',
                "Authorization: Bearer {$token}",
            ],
            'json' => [
                'productIdList' => $productIds,
                'syncToSystem' => $syncSystemCodes,
            ],
        ]);

        $result = $response->toArray();
        if ($result['code'] != 200) {
            Logger::syncProducts($result['msg']);
            return false;
        }

        return true;
    }
}
