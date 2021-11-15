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
    {!! Form::open(['url' => route('buyer.airwallex'),'class'=>"form-inline",'method'=>'get']) !!}
    @component('component.form_group')
        {!! Form::input('text','keyword',old('keyword'),['class'=>"form-control",'placeholder'=>'手机号或者邮箱']) !!}
    @endcomponent
    @component('component.form_group')
        {!! Form::submit('查询',['class' => 'btn btn-default']) !!}
    @endcomponent
    {!! Form::close() !!}
    @if (!empty($buyers))
        {!! Form::open(['url' => route('buyer.airwallex.save'),'class'=>"form-inline",'method'=>'post']) !!}
        <p>用户信息</p>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>#</th>
                <th>Buyer Id</th>
                <th>手机号或邮箱</th>
                <th>Airwallex Id</th>
                <th>修改Airwallex Id</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($buyers as $buyer)
                <tr>
                    <th scope="row">{{ $loop->iteration }}</th>
                    <td>
                        {{ $buyer->buyer_id }}
                        {!! Form::input('hidden','buyer_id[]',$buyer->buyer_id) !!}
                    </td>
                    <td>{{ $buyer->airwallex_email }}</td>
                    <td>
                        {{ $buyer->airwallex_id }}
                    </td>
                    <td>
                        {!! Form::input('text','airwallex_id[]','',['class'=>"form-control",'placeholder'=>'airwallex id']) !!}
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