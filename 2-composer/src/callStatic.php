<?php
/**
 * Created by callStatic.php.
 * User: fuyunnan
 * Date: 2022/2/11
 * Time: 10:23
 */

namespace Acme;

/**
 * Class Test
 * @method static void write($msg, $type = 'info', $context = [])
 *
 * @package Acme
 */
class callStatic
{

    public static function __callStatic(string $name, array $arguments)
    {
        var_dump($arguments);
        var_dump(new callStaticDrive($name));
    }
}


class callStaticDrive
{

    public function __construct($name)
    {
        if ($name == 'write') {
            return self::write();
        }
    }

    /**
     * @return mixed
     */
    public static function write()
    {
        return 'drive-set';
    }
}