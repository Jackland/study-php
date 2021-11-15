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
    {!! Form::open(['url' => route('rebate.repair'),'class'=>"form-inline",'method'=>'get']) !!}
    @if (!empty($repairData))
        @component('component.form_group')
            {!! Form::submit('执行',['class' => 'btn btn-default','name'=>'repair']) !!}
        @endcomponent
    @endif
    {!! Form::close() !!}
    @if ($orders->isEmpty())
        无错误数据
    @else
        <p>错误信息</p>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>#</th>
                <th>Order Id</th>
                <th>Agreement ID</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($orders as $order)
                <tr>
                    <th scope="row">{{ $loop->iteration }}</th>
                    <td>{{ $order->order_id }}</td>
                    <td>{{ $order->agreement_id }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    @if (!empty($repairData))
        <p>待修复数据</p>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>#</th>
                <th>AgreementId</th>
                <th>ItemId</th>
                <th>ProductId</th>
                <th>qty</th>
                <th>OrderId</th>
                <th>OrderProductId</th>
                <th>type</th>
                <th>memo</th>
                <th>CreateUserName</th>
                <th>CreateTime</th>
                <th>UpdateTime</th>
                <th>ProgramCode</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($repairData as $data)
                <tr>
                    <th scope="row">{{ $loop->iteration }}</th>
                    <td>{{ $data['agreement_id'] }}</td>
                    <td>{{ $data['item_id'] }}</td>
                    <td>{{ $data['product_id'] }}</td>
                    <td>{{ $data['qty'] }}</td>
                    <td>{{ $data['order_id'] }}</td>
                    <td>{{ $data['order_product_id'] }}</td>
                    <td>{{ $data['type'] }}</td>
                    <td>{{ $data['memo'] }}</td>
                    <td>{{ $data['create_user_name'] }}</td>
                    <td>{{ $data['create_time'] }}</td>
                    <td>{{ $data['update_time'] }}</td>
                    <td>{{ $data['program_code'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection