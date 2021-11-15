@extends('layouts.app')

@section('content')
    @component('component.form_container',['title' => 'Product Tag','panel_class' => 'col-md-10 col-md-offset-1'])
        @component('component.message')
        @endcomponent
        {!! Form::open(['url' => route('product.changeTag'),'class'=>"form-horizontal"]) !!}
        @component('component.form_group')
            @slot('label')
                {!! Form::label('Product sku or Product Id', '', ['class' => "col-md-4 control-label"]) !!}
            @endslot
            {!! Form::input('text','filter_sku_id','',['class'=>"form-control"]) !!}
        @endcomponent
        @if(isset($products) && $products->count() > 0)
            @component('component.form_group')
                @slot('label')
                    {!! Form::label('Please Select', '', ['class' => "col-md-4 control-label"]) !!}
                @endslot
                @foreach($products as $product)
                    <div class="radio">
                        <label>
                            {!! Form::radio('filter_product_id',$product->product_id,$loop->first) !!}
                            {{ $product->sku }} - {{ $product->customer->nickname  }}
                            ({{ $product->customer->email }})
                        </label>
                    </div>
                @endforeach
            @endcomponent
            @component('component.form_group')
                @slot('label')
                    {!! Form::label('Choose Tag', '', ['class' => "col-md-4 control-label"]) !!}
                @endslot
                {!! Html::tag('label', [Form::radio('filter_tag_id',2,true).'Part'],['class'=>'radio-inline']) !!}
                {!! Html::tag('label', [Form::radio('filter_tag_id',3).'Combo'],['class'=>'radio-inline']) !!}
                {!! Html::tag('label', [Form::radio('filter_tag_id',1).'LTL'],['class'=>'radio-inline']) !!}
            @endcomponent
        @endif
        @component('component.form_group',['ele_class' => 'col-md-8 col-md-offset-4'])
            {!! Form::submit('Submit',['class' => 'btn btn-primary']) !!}
            {!! Html::tag('a','Reset',['href'=> route('product.changeTag'),'class'=> 'btn btn-default']) !!}
        @endcomponent
        {!! Form::close() !!}
    @endcomponent
@endsection


