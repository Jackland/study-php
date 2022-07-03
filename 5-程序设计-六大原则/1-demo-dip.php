<?php
/**
 * Created by 1-demo-dip.php.
 * 依赖反反转的demo
 * User: fuyunnan
 * Date: 2021/12/1
 * Time: 15:48
 */

interface EmailSender
{
    public function send();
}

class EmailSendByQq implements EmailSender
{
    public function send()
    {
        echo "send qq";
    }
}

class EmailSendBy163 implements EmailSender
{
    public function send()
    {
        echo "send 163";
    }
}

class User
{
    protected $emailSenderClass;

    //通过EmailSendByQq和EmailSendBy163类，我们提炼出一个interface接口，让User类register方法依赖于interface接口的对象看起来更合适
    public function __construct(EmailSender $emailSenderObject)
    {
        $this->emailSenderClass = $emailSenderObject;
    }

    public function register()
    {
        $this->emailSenderClass->send();
    }
}
$user = new User(new EmailSendBy163);
$user->register();
