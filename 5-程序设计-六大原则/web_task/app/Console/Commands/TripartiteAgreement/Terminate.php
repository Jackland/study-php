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

class Terminate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tripartite-agreement:terminate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修改已生效的协议为终止';

    /**
     * Execute the console command.
     * @throws \Throwable
     */
    public function handle()
    {
        $agreements = TripartiteAgreement::query()
            ->whereIn('status', TripartiteAgreementStatus::approvedStatus())
            ->where('terminate_time', '<=', Carbon::now())
            ->where('is_deleted', YesNoEnum::NO)
            ->get();
        if ($agreements->isEmpty()) {
            return;
        }
        $agreementOperates = [];
        foreach ($agreements as $agreement) {
            /** @var TripartiteAgreement $agreement */
            $agreementOperates[] = [
                'agreement_id' => $agreement->id,
                'customer_id' => 0,
                'message' => 'Terminate',
                'type' => TripartiteAgreementOperateType::AUTO_TERMINATED,
            ];
        }

        $agreementIds = $agreements->pluck('id');
        $requests = TripartiteAgreementRequest::query()
            ->whereIn('agreement_id', $agreementIds)
            ->where('type', TripartiteAgreementRequestType::TERMINATE)
            ->where('status', TripartiteAgreementRequestStatus::PENDING)
            ->get();

        $requestOperates = [];
        // 组装终止过期取消的操作日志
        foreach ($requests as $request) {
            /** @var TripartiteAgreementRequest $request */
            $requestOperates[] = [
                'agreement_id' => $request->agreement_id,
                'request_id' => $request->id,
                'customer_id' => $request->sender_id,
                'message' => 'Automatic cancellation due to expiration',
                'type' => TripartiteAgreementOperateType::TERMINATE_REQUEST_AUTO_CANCEL,
            ];
        }

        $requestIds = $requests->pluck('id');
        \DB::transaction(function () use ($agreementIds, $requestIds) {
            // 修改协议状态为已终止
            TripartiteAgreement::query()->whereIn('id', $agreementIds)->update(['status' => TripartiteAgreementStatus::TERMINATED]);
            // 将终止申请的状态修改为已取消
            TripartiteAgreementRequest::query()->whereIn('id', $requestIds)->update(['status' => TripartiteAgreementRequestStatus::CANCEL]);
        });

        // 添加自动终止的操作日志
        TripartiteAgreementOperate::query()->insert($agreementOperates);
        // 过期取消的操作日志
        TripartiteAgreementOperate::query()->insert($requestOperates);
    }
}