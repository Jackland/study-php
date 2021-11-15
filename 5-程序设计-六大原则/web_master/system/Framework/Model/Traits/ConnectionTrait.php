<?php

namespace Framework\Model\Traits;

use Framework\Model\Eloquent\Builder;
use Framework\Model\Eloquent\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as EloquentQueryBuilder;
use InvalidArgumentException;

trait ConnectionTrait
{
    protected static $writeConnection;
    protected static $readConnection;

    /**
     * @param null $connection
     * @return Builder|EloquentBuilder
     */
    public static function on($connection = null)
    {
        // 无修改，仅为增加注释用于提示
        return parent::on($connection);
    }

    /**
     * @return QueryBuilder|EloquentQueryBuilder
     */
    public static function onWriteConnection()
    {
        // 无修改，仅为增加注释用于提示
        return parent::onWriteConnection();
    }

    /**
     * 设置读写的链接
     * @param string $readConnection
     * @param string $writeConnection
     */
    public static function setReadWriteConnections(string $readConnection, string $writeConnection)
    {
        static::$readConnection = $readConnection;
        static::$writeConnection = $writeConnection;
    }

    /**
     * 使用读链接
     * @return Builder|EloquentBuilder
     */
    public static function queryRead()
    {
        if (!static::$readConnection) {
            throw new InvalidArgumentException('必须先配置 EloquentModel::setReadWriteConnections');
        }
        return static::on(static::$readConnection);
    }

    /**
     * 使用写链接
     * @return Builder|EloquentBuilder
     */
    public static function queryWrite()
    {
        if (!static::$writeConnection) {
            throw new InvalidArgumentException('必须先配置 EloquentModel::setReadWriteConnections');
        }
        return static::on(static::$writeConnection);
    }
}
