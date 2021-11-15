<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Enums\Message\MsgCustomerExtLanguageType;
use App\Models\Message\MsgCustomerExt;
use Symfony\Component\HttpFoundation\JsonResponse;

class ControllerAccountMessageCenterLanguage extends AuthBuyerController
{
    private $customerId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = $this->customer->getId();
        $this->load->language('account/message_center/language');
    }

    public function index()
    {
        $data['language_type'] = MsgCustomerExt::query()->where('customer_id', $this->customerId)->value('language_type') ?: 0;
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/setting/language', $data, 'buyer');
    }

    /**
     * 设置语言
     *
     * @return JsonResponse
     */
    public function setLanguage()
    {
        $type = request('type', 0);
        if (!in_array($type, MsgCustomerExtLanguageType::getValues())) {
            return $this->jsonFailed();
        }

        MsgCustomerExt::query()->updateOrInsert(['customer_id' => customer()->getId()], ['language_type' => $type]);

        return $this->jsonSuccess();
    }

}
