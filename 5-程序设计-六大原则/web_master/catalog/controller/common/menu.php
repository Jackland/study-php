<?php

use App\Enums\Common\YesNoEnum;
use App\Helper\RouteHelper;

/**
 * Class ControllerCommonMenu
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelCatalogCategory $model_catalog_category
 * @property ModelDesignBanner $model_design_banner
 * @property ModelMessageMessage $model_message_message
 * @property ModelNoticeNotice $model_notice_notice
 * @property ModelStationLetterStationLetter $model_station_letter_station_letter
 * @property ModelExtensionModuleProductCategory $model_extension_module_product_category
 */
class ControllerCommonMenu extends Controller
{
    /**
     * @return string
     * @throws \Framework\Exception\InvalidConfigException
     * @throws Exception
     */
    public function index()
    {
        $this->load->language('common/menu');

        // Menu
        $this->load->model('account/customerpartner');
        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('design/banner');
        $this->load->model('catalog/product');
        $this->load->model('extension/module/product_category');
        $data['categories'] = $this->model_extension_module_product_category->getDefaultCategoryList();
        $country = $this->session->get('country');

        $data['menu_top_lists'] = array_filter($this->model_design_banner->getBanner(10, $country), function ($item) {
            if (!$this->customer->isLogged()) {
                // 未登录的，不显示以下路由的
                return !in_array($item['link'], [
                    'index.php?route=customerpartner/contacted_seller', // My Seller
                ]);
            }
            if ($this->customer->isPartner()) {
                // 是 seller，不显示以下路由的
                return !in_array($item['link'], [
                    'index.php?route=customerpartner/contacted_seller', // My Seller
                    'index.php?route=account/coupon/index', // Coupon Center
                ]);
            }
            return true;
        });

        $data['is_home'] = YesNoEnum::YES;
        $route = request('route', 'common/home');
        if ($route == 'common/home' || !$route) {
            $data['is_home'] = YesNoEnum::YES;
        } else {
            $data['is_home'] = YesNoEnum::NO;
        }

        $data['use_buyer_menu'] = RouteHelper::isCurrentMatchGroup('notBuyerMenu', false);

        return $this->load->view('common/menu', $data);
	}
}
