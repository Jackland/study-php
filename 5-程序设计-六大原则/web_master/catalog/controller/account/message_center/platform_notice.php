<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Forms\Message\Notice\DeletedForm as NoticeDeletedForm;
use App\Catalog\Forms\Message\Notice\MarkedForm as NoticeMarkedForm;
use App\Catalog\Forms\Message\Notice\ReadForm as NoticeReadForm;
use App\Catalog\Search\Message\NoticeSearch;
use App\Models\Setting\Dictionary;
use App\Repositories\Message\NoticeRepository;
use App\Repositories\Message\StationLetterRepository;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Logging\Logger;
use Symfony\Component\HttpClient\HttpClient;
use App\Catalog\Forms\Message\Notice\SureForm;

class ControllerAccountMessageCenterPlatformNotice extends AuthBuyerController
{
    private $customerId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = $this->customer->getId();
        $this->load->language('account/message_center/platform_notice');
    }

    /**
     * 处理已读 可批量
     * @param NoticeReadForm $readForm
     * @return JsonResponse
     */
    public function handleRead(NoticeReadForm $readForm): JsonResponse
    {
        try {
            $readForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 处理确认
     * @param NoticeSureForm $readForm
     * @return JsonResponse
     */
    public function handleSure(SureForm $readForm): JsonResponse
    {
        try {
            $readForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 删除
     * @param NoticeDeletedForm $deletedForm
     * @return JsonResponse
     */
    public function handleDeleted(NoticeDeletedForm $deletedForm): JsonResponse
    {
        try {
            $deletedForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 处理标记 可批量
     * @param NoticeMarkedForm $markedForm
     * @return JsonResponse
     */
    public function handleMarked(NoticeMarkedForm $markedForm): JsonResponse
    {
        try {
            $markedForm->handle();
        } catch (Exception $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 通知&公告 列表
     */
    public function index()
    {
        $search = new NoticeSearch();
        $filters['filter_type_id'] = request('filter_type_id') ?? request('tab', 0);
        $filters['filter_delete_status'] = 0;
        $data = $search->get(array_merge($this->request->query->all(), $filters));

        //获取顶部统计数据
        $data['unread_notice_count'] = app(NoticeRepository::class)->getNewNoticeCount($this->customerId, customer()->getCountryId(), 0);
        $data['unread_station_letter_count'] = app(StationLetterRepository::class)->getNewStationLetterCount($this->customerId);

        //公告类型
        $data['platform_type'] = Dictionary::queryRead()->where('DicCategory', 'PLAT_NOTICE_TYPE')->pluck('DicValue', 'DicKey')->toArray();

        //通知类型
        $data['letter_type'] = Dictionary::queryRead()->where('DicCategory', 'STATION_LETTER_TYPE')->pluck('DicValue', 'DicKey')->toArray();

        $data['tab'] = request('tab', 0);
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/platform_notice/index', $data, 'buyer');
    }

    /**
     * 通知 & 公告 详情
     *
     * @return string|RedirectResponse
     */
    public function view()
    {
        $noticeId = request('notice_id', '');
        $type = request('type', '');
        if (empty($noticeId) && !in_array($type, ['notice', 'station_letter'])) {
            return $this->redirect(url('error/not_found'));
        }

        $search = new NoticeSearch();
        $notice = $search->getNoticeById($noticeId, $type == 'notice' ? 1 : 2);
        if (empty($notice)) {
            return $this->redirect(url('error/not_found'));
        }

        $data = [
            'msg_id' => $notice->notice_id,
            'msg_receive_id' => 0,
            'subject' => $notice->title,
            'post_time' => $notice->publish_date,
            'content' => $notice->content,
            'sender' => 'Marketplace',
            'is_send_email' => app(NoticeRepository::class)->isSendEmail($notice->type_id, customer()->getId()),
            'receiver_id' => customer()->getId(),
            'delete_status' => $notice->delete_status,
            'make_sure_status' => $notice->make_sure_status,
            'p_make_sure_status' => $notice->p_make_sure_status,
            'data_model' => $notice->data_model,
            'attachments' => [],
        ];

        if ($type == 'station_letter') {
            $attachments = app(StationLetterRepository::class)->getStationLetterAttachments($noticeId);
            foreach ($attachments as $attachment) {
                $data['attachments'][] = [
                    'url' => url(['message/station_letter/download', 'filename' => $attachment['url'], 'maskname' => $attachment['file_name']]),
                    'name' => $attachment['file_name'],
                ];
            }
        }

        // 标记已读
        $form = new NoticeReadForm(['notices' => [['id' => $noticeId, 'type' => $type]], 'is_read' => 1]);
        try {
            $form->handle();
        } catch (Exception $e) {
            return $this->redirect(url('error/not_found'));
        }

        $currentRoute = 'account/message_center/platform_notice/view';
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

        $data['msg_id'] = $noticeId;
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/common/detail/notice', $data, 'buyer');
    }

    /**
     * 发送邮件
     * @return JsonResponse
     */
    public function sendEmail(): JsonResponse
    {
        $noticeId = request('notice_id', '');
        $type = request('type', '');
        if (empty($noticeId) && !in_array($type, ['notice', 'station_letter'])) {
            return $this->jsonFailed();
        }

        $search = new NoticeSearch();
        $notice = $search->getNoticeById($noticeId, $type == 'notice' ? 1 : 2);
        if (empty($notice) || $notice->delte_status != 0) {
            return $this->jsonFailed();
        }

        $emails = app(NoticeRepository::class)->getSendEmails(intval($notice->type_id), [customer()->getId()]);
        if (empty($emails)) {
            return $this->jsonFailed();
        }

        $data['body'] = $notice->content;
        $data['subject'] = '[From GIGACLOUD]' . $notice->title;
        $data['to'] = $emails;

        if ($type == 'station_letter') {
            $attachments = app(StationLetterRepository::class)->getStationLetterAttachments($noticeId);
            foreach ($attachments as $key => $attachment) {
                $data['attach'][$key]['url'] = url(['message/station_letter/download', 'filename' => $attachment['url'], 'maskname' => $attachment['file_name']]);
                $data['attach'][$key]['name'] = $attachment['file_name'];
            }
            $data['view_type'] = 2; //使用不改变img的模板
        }

        try {
            $client = HttpClient::create();
            $url = URL_TASK_WORK . '/api/email/send';
            $client->request('POST', $url, [
                'body' => $data,
            ]);
        } catch (\Throwable $e) {
            Logger::error('message send mail error:' . $e->getMessage());
            return $this->jsonFailed();
        }

        return $this->jsonSuccess();
    }
}
