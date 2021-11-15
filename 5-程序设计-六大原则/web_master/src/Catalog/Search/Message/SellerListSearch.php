<?php

namespace App\Catalog\Search\Message;

use App\Enums\Message\MsgCustomerExtLanguageType;
use App\Models\Buyer\BuyerToSeller;
use Framework\DataProvider\Paginator;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Illuminate\Database\Eloquent\Collection;

class SellerListSearch
{
    use SearchModelTrait;

    private $customerId;
    private $needCate;

    private $searchAttributes = [
        'filter_seller_name' => '',
        'filter_language_type' => '',
        'filter_buyer_control_status' => '',
        'filter_all_seller' => 0
    ];

    public function __construct(int $customerId, int $needCate = 1)
    {
        $this->customerId = $customerId;
        $this->needCate = $needCate;
    }

    public function get($params, $isDownload = false)
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();

        $dataProvider = new QueryDataProvider($query);

        $dataProvider->setPaginator(new Paginator([
            'defaultPageSize' => 10,
        ]));

        $data['total'] = $dataProvider->getTotalCount();
        $data['paginator'] = $dataProvider->getPaginator();

        if ($isDownload) {
            $dataProvider->switchPaginator(false);
        }
        $data['list'] = $this->formatList($dataProvider->getList(), $data['paginator']);
        $data['search'] = $this->getSearchData();

        return $data;
    }

    /**
     * 获取所有Seller信息（只有名称和ID）
     *
     * @param $params
     * @return Collection
     */
    public function getAllSeller($params)
    {
        $params['filter_all_seller'] = 1;
        $this->loadAttributes($params);
        $query = $this->buildQuery();

        $dataProvider = new QueryDataProvider($query);
        $dataProvider->switchPaginator(false);

        return $dataProvider->getList();
    }

    /**
     * 格式化数据
     *
     * @param $list
     * @param $paginator
     * @return array
     * @throws \Exception
     */
    private function formatList($list, $paginator)
    {
        $data = [];
        $no = ($paginator->getPage() - 1) * $paginator->getPageSize();
        foreach ($list as $item) {
            $languageStatus = $item->language_type == null ? 0 : $item->language_type;
            $data[] = [
                'no' => ++$no,
                'screenname' => $item->screenname,
                'seller_id' => $item->seller_id,
                'number_of_transaction' => $item->number_of_transaction,
                'money_of_transaction' =>  app('registry')->get('currency')->formatCurrencyPrice($item->money_of_transaction, session()->get('currency')),
                'last_transaction_time' => $item->last_transaction_time == '1970-01-01 00:00:00' ? 'N/A' : $item->last_transaction_time, // 此处last_transaction_time sql中默认值为1970-01-01 00:00:00
                'coop_status_buyer' => $item->buyer_control_status == null ? 0 : $item->buyer_control_status,
                'coop_status_seller' => $item->seller_control_status == null ? 0 : $item->seller_control_status,
                'language_type' => $languageStatus,
                'language_format' => MsgCustomerExtLanguageType::getDescription($languageStatus),
                'is_product_subscribed' => $item['is_product_subscribed'],
                'main_cate_name' => $this->getProductCountByCate($item->buyer_id, $item->seller_id)
            ];
        }

        return $data;
    }

    protected function buildQuery()
    {
        $query = BuyerToSeller::queryRead()->alias('bts')
            ->leftJoinRelations(['sellerCustomer as sc', 'seller as ctc', 'sellerMsgExt as sme'])
            ->where('bts.buyer_id', $this->customerId);

        if ($this->searchAttributes['filter_all_seller']) {
            $query->select(['bts.seller_id', 'ctc.screenname']);
        } else {
            $query->select(['bts.*', 'ctc.screenname', 'sc.email AS seller_email', 'sme.language_type']);
        }
        if (trim($this->searchAttributes['filter_seller_name']) !== '') {
            $query->whereRaw('instr(ctc.screenname, ?)', $this->searchAttributes['filter_seller_name']);
        }
        if (trim($this->searchAttributes['filter_language_type']) !== '') {
            $query->where(function ($q) {
                if ($this->searchAttributes['filter_language_type'] == MsgCustomerExtLanguageType::NOT_LIMIT) {
                    $q->where('sme.language_type', $this->searchAttributes['filter_language_type'])->orWhereNull('sme.language_type');
                } else {
                    $q->where('sme.language_type', $this->searchAttributes['filter_language_type']);
                }
            });
        }
        if (trim($this->searchAttributes['filter_buyer_control_status']) !== '') {
            $query->where('bts.buyer_control_status', $this->searchAttributes['filter_buyer_control_status']);
        }

        return $query;
    }

    /**
     * 获取主营（产品）类别
     * Seller有效产品（店铺在售产品）的分类，产品数最高的一个分类为主营类别。Furniture分类取到二级，其他分类取到一类
     * @param int $buyerId buyerId
     * @param int $sellerId sellerId
     * @return int|string|null 主要的分类名称,没有为其他
     * @throws \Exception
     */
    private function getProductCountByCate($buyerId, $sellerId)
    {
        if (! $this->needCate) {
            return '';
        }

        $main_cate_name = 'Others';
        /** @var \ModelCatalogSearch $modelCatalogSearch */
        $modelCatalogSearch = load()->model('catalog/search');
        $categories = $modelCatalogSearch->sellerCategories($sellerId);
        $product_total_by_cate = [];
        $filter_data['seller_id'] = $sellerId;
        foreach ($categories as $id1 => $category1) {
            //是Furniture分类的走到二级，其余走到一级
            if ($category1['category_id'] == 255 && isset($category1['children'])) {
                foreach ($category1['children'] as $id2 => $category2) {
                    $filter_data['category_id'] = $category2['category_id'];
                    $tmp = $modelCatalogSearch->searchProductId($filter_data, $buyerId, 0);
                    $info = array(
                        'prduct_total' => $tmp['total'],
                        'cate_name' => $category1['name'] . ' > ' . $category2['name'],
                    );
                    $product_total_by_cate[] = $info;
                }
            } else {
                $filter_data['category_id'] = $category1['category_id'];
                $tmp = $modelCatalogSearch->searchProductId($filter_data, $buyerId, 0);
                $info = array(
                    'prduct_total' => $tmp['total'],
                    'cate_name' => $category1['name'],
                );
                $product_total_by_cate[] = $info;
            }
        }
        if ($product_total_by_cate && count($product_total_by_cate) > 0) {
            $arr = array_column($product_total_by_cate, 'prduct_total', 'cate_name');
            arsort($arr);
            if (intval(reset($arr)) > 0) {
                $main_cate_name = key($arr);
            }
        }
        return $main_cate_name;
    }
}
