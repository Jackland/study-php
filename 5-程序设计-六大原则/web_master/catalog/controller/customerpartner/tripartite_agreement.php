<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Enums\Common\YesNoEnum;
use App\Enums\Tripartite\TripartiteAgreementRequestStatus;
use App\Enums\Tripartite\TripartiteAgreementRequestType;
use App\Enums\Tripartite\TripartiteAgreementStatus;
use App\Models\Tripartite\TripartiteAgreement;
use App\Repositories\Seller\SellerRepository;
use App\Repositories\Tripartite\AgreementRepository;
use App\Services\TripartiteAgreement\AgreementRequestService;
use App\Services\TripartiteAgreement\AgreementService;
use App\Widgets\VATToolTipWidget;
use Carbon\Carbon;
use Framework\DataProvider\QueryDataProvider;
use Framework\Exception\Http\NotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;

class ControllerCustomerpartnerTripartiteAgreement extends AuthSellerController
{
    private $sellerId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->sellerId = customer()->getId();
    }

    /**
     * 协议列表
     * @return string
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function index()
    {
        $dataProvider = new QueryDataProvider(
            TripartiteAgreement::queryRead()->where('seller_id', $this->sellerId)->where('is_deleted', YesNoEnum::NO)
                ->where(function ($q) {$q->whereIn('status', TripartiteAgreementStatus::pendingAndRejectedStatus())->orWhereNotNull('seller_approved_time');})
                ->with(['buyer', 'requests', 'buyer.buyer'])
                ->when(!empty(request('name', '')), function ($q) {$q->where('title', 'like', '%' . trim(request('name')) . '%');})
                ->when(!empty(trim(request('buyer', ''))), function ($q) {
                    $q->whereHas('buyer', function ($query) {
                        $search = trim(request('buyer', ''));
                        $query->where('nickname', 'like', '%' . $search . '%')->orWhere('user_number', 'like', '%' . $search . '%');
                    });
                })
                ->when(!empty(request('status', '')), function ($q) {$q->where('status', request('status'));})
                ->orderByRaw('FIELD (`status`, ' . join(',', TripartiteAgreementStatus::sellerOrderStatus()) . ') ASC')
                ->orderByDesc('id')
        );
        $dataProvider->setPaginator(['defaultPageSize' => 10]);
        $data = array(
            'list' => $dataProvider->getList()->map(function (TripartiteAgreement $q) {
                    $q['terminate_handle_request'] = collect($q->requests)
                        ->where('handle_id', $this->sellerId)
                        ->where('status', TripartiteAgreementRequestStatus::PENDING)
                        ->where('type', TripartiteAgreementRequestType::TERMINATE)
                        ->last();
                    $q['cancel_handle_request'] = collect($q->requests)
                        ->where('handle_id', $this->sellerId)
                        ->where('status', TripartiteAgreementRequestStatus::PENDING)
                        ->where('type', TripartiteAgreementRequestType::CANCEL)
                        ->last();
                    $q['terminate_send_request'] = collect($q->requests)
                        ->where('sender_id', $this->sellerId)
                        ->where('status', TripartiteAgreementRequestStatus::PENDING)
                        ->where('type', TripartiteAgreementRequestType::TERMINATE)
                        ->last();
                    $q['cancel_send_request'] = collect($q->requests)
                        ->where('sender_id', $this->sellerId)
                        ->where('status', TripartiteAgreementRequestStatus::PENDING)
                        ->where('type', TripartiteAgreementRequestType::CANCEL)
                        ->last();

                    $q->buyer->setAppends(array('buyer_type'));
                    $q->seven_remind = 0;
                    if ($q->status == TripartiteAgreementStatus::ACTIVE) {
                        $days = app(AgreementRepository::class)->diffDays(Carbon::now(), $q->terminate_time);
                        $q->seven_remind = $days <= 7 ? $days : 0;
                    }
                    $q->canHandle(false);
                    $q['ex_vat'] = VATToolTipWidget::widget(['customer' => $q->buyer])->render();
                    return $q;
                })->toArray(),
            'paginator' => $dataProvider->getPaginator(),
            'total' => $dataProvider->getTotalCount(),
            'filter_status' => TripartiteAgreementStatus::sellerOrderViewItems(),
            'country' => session('country'),
            'search' => array(
                'name' => request('name', ''),
                'buyer' => request('buyer', ''),
                'status' => request('status', ''),
            ),
            'buyers' => app(SellerRepository::class)->getBuyersSimpleInfoBySellerId($this->sellerId),
        );

        return $this->render('customerpartner/tripartite_agreement/index', $data, 'seller');
    }

    /**
     * 协议详情
     * @return string
     * @throws NotFoundException
     */
    public function detail()
    {
        /** @var TripartiteAgreement $agreement */
        $agreement = TripartiteAgreement::queryRead()->with(['buyer', 'seller', 'requests', 'buyer.buyer'])
            ->where('id', request('id'))
            ->where('seller_id', $this->sellerId)
            ->where('is_deleted', YesNoEnum::NO)
            ->first();
        if (empty($agreement)) {
            throw new NotFoundException();
        }
        $agreement->buyer->setAppends(array('buyer_type'));
        $data['agreement'] = app(AgreementRepository::class)->getAgreementDetail($agreement, $this->sellerId)->toArray();
        $data['ex_vat'] = VATToolTipWidget::widget(['customer' => $agreement->buyer])->render();
        $data['country'] = session('country');
        $data['current_time'] = Carbon::now()->toDateTimeString();
        
        return $this->render('customerpartner/tripartite_agreement/detail', $data, 'seller');
    }

    /**
     * 下载
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Framework\Exception\Exception
     */
    public function download()
    {
        return app(AgreementService::class)->downloadAgreement(request('id'), $this->sellerId);
    }

    /**
     * 审核通过
     * @return JsonResponse
     */
    public function approve(): JsonResponse
    {
        $id = request()->post('id', '');
        $message = request()->post('message', '');
        $replaceStr = request()->post('replace_value', '');
        $templateId = request()->post('template_id', '');
        if (empty($id) || empty($message) || empty($replaceStr) || empty($templateId)) {
            return $this->jsonFailed();
        }

        $agreement = TripartiteAgreement::query()->findOrFail($id);
        if ($agreement->status != TripartiteAgreementStatus::TO_BE_SIGNED || $this->sellerId != $agreement->seller_id) {
            return $this->jsonFailed('The agreement has been updated, please refresh the page and try again.');
        }
        if ($agreement->template_id != $templateId || md5($agreement->template_replace_value) != $replaceStr) {
            return $this->jsonFailed('The agreement has been updated, please refresh the page and try again.');
        }
        if (Carbon::now()->subDay()->gt($agreement->effect_time)) {
            return $this->jsonFailed('The time to process the agreement has been expired.');
        }

        try {
            app(AgreementService::class)->approveAgreement($agreement, $this->sellerId, $message, customer()->getCountryId());
        } catch (Throwable $e) {
            if ($e->getCode() == 401) {
                return $this->jsonFailed('Your company information is not complete. Please contact the Marketplace customer service to perfect your information.');
            }
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 拒绝
     * @return JsonResponse
     */
    public function reject(): JsonResponse
    {
        $id = request()->post('id', '');
        $message = request()->post('message', '');
        if (empty($id)) {
            return $this->jsonFailed();
        }

        try {
            app(AgreementService::class)->rejectAgreement(intval($id), $this->sellerId, $message);
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }

    /**
     * 取消申请
     * @return JsonResponse
     */
    public function cancelRequest(): JsonResponse
    {
        $id = request()->post('id', '');
        $message = request()->post('message', '');
        if (empty($id)) {
            return $this->jsonFailed();
        }
        if (empty($message)) {
            return $this->jsonFailed('Please enter the Reason for cancel');
        }

        try {
            app(AgreementRequestService::class)->cancelRequest(intval($id), $this->sellerId, $message, customer()->getCountryId());
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage() ?: 'Failed.');
        }

        return $this->jsonSuccess([], 'The cancellation request has been submitted and will be processed by Buyer.');
    }

    /**
     * 终止申请
     * @return JsonResponse
     */
    public function terminateRequest(): JsonResponse
    {
        $id = request()->post('id', '');
        $time = request()->post('time', '');
        $message = request()->post('message', '');
        if (empty($time)) {
            return $this->jsonFailed('Please enter the Termination Time');
        }
        if (empty($message)) {
            return $this->jsonFailed('Please enter the Reason for Termination');
        }
        if (empty($id) || empty($time) || !preg_match('/^[1-9]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])\s+(20|21|22|23|[0-1]\d):59:59$/', $time)) {
            return $this->jsonFailed();
        }

        try {
            app(AgreementRequestService::class)->terminateRequest(intval($id), $this->sellerId, $time, $message);
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage() ?: 'Failed.');
        }

        return $this->jsonSuccess([], 'The termination request has been submitted and will be processed by Buyer.');
    }

    /**
     * 处理请求
     * @return JsonResponse
     */
    public function handleRequest(): JsonResponse
    {
        $id = request()->post('request_id', '');
        $message = request()->post('message', '');
        $type = request()->post('type', '');
        if (empty($id)) {
            return $this->jsonFailed();
        }

        try {
            switch ($type) {
                case 'approve_cancel':
                    app(AgreementRequestService::class)->agreeCancel(intval($id), $this->sellerId, $message);
                    break;
                case 'reject_cancel':
                    app(AgreementRequestService::class)->rejectCancel(intval($id), $this->sellerId, $message);
                    break;
                case 'approve_terminate':
                    app(AgreementRequestService::class)->agreeTerminate(intval($id), $this->sellerId, $message);
                    break;
                case 'reject_terminate':
                    app(AgreementRequestService::class)->rejectTerminate(intval($id), $this->sellerId, $message);
                    break;
                default:
                    return $this->jsonFailed();
            }

        } catch (Throwable $e) {
            return $this->jsonFailed();
        }

        return $this->jsonSuccess();
    }

}
