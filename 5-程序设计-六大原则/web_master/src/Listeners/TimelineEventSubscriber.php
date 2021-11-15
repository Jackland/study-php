<?php

namespace App\Listeners;

use App\Components\Debug\DebugBarDatabaseMarker;
use Framework\Action\Events\ActionAfterExecute;
use Framework\Action\Events\ActionBeforeExecute;
use Framework\Debug\DebugBar;
use Framework\Helper\StringHelper;
use Framework\Loader\Events\LoadControllerAfter;
use Framework\Loader\Events\LoadControllerBefore;
use Framework\Loader\Events\LoadViewAfter;
use Framework\Loader\Events\LoadViewBefore;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

class TimelineEventSubscriber
{
    /**
     * @var DebugBar
     */
    private $debugBar;

    private $enable = [
        'measureAction' => true,
        'measureController' => false,
        'measureView' => false,
        'databaseMark' => false,
    ];
    private $measureActionBlack = [
        'event/*',
    ];
    private $databaseMarkActionBlack = [
        'event/*',
    ];

    public function subscribe(Dispatcher $dispatcher)
    {
        $this->debugBar = debugBar();

        if (!$this->debugBar->isEnabled()) {
            return;
        }

        $dispatcher->listen(ActionBeforeExecute::class, function (ActionBeforeExecute $event) {
            $actionId = $event->action->getId();
            if ($this->enable['measureAction'] && !$this->isActionIdBlack($this->measureActionBlack, $actionId)) {
                $this->startMeasure("Action: {$actionId}");
            }

            if ($this->enable['databaseMark'] && !$this->isActionIdBlack($this->databaseMarkActionBlack, $actionId)) {
                DebugBarDatabaseMarker::mark("Action: {$actionId} 开始");
            }
        });
        $dispatcher->listen(ActionAfterExecute::class, function (ActionAfterExecute $event) {
            $actionId = $event->action->getId();
            if ($this->enable['measureAction'] && !$this->isActionIdBlack($this->measureActionBlack, $actionId)) {
                $this->stopMeasure("Action: {$actionId}");
            }

            if ($this->enable['databaseMark'] && !$this->isActionIdBlack($this->databaseMarkActionBlack, $actionId)) {
                DebugBarDatabaseMarker::mark("Action: {$actionId} 结束");
            }
        });

        if ($this->enable['measureController']) {
            $dispatcher->listen(LoadControllerBefore::class, function (LoadControllerBefore $event) {
                $this->startMeasure('Controller: ' . $event->route);
            });
            $dispatcher->listen(LoadControllerAfter::class, function (LoadControllerAfter $event) {
                $this->stopMeasure('Controller: ' . $event->route);
            });
        }

        if ($this->enable['measureView']) {
            $dispatcher->listen(LoadViewBefore::class, function (LoadViewBefore $event) {
                $this->startMeasure('View: ' . $event->route);
            });
            $dispatcher->listen(LoadViewAfter::class, function (LoadViewAfter $event) {
                $this->stopMeasure('View: ' . $event->route);
            });
        }
    }

    private function startMeasure($name)
    {
        $this->debugBar->startMeasure($name);
    }

    private function stopMeasure($name)
    {
        try {
            debugBar()->stopMeasure($name);
        } catch (Throwable $e) {
            debugBar()->addException($e);
        }
    }

    private function isActionIdBlack(array $rules, $actionId)
    {
        foreach ($rules as $rule) {
            if (StringHelper::matchWildcard($rule, $actionId)) {
                return true;
            }
        }
        return false;
    }
}
