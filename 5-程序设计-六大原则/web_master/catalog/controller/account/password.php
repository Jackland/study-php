<?php

use App\Catalog\Controllers\BaseController;
use App\Catalog\Controllers\Traits\JsonResultWithAttributeErrorTrait;
use App\Components\CountDownResolver;
use App\Components\RemoteApi\Exceptions\ApiResponseException;
use App\Components\Sms\Exceptions\SmsPhoneInvalidException;
use App\Components\Sms\Exceptions\SmsSendOverRateException;
use App\Components\Sms\Messages\PasswordChangeVerifyMessage;
use App\Components\Sms\SmsSender;
use App\Components\VerifyCodeResolver;
use App\Components\View\LayoutFactory;
use App\Helper\StringHelper;
use App\Logging\Logger;
use App\Models\Customer\Customer;
use App\Repositories\Customer\CustomerRepository;
use App\Services\Customer\CustomerService;
use Framework\Exception\NotSupportException;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Illuminate\Support\Str;
use Overtrue\EasySms\PhoneNumber;

class ControllerAccountPassword extends BaseController
{
    use JsonResultWithAttributeErrorTrait;

    const SESSION_KEY_CAPTCHA = '__password_reset_captcha_phrase';
    const SESSION_KEY_CUSTOMER_ID = '__password_reset_customer_id';
    const SESSION_KEY_CHANGE_PASSWORD_TOKEN = '__password_change_token';

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        if (in_array(request('route'), ['account/password/reset', 'account/password/resetCaptcha', 'account/password/resetVerify'])) {
            session()->remove(self::SESSION_KEY_CUSTOMER_ID); // 进入重置页面时清除之前验证过的号码session
        }
        if (request('route') !== 'account/password/cannotChange') {
            // 帐号是否可以修改密码验证
            $customer = $this->ensureCustomer(true);
            if ($customer && !app(CustomerRepository::class)->isPasswordCanChangeByCustomerSelf($customer)) {
                $this->redirect('account/password/cannotChange')->send();
            }
        }
    }

    // 兼容原路由 account/password 为修改密码页面
    public function index()
    {
        return $this->redirect('account/password/change', 301);
    }

    // 重置
    public function reset()
    {
        $this->ensureCustomerLoginStatus(false);
        view()->params(LayoutFactory::LAYOUT_LOGIN_SHOW_LOGIN, true);
        return $this->renderFront('account/password/reset', 'login');
    }

    // 修改
    public function change()
    {
        $this->ensureCustomerLoginStatus(true);
        $this->ensureCustomer();
        view()->params(LayoutFactory::LAYOUT_LOGIN_SHOW_LOGIN, true);
        return $this->renderFront('account/password/change', 'login');
    }

    // 不能修改密码的，联系客服修改
    public function cannotChange()
    {
        view()->params(LayoutFactory::LAYOUT_LOGIN_SHOW_LOGIN, true);
        return $this->renderFront('account/password/cannotChange', 'login');
    }

    // 修改成功
    public function changeSuccess()
    {
        view()->params(LayoutFactory::LAYOUT_LOGIN_SHOW_LOGIN, true);
        return $this->renderFront('account/password/changeSuccess', 'login');
    }

    // 图形验证码
    public function resetCaptcha()
    {
        $this->ensureCustomerLoginStatus(false);
        $phraseBuilder = new PhraseBuilder(4);
        $captcha = new CaptchaBuilder(null, $phraseBuilder);
        $captcha->build(request('w', 100), request('h', 50));
        session()->set(self::SESSION_KEY_CAPTCHA, $captcha->getPhrase());

        $captcha->output();
        $this->response->headers->set('Content-type', 'image/jpeg');
        return $this->response;
    }

    // 验证账户
    public function resetVerify()
    {
        $this->ensureCustomerLoginStatus(false);
        $data = request()->post();
        $validator = request()->validateData($data, [
            'email' => 'required|email',
            'captcha' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->resultWithErrors($validator->errors());
        }
        // 校验验证码
        $correctPhrase = session(self::SESSION_KEY_CAPTCHA);
        $builder = new CaptchaBuilder($correctPhrase);
        if (!$builder->testPhrase($data['captcha'])) {
            return $this->resultWithErrors(['captcha' => 'Captcha you entered was not correct. Please try again.']);
        }
        // 验证码使用一次后失效，防止重复利用
        session()->remove(self::SESSION_KEY_CAPTCHA);
        // 检查 email 是否存在
        $customer = Customer::query()->where([
            'email' => $data['email'],
            'status' => 1,
        ])->first();
        if (!$customer) {
            return $this->resultWithErrors(['email' => 'The email you have entered does not exist. Please try again.']);
        }
        // session 保存当前验证过的用户
        session()->set(self::SESSION_KEY_CUSTOMER_ID, $customer->customer_id);

        return $this->resultSuccess([
            'can_change' => app(CustomerRepository::class)->isPasswordCanChangeByCustomerSelf($customer)
        ]);
    }

    // 用户可用的验证方式
    public function changeChoice()
    {
        $customer = $this->ensureCustomer();
        $result = [
            'choice' => ['email'],
            'email' => StringHelper::maskEmail($customer->email),
        ];
        if ($customer->telephone_verified_at) {
            $result['choice'][] = 'phone';
            $result['phone'] = $customer->valid_mask_telephone;
        }

        return $this->jsonSuccess($result);
    }

    // 是否可以发送验证码
    public function canSendVerifyCode()
    {
        $data = request()->post();
        $validator = request()->validateData($data, [
            'type' => 'required|in:email,phone',
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }

        $customer = $this->ensureCustomer();
        $info = $this->buildCountDownAndVerifyCode($data['type'], $customer);
        $result = [
            'can' => $info[0]->isOver(),
        ];
        if (!$result['can']) {
            $result['count_down'] = $info[0]->getInfo()->toArray();
        }

        return $this->jsonSuccess($result);
    }

    // 发送验证码
    public function sendVerifyCode()
    {
        $data = request()->post();
        $validator = request()->validateData($data, [
            'type' => 'required|in:email,phone',
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }

        $customer = $this->ensureCustomer();
        $type = $data['type'];
        $verifyCode = null;
        $countDown = null;
        $canSeeCode = false;
        try {
            if ($type === 'email') {
                // 发邮件
                /** @var CountDownResolver\BaseResolver $countDown */
                /** @var VerifyCodeResolver\EmailResolver $verifyCode */
                [$countDown, $verifyCode] = $this->buildCountDownAndVerifyCode($type, $customer);
                if ($countDown->isOver()) {
                    try {
                        $verifyCode->send();
                    } catch (ApiResponseException $e) {
                        return $this->jsonFailed('Email sending is failed, please reset later.');
                    }
                    $countDown->start();
                }
            }
            if ($type === 'phone') {
                // 发短信
                /** @var CountDownResolver\BaseResolver $countDown */
                /** @var VerifyCodeResolver\EmailResolver $verifyCode */
                [$countDown, $verifyCode] = $this->buildCountDownAndVerifyCode($type, $customer);
                if ($countDown->isOver()) {
                    try {
                        $verifyCode->send();
                    } catch (SmsPhoneInvalidException $e) {
                        return $this->jsonFailed(__('手机号格式不正确，请重新输入', [], 'controller/account/phone'));
                    } catch (SmsSendOverRateException $e) {
                        return $this->jsonFailed(__('短信发送次数过多，请1小时后重试', [], 'controller/account/phone'));
                    }
                    $countDown->start();
                }
                $canSeeCode = !SmsSender::isRealSend();
            }
        } catch (Throwable $e) {
            Logger::error('发送短信或邮件失败');
            Logger::error($e);
            if ($type === 'email') {
                return $this->jsonFailed('Failed to send the verification code to your email, please try again.');
            }
            if ($type === 'phone') {
                return $this->jsonFailed(__('验证码发送失败，请重试', [], 'controller/account/phone'));
            }
        }
        if (!$verifyCode) {
            return $this->jsonFailed('type error');
        }
        $correctCode = $verifyCode->getCorrectCode();

        return $this->jsonSuccess([
            'code' => $canSeeCode ? $correctCode : null,
            'countDown' => $countDown instanceof CountDownResolver\BaseResolver ? $countDown->getInfo()->toArray() : [],
        ]);
    }

    // 验证验证码
    public function verifyVerifyCode()
    {
        $data = request()->post();
        $validator = request()->validateData($data, [
            'type' => 'required|in:email,phone',
            'verify_code' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->resultWithErrors($validator->errors());
        }

        $customer = $this->ensureCustomer();
        // 校验验证码
        /** @var CountDownResolver\BaseResolver $countDown */
        /** @var VerifyCodeResolver\EmailResolver $verifyCode */
        [$countDown, $verifyCode] = $this->buildCountDownAndVerifyCode($data['type'], $customer);
        if (!$verifyCode->verify($data['verify_code'])) {
            return $this->resultWithErrors(['verify_code' => 'The verification code is wrong.']);
        }
        // 验证通过后删除可用的验证值
        $countDown->reset();
        $verifyCode->reset();

        // 生成随机码用于修改密码
        $token = Str::random();
        session()->set(self::SESSION_KEY_CHANGE_PASSWORD_TOKEN, $token);
        return $this->resultSuccess([
            'token' => $token,
        ]);
    }

    // 修改密码
    public function changePassword()
    {
        $data = request()->post();
        $validator = request()->validateData($data, [
            'token' => 'required',
            'new_password' => 'required|string|min:8',
            'new_password_again' => 'required|same:new_password',
        ], [
            'new_password_again.same' => 'The passwords entered twice are inconsistent.',
        ]);
        if ($validator->fails()) {
            return $this->resultWithErrors($validator->errors());
        }
        // 验证 token
        if ($data['token'] !== session(self::SESSION_KEY_CHANGE_PASSWORD_TOKEN)) {
            return $this->resultWithErrors(['token' => 'token is invalid']);
        }

        // 修改密码
        $customer = $this->ensureCustomer();
        $is = app(CustomerService::class)->changePassword($customer, $data['new_password']);
        if (!$is) {
            return $this->resultWithErrors(['new_password' => 'Network Error. Please try again.']);
        }
        // 清除 token
        session()->remove(self::SESSION_KEY_CHANGE_PASSWORD_TOKEN);
        session()->remove(self::SESSION_KEY_CUSTOMER_ID);

        if (customer()->isLogged()) {
            // 修改成功退出登录
            customer()->logout();
        }

        return $this->resultSuccess();
    }

    /**
     * 确保 customer 登录状态
     */
    private function ensureCustomerLoginStatus($needLogin): void
    {
        if ($needLogin) {
            if (!customer()->isLogged()) {
                $this->redirect(['account/password/reset'])->send();
            }
        } else {
            if (customer()->isLogged()) {
                $this->redirect(['account/password/change'])->send();
            }
        }
    }

    private $_customer = false;

    /**
     * 确保用户验证过
     * @param bool $canBeNull
     * @return Customer|null
     */
    private function ensureCustomer(bool $canBeNull = false): ?Customer
    {
        if ($this->_customer === false) {
            if (customer()->isLogged()) {
                $this->_customer = customer()->getModel();
            } else {
                $this->_customer = Customer::find(session(self::SESSION_KEY_CUSTOMER_ID));
            }
        }
        if ($this->_customer) {
            // 已登录的用户检查是否可以修改密码
            if (!app(CustomerRepository::class)->isPasswordCanChangeByCustomerSelf($this->_customer)) {
                $this->redirect('account/password/cannotChange')->send();
                return null;
            }
        }

        if (!$canBeNull && !$this->_customer) {
            throw new InvalidArgumentException('no login or not verify');
        }
        return $this->_customer;
    }

    /**
     * @param string $type
     * @param Customer $customer
     * @return array{CountDownResolver\BaseResolver, VerifyCodeResolver\BaseResolver}
     * @throws NotSupportException
     */
    private function buildCountDownAndVerifyCode(string $type, Customer $customer): array
    {
        if ($type === 'email') {
            // 发邮件
            $countDown = CountDownResolver::simple($customer->email, 300);
            $verifyCode = VerifyCodeResolver::email($customer->email)
                ->setTemplate('change_password_code');
            return [$countDown, $verifyCode];
        }
        if ($type === 'phone') {
            // 发短信
            $phoneNumber = new PhoneNumber($customer->telephone, $customer->telephoneCountryCode->code);
            $countDown = CountDownResolver::increasing($phoneNumber->getUniversalNumber());
            $verifyCode = VerifyCodeResolver::sms($phoneNumber)
                ->setMessage(PasswordChangeVerifyMessage::class);
            return [$countDown, $verifyCode];
        }
        throw new NotSupportException('type error');
    }
}
