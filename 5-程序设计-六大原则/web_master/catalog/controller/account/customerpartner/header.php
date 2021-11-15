<?php

use App\Helper\TranslationHelper;
use App\Models\ServiceAgreement\AgreementVersion;
use App\Repositories\ServiceAgreement\ServiceAgreementRepository;

/**
 * Class ControllerAccountCustomerpartnerHeader
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelToolImage model_tool_image
 */
class ControllerAccountCustomerpartnerHeader extends Controller
{
    public function index()
    {
        $data['title'] = $this->document->getTitle();
        $data['customerId'] = $this->customer->getId();
        if ($this->request->server['HTTPS']) {
            $server = $this->config->get('config_ssl');
        } else {
            $server = $this->config->get('config_url');
        }

        if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
            $data['logo'] = $server . 'image/' . $this->config->get('config_logo');
        } else {
            $data['logo'] = 'admin/view/image/logo.png';
        }

        if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
            $this->document->addLink($server . 'image/' . $this->config->get('config_icon'), 'icon');
        }

        $data['name'] = $this->config->get('config_name');
        $data['base'] = $server;
        $data['description'] = $this->document->getDescription();
        $data['keywords'] = $this->document->getKeywords();
        $data['links'] = $this->document->getLinks();
        $data['styles'] = $this->document->getStyles();
        $data['scripts'] = $this->document->getScripts();
        $data['lang'] = $this->language->get('code');
        $data['direction'] = $this->language->get('direction');

        $this->load->language('account/customerpartner/header');

        if ($this->request->get('view')) {
            $this->session->set('marketplace_separate_view', $this->request->get('view'));
        }

        $data['logged'] = '';
        if ($this->customer->isLogged()) {
            $data['logged'] = true;
            if ($this->config->get('module_marketplace_status')
                && is_array($this->config->get('marketplace_allowed_account_menu'))
                && $this->config->get('marketplace_allowed_account_menu')) {
                if (in_array('notification', $this->config->get('marketplace_allowed_account_menu'))) {
                    $data['notification'] = $this->load->controller('account/customerpartner/notification/notifications');
                }

                if (in_array('asktoadmin', $this->config->get('marketplace_allowed_account_menu'))) {
                    $data['asktoadmin'] = 1;
                }
            }
        }

        $currentRoute = $this->request->get('route', '');
        if (
            $this->customer->isLogged()
            && app(ServiceAgreementRepository::class)->checkCustomerSignAgreement(AgreementVersion::AGREEMENT_ID_BY_CUSTOMER_LOGIN, customer()->getId())
            && $currentRoute != 'account/service_agreement'
            && $this->session->has('is_redirect_agreement')
        ) {
            if (!empty($currentRoute)) {
                $this->url->remember();
            }
            return $this->redirect('account/service_agreement')->send();
        }

        //新增#2247  调取店铺名称
        $this->load->model('account/customerpartner');
        $data['screenname'] = '';
        $data['username'] = '';
        $sellerProfile = $this->model_account_customerpartner->getProfile();
        if (isset($sellerProfile['screenname']) && $sellerProfile['screenname']) {
            $data['screenname'] = $sellerProfile['screenname'];
            $data['username'] = $sellerProfile['firstname'] . $sellerProfile['lastname'];
        }
        $this->load->model('tool/image');
        if (isset($sellerProfile['avatar']) && $sellerProfile['avatar']) {
            $data['image'] = $this->model_tool_image->resize($sellerProfile['avatar'], 45, 45);
        } else {
            $data['image'] = $this->model_tool_image->resize('no_image.png');
        }

        $route = request('route', 'common/home');
        $data['is_show_sidebar'] = true;
        if (in_array($route, ['account/ticket/lists',])) {
            $data['is_show_sidebar'] = false;
        }
        // 是否显示 Seller运费计算器 按钮
        $data['is_show_calculator_freight'] = false;
        // 只有美国需要显示运费计算器
        if ($this->customer->isPartner() && $this->customer->isUSA()) {
            $data['is_show_calculator_freight'] = true;
        }

        // 顶部菜单固定需要翻译
        TranslationHelper::tempEnable();
        $content = $this->load->view('account/customerpartner/header', $data);
        TranslationHelper::tempDisableAfterEnable();

        return $content;
    }
}
