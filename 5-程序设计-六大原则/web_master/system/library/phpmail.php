<?php
/**
 * @package        OpenCart
 * @author        Daniel Kerr
 * @copyright    Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license        https://opensource.org/licenses/GPL-3.0
 * @link        https://www.opencart.com
 */

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Mail class
 */
class Phpmail
{
    public $from;
    public $from_name;
    public $to;
    public $cc = array();
    public $subject;
    public $body;
    public $files = array();

    /**
     * 目前生产环境将hostname注掉了  关掉了发邮件的功能
     *  $isMoniter为true是监控邮件  不关闭
     * @param bool $isMoniter
     * @return array|void
     */
    public function send($isMoniter = false)
    {
        if (empty($this->to) || empty($this->body)) {
            return;
        }
        $config = configDB();
        $white_list = $config->get('module_wk_communication_white_list');
        //判断是否在白名单内
        if ($white_list && $white_list == 1) {
            $white_list_email = $config->get('module_wk_communication_white_list_email');
            $white_list_email = explode(',', $white_list_email);
            if (!in_array($this->to, $white_list_email)) {
                return array('success' => false, 'msg' => '收件人' . $this->to . '不在白名单内！');
            }
            foreach ($this->cc as $item) {
                if (!in_array($item, $white_list_email)) {
                    return array('success' => false, 'msg' => '收件人' . $item . '不在白名单内！');
                }
            }
        }
        try {
            require_once DIR_SYSTEM . "library/phpmailer/PHPMailer.php";
            require_once DIR_SYSTEM . "library/phpmailer/Exception.php";
            require_once DIR_SYSTEM . "library/phpmailer/SMTP.php";
            require_once DIR_SYSTEM . "library/phpmailer/OAuth.php";
            $mail = new PHPMailer(true);                              // Passing `true` enables exceptions
            //Server settings
            $mail->SMTPDebug = 0;                                 // Enable verbose debug output
            $mail->isSMTP();                                      // Set mailer to use SMTP
            $mail->CharSet = 'UTF-8';
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Host = $isMoniter ? 'smtp.263.net' : $config->get('config_mail_smtp_hostname');  // Specify main and backup SMTP servers;  // Specify main and backup SMTP servers
            $mail->Username = $config->get("config_mail_smtp_username");                 // SMTP username
            $mail->Password = $config->get('config_mail_smtp_password');                           // SMTP password
            $mail->SMTPSecure = 'ssl';                            // Enable TLS encryption, `ssl` also accepted
            $mail->Port = $config->get('config_mail_smtp_port');                                    // TCP port to connect to

            //Recipients
            if (isset($this->from)) {
                $mail->setFrom($this->from);
            } else {
                $mail->setFrom($config->get("config_mail_smtp_username"));
            }
            if (isset($this->from_name)) {
                $mail->FromName = $this->from_name;
            } else {
                $mail->FromName = $config->get('config_name');
            }
            //收件人
            $mail->addAddress($this->to);
            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
            //抄送
            if (!empty($this->cc)) {
                foreach ($this->cc as $each) {
                    $mail->addCC($each);
                }
            }
            //主题
            if (isset($this->subject)) {
                $mail->Subject = $this->subject;
            } else {
                $mail->Subject = 'Communication';
            }
            //邮件正文
            $mail->Body = $this->body;
            if (isset($this->files['name'])) {
                foreach ($this->files['name'] as $key => $value) {
                    if ($this->files['tmp_name'][$key] && $this->files['name'][$key]) {
                        $mail->addAttachment($this->files['tmp_name'][$key], $this->files['name'][$key]);
                    }
                }
            }

            $mail->send();
            return array('success' => true);
        } catch (Exception $e) {
            return array('success' => false, 'msg' => $e->getMessage());
            $this->log->write('站内信邮件发送失败：' . $e);
        }
    }
}
