<?php

namespace App\Catalog\Forms\Calculator;

use App\Enums\Common\CountryEnum;
use App\Helper\ProductHelper;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use App\Models\Product\Tag;
use Framework\Helper\Json;
use Framework\Model\RequestForm\RequestForm;
use JsonException;
use Throwable;

class PriceCalculator extends RequestForm
{

    /**
     * 产品id
     * @var $productId
     */
    public $productId;
    /**
     * 长
     * @var float $length
     */
    public $length;
    /**
     * 宽
     * @var float $length
     */
    public $width;
    /**
     * 高
     * @var float $length
     */
    public $height;
    /**
     * 重量
     * @var float $weight
     */
    public $weight;
    /**
     * 数量
     * @var int $quantity
     */
    public $quantity = 1;
    /**
     * @var int $day
     */
    public $day;
    /**
     * @var int $customerId
     */
    public $customerId;

    private $msgMap;

    public function __construct()
    {
        parent::__construct();
        $this->msgMap = [
            'The shipping fee for the product cannot be calculated since no shipping fee quote has been set.'
            => __('输入值不符合要求，请手动输入运费、打包费及仓租费', [], 'account/sidebar/price_calculator'),
            'The shipping fee for the LTL product cannot be calculated since no LTL shipping fee quote has been set.'
            => __('输入值不符合要求，请手动输入运费、打包费及仓租费', [], 'account/sidebar/price_calculator'),
            'The Chargeable Weigh of this product exceeds 1000 lbs, which exceeds the size limit, so the quotation cannot be calculated'
            => __('此产品的计费重量超过1000磅，超出尺寸限制，无法计算报价', [], 'account/sidebar/price_calculator'),
            'FAIL' => __('系统异常，请稍后重试', [], 'account/sidebar/price_calculator'),
        ];
    }

    protected function getRules(): array
    {
        if (!request()->post('productId', '')) {
            $regex = "/^(?=.+)(?:[1-9]\d*|0)?(?:\.\d+)?$/";
            return [
                'length' => ['required', 'regex:' . $regex],
                'width' => ['required', 'regex:' . $regex],
                'height' => ['required', 'regex:' . $regex],
                'weight' => ['required', 'regex:' . $regex],
                'day' => ['required', 'integer', 'min:1'],
                'customerId' => ['required', 'integer', 'min:1'],
            ];
        }
        return [
            'productId' => ['required', 'integer', 'min:1'],
            'customerId' => ['required', 'integer', 'min:1'],
            'day' => ['required', 'integer', 'min:1'],
        ];

    }

    /**
     * @return array|string
     * @throws Throwable
     */
    public function getData()
    {
        // 由于目前通过长宽高获取运费、打包费只有美国外部才有，因此需要单独讨论
        if (Customer::find($this->customerId)->country_id == CountryEnum::AMERICA) {
            return $this->getUSAData();
        }
        return $this->getOtherData();
    }

    /**
     * @throws JsonException
     */
    private function getUSAData()
    {
        // 根据是否输入产品id 组合成不同的参数
        $params = !empty($this->productId) ? $this->getCalculateProductParams() : $this->getCalculateInputParams();
        $url = B2B_MANAGEMENT_BASE_URL . '/api/freight/calculate';
        $token = B2B_MANAGEMENT_AUTH_TOKEN;
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            "Authorization: Bearer $token",
        ];
        $res = post_url($url, json_encode($params), $headers, ['CURLOPT_TIMEOUT' => 15]);
        $res = Json::decode($res);
        if (!is_array($res) || $res['code'] == 500) {
            return __('系统异常，请稍后重试', [], 'account/sidebar/price_calculator');
        }
        //状态码说明：
        //200.正常
        //501.标识超大件超规格产品且配置为单独询价
        //500.有误
        ['code' => $code, 'msg' => $msg] = $res;
        if ($code != 200) {
            return $this->msgMap[$msg] ?? $msg;
        }
        return $res['data'] ?? [];
    }

    private function getOtherData(): array
    {
        $ret = [
            'dropShip' => [
                'expressFreight' => 0,
            ],
            'pickUp' => [
                'packageFee' => 0,
            ]
        ];
        $ret['wareHouseRental']['feeTotal'] = 0;
        return $ret;
    }

    private function getCalculateInputParams(): array
    {
        $returnFlag = ProductHelper::getProductLtlRemindLevel(
            $this->width, $this->length, $this->height, $this->weight, 'calculatorPage'
        );
        $params = [];
        $params['customerId'] = $this->customerId;
        $params['comboFlag'] = 0;
        $params['comboList'] = [];
        $params['ltlFlag'] = $returnFlag == 2;
        $params['length'] = $this->length;
        $params['width'] = $this->width;
        $params['height'] = $this->height;
        $params['actualWeight'] = $this->weight;
        $params['dangerFlag'] = false;
        $params['qty'] = $this->quantity;
        $params['day'] = $this->day;
        return $params;
    }

    private function getCalculateProductParams(): array
    {
        $productId = $this->productId;
        $product = Product::query()->with(['tags', 'customerPartner', 'combos',])->find($productId);
        $ltlFlag = $product->tags->filter(function (Tag $p) {
            return $p->tag_id == 1;
        })->isNotEmpty();
        $isCombo = (bool)$product->combo_flag;
        $params = [];
        $params['customerId'] = $this->customerId;
        $params['comboFlag'] = $product->combo_flag;
        $params['comboList'] = [];
        $params['ltlFlag'] = $ltlFlag;
        $params['length'] = !$isCombo ? $product->length : 0;
        $params['width'] = !$isCombo ? $product->width : 0;
        $params['height'] = !$isCombo ? $product->height : 0;
        $params['actualWeight'] = !$isCombo ? $product->weight : 0;
        $params['dangerFlag'] = (bool)$product->danger_flag;
        $params['qty'] = $this->quantity;
        $params['day'] = $this->day;
        if ($product->combo_flag && $product->combos->isNotEmpty()) {
            /** @var ProductSetInfo $combo */
            foreach ($product->combos as $combo) {
                $sonProduct = $combo->setProduct;
                $sonLtlFlag = $sonProduct->tags->filter(function (Tag $p) {
                    return $p->tag_id == 1;
                })->isNotEmpty();
                $tmp = [];
                $tmp['ltlFlag'] = $sonLtlFlag;
                $tmp['length'] = $sonProduct->length;
                $tmp['width'] = $sonProduct->width;
                $tmp['height'] = $sonProduct->height;
                $tmp['actualWeight'] = $sonProduct->weight;
                $tmp['qty'] = $combo->qty;
                $params['comboList'][] = $tmp;
            }
        }
        return $params;
    }

}
