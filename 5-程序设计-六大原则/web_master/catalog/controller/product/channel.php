<?php

use App\Catalog\Controllers\BaseController;
use App\Enums\Product\Channel\ChannelType;
use App\Logging\Logger;
use App\Repositories\Product\Channel\Form\ChannelForm;
use Symfony\Component\HttpFoundation\JsonResponse;

class ControllerProductChannel extends BaseController
{
    public function index()
    {
        $data['channel_name'] = 'New Arrivals';
        return $this->render('channel/index', $data, 'home');
    }

    // 初始化twig模板数据
    public function getChannelData(ChannelForm $channelForm)
    {
        try {
            [$data, $param] = $channelForm->getData();
            $categories = $channelForm->getCategoryByType();
        } catch (Throwable $e) {
            // return no fund 页面
            Logger::error($e);
            return $this->render('error/not_found', [], 'home');
        }
        // title
        $this->document->setTitle(ChannelType::getDescription($channelForm->type));
        // is login
        $data['isLogin'] = customer()->isLogged();
        // 表示频道
        $data['is_channel'] = 1;
        // 当前频道的分类
        $data['categories'] = $categories;
        // 当前路由的分类,默认为0
        $data['category_id'] = in_array($param['category_id'], array_column($categories, 'category_id')) ? $param['category_id'] : 0;
        return $this->render('channel/' . $param['type'], $data, 'home');
    }

    // 异步加载数据
    public function getChannelSynData(ChannelForm $channelForm): JsonResponse
    {
        try {
            $channelForm->setSearchFlag(true);
            $data = $channelForm->getData();
        } catch (Throwable $e) {
            Logger::channelProducts($e);
            return $this->jsonFailed($e->getMessage());
        }

        return $this->jsonSuccess($data);
    }
}
