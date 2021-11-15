@extends('layouts.app')

@section('content')
    <table class="table table-striped">
        <thead>
        <tr>
            <th>#</th>
            <th>国家</th>
            <th>当前商品</th>
            <th>显示</th>
            <th>未显示</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($list as $countryName => $item)
            <tr>
                <th scope="row">{{ $loop->iteration }}</th>
                <td>{{ $countryName }}</td>
                <td>{{ implode(',',$item['all']) }}</td>
                <td>{{ !empty($item['show']) && count($item['show']) > 2 ? implode(',',$item['show']) : '' }}</td>
                <td>
                    <span
                        style="color: red">{{ count($item['show']) <= 2 ? implode(',',$item['show']).',' : '' }}
                    </span>
                    {{ !empty($item['not_show']) ? implode(',',$item['not_show']) : '' }}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @component('component.form_container',['title' => '修改推荐商品'])
        @component('component.form_alert')@endcomponent
        {!! Form::open(['url' => route('featured.save'),'class'=>"form-horizontal"]) !!}
        @component('component.form_group', ['class' => $errors->has('country') ? 'has-error' : ''])
            @slot('label')
                {!! Form::label('国家', '', ['class' => "col-md-4 control-label"]) !!}
            @endslot
            {!! Form::select('country', $countries, old('country'), ['placeholder' => '选择一个国家...','class'=>'form-control']); !!}
            @component('component.form_alert', ['field' => 'country'])@endcomponent
        @endcomponent
        @component('component.form_group',['class' => $errors->has('product_id') ? 'has-error' : ''])
            @slot('label')
                {!! Form::label('商品', '', ['class' => "col-md-4 control-label"]) !!}
            @endslot
            {!! Form::input('text','product_id',old('product_id'),['class'=>"form-control",'placeholder'=>'商品ID，多个用英文逗号隔开']) !!}
            @component('component.form_alert', ['field' => 'product_id'])@endcomponent
        @endcomponent
        @component('component.form_group')
            @slot('label')
                {!! Form::label('强制替换(不校验商品)', '', ['class' => "col-md-4 control-label"]) !!}
            @endslot
            {!! Form::checkbox('is_tough',true,false,['class'=>'checkbox']) !!}
        @endcomponent
        @component('component.form_group',['ele_class' => 'col-md-8 col-md-offset-4'])
            {!! Form::submit('保存',['class' => 'btn btn-primary']) !!}
        @endcomponent
        {!! Form::close() !!}
    @endcomponent
@endsection