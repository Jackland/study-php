<?php

use App\Catalog\Search\Message\InboxSearch;
use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveSendType;

/**
 * @property ModelMessageMessageSetting $model_message_messageSetting
 * @property ModelMessageMessage $model_message_message
 * @property ModelNoticeNotice $model_notice_notice
 * @property ModelStationLetterStationLetter $model_station_letter_station_letter
 * */
class ControllerCommonMessageWindow extends Controller
{
    /**
     * 站内信的弹窗通知
     * @return string
     * @throws Exception
     */
    public function index()
    {
        if (!$this->customer->isLogged()) {
            return '';
        }
        if (!$this->config->get('message_window_state')) {
            return '';
        }

        $this->load->model('message/messageSetting');
        $customerSetting = $this->model_message_messageSetting->getMessageSettingByCustomerId($this->customer->getId());
        $data['messageIntervalTime'] = intval($customerSetting['intervalTime']);
        return $this->load->view('common/message_window', $data);
    }

    public function messageState()
    {
        // 查询store消息未读数量
        $this->load->model('message/message');
        $this->load->model('notice/notice');
        $this->load->model('message/messageSetting');
        $this->load->model('station_letter/station_letter');
        $content = $this->getMessageContent();
        $this->response->returnJson(['content' => $content]);
    }


    private function getMessageContent()
    {
        $customerId = $this->customer->getId();
        // 获取用户配置
        $customerSetting = $this->model_message_messageSetting->getMessageSettingByCustomerId($customerId);
        $content = '';
        $where = [];
        $where2 = ['is_read' => 0];
        $letterSendTime = null;
        $url = $this->url->link('common/message_window/clickToView');
        // 如果存在这个时间，查询这个时间之后的消息
        if (isset($_COOKIE['current_message_time'])) {
            $where['create_time'] = $_COOKIE['current_message_time'];
            $where2['publish_date'] = $_COOKIE['current_message_time'];
            $letterSendTime = $_COOKIE['current_message_time'];
        }
        // 查询站内信未读数量
        if ($customerSetting['store'] && $this->model_message_message->unReadMessageCount($customerId, $where)) {
            $fromStr = $this->customer->isPartner() ? 'Buyer' : 'Seller';
            $count = $this->model_message_message->unReadMessageCount($customerId);
            $content .= '<p>You have received ' . $count . ' new ' . $fromStr . ' message(s). ' . '<a target="_blank" href="' . $url . '&type=1"> Click to view.</a></p>';
        }
        // 查询所有的system未读消息
        if ($customerSetting['system'] && $this->model_message_message->unReadAllSystemMessageCount($customerId, $where)) {
            $count = $this->model_message_message->unReadAllSystemMessageCount($customerId);
            $content .= '<p>You have received ' . $count . ' System Alert(s).  <a target="_blank" href="' . $url . '&type=2"> Click to view.</a></p>';
        }
        // 查询平台公告未读数
        if ($customerSetting['platformNotice'] && $this->model_notice_notice->countNoticeNew($where2)) {
            unset($where2['publish_date']);
            $count = $this->model_notice_notice->countNoticeNew($where2);
            $content .= '<p>You have received ' . $count . ' messages from Marketplace Notifications.  <a target="_blank"href="' . $url . '&type=3"> Click to view.</a></p>';
        }
        // 查询通知未读数
        if ($customerSetting['station_letter'] && $this->model_station_letter_station_letter->stationLetterCount($customerId, 0, $letterSendTime)) {
            $count = $this->model_station_letter_station_letter->stationLetterCount($customerId, 0);
            if($count > 0){
                //有才显示
                $content .= '<p>You have received ' . $count . ' Announcement message(s).  <a target="_blank"href="' . $url . '&type=5"> Click to view.</a></p>';
            }
        }
        // 查询平台客服站内信未读数
        if ($this->model_message_message->unReadTicketCount($customerId, $where)) {
            $count = $this->model_message_message->unReadTicketCount($customerId);
            $content .= '<p>You have received ' . $count . ' messages from customer service.  <a target="_blank" href="' . $url . '&type=4"> Click to view.</a></p>';
        }
        return $content;
    }

    public function clickToView()
    {
        setCookie('current_message_time', date('Y-m-d H:i:s'));
        switch ($this->request->get['type']) {
            case 1:
                $this->response->redirect($this->url->link('message/seller'));
                break;
            case 2:
                $this->response->redirect($this->url->link('message/system'));
                break;
            case 3:
                $this->response->redirect($this->url->link('message/platform_notice', ['category_index' => 1]));
                break;
            case 4:
                $this->response->redirect($this->url->link('account/ticket/lists'));
                break;
            case 5:
                $this->response->redirect($this->url->link('message/platform_notice', ['category_index' => 2]));
                break;
        }
    }

    public function closeWindow()
    {
        setCookie('current_message_time', date('Y-m-d H:i:s'));
    }

    public function getLatestMessages()
    {
        $messages = [];
        if (customer()->isLogged()) {
            $search = new InboxSearch(customer()->getId(), MsgReceiveSendType::PLATFORM_SECRETARY);
            $messages = $search->get( [
                'filter_delete_status' => MsgDeleteStatus::NOT_DELETED,
                'page' => 1,
                'page_limit' => request('page_limit', 4),
            ])['list'];

            $messages = array_map(function ($msg) {
                $msg['url'] = !customer()->isPartner() ? url()->to(['account/message_center/message/detail', 'msg_id' => $msg['msg_id']]) : url()->to(['customerpartner/message_center/message/detail', 'msg_id' => $msg['msg_id']]);
                return $msg;
            }, $messages);
        }

        return $this->jsonSuccess(['messages' => $messages]);
    }
}
