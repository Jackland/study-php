<?php

use App\Components\PageViewSafe\BaseChecker;
use App\Components\PageViewSafe\CaptchaTransfer\TransferRequest;
use App\Components\PageViewSafe\CaptchaTransfer\TransferResponse;
use App\Components\PageViewSafe\IpRateLimitChecker;
use App\Components\PageViewSafe\IpRouteChecker;
use App\Components\PageViewSafe\LoginCountChecker;
use App\Components\PageViewSafe\LoginIpChangeChecker;
use App\Components\PageViewSafe\Support;
use App\Logging\LogChannel;
use Framework\Helper\StringHelper;
use Framework\Log\LogManager;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;

class ControllerStartupSafeChecker extends Controller
{
    const VERIFY_DATA_KEY = 'vk';

    public function index()
    {
        if (!$this->isEnable()) {
            return null;
        }

        $checkers = [
            IpRouteChecker::class,
            LoginCountChecker::class,
            LoginIpChangeChecker::class,
            IpRateLimitChecker::class,
        ];

        foreach ($checkers as $checker) {
            // 载入 Checker，加入配置
            /** @var BaseChecker|string $checker */
            $checker = app()->make($checker, [
                'customer' => $this->customer,
                'logger' => app(LogManager::class),
                'config' => Support::getConfig($checker, []),
            ]);
            // 检查是否是从验证码页面跳回来的
            if ($data = $this->getVerifiedData($checker)) {
                $this->debugLog(['验证码页面返回', $data]);
                // 有数据，调用 pass 通过
                $checker->pass($data);
            }
            // 执行检查
            if ($checker->check()) {
                $this->debugLog(['检查通过', get_class($checker)]);
                continue;
            }
            // 检查不通过获取授权信息
            $data = $checker->getForbiddenData();
            $this->debugLog(['检查未通过', get_class($checker), $data]);
            // 阻止继续
            return $this->forbiddenWithData($checker, $data);
        }

        return null;
    }

    /**
     * @return bool
     */
    private function isEnable(): bool
    {
        if (!Support::getConfig(null, [])) {
            $this->debugLog('配置不存在');
            return false;
        }
        if (!Support::getConfig('enable', false)) {
            $this->debugLog('全局未启用');
            return false;
        }
        if (request()->isAjax() && !Support::getConfig('enableWhenAjax', false)) {
            // enableWhenAjax 为 false 时，不检查 ajax
            $this->debugLog('跳过ajax');
            return false;
        }

        $ip = request()->getUserIp();
        foreach (Support::getConfig('whiteListIps', []) as $pattern) {
            if (StringHelper::matchWildcard($pattern, $ip)) {
                $this->debugLog(['白名单IP忽略', $pattern]);
                return false;
            }
        }

        $route = request('route', 'common/home');
        foreach (Support::getConfig('whiteListRoutes', []) as $pattern) {
            if (StringHelper::matchWildcard($pattern, $route)) {
                $this->debugLog(['白名单路由忽略', $pattern, $route]);
                return false;
            }
        }

        return true;
    }

    private $_transferResponse = false;

    /**
     * @param BaseChecker $checker
     * @return false|mixed
     */
    private function getVerifiedData(BaseChecker $checker)
    {
        if ($this->_transferResponse === false) {
            $this->_transferResponse = $this->getTransferResponse();
        }
        if (!$this->_transferResponse) {
            return false;
        }
        if ($this->_transferResponse->checker !== basename(get_class($checker))) {
            // 非当前 checker 的忽略
            return false;
        }

        return $this->_transferResponse->data;
    }

    /**
     * @return TransferResponse|null
     */
    private function getTransferResponse(): ?TransferResponse
    {
        $data = request(self::VERIFY_DATA_KEY);
        if (!$data) {
            // 无数据
            return null;
        }
        $this->debugLog(['验证码页面返回数据', $data]);
        /** @var TransferResponse $transferResponse */
        $transferResponse = Support::getEncryption()->decrypt($data, TransferResponse::class);
        if (!$transferResponse) {
            // 解析 data 失败
            return null;
        }
        if (!$transferResponse->checkDataVerified($this->cache)) {
            // 返回数据校验不通过
            return null;
        }

        return $transferResponse;
    }

    /**
     * @param BaseChecker $checker
     * @param mixed $data
     * @return Response
     */
    private function forbiddenWithData(BaseChecker $checker, $data): Response
    {
        $url = Support::getConfig('captchaUrl', '');
        return response()->redirectTo(Support::buildUrl($url, [
            self::VERIFY_DATA_KEY => Support::getEncryption()->encrypt(new TransferRequest($checker, $data)),
        ]));
    }

    private $_logger = false;

    /**
     * @param array|string $msg
     */
    private function debugLog($msg)
    {
        if ($this->_logger === false) {
            if (Support::getConfig('checkerDebug', false)) {
                $this->_logger = logger(LogChannel::SAFE_CHECKER);
            } else {
                $this->_logger = new NullLogger();
            }
        }
        if (is_array($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $this->_logger->info($msg);
    }
}
