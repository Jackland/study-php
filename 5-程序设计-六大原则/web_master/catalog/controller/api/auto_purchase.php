<?php

use App\Catalog\Controllers\BaseController;
use App\Logging\Logger;
use App\Services\SalesOrder\AutoPurchaseService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ControllerApiAutoPurchase extends BaseController
{
    /**
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // 不限制超时
        set_time_limit(0);

        // 记录自动购买日志开始
        Logger::autoPurchase('----START AUTO PURCHASE----', 'info', [
            Logger::CONTEXT_WEB_SERVER_VARS => ['_GET', '_POST'],
        ]);

        if (empty(session('api_id'))) {
            return $this->apiJson("Warning: You do not have permission to access the API!", 401);
        }

        if (!request()->isMethod('post')) {
            return $this->apiJson("Warning: Request method error!", 403);
        }

        $saleOrderIds = request('sale_order_ids', '');
        if (empty($saleOrderIds)) {
            return $this->apiJson("Warning: Sale order primary key required!", 400);
        }

        $saleOrderIds = explode(',', $saleOrderIds);
        try {
            $handleResult = app(AutoPurchaseService::class)->autoPurchase($saleOrderIds);
            return $this->apiJson('Success', 200, $handleResult);
        } catch (Exception $e) {
            return $this->apiJson($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 返回
     * @param string $msg
     * @param int $code
     * @param array $data
     * exm: [
     *          [
     *              'salesOrderId' = salesOrderId,
     *              'result' => 'success'/'fail'/'exception',
     *              'content' => '',
     *          ],
     *      ....
     *    ]
     * @return JsonResponse
     */
    private function apiJson(string $msg = 'Success', int $code = 200, array $data = []): JsonResponse
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
        ];

        if ($code == 200 && !empty($data)) {
            $result['data'] = $data;
        }

        Logger::autoPurchase('----END AUTO PURCHASE----', 'info', [
            Logger::CONTEXT_VAR_DUMPER => $result,
        ]);

        return $this->json(json_encode($result, JSON_UNESCAPED_UNICODE));
    }
}
