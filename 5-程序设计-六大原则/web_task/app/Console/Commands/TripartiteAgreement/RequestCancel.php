<?php

namespace App\Console\Commands\TripartiteAgreement;

use App\Enums\Tripartite\TripartiteAgreementOperateType;
use App\Enums\Tripartite\TripartiteAgreementRequestStatus;
use App\Enums\Tripartite\TripartiteAgreementRequestType;
use App\Models\Tripartite\TripartiteAgreementOperate;
use App\Models\Tripartite\TripartiteAgreementRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RequestCancel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tripartite-agreement-request:cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '终止时间已到seller未处理,自动取消';

    /**
     * Execute the console command.
     * @throws \Throwable
     */
    public function handle()
    {
        $requests = TripartiteAgreementRequest::query()
            ->where('request_time', '<', Carbon::now())
            ->where('type', TripartiteAgreementRequestType::TERMINATE)
            ->where('status', TripartiteAgreementRequestStatus::PENDING)
            ->get();

        if ($requests->isEmpty()) {
            return;
        }

        $requestOperates = [];
        $requestIds = [];
        // 组装操作日志
        foreach ($requests as $request) {
            /** @var TripartiteAgreementRequest $request */
            $requestIds[] = $request->id;
            $requestOperates[] = [
                'agreement_id' => $request->agreement_id,
                'request_id' => $request->id,
                'customer_id' => $request->sender_id,
                'message' => 'Automatic cancellation due to expiration',
                'type' => TripartiteAgreementOperateType::TERMINATE_REQUEST_AUTO_CANCEL,
            ];
        }

        TripartiteAgreementRequest::query()->whereIn('id', $requestIds)->update(['status' => TripartiteAgreementRequestStatus::CANCEL]);

        TripartiteAgreementOperate::query()->insert($requestOperates);
    }
}