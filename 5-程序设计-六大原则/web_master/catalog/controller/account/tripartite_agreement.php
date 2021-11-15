<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Search\Tripartite\AgreementSearch;
use App\Repositories\Tripartite\AgreementRepository;
use App\Catalog\Forms\Tripartite\BuyerTripartiteForm;
use App\Repositories\Buyer\BuyerToSellerRepository;
use App\Enums\Tripartite\TripartiteAgreementStatus;
use App\Services\TripartiteAgreement\AgreementRequestService;
use App\Services\TripartiteAgreement\AgreementService;
use Carbon\Carbon;

class ControllerAccountTripartiteAgreement extends AuthBuyerController
{

    /**
     * description:采销协议-列表页
     * @return string
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function index(AgreementRepository $repository)
    {
        $search = (new AgreementSearch($this->customer->getId()));
        $dataProvider = $search->search($this->request->query->all());
        $data = [
            'list' => $repository->getHandleList($dataProvider->getList()),
            'paginator' => $dataProvider->getPaginator(),
            'total' => $dataProvider->getTotalCount(),
            'filter_status' => TripartiteAgreementStatus::buyerOrderViewItems(),
            'country' => session('country'),
            'search' => $search->getSearchData(),
            'customerId' => $this->customer->getId()
        ];
        return $this->render('account/tripartite_agreement/index', $data, 'buyer');
    }

    /**
     * description:采销协议-详情页
     * @param AgreementRepository $repository
     * @return string
     */
    public function detail(AgreementRepository $repository)
    {
        $data = $repository->getDetail($this->request->get(), $this->customer->getId());
        if (!$data) {
            return $this->redirect(['account/tripartite_agreement']);
        }
        $data['current_time'] = Carbon::now()->toDateTimeString();
        $data['customerId'] = $this->customer->getId();
        $data['country'] = session('country');

        return $this->render('account/tripartite_agreement/detail', $data, 'buyer');
    }

    /**
     * description:采销协议-添加buyer采购协议
     * @param BuyerTripartiteForm $form
     * @return string
     * @throws Throwable
     */
    public function create(BuyerTripartiteForm $form, AgreementRepository $repository)
    {
        $customerId = $this->customer->getId();
        $agreement_id = $this->request->get('agreement_id', 0);
        $renew = $this->request->get('renew', 0);
        if (is_post()) {
            $data = $form->save(['customerId' => $this->customer->getId(), 'renew' => $renew]);
            if ($data['code'] == 200) {
                return $this->jsonSuccess([], 'The agreement has been submitted successfully');
            } else {
                return $this->jsonFailed($data['msg'],[],$data['code']);
            }
        }

        if ($agreement_id) {
            $info = $repository->getDetail($this->request->get(), $customerId);
            if (!$info) {
                return $this->redirect(['account/tripartite_agreement']);
            }

            //不在编辑状态和续签状态不能进页面
            if ($renew != 1 && $info['can_tripartite_edit'] === false) {
                return $this->redirect(['account/tripartite_agreement/detail', 'agreement_id' => $agreement_id]);
            }
            if ($renew === 1 && $info['can_tripartite_renewal'] === false) {
                return $this->redirect(['account/tripartite_agreement/detail', 'agreement_id' => $agreement_id]);
            }

            $data = [
                'template_list' => [],
                'info' => $info,
                'sellerList' => app(BuyerToSellerRepository::class)
                    ->connectedSeller($this->customer->getId(), ['seller_ids' => [$info['seller_id']]])
            ];
        } else {
            $data = [
                'template_list' => $repository->getListTemplates($this->customer->getId()),
                'info' => [],
                'sellerList' => app(BuyerToSellerRepository::class)->connectedSeller($this->customer->getId(), $this->request->get())
            ];
        }
        $data['renew'] = $renew;
        $data['country'] = session('country');
        return $this->render('account/tripartite_agreement/create', $data, 'buyer');
    }

    /**
     * description:模糊匹配和buyer 已经签署的店铺和 签约的协议名称
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getSearchVagueList(AgreementRepository $repository)
    {
        if (is_post()) {
            $data = $repository->getSearchVagueList($this->request->post());
            return $this->jsonSuccess($data);
        }
        return $this->jsonFailed('No access.');
    }

    /**
     * description:buyer审核seller的请求
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Throwable
     */
    public function review(AgreementRequestService $agreementRequestService)
    {
        $id = request()->post('request_id', '');
        $message = request()->post('reason', '');
        $handle = request()->post('handle', '');
        $handleType = request()->post('handle_type', '');
        if (empty($id) || !in_array($handle, [1, 2]) || !in_array($handleType, [1, 2])) {
            return $this->jsonFailed();
        }

        try {
            switch ($handle) {
                case 1:
                    if ($handleType == 1) {
                        $agreementRequestService->agreeTerminate(intval($id), customer()->getId(), $message);
                    } else {
                        $agreementRequestService->rejectTerminate(intval($id), customer()->getId(), $message);
                    }
                    break;
                case 2:
                    if ($handleType == 1) {
                        $agreementRequestService->agreeCancel(intval($id), customer()->getId(), $message);
                    } else {
                        $agreementRequestService->rejectCancel(intval($id), customer()->getId(), $message);
                    }
                    break;
            }
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess();
    }


    /**
     * description:删除协议
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function delete(AgreementService $service)
    {
        if (is_post()) {
            $data = $service->delete($this->request->post(), $this->customer->getId());
            if ($data['code'] == 200) {
                return $this->jsonSuccess([], 'The agreement has been deleted successfully');
            } else {
                return $this->jsonFailed($data['msg']);
            }
        }
        return $this->jsonFailed('No access.');
    }

    /**
     * description:终止协议
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Throwable
     */
    public function termination(AgreementRequestService $agreementRequestService)
    {
        $id = request()->post('agreement_id', '');
        $time = request()->post('terminate_time', '');
        $message = request()->post('reason', '');
        if (empty($time)) {
            return $this->jsonFailed('Please enter the Termination Time');
        }
        if (empty($message)) {
            return $this->jsonFailed('Please enter the Reason for Termination');
        }
        if (empty($id) || !preg_match('/^[1-9]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])\s+(20|21|22|23|[0-1]\d):59:59$/', $time)) {
            return $this->jsonFailed();
        }

        try {
            $agreementRequestService->terminateRequest(intval($id), $this->customer->getId(), $time, $message);
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage() ?: 'Failed.');
        }

        return $this->jsonSuccess([], 'The termination request has been submitted and will be processed by Seller.');
    }

    /**
     * description:操作采销协议内容
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Throwable
     */
    public function cancel(AgreementRequestService $agreementRequestService, AgreementRepository $repository)
    {
        $id = request()->post('agreement_id', '');
        $message = request()->post('reason', '');
        $status = request()->post('status', 0);
        if (empty($id)) {
            return $this->jsonFailed('The agreement has been updated, please refresh the page and try again.');
        }

        //如何不在协议中 直接取消
        $info = $repository->getDetail(['agreement_id' => $id], $this->customer->getId());
        if (empty($info) || $info['can_tripartite_cancel'] === false || $status != $info['status']) {
            return $this->jsonFailed('The agreement has been updated, please refresh the page and try again.');
        }

        if ($info['status'] != TripartiteAgreementStatus::TO_BE_ACTIVE) {
            try {
                $agreementRequestService->generalCancel(intval($id), $this->customer->getId());
            } catch (Throwable $e) {
                return $this->jsonFailed($e->getMessage() ?: 'Failed.');
            }
            return $this->jsonSuccess([], 'The agreement has been canceled successfully');
        }

        if (empty($message)) {
            return $this->jsonFailed('Please enter the Reason for cancel');
        }

        try {
            $agreementRequestService->cancelRequest(intval($id), $this->customer->getId(), $message, customer()->getCountryId());
        } catch (Throwable $e) {
            return $this->jsonFailed($e->getMessage() ?: 'Failed.');
        }

        return $this->jsonSuccess([], 'The cancellation request has been submitted and will be processed by Seller.');
    }

    /**
     * description:下载协议
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Framework\Exception\Exception
     */
    public function downloadFile(AgreementRepository $repository)
    {
        $detail = $repository->getDetail($this->request->get(), $this->customer->getId());
        if ($detail) {
            return app(AgreementService::class)->downloadAgreement($detail['id'], $this->customer->getId());
        }
        return $this->jsonFailed('No access.File not found');
    }
}
