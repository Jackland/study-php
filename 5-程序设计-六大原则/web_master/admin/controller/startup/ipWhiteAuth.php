<?php

use App\Logging\Logger;

/**
 * Created by ipWhiteAuth.php. 配置ip后台的白名单访问
 * User: fuyunnan
 * Date: 2021/9/7
 * Time: 10:47
 */
class ControllerStartupIpWhiteAuth extends Controller
{

    /**
     * description:检查白名单
     * @return bool|string
     * @throws Exception
     */
    public function index()
    {
        if (get_env('ENABLE_ADMIN_WHITE') === true) {
            if(!$this->checkWhiteIp()){
                Logger::adminWhiteAuth("当前用户不在白名单ip:". $this->request->getUserIp());
                header("location:{$this->request->getSchemeAndHttpHost()}", true, 302);
            }
        }
        return true;
    }


    /**
     * description:不在白名单配置 重定向到首页
     * @return \http\Client\Response|bool
     * @throws Exception
     */
    private function checkWhiteIp()
    {
        $ips = explode(';', get_env('ADMIN_ALLOW_IPS'));
        $ipNetwork = get_env('ADMIN_ALLOW_NETWORK');

        if (!empty(array_filter($ips)) && in_array($this->request->getUserIp(), $ips)) {
            return true;
        }
        if ((bool)$ipNetwork === true && $this->iPInNetwork($this->request->getUserIp(), $ipNetwork)) {
            return true;
        }
        return false;
    }

    /**
     * description:转化ip段数据
     * @param string $ip 当前ip
     * @param string $network ip段地址
     * @return bool
     * @throws Exception
     */
    private function iPInNetwork(string $ip, string $network)
    {
        $ip = (double)(sprintf("%u", ip2long($ip)));
        $s = explode('/', $network);
        if (!isset($s[1])) {
            throw new \Exception('white ip network Illegal! ex:192.168.0.1/16');
        }
        $network_start = (double)(sprintf("%u", ip2long($s[0])));
        $network_len = pow(2, 32 - $s[1]);
        $network_end = $network_start + $network_len - 1;
        if ($ip >= $network_start && $ip <= $network_end) {
            return true;
        }
        return false;
    }

}
