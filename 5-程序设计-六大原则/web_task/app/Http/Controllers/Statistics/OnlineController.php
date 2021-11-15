<?php

namespace App\Http\Controllers\Statistics;

use App\Mail\OnlineStatistic;
use App\Models\Statistics\RequestRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class OnlineController extends Controller
{
    private $model;
    public function __construct()
    {
        $this->model = new RequestRecord();
    }

    public function test()
    {
        echo date('Y-m-d H:i:s');
    }

    public function buyer(Request $request)
    {
        $request = new RequestRecord();
        $results = $request->getLastDayOnlineForBuyer();
        $content = [];
        foreach ($results as $result) {
            $content[] = [
                $result->firstname . $result->lastname,
                $result->accounting_type == 1 ? '内部' : '外部',
                $result->country_name
            ];
        }
        $data = [
            'subject' => '每日在线平台买家名单',
            'title' => date('Y-m-d', strtotime("-1 day")) . '（US Time）在线的平台买家列表',
            'header' => ['UserName', '内/外部', 'Country'],
            'content' => $content,
        ];
        return new OnlineStatistic($data);
    }

    public function seller(Request $request)
    {
        $request = new RequestRecord();
        $results = $request->getLastDayOnlineForSeller();
        $content = [];
        foreach ($results as $result) {
            $content[] = [
                $result->screenname,
                $result->accounting_type == 1 ? '内部' : '外部',
                $result->country_name
            ];
        }
        $data = [
            'subject' => '每日在线平台供应商名单',
            'title' => date('Y-m-d', strtotime("-1 day")) . '（US Time）在线的平台供应商名单',
            'header' => ['店铺名称', '内/外部', 'Country'],
            'content' => $content,
        ];
        return new OnlineStatistic($data);
    }
}
