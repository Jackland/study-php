@extends('layouts.app')
@section('content')
    @if (!empty($msg))
        <div class="alert alert-danger">
            <ul>
                @foreach ((array)$msg as $m)
                    <li>{{ $m }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @component('component.form_alert')@endcomponent
    {!! Form::open(['url' => route('stock.bo'),'class'=>"form-inline",'method'=>'get']) !!}
    @component('component.form_group')
        {!! Form::input('text','user_number',old('user_number'),['class'=>"form-control",'placeholder'=>'User Number']) !!}
    @endcomponent
    @component('component.form_group')
        {!! Form::input('text','item_code',old('item_code'),['class'=>"form-control",'placeholder'=>'Item Code']) !!}
    @endcomponent
    @component('component.form_group')
        {!! Form::number('qty',old('qty'),['class'=>"form-control",'placeholder'=>'需要出的数量']) !!}
    @endcomponent
    @component('component.form_group')
        {!! Form::input('text','sales_order_id','',['class'=>"form-control",'placeholder'=>'Sales Oder ID(非必填)']) !!}
    @endcomponent
    @component('component.form_group')
        {!! Form::input('text','order_id','',['class'=>"form-control",'placeholder'=>'Order Id(非必填)']) !!}
    @endcomponent
    @component('component.form_group')
        {!! Form::submit('查询',['class' => 'btn btn-default']) !!}
    @endcomponent
    @if (!empty($canSave))
        @component('component.form_group')
            {!! Form::submit('执行',['class' => 'btn btn-default','name'=>'save']) !!}
        @endcomponent
    @endif
    {!! Form::close() !!}
    {{--订单信息--}}
    @if (!empty($salesOrder))
        <p>订单信息</p>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>Order ID</th>
                <th>Id</th>
                <th>Order Status</th>
                <th>Buyer Id</th>
            </tr>
            </thead>
            <tbody>
                <tr>
                    <th>{{ $salesOrder->order_id }}</th>
                    <td>{{ $salesOrder->id }}</td>
                    <td>{{ $salesOrder->order_status }}</td>
                    <td>{{ $salesOrder->buyer_id }}</td>
                </tr>
            </tbody>
        </table>
    @endif
    @if (!empty($salesOrderLines))
        <p>订单明细</p>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>Id</th>
                <th>Item Code</th>
                <th>Qty</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($salesOrderLines as $line)
                <tr>
                    <th>{{ $line->id }}</th>
                    <td>{{ $line->item_code }}</td>
                    <td>{{ $line->qty }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    {{--库存信息--}}
    @if (!empty($stockList))
        <p>库存信息</p>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>#</th>
                <th>Seller Id</th>
                <th>Buyer Id</th>
                <th>Cost Id</th>
                <th>Order Id</th>
                <th>Order Product Id</th>
                <th>Product Id</th>
                <th>Onhand Qty</th>
                <th>Left Qty</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($stockList as $stock)
                <tr>
                    <th scope="row">{{ $loop->iteration }}</th>
                    <td>{{ $stock->seller_id }}</td>
                    <td>{{ $stock->buyer_id }}</td>
                    <td>{{ $stock->id }}</td>
                    <td>{{ $stock->oc_order_id }}</td>
                    <td>{{ $stock->order_product_id }}</td>
                    <td>{{ $stock->sku_id }}</td>
                    <td>{{ $stock->onhand_qty }}</td>
                    <td>{{ $stock->leftQty }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    {{--已绑定信息--}}
    @if (!empty($associatedList))
        <p>已绑定库存</p>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>#</th>
                <th>Order Id</th>
                <th>Order Product Id</th>
                <th>Qty</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($associatedList as $associated)
                <tr>
                    <th scope="row">{{ $loop->iteration }}</th>
                    <td>{{ $associated->order_id }}</td>
                    <td>{{ $associated->order_product_id }}</td>
                    <td>{{ $associated->qty }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    {{--仓租信息--}}
    @if (!empty($storageFeeData))
        <p>需要使用仓租信息</p>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>#</th>
                <th>Id</th>
                <th>Order Id</th>
                <th>Order Product Id</th>
                <th>Fee Unpaid</th>
                <th>Days</th>
                <th>Status</th>
                <th>Sales Order Id</th>
                <th>Sales Order Line Id</th>
                <th>End Type</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($storageFeeData as $storageFee)
                <tr>
                    <th scope="row">{{ $loop->iteration }}</th>
                    <td>{{ $storageFee->id }}</td>
                    <td>{{ $storageFee->order_id }}</td>
                    <td>{{ $storageFee->order_product_id }}</td>
                    <td>{{ $storageFee->fee_unpaid }}</td>
                    <td>{{ $storageFee->days }}</td>
                    <td>{{ $storageFee->status }}</td>
                    <td>{{ $storageFee->sales_order_id }}</td>
                    <td>{{ $storageFee->sales_order_line_id }}</td>
                    <td>{{ $storageFee->end_type }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    {{--待执行sql--}}
    @if (!empty($sqls))
        <p>待执行sql</p>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>#</th>
                <th>Sql</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($sqls as $sql)
                <tr>
                    <th scope="row">{{ $loop->iteration }}</th>
                    <td>{{ $sql }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection