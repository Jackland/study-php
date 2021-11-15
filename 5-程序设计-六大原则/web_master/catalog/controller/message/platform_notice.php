<?php

use App\Catalog\Controllers\AuthController;
use App\Models\Customer\CustomerTip;
use App\Repositories\Message\StationLetterRepository;
use App\Models\Message\StationLetterCustomer;
use App\Enums\Common\YesNoEnum;
use App\Services\Customer\CustomerTipService;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ControllerMessagePlatformNotice
 *
 * @property ModelAccountGuide $model_account_guide
 * @property ModelNoticeNotice $model_notice_notice
 */
class ControllerMessagePlatformNotice extends AuthController
{
    public function index()
    {
        if (customer()->isPartner()) {
            return $this->redirect('customerpartner/message_center/my_message/notice');
        } else {
            return $this->redirect('account/message_center/platform_notice');
        }
    }

    /**
     * 获取用户登入时的首次弹窗提醒
     *
     * @return string
     * @throws Exception
     */
    public function listLoginRemind()
    {
        $this->load->model('account/guide');
        $country_id = $this->customer->getCountryId();
        $customerId = $this->customer->getId();
        $isPartner = $this->customer->isPartner();
        $identity = $isPartner ? '1' : '0';
        $data = [
            'error'    => 0,
            'msg'      => 'Success',
            'can_show' => false
        ];
        if ($this->customer->isLogged() && get_value_or_default($_COOKIE, 'login_flag', '') == 1) {
            //展示最新公告
            $this->load->model('notice/notice');
            // 100297 更换显示逻辑
            $new_notice = $this->model_notice_notice->listLoginRemind($customerId, $country_id, $identity)->toArray();
            // 获取对应需要弹窗的通知
            $stationLetterRepo = app(StationLetterRepository::class);
            $letter = $stationLetterRepo->getPopUpLetters($this->customer->getId())->toArray();
            if ($letter) {
                $new_notice = array_merge($new_notice, $letter);
            }

            //是否有新公告
            if ($new_notice) {
                $data['can_show'] = true;
                $data['new_notice'] = $new_notice;
                $data['confirm_url'] = $this->url->link('message/platform_notice/remindConfirm', '', true);
            }

        }
        $this->response->returnJson($data);
    }

    /**
     * 提醒确认按钮
     *
     * @throws Exception
     */
    public function remindConfirm()
    {
        $notice_id = $this->request->post('notice_id', 0);
        $type = $this->request->post('type');//1-已读  2-确认
        $customerId = $this->customer->getId();
        $messageType = $this->request->post('message_type', 0); // 0：公告 1：通知
        $this->load->model('notice/notice');
        $ret = false;
        if ($notice_id){
            if ($messageType == 0) {
                if ($type == 1) {
                    $ret = $this->model_notice_notice->batchRead($notice_id, 1);
                } elseif ($type == 2) {
                    $ret = $this->model_notice_notice->batchSure($customerId, [$notice_id]);
                }
            } else {
                $updateStatus =  StationLetterCustomer::where('customer_id', $customerId)
                    ->where('letter_id', $notice_id)
                    ->update(['is_read' => YesNoEnum::YES]);
                if ($updateStatus) {
                    $ret = true;
                }
            }
        }
        $return = [
            'status'      => $ret,
        ];
        $this->response->setOutput(json_encode($return));
    }

    /**
     * description:登记免税buyer的弹框记录
     * @return JsonResponse
     */
    public function createVatBuyerConfirm(): JsonResponse
    {
        if ((int)$this->customer->getId() && is_post()) {
            app(CustomerTipService::class)->insertCustomerTip($this->customer->getId(), 'vat_buyer_tips');
            return $this->jsonSuccess();
        }
        return $this->jsonFailed();
    }
}
