<?php

namespace App\Components;

use Illuminate\Database\Eloquent\Model;

/**
 * 批量插入数据库，自动分批次，防止批次数据太多导致插入失败
 * 注意分批次没有使用事务
 */
class BatchInsert
{
    /**
     * 达到该数量自动执行一次批量插入，大于0
     * @var int
     */
    private $maxRows;
    /**
     * 表名
     * @var string
     */
    private $table;
    /**
     * 数据
     * @var array
     */
    private $rows = [];
    /**
     * 当前行数
     * @var int
     */
    private $rowIndex = 0;

    /**
     * 批量插入开始
     * @param string|Model $modelClass
     * @param int $maxRowsOneBatch
     */
    public function begin($modelClass, $maxRowsOneBatch = 500)
    {
        $this->maxRows = $maxRowsOneBatch;
        $this->table = (new $modelClass())->getTable();
        $this->rowIndex = 0;
    }

    /**
     * 批量插入结束
     * @return bool 本次是否有执行 insert
     */
    public function end()
    {
        return $this->insertOneBatch();
    }

    /**
     * @param $data
     * @return bool 本次是否有执行 insert
     */
    public function addRow($data)
    {
        $this->rows[] = $data;
        $this->rowIndex++;

        if ($this->rowIndex % $this->maxRows === 0) {
            return $this->insertOneBatch();
        }
        return false;
    }

    /**
     * 批量插入一批
     * @return bool true 表示成功，false 表示失败或无需插入
     */
    private function insertOneBatch()
    {
        if (!$this->rows) {
            return false;
        }

        $isOk = app()->get('db')->table($this->table)->insert($this->rows);
        if ($isOk) {
            $this->rows = [];
            return true;
        }
        return false;
    }
}
