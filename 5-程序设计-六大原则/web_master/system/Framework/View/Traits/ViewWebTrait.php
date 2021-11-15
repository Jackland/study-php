<?php

namespace Framework\View\Traits;

use Framework\View\Enums\ViewWebPosition;

/**
 * Web 视图相关功能
 */
trait ViewWebTrait
{
    use ViewWebMetaTrait;
    use ViewWebAssetTrait;

    private $params = [];

    /**
     * 设置或获取参数
     * 用于传参，比如可以从 content 往 layout 传参
     * @param string $key
     * @param null $value
     * @return void|mixed
     */
    public function params(string $key, $value = null)
    {
        if (func_num_args() === 1 && $value === null) {
            return $this->params[$key] ?? '';
        }

        $this->params[$key] = $value;
    }

    public function head()
    {
        return static::VIEW_WEB_HEAD_PLACEHOLDER;
    }

    public function beginBody()
    {
        return static::VIEW_WEB_BODY_BEGIN_PLACEHOLDER;
    }

    public function endBody()
    {
        return static::VIEW_WEB_BODY_END_PLACEHOLDER;
    }

    public function renderHead()
    {
        return implode("\n", array_filter([
            $this->renderMeta(),
            $this->renderCss(ViewWebPosition::HEAD),
            $this->renderStyle(ViewWebPosition::HEAD),
            $this->renderJs(ViewWebPosition::HEAD),
            $this->renderScript(ViewWebPosition::HEAD),
        ]));
    }

    public function renderBeginBody()
    {
        return implode("\n", array_filter([
            $this->renderCss(ViewWebPosition::BODY_BEGIN),
            $this->renderStyle(ViewWebPosition::BODY_BEGIN),
            $this->renderJs(ViewWebPosition::BODY_BEGIN),
            $this->renderScript(ViewWebPosition::BODY_BEGIN),
        ]));
    }

    public function renderEndBody()
    {
        return implode("\n", array_filter([
            $this->renderCss(ViewWebPosition::BODY_END),
            $this->renderStyle(ViewWebPosition::BODY_END),
            $this->renderJs(ViewWebPosition::BODY_END),
            $this->renderScript(ViewWebPosition::BODY_END),
        ]));
    }
}
