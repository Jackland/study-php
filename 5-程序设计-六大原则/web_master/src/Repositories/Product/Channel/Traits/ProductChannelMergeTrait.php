<?php

namespace App\Repositories\Product\Channel\Traits;

use App\Enums\Product\Channel\ProductChannelDataType;
use Illuminate\Support\Collection;
use Throwable;

trait ProductChannelMergeTrait
{
    /**
     * 根据产品信息返回
     * @param array $data ["type" => "products","data" => array:5 [▶],"productIds" => array:5 [▶]]
     * @return array
     */
    public function productChannelMergeProductIds(array $data): array
    {
        $collection = new Collection();
        foreach ($data as $items) {
            if ($items) {
                if ($items['type'] == ProductChannelDataType::PRODUCT) {
                    $collection = $collection->merge($items['productIds']);
                }

                if ($items['type'] == ProductChannelDataType::STORES) {
                    foreach ($items['data'] as $info) {
                        $collection = $collection->merge($info['productIds']);
                    }
                }
            }
        }

        return $collection->unique()->all();
    }

    /**
     * 填充产品信息数据
     * @param array $data ["type" => "products","data" => array:5 [▶],"productIds" => array:5 [▶]]
     * @param array $productInfos
     * @return array
     */
    public function setProductChannelProductInfos(array $data, $productInfos): array
    {

        foreach ($data as &$items) {
            if ($items) {
                if ($items['type'] == ProductChannelDataType::PRODUCT) {
                    $products = $items['data'];
                    $tmp = [];
                    foreach ($products as $product) {
                        if (isset($productInfos[$product])) {
                            $tmp[] = $productInfos[$product];
                        }
                    }
                    $items['data'] = $tmp;

                }
                if ($items['type'] == ProductChannelDataType::STORES) {
                    foreach ($items['data'] as $key => $products) {
                        $tmp = [];
                        foreach ($products['productIds'] as $product) {
                            if (isset($productInfos[$product])) {
                                $tmp[] = $productInfos[$product];
                            }
                        }
                        $items['data'][$key]['productIds'] = $tmp;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param array $data ["type" => "products","data" => array:5 [▶],"productIds" => array:5 [▶]]
     * @return array
     * @throws Throwable
     */
    public function setProductChannelTwigData(array $data): array
    {
        foreach ($data as &$items) {
            if ($items) {
                if ($items['type'] == ProductChannelDataType::PRODUCT) {
                    // 获取产品twig 信息
                    $items['data'] = $this->getProductChannelProductInfos($items['data']);
                }

                if ($items['type'] == ProductChannelDataType::STORES) {
                    // 获取店铺twig 信息
                    $items['data'] = $this->getProductChannelStoreProductInfos($items['data']);
                }
            }
        }

        return $data;
    }

    /**
     * @param array $productInfos
     * @return string
     * @throws Throwable
     */
    private function getProductChannelProductInfos(array $productInfos): string
    {
        $isPartner = customer()->isPartner();
        $customFields = customer()->getId();
        $data['products'] = $productInfos;
        $data['is_partner'] = $isPartner;
        $data['isLogin'] = customer()->isLogged();
        $data['login'] = url()->link('account/login');
        $data['products_total'] = 0;
        $data['download_csv_privilege'] = 0;
        $data['is_channel'] = 1;
        //下载素材包的复选框 是否显示
        if (null != $customFields && false == $isPartner) {
            $data['download_csv_privilege'] = 1;
        }

        return load()->view('channel/column_product', $data);
    }

    /**
     * @param array $storeInfo
     * @return string
     * @throws Throwable
     */
    private function getProductChannelStoreProductInfos(array $storeInfo): string
    {
        $isPartner = customer()->isPartner();
        $customFields = customer()->getId();
        $data['storeInfo'] = $storeInfo;
        $data['is_partner'] = $isPartner;
        $data['isLogin'] = customer()->isLogged();
        $data['login'] = url()->link('account/login');
        $data['products_total'] = 0;
        $data['download_csv_privilege'] = 0;
        //下载素材包的复选框 是否显示
        if (null != $customFields && false == $isPartner) {
            $data['download_csv_privilege'] = 1;
        }

        return load()->view('channel/column_store', $data);
    }
}
