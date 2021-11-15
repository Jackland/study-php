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
    {!! Form::open(['url' => route('buyer.loginLimit'),'class'=>"form-inline",'method'=>'get']) !!}
    @component('component.form_group')
        {!! Form::input('text','keyword',old('keyword'),['class'=>"form-control",'placeholder'=>'邮箱']) !!}
    @endcomponent
    @component('component.form_group')
        {!! Form::submit('查询',['class' => 'btn btn-default']) !!}
    @endcomponent
    {!! Form::close() !!}
    @if (!empty($buyers))
        {!! Form::open(['url' => route('buyer.loginLimit.save'),'class'=>"form-inline",'method'=>'post']) !!}
        <p>用户信息</p>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>#</th>
                <th>邮箱</th>
                <th>IP</th>
                <th>错误次数</th>
                <th>修改次数</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($buyers as $buyer)
                <tr>
                    <th scope="row">{{ $loop->iteration }}</th>
                    <td>
                        {{ $buyer->email }}
                        {!! Form::input('hidden','customer_login_id[]',$buyer->customer_login_id) !!}
                    </td>
                    <td>
                        {{ $buyer->ip }}
                    </td>
                    <td>
                        {{ $buyer->total }}
                    </td>
                    <td>
                        {!! Form::input('text','totals[]','',['class'=>"form-control"]) !!}
                    </td>
                </tr>
            @endforeach
            {!! Form::input('hidden','keyword',old('keyword')) !!}
            {!! Form::submit('保存',['class' => 'btn btn-default','name'=>'save']) !!}
            </tbody>
        </table>
        {!! Form::close() !!}
    @endif
@endsection