<?php

namespace App\Services\Margin;

use App\Enums\Common\YesNoEnum;
use App\Models\Margin\MarginContract;
use App\Models\Margin\MarginTemplate;
use Carbon\Carbon;
use Framework\Exception\Exception;
use Throwable;

class ContractService
{
    /**
     * 删除合约
     * @param int $sellerId
     * @param array $ids
     * @return string
     */
    public function delContractsByIds(int $sellerId, array $ids)
    {
        try {
            dbTransaction(function () use ($ids, $sellerId) {
                $res = MarginContract::query()
                    ->where('customer_id', $sellerId)
                    ->whereIn('id', $ids)
                    ->where('is_deleted', YesNoEnum::NO)
                    ->update([
                        'is_deleted' => YesNoEnum::YES,
                        'update_time' => Carbon::now(),
                    ]);
                if ($res == 0) {
                    throw new Exception('These Margin Contracts have been deleted unsuccessfully.');
                }

                MarginTemplate::query()
                    ->where('seller_id', $sellerId)
                    ->whereIn('contract_id', $ids)
                    ->where('is_del', YesNoEnum::NO)
                    ->update([
                        'is_del' => YesNoEnum::YES,
                        'update_time' => Carbon::now(),
                        'update_user' => $sellerId,
                    ]);
            });
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return '';
    }

    /**
     * 添加合约
     * @param int $customerId
     * @param string $contractNo
     * @param int $productId
     * @param string $version
     * @param array $templates
     * @param int $isBid
     * @param $deposit
     */
    public function addContract(int $customerId, string $contractNo, int $productId, string $version, array $templates, int $isBid, $deposit)
    {
        // 售卖天数去配置中都一致，先取第一个里的值
        $day = $templates[0]['days'];
        $bondTemplate = db('tb_bond_template')->where('bond_template_number', $version)->first();

        $contractId = MarginContract::query()->insertGetId([
            'contract_no' => $contractNo,
            'customer_id' => $customerId,
            'product_id' => $productId,
            'payment_ratio' => $deposit,
            'day' => $day,
            'is_bid' => $isBid,
            'bond_template_id' => $bondTemplate->id,
        ]);

        foreach ($templates as $template) {
            MarginTemplate::insert([
                'contract_id' => $contractId,
                'template_id' => $this->generateTemplateNo(),
                'bond_template_id' => $bondTemplate->id,
                'seller_id' => $customerId,
                'product_id' => $productId,
                'price' => $template['price'],
                'payment_ratio' => $deposit,
                'day' => $template['days'],
                'max_num' => $template['max_qty'],
                'min_num' => $template['min_qty'],
                'is_default' => YesNoEnum::NO,
                'is_del' => YesNoEnum::NO,
                'create_time' => Carbon::now(),
                'create_user' => $customerId,
                'update_time' => Carbon::now(),
                'update_user' => $customerId,
            ]);
        }
    }

    /**
     * 编辑合约
     * @param MarginContract $contract
     * @param string $version
     * @param array $templates
     * @param int $isBid
     * @param $deposit
     */
    public function editContract(MarginContract $contract, string $version, array $templates, int $isBid, $deposit)
    {
        // 售卖天数去配置中都一致，先取第一个里的值
        $day = $templates[0]['days'];
        $bondTemplate = db('tb_bond_template')->where('bond_template_number', $version)->first();

        $contract->bond_template_id = $bondTemplate->id;
        $contract->is_bid = $isBid;
        $contract->payment_ratio = $deposit;
        $contract->day = $day;
        $contract->update_time = Carbon::now();
        $contract->save();

        // 组装模板值已最小数量和最大数量和key
        $formatTemplates = [];
        foreach ($templates as $template) {
            $formatTemplates[$template['min_qty'] . '-' . $template['max_qty']] = $template;
        }

        foreach ($contract->templates as $marginTemplate) {
            /** @var MarginTemplate $marginTemplate */
            $key = $marginTemplate->min_num . '-' . $marginTemplate->max_num;
            // 旧模板里没有新模板的配置，则删除
            if (!isset($formatTemplates[$key])) {
                $marginTemplate->is_del = YesNoEnum::YES;
                $marginTemplate->update_time = Carbon::now();
                $marginTemplate->update_user = $contract->customer_id;
                $marginTemplate->save();
                continue;
            }

            // 旧模板里有新模板的配置，则更新并删除新模板中当前的配置
            $formatTemplate = $formatTemplates[$key];
            $marginTemplate->price = $formatTemplate['price'];
            $marginTemplate->bond_template_id = $bondTemplate->id;
            $marginTemplate->payment_ratio = $deposit;
            $marginTemplate->day = $formatTemplate['days'];
            $marginTemplate->update_time = Carbon::now();
            $marginTemplate->update_user = $contract->customer_id;
            $marginTemplate->save();

            unset($formatTemplates[$key]);
        }

        // 剩余没处理的
        if (!empty($formatTemplates)) {
            foreach ($formatTemplates as $formatTemplate) {
                MarginTemplate::insert([
                    'contract_id' => $contract->id,
                    'template_id' => $this->generateTemplateNo(),
                    'bond_template_id' => $bondTemplate->id,
                    'seller_id' => $contract->customer_id,
                    'product_id' => $contract->product_id,
                    'price' => $formatTemplate['price'],
                    'payment_ratio' => $deposit,
                    'day' => $formatTemplate['days'],
                    'max_num' => $formatTemplate['max_qty'],
                    'min_num' => $formatTemplate['min_qty'],
                    'is_default' => YesNoEnum::NO,
                    'is_del' => YesNoEnum::NO,
                    'create_time' => Carbon::now(),
                    'create_user' => $contract->customer_id,
                    'update_time' => Carbon::now(),
                    'update_user' => $contract->customer_id,
                ]);
            }
        }
    }

    /**
     * 生成模板号
     * @return string
     */
    private function generateTemplateNo()
    {
        $todayLastTemplate = MarginTemplate::query()
            ->whereRaw('LEFT(template_id,8)=?', date('Ymd'))
            ->orderByDesc('template_id')
            ->first();
        if (empty($todayLastTemplate)) {
            $templateNo = date('Ymd') . '000001';
        } else {
            $templateNo = $todayLastTemplate->template_id + 1;
        }

        return $templateNo;
    }
}
