<?php

/**
 * Class AccountRmaManage
 *
 * @property ModelAccountRmaManage $model_account_rma_manage
 */
class ControllerAccountRmaManage extends Controller
{
    const RMA_APPLIED = 0;
    const RMA_PROCESSED = 1;
    const RMA_PENDING = 2;
    const RMA_CANCELED = 3;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/rma_management'));
            $this->response->redirect($this->url->link('account/login'));
        }
        // model
        $this->load->model('account/rma/manage');
    }

    // region api
    public function autocomplete()
    {
        $request = $this->request->request;
        $ret = $this->model_account_rma_manage->autocomplete((int)$this->customer->getId(), $request);
        $this->response->returnJson($ret);
    }
    // endregion
}