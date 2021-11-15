<?php

namespace App\Widgets;


use App\Repositories\Customer\CustomerTipRepository;
use Framework\Widget\Widget;

class VatBuyerPopupWidget extends Widget
{

    /**
     * 是否展示免税buyer的弹窗
     * @var bool
     */
    public $can_vat_buyer_show = false;

    /**
     * @inheritDoc
     */
    public function run()
    {
        if (!app(CustomerTipRepository::class)->checkCustomerTipExistsByTypeKey(
                (int)customer()->getId(), 'vat_buyer_tips') && customer()->isEuVatBuyer()) {
            $this->can_vat_buyer_show = true;
        }
        if (PhoneNeedVerifyNoticeWidget::isNeedNotice()) {
            $this->can_vat_buyer_show = false;
        }

        //以下页面不弹出
        if (in_array(request('route'), [
            'account/phone/verify', // 手机号验证页面
            'account/service_agreement', // 服务协议页面
            'account/service_agreement/index', // 服务协议页面
        ])) {
            $this->can_vat_buyer_show = false;
        }
        //修复 公告不弹出问题
        if (!$this->can_vat_buyer_show ) {
            return '';
        }

        return $this->getView()->render('@widgets/vat_buyer_popup', [
                'can_vat_buyer_show' => $this->can_vat_buyer_show,
            ]
        );
    }
}
