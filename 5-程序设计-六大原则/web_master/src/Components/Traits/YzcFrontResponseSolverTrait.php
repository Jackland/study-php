<?php

namespace App\Components\Traits;

use Framework\Helper\Json;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * 处理 yzc_front 请求响应的处理
 */
trait YzcFrontResponseSolverTrait
{
    private function solveYzcFrontAjaxResponse(SymfonyResponse $response)
    {
        if (!request()->isAjax()) {
            // 仅处理 ajax
            return;
        }
        if (!request()->header('ori-status-in-response')) {
            // 仅处理带这个 header 头的
            return;
        }
        $code = $response->getStatusCode();
        if ($code !== 200) {
            $response->setStatusCode(200);
            $data = [
                'code' => $code,
                'msg' => SymfonyResponse::$statusTexts[$code] ?? 'error',
            ];
            if ($redirect = $response->headers->get('Location')) {
                $data['redirect'] = $redirect;
            }
            if ($code === 404) {
                $data['redirect'] = url('error/not_found');
            }
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(Json::encode($data));
        }
    }
}
