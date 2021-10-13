<?php
/**
 * Created by Admin.php.
 * User: fuyunnan
 * Date: 2021/10/13
 * Time: 10:23
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Admin extends model
{
    protected $table = 'admin';

    //禁用createTime/updateTime;
    public $timestamps =  false;
}