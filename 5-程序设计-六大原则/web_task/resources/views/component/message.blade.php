@if (session('errors') && session('errors')->count() > 0)
    <div class="alert alert-danger">
        <ul>
            @foreach (session('errors')->all() as $error)
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