<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Search\Message\SellerListSearch;
use App\Enums\Common\YesNoEnum;
use App\Enums\Message\CustomerComplaintBoxType;
use App\Models\Buyer\BuyerToSeller;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Services\Message\CustomerComplaintBoxService;

class ControllerAccountMessageCenterAddressBook extends AuthBuyerController
{
    private $customerId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = $this->customer->getId();
        $this->load->language('account/message_center/address_book');
    }

    public function index()
    {
        $search = new SellerListSearch($this->customerId);
        $data = $search->get($this->request->get());
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/address_book/index', $data, 'buyer');
    }

    /**
     * 修改Buyer控制合作状态
     *
     * @return JsonResponse
     */
    public function checkControlStatus()
    {
        $status = $this->request->post('status', 0);
        $sellerId = $this->request->post('seller_id', 0);
        if (! in_array($status, [YesNoEnum::NO, YesNoEnum::YES]) || ! $sellerId) {
            return $this->jsonFailed($this->language->get('text_invalid_request'));
        }

        $res = BuyerToSeller::where('buyer_id', $this->customerId)
            ->where('seller_id', $sellerId)
            ->update(['buyer_control_status' => $status]);

        if ($res) {
            return $this->jsonSuccess();
        }

        return $this->jsonFailed();
    }

    /**
     * 修改订阅Seller新品上架状态
     *
     * @return JsonResponse
     */
    public function checkSubscribedStatus()
    {
        $status = $this->request->post('status', 0);
        $sellerId = $this->request->post('seller_id', 0);
        if (! in_array($status, [YesNoEnum::NO, YesNoEnum::YES]) || ! $sellerId) {
            return $this->jsonFailed($this->language->get('text_invalid_request'));
        }

        $res = BuyerToSeller::where('buyer_id', $this->customerId)
            ->where('seller_id', $sellerId)
            ->update(['is_product_subscribed' => $status]);

        if ($res) {
            return $this->jsonSuccess();
        }

        return $this->jsonFailed();
    }

    /**
     * 投诉Seller||投诉消息
     *
     * @return JsonResponse
     */
    public function complain()
    {
        $sellerId = $this->request->post('seller_id', 0);
        $reason = $this->request->post('reason', '');
        $type = $this->request->post('type', 2);
        $msgId = $this->request->post('msg_id', 0);

        if (! in_array($type, CustomerComplaintBoxType::getValues()) || ($type == CustomerComplaintBoxType::MESSAGE && empty($msgId)) || ($type == CustomerComplaintBoxType::SELLER && empty($sellerId))) {
            return $this->jsonFailed($this->language->get('text_invalid_request'));
        }
        if (! $reason || mb_strlen($reason, 'utf8') > 500) {
            return $this->jsonFailed($this->language->get('text_complain_reason'));
        }

        $res = app(CustomerComplaintBoxService::class)->addComplain($this->customerId, $sellerId, $reason, $msgId, $type);
        if ($res) {
            return $this->jsonSuccess();
        }

        return $this->jsonFailed();
    }

    /**
     * 下载CSV文件
     */
    public function download()
    {
        $search = new SellerListSearch($this->customerId);
        $data = $search->get($this->request->get(), true);

        // CSV文件头
        $head = [
            $this->language->get('text_seller_store'),
            $this->language->get('column_main_category'),
            $this->language->get('column_number_transaction'),
            $this->language->get('column_total_transaction'),
            $this->language->get('column_Last_transaction_time'),
            $this->language->get('column_coop_status_seller'),
            $this->language->get('column_status'),
            $this->language->get('text_subscribed')
        ];

        // 文件内容
        $content = [];
        if ($data['list']) {
            foreach ($data['list'] as $item) {
                $content[] = [
                    $item['screenname'],
                    $item['main_cate_name'],
                    $item['number_of_transaction'],
                    $item['money_of_transaction'],
                    $item['last_transaction_time'],
                    $item['coop_status_seller'] ? $this->language->get('text_active') : $this->language->get('text_inactive'),
                    $item['coop_status_buyer'] ? $this->language->get('text_active') : $this->language->get('text_inactive'),
                    $item['is_product_subscribed'] ? $this->language->get('column_subscribed') : $this->language->get('column_unsubscribed'),
                ];
            }
        }
        $fileName = $this->language->get('text_download_csv_filename') . date('Ymd', time()) . '.csv';

        outputCsv($fileName, $head, $content, $this->session);
    }
}
