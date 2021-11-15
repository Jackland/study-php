<?php

use App\Helper\UploadImageHelper;
use Catalog\model\futures\credit;
use Catalog\model\futures\creditApply;

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 */
class ControllerCustomerpartnerCreditManage extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('customerpartner/credit_manage', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('account/customerpartner');
        if ($this->customer->isUSA() && $this->model_account_customerpartner->isRelationUsaSellerSpecialAccountManagerBySellerId($this->customer->getId())) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }
    }

    public function index()
    {
        $data = $this->framework();
        $seller_id = $this->customer->getId();
        // 获取第一次申请
        $data['first_apply'] = creditApply::hasCreditApply($seller_id);
        // 获取最新的一次申请
        $data['last_apply'] = creditApply::getCreditApply($seller_id);
        $data['is_japan'] = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
        $data['params'] = $this->request->get;
        if ($data['is_japan']) {
            $data['currency'] = preg_replace('/(0)/', 'XXX', $this->currency->format(0, $this->session->data['currency']));
        } else {
            $data['currency'] = preg_replace('/(0.00)/', 'XX.XX', $this->currency->format(0, $this->session->data['currency']));
        }
        if ($data['first_apply']) {
            $data['credit'] = credit::getCredit($seller_id);
        }
        if (isset($data['credit'])) {
            // 格式化credit_amount
            $last_bill = credit::getLastBill($seller_id);
            $data['pay_credit'] = $this->currency->format($data['credit']->credit_amount - $last_bill->current_balance, $this->session->data['currency']);
            $data['balance_credit'] = $this->currency->format($last_bill->current_balance, $this->session->data['currency']);
            $data['credit']->credit_amount = $this->currency->format($data['credit']->credit_amount, $this->session->data['currency']);
            // 判断信用是否在有效期
            $data['can_credit'] = $this->isCreditUnExpire($data['credit']);
            // 获取账单流水，获取分页信息
            $data['page'] = get_value_or_default($this->request->get, 'page', 1);
            $data['page_limit'] = get_value_or_default($this->request->request, 'page_limit', 15);
            $bills = credit::getBillList($seller_id, $data['page'], $this->request->get, $data['page_limit']);
            $data['bills'] = $bills['data'];
            $data['page_view'] = $this->load->controller('common/pagination', $bills);
        }
        $this->response->setOutput($this->load->view('customerpartner/future/credit_list', $data));
    }

    public function isCreditUnExpire($credit)
    {
        $now = time();
        if ($credit) {
            if ($credit->credit_start_time < $now && ($credit->credit_end_time > $now || $credit->credit_end_time == -1))
                return true;
        }
        return false;
    }

    /**
     * 首次申请授信
     * @throws ReflectionException
     */
    public function addApply()
    {
        $data = $this->framework();
        $data['type'] = $this->request->get['type'] ?? '';
        // 生成授信编号
        $data['credit_number'] = date('Ymd') . mt_rand(100000, 999999);
        $data['status'] = $this->statusMsg(1);
        $attach_url = $this->url->link('customerpartner/credit_manage/getAttach');
        $data['upload_attach'] = $this->load->controller('customerpartner/credit_manage/uploadAttach', $attach_url);
        $this->response->setOutput($this->load->view('customerpartner/future/credit_apply', $data));
    }

    /**
     * 申请提额和延期
     * @throws ReflectionException
     */
    public function tiApply()
    {
        $data = $this->framework();
        $data['type'] = $this->request->get['type'] ?? '';
        $apply = creditApply::getCreditApply($this->customer->getId());
        // 如果申请提额，判断是否有正在申请的记录
        if (!in_array($apply->status, [3, 5])) {
            return $this->response->failed();
        }
        // 生成授信编号
        $data['credit_number'] = date('Ymd') . mt_rand(100000, 999999);
        $data['currency'] = session('currency');
        $data['apply'] = $apply;
        $data['status'] = $this->statusMsg(1);
        $data['apply']->comments = '';
        // 获取审核详细记录
        $data['apply_operate'] = creditApply::getApplyOperate($apply->id)->each(function ($item) {
            $item->status_msg = $this->statusMsg($item->status);
        });
        // 判断信用是否在有效期
        $data['can_credit'] = credit::isCreditUnExpire($this->customer->getId());
        $attach_url = $this->url->link('customerpartner/credit_manage/getAttach&attach=' . $apply->attach);
        $data['upload_attach'] = $this->load->controller('customerpartner/credit_manage/uploadAttach', $attach_url);
        $this->response->setOutput($this->load->view('customerpartner/future/credit_apply', $data));
    }

    /**
     * 编辑和查看授信页面
     * @throws ReflectionException
     */
    public function editApply()
    {
        $data = $this->framework();
        $data['type'] = $this->request->get['type'] ?? '';
        $apply = creditApply::getCreditApply($this->customer->getId());
        $data['apply'] = $apply;
        $data['credit_number'] = $apply->credit_number;
        $data['type_id'] = json_decode($apply->type_id, true);
        $data['status'] = $this->statusMsg($apply->status);
        $data['currency'] = session('currency');
        // 获取审核详细记录
        $data['apply_operate'] = creditApply::getApplyOperate($apply->id)->each(function ($item) {
            $item->status_msg = $this->statusMsg($item->status);
        });
        $data['is_japan'] = JAPAN_COUNTRY_ID == $this->customer->getCountryId() ? 1 : 0;
        $data['can_credit'] = credit::isCreditUnExpire($this->customer->getId());
        $attach_url = $this->url->link('customerpartner/credit_manage/getAttach&attach=' . $apply->attach);
        $data['upload_attach'] = $this->load->controller('customerpartner/credit_manage/uploadAttach', $attach_url);
        $this->response->setOutput($this->load->view('customerpartner/future/credit_apply', $data));
    }


    public function uploadAttach($attach_url = null)
    {
        $data['upload_input'] = $this->load->controller('upload/upload_component/upload_input');
        $data['attach_url'] = $attach_url;
        $data['allow_types'] = "['image','pdf']";
        return $this->load->view('customerpartner/future/upload_attach', $data);
    }

    public function getAttach()
    {
        if ($this->request->get('attach')) {
            $ids = explode(',', $this->request->get['attach']);
            $attach = creditApply::getCreditAttach($ids);
            $attach = $attach->map(function ($item) {
                $data = UploadImageHelper::getInfoFromOriginUrl($item->path,
                    $item->orig_name, 'default/blank.png');
                $data['file_id'] = $item->file_upload_id;
                return $data;
            });
        } else {
            $attach = [];
        }
        $this->response->returnJson($attach);
    }


    protected function statusMsg($code)
    {
        $arr = ['', 'Applied', 'Pending', 'Canceled', 'Passed first review', 'Passed the final review', 'Rejected', 'First review denied', 'Final review denied'];
        if (isset($arr[$code])) {
            return $arr[$code];
        }
        return;
    }

    public function saveCredit()
    {
        $data = $this->request->post(['credit_number', 'credit_amount', 'first_name', 'email', 'last_name', 'phone', 'job_title', 'id_card', 'type_id', 'comments']);
        $data['seller_id'] = $this->customer->getId();
        // 状态置为Applied
        $data['status'] = 1;
        if (!isset($data['type_id'])) {
            $data['type_id'] = [1];
        } else {
            $data['type_id'] = array_map(function ($val) {
                return intval($val);
            }, $data['type_id']);
            sort($data['type_id']);
        }
        creditApply::saveCreditApply($data);
        $this->response->success();
    }


    public function framework()
    {
        $this->document->setTitle('Credit Authorization Management');
        $data['breadcrumbs'] = [
            [
                'text' => ' Store Management',
                'href' => 'javascript:void(0)',
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => ' Credit Authorization Management',
                'href' => $this->url->link('customerpartner/credit_manage'),
                'separator' => $this->language->get('text_separator')
            ]

        ];
        if (isset($this->request->get['type'])) {
            $data['breadcrumbs'][] = [
                'text' => ' Credit Authorization Application Form',
                'href' => 'javascript:void(0)',
                'separator' => $this->language->get('text_separator')
            ];
        }
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');
        return $data;
    }
}
