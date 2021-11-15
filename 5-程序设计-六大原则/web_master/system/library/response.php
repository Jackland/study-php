<?php
/**
 * @package        OpenCart
 * @author        Daniel Kerr
 * @copyright    Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license        https://opensource.org/licenses/GPL-3.0
 * @link        https://www.opencart.com
 */

/**
 * Response class
 * @deprecated 使用 Framework\Http\Response 代替
 */
class Response
{
    private $headers = array();
    private $level = 0;
    private $output = null;
    private $session;

    /**
     * @var bool $exit
     */
    private $exit = false;

    /**
     * Constructor
     *
     * @param    string $header
     *
     */
    public function addHeader($header)
    {
        $this->headers[] = $header;
    }

    public function  setSession($session){
        $this->session = $session;
    }

    /**
     * @param    string $url
     * @param    int $status
     *
     */
    public function redirect($url, $status = 302)
    {
        header('Location: ' . str_replace(array('&amp;', "\n", "\r"), array('&', '', ''), $url), true, $status);
        exit();
    }

    /**
     * @param    int $level
     */
    public function setCompression($level)
    {
        $this->level = $level;
    }

    /**
     *
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param  string $output
     */
    public function setOutput($output)
    {
        $output = changeOutPutByZone($output,$this->session);
        $this->output = $output;
    }

    /**
     *
     * @param    string $data
     * @param    int $level
     *
     * @return    string
     */
    private function compress($data, $level = 0)
    {
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)) {
            $encoding = 'gzip';
        }

        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false)) {
            $encoding = 'x-gzip';
        }

        if (!isset($encoding) || ($level < -1 || $level > 9)) {
            return $data;
        }

        if (!extension_loaded('zlib') || ini_get('zlib.output_compression')) {
            return $data;
        }

        if (headers_sent()) {
            return $data;
        }

        if (connection_status()) {
            return $data;
        }

        $this->addHeader('Content-Encoding: ' . $encoding);

        return gzencode($data, (int)$level);
    }

    /**
     * 立即结束当前请求，并返回 Json 数据
     *
     * @param array|object|int|string $data
     * @return void
     */
    public function returnJson($data)
    {
        $this->addHeader('Content-Type: application/json');
        if (is_array($data) || is_object($data)) {
            $this->setOutput(json_encode($data));
        } else {
            $this->setOutput($data);
        }
        $this->exit = true;
        $this->output();
    }

    public function success($data = [], $msg = 'Saved successfully.', $code = 200)
    {
        $json['code'] = $code;
        $json['msg'] = $msg;
        if ($data) {
            $json['data'] = $data;
        }
        $this->returnJson($json);
    }

    public function failed($msg = 'Saved failed.', $data = [], $code = 0)
    {
        $json['code'] = $code;
        $json['msg'] = $msg;
        if ($data) {
            $json['data'] = $data;
        }
        $this->returnJson($json);
    }

    /**
     *
     */
    public function output()
    {
        if (!is_null($this->output)) {
            $output = $this->level ? $this->compress($this->output, $this->level) : $this->output;

            if (!headers_sent()) {
                foreach ($this->headers as $header) {
                    header($header, true);
                }
            }
            echo $output;
        }
        if ($this->exit) {
            exit(0);
        }
    }
}
