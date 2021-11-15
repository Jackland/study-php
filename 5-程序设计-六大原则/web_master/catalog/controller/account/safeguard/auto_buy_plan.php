<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Catalog\Search\Safeguard\SafeguardAutoBuyPlanSearch;
use App\Models\Safeguard\SafeguardAutoBuyPlanDetail;
use App\Models\Safeguard\SafeguardAutoBuyPlanLog;
use App\Models\Safeguard\SafeguardAutoBuyPlan;
use App\Models\Safeguard\SafeguardConfig;
use App\Repositories\Safeguard\SafeguardAutoBuyPlanRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Repositories\Customer\CustomerTipRepository;
use App\Services\Safeguard\SafeguardAutoBuyPlanService;
use App\Services\Customer\CustomerTipService;
use App\Enums\Safeguard\SafeguardAutoBuyPlanStatus;
use Carbon\Carbon;
use App\Helper\CountryHelper;
use App\Helper\DateHelper;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * buyer自动购买配置类
 * Class ControllerAccountSafeguardAutoBuyPlan
 */
class ControllerAccountSafeguardAutoBuyPlan extends AuthBuyerController
{
    private $customerId;
    private $countryId;
    private $timezone;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        if (!boolval(Customer()->getCustomerExt(1))) {
            return $this->redirect(['account/account'])->send();
        }
        $this->customerId = intval($this->customer->getId());
        $this->countryId = intval($this->customer->getCountryId());
        $this->timezone = CountryHelper::getTimezone($this->countryId) ?? '';
    }

    public function list()
    {
        $data = [];
        $temp = [];
        $existsEffectivePlan = app(SafeguardAutoBuyPlanRepository::class)->getEffectivePlan((int)$this->customerId);
        //过滤掉第一条的查询列表
        $search = new SafeguardAutoBuyPlanSearch($this->customerId);
        $dataProvider = $search->search();
        $data['total'] = $dataProvider->getTotalCount();
        $list = $dataProvider->getList();
        if ($list->isNotEmpty()) {
            //可用的服务保障
            $availableConfigs = app(SafeguardConfigRepository::class)->getBuyerConfigs($this->customerId);
            $availableConfigRidArr = array_column(obj2array($availableConfigs), 'safeguard_config_rid');
            $key = $existsEffectivePlan ? 1 : 0;
            foreach ($list as $val) {
                $temp[$key]['planId'] = $val->id;
                $temp[$key]['status'] = SafeguardAutoBuyPlanStatus::getDescription($val->status);
                $temp[$key]['canEdit'] = false;
                $temp[$key]['canTerminate'] = false;
                $temp[$key]['info'] = [];
                foreach ($val->planDetails as $detail) {
                    if ($val->status == SafeguardAutoBuyPlanStatus::EFFECTIVE && strtotime($detail->expiration_time) < time() && strtotime($detail->effective_time) < time()) { //已完成
                        $temp[$key]['status'] = SafeguardAutoBuyPlanStatus::getDescription(SafeguardAutoBuyPlanStatus::COMPLETED);
                    }
                    $safeguardInfo = [];
                    //已勾选的
                    $safeguardRidList = SafeguardConfig::query()->whereIn('id', explode(',', $detail->safeguard_config_id))->orderBy('rid')->pluck('rid')->toArray();
                    foreach ($safeguardRidList as $rid) {
                        $safeguardInfo[$rid]['valid'] = false;
                        $safeguardInfo[$rid]['title'] = SafeguardConfig::query()->where('rid', $rid)->orderByDesc('id')->value('title');//取最新保障服务名称
                        //如果rid对本用户生效，就是生效的
                        if (in_array($rid, $availableConfigRidArr)) {
                            $safeguardInfo[$rid]['valid'] = true;//保障服务可用
                        }
                    }
                    $temp[$key]['info'][] = [
                        'effective_time' => Carbon::parse($detail->effective_time)->toDateTimeString(),
                        'expiration_time' => $detail->expiration_time ? Carbon::parse($detail->expiration_time)->toDateTimeString() : '',
                        'safeguard_config' => $safeguardInfo
                    ];
                }
                $key++;
                if ($existsEffectivePlan && $val->id == $existsEffectivePlan->id) { //如果有生效中的，第一条展示生效中的
                    $temp[0] = $temp[$key - 1];
                    $temp[0]['status'] = SafeguardAutoBuyPlanStatus::getDescription(SafeguardAutoBuyPlanStatus::EFFECTIVE);
                    $temp[0]['canEdit'] = true;
                    $temp[0]['canTerminate'] = true;
                    unset($temp[$key]);
                    $key--;
                }
            }

        }
        ksort($temp);
        $data['list'] = $temp;
        return $this->render('account/safeguard/auto_buy_plan/index', $data);
    }

    public function creatPlan()
    {
        //是否有生效投保方案
        $existsPlan = app(SafeguardAutoBuyPlanRepository::class)->getEffectivePlan($this->customerId);
        if ($existsPlan) {
            return $this->jsonFailed('There exists an active Auto-Purchase plan. Please terminate this plan first and then create a new one, or directly modify this plan.');
        }
        //可用的服务保障
        $data['availableConfig'] = app(SafeguardConfigRepository::class)->getBuyerConfigs($this->customerId);
        //是否显示时间提示气泡
        $data['expirationNotice'] = app(CustomerTipRepository::class)->checkCustomerTipExistsByTypeKey($this->customerId, 'safeguard_auto_buy_plan_expiration_notice');
        $data['currentDate'] = Carbon::parse('+1 days')->timezone($this->timezone)->toDateString();
        return $this->jsonSuccess($data);
    }

    //标记不在展示失效日期的提示气泡
    public function closeExpirationNotice()
    {
        //以前存在的也告知标记成功
        if (app(CustomerTipRepository::class)->checkCustomerTipExistsByTypeKey($this->customerId, 'safeguard_auto_buy_plan_expiration_notice')) {
            return $this->jsonSuccess();
        }
        //标记
        if (app(CustomerTipService::class)->insertCustomerTip($this->customerId, 'safeguard_auto_buy_plan_expiration_notice')) {
            return $this->jsonSuccess();
        }
        return $this->jsonFailed();
    }

    //保存方案
    public function saveNewPlan()
    {
        $post = $this->request->post('data', []);
        $ajaxResult = $this->ajaxData($post);
        if ($ajaxResult['error']) {
            return $this->jsonFailed($ajaxResult['msg']);
        }
        //是否有生效投保方案
        if (app(SafeguardAutoBuyPlanRepository::class)->getEffectivePlan($this->customerId)) {
            return $this->jsonFailed('There exists an active Auto-Purchase plan. Please terminate this plan first and then create a new one, or directly modify this plan.');
        }
        //保存
        if (app(SafeguardAutoBuyPlanService::class)->saveSafeguardAutoBuyPlan($post, $this->customerId)) {
            $isAboutToExpire = app(SafeguardAutoBuyPlanRepository::class)->isAboutToExpireByDays((int)$this->customerId);
            return $this->jsonSuccess(['isAboutToExpire' => $isAboutToExpire], 'Submitted successfully.');
        }
        return $this->jsonFailed('Failed to submit, you may contact the customer service.');
    }

    public function terminatePlan()
    {
        $planId = intval($this->request->get('plan_id', 0));
        if (app(SafeguardAutoBuyPlanService::class)->terminateSafeguardAutoBuyPlan($planId, $this->customerId)) {
            $isAboutToExpire = app(SafeguardAutoBuyPlanRepository::class)->isAboutToExpireByDays((int)$this->customerId);
            return $this->jsonSuccess(['isAboutToExpire' => $isAboutToExpire], 'The plan was terminated successfully.');
        }
        return $this->jsonFailed('Failed to terminate the plan. Please contact the customer service.');
    }

    //方案log
    public function planLog()
    {
        $planId = intval($this->request->get('plan_id', 0));
        //方案中最晚失效时间
        $statusAndExpirationTime = app(SafeguardAutoBuyPlanRepository::class)->getPlanStatusAndLatestExpirationTime((int)$planId);
        if (!$statusAndExpirationTime) {
            return $this->jsonFailed();
        }
        $key = 0;
        $result = [];
        if ($statusAndExpirationTime->plan->status == SafeguardAutoBuyPlanStatus::EFFECTIVE
            && $statusAndExpirationTime['expiration_time'] < Carbon::now()
            && $statusAndExpirationTime['expiration_time'] != ''
            && $statusAndExpirationTime['expiration_time'] != '9999-12-31 23:59:59'
        ) {//已完成
            $result[$key]['action'] = 3;
            $result[$key]['createTime'] = Carbon::parse($statusAndExpirationTime->expiration_time)->toDateTimeString();
            $result[$key]['operatorName'] = 'System';
            $result[$key]['statusOldValue'] = SafeguardAutoBuyPlanStatus::getDescription(SafeguardAutoBuyPlanStatus::EFFECTIVE);
            $result[$key]['statusNewValue'] = SafeguardAutoBuyPlanStatus::getDescription(SafeguardAutoBuyPlanStatus::COMPLETED);
            $result[$key]['info'] = [];
            $key++;
        }
        //操作日志
        $logs = SafeguardAutoBuyPlanLog::query()->where('plan_id', '=', $planId)->orderBy('create_time', 'DESC')->get();
        //可用的服务保障
        $availableConfigs = app(SafeguardConfigRepository::class)->getBuyerConfigs($this->customerId);
        $availableConfigRidArr = array_column(obj2array($availableConfigs), 'safeguard_config_rid');
        foreach ($logs as $log) {
            $result[$key]['action'] = $log->type;
            $result[$key]['createTime'] = Carbon::parse($log->create_time)->toDateTimeString();
            $result[$key]['operatorName'] = $this->customer->getNickName();
            $result[$key]['statusOldValue'] = '';
            $result[$key]['statusNewValue'] = '';
            $result[$key]['info'] = [];
            $contents = json_decode($log->content, true);
            if (isset($contents['status'])) {//终止
                $result[$key]['action'] = 3;
                $result[$key]['operatorName'] = $this->customer->getNickName();
                $result[$key]['statusOldValue'] = SafeguardAutoBuyPlanStatus::getDescription($contents['status']['old']);
                $result[$key]['statusNewValue'] = SafeguardAutoBuyPlanStatus::getDescription($contents['status']['new']);
                $result[$key]['info'] = [];
                $key++;
            } else {//创建与编辑
                $effectiveTmp = [];
                $expirationTmp = [];
                foreach ($contents as $kTmp => $row) {
                    $effectiveTmp[$kTmp] = $row['effective_time'];
                    $expirationTmp[$kTmp] = $row['expiration_time'] ?? '9999-12-31 23:59:59';
                }
                array_multisort($effectiveTmp, SORT_ASC, $expirationTmp, SORT_ASC, $contents);
                foreach ($contents as $content) {
                    $safeguardInfo = [];
                    $safeguardRidList = SafeguardConfig::query()->whereIn('id', explode(',', $content['safeguard_config_id']))->pluck('rid')->toArray();
                    foreach ($safeguardRidList as $rid) {
                        $safeguardInfo[$rid]['valid'] = false;
                        $safeguardInfo[$rid]['title'] = SafeguardConfig::query()->where('rid', $rid)->orderByDesc('id')->value('title');//取最新保障服务名称
                        //如果rid对本用户生效，就是生效的
                        if (in_array($rid, $availableConfigRidArr)) {
                            $safeguardInfo[$rid]['valid'] = true;//保障服务可用
                        }
                    }
                    $result[$key]['info'][] = [
                        'effective_time' => $content['effective_time'],
                        'expiration_time' => $content['expiration_time'],
                        'safeguard_config' => $safeguardInfo,
                    ];
                }
            }
            $key++;
        }
        return $this->jsonSuccess($result);
    }

    public function edit()
    {
        $planId = intval($this->request->get('plan_id', 0));
        //生效中的，才能编辑
        $existsEffectivePlan = app(SafeguardAutoBuyPlanRepository::class)->getEffectivePlan((int)$this->customerId);
        if (!$existsEffectivePlan || (int)$existsEffectivePlan->plan_id != $planId) {
            return $this->jsonFailed("The plan cannot be edited since it is not in the status of 'Active'");
        }
        $plan = SafeguardAutoBuyPlan::query()->with(['planDetails' => function ($q) {
            $q->orderBy('effective_time', 'ASC')->orderBy(DB::Raw("case when expiration_time is null then '9999-12-31 23:59:59' else expiration_time end"), 'ASC');
        }])->where('id', $planId)->first();
        if (!$plan) {
            return $this->jsonFailed("The plan cannot be edited since it is not in the status of 'Active'");
        }
        //可用的服务保障
        $availableConfigs = app(SafeguardConfigRepository::class)->getBuyerConfigs($this->customerId);
        $availableConfigRidArr = array_column(obj2array($availableConfigs), 'safeguard_config_rid');
        $currentDate = Carbon::now();

        $data = [];
        $selectList = [];
        //处理数据
        foreach ($plan->planDetails as $key => $detail) {
            $disableStartDate = false;
            $disableEndDate = false;
            $disableDelete = false;
            $safeguardSelectRidArr = SafeguardConfig::query()->whereIn('id', explode(',', $detail->safeguard_config_id))->pluck('rid')->toArray();
            //起始时间大于当前时间
            if ($detail->effective_time > $currentDate->toDateTimeString()) {
                $safeguardArr = array_unique(array_merge($availableConfigRidArr, $safeguardSelectRidArr)); //已选的加上可选的
            } else {
                $safeguardArr = array_unique($safeguardSelectRidArr);//已选的
                $disableStartDate = true;//日期选择框禁用
                $disableDelete = true;//不能删除行
            }
            sort($safeguardArr);
            //终止时间小于当前时间
            if ($detail->expiration_time <= $currentDate->toDateTimeString() && $detail->expiration_time) {
                $disableEndDate = true;
            }

            $info = [];
            foreach ($safeguardArr as $rid) {
                $newConfig = SafeguardConfig::query()->where('rid', $rid)->orderByDesc('id')->first(['title', 'id']);
                $info[] = [
                    'configId' => $newConfig->id,
                    'configTitle' => $newConfig->title,//取最新保障服务名称
                    'invalid' => !in_array($rid, $availableConfigRidArr) ? true : false,//true保障服务失效
                    'select' => in_array($rid, $safeguardSelectRidArr) ? true : false,//true保障服务已选
                ];
            }
            if ($detail->expiration_time == '' || $detail->expiration_time == '9999-12-31 23:59:59') {
                $expiration = '';
            } else {
                $expiration = $detail->expiration_time->toDateTimeString();
            }
            $selectList[] = [
                'planDetailId' => $detail->id,
                'effective' => $detail->effective_time->toDateTimeString(),
                'expiration' => $expiration,
                'disableStartDate' => $disableStartDate,
                'disableEndDate' => $disableEndDate,
                'disableDelete' => $disableDelete,
                'info' => $info,
            ];
        }
        $data['selectList'] = $selectList;
        $data['availableConfig'] = $availableConfigs;
        $data['currentDate'] = $currentDate->toDateString();
        //是否显示时间提示气泡
        $data['expirationNotice'] = app(CustomerTipRepository::class)->checkCustomerTipExistsByTypeKey($this->customerId, 'safeguard_auto_buy_plan_expiration_notice');
        return $this->jsonSuccess($data);
    }

    public function saveEdit()
    {
        $planId = intval($this->request->post('plan_id', 0));
        //生效中的，才能编辑
        $existsEffectivePlan = app(SafeguardAutoBuyPlanRepository::class)->getEffectivePlan($this->customerId);
        if (!$existsEffectivePlan || (int)$existsEffectivePlan->plan_id != $planId) {
            return $this->jsonFailed("The plan cannot be edited since it is not in the status of 'Active'");
        }
        $post = $this->request->post('data', []);
        $ajaxResult = $this->ajaxData($post);
        if ($ajaxResult['error']) {
            return $this->jsonFailed($ajaxResult['msg']);
        }
        //修改
        if (app(SafeguardAutoBuyPlanService::class)->saveSafeguardAutoBuyPlan($post, $this->customerId, $planId)) {
            $isAboutToExpire = app(SafeguardAutoBuyPlanRepository::class)->isAboutToExpireByDays((int)$this->customerId);
            return $this->jsonSuccess(['isAboutToExpire' => $isAboutToExpire], 'Submitted successfully.');
        }
        return $this->jsonFailed('Failed to submit, you may contact the customer service.');
    }

    //校验数据
    public function ajaxData(array $post)
    {
        if (!$post) {
            return $this->jsonFailed('The plan cannot be submitted since the time range of \'Orders Covered\' and the type of \'Auto-Purchase\' are not set. Please complete the required fields.');
        }
        $result = ['error' => true];
        $dateAndSafeguardIdTempList = [];
        $safeguardRidTempList = [];
        //可用的服务保障
        $availableConfigs = app(SafeguardConfigRepository::class)->getBuyerConfigs($this->customerId);
        $availableConfigRidArr = array_unique(array_column(obj2array($availableConfigs), 'safeguard_config_rid'));
        //当前日期
        $currentDate = Carbon::now()->getTimestamp();
        //校验数据
        foreach ($post as $val) {
            $effectiveTime = trim($val['effectiveTime']) ?? '';
            $expirationTime = trim($val['expirationTime']) ?? '';
            $safeguardConfigIdList = $val['safeguardConfigIdList'] ?? [];

            $isNew = true;//新增
            if (trim($val['planDetail']) && isset($val['planDetail'])) {
                $planDetail = SafeguardAutoBuyPlanDetail::query()->find(trim($val['planDetail']));
                if (!$planDetail) {
                    $result['msg'] = 'Failed to submit, you may contact the customer service.';
                    return $result;
                }
                $isNew = false;//编辑
            }

            //是否必填
            if ($effectiveTime == '' || empty($effectiveTime)) {
                $result['msg'] = 'Start Time must be selected';
                return $result;
            }
            if (!$safeguardConfigIdList || empty($safeguardConfigIdList) || !is_array($safeguardConfigIdList)) {
                $result['msg'] = 'Please select the Protection Service to purchase';
                return $result;
            }
            //是否是正确的日期格式
            if (!DateHelper::isCorrectDateFormat($effectiveTime, ['Y-m-d H:i:s'])) {
                $result['msg'] = 'The Start Time is in an incorrect format';
                return $result;
            }
            if (!DateHelper::isCorrectDateFormat($expirationTime, ['Y-m-d H:i:s']) && $expirationTime != '') {
                $result['msg'] = 'The End Time is in an incorrect format';
                return $result;
            }

            //校验起始时间不能小于当前时间  (新增)
            if ($isNew && strtotime($effectiveTime) <= $currentDate) {
                $result['msg'] = 'The Start Time cannot be today or before today';
                return $result;
            }
            //校验起始时间不能小于当前时间  (编辑时生效时间大于当前时间情况下)
            if (!$isNew && strtotime($effectiveTime) <= $currentDate && Carbon::parse($planDetail->effective_time) > Carbon::parse($val['effectiveTime'])) {
                $result['msg'] = 'The Start Time cannot be today or before today';
                return $result;
            }
            //终止时间不能小于起始时间 (新增)
            if (strtotime($expirationTime) < strtotime($effectiveTime) && $expirationTime != '') {
                return $this->jsonFailed('End Time must be later than Start Time');
            }
            //已勾选的
            $safeguardSelect = SafeguardConfig::query()->whereIn('id', array_unique($val['safeguardConfigIdList']))->get();
            //校验保障服务
            foreach ($safeguardSelect as $val) {
                $configNewTitle = SafeguardConfig::query()->where('rid', $val->rid)->orderByDesc('id')->value('title');
                //是否有权限购买(新建)
                if ($isNew && !in_array($val->rid, $availableConfigRidArr)) {
                    $result['msg'] = 'You have no permission to purchase this Protection Service. Please contact the customer service for details.';
                    return $result;
                }
                //同一个保障服务时间不能有重叠
                if (in_array($val->rid, $safeguardRidTempList)) {
                    foreach ($dateAndSafeguardIdTempList[$val->rid] as $item) {
                        if ($item['expirationTime'] == '' || $item['expirationTime'] == '9999-12-31 23:59:59') {
                            if ($item['effectiveTime'] < $expirationTime) {
                                $result['msg'] = 'The time ranges set in Orders Covered field for Auto-Purchase of [' . $configNewTitle . '] Protection Service have overlap, please reselect the time.';
                                return $result;
                            }
                        } elseif ($expirationTime == '' || $expirationTime == '9999-12-31 23:59:59') {
                            if ($item['expirationTime'] > $effectiveTime) {
                                $result['msg'] = 'The time ranges set in Orders Covered field for Auto-Purchase of [' . $configNewTitle . '] Protection Service have overlap, please reselect the time.';
                                return $result;
                            }
                        } else {

                            $startTimeCur = Carbon::parse($item['effectiveTime']);
                            $endTimeCur = Carbon::parse($item['expirationTime']);
                            $startTimePr = Carbon::parse($effectiveTime);
                            $endTimePr = Carbon::parse($expirationTime);
                            if ($startTimeCur->between($startTimePr, $endTimePr)
                                || $endTimeCur->between($startTimePr, $endTimePr)
                                || $startTimePr->between($startTimeCur, $endTimeCur)
                                || $endTimePr->between($startTimeCur, $endTimeCur)
                            ) {
                                $result['msg'] = 'The time ranges set in Orders Covered field for Auto-Purchase of [' . $configNewTitle . '] Protection Service have overlap, please reselect the time.';
                                return $result;
                            }
                        }
                    }
                }
                array_push($safeguardRidTempList, $val->rid);
                $dateAndSafeguardIdTempList[$val->rid][] = [
                    'effectiveTime' => $effectiveTime,
                    'expirationTime' => $expirationTime,
                ];
            }
        }
        return ['error' => false];
    }
}
