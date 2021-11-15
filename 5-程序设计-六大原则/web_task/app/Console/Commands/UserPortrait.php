<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Statistics\UserPortraitModel;
use Illuminate\Support\Facades\Log;

class UserPortrait extends Command
{
    //状态
    const RATE_NA = 0;
    const RATE_HIGH = 1;
    const RATE_MODERATE = 2;
    const RATE_LOW = 3;

    const USER_PORTRAIT_VERSION = '1.0';

    const CREATE_USER_ONE_CUSTOMER = 'command';   //统计单customer数据
    const CREATE_USER_ALL_CUSTOMER = 'schedule';  //统计全体customer数据

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistic:userportrait {customer_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计用户画像信息';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 如果传入customer_id  计算当前改customer的数据，否则计算全部
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $customer_id = $this->argument('customer_id');
        if (!is_numeric($customer_id) || $customer_id <= 0) {   //数据非法
            $customer_id = 0;
        }
        $this->count($customer_id);
    }

    /**
     * 计算用户画像数据
     * DB table oc_buyer_user_portrait
     * @param int $customer_id
     */
    public function count($customer_id = 0)
    {
        //获取数据
        //首单和平台单数
        $firstorder_and_ordernum = UserPortraitModel::get_order_data($customer_id);
        $firstorder_and_ordernum = array_combine(array_column($firstorder_and_ordernum, 'customer_id'), $firstorder_and_ordernum);
        //30天单数
        $order_count_near_30 = UserPortraitModel::get_order_count_near_30($customer_id);
        $order_count_near_30 = array_combine(array_column($order_count_near_30, 'customer_id'), $order_count_near_30);
        //平台交易总额
        $sell_all_price = UserPortraitModel::sell_price($customer_id);
        $sell_all_price = array_combine(array_column($sell_all_price, 'customer_id'), $sell_all_price);
        //退货总金额
        $return_price = UserPortraitModel::refund_price($customer_id);
        $return_price = array_combine(array_column($return_price, 'buyer_id'), $return_price);
        //返金总金额
        $refund_price = UserPortraitModel::return_price($customer_id);
        $refund_price = array_combine(array_column($refund_price, 'buyer_id'), $refund_price);
        //保证金协议总金额
//        $margin_agreement = UserPortraitModel::margin_agreement($customer_id);
//        $margin_agreement = array_combine(array_column($margin_agreement, 'buyer_id'), $margin_agreement);
        //保证金协议头款
        $agreement_head = UserPortraitModel::margin_head($customer_id);
        $agreement_end = UserPortraitModel::margin_end($customer_id);

        $agreement_end = array_combine(array_column($agreement_end, 'buyer_id'), $agreement_end);
        $margin_agreement = array();
        foreach ($agreement_head as $k => $v) {
            $margin_agreement[$v->buyer_id]['agreement_price'] = $v->sum + (isset($agreement_end[$v->buyer_id]->sum) ? $agreement_end[$v->buyer_id]->sum : 0);
        }
        //返点
        $return_point = UserPortraitModel::return_point($customer_id);
        $return_point = array_combine(array_column($return_point, 'buyer_id'), $return_point);
        //获取用户时间
        $get_user_regiest_time = UserPortraitModel::get_user_regiest_time($customer_id);
        //取Buyer complete采购单的产品分类及产品数量，产品数量最高的一个分类为主营类别。Furniture分类取到二级，其他分类取到一级
        $categoryHighestMap = UserPortraitModel::categoryHighestMap();
        $historyOrderCustomerHighestCategoryProductNum = UserPortraitModel::historyOrderCustomerHighestCategoryProductNum($categoryHighestMap, $customer_id);

        //数据操作
        foreach ($get_user_regiest_time as $buyer) {
            //buyer info
            $buyer_id = $buyer->customer_id;
            $buyer_add = array(
                'buyer_id' => $buyer_id
            );
            //data info
            //Return Rate = （（退货总金额 + 返金总金额）/ 平台总金额）* 100.00% ，四舍五入保留两位小数。
            $total_amount_returned = isset($return_price[$buyer_id]->sum) ? $return_price[$buyer_id]->sum : 0;   //退货总金额
            $total_amount_refund = isset($refund_price[$buyer_id]->sum) ? $refund_price[$buyer_id]->sum : 0;     //返金总金额
            $total_amount_platform = isset($sell_all_price[$buyer_id]->total) ? $sell_all_price[$buyer_id]->total : 0;  //平台总金额
            if ($total_amount_platform == 0) {   //除数不为0
                $return_rate = 0;
            } else {
                $return_rate = round(($total_amount_returned + $total_amount_refund) / $total_amount_platform * 100, 2);
            }
            $order_count_platform = isset($firstorder_and_ordernum[$buyer_id]->num) ? $firstorder_and_ordernum[$buyer_id]->num : 0;
            if ($order_count_platform <= 10) {
                $return_rate_show = self::RATE_NA;
            } else {
                if ($return_rate <= 2.5) {
                    $return_rate_show = self::RATE_LOW;
                } elseif ($return_rate > 4.5) {
                    $return_rate_show = self::RATE_HIGH;
                } else {
                    $return_rate_show = self::RATE_MODERATE;
                }
            }
            //保证金参与度 = 保证金协议达成总金额 除以 平台总成交成交额
            $total_amount_margin = isset($margin_agreement[$buyer_id]['agreement_price']) ? $margin_agreement[$buyer_id]['agreement_price'] : 0;    //保证金协议达成总金额
            if ($total_amount_platform == 0) {   //除数不为0
                $complex_complete_rate_value = 0;
            } else {
                $complex_complete_rate_value = round(($total_amount_margin / $total_amount_platform) * 100, 2);    //保证金参与度
            }
            //返点参与度 = 返点协议参加笔数 除以 平台总成交笔数
            $total_return_point = isset($return_point[$buyer_id]->num) ? $return_point[$buyer_id]->num : 0;      //返点协议参加笔数
            if ($order_count_platform == 0) {
                $return_point_rate_value = 0;
            } else {
                $return_point_rate_value = round(($total_return_point / $order_count_platform) * 100, 2);    //返点参与度
            }
            //复杂交易参与度 = 返点参与度 * 50% + 保证金参与度 * 50%  四舍五入，保留两位小数。
            $complex_complete_rate = round($complex_complete_rate_value * 0.5 + $return_point_rate_value * 0.5, 2);
            if ($order_count_platform <= 10) {
                $complex_complete_rate_show = self::RATE_NA;
            } else {
                if ($complex_complete_rate < 1) {
                    $complex_complete_rate_show = self::RATE_LOW;
                } elseif ($complex_complete_rate >= 5) {
                    $complex_complete_rate_show = self::RATE_HIGH;
                } else {
                    $complex_complete_rate_show = self::RATE_MODERATE;
                }
            }

            $main_category_id = 0;
            $highestCategoryNum = [];
            foreach ($historyOrderCustomerHighestCategoryProductNum as $highestCategory => $customerIdNumMap) {
                if (array_key_exists($buyer_id, $customerIdNumMap)) {
                    $highestCategoryNum[$highestCategory] = $customerIdNumMap[$buyer_id];
                }
            }
            if (!empty($highestCategoryNum)) {
                $main_category_id = array_search(max($highestCategoryNum), $highestCategoryNum);
            }

            $arr = array(
                'monthly_sales_count' => isset($order_count_near_30[$buyer_id]->sum) ? $order_count_near_30[$buyer_id]->sum : 0,
                'total_amount_platform' => $total_amount_platform,
                'total_amount_returned' => $total_amount_returned,
                'total_amount_refund' => $total_amount_refund,
                'return_rate_value' => $return_rate,
                'return_rate' => $return_rate_show,
                'order_count_platform' => $order_count_platform,
                'order_count_rebate' => $total_return_point,
                'total_amount_margin' => $total_amount_margin,
                'complex_complete_rate_value' => $complex_complete_rate,
                'complex_complete_rate' => $complex_complete_rate_show,
                'first_order_date' => isset($firstorder_and_ordernum[$buyer_id]->first_order_date) ? $firstorder_and_ordernum[$buyer_id]->first_order_date : date('Y-m-d H:i:s', 0),
                'registration_date' => $buyer->date_added,
                'create_user_name' => $customer_id ? self::CREATE_USER_ONE_CUSTOMER : self::CREATE_USER_ALL_CUSTOMER,
                'program_code' => self::USER_PORTRAIT_VERSION,
                'main_category_id' => $main_category_id,
            );
            //入库
            $res = UserPortraitModel::updateOrCreate($buyer_add, $arr);
            print_r('用户画像：' . $buyer_id . '，数据:' . json_encode($arr) . PHP_EOL);

        }

    }
}
