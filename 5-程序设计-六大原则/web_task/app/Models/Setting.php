<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Setting
 * @package App\Models
 */
class Setting extends Model
{
    protected $table = 'oc_setting';
    protected $connection = 'mysql_proxy';

    /**
     * @param string $key
     * @param mixed $default
     * @return null|array|string
     */
    public static function getConfig($key, $default = null)
    {
        $result = self::query()->where('key', '=', $key)->select('value', 'serialized')->first();
        if (is_null($result)) {
            return $default;
        }
        if (intval($result->serialized)) {
            return json_decode($result->value, true);
        } else {
            return $result->value;
        }
    }

}