<?php

use App\Catalog\Search\MarketingTimeLimit\HomeListOnSaleSearch;
use App\Catalog\Search\MarketingTimeLimit\HomeListWillSaleSearch;
use App\Components\Storage\StorageCloud;
use App\Components\Storage\StorageLocal;
use App\Helper\CountryHelper;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Repositories\Product\Channel\Module\FeaturedStores;
use App\Repositories\Product\Channel\Module\NewStores;
use App\Repositories\Seller\SellerRepository;

/**
 * Class ModelDesignBanner
 * @property ModelCustomerpartnerSellerCenterIndex model_customerpartner_seller_center_index
 */
class ModelDesignBanner extends Model
{
    const SELLER_URL = 'index.php?route=customerpartner/profile&id=';

    public function getBanner($bannerId, $country = null)
    {
        $query = $this->orm->table(DB_PREFIX . 'banner as b')
            ->leftJoin(DB_PREFIX . 'banner_image as bi', 'b.banner_id', '=', 'bi.banner_id');

        if (!empty($country)) {
            $query = $query->leftJoin(DB_PREFIX . 'country as c', 'c.country_id', '=', 'bi.country_id')
                ->where('c.iso_code_3', $country);
        }
        $country = session('country', 'USA');
        $countryId = CountryHelper::getCountryByCode($country);
        $customerId = $this->customer->getId();
        $this->load->model('customerpartner/seller_center/index');
        return $query->where('b.banner_id', intval($bannerId))
            ->where('b.status', 1)
            ->where('bi.language_id', intval($this->config->get('config_language_id')))
            ->where('bi.status', 1)
            ->orderBy('bi.sort_order', 'asc')
            ->get()
            ->map(function ($item) use ($countryId, $customerId) {
                $item = (array)$item;
                if (stripos($item['link'], self::SELLER_URL) !== false) {
                    // 获取seller_id
                    $t = explode('id=', $item['link']);
                    $seller_id = $t[count($t) - 1];
                    $item['score'] = 0;
                    $item['is_out_new_seller'] = app(SellerRepository::class)->isOutNewSeller($seller_id, 3);
                    $task_info = $this->model_customerpartner_seller_center_index->getSellerNowScoreTaskNumberEffective($seller_id);
                    if ($item['is_out_new_seller'] && !isset($task_info['performance_score'])) {
                        $item['new_seller_score'] = true;//评分显示 new seller
                    } else {
                        $item['new_seller_score'] = false;
                        if ($task_info) {
                            $item['score'] = isset($task_info['performance_score']) ? number_format(round($task_info['performance_score'], 2), 2) : '0';
                        }
                    }

                } else {
                    $item['score'] = 0;
                }

                ////限时限量活动
                $isMarketingTime = false;
                if ($item['title'] == 'Limited Sales Promotions') {
                    $isMarketingTime = true;
                }
                $item['isMarketingTime'] = (int)$isMarketingTime;
                
                return $item;
            })
            ->toArray();
    }


    /**
     * @param array $sellerIds
     * @param int $isEnd
     * @return array
     * @throws Exception
     */
    public function getStoresInfo(array $sellerIds,$isEnd): array
    {
        load()->model('customerpartner/seller_center/index');
        $ret = [];
        $sellerInfos = db('oc_customerpartner_to_customer')->whereIn('customer_id', $sellerIds)
            ->select(['avatar','screenname','customer_id'])
            ->get();
        foreach ($sellerInfos as $items) {
            $temp['image'] = StorageCloud::image()->getUrl($items->avatar,['w' => 120, 'h' => 120, 'check-exist' => false] );
            $temp['title'] = $items->screenname;
            $temp['link'] = $this->url->link('customerpartner/profile', ['id' => $items->customer_id]);
            $temp['score'] = 0;
            $temp['is_out_new_seller'] = app(SellerRepository::class)->isOutNewSeller($items->customer_id, 3);
            $task_info = $this->model_customerpartner_seller_center_index->getSellerNowScoreTaskNumberEffective($items->customer_id);
            if ($temp['is_out_new_seller'] && !isset($task_info['performance_score'])) {
                $temp['new_seller_score'] = true;//评分显示 new seller
            } else {
                $temp['new_seller_score'] = false;
                if ($task_info) {
                    $temp['score'] = isset($task_info['performance_score']) ? number_format(round($task_info['performance_score'], 2), 2) : '0';
                }
            }
            $ret[] = $temp;
        }
        return [$ret, $isEnd];
    }
}
