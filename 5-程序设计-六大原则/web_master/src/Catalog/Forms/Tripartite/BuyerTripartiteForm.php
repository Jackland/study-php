<?php

namespace App\Catalog\Forms\Tripartite;

use App\Components\UniqueGenerator;
use App\Enums\Common\CountryEnum;
use App\Enums\Tripartite\TripartiteAgreementOperateType;
use App\Enums\Tripartite\TripartiteAgreementStatus;
use App\Helper\CountryHelper;
use App\Models\Tripartite\TripartiteAgreement;
use App\Models\Tripartite\TripartiteAgreementTemplate;
use App\Repositories\Tripartite\AgreementRepository;
use App\Services\TripartiteAgreement\AgreementService;
use App\Services\TripartiteAgreement\TemplateService;
use Framework\Model\RequestForm\RequestForm;
use App\Components\UniqueGenerator\Enums\ServiceEnum;
use Illuminate\Support\Carbon;

class BuyerTripartiteForm extends RequestForm
{
    public $title;
    public $effect_time;
    public $expire_time;
    public $template_id;
    public $template_replace_value;
    public $seller_id;
    public $agreement_no;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'title' => 'required|string',
            'effect_time' => 'required|date',
            'expire_time' => 'required|date|after:effect_time',
            'template_id' => 'required|int',
            'template_replace_value' => 'required|array',
            'seller_id' => ['required', 'array', function ($attribute, $value, $fail) {
                if (!is_array($value) || empty($value)) {
                    $fail('No item has been selected for seller_id');
                    return;
                }
                foreach ($value as $item) {
                    if (empty($item)) {
                        $fail('No item has been selected for seller_id');
                        return;
                    }
                }
            }]
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getAutoLoadRequestData()
    {
        return $this->request->post();
    }

    /**
     * @param array $condition
     * @return array
     * @throws \Throwable
     */
    public function save($condition)
    {
        $post = $this->getAutoLoadRequestData();
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            return [
                'code' => 0,
                'msg' => $this->getFirstError() ?? 'Request error.',
            ];
        }
        $exits = null;
        $agreement_id = $post['agreement_id'] ?? 0;
        $agreement_id && $exits = TripartiteAgreement::query()->find($agreement_id);
        $defaultArr = app(AgreementRepository::class)->getBuyerDefaultInfo($condition['customerId']);

        $msg = $this->checkData($post, $condition, $defaultArr, $exits);
        if ($msg) {
            return $msg;
        }

        //编辑
        if ($condition['renew'] === 0 && $exits && $exits->toArray()) {
            $lastReplace = array_replace($post['template_replace_value'], $defaultArr);

            $exits->title = trim($post['title']);
            $exits->effect_time = $post['effect_time'];
            $exits->expire_time = $post['expire_time'];
            $exits->template_id = $post['template_id'];
            $exits->status = TripartiteAgreementStatus::TO_BE_SIGNED;
            $exits->template_replace_value = json_encode(
                app(TemplateService::class)->generateReplaceValue($lastReplace)
                , JSON_UNESCAPED_UNICODE);
            $exits->save();

            //插入日志
            app(AgreementService::class)->createOperate($agreement_id,
                $condition['customerId'], TripartiteAgreementOperateType::EDIT_AGREEMENT);

        } else {
            dbTransaction(function () use ($post, $condition, $defaultArr) {
                foreach ($post['seller_id'] as $item) {
                    $add = [
                        'title' => trim($post['title']),
                        'agreement_no' => $this->generateNos(),
                        'effect_time' => $post['effect_time'],
                        'expire_time' => $post['expire_time'],
                        'template_id' => $post['template_id'],
                        'seller_id' => $item,
                        'buyer_id' => $condition['customerId'],
                        'template_replace_value' => json_encode(app(TemplateService::class)
                            ->generateReplaceValue(array_merge($defaultArr, $post['template_replace_value'])),
                            JSON_UNESCAPED_UNICODE),
                        'status' => TripartiteAgreementStatus::TO_BE_SIGNED
                    ];
                    $lastId = TripartiteAgreement::query()->insertGetId($add);
                    //插入日志
                    app(AgreementService::class)->createOperate($lastId,
                        $condition['customerId'], TripartiteAgreementOperateType::CREATE_AGREEMENT);
                }
            });
        }
        return [
            'code' => 200,
            'msg' => 'Successfully.',
        ];
    }

    /**
     * description:生成采销编号
     * @param
     * @return string
     * @throws
     */
    private function generateNos()
    {
        return UniqueGenerator::date()->digit(4)->service(ServiceEnum::TRIPARTITE_NO)->checkDatabase()->random();
    }

    /**
     * description:检查提交数据是否符合当前状态
     * @param array $post
     * @param array $defaultArr buyer 默认自动补全的数组
     * @param array $condition
     * @param TripartiteAgreement $exits
     * @return array
     */
    public function checkData(array $post, array $condition, array $defaultArr, $exits)
    {
        //判断开始时间必须大于当前国别时间
        $timeZone = CountryHelper::getTimezone(customer()->getCountryId());
        if (Carbon::parse($post['effect_time'])->setTimezone($timeZone)->lt(Carbon::now()->setTimezone($timeZone)->subDay())) {
            return [
                'code' => 0,
                'msg' => 'Start Time must be later than the current time.'
            ];
        }


        //自动替换失效的模板
        $templateData = TripartiteAgreementTemplate::query()->findOrFail((int)$post['template_id']);
        if ($templateData->customer_ids === '0') {
            //默认模板
            $template = TripartiteAgreementTemplate::query()->select(['id', 'replace_value', 'is_deleted'])
                ->whereRaw("find_in_set(?,customer_ids)", '0')
                ->orderByDesc('id')
                ->first();
        } else {
            //定制模板
            $template = TripartiteAgreementTemplate::query()
                ->whereRaw("find_in_set(?,customer_ids)", $condition['customerId'])
                ->orderByDesc('id')
                ->first();

            //如果定制模板不存在或者删除 加载默认模板
            if (!$template || $template->is_deleted == 1) {
                $template = TripartiteAgreementTemplate::query()->select(['id', 'is_deleted'])
                    ->whereRaw("find_in_set(?,customer_ids)", '0')
                    ->orderByDesc('id')
                    ->first();
            }
        }

        if ($template->is_deleted == 1 || $post['template_id'] != $template->id) {
            //如果存在不一样 更新数据库
            if ($exits && $exits->template_id != $template->id) {
                $exits->template_id = $template->id;
                $exits->save();
            }
            return [
                'code' => 404,
                'msg' => sprintf('The current template is invalid, please use a new template.')
            ];
        }


        //检查模板是否替换字段字符存在
        if (!app(TemplateService::class)->checkCompanyInfo($template, array_merge($post['template_replace_value'], $defaultArr))) {
            return [
                'code' => 0,
                'msg' => 'Your company information is not complete. Please contact the Marketplace customer service to perfect your information.'
            ];
        }


        //待审核和带生效已生效（不包含提前终止） 不允许重复
        foreach ($post['seller_id'] as $item) {

            if (TripartiteAgreement::query()
                ->where([
                        'buyer_id' => $condition['customerId'],
                        'seller_id' => $item,
                        'status' => TripartiteAgreementStatus::TO_BE_SIGNED
                    ]
                )
                ->when($post['agreement_id'], function ($q) use ($post) {
                    $q->where('id', '!=', (int)$post['agreement_id']);
                })
                ->where('expire_time', '>=', $post['effect_time'])
                ->where('effect_time', '<=', $post['expire_time'])
                ->exists()) {
                return [
                    'code' => 0,
                    'msg' => 'This selected time period of Agreement Validity conflicts with the existing signing/signed agreement(s).'
                ];
            }

            if (TripartiteAgreement::query()
                ->where([
                        'buyer_id' => $condition['customerId'],
                        'seller_id' => $item
                    ]
                )
                ->when($post['agreement_id'], function ($q) use ($post) {
                    $q->where('id', '!=', (int)$post['agreement_id']);
                })
                ->whereIn('status', TripartiteAgreementStatus::approvedStatus())
                ->where('terminate_time', '>=', $post['effect_time'])
                ->where('effect_time', '<=', $post['expire_time'])
                ->exists()) {
                return [
                    'code' => 0,
                    'msg' => 'This selected time period of Agreement Validity conflicts with the existing signing/signed agreement(s).'
                ];
            }
        }


        if ($exits) {
            $detail = app(AgreementRepository::class)->getDetail(['agreement_id' => $exits->id], $exits->buyer_id);
            //是否能编辑
            if ($condition['renew'] === 0 && $detail['can_tripartite_edit'] === false) {
                return [
                    'code' => 0,
                    'msg' => 'The agreement has been updated, please refresh the page and try again.',
                ];
            }
            //是否能续签
            if ($condition['renew'] === 1 && $detail['can_tripartite_renewal'] === false) {
                return [
                    'code' => 0,
                    'msg' => 'The agreement has been updated, please refresh the page and try again.',
                ];
            }
        }


        //普通判断
        if (!in_array($condition['renew'], [0, 1])) {
            return [
                'code' => 0,
                'msg' => 'The request is illegal.',
            ];
        }

        return [];
    }
}
