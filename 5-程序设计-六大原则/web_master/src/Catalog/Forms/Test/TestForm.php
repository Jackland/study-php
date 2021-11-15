<?php

namespace App\Catalog\Forms\Test;

use Framework\Model\RequestForm\RequestForm;

/**
 * @example RequestForm 例子
 */
class TestForm extends RequestForm
{
    public $name;
    public $user_name;
    public $password;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'name' => 'required',
            'user_name' => 'required|max:16',
            'password' => 'nullable|min:10',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getAttributeLabels(): array
    {
        return [
            'user_name' => '用户名',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getAutoLoadRequestData()
    {
        return $this->request->post();
    }

    public function doSomething()
    {
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            return [
                'error' => $this->getFirstError(),
            ];
        }
        // 实际业务处理

        return [
            'name' => $this->name,
            'user_name' => $this->user_name,
            'password' => $this->password,
        ];
    }
}
