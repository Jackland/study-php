<?php
/**
 * Created by SRP.php.
 * User: fuyunnan
 * Date: 2021/6/12
 * Time: 13:55
 */
/**
 *1)单一职责原则
 *
 * 遵循单一职责的优点：
(1)降低类的复杂度，一个类只负责一项职责。
(2)提高类的可读性，可维护性。
(1)降低变更引起的风险。
 *
 *

 */

/**
 *错误的写法
 */
class animal{
    //呼吸空气
    public function breathe($animal)
    {
        echo  $animal."呼吸新鲜空气<br>";
    }
    
}

/**
 *这时候 鱼是不能直接呼吸空气
 */
(new animal())->breathe('鱼');
(new animal())->breathe('鸟');


/**
 *================修正
 */

//海洋生物
class seaAnimal{

//呼吸空气
    public function breathe($animal)
    {
        echo  $animal."呼吸新鲜水空气<br>";
    }
}


//陆地生物
class landAnimal{
//呼吸空气
    public function breathe($animal)
    {
        echo  $animal."呼吸新鲜空气<br>";
    }

}


/**
 *但是这种拆解 虽然麻烦 但是符合 单一原则
 */
 (new seaAnimal())->breathe('鱼');
 (new landAnimal())->breathe('鸟');


/**
 *还可以这样 但是违背单一原则，但是在项目中实用性更高
 */
class allAnimal{

    public function breathe($animal)
    {
        echo  $animal."呼吸新鲜空气<br>";
    }

    public function breathe2($animal)
    {
        echo  $animal."呼吸新鲜水<br>";
    }
}

(new allAnimal())->breathe('人');
(new allAnimal())->breathe2('鱼');