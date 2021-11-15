<style>
    table.rma-record {
        min-width: 80%;
        max-width: 100%;
        margin: 0 auto;
        font-size: 14px;
        border-spacing: 0
    }

    table.rma-record tr > th,
    table.rma-record tr > td {
        border: 1px solid #ccc;
        padding: 8px 12px;
        text-align: center;
    }

    table.rma-record tr > th:not(:last-child),
    table.rma-record tr > td:not(:last-child) {
        border-right: none;
    }

    table.rma-record tr > th {
        background-color: #f3f5f7;
        border-bottom: none;
    }
</style>

<table class="rma-record">
    <thead>
    <tr>
        <th>国别</th>
        <th>Buyer Name(Number)</th>
        <th>账号类型</th>
        <th>Item Code</th>
        <th>数量</th>
        <th>体积(m<sup>3</sup>)</th>
        <th>在库天数</th>
        <th>历史已支付仓租金额</th>
        <th>本次需支付仓租金额</th>
        <th>信用额度余额</th>
        <th>欠款</th>
        <th>RMA ID</th>
    </tr>
    </thead>
    {{-- 这里全写在这里 尽量不要改动 --}}
    <tbody>
    @php
        use App\Models\Currency;
        use App\Models\FeeOrder\FeeOrder;
        use App\Models\Rma\YzcRmaOrder;
        $feeOrder = FeeOrder::with(['buyer', 'storageDetails'])->find($id);
        $rma = YzcRmaOrder::find($feeOrder->order_id);
    @endphp
    <tr>
        <td>{{$feeOrder->buyer->country->name}}</td>
        <td>{{ $feeOrder->buyer->nickname }}({{ $feeOrder->buyer->user_number }})</td>
        @if($feeOrder->buyer->accounting_type  == 2)
            <td>外部</td>
        @else
            <td>内部</td>
        @endif
        <td>{{ $feeOrder->storageDetails->first()->storageFee->product_sku }}</td>
        <td>{{ $feeOrder->storageDetails->count()  }}</td>
        <td>{{ $feeOrder->storageDetails->first()->storageFee->volume_m }}</td>
        <td>{{ $feeOrder->storageDetails->first()->storageFee->days }}</td>
        <td>{{ Currency::format($feeOrder->storageDetails->sum('storage_fee_paid'),$feeOrder->buyer->country_id)  }}</td>
        <td>{{ Currency::format($feeOrder->storageDetails->sum('storage_fee'),$feeOrder->buyer->country_id)  }}</td>
        <td>{{ Currency::format($feeOrder->buyer->line_of_credit,$feeOrder->buyer->country_id)  }}</td>
        <td>{{ Currency::format($feeOrder->fee_total - $feeOrder->balance,$feeOrder->buyer->country_id)  }}</td>
        <td>{{ $rma->rma_order_id  }}</td>
    </tr>
    </tbody>
</table>
