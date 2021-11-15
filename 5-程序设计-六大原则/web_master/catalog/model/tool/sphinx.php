<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Enums\Search\ComplexTransactions;
use App\Logging\Logger;
use App\Models\Futures\FuturesMarginContract;
use App\Models\Margin\MarginTemplate;
use App\Models\Rebate\RebateAgreementTemplateItem;

class ModelToolSphinx extends Model
{
    protected $client;
    protected $mode;
    protected $host = SPHINX_HOST;
    protected $port = SPHINX_PORT;
    protected $index; //'oc_product_description'
    protected $match_mode;
    protected $country_id;
    const SPHINX_MIN_LIMIT = 1;
    const SPHINX_ENV = [
        'oc_product_description_test',
        'oc_product_description',
        '*',
    ];

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->client = new SphinxClient();
        $this->mode = SPH_MATCH_EXTENDED2;
        $this->match_mode = SPH_MATCH_EXTENDED2;
        $this->country_id = array_flip(Country::getViewItems())[$this->session->get('country')];

    }

    public function getSearchProductId($q, $condition)
    {
        $complexTransactionsType = $condition['complexTransactionsNo'];
        $whId = $condition['whId'];
        $length = mb_strlen(trim($q));
        if ($length <= static::SPHINX_MIN_LIMIT) {
            return false;
        }

        if (ENV_SPHINX == 'pro_test') {
            $this->index = static::SPHINX_ENV[0];
        } elseif (ENV_SPHINX == 'pro') {
            $this->index = static::SPHINX_ENV[1];
        } else {
            $this->index = static::SPHINX_ENV[2];
        }
        $this->client->SetServer($this->host, $this->port);
        $this->client->SetConnectTimeout(10);
        $this->client->SetArrayResult(true);
        $this->client->SetMatchMode($this->match_mode);
        $this->client->setlimits(0, 5000, 5000); // sphinx 只支持最多1000个sku查询
        $this->client->SetFilter('country_id', [$this->country_id]);
        $productList = [];

        if ($complexTransactionsType) {
            $complexTransactionsTypeProductsList = $this->getComplexTransactionsProductId($complexTransactionsType);
            if($complexTransactionsTypeProductsList){
                $productList[] = $complexTransactionsTypeProductsList;
            }
        }

        if($whId){
            $whIdProductsList = $this->getInventoryProductId($whId);
            if($whIdProductsList){
                $productList[] = $whIdProductsList;
            }
        }

        $allProductList = $this->getIntersectArr($productList);
        if($allProductList){
            $this->client->SetFilter('product_id', $allProductList);
        }

        $searchStrName = "@name $q";
        //$q 为含有非头尾空格的
        $verifyRet = $this->verifyHasBlankSpace($q);
        // 检测是否含有日文
        if ($verifyRet) {
            $s = explode(' ', trim($q));
            foreach ($s as $k => $v) {
                if (mb_strlen($v) <= static::SPHINX_MIN_LIMIT) {
                    return false;
                }
            }
            $resName = $this->client->Query($searchStrName, $this->index);
            $resNameFinal = [];
            if ($resName != false) {
                if ($resName['total'] != 0) {
                    $resNameFinal = array_column($resName['matches'], 'id');
                }
                Logger::search('sphinx start');
                $log['search'] = $q;
                $log['country'] = $this->session->get('country');
                $log['allProductsId'] = $resNameFinal;
                Logger::search('···' . json_encode($log) . '···');
                Logger::search('sphinx end');
                return $resNameFinal;
            }

            return false;

        } else {

            //判断是否含有日文
            if (preg_match_all('/([\x{0800}-\x{4e00}]+)/u', trim($q), $japan) || preg_match_all('/([\x{4e00}-\x{9fa5}]+)/u', trim($q), $chinese)) {
                $resName = $this->client->Query($searchStrName, $this->index);
                $resNameFinal = [];
                if ($resName != false) {
                    if ($resName['total'] != 0) {
                        $resNameFinal = array_column($resName['matches'], 'id');
                    }

                    return $resNameFinal;
                }

                return false;

            }
            $searchStr = '@(name,tag) ' . $q . ' | @(model,upc,ean,jan,isbn) ^' . $q . '$ | @(sku,mpn) *' . $q . '*';
            $res = $this->client->Query($searchStr, $this->index);
            if ($res != false) {
                if ($res['total'] != 0) {
                    Logger::search('sphinx start');
                    $log['search'] = $q;
                    $log['country'] = $this->session->get('country');
                    $log['allProductsId'] = array_column($res['matches'], 'id');
                    Logger::search('···' . json_encode($log) . '···');
                    Logger::search('sphinx end');
                    return array_column($res['matches'], 'id');
                }
                return [];
            }

            return false;

        }
    }

    /**
     * [verifyHasBlankSpace description]
     * @param $q
     * @return bool
     */
    public function verifyHasBlankSpace($q)
    {
        if (preg_match("/\s/", trim($q))) {
            return true;
        }

        return false;
    }

    public function getIntersectArr($data)
    {
        if (count($data) == 1) {
            return $data[0];
        }
        $result = [];
        foreach ($data as $k => $v) {
            if ($k == 0) {
                $result = $v;
            } else {
                $result = array_intersect($result, $v);
            }
        }

        return array_values($result);
    }

    /**
     * [getComplexTransactionsProductId description] 获取复杂交易模板中的有效的产品id
     * @param $typeId 1 rebate 2 margin 3 future 4 rebate + margin 5 rebate + future 6 future + margin 7 rebate + margin + future
     * @return array
     */
    public function getComplexTransactionsProductId($typeId)
    {
        $marginRet = [];
        $futureRet = [];
        $rebateRet = [];

        if (in_array($typeId, [
            ComplexTransactions::MARGIN,
            ComplexTransactions::REBATE_MARGIN,
            ComplexTransactions::MARGIN_FUTURE,
            ComplexTransactions::REBATE_MARGIN_FUTURE,
        ])) {
            $marginRet = MarginTemplate::query()
                ->where('is_del', YesNoEnum::NO)
                ->distinct()
                ->pluck('product_id')
                ->toArray();
        }

        if (in_array($typeId, [
            ComplexTransactions::REBATE,
            ComplexTransactions::REBATE_MARGIN,
            ComplexTransactions::REBATE_FUTURE,
            ComplexTransactions::REBATE_MARGIN_FUTURE,
        ])) {
            $rebateRet = RebateAgreementTemplateItem::query()
                ->alias('rati')
                ->leftJoinRelations(['RebateAgreementTemplate as rat'])
                ->where([
                    'rat.is_deleted' => YesNoEnum::NO,
                    'rati.is_deleted' => YesNoEnum::NO,
                ])
                ->distinct()
                ->select('rati.product_id')
                ->pluck('product_id')
                ->toArray();
        }

        if (in_array($typeId, [
            ComplexTransactions::FUTURE,
            ComplexTransactions::REBATE_FUTURE,
            ComplexTransactions::MARGIN_FUTURE,
            ComplexTransactions::REBATE_MARGIN_FUTURE,
        ])) {

            $futureRet = FuturesMarginContract::query()
                ->where([
                    'is_deleted' => YesNoEnum::NO,
                    'status' => 1, //合约状态，１售卖中，２禁用，３已售卖完成，４合约终止
                ])
                ->distinct()
                ->pluck('product_id')
                ->toArray();
        }

        return array_values(array_unique(array_merge($futureRet, $marginRet, $rebateRet)));
    }

    public function getInventoryProductId($inventory)
    {
        return db('tb_sys_warehouse_product_distribution')
                ->whereIn('warehouse_id',$inventory)
                ->where('stock_qty','>',0)
                ->distinct()
                ->pluck('product_id')
                ->toArray();
    }
}