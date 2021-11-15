<?php

namespace App\Repositories\Margin;

use App\Enums\Common\YesNoEnum;
use App\Enums\Margin\MarginContractSettingModule;
use App\Models\Margin\MarginContract;

class ContractRepository
{
    /**
     * 获取现货合约的配置
     * @param bool $isJapan
     * @return string[]
     * "Version" => ""
     * "Contract Days" => ""
     * "Deposit Percentage" => ""
     * "Minimum Selling Quantity" => ""
     * "Maximum Selling Quantity" => ""
     * "Storage Fee（Seller）" => ""
     * "Storage Fee（Buyer）" => ""
     */
    public function getContractSettings($isJapan = false)
    {
        $settingModules = MarginContractSettingModule::getValues();
        $modules = ['Version' => ''] + array_map(function () {return $item = '';}, array_flip($settingModules));

        $template = db('tb_bond_template')->where('status', YesNoEnum::NO)
            ->where('delete_flag', YesNoEnum::NO)
            ->orderByDesc('id')
            ->first();

        if (empty($template)) {
            return $modules;
        }

        $settings = db('tb_bond_template_term as t')
            ->join('tb_bond_parameter_module as pm', 'pm.id','=', 't.bond_parameter_module_id')
            ->join('tb_bond_parameter_term as pt', 'pt.id','=', 't.bond_parameter_term_id')
            ->where('t.bond_template_id', $template->id)
            ->where('pm.status', YesNoEnum::NO)
            ->where('pm.delete_flag', YesNoEnum::NO)
            ->where('pt.delete_flag', YesNoEnum::NO)
            ->whereIn('pm.parameter_module_english', $settingModules)
            ->select(['pm.parameter_module_english', 'pt.bond_parameter_term_english'])
            ->get()
            ->pluck('bond_parameter_term_english', 'parameter_module_english')
            ->toArray();

        foreach ($modules as $key => &$module) {
            if ($key == 'Version') {
                $module = $template->bond_template_number;
                continue;
            }

            $module = rtrim($settings[$key], '%') ?? '';

            if ($key == MarginContractSettingModule::MODULE_DEPOSIT_PERCENTAGE && $module) {
                $module = $isJapan ? strval(floor($module)) : sprintf("%.2f", round($module, 2));
            }
        }

        return $modules;
    }

    /**
     * 获取合约
     * @param int $sellerId
     * @param int $productId
     * @return MarginContract
     */
    public function getContractByProductId(int $sellerId, int $productId)
    {
        return MarginContract::query()
            ->with('templates')
            ->where('customer_id', $sellerId)
            ->where('product_id', $productId)
            ->where('status', 1)
            ->where('is_deleted', YesNoEnum::NO)
            ->first();
    }

    /**
     * 获取合约的最小和最大头款金额
     * @param MarginContract $contract
     * @param $precision
     * @return array
     */
    public function getContractMinAndMaxAmount(MarginContract $contract, $precision)
    {
        $minContractAmount = 0;
        $maxContractAmount = 0;
        foreach ($contract->templates as $template) {
            $template->min_margin_amount = round($template->price * $contract->payment_ratio * 0.01, $precision) * $template->min_num;
            $template->max_margin_amount = round($template->price * $contract->payment_ratio * 0.01, $precision) * $template->max_num;
            if ($minContractAmount == 0) {
                $minContractAmount = $template->min_margin_amount;
            }
            if ($minContractAmount > $template->min_margin_amount) {
                $minContractAmount = $template->min_margin_amount;
            }
            if ($maxContractAmount < $template->max_margin_amount) {
                $maxContractAmount = $template->max_margin_amount;
            }
        }

        return [$minContractAmount, $maxContractAmount];
    }
}
