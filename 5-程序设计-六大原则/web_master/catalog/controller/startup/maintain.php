<?php

use App\Services\Maintain\MaintainService;
use Framework\Controller\Controller;

/**
 * 维护页面
 */
class ControllerStartupMaintain extends Controller
{

    public function index()
    {
        if (!app(MaintainService::class)->isMaintain()) {
            return;
        }
        $downEndTime = config('maintain.down_end_time');
        $downEstimateTime = config('maintain.down_estimate_time');
        $leftSeconds = max((!empty($downEndTime) ? strtotime($downEndTime) - time() : 0), 0);
        return response(view('maintain/maintain', compact('leftSeconds', 'downEstimateTime')));
    }

}
