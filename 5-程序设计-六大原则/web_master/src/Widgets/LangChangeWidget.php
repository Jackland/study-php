<?php

namespace App\Widgets;

use App\Enums\Common\LangLocaleEnum;
use App\Helper\LangHelper;
use Framework\Helper\Html;
use Framework\Widget\Widget;

class LangChangeWidget extends Widget
{
    public $mode = 1; // 1 为放在 header 上的模式，2 为 tab 左右切换的形式

    private $langChangeClass;

    public function __construct()
    {
        $this->langChangeClass = 'lang-change-' . random_int(1000, 9999);
    }

    public function run()
    {
        $currentLang = LangHelper::getCurrentCode();
        $fn = "mode{$this->mode}HTML";
        $html = $this->$fn($currentLang);
        $this->registerJs();
        return $html;
    }

    private function mode1Html($currentLang)
    {
        $changeMap = [
            LangLocaleEnum::EN_GB => ['切换为简体中文', LangLocaleEnum::ZH_CN],
            LangLocaleEnum::ZH_CN => ['Change to English', LangLocaleEnum::EN_GB],
        ];
        $lang = $changeMap[$currentLang];
        return Html::a('<i class="giga icon-qiehuanzhanghao" ></i> ' . $lang[0], 'javascript:;', [
            'class' => $this->langChangeClass,
            'data-lang' => $lang[1],
        ]);
    }

    private function mode2Html($currentLang)
    {
        $css = <<<CSS
.lang-change-container {
    display: inline-flex;
}
.lang-change-container a {
    text-align: center;
    font-size: 14px;
    width: 62px;
    color: #333;
    line-height: 24px;
    border: 1px solid #c1c1c1;
}
.lang-change-container a:first-child {
    border-radius: 2px 0 0 2px;
    border: 1px solid #c1c1c1;
}
.lang-change-container a:last-child {
    border-radius: 0 2px 2px 0;
    border: 1px solid #c1c1c1;
}
.lang-change-container a.active {
    background: #2861ce;
    border-color: #2861ce;
    color: #fff;
}
.lang-change-container a:not(.active):hover {
    border-color: #2861ce;
    color: #2861ce;
}
CSS;
        $this->getView()->style($css);

        $changeMap = [
            LangLocaleEnum::ZH_CN => '中文',
            LangLocaleEnum::EN_GB => 'English',
        ];
        $html[] = Html::beginTag('div', ['class' => 'lang-change-container']);
        foreach ($changeMap as $lang => $desc) {
            $html[] = Html::a($desc, 'javascript:;', [
                'class' => $this->langChangeClass . ($currentLang === $lang ? ' active' : ''),
                'data-lang' => $lang,
            ]);
        }
        $html[] = Html::endTag('div');
        return implode("\n", $html);
    }

    private function registerJs()
    {
        // 通过js修改url，因为存在通过 history.pushState 等操作修改 url 的情况
        $js = <<<JS
$('body').on('click', '.{$this->langChangeClass}', function () {
    var lang = $(this).data('lang'), oldUrl = window.location.href;
    var arr = oldUrl.split('#');
    var newUrl = arr[0];
    var hash = arr.length === 2 ? arr[1] : ''
    newUrl = newUrl.replace(/[\?&]lang=[^&]*&?$/g, '') + '&lang=' + lang
    window.location.href = hash ? [newUrl, hash].join('#') : newUrl
})
JS;
        $this->getView()->script($js);
    }
}
