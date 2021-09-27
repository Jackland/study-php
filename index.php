<?php
$pdo = new PDO('mysql:host=mysql;dbname=admin', 'root', '123456');
$statement = $pdo->query("SELECT * from cate where id=".$_GET['id']);
$row = $statement->fetch(PDO::FETCH_ASSOC);
var_dump($row) ;
echo  1111;


class A{
    public function say()
    {
        echo 'A is  hello';

    }

}

class B{
    private $a;
    public function __construct(A $a)
    {
        $this->a = $a;
    }


    public function main()
    {
        $this->a->say();
    }
}

(new B(new A()))->main();