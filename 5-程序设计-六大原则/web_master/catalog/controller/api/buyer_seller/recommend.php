<?php

use App\Logging\Logger;
use App\Services\Buyer\BuyerSellerRecommendService;

class ControllerApiBuyerSellerRecommend extends ControllerApiBase
{
    private $recommendService;

    public function __construct(Registry $registry, BuyerSellerRecommendService $recommendService)
    {
        parent::__construct($registry);

        $this->recommendService = $recommendService;
    }

    public function index()
    {
        set_time_limit(0);

        $validator = $this->request->validate([
            'c' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            Logger::buyerSellerRecommend([__CLASS__, __FUNCTION__, '接口校验失败', $validator->errors()], 'warning');
            return $this->jsonFailed($validator->errors()->first());
        }

        $country = $this->request->get('c');

        try {
            $batchDate = $this->recommendService->recommend($country);
        } catch (Throwable $e) {
            Logger::buyerSellerRecommend([__CLASS__, __FUNCTION__, '接口失败', $e->getMessage()], 'error');
            return $this->jsonFailed('接口调用失败');
        }

        return $this->jsonSuccess(['batchDate' => $batchDate]);
    }
}
