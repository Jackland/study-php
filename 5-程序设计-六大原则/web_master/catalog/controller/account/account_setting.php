<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Repositories\Customer\CustomerRepository;
use App\Services\Seller\SellerService;
use App\Helper\CustomerHelper;

/**
 * @property ModelAccountCustomer $model_account_customer
 */
class ControllerAccountAccountSetting extends AuthSellerController
{
    private $data  = [];
    private $error = [];
    const ZYM_INDEX = 'account_setting';
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('account/customer');
        $this->load->language('account/account_setting');
    }

    public function index()
    {
        $data = [];
        $data['captcha_enter_url'] =  str_replace('&amp;', '&', $this->url->link('account/account_setting/captchaEnter/','',True));
        $data['captcha_vertify_url'] =  str_replace('&amp;', '&', $this->url->link('account/account_setting/checkCaptchaCode','',True));

        $data['email_vertify_url'] =  str_replace('&amp;', '&', $this->url->link('account/account_setting/checkEmail','',True));
        $data['email_post_url'] =  str_replace('&amp;', '&', $this->url->link('account/account_setting/resetEmailInfo','',True));

        $data['phone_post_url'] =  str_replace('&amp;', '&', $this->url->link('account/account_setting/resetPhoneInfo','',True));

        $data['old_pwd_vertify_url'] =  str_replace('&amp;', '&', $this->url->link('account/account_setting/checkOldPassword','',True));
        $data['pwd_post_url'] =  str_replace('&amp;', '&', $this->url->link('account/account_setting/resetPasswordInfo','',True));

        $data['seller_dashboard'] =  str_replace('&amp;', '&', $this->url->link('customerpartner/seller_center/index','',True));

        $data['user_info'] = [
            'user_number' => $this->customer->getUserNumber(),
            'email' =>$this->customer->getEmail(),
            'phone' => $this->customer->getModel()->valid_mask_telephone,
            'can_change_phone' => app(CustomerRepository::class)->isPhoneCanChange(customer(), true),
            'can_change_password' => app(CustomerRepository::class)->isPasswordCanChangeByCustomerSelf(customer()),
            'password' => '******',
        ];

        return $this->render('account/account_setting', $data, 'seller');
    }


    /**
     * 修改email信息
     */
    public function resetEmailInfo()
    {
        $result = ['code' => 0 ,'msg' => $this->language->get('common_error') ,'error_type' => 0];

        if (($this->request->serverBag->get('REQUEST_METHOD') == 'POST') && $this->validateEmailForm() === 1) {
            $update_data = [
                'email' => $this->request->post('email'),
            ];
            $res = $this->model_account_customer->editCustomerInfoById($this->customer->getId(),$update_data);
            if ($res !== false){
                //7787需求，同步到seller开户表
                app(SellerService::class)->updateSellerAccountApplyInfo(customer()->getId(), ['email' => $update_data['email']]);
                $result['code'] = 1;
                $result['msg'] = '';
                //同步到giga onsite
                app(CustomerHelper::class)->postAccountInfoToOnSite(customer()->getId());
                // 通过b2b manage 同步到财务数据平台
                app(SellerService::class)->sendApply(customer()->getId());
            }
        }else{
            $result['msg'] = $this->error['email_error'];
            $result['error_type'] = $this->error['error_type'];
        }
        $this->response->headers->add(['Content-Type' => 'application/json']);
        $this->response->setOutput(json_encode($result));
    }

    /**
     * 修改号码信息
     */
    public function resetPhoneInfo()
    {
        $result = ['code' => 0 ,'msg' => $this->language->get('common_error') ,'error_type' => 0];

        if (($this->request->serverBag->get('REQUEST_METHOD') == 'POST') && $this->validatePhoneForm() === 1) {
            $update_data = [
                'telephone' => $this->request->post('telephone') ,
            ];
            $res = $this->model_account_customer->editCustomerInfoById($this->customer->getId(),$update_data);
            if ($res !== false){
                $result['code'] = 1;
                $result['msg'] = '';
            }
        }else{
            $result['msg'] = $this->error['error_telephone'];
            $result['error_type'] = $this->error['error_type'];
        }
        $this->response->headers->add(['Content-Type' => 'application/json']);
        $this->response->setOutput(json_encode($result));
    }

    /**
     * 修改密码信息
     */
    public function resetPasswordInfo()
    {
        $result = ['code' => 0 ,'msg' => $this->language->get('common_error'),'error_type' => 0];

        if (($this->request->serverBag->get('REQUEST_METHOD') == 'POST') && $this->validatePasswordForm() === 1) {
            $salt = $this->db->escape(token(9)) ;
            $update_data = [
                'salt' => $salt,
                'password' => $this->db->escape(sha1($salt . sha1($salt . sha1($this->request->post('new_password'))))) ,
            ];
            $res = $this->model_account_customer->editCustomerInfoById($this->customer->getId(),$update_data);
            if ($res !== false){
                //7787需求，同步到seller开户表
                app(SellerService::class)->updateSellerAccountApplyInfo(customer()->getId(), ['password' => $this->request->post('new_password')]);
                $result['code'] = 1;
                $result['msg'] = '';
            }
        }else{
            $result['msg'] = $this->error['error_password'];
            $result['error_type'] = $this->error['error_type'];
        }
        $this->response->headers->add(['Content-Type' => 'application/json']);
        $this->response->setOutput(json_encode($result));
    }

    /**
     * 检查老密码是否正确,直接验证是否正确就行，无需验证格式
     */
    public function checkOldPassword()
    {
        $result = ['code' => 1 ,'msg' => ''];
        $old_password = $this->request->post('old_password');
        $check_pwd = $this->model_account_customer->checkPasswordValid($this->customer->getId() , $old_password);
        if (!$check_pwd){
            $result['code'] = 0 ;
            $result['msg'] = $this->language->get('error_old_pwd');
        }
        $this->response->headers->add(['Content-Type' => 'application/json']);
        $this->response->setOutput(json_encode($result));
    }

    /**
     * 检查图形验证码是否正确
     */
    public function checkCaptchaCode()
    {
        $result = ['code' => 1 ,'msg' => ''];

        $code = trim($this->request->post('code'));

        $captcha = new Verify([],$this->registry);

        $res = $captcha->ajaxCheck($code,self::ZYM_INDEX);

        if($res === false){
            $result['code'] = 0 ;
            $result['msg'] = $this->language->get('error_code');
        }elseif($res === 0){
            $result['code'] = 0 ;
            $result['msg'] = $this->language->get('error_captcha_expired');
        }

        $this->response->headers->add(['Content-Type' => 'application/json']);
        $this->response->setOutput(json_encode($result));
    }

    /**
     * 检查邮箱是否可用
     * 先验证是否合法 ，再验证是否被占用
     */
    public function checkEmail()
    {
        $result = ['code' => 1 ,'msg' => ''];

        $email = $this->request->post('email');

        if ((utf8_strlen($email) > 96) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['code'] = 0 ;
            $result['msg'] = $this->language->get('error_eamil');
        }elseif ($this->model_account_customer->checkNewEmailValid($this->customer->getId() , $email)){
            $result['code'] = 0 ;
            $result['msg'] = $this->language->get('error_email_exists');
        }

        $this->response->headers->add(['Content-Type' => 'application/json']);
        $this->response->setOutput(json_encode($result));
    }

    /**
     * 重新生成验证码
     */
    public function  captchaEnter()
    {
        $captcha = new Verify([],$this->registry);
        $captcha->entry(self::ZYM_INDEX);
    }

    /**
     * 提交验证,eamil
     */
    protected function validateEmailForm()
    {
        //email
        if ((utf8_strlen($this->request->post('email')) > 96) || !filter_var($this->request->post('email'), FILTER_VALIDATE_EMAIL)) {
            $this->error['email_error'] = $this->language->get('error_eamil');
            $this->error['error_type'] = 1 ;
            return $this->error ;
        }

        if (($this->customer->getEmail() != $this->request->post('email')) && $this->model_account_customer->getTotalCustomersByEmail($this->request->post('email'))) {
            $this->error['email_error'] = $this->language->get('error_email_exists');
            $this->error['error_type'] = 1 ;
            return $this->error ;
        }

        //验证码
        $check_code = (new Verify([],$this->registry))->check($this->request->post('code') , self::ZYM_INDEX );
        if (!$check_code){
            $this->error['email_error'] = $this->language->get('error_code');
            $this->error['error_type'] = 2 ;
            return $this->error ;
        }
        return 1 ;
    }

    /**
     * 提交验证,phone
     */
    protected function validatePhoneForm()
    {
        if ((utf8_strlen($this->request->post('telephone')) < 3) || (utf8_strlen($this->request->post('telephone')) > 32)) {
            $this->error['error_telephone'] = $this->language->get('error_telephone');
            $this->error['error_type'] = 1 ;
            return $this->error ;
        }

        return 1 ;
    }

    /**
     * 提交验证,password
     */
    protected function validatePasswordForm()
    {
        //老密码验证是否正确
        $check_pwd = $this->model_account_customer->checkPasswordValid($this->customer->getId(),$this->request->post('old_password'));
        if (!$check_pwd){
            $this->error['error_password'] = $this->language->get('error_old_pwd');
            $this->error['error_type'] = 1 ;
            return $this->error ;
        }

        if ((utf8_strlen(html_entity_decode($this->request->post('new_password'), ENT_QUOTES, 'UTF-8')) < 6) || (utf8_strlen(html_entity_decode($this->request->post('new_password'), ENT_QUOTES, 'UTF-8')) > 30)) {
            $this->error['error_password'] = $this->language->get('error_pwd_length_and_number_id');
            $this->error['error_type'] = 2 ;
            return $this->error ;
        }

        if ($this->request->post('new_password') ==  $this->customer->getUserNumber()) {
            $this->error['error_password'] = $this->language->get('error_pwd_length_and_number_id');
            $this->error['error_type'] = 2 ;
            return $this->error ;
        }

        if ($this->request->post('new_password') != $this->request->post('confirm_password')) {
            $this->error['error_password'] = $this->language->get('error_pwd_confirm');
            $this->error['error_type'] = 3 ;
            return $this->error ;
        }

        //验证码
        $check_code = (new Verify([],$this->registry))->check($this->request->post('code') , self::ZYM_INDEX );
        if (!$check_code){
            $this->error['error_password'] = $this->language->get('error_code');
            $this->error['error_type'] = 4 ;
            return $this->error ;
        }

        return 1 ;
    }
}
