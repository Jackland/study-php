<?php

use App\Catalog\Controllers\AuthController;
use App\Widgets\BreadcrumbWidget;

/**
 * Class ControllerAccountMappingManagement
 * Buyer Mapping Management
 *
 * @property ModelAccountMappingManagement $model_account_mapping_management
 */
class ControllerAccountMappingManagement extends AuthController
{

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    public function index()
    {
       load()->model('account/mapping_management');
        // 加载语言层
       load()->language('account/mapping_management');
        // 设置文档标题
        $this->document->setTitle($this->language->get('heading_title'));
        // 面包屑导航
        $breadcrumbs = BreadcrumbWidget::widget([
            'items' => $this->getBreadcrumbs([
                'home',
                [
                    'text' => 'text_account',
                    'href' => 'account/account',
                ],
                'current',
            ]),
        ]);
        $data['breadcrumbs'] = $breadcrumbs->items;
        $data['isShowMappingManagement'] = $this->model_account_mapping_management->isShowMappingManagement();
        $data['isShowItemCodeMapping']   = $this->model_account_mapping_management->isShowItemCodeMapping();
        $data['isShowWarehouseMapping']  = $this->model_account_mapping_management->isShowWarehouseMapping();
        //页面公共布局
        $data['app_version']    = APP_VERSION;
        $this->response->setOutput($this->load->view('account/mapping_management/index', $data));
        return $this->render('account/mapping_management/index', $data, [
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom',
            'footer' => 'common/footer',
            'header' => 'common/header',
        ]);
    }

    public function list()
    {

    }
}