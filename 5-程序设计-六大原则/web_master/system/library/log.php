<?php
/**
 * @package        OpenCart
 * @author        Daniel Kerr
 * @copyright    Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license        https://opensource.org/licenses/GPL-3.0
 * @link        https://www.opencart.com
 */

use App\Logging\Logger;

/**
 * Log class
 * @deprecated 使用 Logger 代替
 */
class Log
{
    private $handle;

    private $filename;

    /**
     * Constructor
     *
     * @param string $filename
     */
    public function __construct($filename)
    {
        if (strpos($filename, 'error/') === false) {
            $fullFilename = DIR_LOGS . $filename;
            //$this->ensureDirectory(dirname($fullFilename));
            $this->handle = fopen($fullFilename, 'a');
        }
        $this->filename = $filename;
    }

    /**
     * @param Exception|string|array|object $message
     * @deprecated 使用 Logger::app() 或 Logger::xxx() 代替
     */
    public function write($message)
    {
        if (!$this->handle) {
            Logger::error($message, 'error');
            return;
        } else {
            Logger::app(['need rewrite log', $this->filename], 'debug');
        }

        if (is_object($message)) {
            if ($message instanceof Exception) {
                $this->write(
                    'PHP Exception: '
                    . $message->getCode() . ':  '
                    . $message->getMessage() . ' in '
                    . $message->getFile() . ' on line '
                    . $message->getLine() . PHP_EOL
                    . $message->getTraceAsString() . PHP_EOL
                );
            } else {
                $this->write(json_encode($message, JSON_FORCE_OBJECT));
            }
            return;
        }
        if (is_array($message)) {
            $this->write(var_export($message, true));
            return;
        }
        fwrite($this->handle, date('Y-m-d G:i:s') . ' - ' . print_r($message, true) . PHP_EOL);
    }

    /**
     * @param mixed ...$messages
     */
    public function batchWrite(...$messages)
    {
        foreach ($messages as $message) {
            $this->write($message);
        }
    }

    /**
     *
     *
     */
    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    protected function ensureDirectory($path)
    {
        if (!is_dir($path)) {
            if (false === @mkdir($path, 0777, true) || false === is_dir($path)) {
                throw new Exception('创建目录失败');
            }
        }
    }
}
