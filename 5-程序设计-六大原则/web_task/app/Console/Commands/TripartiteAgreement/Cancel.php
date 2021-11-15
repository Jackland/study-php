<?php

namespace App\Console\Commands\TripartiteAgreement;

use App\Enums\Common\YesNoEnum;
use App\Enums\Tripartite\TripartiteAgreementOperateType;
use App\Enums\Tripartite\TripartiteAgreementStatus;
use App\Models\Tripartite\TripartiteAgreement;
use App\Models\Tripartite\TripartiteAgreementOperate;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Cancel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tripartite-agreement:cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'seller审核超时，自动取消';

    /**
     * Execute the console command.
     * @throws \Throwable
     */
    public function handle()
    {
        $agreements = TripartiteAgreement::query()
            ->where('status', TripartiteAgreementStatus::TO_BE_SIGNED)
            ->where('effect_time', '<=', Carbon::now()->subDay())
            ->where('is_deleted', YesNoEnum::NO)
            ->get();
        if ($agreements->isEmpty()) {
            return;
        }

        $operates = [];
        foreach ($agreements as $agreement) {
            /** @var TripartiteAgreement $agreement */
            $operates[] = [
                'agreement_id' => $agreement->id,
                'customer_id' => $agreement->buyer_id,
                'message' => 'Automatic cancellation due to expiration',
                'type' => TripartiteAgreementOperateType::CANCEL_AGREEMENT,
            ];
        }

        $agreementIds = $agreements->pluck('id');
        \DB::transaction(function () use ($agreementIds) {
            TripartiteAgreement::query()->whereIn('id', $agreementIds)->update(['status' => TripartiteAgreementStatus::CANCELED]);
        });

        // 自动过期日志
        TripartiteAgreementOperate::query()->insert($operates);
    }
}