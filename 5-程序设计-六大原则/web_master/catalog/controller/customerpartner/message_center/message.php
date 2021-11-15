<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Forms\Message\DeletedForm;
use App\Catalog\Forms\Message\EstablishContactForm;
use App\Catalog\Forms\Message\MarkedForm;
use App\Catalog\Forms\Message\ReadForm;
use App\Catalog\Forms\Message\RecoverForm;
use App\Catalog\Forms\Message\RepliedForm;
use App\Catalog\Forms\Message\ReplyMsgForm;
use App\Catalog\Forms\Message\SellerSaveMsgForm;
use App\Catalog\Forms\Message\SetupAllMsgForm;
use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgCommonWordsStatus;
use App\Enums\Message\MsgCommonWordsTypeCustomerType;
use App\Enums\Message\MsgCustomerExtLanguageType;
use App\Listeners\Events\SendMsgMailEvent;
use App\Models\Buyer\Buyer;
use App\Models\CustomerPartner\BuyerGroup as CustomerPartnerBuyerGroup;
use App\Models\Message\MsgCommonWordsType;
use App\Models\Message\MsgCustomerExt;
use App\Repositories\Message\MessageDetailRepository;
use App\Repositories\Message\MessageRepository;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ControllerCustomerpartnerMessageCenterMessage extends AuthSellerController
{
    /**
     * buyer列表
     * @return string
     * @throws Exception
     */
    public function buyers()
    {
        $search['filter_name'] = request('filter_name', '');
        $search['filter_language'] = request('filter_language', '');
        $search['seller_id'] = customer()->getId();
        $search['page'] = request('page', 1);
        $search['pageSize'] = request('page_limit', 10);

        $customerPartnerBuyers = load()->model('customerpartner/buyers');
        $results = $customerPartnerBuyers->getList($search);

        $buyerIds = $results['data']->pluck('buyer_id')->toArray();
        $languageBuyerIdMap = MsgCustomerExt::query()->whereIn('customer_id', $buyerIds)->pluck('language_type', 'customer_id')->toArray();

        $num = ($search['page']-1)*$search['pageSize'];

        foreach ($results['data'] as $result) {
            $result->num = ++$num;
            $result->is_home_pickup = in_array($result->customer_group_id, COLLECTION_FROM_DOMICILE);
            $result->money_of_transaction = $this->currency->formatCurrencyPrice($result->money_of_transaction, session('currency'));
            $result->language = MsgCustomerExtLanguageType::getViewItems()[$languageBuyerIdMap[$result->buyer_id] ?? 0];
        }

        return $this->render('customerpartner/message_center/buyers', [
            'total' => $results['total'],
            'total_page' => intval(ceil($results['total'] / $search['pageSize'])),
            'rows' => $results['data'],
            'search' =>  $search,
        ]);
    }

    public function getAllBuyers()
    {
        $search['filter_name'] = request('filter_name', '');
        $search['filter_language'] = request('filter_language', '');
        $search['filter_date_from'] = request('filter_date_from', '');
        $search['filter_date_to'] = request('filter_date_to', '');
        $search['filter_buyer_group_id'] = request('filter_buyer_group_id', '');
        $search['seller_id'] = customer()->getId();
        $search['filter_is_all_select'] = 1;
        $customerPartnerBuyers = load()->model('customerpartner/buyers');
        $results = $customerPartnerBuyers->getList($search);

        return $this->jsonSuccess($results['data']);
    }

    /**
     * 新建站内信
     * @return string
     * @throws Throwable
     */
    public function new(): string
    {
        $receiverIds = request('receiver_ids', '');
        if (!empty($receiverIds)) {
            $data['buyers'] = Buyer::query()->alias('b')->joinRelations('customer as c')
                ->whereIn('b.buyer_id', explode(',', $receiverIds))
                ->get(['b.buyer_id', 'c.nickname'])
                ->toJson();

            if (empty(json_decode($data['buyers']))) {
                return $this->redirect(['common/home'])->send();
            }
        }

        $sku = request('item_code', '');
        $data['subject'] = $sku ? 'Item Code: ' . $sku : '';

        $data['customer_id'] = customer()->getId();
        $data['is_jump'] = request('is_jump', 0);
        $data['remain_send_count'] = app(MessageRepository::class)->getTodayRemainSendCount($data['customer_id']);
        $data['words_type'] = MsgCommonWordsType::queryRead()
            ->with(['words' => function ($query) { $query->where('status', MsgCommonWordsStatus::PUBLISHED)->orderByDesc('id'); }])
            ->whereIn('customer_type', MsgCommonWordsTypeCustomerType::getTypesByCustomer())
            ->where('is_deleted', YesNoEnum::NO)
            ->orderByDesc('sort')
            ->get();
        $data['buyer_group'] = CustomerPartnerBuyerGroup::query()
            ->where('seller_id', $data['customer_id'])
            ->where('status', YesNoEnum::YES)->select(['id', 'name'])
            ->get();
        $data['is_clicked_common_words'] = MsgCustomerExt::queryRead()->where('customer_id', $data['customer_id'])->value('common_words_description') ?: 0;

        // 上传文件的配置
        $uploadFileExt = app(MessageRepository::class)->uploadFileExt();

        return $this->render('customerpartner/message_center/new_message', array_merge($data, $uploadFileExt), 'seller');
    }

    /**
     * 保存站内信前的验证
     * @param SellerSaveMsgForm $saveMsgForm
     * @return JsonResponse
     */
    public function saveVerify(SellerSaveMsgForm $saveMsgForm): JsonResponse
    {
        try {
            $saveMsgForm->verify();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage(), [], $e->getCode());
        }

        return $this->jsonSuccess();
    }

    /**
     * 保存站内信
     * @param SellerSaveMsgForm $saveMsgForm
     * @return JsonResponse
     */
    public function save(SellerSaveMsgForm $saveMsgForm): JsonResponse
    {
        try {
            $saveMsgForm->save();
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage(), [], $e->getCode());
        }

        return $this->jsonSuccess();
    }

    /**
     * 回复站内信
     * @param ReplyMsgForm $replyMsgForm
     * @return JsonResponse
     */
    public function reply(ReplyMsgForm $replyMsgForm): JsonResponse
    {
        try {
            $replyMsgForm->reply();
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage(), [], $e->getCode());
        }

        return $this->jsonSuccess();
    }

    /**
     * 消息详情
     * @return string|RedirectResponse
     */
    public function detail()
    {
        $msgId = request('msg_id', '');
        if (empty($msgId)) {
            return $this->redirect(url('error/not_found'));
        }

        try {
            [$type, $data] = app(MessageDetailRepository::class)->getMessageDetail(intval($msgId), customer()->getId());
        } catch (Exception $e) {
            return $this->redirect(url('error/not_found'));
        }

        $currentRoute = 'customerpartner/message_center/message/detail';
        $prevUrl = $this->request->serverBag->get('HTTP_REFERER');
        if (is_null($prevUrl) || Str::contains($prevUrl, $currentRoute)) {
            $prevUrl = url()->previous('msg_detail');
        }
        $parsePrevUrlQuery = parse_url($prevUrl, PHP_URL_QUERY);
        parse_str($parsePrevUrlQuery, $query);
        $data['prev_route'] = $query['route'];
        $data['prev_url'] = $prevUrl;
        if ($data['prev_route'] != $currentRoute) {
            url()->remember($data['prev_url'], 'msg_detail');
        }

        $data['msg_id'] = $msgId;
        switch ($type) {
            case 'chat':
                return $this->render('customerpartner/message_center/common/detail/chat', $data, 'seller');
            case 'notice':
                return $this->render('customerpartner/message_center/common/detail/notice', $data, 'seller');
            default:
                return $this->redirect(url('error/not_found'));
        }
    }

    /**
     * 发送message邮件
     * @return JsonResponse
     */
    public function sendEmail(): JsonResponse
    {
        $msgId = request('msg_id', '');
        $receiverId = request('receiver_id', '');
        if (empty($msgId) || empty($receiverId)) {
            return $this->jsonFailed();
        }

        event(new SendMsgMailEvent($msgId, [$receiverId]));

        return $this->jsonSuccess();
    }


    /**
     * 处理标记 可批量
     * @param MarkedForm $markedForm
     * @return JsonResponse
     */
    public function handleMarked(MarkedForm $markedForm): JsonResponse
    {
        try {
            $markedForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 处理已读 可批量
     * @param ReadForm $readForm
     * @return JsonResponse
     */
    public function handleRead(ReadForm $readForm): JsonResponse
    {
        try {
            $readForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 处理是否回复 可批量
     * @param RepliedForm $repliedForm
     * @return JsonResponse
     */
    public function handleReplied(RepliedForm $repliedForm): JsonResponse
    {
        try {
            $repliedForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 处理删除 可批量
     * @param DeletedForm $deletedForm
     * @return JsonResponse
     */
    public function handleDeleted(DeletedForm $deletedForm): JsonResponse
    {
        try {
            $deletedForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 处理某些筛选条件下的所有
     * @param SetupAllMsgForm $setupAllMsgForm
     * @return JsonResponse
     */
    public function handleAllData(SetupAllMsgForm $setupAllMsgForm): JsonResponse
    {
        try {
            $setupAllMsgForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 处理回收站恢复
     * @param RecoverForm $recoverForm
     * @return JsonResponse
     */
    public function handleRecover(RecoverForm $recoverForm): JsonResponse
    {
        try {
            $recoverForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 回复建立联系
     * @param EstablishContactForm $establishContactForm
     * @return JsonResponse
     * @throws Throwable
     */
    public function establishContact(EstablishContactForm $establishContactForm): JsonResponse
    {
        try {
            $buyerId = $establishContactForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess(['buyer_id' => $buyerId]);
    }
}
