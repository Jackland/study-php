<?php

namespace Cart;

use App\Enums\Cart\CartAddCartType;
use App\Enums\Common\CountryEnum;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Helper\MoneyHelper;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Product\ProductPriceRepository;
use App\Repositories\Seller\SellerProductRatioRepository;
use Framework\App;
use Illuminate\Database\Query\Expression;
use ModelCheckoutPreOrder;

/**
 * Class Cart
 * @package Cart
 * @property \Illuminate\Database\Capsule\Manager $orm 数据库
 * @property \DB $db 数据库
 * @property \Config $config 基础配置
 * @property Customer $customer 用户
 * @property Country $country 国家
 * @property \Session $session Session
 * @property Tax $tax 税
 * @property Weight $weight 重量
 */
class Cart
{
	private $data = array();

    /**
     * Cart constructor.
     * @param \Registry $registry
     */
    public function __construct($registry)
    {
		$this->config = $registry->get('config');
		$this->customer = $registry->get('customer');
		$this->country = $registry->get('country');
		$this->session = $registry->get('session');
		$this->db = $registry->get('db');
		$this->tax = $registry->get('tax');
		$this->weight = $registry->get('weight');
        $this->orm = $registry->get('orm');
        $this->freight = $registry->get('freight');

		// Remove all the expired carts with no customer ID
//		$this->db->query("DELETE FROM " . DB_PREFIX . "cart WHERE (api_id > '0' OR customer_id = '0') AND date_added < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

        // 此处逻辑为 未登录加购后，登录后将这些产品加到该用户下，因现在不登录不能加购，将此处逻辑注释
        // #31625 因在处理自动购买时，用户登录页面会出现等待锁超时的几率
//		if ($this->customer->getId()) {
			// We want to change the session ID on all the old items in the customers cart
//			$this->db->query("UPDATE " . DB_PREFIX . "cart SET session_id = '" . $this->db->escape($this->session->getId()) . "' WHERE api_id = '0' AND customer_id = '" . (int)$this->customer->getId() . "'");

			// Once the customer is logged in we want to update the customers cart
//			$cart_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "cart WHERE api_id = '0' AND customer_id = '0' AND session_id = '" . $this->db->escape($this->session->getId()) . "'");

//			foreach ($cart_query->rows as $cart) {
//				$this->db->query("DELETE FROM " . DB_PREFIX . "cart WHERE cart_id = '" . (int)$cart['cart_id'] . "'");

				// The advantage of using $this->add is that it will check if the products already exist and increaser the quantity if necessary.
//				$this->add($cart['product_id'], $cart['quantity'], json_decode($cart['option']), $cart['recurring_id']);
//			}
//		}
	}

    /**
     * @param array $product 产品
     * @param int $customer_id SellerId
     * @return array
     */
    public function calculateCommission($product, $customer_id)
    {

              if($product) {
                $categories_array = $this->db->query("SELECT p2c.category_id,c.parent_id FROM ".DB_PREFIX."product_to_category p2c LEFT JOIN ".DB_PREFIX."category c ON (p2c.category_id = c.category_id) WHERE p2c.product_id = '".(int)$product['product_id']."' ORDER BY p2c.product_id ");

                if($this->config->get('marketplace_commissionworkedon'))
                  $categories = $categories_array->rows;
                else
                  $categories = array($categories_array->row);

                //get commission array for priority
                $commission = $this->config->get('marketplace_boxcommission');
                $commission_amount = 0;
                $commission_type = '';

                if($commission)
                  foreach($commission as $various) {
                    switch ($various) {
                      case 'category': //get all parent category according to product and process
                        if(isset($categories[0]) && $categories[0]){
                          foreach($categories as $category) {
                            if($category['parent_id']==0){
                              $category_commission = $query = $this->db->query("SELECT * FROM ".DB_PREFIX."customerpartner_commission_category WHERE category_id = '" . (int)$category['category_id'] . "'")->row;
                              if($category_commission){
                                $commission_amount += ( $category_commission['percentage'] ? ($category_commission['percentage']*$product['product_total']/100) : 0 ) + $category_commission['fixed'];
                              }
                            }
                          }
                          $commission_type = 'Category Based';
                          if($commission_amount)
                            break;
                        }

                      case 'category_child': //get all child category according to product and process
                        if(isset($categories[0]) && $categories[0]){

                          foreach($categories as $category){
                            if($category['parent_id'] > 0){
                              $category_commission = $query = $this->db->query("SELECT * FROM ".DB_PREFIX."customerpartner_commission_category WHERE category_id = '" . (int)$category['category_id'] . "'")->row;
                              if($category_commission){
                                $commission_amount += ( $category_commission['percentage'] ? ($category_commission['percentage']*$product['product_total']/100) : 0 ) + $category_commission['fixed'];
                              }
                            }
                          }

                          $commission_type = 'Category Child Based';
                          if($commission_amount)
                            break;
                        }
                      default: //just get all amount and process on that (precentage based)
                        $customer_commission = $query = $this->db->query("SELECT commission FROM ".DB_PREFIX."customerpartner_to_customer WHERE customer_id = '" . (int)$customer_id . "'")->row;
                        if($customer_commission) {
                          $commission_amount += $customer_commission['commission'] ? ($customer_commission['commission']*$product['product_total']/100) : 0;
                        }

                        $commission_type = 'Partner Fixed Based';
                        break;
                    }
                    if($commission_amount)
                      break;
                  }
                $return_array = array(
                  'commission' => $commission_amount,
                  'customer' => $product['product_total']- $commission_amount,
                  'type' => $commission_type,
                );
                return($return_array);
              }
            }


    /**
     * 遍历购物车里的商品
     *
     * @param null $buyer_id 目前只有自动购买调用的时候，才会设置buyer_id参数，所以，如果buyer_id存在，则表明是自动购买。$auto_buy = true; chenyang
     * @param int $delivery_type 0:原始方法，2：云送仓,-1:查询所有产品    --add by zjg
     * @param array $cart_id_arr 指定购物车cart ID, 用于给购物车部分商品下单 2020/05/19 CL
     * @return array
     */
    public function getProducts($buyer_id = null, $delivery_type = -1, $cart_id_arr = [])
    {
		$product_data = [];
        $auto_buy = false;
        // 用户国家 初始化
        $customerCountry = null;

        if(!isset($buyer_id)){
            //未设置buyer_id参数，非自动购买
            $buyer_id = $this->customer->getId();
            $customerCountry = $this->customer->getCountryId();
        }else{
            //设置buyer_id参数，自动购买
            $auto_buy = true;
            $country_query = $this->db->query("SELECT country_id FROM oc_customer WHERE customer_id = " . (int)$buyer_id);
            if(isset($country_query->row['country_id']) && !empty($country_query->row['country_id'])){
                $customerCountry = $country_query->row['country_id'];
            }
        }

        // 查询购物车
        if (!empty($cart_id_arr)){
            $carts = $this->orm->table(DB_PREFIX.'cart')
                ->whereIn('cart_id', $cart_id_arr)
                ->orderBy('cart_id')
                ->get();
        }else{
            $api_id = (int)session('api_id', 0);
            $carts = $this->orm->table(DB_PREFIX.'cart')
                ->where('customer_id', $buyer_id)
                ->where('api_id','=', $api_id)
                ->when($delivery_type > -1, function ($query) use ($delivery_type){
                    if (2 == $delivery_type){
                        return $query->where('delivery_type', '=', $delivery_type);
                    }else{
                        return $query->whereIn('delivery_type', [0,1]);
                    }
                })
                ->orderBy('cart_id')
                ->get();
        }

        $carts = obj2array($carts);
		foreach ($carts as $cart) {
			$stock = true;

            $product_query = $this->orm->table('oc_product as p')
                ->leftJoin('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
                ->leftJoin('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'p.product_id')
                ->leftJoin('oc_customerpartner_to_customer as c2c', 'c2c.customer_id', '=', 'c2p.customer_id')
                ->leftJoin('oc_customer as cus', 'c2p.customer_id', '=', 'cus.customer_id' )
                ->where([
                    'p.product_id'  => $cart['product_id'],
                    'pd.language_id'=> (int)$this->config->get('config_language_id'),
                    //'cus.status'    => 1
                ])
                ->where('p.date_available', '<=', date('Y-m-d'))
                ->select('p.product_id','p.price','p.product_type','p.quantity','p.buyer_flag','pd.name','p.model','p.shipping','p.danger_flag',
                    'p.image', 'p.combo_flag','p.minimum','p.subtract','p.points','p.tax_class_id','p.weight','p.weight_class_id',
                    'p.length','p.width','p.height','p.length_class_id','p.sku','p.mpn','c2c.customer_id','cus.customer_group_id',
                    'c2c.screenname','p.status','cus.status as store_status')
                ->first();
            $product_query = obj2array($product_query);

            // 购物车数量>0,
			if (!empty($product_query) && ($cart['quantity'] > 0)) {
                //自动购买流程调用的话，需要校验子combo数量，校验combo品库存
                $sub_product_array = [];
                if($auto_buy){
                    $sub_product_array = $this->checkComboStock($cart['product_id'], $cart['quantity']);
                }

				$option_price = 0;
				$option_points = 0;
				$option_weight = 0;

				$option_data = [];
                // 购物车产品选项数据判断
                /*
                 * comment by LiLei
                 * 在OpenCart中，我们可以对每个产品再次进行"分类"，比如按照尺寸、颜色、型号等。
                 * 不同的产品分类对应的价格有可能不一样，比如，一个产品有红色和黄色，红色比正常价格高3元，黄色比正常价格低10元，
                 * 遇到这种情况，原始的OpenCart有Option的概念 http://docs.opencart.com/en-gb/catalog/product/option/
                 * Option所涉及到的表有：
                 * oc_option(Option表)
                 * oc_option_description(Option描述表)
                 * oc_option_value(Option值表)
                 * oc_option_value_description(Option值描述表)
                 * oc_order_option(订单Option表)
                 * oc_product_option(产品-Option关联表)
                 * oc_product_option_value(产品Option值)
                 * 下面的代码是，查询购物车产品有没有涉及到Option
                 * 如果涉及到Option，根据Option类型来处理不同的业务
                 *
                 * 注：该业务逻辑B2B暂时没有使用，如果后续有用到该逻辑，请将该条备注注释删除!
                 */
				foreach (json_decode($cart['option']) as $product_option_id => $value) {
					$option_query = $this->db->query("SELECT po.product_option_id, po.option_id, od.name, o.type FROM " . DB_PREFIX . "product_option po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE po.product_option_id = '" . (int)$product_option_id . "' AND po.product_id = '" . (int)$cart['product_id'] . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");

					if ($option_query->num_rows) {
						if ($option_query->row['type'] == 'select' || $option_query->row['type'] == 'radio') {
							$option_value_query = $this->db->query("SELECT pov.option_value_id, ovd.name, pov.quantity, pov.subtract, pov.price, pov.price_prefix, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_option_value_id = '" . (int)$value . "' AND pov.product_option_id = '" . (int)$product_option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

							if ($option_value_query->num_rows) {
								if ($option_value_query->row['price_prefix'] == '+') {
									$option_price += $option_value_query->row['price'];
								} elseif ($option_value_query->row['price_prefix'] == '-') {
									$option_price -= $option_value_query->row['price'];
								}

								if ($option_value_query->row['points_prefix'] == '+') {
									$option_points += $option_value_query->row['points'];
								} elseif ($option_value_query->row['points_prefix'] == '-') {
									$option_points -= $option_value_query->row['points'];
								}

								if ($option_value_query->row['weight_prefix'] == '+') {
									$option_weight += $option_value_query->row['weight'];
								} elseif ($option_value_query->row['weight_prefix'] == '-') {
									$option_weight -= $option_value_query->row['weight'];
								}

								if ($option_value_query->row['subtract'] && (!$option_value_query->row['quantity'] || ($option_value_query->row['quantity'] < $cart['quantity']))) {
									$stock = false;
								}

								$option_data[] = array(
									'product_option_id'       => $product_option_id,
									'product_option_value_id' => $value,
									'option_id'               => $option_query->row['option_id'],
									'option_value_id'         => $option_value_query->row['option_value_id'],
									'name'                    => $option_query->row['name'],
									'value'                   => $option_value_query->row['name'],
									'type'                    => $option_query->row['type'],
									'quantity'                => $option_value_query->row['quantity'],
									'subtract'                => $option_value_query->row['subtract'],
									'price'                   => $option_value_query->row['price'],
									'price_prefix'            => $option_value_query->row['price_prefix'],
									'points'                  => $option_value_query->row['points'],
									'points_prefix'           => $option_value_query->row['points_prefix'],
									'weight'                  => $option_value_query->row['weight'],
									'weight_prefix'           => $option_value_query->row['weight_prefix']
								);
							}
						} elseif ($option_query->row['type'] == 'checkbox' && is_array($value)) {
							foreach ($value as $product_option_value_id) {
								$option_value_query = $this->db->query("SELECT pov.option_value_id, pov.quantity, pov.subtract, pov.price, pov.price_prefix, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix, ovd.name FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id) WHERE pov.product_option_value_id = '" . (int)$product_option_value_id . "' AND pov.product_option_id = '" . (int)$product_option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

								if ($option_value_query->num_rows) {
									if ($option_value_query->row['price_prefix'] == '+') {
										$option_price += $option_value_query->row['price'];
									} elseif ($option_value_query->row['price_prefix'] == '-') {
										$option_price -= $option_value_query->row['price'];
									}

									if ($option_value_query->row['points_prefix'] == '+') {
										$option_points += $option_value_query->row['points'];
									} elseif ($option_value_query->row['points_prefix'] == '-') {
										$option_points -= $option_value_query->row['points'];
									}

									if ($option_value_query->row['weight_prefix'] == '+') {
										$option_weight += $option_value_query->row['weight'];
									} elseif ($option_value_query->row['weight_prefix'] == '-') {
										$option_weight -= $option_value_query->row['weight'];
									}

									if ($option_value_query->row['subtract'] && (!$option_value_query->row['quantity'] || ($option_value_query->row['quantity'] < $cart['quantity']))) {
										$stock = false;
									}

									$option_data[] = array(
										'product_option_id'       => $product_option_id,
										'product_option_value_id' => $product_option_value_id,
										'option_id'               => $option_query->row['option_id'],
										'option_value_id'         => $option_value_query->row['option_value_id'],
										'name'                    => $option_query->row['name'],
										'value'                   => $option_value_query->row['name'],
										'type'                    => $option_query->row['type'],
										'quantity'                => $option_value_query->row['quantity'],
										'subtract'                => $option_value_query->row['subtract'],
										'price'                   => $option_value_query->row['price'],
										'price_prefix'            => $option_value_query->row['price_prefix'],
										'points'                  => $option_value_query->row['points'],
										'points_prefix'           => $option_value_query->row['points_prefix'],
										'weight'                  => $option_value_query->row['weight'],
										'weight_prefix'           => $option_value_query->row['weight_prefix']
									);
								}
							}
						} elseif ($option_query->row['type'] == 'text' || $option_query->row['type'] == 'textarea' || $option_query->row['type'] == 'file' || $option_query->row['type'] == 'date' || $option_query->row['type'] == 'datetime' || $option_query->row['type'] == 'time') {
							$option_data[] = array(
								'product_option_id'       => $product_option_id,
								'product_option_value_id' => '',
								'option_id'               => $option_query->row['option_id'],
								'option_value_id'         => '',
								'name'                    => $option_query->row['name'],
								'value'                   => $value,
								'type'                    => $option_query->row['type'],
								'quantity'                => '',
								'subtract'                => '',
								'price'                   => '',
								'price_prefix'            => '',
								'points'                  => '',
								'points_prefix'           => '',
								'weight'                  => '',
								'weight_prefix'           => ''
							);
						}
					}
				}


                // 原始录入价格 oc_product 表中price字段值
				$price = $product_query['price'];
                // 初始默认价格
                $defaultPrice = $price;

                //add by xxli 购物车折扣
                // 用户ID 使用$buyer_id
                // Seller ID
                $seller_id = $product_query['customer_id'];
                // 议价没有折扣
                $transactionType = $cart['type_id'];
                if ($cart['type_id'] == ProductTransactionType::SPOT && !empty($item['agreement_id'])) {
                    $transactionType = null;
                }
                // 获取大客户折扣
                $discountInfo = app(MarketingDiscountRepository::class)->getMaxDiscount($buyer_id, $cart['product_id'], $cart['quantity'], $transactionType);
                $discountValue = $discountInfo->discount ?? null;
                $discountRate = $discountValue ? intval($discountValue) / 100 : 1;
                $precision = $customerCountry == CountryEnum::JAPAN ? 0 : 2;

                // 获取折扣价格
                // 获取精细化管理价格
                $fineData = $this->getDelicacyManagementInfoByNoView((int)$cart['product_id'], (int)$buyer_id, (int)$seller_id);
                // 查找是否是返点得精细化
                // getDelicacyManagementInfoByNoView 中已经添加了对is_rebate的判断，不需要再次处理
                $exists = isset($fineData['is_rebate']) ? $fineData['is_rebate'] : 0;
                $fine_cannot_buy = 0;
                $refine_price = 0;
                $is_delicacy_effected = false; // 精细化价格是否正在使用
                if ($fineData && !$exists) {
                    if ($fineData['product_display'] != 1) {
                        // 用户无权下单购买该产品
                        $stock = false;
                        $fine_cannot_buy = 1;
                    } else {
                        $price = $fineData['current_price'];
                        $refine_price = $fineData['current_price'];

                        //  查出当前价格使用的是精细化价格， 1.第一次设置精细化价格直接生效 2.已设置过精细化价格，但有设置后期涨价
                        if ($fineData['effective_time'] <= date('Y-m-d H:i:s')) {
                            $is_delicacy_effected = true;
                        } elseif ($this->orm->table('oc_delicacy_management_history')
                            ->where('origin_id', $fineData['id'])
                            ->where('effective_time', '<', date('Y-m-d H:i:s'))
                            ->where('expiration_time', '>', date('Y-m-d H:i:s'))
                            ->whereRaw('current_price = price')
                            ->exists()) {
                            $is_delicacy_effected = true;
                        }
                    }
                }

                $normal_price = $price;

                //交易形式不同需要整理不同的价格
                $agreement_code = null;
                $rebate_expire_alert = null;

                $quote_amount = 0;
                if($stock){
                    if($cart['type_id'] > 0){
                        // 获取新的价格
                        if($cart['type_id'] == ProductTransactionType::MARGIN){
                            //验证是否是头款
                            $mapProcess = [
                                'process_status' => 1,
                                'advance_product_id' => $cart['product_id'],
                            ];
                            $agreement_code = $this->orm->table('tb_sys_margin_process')->where($mapProcess)->value('margin_agreement_id');

                            //验证是否是履约人购买的
                            if($agreement_code){
                                $performer_flag = $this->orm->table('tb_sys_margin_agreement')
                                    ->where(
                                        [
                                            'id'=>$cart['agreement_id'],
                                            'buyer_id' => $cart['customer_id'],
                                        ]
                                    )
                                    ->value('id');
                                if(!$performer_flag){
                                    $stock = false;
                                }
                            }else{
                                $performer_flag = $this->orm->table('oc_agreement_common_performer')
                                    ->where(
                                        [
                                            'agreement_id'=>$cart['agreement_id'],
                                            'agreement_type'=>$this->config->get('common_performer_type_margin_spot'),
                                            'buyer_id' => $cart['customer_id'],
                                        ]
                                    )
                                    ->value('id');
                                if(!$performer_flag){
                                    $stock = false;
                                }

                            }
                        }

                        if (ProductTransactionType::FUTURE == $cart['type_id']){
                            //判断是不是期货头款
                            $info = $this->orm->table('oc_futures_margin_process as fp')
                                ->leftJoin('oc_futures_margin_agreement as fa', 'fa.id', 'fp.agreement_id')
                                ->where([
                                    'fp.process_status'    => 1,
                                    'fp.advance_product_id'=> $cart['product_id'],
                                    'fa.agreement_status'  => 3,
                                ])
                                ->select('fa.agreement_no','fa.buyer_id')
                                ->first();
                            if ($info && $info->buyer_id !=$cart['customer_id'] ){//期货头款商品 但不属于该用户的期货协议
                                $stock = false;
                            }
                            if ($info){
                                $agreement_code = $info->agreement_no;
                            }
                        }
                        if($agreement_code == null){
                            $transaction_info = $this->getTransactionTypeInfo($cart['type_id'],$cart['agreement_id'],$cart['product_id']);
                            if($transaction_info){
                                if ($cart['type_id'] != ProductTransactionType::SPOT) {
                                    $price = $transaction_info['price'] ?? 0;
                                }
                                $agreement_code = $transaction_info['agreement_code'] ?? 0;
                                if(isset($transaction_info['is_valid']) && $transaction_info['is_valid'] == 0){
                                    $rebate_expire_alert = sprintf("The adding to the shopping cart for these products is unavailable at the moment! The rebate agreement ID %s was expired.",$agreement_code);
                                    $stock = false;
                                }

                                if ($cart['type_id'] == ProductTransactionType::SPOT && !empty($cart['agreement_id']) && $transaction_info['qty'] == $cart['quantity']) {
                                    $quote_amount = $transaction_info['price'];
                                }
                            }
                        }
                    }
                }

                // end of quote.
                // 非欧洲、上门取货的buyer在非精细化价格、非议价时 减去运费, 不论何种类型的buyer，下单时均需记录运费
                $freightAndPackageResult = $this->getFreightAndPackageFee($product_query['product_id'],1, $delivery_type);
                //获取该产品超重附加费
                $overweightSurcharge = $freightAndPackageResult['overweight_surcharge'];
                $baseFreight =  $freightAndPackageResult['freight'];
                //运费费率
                $freightRate =  $freightAndPackageResult['freight_rate'];
                $freight = $overweightSurcharge + $freightAndPackageResult['freight'];
                //获取该产品的打包费
                $packageFee = $freightAndPackageResult['package_fee'];
                //获取产品的体积
                $volume =  $freightAndPackageResult['volume'];
                $volumeInch =  $freightAndPackageResult['volume_inch'];
                //议价时获取的价格,现在统一对货值进行议价
                $priceForQuote = $price;

                // #3099 取常规价(精细化)和阶梯价中较低价格进行展示
                $useWkProQuotePrice = false;
                $discountSpotInfo = null;
                if ($cart['type_id'] == ProductTransactionType::NORMAL && $cart['add_cart_type'] != CartAddCartType::NORMAL) {

                    $discountSpotInfo = app(MarketingDiscountRepository::class)->getMaxDiscount($buyer_id, $cart['product_id'], $cart['quantity'], ProductTransactionType::SPOT);
                    $discountSpot = $discountSpotInfo->discount ?? null; //产品折扣
                    $discountSpotRate = $discountSpot ? intval($discountSpot) / 100 : 1;

                    $wkProQuoteDetail = $this->orm->table('oc_wk_pro_quote_details')->where('product_id', $cart['product_id'])
                        ->where('seller_id', $seller_id)
                        ->where('min_quantity', '<=', $cart['quantity'])
                        ->where('max_quantity', '>=', $cart['quantity'])
                        ->first();
                    switch ($cart['add_cart_type']) {
                        case CartAddCartType::TIERED:
                            if (!empty($wkProQuoteDetail)) {
                                $useWkProQuotePrice = true;
                                $price = $wkProQuoteDetail->home_pick_up_price;
                            }
                            break;
                        case CartAddCartType::DEFAULT_OR_OPTIMAL:
                            if (!empty($wkProQuoteDetail)) {
                                // #31737 需要计算出当前免税加后的折扣价对比 (因精细化不需要做折扣)
                                $vatPrice = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($seller_id), $buyer_id, $price);
                                $vatWkProQuotePrice = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($seller_id), $buyer_id, $wkProQuoteDetail->home_pick_up_price);

                                $vatDiscountPrice = $vatPrice;
                                if (empty($refine_price)) {
                                    $vatDiscountPrice =  MoneyHelper::upperAmount($vatPrice * $discountRate, $precision);
                                }
                                $vatWkProQuoteDiscountPrice = MoneyHelper::upperAmount($vatWkProQuotePrice * $discountSpotRate, $precision);
                                if ($vatWkProQuoteDiscountPrice < $vatDiscountPrice) {
                                    $useWkProQuotePrice = true;
                                    $price = $wkProQuoteDetail->home_pick_up_price;
                                }
                            }
                            break;
                    }
                }

                /*
                 * 折扣价格，Buyer和Seller建立联系后，Seller对Buyer所设置的折扣,目前该折扣已失效
                 */
                $price = $this->getDiscountPrice($buyer_id, $seller_id, $price);

                // 欧洲国家产品的展示价格为 oc_product[price] * 0.85 / 2
                if ($customerCountry) {
                    $product_price_per = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($seller_id, $price, $customerCountry);
                    $service_fee_per = $price-$product_price_per;
                    // #31737 下单针对于非复杂交易的价格需要判断是否需免税
                    if (in_array($cart['type_id'], [ProductTransactionType::NORMAL, ProductTransactionType::SPOT]) && $product_query['product_type'] == ProductType::NORMAL) {
                        [$price, $product_price_per, $service_fee_per] = app(ProductPriceRepository::class)
                            ->getProductTaxExemptionPrice(intval($seller_id), $price, $service_fee_per);
                    }
                }
                //end xxli

                // #22763 大客户-折扣 #31737 优化逻辑 （普通商品，普通购买，非精细化）
                $useDiscountInfo = null;
                if ((empty($refine_price) || $useWkProQuotePrice) && $product_query['product_type'] == ProductType::NORMAL && $cart['type_id'] == ProductTransactionType::NORMAL) {
                    $priceTmp = $price;
                    $price = MoneyHelper::upperAmount($price *  ($useWkProQuotePrice ? $discountSpotRate : $discountRate), $precision);
                    $newDiscountPrice = $priceTmp - $price;
                    $useDiscountInfo = $useWkProQuotePrice ? $discountSpotInfo : $discountInfo;

                    // 重新整理计算服务费和货值
                    if (isset($product_price_per) && isset($service_fee_per)) {
                        $product_price_per = MoneyHelper::upperAmount($product_price_per *  ($useWkProQuotePrice ? $discountSpotRate : $discountRate), $precision);
                        $service_fee_per = $price - $product_price_per;
                    }
                }
                $discountPrice = $price;

                /*
                 * 在上一步遍历，统计出某个产品一共购买了$discount_quantity个，OpenCart有产品折扣表，该产品折扣和用户分组以及
                 * 购买数量有关。产品折扣表：oc_product_discount, 当购买数量<=设定数量，且时间大于date_start小于date_end, 价格重新定义
                 * 为oc_product_discount的price
                 * OpenCart Discount 参考文档：http://docs.opencart.com/en-gb/catalog/product/discount/
                 * 注：B2B业务没有用到该表，所以这里的逻辑是预留项
                 */
                // Product Discounts
                /*$discount_quantity = $cart['quantity'];
				$product_discount_query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$cart['product_id'] . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND quantity <= '" . (int)$discount_quantity . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity DESC, priority ASC, price ASC LIMIT 1");

				if ($product_discount_query->num_rows) {
					$price = $product_discount_query->row['price'];
				}*/

				// Product Specials
                /*
                 * 特殊产品价格表，该表的设计同oc_product_discount, 特殊选项与折扣选项相同，但此优惠将被视为特价，而非折扣。
                 * OpenCart Special 参考文档：http://docs.opencart.com/en-gb/catalog/product/special/
                 * 注：B2B业务暂时没有用到
                 */
				/*$product_special_query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$cart['product_id'] . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");

				if ($product_special_query->num_rows) {
					$price = $product_special_query->row['price'];
				}*/

				// Reward Points
                /*
                 * 奖励积分是OpenCart的一项功能，可为客户分配从商店购买产品的“忠诚度积分”。客户可以使用这些获得的积分作为货币从商店购买产品。
                 * 关于reward表有：oc_customer_reward、oc_product_reward。
                 * OpenCart Reward 参考文档：http://docs.opencart.com/en-gb/catalog/product/reward/
                 * 注：B2B业务暂时没有用到
                 */
				/*$product_reward_query = $this->db->query("SELECT points FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . (int)$cart['product_id'] . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'");

				if ($product_reward_query->num_rows) {
					$reward = $product_reward_query->row['points'];
				} else {
					$reward = 0;
				}*/

				// Downloads
                /*
                 * 产品文件下载，所用到的表有：oc_download、oc_download_description,
                 * marketplace插件新增表oc_customerpartner_download，
                 * OpenCart Download 参考文档：http://docs.opencart.com/en-gb/catalog/download/
                 */
				$download_data = array();

				$download_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_download p2d LEFT JOIN " . DB_PREFIX . "download d ON (p2d.download_id = d.download_id) LEFT JOIN " . DB_PREFIX . "download_description dd ON (d.download_id = dd.download_id) WHERE p2d.product_id = '" . (int)$cart['product_id'] . "' AND dd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

				foreach ($download_query->rows as $download) {
					$download_data[] = array(
						'download_id' => $download['download_id'],
						'name'        => $download['name'],
						'filename'    => $download['filename'],
						'mask'        => $download['mask']
					);
				}

                // Stock 库存判断
                // margin 使用oc_product_lock表中qty
                $margin_qty = null;
                if(in_array($cart['type_id'], [ProductTransactionType::MARGIN, ProductTransactionType::FUTURE]) && !in_array($product_query['product_type'], [ProductType::MARGIN_DEPOSIT, ProductType::FUTURE_MARGIN_DEPOSIT] ) ){
				    $mapMargin = [
                        'agreement_id' => $cart['agreement_id'],
                        'parent_product_id'   => $cart['product_id'],
                        'type_id'       => $cart['type_id']
                    ];
                    $margin_qty = $this->orm->table('oc_product_lock')->where($mapMargin)->selectRaw('round(qty/set_qty) as qty')->first();
                    if (!$margin_qty->qty || ($margin_qty->qty < $cart['quantity'])) {
                        $stock = false;
                    }
                }else{
                    /** @var ModelCheckoutPreOrder $modelCheckoutPreOrder */
                    $modelCheckoutPreOrder = load()->model('checkout/pre_order');
                    $product_query['quantity'] = $modelCheckoutPreOrder->calculateProductStockQty($cart['product_id'], $product_query['quantity'], $useDiscountInfo);
                    if (!$product_query['quantity'] || ($product_query['quantity'] < $cart['quantity'])) {
                        $stock = false;
                    }
                }

                /*
                 * Recurring Order
                 * OpenCart 设置产品分期付款，参考文档： http://docs.opencart.com/en-gb/sale/recurring/
                 * 关于分期付款，B2B业务暂未使用
                 */
				/*$recurring_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "recurring r LEFT JOIN " . DB_PREFIX . "product_recurring pr ON (r.recurring_id = pr.recurring_id) LEFT JOIN " . DB_PREFIX . "recurring_description rd ON (r.recurring_id = rd.recurring_id) WHERE r.recurring_id = '" . (int)$cart['recurring_id'] . "' AND pr.product_id = '" . (int)$cart['product_id'] . "' AND rd.language_id = " . (int)$this->config->get('config_language_id') . " AND r.status = 1 AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'");

				if ($recurring_query->num_rows) {
					$recurring = array(
						'recurring_id'    => $cart['recurring_id'],
						'name'            => $recurring_query->row['name'],
						'frequency'       => $recurring_query->row['frequency'],
						'price'           => $recurring_query->row['price'],
						'cycle'           => $recurring_query->row['cycle'],
						'duration'        => $recurring_query->row['duration'],
						'trial'           => $recurring_query->row['trial_status'],
						'trial_frequency' => $recurring_query->row['trial_frequency'],
						'trial_price'     => $recurring_query->row['trial_price'],
						'trial_cycle'     => $recurring_query->row['trial_cycle'],
						'trial_duration'  => $recurring_query->row['trial_duration']
					);
				} else {
					$recurring = false;
				}*/

                // 计算佣金（B2B业务暂未使用）
                $commission_amount = 0;
                /*if ($this->config->get('module_marketplace_status')) {
                   $check_seller_product = $this->db->query("SELECT * FROM ".DB_PREFIX."customerpartner_to_product WHERE product_id = '".$product_query['product_id']."'")->row;
                   if ($check_seller_product) {
                    if ($this->config->get('marketplace_commission_tax')) {
                      $commission_array = $this->calculateCommission(array('product_id'=> $product_query['product_id'], 'product_total'=> $this->tax->calculate(($price + $option_price), $product_query['tax_class_id'], $this->config->get('config_tax'))),$check_seller_product['customer_id']);
                    }else{
                      $commission_array = $this->calculateCommission(array('product_id'=>$product_query['product_id'], 'product_total'=>($price + $option_price)),$check_seller_product['customer_id']);
                    }

                    if($commission_array && isset($commission_array['commission']) && $this->config->get('marketplace_commission_unit_price')){
                          $commission_amount = $commission_array['commission'];
                    }
                  }
                }*/
                // 获取type_id 的图标
                // 产品数据
                $product_data[] = array(
                    'cart_id'         => $cart['cart_id'],
                    'type_id'         => $cart['type_id'], //区分普通交易，rebate margin的
                    'type_icon'       => TRANSACTION_TYPE_ICON[$cart['type_id']], //区分普通交易，rebate margin的
                    'product_type'    => $product_query['product_type'],
                    'agreement_code'  => $agreement_code,
                    'agreement_id'    => ($cart['type_id'] == CartAddCartType::DEFAULT_OR_OPTIMAL && $cart['agreement_id'] == 0) ? null : $cart['agreement_id'],
                    'product_id'      => $product_query['product_id'],
                    'danger_flag'      => $product_query['danger_flag'],
                    'name'            => $product_query['name'],
                    'model'           => $product_query['model'],
                    'shipping'        => $product_query['shipping'],
                    'image'           => $product_query['image'],
                    'combo'           => $product_query['combo_flag'],
                    'option'          => $option_data,
                    'download'        => $download_data,
                    'quantity'        => $cart['quantity'],
                    'minimum'         => $product_query['minimum'],
                    'subtract'        => $product_query['subtract'],
                    'stock'           => $stock,
                    'defaultPrice' => $defaultPrice, // oc_product 表原始价格
                    'price' => ($price + $option_price), // 产品的货值
                    'discountPrice' => $discountPrice, // 打折之后的价格
                    'discount_price' => $newDiscountPrice ?? 0, // #22763 大客户折扣价格, 比如原价100 ，打7折，discount_price=30
                    'realTotal' => round($discountPrice, 2) * $cart['quantity'], // 实际支付总价
                    'commission_amount'  => $commission_amount,
                    'total' => ($price + $option_price + $commission_amount) * $cart['quantity'], // 打折后支付的总价
                    //'reward'          => $reward * $cart['quantity'],
                    'points'          => ($product_query['points'] ? ($product_query['points'] + $option_points) * $cart['quantity'] : 0),
                    'tax_class_id'    => $product_query['tax_class_id'],
                    'weight'          => ($product_query['weight'] + $option_weight) * $cart['quantity'],
                    'singleton_weight'=> round($product_query['weight'],2),
                    'weight_class_id' => $product_query['weight_class_id'],
                    'length'          => $product_query['length'],
                    'width'           => $product_query['width'],
                    'height'          => $product_query['height'],
                    'length_class_id' => $product_query['length_class_id'],
                    //'recurring'       => $recurring,
                    'sku' => $product_query['sku'],
                    'mpn' => $product_query['mpn'],
                    'seller_id' => $product_query['customer_id'],
                    'customer_group_id'=>$product_query['customer_group_id'],
                    'screenname' => $product_query['screenname'],
                    'quote_amount' => $quote_amount,
                    'freight_per'   => $freight,//单件的运费（基础运费+超重附加费）
                    'freight_rate'   => $freightRate,//运费费率
                    'base_freight'   => $baseFreight,//基础运费
                    'overweight_surcharge'   => $overweightSurcharge,//超重附加费
                    'discount_info'   => $useDiscountInfo,
                    'use_wk_pro_quote_price'   =>  $useWkProQuotePrice,
                    'sub_products' => $sub_product_array,
                    'margin_expire_alert' => isset($margin_expire_alert) ? $margin_expire_alert : null,
                    'rebate_expire_alert' => $rebate_expire_alert,
                    'margin_batch_out_stock' => isset($margin_batch_out_stock) ? $margin_batch_out_stock : null,
                    'priceForQuote' =>$priceForQuote, //议价的基准价格
                    'package_fee_per' =>$packageFee, //打包费
                    'product_price_per' =>isset($product_price_per)?$product_price_per:0,//欧洲根据初始货值拆分的展示价格
                    'service_fee_per' =>isset($service_fee_per)?$service_fee_per:0, //欧洲根据初始货值拆分的服务费
                    'volume' => $volume,
                    'volume_inch' => $volumeInch,
                    'delivery_type' => $cart['delivery_type'],
                    'buyer_flag' => $product_query['buyer_flag'],
                    'product_status'    => $product_query['status'],//产品上下架状态
                    'fine_cannot_buy'   => $fine_cannot_buy,//精细化不可见
                    'store_status'      => $product_query['store_status'],//店铺上下架状态
                    'refine_price' => $refine_price ?? 0,//精细化价格 （没有为0）
                    'add_cart_type' => $cart['add_cart_type'],//是否议价
                    'is_delicacy_effected' => $is_delicacy_effected, // 精细化价格是否使用
                    'normal_price' => $normal_price,
                );
			} else {
				$this->remove($cart['cart_id'], $cart['delivery_type']);
			}
		}

		return $product_data;
	}

    /**
     * @param int $product_id
     * @param int $quantity
     * @param array $option
     * @param int $recurring_id
     * @param int $type_id
     * @param null|int $agreement_id
     * @param null|int $delivery_type
     * @return mixed
     */
    public function add($product_id, $quantity = 1, $option = array(), $recurring_id = 0, $type_id = 0, $agreement_id = null, $delivery_type = null)
    {
        if (is_null($delivery_type)) {
            $delivery_type = $this->customer->isCollectionFromDomicile() ? 1 : 0;
        }
        $keyVal = [
            'api_id' => ((int)session('api_id', 0)),
            'customer_id' => (int)$this->customer->getId(),
            'session_id' => $this->session->getId(),
            'product_id' => $product_id,
            'recurring_id' => $recurring_id,
            'option' => json_encode($option),
            'type_id' => $type_id,
            'agreement_id' => $agreement_id,
            'delivery_type' => $delivery_type,
        ];
        $cart_id = $this->orm->table('oc_cart')
            ->where($keyVal)
            ->value('cart_id');

        if ($cart_id) {
            //增加数量
            $this->orm->table('oc_cart')->where('cart_id', $cart_id)->increment('quantity', $quantity);
            $this->orm->table('oc_cart')->where('cart_id', $cart_id)->update([
                'sort_time' => time()
            ]);
            return $cart_id;
        } else {
            return $this->orm->table('oc_cart')->insertGetId(
                array_merge($keyVal, [
                    'quantity' => $quantity,
                    'date_added' => date('Y-m-d H:i:s', time()),
                    'sort_time' => time()
                ])
            );
        }
    }

    /**
     * 仅供自动购买api使用
     *
     * 注：均为内部类型buyer，交付方式均为 一件代发 即 delivery_type 为 1
     *
     * [addWithBuyerId description]
     * @param int $product_id
     * @param int $buyer_id
     * @param int $quantity
     * @param array $option
     * @param int $recurring_id
     * @param int $type_id
     * @param int|null $agreement_id
     * @param int $delivery_type
     * @return int
     */
    public function addWithBuyerId($product_id, $buyer_id,$quantity = 1, $option = [], $recurring_id = 0,$type_id = 0,$agreement_id = null,$delivery_type = 1)
    {
        $mapCart = [
            'api_id'=>   ((int)session('api_id', 0)),
            'customer_id'  => $buyer_id,
            'session_id'   => $this->session->getId(),
            'product_id'   => $product_id,
            'recurring_id' => $recurring_id,
            'option'       => json_encode($option),
            'type_id'      => $type_id,
            'agreement_id' => $agreement_id,
            'delivery_type'=> $delivery_type,
        ];

        $cart_id = $this->orm->table(DB_PREFIX.'cart')
            ->where($mapCart)
            ->value('cart_id');
        if($cart_id){
            //增加数量
            $this->orm->table(DB_PREFIX.'cart')->where('cart_id',$cart_id)->increment('quantity',$quantity);
            return $cart_id;
        }else{
            //再次验证是否有buyer_id 和 product_id重复的导致了
            //新增数据
            $count = $this->verifyProductAdd($product_id,$buyer_id,$type_id,$agreement_id,$delivery_type);
            if($count > 0){
                $mapVerifyCart = [
                    'customer_id' =>  $buyer_id,
                    'product_id'  =>  $product_id,
                    'delivery_type' =>$delivery_type
                ];
                $this->orm->table(DB_PREFIX.'cart')->where($mapVerifyCart)->delete();
            }
            $saveCart = [
                'api_id'       =>  ((int)session('api_id', 0)),
                'customer_id'  =>  $buyer_id,
                'session_id'   =>  $this->session->getId(),
                'product_id'   =>  $product_id,
                'recurring_id' =>  $recurring_id,
                'option'       =>  json_encode($option),
                'type_id'      =>  $type_id,
                'quantity'     =>  $quantity,
                'date_added'   =>  date('Y-m-d H:i:s',time()),
                'agreement_id' =>  $agreement_id,
                'delivery_type'=> $delivery_type,
            ];
            return $this->orm->table(DB_PREFIX.'cart')->insertGetId($saveCart);

        }
    }


    /**
     * @param int $product_id
     * @param int $buyer_id
     * @param $type
     * @param int|null$agreement_id
     * @param null $delivery_type
     * @return mixed
     */
    public function verifyProductAdd($product_id,$buyer_id,$type,$agreement_id,$delivery_type = null)
    {
        $mapCart = [
            'customer_id' =>  $buyer_id,
            'product_id'  =>  $product_id,
            'delivery_type'=> $delivery_type,
        ];
        return $this->orm->table(DB_PREFIX . 'cart')
            ->where($mapCart)
            ->count();
    }

    public function update($cart_id, $quantity)
    {
		$this->db->query("UPDATE " . DB_PREFIX . "cart SET quantity = '" . (int)$quantity . "' WHERE cart_id = '" . (int)$cart_id . "' AND api_id = '" . ((int)session('api_id', 0)) . "' AND customer_id = '" . (int)$this->customer->getId() . "' AND session_id = '" . $this->db->escape($this->session->getId()) . "'");
	}

    public function remove($cart_id,$delivery_type = 0)
    {
        $this->db->query("DELETE FROM " . DB_PREFIX . "cart WHERE cart_id = '" . (int)$cart_id . "' AND api_id = '" . ((int)session('api_id', 0)) . "' AND customer_id = '" . (int)$this->customer->getId() . "' AND delivery_type ='".$delivery_type."'");
	}

    public function clear($isBySession = false)
    {
	    $customer_id = $isBySession ? session('customer_id'):$this->customer->getId();
        if (isset($this->session->data['delivery_type'])) {
            $delivery_type = session('delivery_type');
            $this->db->query("DELETE FROM " . DB_PREFIX . "cart WHERE api_id = '" . ((int)session('api_id', 0)) . "' AND customer_id = '" . (int)$customer_id . "' AND delivery_type = '".$delivery_type."'");
        } else {
            $this->db->query("DELETE FROM " . DB_PREFIX . "cart WHERE api_id = '" . ((int)session('api_id', 0)) . "' AND customer_id = '" . (int)$customer_id . "'");
        }
    }

    public function getRecurringProducts($cart_id_arr=[])
    {
		$product_data = array();
        $delivery_type = isset($this->session->data['delivery_type'])? session('delivery_type'):-1;
		foreach ($this->getProducts(null, $delivery_type, $cart_id_arr) as $value) {
			if ($value['recurring'] ?? 0) {
				$product_data[] = $value;
			}
		}

		return $product_data;
	}

    public function getWeight($cart_id_arr = [])
    {
		$weight = 0;
        $delivery_type = isset($this->session->data['delivery_type'])? session('delivery_type'):-1;
		foreach ($this->getProducts(null, $delivery_type, $cart_id_arr) as $product) {
			if ($product['shipping']) {
				$weight += $this->weight->convert($product['weight'], $product['weight_class_id'], $this->config->get('config_weight_class_id'));
			}
		}

		return $weight;
	}

    public function getRealTotal($cart_id_arr=[], $products = [])
    {
        if (empty($products)) {
            $delivery_type = App::session()->has('delivery_type') ? App::session()->get('delivery_type') : -1;
            $products = $this->getProducts(null, $delivery_type, $cart_id_arr);
        }
        $total = 0;
        foreach ($products as $product) {
            $total += $product['realTotal'];
        }

        return $total;
    }

    public function getQuoteTotal($cart_id_arr=[])
    {
        $total = 0;
        $delivery_type = isset($this->session->data['delivery_type'])? session('delivery_type'):-1;
        $volumeAll = 0;
        $packageAll = 0;
        $freightAll = 0;
        foreach ($this->getProducts(null, $delivery_type, $cart_id_arr) as $product) {
            $volumeAll += $product['volume']*$product['quantity'];
            $packageAll += $product['package_fee_per']*$product['quantity'];
            $total += $product['realTotal'] - $product['quote_amount'];
            $freightAll += ($product['package_fee_per']+$product['freight_per'])*$product['quantity'];
        }
        $shippingRate = $this->config->get('cwf_base_cloud_freight_rate');
        if(in_array($delivery_type,[-1,2])) {
            $totalShipingFee = (double)($volumeAll * $shippingRate);
            $total = $total + $totalShipingFee + $packageAll;
        }else{
            $total = $total + $freightAll;
        }
        return $total;
    }

    public function getSubTotal($cart_id_arr = [], $products = [])
    {
        if (empty($products)) {
            $delivery_type = App::session()->has('delivery_type') ? App::session()->get('delivery_type') : -1;
            $products = $this->getProducts(null, $delivery_type, $cart_id_arr);
        }
		$total = 0;
		foreach ($products as $product) {
			$total += $product['total'];
		}
		return $total;
	}

    public function getRealSubTotal($cart_id_arr=[], $products = [])
    {
        if (empty($products)) {
            $delivery_type = App::session()->has('delivery_type') ? App::session()->get('delivery_type') : -1;
            $products = $this->getProducts(null, $delivery_type, $cart_id_arr);
        }
        $total = 0;
        foreach ($products as $product) {
            $total += round($product['product_price_per'], 2)*$product['quantity'];
        }

        return $total;
    }

    public function getTaxes($cart_id_arr=[], $products = [])
    {
		$tax_data = array();

        if (empty($products)) {
            $delivery_type = isset($this->session->data['delivery_type'])? session('delivery_type'):-1;
            $products = $this->getProducts(null, $delivery_type,$cart_id_arr);
        }

        foreach ($products as $product) {
			if ($product['tax_class_id']) {
				$tax_rates = $this->tax->getRates($product['price'], $product['tax_class_id']);

				foreach ($tax_rates as $tax_rate) {
					if (!isset($tax_data[$tax_rate['tax_rate_id']])) {
						$tax_data[$tax_rate['tax_rate_id']] = ($tax_rate['amount'] * $product['quantity']);
					} else {
						$tax_data[$tax_rate['tax_rate_id']] += ($tax_rate['amount'] * $product['quantity']);
					}
				}
			}
		}
		return $tax_data;
	}

    public function getTotal($cart_id_arr=[])
    {
		$total = 0;
        $delivery_type = isset($this->session->data['delivery_type'])? session('delivery_type'):-1;
		foreach ($this->getProducts(null, $delivery_type, $cart_id_arr) as $product) {

                $total += ($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'))+$product['commission_amount']) * $product['quantity'];

		}

		return $total;
	}

    public function countProducts($delivery_type = -1,$cart_id_arr=[])
    {
		$product_total = 0;
		$products = $this->getProducts($this->customer->getId(), $delivery_type, $cart_id_arr);

		foreach ($products as $product) {
			$product_total += $product['quantity'];
		}

		return $product_total;
	}

    public function hasProducts($delivery_type = -1, $cart_id_arr=[])
    {
		//return count($this->getProducts(null, $delivery_type, $cart_id_arr));
        if (!empty($cart_id_arr)){
            $carts = $this->orm->table(DB_PREFIX.'cart')
                ->whereIn('cart_id', $cart_id_arr)
                ->exists();
        }else{
            $api_id = (int)session('api_id', 0);
            $carts = $this->orm->table(DB_PREFIX.'cart')
                ->where('customer_id', $this->customer->getId())
                ->where('api_id','=', $api_id)
                ->when($delivery_type > -1, function ($query) use ($delivery_type){
                    if (2 == $delivery_type){
                        return $query->where('delivery_type', '=', $delivery_type);
                    }else{
                        return $query->whereIn('delivery_type', [0,1]);
                    }
                })
                ->exists();
        }
        return $carts;
	}

    public function hasRecurringProducts()
    {
		return count($this->getRecurringProducts());
	}

    public function hasStock($cart_id_arr=[])
    {
        $delivery_type = isset($this->session->data['delivery_type'])? session('delivery_type'):-1;
		foreach ($this->getProducts(null, $delivery_type, $cart_id_arr) as $product) {
			if (!$product['stock']) {
				return false;
			}
		}

		return true;
	}

    public function hasShipping($cart_id_arr=[])
    {
        $delivery_type = isset($this->session->data['delivery_type'])? session('delivery_type'):-1;
		foreach ($this->getProducts(null, $delivery_type, $cart_id_arr) as $product) {
			if ($product['shipping']) {
				return true;
			}
		}

		return false;
	}

    public function hasDownload($cart_id_arr=[])
    {
        $delivery_type = isset($this->session->data['delivery_type'])? session('delivery_type'):-1;
		foreach ($this->getProducts(null, $delivery_type, $cart_id_arr) as $product) {
			if ($product['download']) {
				return true;
			}
		}

		return false;
	}

    public function hasProductsWithBuyerId($buyer_id,$cart_id_arr=[])
    {
        $delivery_type = isset($this->session->data['delivery_type'])?session('delivery_type'):0;
        return count($this->getProducts($buyer_id, $delivery_type, $cart_id_arr));
    }

    public function hasStockWithBuyerId($buyer_id,$cart_id_arr=[])
    {
        foreach ($this->getProducts($buyer_id,-1,$cart_id_arr) as $product) {
            if (!$product['stock']) {
                return false;
            }
        }

        return true;
    }

    public function hasShippingWithBuyerId($buyer_id,$cart_id_arr=[])
    {
        foreach ($this->getProducts($buyer_id, -1, $cart_id_arr) as $product) {
            if ($product['shipping']) {
                return true;
            }
        }

        return false;
    }

    public function getTaxesWithBuyerId($buyer_id, $cart_id_arr=[])
    {
        $tax_data = array();

        foreach ($this->getProducts($buyer_id, -1, $cart_id_arr) as $product) {
            if ($product['tax_class_id']) {
                $tax_rates = $this->tax->getRates($product['price'], $product['tax_class_id']);

                foreach ($tax_rates as $tax_rate) {
                    if (!isset($tax_data[$tax_rate['tax_rate_id']])) {
                        $tax_data[$tax_rate['tax_rate_id']] = ($tax_rate['amount'] * $product['quantity']);
                    } else {
                        $tax_data[$tax_rate['tax_rate_id']] += ($tax_rate['amount'] * $product['quantity']);
                    }
                }
            }
        }

        return $tax_data;
    }

    public function getSubTotalWithBuyerId($buyer_id,$cart_id_arr=[])
    {
        $total = 0;
        $delivery_type =  session('delivery_type');
        foreach ($this->getProducts($buyer_id, $delivery_type,$cart_id_arr) as $product) {
            $total += $product['total'];
        }

        return $total;
    }

    public function getRealSubTotalWithBuyerId($buyer_id,$cart_id_arr=[])
    {
        $total = 0;
        $delivery_type =  session('delivery_type');
        foreach ($this->getProducts($buyer_id, $delivery_type, $cart_id_arr) as $product) {
            $total += round($product['price'], 2)*$product['quantity'];
        }

        return $total;
    }

    /**
     * 校验是否是combo品，如果是判断子combo库存是否足够
     *
     * @param $father_product_id
     * @param $father_qty
     * @return array
     */
    public function checkComboStock($father_product_id, $father_qty)
    {
        $sub_product_data = array();
        $setStock = true;
        $sql = 'select p.sku,psi.set_mpn,psi.qty as set_qty,psi.seller_id,psi.set_product_id,p.quantity as p_qty,ctp.quantity as ctp_qty
        from tb_sys_product_set_info as psi join oc_product as p on p.product_id = psi.set_product_id
        join oc_customerpartner_to_product as ctp on ctp.product_id = psi.set_product_id
        where psi.product_id=' . $father_product_id;

        $queryResult = $this->db->query($sql);
        if ($queryResult->num_rows > 0) {
            foreach ($queryResult->rows as $item) {
                //if ($father_qty * (int)$item['set_qty'] > min($item['p_qty'], $item['ctp_qty'])) {
                //            $setStock = false;
                //        }
                $sub_product_data[] = array(
                    'sub_productId' => $item['set_product_id'],
                    'sub_qty' => $item['set_qty'],
                    'sub_mpn' => $item['sku'],
                    'sub_stock'     => $setStock
                );
            }
        }
        return $sub_product_data;
    }

    public function clearWithBuyerId($buyer_id)
    {
        $this->db->query("DELETE FROM " . DB_PREFIX . "cart WHERE api_id = '" . ((int)session('api_id', 0)) . "' AND customer_id = '" . (int)$buyer_id ."'");
    }

    /**
     * @param int $customer_id BuyerId
     * @param int $seller_id SellerId
     * @param double $price 产品单价
     * @return float 折扣价格
     */
    public function getDiscountPrice($customer_id, $seller_id, $price)
    {
        $discountResult = $this->db->query("Select discount from oc_buyer_to_seller where buyer_id = " . (int)$customer_id . " and seller_id =" . (int)$seller_id)->row;
        if ($discountResult) {
            $discount = $discountResult['discount'];
            $discountPrice = $price * $discount;
            $price = round($discountPrice,2);
        }
        if($this->customer->getCountryId() == 107){
            // 日本，返回的价格必须为整数
            return round($price);
        }else{
            return $price;
        }

    }

    /**
     * 获取生效的精细化价格
     * @param int $buyer_id
     * @param int $product_id
     * @return
     */
    private function getDelicacyPrice($buyer_id, $product_id)
    {
        $sql = "SELECT dm.current_price AS delicacy_price,dm.product_display FROM vw_delicacy_management dm WHERE NOW() < dm.expiration_time AND dm.buyer_id = " . (int)$buyer_id . " AND dm.product_id = " . (int)$product_id;
        $query = $this->db->query($sql);
        return $query->row;
    }

    /**
     * buyer不显示的产品IDS，可筛选sellerID
     * @param int $buyerId
     * @param int $sellerId
     * @return array
     */
    public function buyerNoDisplayProductIdsByBuyerIdAndSellerId(int $buyerId, int $sellerId = 0) : array
    {
        if (empty($buyerId)) {
            return [];
        }

        $delicacyManagementQuery = $this->orm->table(DB_PREFIX . 'delicacy_management')
            ->where('buyer_id', $buyerId)
            ->where('product_display', 0)
            ->where('expiration_time', '>', date('Y-m-d H:i-s'));
        if ($sellerId != 0) {
            $delicacyManagementQuery->where('seller_id', $sellerId);
        }
        $delicacyManagementNoDisplayProductIds = $delicacyManagementQuery->pluck('product_id')->toArray();

        $delicacyManagementGroupQuery = $this->orm->table(DB_PREFIX . 'delicacy_management_group as dmg')
            ->join(DB_PREFIX . 'customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->join(DB_PREFIX . 'customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->where('dmg.status', 1)
            ->where('pgl.status', 1)
            ->where('bgl.status', 1)
            ->where('bgl.buyer_id', $buyerId);
        if ($sellerId != 0) {
            $delicacyManagementGroupQuery->where('dmg.seller_id', $sellerId);
        }
        $delicacyManagementGroupNoDisplayProductIds = $delicacyManagementGroupQuery->pluck('pgl.product_id')->toArray();

        return array_unique(array_filter(array_merge($delicacyManagementNoDisplayProductIds, $delicacyManagementGroupNoDisplayProductIds)));
    }

    /**
     * 不根据视图直接查询 精细化相关设置
     * 注：
     *    该方法 需要针对具体 product-buyer 才能使用
     *
     * @todo 如果 vw_delicacy_management 发生修改, 此处也要修改
     *
     * @param int|null $product_id
     * @param int|null $buyer_id
     * @param int|null $seller_id
     * @return array|null 如果为 null, 则代表没有参与精细化管理
     */
    public function getDelicacyManagementInfoByNoView($product_id, $buyer_id, $seller_id = null)
    {
        if (empty($product_id) || empty($buyer_id)) {
            return null;
        }

        $dm_sql = "select
                       product_id,
                       product_display,
                       current_price,
                       price,
                       effective_time,
                       id
                    from
                        oc_delicacy_management
                    where
                        product_id = $product_id
                        and buyer_id = $buyer_id
                        and expiration_time > NOW()
                    order by id DESC
                    limit 1";

        $dmg_sql = "select
                        dmg.id
                    from
                        oc_delicacy_management_group as dmg
                    join oc_customerpartner_product_group_link as pgl on pgl.product_group_id = dmg.product_group_id
                    join oc_customerpartner_buyer_group_link as bgl on bgl.buyer_group_id = dmg.buyer_group_id
                    where
                        dmg.status =1
                        and pgl.status=1
                        and bgl.status=1
                        and pgl.product_id = $product_id
                        and bgl.buyer_id = $buyer_id ";
        $seller_id && $dmg_sql .= " and dmg.seller_id = " . $seller_id;
        $dmg_sql .= " limit 1";

        if ($this->db->query($dmg_sql)->num_rows > 0) {
            $result = [
                'product_display' => 0,
            ];
        } else {
            $dm_res = $this->db->query($dm_sql);
            if ($dm_res->num_rows > 0) {
                $rebate = $this->orm->table('oc_rebate_agreement_item as i')
                    ->leftJoin('oc_rebate_agreement as r', 'r.id', '=', 'i.agreement_id')
                    ->where(
                        [
                            ['r.buyer_id','=',$buyer_id],
                            ['r.status','>=',3],
                            ['i.product_id','=',$product_id],
                            ['r.expire_time', '>', date('Y-m-d H:i:s')],
                        ]
                    )
                    ->exists();

                $result = [
                    'product_display' => $dm_res->row['product_display'],
                    'current_price' => $dm_res->row['current_price'],
                    'price' => $dm_res->row['price'],
                    'effective_time' => $dm_res->row['effective_time'],
                    'is_rebate' => $rebate ? 1 : 0,
                    'id' => $dm_res->row['id'],
                ];
            } else {
                $result = null;
            }
        }
        return $result;
    }


    /*
     * 获取所需运费,打包费
     * @param flag 0:用于计算价格（非欧洲上门取货类型的buyer返回实时有效运费，其他返回0） 1：用于记录运费（所有商品均返回实时运费）
     * @param delivery_type 0、一件代发，1、上门取货，2、云送仓。原来是取session里的值，现在改为传参。
     * */
    public function getFreightAndPackageFee($productId, $flag=0, $delivery_type = 0)
    {
        //$delivery_type =  session('delivery_type', 0);
		if($productId){
			$isEurope = $this->country->isEuropeCountry($this->customer->getCountryId());
			if ($flag || (!$flag && $this->customer->isCollectionFromDomicile() && !$isEurope)){
                if($delivery_type !=2) {

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
                        //#1363 云送仓增加超重附加费，普通订单增加默认值
                        return array(
                            'freight'              => (float)$freight->row['freight'],
                            'freight_rate'         => 0,
                            'package_fee'          => (float)$freight->row['package_fee'],
                            'volume'               => 0,
                            'volume_inch'          => 0,
                            'overweight_surcharge' => 0
                        );
                    }
                }else{
                    //云送仓运费
                    $productArray = array($productId);
                    $freightInfos = $this->freight->getFreightAndPackageFeeByProducts($productArray);
                    $freight = 0;
                    $freightRate = 0;
                    $package_fee = 0;
                    $volume = 0;
                    $volumeInch = 0;
                    $overweightSurcharge = 0;
                    if(!empty($freightInfos)){
                        if($this->freight->isCombo($productId)){
                            foreach ($freightInfos[$productId] as $freightInfo){
                                $freight += $freightInfo['freight']*$freightInfo['qty'];
                                $package_fee += $freightInfo['package_fee']*$freightInfo['qty'];
                                $volume +=  $freightInfo['volume']*$freightInfo['qty'];
                                $volumeInch +=  $freightInfo['volume_inch']*$freightInfo['qty'];
                                $freightRate = $freightInfo['freight_rate'];
                            }
                            if (!empty($freightInfos[$productId])) {
                                $overweightSurcharge = $freightInfos[$productId][array_keys($freightInfos[$productId])[0]]['overweight_surcharge'];//超重附加费在第一个内
                            }
                        }else{
                            $freight = $freightInfos[$productId]['freight'];
                            $package_fee = $freightInfos[$productId]['package_fee'];
                            $volume = $freightInfos[$productId]['volume'];
                            $volumeInch = $freightInfos[$productId]['volume_inch'];
                            $overweightSurcharge = $freightInfos[$productId]['overweight_surcharge'];
                            $freightRate = $freightInfos[$productId]['freight_rate'];
                        }
                    }
                    return array(
                        'freight'              => $freight,
                        'freight_rate'         => $freightRate,
                        'package_fee'          => $package_fee,
                        'volume'               => $volume,
                        'volume_inch'          => $volumeInch,
                        'overweight_surcharge' => $overweightSurcharge
                    );
                }
			}
		}
        return array(
            'freight'              => 0,
            'freight_rate'         => 0,
            'package_fee'          => 0,
            'volume'               => 0,
            'volume_inch'          => 0,
            'overweight_surcharge' => 0
        );
    }


    public function getProductBasicInformation($productId){
        $sql = "SELECT * FROM oc_product WHERE product_id = " . (int)$productId;
        return $this->db->query($sql)->row;
    }

    /**
     * [getTransactionTypeInfo description]
     * @param int $type
     * @param int $agreement_id
     * @param int $product_id
     * @return array|void
     */
    public function getTransactionTypeInfo($type,$agreement_id,$product_id){
        if ($type == ProductTransactionType::REBATE) {
            //rebate
            return $this->getRebateInfo($agreement_id, $product_id);
        } elseif ($type == ProductTransactionType::MARGIN) {
            return $this->getMarginInfo($agreement_id, $product_id, false);
        } elseif ($type == ProductTransactionType::FUTURE) {
            return $this->getFuturesInfo($agreement_id);
        } elseif ($type == ProductTransactionType::SPOT) {
            return $this->getSpotInfo($agreement_id, $product_id);
        }
    }

    public function getSpotInfo($agreement_id, $product_id)
    {
        $spot = $this->orm->table('oc_product_quote')
            ->where([
                'product_id' => $product_id,
                'id'   => $agreement_id,
                'status'   => 1,
            ])
            ->selectRaw('id, agreement_no as agreement_code, price, product_id, quantity as qty,
            quantity as left_qty,
            DATE_ADD(date_approved, INTERVAL 1 DAY) as expire_time')
            ->orderBy('id', 'desc')
            ->first();

        if ($spot && empty($spot->agreement_code)) {
            $spot->agreement_code = $spot->id;
        }

        return obj2array($spot);
    }

    public function getRebateInfo($agreement_id,$product_id){

        //获取是否有margin存在
        $flag = $this->getCurrentMarginPrice($product_id,$this->customer->getId());
        //必须要查找正在履行的返点交易
        $map = [
            ['a.id','=',$agreement_id],
            ['ai.product_id','=',$product_id],
            ['a.status','=',3],
            ['a.expire_time','>',date('Y-m-d H:i:s',time())],
        ];
        //首先要获取现货保证金交易：Agreement Status = Sold，协议未完成数量 ≠ 0；
        $ret = $this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
            ->leftJoin(DB_PREFIX.'rebate_agreement as a','a.id','=','ai.agreement_id')
            ->where($map)
            ->whereIn('a.rebate_result',[1,2])
            ->selectRaw('a.agreement_code,ai.template_price as price,ai.product_id,a.id,a.qty,a.expire_time')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();
        $ret_origin = $ret;
        if($flag){
            if($ret){
                //检测数量是否完成，如果已经完成，则需要剔除返点价格
                foreach($ret as $key => $value){
                    $total_purchased = 0;
                    $mapOrder = [
                        ['rao.agreement_id','=',$value['id']],
                    ];
                    $order_info = $this->orm->table(DB_PREFIX.'rebate_agreement_order as rao')
                        ->where($mapOrder)
                        ->select()
                        ->get()
                        ->map(
                            function ($v) {
                                return (array)$v;
                            })
                        ->toArray();
                    if($order_info){
                        foreach($order_info as $ks => $vs){
                            if($vs['type'] == 1){
                                $total_purchased += $vs['qty'];
                            }elseif($vs['type'] == 2){
                                $total_purchased -= $vs['qty'];
                            }
                        }
                    }

                    if($value['qty'] <= $total_purchased){
                        //已经完成rebate
                        unset($ret[$key]);
                    }else{
                        $ret[$key]['left_qty'] = $value['qty'] - $total_purchased;
                    }

                }
                $ret = array_values($ret);
            }
        }
        if($ret){
            return current($ret);
        }else{
            $ret_origin[0]['is_valid'] = 0;
            return current($ret_origin);
        }
    }

    public function getCurrentMarginPrice($product_id,$customer_id){
        //保证金存在履约人的情况下
        // 获取尾款价格
        $map = [
            ['m.product_id','=',$product_id],
            ['p.buyer_id','=',$customer_id],
            ['m.status','=',6], //sold
            ['m.expire_time','>',date('Y-m-d H:i:s',time())],
            ['l.qty','!=',0],
        ];
        $ret = $this->orm->table('tb_sys_margin_agreement as m')
            ->leftJoin(DB_PREFIX.'product_lock as l',function ($join){
                $join->on('l.agreement_id','=','m.id')->where('l.type_id','=',$this->config->get('transaction_type_margin_spot'));
            })
            ->leftJoin(DB_PREFIX.'agreement_common_performer as p',function ($join){
                $join->on('p.agreement_id','=','m.id')->where('p.agreement_type','=',$this->config->get('common_performer_type_margin_spot'));
            })
            ->where($map)
            ->selectRaw('m.id,m.expire_time,m.agreement_id as agreement_code,round(m.price - m.deposit_per,2) as price,m.product_id,m.num as qty,l.qty as left_qty')
            ->orderBy('m.expire_time','asc')
            ->get()
            ->map(function($value){
                return (array)$value;
            })
            ->toArray();

        return $ret;
    }

    public function getMarginInfo($agreement_id,$product_id,$lock_qty=true){
        $map = [
            ['m.id','=',$agreement_id],
            ['m.product_id','=',$product_id],
            ['p.buyer_id','=',$this->customer->getId()],
            ['m.status','=',6], //sold
        ];
        if ($lock_qty){
            $map[] = ['l.qty','!=',0];
        }
        $ret = $this->orm->table('tb_sys_margin_agreement as m')
            ->leftJoin(DB_PREFIX.'product_lock as l',function ($join){
                $join->on('l.agreement_id','=','m.id')->where('l.type_id','=',$this->config->get('transaction_type_margin_spot'));
            })
            ->leftJoin(DB_PREFIX.'agreement_common_performer as p',function ($join){
                $join->on('p.agreement_id','=','m.id')->where('p.agreement_type','=',$this->config->get('common_performer_type_margin_spot'));
            })
            ->where($map)
            ->selectRaw('m.deposit_per,m.id,m.price as agreement_price,m.expire_time,m.agreement_id as agreement_code,round(m.price - m.deposit_per,2) as price,m.product_id,m.num as qty,l.qty as left_qty')
            ->orderBy('m.expire_time','asc')
            ->get()
            ->map(function($value){
                return (array)$value;
            })
            ->toArray();
        if($ret){
            return current($ret);
        }else{
            return null;
        }

    }

    /**
     * 获取购物车产品的product_id1
     * @param int $customer_id
     * @param $delivery_type
     * @return array
     */
    public function getProductsId($customer_id, $delivery_type)
    {
       return $this->db->query("select product_id,combo_flag from oc_cart where customer_id = {$customer_id} and delivery_type = {$delivery_type}")->rows;
    }

    //一件代发、云送仓移动购物车
    public function change($cart_id,$from_delivery_type,$to_delivery_type){
        $result = $this->db->query("SELECT product_id,delivery_type,customer_id,quantity FROM ".DB_PREFIX ."cart WHERE cart_id=".$cart_id)->row;
        $cartNum = $this->db->query("SELECT count(1) as cartNum FROM ".DB_PREFIX ."cart WHERE product_id = ".$result['product_id']." AND customer_id=".$result['customer_id']." AND delivery_type='".$to_delivery_type."'")->row['cartNum'];
        if($cartNum>0) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "cart WHERE cart_id = '" . (int)$cart_id . "' AND api_id = '" . ((int)session('api_id', 0)) . "' AND customer_id = '" . (int)$this->customer->getId() . "' AND delivery_type ='".$from_delivery_type."'");
            $this->db->query("UPDATE " . DB_PREFIX . "cart SET sort_time = ".time().", quantity = quantity+" . $result['quantity'] . " WHERE product_id = ".$result['product_id']." AND api_id = '" . ((int)session('api_id', 0)) . "' AND customer_id = '" . (int)$this->customer->getId() . "' AND delivery_type='".$to_delivery_type."'");

        }else{
            $this->orm->table(DB_PREFIX.'cart')
                ->where([
                    'cart_id'       => $cart_id,
                    'api_id'        => ((int)session('api_id', 0)),
                    'customer_id'   => (int)$this->customer->getId(),
                    'delivery_type' => $from_delivery_type,
                ])
                ->update([
                    'delivery_type' => $to_delivery_type,
                    'sort_time'     => time()
                ]);
        }
    }

    public function getProductByCartId($cart_id){
        return $this->db->query("select op.sku,oc.product_id,oc.type_id,oc.agreement_id from ". DB_PREFIX . "cart oc left join ".DB_PREFIX."product op on op.product_id=oc.product_id where oc.cart_id = '".(int)$cart_id . "'")->row;
    }

    /**
     * 校验购物车能否切换
     * @param $cart_id
     * @param $to_delivery_type
     * @return bool
     */
    public function checkCartChange($cart_id,$to_delivery_type){
        $result = $this->db->query("SELECT product_id,type_id FROM ".DB_PREFIX."cart WHERE cart_id =".$cart_id)->row;
        $cartCount = $this->db->query("SELECT count(1) as cartCount FROM ".DB_PREFIX."cart WHERE customer_id= '" . (int)$this->customer->getId() . "' AND product_id=".$result['product_id']." AND delivery_type ='".$to_delivery_type."' AND type_id <>".$result['type_id'])->row['cartCount'];
        return $cartCount>0?false:true;
    }


    public function getFuturesInfo($agreement_id)
    {
        $info = $this->orm->table(DB_PREFIX . 'futures_margin_agreement as fa')
            ->leftJoin(DB_PREFIX . 'futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->leftJoin('oc_product_lock as l', function ($join) {
                $join->on('l.agreement_id', '=', 'fa.id')->where('l.type_id', '=', $this->config->get('transaction_type_margin_futures'));
            })
            ->where('fa.id', $agreement_id)
            ->selectRaw('fa.id,fa.agreement_no as agreement_code, fa.unit_price as agreement_price,fa.buyer_payment_ratio ,fa.unit_price, fd.last_unit_price as price,fa.product_id,fa.num as qty,
            fa.version,
            round(l.qty/l.set_qty) as left_qty, DATE_ADD(fd.update_time, INTERVAL 30 DAY) as expire_time')
            ->first();

        if ($this->customer->getCountryId() == JAPAN_COUNTRY_ID) {
            $decimal_place = 0;
        } else {
            $decimal_place = 2;
        }
        $info->deposit_per = round($info->unit_price * $info->buyer_payment_ratio / 100, $decimal_place);
        return obj2array($info);

    }

    public function getCartProductInfo($cart_id){
        $info = $this->orm->table(DB_PREFIX . 'cart as oc')
            ->leftJoin(DB_PREFIX."product as op",'oc.product_id','=','op.product_id')
            ->leftJoin(DB_PREFIX.'customerpartner_to_product as ctp','ctp.product_id','=','op.product_id')
            ->leftJoin(DB_PREFIX.'customer as c','c.customer_id','=','ctp.customer_id')
            ->where('oc.cart_id','=',$cart_id)
            ->select('op.product_type','ctp.customer_id','c.customer_group_id', 'oc.type_id', 'oc.agreement_id', 'oc.product_id')
            ->first();
        return obj2array($info);
    }


    //100828 购物车商品种类数量 CL
    public function productsNum($delivery_type = -1)
    {
        $api_id = (int)session('api_id', 0);

        return $this->orm->table('oc_cart')
            ->where('customer_id', $this->customer->getId())
            ->where('api_id','=', $api_id)
            ->when($delivery_type > -1, function ($query) use ($delivery_type){
                if (2 == $delivery_type){
                    return $query->where('delivery_type', '=', $delivery_type);
                }else{
                    return $query->whereIn('delivery_type', [0,1]);
                }
            })
            ->count();
    }

    //购物车按店铺分类
    public function storeProduct($delivery_type = -1)
    {
        $api_id = (int)session('api_id', 0);
        $select = [
            'c.cart_id',
            'c2c.customer_id as seller_id',
            'c2c.screenname',
            'customer.accounting_type',
        ];
        $storeProducts = $this->orm->table('oc_cart as c')
            ->leftJoin('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'c.product_id')
            ->leftJoin('oc_customerpartner_to_customer as c2c', 'c2c.customer_id', '=', 'c2p.customer_id')
            ->leftJoin('oc_customer as customer' , 'c2c.customer_id', '=', 'customer.customer_id')
            ->where('c.customer_id', $this->customer->getId())
            ->where('c.api_id','=', $api_id)
            ->where('c.delivery_type','=', $delivery_type)
            ->selectRaw(implode(',', $select))
            ->selectRaw(new Expression("IF(customer.email='joybuy-us@gigacloudlogistics.com', 1, 0) AS isNotMove"))
            ->orderBy('c.sort_time', 'desc')
            ->orderBy('c.cart_id', 'desc')
            ->get();
        $store = [];
        foreach ($storeProducts as $k=>$v)
        {
            $store[$v->seller_id]['accounting_type'] = $v->accounting_type;
            $store[$v->seller_id]['isNotMove'] = $v->isNotMove;
            $store[$v->seller_id]['name'] = $v->screenname;
            $store[$v->seller_id]['cart_id_arr'][] = $v->cart_id;
        }
        return $store;
    }

    /**
     * 根据cart ID获取购物车信息
     * @param int $cartId
     * @return array
     */
    public function getInfoByCartId($cartId)
    {
        return obj2array($this->orm->table('oc_cart')
            ->where('cart_id', $cartId)
            ->first());
    }

    //批量移除购物车
    public function batchRemove($cartIdArr,$buyerId=0)
    {
        return $this->orm->table('oc_cart')
            ->whereIn('cart_id', $cartIdArr)
            ->when($buyerId, function ($query) use ($buyerId){
                return $query->where('customer_id', $buyerId);
            })
            ->delete();
    }

    //判断购物车中是否已存在该发货方式的该商品
    public function hasThisProduct($delivery_type, $productId)
    {
        $data = $this->orm->table('oc_cart')
            ->where([
                'customer_id'   => $this->customer->getId(),
                'delivery_type' => $delivery_type,
                'product_id'    => $productId,
            ])
            ->first();
        return obj2array($data);
    }

    public function deleteCart($cartIdArr){
        $this->orm->table('oc_cart')
            ->whereIn('cart_id',$cartIdArr)
            ->delete();
    }

    public function getSalesOrderId($lineId)
    {
        return $this->orm->table("tb_sys_customer_sales_order_line")
            ->where('id', '=', $lineId)
            ->value('header_id');
    }
    public function removeAssociateAndComboInfo($salesOrderId)
    {
        $this->orm->table('tb_sys_customer_sales_order as cso')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'csol.header_id', '=', 'cso.id')
            ->where('cso.id', '=', $salesOrderId)
            ->update([
                'csol.combo_info' => ''
            ]);

        $this->orm->table('tb_sys_order_associated')
            ->where('sales_order_id', '=', $salesOrderId)
            ->delete();
    }

    public function getAutoBuyCartId($buyer_id)
    {
        $result = $this->orm->table('oc_cart')
            ->where('api_id','=',1)
            ->where('customer_id','=',$buyer_id)
            ->get('cart_id');
        return obj2array($result);
    }
}
