<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Product\ProductTransactionType;
use App\Logging\Logger;
use App\Models\Margin\MarginAgreement;
use App\Models\Margin\MarginMessage;
use App\Models\Margin\MarginProcess;
use App\Repositories\Margin\AgreementRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Services\Margin\MarginService;
use Carbon\Carbon;
use App\Enums\Margin\MarginAgreementLogType;

/**
 * Class ControllerAccountProductQuotesMarginAgreement
 * @property ModelAccountProductQuotesMarginAgreement $model_account_product_quotes_margin_agreement
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelMessageMessage $model_message_message
 * @property ModelCommonProduct $model_common_product
 * @property ModelCheckoutCart $model_checkout_cart
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelAccountCustomerpartnerMargin $model_account_customerpartner_margin
 */
class ControllerAccountProductQuotesMarginAgreement extends Controller
{
    const LIMIT_NUM = 5;
    const MARGIN_MAX_DAYS = 120;
    const MARGIN_MIN_DAYS = 0;

    public function __construct($registry)
    {
        parent::__construct($registry);

        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/account', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
    }

    public function getMarginFrontMoney($post_data, $precision)
    {
        $payment_ratio = trim($post_data['input_margin_payment_ratio'], '%');
        $deposit_per = round($post_data['input_margin_price'] * $payment_ratio / 100, $precision);
        return round($post_data['input_margin_qty'] * $deposit_per, $precision);
    }


    public function addAgreement()
    {
        $format = '%.2f';
        $precision = 2;

        $post_data = $this->request->post;
        // 需要校验是否是seller
        if ($this->customer->isPartner()) {
            return $this->response->json(['error' => 'The account currently logged in does not have permissions to access the purchase function.']);
        }
        $validate_result = $this->validatePostData($post_data);
        if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()) {
            $format = '%d';
            $precision = 0;
        }
        $post_data['input_margin_front_money'] = $this->getMarginFrontMoney($post_data, $precision);

        if (!$validate_result['status']) {
            return $this->response->json(['error' => $validate_result['msg']]);
        } else {
            $customer_id = $this->customer->getId();
            if (!$this->customer->isLogged() || !isset($customer_id) || !isset($post_data)) {
                session()->set('redirect', $this->url->link('account/customer_order'));
                $this->response->redirect($this->url->link('account/login'));
            }

            $this->load->model('account/product_quotes/margin_agreement');
            $this->load->language('account/product_quotes/margin');
            $this->load->model('account/customerpartner/margin');
            $this->load->model('customerpartner/DelicacyManagement');
            $delicacy_model = $this->model_customerpartner_DelicacyManagement;
            $is_display = $delicacy_model->checkIsDisplay($post_data['input_margin_product_id'], $customer_id);
            if (!$is_display) {
                $json['error'] = $this->language->get("page_error");
                goto end;
            }


            //没有保证金模板了
            $template_data = $this->model_account_customerpartner_margin->getMarginTemplateForProduct($post_data['input_margin_product_id']);
            if (!$template_data) {
                $json['error'] = $this->language->get("error_date_updated");
                goto end;
            }

            $update_time = '';
            foreach ($template_data as $vt) {
                if ($vt['is_bid'] == 0) {
                    $json['title'] = "Message";
                    $json['error'] = "Seller don't accept bid for the Margin Price transaction.";
                    goto end;
                }
                $update_time = ($vt['update_time'] > $update_time) ? $vt['update_time'] : $update_time;
            }
            if ($update_time != $this->request->post['last_update_time']) {
                $json['error'] = $this->language->get('page_error');
                goto end;
            }


            $product_info = $this->model_account_product_quotes_margin_agreement->getProductInformationByProductId($post_data['input_margin_product_id']);
            if (empty($product_info)) {
                $json['error'] = $this->language->get("page_error");
                goto end;
            } elseif ($product_info['quantity'] < $post_data['input_margin_qty']) {
                $json['error'] = sprintf($this->language->get("error_under_stock"), $product_info['sku']);
                goto end;
            } elseif ($product_info['status'] == 0 || $product_info['is_deleted'] == 1) {
                $json['error'] = $this->language->get("error_product_invalid");
                goto end;
            } elseif ($product_info['buyer_flag'] == 0) {
                $json['error'] = $this->language->get("error_product_invalid");
                goto end;
            } elseif ($product_info['seller_status'] == 0) {
                $json['error'] = 'The store could not be found and may have been closed.';
                goto end;
            } else {
                if (customer()->isJapan()) {
                    if ($post_data['input_margin_price'] < 0 || $post_data['input_margin_price'] > 99999) {
                        $json['error'] = 'Please enter a number equal or greater than 0 and less than 99999 of current price.';
                        goto end;
                    }
                } else {
                    if ($post_data['input_margin_price'] < 0 || $post_data['input_margin_price'] > 99999.99) {
                        $json['error'] = 'Please enter a number equal or greater than 0 and less than 99999.99 of current price.';
                        goto end;
                    }
                }

                $payment_ratio = trim($post_data['input_margin_payment_ratio'], '%');
                $post_data['deposit_per'] = round($post_data['input_margin_price'] * $payment_ratio / 100, $precision);

                $agreement_id = $this->model_account_product_quotes_margin_agreement->saveMarginAgreement($post_data, $customer_id, $product_info);

                //现货四期  记录协议状态变更日志
                if ($agreement_id) {
                    app(MarginService::class)->insertMarginAgreementLog([
                        'from_status' => MarginAgreementStatus::APPLIED,
                        'to_status' => MarginAgreementStatus::APPLIED,
                        'agreement_id' => $agreement_id,
                        'log_type' => MarginAgreementLogType::BID_TO_APPLIED,
                        'operator' => customer()->getNickName(),
                        'customer_id' => customer()->getId(),
                    ]);
                }

                //发送站内信
                if ($agreement_id) {
                    $this->load->model('account/product_quotes/margin_contract');
                    $agreement_detail = $this->model_account_product_quotes_margin_contract->getMarginAgreementDetail(null, null, $agreement_id);
                    if (!empty($agreement_detail)) {
                        $apply_msg_subject = sprintf($this->language->get('margin_apply_subject'),
                            $agreement_detail['nickname'] . ' (' . $agreement_detail['user_number'] . ') ',
                            $agreement_detail['sku'],
                            $agreement_detail['agreement_id']);
                        $apply_msg_content = sprintf($this->language->get('margin_apply_content'),
                            $this->url->link('account/product_quotes/margin_contract/view&agreement_id=' . $agreement_detail['agreement_id']),
                            $agreement_detail['agreement_id'],
                            $agreement_detail['nickname'] . ' (' . $agreement_detail['user_number'] . ') ',
                            $agreement_detail['sku'] . '/' . $agreement_detail['mpn'],
                            $agreement_detail['num'],
                            sprintf($format, $agreement_detail['unit_price']),
                            $agreement_detail['day']
                        );
                        $this->load->model('message/message');
                        $this->model_message_message->addSystemMessageToBuyer('bid_margin', $apply_msg_subject, $apply_msg_content, $agreement_detail['seller_id']);
                    }
                }

                $json['success'] = $this->language->get("text_add_success");
                goto end;
            }
            end:
            $this->response->setOutput(json_encode($json));
        }

    }

    private function validatePostData($post_data)
    {
        $this->language->load('account/customerpartner/margin');

        if (!isset($post_data['input_margin_product_id']) || empty($post_data['input_margin_product_id'])
            || !is_numeric($post_data['input_margin_product_id'])) {
            return ['status' => false, 'msg' => $this->language->get('error_product_id')];
        }

        if (!isset($post_data['input_margin_qty']) || empty($post_data['input_margin_qty'])
            || !is_numeric($post_data['input_margin_qty']) || $post_data['input_margin_qty'] < self::LIMIT_NUM) {
            return ['status' => false, 'msg' => $this->language->get('error_margin_bid_qty')];
        }

        if (!isset($post_data['input_margin_price']) || $post_data['input_margin_price'] < 0) {
            return ['status' => false, 'msg' => $this->language->get('error_margin_bid_price')];
        }

        if (!isset($post_data['input_margin_day']) || empty($post_data['input_margin_day'])
            || !is_numeric($post_data['input_margin_day']) || $post_data['input_margin_day'] < self::MARGIN_MIN_DAYS || $post_data['input_margin_day'] > self::MARGIN_MAX_DAYS) {
            return ['status' => false, 'msg' => 'Agreed Days is required. <br>Please enter an integer number <br>greater than 0 and equal or less than 120.'];
        }

        if (!isset($post_data['input_margin_payment_ratio']) || empty($post_data['input_margin_payment_ratio'])) {
            return ['status' => false, 'msg' => $this->language->get('error_payment_ratio')];
        }

        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $post_data['input_margin_price'])) {
            return ['status' => false, 'msg' => $this->language->get('error_margin_bid_price')];
        }

        //$payment_ratio = trim($post_data['input_margin_payment_ratio'],'%');
        //if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()){
        //    $precision = 0;
        //}else{
        //    $precision = 2;
        //}
        // 头款校验去掉, 有input_margin_price，input_margin_qty 可以计算得出
        //$deposit_per = round($post_data['input_margin_price'] * $payment_ratio / 100, $precision);
        //$money = round($post_data['input_margin_qty'] * $deposit_per, $precision);

        //if ($money != $post_data['input_margin_front_money'])
        //{
        //    return ['status' => false, 'msg' => 'Please calculate the amount again.'];
        //}

        if (utf8_strlen($_POST['input_margin_message']) > 2000) {
            return ['status' => false, 'msg' => 'Comments can not be more than 2000 characters.'];
        }

        return ['status' => true, 'msg' => 'success'];
    }

    /**
     * [validateOriginalPostData description]
     * @param $posts
     * @return array
     * date:2020/12/2 14:56
     */
    public function validateOriginalPostData($posts)
    {
        $error = [
            'status' => true,
            'msg' => 'success'
        ];

        /** @var \Symfony\Component\HttpFoundation\Request $posts */
        $input_margin_product_id = $posts->get('input_margin_product_id');
        $input_margin_qty = $posts->get('input_margin_qty');
        $input_margin_price = $posts->get('input_margin_price');
        $input_margin_day = $posts->get('input_margin_day');
        $input_margin_payment_ratio = $posts->get('input_margin_payment_ratio');
        $input_margin_message = $posts->get('input_margin_message');
        if (!$input_margin_product_id
            || !is_numeric($input_margin_product_id)) {
            return [
                'status' => false,
                'msg' => $this->language->get('error_product_id')
            ];
        }

        if (!$input_margin_qty
            || $input_margin_qty < 0
            || !is_numeric($input_margin_qty)
            || $input_margin_qty < self::LIMIT_NUM) {
            return [
                'status' => false,
                'msg' => $this->language->get('error_margin_bid_qty')
            ];
        }

        if (!$input_margin_price
            || !is_numeric($input_margin_price)
            || $input_margin_price < 0) {
            return [
                'status' => false,
                'msg' => $this->language->get('error_margin_bid_price')
            ];
        }

        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $input_margin_price)) {
            return [
                'status' => false,
                'msg' => $this->language->get('error_margin_bid_price')
            ];
        }

        if (!$input_margin_day
            || !is_numeric($input_margin_day)
            || $input_margin_day < self::MARGIN_MIN_DAYS
            || $input_margin_day > self::MARGIN_MAX_DAYS) {
            return [
                'status' => false,
                'msg' => 'Agreed Days is required. <br>Please enter an integer number <br>greater than 0 and equal or less than 120.'
            ];
        }

        if (!$input_margin_payment_ratio) {
            return [
                'status' => false,
                'msg' => $this->language->get('error_payment_ratio')
            ];
        }

        if (utf8_strlen($input_margin_message) > 2000) {
            return [
                'status' => false,
                'msg' => 'Comments can not be more than 2000 characters.'
            ];
        }

        return $error;

    }

    /**
     * [addAutoMarginAgreement description] 自动添加协议
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     * date:2020/12/2 10:09
     */
    public function addAutoMarginAgreement()
    {
        $json = [];
        $marginRepository = app(MarginRepository::class);

        $format = '%.2f';
        $precision = 2;
        if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()) {
            $format = '%d';
            $precision = 0;
        }

        $this->load->language('account/customerpartner/margin');
        $this->load->language('account/product_quotes/margin');
        $posts = $this->request->input;
        // 需要校验是否是seller
        if ($this->customer->isPartner()) {
            return $this->response->json(['error' => 'The account currently logged in does not have permissions to access the purchase function.']);
        }
        //校验数据
        $validateResult = $this->validateOriginalPostData($posts);
        if (!$validateResult['status']) {
            return $this->response->json(['error' => $validateResult['msg']]);
        }
        // 校验是否是精细化不可见
        $this->load->model('customerpartner/DelicacyManagement');
        $customerId = $this->customer->getId();
        $is_display = $this->model_customerpartner_DelicacyManagement->checkIsDisplay($posts->get('input_margin_product_id'), $customerId);
        if (!$is_display) {
            $json['error'] = $this->language->get('page_error');
            return $this->response->json($json);
        }

        // 验证保证金模板
        $this->load->model('account/customerpartner/margin');
        $templateData = $this->model_account_customerpartner_margin->getMarginTemplateForProduct($posts->get('input_margin_product_id'));
        if (!$templateData) {
            $json['error'] = $this->language->get('error_date_updated');
            return $this->response->json($json);
        }

        $update_time = '';
        foreach ($templateData as $vt) {
            $update_time = ($vt['update_time'] > $update_time) ? $vt['update_time'] : $update_time;
        }
        if ($update_time != $posts->get('last_update_time')) {
            $json['error'] = $this->language->get('page_error');
            return $this->response->json($json);
        }

        // 校验product info
        $this->load->model('account/product_quotes/margin_agreement');
        $productInfo = $this->model_account_product_quotes_margin_agreement->getProductInformationByProductId($posts->get('input_margin_product_id'));
        if (empty($productInfo)) {
            $json['error'] = $this->language->get("page_error");
            return $this->response->json($json);
        }

        if ($productInfo['quantity']  < $posts->get('input_margin_qty')) {
            $json['error'] = sprintf($this->language->get("error_under_stock"), $productInfo['sku']);
            return $this->response->json($json);

        }

        if ($productInfo['status'] == 0
            || $productInfo['is_deleted'] == 1
            || $productInfo['buyer_flag'] == 0
        ) {
            $json['error'] = $this->language->get('error_product_invalid');
            return $this->response->json($json);

        }

        if ($productInfo['seller_status'] == 0) {
            $json['error'] = 'The store could not be found and may have been closed.';
            return $this->response->json($json);

        }

        if (customer()->isJapan()) {
            if ($posts->get('input_margin_price') < 0 || $posts->get('input_margin_price') > 99999) {
                $json['error'] = 'Please enter a number equal or greater than 0 and less than 99999 of current price.';
            }
        } else {
            if ($posts->get('input_margin_price') < 0 || $posts->get('input_margin_price') > 99999.99) {
                $json['error'] = 'Please enter a number equal or greater than 0 and less than 99999.99 of current price.';
            }
        }
        // #22763 折扣
        $discountInfo = app(MarketingDiscountRepository::class)->getMaxDiscount($customerId, $productInfo['product_id'], $posts->get('input_margin_qty'), ProductTransactionType::MARGIN);
        $discount = $discountInfo->discount ?? null; //产品折扣
        $template = app(AgreementRepository::class)->getMarginTemplateByQty($productInfo['seller_id'], $productInfo['product_id'], $posts->get('input_margin_qty'));
        $discountPrice = $template->price - $posts->get('input_margin_price');
        $this->load->language('account/product_quotes/margin_contract');
        $this->load->model('common/product');
        $agreement_product_available_qty = $this->model_common_product->getProductAvailableQuantity(
            (int)$posts->get('input_margin_product_id')
        );

        if ($agreement_product_available_qty < $posts->get('input_margin_qty')) {
            $json['error'] = $this->language->get('error_no_stock');
            return $this->response->json($json);
        }

        $payment_ratio = trim($posts->get('input_margin_payment_ratio'), '%');
        $deposit_per = round($posts->get('input_margin_price') * $payment_ratio / 100, $precision);
        $input_margin_front_money = round($posts->get('input_margin_qty') * $deposit_per, $precision);
        $rest_price = round(($posts->get('input_margin_price') - $deposit_per), 2);
        $bondTemplateInfo = $this->model_account_product_quotes_margin_agreement->getBondTemplateInfo($posts->get('input_bond_template_id'));

        try {
            $this->orm->getConnection()->beginTransaction();
            $data = [
                'agreement_id' => date('Ymd') . rand(100000, 999999),
                'seller_id' => $productInfo['seller_id'],
                'buyer_id' => $customerId,
                'product_id' => $productInfo['product_id'],
                'clauses_id' => 1,
                'price' => $posts->get('input_margin_price'),
                'payment_ratio' => $payment_ratio,
                'day' => $posts->get('input_margin_day'),
                'num' => $posts->get('input_margin_qty'),
                'money' => $input_margin_front_money,
                'deposit_per' => $deposit_per,
                'rest_price' => $rest_price,
                'status' => MarginAgreementStatus::APPLIED,
                'is_bid' => YesNoEnum::NO, // 通过quickView方式进行数据处理
                'period_of_application' => $bondTemplateInfo['period_of_application'],
                //'bond_template_number'  => $bond_template_info['bond_template_number'],
                'discount' => $discount,
                'discount_price' => $discountPrice,
                'create_user' => $customerId,
                'create_time' => Carbon::now(),
                'update_user' => $customerId,
                'update_time' => Carbon::now(),
                'program_code' => MarginAgreement::PROGRAM_CODE_V4, //现货保证金四期
            ];
            $agreementId = MarginAgreement::query()->insertGetId($data);
            //发送站内信
            $agreementDetail = $marginRepository->getMarginAgreementInfo($agreementId);
            // margin message 新增数据
            if ($posts->get('input_margin_message')) {
                $message = [
                    'margin_agreement_id' => $agreementId,
                    'customer_id' => $customerId,
                    'message' => $posts->get('input_margin_message'),
                    'create_time' => Carbon::now(),
                ];
                MarginMessage::query()->insertGetId($message);
            }
            $productNewId = $this->approveMarginAgreement($agreementId, $agreementDetail);

            //现货四期，记录协议状态变更
            app(MarginService::class)->insertMarginAgreementLog([
                'from_status' => MarginAgreementStatus::APPLIED,
                'to_status' => MarginAgreementStatus::APPROVED,
                'agreement_id' => $agreementId,
                'log_type' => MarginAgreementLogType::QUICK_VIEW_TO_APPROVED,
                'operator' => customer()->getNickName(), //自动同意，仍算是买家行为，直接记录买家信息
                'customer_id' => customer()->getId(), // $agreementDetail['seller_id']
            ]);

            $cartId = $this->addMarginAdvanceProductIdToCart($agreementId, $productNewId);
            // 不需要seller发站内信
            $this->orm->getConnection()->commit();

        } catch (Exception $e) {
            Logger::app($e);
            $cartId = null;
            $this->orm->getConnection()->rollBack();
            $json['error'] = 'Something wrong happened.';
            return $this->json($json);
        }

        $delivery_type = $this->customer->isCollectionFromDomicile() == true ? YesNoEnum::YES : YesNoEnum::NO;
        $json['url'] = $this->url->link('checkout/pre_order', ['delivery_type' => $delivery_type, 'cart_id_str' => $cartId]);
        $string = $this->customer->isCollectionFromDomicile() == true ? 'Pick Up' : 'Drop Shipping';
        $json['success'] = 'Success: You have added margin deposit product to your '.$string.' shopping cart!';
        return $this->json($json);
    }

    /**
     * [approveMarginAgreement description] 自动同意协议并创建保证金头款
     * @param int $agreementId
     * @param  $agreementDetail
     * @return int
     * @throws Exception
     * date:2020/12/2 10:10
     */
    public function approveMarginAgreement(int $agreementId, $agreementDetail = null)
    {
        $marginService = app(MarginService::class);
        $marginRepository = app(MarginRepository::class);
        if (!$agreementDetail) {
            $agreementDetail = $marginRepository->getMarginAgreementDetail($agreementId);
        }

        $map = [
            'id' => $agreementId,
            'seller_id' => $agreementDetail['seller_id'],
        ];
        $update = [
            'status' => MarginAgreementStatus::APPROVED,
            'update_time' => Carbon::now(),
            'update_user' => $agreementDetail['seller_id'],
        ];
        MarginAgreement::query()->where($map)->update($update);
        // 复制头款保证金
        $productIdNew = $marginService->copyMarginProduct($agreementId, $agreementDetail);
        // 创建保证金进程记录
        $marginProcessArrays = [
            'margin_id' => $agreementId,
            'margin_agreement_id' => $agreementDetail['agreement_id'],
            'advance_product_id' => $productIdNew,
            'process_status' => 1,
            'create_time' => Carbon::now(),
            'create_username' => $agreementDetail['seller_id'],
            'program_code' => PROGRAM_CODE
        ];
        MarginProcess::query()->insertGetId($marginProcessArrays);

        return $productIdNew;

    }

    /**
     * [addMarginAdvanceProductIdToCart description] 自动加入购物车，打开下单页提供购买
     * date:2020/12/2 10:10
     * @param int $agreementId
     * @param int $productId
     * @return int|mixed
     * @throws Exception
     */
    public function addMarginAdvanceProductIdToCart(int $agreementId, int $productId)
    {

        $this->load->model('checkout/cart');
        $count = $this->model_checkout_cart->verifyProductAdd(
            $productId,
            ProductTransactionType::MARGIN,
            $agreementId,
            0
        );
        if ($count) {
            throw new Exception(__FILE__ . "This product has other transaction type, it can not add to cart!");
        }

        // delivery type  0 一件代发 1 上门取货
        $delivery_type = $this->customer->isCollectionFromDomicile() == true ? YesNoEnum::YES : YesNoEnum::NO;
        return $this->model_checkout_cart->add(
            $productId,
            1,
            [],
            0,
            ProductTransactionType::MARGIN,
            $agreementId,
            $delivery_type,
            0
        );
    }


}
