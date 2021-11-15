<?php

namespace App\Listeners;

use App\Components\Traits\YzcFrontResponseSolverTrait;
use Framework\Http\Events\ResponseBeforeSend;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResponseBeforeSendListener
{
    use YzcFrontResponseSolverTrait;

    public function handle(ResponseBeforeSend $event)
    {
        $this->solveYzcFrontAjaxResponse($event->response);
        $this->addDebugBar($event->response);
    }

    private function addDebugBar(SymfonyResponse $response)
    {
        // 添加 debugBar 的操作栏
        $debugBar = debugBar();
        if (
            !$debugBar->isEnabled() // 未启用
            || $response instanceof StreamedResponse // 流式请求不支持
            || $response instanceof JsonResponse // json 不支持
            || $response instanceof RedirectResponse // 重定向
            || $response instanceof BinaryFileResponse // 文件流
            || request()->isAjax() // ajax
            || request()->header('content-type') === 'application/json' // Content-Type: application/json
            || Str::contains(request()->header('accept'), 'application/json') // Accept: application/json, text/plain, */*
            || Str::startsWith(request()->header('user-agent'), 'PostmanRuntime') // postman
        ) {
            return;
        }
        // 跳过通过 header("Content-Type: text/plain") 设置过 response content-type 非 html 形式的
        foreach (headers_list() as $responseHeader) {
            $arr = explode(':', $responseHeader);
            if (strtolower($arr[0]) === 'content-type' && strpos(strtolower($arr[1]), 'html') === false) {
                return;
            }
        }
        $renderer = $debugBar->getJavascriptRenderer();
        $renderedContent = $renderer->renderHead() . $renderer->render();
        $content = $response->getContent();

        $pos = strripos($content, '</body>');
        if (false !== $pos) {
            $content = substr($content, 0, $pos) . $renderedContent . substr($content, $pos);
        } else {
            $content = $content . $renderedContent;
        }
        $response->setContent($content);
    }
}
