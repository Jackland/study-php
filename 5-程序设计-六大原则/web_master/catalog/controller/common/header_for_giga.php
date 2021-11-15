<?php

/**
 * @property ModelAccountCustomer $model_account_customer
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountWishlist $model_account_wishlist
 * @property ModelExtensionModuleShipmentTime $model_extension_module_shipment_time
 * @property ModelExtensionModuleWkcontact $model_extension_module_wk_contact
 * @property ModelLocalisationCountry $model_localisation_country
 * @property ModelSettingExtension $model_setting_extension
 */
class ControllerCommonHeaderForGiga extends Controller
{
    public function index()
    {
        // Analytics
        $this->load->model('setting/extension');

        $data['analytics'] = array();

        $analytics = $this->model_setting_extension->getExtensions('analytics');

        foreach ($analytics as $analytic) {
            if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
                $data['analytics'][] = $this->load->controller('extension/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
            }
        }

        if ($this->request->server['HTTPS']) {
            $server = $this->config->get('config_ssl');
        } else {
            $server = $this->config->get('config_url');
        }

        if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
            $this->document->addLink($server . 'image/' . $this->config->get('config_icon'), 'icon');
        }

        $data['title'] = $this->document->getTitle();

        $data['base'] = $server;
        $data['description'] = $this->document->getDescription();
        $data['keywords'] = $this->document->getKeywords();
        $data['links'] = $this->document->getLinks();
        $data['styles'] = $this->document->getStyles();
        $data['scripts'] = $this->document->getScripts('header');
        $data['lang'] = $this->language->get('code');
        $data['direction'] = $this->language->get('direction');

        $data['name'] = $this->config->get('config_name');

        if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
            $data['logo'] = $server . 'image/' . $this->config->get('config_logo');
        } else {
            $data['logo'] = '';
        }


        $data['notification'] = '';
        $data['sellmenu'] = $this->load->controller('extension/module/marketplace/sellmenu');
        if ($this->config->get('module_marketplace_status')) {
            $data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();
            $data['marketplace_seller_mode'] = session('marketplace_seller_mode', 1);
            if ($data['chkIsPartner'] && $data['marketplace_seller_mode']) {
                $data['notification'] = $this->load->controller('account/customerpartner/notification/notifications');

                $data['notification_total'] = $this->model_account_notification->getTotalSellerActivity() +
                    $this->model_account_notification->getSellerProductActivityTotal() +
                    $this->model_account_notification->getSellerReviewsTotal() -
                    $this->model_account_notification->getViewedNotifications();
            }
        }

        $this->load->language('common/header');

        // Wishlist
        if ($this->customer->isLogged()) {
            $this->load->model('account/wishlist');

            $data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist());
        } else {
            $data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), count(session('wishlist', [])));
        }

        $data['text_logged'] = sprintf($this->language->get('text_logged'), $this->url->link('account/account', '', true), $this->customer->getFirstName(), $this->url->link('account/logout', '', true));

        $data['home'] = $this->url->link('common/home');
        $data['wishlist'] = $this->url->link('account/wishlist', '', true);
        $data['logged'] = $this->customer->isLogged();
        $data['account'] = $this->url->link('account/account', '', true);
        $data['register'] = $this->url->link('account/register_apply', '', true);
        $data['login'] = $this->url->link('account/login', '', true);
        $data['order'] = $this->url->link('account/order', '', true);
        $data['transaction'] = $this->url->link('account/transaction', '', true);
        $data['download'] = $this->url->link('account/download', '', true);
        $data['buyer_brand_management'] = $this->url->link('account/manufacturer', '', true);
        $data['seller_brand_management'] = $this->url->link('account/brand_management', '', true);

        // show customer order 只能Buyer账号可见
        $this->load->model('account/customerpartner');
        /** @var ModelAccountCustomerpartner $model_account_customerpartner */
        $model_account_customerpartner = $this->model_account_customerpartner;
        $data['chkIsPartner'] = $model_account_customerpartner->chkIsPartner();
        $LoginInInfo = $model_account_customerpartner->getLoginInInfoByCustomerId();

        if ($model_account_customerpartner->chkIsPartner()) {
            $data['marketplace_seller_mode'] = session('marketplace_seller_mode', 1);
            $data['is_seller'] = 1;
            $data['loginInInfo'] = $LoginInInfo['screenname'] . '(' . $LoginInInfo['user_number'] . '-Seller)';
            $data['inbound_management'] = $this->url->link("account/inbound_management", '', true);
            if ($data['marketplace_seller_mode'] == 0) {
                $data['show_c_order'] = $this->url->link("account/corder", '', true);
            }
            // print product labels
            $data['print_product_labels'] = $this->url->link('account/print_product_labels', '', true);
            //isInnerSeller  用于屏蔽内部seller的Inventory Management
            $this->load->model('account/customer');
            $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
            $data['isInnerSeller'] = $customer_info['accounting_type']==1;
        } else {
            $data['is_seller'] = 0;
            if ($this->customer->getId()) {
                $data['loginInInfo'] = $LoginInInfo['nickname'] . '(' . $LoginInInfo['user_number'] . '-Buyer)';
            }
            $data['show_customer_order'] = $this->url->link("account/customer_order", '', true);
            $data['sellers'] = $this->url->link("account/sellers", '', true);
            $data['communication'] = $this->url->link("account/wk_communication", '', true);
            $data['product_quote_my'] = $this->url->link("account/product_quotes/wk_quote_my", '', true);
            $data['rma_management'] = $this->url->link('account/rma_management', '', true);
        }
        $data['inventory_management'] = $this->url->link("account/inventory_management", '', true);
        $data['purchase'] = $this->url->link('account/order_for_giga', '', true);
        $data['logout'] = $this->url->link('account/logout', '', true);
        $data['shopping_cart'] = $this->url->link('checkout/cart');
        $data['checkout'] = $this->url->link('checkout/checkout', '', true);
        $data['contact'] = $this->url->link('information/contact');
        $data['telephone'] = $this->config->get('config_telephone');
        //$data['line_of_credit'] = $this->currency->formatCurrencyPrice($this->customer->getLineOfCredit(), session('currency'));

        $data['guide_menu_url'] = $this->url->link('account/guide', '', true);
//		$data['language'] = $this->load->controller('common/language');
        $data['country'] = $this->load->controller('common/country');
        $data['currency'] = $this->load->controller('common/currency');
        //$data['search'] = $this->load->controller('common/search');
        //$data['cart'] = $this->load->controller('common/cart');
        //$data['menu'] = $this->load->controller('common/menu');
        //$data['creditline_amendment_record'] = $this->load->controller('customerpartner/creditline_amendment_record');
        /**
         * @todo 只开启美国区议价
         */
        $data['isAmerican'] = true;
        if (empty($this->customer->getCountryId()) || $this->customer->getCountryId() != 223) {
            $data['isAmerican'] = false;
        }
        // TODO 判断是否是日本用户
        $data['isJapan'] = true;
        if (empty($this->customer->getCountryId()) || $this->customer->getCountryId() != 107) {
            $data['isJapan'] = false;
        }

        // add by lilei ShipmentTime
        $moduleShipmentTimeStatus = $this->config->get('module_shipment_time_status');
        if ($moduleShipmentTimeStatus) {
            // 获取当前国家
            $countryCode = session('country');
            // 获取国家ID
            $this->load->model('localisation/country');
            $country = $this->model_localisation_country->getCountryByCode2($countryCode);
            // 获取countryId
            $countryId = $country['country_id'];
            // 获取countryId对应的shipment time
            $this->load->model('extension/module/shipment_time');
            $shipmentTimePage = $this->model_extension_module_shipment_time->getShipmentTime($countryId);
            $data['module_shipment_time_status'] = $moduleShipmentTimeStatus;
            $data['shipmentTimePage'] = $shipmentTimePage;
            $data['shipmentPage'] = $this->url->link('information/information', 'shipmentTime=1', true);
        }
        // end
        //未读邮件显示
        $data['contact_unread'] = $this->load->controller('account/wk_communication/contactUnread');
        //未读平台公告
        if($this->customer->isLogged()) {
            $data['unread_notice'] = $this->load->controller('information/notice/unread_notice');
        }

        //buyer对账
        $data['sales_purchase_bill_url'] = $this->url->link("account/bill/sales_purchase_bill", '', true);

        // 头部消息红点添加 buyer展示
        if ($this->customer->isLogged() && !$this->customer->isPartner()) {
            $this->load->model('extension/module/wk_contact');
            /** @var ModelExtensionModuleWkcontact $modelWkContact */
            $modelWkContact = $this->model_extension_module_wk_contact;
            $unread_num = (int)$modelWkContact->countUnread();
            $data['wk_contact_total'] = $unread_num;
        }

        //在分组【B2B-WillCall】24、【UK-DropShip-Buyer】26、【US-DropShip-Buyer】25内的Buyer用户，点击用户账号展开下拉菜单，在“Inventory Management”菜单下面新增“Mapping Management”菜单。
        //国别限制成美国的buyer
        $data['isShowMappingManagement'] = 0;
        if (!$this->customer->isPartner() && in_array($this->customer->getGroupId(), [24, 25, 26]) && $this->customer->getCountryId() == 223) {
            $data['isShowMappingManagement'] = 1;
            $data['mappingManagementLink']   = $this->url->link("account/mapping_management", '', true);
        }
        $data['app_version'] = APP_VERSION;
        return $this->load->view('common/header_for_giga', $data);
    }
}
