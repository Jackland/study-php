<?php

/**
 * @property ModelAccountCustomer $model_account_customer
 * @property ModelAccountRegisterApply $model_account_register_apply
 * @property ModelLocalisationCountry $model_localisation_country
 * @property ModelLocalisationZone $model_localisation_zone
 */
class ControllerAccountRegisterApply extends Controller
{
    private $error = array();

    public function index()
    {
        // 判断用户是否登陆
        if ($this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }

        $this->load->language('account/register_apply');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

        $this->load->model('account/customer');
        load()->model('localisation/country');
        if ((request()->isMethod('POST')) && $this->validate()) {
            // 获取IP地址
            $ip              = request()->serverBag->get('REMOTE_ADDR');
            $market          = is_array($this->request->post('market')) ? $this->request->post('market') : '';
            $market_str      = '';
            $market_name_str = '';
            if (isset($this->request->post['market']) && is_array($this->request->post['market'])) {
                sort($market);
                $market_str  = implode(',', $market);
                $countrysKey = $this->model_localisation_country->getCountrysByIds($market_str);
                $market_name = [];
                foreach ($market as $value) {
                    $market_name[] = $countrysKey[$value]['name'];
                }
                $market_name_str = implode(',', $market_name);
            }
            // 插入數據庫
            $this->load->model('account/register_apply');
            $registerData = array(
                'firstname' => $this->request->post['firstname'],
                'lastname' => $this->request->post['lastname'],
                'jobtitle' => $this->request->post['jobtitle'],
                'email' => $this->request->post['email'],
                'primary_timezone' => $this->request->post['primary_timezone'],
                'company' => $this->request->post['company'],
                'tax_id' => $this->request->post['tax_id'],
                'state_incorporation' => $this->request->post['state_incorporation'],
                'year_company' => $this->request->post['year_company'],
                'annual_revenue' => $this->request->post['annual_revenue'],
                'website' => $this->request->post['website'],
                'telephone' => $this->request->post['telephone'],
                'find_type' => $this->request->post['findType'],
                'source' => $this->request->post['source'],
                'street' => $this->request->post['street'],
                'street_2' => $this->request->post['street_2'],
                'city' => $this->request->post['city'],
                'zip' => $this->request->post['zip'],
                'country_id' => $this->request->post['address']['country_id'],
                'zone_id' => $this->request->post['address']['zone_id'] == '' ? null : $this->request->post['address']['zone_id'],
                //'remark' => $this->request->post['remark'],
                'market' => $market_str,
                'market_name'=> $market_name_str,
                'product' => $this->request->post['product'],
                'account' => $this->request->post['account'],
                'ip' => $ip,
                'create_time' => date('Y-m-d H:i:s', time()),
                'create_user_name' => 'system',
                'send' => 0
            );
            $this->model_account_register_apply->addCustomerRegister($registerData);
            $data['success_register_apply'] = true;
        }

        $data['text_account_already'] = sprintf($this->language->get('text_account_already'), $this->url->link('account/login', '', true));

        // 错误警告
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        // First Name
        if (isset($this->error['firstname'])) {
            $data['error_firstname'] = $this->error['firstname'];
        } else {
            $data['error_firstname'] = '';
        }
        // Last Name
        if (isset($this->error['lastname'])) {
            $data['error_lastname'] = $this->error['lastname'];
        } else {
            $data['error_lastname'] = '';
        }
        // Job Title
        if (isset($this->error['jobtitle'])) {
            $data['error_jobtitle'] = $this->error['jobtitle'];
        } else {
            $data['error_jobtitle'] = '';
        }
        // E-mail
        if (isset($this->error['email'])) {
            $data['error_email'] = $this->error['email'];
        } else {
            $data['error_email'] = '';
        }
        // Telephone
        if (isset($this->error['telephone'])) {
            $data['error_telephone'] = $this->error['telephone'];
        } else {
            $data['error_telephone'] = '';
        }
        // Primary Time Zone
        if (isset($this->error['primary_timezone'])) {
            $data['error_primary_timezone'] = $this->error['primary_timezone'];
        } else {
            $data['error_primary_timezone'] = '';
        }
        // Source
        if (isset($this->error['source'])) {
            $data['error_source'] = $this->error['source'];
        } else {
            $data['error_source'] = '';
        }
        // Primary Time Zone
        if (isset($this->error['company'])) {
            $data['error_company'] = $this->error['company'];
        } else {
            $data['error_company'] = '';
        }
        // Tax ID
        if (isset($this->error['tax_id'])) {
            $data['error_tax_id'] = $this->error['tax_id'];
        } else {
            $data['error_tax_id'] = '';
        }
        // State of Incorporation
        if (isset($this->error['state_incorporation'])) {
            $data['error_state_incorporation'] = $this->error['state_incorporation'];
        } else {
            $data['error_state_incorporation'] = '';
        }
        // Year Company was Founded
        if (isset($this->error['year_company'])) {
            $data['error_year_company'] = $this->error['year_company'];
        } else {
            $data['error_year_company'] = '';
        }
        // Annual Online Sales Revenue
        if (isset($this->error['annual_revenue'])) {
            $data['error_annual_revenue'] = $this->error['annual_revenue'];
        } else {
            $data['error_annual_revenue'] = '';
        }
        // Website(s)
        if (isset($this->error['website'])) {
            $data['error_website'] = $this->error['website'];
        } else {
            $data['error_website'] = '';
        }
        // Street
        if (isset($this->error['street'])) {
            $data['error_street']    = $this->error['street'];
            $data['error_address_1'] = $this->error['street'];
        } else {
            $data['error_street']    = '';
            $data['error_address_1'] = '';
        }
        // Website(s)
        if (isset($this->error['street_2'])) {
            $data['error_street_2'] = $this->error['street_2'];
        } else {
            $data['error_street_2'] = '';
        }
        // City
        if (isset($this->error['city'])) {
            $data['error_city'] = $this->error['city'];
        } else {
            $data['error_city'] = '';
        }
        // Zip
        if (isset($this->error['zip'])) {
            $data['error_zip'] = $this->error['zip'];
        } else {
            $data['error_zip'] = '';
        }
        // Zip
        if (isset($this->error['remark'])) {
            $data['error_remark'] = $this->error['remark'];
        } else {
            $data['error_remark'] = '';
        }
        // Country
        if (isset($this->error['country'])) {
            $data['error_country'] = $this->error['country'];
        } else {
            $data['error_country'] = '';
        }
        if (isset($this->error['country'])) {
            $data['error_country'] = $this->error['country'];
        } else {
            $data['error_country'] = '';
        }
        // product
        if (isset($this->error['product'])) {
            $data['error_product'] = $this->error['product'];
        } else {
            $data['error_product'] = '';
        }
        // account
        if (isset($this->error['account'])) {
            $data['error_account'] = $this->error['account'];
        } else {
            $data['error_account'] = '';
        }
        // 提交错误
        if (isset($this->error['confirm'])) {
            $data['error_confirm'] = $this->error['confirm'];
        } else {
            $data['error_confirm'] = '';
        }

        $data['action'] = $this->url->link('account/register_apply', '', true);

        // First Name
        if (isset($this->request->post['firstname'])) {
            $data['firstname'] = $this->request->post['firstname'];
        } else {
            $data['firstname'] = '';
        }
        // Last Name
        if (isset($this->request->post['lastname'])) {
            $data['lastname'] = $this->request->post['lastname'];
        } else {
            $data['lastname'] = '';
        }
        // Job Title
        if (isset($this->request->post['jobtitle'])) {
            $data['jobtitle'] = $this->request->post['jobtitle'];
        } else {
            $data['jobtitle'] = '';
        }
        // E-mail
        if (isset($this->request->post['email'])) {
            $data['email'] = $this->request->post['email'];
        } else {
            $data['email'] = '';
        }
        // Telephone
        if (isset($this->request->post['telephone'])) {
            $data['telephone'] = $this->request->post['telephone'];
        } else {
            $data['telephone'] = '';
        }
        // Primary Time Zone
        if (isset($this->request->post['primary_timezone'])) {
            $data['primary_timezone'] = $this->request->post['primary_timezone'];
        } else {
            $data['primary_timezone'] = '';
        }
        // company
        if (isset($this->request->post['company'])) {
            $data['company'] = $this->request->post['company'];
        } else {
            $data['company'] = '';
        }
        // Tax ID
        if (isset($this->request->post['company'])) {
            $data['tax_id'] = $this->request->post['tax_id'];
        } else {
            $data['tax_id'] = '';
        }
        // State of Incorporation
        if (isset($this->request->post['company'])) {
            $data['state_incorporation'] = $this->request->post['state_incorporation'];
        } else {
            $data['state_incorporation'] = '';
        }
        // Year Company was Founded
        if (isset($this->request->post['year_company'])) {
            $data['year_company'] = $this->request->post['year_company'];
        } else {
            $data['year_company'] = '';
        }
        // Annual Online Sales Revenue(USD)
        if (isset($this->request->post['annual_revenue'])) {
            $data['annual_revenue'] = $this->request->post['annual_revenue'];
        } else {
            $data['annual_revenue'] = '';
        }
        // Website(s)
        if (isset($this->request->post['website'])) {
            $data['website'] = $this->request->post['website'];
        } else {
            $data['website'] = '';
        }
        // Street
        if (isset($this->request->post['street'])) {
            $data['street'] = $this->request->post['street'];
        } else {
            $data['street'] = '';
        }
        // Street_2
        if (isset($this->request->post['street_2'])) {
            $data['street_2'] = $this->request->post['street_2'];
        } else {
            $data['street_2'] = '';
        }
        // Source
        if (isset($this->request->post['source'])) {
            $data['source'] = $this->request->post['source'];
        } else {
            $data['source'] = '';
        }
        // City
        if (isset($this->request->post['city'])) {
            $data['city'] = $this->request->post['city'];
        } else {
            $data['city'] = '';
        }
        // ZipCode
        if (isset($this->request->post['zip'])) {
            $data['zip'] = $this->request->post['zip'];
        } else {
            $data['zip'] = '';
        }
        //// Remark
        //if (isset($this->request->post['remark'])) {
        //    $data['remark'] = $this->request->post['remark'];
        //} else {
        //    $data['remark'] = '';
        //}
        // Country
        if (isset($this->request->post['address']['country_id'])) {
            $data['country_id'] = $this->request->post['address']['country_id'];
        } else {
            $data['country_id'] = '';
        }
        // State/Region/Province
        if (isset($this->request->post['address']['zone_id'])) {
            $data['zone_id'] = $this->request->post['address']['zone_id'];
        } else {
            $data['zone_id'] = '';
        }

        // market
        $data['market'] = [
            ['id' => '223', 'name' => 'United States', 'status' => false],
            ['id' => '222', 'name' => 'United Kingdom', 'status' => false],
            ['id' => '81', 'name' => 'Germany', 'status' => false],
            ['id' => '107', 'name' => 'Japan', 'status' => false],
        ];
        if(isset($this->request->post['market'])){
            $market = $this->request->post['market'];
            sort($market);
            foreach ($data['market'] as $key => $value) {
                if (in_array($value['id'], $market)) {
                    $value['status'] = true;
                }
                $data['market'][$key] = $value;
            }
        }
        $data['marketJson'] = json_encode($data['market']);

        // product
        if (isset($this->request->post['product'])) {
            $data['product'] = $this->request->post['product'];
        } else {
            $data['product'] = '';
        }
        // account
        if (isset($this->request->post['account'])) {
            $data['account'] = $this->request->post['account'];
        } else {
            $data['account'] = '';
        }

        if (isset($this->request->post['confirm'])) {
            $data['confirm'] = $this->request->post['confirm'];
        } else {
            $data['confirm'] = '';
        }
        if (isset($this->request->post['findType'])) {
            $data['findType'] = $this->request->post['findType'];
        } else {
            $data['findType'] = '1';
        }

        $this->load->model('localisation/country');
        $data['countries'] = $this->model_localisation_country->getShowCountryRegister();

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header', ['display_top' => false, 'display_search' => false, 'display_account_info' => false, 'display_menu' => false, 'display_register_header' => true]);



        //$this->response->setOutput($this->load->view('account/register_apply', $data));
        $this->response->setOutput($this->load->view('account/register_apply_new', $data));
    }

    private function validate()
    {
        // FirstName 1~50
        if ((utf8_strlen(trim($this->request->post['firstname'])) < 1) || (utf8_strlen(trim($this->request->post['firstname'])) > 50)) {
            $this->error['firstname'] = $this->language->get('error_firstname');
        }
        // LastName 1~50
        if ((utf8_strlen(trim($this->request->post['lastname'])) < 1) || (utf8_strlen(trim($this->request->post['lastname'])) > 50)) {
            $this->error['lastname'] = $this->language->get('error_lastname');
        }
        //jobtitle
        if ((utf8_strlen(trim($this->request->post['jobtitle'])) < 1) || (utf8_strlen(trim($this->request->post['jobtitle'])) > 50)) {
            $this->error['jobtitle'] = $this->language->get('error_jobtitle');
        }
        // Email <96
        if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
            $this->error['email'] = $this->language->get('error_email');
        }
        // Email Exists
        if ($this->model_account_customer->getTotalCustomersByEmail($this->request->post['email'])) {
            $this->error['warning'] = $this->language->get('error_exists');
        }
        // Telephone
        if ((utf8_strlen($this->request->post['telephone']) < 3) || (utf8_strlen($this->request->post['telephone']) > 50)) {
            $this->error['telephone'] = $this->language->get('error_telephone');
        }
        // Source
        if ((utf8_strlen($_POST['source']) > 2000)) {
            $this->error['source'] = $this->language->get('error_source');
        }
        // Primary Time Zone
        if ((utf8_strlen($this->request->post['primary_timezone']) < 1) || (utf8_strlen($this->request->post['primary_timezone']) > 50)) {
            $this->error['primary_timezone'] = $this->language->get('error_primary_timezone');
        }
        // Company
        if ((utf8_strlen($this->request->post['company']) < 1) || (utf8_strlen($this->request->post['company']) > 100)) {
            $this->error['company'] = $this->language->get('error_company');
        }
        // Tax ID
        if (utf8_strlen($this->request->post['tax_id']) > 100) {
            $this->error['tax_id'] = $this->language->get('error_tax_id');
        }
        // State of Incorporation
        if (utf8_strlen($this->request->post['state_incorporation']) > 100) {
            $this->error['state_incorporation'] = $this->language->get('error_state_incorporation');
        }
        // Year Company was Founded
        if (utf8_strlen($this->request->post['year_company']) > 100) {
            $this->error['year_company'] = $this->language->get('error_year_company');
        }
        // Annual Online Sales Revenue(USD)
        if (utf8_strlen($this->request->post['annual_revenue']) > 100) {
            $this->error['annual_revenue'] = $this->language->get('error_annual_revenue');
        }
        // Website(s)
        if (utf8_strlen($this->request->post['website']) > 100) {
            $this->error['website'] = $this->language->get('error_website');
        }
        // Street
        if ((utf8_strlen($this->request->post['street']) < 1) || (utf8_strlen($this->request->post['street']) > 100)) {
            $this->error['street'] = $this->language->get('error_street');
        }
        // Address Line 2
        if (utf8_strlen($this->request->post['street_2']) > 100) {
            $this->error['street_2'] = $this->language->get('error_street_2');
        }
        // City
        if ((utf8_strlen($this->request->post['city']) < 1) || (utf8_strlen($this->request->post['city']) > 100)) {
            $this->error['city'] = $this->language->get('error_city');
        }
        // Zip
        if ((utf8_strlen($this->request->post['zip']) < 1) || (utf8_strlen($this->request->post['zip']) > 20)) {
            $this->error['zip'] = $this->language->get('error_zip');
        }
        // country
        if (utf8_strlen($this->request->post['address']['country_id'] ?? '') < 1) {
            $this->error['country'] = $this->language->get('error_country');
        }
        // What product ：categories do you offer?
        if (utf8_strlen($_POST['product']) > 2000) {
            $this->error['product'] = $this->language->get('error_product');
        }
        // What are your top eCommerce Account(s)? (Please provide online storefront URLs)
        if (utf8_strlen($_POST['account']) > 2000) {
            $this->error['account'] = $this->language->get('error_account');
        }

        if($this->request->post['findType'] == 1){
            if(utf8_strlen($_POST['source'])>25 || utf8_strlen($_POST['source'])<1){
                $this->error['source'] = $this->language->get('error_source');
            }
        }else if($this->request->post['findType'] == 2){
            if (utf8_strlen($_POST['source']) > 2000) {
                $this->error['source'] = $this->language->get('error_account');
            }
        }

        //如果是页面请求验证，则返回JSON
        //$from_page = $this->request->post['from_page'];
        //if($from_page){
        //    $this->response->ajax([]);
        //}

        return !$this->error;
    }

    public function country()
    {
        $json = array();
        $this->load->model('localisation/country');
        $country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);
        if ($country_info) {
            $this->load->model('localisation/zone');
            $json = array(
                'country_id' => $country_info['country_id'],
                'name' => $country_info['name'],
                'iso_code_2' => $country_info['iso_code_2'],
                'iso_code_3' => $country_info['iso_code_3'],
                'address_format' => $country_info['address_format'],
                'postcode_required' => $country_info['postcode_required'],
                'zone' => $this->model_localisation_zone->getZonesByCountryId($this->request->get['country_id']),
                'status' => $country_info['status']
            );
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
