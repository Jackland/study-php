<?php

namespace App\Catalog\Forms\Safeguard;

use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\Safeguard\SafeguardBill;
use App\Repositories\Safeguard\SafeguardClaimRepository;
use Framework\Model\RequestForm\RequestForm;
use App\Repositories\Safeguard\SafeguardBillRepository;
use App\Services\Safeguard\SafeguardClaimService;
use App\Models\Safeguard\SafeguardClaim;
use App\Enums\Safeguard\SafeguardClaimStatus;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Components\RemoteApi;
use App\Enums\Safeguard\SafeguardClaimConfig;
use App\Enums\Safeguard\SafeguardConfig;
use App\Helper\StringHelper;
use App\Logging\Logger;

class ClaimForm extends RequestForm
{
    public $claim_id;
    public $safeguard_bill_id;
    public $claim_reason_id;
    public $sales_platform;
    public $problem_desc = '';
    public $confirm_menu_id;
    public $confirm_sub_ids;
    public $products;

    private $return_code = 0; //自定义状态码，返回前端判断使用，非页面传入

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        $postData = $this->request->post();
        if (isset($postData['claim_id']) && $postData['claim_id'] > 0) {
            //完善资料
            return [
                'claim_id' => ['required', 'integer', function ($attribute, $value, $fail) {
                    $claimInfo = SafeguardClaim::query()->where('id', (int)$value)->where('buyer_id', customer()->getId())->first();
                    if (empty($claimInfo)) {
                        $fail('Claim submission failed, you may contact the customer service.');
                        return;
                    }
                    if ($claimInfo->status != SafeguardClaimStatus::CLAIM_BACKED) {
                        $fail('Claim application is not available for this Protection Service which is under the status of ' . SafeguardClaimStatus::getDescription($claimInfo->status) . '.');
                        return;
                    }
                }],
                'problem_desc' => ['required', 'string', function ($attribute, $value, $fail) {
                    $stringCharactersLen = StringHelper::stringCharactersLen(trim($value));
                    if ($stringCharactersLen == 0) {
                        $fail('The Problem Description can not be left blank.');
                        return;
                    }
                    $characterLimit = SafeguardClaimConfig::CLAIM_PROBLEM_DESC_LIMIT;
                    if ($stringCharactersLen > $characterLimit) {
                        $fail("The Problem Description may not be greater than {$characterLimit} characters.");
                        return;
                    }
                }],
                 'confirm_menu_id' => 'integer', //附件menuId
                 'confirm_sub_ids' => 'string', //附件sub id
            ];
        }

        $billDetail = SafeguardBill::query()->where(['buyer_id' => customer()->getId(), 'id' => $postData['safeguard_bill_id']])->first();
        $reasons = [];
        if ($billDetail) {
            $reasons = app(SafeguardClaimRepository::class)->getSafeguardClaimReasons($billDetail->safeguardConfig->config_type);
            $reasons = array_column($reasons, 'id');
        }

        $rules = [
            'safeguard_bill_id' => ['required', 'integer', function ($attribute, $value, $fail) use ($billDetail, $reasons) {
                if (empty($billDetail)) { //校验 是属于自己的保单
                    $this->return_code = SafeguardClaimConfig::RETURN_CODE_80001;
                    $fail('Claim submission failed, you may contact the customer service');
                    return;
                }
                $checkBill = app(SafeguardBillRepository::class)->getSafeguardBillStatus($value);
                if ($checkBill != SafeguardBillStatus::ACTIVE) {
                    $this->return_code = SafeguardClaimConfig::RETURN_CODE_80001;
                    $fail('Claim application is not available for this Protection Service which is under the status of ' . SafeguardBillStatus::getDescription($checkBill, 'unKnown') . '.');
                    return;
                }
                //严格校验销售订单状态，避免销售订单被认为取消而没有取消保单
                if (in_array($billDetail->salesOrder->order_status, [CustomerSalesOrderStatus::CANCELED, CustomerSalesOrderStatus::TO_BE_PAID])) {
                    $this->return_code = SafeguardClaimConfig::RETURN_CODE_80001;
                    $fail('Claim application is not available for the sales order under the status of ' . CustomerSalesOrderStatus::getDescription($billDetail->salesOrder->order_status, 'unKoown') . '.');
                    return;
                }
                $maxCount = SafeguardClaim::query()->where('safeguard_bill_id', $value)->count();
                if ($maxCount >= SafeguardClaimConfig::CLAIM_MAX_NUMBER) {
                    $fail('You cannot submit a claim application for this Protection Service any more since the maximum limit of claims allowed for the Protection Service has been reached.');
                }
            }],
            'claim_reason_id' => 'required|integer|in:' . join(',', $reasons),
            'sales_platform' => 'required|string',
            'problem_desc' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen(trim($value));
                if ($stringCharactersLen == 0) {
                    $fail('The Problem Description can not be left blank.');
                    return;
                }
                $characterLimit = SafeguardClaimConfig::CLAIM_PROBLEM_DESC_LIMIT;
                if ($stringCharactersLen > $characterLimit) {
                    $fail("The Problem Description may not be greater than {$characterLimit} characters.");
                    return;
                }
            }],
            'confirm_menu_id' => 'required|integer', //附件menuId
            'confirm_sub_ids' => 'required|string', //附件sub id

            'products' => ['required', 'array', function ($attribute, $value, $fail) use ($billDetail) {
                if (!is_array($value) || empty($value)) {
                    $fail('No item has been selected for claim');
                }
                foreach ($value as $item) {
                    $checkResult = app(SafeguardClaimService::class)->checkCanApplyClaim($billDetail->id, $item['sale_order_line_id'], trim($item['item_code']));
                    if ($checkResult['can_apply'] == 0) {
                        $fail('There exists a claim in progress for this item, and an additional claim application cannot be submitted until the existing one is completed.');
                        return;
                    }
                }
            }],
            'products.*.qty' => 'required|integer|min:1',
            'products.*.product_id' => 'required|integer',
            'products.*.item_code' => 'required|string',
            // 'products.*.tracking_infos' => 'required',
        ];

        if ($billDetail->safeguardConfig->config_type == SafeguardConfig::CONFIG_TYPE_FULFILLMENT) {
            $rules[] = ['products.*.tracking_infos' => 'required|array'];
        }

        return $rules;
    }

    /**
     * @inheritDoc
     */
    protected function getAttributeLabels(): array
    {
        return [
            'claim_id' => 'Claim ID',
            'claim_reason_id' => 'Reason for Claim',
            'sales_platform' => 'Selling Platform',
            'problem_desc' => 'Problem Description',
            'confirm_menu_id' => 'Supporting files',
            'confirm_sub_ids' => 'Supporting files',
            'products.*.tracking_infos' => 'Tracking Number',
        ];
    }

    protected function getRuleMessages(): array
    {
        return [
            'required' => ':attribute can not be left blank.',
            'integer' => ':attribute must be a interger.',
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
     * @return array
     */
    public function save()
    {
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            return [
                'code' => $this->return_code,
                'msg' => $this->getFirstError(),
            ];
        }
        try {
            //重新提交审核（完善资料）
            if ($this->claim_id > 0) {
                dbTransaction(function () {
                    app(SafeguardClaimService::class)->reApplyClaim($this->claim_id, $this->problem_desc, (int)$this->confirm_menu_id);
                });
            } else {
                dbTransaction(function () {
                    $this->claim_id = app(SafeguardClaimService::class)->applyClaim($this->safeguard_bill_id, $this->claim_reason_id, $this->sales_platform, $this->problem_desc, $this->confirm_menu_id, $this->products);
                });
            }

            //确定上传资源，默认一定会成功
            try {
                if ($this->confirm_menu_id && $this->confirm_sub_ids) {
                    RemoteApi::file()->confirmUpload($this->confirm_menu_id, explode(',', $this->confirm_sub_ids));
                }
            } catch (\Exception $e) {
                Logger::applyClaim('确认上传资源失败' . $this->confirm_menu_id . ',出错信息：' . $e->getMessage() . ';子资源：confirm_sub_ids=' . $this->confirm_sub_ids);
            }

            $kefuHandlePeroid = explode(',', configDB('safeguard_kefu_handle_peroid'));
            $successMsg = 'The claim application has been successfully submitted, and the Marketplace will feedback the processing result within %s-%s business days.';

            return [
                'code' => 200,
                'claim_id' => $this->claim_id,
                'msg' => sprintf($successMsg, $kefuHandlePeroid[0] ?? 3, $kefuHandlePeroid[1] ?? 5),
            ];

        } catch (\Throwable $e) {
            if ($this->claim_id > 0) {
                Logger::applyClaim('完善资料失败：claim_id=' . $this->claim_id . ',出错信息：' . $e->getMessage());
            } else {
                Logger::applyClaim('申请理赔失败：bill_id=' . $this->safeguard_bill_id . ',出错信息：' . $e->getMessage());
            }
            return [
                'code' => 0,
                'claim_id' => $this->claim_id,
                'msg' => 'Claim submission failed, you may contact the customer service.',
            ];
        }
    }

}
