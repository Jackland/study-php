<?php
/**
 * Created by 10-func-type.php.
 * User: fuyunnan
 * Date: 2022/1/20
 * Time: 17:46
 */

/**
 *测试php 函数返回的类型要求 安装严格类型要求
 */
class book
{

}


class read
{

    public function start(): book
    {
        return new book();
    }

    public function write(): self
    {
        return new self();
    }
}

var_dump((new  read())->start());
echo "<br>======<br>";
var_dump((new  read())->write());