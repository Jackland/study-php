<?php

namespace Framework\Session\Handlers;

class FileHandler implements SessionHandlerInterface
{
    protected $savePath;
    protected $prefix = 'sess_';
    protected $ttl = null;

    public function __construct($savePath, $options = [])
    {
        $this->savePath = $savePath;
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0777, true);
        }

        if (isset($options['prefix'])) {
            $this->prefix = $options['prefix'];
        }
        if (isset($options['ttl']) && $options['ttl'] > 0) {
            $this->ttl = (int)$options['ttl'];
        } else {
            $this->ttl = (int)ini_get('session.gc_maxlifetime');
        }
    }

    /**
     * @inheritDoc
     */
    public function read($sessionId)
    {
        $file = $this->getFile($sessionId);

        if (is_file($file)) {
            $handle = fopen($file, 'r');
            flock($handle, LOCK_SH);
            $data = fread($handle, filesize($file));
            flock($handle, LOCK_UN);
            fclose($handle);
            return unserialize($data) ?: [];
        } else {
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function write($sessionId, $data)
    {
        $file = $this->getFile($sessionId);

        $handle = fopen($file, 'w');
        flock($handle, LOCK_EX);
        fwrite($handle, serialize($data));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function destroy($sessionId)
    {
        $file = $this->getFile($sessionId);

        if (is_file($file)) {
            unset($file);
        }
    }

    /**
     * @inheritDoc
     */
    public function gc($expire)
    {
    }

    public function __destruct()
    {
        if (ini_get('session.gc_divisor')) {
            $gc_divisor = ini_get('session.gc_divisor');
        } else {
            $gc_divisor = 1;
        }

        if (ini_get('session.gc_probability')) {
            $gc_probability = ini_get('session.gc_probability');
        } else {
            $gc_probability = 1;
        }

        if ((rand() % $gc_divisor) < $gc_probability) {
            $expire = time() - $this->ttl;

            $files = glob(rtrim($this->savePath, '/') . '/' . $this->prefix . '*');

            foreach ($files as $file) {
                if (filemtime($file) < $expire) {
                    unlink($file);
                }
            }
        }
    }

    private function getFile($sessionId)
    {
        return $file = rtrim($this->savePath, '/') . '/' . $this->prefix . basename($sessionId);
    }
}
