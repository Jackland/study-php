<?php

namespace Framework\Debug;

use DebugBar\DebugBar;
use DebugBar\JavascriptRenderer as BaseJavascriptRenderer;
use Framework\Helper\Json;
use Illuminate\Filesystem\Filesystem;

class JavascriptRenderer extends BaseJavascriptRenderer
{
    private $publishBasePath;
    private $publishUrlPath;

    private $files;

    public function __construct(DebugBar $debugBar, $baseUrl = null, $basePath = null)
    {
        $this->publishBasePath = $basePath;
        $this->publishUrlPath = $baseUrl;

        parent::__construct($debugBar);

        $this->files = new Filesystem();
    }

    /**
     * @inheritDoc
     */
    public function renderHead()
    {
        $this->dumpHeadAssets();
        $this->dumpAssets();

        if (!$this->files->isDirectory($this->publishBasePath)) {
            $this->files->makeDirectory($this->publishBasePath);
        }
        $cssTs = $this->getModifiedTime('css');
        $jsTs = $this->getModifiedTime('js');
        $cacheFile = $this->publishBasePath . '/__ts_cache';
        $tsCache = $tsCacheOld = $this->files->exists($cacheFile) ? Json::decode($this->files->get($cacheFile)) : [];
        $fontawesomeExist = $this->cssVendors['fontawesome'];
        if (!isset($tsCache['css']) || $tsCache['css'] !== $cssTs) {
            unset($this->cssVendors['fontawesome']);
            $this->dumpCssAssets($this->publishBasePath . '/debugbar.css');
            $tsCache['css'] = $cssTs;
            if ($fontawesomeExist) {
                $this->files->copyDirectory(
                    $this->getBasePath() . '/vendor/font-awesome',
                    $this->publishBasePath . '/font-awesome'
                );
            }
        }
        if (!isset($tsCache['js']) || $tsCache['js'] !== $jsTs) {
            $this->dumpJsAssets($this->publishBasePath . '/debugbar.js');
            $tsCache['js'] = $jsTs;
        }
        if ($tsCache !== $tsCacheOld) {
            $this->files->put($cacheFile, Json::encode($tsCache));
        }

        $html = '';
        if ($fontawesomeExist) {
            $fontawesomeRoute = $this->publishUrlPath . '/font-awesome/css/font-awesome.min.css?v=' . $cssTs;
            $html .= "<link rel='stylesheet' type='text/css' property='stylesheet' href='{$fontawesomeRoute}'>";
        }
        $cssRoute = $this->publishUrlPath . '/debugbar.css?v=' . $cssTs;
        $html .= "<link rel='stylesheet' type='text/css' property='stylesheet' href='{$cssRoute}'>";
        $jsRoute = $this->publishUrlPath . '/debugbar.js?v=' . $jsTs;
        $html .= "<script type='text/javascript' src='{$jsRoute}'></script>";

        if ($this->isJqueryNoConflictEnabled()) {
            $html .= '<script type="text/javascript">jQuery.noConflict(true);</script>' . "\n";
        }

        $html .= implode("\n", $this->getAssets('inline_head'));

        return $html;
    }

    /**
     * @param string $type js/css
     * @return false|int
     */
    protected function getModifiedTime(string $type)
    {
        $files = $this->getAssets($type);

        $latest = 0;
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime > $latest) {
                $latest = $mtime;
            }
        }
        return $latest;
    }
}
