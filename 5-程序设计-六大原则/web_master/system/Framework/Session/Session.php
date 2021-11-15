<?php

namespace Framework\Session;

use Framework\Session\Handlers\SessionHandlerInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

class Session
{
    const FLASH_KEY = '__flash';

    /**
     * @deprecated 使用 $session->get($key) 和 $session->set($key) 代替
     * @var array
     */
    public $data = [];
    /**
     * @var FlashBag
     */
    public $flash;

    /**
     * @var AttributeBag
     */
    private $attributeBag;
    /**
     * @var SessionHandlerInterface
     */
    private $handler;
    /**
     * @var string
     */
    private $sessionId;
    /**
     * @var bool
     */
    private $isStarted = false;

    public function __construct(SessionHandlerInterface $handler)
    {
        $this->handler = $handler;

        register_shutdown_function([$this, 'close']);
    }

    private $_cookieSessionId;

    /**
     * @param string $sessionId
     * @return bool
     * @throws \Exception
     */
    public function start($sessionId = '')
    {
        if (!$sessionId) {
            $sessionId = substr(bin2hex(random_bytes(26)), 0, 26);
        }

        if (preg_match('/^[a-zA-Z0-9,\-]{22,52}$/', $sessionId)) {
            $this->sessionId = $sessionId;
        } else {
            throw new \RuntimeException('Error: Invalid session ID!');
        }

        $this->loadSession($sessionId);

        if ($this->_cookieSessionId != $sessionId) {
            setcookie(configDB('session_name'), $sessionId, ini_get('session.cookie_lifetime'), ini_get('session.cookie_path'), ini_get('session.cookie_domain'));
            $this->_cookieSessionId = $sessionId;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isStarted()
    {
        return $this->isStarted;
    }

    /**
     * @param $sessionId
     */
    private function loadSession($sessionId)
    {
        $data = $this->handler->read($sessionId);
        $flashData = [];
        if (isset($data[static::FLASH_KEY])) {
            $flashData = $data[static::FLASH_KEY];
            unset($data[static::FLASH_KEY]);
        }

        $this->data = $data;
        $this->attributeBag = new AttributeBag('session_data');
        if ($this->data) {
            $this->attributeBag->replace($this->data);
        }
        $this->flash = new FlashBag('flash_data');
        if ($flashData) {
            $this->flash->setAll($flashData);
        }

        $this->isStarted = true;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->sessionId;
    }

    /**
     * 关闭
     */
    public function close()
    {
        if (!$this->isStarted) {
            return;
        }

        $this->handler->write($this->sessionId, array_merge($this->all(), [
            static::FLASH_KEY => $this->flash->all()
        ]));

        $this->isStarted = false;
    }

    /**
     * 销毁
     */
    public function __destroy()
    {
        $this->handler->destroy($this->sessionId);
        $this->isStarted = false;
    }

    /**
     * 是否存在
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        // 兼容原代码
        if (isset($this->data[$name])) {
            return true;
        }
        return $this->getAttributeBag()->has($name);
    }

    /**
     * 获取
     * @param string $name
     * @param null $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        // 兼容原代码，因为存在给 data 直接赋值的情况，所以在赋值后需要通过 get 也能取到
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
        return $this->getAttributeBag()->get($name, $default);
    }

    /**
     * 设置
     * @param string $name
     * @param mixed $value
     */
    public function set($name, $value)
    {
        // 兼容原代码，使用 set 后在 data 中也保存一份，确保使用 data 能直接取到数据
        $this->data[$name] = $value;
        $this->getAttributeBag()->set($name, $value);
    }

    /**
     * 所有
     * @return array
     */
    public function all()
    {
        // 兼容原代码
        $all = $this->data ?: [];
        $bagAll = $this->getAttributeBag()->all() ?: [];
        return array_merge($bagAll, $all);
    }

    /**
     * 替换所有
     * @param array $attributes
     */
    public function replace(array $attributes)
    {
        // 兼容原代码
        $this->data = $attributes;
        $this->getAttributeBag()->replace($attributes);
    }

    /**
     * 移除某个
     * @param string $name
     * @return mixed
     */
    public function remove($name)
    {
        // 兼容原代码
        $removed = null;
        if (array_key_exists($name, $this->data)) {
            $removed = $this->data[$name];
            unset($this->data[$name]);
        }
        $removed2 = $this->getAttributeBag()->remove($name);
        return $removed ?: $removed2;
    }

    /**
     * 移除数组下的某个键及其值
     * 不建议这样使用
     * @param $name
     * @param $key
     */
    public function removeDeepByKey($name, $key)
    {
        if ($this->has($name)) {
            $arr = $this->get($name);
            if (is_array($arr)) {
                unset($arr[$key]);
                $this->set($name, $arr);
            }
        }
    }

    /**
     * 清空
     */
    public function clear()
    {
        // 兼容原代码
        $this->data = [];
        $this->getAttributeBag()->clear();
    }

    /**
     * @return AttributeBag
     */
    private function getAttributeBag()
    {
        return $this->attributeBag ?: new AttributeBag();
    }
}
