<?php

namespace Framework\Debug\Traits;

use DebugBar\Bridge\TwigProfileCollector;
use DebugBar\DebugBarException;
use Framework\Debug\Collector\twig\TwigDataNotUse;
use Framework\Debug\Collector\TwigDataNotUseCollector;
use Twig_Profiler_Profile;

trait AddTwigCollectorTrait
{
    private $isTwigEnvCollectorAdded = false;

    /**
     * 添加 twig 的信息收集
     * @param Twig_Profiler_Profile $profile
     * @param TwigDataNotUse $twigDataNotUse
     * @throws DebugBarException
     */
    public function addTwigEnvCollector(Twig_Profiler_Profile $profile, TwigDataNotUse $twigDataNotUse)
    {
        if (!$this->isEnabled() || $this->isTwigEnvCollectorAdded) {
            return;
        }
        $this->addCollector(new TwigProfileCollector($profile));
        $this->addCollector(new TwigDataNotUseCollector($twigDataNotUse));

        $this->isTwigEnvCollectorAdded = true;
    }
}
