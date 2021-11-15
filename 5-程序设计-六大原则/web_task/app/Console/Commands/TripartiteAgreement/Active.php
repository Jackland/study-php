<?php

namespace App\Console\Commands\TripartiteAgreement;

use App\Enums\Common\YesNoEnum;
use App\Enums\Tripartite\TripartiteAgreementOperateType;
use App\Enums\Tripartite\TripartiteAgreementRequestStatus;
use App\Enums\Tripartite\TripartiteAgreementRequestType;
use App\Enums\Tripartite\TripartiteAgreementStatus;
use App\Models\Tripartite\TripartiteAgreement;
use App\Models\Tripartite\TripartiteAgreementOperate;
use App\Models\Tripartite\TripartiteAgreementRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Active extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tripartite-agreement:active';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修改待生效的协议为已生效';

    /**
     * Execute the console command.
     * @throws \Throwable
     */
    public function handle()
    {
        $agreementIds = TripartiteAgreement::query()
            ->where('status', TripartiteAgreementStatus::TO_BE_ACTIVE)
            ->where('effect_time', '<=', Carbon::now())
            ->where('terminate_time', '>', Carbon::now())
            ->where('is_deleted', YesNoEnum::NO)
            ->pluck('id')
            ->toArray();
        if (empty($agreementIds)) {
            return;
        }

        $requests = TripartiteAgreementRequest::query()
            ->whereIn('agreement_id', $agreementIds)
            ->where('type', TripartiteAgreementRequestType::CANCEL)
            ->where('status', TripartiteAgreementRequestStatus::PENDING)
            ->get();

        $operates = [];
        // 组装取消申请过期取消的操作日志
        foreach ($requests as $request) {
            /** @var TripartiteAgreementRequest $request */
            $operates[] = [
                'agreement_id' => $request->agreement_id,
                'request_id' => $request->id,
                'customer_id' => $request->sender_id,
                'message' => 'Automatic cancellation due to expiration',
                'type' => TripartiteAgreementOperateType::CANCEL_REQUEST_AUTO_CANCEL,
            ];
        }


        $requestIds = $requests->pluck('id');
        \DB::transaction(function () use ($agreementIds, $requestIds) {
            // 修改协议状态为生效中
            TripartiteAgreement::query()->whereIn('id', $agreementIds)->update(['status' => TripartiteAgreementStatus::ACTIVE]);
            // 取消的申请自动过期
            TripartiteAgreementRequest::query()->whereIn('id', $requestIds)->update(['status' => TripartiteAgreementRequestStatus::CANCEL]);
        });

        // 自动过期日志
        TripartiteAgreementOperate::query()->insert($operates);
    }
}