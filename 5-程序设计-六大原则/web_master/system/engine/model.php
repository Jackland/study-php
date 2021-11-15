<?php

/**
 * Class Model
 *
 * @package        OpenCart
 * @author        Daniel Kerr
 * @copyright    Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license        https://opensource.org/licenses/GPL-3.0
 * @link        https://www.opencart.com
 *
 *
 * @property Communication $communication
 * @property \Cart\Customer $customer
 * @property \Cart\Cart $cart
 * @property \Cart\Currency $currency
 * @property \Cart\Country $country
 */
abstract class Model extends \Framework\Model\BaseModel
{
    /**
     * @var string $table
     */
    protected $table;

    /**
     * @var string $primaryKey The primary key of the table.
     */
    protected $primaryKey;

    /**
     * Get the first data from this table by the primary key.
     *
     * @param int $id 主键值
     * @param array $columns 字段名
     * @param string $primaryKey 主键名
     * @return null|object
     * @throws Exception
     */
    public function find($id, $columns = ['*'], $primaryKey = 'id')
    {
        if (empty($this->table)) {
            throw new \Exception('The table cannot be empty.');
        }

        if (!empty($this->primaryKey)) {
            $primaryKey = $this->primaryKey;
        }

        if ($primaryKey != 'id') {
            return $this->orm->table($this->table)
                ->where($primaryKey, $id)
                ->first($columns);
        }

        return $this->orm->table($this->table)->find($id, $columns);
    }

    /**
     * @param string $tbName
     * @param array $data
     * @return bool|QueryResult
     */
    public function insert($tbName,array $data){
        $data = $this->_dataFormat($tbName,$data);
        if (!$data) return ;
        $sql = "insert into ".$tbName."(".implode(',',array_keys($data)).") values(".implode(',',array_values($data)).")";
        $ret = $this->db->query($sql);
        if (isset($ret->num_rows) && $ret->num_rows){
            $last = $this->db->query("select last_insert_id() as id");
            return $last->row['id'];
        }else{
            return $ret;
        }
    }

    /**
     * 过滤并格式化数据表字段
     *
     * @param string $tbName 数据表名
     * @param array $data POST提交数据
     * @return array $newdata
     */
    protected function _dataFormat($tbName,$data) {
        if (!is_array($data)) return array();

        $ret=array();
        foreach ($data as $key=>$val) {
            if (!is_scalar($val)) continue; //值不是标量则跳过

            $key = $this->_addChar($key);
            if (is_int($val)) {
                $val = intval($val);
            } elseif (is_float($val)) {
                $val = floatval($val);
            } elseif (preg_match('/^\(\w*(\+|\-|\*|\/)?\w*\)$/i', $val)) {
                // 支持在字段的值里面直接使用其它字段 ,例如 (score+1) (name) 必须包含括号
               // $val = $val;
            } elseif (is_string($val)) {
                $val = '"'.addslashes($val).'"';
            }
            $ret[$key] = $val;
        }
        return $ret;
    }


    /**
     * 字段和表名添加 `符号
     * 保证指令中使用关键字不出错 针对mysql
     *
     * @param string $value
     * @return string
     */
    protected function _addChar($value) {
        if ('*'==$value || false!==strpos($value,'(') || false!==strpos($value,'.') || false!==strpos($value,'`')) {
            //如果包含* 或者 使用了sql方法 则不作处理
        } elseif (false === strpos($value,'`') ) {
            $value = '`'.trim($value).'`';
        }
        return $value;
    }

    /**
     * 获取最近一次查询的sql语句
     *
     * @return String 执行的SQL
     */
    public function getLastSql() {
        return $this->_sql;
    }

    /**
     * 更新函数
     * @param string $tbName 操作的数据表名
     * @param array $data 参数数组
     * @param string $where
     * @return bool|QueryResult 受影响的行数
     */
    public function update($tbName,array $data, $where) {

        //安全考虑,阻止全表更新
        if (!trim($where)) return false;
        $data = $this->_dataFormat($tbName,$data);
        if (!$data) return ;
        $valStr = '';
        foreach($data as $k=>$v){
            $valStr .= $k.'='.$v.',';
        }
        $valStr = trim($valStr,',');
        $sql = 'update '.trim($tbName).' set '.$valStr.' where '.trim($where);
        return $this->db->query($sql);
    }

}