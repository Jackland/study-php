<style>
    * {
        padding: 0;
        margin: 0;
    }

    .container {
        width: 85%;
        min-width: 1000px;
        margin: 0 auto;

    }

    .header h1 {
        text-align: center;
        background: #EDEFF3;
        padding: 7px 0;
        margin-bottom: 3px;
        font-size: 16px;
        color: #465B82;
    }

    .content {
        background: #EDEFF3;
        min-height: 300px;
        position: relative;
        padding: 10px 10px 30px 10px;
        font-family: "Roboto", Helvetica, Arial, serif;
        font-size: 14px;
        line-height: 1.428571429;
        color: #333333;
    }
    @if (empty($view_type) || $view_type == 1)
    .content img{
        width: 100%;
    }
    @endif

    .footer {
        font-weight: bold;
        color: #878787;
        margin-top: 100px;
        border-top: 1px solid #ddd;
        padding-top: 20px;
    }

    .footer img {
        width: 210px;
    }

    .footer .imp {
        width: 18px;
        vertical-align: sub;
    }

    .footer .logo {
        text-align: center;
    }

    .footer .power {
        text-align: center;
    }

    .footer strong {
        display: block;
        margin-bottom: 20px;
    }

    .attach {
        margin: 20px 5px 5px 10px;
        position: absolute;
        bottom: 0;
    }

    .attach-item {
        background: #e1f3f1;
        border: 1px dashed #252121;
        padding: 2px;
    }
    .table > thead > tr > th,
    .table > thead > tr > td,
    .table > tbody > tr > th,
    .table > tbody > tr > td,
    .table > tfoot > tr > th,
    .table > tfoot > tr > td {
        padding: 10px 8px;
        line-height: 1.428571429;
        vertical-align: top;
        border: 1px solid #eee;
    }
    .table > thead > tr > th,
    .table > thead > tr > td{
        font-weight: bold;
    }
    .table {
        border-collapse: collapse;
    }
    .table-bordered {
        border: 1px solid #eee;
    }
    .table {
        width: 100%;
        max-width: 100%;
        margin-bottom: 20px;
        border-collapse: separate;
    }


</style>
<div class="container">
    <div class="header">
        <h1>{!! $subject !!} </h1>

    </div>
    <div class="content">
        {!! $body !!}
        @if(isset($attach))
            <div class="attach">
                @foreach($attach as $item)
                    <a class="attach-item" href="{{$item['url']}}" target="_blank">{{$item['name']}}</a>
                @endforeach
            </div>
        @endif
    </div>
    <div class="footer">
        <p>You received this email because you have registered on GIGACLOUD Logistics and enabled the notification email
            set in the message center. This is an automated message sent by GIGACLOUD， please don’t reply to it.
            If you don’t want to receive automated emails,please click to <a href="https://b2b.gigacloudlogistics.com/index.php?route=account/login&redirect=message/setting" target="_blank">unsubscribe</a>. For any questions during the
            process of purchase, please click <a href="https://b2b.gigacloudlogistics.com/index.php?route=account/login&redirect=information/information" target="_blank"> HELP CENTER</a>.</p>
        <p>If you have any questions or suggestion, please click <a href="https://b2b.gigacloudlogistics.com/index.php?route=information/contact&from=email" target="_blank">CONTACT US</a>.</p>
        <strong> <img  class="imp" src="https://b2b.gigacloudlogistics.com/image/icons/important_18x18.png" > Important Notice: Any transaction or refund should be placed only on GIGACLOUD.</strong>
        <p class="logo"><img src="https://b2b.gigacloudlogistics.com/image/catalog/Logo/logo-s.png" alt="logo"></p>
        <p class="power">
            Powered By B2B.GIGACLOUDLOGISTICS © 2019
        </p>
    </div>
</div>