<?php

namespace Yzc;
use App\Logging\Logger;

class CommonFunction
{
    private $umfHostUrl = "https://fx.soopay.net/cberest/v1";
    private $clientId = "e1d79940df706110764f9ff42fa887c5453866f0";
    private $clientSecret = "4c46858b58a0273e04061d136db9700bff434db9";
    private $auth_key = "eXpjbUFwaTp5emNtQXBpQDIwMTkwNTE1";

    public function __construct($registry) {
        $this->orm = $registry->get('orm');
        $this->db = $registry->get('db');
    }


   public function getDisplayPrice($product_id,$buyer_id,$modified_price){
       $delicacyManagementPrice = $this->getDelicacyManagementInfoByNoView($product_id,$buyer_id);
       $productInfo = $this->getProductPriceAndFreight($product_id);
       $isCollectionFromDomicile = in_array($this->getCustomerGroupId($buyer_id),COLLECTION_FROM_DOMICILE);
       //货值价格即可
       if($isCollectionFromDomicile){
           $freight = 0;
       }else{
           //$freight = $productInfo['freight'];
           $freight = 0;
       }
       if(isset($delicacyManagementPrice['current_price'])){
           $price = $delicacyManagementPrice['current_price'] + $freight;
       }else{
           if(!$isCollectionFromDomicile){
               if(!empty($modified_price)){
                   $price = ($modified_price * 100 + $freight * 100) / 100;
               }else {
                   $price =  ($productInfo['price']*100 + $freight*100)/100;
               }

               $price = round($price,2);

           }else{

               if(!empty($modified_price)){
                   $price = $modified_price;
               }else {
                    $price = $productInfo['price'];
               }
           }
       }
       return $price;
   }

    public function getDelicacyManagementInfoByNoView($product_id, $buyer_id, $seller_id = null)
    {
        if (empty($product_id) || empty($buyer_id)) {
            return null;
        }

        $dm_sql = "select product_id,product_display,current_price,price
                    from oc_delicacy_management
                    where product_id = $product_id and buyer_id = $buyer_id and expiration_time > NOW() order by id DESC limit 1";

        $dmg_sql = "select dmg.id from oc_delicacy_management_group as dmg
                    join oc_customerpartner_product_group_link as pgl on pgl.product_group_id = dmg.product_group_id
                    join oc_customerpartner_buyer_group_link as bgl on bgl.buyer_group_id = dmg.buyer_group_id
                    where dmg.status =1 and pgl.status=1 and bgl.status=1 and pgl.product_id = $product_id and bgl.buyer_id = $buyer_id ";
        $seller_id && $dmg_sql .= " and dmg.seller_id = " . $seller_id;
        if ($this->db->query($dmg_sql)->num_rows > 0) {
            $result = [
                'product_display' => 0,
            ];
        } else {
            $dm_res = $this->db->query($dm_sql);
            if ($dm_res->num_rows > 0) {
                $result = [
                    'product_display' => $dm_res->row['product_display'],
                    'current_price' => $dm_res->row['current_price'],
                ];
            } else {
                $result = null;
            }
        }
        return $result;
    }

    public function getCustomerGroupId($customer_id){
        $result = $this->db->query("select oc.customer_group_id from oc_customer oc  WHERE oc.customer_id=".$customer_id);
        return $result->row['customer_group_id'];
    }

    public function getProductPriceAndFreight($product_id){
        $result = $this->db->query("select op.price,op.freight from oc_product op  WHERE op.product_id=".$product_id);
        return $result->row;
    }

    /**
     * 消息队列处理订单后续逻辑，将订单信息插入队列,异步操作
     * @param $exchange 交换器名称
     * @param $queue 队列名称
     * @param $rountKey
     * @param $msg 消息
     */
    function rabbitMqProducer($exchange,$queue,$rountKey,$msg){
//        //生产者生产消息
//        try {
//            Logger::rabbitMQ(['消息队列生产开始', 'msg' => $msg]);
//            $connection = new AMQPStreamConnection(RABBITMQ_HOST, RABBITMQ_PORT, RABBITMQ_LOGIN, RABBITMQ_PASSWORD);
//            $channel = $connection->channel();
//            /**
//             * 定义交换器
//             * 1.exchange:交换器名称
//             * 2.type:交换器类型
//             * 3.durable:是否持久化
//             * 4.auto_delete:自动删除
//             * 5.no_wait:默认值为false,需要服务器返回,防止丢失
//             * 6.passive:检测相应的交换器是否存在
//             */
//            $channel->exchange_declare($exchange, "direct", false, true, false);
//            /**
//             * 定义队列
//             * 1.queue:队列名称
//             * 2.durable:是否持久化
//             * 3.exclusive 设置是否排他
//             * 4.autoDelete: 设置是否自动删除
//             */
//            $channel->queue_declare($queue, false, true, false, false);
//            /**
//             *队列与交换器绑定
//             */
//            $channel->queue_bind($queue, $exchange, $rountKey);
//            //发送的数据
//            $msg = new AMQPMessage(json_encode($msg), array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
//            $channel->basic_publish($msg, "purchase_exchange", $rountKey);
//            $channel->close();
//            $connection->close();
//            Logger::rabbitMQ(['消息队列生产结束', 'msg' => $msg]);
//        }catch (Exception $e){
//            $errorMsg = "消息队列生产错误," . $msg .",错误信息：".$e->getMessage();
//            Logger::rabbitMQ(['消息队列生产失败', 'msg' => $errorMsg], 'error');
//        }
        try {
            Logger::rabbitMQ(['消息队列生产开始', 'msg' => $msg]);
            $response = post_url(URL_RABBIT_MQ_WORK . '/api/purchaseOrder/process', http_build_query($msg));
            $resData = json_decode($response, true);
            if (isset($resData['status']) && $resData['status']) {
                Logger::rabbitMQ(['消息队列生产结束', 'msg' => $msg, 'response' => $resData]);
            }else {
                Logger::rabbitMQ(['消息队列生产失败', 'msg' => $msg, 'response' => $response], 'warning');
                $arr = array(
                    'type'=>'purchase_rebate',
                    'message'=>json_encode($msg),
                    'create_time'=>date('Y-m-d H:i:s'),
                    'create_person'=>'purchase_order'
                );
                $this->orm->table('tb_rabbitmq_produce_fail')->insert($arr);
            }
        } catch (\Throwable $e) {
            Logger::rabbitMQ(['消息队列生产失败', 'msg' => $msg, 'err' => $e->getMessage()], 'error');
            $arr = array(
                'type'=>'purchase_rebate',
                'msg'=>json_encode($msg),
                'create_time'=>date('Y-m-d H:i:s'),
                'create_person'=>'purchase_order'
            );
            $this->orm->table('tb_rabbitmq_produce_fail')->insert($arr);
        }

    }


    public function sendMsg($url,$reqMethod, $order_id)
    {
        if (strtoupper($reqMethod) != "GET") {
            $msg = "[UMF采购订单查询订单状态失败],采购订单ID：" . $order_id . ",查询方式不为GET请求";
            throw new \Exception($msg);
        }
        $resMessage = null;
        try {
            //获取请求的token
            if (!empty($this->umfToken)) {
                $testToken = $this->send($url, $reqMethod);
                if (isset($testToken['meta']['ret_code']) && $testToken['meta']['ret_code'] = '00280703') {
                    $this->umfToken = "";
                    $this->refreshToken();
                    $resMessage = $this->send($url, $reqMethod);
                }
            } else {
                $this->refreshToken();
                $resMessage = $this->send($url, $reqMethod);
            }
            return $resMessage;
        }catch (\Exception $e){
            $msg = "[UMF采购订单查询订单状态失败],采购订单ID：" . $order_id . ",sendMsg方法失败";
            throw new \Exception($msg."\t".$e->getMessage());
        }
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
                $this->umfToken = $responseResult['access_token'] ?? '';
                if (empty($this->umfToken)) {
                    return false;
                } else {
                    return true;
                }
            }
        }catch (\Exception $e){
            $msg = "[UMF采购订单查询订单状态失败],refreshToken方法失败";
            throw new \Exception($msg."\t".$e->getMessage());
        }
    }

    public function curlRequest($url, $reqMethod,$header,$body = null)
    {
        // 初始化
        $curl = curl_init();
        // 设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        // 设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        if(strtoupper($reqMethod) == 'POST'){
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

    public function send($reqUrl,$reqMethod){
        try{
            $header =array('Content-Type: application/json','Authorization:Bearer'.$this->umfToken,'Accept-Language:ZH');
            $result = $this->curlRequest($this->umfHostUrl.$reqUrl,$reqMethod,$header);
            return $result;
        }catch (\Exception $e){
            $msg = "[UMF采购订单查询订单状态失败],send方法失败";
            throw new \Exception($msg."\t".$e->getMessage());
        }
    }
}
