<?php

namespace DB;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;

class Orm
{
    /**
     * @var Manager $orm
     */
    private $orm = null;

    private $result = null;

    private $connection = 'default';

    public function __construct(...$args)
    {
        $this->orm = $args[0];
    }

    public function setConnection($name)
    {
        $this->connection = $name;
    }

    /**
     * @param $sql
     * @param array $params
     * @return \stdClass
     * user：wangjinxin
     * date：2020/3/12 17:17
     */
    public function query($sql, $params = array())
    {
        $sql = ltrim(str_replace(["\r\n", "\n", "\r"], ' ', $sql));
        $action = trim(strtoupper(substr($sql, 0, strpos($sql, ' '))));
        $res = null;
        $con = $this->getConnection();
        $res = new \stdClass();
        switch ($action) {
            case 'INSERT':
            case 'DELETE':
            case 'UPDATE':
            {
                $res->row = [];
                $res->rows = [];
                $res->num_rows = $con->update($sql, $params);
                break;
            }
            case 'SELECT':
            {
                $ret = $con->select($sql, $params);
                array_walk($ret, function (&$item) {
                    $item = get_object_vars($item);
                });
                $res->row = $ret[0] ?? [];
                $res->rows = $ret;
                $res->num_rows = count($ret);
                break;
            }
            default:
            {
                $res->row = [];
                $res->rows = [];
                $res->num_rows = $con->affectingStatement($sql, $params);
                break;
            }
        }
        $this->result = $res;
        return $res;
    }

    /**
     * Run a select statement against the database and returns a generator.
     * @param $sql
     * @param array $params
     * @return \Generator
     */
    public function cursor($sql, $params = array())
    {
        // only select sql
        return $this->getConnection()->cursor(ltrim(str_replace(["\r\n", "\n", "\r"], ' ', $sql)), $params);
    }

    public function escape($value)
    {
        return str_replace(array("\\", "\0", "\n", "\r", "\x1a", "'", '"'), array("\\\\", "\\0", "\\n", "\\r", "\Z", "\'", '\"'), $value);
    }

    /**
     * @return string
     * user：wangjinxin
     * date：2020/3/12 17:17
     */
    public function getLastId()
    {
        return $this->getConnection()->getPdo()->lastInsertId();
    }

    /**
     * @return int
     * user：wangjinxin
     * date：2020/3/12 17:17
     */
    public function countAffected()
    {
        if (is_object($this->result)) {
            return $this->result->num_rows;
        }
        return 0;
    }

    /**
     * @throws \Exception
     * user：wangjinxin
     * date：2020/3/12 15:30
     */
    public function beginTransaction()
    {
        $this->getConnection()->beginTransaction();
    }

    public function commit()
    {
        $this->getConnection()->commit();
    }

    public function rollback()
    {
        $this->getConnection()->rollBack();
    }

    /**
     * @return Connection
     * user：wangjinxin
     * date：2020/3/12 15:29
     */
    private function getConnection()
    {
        return $this->orm->getConnection($this->connection);
    }
}
