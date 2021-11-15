<?php

use App\Repositories\Common\HomeRepository;

/**
 * Class ControllerExtensionModuleSlideshow
 * @property ModelDesignBanner $model_design_banner
 * @property ModelToolImage $model_tool_image
 * @property ModelExtensionModuleProductCategory $model_extension_module_product_category
 */
class ControllerExtensionModuleSlideshow extends Controller {
    public function index($setting) {

        $this->document->addStyle('catalog/view/javascript/jquery/swiper/css/swiper.min.css?version=' . APP_VERSION);
        $this->document->addStyle('catalog/view/javascript/jquery/swiper/css/opencart.css?version=' . APP_VERSION);
        $this->document->addScript('catalog/view/javascript/jquery/swiper/js/swiper.jquery.js?version=' . APP_VERSION);
        $cacheRefresh = request('_cache_refresh',0);
        $data = app(HomeRepository::class)->getSlideShowData($setting,$cacheRefresh);
        return $this->load->view('extension/module/slideshow', $data);
    }
}
