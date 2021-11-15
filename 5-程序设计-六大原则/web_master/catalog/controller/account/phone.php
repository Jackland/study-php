<?php

use App\Catalog\Controllers\AuthController;
use App\Catalog\Controllers\Traits\JsonResultWithAttributeErrorTrait;
use App\Components\CountDownResolver;
use App\Components\Sms\Exceptions\SmsPhoneInvalidException;
use App\Components\Sms\Exceptions\SmsSendOverRateException;
use App\Components\Sms\Messages\PhoneVerifyMessage;
use App\Components\Sms\SmsSender;
use App\Components\VerifyCodeResolver;
use App\Helper\StringHelper;
use App\Logging\Logger;
use App\Models\Customer\TelephoneCountryCode;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Customer\TelephoneCountryCodeRepository;
use App\Services\Customer\CustomerService;
use App\Widgets\PhoneNeedVerifyNoticeWidget;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Overtrue\EasySms\PhoneNumber;

class ControllerAccountPhone extends AuthController
{
    use JsonResultWithAttributeErrorTrait;

    const SESSION_CHANGE_VERIFIED_KEY = '__CHANGE_VERIFIED_KEY'; // 修改手机号时上一步验证手机号的值，防止非正常操作

    /**
     * @var \App\Models\Customer\Customer
     */
    private $customerModel;
    /**
     * 保留当前发送验证码的手机号，用于刷新页面后恢复
     * @var string
     */
    private $verifyPhoneSessionKey;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->customerModel = customer()->getModel();
        $this->verifyPhoneSessionKey = '__VERIFY_PHONE_' . $this->customerModel->customer_id;
    }

    // 验证手机号
    public function verify()
    {
        if (($can = $this->canVerifyCheck(true)) !== true) {
            return $can;
        }

        $session = array_filter(explode('|', session($this->verifyPhoneSessionKey)));
        $phone = '';
        $countryCodeId = 1;
        $countDown = null;
        if (count($session) === 2) {
            [$phone, $countryCodeId] = $session;
            $countryCode = TelephoneCountryCode::find($countryCodeId);
            $phoneNumber = new PhoneNumber($phone, $countryCode->code);
            $countDown = $this->buildCountDown($phoneNumber);
        }
        if ($countDown === null || $countDown->isOver()) {
            // 首次进入 或者 上次发送的验证码超过倒计时，重新取当前帐号下的手机号
            $phone = trim($this->customerModel->telephone);
            $countryCode = $this->customerModel->telephoneCountryCode;
            $countryCodeId = $countryCode ? $countryCode->id : '';
            if ($phone && $countryCode) {
                $countDown = $this->buildCountDown(new PhoneNumber($phone, $countryCode->code));
            } else {
                $countDown = $this->buildCountDown($phone);
            }
        }

        $data = [
            'countryCodeId' => $countryCodeId,
            'phone' => preg_replace('/[^0-9]/', '', $phone),
            'countDown' => $countDown->getInfo()->toArray(),
            'smsCountryCodeOptions' => app(TelephoneCountryCodeRepository::class)->getSelectOptions(),
        ];

        return $this->render('account/phone/verify', $data, 'login');
    }

    // 修改手机号
    public function change()
    {
        if (($can = $this->canChangeCheck(true)) !== true) {
            return $can;
        }

        $phone = $this->customerModel->telephone; // 修改时默认的手机号必定是验证过的
        $countDown = $this->buildCountDown(new PhoneNumber($phone, $this->customerModel->telephoneCountryCode->code));

        $data = [
            'countryCodeId' => $this->customerModel->telephone_country_code_id,
            'phone' => $this->customerModel->valid_mask_telephone,
            'email' => StringHelper::maskEmail($this->customerModel->email),
            'countDown' => $countDown->getInfo()->toArray(),
            'smsCountryCodeOptions' => app(TelephoneCountryCodeRepository::class)->getSelectOptions(),
        ];

        return $this->render('account/phone/change', $data, customer()->isPartner() ? 'seller' : 'buyer');
    }

    // 隐藏提示，配合 PhoneNeedVerifyNoticeWidget
    public function hideNotice()
    {
        PhoneNeedVerifyNoticeWidget::rememberNotNotice();
        return $this->jsonSuccess();
    }

    // 发送验证页面的验证码
    public function sendVerifyCode()
    {
        if (($can = $this->canVerifyCheck(false)) !== true) {
            return $can;
        }

        $data = request()->post();
        [$validates, $countryCodeCode] = $this->phoneRequiredValidate();
        $validator = request()->validateData($data, $validates);
        if ($validator->fails()) {
            return $this->resultWithErrors($validator->errors());
        }

        session()->set($this->verifyPhoneSessionKey, $data['phone'] . '|' . $data['country_code']);

        return $this->sendCode(new PhoneNumber($data['phone'], $countryCodeCode));
    }

    // 发送修改页面的校验验证码
    public function sendChangeVerifyCode()
    {
        if (($can = $this->canChangeCheck(false)) !== true) {
            return $can;
        }

        // 修改页面的验证码发送时不需要校验是否被其他账号绑定
        return $this->sendCode(new PhoneNumber($this->customerModel->telephone, $this->customerModel->telephoneCountryCode->code), false);
    }

    // 发送修改页面的修改验证码
    public function sendChangeModifyCode()
    {
        if (($can = $this->canChangeCheck(false)) !== true) {
            return $can;
        }

        $data = request()->post();
        [$validates, $countryCodeCode] = $this->phoneRequiredValidate([
            'phone' => function ($attribute, $value, $fail) {
                if ($value === $this->customerModel->telephone) {
                    $fail(__('该手机号与当前绑定的号码相同', [], 'controller/account/phone'));
                }
            },
            'key' => 'required|string',
        ]);
        $validator = request()->validateData($data, $validates);
        if ($validator->fails()) {
            return $this->resultWithErrors($validator->errors());
        }
        if ($data['key'] !== session(self::SESSION_CHANGE_VERIFIED_KEY)) {
            // 非从上一步过来的
            return $this->jsonFailed(__('非正常操作，请刷新页面后重试', [], 'controller/account/phone'));
        }

        return $this->sendCode(new PhoneNumber($data['phone'], $countryCodeCode));
    }

    /**
     * 发送验证码
     * @param PhoneNumber $phone
     * @param bool $checkUsed
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    private function sendCode(PhoneNumber $phone, bool $checkUsed = true)
    {
        if ($checkUsed && app(CustomerRepository::class)->isPhoneExist($phone->getNumber(), $this->customerModel)) {
            return $this->resultWithErrors((new MessageBag())->add('phone', __('该手机号已被其他账号绑定', [], 'controller/account/phone')));
        }
        try {
            $verifyCode = $this->buildVerifyCode($phone);
            $countDown = $this->buildCountDown($phone);
            if ($countDown->isOver()) {
                $verifyCode->send();
                $countDown->start();
            }
            return $this->resultSuccess([
                'countDown' => $countDown->getInfo()->toArray(),
                'code' => !SmsSender::isRealSend() ? $verifyCode->getCorrectCode() : null,
            ]);
        } catch (SmsPhoneInvalidException $e) {
            return $this->resultWithErrors((new MessageBag())->add('phone', __('手机号格式不正确，请重新输入', [], 'controller/account/phone')));
        } catch (SmsSendOverRateException $e) {
            return $this->resultWithErrors((new MessageBag())->add('verify_code', __('短信发送次数过多，请1小时后重试', [], 'controller/account/phone')));
        } catch (Throwable $e) {
            Logger::error('发送短信失败');
            Logger::error($e);
            return $this->resultWithErrors((new MessageBag())->add('verify_code', __('验证码发送失败，请重试', [], 'controller/account/phone')));
        }
    }

    // 验证验证页面的验证码
    public function verifyCode()
    {
        if (($can = $this->canVerifyCheck(false)) !== true) {
            return $can;
        }

        $data = request()->post();
        [$validates, $countryCodeCode] = $this->phoneRequiredValidate([
            'verify_code' => 'required',
        ]);
        $validator = request()->validateData($data, $validates);
        if ($validator->fails()) {
            return $this->resultWithErrors($validator->errors());
        }

        $phoneNumber = new PhoneNumber($data['phone'], $countryCodeCode);
        return $this->verfiyAndChange($phoneNumber, $data['country_code'], $data['verify_code'], true, function (array $successData) {
            session()->remove($this->verifyPhoneSessionKey);
            return $successData;
        });
    }

    // 验证修改页面的验证验证码
    public function verifyChangeVerifyCode()
    {
        if (($can = $this->canChangeCheck(false)) !== true) {
            return $can;
        }

        $data = request()->post();
        $validator = request()->validateData($data, [
            'verify_code' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->resultWithErrors($validator->errors());
        }

        $phoneNumber = new PhoneNumber($this->customerModel->telephone, $this->customerModel->telephoneCountryCode->code);
        return $this->verfiyAndChange($phoneNumber, $this->customerModel->telephone_country_code_id, $data['verify_code'], false, function (array $successData) {
            $successKey = Str::random();
            session()->set(self::SESSION_CHANGE_VERIFIED_KEY, $successKey);
            $successData['key'] = $successKey;
            return $successData;
        });
    }

    // 验证修改页面的修改验证码
    public function verifyChangeModifyCode()
    {
        if (($can = $this->canChangeCheck(false)) !== true) {
            return $can;
        }

        $data = request()->post();
        [$validates, $countryCodeCode] = $this->phoneRequiredValidate([
            'verify_code' => 'required',
            'key' => 'required|string',
        ]);
        $validator = request()->validateData($data, $validates);
        if ($validator->fails()) {
            return $this->resultWithErrors($validator->errors());
        }
        if ($data['key'] !== session(self::SESSION_CHANGE_VERIFIED_KEY)) {
            // 非从上一步过来的
            return $this->jsonFailed(__('非正常操作，请刷新页面后重试', [], 'controller/account/phone'));
        }

        $phoneNumber = new PhoneNumber($data['phone'], $countryCodeCode);
        return $this->verfiyAndChange($phoneNumber, $data['country_code'], $data['verify_code'], true, function (array $successData) {
            session()->remove(self::SESSION_CHANGE_VERIFIED_KEY);
            return $successData;
        });
    }

    /**
     * @param $phone
     * @return CountDownResolver\IncreasingResolver
     */
    private function buildCountDown($phone): CountDownResolver\IncreasingResolver
    {
        if (!$phone instanceof PhoneNumber) {
            $phone = new PhoneNumber($phone);
        }
        return CountDownResolver::increasing($phone->getUniversalNumber());
    }

    /**
     * @param PhoneNumber $phone
     * @return VerifyCodeResolver\SmsResolver
     */
    private function buildVerifyCode(PhoneNumber $phone): VerifyCodeResolver\SmsResolver
    {
        return VerifyCodeResolver::sms($phone)->setMessage(PhoneVerifyMessage::class);
    }

    /**
     * 验证并修改手机号
     * @param PhoneNumber $phone
     * @param int $countryCodeId
     * @param string $code
     * @param bool $change
     * @param callable|null $successCB
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    private function verfiyAndChange(PhoneNumber $phone, int $countryCodeId, string $code, bool $change, callable $successCB = null)
    {
        try {
            $verifyCode = $this->buildVerifyCode($phone);
            if (!$verifyCode->verify($code)) {
                return $this->resultWithErrors((new MessageBag())->add('verify_code', __('验证码错误', [], 'controller/account/phone')));
            }
            // 验证过后重置倒计时
            $countDown = $this->buildCountDown($phone);
            $countDown->reset();
            if ($change) {
                // 校验成功，修改手机号
                app(CustomerService::class)->changeAndVerifyPhone(customer(), $phone->getNumber(), $countryCodeId);
            }
            $maskPhoneNumber = StringHelper::maskCellphone($phone->getNumber());
            $data = [
                'phone' => "+{$phone->getIDDCode()} {$maskPhoneNumber}",
            ];
            if ($successCB) {
                $data = call_user_func($successCB, $data);
            }
            return $this->resultSuccess($data);
        } catch (Throwable $e) {
            return $this->resultWithErrors((new MessageBag())->add('phone', $e->getMessage()));
        }
    }

    /**
     * 手机号必须的校验
     * @param array $extra 其他验证规则
     * @return array
     */
    private function phoneRequiredValidate($extra = []): array
    {
        $validates = [
            'country_code' => 'required', // telephone_country_code 的 id
            'phone' => ['required'],
        ];
        if ($countryCode = request()->post('country_code', 0)) {
            if ($countryCode == TelephoneCountryCodeRepository::CHINA_ID) {
                $validates['phone'][] = 'cellphone1'; // 仅中国号码校验11长度
            }
        }
        foreach ($extra as $key => $validate) {
            if (isset($validates[$key])) {
                $validates[$key] = array_merge((array)$validates[$key], (array)$validate);
                continue;
            }
            $validates[$key] = $validate;
        }
        return [$validates, app(TelephoneCountryCodeRepository::class)->getCodeById($countryCode)];
    }

    /**
     * 是否可验证手机号
     * @param bool $redirect
     * @return true|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    private function canVerifyCheck(bool $redirect)
    {
        if (!app(CustomerRepository::class)->isPhoneNeedVerify($this->customerModel)) {
            // 已经验证过的回到首页
            return $redirect ? $this->redirectHome() : $this->jsonFailed(__('非正常操作，请刷新页面后重试', [], 'controller/account/phone'));
        }
        return true;
    }

    /**
     * 是否可修改手机号
     * @param bool $redirect
     * @return bool|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    private function canChangeCheck(bool $redirect)
    {
        if (!app(CustomerRepository::class)->isPhoneCanChange($this->customerModel)) {
            // 未验证过的去验证页面
            return $redirect ? $this->redirect(['account/phone/verify']) : $this->jsonFailed(__('非正常操作，请刷新页面后重试', [], 'controller/account/phone'));
        }
        return true;
    }
}
