<?php

namespace Framework\Debug\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Framework\Debug\Collector\twig\TwigDataNotUse;

class TwigDataNotUseCollector extends DataCollector implements Renderable
{
    /**
     * @var TwigDataNotUse
     */
    private $twigDataNotUse;

    public function __construct(TwigDataNotUse $twigDataNotUse)
    {
        $this->twigDataNotUse = $twigDataNotUse;
    }

    /**
     * @inheritDoc
     */
    public function collect()
    {
        $data = [];
        $count = 0;
        foreach ($this->twigDataNotUse->getAllNotUse() as $item) {
            $messageText = $item['keys'];
            $messageHtml = null;
            $isString = true;
            if (!is_string($item['keys'])) {
                $messageText = $this->getDataFormatter()->formatVar($item['keys']);
                $isString = false;
            }
            $data[] = [
                'message' => $messageText,
                'message_html' => $messageHtml,
                'is_string' => $isString,
                'label' => $item['view'],
                'time' => microtime(true)
            ];
            $count += count($item['keys']);
        }
        return [
            'data' => $data,
            'count' => $count,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'twigDataNotUse';
    }

    /**
     * @inheritDoc
     */
    public function getWidgets()
    {
        $name = $this->getName();
        return [
            $name => [
                "icon" => "tags",
                "widget" => 'PhpDebugBar.Widgets.MessagesWidget',
                "map" => "$name.data",
                "default" => "{}"
            ],
            "$name:badge" => [
                "map" => "$name.count",
                "default" => "0"
            ]
        ];
    }
}
