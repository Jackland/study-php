<?php

namespace App\Components\VerifyCodeResolver;

use App\Components\RemoteApi;
use InvalidArgumentException;

class EmailResolver extends BaseResolver
{
    private $email;
    private $template;
    private $codeAttribute = 'code';

    public function __construct(string $email)
    {
        parent::__construct();

        $this->email = $email;
    }

    /**
     * @param string $template
     * @return $this
     */
    public function setTemplate(string $template): self
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function sendInner(string $code): void
    {
        if ($this->template) {
            RemoteApi::email()->sendTemplate($this->email, $this->template, [
                $this->codeAttribute => $code,
            ]);
            return;
        }

        throw new InvalidArgumentException('请先调用 setTemplate 设置模版');

    }

    /**
     * @inheritDoc
     */
    protected function getCacheKey()
    {
        return [__CLASS__, $this->email, 'v1'];
    }
}
