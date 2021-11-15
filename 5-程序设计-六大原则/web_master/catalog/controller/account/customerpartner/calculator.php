<?php

use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Onsite\OnsiteFreightConfig;
use App\Helper\ProductHelper;
use App\Repositories\Onsite\OnsiteFreightRepository;

/**
 * Class ControllerAccountCustomerpartnerCalculator
 * @property ModelAccountCustomerpartnerCalculatorFreight $model_account_customerpartner_calculator_freight
 */
class ControllerAccountCustomerpartnerCalculator extends Controller
{
    public function __construct($registry)
    {
        parent::__construct($registry);

        // 如果是 buyer 则跳转到 首页
        // 如果不是美国 则跳转到 首页
        if (!$this->customer->isPartner() || $this->customer->getCountryId() != AMERICAN_COUNTRY_ID) {
            $this->response->redirectTo($this->url->link('common/home', '', true))->send();
        }

        if (!$this->customer->isLogged()) {
            $this->session->set('redirect', $this->url->link('customerpartner/seller_center/index', '', true));
            $this->response->redirectTo($this->url->link('account/login', '', true))->send();
        }

        $this->language->load('account/customerpartner/calculator');
    }

    /**
     * 运费计算器 页面展示
     */
    public function freight()
    {
        $this->document->setTitle( __('物流计算器', [], 'common') );
        $this->load->model('account/customerpartner/calculator_freight');
        $result = $this->model_account_customerpartner_calculator_freight->freight();
        $freightConfig = $this->model_account_customerpartner_calculator_freight->getFreightConfig();
        is_array($result) ? $data = $result : $data = [];
        $data = array_merge($data, $freightConfig);

        //货币符号
        $currency = $this->session->get('currency');
        $data['symbolLeft'] = $symbolLeft = $this->currency->getSymbolLeft($currency);
        $data['symbolRight'] = $symbolRight = $this->currency->getSymbolRight($currency);


        $data['separate_view'] = true;
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['margin'] = "margin-left: 18%";
        $data['footer'] = $this->load->controller('account/customerpartner/footer', ['is_show_calculator_freight' => 0]);
        $data['header'] = $this->load->controller('account/customerpartner/header');


        $dayRange = $data['warehouseQuote']['param']['DAY_RANGE']['value_list'];
        $volumeDayList = [
            "Storage Fee<br>from 0 to ".$dayRange[0]." days",
            "Storage Fee<br>from ".(intval($dayRange[0]) + 1)." to ".$dayRange[1]." days",
            "Storage Fee<br>over ".$dayRange[1]." days",
            "Storage Fee Total",
        ];
        $warehouseRental = $data['warehouseQuote']['quote']['WAREHOUSE_RENTAL']['value_list'];
        //$dayUnit = 30;
        $volumeQuoteList = [];
        foreach ($warehouseRental as $key=>$value){
            $volumeQuoteList[] =  $symbolLeft . strval($value) . $symbolRight. "/ft³•day";
        }
        $data['volumeDayList'] = json_encode($volumeDayList, JSON_UNESCAPED_UNICODE) ?: "[]";
        $data['volumeQuoteList'] = json_encode($volumeQuoteList, JSON_UNESCAPED_UNICODE) ?: "[]";
        $data['account_type'] = customer()->getAccountType();
        if ($data['account_type'] == CustomerAccountingType::GIGA_ONSIDE) {
            $data['giga_seller_quote'] = app(OnsiteFreightRepository::class)->calculateOnsiteFreightInfo(customer()->getId());
        }

        $this->response->setOutput($this->load->view('account/customerpartner/calculator/freight', $data));
    }

    /**
     * 运费计算器 计算结果
     * 调用 B2B后台管理系统 接口
     */
    public function freightDo()
    {

        $content = file_get_contents("php://input");
        $sendData = json_decode($content, true);
        if (!is_array($sendData)) {
            return $this->jsonFailed('Oops! Something went wrong and it wa probably our fault. Please try again later or report the problem to us!!!');
        }
        if ((!array_key_exists('comboFlag', $sendData)) || (!in_array($sendData['comboFlag'], ['0', '1']))) {
            return $this->jsonFailed('Item Type Required');
        }
        if (!array_key_exists('comboList', $sendData)) {
            return $this->jsonFailed('Item Type List Required');
        }
        if ($sendData['comboFlag'] == '1') {
            if (!$sendData['comboList']) {
                return $this->jsonFailed('Item Type List Empty');
            }
            foreach ($sendData['comboList'] as $key => $value) {
                if (!array_key_exists('length', $value) || $value['length'] <= 0) {
                    return $this->jsonFailed('Sub-item '.($key+1).' Length Required');
                }
                if (!array_key_exists('width', $value) || floatval($value['width']) <= 0) {
                    return $this->jsonFailed('Sub-item '.($key+1).' Width Required');
                }
                if (!array_key_exists('height', $value) || floatval($value['height']) <= 0) {
                    return $this->jsonFailed('Sub-item '.($key+1).' Height Required');
                }
                if (!array_key_exists('weight', $value) || intval($value['weight']) <= 0) {
                    return $this->jsonFailed('Sub-item '.($key+1).' Weight Required');
                }
                if (!array_key_exists('qty', $value) || intval($value['qty']) <= 0) {
                    return $this->jsonFailed('Sub-item '.($key+1).' Quantity Required');
                }
            }
        }
        if (!array_key_exists('length', $sendData)) {
            return $this->jsonFailed('Length Required');
        }
        if (!array_key_exists('width', $sendData)) {
            return $this->jsonFailed('Width Required');
        }
        if (!array_key_exists('height', $sendData)) {
            return $this->jsonFailed('Height Required');
        }
        if (!array_key_exists('weight', $sendData)) {
            return $this->jsonFailed('Weight Required');
        }
        if ($sendData['comboFlag'] == '0') {
            if (floatval($sendData['length']) <= 0) {
                return $this->jsonFailed('Length Required');
            }
            if (floatval($sendData['width']) <= 0) {
                return $this->jsonFailed('Width Required');
            }
            if (floatval($sendData['height']) <= 0) {
                return $this->jsonFailed('Height Required');
            }
            if (floatval($sendData['weight']) <= 0) {
                return $this->jsonFailed('Weight Required');
            }
        }
        if (!array_key_exists('qty', $sendData) || intval($sendData['qty']) <= 0) {
            return $this->jsonFailed('Quantity Required');
        }
        if (!array_key_exists('day', $sendData) || intval($sendData['day']) <= 0) {
            return $this->jsonFailed('Days in Stock Required');
        }


        //选在'已存在的SKU'，输入不存在的SKU
        $hasItem = $sendData['hasItem'];
        $sku = $sendData['sku'];
        if ($hasItem == '1' && $sku) {
            $this->load->model('account/customerpartner/calculator_freight');
            $isExists = $this->model_account_customerpartner_calculator_freight->productExists($this->customer->getId(), $sku);
            if (!$isExists) {
                return $this->jsonFailed('The item does not exist, if you want to calculate the shipping cost of a new product, please select New Item', [], 404000);
            }
        }

        $fromPage = 'calculatorPage';
        $ltlFlagParent = false;
        if ($hasItem == '1') {
            //系统内已存在的产品，获取客户端传入的LTL标记。(在搜索时已获取完成)
            if ($sendData['ltlFlag'] && intval($sendData['ltlFlag']) > 0) {
                $ltlFlagParent = true;
            }
        } else {
            //手动输入尺寸，需要根据尺寸半段是否要LTL标记
            if (boolval($sendData['comboFlag'])) {
                $ltlFlagParent = false;
                foreach ($sendData['comboList'] as $key => &$value) {
                    $returnFlag = ProductHelper::getProductLtlRemindLevel($value['width'], $value['length'], $value['height'], $value['weight'], $fromPage);
                    $value['ltlFlag'] = boolval($returnFlag == 2);//判断子产品ltl
                    if ($value['ltlFlag']) {
                        $ltlFlagParent = true;//combo品其中一个子产品是ltl，则父产品标记为ltl
                    }
                }
                unset($value);
            } else {
                $returnFlag = ProductHelper::getProductLtlRemindLevel($sendData['width'], $sendData['length'], $sendData['height'], $sendData['weight'], $fromPage);
                $ltlFlagParent = boolval($returnFlag == 2);
            }
        }


        $params = [];
        $params['customerId'] = intval($this->customer->getId());
        $params['comboFlag'] = boolval($sendData['comboFlag']);
        $params['dangerFlag'] = boolval($sendData['dangerFlag'] ?? 0);
        $params['comboList'] = [];
        if (boolval($sendData['comboFlag'])) {
            foreach ($sendData['comboList'] as $key => $value) {
                $tmp = [];
                $tmp['ltlFlag'] = boolval($value['ltlFlag']);
                $tmp['dangerFlag'] = boolval($value['dangerFlag'] ?? 0);
                $tmp['length'] = floatval($value['length']);
                $tmp['width'] = floatval($value['width']);
                $tmp['height'] = floatval($value['height']);
                $tmp['actualWeight'] = floatval($value['weight']);
                $tmp['qty'] = intval($value['qty']);
                $params['comboList'][] = $tmp;
            }
        }
        $params['ltlFlag'] = boolval($ltlFlagParent);
        $params['length'] = floatval($sendData['length']);
        $params['width'] = floatval($sendData['width']);
        $params['height'] = floatval($sendData['height']);
        $params['actualWeight'] = floatval($sendData['weight']);
        $params['qty'] = intval($sendData['qty']);
        $params['day'] = intval($sendData['day']);
        $data_string = json_encode($params);



        $url = B2B_MANAGEMENT_BASE_URL . '/api/freight/calculate';
        $token = B2B_MANAGEMENT_AUTH_TOKEN;
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            "Authorization: Bearer {$token}",
        ];


        $res = post_url($url, $data_string, $headers, ['CURLOPT_TIMEOUT' => 15]);
        $res = @json_decode($res, true);


        if(!is_array($res) || $res['code'] == 500){
            return $this->jsonFailed('Oops! Something went wrong and it wa probably our fault. Please try again later or report the problem to us!');
        }
        //状态码说明：
        //200.正常
        //501.标识超大件超规格产品且配置为单独询价
        //500.有误
        if ($res['code'] !== 200) {
            if (customer()->isGigaOnsiteSeller()) {
                if ($res['code'] == OnsiteFreightConfig::GIGAONSITE_CODE_NOT_CONFIG) {
                    if ($ltlFlagParent) {
                        return $this->jsonFailed('The shipping fee for the LTL  product cannot be calculated since no LTL shipping fee quote has been set.');
                    }
                    return $this->jsonFailed('The shipping fee for the product cannot be calculated since no shipping fee quote has been set.');
                } elseif ($res['code'] == OnsiteFreightConfig::GIGAONSITE_CODE_CONFIG_NO_RESULT) {
                    return $this->jsonFailed('The shipping fee for the product cannot be calculated since product dimensions exception.');
                } else {
                    return $this->jsonFailed($res['msg']);
                }
            } else {
                return $this->jsonFailed($res['msg']);
            }
        }

        $result = $res['data'];

        return $this->jsonSuccess($result, 'Success');
    }

    /**
     * 运费计算器 SKU联想
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function autocompleteProducts()
    {
        $sku = htmlspecialchars(trim($this->request->query->get('sku', '')));
        $sellerId = (int)$this->customer->getId();
        $pageSize = 5;
        $page = 1;


        if (strlen($sku) < 1) {
            return $this->jsonFailed('Error');
        }
        $filter_data = [
            'sku' => $sku,
            'sellerId' => $sellerId,
            'pageSize' => $pageSize,
        ];
        $this->load->model('account/customerpartner/calculator_freight');
        $results = $this->model_account_customerpartner_calculator_freight->autocompleteProductsBySku($filter_data);
        if (!$results) {
            return $this->jsonFailed('Not Found');
        }


        return $this->jsonSuccess($results, 'Success');
    }
}
