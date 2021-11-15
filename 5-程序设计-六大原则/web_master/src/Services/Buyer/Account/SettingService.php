<?php

namespace App\Services\Buyer\Account;

use App\Models\Buyer\Buyer;
use App\Models\Customer\Customer;
use App\Models\Setting\MessageSetting;

class SettingService
{
    /**
     * description:添加联系人信息
     * @param array $post
     * @return array
     * @throws \Throwable
     */
    public function addContacts(array $post)
    {
        $str = preg_match('/^[0-9a-z]{3,20}$/i', $post['contacts_phone'] ?? '');
        if ((bool)$str === false) {
            return [
                'code' => 0,
                'msg' => 'Please enter a phone number between 3-20 digits.',
            ];
        }
        if ($post['contacts_phone'] && (int)$post['contacts_country_id']) {
            $customerId = customer()->getId();
            Buyer::query()->where('buyer_id', $customerId)
                ->update([
                    'contacts_phone' => $post['contacts_phone'],
                    'contacts_open_status' => (int)$post['contacts_open_status'],
                    'contacts_country_id' => (int)$post['contacts_country_id']
                ]);;
            return [
                'code' => 200,
                'msg' => 'Successfully.',
            ];
        }
        return [
            'code' => 0,
            'msg' => 'Request error.',
        ];
    }

    /**
     * description:修改buyer
     * @param array $post
     * @return array
     */
    public function updateByIdBuyer(array $post)
    {
        if (is_post()) {
            if (strlen($post['nickname']) < 1 || strlen($post['nickname']) > 12) {
                return [
                    'code' => 0,
                    'msg' => 'Nickname must be between 1 and 12 characters!.',
                ];
            }
            Customer::query()->where('customer_id',  customer()->getId())->update(['nickname' => $post['nickname']]);
            return [
                'code' => 200,
                'msg' => 'Successfully.',
            ];
        }
        return [
            'code' => 0,
            'msg' => 'error.',
        ];
    }
}
