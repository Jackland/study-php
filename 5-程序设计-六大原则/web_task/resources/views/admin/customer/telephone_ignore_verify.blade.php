@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">

            {!! Form::open(['url' => route('customer.telephoneIgnoreVerify'),'class'=>"form",'method'=>'get']) !!}
            @component('component.form_group')
                {!! Form::input('text','keyword',old('keyword'),['class'=>"form-control",'placeholder'=>'user_number，多个逗号隔开']) !!}
            @endcomponent
            @component('component.form_group')
                {!! Form::submit('查询',['class' => 'btn btn-default']) !!}
            @endcomponent
            {!! Form::close() !!}

            @if(count($customers) > 0)
            <table class="table table-bordered">
                <caption>用户信息</caption>
                <thead>
                <tr>
                    <th>#</th>
                    <th>user_number</th>
                    <th>name</th>
                    <th>telephone</th>
                    <th>telephone_verified_at</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($customers as $customer)
                    <tr>
                        <td scope="row">{{ $loop->iteration }}</td>
                        <td>
                            {{ $customer->user_number }}
                        </td>
                        <td>
                            {{ $customer->firstname }} {{ $customer->lastname }}
                        </td>
                        <td>
                            {{ $customer->telephone }}
                        </td>
                        <td>
                            @if($customer->telephone_verified_at !== 0)
                                <span class="label label-danger">非0</span>
                            @endif
                            {{ $customer->telephone_verified_at }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
                <div class="alert alert-info">
                    <p>SQL:</p>
                    <p>{{ $sql  }}</p>
                    <p>
                        {!! Form::open(['url' => route('customer.telephoneIgnoreVerify'), 'class' => 'form', 'method' => 'post']) !!}
                        {!! Form::input('hidden', 'keyword', old('keyword')) !!}
                        {!! Form::input('hidden', 'sql', $sql) !!}
                        {!! Form::submit('执行', ['class' => 'btn btn-success']) !!}
                        {!! Form::close() !!}
                    </p>
                </div>
            @endif

        </div>
    </div>
@endsection