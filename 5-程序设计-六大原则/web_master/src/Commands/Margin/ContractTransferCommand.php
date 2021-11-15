<?php

namespace App\Commands\Margin;

use App\Enums\Common\YesNoEnum;
use App\Models\Margin\MarginContract;
use App\Models\Margin\MarginTemplate;
use Carbon\Carbon;
use Framework\Console\Command;

class ContractTransferCommand extends Command
{
    protected $name = 'margin:contract-transfer';

    protected $description = 'tb_sys_margin_template表的数据迁移到tb_sys_margin_contract表';

    protected $help = '';

    public function handle()
    {
        if (MarginContract::query()->count() > 0) {
            echo '数据已处理';
            return;
        }

        $templates = MarginTemplate::query()
            ->where('is_del', YesNoEnum::NO)
            ->groupBy('product_id')
            ->selectRaw('max(update_time) as update_time')
            ->addSelect(['bond_template_id', 'seller_id', 'product_id', 'create_time', 'payment_ratio'])
            ->orderBy('create_time')
            ->get();

        $handleNum = 0;
        foreach ($templates as $template) {
            $contractNo = Carbon::parse($template->create_time)->format('Ymd') . rand(100000, 999999);
            $contract = [
                'contract_no' => $contractNo,
                'customer_id' => $template->seller_id,
                'product_id' => $template->product_id,
                'payment_ratio' => $template->payment_ratio,
                'bond_template_id' => $template->bond_template_id,
                'day' => 30,
                'status' => 1,
                'is_history_contract' => YesNoEnum::YES,
                'is_deleted' => YesNoEnum::NO,
                'create_time' => $template->create_time,
                'update_time' => $template->update_time,
            ];
            $contractId = MarginContract::query()->insertGetId($contract);

            if ($contractId) {
                MarginTemplate::query()->where('product_id', $template->product_id)->update(['contract_id' => $contractId]);

                $handleNum++;
                echo $template->product_id . '已生成合约数据，合约id：' . $contractNo . PHP_EOL;
            }
        }

        echo '已全部生成完毕，模板数量为：' . $templates->count() . '；合约数量为：' . $handleNum;
    }
}
