<?php

namespace App\Commands\Margin;

use App\Enums\Agreement\AgreementCommonPerformerAgreementType;
use App\Enums\Common\YesNoEnum;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Margin\MarginPerformerApplyStatus;
use App\Models\Agreement\AgreementCommonPerformer;
use App\Models\Margin\MarginMessage;
use App\Models\Margin\MarginPerformerApply;
use Carbon\Carbon;
use Framework\Console\Command;

class ApproveMarginPerformerApplyCommand extends Command
{
    protected $name = 'margin:approve-performer-apply';

    //#27869 现货保证金共同履约人添加之后审核流程去掉
    protected $description = '针对待平台审批中的历史申请数据，默认处理其平台审批为【同意】';

    protected $help = '';

    public function handle()
    {
        // 针对于待seller审核的申请，program_code需更新为v2
        MarginPerformerApply::query()->alias('p')
            ->join('tb_sys_margin_agreement as a', 'p.agreement_id', '=', 'a.id')
            ->where('a.status', MarginAgreementStatus::SOLD)
            ->where('a.expire_time', '>', Carbon::now()->toDateTimeString())
            ->where('p.check_result', MarginPerformerApplyStatus::PENDING)
            ->where('p.seller_approval_status', MarginPerformerApplyStatus::PENDING)
            ->update(['p.program_code' => MarginPerformerApply::PROGRAM_CODE_V2]);


        $marginPerformerApplies = MarginPerformerApply::query()->alias('p')
            ->join('tb_sys_margin_agreement as a', 'p.agreement_id', '=', 'a.id')
            ->where('a.status', MarginAgreementStatus::SOLD)
            ->where('a.expire_time', '>', Carbon::now()->toDateTimeString())
            ->where('p.check_result', MarginPerformerApplyStatus::PENDING)
            ->where('p.seller_approval_status', MarginPerformerApplyStatus::APPROVED)
            ->select(['p.*', 'a.product_id'])
            ->get();

        foreach ($marginPerformerApplies as $marginPerformerApply) {
            /** @var MarginPerformerApply $marginPerformerApply */
            $marginPerformerApply->check_result = MarginPerformerApplyStatus::APPROVED;
            $marginPerformerApply->update_time = Carbon::now()->toDateTimeString();
            $marginPerformerApply->update_user_name = 'Automatic'; // 平台用户
            $marginPerformerApply->save();

            // 审核同意的插入共同履约人表
            AgreementCommonPerformer::query()->insert([
                'agreement_type' => AgreementCommonPerformerAgreementType::MARGIN,
                'agreement_id' => $marginPerformerApply->agreement_id,
                'product_id' => $marginPerformerApply->product_id,
                'buyer_id' => $marginPerformerApply->performer_buyer_id,
                'is_signed' => YesNoEnum::NO,
                'create_user_name' => 'Automatic',
                'create_time' => Carbon::now()->toDateTimeString(),
            ]);

            MarginMessage::insert([
                'margin_agreement_id' => $marginPerformerApply->agreement_id,
                'customer_id' => 0,
                'message' => 'The Marketplace has approved the Add a Partner request.',
                'create_time' => Carbon::now(),
            ]);

            echo $marginPerformerApply->agreement_id . 'approved success!' . PHP_EOL;
        }
    }
}
