@component('emails.statistic.message',['title'=>$title])
    @if(count($content)>0)
        @component('mail::table')
            | {{join(' | ',$header)}} |
            | {{str_repeat(' :--- |',count($header))}}
            @foreach($content as $c)
                | {{join(' | ',$c)}} |
            @endforeach
        @endcomponent
    @endif
@endcomponent
