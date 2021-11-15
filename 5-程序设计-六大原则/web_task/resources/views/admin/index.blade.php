@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Dashboard</div>

                <div class="panel-body">
                    @if (session('status'))
                        <div class="alert alert-success">
                            {{ session('status') }}
                        </div>
                    @endif
                    <ul>
                        <li ><a href="{{ route('featured.index') }}">首页推荐商品替换</a></li>
                        <li ><a href="{{ route('stock.bind') }}">库存匹配</a></li>
                        <li ><a href="{{ route('stock.fba') }}">FBA出库</a></li>
                        <li ><a href="{{ route('stock.bo') }}">BO出库</a></li>
                        <li ><a href="{{ route('product.changeTag') }}">修改产品信息</a></li>
                        <li ><a href="{{ route('rebate.repair') }}">返点交易信息添加</a></li>
                        <li ><a href="{{ route('buyer.airwallex') }}">Airwallex账号绑定</a></li>
                        <li ><a href="{{ route('buyer.loginLimit') }}">用户登入次数修改</a></li>
                        <li ><a href="{{ route('customer.telephoneIgnoreVerify') }}">customer手机号置为不需要验证</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
