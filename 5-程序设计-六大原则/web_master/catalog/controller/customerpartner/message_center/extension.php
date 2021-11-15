<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Forms\Message\CommonWordsForm;
use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgCommonWordsTypeCustomerType;
use App\Enums\Message\MsgCustomerExtLanguageType;
use App\Models\Message\MsgCommonWordsType;
use App\Models\Message\MsgCustomerExt;
use App\Models\Setting\MessageSetting;
use App\Repositories\Message\MessageRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class ControllerCustomerpartnerMessageCenterExtension extends AuthSellerController
{
    /**
     * @return string
     */
    public function words()
    {
        $data['types'] = MsgCommonWordsType::query()
            ->whereIn('customer_type', MsgCommonWordsTypeCustomerType::getTypesByCustomer())
            ->where('is_deleted', YesNoEnum::NO)
            ->orderByDesc('sort')
            ->get();

        return $this->render('customerpartner/message_center/words', $data, 'seller');
    }

    /**
     * 报错常用语建议
     * @param CommonWordsForm $commonWordsForm
     * @return JsonResponse
     */
    public function saveSuggest(CommonWordsForm $commonWordsForm)
    {
        try {
            $commonWordsForm->save();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * @return string
     */
    public function language()
    {
        $data['language_type'] = MsgCustomerExt::query()->where('customer_id', customer()->getId())->value('language_type') ?: 0;

        return $this->render('customerpartner/message_center/language', $data, 'seller');
    }

    /**
     * 设置语言
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

    /**
     * 设置已读常用语提示弹框
     * @return JsonResponse
     */
    public function setMsgCommonWordsDescription()
    {
        MsgCustomerExt::query()->updateOrInsert(['customer_id' => customer()->getId()], ['common_words_description' => YesNoEnum::YES]);

        return $this->jsonSuccess();
    }

    /**
     * 保存配置
     * @return JsonResponse
     */
    public function saveSetting(): JsonResponse
    {
        $setting = $_POST;
        if (empty($setting)) {
            return $this->jsonFailed();
        }

        MessageSetting::query()->updateOrInsert(['customer_id' => customer()->getId()], $setting);

        return $this->jsonSuccess();
    }

    /**
     * 是否能发送信息
     * @return JsonResponse
     */
    public function isSendMsg(): JsonResponse
    {
        $isSendMsg = app(MessageRepository::class)->checkCustomerNewMsg(customer()->getId()) ? 1 : 0;

        return $this->jsonSuccess(['is_send_msg' => $isSendMsg]);
    }
}
