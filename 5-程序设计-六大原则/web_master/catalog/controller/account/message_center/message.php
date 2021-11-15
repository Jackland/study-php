<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Forms\Message\DeletedForm;
use App\Catalog\Forms\Message\MarkedForm;
use App\Catalog\Forms\Message\ReadForm;
use App\Catalog\Forms\Message\RecoverForm;
use App\Catalog\Forms\Message\RepliedForm;
use App\Catalog\Forms\Message\ReplyMsgForm;
use App\Catalog\Forms\Message\SetupAllMsgForm;
use App\Catalog\Search\Message\TrashSearch;
use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgCommonWordsStatus;
use App\Listeners\Events\SendMsgMailEvent;
use App\Repositories\Message\MessageDetailRepository;
use App\Repositories\Message\MessageRepository;
use App\Models\Message\MsgCommonWordsType;
use App\Enums\Message\MsgCommonWordsTypeCustomerType;
use App\Models\Message\MsgCustomerExt;
use App\Catalog\Forms\Message\BuyerSaveMsgForm;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Models\Customer\Customer;

class ControllerAccountMessageCenterMessage extends AuthBuyerController
{
    private $customerId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = $this->customer->getId();
        $this->load->language('account/message_center/message');
    }

    /**
     * 新建消息
     */
    public function new()
    {
        $receiverIds = request('receiver_ids', '');
        if (!empty($receiverIds)) {
            $data['seller'] = Customer::query()->alias('c')->joinRelations('seller as s')
                ->whereIn('c.customer_id', explode(',', $receiverIds))
                ->get(['c.customer_id', 's.screenname'])
                ->toJson();

            if (empty(json_decode($data['seller']))) {
                return $this->redirect(['common/home'])->send();
            }
        }
        $sku = request('item_code', '');
        $data['subject'] = $sku ? 'Item Code: ' . $sku : '';

        $data['customer_id'] = $this->customerId;

        $data['words_type'] = MsgCommonWordsType::queryRead()
            ->with(['words' => function ($query) { $query->where('status', MsgCommonWordsStatus::PUBLISHED)->orderByDesc('id'); }])
            ->whereIn('customer_type', MsgCommonWordsTypeCustomerType::getTypesByCustomer())
            ->where('is_deleted', YesNoEnum::NO)
            ->orderByDesc('sort')
            ->get();
        $data['is_clicked_common_words'] = MsgCustomerExt::queryRead()->where('customer_id', $this->customerId)->value('common_words_description') ?: 0;
        $data['remain_send_count'] = app(MessageRepository::class)->getTodayRemainSendCount($data['customer_id'], false);
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        // 上传文件的配置
        $uploadFileExt = app(MessageRepository::class)->uploadFileExt();

        return $this->render('account/message_center/new_message', array_merge($data, $uploadFileExt), 'buyer');
    }

    /**
     * 检测是否能够新建消息
     *
     * @return JsonResponse
     */
    public function checkCustomerNewMsg()
    {
        $status = app(MessageRepository::class)->checkCustomerNewMsg($this->customerId, 0) ? 1 : 0;

        return $this->jsonSuccess(['status' => $status]);
    }

    /**
     * 保存站内信前的验证
     *
     * @param BuyerSaveMsgForm $saveMsgForm
     * @return JsonResponse
     */
    public function saveVerify(BuyerSaveMsgForm $saveMsgForm)
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
     *
     * @param BuyerSaveMsgForm $saveMsgForm
     * @return JsonResponse
     */
    public function save(BuyerSaveMsgForm $saveMsgForm): JsonResponse
    {
        try {
            $saveMsgForm->save();
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage(), [], $e->getCode());
        }

        return $this->jsonSuccess();
    }

    /**
     * 处理回收站恢复
     *
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
     * 处理标记 可批量
     *
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
     * 处理某些筛选条件下的所有
     *
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
     * 处理是否回复 可批量
     *
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
     * 处理已读 可批量
     *
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
     * 处理删除 可批量
     *
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
     * 回复站内信
     *
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
     *
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

        $currentRoute = 'account/message_center/message/detail';
        $prevUrl = $this->request->serverBag->get('HTTP_REFERER');
        if (is_null($prevUrl) || Str::contains($prevUrl, $currentRoute)) {
            $prevUrl = url()->previous('msg_detail');
        }
        $parsePrevUrlQuery = parse_url($prevUrl, PHP_URL_QUERY);
        parse_str($parsePrevUrlQuery, $query);
        $data['prev_route'] = $query['route'];
        $data['prev_url'] = $prevUrl;
        $data['msg_type'] = isset($query['msg_type']) ? $query['msg_type'] : '';
        if ($data['prev_route'] != $currentRoute) {
            url()->remember($data['prev_url'], 'msg_detail');
        }

        $data['msg_id'] = $msgId;
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        switch ($type) {
            case 'chat':
                return $this->render('account/message_center/common/detail/chat', $data, 'buyer');
            case 'notice':
                return $this->render('account/message_center/common/detail/notice', $data, 'buyer');
            default:
                return $this->redirect(url('error/not_found'));
        }
    }

    /**
     * 回收站
     */
    public function trash()
    {
        $search = new TrashSearch();
        $data = $search->get($this->request->query->all());
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/trash', $data, 'buyer');
    }
}
