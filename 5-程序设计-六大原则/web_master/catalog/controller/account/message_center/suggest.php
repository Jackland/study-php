<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Forms\Message\CommonWordsForm;
use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgCommonWordsTypeCustomerType;
use App\Models\Message\MsgCommonWordsType;
use App\Models\Message\MsgCustomerExt;
use Symfony\Component\HttpFoundation\JsonResponse;

class ControllerAccountMessageCenterSuggest extends AuthBuyerController
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->language('account/message_center/suggest');
    }

    public function index()
    {
        $data['types'] = MsgCommonWordsType::query()
            ->whereIn('customer_type', MsgCommonWordsTypeCustomerType::getTypesByCustomer())
            ->where('is_deleted', YesNoEnum::NO)
            ->orderByDesc('sort')
            ->get();
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/setting/suggest', $data, 'buyer');
    }


    /**
     * 设置已读常用语提示弹框
     */
    public function setMsgCommonWordsDescription()
    {
        MsgCustomerExt::query()->updateOrInsert(['customer_id' => customer()->getId()], ['common_words_description' => YesNoEnum::YES]);

        return $this->jsonSuccess();
    }

    /**
     * 保存常用语建议
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
}
