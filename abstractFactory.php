<?php

/**

* 抽象工厂模式
//---------------------------------------------------------------------------

// 抽象工厂(AbstractFactory)角色：它声明一个创建抽象产品对象的接口。

// 通常以接口或抽象类实现，所有的具体工厂类必须实现这个接口或继承这个类。

//---------------------------------------------------------------------------

// 具体工厂(ConcreteFactory)角色：实现创建产品对象的操作。

// 客户端直接调用这个角色创建产品的实例。这个角色包含有选择合适的产品对象的逻辑。通常使用具体类实现。

//---------------------------------------------------------------------------

// 抽象产品(Abstract Product)角色：声明一类产品的接口。它是工厂方法模式所创建的对象的父类，或它们共同拥有的接口。

//---------------------------------------------------------------------------

// 具体产品(Concrete Product)角色：实现抽象产品角色所定义的接口，定义一个将被相应的具体工厂创建的产品对象。

// 其内部包含了应用程序的业务逻辑。

//---------------------------------------------------------------------------

*/
 

///抽象工厂

interface AnimalFactory{

public function createCat();

public function createDog();

}

 
//黑色动物具体工厂

class BlackAnimalFactory implements AnimalFactory{

function createCat(){

return new BlackCat();

}

function createDog(){

return new BlackDog();

}

 
}

//白色动物具体工厂

class WhiteAnimalFactory implements AnimalFactory{

function createCat(){

return new WhiteCat();

}

function createDog(){

return new WhiteDog();

}

}

 
//抽象产品

interface Cat{

function Voice();

}

interface Dog{

function Voice();

}

 
 
//具体产品

class BlackCat implements Cat {

 
function Voice(){

echo '黑猫喵喵……';

}

}

 
class WhiteCat implements Cat {

 
function Voice(){

echo '白猫喵喵……';

}

}

 
class BlackDog implements Dog {

 
function Voice(){

echo '黑狗汪汪……';

}

}

 
class WhiteDog implements Dog {

 
function Voice(){

echo '白狗汪汪……';

}

}

 
 
//客户端

 
 
class CLient{

public static function main(){

self::run(new BlackAnimalFactory());

self::run(new WhiteAnimalFactory());

}

public static function run(AnimalFactory $AnimalFactory){

 
$cat=$AnimalFactory->createCat();

$cat->Voice();

$dog=$AnimalFactory->createDog();

$dog->Voice();

}

}

CLient::main();