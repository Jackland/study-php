<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Enums\Buyer\BuyerType;
use App\Enums\Customer\CustomerScoreDimension;
use App\Models\Buyer\BuyerSellerRecommend;
use App\Repositories\Buyer\BuyerRepository;
use App\Repositories\Buyer\BuyerToSellerRepository;
use App\Repositories\Buyer\BuyerUserPortraitRepository;
use App\Repositories\Customer\CustomerScoreDimensionRepository;
use App\Services\Buyer\BuyerSellerRecommendService;
use Framework\Exception\Http\NotFoundException;

class ControllerCustomerpartnerSellerCenterRecommend extends AuthSellerController
{
    // 推荐详情
    public function detail()
    {
        $data = [];

        $data['recommend'] = BuyerSellerRecommend::query()
            ->where('seller_id', $this->customer->getId())
            ->where('id', $this->request->get('id'))
            ->first();
        if (!$data['recommend']) {
            throw new NotFoundException();
        }
        $sellerId = $this->customer->getId();
        $buyerId = $data['recommend']->buyer_id;
        // 累加访问次数
        app(BuyerSellerRecommendService::class)->increaseSellerViewCount($data['recommend']->id);

        $buyerRepo = app(BuyerRepository::class);
        $buyerToSellerRepo = app(BuyerToSellerRepository::class);
        $buyerUserPortraitRepo = app(BuyerUserPortraitRepository::class);
        $customerScoreDimensionRepo = app(CustomerScoreDimensionRepository::class);

        $buyer = $data['recommend']->buyer;
        $buyer->performance_score = $buyer->valid_performance_score;
        $buyer->performance_score = $buyer->performance_score ? round($buyer->performance_score, 2) : '--';
        $data['buyer'] = $buyer;
        $data['buyerCustomer'] = $data['recommend']->buyerCustomer;
        $userPortrait = $buyer->userPortrait;
        $data['userPortrait'] = $userPortrait;
        $data['userPortraitFormatted'] = $buyerUserPortraitRepo->formatUserPortrait($userPortrait, [
            'monthly_sales_count' => 'monthly_sales',
            'return_rate' => 'return_rate',
            'complex_complete_rate' => 'complex_complete',
            'first_order_date' => 'first_order',
            'main_category_id' => 'main_category',
        ]);
        $data['extra'] = [
            'buyer_type' => BuyerType::getDescription($buyerRepo->getTypeById($buyerId)), // 帐号类型
            'is_connected' => $buyerToSellerRepo->isConnected($sellerId, $buyerId), // 是否建议联系
            'last_message_date' => $buyerToSellerRepo->getLastConnectMessageDate($sellerId, $buyerId), // 最近一次消息来往日期
            'last_transaction_date' => $buyerToSellerRepo->getLastCompleteTransactionOrderDate($sellerId, $buyerId), // 最近一次交易日期
            'platform_active' => !$buyer->score_task_number ? 'N/A' : $customerScoreDimensionRepo->getDimensionScore(
                $buyer->score_task_number, $buyer->buyer_id,
                CustomerScoreDimension::BUYER_ACTIVE_ONLINE, true
            ), // 平台活跃度
        ];

        return $this->render('customerpartner/seller_center/recommend_detail', $data, 'seller');
    }

    // 不感兴趣
    public function notInterest()
    {
        $validator = $this->request->validate([
            'id' => 'required|numeric',
            'reason' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $recommend = BuyerSellerRecommend::find($this->request->post('id'));
        if ($recommend->seller_id != $this->customer->getId()) {
            return $this->jsonFailed('seller not match');
        }
        app(BuyerSellerRecommendService::class)->markNotInterest($recommend, $this->request->post('reason'));
        return $this->jsonSuccess();
    }
}
