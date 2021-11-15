<?php

use Illuminate\DataBase\Query\Builder;

function get_url($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($url,CURLOPT_HTTPHEADER,$headerArray);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}


function post_url($url, $data, $headers = null, $opt = null,$auth = null)
{
    if (is_array($data)) {
        $data = http_build_query($data);
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    if ($headers) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    if($auth){
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $auth);
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    if ($opt) {
        foreach ($opt as $key => $value) {
            if ($key == 'CURLOPT_TIMEOUT') {
                curl_setopt($curl, CURLOPT_TIMEOUT, $value);
            }
        }
    }

    $output = curl_exec($curl);
    curl_close($curl);
    return $output;
}

function get_https_header()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
}

/**
 * [get_need_string description] 虽然很蠢但是凑合着用用吧
 * @param $string
 * @param array $remove
 * @return string|string[]
 */
function get_need_string($string,$remove = [' '])
{
    foreach($remove as $key => $value){
        $string = str_replace($value,'',$string);
    }
    return $string;
}


/**
 * [get_complete_sql description] 根据查询构造器给出完整的sql
 * @param Builder $builder
 * @return string
 */
function get_complete_sql($builder)
{
    $bindings = $builder->getBindings();
    $i = 0;
    return preg_replace_callback('/\?/', function ($matches) use ($bindings, &$i) {
        return "'" . addslashes($bindings[$i++] ?? '') . "'";
    }, $builder->toSql());
}

/**
 * 加密解密算法
 * @param string $string 原始字符串
 * @param string $operation 操作方式('E'加密 'D'解密)
 * @param string $key 密钥
 * @return false|string|string[]
 */
function encrypt($string, $operation, $key = '')
{
    $key = md5($key);
    $key_length = strlen($key);
    $string = $operation == 'D' ? base64_decode($string) : substr(md5($string . $key), 0, 8) . $string;
    $string_length = strlen($string);
    $rndkey = $box = array();
    $result = '';
    for ($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($key[$i % $key_length]);
        $box[$i] = $i;
    }
    for ($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }
    for ($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }
    if ($operation == 'D') {
        if (substr($result, 0, 8) == substr(md5(substr($result, 8) . $key), 0, 8)) {
            return substr($result, 8);
        } else {
            return '';
        }
    } else {
        return str_replace('=', '', base64_encode($result));
    }



}


/**
 * 判断字符串是否为 Json 格式
 *
 * @param string $data Json 字符串
 * @param bool $assoc 是否返回关联数组。默认返回对象
 *
 * @return array|bool|object 成功返回转换后的对象或数组，失败返回 false
 */
function isJson($data = '', $assoc = false)
{
    $data = json_decode($data, $assoc);
    if (($data && is_object($data)) || (is_array($data) && !empty($data))) {
        return $data;
    }
    return false;
}


function get_ip()
{
    $ip = false;
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
        if ($ip) {
            array_unshift($ips, $ip);
            $ip = FALSE;
        }
        for ($i = 0; $i < count($ips); $i++) {
            if (!eregi('^(10│172.16│192.168).', $ips[$i])) {
                $ip = $ips[$i];
                break;
            }
        }
    }
    return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
}

//返回当前的毫秒时间戳
function msectime() {
    list($msec,$sec) = explode(' ',microtime());
    return (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
}

function escape_like_str($str)
{
    $like_escape_char = '!';

    return str_replace([$like_escape_char, '%', '_'], [
        $like_escape_char.$like_escape_char,
        $like_escape_char.'%',
        $like_escape_char.'_',
    ], $str);
}

?>
