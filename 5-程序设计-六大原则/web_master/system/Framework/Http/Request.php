<?php

namespace Framework\Http;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\ServerBag;

final class Request extends \Request
{
    /**
     * 所有 $_POST（不包含 application/json 的提交）
     * key 和 value 都已经经过 htmlspecialchars 转译
     * @var ParameterBag
     */
    public $input;
    /**
     * 所有 $_GET
     * key 和 value 都已经经过 htmlspecialchars 转译
     * @var ParameterBag
     */
    public $query;
    /**
     * 所有 $_GET 和 $_POST 的数据
     * @var ParameterBag
     */
    public $attributes;
    /**
     * @var ParameterBag
     */
    public $cookieBag;
    /**
     * @var ServerBag
     */
    public $serverBag;
    /**
     * @var FileBag
     */
    public $filesBag;
    /**
     * @var HeaderBag
     */
    public $headers;

    public function __construct()
    {
        parent::__construct();

        // get 和 post 参数使用 htmlspecialchars 进行转译
        $this->input = new ParameterBag($this->post);
        $this->query = new ParameterBag($this->get);
        $this->attributes = new ParameterBag($this->request);

        $this->cookieBag = new ParameterBag($_COOKIE);
        $this->filesBag = new FileBag($_FILES);
        $this->serverBag = new ServerBag($_SERVER);
        $this->headers = new HeaderBag($this->serverBag->getHeaders());
    }

    public static function createFromGlobals()
    {
        return new self();
    }

    /**
     * 获取 GET 参数，若不传 key 则取出全部，
     * 注意：key 和 value 都已经经过 htmlspecialchars 转译
     * @param null|string|array $key
     * @param null|mixed $default
     * @return null|string|array
     */
    public function get($key = null, $default = null)
    {
        return $this->retrieveItem($this->query, $key, $default);
    }

    /**
     * 获取 POST 参数，若不传 key 则取出全部
     * 可以获取到 application/json 提交的数据
     * 注意：非 application/json 提交的 post 请求，key 和 value 都已经经过 htmlspecialchars 转译
     * @param null|string|array $key
     * @param null|mixed $default
     * @return null|string|array
     */
    public function post($key = null, $default = null)
    {
        if ($this->isJson()) {
            return $this->json($key, $default);
        }

        return $this->retrieveItem($this->input, $key, $default);
    }

    /**
     * 获取 json 参数，若不传 key 则取出全部
     * @param null|string|array $key
     * @param null|mixed $default
     * @return array|mixed
     * @throws \Throwable
     */
    public function json($key = null, $default = null)
    {
        return $this->retrieveItem($this->bodyBag(), $key, $default);
    }

    /**
     * 获取 FILE 参数，若不传 key 则取出全部
     * @param null|string|array $key
     * @param null|mixed $default
     * @return array|UploadedFile|UploadedFile[]
     */
    public function file($key = null, $default = null)
    {
        return $this->retrieveItem($this->filesBag, $key, $default);
    }

    /**
     * 获取 header 参数，若不传 key 则取出全部
     * @param null|string|array $key
     * @param null|mixed $default
     * @return array|string|null
     */
    public function header($key = null, $default = null)
    {
        return $this->retrieveItem($this->headers, $key, $default);
    }

    /**
     * 获取 server 参数，若不传 key 则取出全部
     * @param null|string|array $key
     * @param null|mixed $default
     * @return array|string|null
     */
    public function server($key = null, $default = null)
    {
        return $this->retrieveItem($this->serverBag, $key, $default);
    }

    /**
     * 处理数据
     * @param ParameterBag|HeaderBag|BodyBag $source
     * @param null|string|array $key
     * @param null|mixed $default
     * @return mixed
     */
    private function retrieveItem($source, $key = null, $default = null)
    {
        if ($key === null) {
            // key 为 null 取出全部
            return $source->all();
        }
        if (is_array($key)) {
            // key 为数组返回给定的值，不存在时不返回该 key
            $result = [];
            foreach ($key as $k) {
                if (!$source->has($k)) {
                    continue;
                }
                $result[$k] = $source->get($k);
            }
            return $result;
        }
        // 单独指定获取某个值
        return $source->get($key, $default);
    }

    private $_bodyBag;

    /**
     * 获取 POST body 中的数据
     * @return BodyBag
     */
    public function bodyBag()
    {
        if (!$this->_bodyBag) {
            $this->_bodyBag = new BodyBag($this->getSymfonyRequest()->getContent());
        }
        return $this->_bodyBag;
    }

    private $_userIp = false;

    /**
     * 获取用户 IP
     * @param null|mixed $ifNotExist 当获取失败时返回该值
     * @return string|null|mixed
     */
    public function getUserIp($ifNotExist = null)
    {
        if ($this->_userIp === false) {
            $ip = $this->serverBag->get('HTTP_X_FORWARDED_FOR');
            if (!$ip) {
                $ip = $this->serverBag->get('REMOTE_ADDR');
            }
            $this->_userIp = $ip ? trim(explode(',', $ip)[0]) : '____NOT_EXIST';
        }
        if ($this->_userIp === '____NOT_EXIST') {
            return $ifNotExist;
        }
        return $this->_userIp;
    }

    /**
     * 获取 UA
     * @return string|null
     */
    public function getUserAgent()
    {
        return $this->header('User-Agent');
    }

    /**
     * 获取 referer
     * @return string|null
     */
    public function getReferer()
    {
        return $this->header('Referer');
    }

    /**
     * 获取当前请求方式
     * @return string
     */
    public function getMethod()
    {
        return $this->getSymfonyRequest()->getMethod();
    }

    /**
     * 是否是 application/json
     * @return bool
     */
    public function isJson(): bool
    {
        return Str::contains($this->headers->get('CONTENT_TYPE'), ['/json', '+json']);
    }

    /**
     * 是否是 ajax 请求
     * @return bool
     */
    public function isAjax(): bool
    {
        return 'XMLHttpRequest' == $this->headers->get('X-Requested-With');
    }

    /**
     * 是否是 pjax 请求
     * @return bool
     */
    public function isPjax(): bool
    {
        return $this->headers->get('X-PJAX') == true;
    }

    /**
     * 预测是否是 json 返回
     * @return bool
     */
    public function expectsJson()
    {
        return
            ($this->isAjax() && !$this->isPjax()) // ajax not pjax
            || Str::contains(request()->header('Accept'), ['/json', '+json']) // Accept: application/json
            ;
    }

    /**
     * 是否是某种请求
     * @param string $method get/post/PUT 等
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * 校验所有 get 和 post 参数
     * @param $rules
     * @param array $messages
     * @param array $customAttributes
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validate($rules, $messages = [], $customAttributes = [])
    {
        return validator($this->get() + $this->post() + $this->file(), $rules, $messages, $customAttributes);
    }

    /**
     * 校验数据
     * @param $data
     * @param $rules
     * @param array $messages
     * @param array $customAttributes
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validateData($data, $rules, $messages = [], $customAttributes = [])
    {
        return validator($data, $rules, $messages, $customAttributes);
    }

    /**
     * http/https
     * @return string
     */
    public function getScheme()
    {
        return $this->getSymfonyRequest()->getScheme();
    }

    /**
     * domain 不带 port
     * @return string
     */
    public function getHost()
    {
        return $this->getSymfonyRequest()->getHost();
    }

    /**
     * domain:port
     * 当 http port 为 80 和 https port 为 443 时，返回 domain
     * @return string
     */
    public function getHttpHost()
    {
        return $this->getSymfonyRequest()->getHttpHost();
    }

    /**
     * 获取 scheme://domain:port
     * http://yzc.test 或 http://yzc.test:8888
     * @return string
     */
    public function getSchemeAndHttpHost()
    {
        return $this->getSymfonyRequest()->getSchemeAndHttpHost();
    }

    private $_symfonyRequest;

    /**
     * 获取 Symfony 的 Request
     * @param bool $reset
     * @return SymfonyRequest
     * @internal 非特殊情况，外部不允许使用
     */
    public function getSymfonyRequest($reset = false)
    {
        if (!$this->_symfonyRequest || $reset) {
            $this->_symfonyRequest = new SymfonyRequest(
                $this->query->all(),
                $this->input->all(),
                $this->attributes->all(),
                $this->cookieBag->all(),
                $_FILES,
                $this->serverBag->all()
            );
        }
        return $this->_symfonyRequest;
    }
}
