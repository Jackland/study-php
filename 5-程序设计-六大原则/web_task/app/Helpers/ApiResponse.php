<?php

namespace App\Helpers;

use Illuminate\Http\Response;

trait ApiResponse
{
    protected function responseData($code, $message, $data = [])
    {
        return [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * 一般成功返回
     * @return \Illuminate\Http\JsonResponse
     */
    public function success($data = [], $message = 'success')
    {
        return $this->response($this->responseData(Response::HTTP_OK, $message, $data));
    }

    /**
     * 一般失败返回
     * @return \Illuminate\Http\JsonResponse
     */
    public function error($message, $code = 0)
    {
        return $this->response($this->responseData($code, $message));
    }

    /**
     * 自定义错误码和消息的返回
     * @param int $code
     * @param $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function message($message, $code = Response::HTTP_OK)
    {
        return $this->response($this->responseData($code, $message));
    }

    /**
     * 一般错误返回
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function failed($code = Response::HTTP_INTERNAL_SERVER_ERROR)
    {
        $data = $this->responseData($code, '操作失败，请稍后再试');
        return $this->response($data);
    }

    /**
     * 一般404返回
     * @return \Illuminate\Http\JsonResponse
     */
    public function notFound()
    {
        $data = $this->responseData(Response::HTTP_NOT_FOUND, '数据未找到');
        return $this->response($data, Response::HTTP_NOT_FOUND);
    }

    /**
     * 返回信息
     * @param $data
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    private function response($data, $code = 200)
    {
        return response()->json($data, $code);
    }
}