<?php

namespace Framework\ErrorHandler;

interface ErrorHandlerInterface
{
    /**
     * 注冊监听 php 的异常事件
     * @return void
     */
    public function register();
}
