<?php

namespace App\Catalog\Controllers\Traits;

use Illuminate\Support\MessageBag;

/**
 * json 返回前端参数错误时，按照具体 attribute 返回
 */
trait JsonResultWithAttributeErrorTrait
{
    /**
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    private function resultSuccess(array $data = [])
    {
        return $this->jsonSuccess(array_merge([
            'result' => 'success',
        ], $data));
    }

    /**
     * @param array|MessageBag $errors
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    private function resultWithErrors($errors)
    {
        if (is_array($errors)) {
            $errors = new MessageBag($errors);
        }

        return $this->jsonSuccess([
            'result' => 'fail',
            'errors' => $errors,
        ]);
    }
}
