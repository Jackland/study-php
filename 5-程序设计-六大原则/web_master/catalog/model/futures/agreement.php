<?php

use App\Enums\Future\FutureMarginContractStatus;
use App\Enums\Future\FuturesMarginApplyStatus;
use App\Enums\Future\FuturesMarginApplyType;
use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Enums\Future\FuturesMarginDeliveryStatus;
use App\Enums\Future\FuturesMarginPayRecordFlowType;
use App\Enums\Future\FuturesMarginPayRecordType;
use App\Enums\Future\FuturesVersion;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Pay\PayCode;
use App\Enums\Product\ProductLockType;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Models\Futures\FuturesAgreementApply;
use App\Models\Futures\FuturesAgreementMarginPayRecord;
use App\Models\Futures\FuturesMarginMessage;
use App\Models\Margin\MarginAgreement;
use App\Repositories\Common\SerialNumberRepository;
use App\Widgets\VATToolTipWidget;
use Carbon\Carbon;
use Catalog\model\filter\FutureAgreementFilter;
use Catalog\model\futures\agreementMargin;
use Catalog\model\futures\credit;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Class ModelFuturesAgreement
 *
 * @property \ModelCatalogProduct $model_catalog_product
 * @property \ModelFuturesContract $model_futures_contract
 * @property \ModelToolImage $model_tool_image
 * @property ModelAccountProductQuotesMarginAgreement $model_account_product_quotes_margin_agreement
 * @property \ModelMessageMessage $model_message_message
 * @property \ModelCheckoutOrder $model_checkout_order
 * @property ModelCommonProduct $model_common_product
 */
class ModelFuturesAgreement extends Model
{

    use FutureAgreementFilter;
    const AGREEMENT_STATUS = [
        1   => ['name'=>'Applied', 'color'=>'#FA6400'],
        2   => ['name'=>'Pending', 'color'=>'#FA6400'],
        3   => ['name'=>'Approved', 'color'=>'#4B7902'],
        4   => ['name'=>'Rejected', 'color'=>'#D9001B'],
        5   => ['name'=>'Canceled', 'color'=>'#AAAAAA'],
        6   => ['name'=>'Time Out', 'color'=>'#AAAAAA'],
        7   => ['name'=>'Deposit Received', 'color'=>'#2D57A9'], //Sold 改为Deposit Received buyer 支付完成期货保证金头款
        8   => ['name'=>'Ignore', 'color'=>'#AAAAAA'],
    ];

    const DELIVERY_STATUS = [
        1   => ['name'=>'To be delivered', 'color'=>'#FA6400'],//等待交付产品，[待入仓]
        9   => ['name'=>'Terminated', 'color'=>'#AAAAAA'],//Seller拒绝Buyer的交割形式
        6   => ['name'=>'To be paid', 'color'=>'#4B7902'],//Seller同意Buyer的交割形式，[已入仓]
        8   => ['name'=>'Completed', 'color'=>'#333333'],//已完成交割的协议，[已交割]
        2   => ['name'=>'Delivery Failed', 'color'=>'#AAAAAA'],//Seller未成功交付产品
        4   => ['name'=>'Default', 'color'=>'#AAAAAA'],//Buyer未根据条款履行协议内容
        3   => ['name'=>'Being Processed', 'color'=>'#4B7902'],//Seller成功交付产品等待Buyer选择交付方式
        5   => ['name'=>'Processing', 'color'=>'#4B7902'],//选择交割方式待Seller处理的协议
        7   => ['name'=>'Being Processed', 'color'=>'#4B7902'],//Seller拒绝Buyer的交割形式
    ];

    const DELIVERY_ACTION_1 = 1; // 待入仓→Buyer提交协商终止协议申请 交货后
    const DELIVERY_ACTION_2 = 2; // 待入仓 seller无其他按钮
    const DELIVERY_ACTION_3 = 3; // 待入仓 seller交货前
    const DELIVERY_ACTION_4 = 4; // 待入仓 seller交货当天
    const DELIVERY_ACTION_40 = 40; // 待入仓 seller提交协商终止协议申请, 此操作场景为Seller向Buyer提交取消交付的申请
    const DELIVERY_ACTION_5 = 5; // 已入仓待交割 seller协议过期前
    const DELIVERY_ACTION_6 = 6; // 已入仓待交割 seller协议过期当天
    const DELIVERY_ACTION_7 = 7; // 已入仓待交割 seller协议过期后
    const DELIVERY_ACTION_8 = 8; // 已入仓待交割 seller审批
    const DELIVERY_ACTION_9 = 9; // 已入仓待交割 无其他按钮
    const IGNORE = 1;
    const IGNORE_STATUS = 8;

    const AGREEMENT_APPLIED     = 1;
    const AGREEMENT_PENDING     = 2;
    const AGREEMENT_APPROVED    = 3;
    const AGREEMENT_REJECTED    = 4;
    const AGREEMENT_CANCELED    = 5;
    const AGREEMENT_TIMEOUT     = 6;
    const AGREEMENT_SOLD        = 7;

    const DELIVERY_TYPE_FUTURES = 1;//支付期货协议尾款
    const DELIVERY_TYPE_MARGIN  = 2;//转现货保证金
    const DELIVERY_TYPE_COMB    = 3;//组合交割方式

    const DELIVERY_BEING_PROCESSED = [3,7];
    const DELIVERY_FORWARD_DELIVERY= 1;
    const DELIVERY_BACK_ORDER = 2;
    const DELIVERY_TERMINATED = 4;
    const DELIVERY_PROCESSING = 5;
    const DELIVERY_TO_BE_PAID = 6;
    const DELIVERY_COMPLETED  = 8;

    const PROCESS_STATUS_1 = 1;//头款商品创建成功
    const PROCESS_STATUS_2 = 2;//头款商品购买成功
    const PROCESS_STATUS_3 = 3;//尾款支付分销中
    const PROCESS_STATUS_4 = 4;//完成

    const SELLER_MARGIN_PAY = [
        1 => 'Authorized credit',//授信额度
        2 => 'Future goods in valid bills of lading',//有效提单
        3 => 'Receivables',
    ];

    const DELIVERY_TYPES = [
        'N/A',
        'Direct Settlement',
        'Transfer to Margin Transaction',
        'Combined payment'
    ];
    const MARGIN_APPLIED = 1;
    const MARGIN_PENDING = 2;
    const MARGIN_APPROVED = 3;
    const MARGIN_REJECT = 4;
    const MARGIN_TIME_OUT = 5;
    protected $table='oc_futures_margin_agreement';

    const TRANSACTION_TYPE_MARGIN = 2;
    const TRANSACTION_TYPE_FUTURES= 3;
    const FUTURES_FINISH_DAY = 7;
    const CREDIT_TYPE = [
        1 => "Refunded Buyer's collateral" ,
        2 => "Compensation Amount",
    ];

    /**
     * 针对购物车同一个合约多个头款支付是，需要计算当前占用数量
     * @var array
     */
    private $contractIdCacheNumMap = [];

    /*
     * 提交申请
     * */
    public function submitAgreement($postData,$map, $sellerId, $buyerId)
    {
        try {

            $this->orm->getConnection()->beginTransaction();

            $data = [
                'agreement_no'              => currentZoneDate($this->session, date('Ymd'), 'Ymd') . rand(100000, 999999),
                'product_id'                => $postData['product_id'],
                'contract_id'               => $postData['contract_id'],
                'buyer_id'                  => $buyerId,
                'seller_id'                 => $sellerId,
                'num'                       => $postData['qty'],
                'unit_price'                => $postData['price'],
                'buyer_payment_ratio'       => $postData['buyer_payment_ratio'],
                'seller_payment_ratio'      => $postData['seller_payment_ratio'],
                'expected_delivery_date'    => $postData['expected_delivery_date'],
                'min_expected_storage_days' => 1,
                'max_expected_storage_days' => 90,
                'is_bid'                  => $postData['is_bid'],
                'comments'                  => $postData['message'],
                'discount'                  => $postData['discount'] ?? null,
                'discount_price'                  => $postData['discount_price'] ?? null,
                'agreement_status'          => self::AGREEMENT_APPLIED,
                'version' => FuturesVersion::VERSION,
            ];
            // 如果不是bid的协议，协议状态置为Approved,锁定合约库存
            if (!$data['is_bid']) {
                $data['agreement_status'] = self::AGREEMENT_APPROVED;
            }
            $res = [];
            $res['agreement_id'] = $this->orm->table('oc_futures_margin_agreement')
                ->insertGetId($data);
            $map['agreement_id'] = $res['agreement_id'];
            $this->orm->table('oc_futures_margin_delivery')
                ->insertGetId($map);
            // 如果不是bid的协议，直接创建期货保证金头款
            if (!$data['is_bid'] && $res['agreement_id']) {
                $res['product_id'] = $this->copyFutureMaginProduct($res['agreement_id']);
                // 创建期货保证金记录
                $this->addFutureMarginProcess([
                    'advance_product_id' => $res['product_id'],
                    'agreement_id' => $res['agreement_id'],
                    'process_status' => 1
                ]);

            }
            $this->orm->getConnection()->commit();
            return $res;
        } catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
        }

    }

    public function getCartIdByproductId($customer_id, $product_id)
    {
        return $this->orm->table('oc_cart')
            ->where('product_id', $product_id)
            ->where('customer_id', $customer_id)
            ->value('cart_id');
    }
    /*
     * 获取店铺信息
     * */
    public function getSellerInfo($productId)
    {
        $info = $this->orm->table('oc_customerpartner_to_product as p')
            ->leftJoin('oc_customerpartner_to_customer as c', 'p.customer_id', 'c.customer_id')
            ->where('p.product_id', $productId)
            ->select('p.customer_id','c.screenname','p.price')
            ->first();

        return obj2array($info);
    }

    /*
     * 校验buyer可否购买该商品
     * */
    public function checkProduct($productId)
    {
        $product = $this->orm->table('oc_product')
            ->where([
                'status'          => 1,//上架
                'is_deleted'      => 0,//未删除
                'buyer_flag'      => 1,//允许单独售卖
                'product_id'      => $productId
            ])
            ->first();
        if (empty($product)){
            return false;
        }

        $notIn = $this->orm->table('oc_delicacy_management')
            ->where([
                'buyer_id'          => $this->customer->getId(),
                'product_display'   => 0,
                'product_id'        => $productId
            ])
            ->first();
        if (!empty($notIn)){
            return false;
        }

        $notIn1 = $this->orm->table('oc_customerpartner_product_group_link as pgl')
            ->leftjoin('oc_delicacy_management_group as dmg', 'dmg.product_group_id', 'pgl.product_group_id')
            ->leftjoin('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', 'dmg.buyer_group_id')
            ->where([
                'bgl.status'    => 1,
                'pgl.status'    => 1,
                'dmg.status'    => 1,
                'bgl.buyer_id'  => $this->customer->getId(),
                'pgl.product_id'=> $productId
            ])
            ->first();
        if (!empty($notIn1)){
            return false;
        }

        return true;
    }

    /*
     * 检查seller是否支付得起定金
     * */
    public function checkSellerDeposit($sellerId, $sellerDeposit, $productId = '', $qty = '')
    {
        //授信额度余额
        $credit = credit::getLineOfCredit($sellerId);
        if ($credit >= $sellerDeposit){
            return true;
        }

        //现货抵押物 有效提单数 需求号101222要求去掉有效提单抵押
/*        $this->load->model('futures/template');
        $expectedQty = $this->model_futures_template->getExpectedQty($productId);
        $usedQty = $this->getMarginPayRecordProductSum($productId);
        if ($expectedQty-$usedQty >= $qty){
            return true;
        }*/

        //应收款
        $billTotal = $this->getSellerBill($sellerId);
        $futureMarginUnfinishedAmount = $this->getMarginPayRecordSum($sellerId,3);
        $this->load->model('futures/contract');
        $futureContractMarginUnfinishedAmount = $this->model_futures_contract->getUnfinishedPayRecordAmount($sellerId,3);
        if ($billTotal - $futureMarginUnfinishedAmount - $futureContractMarginUnfinishedAmount >= $sellerDeposit) {
            return true;
        }

        return false;
    }


    /*
     * 协议列表
     * */
    public function agreementListForBuyer($buyerId, $filterData)
    {
        $this->load->model('catalog/product');

        $select = [
            'fa.id',
            'fa.contract_id',
            'fa.agreement_no',
            'fa.product_id',
            'fa.create_time',
            'fa.seller_id',
            'fa.num',
            'fa.unit_price',
            'fa.agreement_status',
            'fa.ignore',
            'fa.expected_delivery_date',
            'fa.version',
            'fd.delivery_type',
            'fd.delivery_status',
            'fd.delivery_date',
            'fd.confirm_delivery_date',
            'fd.margin_agreement_id',
            'fd.margin_apply_num',
            'fd.last_purchase_num',
            'fd.margin_agreement_id',
            'fc.status AS contract_status',
            'p.sku',
            'p.mpn',
            'c2c.screenname',
            'if(fa.agreement_status < '.self::AGREEMENT_SOLD.', fa.update_time, fd.update_time) as update_time',
        ];
        $query = $this->orm->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->leftJoin('oc_futures_contract AS fc', 'fc.id', '=', 'fa.contract_id')
            ->leftJoin('oc_product as p', 'p.product_id', 'fa.product_id')
            ->leftJoin('oc_customerpartner_to_customer as c2c', 'c2c.customer_id', 'fa.seller_id')
            ->where('fa.buyer_id', $buyerId)
            ->when(!empty($filterData['agreement_no']), function (Builder $query) use ($filterData){
                return $query->where('fa.agreement_no', 'like', '%'.$filterData['agreement_no'].'%');
            })
            ->when(!empty($filterData['sku']), function (Builder $query) use ($filterData){
                return $query->where('p.sku', 'like', '%'.$filterData['sku'].'%');
            })
            ->when(!empty($filterData['store_name']), function (Builder $query) use ($filterData){
                return $query->where('c2c.screenname', 'like', '%'.$filterData['store_name'].'%');
            })
            ->when(!empty($filterData['delivery_date_from']), function(Builder $query) use ($filterData){
                $delivery_date_from = $filterData['delivery_date_from'];//太平洋时区
                $country = $this->session->get('country');
                $fromZone = CountryHelper::getTimezoneByCode('USA');
                $toZone = CountryHelper::getTimezoneByCode($country);
                $current_delivery_date_from = substr(dateFormat($fromZone,$toZone,  $delivery_date_from), 0, 10);//当前国别的日期
                return $query->whereRaw('CASE WHEN fd.delivery_date IS NOT NULL THEN fd.delivery_date >= ? ELSE fa.expected_delivery_date >= ? END', [$delivery_date_from, $current_delivery_date_from]);
            })
            ->when(!empty($filterData['delivery_date_to']), function(Builder $query) use ($filterData){
                $delivery_date_to = $filterData['delivery_date_to'];//太平洋时区
                $country = $this->session->get('country');
                $fromZone = CountryHelper::getTimezoneByCode('USA');
                $toZone = CountryHelper::getTimezoneByCode($country);
                $current_delivery_date_to = substr(dateFormat($fromZone,$toZone,  $delivery_date_to), 0, 10);//当前国别的日期
                return $query->whereRaw('CASE WHEN fd.delivery_date IS NOT NULL THEN fd.delivery_date <= ? ELSE fa.expected_delivery_date <= ? END', [$delivery_date_to, $current_delivery_date_to]);
            })
            ->when(!empty($filterData['date_from']), function (Builder $query) use ($filterData){
                return $query->where(function (Builder $q) use ($filterData){
                    $q->where(function(Builder $q) use($filterData){
                        $q->where('fa.update_time', '>=', $filterData['date_from'])
                            ->where('fa.agreement_status', '<', 7);
                    })->orWhere(function (Builder $q) use ($filterData) {
                        $q->where('fd.update_time', '>=', $filterData['date_from'])
                            ->where('fa.agreement_status', 7);
                    });
                });
            })
            ->when(!empty($filterData['date_to']), function (Builder $query) use ($filterData){
                return $query->where(function(Builder $q) use ($filterData){
                    $q->where(function(Builder $q) use($filterData){
                        $q->where('fa.update_time', '<=', $filterData['date_to'])
                            ->where('fa.agreement_status', '<', 7);
                    })->orWhere(function (Builder $q) use ($filterData) {
                        $q->where('fd.update_time', '<=', $filterData['date_to'])
                            ->where('fa.agreement_status', 7);
                    });
                });
            });

        if (!empty($filterData['status'])){//快捷筛选，组合状态
            switch ($filterData['status']){
                case 1:{//待处理
                    $query->where(function (Builder $query) {
                        return $query->where(function (Builder $q){
                            return $q->whereIn('fa.agreement_status', [1,2,4,6])
                                ->where(function(Builder $qq){
                                    $qq->where('fd.delivery_status', null)
                                        ->orWhere('fd.delivery_status', '<', 1);
                                });
                        })
                            ->orWhere('fd.delivery_status', 2);
                    })
                        ->where('fa.ignore', 0);
                    break;
                }
                case 2:{//待交付
                    $query->where('fd.delivery_status', 1)
                        ->where('fa.ignore', 0);
                    break;
                }
                case 3:{//待交割
                    $query->whereIn('fd.delivery_status', [3,5,7])
                        ->where('fa.ignore', 0);
                    break;
                }
                case 4:{//待支付
                    $query->where(function (Builder $query){
                        return $query->where([
                            'fa.agreement_status' => self::AGREEMENT_APPROVED,
                            'fd.delivery_status'  => null
                        ])
                            ->orWhere('fd.delivery_status', 6);
                    })
                        ->where('fa.ignore', 0);
                    break;
                }
                case 7:
                {// 待交割
                    $query->where(function (Builder $query){
                        return $query->where([
                            'fa.agreement_status' => self::AGREEMENT_APPROVED,
                            'fd.delivery_status'  => null
                        ])
                            ->orWhere('fd.delivery_status', 6);
                    })
                        ->where('fa.ignore', 0);
                    break;
                }
                case 8:
                {//DUE SOON
                    $fromTz = TENSE_TIME_ZONES_NO[getPSTOrPDTFromDate(date('Y-m-d H:i:s'))];
                    $toTz = ($this->customer->isUSA() || $this->session->get('country', 'USA') == 'USA') ? $fromTz : COUNTRY_TIME_ZONES_NO[$this->session->get('country')];

                    $last_tips_date = date('Y-m-d H:i:s',time() - 23*3600);
                    $last_end_date = date('Y-m-d H:i:s',time() - 24*3600);
                    $expected_delivery_date_start = date('Y-m-d', strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session)));
                    $expected_delivery_date_end = date('Y-m-d', strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session)) + 7*86400);
                    $future_margin_start = changeOutPutByZone(date('Y-m-d H:i:s',time() - 7*86400), $this->session);
                    $future_margin_end   = changeOutPutByZone(date('Y-m-d H:i:s',time() - 7*86400 + 3600), $this->session);

                    $condition = [
                        'last_tips_date' => $last_tips_date,
                        'last_end_date' => $last_end_date,
                        'expected_delivery_date_start' => $expected_delivery_date_start,
                        'expected_delivery_date_end' => $expected_delivery_date_end,
                        'future_margin_start' => $future_margin_start,
                        'future_margin_end' => $future_margin_end,
                        'from_tz' => $fromTz,
                        'to_tz' => $toTz,
                    ];

                    $query->where(function (Builder $query) use ($condition) {
                        $query->where(function (Builder $query) use ($condition) {
                            $query->where('fa.update_time', '>=', $condition['last_end_date'])
                                ->where('fa.update_time', '<=', $condition['last_tips_date'])
                                ->whereIn('fa.agreement_status', [1,2,3]);
                        })->orWhere(function (Builder $query) use ($condition) {
                            $query->where('fa.expected_delivery_date', '>=', $condition['expected_delivery_date_start'])
                                ->where('fa.expected_delivery_date', '<', $condition['expected_delivery_date_end'])
                                ->where('fd.delivery_status', 1);
                        })->orWhere(function (Builder $query) use ($condition) {
                            $query->where('fd.confirm_delivery_date', '>=', $condition['last_end_date'])
                                ->where('fd.confirm_delivery_date', '<=', $condition['last_tips_date'])
                                ->where([
                                    'fd.delivery_status'=> 6,
                                    'fd.delivery_type'=> 2,
                                ]);
                        })->orWhere(function (Builder $query) use ($condition) {
                            $query->where([
                                'fd.delivery_status'=> 6,
                                'fd.delivery_type'=> 1,
                            ])->whereRaw("CONCAT(DATE(CONVERT_TZ(fd.confirm_delivery_date, ?, ?)), ' 23:59:59') between ? and ?", [
                                $condition['from_tz'],
                                $condition['to_tz'],
                                $condition['future_margin_start'],
                                $condition['future_margin_end']
                            ]);
                        });
                    });
                    break;
                }
                case 9:
                {//待审批的协议
                }
            }

        }else{
            $query->when(!empty($filterData['agreement_status']), function (Builder $query) use ($filterData){
                    if (self::IGNORE_STATUS == $filterData['agreement_status']){
                        return $query->where('fa.ignore', self::IGNORE);
                    }else{
                        return $query->where('fa.agreement_status', $filterData['agreement_status'])
                            ->where('fa.ignore', '!=',self::IGNORE);
                    }
                })
                ->when(!empty($filterData['delivery_status']), function (Builder $query) use ($filterData){
                    if (in_array($filterData['delivery_status'], self::DELIVERY_BEING_PROCESSED)){
                        return $query->whereIn('fd.delivery_status', self::DELIVERY_BEING_PROCESSED)
                            ->where('fa.ignore', '!=',self::IGNORE);
                    }else{
                        return $query->where('fd.delivery_status', $filterData['delivery_status'])
                            ->where('fa.ignore', '!=',self::IGNORE);
                    }
                });
        }

        //排除Quick View直接下单，但是未支付头款订单，产生的协议
        $query->where(function (Builder $query) {
            $query->where([
                ['fa.contract_id', '<>', 0],
                ['fa.is_bid', '=', 0],
                ['fa.agreement_status', '=', 7],
            ])->orWhere([
                ['fa.contract_id', '<>', 0],
                ['fa.is_bid', '=', 1],
            ])->orWhere([
                ['fa.contract_id', '=', 0],
            ]);
        });

        $count = $query->count();

        $query->select(DB::raw(implode(',',$select)));

        if (isset($filterData['start']) && isset($filterData['limit'])){
            $query->offset($filterData['start'])
                ->limit($filterData['limit']);
        }
        $query->groupBy('fa.id');
        if (!empty($filterData['sort']) && in_array($filterData['sort'], ['update_time', 'delivery_date'])){
            $order = !empty($filterData['order']) && $filterData['order']=='DESC'?'DESC':'ASC';
            $query->orderBy($filterData['sort'], $order);
        }else{
            $query->orderBy('fa.id', 'DESC');
        }

        $list = $query->get();

        $isJapan = JAPAN_COUNTRY_ID == $this->customer->getCountryId()? 1:0;
        $tag     = [];
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        unset($value);
        foreach ($list as &$value){
            if ($isJapan){
                $format = '%d';
                $precision = 0;
            }else{
                $format = '%.2f';
                $precision = 2;
            }
            $value->unit_price = sprintf($format, round($value->unit_price, $precision));
            $value->amount = sprintf($format, round($value->unit_price * $value->num, $precision));


            //协议状态
            if ($value->ignore) {
                $value->agreement_status       = self::IGNORE_STATUS;
                $value->agreement_status_name  = self::AGREEMENT_STATUS[$value->agreement_status]['name'];
                $value->agreement_status_color = self::AGREEMENT_STATUS[$value->agreement_status]['color'];
                $value->delivery_status_name   = isset(self::DELIVERY_STATUS[$value->delivery_status])?self::DELIVERY_STATUS[$value->delivery_status]['name']:'N/A';
                $value->delivery_status_color  = isset(self::DELIVERY_STATUS[$value->delivery_status])?self::DELIVERY_STATUS[$value->delivery_status]['color']:'GREY';
            } elseif ($value->delivery_status) {
                $value->agreement_status_name  = self::AGREEMENT_STATUS[$value->agreement_status]['name'];
                $value->agreement_status_color = self::AGREEMENT_STATUS[$value->agreement_status]['color'];
                $value->delivery_status_name   = self::DELIVERY_STATUS[$value->delivery_status]['name'];
                $value->delivery_status_color  = self::DELIVERY_STATUS[$value->delivery_status]['color'];
            } else {
                $value->agreement_status_name  = self::AGREEMENT_STATUS[$value->agreement_status]['name'];
                $value->agreement_status_color = self::AGREEMENT_STATUS[$value->agreement_status]['color'];
                $value->delivery_status_name   = 'N/A';
                $value->delivery_status_color  = 'GREY';
            }

            if(!$value->contract_id){
                $margin_status = $this->getMarginStatus($value->margin_agreement_id);
                if(in_array($margin_status,[self::MARGIN_APPLIED,self::MARGIN_PENDING,self::MARGIN_REJECT,self::MARGIN_TIME_OUT])){
                    $value->margin_amount = false;
                }else{
                    $value->margin_amount = true;
                }
            }
            if ($value->version == FuturesVersion::VERSION || $value->delivery_type == self::DELIVERY_TYPE_MARGIN) {
                $value->expire_time = Carbon::parse($value->confirm_delivery_date)->addDays(1)->toDateTimeString();
            } else {
                $tmp_expire_time = date('Y-m-d 23:59:59', strtotime($value->confirm_delivery_date . '+7 day'));
                $value->expire_time = dateFormat($fromZone, $toZone, $tmp_expire_time, 'Y-m-d H:i:s');
            }
            $value->left_time_secs = ($value->expire_time) ? strtotime($value->expire_time) - time() : -1;

            $value->days = $this->getLeftDay($value);
            if ($value->delivery_date) {
                $value->show_delivery_date = dateFormat($fromZone, $toZone, $value->delivery_date,'Y-m-d');
            } elseif ($value->expected_delivery_date) {
                $value->show_delivery_date = $value->expected_delivery_date;
                if ($value->days <= self::FUTURES_FINISH_DAY && $value->days > 0) {
                    $value->days_tip = sprintf($this->language->get('tip_in_delivery_days'), $value->days);
                } elseif ($value->days == 0) {
                    $value->days_tip = $this->language->get('tip_out_delivery_days_seller');
                }
            } else {
                $value->show_delivery_date = 'N/A';
            }


            $value->delivery_type_name = self::DELIVERY_TYPES[(int)$value->delivery_type];

            if (
                self::AGREEMENT_APPROVED == $value->agreement_status
                && (
                    $value->delivery_status < 1
                    || is_null($value->delivery_status
                    )
                )
            ) {
                $value->add_to_cart = 1;//1期货头款
                $value->advance_product_id = $this->getFuturesAdvanceProductId($value->id);
            } elseif (
                self::AGREEMENT_SOLD == $value->agreement_status
                && self::DELIVERY_TO_BE_PAID == $value->delivery_status
            ) {
                $value->add_to_cart = 2;//2期货尾款(可能包含现货头款补足款+期货尾款)
                if ($value->margin_agreement_id){
                    //转现货头款商品尚未购买成功 则可加入购物车
                    $marginProcess = $this->marginAdvanceProcess($value->margin_agreement_id);
                    if ($marginProcess['process_status'] == self::PROCESS_STATUS_1){
                        $value->advance_product_id = $marginProcess['advance_product_id'];
                    }
                }
                if ($value->last_purchase_num){
                    //$purchaseSum = $this->getPurchaseSum($value->id);
                    //$value->last_purchase_num = $value->last_purchase_num - $purchaseSum;
                    $value->last_purchase_num = $this->lockQty($value->id);//期货协议剩余锁库存
                }
            }else{
                $value->add_to_cart = 0;
            }
            $value->advance_product_id = isset($value->advance_product_id) ? $value->advance_product_id : 0;

            $value->unit_price_str = $this->currency->format($value->unit_price, $this->session->data['currency']);
            $value->amount_str = $this->currency->format($value->amount, $this->session->data['currency']);

            $new_timezone       = changeOutPutByZone($value->update_time, $this->session);
            $value->update_day  = substr($new_timezone, 0, 10);
            $value->update_hour = substr($new_timezone, 11);



            if(isset($tag[$value->product_id])){
                $value->tag = $tag[$value->product_id];
            }else{
                $value->tag = $this->model_catalog_product->getProductTagHtmlForThumb($value->product_id);
                $tag[$value->product_id] = $value->tag;
            }


            // 获取contract_id来确认是否是新的期货
            // timeout 时间 agreement_status [1,2,3] 时间距离当前时间相差一个小时
            $current_timestamp = time();
            if(in_array($value->agreement_status,[1,2,3])){
                $update_timestamp = strtotime($value->update_time);
                if(($current_timestamp - $update_timestamp - 23*3600) > 0 && ($current_timestamp - $update_timestamp - 23*3600) <= 3600){
                    $left_timestamp = 3600 - ($current_timestamp - $update_timestamp - 23*3600);
                    $value->time_left = str_pad(floor($left_timestamp/60), 2, 0, STR_PAD_LEFT) . ':' . str_pad(($left_timestamp%60), 2, 0, STR_PAD_LEFT);
                    $value->time_tips = true;
                }elseif(($current_timestamp - $update_timestamp - 24*3600) > 0){
                    $value->time_left = '00:00';
                    $value->time_tips = true;
                }
            } elseif($value->delivery_status == 6 && $value->agreement_status == 7){
                if($value->delivery_type == self::DELIVERY_TYPE_FUTURES){
                    $time_left = $this->getConfirmLeftDay($value,1);
                    if($time_left != false){
                        $value->time_left = $time_left;
                        $value->time_tips = true;
                    }else{
                        $value->time_tips = false;
                    }
                }elseif($value->delivery_type == self::DELIVERY_TYPE_MARGIN){
                    $update_timestamp = strtotime($value->confirm_delivery_date);
                    if(($current_timestamp - $update_timestamp - 23*3600) > 0 && ($current_timestamp - $update_timestamp - 23*3600) <= 3600){
                        $left_timestamp = 3600 - ($current_timestamp - $update_timestamp - 23*3600);
                        $value->time_left = str_pad(floor($left_timestamp/60), 2, 0, STR_PAD_LEFT) . ':' . str_pad(($left_timestamp%60), 2, 0, STR_PAD_LEFT);
                        $value->time_tips = true;
                    }elseif(($current_timestamp - $update_timestamp - 24*3600) > 0){
                        $value->time_left = '00:00';
                        $value->time_tips = true;
                    }
                }else{
                    $value->time_tips = false;
                }

            } elseif ($value->delivery_status == 1 && $value->agreement_status == 7) {
                $minute = $this->getLeftDay($value,null,1);
                if($minute) {
                    $value->time_left = $minute;
                    $value->time_tips = true;
                }else{
                    $value->time_tips = false;
                }

            }else{
                $value->time_tips = false;
            }


            // 获取协议数量和 已经采购的数量去掉协议期内rma的数量，其中期货转保证金成功的数量直接算在内
            $value->purchase_num = $this->getAgreementCurrentPurchaseQuantity($value->id) ?? 0;
            $value->purchase_num_show = $value->purchase_num;
            //期货中转现货的，只要没有completed的都置为0数量
            if ($value->contract_id == 0) {//期货一期
                if ($value->margin_amount == false) {
                    if ($value->delivery_type == 2) {
                        $value->purchase_num_show = 0;
                    } elseif ($value->delivery_type == 3) {
                        $value->purchase_num_show = max($value->purchase_num - $value->margin_apply_num, 0);
                    }
                }
            } else {//期货二期
                if ($value->delivery_type == 2 && $value->delivery_status != 8) {
                    $value->purchase_num_show = 0;
                }
            }
        }
        unset($value);

        return ['total' => $count, 'agreement_list'   => obj2array($list)];
    }

    /**
     * 获取期货头款商品ID
     * @param int $agreementId
     * @return int|mixed
     */
    public function getFuturesAdvanceProductId($agreementId)
    {
        return $this->orm->table('oc_futures_margin_process')
            ->where('agreement_id', $agreementId)
            ->value('advance_product_id');
    }

    //获取现货头款商品ID
    public function getMarginAdvanceProductId($agreementId)
    {
        return $this->orm->table('tb_sys_margin_process')
            ->where('margin_id', $agreementId)
            ->value('advance_product_id');
    }

    /*
     * 协议列表
     * */
    public function agreementListForSeller($customer_id, $filter)
    {
        $select = [
            'fa.id',
            'fa.agreement_no',
            'fa.product_id',
            'fa.num',
            'fa.unit_price',
            'fa.agreement_status',
            'fa.create_time',
            'fa.expected_delivery_date',
            'fa.seller_payment_ratio',
            'fd.delivery_type',
            'fd.delivery_status',
            'fd.delivery_date',
            'fd.margin_agreement_id',
            'fd.margin_apply_num',
            'fa.contract_id',
            'DATE_ADD( DATE_FORMAT( fd.confirm_delivery_date, "%Y-%m-%d %H:%i:%s" ), INTERVAL 1 DAY ) AS expire_time',
            'fa.version',
            'p.sku',
            'p.mpn',
            'c.nickname',
            'c.user_number',
            'c.customer_group_id',
            'if(fa.agreement_status < '.self::AGREEMENT_SOLD.', fa.update_time, fd.update_time) as update_time',
            'aa.id as apply_id',
            'aa.is_read',
            'fa.seller_id',
            'fa.buyer_id',
            'fd.confirm_delivery_date',
            'mm.message as remark',
            'fd.cancel_appeal_apply',
            'fc.contract_no'
        ];
        $query = $this->orm->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->leftJoin('oc_product as p', 'p.product_id', 'fa.product_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', 'fa.buyer_id')
            ->leftJoin('oc_futures_contract AS fc', 'fc.id', '=', 'fa.contract_id')
            ->leftJoin('oc_futures_agreement_apply as aa',function ($join) use ($customer_id) {
                $join->on('aa.agreement_id', '=', 'fa.id')
                    ->where([
                        ['aa.status', '=', 0],
                        ['aa.apply_type', '=', 3],
                        ['aa.customer_id', '!=', $customer_id],
                    ]);
            })
            ->leftJoin('oc_futures_margin_message as mm',function ($join)  {
                $join->on('mm.apply_id', '=', 'aa.id')
                    ->where([
                        ['mm.apply_id', '!=', 0]

                    ]);
            })
            ->where('fa.seller_id', $customer_id)
            ->where(function(Builder $query){
                $query->where([
                    ['fa.contract_id','<>', 0],
                    ['fa.is_bid','=', 0],
                    ['fa.agreement_status','=',7],
                    ])->orWhere([
                        ['fa.contract_id','<>', 0],
                        ['fa.is_bid','=', 1],
                    ])
                    ->orWhere([
                        ['fa.contract_id','=', 0],
                    ]);
            })
            ->groupBy('fa.id');
        // 加了一个判断 is bid 0 的付完尾款才能看到 7
        $query = $this->filter($query, $filter);
        if(!isset($filter['page_limit'])){
            $filter['page_limit'] = null;
        }
        $query->select(DB::raw(implode(',', $select)));
        // 点击的优先排序，其他的降序
        if(isset($filter['column'])){
            if($filter['column'] == 'update_time'){
                $query->orderBy('update_time', $filter['sort_update_time'])->orderBy('fa.id', $filter['sort_agreement_id']);
            }else{
                $query->orderBy('fa.id', $filter['sort_agreement_id'])->orderBy('update_time', $filter['sort_update_time']);
            }
        }else{
            $query->orderBy('fa.id', $filter['sort_agreement_id']);
        }
        $total = count($query->get());
        //  判断是不是导出excel
        $data = $filter['page_limit'] ? $query->forPage($filter['page'], $filter['page_limit'])->get() : $query->get();
        return [
            'total' => $total,
            'agreement_list' => $this->handleAgreementList($data)
        ];
    }


    function handleAgreementList($list)
    {
        $this->load->model('catalog/product');
        $this->load->model('common/product');
        $isJapan = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
        $tag = [];
        $country = session('country', 'USA');
        $usaZone = $fromZone = CountryHelper::getTimezoneByCode('USA');
        $countryZone = $toZone = CountryHelper::getTimezoneByCode($country);
        unset($value);

        $buyerIds = collect($list)->pluck('buyer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($list as &$value) {
            $value->ex_vat = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($value->buyer_id), 'is_show_vat' => true])->render();
            $value->nickname_escape = addslashes(str_replace('"', '&quot;', $value->nickname));
            if ($isJapan) {
                $value->amount = sprintf('%d', round($value->unit_price * $value->num, 2));
                $value->unit_price = sprintf('%d', round($value->unit_price, 2));

            } else {
                $value->amount = sprintf('%.2f', round($value->unit_price * $value->num, 2));
            }
            if ($value->delivery_status) {
                $value->agreement_status_name  = self::AGREEMENT_STATUS[$value->agreement_status]['name'];
                $value->agreement_status_color = self::AGREEMENT_STATUS[$value->agreement_status]['color'];
                $value->delivery_status_name  = self::DELIVERY_STATUS[$value->delivery_status]['name'];
                $value->delivery_status_color = self::DELIVERY_STATUS[$value->delivery_status]['color'];
            } else {
                $value->agreement_status_name  = self::AGREEMENT_STATUS[$value->agreement_status]['name'];
                $value->agreement_status_color = self::AGREEMENT_STATUS[$value->agreement_status]['color'];
                $value->delivery_status_name  = 'N/A';
                $value->delivery_status_color = 'GREY';
            }
            if(!$value->contract_id){
                $margin_status = $this->getMarginStatus($value->margin_agreement_id);
                if(in_array($margin_status,[self::MARGIN_APPLIED,self::MARGIN_PENDING,self::MARGIN_REJECT,self::MARGIN_TIME_OUT])){
                    $value->margin_amount = false;
                }else{
                    $value->margin_amount = true;
                }
            }

            $need_alarm = 0;
            if (
                $this->customer->isNonInnerAccount()
                && !in_array($value->customer_group_id, COLLECTION_FROM_DOMICILE)
            ) {
                $alarm_price = $this->model_common_product->getAlarmPrice($value->product_id);
                if (bccomp($value->unit_price, $alarm_price, 4) === -1) {
                    $need_alarm = 1;
                }
            }
            $value->need_alarm = $need_alarm;
            if(in_array($value->agreement_status,[self::AGREEMENT_APPLIED, self::AGREEMENT_PENDING,self::AGREEMENT_APPROVED])){
                $value->amount_status = $this->verifyAmountIsEnough($value,$isJapan);
            }else{
                $value->amount_status = true;
            }
            if ($value->version < FuturesVersion::VERSION) {//针对期货协议旧版本(第三期之前)seller审核交割日期的过期时间为Buyer7天后最后一秒
                $tmp_expire_time = date('Y-m-d 23:59:59', strtotime($value->confirm_delivery_date . '+7 day'));
                $value->expire_time = dateFormat($fromZone, $toZone, $tmp_expire_time, 'Y-m-d H:i:s');
            }
            $value->left_time_secs = ($value->expire_time) ? strtotime($value->expire_time) - time() : -1;

            $value->days = $this->getLeftDay($value);
            if ($value->delivery_date) {
                $value->show_delivery_date = dateFormat($fromZone, $toZone, $value->delivery_date,'Y-m-d');
            } elseif ($value->expected_delivery_date) {
                $value->show_delivery_date = $value->expected_delivery_date;
                if ($value->days <= self::FUTURES_FINISH_DAY && $value->days > 0) {
                    $value->days_tip = sprintf($this->language->get('tip_in_delivery_days'), $value->days);
                } elseif ($value->days == 0) {
                    $value->days_tip = $this->language->get('tip_out_delivery_days_seller');
                }
            } else {
                $value->show_delivery_date = 'N/A';
            }
            $value->delivery_type_name = self:: DELIVERY_TYPES[intval($value->delivery_type)];
            // 获取分组
            if (in_array($value->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $value->is_homepick = true;
                $value->img_tips = 'Pick up Buyer';
            }else{
                $value->is_homepick = false;
                $value->img_tips = 'Dropshiping Buyer';
            }
            // 获取agreement status
            if(isset($tag[$value->product_id])){
                $value->tag = $tag[$value->product_id];
            }else{
                $value->tag = $this->model_catalog_product->getProductTagHtmlForThumb($value->product_id);
                $tag[$value->product_id] = $value->tag;
            }
            // 获取contract_id来确认是否是新的期货
            // timeout 时间 agreement_status [1,2,3] 时间距离当前时间相差一个小时
            $current_timestamp = time();
            if(in_array($value->agreement_status,[1,2,3])){
                $update_timestamp = strtotime($value->update_time);
                if(($current_timestamp - $update_timestamp - 23*3600) > 0 && ($current_timestamp - $update_timestamp - 23*3600) <= 3600){
                    $left_timestamp = 3600 - ($current_timestamp - $update_timestamp - 23*3600);
                    $value->time_left = str_pad(floor($left_timestamp/60), 2, 0, STR_PAD_LEFT) . ':' . str_pad(($left_timestamp%60), 2, 0, STR_PAD_LEFT);
                    $value->time_tips = true;
                }elseif(($current_timestamp - $update_timestamp - 24*3600) > 0){
                    $value->time_left = '00:00';
                    $value->time_tips = true;
                }
            } elseif($value->delivery_status == 6 && $value->agreement_status == 7){
                if($value->delivery_type == self::DELIVERY_TYPE_FUTURES){
                    $time_left = $this->getConfirmLeftDay($value,1);
                    if($time_left != false){
                        $value->time_left = $time_left;
                        $value->time_tips = true;
                    }else{
                        $value->time_tips = false;
                    }
                }elseif($value->delivery_type == self::DELIVERY_TYPE_MARGIN){
                    $update_timestamp = strtotime($value->confirm_delivery_date);
                    if(($current_timestamp - $update_timestamp - 23*3600) > 0 && ($current_timestamp - $update_timestamp - 23*3600) <= 3600){
                        $left_timestamp = 3600 - ($current_timestamp - $update_timestamp - 23*3600);
                        $value->time_left = str_pad(floor($left_timestamp/60), 2, 0, STR_PAD_LEFT) . ':' . str_pad(($left_timestamp%60), 2, 0, STR_PAD_LEFT);
                        $value->time_tips = true;
                    }elseif(($current_timestamp - $update_timestamp - 24*3600) > 0){
                        $value->time_left = '00:00';
                        $value->time_tips = true;
                    }
                }else{
                    $value->time_tips = false;
                }

            } elseif ($value->delivery_status == 1 && $value->agreement_status == 7) {

                $minute = $this->getLeftDay($value,null,1);
                if($minute) {
                    $value->time_left = $minute;
                    $value->time_tips = true;
                }else{
                    $value->time_tips = false;
                }

            }else{
                $value->time_tips = false;
            }
            // 获取协议数量和 已经采购的数量去掉协议期内rma的数量，其中期货转保证金成功的数量直接算在内
            $value->purchase_num = $this->getAgreementCurrentPurchaseQuantity($value->id) ?? 0;
            $value->purchase_num_show = $value->purchase_num;
            //期货中转现货的，只要没有completed的都置为0数量
            if ($value->contract_id == 0) {//期货一期
                if ($value->margin_amount == false) {
                    if ($value->delivery_type == 2) {
                        $value->purchase_num_show = 0;
                    } elseif ($value->delivery_type == 3) {
                        $value->purchase_num_show = max($value->purchase_num - $value->margin_apply_num, 0);
                    }
                }
            } else {//期货二期
                if($value->delivery_type == 2 && $value->delivery_status != 8){
                    $value->purchase_num_show = 0;
                }
            }
            // 待入仓的聚合按钮中分为很多种情况需要区别拆分
            if($value->contract_id){
                if($value->agreement_status == 7 &&  $value->delivery_status == 1){
                    // 1 待入仓→Buyer提交协商终止协议申请
                    if($value->apply_id){
                        $value->delivery_action = self::DELIVERY_ACTION_1;
                        // 需要审批
                    }else{
                    // 查询是否有seller提交的未审核的apply
                        $exists = $this->getCustomerSubmitApply([$value->seller_id],$value->id,[0]);
                        if($exists){
                            $value->delivery_action = self::DELIVERY_ACTION_2;
                            // 无其他按钮
                        }else{
                            // 根据时间分为三种 交货前 交货当天 交货日期后
                            if($value->days > 1){
                                $value->delivery_action = self::DELIVERY_ACTION_3;
                            }elseif($value->days == 1){
                                $value->delivery_action = self::DELIVERY_ACTION_4;
                            }else{
                                $value->delivery_action = self::DELIVERY_ACTION_2; // 交货日期后
                            }
                        }
                    }

                }elseif($value->agreement_status == 7 &&  $value->delivery_status == 6){
                    // buyer提交的需要审批
                    if($value->apply_id){
                        $value->delivery_action = self::DELIVERY_ACTION_8;
                        // 需要审批
                    }else{
                        $exists = $this->getCustomerSubmitApply([$value->seller_id],$value->id,[0]);
                        if($exists){
                            $value->delivery_action = self::DELIVERY_ACTION_9;
                            // 无其他按钮
                        }else{
                            if($value->delivery_type == 1){
                                // 尾款交割：Seller确认交付后~+7个自然日至当前国别时间的23:59:59
                                $days = $this->getConfirmLeftDay($value);
                                if($days >= 1){
                                    $value->delivery_action = self::DELIVERY_ACTION_5;//协议过期前
                                }elseif($days == 0){
                                    $value->delivery_action = self::DELIVERY_ACTION_6;//协议过期当天
                                }else{
                                    $value->delivery_action = self::DELIVERY_ACTION_7; //协议过期后
                                }

                            }elseif($value->delivery_type == 2){
                                // 转现货保证金交割：Seller确认交付后~+现货保证金申请有效期
                                $days = floor((strtotime($value->confirm_delivery_date.' '.$countryZone)- $current_timestamp)/86400);
                                if($days >= 1){
                                    $value->delivery_action = self::DELIVERY_ACTION_5;//协议过期前
                                }elseif($days == 0){
                                    $value->delivery_action = self::DELIVERY_ACTION_6;//协议过期当天
                                }else{
                                    $value->delivery_action = self::DELIVERY_ACTION_7; //协议过期后
                                }
                            }
                        }
                    }
                } elseif($value->agreement_status == 7 &&  $value->delivery_status == 2){
                    // back order
                    $value->seller_apply = $this->getCustomerSubmitApply([$value->seller_id],$value->id,[0]);
                    // 拒绝交付后、交付超时可提交申诉，可提交申诉的有效期为7个自然日，7个自然日后隐藏列表页的申诉按钮和详情页的申诉入口（列表页也同样的限制）
                    if(($current_timestamp - strtotime($value->update_time.' '.$countryZone) - 7*86400) > 0){
                        $value->complain_time_out = true;
                    }else{
                        $value->complain_time_out = false;
                    }


                }
            }
        }
        unset($value);
        return obj2array($list);
    }

    public function getMarginStatus($margin_id)
    {
        return $this->orm->table('tb_sys_margin_agreement')->where('id',$margin_id)->value('status');
    }

    public function verifyAmountIsEnough($data,$is_japan)
    {
        $format   = $is_japan ? 0 : 2;
        // 获取当前协议的contract_id 的 available_balance
        $available_balance = $this->orm->table('oc_futures_contract')->where('id',$data->contract_id)->value('available_balance');
        // 获取当前已经approval但是没有付头款的钱
        $approval = $this->orm->table('oc_futures_margin_agreement')
            ->where([
            'contract_id'=>$data->contract_id,
            'agreement_status'=>self::AGREEMENT_APPROVED,
            'is_bid' => 1,
        ])
        ->selectRaw('sum(round(unit_price*seller_payment_ratio/100,'.$format.')*num) as sum')
        ->first();
       $take_up_amount = $approval->sum == '' ? 0 : $approval->sum;
        // 获取当次需要付的钱
        if ($is_japan) {
            $all_seller_earnest = round($data->unit_price * $data->seller_payment_ratio / 100) * $data->num;
        } else {
            $all_seller_earnest = sprintf('%.2f', round($data->unit_price * $data->seller_payment_ratio / 100, 2) * $data->num);
        }
        if(($available_balance - $take_up_amount - $all_seller_earnest) >= 0){
            return true;
        }

        return false;

    }


    /**
     * @param array $customer_list
     * @param int $agreement_id
     * @param array $status_list
     * @param array $apply_type
     * @return bool
     */
    public function getCustomerSubmitApplyType($customer_list = [], $agreement_id = 0, $status_list = [], $apply_type = [])
    {
        $orm = $this->orm->table('oc_futures_agreement_apply')
            ->where([
                'agreement_id' => $agreement_id,
            ])
            ->whereIn('customer_id', $customer_list)
            ->whereIn('status', $status_list);
        if ($apply_type) {
            $orm->whereIn('apply_type', $apply_type);
        }
        return $orm->exists();
    }


    public function getCustomerSubmitApply($customer_list,$agreement_id,$status_list)
    {
        return $this->orm->table('oc_futures_agreement_apply')
            ->where([
                'agreement_id' => $agreement_id,
            ])
            ->whereIn('customer_id',$customer_list)
            ->whereIn('status',$status_list)
            ->where('apply_type', '!=', FuturesMarginApplyType::APPEAL)
            ->exists();
    }

    public function getLastCustomerApplyInfo($customer_id,$agreement_id,$status_list)
    {
        return $this->orm->table('oc_futures_agreement_apply')
            ->where([
                'customer_id'  => $customer_id,
                'agreement_id' => $agreement_id,
            ])
            ->whereIn('status',$status_list)
            ->first();
    }


    /**
     * 统计buyer待处理
     * @param int $buyerId
     * @return int
     */
    public function toBeProcessedCount($buyerId)
    {
        $query = $this->orm->connection('read')->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->where('fa.buyer_id', $buyerId)
            ->where('fa.ignore', 0)
            ->where(function ($query) {
                return $query->where(function ($q){
                    return $q->whereIn('fa.agreement_status', [1,2,4,6])
                        ->where(function($qq){
                            $qq->where('fd.delivery_status', null)
                                ->orWhere('fd.delivery_status', '<', 1);
                        });
                })
                    ->orWhere('fd.delivery_status', 2);
            });

        //排除Quick View直接下单，但是未支付头款订单，产生的协议
        $query->where(function (Builder $query) {
            $query->where([
                ['fa.contract_id', '<>', 0],
                ['fa.is_bid', '=', 0],
                ['fa.agreement_status', '=', 7],
            ])->orWhere([
                ['fa.contract_id', '<>', 0],
                ['fa.is_bid', '=', 1],
            ])->orWhere([
                ['fa.contract_id', '=', 0],
            ]);
        });

        return $query->count();
    }

    /**
     * 统计buyer待交付
     * @param int $buyerId
     * @return int
     */
    public function toBeDeliveredCount($buyerId)
    {
        $query = $this->orm->connection('read')->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->where('fa.buyer_id', $buyerId)
            ->where('fd.delivery_status', 1)
            ->where('fa.ignore', 0);

        //排除Quick View直接下单，但是未支付头款订单，产生的协议
        $query->where(function (Builder $query) {
            $query->where([
                ['fa.contract_id', '<>', 0],
                ['fa.is_bid', '=', 0],
                ['fa.agreement_status', '=', 7],
            ])->orWhere([
                ['fa.contract_id', '<>', 0],
                ['fa.is_bid', '=', 1],
            ])->orWhere([
                ['fa.contract_id', '=', 0],
            ]);
        });
        return $query->count();
    }

    /*
     * 统计buyer待交割
     * */
    public function forTheDeliveryCount($buyerId)
    {
        return $this->orm->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->where('fa.buyer_id', $buyerId)
            ->whereIn('fd.delivery_status', [3,5,7])
            ->where('fa.ignore', 0)
            ->count();
    }

    /**
     * 统计buyer待支付
     * @param int $buyerId
     * @return int
     */
    public function toBePaidCount($buyerId)
    {
        $query = $this->orm->connection('read')->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->where('fa.buyer_id', $buyerId)
            ->where('fa.ignore', 0)
            ->where(function ($query){
                return $query->where([
                    'fa.agreement_status' => self::AGREEMENT_APPROVED,
                    'fd.delivery_status'  => null
                ])
                    ->orWhere('fd.delivery_status', 6);
            });

        //排除Quick View直接下单，但是未支付头款订单，产生的协议
        $query->where(function (Builder $query) {
            $query->where([
                ['fa.contract_id', '<>', 0],
                ['fa.is_bid', '=', 0],
                ['fa.agreement_status', '=', 7],
            ])->orWhere([
                ['fa.contract_id', '<>', 0],
                ['fa.is_bid', '=', 1],
            ])->orWhere([
                ['fa.contract_id', '=', 0],
            ]);
        });
        return $query->count();
    }


    /**
     * 统计 Due Soon，即将到交货日期和即将超时的协议
     * 参考 sellerAgreementExpiredCount()
     * @param int $buyerId
     * @return int
     */
    public function dueSoonCount($buyerId)
    {
        $fromTz = TENSE_TIME_ZONES_NO[getPSTOrPDTFromDate(date('Y-m-d H:i:s'))];
        $toTz = ($this->customer->isUSA() || $this->session->get('country', 'USA') == 'USA') ? $fromTz : COUNTRY_TIME_ZONES_NO[$this->session->get('country')];

        $last_tips_date = date('Y-m-d H:i:s',time() - 23*3600);
        $last_end_date = date('Y-m-d H:i:s',time() - 24*3600);
        $expected_delivery_date_start = date('Y-m-d', strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session)));
        $expected_delivery_date_end = date('Y-m-d', strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session)) + 7*86400);
        $future_margin_start = changeOutPutByZone(date('Y-m-d H:i:s',time() - 7*86400), $this->session);
        $future_margin_end   = changeOutPutByZone(date('Y-m-d H:i:s',time() - 7*86400 + 3600), $this->session);

        $condition = [
            'last_tips_date' => $last_tips_date,
            'last_end_date' => $last_end_date,
            'expected_delivery_date_start' => $expected_delivery_date_start,
            'expected_delivery_date_end' => $expected_delivery_date_end,
            'future_margin_start' => $future_margin_start,
            'future_margin_end' => $future_margin_end,
            'from_tz' => $fromTz,
            'to_tz' => $toTz,
        ];

        $query = $this->orm->connection('read')->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->where([
                'fa.buyer_id' => $buyerId,
            ])
            ->where(function (Builder $query){
                $query->where([
                    'fa.is_bid'=> 0,
                    'fa.agreement_status'=> 7,
                ])->orWhere([ 'fa.is_bid'=> 1]);
            })
            ->where(function ($query) use ($condition) {
                $query->where(function ($query) use ($condition) {
                    $query->where('fa.update_time', '>=', $condition['last_end_date'])
                        ->where('fa.update_time', '<=', $condition['last_tips_date'])
                        ->whereIn('fa.agreement_status', [1,2,3]);
                })->orWhere(function ($query) use ($condition) {
                    $query->where('fa.expected_delivery_date', '>=', $condition['expected_delivery_date_start'])
                        ->where('fa.expected_delivery_date', '<', $condition['expected_delivery_date_end'])
                        ->where('fd.delivery_status', 1);
                })->orWhere(function ($query) use ($condition) {
                    $query->where('fd.confirm_delivery_date', '>=', $condition['last_end_date'])
                        ->where('fd.confirm_delivery_date', '<=', $condition['last_tips_date'])
                        ->where([
                            'fd.delivery_status'=> 6,
                            'fd.delivery_type'=> 2,
                        ]);
                })->orWhere(function ($query) use ($condition) {
                    $query->where([
                        'fd.delivery_status'=> 6,
                        'fd.delivery_type'=> 1,
                    ])->whereRaw("CONCAT(DATE(CONVERT_TZ(fd.confirm_delivery_date, ?, ?)), ' 23:59:59') between ? and ?", [
                        $condition['from_tz'],
                        $condition['to_tz'],
                        $condition['future_margin_start'],
                        $condition['future_margin_end']
                    ]);
                });
            });

        //排除Quick View直接下单，但是未支付头款订单，产生的协议
        $query->where(function (Builder $query) {
            $query->where([
                ['fa.contract_id', '<>', 0],
                ['fa.is_bid', '=', 0],
                ['fa.agreement_status', '=', 7],
            ])->orWhere([
                ['fa.contract_id', '<>', 0],
                ['fa.is_bid', '=', 1],
            ])->orWhere([
                ['fa.contract_id', '=', 0],
            ]);
        });

        return $query->count();
    }


    /*
     * Futures Bids 待处理、待交付、待交割、待支付协议总数
     * */
    public function totalForBuyer($buyerId)
    {
        $toBeProcessedCount = $this->toBeProcessedCount($buyerId);
        $toBeDeliveredCount = $this->toBeDeliveredCount($buyerId);
        $toBePaidCount      = $this->toBePaidCount($buyerId);
        $dueSoonCount       = $this->dueSoonCount($buyerId);

        return $toBeProcessedCount + $toBeDeliveredCount + $toBePaidCount + $dueSoonCount;
    }

    /*
     * 协议所有状态
     * */
    public function agreementStatusList()
    {
        return self::AGREEMENT_STATUS;
    }

    /*
     * 交割状态
     * */
    public function deliveryStatusList()
    {
        return self::DELIVERY_STATUS;
    }

    /*
     * Buyer取消协议
     * */
    public function cancelAgreement($agreementInfo)
    {
        $id = $agreementInfo->id;
        if ($agreementInfo->contract_id == 0) {
            //期货保证金一期
            return $this->orm->table('oc_futures_margin_agreement')
                ->where([
                    'id'       => $id,
                    'buyer_id' => $this->customer->getId(),
                ])
                ->whereIn('agreement_status', [1, 3])
                ->update([
                    'agreement_status' => self::AGREEMENT_CANCELED,
                ]);
        } else {
            //期货保证金二期
            return $this->orm->table('oc_futures_margin_agreement')
                ->where([
                    'id'       => $id,
                    'buyer_id' => $this->customer->getId(),
                ])
                ->whereIn('agreement_status', [1, 2, 3])
                ->update([
                    'agreement_status' => self::AGREEMENT_CANCELED,
                    'is_lock'          => 0,
                ]);
        }
    }


    /**
     * 期货保证金二期(Buyer取消协议)
     * @param $agreementInfo
     * @return bool
     * @throws Exception
     */
    public function cancelAgreementAfter($agreementInfo)
    {
        //返还seller合约保证金，即[系统]向[seller]返还合约保证金
        //参考：yzc_task_work\app\Models\Future\Agreement.php updateSellerPayRecord方法
        if (FutureMarginContractStatus::TERMINATE != $agreementInfo->contract_status) {
            return false;
        }
        $this->load->model('futures/contract');

        $firstPayRecordContracts = $this->model_futures_contract->firstPayRecordContracts($agreementInfo->seller_id, [$agreementInfo->contract_id]);
        $point                   = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
        $amount                  = round($agreementInfo->unit_price * $agreementInfo->seller_payment_ratio / 100, $point) * $agreementInfo->num;
        if ($firstPayRecordContracts) {
            $records = [
                'contract_id' => $agreementInfo->contract_id,
                'customer_id' => $agreementInfo->seller_id,
                'type'        => $firstPayRecordContracts[0]['pay_type'],
                'amount'      => $amount,
                'bill_type'   => 2,
                'bill_status' => $firstPayRecordContracts[0]['pay_type'] == 1 ? 1 : 0,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
                'operator'    => 'System',
            ];
            try {
                DB::connection()->beginTransaction();

                if ($amount > 0 && $firstPayRecordContracts[0]['pay_type'] == 1) {
                    credit::insertCreditBill($agreementInfo->seller_id, $amount, 2);//添加授信账单
                }

                //更新期货合约余额 判断协议是否使用了合约中抵押物的金额
                $collateralBalance = agreementMargin::updateContractBalance($agreementInfo->contract_id, $amount, $firstPayRecordContracts[0]['pay_type']);

                if ($collateralBalance > 0) {
                    if ($records['amount'] - $collateralBalance > 0) {
                        $records['amount'] = $records['amount'] - $collateralBalance;
                        $this->orm->table(DB_PREFIX . 'futures_contract_margin_pay_record')->insert($records);//添加 seller期货协议保证金明细表
                    }

                    $records['type'] = FuturesMarginPayRecordType::SELLER_COLLATERAL;
                    $records['amount'] = $collateralBalance;
                    $this->orm->table(DB_PREFIX . 'futures_contract_margin_pay_record')->insert($records);//添加 seller期货协议保证金明细表
                } else {
                    $this->orm->table(DB_PREFIX . 'futures_contract_margin_pay_record')->insert($records);//添加 seller期货协议保证金明细表
                }

                DB::connection()->commit();
                return true;
            } catch (Exception $e) {
                DB::connection()->rollBack();
                return false;
            }
        }
    }


    /**
     * 下架期货头款商品
     * @param int $agreementId
     * @return bool|int
     */
    public function handleAdvanceProduct($agreementId)
    {
        //尚未付款的期货头款商品
        $advanceProductId = $this->orm->table('oc_futures_margin_process')
            ->where('agreement_id', $agreementId)
            ->where('process_status', 1)
            ->value('advance_product_id');
        if ($advanceProductId){
            return $this->orm->table('oc_product')
                ->where('product_id', $advanceProductId)
                ->update([
                    'is_deleted'    => 1,
                    'status'        => 0
                ]);
        }else{
            return true;
        }
    }


    /**
     * 退还seller期货保证金
     * @param int $agreement_id
     * @return bool
     * @throws Exception
     */
    public function sellerMarginBack($agreement_id)
    {

        $res = $this->updateMarginPayRecord($agreement_id, ['status'=>1]);
        if ($res) {
            $record = $this->getMarginPayRecord($agreement_id);
            // 如果是授信支付，就添加一条授信账单
            if ($record->type == 1) {
                $credit = credit::getLastBill($record->customer_id);
                $data['seller_id'] = $record->customer_id;
                $data['amount'] = $record->amount;
                $data['type'] = 2;
                $data['current_balance'] = $credit->current_balance + $record->amount;
                $data['introduce'] = 'Return future goods deposit';
                credit::addCreditBill($data);
            }
            return true;
        }
        return false;
    }

    /*
     * 重新申请 （拒绝或超时情况下）
     * */
    public function reapplyAgreement($postData)
    {
        $id = empty($postData['agreement_id'])?0:$postData['agreement_id'];
        if (empty($id)){
            return false;
        }
        $info = $this->getAgreementById($id);
        if (self::AGREEMENT_REJECTED != $info->agreement_status && self::AGREEMENT_TIMEOUT != $info->agreement_status){
            return false;
        }

        $data = [
            'num'           => $postData['qty'],
            'unit_price'    => $postData['price'],
            'comments'      => $postData['message'],
            'agreement_status'  => self::AGREEMENT_APPLIED,
        ];

        return $this->orm->table('oc_futures_margin_agreement')
            ->where([
                'id'        =>$id,
                'buyer_id'  =>$this->customer->getId(),
            ])
            ->update($data);

    }

    /*
     * 忽略该协议
     * */
    public function ignoreAgreement($info)
    {
        $id = $info->id;
        if ((in_array($info->agreement_status, [4,6]) && !$info->delivery_status)
            || self::DELIVERY_BACK_ORDER == $info->delivery_status ){

            return $this->orm->table('oc_futures_margin_agreement')
                ->where([
                    'id'        =>$id,
                    'buyer_id'  =>$this->customer->getId()
                ])
                ->update([
                    'ignore'    => self::IGNORE,
                ]);
        }
        return false;
    }

    /**
     * 协议详情 buyer
     * @param int $id
     * @return array
     * @throws Exception
     */
    public function agreementInfoForBuyer($id)
    {
        $select = [
            'fa.id as agreement_id',
            'fa.contract_id',
            'fa.agreement_no',
            'fa.product_id',
            'fa.buyer_id',
            'fa.seller_id',
            'fa.num',
            'fa.unit_price',
            'fa.buyer_payment_ratio',
            'fa.seller_payment_ratio',
            'fa.min_expected_storage_days',
            'fa.max_expected_storage_days',
            'fa.expected_delivery_date',
            'fa.agreement_status',
            'fa.ignore',
            'fa.comments',
            'fa.update_time as agreement_update_time',
            'fd.id as delivery_id',
            'fd.delivery_type',
            'fd.delivery_status',
            'fd.delivery_date',
            'fd.last_purchase_num',
            'fd.last_unit_price',
            'fd.margin_apply_num',
            'fd.margin_unit_price',
            'fd.margin_deposit_amount',
            'fd.margin_last_price',
            'fd.margin_agreement_amount',
            'fd.margin_days',
            'fd.margin_agreement_id',
            'fd.update_time as delivery_update_time',
            'p.sku',
            'p.price as product_price',
            'p.freight',
            'IFNULL(pf.fee,0)  as package_fee',
            'c2c.screenname',
            'c2c.avatar',
            'if(fa.agreement_status < '.self::AGREEMENT_APPROVED.',null,if(fd.delivery_date, fd.delivery_date, fa.expected_delivery_date)) as delivery_date'
        ];
        $info = $this->orm->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->leftJoin('oc_product as p', 'p.product_id', 'fa.product_id')
            ->leftJoin('oc_customerpartner_to_customer as c2c', 'c2c.customer_id', 'fa.seller_id')
            ->leftJoin('oc_product_fee as pf',function($query){     // 打包附加费 更改了打包费的的表 2020-06-30 14:04:17 by lester.you
                $query->on('pf.product_id', '=', 'fa.product_id')
                    ->where('pf.type', '=', $this->customer->isCollectionFromDomicile() ? 2 : 1);
            })
            ->where('fa.id', $id)
            ->select(DB::raw(implode(',',$select)))
            ->first();

        if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()){
            $format = '%d';
            $precision = 0;
        }else{
            $format = '%.2f';
            $precision = 2;
        }

        $info->unit_price = sprintf($format, $info->unit_price);
        $deposit_price = sprintf($format, round($info->unit_price * $info->buyer_payment_ratio / 100, $precision));//定金单价
        $info->unit_deposit = $deposit_price;
        $info->deposit = sprintf($format, round($deposit_price * $info->num, $precision));
        if (0.00 == $info->last_unit_price){
            $info->last_unit_price = sprintf($format, round($info->unit_price - $deposit_price, $precision));//尾款单价
        }else{
            $info->last_unit_price = sprintf($format, round($info->last_unit_price, $precision));//尾款单价
        }
        $info->amount = sprintf($format, round($info->unit_price * $info->num, $precision));//协议金额

        if ($info->delivery_status){
            $info->status_name  = self::DELIVERY_STATUS[$info->delivery_status]['name'];
            $info->status_color = self::DELIVERY_STATUS[$info->delivery_status]['color'];
        }else{
            if ($info->ignore){
                $info->agreement_status = self::IGNORE_STATUS;
            }
            $info->status_name  = self::AGREEMENT_STATUS[$info->agreement_status]['name'];
            $info->status_color = self::AGREEMENT_STATUS[$info->agreement_status]['color'];
        }
        if ($info->agreement_status >= self::AGREEMENT_APPROVED && $info->delivery_date > '2020-00-00'){
            $info->delivery_date = substr($info->delivery_date, 0, 10);
        }else{
            $info->delivery_date = null;
        }

        if ($info->avatar && file_exists(DIR_IMAGE . $info->avatar)){
            $this->load->model('tool/image');
            $info->avatar = $this->model_tool_image->resize($info->avatar);
        }else{
            $info->avatar = '';
        }
        if ($this->customer->isCollectionFromDomicile()){//是否是上门取货buyer 上门取货buyer不收取运费
            $info->logistics_fee = $info->package_fee;
        }else{
            $info->logistics_fee = sprintf($format, round($info->freight + $info->package_fee, $precision));
        }

        $info->liquidation_day = $this->config->get('liquidation_day');

        if (in_array($info->delivery_status, [3,4,5,6,7,8])){
            if (self::DELIVERY_TYPE_MARGIN != $info->delivery_type){
                $final = sprintf($format, round($info->last_unit_price + $info->logistics_fee, $precision));
                $info->d_logistics_fee_total = sprintf($format, round($info->last_purchase_num * $info->logistics_fee, $precision));
                $info->d_final_payment_total = sprintf($format, round($info->last_purchase_num * $final, $precision));
            }

            if ('0.00' == $info->margin_unit_price){
                $info->margin_unit_price = $info->unit_price;//初始默认值
            }
            $info->margin_unit_price = sprintf($format, round($info->margin_unit_price, $precision));
            $info->margin_deposit_amount = sprintf($format, round($info->margin_deposit_amount, $precision));
            $info->margin_last_price = sprintf($format, round($info->margin_last_price, $precision));
            $info->margin_agreement_amount = sprintf($format, round($info->margin_agreement_amount, $precision));
            $info->margin_unit_price = sprintf($format, round($info->margin_unit_price, $precision));
        }

        if (self::DELIVERY_TYPE_FUTURES != $info->delivery_type && $info->margin_agreement_id){
            $info->margin_agreement_code = $this->getMarginCode($info->margin_agreement_id);
            $info->margin_detail_url = $this->url->link('account/product_quotes/margin/detail_list', '&id='.$info->margin_agreement_id);
        }

        return obj2array($info);
    }

    /*
     * 取现货协议号
     * */
    public function getMarginCode($marginId)
    {
        return $this->orm->table('tb_sys_margin_agreement')
            ->where('id', $marginId)
            ->value('agreement_id');
    }

    /*
     * 通过期货头款商品ID取期货协议号
     * */
    public function getFuturesCodeByAdvanceProductId($advanceProductId)
    {
        return $this->orm->table('oc_futures_margin_process as fp')
            ->leftJoin('oc_futures_margin_agreement as fa', 'fa.id', 'fp.agreement_id')
            ->where('fp.advance_product_id',$advanceProductId)
            ->value('fa.agreement_no');
    }

    /*
     * 通过期货头款商品ID取期货协议ID
     * */
    public function getFuturesIdByAdvanceProductId($advanceProductId)
    {
        return $this->orm->table('oc_futures_margin_process')
            ->where('advance_product_id',$advanceProductId)
            ->value('agreement_id');
    }

    /**
     * @param int $agreementId
     * @return array
     */
    public function messageInfo($agreementId)
    {
        $msg = $this->orm->table('oc_futures_margin_message')
            ->where('agreement_id', $agreementId)
            ->orderBy('id')
            ->get()
            ->toArray();
        return obj2array($msg);
    }

    /*
     * buyer 修改协议
     * */
    public function editAgreement($postData)
    {
        $id = empty($postData['agreement_id'])?0:$postData['agreement_id'];
        if (empty($id)){
            return false;
        }
        $customer_id = $this->customer->getId();
        $operator    = $this->customer->getFirstName() . $this->customer->getLastName();
        $agreementInfo = $this->getAgreementById($id);
        if ($agreementInfo->contract_id == 0) {//现货保证金一期
            if (self::AGREEMENT_APPLIED != $agreementInfo->agreement_status
                && self::AGREEMENT_PENDING != $agreementInfo->agreement_status
            ) {
                return false;
            }

            $data = [
                'num'         => $postData['qty'],
                'unit_price'  => $postData['price'],
                'update_time' => date('Y-m-d H:i:s')
            ];
            $this->orm->table('oc_futures_margin_agreement')
                ->where('id', $id)
                ->where('buyer_id', $this->customer->getId())
                ->update($data);

            $this->addMessage([
                'agreement_id' => $id,
                'customer_id'  => $this->customer->getId(),
                'message'      => $postData['message']
            ]);
        } else {//现货保证金二期
            if (self::AGREEMENT_APPLIED != $agreementInfo->agreement_status
                && self::AGREEMENT_PENDING != $agreementInfo->agreement_status
                && self::AGREEMENT_APPROVED != $agreementInfo->agreement_status
            ) {
                return false;
            }

            if (1 != $agreementInfo->contract_status) {
                return false;
            }

            //期货保证金协议表
            $data = [
                'num' => $postData['qty'],
                'unit_price' => $postData['price'],
                'update_time' => date('Y-m-d H:i:s'),
                'agreement_status' => 1,//协议状态为Applied
                'is_lock' => 0,
            ];
            $this->orm->table('oc_futures_margin_agreement')
                ->where('id', $id)
                ->where('buyer_id', $this->customer->getId())
                ->update($data);


            //期货保证金协议交割方式表，保持与 controller\account\product_quotes\futures.php addAgreement()方法一致
            $data = [];
            if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()) {
                $format = '%d';
                $precision = 0;
            } else {
                $format = '%.2f';
                $precision = 2;
            }
            $perDeposit = sprintf($format, round($postData['price'] * $agreementInfo->buyer_payment_ratio / 100, $precision));
            if ($agreementInfo->delivery_type == 1) {
                $data['last_purchase_num'] = $postData['qty'];
                $data['last_unit_price'] = bcsub($postData['price'], $perDeposit, $precision);
            } else {
                // 用现货的20%,减去期货保证金支付比例
                // 101306 期货二期 期货保证金支付比例大于现货的比例，则现货的支付比例为期货的支付比例
                $marginPaymentRatio = MARGIN_PAYMENT_RATIO;
                if ($agreementInfo->buyer_payment_ratio * 0.01 > $marginPaymentRatio) {
                    $marginPaymentRatio = $agreementInfo->buyer_payment_ratio * 0.01;
                }
                $data['margin_apply_num'] = $postData['qty'];//转现货保证金申请数量
                $data['margin_unit_price'] = $postData['price'];//转现货保证金单价
                $margin_deposit_paid_amount = sprintf($format, round($perDeposit * $data['margin_apply_num'], $precision));//已付定金总金额
                $margin_unit_deposit = sprintf($format, round($data['margin_unit_price'] * $marginPaymentRatio, $precision));//现货定金单价
                $data['margin_last_price'] = sprintf($format, round($data['margin_unit_price'] - $margin_unit_deposit, $precision));//转现货保证金尾款单价
                $deposit_amount = sprintf($format, round($margin_unit_deposit * $data['margin_apply_num'], $precision));//转现货定金总金额
                $data['margin_deposit_amount'] = sprintf($format, round($deposit_amount - $margin_deposit_paid_amount, $precision));//补足款 转现货保证金需补足定金金额
                $margin_last_price_amount = sprintf($format, round($data['margin_last_price'] * $data['margin_apply_num'], $precision));
                $data['margin_agreement_amount'] = sprintf($format, round(floatval($data['margin_deposit_amount']) + floatval($margin_last_price_amount), $precision));//现货协议总金额 转现货保证金协议金额
            }
            $this->updateDelivery($id, $data);


            //生成的原保证金产品作废
            $this->handleAdvanceProduct($id);


            //记录操作日志、添加Message、发站内信
            $log_type = 0;
            switch ($agreementInfo->agreement_status) {
                case self::AGREEMENT_APPLIED:
                    $log_type = 35;
                    break;
                case self::AGREEMENT_PENDING:
                    $log_type = 36;
                    break;
                case self::AGREEMENT_APPROVED:
                    $log_type = 37;
                    break;
            }
            $info = [];
            $info['delivery_status'] = null;
            $info['apply_status'] = null;
            $info['add_or_update'] = 'add';//新增message消息
            $info['remark'] = $postData['message'];//message消息
            $info['communication'] = true;//是否发站内信
            $info['from'] = $agreementInfo->buyer_id;
            $info['to'] = $agreementInfo->seller_id;
            $info['country_id'] = $this->customer->getCountryId();
            $info['status'] = null;//1 同意 0 拒绝
            $info['communication_type'] = 2;
            $info['apply_type'] = null;

            $log = [];
            $log['info'] = [
                'agreement_id' => $agreementInfo->id,
                'customer_id' => $customer_id,
                'type' => $log_type,
                'operator' => $operator,
            ];
            $log['agreement_status'] = [$agreementInfo->agreement_status, 1];
            $log['delivery_status'] = [$agreementInfo->delivery_status, $info['delivery_status']];
            $this->updateFutureAgreementAction(
                $agreementInfo,
                $customer_id,
                $info,
                $log
            );
        }


        return true;
    }

    /*
     * buyer 选择交割方式
     * */
    public function applyDelivery($postData)
    {
        $agreementId = empty($postData['agreement_id'])?0:$postData['agreement_id'];
        $deliveryType = $postData['delivery_type'];
        $lastPurchaseNum = empty($postData['last_purchase_num'])?0:intval($postData['last_purchase_num']);

        if (empty($agreementId)){
            return false;
        }
        $info = $this->getAgreementById($agreementId);
        if (!in_array($info->delivery_status, self::DELIVERY_BEING_PROCESSED)){
            return false;
        }
        $data = [
            'delivery_type'     => $deliveryType,
            'delivery_status'   => self::DELIVERY_PROCESSING,
            'last_purchase_num' => self::DELIVERY_TYPE_MARGIN == $deliveryType ? 0 : $lastPurchaseNum
        ];
        if (self::DELIVERY_TYPE_FUTURES != $deliveryType){
            if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()){
                $format = '%d';
                $precision = 0;
            }else{
                $format = '%.2f';
                $precision = 2;
            }
            $margin_apply_num = empty($postData['margin_apply_num'])?0:intval($postData['margin_apply_num']);
            $margin_unit_price = empty($postData['margin_unit_price'])?0:$postData['margin_unit_price'];
            $unit_deposit = sprintf($format, round($info->unit_price * $info->buyer_payment_ratio / 100, $precision));//期货定金单价
            $margin_deposit_paid_amount = sprintf($format, round($unit_deposit * $margin_apply_num, $precision));//已付定金金额
            $margin_unit_deposit = sprintf($format, round($margin_unit_price * MARGIN_PAYMENT_RATIO, $precision));//现货定金单价
            $margin_last_price = sprintf($format, round($margin_unit_price - $margin_unit_deposit, $precision));//现货尾款单价
            $deposit_amount = sprintf($format, round($margin_unit_deposit * $margin_apply_num, $precision));//转现货定金总金额
            $margin_deposit_amount = sprintf($format, round($deposit_amount - $margin_deposit_paid_amount, $precision));//补足款
            $margin_last_price_amount = sprintf($format, round($margin_last_price * $margin_apply_num, $precision));
            $margin_agreement_amount = sprintf($format, round(floatval($margin_deposit_amount) + floatval($margin_last_price_amount), $precision));//现货协议总金额

            $data = array_merge($data, [
                'margin_apply_num'      => $margin_apply_num,
                'margin_unit_price'     => $margin_unit_price,
                'margin_deposit_amount' => $margin_deposit_amount,
                'margin_last_price'     => $margin_last_price,
                'margin_agreement_amount'   => $margin_agreement_amount,
                'margin_days'           => 30,
            ]);
        }else{
            $data = array_merge($data, [
                'margin_apply_num'      => 0,
                'margin_unit_price'     => 0,
                'margin_deposit_amount' => 0,
                'margin_last_price'     => 0,
                'margin_agreement_amount'   => 0,
                'margin_days'           => 30,
            ]);
        }

        $ret = $this->orm->table('oc_futures_margin_delivery')
            ->where('agreement_id', $agreementId)
            ->update($data);

        return $ret;
    }

    /*
     * buyer 拒绝履约
     * */
    public function terminated($agreementId)
    {
        $info = $this->getAgreementById($agreementId);
        if (!in_array($info->delivery_status, self::DELIVERY_BEING_PROCESSED)){
            return false;
        }

        $ret = $this->orm->table('oc_futures_margin_delivery')
            ->where([
                'agreement_id' => $agreementId,
            ])
            ->update([
                'delivery_status' => self::DELIVERY_TERMINATED,
            ]);
        if ($ret){
            $this->addMessage([
                'agreement_id'  => $agreementId,
                'customer_id'   => $this->customer->getId(),
                'message'       => 'Buyer has been canceled this agreement.'
            ]);
        }
        return $ret;
    }


    /**
     * Buyer要看Seller发起的取消交付协议的申请信息
     * @param int $id
     * @return string
     */
    public function getSellerTerminationMessage($id)
    {
        $apply = $this->orm->table('oc_futures_agreement_apply AS aa')
            ->leftJoin('oc_futures_margin_message AS mm', 'mm.apply_id', '=', 'aa.id')
            ->where([
                ['aa.agreement_id', '=', $id],
                ['aa.customer_id', '<>', $this->customer->getId()],
                ['aa.apply_type', '=', 3],
                ['aa.status', '=', 0],
                ['mm.customer_id', '=', 'aa.customer_id'],
            ])
            ->orderBy('aa.id', 'DESC')
            ->offset(0)
            ->limit(1)
            ->select(['mm.message'])
            ->first();

        return $apply ? $apply->message : '';
    }


    /*
     * 获取期货相关的订单详情
     * */
    public function getOrderInfo($orderId)
    {
        return $this->orm->table('oc_order as o')
            ->leftJoin('oc_order_product as op', 'op.order_id', 'o.order_id')
            ->where('o.order_id', $orderId)
            ->where('op.type_id', '>', 1)
            ->select('op.type_id', 'op.agreement_id', 'op.product_id')
            ->get();
    }

    /*
     * 订单支付成功后调用
     * */
    public function handleFuturesProcess($orderId)
    {
        $orderInfo = $this->getOrderInfo($orderId);
        foreach ($orderInfo as $k=>$v)
        {
            if (self::TRANSACTION_TYPE_MARGIN == $v->type_id){//现货

                $advanceProductIdM = $this->orm->table('oc_futures_margin_delivery as fd')
                    ->leftJoin('tb_sys_margin_process as mp', 'fd.margin_agreement_id', 'mp.margin_id')
                    ->where('fd.margin_agreement_id', $v->agreement_id)
                    ->value('mp.advance_product_id');
                if ($advanceProductIdM == $v->product_id){//现货头款才跟期货有关
                    $this->afterFinalPayment($orderId, 0, $v->agreement_id);
                }

            }elseif (self::TRANSACTION_TYPE_FUTURES == $v->type_id){//期货
                $advanceProductId = $this->getFuturesAdvanceProductId($v->agreement_id);
                if ($advanceProductId == $v->product_id){//头款
                    $this->afterAdvanceProduct($orderId, $v->agreement_id, $advanceProductId);
                }else{//尾款
                    $this->afterFinalPayment($orderId, $v->agreement_id);
                }
            }
        }
    }

    /*
     * 头款支付成功后调用
     * */
    public function afterAdvanceProduct($orderId,$agreementId, $advanceProductId)
    {
        $info = $this->getAgreementById($agreementId);
        if ($info){
            $this->orm->table($this->table)
                ->where([
                    'id' => $agreementId
                ])
                ->update([
                    'agreement_status'  => self::AGREEMENT_SOLD
                ]);
            $this->orm->table('oc_futures_margin_process')
                ->where([
                    'agreement_id'  => $agreementId,
                    'process_status'=> 1,
                ])
                ->update([
                    'process_status'    => 2,
                    'advance_order_id'  => $orderId
                ]);
            //下架删除该期货头款商品
            $this->orm->table('oc_product')
                ->where('product_id', $advanceProductId)
                ->update([
                    'status'    => 0,
                    'is_deleted'=> 1,
                    'date_modified' => date('Y-m-d H:i:s')
                ]);

            if (is_null($info->delivery_id)){
                $this->orm->table('oc_futures_margin_delivery')
                    ->insert([
                        'agreement_id'  => $agreementId,
                        'delivery_type' => 0,
                        'delivery_status'   => self::DELIVERY_FORWARD_DELIVERY,
                        'last_unit_price'   => $this->lastUnitPrice($agreementId)
                    ]);
            }
            // 判断是不是期货二期
            if ($info->contract_id) {
                // 更改交付状态为待交付
                $this->orm->table('oc_futures_margin_delivery')
                    ->where('agreement_id',$agreementId)
                    ->update(['delivery_status'   => self::DELIVERY_FORWARD_DELIVERY]);
                // 修改协议的锁状态
                $this->orm->table('oc_futures_margin_agreement')
                    ->where('id', $agreementId)
                    ->update(['is_lock' => 0]);
                // 增加log
                $log = [
                    'agreement_id' => $agreementId,
                    'customer_id' => $info->buyer_id,
                    'type' => 47,
                    'operator' => 'System',
                ];
                $this->addAgreementLog($log,
                    [self::AGREEMENT_APPROVED,self::AGREEMENT_SOLD],
                    [0, self::DELIVERY_FORWARD_DELIVERY]
                );
                $this->load->model('futures/contract');
                $precision = 2;
                // 日本国家金额取整
                if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()) {
                    $precision = 0;
                }
                $amount = round($info->unit_price * $info->seller_payment_ratio / 100, $precision) * $info->num;
                // 获取合约的保证金类型是授信额度还是seller账单
                $payRecord = $this->model_futures_contract->firstPayRecordContracts($info->seller_id, [$info->contract_id]);
                agreementMargin::sellerPayFutureMargin($info->seller_id, $info->contract_id, $info->id, $amount, $payRecord[0]['pay_type']);
                // 修改合约已完成数量
                $build = $this->orm->table('oc_futures_contract')
                    ->where('id', $info->contract_id);
                $build->increment('purchased_num', $info->num);
                // 如果合约全部都完成，则修改合约状态为完成
                $contract = $build->first();
                if ($contract->num <= $contract->purchased_num) {
                    $build->update(['status' => 3]);
                }
                //站内信
                $communication_info = [
                    'from'=> $info->buyer_id,
                    'to'=> $info->seller_id,
                ];
                $this->addFuturesAgreementCommunication($info->id,1,$communication_info);
            }
        }

    }

    /*
     * 获取期货尾款单价
     * */
    public function lastUnitPrice($agreementId)
    {

        $info = $this->getAgreementById($agreementId);
        if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()){
            $format = '%d';
            $precision = 0;
        }else{
            $format = '%.2f';
            $precision = 2;
        }

        $info->unit_price = sprintf($format, $info->unit_price);
        $deposit_price = sprintf($format, round($info->unit_price * $info->buyer_payment_ratio / 100, $precision));//定金单价
        $last_unit_price = sprintf($format, round($info->unit_price - $deposit_price, $precision));//尾款单价

        return $last_unit_price;
    }

    /*
     * 期货尾款支付成功后
     * */
    public function afterFinalPayment($orderId,$futuresId=0,$marginId=0)
    {
        if ($futuresId){
            $agreementInfo = $this->getAgreementById($futuresId);
        }elseif($marginId){
            $agreementInfo = $this->getInfoByMarginId($marginId);
        }else{
            return false;
        }

        switch ($agreementInfo->delivery_type){
            case self::DELIVERY_TYPE_FUTURES:{//支付期货尾款
                $purchaseQty = $this->getPurchaseNum($orderId, $agreementInfo->product_id);
                $purchaseSum = $this->getPurchaseSum($futuresId);
                $lockQty = $this->lockQty($futuresId);
                $completed = ($purchaseQty + $purchaseSum >= $agreementInfo->last_purchase_num ? true:false) && !$lockQty;
                if ($purchaseQty){
                    $this->orm->table('oc_futures_margin_order_relation')
                        ->insert([
                            'agreement_id'  => $futuresId,
                            'rest_order_id' => $orderId,
                            'purchase_quantity' => $purchaseQty,
                            'product_id'    => $agreementInfo->product_id
                        ]);
                }
                if ($completed){
                    $processStatus = self::PROCESS_STATUS_4;
                }elseif(!$purchaseSum){//purchaseSum值为0说明此单为该期货尾款第一单
                    $processStatus = self::PROCESS_STATUS_3;
                }else{
                    $processStatus = 0;
                }

                break;
            }
            case self::DELIVERY_TYPE_MARGIN:{//全部转现货保证金
                if ($agreementInfo->margin_agreement_id == $marginId){
                    $processStatus = self::PROCESS_STATUS_4;
                    $completed = true;
                }else{
                    $processStatus = 0;
                }
                $futuresId = $agreementInfo->agreement_id;
                break;
            }
            case self::DELIVERY_TYPE_COMB:{//组合交割
                $purchaseQty = 0;
                $purchaseSum = $this->getPurchaseSum($agreementInfo->agreement_id);
                $completed = false;
                if ($futuresId){
                    $purchaseQty = $this->getPurchaseNum($orderId, $agreementInfo->product_id);
                    if ($purchaseQty){
                        $this->orm->table('oc_futures_margin_order_relation')
                            ->insert([
                                'agreement_id'  => $futuresId,
                                'rest_order_id' => $orderId,
                                'purchase_quantity' => $purchaseQty,
                                'product_id'    => $agreementInfo->product_id
                            ]);
                    }
                }
                if (!$marginId){
                    $marginProcess = $this->marginAdvanceProcess($agreementInfo->margin_agreement_id);
                    if ($marginProcess && $marginProcess['process_status'] >= self::PROCESS_STATUS_2){
                        $completed = true;
                    }
                }else{
                    $completed = true;
                }
                //已采购数量达到协议数量 且RMA数量已再次采购
                $lockQty = $this->lockQty($agreementInfo->agreement_id);
                $completed = $completed && ($purchaseQty + $purchaseSum >= $agreementInfo->last_purchase_num ? true:false) && !$lockQty;
                if ($completed){
                    $processStatus = self::PROCESS_STATUS_4;
                }elseif(!$purchaseSum){//purchaseSum值为0说明此单为该期货尾款第一单
                    $processStatus = self::PROCESS_STATUS_3;
                }else{
                    $processStatus = 0;
                }

                break;
            }

        }

        if (isset($processStatus) && $processStatus){
            $this->orm->table('oc_futures_margin_process')
                ->where(['agreement_id'  => $agreementInfo->agreement_id])
                ->update([
                    'process_status'    => $processStatus
                ]);
        }
        if (isset($completed) && $completed){
            $this->orm->table('oc_futures_margin_delivery')
                ->where('agreement_id', $agreementInfo->agreement_id)
                ->where('delivery_status', self::DELIVERY_TO_BE_PAID)
                ->update([
                    'delivery_status'   => self::DELIVERY_COMPLETED
                ]);
            // 退还Seller支付的保证金
            if ($agreementInfo->version == FuturesVersion::VERSION) {
                self::sellerBackFutureMargin($futuresId, $this->customer->getCountryId());
            }
            if($marginId){
                $money = $this->orm->table('tb_sys_margin_agreement')->where('id',$marginId)->value('money');
                if($money <= 0){
                    $log = [
                        'agreement_id' => $agreementInfo->agreement_id,
                        'customer_id' => 0,
                        'type' => 51,
                        'operator' => 'System',
                    ];
                }else{
                    $log = [
                        'agreement_id' => $agreementInfo->agreement_id,
                        'customer_id' => $agreementInfo->buyer_id,
                        'type' => 50,
                        'operator' => $this->customer->getFirstName() . $this->customer->getLastName(),
                    ];
                }

                $this->addAgreementLog(
                    $log,
                    [self::AGREEMENT_SOLD, self::AGREEMENT_SOLD],
                    [self::DELIVERY_TO_BE_PAID, self::DELIVERY_COMPLETED]
                );

            }
        }

        return true;
    }
    /*
     * 货期锁定库存余量
     * */
    public function lockQty($agreementId)
    {
        $result = 0;
        $query = $this->orm->table('oc_product_lock')
            ->where([
                'agreement_id' => $agreementId,
                'type_id' => ProductLockType::FUTURES
            ])
            ->select('qty', 'set_qty')
            ->first();
        $qty = intval($query->qty ?? 0);
        $set_qty = intval($query->set_qty ?? 0);
        if ($set_qty > 0 && $qty >= $set_qty) {
            $result = intval($qty / $set_qty);
        } else {
            $result = 0;
        }

        return $result;
    }

    /*
     * 获取指定订单中商品采购数量
     * */
    public function getPurchaseNum($orderId,$productId)
    {
        //由于订单支付相关逻辑使用了事物，故采用原生SQL查询
        $sql = "select op.quantity from oc_order_product as op
                LEFT JOIN oc_order as o ON o.order_id = op.order_id
                WHERE op.order_id = {$orderId} AND op.product_id = {$productId} AND o.order_status_id = ".OcOrderStatus::COMPLETED;
        $info = $this->db->query($sql);

        return intval($info->row['quantity']);

        /*$num = $this->orm->table('oc_order_product as op')
            ->leftJoin('oc_order as o', 'o.order_id', 'op.order_id')
            ->where([
                'op.order_id'   => $orderId,
                'op.product_id' => $productId,
                'o.order_status_id' => OcOrderStatus::COMPLETED,
            ])
            ->value('op.quantity');
        return intval($num);*/
    }

    /*
     * 获取已支付尾款的期货数量
     * */
    public function getPurchaseSum($agreementId)
    {
        //由于订单支付相关逻辑使用了事物，故采用原生SQL查询
        $sql = "select sum(purchase_quantity) as qty from oc_futures_margin_order_relation WHERE agreement_id = {$agreementId}";
        $info = $this->db->query($sql);

        return intval($info->row['qty']);

        /*return $this->orm->table('oc_futures_margin_order_relation')
            ->where(['agreement_id'=>$agreementId])
            ->sum('purchase_quantity');*/
    }

    /*
     * 获取现货保证金进展
     * */
    public function marginAdvanceProcess($marginId)
    {
        //由于订单支付相关逻辑使用了事物，故采用原生SQL查询
        $sql = "select * from tb_sys_margin_process WHERE margin_id = $marginId";
        $info = $this->db->query($sql);

        return $info->row;
        /*return obj2array($this->orm->table('tb_sys_margin_process')
            ->where('margin_id', $marginId)
            ->first());*/
    }

    /*
     * 由现货ID获取期货协议详情
     * */
    public function getInfoByMarginId($marginId)
    {
        return $this->orm->table('oc_futures_margin_delivery as fd')
            ->leftJoin('oc_futures_margin_agreement as fa', 'fa.id', 'fd.agreement_id')
            ->where('margin_agreement_id', $marginId)
            ->where('delivery_type', '!=', self::DELIVERY_TYPE_FUTURES)
            ->select('fa.*', 'fd.*', 'fd.id as delivery_id')
            ->first();
    }

    /*
     * 是否是期货头款商品
     * */
    public function isFuturesAdvanceProduct($productId)
    {
        return $this->orm->table('oc_futures_margin_process')
            ->where('advance_product_id', $productId)
            ->exists();
    }

    /*
     * 获取协议ID
     * */
    public function agreementIdByAdvanceProductId($productId)
    {
        return $this->orm->table('oc_futures_margin_process')
            ->where('advance_product_id', $productId)
            ->where('process_status', self::PROCESS_STATUS_1)
            ->value('agreement_id');
    }

    /**
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model|Builder|object|null
     */
    public function getAgreementById($id)
    {
        return $this->orm->table($this->table.' as a')
            ->select('a.*','d.*','a.id','a.update_time', 'd.id as delivery_id','d.update_time as de_update_time','p.sku','p.mpn')
            ->addSelect(['fc.contract_no', 'fc.min_num', 'fc.status AS contract_status'])
            ->addSelect(['bc.customer_group_id','bc.country_id','bc.nickname','bc.user_number','bc.firstname','bc.lastname','bc.email','bc.telephone'])
            ->selectRaw(new Expression("IF(a.discount IS NULL, '', 100-a.discount) AS discountShow"))
            ->leftJoin('oc_futures_margin_delivery as d','a.id','d.agreement_id')
            ->leftJoin('oc_futures_contract AS fc', 'fc.id', '=', 'a.contract_id')
            ->leftJoin('oc_customer as bc','a.buyer_id' ,'=', 'bc.customer_id')
            ->leftJoin('oc_product as p','a.product_id' ,'=', 'p.product_id')
            ->where(['a.id' => $id])
            ->first();
    }

    public function getAgreementsByIds($ids)
    {
        return $this->orm->table($this->table)
            ->whereIn('id', $ids)
            ->get();
    }

    /**n
     * [checkAgreementStatus description] seller 点开详情页需要把状态置为peding
     * @param int $id
     */
    public function checkAgreementStatus($id){
        $agreement_info = $this->getAgreementById($id);
        if($agreement_info->agreement_status == 1){
            $this->updateAgreement($id,['agreement_status' => 2]);

            //记录操作日志
            $customer_id = $this->customer->getId();
            $operator = $this->customer->getFirstName() . $this->customer->getLastName();
            $log = [
                'agreement_id' => $id,
                'customer_id' => $customer_id,
                'type' => 2,
                'operator' => $operator,
            ];
            $this->addAgreementLog(
                $log,
                [1, 2],
                [0, 0]
            );
        }
    }

    public function updateAgreement($id, $data)
    {
        return $this->orm->table($this->table)
            ->where(['id' => $id])
            ->update($data);
    }

    public function updateDelivery($agreement_id, $data)
    {
        return $this->orm->table('oc_futures_margin_delivery')
            ->where(['agreement_id' => $agreement_id])
            ->update($data);
    }

    public function addFutureApply($data)
    {
        return $this->orm->table('oc_futures_agreement_apply')
            ->insertGetId($data);
    }

    public function updateFutureApply($apply_id,$data)
    {
        return $this->orm->table('oc_futures_agreement_apply')
            ->where('id',$apply_id)
            ->update($data);
    }

    public function addMessage($data){
        return $this->orm->table('oc_futures_margin_message')
            ->insert($data);
    }

    public function getMessages($agreement_id){
        return $this->orm->table('oc_futures_margin_message')
            ->where(['agreement_id' => $agreement_id])
            ->orderBy('id')
            ->get()->toArray();
    }

    /**
     * [getNeedMessages description] 根据类别获取需要的
     * @param int $agreement_id
     * @return array
     */
    public function getApprovalMessages($agreement_id){
        return $this->orm->table('oc_futures_margin_message')
            ->where([
                ['agreement_id','=', $agreement_id],
                ['apply_id','=', 0]

            ])
            ->orderBy('id')
            ->get()->toArray();
    }

    public function getApplyMessages($agreement_id){
        return $this->orm->table('oc_futures_agreement_apply as a')
            ->leftJoin('oc_futures_margin_message as m','m.apply_id','=','a.id')
            ->where([
                ['m.agreement_id' ,'=', $agreement_id],
                ['m.apply_id' ,'!=', 0],
                ['m.receive_customer_id', '=', 0],
            ])
            ->orderBy('m.create_time')
            ->select('m.id','a.agreement_id','m.customer_id','m.message','m.create_time','a.apply_type','a.id as apply_id')
            ->get()->toArray();
    }


    public function getApplyMessagesForBuyer($agreement_id, $customerId=0){
        return $this->orm->table('oc_futures_agreement_apply as a')
            ->leftJoin('oc_futures_margin_message as m','m.apply_id','=','a.id')
            ->where([
                ['a.apply_type','=',5],//5正常交付
                ['m.agreement_id', '=', $agreement_id],
                ['m.apply_id', '!=', 0],
            ])
            ->orwhere(function($query) use ($agreement_id, $customerId){
                return $query->where([
                    ['a.apply_type','=',1],
                    ['m.agreement_id', '=', $agreement_id],
                    ['m.apply_id', '!=', 0],
                    ['m.receive_customer_id', '=', $customerId],
                ]);
            })
            ->orderBy('m.create_time')
            ->select('m.id','a.agreement_id','a.customer_id AS a_customer_id','a.apply_type','m.customer_id','m.message','m.create_time')
            ->get()->toArray();
    }

    public function addMarginPayRecord($data){
        return $this->orm->table('oc_futures_margin_pay_record')
            ->insert($data);
    }

    public function updateMarginPayRecord($agreement_id, $data)
    {
        return $this->orm->table('oc_futures_margin_pay_record')
            ->where(['agreement_id' => $agreement_id])
            ->update($data);
    }

    public function getMarginPayRecord($agreement_id){
        return $this->orm->table('oc_futures_margin_pay_record')
            ->where(['agreement_id' => $agreement_id])
            ->orderBy('id','DESC')
            ->first();
    }

    public function getMarginPayRecordSum($customer_id,$type){
        return $this->orm->table('oc_futures_margin_pay_record')
            ->where([
                'customer_id' => $customer_id,
                'status' => 0,
                'bill_status' => 0,
                'type' => $type
            ])
            ->sum('amount');
    }

    public function getReceiptsOrderByProductId($customer_id, $product_id)
    {
        return $this->orm->table('tb_sys_receipts_order_detail as d')
            ->leftJoin('tb_sys_receipts_order as r', 'r.receive_order_id', 'd.receive_order_id')
            ->where([
                'd.product_id' => $product_id,
                'r.customer_id' => $customer_id,
                'r.status' => ReceiptOrderStatus::TO_BE_RECEIVED,
            ])
            ->sum('expected_qty');
    }

    public function getComboProductInfo($product_id)
    {
        return $this->orm->table('tb_sys_product_set_info')
            ->select('set_product_id','qty')
            ->where([
                'product_id' => $product_id,
            ])
            ->whereNotNull('set_product_id')
            ->get()->toArray();
    }

    /**
     * 获取某商品占用的抵押物：有效提单
     * @param int $product_id
     * @return int
     */
    public function getMarginPayRecordProductSum($product_id)
    {
        $isCombo = $this->isCombo($product_id);
        // 判断是不是combo商品
        if ($isCombo) {
            $childProduct = $this->getComboProductInfo($product_id);
            foreach ($childProduct as $item) {
                $qty[] = ceil($this->getMarginPayRecordProductSum($item->set_product_id) / $item->qty);
            }
            return max($qty);
        }
        return $this->orm->table('oc_futures_margin_pay_record')
            ->where([
                'product_id' => $product_id,
                'type' => 2,
                'status' => 0
            ])
            ->sum('amount');
    }

    /*
     * 是否是combo品
     * */
    public function isCombo($productId)
    {
        return $this->orm->table('oc_product')
            ->where('product_id', $productId)
            ->value('combo_flag');
    }


    public function getSellerBill($seller_id)
    {
        $total = $this->orm->table('tb_seller_bill')
            ->where([
                'seller_id' => $seller_id,
                'settlement_status' => 0,
            ])
            ->orderBy('id','DESC')
            ->value('total');
        if (!$total)
            return 0;
        return $total;
    }

    /**
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     * @deprecated 2020-06-30 16:31:29 打包费按照buyer的类型拆分
     */
    public function getProductById($id)
    {
        return $this->orm->table('oc_product')
            ->select('sku','freight','package_fee','combo_flag','price')
            ->where(['product_id' => $id])
            ->first();
    }

    /**
     * 采用新的打包费
     *
     * @param int $id oc_product.product_id
     * @param int|bool $is_home_pickup 是否为上门取货类型的buyer
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getProductAndNewPackageFeeById($id, $is_home_pickup)
    {
        return $this->orm->table('oc_product as p')
            ->leftJoin('oc_product_fee as pf', function ($query) use ($is_home_pickup) {
                $query->on('pf.product_id', '=', 'p.product_id')
                    ->where('pf.type', '=', $is_home_pickup ? 2 : 1);
            })
            ->select('sku', 'freight', 'combo_flag', 'price')
            ->selectRaw('IFNULL(pf.fee,0) as package_fee')
            ->where('p.product_id', '=', $id)
            ->first();
    }

    public function productAmountRatio($customer_id)
    {
        return $this->orm->table('oc_product_amount_ratio as o')
            ->select(['p.price', 'o.ratio'])
            ->leftJoin('oc_product as p', 'o.product_id', 'p.product_id')
            ->where(['seller_id' => $customer_id])
            ->get();
    }


    /**
     * 统计seller待处理
     * @param int $seller_id
     * @return int
     */
    public function sellerBeProcessedCount($seller_id)
    {
        return $this->orm->table('oc_futures_margin_agreement')
            ->where('seller_id', $seller_id)
            ->whereIn('agreement_status', [1, 2])
            ->where(function (Builder $query){
                $query->where([
                    ['contract_id', '<>', 0],
                    ['is_bid', '=', 0],
                    ['agreement_status', '=', 7]
                ])->orWhere([
                    ['contract_id', '<>', 0],
                    ['is_bid', '=', 1],
                ])->orWhere([
                    ['contract_id', '=', 0]
                ]);
            })
            ->count();
    }


    /**
     * 统计seller交割状态
     * @param int $seller_id
     * @param int $status
     * @return int
     */
    public function sellerBeDeliveredCount($seller_id, $status)
    {
        return $this->orm->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->where([
                'fa.seller_id' => $seller_id,
                'fd.delivery_status' => $status
            ])
            ->where(function (Builder $query){
                $query->where([
                    ['fa.contract_id', '<>', 0],
                    ['fa.is_bid', '=', 0],
                    ['fa.agreement_status', '=', 7]
                ])->orWhere([
                    ['fa.contract_id', '<>', 0],
                    ['fa.is_bid', '=', 1],
                ])->orWhere([
                    ['fa.contract_id', '=', 0]
                ]);
            })
            ->count();
    }

    public function sellerAgreementExpiredCount($seller_id)
    {
        $fromTz = TENSE_TIME_ZONES_NO[getPSTOrPDTFromDate(date('Y-m-d H:i:s'))];
        $toTz = ($this->customer->isUSA() || $this->session->get('country', 'USA') == 'USA') ? $fromTz : COUNTRY_TIME_ZONES_NO[$this->session->get('country')];

        $last_tips_date = date('Y-m-d H:i:s',time() - 23*3600);
        $last_end_date = date('Y-m-d H:i:s',time() - 24*3600);
        $expected_delivery_date_start = date('Y-m-d', strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session)));
        $expected_delivery_date_end = date('Y-m-d', strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session)) + 7*86400);
        $future_margin_start = changeOutPutByZone(date('Y-m-d H:i:s',time() - 7*86400), $this->session);
        $future_margin_end   = changeOutPutByZone(date('Y-m-d H:i:s',time() - 7*86400 + 3600), $this->session);

        $condition = [
            'last_tips_date' => $last_tips_date,
            'last_end_date' => $last_end_date,
            'expected_delivery_date_start' => $expected_delivery_date_start,
            'expected_delivery_date_end' => $expected_delivery_date_end,
            'future_margin_start' => $future_margin_start,
            'future_margin_end' => $future_margin_end,
            'from_tz' => $fromTz,
            'to_tz' => $toTz,
        ];

        return $this->orm->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->where([
                'fa.seller_id' => $seller_id,
            ])
            ->where(function (Builder $query){
                $query->where([
                    ['fa.contract_id', '<>', 0],
                    ['fa.is_bid', '=', 0],
                    ['fa.agreement_status', '=', 7]
                ])->orWhere([
                    ['fa.contract_id', '<>', 0],
                    ['fa.is_bid', '=', 1],
                ])->orWhere([
                    ['fa.contract_id', '=', 0]
                ]);
            })
            ->where(function ($query) use ($condition) {
                $query->where(function ($query) use ($condition) {
                    $query->where('fa.update_time', '>=', $condition['last_end_date'])
                        ->where('fa.update_time', '<=', $condition['last_tips_date'])
                        ->whereIn('fa.agreement_status',[1,2,3]);
                })->orWhere(function ($query) use ($condition) {
                    $query->where('fa.expected_delivery_date', '>=', $condition['expected_delivery_date_start'])
                        ->where('fa.expected_delivery_date', '<', $condition['expected_delivery_date_end'])
                        ->where('fd.delivery_status', 1);
                })->orWhere(function ($query) use ($condition) {
                    $query->where('fd.confirm_delivery_date', '>=', $condition['last_end_date'])
                        ->where('fd.confirm_delivery_date', '<=', $condition['last_tips_date'])
                        ->where([
                            'fd.delivery_status'=> 6,
                            'fd.delivery_type'=> 2,
                        ]);
                })->orWhere(function ($query) use ($condition) {
                    $query->where([
                            'fd.delivery_status'=> 6,
                            'fd.delivery_type'=> 1,
                        ])->whereRaw("CONCAT(DATE(CONVERT_TZ(fd.confirm_delivery_date, ?, ?)), ' 23:59:59') between ? and ?", [
                            $condition['from_tz'],
                            $condition['to_tz'],
                            $condition['future_margin_start'],
                            $condition['future_margin_end']
                        ]);
                });
            })
            ->count();
    }

    public function sellerAgreementApprovalCount($seller_id)
    {
        return $this->orm->table('oc_futures_margin_agreement as fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
            ->leftJoin('oc_futures_agreement_apply as aa',function ($join) use ($seller_id) {
                $join->on('aa.agreement_id', '=', 'fa.id')
                    ->where([
                        ['aa.status', '=', 0],
                        ['aa.apply_type', '=', 3],
                        ['aa.customer_id', '!=', $seller_id],
                    ]);
            })
            ->whereNotNull('aa.id')
            ->where([
                'fa.seller_id' => $seller_id,
            ])
            ->count();
    }

    public function sellerAgreementTotal($seller_id)
    {
        return $this->sellerBeProcessedCount($seller_id) + $this->sellerBeDeliveredCount($seller_id, 1) + $this->sellerBeDeliveredCount($seller_id, 6) + $this->sellerAgreementExpiredCount($seller_id);
    }

    /**
     * 复制期货头款保证金产品
     * @param int $agreement_id
     * @return int
     * @throws Exception
     */

    public function copyFutureMaginProduct($agreement_id)
    {
        $agreement = $this->getAgreementById($agreement_id);
        $data['agreement_no'] = $agreement->agreement_no;
        $data['seller_id'] = $agreement->seller_id;
        $data['product_id'] = $agreement->product_id;
        // 获取所属国家id
        $country_id = $this->orm
            ->table('oc_customer')
            ->where('customer_id', $data['seller_id'])
            ->value('country_id');
        if ($country_id == JAPAN_COUNTRY_ID) {
            $decimal_place = 0;
        } else {
            $decimal_place = 2;
        }
        $data['price_new'] = round($agreement->unit_price * $agreement->buyer_payment_ratio / 100, $decimal_place) * $agreement->num;
      return  $this->copyMarginProduct($data,2);
    }


    /**
     * 复制现货头款保证金产品
     * @param array $agreement
     * @param int $type
     * @return int
     * @throws Exception
     */
    public function copyMarginProduct($agreement, $type): int
    {
        // 获取元商品信息
        $product_info = $this->orm
            ->table('oc_product as p')
            ->select(['p.*', 'pd.name', 'pd.description'])
            ->join('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->where('p.product_id', $agreement['product_id'])
            ->first();

        if (!$product_info) {
            throw new Exception(__FILE__ . " Can not find product relate to product_id:{$agreement['product_id']}.");
        }
        // 获取所属国家id
        $country_id = $this->orm
            ->table('oc_customer')
            ->where('customer_id', $agreement['seller_id'])
            ->value('country_id');

        if ($type == 1) {
            $sku_new = 'M' . str_pad($country_id, 4, "0", STR_PAD_LEFT) . date("md") . substr(time(), -6);
            $product_type = configDB('product_type_dic_margin_deposit');
        } else {
            $sku_new = 'F' . str_pad($country_id, 4, "0", STR_PAD_LEFT) . date("md") . substr(time(), -6);
            $product_type = configDB('product_type_dic_future_margin_deposit');
        }
        $param = [
            'product_id'   => $agreement['product_id'],
            'num'          => 1,
            'price'        => $agreement['price_new'],
            'seller_id'    => $agreement['seller_id'],
            'sku'          => $sku_new,
            'product_name' => "[Agreement ID:{$agreement['agreement_no']}]{$product_info->name}",
            'freight'      => 0,//保证金订金商品的运费和打包费都为0
            'package_fee'  => 0,
            'product_type' => $product_type,
        ];
        // 复制产品
        $this->load->model('catalog/product');
        $product_id_new = $this->model_catalog_product->copyProductMargin( $agreement['product_id'], $param);
        if ($product_id_new === 0) {
            throw new Exception(__FILE__ . " Create product failed. product_id:{$agreement['product_id']}");
        }
        // 更新ctp
        $this->orm->table('oc_customerpartner_to_product')
            ->insert([
                'customer_id' => $agreement['seller_id'],
                'product_id' => $product_id_new,
                'seller_price' => $agreement['price_new'],
                'price' => $agreement['price_new'],
                'currency_code' => '',
                'quantity' => 1,
            ]);
        // 更新product tag
        $orig_tags = $this->orm->table('oc_product_to_tag')->where('product_id', $agreement['product_id'])->get();
        if (!$orig_tags->isEmpty()) {
            $insert_tags = [];
            foreach ($orig_tags as $item) {
                $insert_tags[] = [
                    'is_sync_tag' => ($item->tag_id == 1) ? 0 : $item->is_sync_tag,
                    'tag_id' => $item->tag_id,
                    'product_id' => $product_id_new,
                    'create_user_name' => $agreement['seller_id'],
                    'update_user_name' => $agreement['seller_id'],
                    'create_time' => Carbon::now(),
                    'program_code' => 'MARGIN',
                ];
            }
            $this->orm->table('oc_product_to_tag')->insert($insert_tags);
        }

        return $product_id_new;
    }

    public function getAgreementInfo($agreement_id)
    {
        return $this->orm
            ->table('tb_sys_margin_agreement')
            ->where('agreement_id', $agreement_id)
            ->first()->toArray();
    }

    public function saveMarginAgreement($data)
    {
        return $this->orm->table('tb_sys_margin_agreement')
            ->insertGetId($data);
    }

    public function addFutureMarginProcess($data){

        $this->orm->table('oc_futures_margin_process')
            ->where('agreement_id', $data['agreement_id'])
            ->delete();//oc_futures_margin_process表一条协议应只有一条数据

        return $this->orm->table('oc_futures_margin_process')
            ->insert($data);
    }

    /**
     * 返还seller期货保证金：退回授信额度，修改status状态为完成，释放状态
     * @param int $agreement_id
     * @return int
     */
    public function backFutureMargin($agreement_id)
    {
        $record = $this->getMarginPayRecord($agreement_id);
        // 退回授信额度
        if (1 == $record->type && $record->status == 0) {
            credit::insertCreditBill($record->customer_id, $record->amount, 2);
        }
        $res = $this->updateMarginPayRecord($agreement_id, ['status' => 1]);// 修改status状态为完成，释放状态
        return $res;
    }

    /**
     *  扣押seller期货保证金：退回授信额度，不修改status状态等待seller账单脚本扣除保证金
     * @param int $agreement_id
     * @return bool
     */
    public function withholdFutureMargin($agreement_id)
    {
        $record = $this->getMarginPayRecord($agreement_id);
        // 退回授信额度
        if (1 == $record->type && $record->status == 0) {
            $res = credit::insertCreditBill($record->customer_id, $record->amount, 2);
        }
        // 有效提单判断
        if (2 == $record->type && $record->status == 0) {
            $res = $this->updateMarginPayRecord($agreement_id, ['status' => 1]);
        } else {
            $res = $this->updateMarginPayRecord($agreement_id, ['update_time' => date('Y=m-d H:i:s')]);
        }
        return $res;
    }

    public function isCollectionFromDomicile($buyer_id)
    {
        $customer_group_id = $this->orm
            ->table('oc_customer')
            ->where('customer_id', $buyer_id)
            ->value('customer_group_id');
        if (in_array($customer_group_id, COLLECTION_FROM_DOMICILE)) {
            return true;
        }
        return false;
    }

    /**
     * 期货转现货
     * @param int $agreement_id oc_futures_margin_agreement.id
     * @param int $product_id
     * @return array
     */
    public function futures2margin($agreement_id,$product_id){
        $fmSql = "select fd.id from oc_futures_margin_delivery as fd
                              LEFT JOIN tb_sys_margin_process as mp ON fd.margin_agreement_id = mp.margin_id
                              WHERE fd.margin_agreement_id={$agreement_id} AND mp.advance_product_id={$product_id}
                              AND fd.delivery_type != 1 AND fd.delivery_status = 6";
        $futures2margin = $this->db->query($fmSql)->row;
        return $futures2margin;
    }

    /**
     * @param array $contractIds
     * @param array $agreementStatus
     * @return array
     */
    public function agreementNumByContractIdsAndStatus(array $contractIds, $agreementStatus = [])
    {
        return $this->orm->table(DB_PREFIX . 'futures_margin_agreement')
            ->whereIn('contract_id', $contractIds)
            ->when(!empty($agreementStatus), function ($q) use ($agreementStatus) {
                $q->whereIn('agreement_status', $agreementStatus);
            })
            ->groupBy(['contract_id'])
            ->selectRaw("sum(num) as contract_num, contract_id")
            ->pluck('contract_num', 'contract_id')
            ->toArray();
    }

    /**
     * @param array $contractIds
     * @param array $agreementStatus
     * @return array
     */
    public function agreementNoByContractIdsAndStatus(array $contractIds, $agreementStatus = [])
    {
        return $this->orm->table(DB_PREFIX . 'futures_margin_agreement')
            ->whereIn('contract_id', $contractIds)
            ->when(!empty($agreementStatus), function ($q) use ($agreementStatus) {
                $q->whereIn('agreement_status', $agreementStatus);
            })
            ->pluck('agreement_no')
            ->toArray();
    }

    /**
     * [getPurchaseOrderInfoByProductId description] 获取期货头款采购订单的详情
     * @param int $product_id
     * @param int $country_id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     * @throws Exception
     */
    public function getPurchaseOrderInfoByProductId($product_id,$country_id)
    {
        $this->load->model('tool/image');
        $this->load->model('catalog/product');
        $ret = $this->orm->table(DB_PREFIX.'order_product as op')
            ->leftJoin(DB_PREFIX.'order as oo','oo.order_id','=','op.order_id')
            ->leftJoin(DB_PREFIX.'customer as c','c.customer_id','=','oo.customer_id')
            ->leftJoin(DB_PREFIX.'order_status as os','oo.order_status_id','=','os.order_status_id')
            ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','op.product_id')
            ->leftJoin(DB_PREFIX.'product_description AS pd', 'pd.product_id', '=', 'p.product_id')
            ->select(
                'os.name as status_name',
                'oo.order_id',
                'c.nickname',
                'c.user_number',
                'c.customer_group_id',
                'oo.date_modified',
                'p.image',
                'p.sku',
                'p.mpn',
                'pd.name AS product_name',
                'c.customer_id',
                'op.*'
            )
            ->where('op.product_id',$product_id)
            ->whereIn('oo.order_status_id',[OcOrderStatus::COMPLETED,OcOrderStatus::CHARGEBACK])
            ->first();
        if($ret){
            $currency_code = $this->session->get('currency');
            if (in_array($ret->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $ret->is_homepick = true;
                $ret->img_tips = 'Pick up Buyer';
            }else{
                $ret->is_homepick = false;
                $ret->img_tips = 'Dropshiping Buyer';
            }
            $ret->image_show = $this->model_tool_image->resize($ret->image, 40, 40);
            $ret->image_big = $this->model_tool_image->resize($ret->image, 150, 150);
            $ret->tag = $this->model_catalog_product->getProductTagHtmlForThumb($ret->product_id);
            $service_fee_total = $ret->service_fee_per* $ret->quantity;
            $freight = $ret->freight_per +  $ret->package_fee;
            $ret->total_price = sprintf('%.2f', ($ret->price + $freight) * $ret->quantity + $service_fee_total);
            $is_japan = JAPAN_COUNTRY_ID == $country_id ? 1 : 0;
            if($is_japan){
                $ret->total_price =  $this->currency->format(intval($ret->total_price), $currency_code);
            }else{
                $ret->total_price =  $this->currency->format($ret->total_price, $currency_code);
            }
            $ret->ex_vat = VATToolTipWidget::widget(['customer' => Customer::query()->find($ret->customer_id), 'is_show_vat' => true])->render();
        }
        return $ret;

    }

    /**
     * Buyer期货协议详情页-交割Tab-头部基本信息
     * @param int $agreement_id
     * @return array
     */
    public function getBuyerFuturesPurchaseRecordDetailMainById($agreement_id)
    {
        $country_id     = $this->customer->getCountryId();
        $ret            = [];
        $agreement_info = $this->orm->table($this->table . ' as a')
            ->select(
                'a.num',
                'd.confirm_delivery_date',
                'ma.effect_time',
                'ma.expire_time',
                'ma.agreement_id',
                'ma.status',
                'a.version',
                'mp.process_status',
                'mp.advance_product_id',
                'mp.advance_order_id',
                'd.delivery_type',
                'd.delivery_status',
                'd.margin_apply_num',
                'd.margin_unit_price',
                'a.product_id',
                'mas.name as margin_status_name',
                'mas.color as margin_status_color',
                'ma.id as m_id',
                'a.seller_id',
                'a.buyer_id'
            )
            ->addSelect(['bc.customer_group_id'])
            ->leftJoin('oc_futures_margin_delivery as d', 'a.id', 'd.agreement_id')
            ->leftJoin('oc_customer as bc', 'a.buyer_id', '=', 'bc.customer_id')
            ->leftJoin('tb_sys_margin_agreement as ma', 'ma.id', '=', 'd.margin_agreement_id')
            ->leftJoin('tb_sys_margin_process as mp', 'mp.margin_id', '=', 'd.margin_agreement_id')
            ->leftJoin('tb_sys_margin_agreement_status as mas', 'mas.margin_agreement_status_id', '=', 'ma.status')
            ->where(['a.id' => $agreement_id])
            ->first();
        // 尾款交割
        if($agreement_info) {
            $country = session('country', 'USA');
            $fromZone = CountryHelper::getTimezoneByCode('USA');
            $toZone = CountryHelper::getTimezoneByCode($country);
            $is_japan = JAPAN_COUNTRY_ID == $country_id ? 1 : 0;
            $format   = $is_japan ? '%d':'%.2f';
            if ($agreement_info->delivery_type == self::DELIVERY_TYPE_FUTURES) {
                // 交付时间是否剩余七天
                // 协议数量，未完成数量 ,已完成数量，未完成数量

                // 需要判断 尾款支付情况，以及交付倒计时
                // todo 根据支付状态也会影响展示的
                if ($agreement_info->delivery_status == FuturesMarginDeliveryStatus::COMPLETED) {
                    $ret['paid_ret']   = 'Direct Settlement Completed';
                    $ret['paid_color'] = '#65B017';
                } else {
                    if ($agreement_info->version == FuturesVersion::VERSION) {
                        $ret['expire_time'] = Carbon::parse($agreement_info->confirm_delivery_date)->addDays(1)->toDateTimeString();
                    } else {
                        $tmp_expire_time = date('Y-m-d 23:59:59', strtotime($agreement_info->confirm_delivery_date . '+7 day'));
                        $ret['expire_time'] = dateFormat($fromZone, $toZone, $tmp_expire_time, 'Y-m-d H:i:s');
                    }
                    $days = $this->getConfirmLeftDay($agreement_info);
                    $ret['left_time_secs'] = ($ret['expire_time']) ? strtotime($ret['expire_time']) - time() : -1;
                    if ($agreement_info->version == FuturesVersion::VERSION) {
                        if ($days>0) {
                            $ret['paid_ret']   = 'Direct Settlement Countdown';
                            $ret['paid_color'] = '#8BC7F8';
                        } else {
                            $ret['paid_ret']   = 'Direct Settlement Timed Out';
                            $ret['paid_color'] = '#FF414D';
                        }
                    } else {
                        if ($days <= self::FUTURES_FINISH_DAY && $days > 0) {
                            $ret['paid_ret']   = 'Direct Settlement Countdown';
                            $ret['paid_color'] = '#8BC7F8';
                        } elseif($days >= self::FUTURES_FINISH_DAY) {
                            $ret['paid_ret']   = '';
                            $ret['paid_color'] = 'white';
                        } else {
                            $ret['paid_ret']   = 'Direct Settlement Timed Out';
                            $ret['paid_color'] = '#FF414D';
                        }
                    }
                }
                $ret['agreement_num'] = $agreement_info->num;
                // 获取当前的已经采购的数量减去当前rma的数量
                $ret['purchase_num'] = $this->getAgreementCurrentPurchaseQuantity($agreement_id);
                $ret['left_num']     = $ret['agreement_num'] - $ret['purchase_num'];
            } else {
                // 转现货保证金
                $ret['agreement_id']        = $agreement_info->agreement_id;
                $ret['effect_time']         = $agreement_info->effect_time;
                $ret['expire_time']         = $agreement_info->expire_time;
                $ret['margin_status_name']  = $agreement_info->margin_status_name;
                $ret['margin_status_color'] = $agreement_info->margin_status_color;
                $ret['m_id']                = $agreement_info->m_id;
                $ret['margin_apply_num']        = $agreement_info->margin_apply_num;//转现货保证金申请数量
                $ret['margin_unit_price_show']  = sprintf($format, $agreement_info->margin_unit_price);//转现货保证金单价
                $ret['margin_order_total_show'] = sprintf($format, $agreement_info->margin_unit_price * $agreement_info->margin_apply_num);
                $ret['order_info']          = $this->getMarginAdvanceInfo($agreement_info->advance_product_id, $country_id);//返回一条数据
            }
            $ret['delivery_type'] = $agreement_info->delivery_type;
        }
        return $ret;
    }


    /**
     * Buyer期货协议详情页-交割Tab-订单列表
     * @param int $agreement_id
     * @param int $page
     * @param int $page_limit
     * @return array
     */
    public function getBuyerFuturesPurchaseRecordDetailListById($agreement_id, $page=1, $page_limit = 15)
    {
        $country_id                  = $this->customer->getCountryId();
        $is_collection_from_domicile = $this->customer->isCollectionFromDomicile();

        $agreement_info = $this->orm->table($this->table . ' as a')
            ->select(
                'a.num',
                'a.product_id',
                'a.seller_id',
                'a.buyer_id'
            )
            ->where(['a.id' => $agreement_id])
            ->first();
        // 尾款交割

        $condition['product_id']   = $agreement_info->product_id;
        $condition['agreement_id'] = $agreement_id;
        $condition['page']         = $page;
        $condition['page_limit']   = $page_limit;
        return $this->getPurchaseOrderByCondition($condition, $country_id, $is_collection_from_domicile);
    }


    /**
     * Seller期货协议详情页-交割Tab-头部基本信息
     * 参考 getFuturesPurchaseRecordDetailById()
     * @param int $agreement_id
     * @return array
     */
    public function getSellerFuturesPurchaseRecordDetailMainById($agreement_id)
    {
        $country_id     = $this->customer->getCountryId();
        $ret            = [];
        $agreement_info = $this->orm->table($this->table . ' as a')
            ->select(
                'a.num',
                'd.confirm_delivery_date',
                'd.margin_agreement_id',
                'ma.effect_time',
                'ma.expire_time',
                'ma.agreement_id',
                'ma.status',
                'a.version',
                'mp.process_status',
                'mp.advance_product_id',
                'mp.advance_order_id',
                'd.delivery_type',
                'd.delivery_status',
                'd.margin_apply_num',
                'd.margin_unit_price',
                'a.product_id',
                'mas.name as margin_status_name',
                'mas.color as margin_status_color',
                'ma.id as m_id',
                'a.seller_id',
                'a.buyer_id'
            )
            ->addSelect(['bc.customer_group_id'])
            ->leftJoin('oc_futures_margin_delivery as d', 'a.id', 'd.agreement_id')
            ->leftJoin('oc_customer as bc', 'a.buyer_id', '=', 'bc.customer_id')
            ->leftJoin('tb_sys_margin_agreement as ma', 'ma.id', '=', 'd.margin_agreement_id')
            ->leftJoin('tb_sys_margin_process as mp', 'mp.margin_id', '=', 'd.margin_agreement_id')
            ->leftJoin('tb_sys_margin_agreement_status as mas', 'mas.margin_agreement_status_id', '=', 'ma.status')
            ->where(['a.id' => $agreement_id])
            ->first();
        // 尾款交割
        if($agreement_info) {
            $country = session('country', 'USA');
            $fromZone = CountryHelper::getTimezoneByCode('USA');
            $toZone = CountryHelper::getTimezoneByCode($country);
            $is_japan = JAPAN_COUNTRY_ID == $country_id ? 1 : 0;
            $format   = $is_japan ? '%d' : '%.2f';
            if ($agreement_info->delivery_type == self::DELIVERY_TYPE_FUTURES) {
                // 交付时间是否剩余七天
                // 协议数量，未完成数量 ,已完成数量，未完成数量

                // 需要判断 尾款支付情况，以及交付倒计时
                // todo 根据支付状态也会影响展示的
                if ($agreement_info->delivery_status == FuturesMarginDeliveryStatus::COMPLETED) {
                    $ret['paid_ret']   = 'Direct Settlement Completed';
                    $ret['paid_color'] = '#65B017';
                } else {
                    if ($agreement_info->version == FuturesVersion::VERSION) {
                        $ret['expire_time'] = Carbon::parse($agreement_info->confirm_delivery_date)->addDays(1)->toDateTimeString();
                    } else {//针对期货协议旧版本(第三期之前)seller审核交割日期的过期时间为Buyer7天后最后一秒
                        $tmp_expire_time = date('Y-m-d 23:59:59', strtotime($agreement_info->confirm_delivery_date . '+7 day'));
                        $ret['expire_time'] = dateFormat($fromZone, $toZone, $tmp_expire_time, 'Y-m-d H:i:s');
                    }
                    $days = $this->getConfirmLeftDay($agreement_info);
                    $ret['left_time_secs'] = ($ret['expire_time']) ? strtotime($ret['expire_time']) - time() : -1;
                    if ($agreement_info->version == FuturesVersion::VERSION) {
                        if ($days>0) {
                            $ret['paid_ret']   = 'Direct Settlement Countdown';
                            $ret['paid_color'] = '#8BC7F8';
                        } else {
                            $ret['paid_ret']   = 'Direct Settlement Timed Out';
                            $ret['paid_color'] = '#FF414D';
                        }
                    } else {
                        if ($days <= self::FUTURES_FINISH_DAY && $days > 0) {
                            $ret['paid_ret']   = 'Direct Settlement Countdown';
                            $ret['paid_color'] = '#8BC7F8';
                        } elseif ($days >= self::FUTURES_FINISH_DAY) {
                            $ret['paid_ret']   = '';
                            $ret['paid_color'] = 'white';
                        } else {
                            $ret['paid_ret']   = 'Direct Settlement Timed Out';
                            $ret['paid_color'] = '#FF414D';
                        }
                    }
                }
                $ret['agreement_num'] = $agreement_info->num;
                // 获取当前的已经采购的数量减去当前rma的数量
                $ret['purchase_num'] = $this->getAgreementCurrentPurchaseQuantity($agreement_id);
                $ret['left_num']     = $ret['agreement_num'] - $ret['purchase_num'];
            } else {
                // 转现货保证金
                $ret['agreement_id']            = $agreement_info->agreement_id;
                $ret['effect_time']             = $agreement_info->effect_time;
                $ret['expire_time']             = $agreement_info->expire_time;
                $ret['margin_status_name']      = $agreement_info->margin_status_name;
                $ret['margin_status_color']     = $agreement_info->margin_status_color;
                $ret['m_id']                    = $agreement_info->m_id;
                $ret['margin_apply_num']        = $agreement_info->margin_apply_num;//转现货保证金申请数量
                $ret['margin_unit_price_show']  = sprintf($format, $agreement_info->margin_unit_price);//转现货保证金单价
                $ret['margin_order_total_show'] = sprintf($format, $agreement_info->margin_unit_price * $agreement_info->margin_apply_num);
                $ret['order_info']              = $this->getMarginAdvanceInfo($agreement_info->advance_product_id, $country_id);//返回一条数据
            }
            $ret['delivery_type'] = $agreement_info->delivery_type;
        }
        return $ret;
    }

    public function getMarginAdvanceInfo($advance_product_id, $country_id)
    {
        $this->load->model('tool/image');
        $this->load->model('catalog/product');
        $ret = $this->orm->table(DB_PREFIX.'product as p')
            ->leftJoin(DB_PREFIX.'product_description AS pd', 'pd.product_id', '=', 'p.product_id')
            ->select(
                'p.product_id',
                'p.image',
                'p.sku',
                'p.mpn',
                'pd.name AS product_name',
                'p.price',
                'p.product_id'
            )
            ->where('p.product_id',$advance_product_id)
            ->first();
        if($ret){
            $currency_code = $this->session->get('currency');
            $ret->image_show = $this->model_tool_image->resize($ret->image, 40, 40);
            $ret->image_big = $this->model_tool_image->resize($ret->image, 150, 150);
            $ret->tag = $this->model_catalog_product->getProductTagHtmlForThumb($ret->product_id);

            $ret->total_price = sprintf('%.2f', $ret->price);
            $is_japan = JAPAN_COUNTRY_ID == $country_id ? 1 : 0;
            if($is_japan){
                $ret->total_price_show =  $this->currency->format(intval($ret->total_price), $currency_code);
            }else{
                $ret->total_price_show =  $this->currency->format($ret->total_price, $currency_code);
            }
        }
        return $ret;
    }


    /**
     * Seller期货协议详情页-交割Tab-订单列表
     * @param int $agreement_id
     * @param int $page
     * @param int $page_limit
     * @return array
     */
    public function getSellerFuturesPurchaseRecordDetailListById($agreement_id, $page=1, $page_limit = 15)
    {
        $country_id                  = $this->customer->getCountryId();
        $is_collection_from_domicile = $this->customer->isCollectionFromDomicile();

        $agreement_info = $this->orm->table($this->table . ' as a')
            ->select(
                'a.num',
                'd.confirm_delivery_date',
                //'ma.effect_time',
                //'ma.expire_time',
                //'ma.agreement_id',
                //'ma.status',
                //'mp.process_status',
                //'mp.advance_product_id',
                //'mp.advance_order_id',
                'd.delivery_type',
                'd.delivery_status',
                'a.product_id',
                //'mas.name as margin_status_name',
                //'mas.color as margin_status_color',
                //'ma.id as m_id',
                'a.seller_id',
                'a.buyer_id',
                'bc.customer_group_id'
            )
            ->leftJoin('oc_futures_margin_delivery as d', 'a.id', 'd.agreement_id')
            ->leftJoin('oc_customer as bc', 'a.buyer_id', '=', 'bc.customer_id')
            //->leftJoin('tb_sys_margin_agreement as ma', 'ma.id', '=', 'd.margin_agreement_id')
            //->leftJoin('tb_sys_margin_process as mp', 'mp.margin_id', '=', 'd.margin_agreement_id')
            //->leftJoin('tb_sys_margin_agreement_status as mas', 'mas.margin_agreement_status_id', '=', 'ma.status')
            ->where(['a.id' => $agreement_id])
            ->first();
        // 尾款交割
        $condition['product_id']   = $agreement_info->product_id;
        $condition['agreement_id'] = $agreement_id;
        $condition['page']         = $page;
        $condition['page_limit']   = $page_limit;
        return $this->getPurchaseOrderByCondition($condition, $country_id, $is_collection_from_domicile);
    }


    /**
     * [getFuturesPurchaseRecordDetailById description]
     * @param int $agreement_id
     * @param int $country_id
     * @param bool $is_collection_from_domicile
     * @return array
     */
    public function getFuturesPurchaseRecordDetailById($agreement_id,$country_id,$is_collection_from_domicile)
    {
        // 获取协议信息
        return $this->getAgreementDeliveryInfoById($agreement_id,$country_id,$is_collection_from_domicile);
    }

    public function getAgreementDeliveryInfoById($agreement_id,$country_id,$is_collection_from_domicile, $page=1, $page_limit = 15)
    {
        $ret = [];
        $agreement_info = $this->orm->table($this->table.' as a')
                            ->select(
                                'a.num',
                                'd.confirm_delivery_date',
                                'ma.effect_time',
                                'ma.expire_time',
                                'ma.agreement_id',
                                'ma.status',
                                'a.version',
                                'mp.process_status',
                                'mp.advance_product_id',
                                'mp.advance_order_id',
                                'd.delivery_type',
                                'd.delivery_status',
                                'a.product_id',
                                'mas.name as margin_status_name',
                                'mas.color as margin_status_color',
                                'ma.id as m_id',
                                'a.seller_id',
                                'a.buyer_id'
                            )
                            ->addSelect(['bc.customer_group_id'])
                            ->leftJoin('oc_futures_margin_delivery as d','a.id','d.agreement_id')
                            ->leftJoin('oc_customer as bc','a.buyer_id' ,'=', 'bc.customer_id')
                            ->leftJoin('tb_sys_margin_agreement as ma','ma.id' ,'=', 'd.margin_agreement_id')
                            ->leftJoin('tb_sys_margin_process as mp','mp.margin_id' ,'=', 'd.margin_agreement_id')
                            ->leftJoin('tb_sys_margin_agreement_status as mas','mas.margin_agreement_status_id' ,'=', 'ma.status')
                            ->where(['a.id' => $agreement_id])
                            ->first();
        // 尾款交割
        if($agreement_info->delivery_type == self::DELIVERY_TYPE_FUTURES){
            // 交付时间是否剩余七天
            // 协议数量，未完成数量 ,已完成数量，未完成数量
            // 列表
            if (in_array($agreement_info->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $ret['is_homepick'] = true;
            }else{
                $ret['is_homepick'] = false;
            }
            // 需要判断 尾款支付情况，以及交付倒计时
            // todo 根据支付状态也会影响展示的
            if($agreement_info->delivery_status == 8){
                $ret['paid_ret'] = 'Direct Settlement Completed';
                $ret['paid_color'] = '#65B017';
            }else{
                $days = $this->getConfirmLeftDay($agreement_info);
                if($days <= self::FUTURES_FINISH_DAY && $days > 0){
                    $ret['paid_ret'] = 'Direct Settlement Timed Out: ' .$days. 'Days Left';
                    $ret['paid_color'] = '#8BC7F8';
                }else{
                    $ret['paid_ret'] = 'Direct Settlement Timed Out';
                    $ret['paid_color'] = '#FF414D';
                }
            }
            $condition['product_id'] = $agreement_info->product_id;
            $condition['agreement_id'] = $agreement_id;
            $condition['page'] = $page;
            $condition['page_limit'] = $page_limit;
            $ret['order_info'] = $this->getPurchaseOrderByCondition($condition,$country_id,$is_collection_from_domicile);
            $ret['agreement_num'] = $agreement_info->num;
            // 获取当前的已经采购的数量减去当前rma的数量
            $ret['purchase_num'] = $this->getAgreementCurrentPurchaseQuantity($agreement_id);
            $ret['left_num'] = $ret['agreement_num'] - $ret['purchase_num'];
        }else{
            // 转现货保证金
            $ret['agreement_id'] = $agreement_info->agreement_id;
            $ret['effect_time'] = $agreement_info->effect_time;
            $ret['expire_time'] = $agreement_info->expire_time;
            $ret['margin_status_name'] = $agreement_info->margin_status_name;
            $ret['margin_status_color'] = $agreement_info->margin_status_color;
            $ret['m_id'] = $agreement_info->m_id;
            $ret['order_info'] = $this->getPurchaseOrderInfoByProductId($agreement_info->advance_product_id ,$country_id);
        }
        $ret['delivery_type'] = $agreement_info->delivery_type;
        return $ret;
    }

    /**
     * [getAgreementCurrentPurchaseQuantity description] 之前定义的方法有显示的问题，直接使用lock表里的数据
     * @param int $agreement_id
     * @return int
     */
    public function getAgreementCurrentPurchaseQuantity($agreement_id)
    {
        $ret = $this->orm->table('oc_product_lock')
            ->where([
                'type_id'=> 3,
                'agreement_id'=>$agreement_id,
            ])
            ->orderBy('id','desc')
            ->first();
        if (!$ret) {
            goto End;
        }
        $set_qty = intval($ret->set_qty) < 1 ? 1 : intval($ret->set_qty);
        //查找有没有一条log记录 yzc task work 4
        $qtyTimeout = $this->orm->table('oc_product_lock_log')->where([
            'change_type'     => 4,
            'transaction_id'  => $agreement_id,
            'product_lock_id'  => $ret->id,
            'create_user_name'=>'yzc task work',
        ])->value('qty');

        $qtyInterrupt = $this->orm->table('oc_product_lock_log')->where([
            'change_type'     => 5,
            'product_lock_id'  => $ret->id,
            'transaction_id'  => $agreement_id,
        ])->value('qty');

        if(isset($ret->origin_qty)){
            if($qtyTimeout || $qtyInterrupt){
                return intval(bcdiv(($ret->origin_qty + $qtyTimeout + $qtyInterrupt), $set_qty));
            }
            return intval(bcdiv(($ret->origin_qty - $ret->qty), $set_qty));
        }
        End:
        return  0 ;
    }


    public function getPurchaseOrderByCondition($condition,$country_id,$is_collection_from_domicile)
    {
        $order_info = $this->orm->table(DB_PREFIX.'order_product as op')
                        ->leftJoin(DB_PREFIX.'order as oo','oo.order_id','=','op.order_id')
                        ->leftJoin(DB_PREFIX.'yzc_rma_order_product as rop','rop.order_product_id','=','op.order_product_id')
                        ->leftJoin(DB_PREFIX.'yzc_rma_order as ro','ro.id','=','rop.rma_id')
                        ->leftJoin(DB_PREFIX.'customer AS c', 'oo.customer_id', '=', 'c.customer_id')
                        ->where([
                            'op.product_id'=>$condition['product_id'],
                            'op.agreement_id'=>$condition['agreement_id'],
                            'op.type_id'=>3,
                            'oo.order_status_id'=>OcOrderStatus::COMPLETED,
                        ])
                        ->selectRaw('op.*,oo.delivery_type,oo.date_added,oo.date_modified,group_concat(ro.id) as r_id,group_concat(ro.rma_order_id) as r_order_id,c.customer_group_id')
                        ->groupBy('op.order_product_id')
                        ->forPage($condition['page'],$condition['page_limit'])
                        ->get()
                        ->map(function ($v){
                            return (array)$v;
                        })
                        ->toArray();
        $is_europe = in_array($country_id,EUROPE_COUNTRY_ID);
        $is_japan = JAPAN_COUNTRY_ID == $country_id ? 1 : 0;
        foreach($order_info as $key => $value){
            $order_info[$key]['r_id_list'] = explode(',',$value['r_id']);
            $order_info[$key]['r_order_id_list'] = explode(',',$value['r_order_id']);
            if($is_collection_from_domicile){
                $order_info[$key]['freight_per'] = 0;
            }
            if ($is_europe) {
                $service_fee_per = $value['service_fee_per'];
                //获取discount后的 真正的service fee
                $service_fee_total = $service_fee_per * $value['quantity'];
                $service_fee_total_pre = $service_fee_per ;
            } else {
                $service_fee_total = 0;
                $service_fee_total_pre = 0;
            }
            $format = $is_japan ? '%d':'%.2f';
            $order_info[$key]['service_fee']  = $service_fee_total_pre = sprintf($format, $service_fee_total_pre);
            $order_info[$key]['service_fee_show'] = $this->currency->formatCurrencyPrice($service_fee_total_pre, $this->session->data['currency']);
            $freight = ($order_info[$key]['freight_per'] + $order_info[$key]['package_fee']);
            $order_info[$key]['package_fee_tips_show'] = $this->currency->formatCurrencyPrice(sprintf($format, $order_info[$key]['package_fee']), $this->session->data['currency']);
            $order_info[$key]['freight_tips_show'] = $this->currency->formatCurrencyPrice(sprintf($format,$order_info[$key]['freight_per']), $this->session->data['currency']);
            $order_info[$key]['freight_show'] = $this->currency->formatCurrencyPrice(sprintf($format,$freight), $this->session->data['currency']);
            $order_info[$key]['unit_price_show'] = $this->currency->formatCurrencyPrice(sprintf($format,$order_info[$key]['price']), $this->session->data['currency']);
            $order_info[$key]['total_price'] = sprintf($format, ($order_info[$key]['price'] + $freight) * $order_info[$key]['quantity'] + $service_fee_total);
            $order_info[$key]['total_price_show'] = $this->currency->formatCurrencyPrice($order_info[$key]['total_price'], $this->session->data['currency']);
            $order_info[$key]['discount_total_price_show'] = $this->currency->formatCurrencyPrice($order_info[$key]['total_price'] - $order_info[$key]['coupon_amount'] - $order_info[$key]['campaign_amount'], $this->session->data['currency']);
            $order_info[$key]['campaign_discount_total_price_show'] = $this->currency->formatCurrencyPrice($order_info[$key]['total_price'] - $order_info[$key]['campaign_amount'], $this->session->data['currency']);
            $order_info[$key]['poundage'] = sprintf($format, $order_info[$key]['poundage'] / $order_info[$key]['quantity']);
            $order_info[$key]['poundage_show'] = $this->currency->formatCurrencyPrice($order_info[$key]['poundage'], $this->session->data['currency']);
            $order_info[$key]['isCollectionFromDomicile'] = in_array($value['customer_group_id'], COLLECTION_FROM_DOMICILE);
        }
        return $order_info;
    }

    /**
     * @param int $agreement_id
     * @param int $country_id
     * @return \Illuminate\Database\Eloquent\Model|Builder|object|null
     * @throws Exception
     */
    public function getFutureTransactionModeDetail($agreement_id,$country_id)
    {
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $agreement_info = $this->getAgreementById($agreement_id);
        if ($agreement_info) {
            $agreement_info->delivery_type_name = self::DELIVERY_TYPES[$agreement_info->delivery_type];
            $agreement_info->status_name  = self::DELIVERY_STATUS[$agreement_info->delivery_status]['name'];
            $agreement_info->status_color = self::DELIVERY_STATUS[$agreement_info->delivery_status]['color'];
            $this->load->model('catalog/product');
            $agreement_info->tag = $this->model_catalog_product->getProductTagHtmlForThumb($agreement_info->product_id);
            $is_japan = JAPAN_COUNTRY_ID == $country_id ? 1 : 0;
            if ($is_japan){
                $format = '%d';
                $precision = 0;
            }else{
                $format = '%.2f';
                $precision = 2;
            }
            $current_timestamp = time();
            $minute = $this->getLeftDay($agreement_info,null,1);
            if($minute) {
                $agreement_info->time_left = $minute;
                $agreement_info->time_tips = true;
            }else{
                $agreement_info->time_tips = false;
            }
            //需要在X个自然日内支付完成
            if($agreement_info->delivery_date){
                $agreement_info->days = 0;
            }else{
                $agreement_info->days = $this->getLeftDay($agreement_info);
            }
            $agreement_info->unit_deposit = sprintf($format, $agreement_info->last_unit_price);//期货尾款单价
            // 转期货和转保证金不同
            if($agreement_info->delivery_type == self::DELIVERY_TYPE_FUTURES){
                // 处理一下单价
                // 处理一下尾款总金额
                $agreement_info->unit_last = sprintf($format,$agreement_info->last_unit_price);//期货尾款单价
                $agreement_info->last_price_amount = sprintf($format,$agreement_info->unit_last * $agreement_info->num);
                $agreement_info->unit_last_show = $this->currency->formatCurrencyPrice($agreement_info->unit_last, $this->session->data['currency']);
                $agreement_info->last_price_amount_show = $this->currency->formatCurrencyPrice($agreement_info->last_price_amount, $this->session->data['currency']);
            }else{
                $margin_apply_num =  $agreement_info->margin_apply_num;
                $margin_unit_price = $agreement_info->margin_unit_price;
                $agreement_info->margin_last_price_show = $this->currency->formatCurrencyPrice(round($agreement_info->margin_last_price,$precision), $this->session->data['currency']);
                $agreement_info->deposit_amount_show = $this->currency->formatCurrencyPrice(round($agreement_info->margin_deposit_amount,$precision), $this->session->data['currency']);
                $agreement_info->default_days = 30;
                $agreement_info->default_days_show = $agreement_info->default_days.' Days';
            }
            $agreement_info->has_apply = $this->getCustomerSubmitApply([$agreement_info->seller_id,$agreement_info->buyer_id],$agreement_info->id,[0]);

            if ($agreement_info->delivery_date) {
                $agreement_info->show_delivery_date = dateFormat($fromZone, $toZone, $agreement_info->delivery_date, 'Y-m-d');
            } elseif ($agreement_info->expected_delivery_date) {
                $agreement_info->show_delivery_date = $agreement_info->expected_delivery_date;
            } else {
                $agreement_info->show_delivery_date = 'N/A';
            }
            $delivery_date = $agreement_info->delivery_date ? $agreement_info->delivery_date:$agreement_info->expected_delivery_date;

            // 拒绝交付后、交付超时可提交申诉，可提交申诉的有效期为7个自然日，7个自然日后隐藏列表页的申诉按钮和详情页的申诉入口（列表页也同样的限制）
            if(($current_timestamp - strtotime($delivery_date.' '.$toZone) - 7*86400) > 0){
                $agreement_info->complain_time_out = true;
            }else{
                $agreement_info->complain_time_out = false;
            }
        }

        return $agreement_info;
    }

    /**
     * [addFuturesAgreementCommunication description]
     * @param int $agreement_id
     * @param $type
     * @param $condition // from  to  status  country_id
     * @throws Exception
     */
    public function addFuturesAgreementCommunication($agreement_id,$type,$condition){
        $ret = $this->setTemplateOfCommunication(...func_get_args());
        $this->load->model('message/message');
        $this->model_message_message->addSystemMessageToBuyer('bid_futures',$ret['subject'],$ret['message'],$ret['received_id']);
    }

    /**
     * [setTemplateOfCommunication description]
     * @param int $agreement_id
     * @param $type
     * @param $condition // from  to  status country_id
     * @return mixed
     */
    private function setTemplateOfCommunication($agreement_id,$type,$condition)
    {
        $subject = '';
        $message = '';
        $received_id = $condition['to'];
        if ($condition['country_id'] == JAPAN_COUNTRY_ID){
            $format = '%d';
            $precision = 0;
        }else{
            $format = '%.2f';
            $precision = 2;
        }
        $agreement = $this->getAgreementById($agreement_id);
        $agreement->screenname = $this->orm->table(DB_PREFIX.'customerpartner_to_customer')
            ->where('customer_id',$agreement->seller_id)->value('screenname');
        $message_header = '<table  border="0" cellspacing="0" cellpadding="0">';
        $message_buyer_agreement = '<tr>
                                    <th align="left">Future Goods Agreement ID:&nbsp;</th>
                                    <td style="max-width: 600px">
                                        <a target="_blank"
                                             href="' . $this->url->link('account/product_quotes/futures/buyerFuturesBidDetail', 'id=' . $agreement_id ). '">'
                                        .$agreement->agreement_no.
                                        '</a>
                                    </td></tr>';
        $message_seller_agreement = '<tr>
                                <th align="left">Future Goods Agreement ID:&nbsp;</th>
                                <td style="max-width: 600px">
                                    <a target="_blank" href="' . $this->url->link('account/product_quotes/futures/sellerFuturesBidDetail', 'id=' . $agreement_id ). '">'
                                    .$agreement->agreement_no.
                                    '</a>
                                </td></tr>';
        $message_seller_contract = '<tr>
                                <th align="left">Future Goods Contract ID:&nbsp;</th>
                                <td style="max-width: 600px">
                                     <a target="_blank" href="' . $this->url->to(['account/customerpartner/future/contract/tab', 'id' => $agreement->contract_id ]). '">'
                                    .$agreement->contract_no.
                                    '</a>
                                </td></tr>';
        $message_store = '<tr>
                            <th align="left">Store:&nbsp;</th>
                            <td style="max-width: 600px">
                                 <a target="_blank"
                                         href="' . $this->url->link('customerpartner/profile', 'id=' .$agreement->seller_id ). '">'
                                .$agreement->screenname.
                                '</a>
                             </td></tr>';
        $message_buyer_name = '<tr>
                                <th align="left">Name:&nbsp;</th>
                                <td style="max-width: 600px">
                                    '.$agreement->nickname.'('.$agreement->user_number.')
                                </td></tr>';
        $message_item_code_mpn =    '<tr>
                                    <th align="left">Item Code/MPN:&nbsp;</th>
                                    <td style="max-width: 600px">
                                     '. $agreement->sku.'/'.$agreement->mpn.'
                                    </td></tr>';
        $message_delivery_date = '<tr>
                                    <th align="left">Delivery Date:&nbsp;</th>
                                    <td style="max-width: 600px">
                                     '. $agreement->expected_delivery_date.'
                                    </td></tr>';
        $message_item_code = '<tr>
                                <th align="left">Item Code:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $agreement->sku.'
                                </td></tr>';
        $message_agreement_num = '<tr>
                                <th align="left">Agreement Quantity:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $agreement->num.'
                                </td></tr>';
        $message_agreement_unit_price = '<tr>
                                <th align="left">Agreement Price:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '.$this->currency->format(sprintf($format, round($agreement->unit_price, $precision)), $this->session->data['currency']) .'
                                </td></tr>';
        $message_footer = '</table>';
        switch ($type){
            case 1:
                // Buyer直接购买期货合约生成期货协议定单并支付期货保证金后
                //$subject .= 'Buyer('.$agreement->nickname.'&nbsp;'.$agreement->user_number.')支付了期货协议ID为'.$agreement->agreement_no.'的保证金';
                $subject .= 'Buyer '.$agreement->nickname.'('.$agreement->user_number.') paid the deposit for Future Goods Agreement ('.$agreement->agreement_no.').';
                $message .= $message_header;
                $message .= $message_seller_agreement;
                $message .= $message_seller_contract;
                $message .= $message_buyer_name;
                $message .= $message_item_code_mpn;
                $message .= $message_delivery_date;
                $message .= $message_agreement_num;
                $message .= $message_agreement_unit_price;
                $message .= $message_footer;
                break;
            case 2:
                // Buyer向Seller发起/编辑期货BID申请，向Seller发送新期货申请的站内信
                //$subject .= 'Buyer('.$agreement->nickname.'&nbsp;'.$agreement->user_number.')向您提交了一笔的期货协议申请(协议ID:&nbsp;'.$agreement->agreement_no.')';
                $subject .= 'Buyer '.$agreement->nickname.'('.$agreement->user_number.') submit a request of future goods agreement ('.$agreement->agreement_no.') to you.';
                $message .= $message_header;
                $message .= $message_seller_agreement;
                $message .= $message_seller_contract;
                $message .= $message_buyer_name;
                $message .= $message_item_code_mpn;
                $message .= $message_delivery_date;
                $message .= $message_agreement_num;
                $message .= $message_agreement_unit_price;
                $message .= $message_footer;
                break;
            case 3:
                // 平台处理Seller期货合约保证金变动申请后，向Seller发送处理结果的站内信
                // 应该在java端
                break;
            case 4:
                //Seller处理Buyer的期货协议后，向Buyer发送期货协议处理结果的站内信（Bid）同意/拒绝
                $status = $condition['status'] ? 'approved':'rejected';
                //$subject .= '期货协议ID为'.$agreement->agreement_no.'的申请已'. $status;
                $subject .= 'The request of the future goods agreement ('.$agreement->agreement_no.') has been '.$status;
                $message .= $message_header;
                $message .= $message_buyer_agreement;
                $message .= $message_store;
                $message .= $message_item_code_mpn;
                $message .= $message_delivery_date;
                if($condition['status']){
                    $message .= $message_agreement_num;
                    $message .= $message_agreement_unit_price;
                }
                $message .= $message_footer;
                break;
            case 5:
                //Buyer取消期货交易，向Seller发送取消期货协议的站内信；
                $subject .= '期货协议ID为'.$agreement->agreement_no.'的申请已取消';
                $message .= $message_header;
                $message .= $message_seller_agreement;
                $message .= $message_seller_contract;
                $message .= $message_buyer_name;
                $message .= $message_item_code_mpn;
                $message .= $message_delivery_date;
                $message .= $message_footer;
                break;
            case 6:
                //超时未处理yzc_task_work期货协议，向Buyer&Seller发送期货超时的站内信（Bid）
                //Applied、Pending、Approved24小时未处理 协议状态变为超时
                //yzc task work
                break;
            case 7:
                //平台处理Seller提前交货申请后，向Seller发送处理结果的站内信
                //java端
                break;
            case 8:
                //平台同意Seller提前交货申请后，向Buyer发送确认交货的站内信
                //java端
                break;
            case 9:
                //Seller点击确认交货/取消交货后，或平台判定Seller违约协议取消交货，向Buyer发送交货结果的站内信
                $status = $condition['status'] ? 'completed':'canceled';
                //$subject .= '期货协议ID为'.$agreement->agreement_no.'的期货协议已'. $status;
                $subject .= 'The delivery of future goods agreement ('.$agreement->agreement_no.') has been '. $status;
                $message .= $message_header;
                $message .= $message_buyer_agreement;
                $message .= $message_store;
                $message .= $message_item_code;
                $message .= $message_delivery_date;
                $message .= '<tr>
                                <th align="left">Delivery Result:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $status.'
                                </td></tr>';
                if(0 == $condition['status'] ){
                    $apply_info = [
                        'a.apply_type' => $condition['apply_type'],
                        'a.agreement_id' => $agreement_id,
                    ];
                    $msg = $this->getLastApplyInfo($apply_info);
                    $message .= '<tr>
                                <th align="left">取消原因:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '.$msg.'
                                </td></tr>';
                }
                $message .= $message_footer;
                break;
            case 10:
                //Buyer/Seller发起协商终止协议的申请，向Seller/Buyer发送协商终止申请的站内信
                $apply_info = [
                    'a.apply_type' => $condition['apply_type'],
                    'a.agreement_id' => $agreement_id,
                ];
                $msg = $this->getLastApplyInfo($apply_info);
                if($condition['from'] == $agreement->seller_id){
                    $subject .= $agreement->screenname.'向您发起了协议协议编号为'.$agreement->agreement_no.'的协商终止协议申请';
                    $message .= $message_header;
                    $message .= $message_buyer_agreement;
                    $message .= $message_store;
                    $message .= $message_item_code;
                    $message .= '<tr>
                                <th align="left">协商原因:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $msg->message.'
                                </td></tr>';
                    $message .= $message_footer;
                }else{
                    $agreement->contract_no = $this->orm->table('oc_futures_contract')
                        ->where('id',$agreement->contract_id)->value('contract_no');
                    $subject .= $agreement->nickname.'('.$agreement->user_number.')向您发起了协议协议编号为'.$agreement->agreement_no.'的协商终止协议申请';
                    $message .= $message_header;
                    $message .= $message_seller_agreement;
                    $message .= $message_seller_contract;
                    $message .= $message_buyer_name;
                    $message .= $message_item_code_mpn;
                    $message .= '<tr>
                                <th align="left">协商原因:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $msg->message.'
                                </td></tr>';
                    $message .= $message_footer;
                }
                break;
            case 11:
                //Buyer/Seller处理协商终止协议的申请后，向Seller/Buyer发送协商终止申请结果的站内信
                $status = $condition['status'] ? '同意':'拒绝';
                $apply_info = [
                    'a.apply_type' => $condition['apply_type'],
                    'a.agreement_id' => $agreement_id,
                ];
                $msg = $this->getLastApplyInfo($apply_info);
                if($condition['from'] == $agreement->seller_id){
                    $subject .= '您发起了协议编号为'.$agreement->agreement_no.'的协商终止协议申请已被'. $agreement->screenname.'处理，处理结果为'.$status;
                    $message .= $message_header;
                    $message .= '<tr>
                                <th align="left">Agreement ID:&nbsp;</th>
                                <td style="max-width: 600px">
                                    <a target="_blank"
                                         href="' . $this->url->link('account/product_quotes/futures/buyerFuturesBidDetail', 'id=' . $agreement_id ). '">'
                                    .$agreement->agreement_no.
                                    '</a>
                                </td></tr>';
                    $message .= '<tr>
                                <th align="left">Store:&nbsp;</th>
                                <td style="max-width: 600px">
                                     <a target="_blank"
                                             href="' . $this->url->link('customerpartner/profile', 'id=' .$agreement->seller_id ). '">'
                        .$agreement->screenname.
                        '</a>
                                 </td></tr>';
                    $message .= '<tr>
                                <th align="left">Item Code:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $agreement->sku.'
                                </td></tr>';
                    $message .= '<tr>
                                <th align="left">处理结果:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $msg->message.'
                                </td></tr>';
                    $message .= $message_footer;
                }else{
                    $agreement->contract_no = $this->orm->table('oc_futures_contract')
                        ->where('id',$agreement->contract_id)->value('contract_no');
                    $subject .= '您发起了协议编号为'.$agreement->agreement_no.'的协商终止协议申请已被'. $agreement->nickname.'('.$agreement->user_number.')处理，处理结果为'.$status;
                    $message .= $message_header;
                    $message .= $message_seller_agreement;
                    $message .= $message_seller_contract;
                    $message .= $message_buyer_name;
                    $message .= $message_seller_contract;
                    $message .= '<tr>
                                <th align="left">处理结果:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $msg->message.'
                                </td></tr>';
                    $message .= $message_footer;
                }
                break;
        }

        $ret['subject'] = $subject;
        $ret['message'] = $message;
        $ret['received_id']  = $received_id;
        return $ret;

    }

    public function getLastApplyInfo($apply_info)
    {
        return $this->orm->table('oc_futures_agreement_apply as a')
                ->leftJoin('oc_futures_margin_message as m','m.apply_id','=','a.id')
                ->where($apply_info)
                ->orderBy('m.create_time','desc')
                ->first();
    }

    /**
     * [getLeftDay description] 待入仓之前获取天数
     * @param object $agreement
     * @param null $start_time
     * @param int $minute
     * @return int|mixed
     * @throws Exception
     */
    public function getLeftDay($agreement,$start_time = null,$minute = 0)
    {
        if(!$start_time){
            $start_time = date('Y-m-d H:i:s',time());
        }
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $current_date = dateFormat($fromZone, $toZone, $start_time);
        $expected_delivery_date = substr($agreement->expected_delivery_date, 0, 10).' 23:59:59';
        if($minute){
            $left_timestamp = strtotime($expected_delivery_date.' '.$toZone)- time();
            if($left_timestamp >= 0 && $left_timestamp < 3600){
                return str_pad(floor($left_timestamp/60), 2, 0, STR_PAD_LEFT) . ':' . str_pad(($left_timestamp%60), 2, 0, STR_PAD_LEFT);
            }else{
                return false;
           }
        }else{
            $days = intval(ceil((strtotime($expected_delivery_date)- strtotime($current_date))/86400));
            if($days <= 0){
                return 0;
            }else{
                return $days;
            }
        }

    }

    /**
     * @param object $agreement   confirm_delivery_date,futures_version
     * @param int $minute
     * @return bool|int|string
     */
    public function getConfirmLeftDay($agreement,$minute = 0)
    {
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $futuresVersion = ($agreement->version ?? 0);
        if ($futuresVersion == 3) {
            // 当前时间太平洋时间转成当前国别的时间
            $current_date = dateFormat($fromZone, $toZone, date('Y-m-d H:i:s'));
            $confirm_delivery_date = dateFormat($fromZone, $toZone, date('Y-m-d H:i:s', strtotime($agreement->confirm_delivery_date) + 3600 * 24));
//            $confirm_delivery_date = substr($confirm_delivery_date, 0, 10) . ' 23:59:59';
            if ($minute) {
                $left_timestamp = strtotime($confirm_delivery_date . ' ' . $toZone) - time();
                if ($left_timestamp >= 0 && $left_timestamp < 3600) {
                    return str_pad(floor($left_timestamp / 60), 2, 0, STR_PAD_LEFT) . ':' . str_pad(($left_timestamp % 60), 2, 0, STR_PAD_LEFT);
                } else {
                    return false;
                }
            } else {
                $days = intval(ceil((strtotime($confirm_delivery_date) - strtotime($current_date)) / 86400));
                if ($days >= 0) {
                    return $days;
                } else {
                    return 0;
                }
            }
        } else {
            // 当前时间太平洋时间转成当前国别的时间
            $current_date = dateFormat($fromZone,$toZone,  date('Y-m-d H:i:s'));
            $confirm_delivery_date = dateFormat($fromZone,$toZone, date('Y-m-d H:i:s',strtotime($agreement->confirm_delivery_date) + self::FUTURES_FINISH_DAY*86400));
            $confirm_delivery_date = substr($confirm_delivery_date, 0, 10).' 23:59:59';
            if($minute){
                $left_timestamp = strtotime($confirm_delivery_date.' '.$toZone)- time();
                if($left_timestamp >= 0 && $left_timestamp < 3600){
                    return str_pad(floor($left_timestamp/60), 2, 0, STR_PAD_LEFT) . ':' . str_pad(($left_timestamp%60), 2, 0, STR_PAD_LEFT);
                }else{
                    return false;
                }
            }else{
                $days = intval(ceil((strtotime($confirm_delivery_date)- strtotime($current_date))/86400));
                if($days > self::FUTURES_FINISH_DAY){
                    return self::FUTURES_FINISH_DAY;
                }elseif($days <= 0){
                    return 0;
                }else{
                    return $days;
                }
            }
        }


    }

    /**
     * [addCreditRecord description]Buyer充值
     * @param object $agreement_info
     * @param int|float $amount
     * @param int $type 1.buyer期货保证金返还 2.buyer期货保证金 seller 违约部分
     * @throws Exception
     */
    public function addCreditRecord($agreement_info,$amount,$type = 1)
    {
        $db = $this->orm->getConnection();
        try {
            $db->beginTransaction();
            $line_of_credit = $db->table(DB_PREFIX . 'customer')
                ->where('customer_id', $agreement_info->buyer_id)->value('line_of_credit');
            $line_of_credit = round($line_of_credit, 4);
            $new_line_of_credit = round($line_of_credit + $amount, 4);
            if(in_array($type,[1,2])){
                $serialNumber = SerialNumberRepository::getDateSerialNumber(ServiceEnum::AMENDMENT_RECORD_NO);
                $mapInsert = [
                    'serial_number' => $serialNumber,
                    'customer_id' => $agreement_info->buyer_id,
                    'old_line_of_credit' => $line_of_credit,
                    'new_line_of_credit' => $new_line_of_credit,
                    'date_added' => date('Y-m-d H:i:s'),
                    'operator_id' => $agreement_info->seller_id,
                    'type_id' => 10,
                    'memo' => self::CREDIT_TYPE[$type], //根据type 不同产生的
                    'header_id' => $agreement_info->id
                ];
                $db->table('tb_sys_credit_line_amendment_record')->insertGetId($mapInsert);
                $db->table(DB_PREFIX . 'customer')
                    ->where('customer_id', $agreement_info->buyer_id)->update(['line_of_credit' => $new_line_of_credit]);
            }else{
                throw new \Exception('invalid type');
            }
        } catch (Exception $exception) {

            $this->log->write($exception);
            $this->log->write('buyer充值失败');
            $db->rollBack();
        }
        $db->commit();
    }

    /**
     * @param int $agreementId
     * @param int $customerId
     * @param null $applyType
     * @return bool
     */
    public function existUncheckedAgreementApply(int $agreementId, int $customerId, $applyType = null)
    {
        return $this->orm->table('oc_futures_agreement_apply')
            ->where([
                'status' => 0,
                'agreement_id' => $agreementId,
                'customer_id' =>$customerId
            ])
            ->when(!empty($applyType), function ($q) use ($applyType) {
                $q->where('apply_type', $applyType);
            })
            ->exists();
    }

    /**
     * @param int $agreementId
     * @param int $customerId
     * @return bool
     */
    public function existDeliveryAgreementApply(int $agreementId, int $customerId)
    {
        return $this->orm->table('oc_futures_agreement_apply')
            ->where([
                'status' => 1,
                'agreement_id' => $agreementId,
                'customer_id' =>$customerId,
                'apply_type' => 5,
            ])
            ->exists();
    }

    public function updateFutureAgreementAction($agreement,$customer_id,$info,$log)
    {
        // 根据apply_status 不同规划出不同的
        // 更新delivery
        $data['update_time'] = date('Y-m-d H:i:s');
        if($info['delivery_status']){
            $data['delivery_status'] = $info['delivery_status'];
        }
        $this->updateDelivery($agreement->id,$data);
        if($info['add_or_update'] == 'add'){
            $apply_id = 0;
            if (!is_null($info['apply_type'])) {
                $record = [
                    'agreement_id'=> $agreement->id,
                    'customer_id' => $customer_id,
                    'apply_type'  => $info['apply_type'],
                    //'remark'      => '已向平台发起提前交付申请，理由：'.$post['remark'],
                ];
                if(isset($info['apply_status'])){
                    $record['status'] = $info['apply_status'];
                }
                // 新增apply
                $apply_id = $this->addFutureApply($record);
            }

            $message = [
                'agreement_id'=> $agreement->id,
                'customer_id' => $customer_id,
                'apply_id'    => $apply_id,
                'message'     => $info['remark'],
            ];
        }else{
            $record = [
                'update_time'=> date('Y-m-d H:i:s'),
                'status'  => $info['apply_status'],
            ];
            $apply_info = $this->getLastCustomerApplyInfo([$agreement->buyer_id,$agreement->seller_id],$agreement->id,[0]);
            $this->updateFutureApply($apply_info->id,$record);
            $message = [
                'agreement_id'=> $agreement->id,
                'customer_id' => $customer_id,
                'apply_id'    => $apply_info->id,
                'message'     => $info['remark'],
            ];
        }
        // 新增message
        if (!is_null($info['remark'])) {
            $this->addMessage($message);
        }
        // 站内信
        if(isset($info['communication']) && $info['communication']){
            $communication_info = [
                'from'=> $info['from'],
                'to'=> $info['to'],
                'country_id'=> $info['country_id'],
                'status'=> $info['status'],
                'apply_type'=> $info['apply_type'],
            ];
            $this->addFuturesAgreementCommunication($agreement->id,$info['communication_type'],$communication_info);
        }
        // log
        $this->addAgreementLog($log['info'],
            $log['agreement_status'],
            $log['delivery_status']
        );


    }

    /**
     *  拒绝申诉申请
     * @param object $agreement
     * @throws Exception
     */
    public function rejectSellerAppeal($agreement)
    {
        $agreementId = $agreement->id;
        $agreementNo = $agreement->agreement_no;
        $appealBuild = FuturesAgreementApply::query()
            ->where(['agreement_id' => $agreementId, 'apply_type' => FuturesMarginApplyType::APPEAL, 'status' => FuturesMarginApplyStatus::PENDING]);
        $appeal = $appealBuild->first();
        if ($appeal) {
            $appealBuild->update(['status' => FuturesMarginApplyStatus::REJECT]);
            $message = [
                'agreement_id' => $agreementId,
                'apply_id' => $appeal->id,
                'create_user_name' => 'System',
                'message' => 'Result for submitting claim: Decline Seller’s Force Majuere Claim, keeping the delivery status as unchanged and allowing the Seller to appeal.<br>Reason for submitting claim: This is an automated message sent by the Giga Cloud system',
            ];
            FuturesMarginMessage::query()->insertGetId($message);
            // 发送站内信
            $this->load->model('message/message');
            $subject = "Your Force Majuere Claim for Future Goods Agreement (ID:$agreementNo) has been declined (eligible for appeal). ";
            $url = url('account/product_quotes/futures/sellerFuturesBidDetail&id=' . $agreementId);
            $content = "Your claim has been declined. You may appeal this decision and submit your claim again with additional information for reconsideration. <br>Future Goods Agreement ID: <a href='{$url}'>$agreementNo</a> <br>Claim Status: Declined – Eligible for Appeal（This is an automated message sent by the Giga Cloud system）";
            $this->model_message_message->addSystemMessageToBuyer('bid_futures', $subject, $content, $appeal->customer_id);
        }
    }

    /**
     * [addAgreementLog description]
     * @param $data
     * @param $agreement_status
     * @param $delivery_status
     */
    public function addAgreementLog($data,$agreement_status,$delivery_status)
    {
        $delivery_status_pre = $delivery_status[0] == 0 ? 'N/A':self::DELIVERY_STATUS[$delivery_status[0]]['name'];
        $delivery_status_suf = $delivery_status[1] == 0 ? 'N/A':self::DELIVERY_STATUS[$delivery_status[1]]['name'];
        $data['content'] = json_encode([
            'delivery_status' => $delivery_status_pre .' -> '. $delivery_status_suf,
            'agreement_status'=> self::AGREEMENT_STATUS[$agreement_status[0]]['name'] .' -> '. self::AGREEMENT_STATUS[$agreement_status[1]]['name'],
        ]);
        $this->orm->table('oc_futures_agreement_log')->insert($data);
    }

    /**
     * 获取合约可用数量
     * @param int $contract_id
     * @return int|mixed
     */
    public function getContractRemainQty($contract_id)
    {
        $contract = $this->orm->table('oc_futures_contract')
            ->select(['num', 'purchased_num'])
            ->where('id', $contract_id)
            ->where('is_deleted', 0)
            ->first();
        if (!$contract) {
            return 0;
        }
        // 获取协议锁定数量
        $lock_num = $this->orm->table('oc_futures_margin_agreement')
            ->select(['contract_id', 'num'])
            ->where('contract_id', $contract_id)
            ->where('is_lock', 1)
            ->sum('num');
        // 获取合约剩余数量
        $remain_num = $contract->num - $contract->purchased_num;
        return $remain_num - $lock_num;
    }

    public function autoFutureToMarginCompleted($agreement_id){
        $this->load->model('checkout/order');
        $order_id = $this->addPurchaseOrder($agreement_id);
        if($order_id){
            $this->model_checkout_order->withHoldStock($order_id);
            $this->model_checkout_order->addOrderHistoryByYzcModel($order_id,[], 5);
        }
        return $order_id;
    }

    public function autoFutureToMarginCompletedAfterCommit($order_id){
        if ($order_id) {
            $this->load->model('checkout/order');
            $this->model_checkout_order->addOrderHistoryByYzcModelAfterCommit($order_id);
        }
    }

    public function addPurchaseOrder($agreement_id){
        $now = date('Y-m-d H:i:s');
        $agreement_info = $this->getAgreementById($agreement_id);
        // 根据现货保证金交的保证金来决定要不要执行
        $money = $this->orm->table('tb_sys_margin_agreement')->where('id',$agreement_info->margin_agreement_id)->value('money');
        if( $money <= 0 && $agreement_info->margin_agreement_id){
            $order_data = [
                'invoice_no' => 0,
                'invoice_prefix' => $this->config->get('config_invoice_prefix'),
                'store_id' => $this->config->get('config_store_id'),
                'store_name' =>$this->config->get('config_name'),
                'store_url' => $this->config->get('config_url'),
                'customer_id' => $agreement_info->buyer_id,
                'customer_group_id' => $agreement_info->customer_group_id,
                'firstname' => $agreement_info->firstname,
                'lastname' => $agreement_info->lastname,
                'email' => $agreement_info->email,
                'telephone' => $agreement_info->telephone,
                'custom_field' => '[]',
                'payment_country_id' => 0,
                'payment_zone_id' =>  0,
                'payment_custom_field' =>  '[]',
                'payment_method' => PayCode::getDescriptionWithPoundage(PayCode::PAY_LINE_OF_CREDIT),
                'payment_code' => PayCode::PAY_LINE_OF_CREDIT,
                'shipping_zone_id' =>  0,
                'shipping_country_id' =>  0,
                'total' => 0,
                'order_status_id' => 0,
                'affiliate_id' => 0,
                'commission' => 0,
                'language_id' => $this->config->get('config_language_id'),
                'currency_id' => $this->currency->getId($this->session->data['currency']),
                'currency_code' => $this->session->data['currency'],
                'currency_value' => $this->currency->getValue($this->session->data['currency']),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'date_added' => $now,
                'date_modified' => $now,
                'current_currency_value'    => 1,
                'delivery_type' => 0,
                'cloud_logistics_id' => 0,
            ];
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $order_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            } else {
                $order_data['user_agent'] = '';
            }

            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $order_data['accept_language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            } else {
                $order_data['accept_language'] = '';
            }

            $order_id = $this->orm->table(DB_PREFIX.'order')
                ->insertGetId($order_data);
            // 获取转保证金的
            $advance_margin_product_id = $this->getMarginAdvanceProductId($agreement_info->margin_agreement_id);
            $search =["\n","\r","\t"];
            $replace = ["","",""];
            $product_info = $this->orm->table(DB_PREFIX.'product as p')
                ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
                ->where('p.product_id',$advance_margin_product_id)->first();
            $product_name = str_replace($search, $replace, $product_info->name);
            $order_product_data = [
                'order_id' => $order_id,
                'product_id'=>$advance_margin_product_id,
                'name'      =>$product_name,
                'model'     =>$product_info->model,
                'quantity'  =>1,
                'price'     =>0,
                'total'     =>0,
                'tax'       =>0,
                'reward'    =>0,
                'service_fee'  =>0,
                'poundage'  =>0,
                'service_fee_per' => 0,
                'freight_per' => 0,
                'freight_difference_per' => 0,
                'package_fee' => 0,
                'type_id' => 2, // 保证金头款
                'agreement_id'=> $agreement_info->margin_agreement_id,

            ];
            $order_product_id = $this->orm->table(DB_PREFIX . 'order_product')
                ->insertGetId($order_product_data);
            $this->orm->table(DB_PREFIX.'order_total')
                ->insert([
                    'order_id'   => $order_id,
                    'code'       => 'sub_total',
                    'title'      => 'Sub-Total',
                    'value'      => 0,
                    'sort_order' => 1
                ]);
            $this->orm->table(DB_PREFIX.'order_total')
                ->insert([
                    'order_id'   => $order_id,
                    'code'       => 'freight',
                    'title'      => 'Freight',
                    'value'      => 0,
                    'sort_order' => 3
                ]);
            $this->orm->table(DB_PREFIX.'order_total')
                ->insert([
                    'order_id'   => $order_id,
                    'code'       => 'total',
                    'title'      => 'Order Total',
                    'value'      => 0,
                    'sort_order' => 10
                ]);

            return $order_id;
        }
        return false;


    }

    /**
     * 购物车某个协议不能支付后查找选中的购物车中都不满足该协议合约的协议no
     * @param int $agreementId
     * @param array $cartIds
     * @return array
     */
    public function unEnoughContractAgreementNos($agreementId, $cartIds)
    {
        $contractId = $this->orm->table('oc_futures_margin_agreement')->where('id', $agreementId)->value('contract_id');

        return $this->orm->table('oc_futures_margin_agreement as a')
            ->join('oc_cart as c', function ($q) {
                $q->on('a.id', '=', 'c.agreement_id')->where('c.type_id', 3);
            })
            ->where('a.contract_id', $contractId)
            ->whereIn('c.cart_id', $cartIds)
            ->pluck('agreement_no')
            ->toArray();
    }

    public function isEnoughContractQty($agreement_id)
    {
        $agreement = $this->orm->table('oc_futures_margin_agreement')
            ->select(['contract_id', 'num','is_bid','agreement_status'])
            ->where('id', $agreement_id)
            ->first();
        if (!$agreement) {
            return false;
        }
        // 兼容期货一期
        if (!$agreement->contract_id) {
            return true;
        }
        $contract_remain_num = $this->getContractRemainQty($agreement->contract_id);
        // 如果是is_bid的协议，协议同意时候已锁定合约数量，不用验证
        if ($agreement->agreement_status == 3 && $agreement->is_bid) {
            return true;
        }

        //针对购物车同一个合约多个头款支付是，需要计算当前占用数量
        $contractCacheNum = isset($this->contractIdCacheNumMap[$agreement->contract_id]) ? $this->contractIdCacheNumMap[$agreement->contract_id] : 0;
        if (($contract_remain_num - $agreement->num - $contractCacheNum) >= 0) {
            if (!isset($this->contractIdCacheNumMap[$agreement->contract_id])) {
                $this->contractIdCacheNumMap[$agreement->contract_id] = $agreement->num;
            } else {
                $this->contractIdCacheNumMap[$agreement->contract_id] += $agreement->num;
            }

            return true;
        }
        return false;
    }

    /**
     * 判断合约是否有足够的保证金
     * @param int $agreementId
     * @return bool
     */
    public function isEnoughContractMargin($agreementId)
    {
        $agreement = $this->orm->table('oc_futures_margin_agreement')
            ->select(['agreement_no', 'unit_price', 'num', 'seller_payment_ratio', 'contract_id','is_bid'])
            ->where('id', $agreementId)
            ->first();
        if (!$agreement) {
            return false;
        }
        // 兼容期货一期
        if (!$agreement->contract_id) {
            $res['status'] = true;
            return $res;
        }
        $contract = $this->orm->table('oc_futures_contract')
            ->select(['available_balance','status'])
            ->where('id', $agreement->contract_id)
            ->where('is_deleted', 0)
            ->first();
        if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()) {
            $precision = 0;
        } else {
            $precision = 2;
        }
        $amount = round($agreement->unit_price * $agreement->seller_payment_ratio / 100, $precision) * $agreement->num;
        $res['agreement_no'] = $agreement->agreement_no;
        // 如果合约终止，quick view就不能购买头款产品
        if (!$agreement->is_bid && $contract->status != 1) {
            $res['status'] = false;
            return $res;
        }
        if (bccomp($contract->available_balance, $amount) != -1) {
            $res['status'] = true;
        } else {
            $res['status'] = false;
        }
        return $res;
    }

    /**
     * [addNewMarginAgreement description] 期货协议转现货逻辑
     * @param object $agreement
     * @param int $countryId
     * @return array|mixed
     * @throws Exception author: allen.tai <taixing@oristand.com>
     */
    public function addNewMarginAgreement($agreement, $countryId)
    {
        $this->load->model('account/product_quotes/margin_agreement');
        $this->load->language('account/product_quotes/margin');
        $product_info = $this->model_account_product_quotes_margin_agreement->getProductInformationByProductId($agreement->product_id);
        if (empty($product_info)) {
            throw new \Exception($this->language->get("error_no_product"));
        }
//        if ($product_info['quantity'] < $agreement->margin_apply_num) {
//            throw new \Exception($this->language->get("error_under_stock"));
//        }
        if ($product_info['status'] == 0 || $product_info['is_deleted'] == 1 || $product_info['buyer_flag'] == 0) {
            throw new \Exception($this->language->get("error_product_invalid"));
        }
        if (JAPAN_COUNTRY_ID == $countryId) {
            $precision = 0;
        } else {
            $precision = 2;
        }
        if ($agreement->buyer_payment_ratio > 20) {
            $margin_payment_ratio = $agreement->buyer_payment_ratio/100;
        } else {
            $margin_payment_ratio = MARGIN_PAYMENT_RATIO;
        }
        $agreement_id = date('Ymd') . rand(100000, 999999);
        $data = [
            'agreement_id' => $agreement_id,
            'seller_id' => $agreement->seller_id,
            'buyer_id' => $agreement->buyer_id,
            'product_id' => $product_info['product_id'],
            'clauses_id' => 1,
            'price' => $agreement->margin_unit_price,
            'payment_ratio' => $margin_payment_ratio * 100,
            'day' => $agreement->margin_days,
            'num' => $agreement->margin_apply_num,
            'money' => $agreement->margin_deposit_amount,
            'deposit_per' => round($agreement->margin_unit_price * $margin_payment_ratio, $precision),
            'status' => 3,
            'period_of_application' => 1,
            'create_user' => $agreement->buyer_id,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'program_code' => MarginAgreement::PROGRAM_CODE_V4, //现货保证金四期
        ];
        $back['agreement_id'] = $this->saveMarginAgreement($data);
        if ($back['agreement_id']) {
            $data_t = [
                'margin_agreement_id' => $back['agreement_id'],
                'customer_id' => $data['buyer_id'],
                'message' => 'Transfered to margin goods payment',
                'create_time' => date('Y-m-d H:i:s'),
            ];
            $this->orm->table('tb_sys_margin_message')->insert($data_t);
        }
        $back['agreement_no'] = $agreement_id;
        $back['seller_id'] = $agreement->seller_id;
        $back['product_id'] = $agreement->product_id;
        $back['price_new'] = $agreement->margin_deposit_amount;
        return $back;
    }

    public function getAgreementNoByAgreementId($agreementId)
    {
        return $this->orm->table('oc_futures_margin_agreement')
            ->where('id', $agreementId)
            ->value('agreement_no');
    }


    /**
     *  平台退还Seller支付的保证金
     * @param int $futureId
     * @param int $countryId
     * @throws Exception
     */
    private function sellerBackFutureMargin(int $futureId, $countryId)
    {
        $backed = FuturesAgreementMarginPayRecord::query()
            ->where('flow_type', FuturesMarginPayRecordFlowType::SELLER_DEPOSIT_INCOME)
            ->where('agreement_id', $futureId)
            ->exists();
        if ($backed) {
            return;
        }
        $futuresMarginAgreement = $this->orm->table('oc_futures_margin_agreement')->where('id', $futureId)->first();
        if ($futuresMarginAgreement->contract_id == 0) {
            return;
        }

        $this->load->model('futures/contract');
        $contracts = $this->model_futures_contract->firstPayRecordContracts($futuresMarginAgreement->seller_id, [$futuresMarginAgreement->contract_id]);
        if (empty($contracts)) {
            return;
        }

        $amount = round($futuresMarginAgreement->unit_price * $futuresMarginAgreement->seller_payment_ratio * 0.01, $countryId == JAPAN_COUNTRY_ID ? 0 : 2)  * $futuresMarginAgreement->num;
        $futuresMarginAgreementPayType = $contracts[0]['pay_type'];

        $billStatus = 0;
        if ($futuresMarginAgreementPayType == 1) {
            credit::insertCreditBill($futuresMarginAgreement->seller_id, $amount, 2);
            $billStatus = 1;
        }

        agreementMargin::sellerBackFutureMargin($futuresMarginAgreement->seller_id, $futuresMarginAgreement->id, $amount, $futuresMarginAgreementPayType, $billStatus);
    }
}
