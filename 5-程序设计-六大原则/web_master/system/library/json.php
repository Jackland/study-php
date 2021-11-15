<?php

class Json
{
    public $success;
    public $msg;
    public $error;

    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    public function toJson()
    {
        return json_encode($this);
    }

    public static function fail($msg = 'Operation failed!')
    {
        $json = new Json();
        $json->success = false;
        $json->msg = $msg;
        return $json;
    }

    public static function success($msg = 'Operation success!')
    {
        $json = new Json();
        $json->success = true;
        $json->msg = $msg;
        return $json;
    }

}