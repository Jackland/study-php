<?php

namespace App\Components\VerifyCodeResolver;

abstract class BaseResolver
{
    private $codeLength = 6;
    private $codeExpireTime = 300; // code 有效期
    private $cache;

    public function __construct()
    {
        $this->cache = cache();
    }

    /**
     * 设置 code 的长度
     * @param int $length
     * @return $this
     */
    public function setCodeLength(int $length): self
    {
        $this->codeLength = $length;
        return $this;
    }

    /**
     * 设置 code 有效期
     * @param int $expireTime
     * @return $this
     */
    public function setCodeExpireTime(int $expireTime): self
    {
        $this->codeExpireTime = $expireTime;
        return $this;
    }

    /**
     * 发送验证码
     */
    public function send()
    {
        $code = $this->generateCode();
        $this->sendInner($code);
        $this->cache->set($this->getCacheKey(), $code, $this->codeExpireTime);
    }

    /**
     * 校验验证码
     * @param string $code 用户输入的验证码
     * @return bool
     */
    public function verify(string $code): bool
    {
        $correctCode = $this->getCorrectCode();
        return $correctCode !== null && $correctCode === $code;
    }

    /**
     * 重置已经生成的验证码
     */
    public function reset(): void
    {
        $this->cache->delete($this->getCacheKey());
    }

    /**
     * 获取正确的 code
     * @return string|null
     */
    public function getCorrectCode(): ?string
    {
        return $this->cache->get($this->getCacheKey());
    }

    /**
     * @param string $code
     */
    abstract protected function sendInner(string $code): void;

    /**
     * @return array|string
     */
    abstract protected function getCacheKey();

    /**
     * 生成 code
     * @return string
     */
    protected function generateCode(): string
    {
        $min = pow(10, $this->codeLength - 1);
        $max = pow(10, $this->codeLength) - 1;
        return random_int($min, $max);
    }
}
