<?php
/**
 * Class ModelExtensionModuleCartHome
 * @property ModelAccountCartCart $model_account_cart_cart
 * @property ModelAccountProductQuoteswkproductquotes $model_account_product_quotes_wk_product_quotes
 * */
class ModelExtensionModuleCartHome extends Model {
    protected $customer_id;
    protected $is_collection_from_domicile;
    protected $country_id;
    public function __construct(Registry $registry) {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->is_collection_from_domicile = $this->customer->isCollectionFromDomicile(); //是否是上门取货 上门取货不需要加运费
        $this->country_id = $this->customer->getCountryId();
    }

    /**
     * [index description]  目的两个 1.获取采购数量 2.获取总价 3.不计算库存是否过期
     */
    public function index()
    {
        return $this->getCartInfo($this->customer_id);
    }

    /**
     * [getCartInfo description] 购物车逻辑分为云送仓和普通cart区分为delivery_type，云送仓通过体积计算freight
     * 头部的购物车信息已不需展示价格，故添加判断是否展示;
     * @param int $customer_id
     * @param bool $isShowTotal
     * @return array
     */
    public function getCartInfo($customer_id, bool $isShowTotal = false)
    {
        $api_id = isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0;
        $cart_info = $this->orm->table(DB_PREFIX.'cart')
                    ->where('customer_id',$customer_id)
                    ->where('api_id','=', $api_id)
                    ->get()
                    ->map(function ($v){
                        return (array)$v;
                    })
                    ->toArray();
        $ret = [];
        if(!$cart_info){
            $ret['quantity'] = 0;
            $ret['total_price'] = 0;
        }else{
            $total = 0;
            $volume = 0;

            if ($isShowTotal) {
                foreach($cart_info as $key => $value){
                    //算钱 根据 type_id 和 agreement_id 和 delivery
                    $line_price =  $this->getLineTotal($value);
                    if($value['delivery_type'] == 2){
                        $volume += $line_price['volume'];
                    }
                    $total += $line_price['price']*$value['quantity'];
                }
            }

            $ret['quantity'] = count($cart_info);
            if($volume !== 0 ){
                //$total += $volume * $this->config->get('cwf_base_cloud_freight_rate');
            }
            if($this->country_id == JAPAN_COUNTRY_ID){
                $ret['total_price'] = round($total);
            }else{
                $ret['total_price'] = round($total,2);
            }

        }

        return $ret;
    }

    public function getProductInfo($productId)
    {
        return $this->orm->table('oc_product as p')
            ->leftJoin('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'c2p.customer_id')
            ->where('p.product_id', $productId)
            ->select('p.price','p.buyer_flag', 'p.status', 'p.is_deleted','c.status as store_status','p.product_type',
                'c2p.customer_id as seller_id')
            ->first();
    }

    public function getLineTotal($data)
    {
        $this->load->model('account/cart/cart');
        $type_id = $data['type_id'];
        $agreement_id = $data['agreement_id'];
        $product_id = $data['product_id'];
        $default_info = $this->getProductInfo($product_id);
        $dm = $this->cart->getDelicacyManagementInfoByNoView($product_id, $this->customer_id);
        $sellerId = $default_info->seller_id;
        $hasContact = $this->model_account_cart_cart->hasContact($this->customer->getId(), $sellerId);

        //可独立售卖 商品上架 店铺上架 商品未删除 不参与精细化或精细化可见 buyer与seller建立联系 100828 add by CL
        if ($default_info->buyer_flag && $default_info->status && $default_info->store_status && !$default_info->is_deleted && $hasContact
            && (null == $dm || (isset($dm['product_display']) && $dm['product_display']))){
            $default_price = $default_info->price;
            $product_type = $default_info->product_type;

            if($type_id == 0 || $type_id == 4){
                //可能是议价的产品价格需要结合数量来看
                //精细化产品价格
                //oc_product价格
                // start of quote.
                $quote_price = 0;
                if ($type_id == 4) {
                    $this->load->model('account/product_quotes/wk_product_quotes');
                    $productQuoteDetail = $this->model_account_product_quotes_wk_product_quotes->getProductQuoteDetail($data['agreement_id']);
                    if ($productQuoteDetail) {
                        if ($data['quantity'] == $productQuoteDetail['quantity']) {
                            $quote_price = $productQuoteDetail['price'];
                        }
                    }
                }

                // end of quote.
                if($quote_price){
                    $price = $quote_price;
                }elseif(isset($dm) && $dm['product_display'] == 1 && 0 == $dm['is_rebate']){
                    $price = $dm['current_price'];
                }else{
                    $price = $default_price;
                }

            }else{
                if($product_type != 0 && $product_type != 3){
                    $price = $default_price;
                }else{
                    $transaction_info = $this->cart->getTransactionTypeInfo($type_id,$agreement_id,$product_id);
                    if($transaction_info){
                        $price = $transaction_info['price'];
                    }else{
                        $price = $default_price;
                    }

                    if(isset($transaction_info['is_valid']) && $transaction_info['is_valid'] == 0){
                        if(isset($dm) && $dm['product_display'] == 1){
                            $price = $dm['current_price'];
                        }else{
                            $price = $default_price;
                        }
                    }
                }
            }

            // $price 为我们需要的价格
            // 非欧洲、上门取货的buyer在非精细化价格、非议价时 减去运费, 不论何种类型的buyer，下单时均需记录运费
            $freightAndPackageResult = $this->getFreightAndPackageFee($product_id,1,$data['delivery_type']);
            $freight = $freightAndPackageResult['freight'];
            //获取该产品的打包费
            $packageFee = $freightAndPackageResult['package_fee'];
            $volume = $freightAndPackageResult['volume'];
            $overweightSurcharge = $freightAndPackageResult['overweight_surcharge'];
            // 云送仓的freight 需要独自计算

            //最终单价
            if($this->is_collection_from_domicile){
                $ret['price'] = $price + $packageFee;
                $ret['volume'] = $volume * $data['quantity'];
            }else{
                $ret['price'] = $price + $packageFee + $freight;
                $ret['volume'] = $volume * $data['quantity'];
            }
        }else{
            $ret = [
                'price' => 0,
                'volume'=> 0
            ];
        }

        return  $ret;

    }

    /*
     * 获取所需运费,打包费
     * 1363 返回的运费中云送仓的运费增加了超重附加费
     *
     * @param flag 0:用于计算价格（非欧洲上门取货类型的buyer返回实时有效运费，其他返回0） 1：用于记录运费（所有商品均返回实时运费）
     * */
    public function getFreightAndPackageFee($productId, $flag=0,$delivery_type = 0)
    {
        if($productId){
            $isEurope = $this->country->isEuropeCountry($this->customer->getCountryId());
            if ($flag || (!$flag && $this->customer->isCollectionFromDomicile() && !$isEurope)){
                if($delivery_type != 2) {
                    /**
                     * 打包费添加 附件打包费
                     *
                     * @since 101457
                     */
                    if ($this->customer->isCollectionFromDomicile()) {
                        $package_fee_type = 2;
                    }else{
                        $package_fee_type = 1;
                    }
                    $sql = "select p.freight,pf.fee as package_fee from oc_product as p
                        left join oc_product_fee as pf on pf.product_id = p.product_id and pf.type={$package_fee_type}
                        where p.product_id={$productId} limit 1";
                    $freight = $this->db->query($sql);
                    if ($freight->row) {
                        return array(
                            'freight' => (float)$freight->row['freight'],
                            'package_fee' => (float)$freight->row['package_fee'],
                            'volume' => 0,
                            'overweight_surcharge'=>0,
                        );
                    }
                }else{
                    //云送仓运费
                    $productArray = array($productId);
                    $freightInfos = $this->freight->getFreightAndPackageFeeByProducts($productArray);
                    $freight = 0;
                    $package_fee = 0;
                    $volume = 0;
                    $overweightSurcharge = 0;
                    if(!empty($freightInfos)){
                        if($this->freight->isCombo($productId)){
                            foreach ($freightInfos[$productId] as $freightInfo){
                                $freight += $freightInfo['freight']*$freightInfo['qty'];
                                $overweightSurcharge += $freightInfo['overweight_surcharge'] ?? 0;
                                $freight += $freightInfo['overweight_surcharge'] ?? 0;//运费加上超重附加费
                                $package_fee += $freightInfo['package_fee']*$freightInfo['qty'];
                                $volume +=  $freightInfo['volume_inch']*$freightInfo['qty'];
                            }
                        }else{
                            $freight = $freightInfos[$productId]['freight'];
                            $overweightSurcharge = $freightInfos[$productId]['overweight_surcharge'] ?? 0;
                            $freight += $freightInfos[$productId]['overweight_surcharge'] ?? 0;//运费加上超重附加费
                            $package_fee = $freightInfos[$productId]['package_fee'];
                            $volume = $freightInfos[$productId]['volume_inch'];
                        }
                    }
                    return  array(
                        'freight' =>$freight,
                        'package_fee' => $package_fee,
                        'volume' => $volume,
                        'overweight_surcharge' => $overweightSurcharge,
                    );
                }
            }
        }
        return  array(
            'freight' => 0,
            'package_fee' => 0,
            'volume' =>0,
            'overweight_surcharge' => 0,
        );
    }
}
