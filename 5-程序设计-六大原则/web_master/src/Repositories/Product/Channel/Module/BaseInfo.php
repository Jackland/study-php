<?php

namespace App\Repositories\Product\Channel\Module;

use App\Repositories\Product\Channel\ChannelRepository;

abstract class BaseInfo
{
    public $productIds = [];
    /**
     * @var ChannelRepository $channelRepository
     */
    public $channelRepository;
    private $showNum; //每个模块展示产品个数（seller 一组产品为一个）

    public function __construct()
    {
        $this->channelRepository = app(ChannelRepository::class);
    }

    public function setShowNum(int $showNum)
    {
        $this->showNum = $showNum;
    }

    public function getShowNum()
    {
        return $this->showNum;
    }

    /**
     * @param array $param 过滤后的url参数
     * @return array ['type' => ,'data' =>  ,'productIds' => ,];
     */
    abstract function getData(array $param): array;

}
