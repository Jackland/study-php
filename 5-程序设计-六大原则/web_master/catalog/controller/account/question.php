<?php

/**
 * Class ControllerAccountQuestion
 * @property ModelFuturesContract $model_futures_contract
 */
class ControllerAccountQuestion extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    public function index()
    {
        $this->response->setOutput($this->load->view('account/question/index'));
    }


    /**
     * Seller的期货调查问卷
     * @throws Exception
     */
    public function sellerFutures()
    {
        $question_json = $this->config->get('seller_futures_question');
        if (isJson($question_json) === false) {
            $question_json = '[]';//默认为空的JSON数组
        }


        $data                  = [];
        $data['is_seller']     = intval($this->customer->isPartner());
        $data['question_json'] = $question_json;
        $this->response->setOutput($this->load->view('account/question/futures', $data));
    }


    /**
     * Buyer的期货调查问卷
     * @throws Exception
     */
    public function buyerFutures()
    {
        $question_json = $this->config->get('buyer_futures_question');
        if (isJson($question_json) === false) {
            $question_json = '[]';//默认为空的JSON数组
        }


        $data                  = [];
        $data['is_seller']     = intval($this->customer->isPartner());
        $data['question_json'] = $question_json;
        $this->response->setOutput($this->load->view('account/question/futures', $data));
    }


    /**
     * @throws Exception
     */
    public function saveFuturesQuestionnaire()
    {
        $customer_id = $this->customer->getId();
        $this->load->model('futures/contract');
        $exist = $this->model_futures_contract->existFuturesQuestionnaire($customer_id);
        if (!$exist) {
            $this->model_futures_contract->saveFuturesQuestionnaire($customer_id);
        }

        $this->response->success([], 'OK');
    }
}