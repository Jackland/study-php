<?php

namespace App\Services\Customer;

use App\Listeners\Events\CustomerLoginFailedEvent;
use App\Listeners\Events\CustomerLoginSuccessEvent;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerTelephoneVerifyHistory;
use App\Repositories\Customer\CustomerRepository;
use Carbon\Carbon;
use Exception;

class CustomerService
{
    /**
     * 修改并标记已验证帐号手机号
     * @param int|Customer|\Cart\Customer $customer
     * @param string $phone
     * @param int $telephoneCountryCodeId
     * @return bool
     * @throws Exception
     */
    public function changeAndVerifyPhone($customer, string $phone, int $telephoneCountryCodeId): bool
    {
        $customerRepo = app(CustomerRepository::class);
        $customer = $customerRepo->ensureCustomerModel($customer);
        if ($customerRepo->isPhoneExist($phone, $customer)) {
            throw new Exception('该手机号已被其他账号绑定');
        }

        $customer->telephone = $phone;
        $customer->telephone_verified_at = time();
        $customer->telephone_country_code_id = $telephoneCountryCodeId;
        $is = $customer->save();
        if ($is) {
            // 修改手机号之后记录校验历史
            CustomerTelephoneVerifyHistory::create([
                'customer_id' => $customer->customer_id,
                'telephone_country_code_id' => $telephoneCountryCodeId,
                'telephone' => $customer->telephone,
                'telephone_verified_time' => Carbon::now(),
            ]);
        }
        return $is;
    }

    /**
     * 修改密码
     * @param $customer
     * @param string $password
     * @return bool
     */
    public function changePassword($customer, string $password): bool
    {
        $customerRepo = app(CustomerRepository::class);
        $customer = $customerRepo->ensureCustomerModel($customer);
        $customer->salt = '';
        $customer->password = md5($password);
        return $customer->update();
    }

    /**
     * 邮箱登录
     * @param string $email
     * @param string|null $password
     * @param bool $noPassword
     * @return Customer|null
     */
    public function loginByEmail(string $email, ?string $password, bool $noPassword = false): ?Customer
    {
        $customer = Customer::query()
            ->whereRaw('LOWER(email) = ?', utf8_strtolower($email))//sql 注入
            ->where('status', 1)
            ->first();
        if (is_null($customer)) {
            event(new CustomerLoginFailedEvent(CustomerLoginFailedEvent::TYPE_ACCOUNT_NOT_EXIST, $email, $password));
            return null;
        }
        if ($noPassword) {
            event(new CustomerLoginSuccessEvent(CustomerLoginSuccessEvent::PASSWORD_NO_PASSWORD, $customer));
            return $customer;
        } else {
            // md5 加密形式
            if ($customer->password == md5($password)) {
                event(new CustomerLoginSuccessEvent(CustomerLoginSuccessEvent::PASSWORD_MD5, $customer));
                return $customer;
            }
            //数据库 salt 加密形式
            $sha1Password = SHA1($customer->salt . SHA1($customer->salt . SHA1($password)));
            if ($customer->password == $sha1Password) {
                event(new CustomerLoginSuccessEvent(CustomerLoginSuccessEvent::PASSWORD_HASH, $customer));
                return $customer;
            }
        }
        event(new CustomerLoginFailedEvent(CustomerLoginFailedEvent::TYPE_PASSWORD_ERROR, $email, $password));
        return null;
    }
}
