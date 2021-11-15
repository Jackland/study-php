<?php

/*
 * zjg
 * 2019年12月19日
 * N-503
 * 获取用户画像数据
 */

/**
 * Class ControllerCommonUserPortrait
 * @property ModelCommonUserPortrait $model_common_user_portrait
 * @property ModelCustomerpartnerSellerCenterIndex model_customerpartner_seller_center_index
 */

class ControllerCommonUserPortrait extends Controller
{
    const RATE_NA = 0;
    const RATE_HIGH = 1;
    const RATE_MODERATE = 2;
    const RATE_LOW = 3;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    private function rtn_data($status, $msg = '')
    {
        return array(
            'status' => $status,
            'msg' => $msg
        );
    }

    private function get_status_show($data)
    {
        $this->load->language('common/user_portrait');
        switch ($data) {
            case self::RATE_HIGH:
                $tmp = $this->language->get('high');
                break;
            case self::RATE_MODERATE:
                $tmp =  $this->language->get('moderate');
                break;
            case self::RATE_LOW:
                $tmp =  $this->language->get('low');
                break;
            default:
                $tmp =  $this->language->get('NA');
                break;
        }
        return $tmp;
    }

    public function get_user_portrait_data()
    {
        //text 添加p和样式
        $add_style_fun = function ($text) {
            return sprintf('<p style="word-wrap: break-word;word-break: break-all;overflow: hidden;color: #000000;" >%s</p>', $text);
        };
        // 显示添加颜色
        $add_text_color=function ($text){
            return sprintf('<span style=" color: #FD5C2A;font-weight: 700">%s</span>',$text);
        };

        $this->load->language('common/user_portrait');
        $customer_id = isset($this->request->post['customer_id']) ? $this->request->post['customer_id'] : 0;
        if (empty($customer_id) || !intval($customer_id) || !$customer_id) {
            $this->response->returnJson($this->rtn_data(0, $add_style_fun($this->language->get('disable_user'))));
        }
        $this->load->model('common/user_portrait');
        //获取数据
        /**
         * 以下数据已经重新整理格式化
         * @see App\Repositories\Buyer\BuyerUserPortraitRepository::formatUserPortrait()
         */
        //没有查询到数据
        $res = $this->model_common_user_portrait->get_user_portrait($customer_id);
        if (!is_array($res) || !$res) {  // 查询错误或者为空
            $buyer_status=$this->model_common_user_portrait->get_buyer_status($customer_id);
            if($buyer_status[0]['status']){ //用户存在 status=1   新用户
                $this->response->returnJson($this->rtn_data(0, $add_style_fun($this->language->get('new_user'))));
            }
            $this->response->returnJson($this->rtn_data(0, $add_style_fun($this->language->get('disable_user'))));
        }
        //查询成功--拼接数据
        //首单距今月数
        if (strtotime($res[0]['first_order_date']) < strtotime('2010-1-1')) { // 时间小于2010年
            $first_order_month = sprintf($this->language->get('first_order_more_one'), $add_text_color('N/A'));    //????用户没有成交
        } else {
            $diff = time() - strtotime($res[0]['first_order_date']);
            if ($diff < 30 * 24 * 3600) {
                $first_order_month = sprintf($this->language->get('first_order_less_one'), $add_text_color('less than one'));
            } else {   //>=30天
                $days = $diff / (3600 * 24);
                $month = (int)($days / 30);
                if ($days % 30 >= 15) {
                    $month++;
                }
                $first_order_month = sprintf($this->language->get('first_order_more_one'),$add_text_color( (string)$month));
            }
        }
        //近30天item code 总数量
        $diff = time() - strtotime($res[0]['registration_date']);
        if ($diff < 30 * 24 * 3600) {
            $quantity_near_30_show=$this->language->get('quantity_near_30') .$add_text_color( 'N/A');
        }else{
            switch (true) {
                case $res[0]['monthly_sales_count'] > 10000:
                    $formantQuantity = '10000+';
                    break;
                case  $res[0]['monthly_sales_count'] > 1000:
                    $formantQuantity = '1000+';
                    break;
                case  $res[0]['monthly_sales_count'] > 100:
                    $formantQuantity = '100+';
                    break;
                case  $res[0]['monthly_sales_count'] > 0:
                    $formantQuantity = 'Less than 100';
                    break;
                default:
                    $formantQuantity = '0';
            }
            $quantity_near_30_show= $this->language->get('quantity_near_30') . $add_text_color($formantQuantity);
        }
        //拼接数据
        $text='';

        //评分
        $this->load->model('customerpartner/seller_center/index');
        $task_info=$this->model_customerpartner_seller_center_index->getBuyerNowScoreTaskNumberEffective($customer_id);
        //判断是否评分
        if(isset($task_info['performance_score']) && $task_info['performance_score']){
            $comprehensive_score=number_format(round($task_info['performance_score'],2),2);
            $comprehensive_mark= $this->language->get('comprehensive_mark') . $add_text_color($comprehensive_score);
            $text = $add_style_fun($comprehensive_mark);
        }

        $text .= $add_style_fun($quantity_near_30_show);
        $text .= $add_style_fun($first_order_month);
        $text .= $add_style_fun($this->language->get('complex_complete') . $add_text_color($this->get_status_show($res[0]['complex_complete_rate'])));
        $text .= $add_style_fun($this->language->get('rma_rate') . $add_text_color($this->get_status_show($res[0]['return_rate'])));
//        sleep(5);

        // 取Buyer complete采购单的产品分类及产品数量，产品数量最高的一个分类为主营类别。Furniture分类取到二级，其他分类取到一级。 ones#2407
        if (!empty($res[0]['main_category_id'])) {
            $categoryNames = $this->model_common_user_portrait->getPortraitCategoryName($res[0]['main_category_id']);
            $text .= $add_style_fun($this->language->get('main_category') . $add_text_color(join(" > ", $categoryNames)));
        }

        $this->response->returnJson($this->rtn_data(1, $text));
    }
}
