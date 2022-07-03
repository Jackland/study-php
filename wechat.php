<?php
/**
 * Created by wechat.php.
 * User: fuyunnan
 * Date: 2022/6/7
 * Time: 11:06
 */

$url = '	http://wchat.phpstart.cn/wechat.php?msg_signature=3cc2a873bc26f53aa99ef4580e6e14ca2d14c92b&timestamp=1654583131&nonce=cftp3z6ebzi&echostr=Z0T4%2FNL2VVnailhV6mGeNAXWSDMCUfLUA4er56VerF7ROxlCVp7sG1ZzAoRyynCSIeaXPZTzYWD%2F4WGaQeF0Fw%3D%3D';

$url = urldecode($url);
var_dump($url);


$data = $_GET;
var_dump($data);