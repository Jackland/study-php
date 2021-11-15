<?php
/**
 * Created by IntelliJ IDEA.
 * User: or
 * Date: 2020/8/11
 * Time: 14:17
 */

namespace App\Umfpay;


/**
 * 数据签名,验签处理工具类
 * @author xuchaofu
 * 2010-03-29
 */
Class SignUtil
{

    const PRIVATE_KEY_PATH = __DIR__."/key.pem";

    /**
     * 数据签名
     * @param $plain    签名明文串
     * @param $priv_key_file    商户私钥证书
     */
    public static function sign2($plain)
    {
        try {
            //用户私钥证书
            $priv_key_file = self::PRIVATE_KEY_PATH;
            //如果商户号不为空，则获取商户私钥地址配置信息
            if (!File_exists($priv_key_file)) {
                return FALSE;
                die("The key is not found, please check the configuration!");
            }
            $fp = fopen($priv_key_file, "rb");

            $priv_key = fread($fp, 8192);
            @fclose($fp);
            $pkeyid = openssl_get_privatekey($priv_key);
            if (!is_resource($pkeyid)) {
                return FALSE;
            }
            // compute signature
            @openssl_sign($plain, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
            // free the key from memory
            @openssl_free_key($pkeyid);
            return base64_encode($signature);
        } catch (Exception $e) {
            \Log::info("Signature attestation failure" . $e->getMessage());
        }
    }
}