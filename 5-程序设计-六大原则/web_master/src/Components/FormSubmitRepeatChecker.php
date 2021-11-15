<?php

namespace App\Components;

/**
 * 表单防重复提交检查器
 *
 * if ($this->request->isMethod('POST')) {
 *     // 表单提交
 *     if (FormSubmitRepeatChecker::create()->isSubmitRepeat()) {
 *         // 重复提交
 *         //return;
 *     }
 *     // 实际业务
 * }
 * // 展示表单前初始化
 * FormSubmitRepeatChecker::create()->initForm();
 * return $this->render('xxx');
 */
class FormSubmitRepeatChecker
{
    private $session;
    private $key;

    public function __construct($key = null)
    {
        $this->session = session();
        $this->key = '__FORM_REPEAT_KEY_' . ($key ?: request('route', 'default'));
    }

    /**
     * @param null $key 默认为当前路由值，在一个路由有多个表单时可以指定key来确保多个表单都能使用
     * @return static
     */
    public static function create($key = null): self
    {
        return new static($key);
    }

    /**
     * 初始化表单
     */
    public function initForm()
    {
        $this->session->set($this->key, time());
    }

    /**
     * 判断是否重复提交
     * @return bool
     */
    public function isSubmitRepeat()
    {
        if ($this->session->has($this->key)) {
            $this->session->remove($this->key);
            return false;
        }
        return true;
    }
}
