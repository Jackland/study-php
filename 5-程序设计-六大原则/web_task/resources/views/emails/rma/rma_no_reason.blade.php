<style type="text/css">
    table.gridtable {
        font-family: verdana, arial, sans-serif;
        font-size: 11px;
        color: #333333;
        border-width: 1px;
        border-color: #666666;
        border-collapse: collapse;
        width: 100%;
        max-width: 100%;
    }

    table.gridtable th {
        border-width: 1px;
        padding: 8px;
        border-style: solid;
        border-color: #666666;
        background-color: #dedede;
    }

    table.gridtable td {
        border-width: 1px;
        padding: 8px;
        border-style: solid;
        border-color: #666666;
        background-color: #ffffff;
    }
</style>
<div style="padding-right: 15px; padding-left: 15px; margin: 0 auto">
    <h2 style="text-align: center;color: red">{{$subject}}</h2>
    <table class="gridtable">
        <thead>
        <tr>
            @foreach ($header as $head)
                <th>{{$head}}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach ($content as $datas)
            <tr>
                @foreach ($datas as $data)
                    <td>{{$data}}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
