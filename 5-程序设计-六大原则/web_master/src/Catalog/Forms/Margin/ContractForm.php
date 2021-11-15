<?php

namespace App\Catalog\Forms\Margin;

use App\Enums\Margin\MarginContractSettingModule;
use App\Repositories\Margin\ContractRepository;
use App\Services\Margin\ContractService;
use Framework\Model\RequestForm\RequestForm;

class ContractForm extends RequestForm
{
    public $contract_no;
    public $product_id;
    public $version;
    public $is_bid;
    public $templates;
    public $deposit;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        $isJapan = customer()->isJapan();
        $settings = app(ContractRepository::class)->getContractSettings($isJapan);

        return [
            'contract_no' => 'required|string',
            'product_id' => 'required',
            'version' => ['required', function($attribute, $value, $fail) use ($settings) {
                if ($settings['Version'] != $value) {
                    $fail(__('Unavailable version!',[],'validation/margin'));
                    return;
                }
            }],
            'is_bid' => 'required|integer|in:0,1',
            'deposit' => ['required', function($attribute, $value, $fail) use ($settings) {
                if ($settings[MarginContractSettingModule::MODULE_DEPOSIT_PERCENTAGE] != $value) {
                    $fail(__('Unavailable deposit percentage!',[],'validation/margin'));
                    return;
                }
            }],
            'templates' => 'required|array|max:4|distinct',
            'templates.*.days' => ['required', function($attribute, $value, $fail) use ($settings) {
                if ($settings[MarginContractSettingModule::MODULE_DAYS] != $value) {
                    $fail(__('Unavailable contract days!',[],'validation/margin'));
                    return;
                }
            }],
            'templates.*.min_qty' => "required|numeric|min:{$settings[MarginContractSettingModule::MODULE_MIN_QUANTITY]}",
            'templates.*.max_qty' => 'required|numeric',
            'templates.*.price' => $isJapan ? 'required|regex:/^(\d{1,6})$/' : 'required|regex:/^(\d{1,5})(\.\d{0,2})?$/'
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
        $customerId = customer()->getId();
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            return [
                'code' => 0,
                'msg' => $this->getFirstError(),
            ];
        }

        $contract = app(ContractRepository::class)->getContractByProductId($customerId, $this->product_id);

        try {
            if (empty($contract)) {
                // 新增合约
                dbTransaction(function () use ($customerId) {
                    app(ContractService::class)->addContract($customerId, $this->contract_no, $this->product_id, $this->version, $this->templates, $this->is_bid, $this->deposit);
                });
            } else {
                // 编辑合约
                dbTransaction(function () use ($contract) {
                    app(ContractService::class)->editContract($contract, $this->version, $this->templates, $this->is_bid, $this->deposit);
                });
            }

            return [
                'code' => 200,
                'msg' => 'Successfully.',
            ];
        } catch (\Throwable $e) {
            return [
                'code' => 0,
                'msg' => $e->getMessage(),
            ];
        }
    }
}
