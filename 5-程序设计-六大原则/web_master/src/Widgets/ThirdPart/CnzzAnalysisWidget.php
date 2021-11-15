<?php

namespace App\Widgets\ThirdPart;

use App\Assets\Common\CnzzAnalysisAsset;
use Framework\Helper\Html;
use Framework\Widget\Widget;

class CnzzAnalysisWidget extends Widget
{
    /**
     * @inheritDoc
     */
    public function run()
    {
        // CNZZ 统计的 js 代码，如：'https://s4.cnzz.com/z_stat.php?id=1279194549&web_id=1279194549'
        $cnzzUrl = get_env('CNZZ_ANALYSIS_URL');
        // CNZZ 统计的 js 代码中的站点 id，如：1279194549
        $cnzzId = get_env('CNZZ_ANALYSIS_ID');
        if (!$cnzzUrl || !$cnzzId) {
            return '';
        }
        // 点击事件时是否 console 输出日志
        $eventConsoleLog = (int)get_env('CNZZ_ANALYSIS_EVENT_CONSOLE_LOG');
        $customerId = session('customer_id', 0);
        $loginStatus = intval($customerId > 0);

        $this->getView()->registerAssets(CnzzAnalysisAsset::class);
        $options = json_encode([
            'loginStatus' => $loginStatus,
            'customerId' => $customerId,
            'eventConsoleLog' => $eventConsoleLog,
        ]);
        $this->getView()->script("window.CNZZ.init({$options});");

        return Html::tag('span', '', ['id' => 'cnzz_stat_icon_' . $cnzzId, 'style' => 'display:none']);
    }
}
