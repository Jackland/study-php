<?php

use App\Catalog\Controllers\AuthController;
use App\Enums\Onsite\OnsiteFreightConfig;
use App\Models\Product\Product;
use Framework\Http\Request;
use App\Helper\ProductHelper;
use App\Models\Link\ProductToTag;

class ControllerProFreight extends AuthController
{
    //运费计算接口
    public function index(Request $request)
    {
        $freightData = $request->post();
        $checkRes = $this->checkPostData($freightData);
        if ($checkRes) {
            return $this->jsonFailed($checkRes);
        }

        $params = [];
        $params['dangerFlag'] = false;
        if (!empty($freightData['product_id'])) {
            $params['dangerFlag'] = boolval(Product::query()->where('product_id', $freightData['product_id'])->value('danger_flag'));
        }
        $params['customerId'] = intval($this->customer->getId());
        $params['requestType'] = 1; //会自定义传输ltl参数
        $params['comboFlag'] = $freightData['combo_flag'] == '1' ? true : false;
        $params['comboList'] = [];
        if ($freightData['combo_flag'] == 1) {
            $params['ltlFlag'] = false;
            $comboProductIds = array_column($freightData['combo'], 'product_id');
            $ltlProducts = ProductToTag::query()->whereIn('product_id', $comboProductIds)
                ->where('tag_id', (int)configDB('tag_id_oversize'))
                ->get();
            $dangerFlagProductIdMap = Product::query()->whereIn('product_id', $comboProductIds)->pluck('danger_flag', 'product_id');
            // 内部seller产品B2B不再标记LTL
            if ($ltlProducts->isNotEmpty() && !customer()->isInnerAccount()) {
                $params['ltlFlag'] = true;
            }
            $ltlProducts = $ltlProducts->keyBy('product_id');
            foreach ($freightData['combo'] as $key => $value) {
                $tmp = [];
                $tmp['length'] = floatval($value['length']);
                $tmp['width'] = floatval($value['width']);
                $tmp['height'] = floatval($value['height']);
                $tmp['actualWeight'] = floatval($value['weight']);
                $tmp['qty'] = (int)$value['quantity'];
                $tmp['ltlFlag'] = isset($ltlProducts[$value['product_id']]) ? true : false;
                $tmp['dangerFlag'] = boolval($dangerFlagProductIdMap->get($value['product_id'], 0));
                $params['comboList'][] = $tmp;
            }
        } else {
            if ($freightData['is_ltl'] == 0 && !customer()->isInnerAccount()) {
                $returnFlag = ProductHelper::getProductLtlRemindLevel($freightData['width'], $freightData['length'], $freightData['height'], $freightData['weight']);
                if ($returnFlag == 2) {
                    return $this->jsonFailed(__('产品运费计算失败，若要更新产品尺寸，请联系客服。', [], 'controller/freight'));//正常是走不到这一步
                }
            }
            $params['ltlFlag'] = boolval($freightData['is_ltl']);
        }
        $params['length'] = isset($freightData['length']) ? floatval($freightData['length']) : 0;
        $params['width'] = isset($freightData['width']) ? floatval($freightData['width']) : 0;
        $params['height'] = isset($freightData['height']) ? floatval($freightData['height']) : 0;
        $params['actualWeight'] = isset($freightData['weight']) ? floatval($freightData['weight']) : 0;
        $params['qty'] = 1;
        $params['day'] = 180; // 不需要返回仓租，这个字段只影响仓租，为了兼容 传个180
        $data_string = json_encode($params);

        $url = B2B_MANAGEMENT_BASE_URL . '/api/freight/calculate';
        $token = B2B_MANAGEMENT_AUTH_TOKEN;
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            "Authorization: Bearer {$token}",
        ];
        $res = post_url($url, $data_string, $headers, ['CURLOPT_TIMEOUT' => 15]);
        $res = @json_decode($res, true);
        if (!is_array($res)) {
            $errorMsg = __('哎呀！ 报错了，这可能是系统的问题。 请稍后重试或向我们报告问题！', [], 'common');
            return $this->jsonFailed($errorMsg);
        }

        // 500(501) 507 200

        if (!customer()->isGigaOnsiteSeller() && $res['code'] != 200) {
            return $this->jsonFailed($res['msg']);
        }

        $result = $res['data'] ?? [];
        if ($params['ltlFlag']) {
            $freight = $result['dropShip']['ltlFreight'] ?? '';
        } else {
            $freight = $result['dropShip']['expressFreight'] ?? '';
        }
        $dropShipPackageFee = $result['dropShip']['packageFee'] ?? '';
        $dangerFee = $result['dropShip']['dangerFee'] ?? '';
        $peakSeasonFee = $result['dropShip']['peakSeasonFee'] ?? '';
        $pickUpPackageFee = $result['pickUp']['packageFee'] ?? '';

        $originCode = $res['code'];
        if ($res['code'] == OnsiteFreightConfig::GIGAONSITE_CODE_NOT_CONFIG_LTL) {
            $res['code'] = OnsiteFreightConfig::GIGAONSITE_CODE_NOT_CONFIG;
        }

        $freightResult = [
            'ltlFlag' => intval($params['ltlFlag']), //兼容前端，java的返回接口，后续没有ltlFlag,这儿返回$params['ltlFlag']
            'freight' => $freight,
            'dropShipPackageFee' => $dropShipPackageFee,
            'dangerFee' => $dangerFee,
            'peakSeasonFee' => $peakSeasonFee,
            'pickUpPackageFee' => $pickUpPackageFee,
            'returnCode' => $res['code'], //接口状态码
            'originCode' => $originCode,
        ];
        if (customer()->isGigaOnsiteSeller() && (in_array($res['code'], OnsiteFreightConfig::getGigaOnsiteIllegalCode()) || $res['code'] == 501)) {
            $freightResult['freight'] = $freightResult['dropShipPackageFee'] = $freightResult['pickUpPackageFee'] = 'N/A';
        }
        return $this->jsonSuccess($freightResult, 'Success');
    }

    /**
     * 校验数据
     * @param array $freightData
     * @return string
     */
    public function checkPostData($freightData)
    {
        if (customer()->isPartner() == 0) {
            return 'illegal visit';
        }
        try {
            if ($freightData['combo_flag'] == 1) {
                if (!isset($freightData['combo']) || empty($freightData)) {
                    throw new Exception('Sub-item Required');
                }
                $initError = '';
                foreach ($freightData['combo'] as $combo) {
                    if ($combo['length'] <= 0 || $combo['width'] <= 0 || $combo['height'] <= 0 || $combo['weight'] <= 0 || empty($combo['product_id'])) {
                        $initError = 'Sub-item illegal';
                        break;
                    }
                }
                if ($initError) {
                    throw new Exception($initError);
                }
            } else {
                if (!isset($freightData['length']) || floatval($freightData['length']) <= 0) {
                    throw new Exception('Length Required');
                }
                if (!isset($freightData['width']) || floatval($freightData['width']) <= 0) {
                    throw new Exception('Width Required');
                }
                if (!isset($freightData['height']) || floatval($freightData['height']) <= 0) {
                    throw new Exception('Height Required');
                }
                if (!isset($freightData['weight']) || floatval($freightData['weight']) <= 0) {
                    throw new Exception('Weight Required');
                }
            }
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
        return '';
    }

}
