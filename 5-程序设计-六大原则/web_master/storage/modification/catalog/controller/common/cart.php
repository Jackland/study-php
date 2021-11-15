<?php

class ControllerCommonCart extends Controller
{
    /**
     * 四舍五入精度
     * 日本不保留小数位，其他国家保留两位
     *
     * @var int $precision
     */
    private $precision;

    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
    }

    public function index()
    {
        //判断是否为欧洲
        $data['isEuropean'] = false;
        if (!empty($this->customer->getCountryId() && in_array($this->customer->getCountryId(), [81, 222]))) {
            $data['isEuropean'] = true;
        }
        $this->load->language('common/cart');

        // head中不展示价格 fix:#7085  实现: 原有逻辑还需保留
        $is_show_total = false;
        if ($is_show_total) {
            $this->load->model('extension/module/cart_home');
            $carts_info = $this->model_extension_module_cart_home->index();
            //$this->load->model('account/cart/cart');
            ///**
            // * @var ModelAccountCartCart $cartModel
            // */
            //$cartModel = $this->model_account_cart_cart;
            //查询购物车所有的信息$delivery_type = -1;
            //$data = $cartModel->cartShow(-1);
            //$countProducts = $this->cart->countProducts();
            //if(isset($data['products'])){
            //    $countProducts = array_sum(array_column($data['products'],'quantity'));
            //}else{
            //    $countProducts = 0;
            //}
            $countProducts = $carts_info['quantity'];
            $data['logged'] = $this->customer->isLogged();
            //$countvouchers = count(session('vouchers', []));  
            //$countNum      = $countProducts + $countvouchers;
            $countNum = $countProducts;
            //$countMoney    = $this->currency->format($data['total']['value'], session('currency'));
            $countMoney = $this->currency->format($carts_info['total_price'], session('currency'));

            $data['countNum'] = $countNum;
            $data['countMoney'] = $countMoney;
            $data['text_items'] = sprintf($this->language->get('text_items'), $countNum, $countMoney);
            //$data['vouchers'] = array();
            //
            //if (!empty($this->session->data['vouchers'])) {
            //    foreach (session('vouchers', []) as $key => $voucher) {
            //        $data['vouchers'][] = array(
            //            'key'         => $key,
            //            'description' => $voucher['description'],
            //            'amount'      => $this->currency->format($voucher['amount'], session('currency'))
            //        );
            //    }
            //}
            //
            //
            //
            ////B2B页面改版，头部购物车只显示两种商品
            //$data['products_mini'] = [];
            //$index                 = 0;
            //if(isset($data['products'])) {
            //    foreach ($data['products'] as $value) {
            //        if ($index >= 2) {
            //            break;
            //        }
            //        $data['products_mini'][] = $value;
            //        ++$index;
            //    }
            //}
            //$skuNum = isset($data['products'])?count($data['products']):0;
            //$data['products_other_number'] = ($skuNum > 2) ? ($skuNum - 2) : (0);
        } else {
            $data['countNum'] = $this->cart->productsNum();
        }

        $data['cart'] = $this->url->link('checkout/cart');
        $data['checkout'] = $this->url->link('checkout/checkout', '', true);


        return $this->load->view('common/cart', $data);
    }

    public function info()
    {
        $this->response->setOutput($this->index());
    }
}
