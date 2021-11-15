<?php
/**
 * Created by IntelliJ IDEA.
 * User: or
 * Date: 2020/7/28
 * Time: 10:52
 */

namespace App\Console\Commands;

use App\Models\Tracking\UploadTracking;
use Illuminate\Console\Command;
use App\Umfpay\SignUtil;
use App\Jobs\SendMail;

class TrackingUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracking:upload';

    protected $description = '联动支付上传运单号';

    /**
     * 联动支付的token
     * @var string
     */
    private $umfToken;
    /**
     * 联动支付的服务器url,和商户信息
     * @var string
     */
    private $umfHostUrl = "https://fx.soopay.net/cberest/v1";
    private $clientId = "e1d79940df706110764f9ff42fa887c5453866f0";
    private $clientSecret = "4c46858b58a0273e04061d136db9700bff434db9";
    private $auth_key = "eXpjbUFwaTp5emNtQXBpQDIwMTkwNTE1";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info("------tracking-upload-start------");
        //获取待上传运单号的订单
        $uploadTracking = new UploadTracking();
        $uploadTrackingOrders = $uploadTracking->getUploadTrackingOrders();
        $successUploadArr = $this->uploadTrackingInfo($uploadTrackingOrders);
        //修改uploadTracking表
        foreach ($successUploadArr as $subFerenceId => $successDate) {
            $uploadTracking->updateTrackingUploadStatus($subFerenceId);
        }
        \Log::info("------tracking-upload-end------");
    }

    public function uploadTrackingInfo($uploadTrackingOrders)
    {
        //上传运单号成功的订单
        $successUploadArr = [];
        foreach ($uploadTrackingOrders as $uploadTrackingOrder) {
            $post = [];
            $payId =  $uploadTrackingOrder->pay_id;
            $merSubReferenceId = $uploadTrackingOrder->mer_sub_reference_id;
            $logisticsName = $uploadTrackingOrder->logistics_name;
            $trackingNo = $uploadTrackingOrder->tracking_number;
            $trackingNumber = $logisticsName." ".$trackingNo;
            $requestUrl = "/payments/payment/" . $payId . "/update_express_info";
            $post[] = array(
                "mer_sub_reference_id" => $merSubReferenceId,
                "tracking_number" => $trackingNumber,
            );
            $subOrders = array(
                'sub_orders' => $post
            );
            $data = array(
                'order' => $subOrders
            );
            try {
                $result = $this->sendMsg($requestUrl, 'POST', json_encode($data));
                if (isset($result) && $result['meta']['ret_code'] == "0000") {
                    foreach ($post as $subOrder) {
                        $mer_sub_reference_id = $merSubReferenceId;
                        $successUploadArr[$mer_sub_reference_id] = $subOrder;
                    }
                }
            } catch (Exception $e) {
                \Log::info('联动支付上传失败,子订单信息' . json_encode($post));
            }
        }
        return $successUploadArr;
    }

    public function refreshToken()
    {
        try {
            if (empty($this->umfToken)) {
                $hostUrl = $this->umfHostUrl;
                $reqUrl = $hostUrl . "/oauth/authorize";
                $reqBodyArray = array(
                    "grant_type" => "client_credentials",
                    "client_secret" => $this->clientSecret,
                    "client_id" => $this->clientId
                );
                $body = json_encode($reqBodyArray);
                $header = array('Content-Type: application/json');
                $responseResult = $this->curlRequest($reqUrl, "POST", $header, $body);
                $this->umfToken = $responseResult['access_token'];
                if (empty($this->umfToken)) {
                    return false;
                } else {
                    return true;
                }
            }
        } catch (\Exception $e) {
            $msg = "[联动支付上传物流单号],refreshToken方法失败";
            throw new \Exception($msg . "\t" . $e->getMessage());
        }
    }

    public function curlRequest($url, $reqMethod, $header, $body = null)
    {
        // 初始化
        $curl = curl_init();
        // 设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        // 设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        if (strtoupper($reqMethod) == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        // 设置获取的信息输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //取消ssl证书验证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // 执行命令
        $pay_result = curl_exec($curl);
        // 关闭URL请求
        curl_close($curl);
        // 将结果转为数组
        $pay_result = json_decode($pay_result, true);
        return $pay_result;
    }

    public function send($reqUrl, $reqMethod, $data)
    {
        try {
            $signature = $this->sign($data);
            $header = array('Content-Type: application/json', 'Authorization:Bearer' . $this->umfToken, 'Accept-Language:ZH', 'Signature:' . $signature);
            $result = $this->curlRequest($this->umfHostUrl . $reqUrl, $reqMethod, $header, $data);
            return $result;
        } catch (\Exception $e) {
            $msg = "[联动优势上传物流运单号失败],send方法失败";
            throw new \Exception($msg . "\t" . $e->getMessage());
        }
    }

    public function sendMsg($url, $reqMethod, $data)
    {
        try {
            //获取请求的token
            if (!empty($this->umfToken)) {
                $testToken = $this->send($url, $reqMethod, $data);
                if (isset($testToken['meta']['ret_code']) && $testToken['meta']['ret_code'] = '00280703') {
                    $this->umfToken = "";
                    $this->refreshToken();
                    $resMessage = $this->send($url, $reqMethod, $data);
                }
            } else {
                $this->refreshToken();
                $resMessage = $this->send($url, $reqMethod, $data);
            }
            return $resMessage;
        } catch (\Exception $e) {

            throw new \Exception("\t" . $e->getMessage());
        }
    }

    public function sign($jsonDate)
    {
        $str = utf8_encode($jsonDate);
        $signature = SignUtil::sign2($str);
        return $signature;
    }
}