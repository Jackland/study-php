<?php

/**
 * Class ControllerCustomerCustomer
 * @property ModelCustomerCustomer $model_customer_customer
 * @property ModelCustomerCustomerGroup $model_customer_customer_group
 */

use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Customer\CustomerAgentOperation;
use App\Helper\CustomerHelper;
use App\Helper\GigaOnsiteHelper;
use App\Models\Customer\Country;
use App\Models\Customer\Customer;
use App\Repositories\Customer\TelephoneCountryCodeRepository;
use App\Services\Seller\SellerService;

class ControllerCustomerCustomer extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('customer/customer');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customer/customer');

        $this->getList();
    }

    public function add()
    {
        $this->load->language('customer/customer');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customer/customer');
        $user_number = $this->getUniqueUserNumber();
        if ((request()->isMethod('POST')) && !isset($user_number)) {
            $this->error['warning'] = $this->language->get('error_user_number');
        }
        if ((request()->isMethod('POST')) && $this->validateForm() && isset($user_number)) {
            $post_data = $this->request->post;
            $firstname = $post_data['firstname'];
            $lastname = $post_data['lastname'];
            $default_nickname = mb_substr($firstname, 0, 1, 'utf-8') . mb_substr($lastname, 0, 1, 'utf-8');
            $post_data['nickname'] = $default_nickname;
            $post_data['user_number'] = $user_number;
            // Marketplace Code Starts Here
            if ($this->config->get('module_marketplace_status') && isset($this->request->get['create_seller'])) {
                $seller_id = $this->model_customer_customer->addCustomer($post_data, $create_seller = 1);
                //seller创建成功 并且是giga onsite分组，需要同步给onsite
                if ($seller_id && $post_data['accounting_type_id'] == CustomerAccountingType::GIGA_ONSIDE) {
                    db('oc_customer_exts')->updateOrInsert(['customer_id' => $seller_id], ['agent_operation' => $post_data['agent_operation']]);
                    app(CustomerHelper::class)->postAccountInfoToOnSite(customer()->getId());
                }else{
                    db('oc_customer_exts')->updateOrInsert(['customer_id' => $seller_id], ['agent_operation' => CustomerAgentOperation::DEFAULT]);
                }

                session()->set('success', $this->language->get('text_success'));

                $url = '';

                if (isset($this->request->get['filter_name'])) {
                    $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
                }

                if (isset($this->request->get['filter_email'])) {
                    $url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
                }

                if (isset($this->request->get['filter_customer_group_id'])) {
                    $url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
                }

                if (isset($this->request->get['filter_status'])) {
                    $url .= '&filter_status=' . $this->request->get['filter_status'];
                }

                if (isset($this->request->get['filter_approved'])) {
                    $url .= '&filter_approved=' . $this->request->get['filter_approved'];
                }

                if (isset($this->request->get['filter_ip'])) {
                    $url .= '&filter_ip=' . $this->request->get['filter_ip'];
                }

                if (isset($this->request->get['filter_date_added'])) {
                    $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
                }

                if (isset($this->request->get['sort'])) {
                    $url .= '&sort=' . $this->request->get['sort'];
                }

                if (isset($this->request->get['order'])) {
                    $url .= '&order=' . $this->request->get['order'];
                }

                if (isset($this->request->get['page'])) {
                    $url .= '&page=' . $this->request->get['page'];
                }

                if (isset($this->request->get['view_all'])) {
                    $url .= '&view_all=' . $this->request->get['view_all'];
                }

                $this->response->redirect($this->url->link('customerpartner/partner', 'user_token=' . session('user_token') . $url, true));

            } else { // Marketplace Code Ends Here

                $buyer_id = $this->model_customer_customer->addCustomer($post_data);
                db('oc_customer_exts')->updateOrInsert(['customer_id' => $buyer_id], ['agent_operation' => CustomerAgentOperation::DEFAULT]);
                // Marketplace Code Starts Here
            }
            // Marketplace Code Ends Here


            session()->set('success', $this->language->get('text_success'));

            $url = '';

            if (isset($this->request->get['filter_name'])) {
                $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_email'])) {
                $url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_customer_group_id'])) {
                $url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
            }

            if (isset($this->request->get['filter_status'])) {
                $url .= '&filter_status=' . $this->request->get['filter_status'];
            }

            if (isset($this->request->get['filter_ip'])) {
                $url .= '&filter_ip=' . $this->request->get['filter_ip'];
            }

            if (isset($this->request->get['filter_date_added'])) {
                $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
            }

            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }

            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }

            $this->response->redirect($this->url->link('customer/customer', 'user_token=' . session('user_token') . $url, true));
        }

        $this->getForm();
    }

    public function edit()
    {
        $this->load->language('customer/customer');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customer/customer');

        if ((request()->isMethod('POST')) && $this->validateForm()) {
            $post = $this->request->post();
            $isPartner = $this->request->get('is_partner', 0); //页面通过get提交此参数
            $customerId = $this->request->get('customer_id', 0);
            $accountingTypeId = $post['accounting_type_id'] ?? 0;
            $originCustomerInfo = [];
            if ($isPartner == 1) {
                //查询原始邮箱
                $originCustomerInfo = obj2array(Customer::find($customerId));
            }
            $this->model_customer_customer->editCustomer($customerId, $post);
            //giga onsite分组卖家，需要同步到giga onsite系统
            if ($originCustomerInfo) {
                if ($originCustomerInfo['accounting_type'] == CustomerAccountingType::GIGA_ONSIDE || $accountingTypeId == CustomerAccountingType::GIGA_ONSIDE) {

                    if($accountingTypeId != CustomerAccountingType::GIGA_ONSIDE){
                        $agent_operation = CustomerAgentOperation::DEFAULT;
                    }else{
                        $agent_operation = $post['agent_operation'];
                    }
                    db('oc_customer_exts')->updateOrInsert(['customer_id' => $customerId], ['agent_operation' => $agent_operation]);
                    app(CustomerHelper::class)->postAccountInfoToOnSite($customerId);
                }else{
                    db('oc_customer_exts')->updateOrInsert(['customer_id' => $customerId], ['agent_operation' => CustomerAgentOperation::DEFAULT]);
                }
            }

            // 33643 onsite供应商的产品运费按照Seller的报价计算并且展示给buyer 涉及账号类型变更=>更新运费
            if ($originCustomerInfo && $originCustomerInfo['accounting_type'] == CustomerAccountingType::GIGA_ONSIDE && $accountingTypeId != CustomerAccountingType::GIGA_ONSIDE) {
                GigaOnsiteHelper::sendProductsFreightRequest($customerId, $originCustomerInfo['accounting_type'], $accountingTypeId);
            }

            //7787 同步到seller开户表
            if ($this->request->get('is_partner', 0) && $this->request->get('customer_id', 0)) {
                $accountSaveInfo = [
                    'country_id' => $this->request->post('country_id', 0),
                    'email' => $this->request->post('email', ''),
                    'password' => $this->request->post('password', 0),
                    'account_type' => $this->request->post('accounting_type_id', 0),
                ];
                $accountSaveInfo = array_filter($accountSaveInfo);
                if ($accountSaveInfo) {
                    app(SellerService::class)->updateSellerAccountApplyInfo($this->request->get('customer_id', 0), $accountSaveInfo);
                }
            }

            session()->set('success', $this->language->get('text_success'));

            $url = '';

            if (isset($this->request->get['filter_name'])) {
                $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_email'])) {
                $url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_customer_group_id'])) {
                $url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
            }

            if (isset($this->request->get['filter_status'])) {
                $url .= '&filter_status=' . $this->request->get['filter_status'];
            }

            if (isset($this->request->get['filter_ip'])) {
                $url .= '&filter_ip=' . $this->request->get['filter_ip'];
            }

            if (isset($this->request->get['filter_date_added'])) {
                $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
            }

            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }

            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }

            $this->response->redirect($this->url->link('customer/customer', 'user_token=' . session('user_token') . $url, true));
        }

        $this->getForm();
    }

    /**
     * 禁止删除
     * @throws Exception
     */
    public function delete()
    {
        if (true) {
            return;
        }
        $this->load->language('customer/customer');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customer/customer');

        if (isset($this->request->post['selected']) && $this->validateDelete()) {
            foreach ($this->request->post['selected'] as $customer_id) {
                $this->model_customer_customer->deleteCustomer($customer_id);
            }

            session()->set('success', $this->language->get('text_success'));

            $url = '';

            if (isset($this->request->get['filter_name'])) {
                $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_email'])) {
                $url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_customer_group_id'])) {
                $url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
            }

            if (isset($this->request->get['filter_status'])) {
                $url .= '&filter_status=' . $this->request->get['filter_status'];
            }

            if (isset($this->request->get['filter_ip'])) {
                $url .= '&filter_ip=' . $this->request->get['filter_ip'];
            }

            if (isset($this->request->get['filter_date_added'])) {
                $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
            }

            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }

            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }

            $this->response->redirect($this->url->link('customer/customer', 'user_token=' . session('user_token') . $url, true));
        }

        $this->getList();
    }

    public function unlock()
    {
        $this->load->language('customer/customer');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customer/customer');

        if (isset($this->request->get['email']) && $this->validateUnlock()) {
            $this->model_customer_customer->deleteLoginAttempts($this->request->get['email']);

            session()->set('success', $this->language->get('text_success'));

            $url = '';

            if (isset($this->request->get['filter_name'])) {
                $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_email'])) {
                $url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_customer_group_id'])) {
                $url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
            }

            if (isset($this->request->get['filter_status'])) {
                $url .= '&filter_status=' . $this->request->get['filter_status'];
            }

            if (isset($this->request->get['filter_ip'])) {
                $url .= '&filter_ip=' . $this->request->get['filter_ip'];
            }

            if (isset($this->request->get['filter_date_added'])) {
                $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
            }

            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }

            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }

            $this->response->redirect($this->url->link('customer/customer', 'user_token=' . session('user_token') . $url, true));
        }

        $this->getList();
    }

    protected function getList()
    {
        if (isset($this->request->get['filter_name'])) {
            $filter_name = $this->request->get['filter_name'];
        } else {
            $filter_name = '';
        }

        if (isset($this->request->get['filter_nickname'])) {
            $filter_nickname = $this->request->get['filter_nickname'];
        } else {
            $filter_nickname = '';
        }

        if (isset($this->request->get['filter_email'])) {
            $filter_email = $this->request->get['filter_email'];
        } else {
            $filter_email = '';
        }

        if (isset($this->request->get['filter_customer_group_id'])) {
            $filter_customer_group_id = $this->request->get['filter_customer_group_id'];
        } else {
            $filter_customer_group_id = '';
        }

        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = '';
        }

        if (isset($this->request->get['filter_ip'])) {
            $filter_ip = $this->request->get['filter_ip'];
        } else {
            $filter_ip = '';
        }

        if (isset($this->request->get['filter_date_added'])) {
            $filter_date_added = $this->request->get['filter_date_added'];
        } else {
            $filter_date_added = '';
        }

        if (isset($this->request->get['filter_screenname'])) {
            $filter_screenname = $this->request->get['filter_screenname'];
        } else {
            $filter_screenname = '';
        }

        if (isset($this->request->get['filter_is_partner'])) {
            $filter_is_partner = $this->request->get['filter_is_partner'];
        } else {
            $filter_is_partner = '';
        }

        if (isset($this->request->get['filter_country_id'])) {
            $filter_country_id = $this->request->get['filter_country_id'];
        } else {
            $filter_country_id = '';
        }
        if (isset($this->request->get['filter_accounting_type_id'])) {
            $filter_accounting_type_id = $this->request->get['filter_accounting_type_id'];
        } else {
            $filter_accounting_type_id = '';
        }

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'name';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $url = '';
        $page_limit = get_value_or_default($this->request->request, 'page_limit', $this->config->get('config_limit_admin'));

        if (isset($this->request->get['filter_nickname'])) {
            $url .= '&filter_nickname=' . urlencode(html_entity_decode($this->request->get['filter_nickname'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_email'])) {
            $url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_customer_group_id'])) {
            $url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_ip'])) {
            $url .= '&filter_ip=' . $this->request->get['filter_ip'];
        }

        if (isset($this->request->get['filter_date_added'])) {
            $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
        }

        if (isset($this->request->get['filter_screenname'])) {
            $url .= '&filter_screenname=' . $this->request->get['filter_screenname'];
        }

        if (isset($this->request->get['filter_is_partner'])) {
            $url .= '&filter_is_partner=' . $this->request->get['filter_is_partner'];
        }

        if (isset($this->request->get['filter_country_id'])) {
            $url .= '&filter_country_id=' . $this->request->get['filter_country_id'];
        }

        if (isset($this->request->get['filter_accounting_type_id'])) {
            $url .= '&filter_accounting_type_id=' . $this->request->get['filter_accounting_type_id'];
        }

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('customer/customer', 'user_token=' . session('user_token') . $url, true)
        );


        $data['countrys'] = [
            ['country_id' => '223', 'country_name' => 'US'],
            ['country_id' => '81', 'country_name' => 'DE'],
            ['country_id' => '222', 'country_name' => 'UK'],
            ['country_id' => '107', 'country_name' => 'JP'],
        ];

        $data['add'] = $this->url->link('customer/customer/add', 'user_token=' . session('user_token') . $url, true);
        $data['delete'] = $this->url->link('customer/customer/delete', 'user_token=' . session('user_token') . $url, true);

        $this->load->model('setting/store');
        $this->load->model('customer/customer_group');

        $stores = $this->model_setting_store->getStores();

        $data['customers'] = array();

        $filter_data = array(
            'filter_nickname' => trim($filter_nickname),
            'filter_name' => trim($filter_name),
            'filter_email' => trim($filter_email),
            'filter_customer_group_id' => $filter_customer_group_id,
            'filter_status' => $filter_status,
            'filter_date_added' => $filter_date_added,
            'filter_ip' => trim($filter_ip),
            'filter_screenname' => trim($filter_screenname),
            'filter_is_partner' => $filter_is_partner,
            'filter_country_id' => $filter_country_id,
            'filter_accounting_type_id' => $filter_accounting_type_id,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $page_limit,
            'limit' => $page_limit
        );

        $customer_total = $this->model_customer_customer->getTotalCustomers($filter_data);

        $results = $this->model_customer_customer->getCustomers($filter_data);

        $accounting_types = $this->model_customer_customer_group->getCustomerAccountingType();

        $num = $filter_data['start'] ? $filter_data['start'] + 1 : 1;
        foreach ($results as $result) {
            $login_info = $this->model_customer_customer->getTotalLoginAttempts($result['email']);

            if ($login_info && $login_info['total'] >= $this->config->get('config_login_attempts')) {
                $unlock = $this->url->link('customer/customer/unlock', 'user_token=' . session('user_token') . '&email=' . $result['email'] . $url, true);
            } else {
                $unlock = '';
            }

            $store_data = array();

            $store_data[] = array(
                'name' => $this->config->get('config_name'),
                'href' => $this->url->link('customer/customer/login', 'user_token=' . session('user_token') . '&customer_id=' . $result['customer_id'] . '&store_id=0', true)
            );

            foreach ($stores as $store) {
                $store_data[] = array(
                    'name' => $store['name'],
                    'href' => $this->url->link('customer/customer/login', 'user_token=' . session('user_token') . '&customer_id=' . $result['customer_id'] . '&store_id=' . $result['store_id'], true)
                );
            }

            $user_attribute = '';
            foreach ($accounting_types as $acc) {
                if ($acc['type_id'] == $result['accounting_type']) {
                    $user_attribute = $acc['type_name'];
                    break;
                }
            }

            $data['customers'][] = array(
                'num' => $num++,
                'customer_id' => $result['customer_id'],
                'nickname' => $result['nickname'],
                'name' => $result['name'],
                'is_partner' => $result['is_partner'],
                'email' => $result['email'],
                'customer_group' => $result['customer_group'],
                'status' => ($result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled')),
                'ip' => $result['ip'],
                'screenname' => $result['screenname'],
                'country_name' => $result['country_name'],
                'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
                'unlock' => $unlock,
                'store' => $store_data,
                'user_attribute' => $user_attribute,
                'edit' => $this->url->link('customer/customer/edit', 'user_token=' . session('user_token') . '&customer_id=' . $result['customer_id'] . '&is_partner=' . $result['is_partner'] . $url, true)
            );
        }

        $data['user_token'] = session('user_token');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');

            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }

        if (isset($this->request->post['selected'])) {
            $data['selected'] = (array)$this->request->post['selected'];
        } else {
            $data['selected'] = array();
        }

        $url = '';

        if (isset($this->request->get['filter_nickname'])) {
            $url .= '&filter_nickname=' . urlencode(html_entity_decode($this->request->get['filter_nickname'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_email'])) {
            $url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_customer_group_id'])) {
            $url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_ip'])) {
            $url .= '&filter_ip=' . $this->request->get['filter_ip'];
        }

        if (isset($this->request->get['filter_date_added'])) {
            $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
        }

        if (isset($this->request->get['filter_screenname'])) {
            $url .= '&filter_screenname=' . $this->request->get['filter_screenname'];
        }

        if (isset($this->request->get['filter_is_partner'])) {
            $url .= '&filter_is_partner=' . $this->request->get['filter_is_partner'];
        }

        if (isset($this->request->get['filter_country_id'])) {
            $url .= '&filter_country_id=' . $this->request->get['filter_country_id'];
        }

        if (isset($this->request->get['filter_accounting_type_id'])) {
            $url .= '&filter_accounting_type_id=' . $this->request->get['filter_accounting_type_id'];
        }

        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['sort_nickname'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&sort=nickname' . $url, true);
        $data['sort_name'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&sort=name' . $url, true);
        $data['sort_is_partner'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&sort=ctc.is_partner' . $url, true);
        $data['sort_email'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&sort=c.email' . $url, true);
        $data['sort_customer_group'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&sort=customer_group' . $url, true);
        $data['sort_status'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&sort=c.status' . $url, true);
        $data['sort_ip'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&sort=c.ip' . $url, true);
        $data['sort_date_added'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&sort=c.date_added' . $url, true);
        $data['sort_screenname'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&sort=ctc.screenname' . $url, true);
        $data['sort_country'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&sort=c.country_id' . $url, true);

        $url = '';

        if (isset($this->request->get['filter_nickname'])) {
            $url .= '&filter_nickname=' . urlencode(html_entity_decode($this->request->get['filter_nickname'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_email'])) {
            $url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_customer_group_id'])) {
            $url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_ip'])) {
            $url .= '&filter_ip=' . $this->request->get['filter_ip'];
        }

        if (isset($this->request->get['filter_date_added'])) {
            $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
        }

        if (isset($this->request->get['filter_screenname'])) {
            $url .= '&filter_screenname=' . $this->request->get['filter_screenname'];
        }

        if (isset($this->request->get['filter_is_partner'])) {
            $url .= '&filter_is_partner=' . $this->request->get['filter_is_partner'];
        }

        if (isset($this->request->get['filter_country_id'])) {
            $url .= '&filter_country_id=' . $this->request->get['filter_country_id'];
        }

        if (isset($this->request->get['filter_accounting_type_id'])) {
            $url .= '&filter_accounting_type_id=' . $this->request->get['filter_accounting_type_id'];
        }

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        $pagination = new Pagination();
        $pagination->total = $customer_total;
        $pagination->page = $page;
        $pagination->limit = $page_limit;
        $pagination->url = $this->url->link('customer/customer', 'user_token=' . session('user_token') . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($customer_total) ? (($page - 1) * $page_limit) + 1 : 0,
            ((($page - 1) * $page_limit) > ($customer_total - $page_limit)) ? $customer_total : ((($page - 1) * $page_limit) + $page_limit), $customer_total, ceil($customer_total / $page_limit));

        $data['filter_nickname'] = $filter_nickname;
        $data['filter_name'] = $filter_name;
        $data['filter_email'] = $filter_email;
        $data['filter_customer_group_id'] = $filter_customer_group_id;
        $data['filter_status'] = $filter_status;
        $data['filter_ip'] = $filter_ip;
        $data['filter_date_added'] = $filter_date_added;
        $data['filter_screenname'] = $filter_screenname;
        $data['filter_is_partner'] = $filter_is_partner;
        $data['filter_country_id'] = $filter_country_id;
        $data['filter_accounting_type_id'] = $filter_accounting_type_id;

        $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
        $data['accounting_types'] = $accounting_types;

        $data['sort'] = $sort;
        $data['order'] = $order;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('customer/customer_list', $data));
    }

    protected function getForm()
    {
        $data['text_form'] = !isset($this->request->get['customer_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
        // add by lilei buyer 和 seller 对应关系
        if (isset($this->request->get['is_partner'])) {
            if ($this->request->get['is_partner'] == 0) {
                // 请求者为buyer 页面显示Sellers
                $data['text_seller_or_buyer'] = $this->language->get('text_sellers');
            } else {
                // 请求者为seller 页面显示Buyers
                $data['text_seller_or_buyer'] = $this->language->get('text_buyers');
            }
            $data['is_partner'] = $this->request->get['is_partner'];
        }

        $data['user_token'] = session('user_token');

        if (isset($this->request->get['customer_id'])) {
            $data['customer_id'] = $this->request->get['customer_id'];
        } else {
            $data['customer_id'] = 0;
        }

        if (isset($this->request->get['country_id'])) {
            $data['country_id'] = $this->request->get['country_id'];
        } else {
            $data['country_id'] = 0;
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['firstname'])) {
            $data['error_firstname'] = $this->error['firstname'];
        } else {
            $data['error_firstname'] = '';
        }

        if (isset($this->error['lastname'])) {
            $data['error_lastname'] = $this->error['lastname'];
        } else {
            $data['error_lastname'] = '';
        }

        if (isset($this->error['email'])) {
            $data['error_email'] = $this->error['email'];
        } else {
            $data['error_email'] = '';
        }

        if (isset($this->error['telephone_country_code_id'])) {
            $data['error_telephone_country_code_id'] = $this->error['telephone_country_code_id'];
        } else {
            $data['error_telephone_country_code_id'] = '';
        }

        if (isset($this->error['telephone'])) {
            $data['error_telephone'] = $this->error['telephone'];
        } else {
            $data['error_telephone'] = '';
        }

        if (isset($this->error['country'])) {
            $data['error_country'] = $this->error['country'];
        } else {
            $data['error_country'] = '';
        }

        if (isset($this->error['cheque'])) {
            $data['error_cheque'] = $this->error['cheque'];
        } else {
            $data['error_cheque'] = '';
        }

        if (isset($this->error['paypal'])) {
            $data['error_paypal'] = $this->error['paypal'];
        } else {
            $data['error_paypal'] = '';
        }

        if (isset($this->error['bank_account_name'])) {
            $data['error_bank_account_name'] = $this->error['bank_account_name'];
        } else {
            $data['error_bank_account_name'] = '';
        }

        if (isset($this->error['bank_account_number'])) {
            $data['error_bank_account_number'] = $this->error['bank_account_number'];
        } else {
            $data['error_bank_account_number'] = '';
        }

        if (isset($this->error['password'])) {
            $data['error_password'] = $this->error['password'];
        } else {
            $data['error_password'] = '';
        }

        if (isset($this->error['confirm'])) {
            $data['error_confirm'] = $this->error['confirm'];
        } else {
            $data['error_confirm'] = '';
        }

        if (isset($this->error['custom_field'])) {
            $data['error_custom_field'] = $this->error['custom_field'];
        } else {
            $data['error_custom_field'] = array();
        }

        if (isset($this->error['address'])) {
            $data['error_address'] = $this->error['address'];
        } else {
            $data['error_address'] = array();
        }

        $url = '';

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_email'])) {
            $url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_customer_group_id'])) {
            $url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_ip'])) {
            $url .= '&filter_ip=' . $this->request->get['filter_ip'];
        }

        if (isset($this->request->get['filter_date_added'])) {
            $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
        }

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('customer/customer', 'user_token=' . session('user_token') . $url, true)
        );

        if (!isset($this->request->get['customer_id'])) {

            // Marketplace Code Starts Here
            if ($this->config->get('module_marketplace_status') && isset($this->request->get['create_seller'])) {

                if (isset($this->request->get['filter_name'])) {
                    $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
                }

                if (isset($this->request->get['filter_email'])) {
                    $url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
                }

                if (isset($this->request->get['filter_customer_group_id'])) {
                    $url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
                }

                if (isset($this->request->get['filter_status'])) {
                    $url .= '&filter_status=' . $this->request->get['filter_status'];
                }

                if (isset($this->request->get['filter_approved'])) {
                    $url .= '&filter_approved=' . $this->request->get['filter_approved'];
                }

                if (isset($this->request->get['filter_ip'])) {
                    $url .= '&filter_ip=' . $this->request->get['filter_ip'];
                }

                if (isset($this->request->get['filter_date_added'])) {
                    $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
                }

                if (isset($this->request->get['sort'])) {
                    $url .= '&sort=' . $this->request->get['sort'];
                }

                if (isset($this->request->get['order'])) {
                    $url .= '&order=' . $this->request->get['order'];
                }

                if (isset($this->request->get['page'])) {
                    $url .= '&page=' . $this->request->get['page'];
                }

                if (isset($this->request->get['view_all'])) {
                    $url .= '&view_all=' . $this->request->get['view_all'];
                }

                $data['action'] = $this->url->link('customer/customer/add', 'user_token=' . session('user_token') . $url . '&create_seller', true);
            } else {  // Marketplace Code Ends Here


                $data['action'] = $this->url->link('customer/customer/add', 'user_token=' . session('user_token') . $url, true);

                // Marketplace Code Starts Here
            }
            // Marketplace Code Ends Here


        } else {
            $data['action'] = $this->url->link('customer/customer/edit', 'user_token=' . session('user_token') . '&customer_id=' . $this->request->get['customer_id'] . '&is_partner=' . $this->request->get['is_partner'] . $url, true);
        }

        $data['cancel'] = $this->url->link('customer/customer', 'user_token=' . session('user_token') . $url, true);

        if (isset($this->request->get['customer_id']) && (!request()->isMethod('POST'))) {
            $customer_info = $this->model_customer_customer->getCustomer($this->request->get['customer_id']);
        }


        $this->load->model('customer/customer_group');

        $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
        $data['accounting_types'] = $this->model_customer_customer_group->getCustomerAccountingType();

        if (isset($this->request->post['customer_group_id'])) {
            $data['customer_group_id'] = $this->request->post['customer_group_id'];
        } elseif (!empty($customer_info)) {
            $data['customer_group_id'] = $customer_info['customer_group_id'];
        } else {
            $data['customer_group_id'] = $this->config->get('config_customer_group_id');
        }

        if (isset($this->request->post['accounting_type_id'])) {
            $data['accounting_type_id'] = $this->request->post['accounting_type_id'];
        } elseif (!empty($customer_info)) {
            $data['accounting_type_id'] = $customer_info['accounting_type'];
        } else {
            $data['accounting_type_id'] = $this->config->get('config_customer_accounting_type_id');
        }

        // 获取 agent_operation的值
        if(request()->post('agent_operation')){
            $data['agent_operation'] = request()->post('agent_operation');
        }else{
            if($data['accounting_type_id'] == CustomerAccountingType::GIGA_ONSIDE){
                $data['agent_operation'] = db('oc_customer_exts')
                    ->where('customer_id',request('customer_id'))
                    ->value('agent_operation') ?? CustomerAgentOperation::INACTIVE;
            }else{
                $data['agent_operation'] = CustomerAgentOperation::DEFAULT;
            }

        }

        if (isset($this->request->post['firstname'])) {
            $data['firstname'] = $this->request->post['firstname'];
        } elseif (!empty($customer_info)) {
            $data['firstname'] = $customer_info['firstname'];
        } else {
            $data['firstname'] = '';
        }

        if (isset($this->request->post['lastname'])) {
            $data['lastname'] = $this->request->post['lastname'];
        } elseif (!empty($customer_info)) {
            $data['lastname'] = $customer_info['lastname'];
        } else {
            $data['lastname'] = '';
        }

        if (isset($this->request->post['email'])) {
            $data['email'] = $this->request->post['email'];
        } elseif (!empty($customer_info)) {
            $data['email'] = $customer_info['email'];
        } else {
            $data['email'] = '';
        }

        $data['telephone_country_code_options'] = app(TelephoneCountryCodeRepository::class)->getSelectOptions();
        if (isset($this->request->post['telephone_country_code_id'])) {
            $data['telephone_country_code_id'] = $this->request->post['telephone_country_code_id'];
        } elseif (!empty($customer_info)) {
            $data['telephone_country_code_id'] = $customer_info['telephone_country_code_id'];
        } else {
            $data['telephone_country_code_id'] = '';
        }

        if (isset($this->request->post['telephone'])) {
            $data['telephone'] = $this->request->post['telephone'];
        } elseif (!empty($customer_info)) {
            $data['telephone'] = $customer_info['telephone'];
        } else {
            $data['telephone'] = '';
        }

        if (isset($this->request->post['country_id'])) {
            $data['country_id'] = $this->request->post['country_id'];
        } elseif (!empty($customer_info)) {
            $data['country_id'] = $customer_info['country_id'];
        } else {
            $data['country_id'] = '';
        }
        if ($data['customer_id'] && $data['country_id']) {
            $data['country_name'] = Country::find($data['country_id'])->name;
        }

        // Custom Fields
        $this->load->model('customer/custom_field');

        $data['custom_fields'] = array();

        $filter_data = array(
            'sort' => 'cf.sort_order',
            'order' => 'ASC'
        );

        $custom_fields = $this->model_customer_custom_field->getCustomFields($filter_data);

        foreach ($custom_fields as $custom_field) {
            $data['custom_fields'][] = array(
                'custom_field_id' => $custom_field['custom_field_id'],
                'custom_field_value' => $this->model_customer_custom_field->getCustomFieldValues($custom_field['custom_field_id']),
                'name' => $custom_field['name'],
                'value' => $custom_field['value'],
                'type' => $custom_field['type'],
                'location' => $custom_field['location'],
                'sort_order' => $custom_field['sort_order']
            );
        }

        if (isset($this->request->post['custom_field'])) {
            $data['account_custom_field'] = $this->request->post['custom_field'];
        } elseif (!empty($customer_info)) {
            $data['account_custom_field'] = json_decode($customer_info['custom_field'], true);
        } else {
            $data['account_custom_field'] = array();
        }

        if (isset($this->request->post['newsletter'])) {
            $data['newsletter'] = $this->request->post['newsletter'];
        } elseif (!empty($customer_info)) {
            $data['newsletter'] = $customer_info['newsletter'];
        } else {
            $data['newsletter'] = '';
        }

        if (isset($this->request->post['status'])) {
            $data['status'] = $this->request->post['status'];
        } elseif (!empty($customer_info)) {
            $data['status'] = $customer_info['status'];
        } else {
            $data['status'] = true;
        }

        if (isset($this->request->post['safe'])) {
            $data['safe'] = $this->request->post['safe'];
        } elseif (!empty($customer_info)) {
            $data['safe'] = $customer_info['safe'];
        } else {
            $data['safe'] = 0;
        }
        //12905 B2B增加Buyer录入各个平台映射的功能
        if (isset($this->request->post['trusteeship'])) {
            $data['trusteeship'] = $this->request->post['trusteeship'];
        } elseif (!empty($customer_info)) {
            $data['trusteeship'] = $customer_info['trusteeship'];
        } else {
            $data['trusteeship'] = 0;
        }

        if (isset($this->request->post['password'])) {
            $data['password'] = $this->request->post['password'];
        } else {
            $data['password'] = '';
        }

        if (isset($this->request->post['confirm'])) {
            $data['confirm'] = $this->request->post['confirm'];
        } else {
            $data['confirm'] = '';
        }

        $this->load->model('localisation/country');

        $data['countries'] = $this->model_localisation_country->getShowCountries();

        if (isset($this->request->post['address'])) {
            $data['addresses'] = $this->request->post['address'];
        } elseif (isset($this->request->get['customer_id'])) {
            $data['addresses'] = $this->model_customer_customer->getAddresses($this->request->get['customer_id']);
        } else {
            $data['addresses'] = array();
        }

        if (isset($this->request->post['address_id'])) {
            $data['address_id'] = $this->request->post['address_id'];
        } elseif (!empty($customer_info)) {
            $data['address_id'] = $customer_info['address_id'];
        } else {
            $data['address_id'] = '';
        }

        // Affliate
        if (isset($this->request->get['customer_id']) && (!request()->isMethod('POST'))) {
            $affiliate_info = $this->model_customer_customer->getAffiliate($this->request->get['customer_id']);
        }

        if (isset($this->request->post['affiliate'])) {
            $data['affiliate'] = $this->request->post['affiliate'];
        } elseif (!empty($affiliate_info)) {
            $data['affiliate'] = $affiliate_info['status'];
        } else {
            $data['affiliate'] = '';
        }

        if (isset($this->request->post['company'])) {
            $data['company'] = $this->request->post['company'];
        } elseif (!empty($affiliate_info)) {
            $data['company'] = $affiliate_info['company'];
        } else {
            $data['company'] = '';
        }

        if (isset($this->request->post['website'])) {
            $data['website'] = $this->request->post['website'];
        } elseif (!empty($affiliate_info)) {
            $data['website'] = $affiliate_info['website'];
        } else {
            $data['website'] = '';
        }

        if (isset($this->request->post['tracking'])) {
            $data['tracking'] = $this->request->post['tracking'];
        } elseif (!empty($affiliate_info)) {
            $data['tracking'] = $affiliate_info['tracking'];
        } else {
            $data['tracking'] = '';
        }

        if (isset($this->request->post['commission'])) {
            $data['commission'] = $this->request->post['commission'];
        } elseif (!empty($affiliate_info)) {
            $data['commission'] = $affiliate_info['commission'];
        } else {
            $data['commission'] = $this->config->get('config_affiliate_commission');
        }

        if (isset($this->request->post['tax'])) {
            $data['tax'] = $this->request->post['tax'];
        } elseif (!empty($affiliate_info)) {
            $data['tax'] = $affiliate_info['tax'];
        } else {
            $data['tax'] = '';
        }

        if (isset($this->request->post['payment'])) {
            $data['payment'] = $this->request->post['payment'];
        } elseif (!empty($affiliate_info)) {
            $data['payment'] = $affiliate_info['payment'];
        } else {
            $data['payment'] = 'cheque';
        }

        if (isset($this->request->post['cheque'])) {
            $data['cheque'] = $this->request->post['cheque'];
        } elseif (!empty($affiliate_info)) {
            $data['cheque'] = $affiliate_info['cheque'];
        } else {
            $data['cheque'] = '';
        }

        if (isset($this->request->post['paypal'])) {
            $data['paypal'] = $this->request->post['paypal'];
        } elseif (!empty($affiliate_info)) {
            $data['paypal'] = $affiliate_info['paypal'];
        } else {
            $data['paypal'] = '';
        }

        if (isset($this->request->post['bank_name'])) {
            $data['bank_name'] = $this->request->post['bank_name'];
        } elseif (!empty($affiliate_info)) {
            $data['bank_name'] = $affiliate_info['bank_name'];
        } else {
            $data['bank_name'] = '';
        }

        if (isset($this->request->post['bank_branch_number'])) {
            $data['bank_branch_number'] = $this->request->post['bank_branch_number'];
        } elseif (!empty($affiliate_info)) {
            $data['bank_branch_number'] = $affiliate_info['bank_branch_number'];
        } else {
            $data['bank_branch_number'] = '';
        }

        if (isset($this->request->post['bank_swift_code'])) {
            $data['bank_swift_code'] = $this->request->post['bank_swift_code'];
        } elseif (!empty($affiliate_info)) {
            $data['bank_swift_code'] = $affiliate_info['bank_swift_code'];
        } else {
            $data['bank_swift_code'] = '';
        }

        if (isset($this->request->post['bank_account_name'])) {
            $data['bank_account_name'] = $this->request->post['bank_account_name'];
        } elseif (!empty($affiliate_info)) {
            $data['bank_account_name'] = $affiliate_info['bank_account_name'];
        } else {
            $data['bank_account_name'] = '';
        }

        if (isset($this->request->post['bank_account_number'])) {
            $data['bank_account_number'] = $this->request->post['bank_account_number'];
        } elseif (!empty($affiliate_info)) {
            $data['bank_account_number'] = $affiliate_info['bank_account_number'];
        } else {
            $data['bank_account_number'] = '';
        }

        if (isset($this->request->post['custom_field'])) {
            $data['affiliate_custom_field'] = $this->request->post['custom_field'];
        } elseif (!empty($affiliate_info)) {
            $data['affiliate_custom_field'] = json_decode($affiliate_info['custom_field'], true);
        } else {
            $data['affiliate_custom_field'] = array();
        }

        $data['is_seller'] = 0;
        if(isset($this->request->get['create_seller']) || request('is_partner') == 1){
           $data['is_seller'] = 1;
        }

        $data['giga_onsite'] = CustomerAccountingType::GIGA_ONSIDE;
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('customer/customer_form', $data));
    }

    protected function validateForm()
    {
        if (!$this->user->hasPermission('modify', 'customer/customer')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ((utf8_strlen($this->request->post['firstname']) < 1) || (utf8_strlen(trim($this->request->post['firstname'])) > 32)) {
            $this->error['firstname'] = $this->language->get('error_firstname');
        }

        if ((utf8_strlen($this->request->post['lastname']) < 1) || (utf8_strlen(trim($this->request->post['lastname'])) > 32)) {
            $this->error['lastname'] = $this->language->get('error_lastname');
        }

        if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
            $this->error['email'] = $this->language->get('error_email');
        }

        if (!isset($this->request->post['country_id']) || empty($this->request->post['country_id'])) {
            $this->error['country'] = $this->language->get('error_country');
        }

        $customer_info = $this->model_customer_customer->getCustomerByEmail($this->request->post['email']);

        if (!isset($this->request->get['customer_id'])) {
            if ($customer_info) {
                $this->error['warning'] = $this->language->get('error_exists');
            }
        } else {
            if ($customer_info && ($this->request->get['customer_id'] != $customer_info['customer_id'])) {
                $this->error['warning'] = $this->language->get('error_exists');
            }
        }

        if (!isset($this->request->post['telephone_country_code_id']) || empty($this->request->post['telephone_country_code_id'])) {
            $this->error['telephone_country_code_id'] = $this->language->get('error_telephone_country_code_id');
        }

        if ((utf8_strlen($this->request->post['telephone']) < 3) || (utf8_strlen($this->request->post['telephone']) > 32)) {
            $this->error['telephone'] = $this->language->get('error_telephone');
        }

        // Custom field validation
        $this->load->model('customer/custom_field');

        $custom_fields = $this->model_customer_custom_field->getCustomFields(array('filter_customer_group_id' => $this->request->post['customer_group_id']));

        foreach ($custom_fields as $custom_field) {
            if (($custom_field['location'] == 'account') && $custom_field['required'] && empty($this->request->post['custom_field'][$custom_field['custom_field_id']])) {
                $this->error['custom_field'][$custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
            } elseif (($custom_field['location'] == 'account') && ($custom_field['type'] == 'text') && !empty($custom_field['validation']) && !filter_var($this->request->post['custom_field'][$custom_field['custom_field_id']], FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $custom_field['validation'])))) {
                $this->error['custom_field'][$custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
            }
        }

        if ($this->request->post['password'] || (!isset($this->request->get['customer_id']))) {
            if ((utf8_strlen(html_entity_decode($this->request->post['password'], ENT_QUOTES, 'UTF-8')) < 4) || (utf8_strlen(html_entity_decode($this->request->post['password'], ENT_QUOTES, 'UTF-8')) > 40)) {
                $this->error['password'] = $this->language->get('error_password');
            }

            if ($this->request->post['password'] != $this->request->post['confirm']) {
                $this->error['confirm'] = $this->language->get('error_confirm');
            }
        }

        if (isset($this->request->post['address'])) {
            foreach ($this->request->post['address'] as $key => $value) {
                if ((utf8_strlen($value['firstname']) < 1) || (utf8_strlen($value['firstname']) > 32)) {
                    $this->error['address'][$key]['firstname'] = $this->language->get('error_firstname');
                }

                if ((utf8_strlen($value['lastname']) < 1) || (utf8_strlen($value['lastname']) > 32)) {
                    $this->error['address'][$key]['lastname'] = $this->language->get('error_lastname');
                }

                if ((utf8_strlen($value['address_1']) < 3) || (utf8_strlen($value['address_1']) > 128)) {
                    $this->error['address'][$key]['address_1'] = $this->language->get('error_address_1');
                }

                if ((utf8_strlen($value['city']) < 2) || (utf8_strlen($value['city']) > 128)) {
                    $this->error['address'][$key]['city'] = $this->language->get('error_city');
                }

                $this->load->model('localisation/country');

                $country_info = $this->model_localisation_country->getCountry($value['country_id']);

                if ($country_info && $country_info['postcode_required'] && (utf8_strlen($value['postcode']) < 2 || utf8_strlen($value['postcode']) > 10)) {
                    $this->error['address'][$key]['postcode'] = $this->language->get('error_postcode');
                }

                if ($value['country_id'] == '') {
                    $this->error['address'][$key]['country'] = $this->language->get('error_country');
                }

                if (!isset($value['zone_id']) || $value['zone_id'] == '') {
                    $this->error['address'][$key]['zone'] = $this->language->get('error_zone');
                }

                foreach ($custom_fields as $custom_field) {
                    if (($custom_field['location'] == 'address') && $custom_field['required'] && empty($value['custom_field'][$custom_field['custom_field_id']])) {
                        $this->error['address'][$key]['custom_field'][$custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
                    } elseif (($custom_field['location'] == 'address') && ($custom_field['type'] == 'text') && !empty($custom_field['validation']) && !filter_var($value['custom_field'][$custom_field['custom_field_id']], FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $custom_field['validation'])))) {
                        $this->error['address'][$key]['custom_field'][$custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
                    }
                }
            }
        }

        if ($this->request->post['affiliate']) {
            if ($this->request->post['payment'] == 'cheque') {
                if ($this->request->post['cheque'] == '') {
                    $this->error['cheque'] = $this->language->get('error_cheque');
                }
            } elseif ($this->request->post['payment'] == 'paypal') {
                if ((utf8_strlen($this->request->post['paypal']) > 96) || !filter_var($this->request->post['paypal'], FILTER_VALIDATE_EMAIL)) {
                    $this->error['paypal'] = $this->language->get('error_paypal');
                }
            } elseif ($this->request->post['payment'] == 'bank') {
                if ($this->request->post['bank_account_name'] == '') {
                    $this->error['bank_account_name'] = $this->language->get('error_bank_account_name');
                }

                if ($this->request->post['bank_account_number'] == '') {
                    $this->error['bank_account_number'] = $this->language->get('error_bank_account_number');
                }
            }

            if (!$this->request->post['tracking']) {
                $this->error['tracking'] = $this->language->get('error_tracking');
            }

            $affiliate_info = $this->model_customer_customer->getAffliateByTracking($this->request->post['tracking']);

            if (!isset($this->request->get['customer_id'])) {
                if ($affiliate_info) {
                    $this->error['tracking'] = $this->language->get('error_tracking_exists');
                }
            } else {
                if ($affiliate_info && ($this->request->get['customer_id'] != $affiliate_info['customer_id'])) {
                    $this->error['tracking'] = $this->language->get('error_tracking_exists');
                }
            }

            foreach ($custom_fields as $custom_field) {
                if (($custom_field['location'] == 'affiliate') && $custom_field['required'] && empty($this->request->post['custom_field'][$custom_field['custom_field_id']])) {
                    $this->error['custom_field'][$custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
                } elseif (($custom_field['location'] == 'affiliate') && ($custom_field['type'] == 'text') && !empty($custom_field['validation']) && !filter_var($this->request->post['custom_field'][$custom_field['custom_field_id']], FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $custom_field['validation'])))) {
                    $this->error['custom_field'][$custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
                }
            }
        }

        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        return !$this->error;
    }

    protected function validateDelete()
    {
        if (!$this->user->hasPermission('modify', 'customer/customer')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    protected function validateUnlock()
    {
        if (!$this->user->hasPermission('modify', 'customer/customer')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    public function login()
    {
        if (isset($this->request->get['customer_id'])) {
            $customer_id = $this->request->get['customer_id'];
        } else {
            $customer_id = 0;
        }

        $this->load->model('customer/customer');

        $customer_info = $this->model_customer_customer->getCustomer($customer_id);

        if ($customer_info) {
            // Create token to login with
            $token = token(64);

            $this->model_customer_customer->editToken($customer_id, $token);

            if (isset($this->request->get['store_id'])) {
                $store_id = $this->request->get['store_id'];
            } else {
                $store_id = 0;
            }

            $this->load->model('setting/store');

            $store_info = $this->model_setting_store->getStore($store_id);

            if ($store_info) {
                $this->response->redirect($store_info['url'] . 'index.php?route=account/login&token=' . $token);
            } else {
                $this->response->redirect(HTTP_CATALOG . 'index.php?route=account/login&token=' . $token);
            }
        } else {
            $this->load->language('error/not_found');

            $this->document->setTitle($this->language->get('heading_title'));

            $data['breadcrumbs'] = array();

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
            );

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('error/not_found', 'user_token=' . session('user_token'), true)
            );

            $data['header'] = $this->load->controller('common/header');
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['footer'] = $this->load->controller('common/footer');

            $this->response->setOutput($this->load->view('error/not_found', $data));
        }
    }

    public function history()
    {
        $this->load->language('customer/customer');

        $this->load->model('customer/customer');

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $data['histories'] = array();

        $results = $this->model_customer_customer->getHistories($this->request->get['customer_id'], ($page - 1) * 10, 10);

        foreach ($results as $result) {
            $data['histories'][] = array(
                'comment' => $result['comment'],
                'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added']))
            );
        }

        $history_total = $this->model_customer_customer->getTotalHistories($this->request->get['customer_id']);

        $pagination = new Pagination();
        $pagination->total = $history_total;
        $pagination->page = $page;
        $pagination->limit = 10;
        $pagination->url = $this->url->link('customer/customer/history', 'user_token=' . session('user_token') . '&customer_id=' . $this->request->get['customer_id'] . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($history_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($history_total - 10)) ? $history_total : ((($page - 1) * 10) + 10), $history_total, ceil($history_total / 10));

        $this->response->setOutput($this->load->view('customer/customer_history', $data));
    }

    public function addHistory()
    {
        $this->load->language('customer/customer');

        $json = array();

        if (!$this->user->hasPermission('modify', 'customer/customer')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('customer/customer');

            $this->model_customer_customer->addHistory($this->request->get['customer_id'], $this->request->post['comment']);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function transaction()
    {
        $this->load->language('customer/customer');

        $this->load->model('customer/customer');

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $data['transactions'] = array();

        $results = $this->model_customer_customer->getTransactions($this->request->get['customer_id'], ($page - 1) * 10, 10);

        foreach ($results as $result) {
            $data['transactions'][] = array(
                'amount' => $this->currency->format($result['amount'], $this->config->get('config_currency')),
                'description' => $result['description'],
                'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added']))
            );
        }

        $data['balance'] = $this->currency->format($this->model_customer_customer->getTransactionTotal($this->request->get['customer_id']), $this->config->get('config_currency'));

        $transaction_total = $this->model_customer_customer->getTotalTransactions($this->request->get['customer_id']);

        $pagination = new Pagination();
        $pagination->total = $transaction_total;
        $pagination->page = $page;
        $pagination->limit = 10;
        $pagination->url = $this->url->link('customer/customer/transaction', 'user_token=' . session('user_token') . '&customer_id=' . $this->request->get['customer_id'] . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($transaction_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($transaction_total - 10)) ? $transaction_total : ((($page - 1) * 10) + 10), $transaction_total, ceil($transaction_total / 10));

        $this->response->setOutput($this->load->view('customer/customer_transaction', $data));
    }

    public function addTransaction()
    {
        $this->load->language('customer/customer');

        $json = array();

        if (!$this->user->hasPermission('modify', 'customer/customer')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('customer/customer');

            $this->model_customer_customer->addTransaction($this->request->get['customer_id'], $this->request->post['description'], $this->request->post['amount']);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function reward()
    {
        $this->load->language('customer/customer');

        $this->load->model('customer/customer');

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $data['rewards'] = array();

        $results = $this->model_customer_customer->getRewards($this->request->get['customer_id'], ($page - 1) * 10, 10);

        foreach ($results as $result) {
            $data['rewards'][] = array(
                'points' => $result['points'],
                'description' => $result['description'],
                'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added']))
            );
        }

        $data['balance'] = $this->model_customer_customer->getRewardTotal($this->request->get['customer_id']);

        $reward_total = $this->model_customer_customer->getTotalRewards($this->request->get['customer_id']);

        $pagination = new Pagination();
        $pagination->total = $reward_total;
        $pagination->page = $page;
        $pagination->limit = 10;
        $pagination->url = $this->url->link('customer/customer/reward', 'user_token=' . session('user_token') . '&customer_id=' . $this->request->get['customer_id'] . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($reward_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($reward_total - 10)) ? $reward_total : ((($page - 1) * 10) + 10), $reward_total, ceil($reward_total / 10));

        $this->response->setOutput($this->load->view('customer/customer_reward', $data));
    }

    public function addReward()
    {
        $this->load->language('customer/customer');

        $json = array();

        if (!$this->user->hasPermission('modify', 'customer/customer')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('customer/customer');

            $this->model_customer_customer->addReward($this->request->get['customer_id'], $this->request->post['description'], $this->request->post['points']);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function ip()
    {
        $this->load->language('customer/customer');

        $this->load->model('customer/customer');

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $data['ips'] = array();

        $results = $this->model_customer_customer->getIps($this->request->get['customer_id'], ($page - 1) * 10, 10);

        foreach ($results as $result) {
            $data['ips'][] = array(
                'ip' => $result['ip'],
                'total' => $this->model_customer_customer->getTotalCustomersByIp($result['ip']),
                'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
                'filter_ip' => $this->url->link('customer/customer', 'user_token=' . session('user_token') . '&filter_ip=' . $result['ip'], true)
            );
        }

        $ip_total = $this->model_customer_customer->getTotalIps($this->request->get['customer_id']);

        $pagination = new Pagination();
        $pagination->total = $ip_total;
        $pagination->page = $page;
        $pagination->limit = 10;
        $pagination->url = $this->url->link('customer/customer/ip', 'user_token=' . session('user_token') . '&customer_id=' . $this->request->get['customer_id'] . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($ip_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($ip_total - 10)) ? $ip_total : ((($page - 1) * 10) + 10), $ip_total, ceil($ip_total / 10));

        $this->response->setOutput($this->load->view('customer/customer_ip', $data));
    }

    /**
     * add by lilei
     * Buyer和Seller的管理页面
     */
    public function sellerOrBuyer()
    {
        // 加载国际化
        $this->load->language('customer/customer');
        // 加载Model
        $this->load->model('customer/customer');
        // 是否是Seller
        $isPartner = $this->request->get['is_partner'] ?: 0;
        // Customer Id
        $customerId = $this->request->get['customer_id'];
        $tableData = array();
        // 获取排序字段和方式
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            if ($isPartner == 1) {
                $sort = 'cc.`firstname`';
            } else {
                $sort = 'sc.`firstname`';
            }
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }
        // 初始化URL
        $url = "";
        // 查询过滤条件
        if (isset($this->request->get['input_customer_name'])) {
            $filter_customer_name = $this->request->get['input_customer_name'];
            $url .= "&input_customer_name=" . $filter_customer_name;
            $data['input_bos_customer_name'] = $filter_customer_name;
        } else {
            $filter_customer_name = "";
            $data['input_bos_customer_name'] = "";
        }

        if (isset($this->request->get['input_email'])) {
            $filter_email = $this->request->get['input_email'];
            $url .= "&input_email=" . $filter_email;
            $data['input_bos_email'] = $filter_email;
        } else {
            $filter_email = "";
            $data['input_bos_email'] = "";
        }

        /* 分页 */
        // 第几页
        if (isset($this->request->get['page_num'])) {
            $page_num = $this->request->get['page_num'];
        } else {
            $page_num = 1;
        }
        $url .= "&page_num=" . $page_num;
        $data['page_num'] = $page_num;
        // 每页显示数目
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 20;
        }
        $url .= "&page_limit=" . $page_limit;
        $data['page_limit'] = $page_limit;

        $filter_data = array(
            "sort" => $sort,
            "order" => $order,
            "filter_customer_name" => $filter_customer_name,
            "filter_email" => $filter_email,
            "page_num" => $page_num,
            "page_limit" => $page_limit
        );

        $filter_other_data = array();
        if ($isPartner) {
            // Seller, 展示其合作的Buyer
            $buyers = $this->model_customer_customer->getBuyersByCustomerId($customerId, $filter_data);
            $customerTotal = $this->model_customer_customer->getBuyersTotalByCustomerId($customerId, $filter_data);
            $data['customer_total'] = $customerTotal;
            $total_pages = ceil($customerTotal / $page_limit);
            $data['total_pages'] = $total_pages;
            $num = (($page_num - 1) * $page_limit) + 1;
            $data['results'] = sprintf($this->language->get('text_pagination'), ($customerTotal) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($customerTotal - $page_limit)) ? $customerTotal : ((($page_num - 1) * $page_limit) + $page_limit), $customerTotal, $total_pages);
            foreach ($buyers->rows as $buyer) {
                $tableData[] = array(
                    "num" => $num++,
                    "id" => $buyer['id'],
                    "buyer_name" => $buyer['buyer_name'],
                    "buyer_email" => $buyer['buyer_email'],
                    "buy_status" => $buyer['buy_status'] == null ? 0 : $buyer['buy_status'],
                    "price_status" => $buyer['price_status'] == null ? 0 : $buyer['price_status'],
                    "discount" => $buyer['discount'],
                    "coop_status_seller" => $buyer['seller_control_status'] == null ? 0 : $buyer['seller_control_status'],
                    "coop_status_buyer" => $buyer['buyer_control_status'] == null ? 0 : $buyer['buyer_control_status']
                );
            }
            // 排序字段
            $data['sort'] = $sort;
            // 排序
            $data['order'] = $order;
            if ($order == "ASC") {
                $order = "DESC";
            } else {
                $order = "ASC";
            }

            /* 获取未添加合作关系的Buyer */
            $data["entry_customer_email"] = $this->language->get('text_buyer_email');
            //展示所有同国别未绑定seller，实际下方的other_limit、other_num在前端已经无意义，防止后续页面又变更回分页模式，暂时保留代码。No.12769 addby chenxiang 20190326
            $other_buyer_total = $this->model_customer_customer->getTotalBuyersNotAssociateForThisSeller($customerId, $filter_other_data);

            if (isset($this->request->get['page_other_num'])) {
                $page_other_num = $this->request->get['page_other_num'];
            } else {
                $page_other_num = 1;
            }
            $url .= "&page_other_num=" . $page_other_num;
            $data['page_other_num'] = $page_other_num;
            if (isset($this->request->get['page_other_limit'])) {
                $page_other_limit = $this->request->get['page_other_limit'];
            } else {
                $page_other_limit = $other_buyer_total == 0 ? 1 : $other_buyer_total;
            }
            $url .= "&page_other_limit=" . $page_other_limit;
            $data['page_other_limit'] = $page_other_limit;
            $filter_other_data = array(
                "page_num" => $page_other_num,
                "page_limit" => $page_other_limit
            );

            //            $other_buyer_total = $this->model_customer_customer->getTotalBuyersNotInCustomerId($customerId, $filter_other_data);
            $data['customer_other_total'] = $other_buyer_total;
            $other_total_pages = ceil($other_buyer_total / $page_other_limit);
            $data['other_total_pages'] = $other_total_pages;
            $data['results_other'] = sprintf($this->language->get('text_pagination'), ($other_buyer_total) ? (($page_other_num - 1) * $page_other_limit) + 1 : 0, ((($page_other_num - 1) * $page_other_limit) > ($other_buyer_total - $page_other_limit)) ? $other_buyer_total : ((($page_other_num - 1) * $page_other_limit) + $page_other_limit), $other_buyer_total, $other_total_pages);
            //            $other_buyers = $this->model_customer_customer->getBuyersNotInCustomerId($customerId, $filter_other_data);
            $other_buyers = $this->model_customer_customer->getBuyersNotAssociateForThisSeller($customerId, $filter_other_data);

            if (count($other_buyers->rows)) {
                $customers = array();
                foreach ($other_buyers->rows as $other_buyer) {
                    $customers[] = array(
                        "id" => $other_buyer['customer_id'],
                        "name" => $other_buyer['customer_name'],
                        "email" => $other_buyer['email']
                    );
                }
                $data['customers'] = $customers;
            }

            /* 排序 */
            $data['sort_buyer_name'] = $this->url->link('customer/customer/sellerOrBuyer', 'user_token=' . session('user_token') . '&is_partner=' . $isPartner . '&customer_id=' . $customerId . '&sort=cc.`firstname`' . '&order=' . $order . $url, true);
            $data['sort_buy_status'] = $this->url->link('customer/customer/sellerOrBuyer', 'user_token=' . session('user_token') . '&is_partner=' . $isPartner . '&customer_id=' . $customerId . '&sort=bts.`buy_status`' . '&order=' . $order . $url, true);
            $data['sort_price_status'] = $this->url->link('customer/customer/sellerOrBuyer', 'user_token=' . session('user_token') . '&is_partner=' . $isPartner . '&customer_id=' . $customerId . '&sort=bts.`price_status`' . '&order=' . $order . $url, true);
            $data['sort_coop_status_seller'] = $this->url->link('customer/customer/sellerOrBuyer', 'user_token=' . session('user_token') . '&is_partner=' . $isPartner . '&customer_id=' . $customerId . '&sort=bts.`seller_control_status`' . '&order=' . $order . $url, true);
            $data['sort_coop_status_buyer'] = $this->url->link('customer/customer/sellerOrBuyer', 'user_token=' . session('user_token') . '&is_partner=' . $isPartner . '&customer_id=' . $customerId . '&sort=bts.`buyer_control_status`' . '&order=' . $order . $url, true);
        } else {
            // Buyer, 展示其合作的Seller
            $sellers = $this->model_customer_customer->getSellersByCustomerId($customerId, $filter_data);
            $customerTotal = $this->model_customer_customer->getSellersTotalByCustomerId($customerId, $filter_data);
            $data['customer_total'] = $customerTotal;
            $total_pages = ceil($customerTotal / $page_limit);
            $data['total_pages'] = $total_pages;
            $num = (($page_num - 1) * $page_limit) + 1;
            $data['results'] = sprintf($this->language->get('text_pagination'), ($customerTotal) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($customerTotal - $page_limit)) ? $customerTotal : ((($page_num - 1) * $page_limit) + $page_limit), $customerTotal, $total_pages);
            foreach ($sellers->rows as $seller) {
                $tableData[] = array(
                    "num" => $num++,
                    "id" => $seller['id'],
                    "seller_name" => $seller['seller_name'],
                    "seller_email" => $seller['seller_email'],
                    "account" => $seller['account'] == null ? "" : $seller['account'],
                    "password" => $seller['pwd'] == null ? "" : $seller['pwd'],
                    "coop_status_seller" => $seller['seller_control_status'] == null ? 0 : $seller['seller_control_status'],
                    "coop_status_buyer" => $seller['buyer_control_status'] == null ? 0 : $seller['buyer_control_status']
                );
            }
            // 排序字段
            $data['sort'] = $sort;
            // 排序
            $data['order'] = $order;
            if ($order == "ASC") {
                $order = "DESC";
            } else {
                $order = "ASC";
            }

            /* 获取未添加合作关系的Buyer */
            $data["entry_customer_email"] = $this->language->get('text_seller_email');
            //展示所有同国别未绑定buyer No.12769 addby chenxiang 20190326
            $other_seller_total = $this->model_customer_customer->getTotalSellersNotAssociateForThisBuyer($customerId, $filter_other_data);
            if (isset($this->request->get['page_other_num'])) {
                $page_other_num = $this->request->get['page_other_num'];
            } else {
                $page_other_num = 1;
            }
            $url .= "&page_other_num=" . $page_other_num;
            $data['page_other_num'] = $page_other_num;
            if (isset($this->request->get['page_other_limit'])) {
                $page_other_limit = $this->request->get['page_other_limit'];
            } else {
                $page_other_limit = $other_seller_total == 0 ? 1 : $other_seller_total;
            }
            $url .= "&page_other_limit=" . $page_other_limit;
            $data['page_other_limit'] = $page_other_limit;
            $filter_other_data = array(
                "page_num" => $page_other_num,
                "page_limit" => $page_other_limit
            );
            //            $other_seller_total = $this->model_customer_customer->getTotalSellersNotInCustomerId($customerId, $filter_other_data);
            $data['customer_other_total'] = $other_seller_total;
            $other_total_pages = ceil($other_seller_total / $page_other_limit);
            $data['other_total_pages'] = $other_total_pages;

            $data['results_other'] = sprintf($this->language->get('text_pagination'), ($other_seller_total) ? (($page_other_num - 1) * $page_other_limit) + 1 : 0, ((($page_other_num - 1) * $page_other_limit) > ($other_seller_total - $page_other_limit)) ? $other_seller_total : ((($page_other_num - 1) * $page_other_limit) + $page_other_limit), $other_seller_total, $other_total_pages);

            //            $other_sellers = $this->model_customer_customer->getSellersNotInCustomerId($customerId, $filter_other_data);
            $other_sellers = $this->model_customer_customer->getSellersNotAssociateForThisBuyer($customerId, $filter_other_data);
            if (count($other_sellers->rows)) {
                $customers = array();
                foreach ($other_sellers->rows as $other_buyer) {
                    $customers[] = array(
                        "id" => $other_buyer['customer_id'],
                        "name" => $other_buyer['customer_name'],
                        "email" => $other_buyer['email']
                    );
                }
                $data['customers'] = $customers;
            }

            /* 排序 */
            $data['sort_seller_name'] = $this->url->link('customer/customer/sellerOrBuyer', 'user_token=' . session('user_token') . '&is_partner=' . $isPartner . '&customer_id=' . $customerId . '&sort=sc.`firstname`' . '&order=' . $order . $url, true);
            $data['sort_coop_status_seller'] = $this->url->link('customer/customer/sellerOrBuyer', 'user_token=' . session('user_token') . '&is_partner=' . $isPartner . '&customer_id=' . $customerId . '&sort=bts.`seller_control_status`' . '&order=' . $order . $url, true);
            $data['sort_coop_status_buyer'] = $this->url->link('customer/customer/sellerOrBuyer', 'user_token=' . session('user_token') . '&is_partner=' . $isPartner . '&customer_id=' . $customerId . '&sort=bts.`buyer_control_status`' . '&order=' . $order . $url, true);
        }
        // 表格数据
        $data['tableData'] = $tableData;
        // 是否是seller
        $data['is_partner'] = $isPartner;
        // customer id
        $data['customer_id'] = $customerId;
        // user token
        $data['user_token'] = session('user_token');
        $this->response->setOutput($this->load->view('customer/customer_buyer_or_seller', $data));
    }

    public function getOtherCustomer()
    {
        // 加载Model
        $this->load->model('customer/customer');
        $is_partner = $this->request->post['is_partner'];
        $customerId = $this->request->post["customer_id"];
        if (isset($this->request->post['page'])) {
            $page_other_num = $this->request->post['page'];
        } else {
            $page_other_num = 1;
        }
        $data['page_other_num'] = $page_other_num;
        if (isset($this->request->post['page_other_limit'])) {
            $page_other_limit = $this->request->post['page_other_limit'];
        } else {
            $page_other_limit = 20;
        }
        $customer_email = null;
        if (isset($this->request->post['customer_email'])) {
            $customer_email = $this->request->post['customer_email'];
        }
        $data['page_other_limit'] = $page_other_limit;
        //No.12769 changeby chenxiang 20190326
        $filter_other_data = array(
            //            "page_num"             => $page_other_num,
            //            "page_limit"           => $page_other_limit,
            "customer_email" => $customer_email
        );
        if ($is_partner) {
            //            $other_buyer_total = $this->model_customer_customer->getTotalBuyersNotInCustomerId($customerId, $filter_other_data);
            $other_buyer_total = $this->model_customer_customer->getTotalBuyersNotAssociateForThisSeller($customerId, $filter_other_data);
            $page_other_limit = $other_buyer_total == 0 ? 1 : $other_buyer_total;
            $data['customer_other_total'] = $other_buyer_total;
            $other_total_pages = ceil($other_buyer_total / $page_other_limit);
            $data['other_total_pages'] = $other_total_pages;

            $data['results_other'] = sprintf($this->language->get('text_pagination'), ($other_buyer_total) ? (($page_other_num - 1) * $page_other_limit) + 1 : 0, ((($page_other_num - 1) * $page_other_limit) > ($other_buyer_total - $page_other_limit)) ? $other_buyer_total : ((($page_other_num - 1) * $page_other_limit) + $page_other_limit), $other_buyer_total, $other_total_pages);
            //            $other_buyers = $this->model_customer_customer->getBuyersNotInCustomerId($customerId, $filter_other_data);
            $other_buyers = $this->model_customer_customer->getBuyersNotAssociateForThisSeller($customerId, $filter_other_data);
            $customers = array();
            if (count($other_buyers->rows)) {

                foreach ($other_buyers->rows as $other_buyer) {
                    $customers[] = array(
                        "id" => $other_buyer['customer_id'],
                        "name" => $other_buyer['customer_name'],
                        "email" => $other_buyer['email']
                    );
                }
            }
            $data['customers'] = $customers;
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($data));
        } else {
            //            $other_buyer_total = $this->model_customer_customer->getTotalSellersNotInCustomerId($customerId, $filter_other_data);
            $other_buyer_total = $this->model_customer_customer->getTotalSellersNotAssociateForThisBuyer($customerId, $filter_other_data);
            $page_other_limit = $other_buyer_total == 0 ? 1 : $other_buyer_total;
            $data['customer_other_total'] = $other_buyer_total;
            $other_total_pages = ceil($other_buyer_total / $page_other_limit);
            $data['other_total_pages'] = $other_total_pages;

            $data['results_other'] = sprintf($this->language->get('text_pagination'), ($other_buyer_total) ? (($page_other_num - 1) * $page_other_limit) + 1 : 0, ((($page_other_num - 1) * $page_other_limit) > ($other_buyer_total - $page_other_limit)) ? $other_buyer_total : ((($page_other_num - 1) * $page_other_limit) + $page_other_limit), $other_buyer_total, $other_total_pages);

            //            $other_buyers = $this->model_customer_customer->getSellersNotInCustomerId($customerId, $filter_other_data);
            $other_buyers = $this->model_customer_customer->getSellersNotAssociateForThisBuyer($customerId, $filter_other_data);
            $customers = array();
            if (count($other_buyers->rows)) {
                foreach ($other_buyers->rows as $other_buyer) {
                    $customers[] = array(
                        "id" => $other_buyer['customer_id'],
                        "name" => $other_buyer['customer_name'],
                        "email" => $other_buyer['email']
                    );
                }
            }
            $data['customers'] = $customers;
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($data));
        }
    }

    public function updateSellerOrBuyer()
    {
        // 加载Model
        $this->load->model('customer/customer');
        $is_partner = $this->request->post['is_partner'];
        $customer_id = $this->request->post["customer_id"];
        $order = $this->request->post['order'];
        $sort = $this->request->post['sort'];
        $json = array();
        if (isset($is_partner)) {
            if ($is_partner) {
                // seller;
                $id = $this->request->post["id"];
                $buy_status = $this->request->post["buy_status"];
                $price_status = $this->request->post["price_status"];
                $discount = $this->request->post["discount"];
                $coop_status_seller = $this->request->post["coop_status_seller"];
                $coop_status_buyer = $this->request->post["coop_status_buyer"];
                $updata_data = array(
                    "id" => $id,
                    "buy_status" => $buy_status,
                    "price_status" => $price_status,
                    "buyer_control_status" => $coop_status_buyer,
                    "seller_control_status" => $coop_status_seller,
                    "discount" => $discount
                );
                $this->model_customer_customer->updateBuyerInfo($updata_data);
                $url = "";
                // 查询过滤条件
                if (isset($this->request->post['input_customer_name'])) {
                    $filter_customer_name = $this->request->post['input_customer_name'];
                    $url .= "&input_customer_name=" . $filter_customer_name;
                }

                if (isset($this->request->post['input_email'])) {
                    $filter_email = $this->request->post['input_email'];
                    $url .= "&input_email=" . $filter_email;
                }

                // 分页
                // 第几页
                if (isset($this->request->post['page_num'])) {
                    $page_num = $this->request->post['page_num'];
                } else {
                    $page_num = 1;
                }
                $url .= "&page_num=" . $page_num;
                // 每页显示数目
                if (isset($this->request->post['page_limit'])) {
                    $page_limit = $this->request->post['page_limit'];
                } else {
                    $page_limit = 20;
                }
                $url .= "&page_limit=" . $page_limit;
                $json['load'] = $this->url->link('customer/customer/sellerOrBuyer', '&user_token=' . session('user_token') . "&customer_id=" . $customer_id . "&is_partner=" . $is_partner . "&sort=" . $sort . "&order=" . $order . $url, false);
                $json['load'] = str_replace("&amp;", "&", $json['load']);
            } else {
                // buyer
                $id = $this->request->post["id"];
                $account = $this->request->post["account"];
                $pwd = $this->request->post["pwd"];
                $coop_status_seller = $this->request->post["coop_status_seller"];
                $coop_status_buyer = $this->request->post["coop_status_buyer"];
                $updata_data = array(
                    "id" => $id,
                    "account" => $account,
                    "pwd" => $pwd,
                    "buyer_control_status" => $coop_status_buyer,
                    "seller_control_status" => $coop_status_seller
                );
                $this->model_customer_customer->updateSellerInfo($updata_data);
                $url = "";
                // 查询过滤条件
                if (isset($this->request->post['input_customer_name'])) {
                    $filter_customer_name = $this->request->post['input_customer_name'];
                    $url .= "&input_customer_name=" . $filter_customer_name;
                }

                if (isset($this->request->post['input_email'])) {
                    $filter_email = $this->request->post['input_email'];
                    $url .= "&input_email=" . $filter_email;
                }

                // 分页
                // 第几页
                if (isset($this->request->post['page_num'])) {
                    $page_num = $this->request->post['page_num'];
                } else {
                    $page_num = 1;
                }
                $url .= "&page_num=" . $page_num;
                // 每页显示数目
                if (isset($this->request->post['page_limit'])) {
                    $page_limit = $this->request->post['page_limit'];
                } else {
                    $page_limit = 20;
                }
                $url .= "&page_limit=" . $page_limit;
                $json['load'] = $this->url->link('customer/customer/sellerOrBuyer', '&user_token=' . session('user_token') . "&customer_id=" . $customer_id . "&is_partner=" . $is_partner . "&sort=" . $sort . "&order=" . $order . $url, false);
                $json['load'] = str_replace("&amp;", "&", $json['load']);
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function deleteSellerOrBuyer()
    {
        // 加载Model
        $this->load->model('customer/customer');
        $is_partner = $this->request->post['is_partner'];
        $customer_id = $this->request->post["customer_id"];
        $order = $this->request->post['order'];
        $sort = $this->request->post['sort'];
        $json = array();
        $id = $this->request->post["id"];
        //刪除和精细化有关的数据
        if (!is_null($is_partner)) {
            $this->model_customer_customer->deleteDelicacyManagement($customer_id, $id, $is_partner);
        }
        $delete_data = array(
            "id" => $id,
        );
        $this->model_customer_customer->deleteBuyerToSeller($delete_data);
        $url = "";
        // 查询过滤条件
        if (isset($this->request->post['input_customer_name'])) {
            $filter_customer_name = $this->request->post['input_customer_name'];
            $url .= "&input_customer_name=" . $filter_customer_name;
        }

        if (isset($this->request->post['input_email'])) {
            $filter_email = $this->request->post['input_email'];
            $url .= "&input_email=" . $filter_email;
        }

        // 分页
        // 第几页
        if (isset($this->request->post['page_num'])) {
            $page_num = $this->request->post['page_num'];
        } else {
            $page_num = 1;
        }
        $url .= "&page_num=" . $page_num;
        // 每页显示数目
        if (isset($this->request->post['page_limit'])) {
            $page_limit = $this->request->post['page_limit'];
        } else {
            $page_limit = 20;
        }
        $url .= "&page_limit=" . $page_limit;
        $json['load'] = $this->url->link('customer/customer/sellerOrBuyer', '&user_token=' . session('user_token') . "&customer_id=" . $customer_id . "&is_partner=" . $is_partner . "&sort=" . $sort . "&order=" . $order . $url, true);
        $json['load'] = str_replace("&amp;", "&", $json['load']);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function addBuyerOrSeller()
    {
        // 加载Model
        $this->load->model('customer/customer');
        $is_partner = $this->request->post['is_partner'];
        $customer_id = $this->request->post["customer_id"];
        $json = array();
        if (isset($is_partner)) {
            if ($is_partner) {
                if (isset($this->request->post['customer_ids'])) {
                    $customerIds = $this->request->post['customer_ids'];
                    // 建立seller合作关系
                    $this->model_customer_customer->addBuyersToSeller($customer_id, $customerIds);
                    $this->model_customer_customer->batchAddBuyerToDefaultBuyerGroup($customer_id, $customerIds);
                    $json['load'] = $this->url->link('customer/customer/sellerOrBuyer', '&user_token=' . session('user_token') . "&customer_id=" . $customer_id . "&is_partner=" . $is_partner, true);
                    $json['load'] = str_replace("&amp;", "&", $json['load']);
                }
            } else {
                if (isset($this->request->post['customer_ids'])) {
                    $customerIds = $this->request->post['customer_ids'];
                    // 建立seller合作关系
                    $this->model_customer_customer->addSellersToSeller($customer_id, $customerIds);
                    $json['load'] = $this->url->link('customer/customer/sellerOrBuyer', '&user_token=' . session('user_token') . "&customer_id=" . $customer_id . "&is_partner=" . $is_partner, true);
                    $json['load'] = str_replace("&amp;", "&", $json['load']);
                }
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function autocomplete()
    {
        $json = array();

        if (isset($this->request->get['filter_name']) || isset($this->request->get['filter_email']) || isset($this->request->get['filter_screenname'])) {
            if (isset($this->request->get['filter_name'])) {
                $filter_name = $this->request->get['filter_name'];
            } else {
                $filter_name = '';
            }

            if (isset($this->request->get['filter_email'])) {
                $filter_email = $this->request->get['filter_email'];
            } else {
                $filter_email = '';
            }

            if (isset($this->request->get['filter_affiliate'])) {
                $filter_affiliate = $this->request->get['filter_affiliate'];
            } else {
                $filter_affiliate = '';
            }

            if (isset($this->request->get['filter_screenname'])) {
                $filter_screenname = str_replace('%', '\%', $this->request->get['filter_screenname']);
            } else {
                $filter_screenname = '';
            }

            if (isset($this->request->get['sort'])) {
                $sort = $this->request->get['sort'];
            } else {
                $sort = '';
            }

            $this->load->model('customer/customer');

            $filter_data = array(
                'filter_name' => $filter_name,
                'filter_email' => $filter_email,
                'filter_affiliate' => $filter_affiliate,
                'filter_screenname' => $filter_screenname,
                'start' => 0,
                'limit' => 5,
                'sort' => $sort,
            );


            $results = $this->model_customer_customer->getCustomers($filter_data);

            foreach ($results as $result) {
                $json[] = array(
                    'customer_id' => $result['customer_id'],
                    'customer_group_id' => $result['customer_group_id'],
                    'name' => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
                    'customer_group' => $result['customer_group'],
                    'firstname' => html_entity_decode($result['firstname']),
                    'lastname' => html_entity_decode($result['lastname']),
                    'email' => $result['email'],
                    'telephone' => $result['telephone'],
                    'custom_field' => json_decode($result['custom_field'], true),
                    'address' => $this->model_customer_customer->getAddresses($result['customer_id']),
                    'screenname' => html_entity_decode($result['screenname']),
                );
            }
        }

        $sort_order = array();

        foreach ($json as $key => $value) {
            $sort_order[$key] = $value['name'];
        }

        array_multisort($sort_order, SORT_ASC, $json);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function customfield()
    {
        $json = array();

        $this->load->model('customer/custom_field');

        // Customer Group
        if (isset($this->request->get['customer_group_id'])) {
            $customer_group_id = $this->request->get['customer_group_id'];
        } else {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }

        $custom_fields = $this->model_customer_custom_field->getCustomFields(array('filter_customer_group_id' => $customer_group_id));

        foreach ($custom_fields as $custom_field) {
            $json[] = array(
                'custom_field_id' => $custom_field['custom_field_id'],
                'required' => empty($custom_field['required']) || $custom_field['required'] == 0 ? false : true
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function address()
    {
        $json = array();

        if (!empty($this->request->get['address_id'])) {
            $this->load->model('customer/customer');

            $json = $this->model_customer_customer->getAddress($this->request->get['address_id']);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 获取可用的随机用户编码
     */
    private function getUniqueUserNumber()
    {
        $used_random = array();
        //最大尝试获取随机数的次数，防止死循环
        $max_test_count = 100;
        while ($max_test_count > 0) {
            //5个一批的随机数去数据库检测重复，减少数据库连接
            $test_random_array = array();
            $count = 5;
            while ($count > 0) {
                $random = mt_rand(10000000, 99999999);
                while (!in_array($random, $used_random)) {
                    $test_random_array[] = $random;
                    $used_random[] = $random;
                }
                $count--;
            }
            $valid_number = $this->model_customer_customer->testUniqueUserNumber($test_random_array);

            if (isset($valid_number) && !empty($valid_number)) {
                return current($valid_number);
            }
            $max_test_count--;
        }
    }
}
