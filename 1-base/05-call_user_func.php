<?php
/**
 * Created by 5-can_fun_.php.
 * User: fuyunnan
 * Date: 2021/12/22
 *
 * 通过 call_user_func 来实现文件写入
 * 学习函数的回调
 *
 * Time: 16:49
 */
$columns = [
    [
        'label' => 'test01',
        'value' => function(baseInfo $info) {
            return $info->getName();
        },
    ],
    [
        'label' => 'test02',
        'value' => function(baseInfo $info) {
            return $info->getAge();
        }
    ],
];
class baseInfo{
    function getName(){
        return 'name'.rand(11,20);
    }
    function getAge(){
        return 'age'.rand(1,10);
    }
    function getArae(){
        return 'area'.rand(1,10);
    }
}
$fp = fopen('./a.csv', 'w');
$baseInfo = new baseInfo();


$aa = array_map(function ($item) use ($baseInfo) {
    echo '<pre>';
    var_dump($item['value']);
    return call_user_func($item['value'], $baseInfo);
}, $columns);
var_dump($aa).PHP_EOL;
echo '===========================';
die;
fputcsv($fp,array_map(function ($item) use ($baseInfo) {
    return call_user_func($item['area'], $baseInfo);
}, $columns));
echo '===========================';
fclose($fp);
//var_dump($map);
exit(1212);