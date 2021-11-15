<?php
use Framework\App;
use App\Catalog\Controllers\AuthController;
use Catalog\model\account\seller_bill\account_info;
use Catalog\model\account\seller_bill\account_apply;
use Catalog\model\account\seller_bill\account_log;
use App\Components\Storage\StorageCloud;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ControllerAccountSellerBillAccountManage extends AuthController
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->language('account/seller_bill/account_manage');
    }

    public function index()
    {
        $data = $this->framework();
        return $this->render('account/seller_bill/account_manage_list', $data);
    }

    public function add()
    {
        $data = $this->framework();
        array_push($data['breadcrumbs'], [
            'text' => $this->language->get('seller_bill_account_manage_add'),
            'href' => 'javascript:void(0)',
        ]);
        return $this->render('account/seller_bill/account_manage', $data);
    }

    public function edit()
    {
        $data = $this->framework();
        array_push($data['breadcrumbs'], [
            'text' => $this->language->get('seller_bill_account_manage_edit'),
            'href' => 'javascript:void(0)',
        ]);
        if (!$this->request->get('id')) {
            return  $this->response->redirectTo($this->url->link('error/not_found'));
        }
        $data['info'] = account_info::getInfoById($this->request->get('id'));
        if (!$data['info']) {
            return  $this->response->redirectTo($this->url->link('error/not_found'));
        }
        if ($data['info']->seller_id != $this->customer->getId()) {
            return $this->response->redirectTo($this->url->link('error/not_found'));
        }
        $data['attach'] = account_info::getFile($data['info']->id);
        $data['apply'] = account_apply::getApplyById($data['info']->apply_id);
        $data['apply_status'] = account_apply::APPLY_STATUS;
        return $this->render('account/seller_bill/account_manage', $data);
    }

    public function save()
    {
        $data = $this->request->post();
        $reason = $this->request->post('reason');
        $this->validatePostData($data);
        $data['seller_id'] = $this->customer->getId();
        $file_ids = $data['file_ids'] ?? [];
        unset($data['file_ids']);
        unset($data['reason']);
        //需求102140 Collection Management，输入项超过限制时，切换PROFIT COLLECTION ACCOUNT TYPE后，输入项符合限制，保存后将超过限制的输入项也保存了
        //限制类型传过来的数据
        //Expected Result：保存时，有一边输入不正确，将不正确的Profit Collection Account Type清空
        //如果切换了类型，另一个类型输入错误的信息将清空
        if ($data['account_type'] == 1) {
            $pEmailLength = mb_strlen(trim($data['p_email']));
            $emailReg = "/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/";
            if (($pEmailLength < 1 || $pEmailLength > 200) || !preg_match($emailReg, $data['p_email'])) {
                $data['p_id'] = '';
                $data['p_email'] = '';
            }
        } elseif ($data['account_type'] == 3) {
            $bankNameLength = mb_strlen(trim($data['bank_name']));
            $bankAddressLength = mb_strlen(trim($data['bank_address']));
            $bankAccountLength = mb_strlen(trim($data['bank_account']));
            $swiftCodeLength = mb_strlen(trim($data['swift_code']));
            if (($bankNameLength < 1 || $bankNameLength > 200) || ($bankAddressLength < 1 || $bankAddressLength > 500) || ($bankAccountLength < 1 || $bankAccountLength > 50) || ($swiftCodeLength < 1 || $swiftCodeLength > 30)) {
                $data['bank_name'] = '';
                $data['bank_address'] = '';
                $data['bank_account'] = '';
                $data['swift_code'] = '';
            }
        }
        $account = $this->isAccountExist($data['bank_account'], $data['p_email'], $data['account_type']);
        if ($account && $account->id != $data['id']) {
            return $this->jsonFailed('The collection account information add failed.', [], 150);
        }

        if (!empty($data['id'])) {
            $info = account_info::getInfoById($data['id']);
        }

        try {
            $this->orm->getConnection()->beginTransaction();
            $data['status'] = 0;
            $status = $this->request->post('status');
            $now = date('Y-m-d H:i:s', time());
            $data['update_time'] = $now;
            if (!empty($info)) {
                account_info::update($data['id'], $data);
                $id = $data['id'];
                $accountApply = account_apply::getPendingApplyByAccountId($id);
                if ($accountApply) {
                    throw new Exception('The collection account information edit failed.');
                }
                // 添加申请审核记录
                if ($status == 1) {
                    $this->addApply($id, $reason, $data['seller_id']);
                } else {
                    account_apply::update($info->apply_id, ['reason' => $reason]);
                }
                $this->addLog($id, account_log::CHANGE_TYPE_EDIT, $reason, $status, $info);
            } else {
                $data['create_time'] = $now;
                $id = account_info::insert($data);
                // 添加申请审核记录
                $this->addApply($id, $reason, $data['seller_id']);
                $this->addLog($id, account_log::CHANGE_TYPE_ADD, $reason, $status);
            }
            $files = request()->file('file') ?? [];
            $this->handleAttach($id, $files, $file_ids);
            $this->orm->getConnection()->commit();
            return $this->jsonSuccess([], 'Request has been sent for approval.');
        } catch (Exception $e) {
            $this->orm->getConnection()->rollBack();
            return $this->jsonFailed('The collection account information edit failed.');
        }
    }

    /**
     * 添加申请
     * @param int $accountId 账户记录ID
     * @param string $reason
     * @param int|string $sellerId
     */
    public function addApply($accountId, $reason, $sellerId)
    {
        $res = account_apply::getPendingApplyByAccountId($accountId);
        if (!$res) {
            // 添加申请审核记录
            $map['account_id'] = $accountId;
            $map['create_time'] = date('Y-m-d H:i:s', time());;
            $map['reason'] = $reason;
            $map['create_username'] = $sellerId;
            $applyId = account_apply::insert($map);
            account_info::update($accountId, ['apply_id' => $applyId]);
        }
    }

    /**
     * 添加操作日志
     * @param int $accountId
     * @param int $type
     * @param string $reason
     * @param int $status
     * @param array $oldData
     */
    public function addLog($accountId, $type, $reason, $status, $oldData = [])
    {
        $info = account_info::getInfoById($accountId);
        $logData = [
            'account_id' => $accountId,
            'account_type' => $info->account_type,
            'seller_id' => $info->seller_id,
            'apply_id' => $info->apply_id,
            'change_type' => $type,
            'account_status_old' => account_info::ACCOUNT_STATUS_DISABLED,
            'account_status_new' => account_info::ACCOUNT_STATUS_DISABLED,
            'apply_status_old' => account_apply::APPLY_STATUS_PENDING,
            'apply_status_new' => account_apply::APPLY_STATUS_PENDING,
            'marketplace_flag' => 0,
            'create_username' => $info->seller_id,
            'create_time' => date('Y-m-d H:i:s'),
        ];

        if ($info->account_type == account_info::CORPORATE_ACCOUNT) {
            $name = account_info::calculateBankFormat($info->bank_account)['last_four_bank_num'];
            $prefix = 'corporate';
        } elseif ($info->account_type == account_info::PAYONEER_ACCOUNT) {
            $name = account_info::calculateEmailFormat($info->p_email)['front_four_p_num'];
            $prefix = 'payoneer';
        }
        $logText = '';
        if ($type == account_log::CHANGE_TYPE_EDIT) {
            $apply = account_apply::getApplyById($oldData->apply_id);
            $logData['account_status_old'] = $oldData->status;
            $logData['apply_status_old'] = $apply->status;
            if ($info->apply_id == $oldData->apply_id) {
                $logData['apply_status_new'] = $apply->status;
            }
            $terms = $this->handleTermText($info, $oldData);
            if ($terms) {
                $logText .= sprintf($this->language->get($prefix . '_account_edit_term_log'), $name, $terms).'<br>';
            }
            if ($status == account_info::ACCOUNT_STATUS_ENABLED) {
                $logText .= sprintf($this->language->get($prefix . '_account_submit_log'), $name).'<br>';
            }
        } else {
            $logText = sprintf($this->language->get($prefix . '_account_add_log'), $name) . '<br>';
            $logText .= sprintf($this->language->get($prefix . '_account_submit_log'), $name) . '<br>';
        }
        $logText .= 'Reason: ' . $reason;
        $logData['log_text'] = $logText;
        account_log::insert($logData);
    }

    /**
     * 处理修改的字段
     * @param $newData
     * @param $oldData
     * @return false|string
     */
    public function handleTermText($newData, $oldData)
    {
        $terms = '';
        $exclude = ['update_time', 'is_deleted', 'update_user_name', 'apply_id'];
        $map=[
            'company'=>'Company Name',
            'address'=>'Company Address',
            'bank_name'=>'Bank Name',
            'bank_account'=>'Bank Account',
            'bank_address'=>'Bank Address',
            'p_id'=>'Payoneer ID',
            'account_type'=>'Profit Collection Account Type',
            'p_email'=>'Payoneer Registration Email',
            'swift_code'=>'Swift Code',
           // 'company'=>'Company Name',
           // 'company'=>'Company Name',
        ];
        foreach ($newData as $key => $item) {
            if (!in_array($key, $exclude) && isset($oldData->$key) && $oldData->$key != $item) {
                $terms .= ($map[$key] ?? '') . ',';
            }
        }
        $terms = substr($terms, 0, -1);
        return $terms;
    }

    /**
     * 判断账号是否存在
     * @param $account
     * @param $pEmail
     * @param $accountType
     * @return array|false
     */
    public function isAccountExist($account, $pEmail, $accountType)
    {
        if ($accountType == account_info::CORPORATE_ACCOUNT && $account) {
            $where['bank_account'] = $account;
        }
        if ($accountType == account_info::PAYONEER_ACCOUNT && $pEmail) {
            $where['p_email'] = $pEmail;
        }
        if (!empty($where)) {
            $where['seller_id'] = $this->customer->getId();
            return account_info::getInfoByCondition($where);
        }
        return false;
    }

    /**
     * 验证账号是否存在接口
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function checkAccount()
    {
        $account = $this->request->post('account');
        $pEmail = $this->request->post('p_email');
        $accountType = $this->request->post('account_type');
        $accountId = $this->request->post('account_id');
        $res = $this->isAccountExist($account, $pEmail, $accountType);
        $msg = '';
        if ($accountType == account_info::CORPORATE_ACCOUNT && $account) {
            $format = account_info::calculateBankFormat($account);
            $msg = sprintf('The account ending in %s already exists.  Do you want to edit the collection account ?', $format['last_four_bank_num']);
        }
        if ($accountType == account_info::PAYONEER_ACCOUNT && $pEmail) {
            $format = account_info::calculateEmailFormat($pEmail);
            $msg = sprintf('The Payoneer account starting with %s already exists.Do you want to edit the collection account ?', $format['front_four_p_num']);
        }
        if ($res && $accountId != $res->id) {
            return $this->jsonFailed($msg, $res);
        }
        return $this->jsonSuccess();
    }

    /**
     * 接口获取数据
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getAccountInfos()
    {
        $page = $this->request->get('page', 1);
        $pageLimit = $this->request->get('page_limit', 8);
        $displayDisable = $this->request->get('display_disable', 0);
        $condition = [
            'display_disable' => $displayDisable,
        ];
        $accounts = account_info::calculateSellerAccountList($this->customer->getId(), $page, $pageLimit, $condition);
        $pageData = [
            'accounts' => $accounts['list'],
            'total_no_page' => $accounts['total_no_page'],
            'display_disable' => $displayDisable,
        ];
        $data = [
            'is_end' => ceil($accounts['total'] / $pageLimit) <= $page,
            'html' => $this->load->view('account/seller_bill/common/account_info', $pageData),
        ];
        return $this->response->json($data);
    }

    /**
     * seller删除账号
     *
     * @return  \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function deleteSellerAccount()
    {
        $result['code'] = -1;
        $result['msg'] = $this->language->get('seller_account_del_error');

        $account_id = $this->request->post('account_id', 0);
        if (($this->request->serverBag->get('REQUEST_METHOD') == 'POST') && ($check_data = $this->validateOwnData())) {
            $res = account_info::deleteSellerAccount($account_id);
            if ($res !== false) {
                //日志
                $log_data = [
                    'account_id' => $check_data['id'],
                    'account_type' => $check_data['account_type'],
                    'seller_id' => $this->customer->getId(),
                    'apply_id' => 0, //停用不需要重新申请
                    'change_type' => account_log::CHANGE_TYPE_DELETE,
                    'account_status_old' => -1,
                    'account_status_new' => -1,
                    'apply_status_old' => -1, //停用不需要审批
                    'apply_status_new' => -1, //停用不需要审批
                    'marketplace_flag' => 0,
                    'create_username' => $this->customer->getId(),
                    'create_time' => date('Y-m-d H:i:s'),
                ];
                $log_text = 'N/A'; //容错
                switch ($check_data['account_type']) {
                    case 1:
                        $log_text = sprintf($this->language->get('corporate_account_delete_log'), account_info::calculateBankFormat($check_data['bank_account'])['last_four_bank_num']);
                        break;
                    case 3:
                        $log_text = sprintf($this->language->get('payoneer_account_delete_log'), account_info::calculateEmailFormat($check_data['p_email'])['front_four_p_num']);
                        break;
                    default:
                        break;
                }
                $log_data['log_text'] = $log_text;
                account_log::insert($log_data);

                $result['code'] = 1;
                $result['msg'] = $this->language->get('seller_account_del_success');
            }
        }

        $this->response->headers->add(['Content-Type' => 'application/json']);
        return $this->response->json(json_encode($result));
    }

    /**
     * seller禁用账号
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function disabledAccount()
    {
        $result['code'] = -1;
        $result['msg'] = $this->language->get('seller_account_status_error');

        if (($this->request->serverBag->get('REQUEST_METHOD') == 'POST') && ($check_data = $this->validateOwnData())) {
            if ($check_data['status'] == 1) { //禁用前，必须是启用状态
                $save_data = [
                    'status' => 0,
                    'update_time' => date('Y-m-d H:i:s'),
                    'update_user_name' => $this->customer->getId(),
                ];
                $res = account_info::editAccountInfo($check_data['id'], $save_data);
                if ($res !== false) {
                    //日志
                    $log_data = [
                        'account_id' => $check_data['id'],
                        'account_type' => $check_data['account_type'],
                        'seller_id' => $this->customer->getId(),
                        'apply_id' => 0, //停用不需要重新申请
                        'change_type' => account_log::CHANGE_TYPE_EDIT,
                        'account_status_old' => account_info::ACCOUNT_STATUS_ENABLED,
                        'account_status_new' => account_info::ACCOUNT_STATUS_DISABLED,
                        'apply_status_old' => account_apply::APPLY_STATUS_APPROVED, //停用不需要审批，直接给approved状态
                        'apply_status_new' => account_apply::APPLY_STATUS_APPROVED,
                        'marketplace_flag' => 0,
                        'create_username' => $this->customer->getId(),
                        'create_time' => date('Y-m-d H:i:s'),
                    ];
                    $log_text = 'N/A'; //容错
                    switch ($check_data['account_type']) {
                        case account_info::CORPORATE_ACCOUNT:
                            $log_text = sprintf($this->language->get('corporate_account_edit_log'), account_info::calculateBankFormat($check_data['bank_account'])['last_four_bank_num']);
                            break;
                        case account_info::PAYONEER_ACCOUNT:
                            $log_text = sprintf($this->language->get('payoneer_account_edit_log'), account_info::calculateEmailFormat($check_data['p_email'])['front_four_p_num']);
                            break;
                        default:
                            break;
                    }
                    $log_data['log_text'] = $log_text;
                    account_log::insert($log_data);
                    $result['code'] = 1;
                    $result['msg'] = $this->language->get('seller_account_status_success');
                }
            }
        }
        $this->response->headers->add(['Content-Type' => 'application/json']);
        return $this->response->json(json_encode($result));
    }

    /**
     * seller启用账号
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function enabledAccount()
    {
        $result['code'] = -1;
        $result['msg'] = $this->language->get('seller_account_status_error');

        $reason = trim($this->request->post('reason'));
        if (!$reason || utf8_strlen($reason) > 2000) {
            $this->response->headers->add(['Content-Type' => 'application/json']);
            return $this->response->json(json_encode($result));
        }

        if (($this->request->serverBag->get('REQUEST_METHOD') == 'POST') && ($check_data = $this->validateOwnData())) {
            $checkApply = $this->validateApplyData($check_data['apply_id']);
            if ($check_data['status'] == 0 && !empty($checkApply) && $checkApply['status'] != 0) { //启用前，必须是禁用状态+非审核状态
                $data = [
                    'account_id' => $check_data['id'],
                    'reason' => $reason,
                    'create_username' => $this->customer->getId(),
                ];
                $res = account_info::doAccountApply($data);
                if ($res) {
                    //日志
                    $log_data = [
                        'account_id' => $check_data['id'],
                        'account_type' => $check_data['account_type'],
                        'seller_id' => $this->customer->getId(),
                        'apply_id' => $res,
                        'change_type' => account_log::CHANGE_TYPE_EDIT,
                        'account_status_old' => account_info::ACCOUNT_STATUS_DISABLED,
                        'account_status_new' => account_info::ACCOUNT_STATUS_ENABLED,
                        'apply_status_old' => $checkApply['status'],
                        'apply_status_new' => account_apply::APPLY_STATUS_PENDING,
                        'marketplace_flag' => 0,
                        'create_username' => $this->customer->getId(),
                        'create_time' => date('Y-m-d H:i:s'),
                    ];
                    $log_text = 'N/A'; //容错
                    switch ($check_data['account_type']) {
                        case account_info::CORPORATE_ACCOUNT:
                            $log_text = sprintf($this->language->get('corporate_account_submit_log'), account_info::calculateBankFormat($check_data['bank_account'])['last_four_bank_num']);
                            break;
                        case account_info::PAYONEER_ACCOUNT:
                            $log_text = sprintf($this->language->get('payoneer_account_submit_log'), account_info::calculateEmailFormat($check_data['p_email'])['front_four_p_num']);
                            break;
                        default:
                            break;
                    }
                    $log_data['log_text'] = $log_text . '<br>Reason: ' . trim($reason);
                    account_log::insert($log_data);
                }
                $result['code'] = 1;
                $result['msg'] = $this->language->get('seller_account_approval');
            }
        }
        $this->response->headers->add(['Content-Type' => 'application/json']);
        return $this->response->json(json_encode($result));
    }

    function handleAttach($id, $files, $file_ids)
    {
        if ($file_ids) {
            $this->orm->table('tb_sys_seller_account_file')
                ->whereIn('id', $file_ids)
                ->where('header_id', $id)
                ->update(['delete_flag' => 1]);
        }
        if (!empty($files)) {
            $uploaded = [];
            foreach ($files as $key => $file) {
                /** @var UploadedFile $file */
                $fileName = $file->getClientOriginalName();
                $fullPath = StorageCloud::sellerFile()->writeFile($file, $this->customer->getId(), time() . $fileName); //兼容以前命名规则
                $uploaded[$key]['file_path'] = $fullPath;
                $uploaded[$key]['file_name'] = $file->getClientOriginalName();
                $uploaded[$key]['file_size'] = StorageCloud::root()->fileSize($fullPath);//文件大小 单位（字节）
                $uploaded[$key]['header_id'] = $id;
            }
            $this->orm->table('tb_sys_seller_account_file')->insert($uploaded);
        }
    }

    function downAttach()
    {
        $file_id = $this->request->get['file_id'];
        $file = $this->orm->table('tb_sys_seller_account_file')
            ->where('id', $file_id)
            ->first();
        if (!$file) {
            return $this->redirect('error/not_found');
        }
        if (!StorageCloud::root()->fileExists($file->file_path)) {
            return $this->redirect('error/not_found');
        }
        return StorageCloud::root()->browserDownload($file->file_path, $file->file_name);
    }

    /**
     * 验证所属数据
     *
     * @return array
     */
    private function validateOwnData()
    {
        $account_id = intval($this->request->post('account_id', 0));
        if (empty($account_id)) {
            return [];
        }
        return account_info::checkAccountInfo($account_id, $this->customer->getId());
    }

    /**
     * 验证apply数据
     *
     * @return array
     */
    private function validateApplyData($applyId)
    {
        if (empty($applyId)) {
            return [];
        }
        $applyInfo = account_apply::getApplyById($applyId);
        $applyInfo = obj2array($applyInfo);
        return isset($applyInfo) ? $applyInfo : [];
    }

    private function validatePostData($post_data)
    {
        if (!isset($post_data['company']) || empty($post_data['company'])) {
            $this->response->failed('Please company  enter 1 ~ 200 characters');
        }
        if (!isset($post_data['address']) || empty($post_data['address'])) {
            $this->response->failed('Please address  enter 1 ~ 500 characters');
        }
        if (!isset($post_data['account_type']) || empty($post_data['account_type']) || !in_array($post_data['account_type'], [1, 3])) {
            $this->response->failed('Please select account_type');
        }
        if ($post_data['account_type'] == 3) {
//            if (!isset($post_data['p_id']) || empty($post_data['p_id'])) {
//                $this->response->failed('Please enter p_id');
//            }
            if (!isset($post_data['p_email']) || empty($post_data['p_email'])) {
                $this->response->failed('Please enter p_email');
            }
        } else {
            if (!isset($post_data['bank_name']) || empty($post_data['bank_name'])) {
                $this->response->failed('Please enter bank_name');
            }
            if (!isset($post_data['bank_account']) || empty($post_data['bank_account'])) {
                $this->response->failed('Please enter bank_account');
            }
            if (!isset($post_data['bank_address']) || empty($post_data['bank_address'])) {
                $this->response->failed('Please enter bank_address');
            }
            if (!isset($post_data['swift_code']) || empty($post_data['swift_code'])) {
                $this->response->failed('Please enter swift_code');
            }
        }
//        if (!isset($this->request->files['file']) && empty($this->request->files['file'])) {
//            $this->response->failed('Please upload attach');
//        }
        if (isset($this->request->files['file']) && (max($this->request->files['file']['size']) > 8192000)) {
            $this->response->failed('Warning: The attachment is too big.', [], 100);
        }

    }

    /**
     * 下载文件
     */
    public function downloadFile()
    {
        // 判断用户是否登录
        $file_path = DIR_DOWNLOAD . "CollectionAccountInformationConfirmationLetter.pdf";
        $this->download($file_path, 'CollectionAccountInformationConfirmationLetter.pdf');
    }

    /**
     * 下载文件
     */
    private function download($file_path,$file_name)
    {
        // 判断用户是否登录
        if (!headers_sent()) {
            if (!is_file($file_path)) {
                $this->response->redirect($this->url->link('error/not_found'));
            }
            ob_end_clean();//解决乱码
            header('Content-Type: application/octet-stream');
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path, 'rb');
        } else {
            exit('Error: Headers already sent out!');
        }
    }

    public function framework()
    {
        $this->language->load('account/seller_bill/bill');
        $this->document->setTitle('Billing Management');
        //链接
        $url = App::url();
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('billing_management'),
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('seller_bill_account_manage'),
                'href' => $url->link('account/seller_bill/account_manage'),
                'separator' => $this->language->get('text_separator')
            ]
        ];
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');

        $data['account_del_url'] = $url->link('account/seller_bill/account_manage/deleteSellerAccount');
        $data['account_disable_url'] = $url->link('account/seller_bill/account_manage/disabledAccount');
        $data['account_enable_url'] = $url->link('account/seller_bill/account_manage/enabledAccount');

        return $data;
    }
}
