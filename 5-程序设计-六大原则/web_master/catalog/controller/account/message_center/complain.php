<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Models\Customer\CustomerComplaintBox;
use App\Catalog\Search\Message\ComplaintBoxSearch;
use App\Repositories\Message\MessageDetailRepository;

class ControllerAccountMessageCenterComplain extends AuthBuyerController
{
    private $customerId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = $this->customer->getId();
        $this->load->language('account/message_center/complain');
    }

    public function index()
    {
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/setting/my_complain/index', $data, 'buyer');
    }

    /**
     * 投诉列表
     */
    public function list()
    {
        $filters = [
            'status' => $this->request->get('status', 0)
        ];

        $search = new ComplaintBoxSearch($this->customerId);
        $data = $search->get($filters);

        return $this->render('account/message_center/setting/my_complain/list', $data);
    }

    /**
     * 投诉消息详情
     */
    public function detail()
    {
        $id = $this->request->get('id', '');

        $data = [];
        $complaint = CustomerComplaintBox::where('id', $id)
            ->where('complainant_id', $this->customerId)
            ->first();
        if ($complaint && $complaint->msg_id) {
            list($type, $data) = app(MessageDetailRepository::class)->getMessageDetail($complaint->msg_id, $this->customerId);
        }

        return $this->render('account/message_center/setting/my_complain/detail', $data);
    }
}
