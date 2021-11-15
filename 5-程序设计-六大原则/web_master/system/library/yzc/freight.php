<?php
/**
 * 运送仓的运费,打包费
 * User: lxx
 * Date: 2020/4/8
 * Time: 14:07
 */

namespace Yzc;

use App\Models\Buyer\Buyer;
use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use Exception;
use ModelAccountSalesOrderMatchInventoryWindow;

class freight
{
    private $db;
    private $orm;
    private $customer;
    private $config;
    private $length_change_rate = [];

    public function __construct($registry)
    {
        $this->db = $registry->get('db');
        $this->orm = $registry->get('orm');
        $this->customer = $registry->get('customer');
        $this->config = $registry->get('config');
    }

    /**
     * @param array $productArr
     * @return array|null
     * @throws Exception
     */
    public function getFreightAndPackageFeeByProducts(array $productArr): ?array
    {
        $customer_id = $this->customer->getId();
        if (empty($customer_id)) {
            return null;
        }
        //1.获取该用户的运费费率,打包费率（buyer的运费费率通过oc_buyer表获取,seller的运费和没用维护的buyer根据基础运费费率维护）
        if ($this->customer->isPartner()) {
            $freight_rate = $this->config->get('cwf_base_cloud_freight_rate');
        } else {
            $buyer = Buyer::where('buyer_id', $customer_id)->first();
            $freight_rate = empty($buyer)
                ? $this->config->get('cwf_base_cloud_freight_rate')
                : $buyer->cloud_freight_rate;
        }
        $packageFeeUnitArr = db('oc_package_fee')
            ->where('type', '=', 1)
            ->pluck('weight_class_id as weight_id')
            ->toArray();
        //2.获取产品的长宽高重量
        $productInfos = Product::query()->with(['fee', 'combos', 'combos.setProduct', 'combos.setProduct.fee'])->find($productArr);
        $resultArray = array();
        foreach ($productInfos as $productInfo) {
            if ($productInfo->combo_flag == 0) {
                //非combo
                $productResult = $this->getNoComboFreightAndPackageFee($productInfo, $freight_rate, $packageFeeUnitArr);
                $isCombo = false;
            } else {
                //combo计算规则
                $productResult = $this->getComboFreightAndPackageFee($productInfo, $freight_rate, $packageFeeUnitArr);
                $isCombo = true;
            }
            $productResult = $this->getOverweightSurcharge($productResult, $isCombo);
            $resultArray[$productInfo->product_id] = $productResult;
        }
        return $resultArray;
    }


    /**
     * 计算打包费
     * @param float $weight 重量
     * @param array $packageFeeRateArr 打包费费率数据
     * @param int $unit 打包费计算单位
     * @return float 打包费
     * @deprecated 云送仓打包费已经统一和一件代发打包费使用一样的逻辑，故这个方法废弃，请使用$matchInventoryWindow->getFreightAndPackageFee()
     */
    public function getPackageFeeByUnit($weight, $packageFeeRateArr, $unit)
    {
        $package_fee = 0;
        foreach ($packageFeeRateArr as $packageFeeRate) {
            if ($packageFeeRate->weight_class_id == $unit && $weight > $packageFeeRate->lower_limit && $weight <= $packageFeeRate->upper_limit) {
                $package_fee = round($packageFeeRate->package_fee_rate, 2);
                break;
            }
        }
        return $package_fee;
    }

    /**
     * @param Product $productInfo
     * @param float $freight_rate
     * @param array $packageFeeUnitArr
     * @return array
     * @throws Exception
     */
    public function getNoComboFreightAndPackageFee($productInfo, $freight_rate, $packageFeeUnitArr): array
    {

        //由于云送仓仅针对于美国用户，并且长度单位,和重量单位为inch,lb
        if (!array_key_exists(3, $this->length_change_rate)) {
            $this->length_change_rate[3] = db('oc_length_class')
                ->where('length_class_id', '=', 3)
                ->value('value');
        }
        $length_change_rate = $this->length_change_rate[3];
        if ($length_change_rate == null) {
            $length_change_rate = 1;
        }
        //非combo计算规则
        $length = $productInfo->length / $length_change_rate;
        $width = $productInfo->width / $length_change_rate;
        $height = $productInfo->height / $length_change_rate;
        //102497 计算体积（立方英尺）体积向上取整
        $volumeInch = ceil(($productInfo->length / 12) * ($productInfo->width / 12) * ($productInfo->height / 12));
        //换算成立方米,体积向上保留一位小数
        $volume = ceil($length * $width * $height / 100000) / 10;
        //102497 费率用立方英尺计算
        $freight = round($volumeInch * $freight_rate, 2);

        $actualWeight = $productInfo->weight;//记录实际重量
        //核算重量
        $accountingWeight = ceil(ceil($productInfo->length) * ceil($productInfo->width) * ceil($productInfo->height) / DIM);
        $weight = ceil(max($actualWeight, $accountingWeight));
        //判断有没有该单位的打包费规则
        $weight_class_id = 5;
        if (!in_array($weight_class_id, array_values($packageFeeUnitArr))) {
            //直接转换为kg
            $weight_change_rate = db('oc_weight_class')
                ->where('weight_class_id', '=', 5)
                ->select('value')
                ->first();
            $weight_change_rate = empty($weight_change_rate) ? 1 : $weight_change_rate->value;
            $weight = $weight / $weight_change_rate;
        }
        // 获取打包费
        /** @var ModelAccountSalesOrderMatchInventoryWindow $matchInventoryWindow */
        $matchInventoryWindow = load()->model('account/sales_order/match_inventory_window');
        //combo子sku product id字段是set_product_id
        if (property_exists($productInfo, 'set_product_id')) {
            $package_fee = $matchInventoryWindow->getFreightAndPackageFee($productInfo->set_product_id, 1)['package_fee'] ?? 0;
        } else {
            $package_fee = $matchInventoryWindow->getFreightAndPackageFee($productInfo, 1)['package_fee'] ?? 0;
        }


        return [
            'qty' => $productInfo->qty ?? 1,
            'item_code' => $productInfo->sku,
            'length' => round($length, 2),
            'width' => round($width, 2),
            'height' => round($height, 2),
            'length_inch' => round($productInfo->length, 2),
            'width_inch' => round($productInfo->width, 2),
            'height_inch' => round($productInfo->height, 2),
            'freight_rate' => $freight_rate,
            'volume' => $volume,
            'volume_inch' => $volumeInch,//云送仓改使用立方英尺，外面取值请注意
            'freight' => $freight,
            'weight' => round($weight, 2),
            'actual_weight' => $actualWeight,//实际重量
            'package_fee' => $package_fee
        ];
    }

    /**
     * @param Product $productInfo
     * @param $freight_rate
     * @param $packageFeeUnitArr
     * @return array
     * @throws Exception
     */
    public function getComboFreightAndPackageFee($productInfo, $freight_rate, $packageFeeUnitArr): array
    {
        $combos = $productInfo->combos;

        $setProductArr = [];
        foreach ($combos as $combo) {
            /** @var ProductSetInfo $productArr */
            $productArr = $this->getNoComboFreightAndPackageFee($combo->setProduct, $freight_rate, $packageFeeUnitArr);
            $setProductArr[$combo->set_product_id] = $productArr;
        }
        return $setProductArr;
    }

    /**
     * 计算超重附加费
     * combo品，超重附加费会附加到第一个商品的下面
     * 如果有超重附加费：原freight字段会加上超重附加费
     * 并且会增加返回以下字段
     * overweight超重值
     * overweight_surcharge超重附加费默认0
     *
     * @param array $productInfo
     * @param bool $isCombo
     *
     * @return mixed
     */
    public function getOverweightSurcharge($productInfo, $isCombo)
    {
        if (empty($productInfo)) {
            return $productInfo;
        }
        $cwfOverweightSurchargeRate = $this->config->get('cwf_overweight_surcharge_rate');
        $cwfOverweightSurchargeMinWeight = $this->config->get('cwf_overweight_surcharge_min_weight');
        if (!$cwfOverweightSurchargeRate || !$cwfOverweightSurchargeMinWeight) {
            //没有设置则不加收附加费
            return $productInfo;
        }
        if ($isCombo) {
            //combo需要累加子sku的
            //如果是combo产品，是把combo品作为一个整体来计算超重附加费，即算出整个combo的实际重量（全部子SKU重量和），
            //再算出整个combo的总体积（全部子SKU的体积和），再去两者相除，求出combo品单位体积重量，后面的计算逻辑与单包裹产品一致
            $weight = 0;
            $volume = 0;
            foreach ($productInfo as $item) {
                $weight += ($item['actual_weight'] * $item['qty']);
                $volume += ($item['volume_inch'] * $item['qty']);
            }
            //暂存商品信息
            $productData = array_values($productInfo)[0];
        } else {
            $weight = $productInfo['actual_weight'];
            $volume = $productInfo['volume_inch'];
            //暂存商品信息
            $productData = $productInfo;
        }
        //region 1.计算产品单位体积重量=产品实际重量lb / 产品体积 ft³ (产品体积换算成立方英尺再向上取整)，结果向上取整
        $unitVolumeWeight = $this->getProductUnitVolumeWeight($weight, $volume);
        //endregion
        //region  2.计算超重值=产品单位体积重量-12
        $overweight = $unitVolumeWeight - $cwfOverweightSurchargeMinWeight;
        $productData['overweight'] = $overweight;
        if ($overweight <= 0) {
            $productData['overweight_surcharge'] = 0;
        } else {
            //region 3.计算超重附加费=超重值*超重附加费率（8.5%）*基础运费费率（1.5），结果四舍五入保留2位小数。
            $overweightSurcharge = round($overweight * $cwfOverweightSurchargeRate * $productData['freight_rate'], 2);
            $productData['overweight_surcharge'] = $overweightSurcharge;
            //endregion
        }
        //替换商品信息
        if ($isCombo) {
            $productInfo[array_keys($productInfo)[0]] = $productData;
        } else {
            $productInfo = $productData;
        }
        //endregion
        return $productInfo;
    }

    /**
     * 计算商品单位体积重量
     * 计算产品单位体积重量=产品实际重量lb / 产品体积 ft³ (产品体积换算成立方英尺再向上取整)，结果向上取整
     *
     * @param float $weight 重量（单位：英镑 lb）
     * @param float $volume 体积（单位：立方英尺 ft³）
     *
     * @return float 单位体积(结果向上取整，使用请注意)
     */
    public function getProductUnitVolumeWeight($weight, $volume)
    {
        if (!$volume || !$weight) {
            return 0;
        }
        return ceil($weight / $volume);
    }

    /**
     * 判断是否为combo品
     * @param int $product_id
     * @return bool|null
     */
    public function isCombo($product_id)
    {
        $product_info = $this->orm->table('oc_product')
            ->where('product_id', '=', $product_id)
            ->select('combo_flag')
            ->first();
        return $product_info == null ? null : $product_info->combo_flag;
    }
}
