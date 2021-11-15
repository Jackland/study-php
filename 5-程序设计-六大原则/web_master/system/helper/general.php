<?php

use App\Helper\CountryHelper;
use Illuminate\Support\Arr;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function token($length = 32)
{
    // Create random token
    $string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    $max = strlen($string) - 1;

    $token = '';

    for ($i = 0; $i < $length; $i++) {
        $token .= $string[mt_rand(0, $max)];
    }

    return $token;
}

/**
 * Backwards support for timing safe hash string comparisons
 *
 * http://php.net/manual/en/function.hash-equals.php
 */

if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string)
    {
        $known_string = (string)$known_string;
        $user_string = (string)$user_string;

        if (strlen($known_string) != strlen($user_string)) {
            return false;
        } else {
            $res = $known_string ^ $user_string;
            $ret = 0;

            for ($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);

            return !$ret;
        }
    }
}

/**
 * @param array|object $input
 * @param string $index
 * @param string|int|null $defaultValue
 * @return mixed|string
 * @deprecated 使用 $this->request->query->get 或 $this->request->input->get 等代替
 */
function get_value_or_default($input, $index, $defaultValue = '')
{
    if (!empty($index)) {
        if (is_array($input) && isset($input[$index])) {
            return $input[$index];
        }

        if (is_object($input) && isset($input->{$index})) {
            return $input->{$index};
        }
    }

    return $defaultValue;
}

/**
 * @param $obj
 * @return array
 */
function obj2array($obj)
{
    if (empty($obj)) return [];
    return json_decode(json_encode($obj), true);
}

/**
 * 递归移除字符串左右两边的非法字符
 *
 * @param array|object|string $input
 */
function trim_strings(&$input)
{
    if (is_string($input)) {
        $input = trim($input);
    } elseif (!empty($input) && (is_array($input) || is_object($input))) {
        foreach ($input as &$value) {
            trim_strings($value);
        }
    }
}

/**
 * 判断是否存在且不为 空/0/null
 *
 * @param array|object $input
 * @param string $key
 * @return bool
 */
function isset_and_not_empty($input, string $key): bool
{
    if (is_array($input) && isset($input[$key]) && !empty($input[$key])) {
        return true;
    }

    if (is_object($input) && isset($input->$key) && !empty($input->$key)) {
        return true;
    }
    return false;
}

/**
 * 格式化字符串的一种方法
 * dprintf('{a} {b}',['a'=>'hello','b'=>'world']) => output:hello world
 * dprintf('{0} {1}',['hello','world']) => output:hello world
 * dprintf('{0} {1}','hello','world') => output:hello world
 *
 * @param string $format
 * @param mixed ...$args
 * @return string
 */
if (!function_exists('dprintf')) {
    function dprintf(string $format, ...$args)
    {
        if (empty($args) || !$format) {
            return $format;
        }
        if (!is_array($args[0])) {
            return dprintf($format, $args);
        }
        $args = $args[0];
        foreach ($args as $k => $v) {
            $format = strtr($format, ['{' . $k . '}' => $v]);
        }

        return $format;
    }
}


if (!function_exists('truncate')) {
    /**
     * 截取字符串
     *
     * @param  $string
     * @param int $length
     * @param string $pads
     * @param int $minLength 最小截取长度 表示大于多长时候开始截取
     * @return string
     */
    function truncate($string, int $length, string $pads = '...', int $minLength = 0)
    {
        if (empty($string) || $length <= 0) {
            return '';
        }
        if (mb_strlen($string) <= max($length, $minLength)) {
            return $string;
        }

        return mb_substr($string, 0, $length) . $pads;
    }
}

/**
 * 获取当前系统毫秒时间戳
 *
 * @return float
 */
function micro_time()
{
    list($micro_sec, $sec) = explode(" ", microtime());
    return ((float)$micro_sec + (float)$sec);
}

/**
 * 根据国别更换时间显示
 * @param string $output
 * @param \Framework\Session\Session $session
 * @param string $target_format 如果为空，即按照原输入时间格式输出
 * @return null|string
 */
function changeOutPutByZone($output, $session, $target_format = '')
{
    if (
        !is_string($output) ||
        !$session->has('country') ||
        !in_array($session->get('country'), CHANGE_TIME_COUNTRIES)
    ) {
        return $output;
    }
    $reg = "/(@?\d{4}-\d{1,2}-\d{1,2} \d{1,2})(:\d{1,2}:\d{1,2})?/";
    preg_match_all($reg, $output, $matches);
    $country = session('country', 'USA');
    $fromZone = CountryHelper::getTimezoneByCode('USA');
    $toZone = CountryHelper::getTimezoneByCode($country);
    foreach ($matches[0] as $index => $all_match_str) {
        if (strpos($all_match_str, '@', 0) === 0) {
            $output = str_replace($all_match_str, substr($all_match_str, 1), $output);
        } else {
            if (empty($matches[2][$index])) {
                $old_date = $matches[1][$index];
                $old_date_format = 'Y-m-d H';
            } else {
                $old_date = $matches[1][$index] . $matches[2][$index];
                $old_date_format = 'Y-m-d H:i:s';
            }
            $new_date = dateFormat($fromZone, $toZone, $old_date, $target_format ?: $old_date_format, $old_date_format);
            $output = str_replace($old_date, $new_date, $output);
        }
    }
    return $output;
}

/**
 *
 * 非太平洋时区时间转为 太平洋时区时间
 *
 * @param string $input
 * @param string $country_str 国家符号
 * @param string $target_format 目标时间格式，如果为空，则取原时间格式
 * @return mixed
 */
function changeInputByZone($input, $country_str, $target_format = '')
{
    if (
        !is_string($input) ||
        empty($country_str) ||
        !in_array($country_str, CHANGE_TIME_COUNTRIES)
    ) {
        return $input;
    }
    $reg = "/(@?\d{4}-\d{1,2}-\d{1,2} \d{1,2})(:\d{1,2}:\d{1,2})?/";
    preg_match_all($reg, $input, $matches);
    foreach ($matches[0] as $index => $all_match_str) {
        if (strpos($all_match_str, '@', 0) === 0) {
            $input = str_replace($all_match_str, substr($all_match_str, 1), $input);
        } else {
            if (empty($matches[2][$index])) {
                $old_date = $matches[1][$index];
                $old_date_format = 'Y-m-d H';
            } else {
                $old_date = $matches[1][$index] . $matches[2][$index];
                $old_date_format = 'Y-m-d H:i:s';
            }
            $new_date = dateFormat(CountryHelper::getTimezoneByCode($country_str), CountryHelper::getTimezoneByCode('USA'), $old_date, $target_format ?: $old_date_format, $old_date_format);
            $input = str_replace($old_date, $new_date, $input);
        }
    }
    return $input;
}

/**
 * 时区转换
 *
 * 支持： 'Y-m-d H:i:s' 'Y-m-d H' 这两种格式转换
 *
 * @param string $from_zone 原始时区
 * @param string $to_zone 目标时区
 * @param string $input_date 待转换的日期字符串
 * @param string $output_format 输出的日期格式
 * @param string $input_format 输入的日期格式
 * @return string
 * @since 适配不同的时间格式 2020-3-27 17:34:50 by Lester.You
 */
function dateFormat($from_zone, $to_zone, $input_date, $output_format = 'Y-m-d H:i:s', $input_format = '')
{
    $analysis_formats = [
        'Y-m-d H:i:s',
        'Y-m-d H'
    ];

    !empty($input_format) && $analysis_formats = array_unique(array_merge([$input_format], $analysis_formats));

    $datetime = false;
    foreach ($analysis_formats as $analysis_format) {
        if ($datetime = DateTime::createFromFormat($analysis_format, $input_date, new DateTimeZone($from_zone))) {
            break;
        }
    }

    $output_date = $input_date;
    if ($datetime) {
        $output_date = $datetime->setTimezone(new DateTimeZone($to_zone))
            ->format($output_format);
    }
    return $output_date;
}

/**
 * 根据国别获取timezone
 * @param string $country
 * @return string
 */
function getZoneByCountry($country)
{
    $map = [
        'DEU' => 'Europe/Berlin',
        'JPN' => 'Asia/Tokyo',
        'GBR' => 'Europe/London',
    ];

    return Arr::get($map, strtoupper($country), 'America/Los_Angeles');
}

/**
 * 获取当前国别的时间
 * @param $session
 * @param string $date 日期/时间字符串
 * @param string $format
 * @return string
 */
function currentZoneDate($session, $date, $format = 'Y-m-d H:i:s')
{
    $toZone = getZoneByCountry($session->get('country', 'USA'));
    $fromZone = 'America/Los_Angeles';
    $datetime = new DateTime($date, new DateTimeZone($fromZone));
    $datetime = $datetime->setTimezone(new DateTimeZone($toZone));
    return $datetime->format($format);
}


/*
 * 指定时间转美国时间
 * */
function changeToUSADate($session, $date, $format = 'Y-m-d H:i:s')
{
    if (empty($date)) {
        return $date;
    } elseif (isset($session->data['country']) && $session->data['country'] != 'USA') {
        $country = $session->data['country'];
        $fromZone = $country == 'DEU' ? 'Europe/Berlin' : ($country == 'JPN' ? 'Asia/Tokyo' : ($country == 'GBR' ? 'Europe/London' : 'America/Los_Angeles'));
        $toZone = 'America/Los_Angeles';
        $datetime = new DateTime($date, new DateTimeZone($fromZone));
        $datetime = $datetime->setTimezone(new DateTimeZone($toZone));
        return $datetime->format($format);
    } else {
        return date($format, strtotime($date));
    }

}

/**
 * 下载csv统一的方法
 * @param $fileName
 * @param $head
 * @param $contents
 */
function outputCsv($fileName, $head, $contents, $session)
{
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
    header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
    header('Expires:0');
    header('Pragma:public');
    echo chr(239) . chr(187) . chr(191);
    $fp = fopen('php://output', 'a+');
    /*    foreach ($head as $i => $v) {
            // CSV的Excel支持GBK编码，一定要转换，否则乱码
         //   $head [$i] = iconv('utf-8', 'gbk', $v);
        }*/
    fputcsv($fp, $head);
    if ((is_array($contents) && count($contents) > 0) || $contents instanceof Traversable) {
        foreach ($contents as $content) {
            $contentColumnNewArray = array();
            foreach ($content as $contentArray) {
                $contentColumnNew = changeOutPutByZone($contentArray, $session);
                array_push($contentColumnNewArray, $contentColumnNew);
            }
            fputcsv($fp, $contentColumnNewArray);
        }
    } else {
        $content = array('No Records!');
        fputcsv($fp, $content);
    }
}

/**
 * @param string $fileName
 * @param array $head
 * @param array $content
 * @param null $session
 * @throws \PhpOffice\PhpSpreadsheet\Exception
 * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
 */
function outputExcel(string $fileName, array $head, array $content, $session = null)
{
    $data = $content;
    array_unshift($data, $head);
    $spreadsheet = new Spreadsheet();
    $spreadsheet->setActiveSheetIndex(0);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->getStyle('O')->getNumberFormat()->setFormatCode("0.00");
    $sheet->setTitle('Sheet1')->fromArray($data, null, 'A1');
    $sheet->freezePane('A2');
    ob_end_clean();//解决乱码
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    $writer = IOFactory::createWriter($spreadsheet, 'Xls');
    $writer->save('php://output');
}

/**
 * 判断是否为post
 */
if (!function_exists('is_post')) {
    function is_post()
    {
        return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) == 'POST';
    }
}

/**
 *  判断是否为get
 */
if (!function_exists('is_get')) {
    function is_get()
    {
        return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) == 'GET';
    }
}

/**
 * 判断是否为ajax
 */
if (!function_exists('is_ajax')) {
    function is_ajax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtoupper($_SERVER['HTTP_X_REQUESTED_WITH']) == 'XMLHTTPREQUEST';
    }
}

/**
 *  判断是否为命令行模式
 */
if (!function_exists('is_cli')) {
    function is_cli()
    {
        return (PHP_SAPI === 'cli' or defined('STDIN'));
    }
}

/**
 * 生成临时zip文件
 * @param string $file_name 文件名称 必填
 * @param array $file_info
 * file_info  形如[ ['file_path'=>'d:/sds.jpg','file_name' => 'dsada/dsad.jpg'],[....],[....] ]
 * @return bool|string 成功则返回文件路径 失败返回false
 */
function generateZipFile(string $file_name, array $file_info)
{
    $temp_dir = DIR_STORAGE_TEMP;
    $temp_dir = str_replace(['\\', '\/'], DIRECTORY_SEPARATOR, $temp_dir);
    if (!is_dir($temp_dir)) @mkdir($temp_dir, 0777, true);
    $temp_file = rtrim(DIR_STORAGE_TEMP, '\\\/') . DIRECTORY_SEPARATOR . $file_name;
    if (is_file($temp_file)) @unlink($temp_file);
    $zip = new ZipArchive();
    if ($zip->open($temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($file_info as $file) {
            $path = $file['file_path'] ?? null;
            $path = str_replace(['\\', '\/'], DIRECTORY_SEPARATOR, $path);
            if (!$path || !is_file($path)) continue;
            $local_name = $file['file_name'] ?? null;
            if ($local_name) {
                $zip->addFile($path, $local_name);
            } else {
                $zip->addFile($path);
            }
        }
    }
    $zip->close();
    return is_file($temp_file) ? $temp_file : false;
}

/**
 * 修改显示时间，判断时令
 * @param $date
 * @return string PST or PDT
 */
function getPSTOrPDTFromDate($date): string
{
    $y = date('Y', strtotime($date));
    //当年的夏时令
    //起点：当年的3月1好 +1周 + （7-（3月1号周几）-1）+2h   =夏时令的起点
    $time_3_1 = strtotime("$y-3-1 00:00:00");
    $add_day = (7 - date('N', $time_3_1));
    $start_summer = strtotime("$y-3-1 00:00:00 +1 week $add_day days +2hours");
    //夏时令的截止点
    //截止点：当年11月1号 +（7-（11月1号星期几）-1）+ 2h -1s  =夏时令的截止点
    $time_11_1 = strtotime("$y-11-1 00:00:00");
    $add_day = (7 - date('N', $time_11_1));
    $end_summer = strtotime("$y-11-1 00:00:00 $add_day days +2hours -1 seconds");
    //计算时令
    $input_time = strtotime($date);
    if ($end_summer > $input_time && $input_time >= $start_summer) {  //处于夏时令
        return 'PDT';
    } else {
        return 'PST';
    }
}

/**
 * 递归处理 request 传入的参数(非太平洋时区时间转为 太平洋时区时间)
 *
 * @param string|array|object|mixed $input
 * @param string $country_str
 */
function request_data_change(&$input, $country_str)
{
    if (is_array($input) || is_object($input)) {
        foreach ($input as $key => &$value) {
            request_data_change($value, $country_str);
        }
    } elseif (is_string($input) && !empty($input)) {
        $input = changeInputByZone($input, $country_str);
    }
}

/**
 * 根据给定的时间格式 去解析时间字符串
 *
 * @param string $time_str 待解析的时间字符串
 * @param string $out_format 将要输出的时间格式
 * @param array $formats 解析用到的时间格式
 * @return string
 */
function analyze_time_string($time_str, $out_format = 'Y-m-d H:i:s', $formats = ['Y-m-d H:i:s', 'Y-m-d H', 'Y-m-d', 'Y/m/d H',])
{
    if (empty($time_str) || empty($out_format) || empty($formats)) {
        return $time_str;
    }

    $new_time_str = '';
    foreach ($formats as $format) {
        if ($dateObj = DateTime::createFromFormat($format, $time_str)) {
            $new_time_str = $dateObj->format($out_format);
            break;
        }
    }
    return $new_time_str;
}

/**
 * 比较两二维数组
 * @param array $arr1
 * @param array $arr2
 * @return int
 * @author xxl
 */
function compare_array(array $arr1, array $arr2)
{
    /*
     * 数组示例
     * array(
            array(
                'product_id'=>1080,
                'quantity'=>10
                ),
            array(
                'product_id'=>1081,
                'quantity'=>2
            ),
            array(
                'product_id'=>1083,
                'quantity'=>3
            )
        );
     */
    $productIdA = $arr1['product_id'];
    $qtyA = $arr1['quantity'];
    $areaA = $productIdA . "_" . $qtyA;
    $productIdB = $arr2['product_id'];
    $qtyB = $arr2['quantity'];
    $areaB = $productIdB . "_" . $qtyB;
    if ($areaA != $areaB) {
        if ($productIdA * $qtyA > $productIdB * $qtyB) {
            return 1;
        } else {
            return -1;
        }
    } else {
        return 0;
    }

}
