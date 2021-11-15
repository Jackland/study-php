<?php

use App\Catalog\Controllers\AuthController;
use Catalog\model\account\seller_bill\account_log;

class ControllerAccountSellerBillAccountLog extends AuthController
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    /**
     * 接口获取数据
     *
     */
    public function getAccountLogs()
    {
        $page = $this->request->query->get('page', 1);
        $pageLimit = $this->request->query->get('page_limit', 8);
        $logs = account_log::calculateSellerLogList($this->customer->getId(), $page, $pageLimit);
        $data = [
            'is_end' => ceil($logs['total'] / $pageLimit) <= $page,
            'html' => $this->load->view('account/seller_bill/common/log_info', ['logs' => $logs['list']]),
        ];

        return $this->response->json($data);
    }

}
