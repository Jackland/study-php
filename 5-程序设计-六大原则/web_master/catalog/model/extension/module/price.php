<?php

use App\Enums\Future\FuturesVersion;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Enums\Spot\SpotProductQuoteStatus;
use App\Exception\SalesOrder\AutoPurchaseException;
use App\Exception\SalesOrder\Enums\AutoPurchaseCode;
use App\Helper\MoneyHelper;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\Marketing\MarketingTimeLimitProduct;
use App\Models\Product\ProQuoteDetail;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use App\Repositories\Product\ProductPriceRepository;
use Illuminate\Support\Carbon;
use App\Repositories\Marketing\MarketingDiscountRepository;

/**
 * Class ModelExtensionModulePrice
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 */
class ModelExtensionModulePrice extends Model
{
    protected $product_id;
    protected $customer_id;
    const COUNTRY_JAPAN = 107;

    /**
     * @var ModelCatalogProduct $catalog_product
     */
    private $catalog_product;

    /** @var ModelExtensionModuleProductShow $productShowModel */
    private $product_show;

    protected $country_id;
    protected $isCollectionFromDomicile;
    protected $transaction_type;
    protected $error_list = [
        'Product id %s is unavailable.',
        'Product id %s quantity is %s, The amount need to buy is %s.',
        'No products can buy, It needs to be associated with the seller.',
        'No products can buy, all products quantity are not available',

    ];

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('catalog/product');
        $this->catalog_product = $this->model_catalog_product;
        $this->load->model('extension/module/product_show');
        $this->product_show = $this->model_extension_module_product_show;
        $this->transaction_type = ['transaction_type_normal', 'transaction_type_rebate', 'transaction_type_margin_spot', 'transaction_type_margin_futures'];
    }

    /**
     * [getMarginAgreementId description] 根据头款商品获取MarginAgreementId
     * @param int $product_id
     * @return string
     */
    public function getMarginAgreementId($product_id)
    {
        $map = [
            'process_status' => 1,
            'advance_product_id' => $product_id,
        ];

        return $this->orm->table('tb_sys_margin_process')->where($map)->value('margin_agreement_id');

    }

    /**
     * [getProductPriceInfo description] 获取该用户能看到的价格
     * @param int $product_id
     * @param int $customer_id
     * @param array $type 0 精细化价格 1 返点价格 2.现货保证金价格 3.期货保证金尾款价格 4.议价协议价格
     * @param bool $is_partner
     * @param bool $isDiscount 是否获取折扣价
     * @param array $extend 扩展参数
     * @return array
     */
    public function getProductPriceInfo($product_id, $customer_id, $type = [], $is_partner = false, $isDiscount = false, $extend = [])
    {

        $this->setCustomerInfo($customer_id);
        $currency = $this->session->get('currency');
        $precision = $this->currency->getDecimalPlace($currency);
        //获取初始的price
        $ret = [];
        $base_info = $this->getOcProductPrice($product_id);
        $base_info['country_id'] = $this->country_id;
        $base_info['isCollectionFromDomicile'] = $this->isCollectionFromDomicile;
        $base_info['normal_price']= $base_info['price'];
        if ($base_info['status'] == 1 && $base_info['sellerStatus'] == 1) {
            $base_info['status'] = 1;
        } else {
            $base_info['status'] = 0;
        }
        if (!$base_info) {
            return $ret;
        }
        if (!$type) {
            $type = [ProductTransactionType::NORMAL, ProductTransactionType::REBATE, ProductTransactionType::MARGIN, ProductTransactionType::FUTURE, ProductTransactionType::SPOT];
        }
        $listQuote = [];//议价列表
        $list = [];//各种协议的列表(非议价协议列表)
        //价格做一些处理
        //计算一下单价总价
        if ($base_info['isCollectionFromDomicile']) {
            $freight = $base_info['package_fee'];
        } else {
            $freight = $base_info['package_fee'] + $base_info['freight'];
        }
        $base_info['freight'] = round($freight, 2);

        if ($type) {
            foreach ($type as $key => $value) {
                //
                if ($value == ProductTransactionType::NORMAL) {
                    //精细化仅仅提供price  unavailable
                    $tmp = $this->getDelicacyManagementPrice($product_id, $customer_id);
                    $base_info['delicacy_management_price'] = $tmp['price'];
                    if ($base_info['delicacy_management_price']) {
                        $base_info['price_all'] = $tmp['price'] + $freight;
                        $base_info['price'] = $tmp['price'];
                        $base_info['is_delicacy'] = true;
                    } else {
                        $base_info['price_all'] = $base_info['price'] + $freight;
                        $base_info['is_delicacy'] = false;
                    }

                    // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                    if ($base_info['product_type'] == ProductType::NORMAL) {
                        $base_info['price'] = app(ProductPriceRepository::class)
                            ->getProductActualPriceByBuyer(intval($base_info['customer_id']), intval($customer_id), $base_info['price']);
                        $base_info['price_all'] = $base_info['price'] + $freight;
                    }

                    // #22763 大客户-折扣 #31737 优化逻辑 （非精细化）
                    $base_info['qty_type_str'] = 'Available';
                    if ($isDiscount) {
                        //获取产品最大折扣
                        $maxDiscount = app(MarketingDiscountRepository::class)->getMaxDiscount($customer_id, $product_id, $extend['qty'] ?? 0, empty($extend['use_wk_pro_quote_price']) ? ProductTransactionType::NORMAL : ProductTransactionType::SPOT);
                        // 如果NORMAL类型时没有折扣，查看一下SPOT-阶梯价类型有没有
//                        if (empty($maxDiscount) && !$base_info['is_delicacy'] && !isset($extend['use_wk_pro_quote_price'])) {
//                            $wkProQuoteDetail = ProQuoteDetail::query()->where('product_id', $product_id)
//                                ->where('min_quantity', '<=', $extend['qty'])
//                                ->where('max_quantity', '>=', $extend['qty'])
//                                ->first();
//                            if (!empty($wkProQuoteDetail)) {
//                                $maxDiscount = app(MarketingDiscountRepository::class)->getMaxDiscount($customer_id, $product_id, $extend['qty'] ?? 0, ProductTransactionType::SPOT);
//                            }
//                        }
                        $discount = $maxDiscount->discount ?? null;
                        $discountRate = $discount ? intval($discount) / 100 : 1;
                        $base_info['price'] = MoneyHelper::upperAmount($base_info['price'] * $discountRate, $precision);
                        $base_info['discount'] = $discount;
                        // 获取限时限量折扣
                        $timeLimitDiscount = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountInfo($product_id);
                        // 判断有没有限时限量折扣
                        if ($timeLimitDiscount) {
                            if ($maxDiscount instanceof MarketingTimeLimitProduct) {
                                $timeLimitDiscount->time_limit_buy = true;
                            } else {
                                $timeLimitDiscount->time_limit_buy = false;
                            }
                            $availableQty = $base_info['quantity']; // 上架库存
                            $base_info['quantity'] = $availableQty - $timeLimitDiscount->qty - $timeLimitDiscount->other_time_limit_qty; // 当存在限时限量折扣时，　其他库存　＝　上架库存　－　最近一个限时限量折扣库存　－　其他未来的限时限量折扣库存
                            $base_info['quantity'] < 0 && $base_info['quantity'] = 0;
                            $base_info['time_limit_qty'] = 0;
                            $base_info['time_limit_buy'] = $timeLimitDiscount->time_limit_buy; // 当前是否用限时限量折扣购买
                            $base_info['time_limit_starting'] = $timeLimitDiscount->starting;
                            if ($timeLimitDiscount->starting) {
                                $base_info['time_limit_price'] = MoneyHelper::upperAmount($base_info['normal_price'] * $timeLimitDiscount->discount/ 100, $precision);
                                $base_info['time_limit_qty'] = ($timeLimitDiscount->qty < $availableQty) ? $timeLimitDiscount->qty : $availableQty; // 活动库存
                                $base_info['qty_type_str'] = 'Non-promotional';
                            }
                            if (!isset($extend['no_use_time_limit_qty']) && $timeLimitDiscount->time_limit_buy) {
                                $base_info['discount'] = $timeLimitDiscount->discount;
                                $base_info['quantity'] = $base_info['time_limit_qty'];
                                $base_info['qty_type_str'] = 'Promotional';
                            }
                        }
                        $base_info['price_all'] = $base_info['price'] + $freight;
                    }

                    $base_info['precision'] = $precision;
                    $base_info['price_all'] = round($base_info['price_all'], $precision);
                    $base_info['price'] = round($base_info['price'], $precision);
                    $base_info['freight'] = round($base_info['freight'], $precision);

                    $base_info['freight_show'] = $this->currency->format($base_info['freight'], $currency);
                    $base_info['price_all_show'] = $this->currency->format($base_info['price_all'], $currency);
                    $base_info['price_show'] = $this->currency->format($base_info['price'], $currency);
                    $base_info['unavailable'] = $tmp['unavailable'];
                    $base_info['type'] = $value;
                } else {
                    $tmp = [];
                    $agreementName = '';
                    switch ($value) {
                        case ProductTransactionType::REBATE://返点
                            $tmp = $this->getRebatePrice($product_id, $customer_id, $is_partner);
                            $agreementName = 'Rebate';
                            break;
                        case ProductTransactionType::MARGIN://现货保证金
                            $tmp = $this->getCurrentMarginPrice($product_id, $customer_id, $is_partner);
                            $agreementName = 'Margin';
                            break;
                        case ProductTransactionType::FUTURE://期货保证金
                            $tmp = $this->getCurrentFuturesPrice($product_id, $customer_id, $is_partner);
                            $agreementName = 'Future Goods';
                            break;
                        case ProductTransactionType::SPOT://阶梯价&议价
                            $tmp = $this->getCurrentSpotPrice($product_id, $customer_id, $is_partner);
                            $agreementName = 'Spot Price';
                            break;
                    }
                    unset($item);
                    foreach ($tmp as &$item) {
                        $item['version'] = $item['version'] ?? null;
                        // 因为期货v3尾款不能分批购买，所以自动购买去除期货的v3
                        if (ProductTransactionType::FUTURE == $value && !empty($extend['filter_future_v3']) && $item['version'] == FuturesVersion::VERSION) {
                            continue;
                        }
                        $item['price_all'] = round($item['price'] + $freight, $precision);
                        $item['price'] = round($item['price'], $precision);
                        $item['price_all_show'] = $this->currency->format($item['price_all'], $currency);
                        $item['price_show'] = $this->currency->format($item['price'], $currency);
                        $item['agree_price_show'] = $this->currency->format($item['agree_price'] ?? 0, $currency);
                        $item['agreement_code'] = empty($item['agreement_code']) ? $item['id'] : $item['agreement_code'];
                        $item['agreement_name'] = $agreementName;
                        $item['type'] = $value;
                        $item['left_qty'] = $value == ProductTransactionType::SPOT ? $base_info['quantity'] : $item['left_qty'];
                        if ($value == ProductTransactionType::REBATE) {
                            $item['agreement_show_quantity'] = $base_info['quantity'];
                        }
                        $item['left_time_secs'] = ($item['expire_time']) ? strtotime($item['expire_time']) - time() : -1;
                        //大客户折扣相关
                        if ($value == ProductTransactionType::MARGIN) {
                            //$item['deposit_per'] = $item['deposit_per']; //sql查询的有
                        } elseif ($value == ProductTransactionType::FUTURE) {
                            //其它地方也是直接四舍五入计算的
                            $item['deposit_per'] = round($item['unit_price'] * $item['buyer_payment_ratio'] / 100, customer()->isJapan() ? 0 : 2);
                            unset($item['unit_price'], $item['buyer_payment_ratio']);
                        }
                        if ($value == ProductTransactionType::SPOT) {//议价
                            $listQuote[] = $item;
                        } else {
                            $list[] = $item;
                        }
                    }
                    unset($item);
                }
            }
        }
        //对 非议价协议列表 进行排序
        $list = $this->transactionListSort($list);

        //对 议价协议列表 进行排序
        $listQuote = $this->transactionListSort($listQuote);

        $list = array_merge($listQuote, $list);//议价协议 与 非议价协议 合并，议价协议排在最前。

        $ret['base_info'] = $base_info;
        $ret['transaction_type'] = $list;
        $ret['first_get'] = $this->compareWithPriceInfo($base_info, $list);
        return $ret;
    }

    /**
     * 多种复杂交易协议排序
     *
     * @param $transactionList
     *
     * @return array 排序后数据格式返回
     */
    public function transactionListSort($transactionList)
    {
        if (empty($transactionList)) {
            return [];
        }
        $num1 = array_column($transactionList, 'expire_time');// ASC
        $num2 = array_column($transactionList, 'price');// ASC
        $num3 = [];
        foreach ($transactionList as $key => $item) {
            $num3[$key] = $item['type'] == ProductTransactionType::SPOT ? 1 : 0;
        }
        array_multisort($num1, SORT_ASC, $num2, SORT_ASC, $num3, SORT_DESC, $transactionList);
        return $transactionList;
    }

    /**
     * [compareWithPriceInfo description]
     * @param $base_info
     * @param $list
     * @return mixed
     */
    public function compareWithPriceInfo($base_info, $list)
    {
        if (!$list) {
            //啥都没有，直接返回基础价格
            return $base_info;
        } else {
            $prices = array_column($list, 'price');
            asort($prices);
            $key = key($prices);
            if (bccomp($prices[$key], $base_info['price'], 2) === -1 || bccomp($prices[$key], $base_info['price'], 2) === 0) {
                return $list[$key];
            }
            return $base_info;
        }

    }

    /**
     * [getOcProductPrice description] 给出价格
     * @param int $product_id
     * @return array
     * @since 2020-06-30 11:33:00 打包费取自 oc_product_fee 字段 by Lester.You
     */
    public function getOcProductPrice($product_id)
    {
        $map = [
            //'buyer_flag' => 1,
            //'status'     => 1,
            'p.product_id' => $product_id,
        ];
        $list = $this->orm->table(DB_PREFIX . 'product as p')
            ->leftJoin('oc_product_fee as pf', function ($query) {
                $query->on('pf.product_id', '=', 'p.product_id')
                    ->where('pf.type', '=', $this->isCollectionFromDomicile ? 2 : 1);
            })
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer as oc', 'oc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'oc.customer_id')
            ->where($map)
            ->select('p.product_id', 'p.price', 'p.freight', 'p.quantity', 'p.buyer_flag', 'p.status', 'p.combo_flag', 'oc.customer_id', 'ctc.screenname', 'p.image', 'p.product_type')
            ->selectRaw('IFNULL(pf.fee,0) as package_fee,oc.status as sellerStatus')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return current($list);
    }

    /**
     * [getDelicacyManagementPrice description] 获取精细化价格
     * @param int $product_id
     * @param int $customer_id
     * @return array|null
     */
    public function getDelicacyManagementPrice($product_id, $customer_id)
    {
        $dm_info = $this->catalog_product->getDelicacyManagementInfoByNoView($product_id, $customer_id);
        $ret = [];
        if ($dm_info && $dm_info['product_display'] && isset($dm_info['current_price'])) {
            $ret['price'] = $dm_info['current_price'];
            $ret['unavailable'] = 0;
        } elseif ($dm_info && $dm_info['product_display'] == 0) {
            $ret['price'] = 0;
            $ret['unavailable'] = 1;
        } else {
            $ret['price'] = 0;
            $ret['unavailable'] = 0;
        }

        $exists = $this->orm->table('oc_rebate_agreement as a')
            ->join('oc_rebate_agreement_item as i', 'i.agreement_id', '=', 'a.id')
            ->join('oc_delicacy_management as dm', [['a.buyer_id', '=', 'dm.buyer_id'], ['a.seller_id', '=', 'dm.seller_id'], ['i.product_id', '=', 'dm.product_id'], ['a.effect_time', '=', 'dm.effective_time']])
            ->where([
                ['a.status', '=', 3],
                ['a.buyer_id', '=', $customer_id],
                ['dm.product_id', '=', $product_id],
                ['a.expire_time', '>', date('Y-m-d H:i:s')],
                ['dm.product_display', '=', 1]
            ])
            ->exists();
        if ($exists) {
            $ret['price'] = 0;
            $ret['unavailable'] = 0;;
        }
        return $ret;

    }

    /**
     * [getCurrentMarginPrice description]
     * @param int $product_id
     * @param int $customer_id
     * @param bool $is_partner
     * @return array
     */
    public function getCurrentMarginPrice($product_id, $customer_id, $is_partner = false)
    {
        //保证金存在履约人的情况下
        // 获取尾款价格
        $str = 'p.buyer_id';
        if ($is_partner) {
            $str = 'm.seller_id';
        }
        $map = [
            ['m.product_id', '=', $product_id],
            [$str, '=', $customer_id],
            ['m.expire_time', '>', date('Y-m-d H:i:s', time())],
            ['m.status', '=', 6], //sold
            ['l.qty', '!=', 0],
        ];
        return db('tb_sys_margin_agreement as m')
            ->leftJoin(DB_PREFIX . 'product_lock as l', function ($join) {
                $join->on('l.agreement_id', '=', 'm.id')->where('l.type_id', '=', $this->config->get('transaction_type_margin_spot'));
            })
            ->leftJoin(DB_PREFIX . 'agreement_common_performer as p', function ($join) {
                $join->on('p.agreement_id', '=', 'm.id')->where('p.agreement_type', '=', $this->config->get('common_performer_type_margin_spot'));
            })
            ->where($map)
            ->selectRaw('m.id,m.expire_time,m.price as agreement_price,m.agreement_id as agreement_code,m.price as agree_price,round(m.price - m.deposit_per,2) as price,m.product_id,m.day,m.num as qty,round(l.qty/l.set_qty) as left_qty,m.deposit_per')
            ->orderBy('m.expire_time')
            ->groupBy(['m.id'])
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
    }

    public function getCurrentSpotPrice($product_id, $customer_id, $is_partner = false)
    {
        $ret = $this->orm->table('oc_product_quote as q')
            ->when(!$is_partner, function ($q) use ($product_id, $customer_id) {
                return $q->where([
                    'q.product_id' => $product_id,
                    'q.customer_id' => $customer_id,
                    'q.status' => SpotProductQuoteStatus::APPROVED,
                ]);
            })
            ->when($is_partner, function ($q) use ($product_id, $customer_id) {
                return $q->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'q.product_id')
                    ->where([
                        'q.product_id' => $product_id,
                        'ctp.customer_id' => $customer_id,
                        'q.status' => SpotProductQuoteStatus::APPROVED,
                    ]);
            })
            ->selectRaw('q.id, q.agreement_no as agreement_code, q.price, q.product_id, q.quantity as qty,
            q.quantity as left_qty,
            DATE_ADD(q.date_approved, INTERVAL 1 DAY) as expire_time')
            ->orderBy('q.id', 'desc')
            ->get();

        return obj2array($ret);
    }

    /*
     * 获取该商品的期货尾款单价列表
     * */
    public function getCurrentFuturesPrice($product_id, $customer_id, $is_partner = false)
    {
        $str = 'fa.buyer_id';
        if ($is_partner) {
            $str = 'fa.seller_id';
        }
        // Seller交付后，Buyer需要在7个自然日内支付尾款,否则产品不可购买，去协商终止协议
        //        $time30 = date('Y-m-d H:i:s', strtotime('- 30 day'));
        //        $time7 = date('Y-m-d H:i:s', strtotime('- 7 day'));
        $ret = $this->orm->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->leftJoin('oc_product_lock as l', function (\Illuminate\Database\Query\JoinClause $join) {
                $join->on('l.agreement_id', '=', 'fa.id')->where('l.type_id', '=', $this->config->get('transaction_type_margin_futures'));
            })
            ->leftJoin('oc_customer as buyer', 'fa.buyer_id', '=', 'buyer.customer_id')
            ->leftJoin('oc_country as country', 'buyer.country_id', '=', 'country.country_id')
            ->where([
                'fa.product_id' => $product_id,
                $str => $customer_id,
                'fa.agreement_status' => 7,//sold
                'fd.delivery_status' => 6,//To be Paid
            ])
            //            ->where(function ($query) use ($time30, $time7) {
            //                // 期货二期需要在7个自然日内支付尾款,期货一期需要在30个自然日内支付尾款
            //                $query->where([
            //                    ['fd.delivery_date', '>', $time7],
            //                    ['fa.contract_id', '>', 0]
            //                ])->orWhere([
            //                    ['fd.delivery_date', '>', $time30],
            //                    ['fa.contract_id', '=', 0]
            //                ]);
            //            })
            ->where('fd.delivery_type', '!=', 2)
            ->where('l.qty', '>', 0)
            ->select(['fa.unit_price','fa.buyer_payment_ratio'])
            ->selectRaw('fa.id,fa.agreement_no as agreement_code,fa.unit_price as agree_price, fa.unit_price as agreement_price, fd.last_unit_price as price,fa.product_id,fa.num as qty,
            round(l.qty/l.set_qty) as left_qty,fd.confirm_delivery_date,fa.create_time,
            DATE_ADD( DATE_FORMAT( fd.confirm_delivery_date, "%Y-%m-%d %H:%i:%s" ), INTERVAL 1 DAY ) AS expire_time')
            ->addSelect('fa.version', 'country.iso_code_3')
            ->orderBy('fa.id', 'desc')
            ->groupBy('fa.id')
            ->get();
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        foreach ($ret as $key => $value) {
            if ($value->version < FuturesVersion::VERSION) {//针对期货协议旧版本(第三期之前)seller审核交割日期的过期时间为Buyer7天后最后一秒
                $toZone = CountryHelper::getTimezoneByCode($value->iso_code_3);
                $tmp_expire_time = date('Y-m-d 23:59:59', strtotime($value->confirm_delivery_date . '+7 day'));
                $value->expire_time = dateFormat($fromZone, $toZone, $tmp_expire_time, 'Y-m-d H:i:s');
                $ret[$key] = $value;
            }
        }
        return obj2array($ret);
    }

    /**
     * [getRebatePrice description]
     * @param int $product_id
     * @param int $customer_id
     * @param bool $is_partner
     * @return array
     */
    public function getRebatePrice($product_id, $customer_id, $is_partner = false)
    {
        $flag = $this->getCurrentMarginPrice($product_id, $customer_id);
        $str = 'a.buyer_id';
        if ($is_partner) {
            $str = 'a.seller_id';
        }
        if ($flag) {
            //必须要查找正在履行的返点交易
            $map = [
                [$str, '=', $customer_id],
                ['ai.product_id', '=', $product_id],
                ['a.expire_time', '>', date('Y-m-d H:i:s', time())],
                ['a.status', '=', 3],
            ];
            //首先要获取现货保证金交易：Agreement Status = Sold，协议未完成数量 ≠ 0；
            $ret = $this->orm->table(DB_PREFIX . 'rebate_agreement_item as ai')
                ->leftJoin(DB_PREFIX . 'rebate_agreement as a', 'a.id', '=', 'ai.agreement_id')
                ->where($map)
                ->whereIn('a.rebate_result', [1, 2])
                ->selectRaw('a.agreement_code,ai.template_price as price,ai.product_id,a.id,a.qty,a.expire_time')
                ->get()
                ->map(function ($value) {
                    return (array)$value;
                })
                ->toArray();
            if ($ret) {
                //检测数量是否完成，如果已经完成，则需要剔除返点价格
                foreach ($ret as $key => $value) {
                    $total_purchased = 0;
                    $mapOrder = [
                        ['rao.agreement_id', '=', $value['id']],
                    ];
                    $order_info = $this->orm->table(DB_PREFIX . 'rebate_agreement_order as rao')
                        ->where($mapOrder)
                        ->select()
                        ->get()
                        ->map(
                            function ($v) {
                                return (array)$v;
                            })
                        ->toArray();
                    if ($order_info) {
                        foreach ($order_info as $ks => $vs) {
                            if ($vs['type'] == 1) {
                                $total_purchased += $vs['qty'];
                            } elseif ($vs['type'] == 2) {
                                $total_purchased -= $vs['qty'];
                            }
                        }
                    }

                    if ($value['qty'] <= $total_purchased) {
                        //已经完成rebate
                        unset($ret[$key]);
                    } else {
                        $ret[$key]['left_qty'] = $value['qty'] - $total_purchased;
                    }

                }
                $ret = array_values($ret);
            }
        } else {
            //返点协议没有过期 Buyer还可以选择该返点协议返点的专享价成交
            $map = [
                [$str, '=', $customer_id],
                ['ai.product_id', '=', $product_id],
                ['a.status', '=', 3],
                ['a.expire_time', '>', date('Y-m-d H:i:s', time())],
            ];
            $ret = $this->orm->table(DB_PREFIX . 'rebate_agreement_item as ai')
                ->leftJoin(DB_PREFIX . 'rebate_agreement as a', 'a.id', '=', 'ai.agreement_id')
                ->where($map)
                ->selectRaw('a.agreement_code,ai.template_price as price,ai.product_id,a.id,a.qty,a.expire_time')
                ->get()
                ->map(function ($value) {
                    return (array)$value;
                })
                ->toArray();

            //为了排序
            if ($ret) {
                //检测数量是否完成，如果已经完成，则需要剔除返点价格
                foreach ($ret as $key => $value) {
                    $total_purchased = 0;
                    $mapOrder = [
                        ['rao.agreement_id', '=', $value['id']],
                    ];
                    $order_info = $this->orm->table(DB_PREFIX . 'rebate_agreement_order as rao')
                        ->where($mapOrder)
                        ->select()
                        ->get()
                        ->map(
                            function ($v) {
                                return (array)$v;
                            })
                        ->toArray();
                    if ($order_info) {
                        foreach ($order_info as $ks => $vs) {
                            if ($vs['type'] == 1) {
                                $total_purchased += $vs['qty'];
                            } elseif ($vs['type'] == 2) {
                                $total_purchased -= $vs['qty'];
                            }
                        }
                    }

                    if ($value['qty'] <= $total_purchased) {
                        //已经完成rebate
                        $ret[$key]['left_qty'] = 0;
                    } else {
                        $ret[$key]['left_qty'] = $value['qty'] - $total_purchased;
                    }

                }
                $ret = array_values($ret);
            }
        }

        return $ret;

    }

    public function setCustomerInfo($customer_id)
    {
        //设置国别和类型
        if ($customer_id == $this->customer->getId()) {
            $this->isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
            $this->country_id = $this->customer->getCountryId();
        } else {
            $this->isCollectionFromDomicile = $this->product_show->get_is_collection_from_domicile($customer_id);
            $this->country_id = $this->orm->table(DB_PREFIX . 'customer')->where('customer_id', $customer_id)->value('country_id');
        }

    }

    /**
     * [verifyAutoBuyItemCode description] 取b2b有效的sku其中之一即可 sku,sku1
     * @param $data
     * @return string
     */
    public function verifyAutoBuyItemCode($data)
    {
        $list = explode(',', $data);
        if (count($list) == 1) {
            return $data;
        } else {
            foreach ($list as $key => $value) {
                // 验证传的item_code 是否有效
                // 简单验证b2bsku是否可以购买即可
                $map = [
                    'sku' => $value,
                    'buyer_flag' => 1,
                    'is_deleted' => 0,
                    'status' => 1
                ];
                $flag = $this->orm->table(DB_PREFIX . 'product')->where($map)->exists();
                if ($flag) {
                    return $value;
                }

            }
        }
        return false;
    }


    /**
     * [getAutoBuyProductId description] 根据item_code customer_id quantity 找到优先购买的店铺
     * @param $item_code
     * @param int $customer_id
     * @param $quantity
     * @param int $seller_id
     * @return string | array
     * @throws AutoPurchaseException
     */
    public function getAutoBuyProductId($item_code, $customer_id, $quantity, $seller_id = 0)
    {
        //进入自动购买的多个SKU，只能有一个生效，productID可以有多个匹配；
        //商品优先级排序：自动购买seller店铺优先级高的优先（oc_buyer_to_seller.auto_buy_sort 越大越优先）-> 存在限期协议，结束日期最近越优先 -> 实际支付单价越低越优先 -> 剩余协议数量越少越优先 -> 上架数量越多越优先；
        //100442 当有指定seller的时候，只允许加购该店铺的商品
        $map = [
            'bts.buy_status' => 1,
            'bts.buyer_control_status' => 1,
            'bts.seller_control_status' => 1,
            'p.buyer_flag' => 1,
            'p.is_deleted' => 0,
            'p.status' => 1,
            'p.sku' => $item_code,
        ];
        if ($seller_id) {
            $map['ctp.customer_id'] = $seller_id;
        }
        $product_list = $this->orm->table(DB_PREFIX . 'product as p')
            ->leftJoin(DB_PREFIX . 'customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin(DB_PREFIX . 'buyer_to_seller as bts', function ($join) use ($customer_id) {
                $join->on('bts.seller_id', '=', 'ctp.customer_id')->where('bts.buyer_id', '=', $customer_id);
            })
            ->where($map)
            ->orderBy('bts.auto_buy_sort', 'desc')
            ->select('p.product_id', 'bts.auto_buy_sort')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        //根据排序去购买
        if ($product_list) {
            $verify_product = [];
            $currentTime = time();
            foreach ($product_list as $key => $value) {
                $all_product_info = $this->getProductPriceInfo($value['product_id'], $customer_id, [], false, true,['filter_future_v3' => 1, 'qty' => $quantity]);
                // 记录加入购物车的价格详情
                Logger::autoPurchase(['price info', 'info',
                    Logger::CONTEXT_VAR_DUMPER => [
                        'customer_id' => $customer_id,
                        'product_id' => $value['product_id'],
                        'all_product_info' => $all_product_info
                    ],
                ]);// 按照可视化形式输出

                $res = $this->verifyAutoBuyQuantityByProductInfo($all_product_info, $quantity, $currentTime);
                // price 》 9999999 阈值是不对的所以应该排除
                if ($res['error'] == '0' && $res['price'] < 9990000) {
                    $res['auto_buy_sort'] = $value['auto_buy_sort'];
                    $verify_product[] = $res;
                }
            }
            if ($verify_product) {
                $num1 = [];
                $num2 = [];
                $num3 = [];
                $num4 = [];
                foreach ($verify_product as $ks => $vs) {
                    $num1[$ks] = $vs ['auto_buy_sort'];
                    $num2[$ks] = $vs ['expire_time'];
                    $num3[$ks] = $vs ['price'];
                    $num4[$ks] = $vs ['left_qty'];

                }
                array_multisort($num1, SORT_DESC, $num2, SORT_ASC, $num3, SORT_ASC, $num4, SORT_DESC, $verify_product);
                return current($verify_product);
            } else {
                Logger::autoPurchase(['all verify failed', 'error',
                    Logger::CONTEXT_VAR_DUMPER => [
                        'product_list' => $product_list,
                    ], // 按照可视化形式输出
                ]);
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_产品加入购物车失败_库存不足, $item_code);
            }
        } else {
            Logger::autoPurchase(['no product match', 'error',
                Logger::CONTEXT_VAR_DUMPER => [
                    'product_list' => $product_list,
                ], // 按照可视化形式输出
            ]);
            throw new AutoPurchaseException(AutoPurchaseCode::CODE_产品加入购物车失败_seller未与当前用户建立联系, $item_code);
        }
    }

    /**
     * [verifyAutoBuyQuantityByProductInfo description]  自动购买根据产品id
     * @param $data
     * @param $quantity
     * @return bool | array
     */
    public function verifyAutoBuyQuantityByProductInfo($data, $quantity, $currentTime)
    {
        if ($data['base_info']['unavailable'] == 1) {
            return [
                'error' => sprintf($this->error_list[0], $data['base_info']['product_id']),
            ];
        } else {
            if ($data['transaction_type']) {
                // 依次循环比对特殊交易类型中的数量能不能购买
                foreach ($data['transaction_type'] as $key => $value) {
                    if ($value['type'] == ProductTransactionType::FUTURE) {
                        //  期货二期
                        if ($value['left_qty'] >= $quantity) {
                            return [
                                'product_id' => $data['base_info']['product_id'],
                                'type_id' => $value['type'],
                                'agreement_id' => $value['id'],
                                'left_qty' => $value['left_qty'],
                                'price' => $value['price_all'],
                                'expire_time' => $value['expire_time'],
                                'error' => 0,
                            ];
                        }
                    } elseif ($value['type'] == ProductTransactionType::MARGIN) {
                        //  保证金
                        if ($value['left_qty'] >= $quantity) {
                            return [
                                'product_id' => $data['base_info']['product_id'],
                                'type_id' => $value['type'],
                                'agreement_id' => $value['id'],
                                'left_qty' => $value['left_qty'],
                                'price' => $value['price_all'],
                                'expire_time' => $value['expire_time'],
                                'error' => 0,
                            ];
                        }

                    } elseif ($value['type'] == ProductTransactionType::REBATE) {
                        if ($data['base_info']['quantity'] >= $quantity) {
                            return [
                                'product_id' => $data['base_info']['product_id'],
                                'type_id' => $value['type'],
                                'agreement_id' => $value['id'],
                                'left_qty' => $data['base_info']['quantity'],
                                'price' => $value['price_all'],
                                'expire_time' => $value['expire_time'],
                                'error' => 0,
                            ];
                        }
                    } elseif ($value['type'] == ProductTransactionType::SPOT) {
                        if ($value['qty'] == $quantity) {
                            return [
                                'product_id' => $data['base_info']['product_id'],
                                'type_id' => $value['type'],
                                'agreement_id' => $value['id'],
                                'left_qty' => $value['qty'],
                                'price' => $value['price_all'],
                                'expire_time' => $value['expire_time'],
                                'error' => 0,
                            ];
                        }
                    }
                }

                // 复杂交易都数量不足够，只能返回 oc_product 数量
                // 直接比对quantity够不够
                if ($data['base_info']['quantity'] >= $quantity) {
                    return [
                        'product_id' => $data['base_info']['product_id'],
                        'type_id' => $data['base_info']['type'],
                        'agreement_id' => null,
                        'left_qty' => $data['base_info']['quantity'],
                        'price' => $data['base_info']['price_all'],
                        'expire_time' => date("Y-m-d H:i:s", strtotime("+1years", $currentTime)),
                        'error' => 0,
                    ];
                } else {
                    return [
                        'error' => sprintf($this->error_list[1], $data['base_info']['product_id'], $data['base_info']['quantity'], $quantity),
                    ];
                }
            } else {
                // 直接比对quantity够不够
                if ($data['base_info']['quantity'] >= $quantity) {
                    return [
                        'product_id' => $data['base_info']['product_id'],
                        'type_id' => $data['base_info']['type'],
                        'agreement_id' => null,
                        'left_qty' => $data['base_info']['quantity'],
                        'price' => $data['base_info']['price_all'],
                        'expire_time' => date("Y-m-d H:i:s", strtotime("+1years", $currentTime)),
                        'error' => 0,
                    ];
                } else {
                    return [
                        'error' => sprintf($this->error_list[1], $data['base_info']['product_id'], $data['base_info']['quantity'], $quantity),
                    ];
                }
            }
        }

    }


}
