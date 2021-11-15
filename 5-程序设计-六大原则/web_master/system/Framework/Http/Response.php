<?php

namespace Framework\Http;

use App\Components\Traits\YzcFrontResponseSolverTrait;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Response extends \Symfony\Component\HttpFoundation\Response
{
    use YzcFrontResponseSolverTrait;

    /**
     * 进程退出时的状态码，0表示成功
     * @var int
     */
    public $exitStatus = 0;
    /**
     * @deprecated 后续将移除压缩
     * @var int
     */
    private $compressLevel;
    /**
     * @var \Framework\Session\Session
     */
    private $session;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct($content = '', int $status = 200, array $headers = [])
    {
        parent::__construct($content, $status, $headers);

        $this->logger = new NullLogger();
    }

    /**
     * @param $header
     * @deprecated 使用 $this->response->headers->set($key, $value) 代替
     */
    public function addHeader($header)
    {
        $arr = explode(':', $header);
        if (count($arr) !== 2) {
            $this->logger->error(__CLASS__ . '::' . __FUNCTION__ . '错误：' . $header);
            return;
        }
        $this->headers->set(trim($arr[0]), trim($arr[1]));
    }

    /**
     * @param \Framework\Session\Session $session
     */
    public function setSession($session)
    {
        $this->session = $session;
    }

    /**
     * @return \Framework\Session\Session
     */
    public function getSession()
    {
        return $this->session ?: session();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $url
     * @param int $status
     * @deprecated 使用 return $response->redirectTo($url) 代替
     */
    public function redirect($url, $status = 302)
    {
        $url = str_replace(['&amp;', "\n", "\r"], ['&', '', ''], $url);
        $response = $this->redirectTo($url, $status);
        $response->send();
        // 兼容原代码未使用 return
        exit($this->exitStatus);
    }

    /**
     * @param string $url
     * @param int $status
     * @param array $headers
     * @return RedirectResponse
     */
    public function redirectTo($url, $status = 302, $headers = [])
    {
        $redirectResponse = new RedirectResponse($url, $status, $headers);
        // 保留通过 response->headers->setCookie() 的值
        foreach ($this->headers->getCookies() as $cookie) {
            $redirectResponse->headers->setCookie($cookie);
        }
        $this->solveYzcFrontAjaxResponse($redirectResponse);
        return $redirectResponse;
    }

    /**
     * @param int $level
     */
    public function setCompression($level)
    {
        $this->compressLevel = $level;
    }

    /**
     * @return string
     * @deprecated 使用 getContent() 代替
     */
    public function getOutput()
    {
        return $this->content;
    }

    /**
     * 确保所有 response content 仅进行一次 setOutput 处理
     * @var array
     */
    private $_contentChangedHash = [];

    /**
     * @param string $output
     * @return Response
     */
    public function setOutput($output)
    {
        if (array_key_exists(md5($output), $this->_contentChangedHash)) {
            return $this;
        }

        $this->_contentChangedHash[md5($output)] = true;

        $output = changeOutPutByZone($output, $this->getSession());

        $output = $this->replaceDomain($output);

        $view = view();
        $output = strtr($output, [
            $view->head() => $view->renderHead(),
            $view->beginBody() => $view->renderBeginBody(),
            $view->endBody() => $view->renderEndBody(),
        ]);

        $this->content = $output;
        $this->_contentChangedHash[md5($output)] = true;

        return $this;
    }

    /**
     * #27711 在PHP response中替换新旧域名为当前登录域名
     * @param string $output
     * @return string
     */
    private function replaceDomain(string $output): string
    {
        $replaceDomains = trim(configDB('replace_domains', ''));
        if (empty($replaceDomains)) {
            return $output;
        }
        $replaceDomains = explode(',', $replaceDomains);

        $currentHost = request()->getHost();
        $currentDomain = request()->getScheme() . '://' . $currentHost;
        $patternReplacementMap = [];
        foreach ($replaceDomains as $replaceDomain) {
            if ($replaceDomain == $currentHost) {
                continue;
            }
            $patternReplacementMap['href="http://' . $replaceDomain] = 'href="' . $currentDomain;
            $patternReplacementMap["href='http://" . $replaceDomain] = "href='" . $currentDomain;
            $patternReplacementMap['href="https://' . $replaceDomain] = 'href="' . $currentDomain;
            $patternReplacementMap["href='https://" . $replaceDomain] = "href='" . $currentDomain;
            $patternReplacementMap['src="http://' . $replaceDomain] = 'src="' . $currentDomain;
            $patternReplacementMap["src='http://" . $replaceDomain] = "src='" . $currentDomain;
            $patternReplacementMap['src="https://' . $replaceDomain] = 'src="' . $currentDomain;
            $patternReplacementMap["src='https://" . $replaceDomain] = "src='" . $currentDomain;
        }

        if (empty($patternReplacementMap)) {
            return $output;
        }

        return str_replace(array_keys($patternReplacementMap), array_values($patternReplacementMap), $output);
    }

    /**
     * @param string $data
     * @param int $level
     * @return string
     * @deprecated 待定废除，目前可用，后续可能使用 nginx 处理压缩
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

        $this->headers->set('Content-Encoding', $encoding);
        return gzencode($data, (int)$level);
    }

    /**
     * 立即结束当前请求，并返回 Json 数据
     * @param array|object|int|string $data
     * @return void
     * @deprecated 使用 return $response->json($data); 代替
     */
    public function returnJson($data)
    {
        if (is_array($data) || is_object($data)) {
            $this->setOutput(json_encode($data));
        } else {
            $this->setOutput($data);
        }
        $response = new JsonResponse($this->content, 200, [], true);
        $response->send();
        exit($this->exitStatus);
    }

    /**
     * @param array $data
     * @param string $msg
     * @param int $code
     * @deprecated 使用 return $response->jsonSuccess($data); 代替
     */
    public function success($data = [], $msg = 'Saved successfully.', $code = 200)
    {
        $json['code'] = $code;
        $json['msg'] = $msg;
        if ($data) {
            $json['data'] = $data;
        }
        $this->returnJson($json);
    }

    /**
     * @param string $msg
     * @param array $data
     * @param int $code
     * @deprecated 使用 return $response->jsonFailed($data); 代替
     */
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
     * @deprecated 使用 send() 代替
     */
    public function output()
    {
        $content = $this->getContent();
        if (!is_null($content)) {
            $compressLevel = $this->compressLevel ?: configDB('config_compression');
            $content = $compressLevel ? $this->compress($content, $compressLevel) : $content;
            $this->setContent($content);
        }
        $this->send();
        exit($this->exitStatus);
    }

    /**
     * 返回 json
     * @param $data
     * @param int $status
     * @param array $headers
     * @return JsonResponse
     */
    public function json($data, $status = 200, $headers = [])
    {
        if (is_array($data) || is_object($data)) {
            $this->setOutput(json_encode($data));
        } else {
            $this->setOutput($data);
        }
        return new JsonResponse($this->content, $status, $headers, true);
    }

    /**
     * 发送文件
     * @param $file
     * @param null $name
     * @param array $headers
     * @param string $disposition
     * @return BinaryFileResponse
     */
    public function download($file, $name = null, array $headers = [], $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT)
    {
        $response = new BinaryFileResponse($file, 200, $headers, true, $disposition);

        if ($name) {
            $response->setContentDisposition($disposition, $name, Str::ascii($name));
        }

        return $response;
    }

    /**
     * 按流的形式下载文件
     * @param $callback
     * @param null $name
     * @param array $headers
     * @param string $disposition
     * @return StreamedResponse
     */
    public function streamDownload($callback, $name, array $headers = [], $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT)
    {
        $response = new StreamedResponse($callback, 200, $headers);

        if ($name) {
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                $disposition,
                $name,
                Str::ascii($name)
            ));
        }

        return $response;
    }

    /**
     * 在线显示文件内容
     * @param $file
     * @param array $headers
     * @return BinaryFileResponse
     */
    public function file($file, $headers = [])
    {
        return new BinaryFileResponse($file, 200, $headers);
    }
}
