<?php
/**
 * buyer 账号设置控制器
 */

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Forms\Buyer\Account\SettingForm;
use App\Repositories\Buyer\BuyerRepository;
use App\Repositories\Buyer\TelephoneCountryCodeRepository;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Setting\MessageSettingRepository;
use App\Services\Buyer\Account\SettingService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ControllerAccountSetting extends AuthBuyerController
{

    /**
     * description:buyer 账号设置首页
     * @return string
     */
    public function index()
    {
        $this->load->language('account/edit');
        $data = [
            'valid_mask_telephone' => customer()->getModel()->valid_mask_telephone,
            'customer' => customer()->getModel()->toArray(),
            'can_change_phone' => app(CustomerRepository::class)->isPhoneCanChange(customer(), true),
            'can_change_password' => app(CustomerRepository::class)->isPasswordCanChangeByCustomerSelf(customer())
        ];
        return $this->render('account/setting/index', $data, 'buyer');
    }


    /**
     * description:添加采购人信息
     * @return string
     * @throws Throwable
     */
    public function addPurchaser(SettingForm $form, BuyerRepository $buyer)
    {
        if (is_post()) {
            $data = $form->save();
            return $this->json($data);
        }
        $data = [
            'customer_info' => collect($buyer->getBuyerByIdList($this->customer->getId()))->toArray(),
            'seller_recommend_setting' => collect(app(MessageSettingRepository::class)
                ->getMsgSettingByIdData($this->customer->getId(), ['is_in_seller_recommend']))->toArray(),
            'tel_country_code' => app(TelephoneCountryCodeRepository::class)->getAllList()
        ];
        return $this->render('account/setting/add_purchaser', $data, 'buyer');
    }

    /**
     * description:添加联系人信息
     * @return string
     * @throws Throwable
     */
    public function addContacts(SettingService $service, BuyerRepository $buyer)
    {
        if (is_post()) {
            $data = $service->addContacts($this->request->post());
            return $this->json($data);
        }
        $data = [
            'tel_country_code' => app(TelephoneCountryCodeRepository::class)->getAllList(),
            'customer_info' => collect($buyer->getBuyerByIdList($this->customer->getId(),
                ['buyer_id', 'contacts_country_id', 'contacts_phone', 'contacts_open_status']))->toArray()
        ];
        return $this->render('account/setting/add_contacts', $data, 'buyer');
    }

    /**
     * description:修改buyer 的基础信息
     * @param SettingService $service
     * @return JsonResponse
     */
    public function updateByIdBuyer(SettingService $service): JsonResponse
    {
        $data = $service->updateByIdBuyer($this->request->post());
        return $this->json($data);

    }
}
