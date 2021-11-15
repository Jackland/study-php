<?php

use App\Components\Storage\StorageCloud;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @property ModelCommunicationWkCommunication $model_communication_wk_communication
 */
class ControllerCommunicationWkCommunication extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('communication/wk_communication');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('communication/wk_communication');

        $this->getlist();
    }

    public function getlist()
    {
        $url = '';
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('communication/wk_communication', 'user_token=' . session('user_token'), true)
        );
        $this->document->setTitle($this->language->get('heading_title'));

        if (isset($this->request->get['filter_from'])) {
            $filter_from = $this->request->get['filter_from'];
            $url .= '&filter_from=' . $filter_from;
        } else {
            $filter_from = null;
        }
        if (isset($this->request->get['filter_to'])) {
            $filter_to = $this->request->get['filter_to'];
            $url .= '&filter_to=' . $filter_to;
        } else {
            $filter_to = null;
        }
        if (isset($this->request->get['filter_subject'])) {
            $filter_subject = $this->request->get['filter_subject'];
            $url .= '&filter_subject=' . $filter_subject;
        } else {
            $filter_subject = null;
        }
        if (isset($this->request->get['filter_date'])) {
            $filter_date = $this->request->get['filter_date'];
            $url .= '&filter_date=' . $filter_date;
        } else {
            $filter_date = null;
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
            // $url .= '&page=' . $this->request->get['page'];
        } else {
            $page = 1;
        }

        if (isset($this->session->data['error_warning'])) {
            $data['error_warning'] = session('error_warning');
            $this->session->remove('error_warning');
        } else {
            $data['error_warning'] = '';
        }
        $filterData = array(
            'filter_from' => $filter_from,
            'filter_to' => $filter_to,
            'filter_subject' => $filter_subject,
            'filter_date' => $filter_date,
            'start' => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit' => $this->config->get('config_limit_admin')
        );
        foreach ($filterData as $key => $value) {
            $data[$key] = $value;
        }

        $data['messageses'] = array();
        $this->load->model('communication/wk_communication');
        $data['messages'] = array();
        $data['messageses'] = $this->model_communication_wk_communication->getMessages($filterData);
        $total = $this->model_communication_wk_communication->getTotalMessages($filterData);

        foreach ($data['messageses'] as $message) {
            $data['messages'][] = array(
                'message_id' => $message['message_id'],
                'from' => $message['message_from'],
                'to' => $message['message_to'],
                'subject' => $message['message_subject'],
                'date' => $message['message_date'],
                'action' => $this->url->link('communication/wk_communication/getThreads', 'user_token=' . session('user_token') . '&message_id=' . $message['message_id'], true)
            );
        }
        $data['total_threads'] = array();
        foreach ($data['messageses'] as $message) {
            $data['total_threads'][] = $this->model_communication_wk_communication->countThreads($message['message_id']);
        }

        $data['user_token'] = session('user_token');
        $data['delete'] = $this->url->link('communication/wk_communication/delete', 'user_token=' . session('user_token') . $url, true);
        $pagination = new Pagination();

        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link('communication/wk_communication', 'user_token=' . session('user_token') . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0,
            ((($page - 1) * $this->config->get('config_limit_admin')) > ($total - $this->config->get('config_limit_admin'))) ? $total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')),
            $total,
            ceil($total / $this->config->get('config_limit_admin'))
        );

        $data['heading_title'] = $this->language->get('heading_title');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['user_token'] = session('user_token');
        if (version_compare(VERSION, '2.2.0.0', '>='))
            $this->response->setOutput($this->load->view('communication/wk_communication', $data));
        else
            $this->response->setOutput($this->load->view('communication/wk_communication', $data));
    }

    public function getThreads()
    {
        if (isset($this->request->get['message_id'])) {
            $message_id = $this->request->get['message_id'];
        } else {
            $message_id = null;
        }

        $this->load->language('communication/wk_communication');
        $this->document->setTitle($this->language->get('heading_title'));
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('communication/wk_communication', 'user_token=' . session('user_token'), true)
        );

        $lang = array(
            'text_history'
        );
        foreach ($lang as $value) {
            $data[$value] = $this->language->get($value);
        }
        $extension = $this->config->get('module_wk_communication_type');
        $extensions = explode(',', $extension);
        $data['extension'] = $extensions;
        $data['max'] = $this->config->get('module_wk_communication_max');
        $data['size'] = $this->config->get('module_wk_communication_size');
        $data['size_mb'] = round($data['size'] / 1024, 2) . 'MB';
        $data['type'] = explode(",", $this->config->get('module_wk_communication_type'));
        $data['cancel'] = $this->url->link('communication/wk_communication', 'user_token=' . session('user_token'), true);
        $this->load->model('communication/wk_communication');
        $data['message_info'] = $this->model_communication_wk_communication->getMessageInfo($message_id);
        $thread_info = $this->model_communication_wk_communication->getThreads($message_id);
        $data['thread_info'] = array();
        $data['attachments_info'] = array();
        if (!empty($thread_info))
            foreach ($thread_info as $thread) {
                $data['thread_info'][] = $this->model_communication_wk_communication->getMessageInfo($thread['message_id']);
                $data['attachments_info'][] = $this->model_communication_wk_communication->getAttachment($thread['message_id']);
            }
        //Attachment of the parent message
        $attachment = $this->model_communication_wk_communication->getAttachment($message_id);
        $data['attachments'] = array();

        if (!empty($attachment)) {
            $data['attachments'] = $attachment;
        }
        $user_ids = explode('_',$data['message_info']['user_id']);

        $data['reply'] = array(
            'from' => $data['message_info']['message_from'],
            'to' => $data['message_info']['message_to'],
            'message_from_id' => get_value_or_default($user_ids,0,$this->config->get('system_id')),
            'message_to_id' => get_value_or_default($user_ids,1,$this->config->get('system_id')),
            'both' => $this->language->get("text_both")
        );
        $data['user_token'] = session('user_token');
        $data['heading_title'] = $this->language->get('heading_title');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $sendMsgUrl = $this->url->link('communication/wk_communication/sendMail', '', true) . '&user_token=' . session('user_token') . '&message_id=' . $message_id;
        $sendMailUrl = $this->url->link('communication/wk_communication/sendSMTPMail', '', true) . '&user_token=' . session('user_token');
        $data['action'] = $sendMsgUrl;
        $data['mail_action'] = $sendMailUrl;
        if (isset($this->session->data['errors']) && session('errors')) {
            $data['errors'] = session('errors');
            $this->session->remove('errors');
        }
        if (version_compare(VERSION, '2.2.0.0', '>='))
            $this->response->setOutput($this->load->view('communication/wk_communication_form', $data));
        else
            $this->response->setOutput($this->load->view('communication/wk_communication_form', $data));
    }

    public function sendMail()
    {
        $this->load->language('communication/wk_communication');
        if (request()->isMethod('POST') && $this->validate()) {
            try {
                $demo = is_dir(DIR_DOWNLOAD . 'attachment') ? '' : mkdir(DIR_DOWNLOAD . 'attachment');
                $files = array();
                if (isset($this->request->files['file'])) {
                    $files = $this->request->files['file'];
                }
                $message_from_id = $this->config->get('system_id');
                $message_to_id = $this->request->post['message_to_id'];

                $message_id = $this->communication->insertQuery(
                    $this->request->post['subject'],
                    $this->request->post['message'],
                    $message_from_id,
                    $message_to_id,
                    $files);

                $this->communication->updateReply($message_id, $this->request->post['parent_message']);
                $this->error['success'] = $this->language->get('send_success');
                unset($this->error['error']);
            } catch (Exception $e) {
                $this->log->write('站内信msg发送失败：' . $e);
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($this->error));
    }

    public function sendSMTPMail()
    {
        $this->load->language('communication/wk_communication');
        if ($this->validate()) {
           $json =  $this->sendMyMail();
        }else{
            $json = $this->error;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function sendMyMail()
    {
        $mail = new Phpmail();
        $message_to_id = $this->request->post['message_to_id'];
        list($email) = $this->communication->getEmailAndName($message_to_id);
        $mail->to = $email;
        $mail->body = $this->formatMailMessage();
        if (isset($this->request->files['file'])) {
            $mail->files = $this->request->files['file'];
        }
        return $mail->send();
    }

    public function formatMailMessage()
    {
        $subject = $this->request->post['subject'];
        $subject = html_entity_decode($subject, ENT_QUOTES, 'UTF-8');
        $from = $this->config->get('system_name');
        $date = date('Y-m-d H:i:s', time());
        $message = html_entity_decode(trim($this->request->post['message']), ENT_QUOTES, 'UTF-8');
        $href = $this->url->link('account/wk_communication', '', 'SSL');
        $html = "<br><a href='$href'><h3>You have received an communication from the gigacloudlogistics Giga Cloud.</h3></a><hr>
<table   border='0' cellspacing='0' cellpadding='0' >
<tr><th align='left'>From:</th><td>$from</td></tr>
<tr><th align='left'>Subject:</th><td>$subject</td></tr>
<tr><th align='left'>Date:</th><td>$date</td></tr>
<tr><th align='left'>Message:</th><td></td></tr>
</table><br>
$message";
        return $html;
    }

    public function download()
    {

        $this->load->model('communication/wk_communication');
        if (isset($this->request->get['attachment_id'])) {
            $attachment_id = $this->request->get['attachment_id'];
        } else {
            $attachment_id = 0;
        }

        $download_info = $this->model_communication_wk_communication->getDownload($attachment_id);

        if ($download_info) {
            $file = 'download/attachment/' . $download_info['filename'];
            $mask = basename($download_info['maskname']);

            if (!headers_sent()) {
                if (file_exists(DIR_STORAGE . $file)) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . ($mask ? $mask : basename(DIR_STORAGE . $file)) . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize(DIR_STORAGE . $file));

                    if (ob_get_level()) {
                        ob_end_clean();
                    }

                    readfile(DIR_STORAGE . $file, 'rb');

                    exit();
                } elseif (StorageCloud::storage()->fileExists($file)) {
                    return StorageCloud::storage()->browserDownload($file, $mask ?: $download_info['filename']);
                } else {
                    exit('Error: Could not find file ' . $file . '!');
                }
            } else {
                exit('Error: Headers already sent out!');
            }
        } else {
            $this->response->redirect($this->url->link('account/wk_communication', '', true));
        }
    }

    public function delete()
    {
        $this->load->language('communication/wk_communication');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('communication/wk_communication');
        if (isset($this->request->post['selected'])) {
            foreach ($this->request->post['selected'] as $message_id) {
                $this->model_communication_wk_communication->deleteMessage($message_id);
            }

            session()->set('success', $this->language->get('text_success'));

            $url = '';
            if (isset($this->request->get['filter_from'])) {
                $url .= '&filter_from=' . urlencode(html_entity_decode($this->request->get['filter_from'], ENT_QUOTES, 'UTF-8'));
            }
            if (isset($this->request->get['filter_to'])) {
                $url .= '&filter_to=' . urlencode(html_entity_decode($this->request->get['filter_to'], ENT_QUOTES, 'UTF-8'));
            }
            if (isset($this->request->get['filter_subject'])) {
                $url .= '&filter_subject=' . urlencode(html_entity_decode($this->request->get['filter_subject'], ENT_QUOTES, 'UTF-8'));
            }
            if (isset($this->request->get['filter_date'])) {
                $url .= '&filter_date=' . urlencode(html_entity_decode($this->request->get['filter_date'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['page'])) {
                $url .= '&page=' . urlencode(html_entity_decode($this->request->get['page'], ENT_QUOTES, 'UTF-8'));
            }

            $this->response->redirect($this->url->link('communication/wk_communication', 'user_token=' . session('user_token') . $url, true));
        }

        $this->getList();
    }

    public function upload()
    {

        if ($this->request->files['file']['name']) {
            if (!$this->request->files['file']['error']) {
                $name = md5(time() . rand(100, 200));
                $ext = pathinfo($this->request->files['file']['name'], PATHINFO_EXTENSION);
                $filename = $name . ($ext ? '.' . $ext : '');
                return StorageCloud::image()->writeFile(request()->filesBag->get('file'), 'attachment', $filename);
            } else {
                echo $message = 'Ooops!  Your upload triggered the following error:  ' . $this->request->files['file']['error'];
            }
        }
    }

    public function validate()
    {
        $this->load->language('communication/wk_communication');
        $this->error['error'] = array();
        if (empty($this->request->post)) {
            //附件太大导致所有参数为空
            $this->error['error'][] = $this->language->get('error_file_too_big');
            return false;
        }
        if (!isset($this->request->post['message_to_id']) || !$this->request->post['message_to_id']) {
            $this->error['error'][] = $this->language->get('error_something_wrong');
        }
        if (!isset($this->request->post['subject']) || !$this->request->post['subject']) {
            $this->error['error'][] = $this->language->get('error_subject_empty');
        }
        if (!isset($this->request->post['message']) || !$this->request->post['message']) {
            $this->error['error'][] = $this->language->get('error_message_empty');
        }
        return !$this->error['error'];
    }

    public function userMsg()
    {
        $this->load->language('communication/wk_communication');
        $data = array();
        $data['delete'] = $this->url->link('communication/wk_communication/delete', 'user_token=' . session('user_token'), true);
        $url = '';
        if (isset($this->request->get['filter_date_to'])) {
            $filter_date_to = $this->request->get['filter_date_to'];
            $url .= '&filter_date_to=' . $filter_date_to;
        } else {
            $filter_date_to = null;
        }
        if (isset($this->request->get['filter_date_from'])) {
            $filter_date_from = $this->request->get['filter_date_from'];
            $url .= '&filter_date_from=' . $filter_date_from;
        } else {
            $filter_date_from = null;
        }
        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }
        $url .= '&page=' . $page;

        $limit = $this->config->get('config_limit_admin');
        $filter_data = array(
            'filter_date_from' => $filter_date_from,
            'filter_date_to' => $filter_date_to,
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
        );
        foreach ($filter_data as $k => $v) {
            $data[$k] = $v;
        }
        $data['messages'] = $this->queryUserMsg($filter_data);
        $total = $this->countUserMsg($filter_data);
        $data['user_token'] = session('user_token');


        $pagination = new Pagination();

        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('communication/wk_communication/userMsg', 'user_token=' . session('user_token') . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($total) ? (($page - 1) * $limit) + 1 : 0,
            ((($page - 1) * $limit) > ($total - $limit)) ? $total : ((($page - 1) * $limit) + $limit),
            $total,
            ceil($total / $limit)
        );
        $this->response->setOutput($this->load->view('communication/wk_communication_usermsg', $data));
    }

    public function countUserMsg($filter_data = array())
    {
        $sql = $this->createUserMsgSql($filter_data);
        return $this->db->query('select count(*) as total from (' . $sql . ') t ')->row['total'];
    }

    public function queryUserMsg($filter_data = array())
    {
        $sql = $this->createUserMsgSql($filter_data);

        $sort_data = array(
            'name',
            'c.email',
            'customer_group',
            'c.status',
            'c.ip',
            'c.date_added'
        );

        if (isset($filter_data['sort']) && in_array($filter_data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $filter_data['sort'];
        } else {
            $sql .= " ORDER BY m.message_id";
        }

        if (isset($filter_data['order']) && ($filter_data['order'] == 'ASC')) {
            $sql .= " ASC";
        } else {
            $sql .= " DESC";
        }

        if (isset($filter_data['start']) || isset($filter_data['limit'])) {
            if ($filter_data['start'] < 0) {
                $filter_data['start'] = 0;
            }

            if ($filter_data['limit'] < 1) {
                $filter_data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$filter_data['start'] . "," . (int)$filter_data['limit'];
        }

        $user_msg_query = $this->db->query($sql)->rows;
        $messages = array();
        foreach ($user_msg_query as $item) {
            $message = array(
                'user_name' => $item['user_name'],
                'message_id' => $item['message_id'],
                'body' => $item['message_body'],
                'email' => $item['message_from'],
                'date' => $item['message_date']);
            if ($item['user_id'] !== '0') {
                $LoginInInfo = $this->db->query("SELECT c.nickname,c.user_number,c2c.screenname FROM oc_customer c LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id = c.customer_id
            WHERE c.customer_id = " . $item['user_id'])->row;
                if (empty($LoginInInfo)) {
                    $message['account'] = "none";
                } else {
                    if ($this->chkIsPartner($item['user_id'])) {
                        $message['account'] = $LoginInInfo['screenname'] . '(' . $LoginInInfo['user_number'] . '-Seller)';
                    } else {
                        $message['account'] = $LoginInInfo['nickname'] . '(' . $LoginInInfo['user_number'] . '-Buyer)';
                    }
                }

            }else{
                $message['account'] = '';
            }
            $messages[] = $message;
        }
        return $messages;
    }

    public function chkIsPartner($user_id)
    {

        $sql = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_customer WHERE customer_id = '" . (int)$user_id . "'");

        if (count($sql->row) && $sql->row['is_partner'] == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $filter_data
     * @return array
     */
    public function createUserMsgSql($filter_data)
    {
        $sql = "select m.message_id,m.message_body,m.message_date,m.message_from,p.user_id,p.user_name,p.is_read
 from oc_wk_communication_message m
left join  oc_wk_communication_placeholder p
 on p.message_id=m.message_id
 where p.is_contact_us=1 and p.placeholder_id=2  ";
        if (!empty($filter_data['filter_ids'])) {
            $sql.= ' and m.message_id in ('.implode(',',$filter_data['filter_ids']).') ';
        }
        if (!empty($filter_data['filter_date_from'])) {
            $sql .= " and  message_date>='{$filter_data['filter_date_from']}'";
        }
        if (!empty($filter_data['filter_date_to'])) {
            $sql .= " and  message_date<='{$filter_data['filter_date_to']} 23:59:59'";
        }
        return $sql;
    }

    public function exportCsv()
    {
        $json = $this->createCsv($this->request->post['ids']);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function createCsv($ids)
    {
        try {
            $filename =  'User Messages '.date('Ymd').'.csv';
            $dir = DIR_DOWNLOAD . 'userMsgCsv/';
            $msg_rows = $this->queryUserMsg(array('filter_ids'=>$ids));
            $data = array();
            foreach ($msg_rows as $row){
                $data[] = array($row['account'],$row['user_name'],$row['email'],$row['body'],$row['date']);
            }

            $header = ['Account', 'Name', 'E-Mail', 'Enquiry', 'Date'];
            $header = implode(",", $header);
            $header = iconv('UTF-8', 'GBK//IGNORE', $header);
            $header = explode(",", $header);
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            $fp = fopen($dir . $filename, 'w+');
            fputcsv($fp, $header);
            foreach ($data as $row) {
                $tmp = array();
                foreach ($row as $it) {
                    $it = iconv('UTF-8', 'GBK//IGNORE', $it);
                    $tmp[] = $it;
                }
                fputcsv($fp, $tmp);
            }
            unset($data);
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        } catch (Exception $e) {
            $this->log->write('导出用户消息CSV失败'.$e->getMessage());
            return array('success'=>false,'msg'=>'Operate Failed!');
        }
        return array('success'=>true,'path'=>HTTPS_CATALOG.'storage/download/userMsgCsv/'.$filename);
    }
}
