<?php

namespace App\Repositories\Tripartite;

use App\Enums\Common\YesNoEnum;
use App\Enums\Tripartite\TripartiteAgreementRequestStatus as RequestStatus;
use App\Enums\Tripartite\TripartiteAgreementRequestType;
use App\Enums\Tripartite\TripartiteAgreementStatus;
use App\Models\Customer\Customer;
use App\Models\Tripartite\TripartiteAgreement;
use App\Models\Tripartite\TripartiteAgreementOperate;
use App\Models\Tripartite\TripartiteAgreementRequest;
use App\Models\Tripartite\TripartiteAgreementTemplate;
use App\Repositories\Seller\SellerRepository;
use App\Services\TripartiteAgreement\TemplateService;
use Carbon\Carbon;

class AgreementRepository
{
    /**
     * 获取详情
     * @param TripartiteAgreement $agreement
     * @param int $operatorId
     * @return TripartiteAgreement
     */
    public function getAgreementDetail(TripartiteAgreement $agreement, int $operatorId): TripartiteAgreement
    {
        // 获取模板内容
        $replaces = $agreement->template_replaces;
        if (in_array($agreement->status, TripartiteAgreementStatus::unapprovedStatus()) && $operatorId == $agreement->seller_id) {
            [$company, $address, $name, $telephone] = app(SellerRepository::class)->getSellerCompanyInfo($agreement->seller_id);
            $replacesMap = [
                TemplateService::KEYWORD_SELLER_COMPANY => $company,
                TemplateService::KEYWORD_SELLER_ADDRESS => $address,
                TemplateService::KEYWORD_SELLER_NAME => $name,
                TemplateService::KEYWORD_SELLER_TELEPHONE => $telephone,
                TemplateService::KEYWORD_SELLER_ACCOUNT_NAME => "{$agreement->seller->screenname}({$agreement->seller->customer->user_number})",
            ];
            $replaces = app(TemplateService::class)->generateReplaceValue($replacesMap, $replaces);
        }
        $agreement->content = app(TemplateService::class)->replaceTemplateContent($agreement->template->content, $replaces);
        $agreement['write_keywords'] = app(TemplateService::class)->findParameters($replaces);
        $agreement['records'] = $this->getAgreementOperateRecords($agreement);
        $agreement['md5_replace_value'] = md5($agreement->template_replace_value);

        // 过期天数提醒
        $agreement->seven_remind = 0;
        if ($agreement->status == TripartiteAgreementStatus::ACTIVE) {
            $days = app(AgreementRepository::class)->diffDays(Carbon::now(), $agreement->terminate_time);
            $agreement->seven_remind = $days <= 7 ? $days : 0;
        }

        $canHandle = $agreement->canHandle(false);
        if ($canHandle && in_array($agreement->status, TripartiteAgreementStatus::approvedStatus())) {
            $request = collect($agreement->requests)->where('handle_id', $operatorId)->where('status', RequestStatus::PENDING);
            $agreement['cancel_handle_request'] = $request->where('type', TripartiteAgreementRequestType::CANCEL)->last() ?: false;
            if ($agreement['cancel_handle_request']) {
                $agreement['cancel_handle_request_remain_time'] = $this->getRequestRemainTime(Carbon::parse($agreement['cancel_handle_request']['create_time']));
                $agreement['request_id'] = $agreement['cancel_handle_request']['id'];
            }
            $agreement['terminate_handle_request'] = $request->where('type', TripartiteAgreementRequestType::TERMINATE)->last() ?: false;
            if ($agreement['terminate_handle_request']) {
                $agreement['terminate_handle_request_remain_time'] = $this->getRequestRemainTime(Carbon::parse($agreement['terminate_handle_request']['create_time']));
                $agreement['request_id'] = $agreement['terminate_handle_request']['id'];
            }
        }

        $agreement->canCancel(false);
        $agreement->canTerminate(false);

        return $agreement;
    }

    /**
     * 获取请求审核剩余时间 默认7天
     * @param Carbon $createTime
     * @return array
     */
    private function getRequestRemainTime(Carbon $createTime): array
    {
        if (Carbon::now()->subDays(7)->gt($createTime)) {
            return [0 , 0, 0];
        }

        $expireTime = Carbon::parse($createTime)->addDays(7);
        $now = Carbon::now();
        $diffInDays = $expireTime->diffInDays($now, true);

        $expireTime = $expireTime->subDay($diffInDays);
        $hour = $expireTime->diffInHours($now, true);

        $expireTime = $expireTime->subHours($hour);
        $minute = $expireTime->diffInMinutes($now, true);

        return [$diffInDays, $hour, $minute];
    }

    /**
     * 获取协议的操作记录
     * @param TripartiteAgreement $agreement
     * @return array
     */
    public function getAgreementOperateRecords(TripartiteAgreement $agreement): array
    {
        return TripartiteAgreementOperate::query()
            ->where('agreement_id', $agreement->id)
            ->where('customer_id', '!=', 0)
            ->orderByDesc('id')
            ->with(['buyer:customer_id,user_number'])
            ->get()
            ->map(function (TripartiteAgreementOperate $q) use ($agreement) {
                if ($q->customer_id == $agreement->buyer_id) {
                    $q['customer_name'] = 'Buyer-' . $agreement->buyer->nickname . '(' . $agreement->buyer->user_number . ')';
                } elseif ($q->customer_id == $agreement->seller_id) {
                    $q['customer_name'] = 'Seller-' . $agreement->seller->screenname;
                }
                return $q;
            })->toArray();
    }

    /**
     * description:处理列表信息
     * @param array $list
     * @return array
     * @throws
     */
    public function getHandleList($list)
    {
        if (collect($list)->isNotEmpty()) {
            collect($list)->map(function (TripartiteAgreement $q) {
                $q->canEdit();
                $q->canCancel();
                $q->canRenewal();//是否能续签
                $q->canDelete();
                $q->canHandle();//是否可以处理
                $q->canTerminate();//是否可以终止
                $days = $this->diffDays(Carbon::now(), $q->terminate_time ?? $q->expire_time);
                $q->seven_remind = 0;
                if ($q->can_tripartite_renewal === true && $days <= 7) {
                    $q->seven_remind = $days;
                }
                $requestData = $this->getRequest($q->id, ['status' => RequestStatus::PENDING]);
                if (isset($requestData[$q->seller_id])) {
                    if ($requestData[$q->seller_id]['type'] == TripartiteAgreementRequestType::TERMINATE) {
                        $q->early_termination = true;
                    } elseif ($requestData[$q->seller_id]['type'] == TripartiteAgreementRequestType::CANCEL) {
                        $q->early_cancel = true;
                    }
                }
                if (isset($requestData[$q->buyer_id])) {
                    if ($requestData[$q->buyer_id]['type'] == TripartiteAgreementRequestType::TERMINATE) {
                        $q['send_early_termination'] = true;
                    } elseif ($requestData[$q->buyer_id]['type'] == TripartiteAgreementRequestType::CANCEL) {
                        $q['send_early_cancel'] = true;
                    }
                }

            });
            return collect($list)->toArray();
        }
        return $list;
    }

    /**
     * description:获取采销协议详情
     * @param array $condition
     * @param int $customerId
     * @return array
     * @throws
     */
    public function getDetail(array $condition, int $customerId = 0)
    {
        if (isset($condition['agreement_id']) && (int)$condition['agreement_id']) {
            $data = TripartiteAgreement::query()
                ->where(['id' => (int)$condition['agreement_id'], 'buyer_id' => $customerId,])
                ->with(['seller' => function ($q) {
                    $q->select(['customer_id', 'screenname']);
                }])
                ->with(['template'])
                ->get();
            if ($data && $data->isNotEmpty()) {
                $data->map(function (TripartiteAgreement $q) use ($customerId) {
                    $q->canEdit();
                    $q->canCancel();
                    $q->canRenewal();//是否能续签
                    $q->canDelete();
                    $q->canHandle();//是否可以处理
                    $q->canTerminate();//是否可以终止协议
                    $days = $this->diffDays(Carbon::now(), $q->terminate_time ?? $q->expire_time);

                    $q->seven_remind = 0;
                    if ($q->can_tripartite_renewal === true && $days <= 7) {
                        $q->seven_remind = $days;
                    }
                    //组装模板给前端的数据
                    $q->replace_value_input = app(TemplateService::class)
                        ->findParameters(json_decode($q->template->replace_value, true));

                    //查看请求中的数据
                    $requestData = $this->getRequest($q->id, ['status' => RequestStatus::PENDING]);

                    //如何存在seller 请求
                    $cancel_handle_request = [];
                    if (isset($requestData[$q->seller_id]) && $requestData[$q->seller_id]['type'] == 1) {
                        $q->request_seller_record = $requestData[$q->seller_id];
                        $cancel_handle_request = collect($requestData)
                            ->where('type', TripartiteAgreementRequestType::TERMINATE)->last() ?: false;
                    } elseif (isset($requestData[$q->seller_id]) && $requestData[$q->seller_id]['type'] == 2) {
                        $cancel_handle_request = collect($requestData)
                            ->where('type', TripartiteAgreementRequestType::CANCEL)->last() ?: false;
                        $q->request_seller_record = $requestData[$q->seller_id];
                    }
                    $q->cancel_handle_request_remain_time = $this->getRequestRemainTime(
                        Carbon::parse($cancel_handle_request['create_time'] ?? ''));

                    //如果是buyer 申请
                    if (isset($requestData[$q->buyer_id]) && $requestData[$q->buyer_id]['type'] == 1) {
                        $q->request_buyer_record = $requestData[$q->buyer_id];
                    } elseif (isset($requestData[$q->buyer_id]) && $requestData[$q->buyer_id]['type'] == 2) {
                        $q->request_buyer_record = $requestData[$q->buyer_id];
                    }

                    //如初当前列表的实时buyer 信息
                    $defaultArr = app(TemplateService::class)->generateReplaceValue(
                        app(AgreementRepository::class)->getBuyerDefaultInfo($customerId));

                    $q->content = app(TemplateService::class)
                        ->replaceTemplateContent($q->template->content, array_merge($q->template->template_replaces, array_merge($q->template_replaces, $defaultArr)));

                    $q->records = $this->getAgreementOperateRecords($q);
                    $q->recordsSort = $this->sortRecord($this->getAgreementOperateRecords($q));

                });
                return $data->toArray()[0];
            }
            return [];
        }
        return [];
    }

    /**
     * description:模糊匹配和buyer 已经签署的店铺和 签约的协议名称
     * @param array $condition 提交的数据
     * @return array
     */
    public function getSearchVagueList(array $condition, int $limit = 10)
    {
        return TripartiteAgreement::query()
            ->where(function ($q) use ($condition) {
                if (isset($condition['title']) && $condition['title']) {
                    $q->where('title', 'like', "%{$condition['title']}%");
                }
            })
            ->whereHas('seller', function ($q) use ($condition) {
                if (isset($condition['screenname']) && $condition['screenname']) {
                    $q->where('screenname', 'like', "%{$condition['screenname']}%");
                }
            })
            ->with(['seller:customer_id,screenname'])
            ->limit($limit)
            ->get(['id', 'seller_id', 'title', 'agreement_no'])
            ->toArray();
    }


    /**
     * description:获取俩个时间的相差天数
     * @param string $startTime
     * @param string $endTime
     * @param int $absolute 1 向上取整  2 向下取整 3 四舍五入
     * @return int
     */
    public function diffDays($startTime, $endTime, $absolute = 1)
    {
        $start = Carbon::parse($startTime);
        switch ($absolute) {
            case 1:
                $min = $start->diffInMinutes(Carbon::parse($endTime));
                return intval(ceil($min / 60 / 24));
            case 2:
                return $start->diffInDays(Carbon::parse($endTime));
            case 3:
                $min = $start->diffInMinutes(Carbon::parse($endTime));
                return intval(round($min / 60 / 24));
            default:
                return 0;
        }
    }

    /**
     * description:获取后台配置的模板
     * @param int $customer_id
     * @return array
     */
    public function getListTemplates(int $customer_id)
    {
        $data = TripartiteAgreementTemplate::query()
            ->where(['is_deleted' => YesNoEnum::NO])
            ->where(function ($q) use ($customer_id) {
                $q->whereRaw("find_in_set(?,customer_ids)", $customer_id)
                    ->orWhere('customer_ids', 0);
            })
            ->get()
            ->toArray();
        $info = [];
        if ($data && count($data) <= 2) {
            $default = app(TemplateService::class)->generateReplaceValue($this->getBuyerDefaultInfo($customer_id));
            foreach ($data as &$datum) {
                $replace = json_decode($datum['replace_value'], true);
                $lastReplace = array_replace($replace, $default);

                $datum['content'] = app(TemplateService::class)
                    ->replaceTemplateContent($datum['content'], $lastReplace);

                $datum['replace_value_input'] = app(TemplateService::class)
                    ->findParameters(json_decode($datum['replace_value'], true) ?? []);
                if ($datum['customer_ids']) {
                    $info['config'] = $datum;
                } else {
                    $info['default'] = $datum;
                }
            }
            unset($datum);
            return $info;
        }
        return [];
    }

    /**
     * description:获取是否有请求终止的记录
     * @param int $agreementId 活动id
     * @param array $condition 条件
     * @return array
     */
    public function getRequest($agreementId, $condition)
    {
        return TripartiteAgreementRequest::query()
            ->where('agreement_id', $agreementId)
            ->where(function ($q) use ($condition) {
                if (isset($condition['sender_id']) && $condition['sender_id']) {
                    $q->where('sender_id', $condition['sender_id']);
                }
                if (isset($condition['handle_id']) && $condition['handle_id']) {
                    $q->where('handle_id', $condition['handle_id']);
                }
                if (isset($condition['type']) && $condition['type']) {
                    $q->where('type', $condition['type']);
                }
                if (isset($condition['status']) && $condition['status']) {
                    $q->where('status', $condition['status']);
                }
            })
            ->get()
            ->keyBy('sender_id')
            ->toArray();
    }

    /**
     * description:获取buyer合同的默认信息
     * @return array
     */
    public function getBuyerDefaultInfo(int $customer_id)
    {
        //查找buyer 默认的表单数据
        $defaultArr = [];
        $buyerData = Customer::query()
            ->select([
                'customer_id', 'firstname', 'lastname', 'telephone', 'company_name', 'register_address', 'company_address',
                'nickname', 'user_number'])
            ->find($customer_id);
        if ($buyerData) {
            $defaultArr = [
                TemplateService::KEYWORD_BUYER_COMPANY => $buyerData->company_name,
                TemplateService::KEYWORD_BUYER_ADDRESS => $buyerData->register_address,
                TemplateService::KEYWORD_BUYER_NAME => $buyerData->firstname . $buyerData->lastname,
                TemplateService::KEYWORD_BUYER_TELEPHONE => $buyerData->telephone,
                TemplateService::KEYWORD_BUYER_ACCOUNT_NAME => "{$buyerData->nickname}({$buyerData->user_number})",
            ];
        }
        return $defaultArr;
    }

    /**
     * description:buyer待处理数量 = seller拒绝+seller申请提前终止+seller申请取消
     * @param int $customerId
     * @return int
     */
    public function getSellerRequestNum(int $customerId = 0)
    {
        if (!$customerId) {
            return 0;
        }
        $rejectCnt = TripartiteAgreement::query()
            ->where(['buyer_id' => $customerId])
            ->where('status', TripartiteAgreementStatus::REJECTED)
            ->count('id');

        $requestCnt = TripartiteAgreementRequest::query()
            ->where('status', RequestStatus::PENDING)
            ->whereIn('type', [RequestStatus::TERMINATION_TYPE, RequestStatus::CANCEL_TYPE])
            ->where(['handle_id' => $customerId])
            ->count('id');
        return $rejectCnt + $requestCnt;
    }


    /**
     * 获取待处理的数量（协议待审核，终止待审核，取消待审核）
     * @param int $operatorId
     * @param bool $isSeller
     * @return int
     */
    public function getPendingAgreementCountByOperatorId(int $operatorId, bool $isSeller = true): int
    {
        $wheres['status'] = TripartiteAgreementStatus::TO_BE_SIGNED;
        $wheres['is_deleted'] = YesNoEnum::NO;
        if ($isSeller) {
            $wheres['seller_id'] = $operatorId;
        } else {
            $wheres['buyer_id'] = $operatorId;
        }

        $pendingAgreementCount = TripartiteAgreement::queryRead()->where($wheres)->count();

        $pendingRequestCount = TripartiteAgreementRequest::queryRead()
            ->where('handle_id', $operatorId)
            ->where('status', RequestStatus::PENDING)
            ->count();

        return $pendingAgreementCount + $pendingRequestCount;
    }


    /**
     * description:对查找的记录 按照类型 找出最新的一条记录
     * @param array $record 记录
     * @return array
     */
    public function sortRecord(array $record)
    {
        if ($record) {
            $info = [];
            foreach ($record as $item) {
                $info[$item['type']][] = $item;
            }
            return $info;
        }
        return $record;
    }
}
