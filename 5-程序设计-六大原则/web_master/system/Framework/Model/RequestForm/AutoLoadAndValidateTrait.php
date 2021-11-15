<?php

namespace Framework\Model\RequestForm;

use Illuminate\Contracts\Validation\Validator;

trait AutoLoadAndValidateTrait
{
    /**
     * @var Validator|null
     */
    protected $validator;

    /**
     * 自动加载数据并校验
     */
    public function autoLoadAndValidate()
    {
        $this->loadAttributes($this->getAutoLoadRequestData());
        $this->validator = $this->validateAttributes();
    }

    /**
     * 获取自动加载的数据
     * @return array
     */
    protected function getAutoLoadRequestData()
    {
        return request()->post();
    }

    /**
     * 获取校验后的校验器
     * @return Validator|null
     */
    public function getValidator(): ?Validator
    {
        return $this->validator;
    }

    /**
     * 是否已经校验成功，未调用 validateAttributes 时为 false
     * @return bool
     */
    public function isValidated(): bool
    {
        return $this->validator ? !$this->validator->fails() : false;
    }

    /**
     * 获取第一个错误提示
     * @return string|null
     */
    public function getFirstError(): ?string
    {
        return $this->validator ? $this->validator->errors()->first() : null;
    }
}
