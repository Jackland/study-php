@component('emails.statistic.message',['title'=>$title])
    @if(count($content)>0)
        @component('mail::table')
            | {{join(' | ',$header)}} |
            | {{str_repeat(' :--- |',count($header))}}
            @foreach($content as $c)
                | {{join(' | ',$c)}} |
            @endforeach
        @endcomponent
    @else
        # 此期间没有产生返点充值数据
    @endif
@endcomponent
