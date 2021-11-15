<?php

use App\Enums\Country\CountryCode;
use App\Models\Order\OrderProductInfo;

/**
 * Class ModelExtensionModuleEuropeFreight
 */
class ModelExtensionModuleEuropeFreight extends Model
{
    public $coefficient_a; // （清关费+国际运费*系数A）*系数B-国内运费
    public $coefficient_b;
    public $extra_fee;
    private $isPartner;
    const BRITAIN = 'GBR';
    const BRITAIN_COUNTRY_ID = 222;
    const GERMANY = 'DEU';
    const GERMANY_COUNTRY_ID = 81;
    const BRITAIN_ALIAS_NAME = ['UK','GB'];
    const ERROR = [
        'length is wrong',
        'max length is wrong',
        'weight is wrong',
        'second length is wrong',
        'min length is wrong',
        'child sku is wrong',
        'info is wrong',
        'from is wrong',
        'to is wrong'
    ];
    const CODE_SUCCESS = 200; // 成功返回产品信息
    const CODE_PRODUCT_ERROR = 101; // 产品因尺寸问题导致的运费计算失败
    const CODE_CONFIG_ERROR = 102;  // 后台配置欧洲运费信息导致的产品不可用
    const NOT_VAT_ADDRESS_STATUS = 103;  //#31737 超出免税地区

    public function __construct($registry)
    {
        parent::__construct($registry);
    }

    /**
     * [getFreight description]
     * @param array $data [['product_id'=>'','from'=>'','to'=>'','zip_code'=>'','line_id'=>'']]
     * @param bool $isPartner  seller 欧洲seller计算运费不需要计算产品初始freight
     * @return array
     */
    public function getFreight(array $data,$isPartner = false): array
    {
        $this->setIsPartner($isPartner);
        //获取产品对应国别以及尺寸
        $result = $products_info =  $this->getBaseProductInfo($data);
        if(count($data) == count($result['end'])){
            return $result['end'];
        }
        //区分country来进行不同的数据
        $products_info = $this->verifyProductRule($result);
        $ret = array_merge($result['end'],$products_info['end']);
        array_walk($ret,function (&$value,$key){
            if($value['code'] == self::CODE_SUCCESS){
                $value['freight'] = $this->setFreightFormat($value['freight']);
            }
        });
        return $ret;
    }

    private function setIsPartner(bool $isPartner)
    {
        $this->isPartner = $isPartner;
    }

    /**
     * [verifyProductRule description] 根据查询出的数据进行分类返回验证是否成功
     * @param array $data
     * @return array
     */
    public function verifyProductRule(array $data): array
    {
        $ret = [];
        foreach($data['data'] as $key => $value){
            if($value['from'] == self::BRITAIN){
                // seller 纯物流 && fab的freight为 0 此种情况下不进行产品尺寸的二次校验
                if($this->isPartner && $value['delivery_to_fba']){
                    $ret['end'][] = $this->checkGBRProduct($value,$data);
                }else{
                    $ret['end'][] = $this->getGBRProductRule($value,$data);
                }
            }elseif($value['from'] == self::GERMANY){
                // seller 纯物流 && fab的freight为0
                if($this->isPartner && $value['delivery_to_fba']){
                    $ret['end'][] = $this->checkDEUProduct($value,$data);
                }else{
                    $ret['end'][] = $this->getDEUProductRule($value,$data);
                }

            }
        }
        return $ret;
    }

    /**
     * [getExtraFeeByProductInfo description] 针对于德国的 获取附加费用的方法
     * @param array $value
     * @param array $extra_rule
     * @return float
     */
    public function getExtraFeeByProductInfo($value,$extra_rule)
    {
        // 获取extra_rule的计算规则
        $rule = [];
        $extra_fee = 0;
        $regular_length_fee = 0;
        $regular_volume_fee = 0;
        foreach($extra_rule as $ks => $vs){
            if($vs->max_length){
                $rule['max_length'] = $vs->max_length;
                $rule['max_length_rule'] = $vs->max_length_rule;
                $rule['min_length'] = $vs->min_length;
                $rule['min_length_rule'] = $vs->min_length_rule;
                $rule['extra_length_charge'] = $vs->extra_charge;
            }elseif($vs->volume_density){
                $rule['volume_density'] = $vs->volume_density;
                $rule['volume_density_rule'] = $vs->volume_density_rule;
                $rule['extra_volume_charge'] = $vs->extra_charge;
            }
        }

        $num = [$value['info']->length_cm,$value['info']->width_cm,$value['info']->height_cm];

        rsort($num);
        //非combo
        //规则一：最长边≥120CM或高＜3CM时，附加费用3.6£ 更改为最短边
        if( $this->getCompareResult($num[0],$rule['max_length'],$rule['max_length_rule'])
            || $this->getCompareResult($num[2],$rule['min_length'],$rule['min_length_rule'])
        ){
            $regular_length_fee = $rule['extra_length_charge'];
        }
        //规则二：长*宽*高/1000＞150CM，附加费收取4.9£
        if( $this->getCompareResult($num[0]*$num[1]*$num[2]/1000,$rule['volume_density'], $rule['volume_density_rule'])
        ){
            $regular_volume_fee = $rule['extra_volume_charge'] ;
        }
        // 逻辑变更部分满足规则一和规则二的情况下，要去价格高的
        return max($extra_fee,$regular_length_fee,$regular_volume_fee);
    }

    /**
     * [getIdealProductFreight description] 获取最终运费的方法
     * @param $value
     * @param $data
     * @param int $country_id
     * @param $extra_fee
     * @param $combo_child_info
     * @return array
     */
    public function getIdealProductFreight($value,$data,$country_id,$extra_fee = 0,$combo_child_info = null)
    {
        if($combo_child_info){
            $value['product_id'] = $combo_child_info['info']->set_product_id;
            $value['info']->weight_kg = $combo_child_info['info']->weight_kg;
            $value['info']->freight = $combo_child_info['info']->freight;
        }
        // 德国需要通过重量来获取国际运费
        switch (count($value['to_info'])) {
            case 0: // 没有对应的to国别报价
                $ret = $this->returnProduct(self::CODE_CONFIG_ERROR,$value['product_id'],$value,'tb_sys_international_order is wrong');
                break;
            case 1:// 有唯一的to国别的报价
                if($country_id == self::GERMANY_COUNTRY_ID){
                    $freight_fee = $this->getFreightByWeight($value['info']->weight_kg,$value['to_info'][0]);
                }else{
                    $freight_fee = $value['to_info'][0]->freight_fee;
                }
                $freight[$value['to_info'][0]->id] = $this->calcFreight($data['rule'][$country_id]->extra_charge_ratio,
                    $data['rule'][$country_id]->vat_ratio,
                    $value['to_info'][0]->clearance_fee ,
                    $freight_fee + $extra_fee,
                    $value['info']->freight
                );
                // combo的子sku不能取每个最优的需要取每个地区最优的
                if($combo_child_info){
                    $min_freight = $freight;
                }else{
                    $min_freight = min($freight);
                }
                $ret = $this->returnProduct(self::CODE_SUCCESS,$value['product_id'],$value,null,$min_freight);
                break;
            default:
                $freight = [];
                foreach($value['to_info'] as $ks => $vs){
                    if($country_id == self::GERMANY_COUNTRY_ID){
                        $freight_fee = $this->getFreightByWeight($value['info']->weight_kg,$vs);
                    }else{
                        $freight_fee = $value['to_info'][0]->freight_fee;
                    }
                    $freight[$vs->id] = $this->calcFreight($data['rule'][$country_id]->extra_charge_ratio,
                        $data['rule'][$country_id]->vat_ratio,
                        $vs->clearance_fee,
                        $freight_fee + $extra_fee,
                        $value['info']->freight
                    );
                }
                // combo的子sku不能取每个最优的需要取每个地区最优的
                if($combo_child_info){
                    $min_freight = $freight;
                }else{
                    $min_freight = min($freight);
                }
                $ret =  $this->returnProduct(self::CODE_SUCCESS,$value['product_id'],$value,null,$min_freight);
        }
        return $ret;
    }

    public function getFreightByWeight($weight,$regular){
        switch ($weight){
            case ($weight>= 30):
                return $regular->freight40_fee;
                break;
            case ($weight>= 25):
                return $regular->freight30_fee;
                break;
            case ($weight>= 15):
                return $regular->freight25_fee;
                break;
            case ($weight>= 10):
                return $regular->freight15_fee;
                break;
            case ($weight>= 5):
                return $regular->freight10_fee;
                break;
            case ($weight >= 2):
                return $regular->freight5_fee;
                break;
            default:
                return $regular->freight2_fee;
        }
    }

    /**
     * [calcFreight description] 根据公式计算运费
     * @param $coefficient_a
     * @param $coefficient_b
     * @param $clearance_fee
     * @param $freight_fee
     * @param $origin_fee
     * @return false|float
     */
    public function calcFreight($coefficient_a,$coefficient_b,$clearance_fee,$freight_fee,$origin_fee)
    {
        // 欧洲seller 计算补运费费用的时候不需要计算origin_fee
        if($this->isPartner){
            $origin_fee = 0;
        }
        return ($clearance_fee + $freight_fee*$coefficient_a)*$coefficient_b - $origin_fee;
    }

    public function setFreightFormat($freight)
    {
        //保留三位小数，然后向上进一位
        $ret = sprintf('%.2f', ceil(round($freight*1000)/10)/100);
        if($ret < 0 ){
            $ret = '0.00';
        }
        return $ret;
    }

    /**
     * [getBaseProductInfo description] 根据产品基本信息获取规则 额外规则 combo信息  from to 信息
     * @param $data
     * @return array
     */
    public function getBaseProductInfo($data)
    {
        $info = $this->orm->table('oc_product')
            ->whereIn('product_id',array_column($data,'product_id'))
            ->select('weight_kg','length_cm','width_cm','height_cm','product_id','freight','combo_flag')
            ->get()
            ->keyBy('product_id')
            ->toArray();
        $costOrderProductId = array_filter(array_column($data, 'order_product_id'));
        if ($costOrderProductId) {
            $costInfo = OrderProductInfo::whereIn('order_product_id', $costOrderProductId)
                ->select(['weight_kg', 'length_cm', 'width_cm', 'height_cm', 'product_id', 'combo_flag', 'order_product_id', 'freight'])
                ->get()->keyBy('order_product_id');
        }

        // combo的话进行子sku的验证
        //获取验证规则
        $rule = $this->orm->table('tb_sys_international_order_config')
            ->whereIn('country_id',EUROPE_COUNTRY_ID)
            ->where('status',1)
            ->get()
            ->keyBy('country_id')
            ->toArray();
        $extra_rule = $this->orm->table('tb_sys_international_order_extra_config')
            ->whereIn('country_id',EUROPE_COUNTRY_ID)
            ->get()
            ->toArray();
        $ret['rule'] = $rule;
        $ret['extra_rule'] = $extra_rule;
        $ret['data'] = [];
        $ret['end'] = [];
        $country_code_info = [];
        foreach($data as $key => $value){
            if(in_array($value['from'],[self::BRITAIN,self::GERMANY])){
                $country_id = $value['from'] == self::BRITAIN ? self::BRITAIN_COUNTRY_ID : self::GERMANY_COUNTRY_ID;
                // GB UK 同一写法
                if(in_array(strtoupper($value['to']),self::BRITAIN_ALIAS_NAME)){
                    $value['to'] = self::BRITAIN_ALIAS_NAME[0];
                }

                // 判断是否存在英国和德国的数据
                if(!isset($rule[$country_id])){
                    $ret['end'][] = $this->returnProduct(self::CODE_CONFIG_ERROR,$value['product_id'],$value,self::ERROR[7]);
                    continue;
                }
                // #31737 免税buyer限制销售订单的地址(德国)
                if ($this->customer->isEuVatBuyer() && strtoupper($value['to']) == CountryCode::GERMANY) {
                    $ret['end'][] = $this->returnProduct(self::NOT_VAT_ADDRESS_STATUS, $value['product_id'], $value, self::ERROR[8]);
                    continue;
                }
                // 根据 to 获取信息
                if(isset($country_code_info[$value['from'].'-'.$value['to']])){
                    $to_info = $country_code_info[$value['from'].'-'.$value['to']].'-'.$value['zip_code'];
                }else{
                    // 根据邮编和to来确认是哪一个区域
                    $to_id = $this->getCountryIdByZipCode(get_need_string($value['zip_code'],[' ','-','*','_']),$country_id,$value['to']);
                    $to_info = $this->orm->table('tb_sys_international_order')
                        ->where([
                            'country_code' => $value['to'],
                            'country_id' => $country_id,
                        ])
                        ->when($to_id, function ($query) use ($to_id) {
                            $query->where('country_code_mapping_id', $to_id);
                        })
                        ->select()
                        ->get()
                        ->toArray();

                    if(!count($to_info)){
                        //没有to的国别的数据
                        $ret['end'][] = $this->returnProduct(self::CODE_CONFIG_ERROR,$value['product_id'],$value,self::ERROR[8]);
                        continue;
                    }
                    // #31737 免税buyer限制销售订单的地址(是否为非德国的欧盟国家)
                    if ($this->customer->isEuVatBuyer()
                        && (!in_array(strtoupper($value['to']), CountryCode::getEuropeanUnionMemberCountry()) || strtoupper($value['to']) == CountryCode::GERMANY)) {
                        $ret['end'][] = $this->returnProduct(self::NOT_VAT_ADDRESS_STATUS, $value['product_id'], $value, self::ERROR[8]);
                        continue;
                    }
                    $country_code_info[$value['from'].'-'.$value['to'].'-'.$value['zip_code']] = $to_info;
                }
                $temp = $value;
                // 使用囤货库存
                if (! empty($value['order_product_id']) && isset($costInfo[$value['order_product_id']])) {
                    $temp['info'] = $costInfo[$value['order_product_id']];
                    // combo的话进行子sku的验证
                    if($temp['info']->combo_flag){
                        $temp['combo_info'] = $this->getCostComboInfo($value['order_product_id']);
                    }else{
                        $temp['combo_info'] = false;
                    }
                    $temp['to_info'] = $to_info;
                    $ret['data'][] = $temp;
                } elseif (isset($info[$value['product_id']])) {
                    $temp['info'] = $info[$value['product_id']];
                    // combo的话进行子sku的验证
                    if($temp['info']->combo_flag){
                        $temp['combo_info'] = $this->getComboInfo($value['product_id']);
                    }else{
                        $temp['combo_info'] = false;
                    }
                    $temp['to_info'] = $to_info;
                    $ret['data'][] = $temp;
                }else{
                    $ret['end'][] = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[6]);
                }
                unset($temp);
            }else{
                $ret['end'][] = $this->returnProduct(self::CODE_CONFIG_ERROR,$value['product_id'],$value,self::ERROR[7]);
            }
        }
        return $ret;

    }

    /**
     * [getComboInfo description] 获取product_id 产品信息
     * @param int $product_id
     * @return array
     */
    public function getComboInfo($product_id)
    {
        return $this->orm->table('tb_sys_product_set_info as s')
            ->where('p.product_id',$product_id)
            ->leftJoin(DB_PREFIX .'product as p','p.product_id','=','s.product_id')
            ->leftJoin(DB_PREFIX .'product as pc','pc.product_id','=','s.set_product_id')
            ->whereNotNull('s.set_product_id')
            ->select('s.set_product_id','s.qty','pc.sku','pc.weight_kg','pc.length_cm','pc.width_cm','pc.height_cm','pc.freight')
            ->orderBy('pc.sku','asc')
            ->get()
            ->keyBy('set_product_id')
            ->toArray();
    }

    /**
     * 获取囤货库存产品信息
     *
     * @param int $order_product_id 采购订单商品明细记录ID
     * @return array
     */
    public function getCostComboInfo($order_product_id)
    {
        return $this->orm->table('oc_order_product_set_info as s')
            ->where('p.order_product_id',$order_product_id)
            ->leftJoin( 'oc_order_product_info as p','p.id','=','s.order_product_info_id')
            ->whereNotNull('s.set_product_id')
            ->select('s.set_product_id','s.item_code as sku','s.weight_kg','s.length_cm','s.width_cm','s.height_cm', 's.freight')
            ->selectRaw('s.qty/p.qty as qty')
            ->orderBy('s.item_code','asc')
            ->get()
            ->keyBy('set_product_id')
            ->toArray();
    }

    public function returnProduct($code,$product_id,$value,$msg = null,$freight = null )
    {
        return array_merge($value,[
            'product_id'          => $product_id,
            'line_id'             => $value['line_id'],
            'order_product_id'    => isset($value['order_product_id'])?$value['order_product_id']:null,
            'freight'             => $freight,
            'code'                => $code,
            'msg'                 => $msg,
            'children_freight'    => isset($value['children_freight'])?$value['children_freight']:null,
        ]);
    }

    public function getCompareResult($pre,$suf,$operator = '>')
    {
        switch ($operator) {
            case '<':
                if($pre < $suf){
                    return true;
                }
                return false;
                break;
            case '≤':
                if($pre <= $suf){
                    return true;
                }
                return false;
                break;
            case '=':
                if($pre == $suf){
                    return true;
                }
                return false;
                break;
            case '>':
                if($pre > $suf){
                    return true;
                }
                return false;
                break;
            case '≥':
                if($pre >= $suf){
                    return true;
                }
                return false;
                break;
            default;
        }
        return false;
    }



    public function checkGBRProduct($value,$data): array
    {
        $flag = true;
        $ret = $this->returnProduct(self::CODE_SUCCESS,$value['product_id'],$value,null,0);
        if($value['combo_info']){
            foreach($value['combo_info'] as $kk => $vv){
                // 三个数字排序
                $num = [$vv->length_cm,$vv->width_cm,$vv->height_cm];
                rsort($num);
                //条件一：最长边+2*次长边 +2*最短边<300cm
                if(!$this->getCompareResult($num[0] + $num[1]*2 + $num[2]*2,$data['rule'][self::BRITAIN_COUNTRY_ID]->girth,$data['rule'][self::BRITAIN_COUNTRY_ID]->girth_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[0]);
                    $flag = false;
                    break;
                }
                //条件二：最长边<175cm
                if(!$this->getCompareResult($num[0], $data['rule'][self::BRITAIN_COUNTRY_ID]->max_length,$data['rule'][self::BRITAIN_COUNTRY_ID]->max_length_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[1]);
                    $flag = false;
                    break;
                }
                //条件三：重量<30KG
                if(!$this->getCompareResult($vv->weight_kg ,$data['rule'][self::BRITAIN_COUNTRY_ID]->weight,$data['rule'][self::BRITAIN_COUNTRY_ID]->weight_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[2]);
                    $flag = false;
                    break;
                }
            }

            if($flag){
                //combo为单个sku的运费的总和
                $all_freight = [];
                $allChildrenFreight = [];
                $combo_flag  = true;
                foreach($value['combo_info'] as $kk => $vv){
                    $extraVV['info'] = $vv;
                    //附加运费
                    $temp = $this->getIdealProductFreight($value,$data,self::BRITAIN_COUNTRY_ID,0,$extraVV);

                    if($temp['code'] != self::CODE_SUCCESS){
                        $combo_flag = false;
                        $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[5]);
                        break;
                    }
                }

            }
        }else{
            // 三个数字排序
            $num = [$value['info']->length_cm,$value['info']->width_cm,$value['info']->height_cm];
            rsort($num);
            //条件一：最长边+2*次长边 +2*最短边<300cm
            if(!$this->getCompareResult($num[0] + $num[1]*2 + $num[2]*2 ,$data['rule'][self::BRITAIN_COUNTRY_ID]->girth,$data['rule'][self::BRITAIN_COUNTRY_ID]->girth_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[0]);


            }
            //条件二：最长边<175cm
            if(!$this->getCompareResult($num[0] , $data['rule'][self::BRITAIN_COUNTRY_ID]->max_length,$data['rule'][self::BRITAIN_COUNTRY_ID]->max_length_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[1]);

            }
            //条件三：重量<30KG
            if(!$this->getCompareResult($value['info']->weight_kg ,$data['rule'][self::BRITAIN_COUNTRY_ID]->weight,$data['rule'][self::BRITAIN_COUNTRY_ID]->weight_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[2]);
            }
        }

        return $ret;
    }

    public function getGBRProductRule($value,$data)
    {
        $flag = true;
        $ret = [];
        if($value['combo_info']){
            foreach($value['combo_info'] as $kk => $vv){
                // 三个数字排序
                $num = [$vv->length_cm,$vv->width_cm,$vv->height_cm];
                rsort($num);
                //条件一：最长边+2*次长边 +2*最短边<300cm
                if(!$this->getCompareResult($num[0] + $num[1]*2 + $num[2]*2,$data['rule'][self::BRITAIN_COUNTRY_ID]->girth,$data['rule'][self::BRITAIN_COUNTRY_ID]->girth_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[0]);
                    $flag = false;
                    break;
                }
                //条件二：最长边<175cm
                if(!$this->getCompareResult($num[0], $data['rule'][self::BRITAIN_COUNTRY_ID]->max_length,$data['rule'][self::BRITAIN_COUNTRY_ID]->max_length_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[1]);
                    $flag = false;
                    break;
                }
                //条件三：重量<30KG
                if(!$this->getCompareResult($vv->weight_kg ,$data['rule'][self::BRITAIN_COUNTRY_ID]->weight,$data['rule'][self::BRITAIN_COUNTRY_ID]->weight_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[2]);
                    $flag = false;
                    break;
                }
            }

            if($flag){
                //combo为单个sku的运费的总和
                $all_freight = [];
                $allChildrenFreight = [];
                $combo_flag  = true;
                foreach($value['combo_info'] as $kk => $vv){
                    $extraVV['info'] = $vv;
                    //附加运费
                    $temp = $this->getIdealProductFreight($value,$data,self::BRITAIN_COUNTRY_ID,0,$extraVV);

                    if($temp['code'] != self::CODE_SUCCESS){
                        $combo_flag = false;
                        $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[5]);
                        break;
                    }
                    foreach($temp['freight'] as $kF => $vF){
                        $allChildrenFreight[$kF][$vv->set_product_id]['freight'] = $vF;
                        $allChildrenFreight[$kF][$vv->set_product_id]['qty'] = $vv->qty;
                        if(isset($all_freight[$kF])){
                            $all_freight[$kF] += $vF* $vv->qty;
                        }else{
                            $all_freight[$kF] = $vF* $vv->qty;
                        }

                    }
                }
                if($combo_flag){
                    $min = min($all_freight);
                    $minKey = array_search($min,$all_freight);
                    $value['children_freight'] = $allChildrenFreight[$minKey];
                    $ret =  $this->returnProduct(self::CODE_SUCCESS,$value['product_id'],$value,'',$min);
                }
            }
        }else{
            // 三个数字排序
            $num = [$value['info']->length_cm,$value['info']->width_cm,$value['info']->height_cm];
            rsort($num);
            //条件一：最长边+2*次长边 +2*最短边<300cm
            if(!$this->getCompareResult($num[0] + $num[1]*2 + $num[2]*2 ,$data['rule'][self::BRITAIN_COUNTRY_ID]->girth,$data['rule'][self::BRITAIN_COUNTRY_ID]->girth_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[0]);


            }
            //条件二：最长边<175cm
            if(!$this->getCompareResult($num[0] , $data['rule'][self::BRITAIN_COUNTRY_ID]->max_length,$data['rule'][self::BRITAIN_COUNTRY_ID]->max_length_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[1]);

            }
            //条件三：重量<30KG
            if(!$this->getCompareResult($value['info']->weight_kg ,$data['rule'][self::BRITAIN_COUNTRY_ID]->weight,$data['rule'][self::BRITAIN_COUNTRY_ID]->weight_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[2]);
            }

            if($flag){
                $ret = $this->getIdealProductFreight($value,$data,self::BRITAIN_COUNTRY_ID);
            }
        }


        return $ret;
    }

    public function checkDEUProduct($value,$data)
    {
        $flag = true;
        $ret = $this->returnProduct(self::CODE_SUCCESS,$value['product_id'],$value,null,0);
        if($value['combo_info']){
            //所有子sku满足条件
            //extra_fee 为子sku相加
            //重量为子sku相加
            $weight_all = 0;
            foreach($value['combo_info'] as $kk => $vv){
                $weight_all += $vv->weight_kg*$vv->qty;
                // 三个数字排序
                $num = [$vv->length_cm,$vv->width_cm,$vv->height_cm];
                rsort($num);
                //条件一：最长边<300cm
                if(!$this->getCompareResult($num[0] , $data['rule'][self::GERMANY_COUNTRY_ID]->max_length, $data['rule'][self::GERMANY_COUNTRY_ID]->max_length_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[1]);
                    $flag = false;
                    break;
                }
                //条件二：中间边≤80cm
                if( !$this->getCompareResult($num[1] , $data['rule'][self::GERMANY_COUNTRY_ID]->second_length, $data['rule'][self::GERMANY_COUNTRY_ID]->second_length_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[3]);
                    $flag = false;
                    break;
                }
                //条件三：最小边≤60cm
                if( !$this->getCompareResult($num[2] , $data['rule'][self::GERMANY_COUNTRY_ID]->min_length, $data['rule'][self::GERMANY_COUNTRY_ID]->min_length_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[4]);
                    $flag = false;
                    break;
                }
                //条件四：最长边+2*次长边 +2*最短边<300cm
                if( !$this->getCompareResult($num[0] + $num[1]*2 + $num[2]*2 ,$data['rule'][self::GERMANY_COUNTRY_ID]->girth,$data['rule'][self::GERMANY_COUNTRY_ID]->girth_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[0]);
                    $flag = false;
                    break;
                }
                //条件五：重量<30KG
                if(!$this->getCompareResult($vv->weight_kg , $data['rule'][self::GERMANY_COUNTRY_ID]->weight, $data['rule'][self::GERMANY_COUNTRY_ID]->weight_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[2]);
                    $flag = false;
                    break;
                }
            }
            if($flag){
                //combo为单个sku的运费的总和
                $all_freight = [];
                $allChildrenFreight = [];
                $combo_flag  = true;
                foreach($value['combo_info'] as $kk => $vv){
                    //附加运费
                    $extraVV['info'] = $vv;
                    $extra_fee = $this->getExtraFeeByProductInfo($extraVV,$data['extra_rule']);
                    $temp = $this->getIdealProductFreight($value,$data,self::GERMANY_COUNTRY_ID,$extra_fee,$extraVV);
                    if($temp['code'] != self::CODE_SUCCESS){
                        $combo_flag = false;
                        $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[5]);
                        break;
                    }
                }

            }
        }else{
            // 三个数字排序
            $num = [$value['info']->length_cm,$value['info']->width_cm,$value['info']->height_cm];
            rsort($num);
            //条件一：最长边<300cm
            if( !$this->getCompareResult($num[0] , $data['rule'][self::GERMANY_COUNTRY_ID]->max_length,$data['rule'][self::GERMANY_COUNTRY_ID]->max_length_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[1]);
            }
            //条件二：中间边≤80cm
            if( !$this->getCompareResult($num[1] , $data['rule'][self::GERMANY_COUNTRY_ID]->second_length,$data['rule'][self::GERMANY_COUNTRY_ID]->second_length_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[3]);
            }
            //条件三：最小边≤60cm
            if( !$this->getCompareResult($num[2] , $data['rule'][self::GERMANY_COUNTRY_ID]->min_length,$data['rule'][self::GERMANY_COUNTRY_ID]->min_length_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[4]);
            }
            //条件四：最长边+2*次长边 +2*最短边<300cm
            if( !$this->getCompareResult($num[0] + $num[1]*2 + $num[2]*2 ,$data['rule'][self::GERMANY_COUNTRY_ID]->girth,$data['rule'][self::GERMANY_COUNTRY_ID]->girth_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[0]);
            }
            //条件五：重量<30KG
            if(!$this->getCompareResult($value['info']->weight_kg , $data['rule'][self::GERMANY_COUNTRY_ID]->weight,$data['rule'][self::GERMANY_COUNTRY_ID]->weight_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[2]);
            }
            //附加运费
        }
        return $ret;
    }

    public function getDEUProductRule($value,$data)
    {
        $flag = true;
        $ret = [];
        if($value['combo_info']){
            //所有子sku满足条件
            //extra_fee 为子sku相加
            //重量为子sku相加
            $weight_all = 0;
            foreach($value['combo_info'] as $kk => $vv){
                $weight_all += $vv->weight_kg*$vv->qty;
                // 三个数字排序
                $num = [$vv->length_cm,$vv->width_cm,$vv->height_cm];
                rsort($num);
                //条件一：最长边<300cm
                if(!$this->getCompareResult($num[0] , $data['rule'][self::GERMANY_COUNTRY_ID]->max_length, $data['rule'][self::GERMANY_COUNTRY_ID]->max_length_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[1]);
                    $flag = false;
                    break;
                }
                //条件二：中间边≤80cm
                if( !$this->getCompareResult($num[1] , $data['rule'][self::GERMANY_COUNTRY_ID]->second_length, $data['rule'][self::GERMANY_COUNTRY_ID]->second_length_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[3]);
                    $flag = false;
                    break;
                }
                //条件三：最小边≤60cm
                if( !$this->getCompareResult($num[2] , $data['rule'][self::GERMANY_COUNTRY_ID]->min_length, $data['rule'][self::GERMANY_COUNTRY_ID]->min_length_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[4]);
                    $flag = false;
                    break;
                }
                //条件四：最长边+2*次长边 +2*最短边<300cm
                if( !$this->getCompareResult($num[0] + $num[1]*2 + $num[2]*2 ,$data['rule'][self::GERMANY_COUNTRY_ID]->girth,$data['rule'][self::GERMANY_COUNTRY_ID]->girth_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[0]);
                    $flag = false;
                    break;
                }
                //条件五：重量<30KG
                if(!$this->getCompareResult($vv->weight_kg , $data['rule'][self::GERMANY_COUNTRY_ID]->weight, $data['rule'][self::GERMANY_COUNTRY_ID]->weight_rule)){
                    $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[2]);
                    $flag = false;
                    break;
                }
            }
            if($flag){
                //combo为单个sku的运费的总和
                $all_freight = [];
                $allChildrenFreight = [];
                $combo_flag  = true;
                foreach($value['combo_info'] as $kk => $vv){
                    //附加运费
                    $extraVV['info'] = $vv;
                    $extra_fee = $this->getExtraFeeByProductInfo($extraVV,$data['extra_rule']);
                    $temp = $this->getIdealProductFreight($value,$data,self::GERMANY_COUNTRY_ID,$extra_fee,$extraVV);
                    if($temp['code'] != self::CODE_SUCCESS){
                        $combo_flag = false;
                        $ret = $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[5]);
                        break;
                    }
                    foreach($temp['freight'] as $kF => $vF){
                        $allChildrenFreight[$kF][$vv->set_product_id]['freight'] = $vF;
                        $allChildrenFreight[$kF][$vv->set_product_id]['qty'] = $vv->qty;
                        if(isset($all_freight[$kF])){
                            $all_freight[$kF] += $vF* $vv->qty;
                        }else{
                            $all_freight[$kF] = $vF* $vv->qty;
                        }
                    }
                }
                if($combo_flag){
                    $min = min($all_freight);
                    $minKey = array_search($min,$all_freight);
                    $value['children_freight'] = $allChildrenFreight[$minKey];
                    $ret =  $this->returnProduct(self::CODE_SUCCESS,$value['product_id'],$value,'',$min);
                }
            }
        }else{
            // 三个数字排序
            $num = [$value['info']->length_cm,$value['info']->width_cm,$value['info']->height_cm];
            rsort($num);
            //条件一：最长边<300cm
            if( !$this->getCompareResult($num[0] , $data['rule'][self::GERMANY_COUNTRY_ID]->max_length,$data['rule'][self::GERMANY_COUNTRY_ID]->max_length_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[1]);
            }
            //条件二：中间边≤80cm
            if( !$this->getCompareResult($num[1] , $data['rule'][self::GERMANY_COUNTRY_ID]->second_length,$data['rule'][self::GERMANY_COUNTRY_ID]->second_length_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[3]);
            }
            //条件三：最小边≤60cm
            if( !$this->getCompareResult($num[2] , $data['rule'][self::GERMANY_COUNTRY_ID]->min_length,$data['rule'][self::GERMANY_COUNTRY_ID]->min_length_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[4]);
            }
            //条件四：最长边+2*次长边 +2*最短边<300cm
            if( !$this->getCompareResult($num[0] + $num[1]*2 + $num[2]*2 ,$data['rule'][self::GERMANY_COUNTRY_ID]->girth,$data['rule'][self::GERMANY_COUNTRY_ID]->girth_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[0]);
            }
            //条件五：重量<30KG
            if(!$this->getCompareResult($value['info']->weight_kg , $data['rule'][self::GERMANY_COUNTRY_ID]->weight,$data['rule'][self::GERMANY_COUNTRY_ID]->weight_rule)){
                return $this->returnProduct(self::CODE_PRODUCT_ERROR,$value['product_id'],$value,self::ERROR[2]);
            }
            //附加运费
            $extra_fee = $this->getExtraFeeByProductInfo($value,$data['extra_rule']);
            if($flag){
                return $this->getIdealProductFreight($value,$data,self::GERMANY_COUNTRY_ID,$extra_fee);
            }
        }
        return $ret;
    }

    public function getCountryIdByZipCode($zip_code,$country_id,$to)
    {
        return $this->orm->table('tb_sys_international_order_postcode as p')
            ->leftJoin('tb_sys_country_code_mapping as m','m.id','=','p.country_code_mapping_id')
            ->where([
                ['p.postcode','like',"%{$zip_code}%"],
                ['p.country_id','=',$country_id],
                ['m.country_code','=',$to],
            ])
            ->value('m.id');
    }
}

