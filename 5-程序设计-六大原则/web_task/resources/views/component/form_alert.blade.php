@if (empty($field))
    {{--这个error message 是AdminErrorShowException抛出异常时设置的错误信息key值--}}
    {{--如果需要特殊指定，可以传递变量errorMsgField--}}
    @if ($errors->has($errorMsgFiled ?? 'error_message'))
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->get($errorMsgFiled ?? 'error_message') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">
            <ul>
                @foreach ((array)session('success') as $success)
                    <li>{{ $success }}</li>
                @endforeach
            </ul>
        </div>
    @endif
@elseif(!empty($errors) && $errors->has($field))
    <span class="help-block">
     <b> {{ implode('<br>', $errors->get($field)) }} </b>
    </span>
@endif