<?php

/**
 * @property ModelExtensionModuleWkcontact $model_extension_module_wk_contact
 */
class ControllerAccountCustomerpartnerSendquery extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('account/wk_communication');
        $this->load->model('extension/module/wk_contact');
        if (request()->isMethod('POST') && $this->validate()) {
            try {
                $demo = is_dir(DIR_DOWNLOAD . 'attachment') ? '' : mkdir(DIR_DOWNLOAD . 'attachment');
                $files = array();
                if (isset($this->request->files['file'])) {
                    $files = $this->request->files['file'];
                }
                if (isset($this->request->post['establishContact']) && $this->request->post['establishContact']=='1') {
                    $establishContact = 100;
                } else {
                    $establishContact = null;
                }
                $this->orm->getConnection()->beginTransaction();
                $this->communication->insertQuery(
                    $this->request->post['subject'],
                    $this->request->post['message'],
                    $this->customer->getId(),
                    $this->request->post['customer_id_to'],
                    $files,
                    $establishContact
                );
                $this->orm->getConnection()->commit();
                $this->error['success'] = $this->language->get('text_success');
                unset($this->error['error']);
            } catch (Exception $e) {
                $this->orm->getConnection()->rollBack();
                $this->error['error'][] = $this->language->get("error_message");
                $this->log->write('站内msg发送失败' . $e->getMessage());
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($this->error));
    }

    public function sendSMTPMail()
    {
        if ($this->validate()) {
            $files = array();
            if (isset($this->request->files['file'])) {
                $files = $this->request->files['file'];
            }

            $this->load->model('extension/module/wk_contact');
            foreach (explode(',', $this->request->post['customer_id_to']) as $customer_id_to) {
                $json = $this->model_extension_module_wk_contact->sendMyMail(
                    $this->request->post['subject'],
                    $this->request->post['message'],
                    $customer_id_to,
                    $files
                );
            }
        } else {
            $json = $this->error;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function validate()
    {
        $this->load->language('account/wk_communication');
        $this->error['error'] = array();
        if (empty($this->request->post)) {
            //附件太大导致所有参数为空
            $this->error['error'][] = $this->language->get('error_file_too_big');
            return false;
        }
        if (!isset($this->request->post['customer_id_to']) || !$this->request->post['customer_id_to']) {
            $this->error['error'][] = $this->language->get('error_something_wrong');
        }
        if (!isset($this->request->post['subject']) || !$this->request->post['subject']) {
            $this->error['error'][] = $this->language->get('error_subject_empty');
        }
        if (!isset($this->request->post['message']) || !$this->request->post['message']
            || $this->request->post['message'] == '&lt;p&gt;&lt;br&gt;&lt;/p&gt;') {
            $this->error['error'][] = $this->language->get('error_message_empty');
        }
        return !$this->error['error'];
    }

}
