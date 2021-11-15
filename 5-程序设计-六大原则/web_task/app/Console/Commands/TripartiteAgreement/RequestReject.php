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

class RequestReject extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tripartite-agreement-request:reject';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修改已过期的请求（终止和取消）状态为拒绝';

    /**
     * Execute the console command.
     * @throws \Throwable
     */
    public function handle()
    {
        $requests = TripartiteAgreementRequest::query()->with('agreement')
            ->where('create_time', '<', Carbon::now()->subDay(7))
            ->where('status', TripartiteAgreementRequestStatus::PENDING)
            ->get();

        $requestOperates = [];
        $requestIds = [];
        // 组装操作日志
        foreach ($requests as $request) {
            /** @var TripartiteAgreementRequest $request */
            $requestIds[] = $request->id;
            $customerType = $request->handle_id == $request->agreement->buyer_id ? 'Buyer' : 'Seller';
            $requestOperates[] = [
                'agreement_id' => $request->agreement_id,
                'request_id' => $request->id,
                'customer_id' => $request->handle_id,
                'message' => "The request was not processed by the {$customerType} within the specified time. The system set the result as 'Refused' by default.",
                'type' => $request->type == TripartiteAgreementRequestType::TERMINATE ? TripartiteAgreementOperateType::REJECT_TERMINATED_REQUEST : TripartiteAgreementOperateType::REJECT_CANCEL_REQUEST,
            ];
        }

        TripartiteAgreementRequest::query()->whereIn('id', $requestIds)->update(['status' => TripartiteAgreementRequestStatus::REJECTED]);

        TripartiteAgreementOperate::query()->insert($requestOperates);
    }
}