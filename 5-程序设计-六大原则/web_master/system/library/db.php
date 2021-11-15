<?php
/**
 * @package        OpenCart
 * @author        Daniel Kerr
 * @copyright    Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license        https://opensource.org/licenses/GPL-3.0
 * @link        https://www.opencart.com
 */

/**
 * DB class
 */
class DB
{
    /**
     * @var \DB\mPDO $adaptor
     */
    private $adaptor;

    /**
     * Constructor
     *
     * @param    string $adaptor
     * @param    string $hostname
     * @param    string $username
     * @param    string $password
     * @param    string $database
     * @param    int $port
     * @throws
     *
     */
    public function __construct($adaptor, $hostname, $username, $password, $database, $port = NULL)
    {
        $class = 'DB\\' . $adaptor;

        if (class_exists($class)) {
            $this->adaptor = new $class($hostname, $username, $password, $database, $port);
        } else {
            throw new \Exception('Error: Could not load database adaptor ' . $adaptor . '!');
        }
    }

    public function connection($name)
    {
        $new = clone $this;

        if (method_exists($new->adaptor, 'setConnection')) {
            $new->adaptor = clone $this->adaptor;
            $new->adaptor->setConnection($name);
        }

        return $new;
    }

    /**
     * @param    string $sql
     * @param    array $params
     * @return   QueryResult | bool
     * @throws
     */
    public function query($sql, $params = array())
    {
        return $this->adaptor->query($sql, $params);
    }

    /**
     * @param $sql
     * @param array $params
     * @return \Generator
     */
    public function cursor($sql, $params = array())
    {
        return $this->adaptor->cursor($sql, $params);
    }

    /**
     *
     *
     * @param    string $value
     *
     * @return    string
     */
    public function escape($value)
    {
        return $this->adaptor->escape($value);
    }

    public function escapeParams(&$params = [])
    {
        foreach ($params as $k => $v) {
            if (!empty($v)) {
                $params[$k] = $this->escape($v);
            }
        }
    }

    /**
     *
     *
     * @return    int
     */
    public function countAffected()
    {
        return $this->adaptor->countAffected();
    }

    /**
     *
     *
     * @return    int
     */
    public function getLastId()
    {
        return $this->adaptor->getLastId();
    }

    /**
     *
     *
     * @return    bool
     */
    public function connected()
    {
        return $this->adaptor->connected();
    }

    public function beginTransaction()
    {
        return $this->adaptor->beginTransaction();
    }

    public function commit()
    {
        return $this->adaptor->commit();
    }

    public function rollback()
    {
        return $this->adaptor->rollback();
    }
}

/**
 * 只用于提醒
 * Class QueryResult
 */
class QueryResult
{
    public $num_rows = 1;
    public $row = [];
    public $rows = [];
}
