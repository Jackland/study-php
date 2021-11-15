<?php

use App\Enums\Product\ProductStatus;

/**
 * @property ModelAccountCustomerGroup $model_account_customer_group
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerProductGroup $model_Account_Customerpartner_ProductGroup
 * @property Modelaccountwkcustomfield $model_account_wkcustomfield
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelLocalisationLanguage $model_localisation_language
 * @property ModelMpLocalisationLengthClass $model_mp_localisation_length_class
 * @property ModelMpLocalisationStockStatus $model_mp_localisation_stock_status
 * @property ModelMpLocalisationTaxClass $model_mp_localisation_tax_class
 * @property ModelMpLocalisationWeightClass $model_mp_localisation_weight_class
 * @property ModelSettingStore $model_setting_store
 * @property ModelToolImage $model_tool_image
 * Class ControllerAccountCustomerpartnerAddproduct
 */
class ControllerAccountCustomerpartnerAddproduct extends Controller
{
    private $error = array();

    private $membershipData = array();

    public function __construct($registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/addproduct', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
    }

    public function index()
    {

        if ($this->request->post) {
            function cleans(&$item)
            {
                $item = strip_tags(trim($item));
            }

            array_walk_recursive($this->request->post, 'cleans');

            trim_strings($this->request->post);
        }
        trim_strings($this->request->get);

        $this->load->model('account/customerpartner');

        // membership codes starts here
        if ($this->config->get('module_wk_seller_group_status')) {
            $data['module_wk_seller_group_status'] = true;
            $data['module_wk_seller_group_single_category'] = true;
            if ($this->config->get('module_wk_seller_group_membership_type') == 'product') {
                $data['module_wk_seller_group_membership_type'] = 'product';
            } else {
                $data['module_wk_seller_group_status'] = true;
                $this->document->addscript("catalog/view/javascript/sellergroup/function.js?v=".APP_VERSION);
                $data['module_wk_seller_group_membership_type'] = 'quantity';
                $data['remaining_quantity'] = true;
                $sellerDetail = $this->model_account_customerpartner->getSellerDetails();
                foreach ($sellerDetail as $detail) {
                    $data['sellerDetail'][] = array(
                        'product_id' => $detail['product_id'],
                        'group_id' => $detail['groupid'],
                        'name' => $detail['name'],
                        'quantity' => $detail['gquantity'],
                        'price' => $this->currency->format($detail['gprice'], $this->session->data['currency']),
                    );
                }
            }
        } else {

            $data['module_wk_seller_group_status'] = false;
            $data['module_wk_seller_group_single_category'] = false;
            $data['module_wk_seller_group_membership_type'] = false;
        }
        // membership codes ends here

        $this->load->model('catalog/product');

        $data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

        if (!$data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode']))
            $this->response->redirect($this->url->link('account/account', '', true));

        $this->load->language('account/customerpartner/addproduct');

        if (isset_and_not_empty($this->request->get, 'product_id')) {
            $this->document->setTitle($this->language->get('heading_title_edit_product'));
            $data['is_add'] = false;
        } else {
            $this->document->setTitle($this->language->get('heading_title_add_product'));
            $data['is_add'] = true;
        }

        $this->document->addScript('catalog/view/javascript/wk_summernote/summernote.js');
        $this->document->addStyle('catalog/view/javascript/wk_summernote/summernote.css');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');
        $this->document->addStyle('catalog/view/theme/default/stylesheet/MP/sell.css?v=' . APP_VERSION);
        $this->document->addStyle('catalog/view/javascript/bootstrap-select/css/bootstrap-select.min.css');
        $this->document->addScript('catalog/view/javascript/bootstrap-select/js/bootstrap-select.min.js');

        if ((request()->isMethod('POST')) AND $this->validateForm()) {
            if ($this->config->get('module_wk_seller_group_status')) {
                if ($this->membershipData && isset($this->membershipData['remain'])) {
                    $this->load->model('account/wk_membership_catalog');
                    $this->model_account_wk_membership_catalog->insertInPay($this->membershipData['remain']);
                }
            }

            if (isset($this->request->post['clone']) && $this->request->post['clone']) {
                $this->model_account_customerpartner->addProduct($this->request->post);
            } else if (!isset($this->request->get['product_id'])) {
                $this->model_account_customerpartner->addProduct($this->request->post);
                session()->set('success', 'Success：You have created a product.');
            } else {
                $this->model_account_customerpartner->editProduct($this->request->post);
                session()->set('success', 'Success：You have modified the product information.');
            }

            $this->response->redirect($this->url->link('account/customerpartner/productlist', '', true));

        }


        $data['entry_image'] = ' <span data-toggle="tooltip" title="' . $this->config->get('marketplace_imageex') . '">' . $this->language->get('entry_image') . '</span>';


        $help = array(
            'help_keyword',
            'help_sku',
            'help_upc',
            'help_ean',
            'help_jan',
            'help_isbn',
            'help_mpn',
            'help_manufacturer',
            'help_minimum',
            'help_stock_status',
            'help_points',
            'help_category',
            'help_filter',
            'help_download',
            'help_related',
            'help_tag',
            'help_length',
            'help_width',
            'help_height',
            'help_weight',
            'help_image',
        );

        foreach ($help as $value) {
            $data[$value] = $this->language->get($value);
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['customFieldError'])) {
            $data['customFieldError'] = $this->error['customFieldError'];
        } else {
            $data['customFieldError'] = array();
        }

        if (isset($this->error['name'])) {
            $data['error_name'] = $this->error['name'];
        } else {
            $data['error_name'] = array();
        }

        if (isset($this->error['name'])) {
            $data['error_name'] = $this->error['name'];
        } else {
            $data['error_name'] = array();
        }

        if (isset($this->error['error_meta_title'])) {
            $data['error_meta_title'] = $this->error['error_meta_title'];
        } else {
            $data['error_meta_title'] = array();
        }

        if (isset($this->error['model'])) {
            $data['error_model'] = $this->error['model'];
        } else {
            $data['error_model'] = '';
        }

        if (isset($this->error['category'])) {
            $data['error_category'] = $this->error['category'];
        } else {
            $data['error_category'] = '';
        }

        if (isset($this->error['keyword'])) {
            $data['error_keyword'] = $this->error['keyword'];
        } else {
            $data['error_keyword'] = '';
        }

        if (!isset($this->request->get['product_id'])) {
            $data['Edit'] = 0;
            $data['product_id'] = '';
            $data['action'] = $this->url->link('account/customerpartner/addproduct', '', true);
        } else {
            $data['Edit'] = 1;
            $data['product_id'] = $this->request->get['product_id'];
            $data['action'] = $this->url->link('account/customerpartner/addproduct&product_id=' . $this->request->get['product_id'], '', true);
        }

        $data['cancel'] = $this->url->link('account/customerpartner/productlist', '', true);

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', '', true),
            'separator' => false
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_account'),
            'href' => 'javascript:void(0);',
            'separator' => $this->language->get('text_separator')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title_productlist'),
            'href' => $this->url->link('account/customerpartner/productlist'),
            'separator' => $this->language->get('text_separator')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_product'),
            'href' => $data['action'],
            'separator' => $this->language->get('text_separator')
        );

        $data['mp_allowproducttabs'] = array();
        $data['isMember'] = false;

        // membership codes starts here
        if ($this->config->get('module_wk_seller_group_status')) {
            $data['module_wk_seller_group_status'] = true;
            $this->load->model('account/customer_group');
            $isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
            if ($isMember) {
                $allowedProductTabs = $this->model_account_customer_group->getproductTab($isMember['gid']);
                if ($allowedProductTabs['value']) {
                    $allowedProductTab = explode(',', $allowedProductTabs['value']);
                    foreach ($allowedProductTab as $key => $tab) {
                        $ptab = explode(':', $tab);
                        $data['mp_allowproducttabs'][$ptab[0]] = $ptab[1];
                    }
                }
                $data['isMember'] = true;
            } else {
                $data['isMember'] = false;
            }
        } else {
            $data['mp_allowproducttabs'] = $this->config->get('marketplace_allowedproducttabs');
        }
        // membership codes ends here

        $data['marketplace_account_menu_sequence'] = $this->config->get('marketplace_account_menu_sequence');

        $data['marketplace_product_reapprove'] = false;
        if ($this->config->get('marketplace_product_reapprove')) {
            if (!$this->config->get('marketplace_productapprov')) {
                $data['marketplace_product_reapprove'] = ' As auto approve is not enabled, your product will be disabled for cross checking of changes.';
            }
        }

        $data['mp_allowproductcolumn'] = $this->config->get('marketplace_allowedproductcolumn');

        if (isset($this->request->get['product_id']) && (!request()->isMethod('POST'))) {
            $data['heading_title'] = $this->language->get('heading_title_update');
            $this->document->setTitle($this->language->get('heading_title_update'));

            // membership codes starts here
            if ($this->config->get('module_wk_seller_group_status') && (isset($this->request->get['clone']) || isset($this->request->post['clone']) && $this->request->post['clone'])) {
                $data['heading_title'] = $this->language->get('heading_title_clone');
                $this->document->setTitle($this->language->get('heading_title_clone'));
            }

            // membership codes ends here

            if (!$this->model_account_customerpartner->chkSellerProductAccess($this->request->get['product_id']))
                $data['access_error'] = true;
            else {
                $product_info = $this->model_account_customerpartner->getProduct($this->request->get['product_id']);
                if (!$product_info)
                    $data['access_error'] = true;
            }
        }

        if (isset($product_info) && $product_info) {
            $data['prevQuantity'] = $product_info['quantity'];
            $data['prevPrice'] = $product_info['price'];
            $data['price_display'] = $product_info['price_display'];
            $data['quantity_display'] = $product_info['quantity_display'];
        } else {
            $data['prevQuantity'] = 0;
            $data['prevPrice'] = 0;
            $data['price_display'] = 0;
            $data['quantity_display'] = 0;
        }

        if (isset($this->request->post['product_description'])) {
            $data['product_description'] = $this->request->post['product_description'];
        } elseif (isset($this->request->get['product_id'])) {
            $data['product_description'] = $this->model_account_customerpartner->getProductDescriptions($this->request->get['product_id']);
        } else {
            $data['product_description'] = array();
        }

        if (isset($this->request->post['model'])) {
            $data['model'] = $this->request->post['model'];
        } elseif (!empty($product_info)) {
            $data['model'] = $product_info['model'];
        } else {
            $data['model'] = '';
        }

        if (isset($this->request->post['sku'])) {
            $data['sku'] = $this->request->post['sku'];
        } elseif (!empty($product_info)) {
            $data['sku'] = $product_info['sku'];
        } else {
            $data['sku'] = '';
        }

        if (isset($this->request->post['upc'])) {
            $data['upc'] = $this->request->post['upc'];
        } elseif (!empty($product_info)) {
            $data['upc'] = $product_info['upc'];
        } else {
            $data['upc'] = '';
        }

        if (isset($this->request->post['ean'])) {
            $data['ean'] = $this->request->post['ean'];
        } elseif (!empty($product_info)) {
            $data['ean'] = $product_info['ean'];
        } else {
            $data['ean'] = '';
        }

        if (isset($this->request->post['jan'])) {
            $data['jan'] = $this->request->post['jan'];
        } elseif (!empty($product_info)) {
            $data['jan'] = $product_info['jan'];
        } else {
            $data['jan'] = '';
        }

        if (isset($this->request->post['isbn'])) {
            $data['isbn'] = $this->request->post['isbn'];
        } elseif (!empty($product_info)) {
            $data['isbn'] = $product_info['isbn'];
        } else {
            $data['isbn'] = '';
        }

        if (isset($this->request->post['mpn'])) {
            $data['mpn'] = $this->request->post['mpn'];
        } elseif (!empty($product_info)) {
            $data['mpn'] = $product_info['mpn'];
        } else {
            $data['mpn'] = '';
        }

        if (isset($this->request->post['location'])) {
            $data['location'] = $this->request->post['location'];
        } elseif (!empty($product_info)) {
            $data['location'] = $product_info['location'];
        } else {
            $data['location'] = '';
        }

        if (isset($this->request->post['keyword'])) {
            $data['keyword'] = $this->request->post['keyword'];
        } elseif (!empty($product_info)) {
            $data['keyword'][$this->config->get('config_store_id')] = $this->model_account_customerpartner->getProductKeyword($product_info['product_id']);
        } else {
            $data['keyword'] = '';
        }

        $data['current_store'] = (int)$this->config->get('config_store_id');

        if (isset($this->request->post['image'])) {
            $data['image'] = $this->request->post['image'];
        } elseif (!empty($product_info)) {
            $data['image'] = $product_info['image'];
        } else {
            $data['image'] = '';
        }

        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $this->load->model('tool/image');

        if (isset($this->request->post['image']) && $this->request->post['image'] && file_exists(DIR_IMAGE . $this->request->post['image'])) {
            $data['thumb_img'] = $this->request->post['image'];
            $data['thumb'] = $this->model_tool_image->resize($this->request->post['image'], 100, 100);
        } elseif (!empty($product_info) && $product_info['image'] && file_exists(DIR_IMAGE . $product_info['image'])) {
            $data['thumb_img'] = $product_info['image'];
            $data['thumb'] = $this->model_tool_image->resize($product_info['image'], 100, 100);
        } else {
            $data['thumb_img'] = '';
            $data['thumb'] = $this->model_tool_image->resize('imageUpload.png', 100, 100);
        }

        $data['placeholder'] = $this->model_tool_image->resize('imageUpload.png', 100, 100);

        if (isset($this->request->post['shipping'])) {
            $data['shipping'] = $this->request->post['shipping'];
        } elseif (!empty($product_info)) {
            $data['shipping'] = $product_info['shipping'];
        } else {
            $data['shipping'] = 1;
        }

        if (isset($this->request->post['price'])) {
            $data['price'] = $this->request->post['price'];
        } elseif (!empty($product_info)) {
            $data['price'] = $this->currency->convert($product_info['price'], $this->config->get('config_currency'), $this->session->data['currency']);
            $data['price'] = number_format($data['price'], 2, '.', '');
        } else {
            $data['price'] = '';
        }

        // membership code

        if (isset($this->request->post['expiry_date'])) {
            $data['expiry_date'] = $this->request->post['expiry_date'];
        } elseif (!empty($product_info) && isset($product_info['expiry_date'])) {
            $data['expiry_date'] = $product_info['expiry_date'];
        } else {
            $data['expiry_date'] = '';
        }

        if (isset($this->request->post['relist_duration'])) {
            $data['relist_duration'] = $this->request->post['relist_duration'];
        } elseif (!empty($product_info) && isset($product_info['relist_duration'])) {
            $data['relist_duration'] = $product_info['relist_duration'];
        } else {
            $data['relist_duration'] = '';
        }

        if (isset($this->request->post['auto_relist'])) {
            $data['auto_relist'] = $this->request->post['auto_relist'];
        } elseif (!empty($product_info) && isset($product_info['auto_relist'])) {
            $data['auto_relist'] = $product_info['auto_relist'];
        } else {
            $data['auto_relist'] = '';
        }

        if (isset($this->request->get['relist']) || isset($this->request->get['relist']) && $this->request->get['relist'] || isset($this->request->post['isRelist']) && $this->request->post['isRelist']) {
            $data['isRelist'] = true;
        } else {
            $data['isRelist'] = false;
        }

        if (isset($this->request->get['edit']) || isset($this->request->get['edit']) && $this->request->get['edit'] || isset($this->request->post['isEdit']) && $this->request->post['isEdit']) {
            $data['isEdit'] = true;
        } else {
            $data['isEdit'] = false;
        }


        //end membership code

        $this->load->model('mp_localisation/tax_class');

        $data['tax_classes'] = $this->model_mp_localisation_tax_class->getTaxClasses();

        if (isset($this->request->post['tax_class_id'])) {
            $data['tax_class_id'] = $this->request->post['tax_class_id'];
        } elseif (!empty($product_info)) {
            $data['tax_class_id'] = $product_info['tax_class_id'];
        } else {
            $data['tax_class_id'] = 0;
        }

        if (isset($this->request->post['date_available'])) {
            $data['date_available'] = $this->request->post['date_available'];
        } elseif (!empty($product_info)) {
            $data['date_available'] = date('Y-m-d', strtotime($product_info['date_available']));
        } else {
            $data['date_available'] = date('Y-m-d', time() - 86400);
        }

        if (isset($this->request->post['quantity'])) {
            $data['quantity'] = $this->request->post['quantity'];
        } elseif (!empty($product_info)) {
            $data['quantity'] = $product_info['quantity'];
        } else {
            $data['quantity'] = 0;
        }

        if (isset($this->request->post['minimum'])) {
            $data['minimum'] = $this->request->post['minimum'];
        } elseif (!empty($product_info)) {
            $data['minimum'] = $product_info['minimum'];
        } else {
            $data['minimum'] = 1;
        }

        if (isset($this->request->post['subtract'])) {
            $data['subtract'] = $this->request->post['subtract'];
        } elseif (!empty($product_info)) {
            $data['subtract'] = $product_info['subtract'];
        } else {
            $data['subtract'] = 1;
        }

        if (isset($this->request->post['sort_order'])) {
            $data['sort_order'] = $this->request->post['sort_order'];
        } elseif (!empty($product_info)) {
            $data['sort_order'] = $product_info['sort_order'];
        } else {
            $data['sort_order'] = 1;
        }

        $this->load->model('mp_localisation/stock_status');

        $data['stock_statuses'] = $this->model_mp_localisation_stock_status->getStockStatuses();

        if (isset($this->request->post['stock_status_id'])) {
            $data['stock_status_id'] = $this->request->post['stock_status_id'];
        } elseif (!empty($product_info)) {
            $data['stock_status_id'] = $product_info['stock_status_id'];
        } else {
            $data['stock_status_id'] = $this->config->get('config_stock_status_id');
        }

        if (isset($this->request->post['status'])) {
            $data['status'] = $this->request->post['status'];
        } elseif (!empty($product_info)) {
            $data['status'] = $product_info['status'];
        } else {
            $data['status'] = 1;
        }

        if (isset($this->request->post['weight'])) {
            $data['weight'] = sprintf("%.2f", $this->request->post['weight']);
        } elseif (!empty($product_info)) {
            $data['weight'] = sprintf("%.2f", $product_info['weight']);
        } else {
            $data['weight'] = '';
        }

        $this->load->model('mp_localisation/weight_class');

        $data['weight_classes'] = $this->model_mp_localisation_weight_class->getWeightClasses();

        if (isset($this->request->post['weight_class_id'])) {
            $data['weight_class_id'] = $this->request->post['weight_class_id'];
        } elseif (!empty($product_info)) {
            $data['weight_class_id'] = $product_info['weight_class_id'];
        } else {
            $data['weight_class_id'] = $this->config->get('config_weight_class_id');
        }

        if (isset($this->request->post['length'])) {
            $data['length'] = sprintf("%.2f", $this->request->post['length']);
        } elseif (!empty($product_info)) {
            $data['length'] = sprintf("%.2f", $product_info['length']);
        } else {
            $data['length'] = '';
        }

        if (isset($this->request->post['width'])) {
            $data['width'] = sprintf("%.2f", $this->request->post['width']);
        } elseif (!empty($product_info)) {
            $data['width'] = sprintf("%.2f", $product_info['width']);
        } else {
            $data['width'] = '';
        }

        if (isset($this->request->post['height'])) {
            $data['height'] = sprintf("%.2f", $this->request->post['height']);
        } elseif (!empty($product_info)) {
            $data['height'] = sprintf("%.2f", $product_info['height']);
        } else {
            $data['height'] = '';
        }

        $this->load->model('mp_localisation/length_class');

        $data['length_classes'] = $this->model_mp_localisation_length_class->getLengthClasses();

        if (isset($this->request->post['length_class_id'])) {
            $data['length_class_id'] = $this->request->post['length_class_id'];
        } elseif (!empty($product_info)) {
            $data['length_class_id'] = $product_info['length_class_id'];
        } else {
            $data['length_class_id'] = $this->config->get('config_length_class_id');
        }

        if (isset($this->request->post['manufacturer_id'])) {
            $data['manufacturer_id'] = $this->request->post['manufacturer_id'];
        } elseif (!empty($product_info)) {
            $data['manufacturer_id'] = $product_info['manufacturer_id'];
        } else {
            $data['manufacturer_id'] = 0;
        }

        if (isset($this->request->post['manufacturer'])) {
            $data['manufacturer'] = $this->request->post['manufacturer'];
        } elseif (!empty($product_info)) {
            $data['manufacturer'] = $product_info['manufacturer'];
        } else {
            $data['manufacturer'] = '';
        }

        // Categories
        $this->load->model('setting/store');
        $data['stores'] = $this->model_setting_store->getStores();

        $data['marketplace_seller_product_store'] = $this->config->get('marketplace_seller_product_store');

        if (isset($this->request->post['product_store'])) {
            $data['product_store'] = $this->request->post['product_store'];
        } elseif (isset($this->request->get['product_id'])) {
            $data['product_store'] = $this->model_account_customerpartner->getProductStores($this->request->get['product_id']);
        } else {
            $data['product_store'] = array(0);
        }

        $data['current_store'] = $this->config->get('config_store_id');

        if (isset($this->request->post['product_category'])) {
            $categories = $this->request->post['product_category'];
        } elseif (isset($this->request->get['product_id'])) {
            $categories = $this->model_account_customerpartner->getProductCategories($this->request->get['product_id']);
        } else {
            $categories = array();
        }

        $data['product_categories'] = array();

        foreach ($categories as $category_id) {
            $category_info = $this->model_account_customerpartner->getCategory($category_id);

            if ($category_info) {
                $data['product_categories'][] = array(
                    'category_id' => $category_info['category_id'],
                    'name' => ($category_info['path'] ? $category_info['path'] . ' &gt; ' : '') . $category_info['name']
                );
            }
        }

        // Filters

        if (isset($this->request->post['product_filter'])) {
            $filters = $this->request->post['product_filter'];
        } elseif (isset($this->request->get['product_id'])) {
            $filters = $this->model_account_customerpartner->getProductFilters($this->request->get['product_id']);
        } else {
            $filters = array();
        }

        $data['product_filters'] = array();

        foreach ($filters as $filter_id) {
            $filter_info = $this->model_account_customerpartner->getFilter($filter_id);

            if ($filter_info) {
                $data['product_filters'][] = array(
                    'filter_id' => $filter_info['filter_id'],
                    'name' => $filter_info['group'] . ' &gt; ' . $filter_info['name']
                );
            }
        }

        // Attributes
        if (isset($this->request->post['product_attribute'])) {
            $product_attributes = $this->request->post['product_attribute'];
        } elseif (isset($this->request->get['product_id'])) {
            $product_attributes = $this->model_account_customerpartner->getProductAttributes($this->request->get['product_id']);
        } else {
            $product_attributes = array();
        }

        $data['product_attributes'] = array();

        foreach ($product_attributes as $product_attribute) {

            if ($product_attribute) {
                $data['product_attributes'][] = array(
                    'attribute_id' => $product_attribute['attribute_id'],
                    'name' => $product_attribute['name'],
                    'product_attribute_description' => $product_attribute['product_attribute_description']
                );
            }
        }

        // Options
        if (isset($this->request->post['product_option'])) {
            $product_options = $this->request->post['product_option'];
        } elseif (isset($this->request->get['product_id'])) {
            $product_options = $this->model_account_customerpartner->getProductOptions($this->request->get['product_id']);
        } else {
            $product_options = array();
        }

        $data['product_options'] = array();

        foreach ($product_options as $product_option) {
            if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
                $product_option_value_data = array();

                foreach ($product_option['product_option_value'] as $product_option_value) {
                    $product_option_value_data[] = array(
                        'product_option_value_id' => $product_option_value['product_option_value_id'],
                        'option_value_id' => $product_option_value['option_value_id'],
                        'quantity' => $product_option_value['quantity'],
                        'subtract' => $product_option_value['subtract'],
                        'price' => number_format($this->currency->convert($product_option_value['price'], $this->config->get('config_currency'), $this->session->data['currency']), 2, '.', ''),
                        'price_prefix' => $product_option_value['price_prefix'],
                        'points' => $product_option_value['points'],
                        'points_prefix' => $product_option_value['points_prefix'],
                        'weight' => $product_option_value['weight'],
                        'weight_prefix' => $product_option_value['weight_prefix']
                    );
                }

                $data['product_options'][] = array(
                    'product_option_id' => $product_option['product_option_id'],
                    'product_option_value' => $product_option_value_data,
                    'option_id' => $product_option['option_id'],
                    'name' => $product_option['name'],
                    'type' => $product_option['type'],
                    'required' => $product_option['required']
                );
            } else {
                $data['product_options'][] = array(
                    'product_option_id' => $product_option['product_option_id'],
                    'option_id' => $product_option['option_id'],
                    'name' => $product_option['name'],
                    'type' => $product_option['type'],
                    'option_value' => $product_option['option_value'],
                    'required' => $product_option['required']
                );
            }
        }

        $data['option_values'] = array();

        foreach ($data['product_options'] as $product_option) {
            if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
                if (!isset($data['option_values'][$product_option['option_id']])) {
                    $data['option_values'][$product_option['option_id']] = $this->model_account_customerpartner->getOptionValues($product_option['option_id']);
                }
            }
        }

        $data['customer_groups'] = $this->model_account_customerpartner->getCustomerGroups();

        if (isset($this->request->post['product_discount'])) {
            $data['product_discounts'] = $this->request->post['product_discount'];
        } elseif (isset($this->request->get['product_id'])) {
            $data['product_discounts'] = $this->model_account_customerpartner->getProductDiscounts($this->request->get['product_id']);
        } else {
            $data['product_discounts'] = array();
        }

        foreach ($data['product_discounts'] as $key => $value) {

            $data['product_discounts'][$key]['price'] = number_format($this->currency->convert($value['price'], $this->config->get('config_currency'), $this->session->data['currency']), 2, '.', '');
        }


        if (isset($this->request->post['product_special'])) {
            $data['product_specials'] = $this->request->post['product_special'];
        } elseif (isset($this->request->get['product_id'])) {
            $data['product_specials'] = $this->model_account_customerpartner->getProductSpecials($this->request->get['product_id']);
        } else {
            $data['product_specials'] = array();
        }

        foreach ($data['product_specials'] as $key => $value) {

            $data['product_specials'][$key]['price'] = number_format($this->currency->convert($value['price'], $this->config->get('config_currency'), $this->session->data['currency']), 2, '.', '');
        }

        // Images
        if (isset($this->request->post['product_image'])) {
            $product_images = $this->request->post['product_image'];
        } elseif (isset($this->request->get['product_id'])) {
            $product_images = $this->model_catalog_product->getProductImages($this->request->get['product_id']);
        } else {
            $product_images = array();
        }

        $data['product_images'] = array();

        foreach ($product_images as $product_image) {
            if ($product_image['image'] && file_exists(DIR_IMAGE . $product_image['image'])) {
                $image = $product_image['image'];
                $thumg_img = $product_image['image'];
            } else {
                $image = 'no_image.jpg';
                $thumg_img = '';
            }

            $data['product_images'][] = array(
                'image' => $image,
                'thumg_img' => $thumg_img,
                'thumb' => $this->model_tool_image->resize($image, 100, 100),
                'sort_order' => $product_image['sort_order']
            );
        }

        $data['no_image'] = $this->model_tool_image->resize('no_image.png', 100, 100);

        // Downloads

        if (isset($this->request->post['product_download'])) {
            $product_downloads = $this->request->post['product_download'];
        } elseif (isset($this->request->get['product_id'])) {
            $product_downloads = $this->model_account_customerpartner->getProductDownloads($this->request->get['product_id']);
        } else {
            $product_downloads = array();
        }

        $data['product_downloads'] = array();

        foreach ($product_downloads as $download_id) {
            $download_info = $this->model_account_customerpartner->getDownloadProduct($download_id);

            if ($download_info) {
                $data['product_downloads'][] = array(
                    'download_id' => $download_info['download_id'],
                    'name' => $download_info['name']
                );
            }
        }


        if (isset($this->request->post['product_related'])) {
            $products = $this->request->post['product_related'];
        } elseif (isset($this->request->get['product_id'])) {
            $products = $this->model_account_customerpartner->getProductRelated($this->request->get['product_id']);
        } else {
            $products = array();
        }

        $data['product_relateds'] = array();
        foreach ($products as $product_id) {
            $related_info = $this->model_account_customerpartner->getProductRelatedInfo($product_id);

            if ($related_info) {
                $data['product_relateds'][] = array(
                    'product_id' => $related_info['product_id'],
                    'name' => $related_info['name']
                );
            }
        }

        $this->load->model('account/wkcustomfield');
        $wkcustomFields = array();
        $data['wkcustomFields'] = $this->model_account_wkcustomfield->getOptionList();
        if (isset($this->request->get['product_id']) || isset($this->request->post['product_custom_field'])) {
            if (isset($this->request->get['product_id'])) {
                $product_id = $this->request->get['product_id'];
            } else {
                $product_id = 0;
            }
            $data['wkPreCustomFields'] = array();
            $wkPreCustomFieldOptions = array();
            $wkPreCustomFields = $this->model_account_wkcustomfield->getProductFields($product_id);
            if (isset($this->request->post['product_custom_field'])) {
                foreach ($this->request->post['product_custom_field'] as $key => $value) {
                    if (!isset($wkPreCustomFields[$key])) {
                        $wkPreCustomFields[] = array(
                            'fieldId' => $value['custom_field_id'],
                            'fieldName' => $value['custom_field_name'],
                            'fieldType' => $value['custom_field_type'],
                            'fieldDescription' => $value['custom_field_des'],
                            'id' => '',
                            'isRequired' => $value['custom_field_is_required'],
                        );
                    }
                }
            }
            foreach ($wkPreCustomFields as $field) {
                $wkPreCustomFieldOptions = $this->model_account_wkcustomfield->getOptions($field['fieldId']);
                if ($field['fieldType'] == 'select' || $field['fieldType'] == 'checkbox' || $field['fieldType'] == 'radio') {
                    $wkPreCustomProductFieldOptions = $this->model_account_wkcustomfield->getProductFieldOptions($product_id, $field['fieldId'], $field['id']);
                } else {
                    $wkPreCustomProductFieldOptions = $this->model_account_wkcustomfield->getProductFieldOptionValue($product_id, $field['fieldId'], $field['id']);
                }
                $data['wkPreCustomFields'][] = array(
                    'fieldId' => $field['fieldId'],
                    'fieldName' => $field['fieldName'],
                    'fieldType' => $field['fieldType'],
                    'fieldDes' => $field['fieldDescription'],
                    'productFieldId' => $field['id'],
                    'isRequired' => $field['isRequired'],
                    'fieldOptions' => $wkPreCustomProductFieldOptions,
                    'preFieldOptions' => $wkPreCustomFieldOptions,
                );
            }
        }

        $customPost = array();

        if (isset($this->request->post['product_custom_field']) && $this->request->post['product_custom_field']) {
            foreach ($this->request->post['product_custom_field'] as $customwk) {
                if (isset($customwk['custom_field_value']) && $customwk['custom_field_value']) {
                    $customPost[$customwk['custom_field_id']] = $customwk['custom_field_value'];
                }
            }
        }

        $data['customPost'] = $customPost;

        if (isset($this->request->post['points'])) {
            $data['points'] = $this->request->post['points'];
        } elseif (!empty($product_info)) {
            $data['points'] = $product_info['points'];
        } else {
            $data['points'] = '';
        }

        $data['isMember'] = true;

        // membership codes starts here
        if ($this->config->get('module_wk_seller_group_status')) {
            $data['module_wk_seller_group_status'] = true;
            $this->load->model('account/customer_group');
            $isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
            if ($isMember) {
                $allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);
                if ($allowedAccountMenu['value']) {
                    $accountMenu = explode(',', $allowedAccountMenu['value']);
                    if ($accountMenu && !in_array('addproduct:addproduct', $accountMenu)) {
                        $data['isMember'] = false;
                    }
                }
            } else {
                $data['isMember'] = false;
            }
        } else {
            if (!is_array($this->config->get('marketplace_allowed_account_menu')) || !in_array('addproduct', $this->config->get('marketplace_allowed_account_menu'))) {
                $this->response->redirect($this->url->link('account/account', '', true));
            }
        }

        // membership codes ends here
        $data['category_required'] = 0;

        if ($this->config->get('marketplace_seller_category_required')) {
            $data['category_required'] = 1;
        }
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $data['separate_view'] = false;

        $data['separate_column_left'] = '';


        $ColorResult = $this->model_account_customerpartner->getColorCategories((int)$this->customer->getId());
        foreach ($ColorResult as $key => $result) {
            $data['colors'][] = array(
                'id' => $result['option_value_id'],
                'color' => $result['name']
            );
        }
        if (isset($this->request->post['color'])) {
            $data['color'] = $this->request->post['color'];
        } elseif (!empty($product_info)) {
            $data['color'] = $product_info['color'];
        } else {
            $data['color'] = '';
        }
//add by xxl 素材包展示
        if (isset($this->request->get['product_id'])) {
            $imageResult = $this->model_account_customerpartner->getProductPackageImage($this->request->get['product_id']);
            $fileResult = $this->model_account_customerpartner->getProductPackageFile($this->request->get['product_id']);
            $videoResult = $this->model_account_customerpartner->getProductPackageVideo($this->request->get['product_id']);
            foreach ($imageResult as $key => $result) {
                $data['imageResult'][] = array(
                    'id' => $result['product_package_image_id'],
                    'productId' => $result['product_id'],
                    'name' => $result['origin_image_name'] ?: $result['image_name'],
                    'path' => PRODUCT_PACKAGE . $result['image'],
                );
            }
            foreach ($fileResult as $key => $result) {
                $data['fileResult'][] = array(
                    'id' => $result['product_package_file_id'],
                    'productId' => $result['product_id'],
                    'name' => $result['origin_file_name'] ?: $result['file_name'],
                    'path' => PRODUCT_PACKAGE . $result['file'],
                );
            }
            foreach ($videoResult as $key => $result) {
                $data['videoResult'][] = array(
                    'id' => $result['product_package_video_id'],
                    'productId' => $result['product_id'],
                    'name' => $result['origin_video_name'] ?: $result['video_name'],
                    'path' => PRODUCT_PACKAGE . $result['video'],
                );
            }
        } else {
            $data['imageResult'] = '';
            $data['$fileResult'] = '';
            $data['$videoResult'] = '';
        }
//combo
        if (isset($this->request->get['product_id']) && $product_info['comboFlag'] == '1') {
            $comboInfos = $this->model_account_customerpartner->getComboProductByOrm($this->request->get['product_id'], 2);
            $data['comboInfos'] = $comboInfos;
            $data['comboFlag'] = $product_info['comboFlag'];
        }
//配件
        if (isset($this->request->get['product_id']) && $product_info['partFlag'] == '1') {
            $data['partFlag'] = $product_info['partFlag'];
        }
//多颜色
        if (isset($this->request->get['product_id'])) {
            $associateColors = $this->model_account_customerpartner->getColorAssociate($this->request->get['product_id']);
            $data['product_colors'] = $associateColors;
        }

        if (isset($this->request->post['allowedBuy'])) {
            $data['allowedBuy'] = $this->request->post['allowedBuy'];
        } elseif (!empty($product_info)) {
            $data['allowedBuy'] = $product_info['buyer_flag'];
        } else {
            $data['allowedBuy'] = 1;
        }

        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');

            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        }
        // productColorCheckUrl
        $data['product_color_check_url'] = $this->url->link('account/customerpartner/addproduct/checkColor');
        // product status enum
        $data['product_status'] = ProductStatus::getViewItems();

        // Select Product Groups
        $this->load->model('Account/Customerpartner/ProductGroup');
        $data['product_groups'] = $this->model_Account_Customerpartner_ProductGroup->getAllListAndNoPage([], $this->customer->getId());
        if (isset($this->request->get['product_id'])) {
            $selected_product_group_ids = $this->model_Account_Customerpartner_ProductGroup->getGroupIDsByProductIDs(
                $this->customer->getId(),
                [$this->request->get['product_id']]
            );
            $data['selected_product_group_ids'] = implode(',', $selected_product_group_ids);
        }

        $this->response->setOutput($this->load->view('account/customerpartner/addproduct', $data));
    }

    public function getOptions()
    {
        if (request()->isMethod('POST') && $this->request->post['value'] != '') {
            $this->load->language("account/customerpartner/wkcustomfield");
            $this->load->model("account/wkcustomfield");
            $options = array();
            $options = $this->model_account_wkcustomfield->getOptions($this->request->post['value']);
            $this->response->setOutput(json_encode($options));
        }
    }

    protected function validateForm()
    {

        foreach ($this->request->post['product_description'] as $language_id => $value) {
            if ((utf8_strlen($value['name']) < 3) || (utf8_strlen($value['name']) > 255)) {
                $this->error['name'][$language_id] = $this->language->get('error_name');
            }

            if ((utf8_strlen($value['meta_title']) < 3) || (utf8_strlen($value['meta_title']) > 255)) {
                $this->error['error_meta_title'][$language_id] = $this->language->get('error_meta_title');
            }
        }

        if ((utf8_strlen($this->request->post['model']) < 3) || (utf8_strlen($this->request->post['model']) > 64)) {
            $this->error['model'] = $this->language->get('error_model');
        }

        if ((!is_numeric($this->request->post['price']) || $this->request->post['price'] < 0) && (!is_numeric($this->request->post['quantity']) || $this->request->post['quantity'] < 0)) {
            $this->error['warning'] = $this->language->get('error_price_quantity');
        } else {
            if (!is_numeric($this->request->post['price']) || $this->request->post['price'] < 0) {
                $this->error['warning'] = $this->language->get('error_price');
            }

            if (!is_numeric($this->request->post['quantity']) || $this->request->post['quantity'] < 0) {
                $this->error['warning'] = $this->language->get('error_quantity');
            }
        }

        $data['mp_allowproducttabs'] = array();
        // membership codes starts here
        if ($this->config->get('module_wk_seller_group_status')) {
            $this->load->model('account/customer_group');

            $isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());

            if ($isMember) {
                $allowedProductTabs = $this->model_account_customer_group->getproductTab($isMember['gid']);

                if ($allowedProductTabs['value']) {
                    $allowedProductTab = explode(',', $allowedProductTabs['value']);
                    foreach ($allowedProductTab as $key => $tab) {
                        $ptab = explode(':', $tab);
                        $data['mp_allowproducttabs'][$ptab[0]] = $ptab[1];
                    }
                }
            }
        } else {
            $data['mp_allowproducttabs'] = $this->config->get('marketplace_allowedproducttabs');
        }
        // membership codes ends here

        if (!empty($data['mp_allowproducttabs']) && isset($data['mp_allowproducttabs']['links'])) {
            if ($this->config->get('marketplace_seller_category_required')) {
                if (!isset($this->request->post['product_category']) || !is_array($this->request->post['product_category']) || empty($this->request->post['product_category'])) {
                    $this->error['category'] = $this->language->get('error_category');
                }
            }
        }

        if (isset($this->request->post['keyword']) && $this->request->post['keyword']) {
            foreach ($this->request->post['keyword'][(int)$this->config->get('config_store_id')] as $language_id => $value) {
                if (utf8_strlen($value) > 0) {
                    $url_alias_info = $this->model_account_customerpartner->getUrlAlias($value, $language_id);

                    if ($url_alias_info && isset($this->request->get['product_id']) && $url_alias_info['query'] != 'product_id=' . $this->request->get['product_id']) {
                        $this->error['keyword'] = sprintf($this->language->get('error_keyword'));
                    }

                    if ($url_alias_info && !isset($this->request->get['product_id'])) {
                        $this->error['keyword'] = sprintf($this->language->get('error_keyword'));
                    }
                }
            }
        }

        if (isset($this->request->post['product_image']) && $this->request->post['product_image'] && ((count($this->request->post['product_image'])) > (int)$this->config->get('marketplace_noofimages'))) {

            $this->error['warning'] = $this->language->get('error_no_of_images') . $this->config->get('marketplace_noofimages');
        }

        $customfielddata = array();
        if (isset($this->request->post['product_custom_field'])) {
            $customfielddata = $this->request->post['product_custom_field'];
        }
        foreach ($customfielddata as $key => $value) {
            if (isset($value['custom_field_is_required']) && (($value['custom_field_is_required'] == 'yes' && isset($value['custom_field_value']) && $value['custom_field_value'][0] == '') || ($value['custom_field_is_required'] == 'yes' && !isset($value['custom_field_value'])))) {
                $this->error['customFieldError'][] = $value['custom_field_id'];
            }
        }
        if (isset($this->error['customFieldError'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }
        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        /**
         * membership code starts here.
         * @var [type]
         */

        if ($this->config->get('wk_seller_group_status')) {

            /**
             * unset membership array so that fresh check can be done on it and save it for later use
             * @var [type]
             */
            if (isset($this->session->data['membership_array'])) {
                $this->session->remove('membership_array');
            }
            if (isset($this->session->data['membership_original'])) {
                $this->session->remove('membership_original');
            }

            $this->load->model('account/wk_membership_catalog');
            $this->load->model('account/customerpartner');
            $this->load->language('account/customerpartner/wk_membership_catalog');

            if (isset($this->request->get['product_id'])) {
                $this->request->get['product_id'] = (int)$this->request->get['product_id'];
            }

            if (isset($this->request->get['product_id']) && !$this->model_account_customerpartner->chkSellerProductAccess($this->request->get['product_id'])) {
                $this->response->redirect('account/customerpartner/addproduct', '&product_id=' . $this->request->get['product_id'], true);
            }

            $seller_id = $this->customer->getId();

            /**
             * set product category empty if no category is selected.
             * @var [type]
             */
            if (isset($this->request->post['product_category']) && is_array($this->request->post['product_category']) && !empty($this->request->post['product_category'])) {
                $category_id = $this->request->post['product_category'];
            } else {
                $category_id[] = 0;
            }

            /**
             * find listing duration for a product.
             * @var array
             */
            $listing_durations = array();

            foreach ($category_id as $key => $value) {
                $listing_durations[] = $this->model_account_wk_membership_catalog->getRemainingListingDuration($this->customer->getId(), $value);
            }

            foreach ($listing_durations as $listing_duration) {
                if (!$listing_duration) {
                    $this->error['warning'] = $this->language->get('error_relist');
                    break;
                }
            }

            if (!isset($this->request->post['relist_duration']) || $this->request->post['relist_duration'] > min($listing_durations)) {
                $this->error['warning'] = $this->language->get('error_relist_bypass');
            }
            if ($this->config->get('wk_seller_group_membership_type') == 'quantity') {

                /**
                 * forcefully enable subtract stock when membership is working according to quantity and price
                 */
                $this->request->post['subtract'] = true;

                if (isset($this->request->get['product_id'])) {
                    $product_info = $this->model_account_customerpartner->getProduct($this->request->get['product_id']);
                    $this->request->post['prevQuantity'] = $product_info['quantity'];
                    $this->request->post['prevPrice'] = $product_info['price'];
                } else {
                    $this->request->post['prevQuantity'] = 0;
                    $this->request->post['prevPrice'] = 0;
                }

                if (isset($this->request->post['clone']) && $this->request->post['clone']) {
                    $quantity = $this->request->post['quantity'];
                } else if ($this->request->post['quantity'] > $this->request->post['prevQuantity']) {
                    $quantity = $this->request->post['quantity'] - $this->request->post['prevQuantity'];
                } else {
                    $quantity = $this->request->post['prevQuantity'];
                }

                if (isset($this->request->post['clone']) && $this->request->post['clone']) {
                    $price = $this->request->post['price'];
                } else if ($this->request->post['price'] > $this->request->post['prevPrice']) {
                    $price = $this->request->post['price'] - $this->request->post['prevPrice'];
                } else {
                    $price = $this->request->post['prevPrice'];
                }

                foreach ($category_id as $key => $value) {
                    $check[] = $this->model_account_wk_membership_catalog->checkAvailabilityToAdd($quantity, $seller_id, $price, $value, $this->request->post);
                }

            } elseif ((isset($this->request->post['edit']) && $this->request->post['edit']) || (isset($this->request->post['relist']) && $this->request->post['relist'])) {
                foreach ($category_id as $key => $value) {
                    $check[] = $this->model_account_wk_membership_catalog->checkAvailabilityProductToAdd($value, $this->request->post, $seller_id);
                }
            } else {
                foreach ($category_id as $key => $value) {
                    $check[] = $this->model_account_wk_membership_catalog->checkAvailabilityProductToAdd($value, array(), $seller_id);
                }
            }

            if (isset($check) && is_array($check)) {
                $result = $check;
                if (in_array('', $check)) {
                    $result = '';
                    $this->error['category'] = str_replace('{link}', $this->url->link('account/customerpartner/wk_membership_catalog', '', TRUE), $this->language->get('error_insufficient_cat'));
                }
            }

            if ($result) {
                if ($this->error) {
                    return false;
                } else {
                    return $this->membershipData['remain'] = $result;
                }
            } else {
                $this->error['warning'] = " Warning: " . $this->language->get('error_insufficient_bal');
            }
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

    public function getcategories()
    {
        $json = array();

        if (isset($this->request->get['category_id'])) {
            $this->load->model('account/customerpartner');
            $results = $this->model_account_customerpartner->getCategoryByParentCategoryId(request('category_id',0));
            if ($results) {
                $json['categories'] = $results;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    //add by xxli
    public function addProductsByTemplate()
    {
        // 获取当前登录用户
        if ($this->customer->isLogged()) {
            // 检查文件名以及文件类型
            if (isset($this->request->files['file']['name'])) {
                if (substr($this->request->files['file']['name'], -4) != '.csv') {
                    $json['error'] = "Invalid file type!";
                }
                if ($this->request->files['file']['error'] != UPLOAD_ERR_OK) {
                    $json['error'] = $this->language->get('error_upload_' . $this->request->files['file']['error']);
                    goto end;
                }
            } else {
                if (isset($GLOBALS['php_errormsg' . "\0" . ''])) {
                    $json['error'] = $GLOBALS['php_errormsg' . "\0" . ''];
                } else {
                    $json['error'] = $this->language->get('error_upload');
                }
                goto end;
            }


            // 检查文件短期之内是否重复上传(首次提交文件后5s之内不能提交文件)
            $files = glob(DIR_UPLOAD . '*.tmp');
            foreach ($files as $file) {
                if (is_file($file) && (filectime($file) < (time() - 5))) {
                    unlink($file);
                }

                if (is_file($file)) {
                    $json['error'] = $this->language->get('error_install');
                    break;
                }
            }

            // 获取登录用户信息
            $customer = $this->customer;
            $customer_id = $customer->getId();
            // 上传订单文件，以用户ID进行分类
            $dir_upload = DIR_PRODUCT_UPLOAD . $customer_id . "/";
            if (!is_dir($dir_upload)) {
                mkdir(iconv("UTF-8", "GBK", $dir_upload), 0777, true);
            }
            if (!isset($json['error'])) {
                // 复制文件到Upload文件夹下
                session()->set('install', token(10));
                $file = DIR_UPLOAD . session('install') . '.tmp';
                move_uploaded_file($this->request->files['file']['tmp_name'], $file);
                // 复制上传的文件到orderCSV路径下
                $run_id = time();
                $uploadFile = $dir_upload . $run_id . ".csv";
                copy($file, $uploadFile);

                $csvDatas = $this->csv_get_lines($uploadFile);
                // 检查CSV的读取是否正确
                $csvHeader = array(
                    'Item Code',
                    'Product Name',
                    'Length(Inches)',
                    'Width(Inches)',
                    'Height(Inches)',
                    'Weight(Pounds)'
                );
                if (isset($csvDatas['keys']) && $csvDatas['keys'] == $csvHeader) {
                    // CSV读取到的产品数据
                    $csvDataValues = $csvDatas['values'];
                    //产品数组
                    $productArr = array();
                    //itenCode数组 用于重复性校验
                    $itemCodeArr = array();
                    $itemCodeArray = array();
                    if (isset($csvDataValues) && count($csvDataValues) > 0) {
                        $index = 0;
                        foreach ($csvDataValues as $csvData) {
                            $index++;
                            if (!isset($csvData['Item Code']) || $csvData['Item Code'] == '') {
                                continue;
                            } else {
                                $itemCodeArr[] = array(
                                    'ItemCode' => $csvData['Item Code']
                                );
//                                   $itemCodeArray[] = array(
//                                       $csvData['Item Code']
//								   );
                                array_push($itemCodeArray, $csvData['Item Code']);
                                $productArr[] = array(
                                    'ItemCode' => $csvData['Item Code'],
                                    'ProductName' => $csvData['Product Name'],
                                    'Length(Inches)' => $csvData['Length(Inches)'],
                                    'Width(Inches)' => $csvData['Width(Inches)'],
                                    'Height(Inches)' => $csvData['Height(Inches)'],
                                    'Weight(Pounds)' => $csvData['Weight(Pounds)'],
                                );
                            }
                        }
                        //校验该Seller的Item Code 是否存在
                        $this->load->model('account/customerpartner');
                        $selfSupport = $this->model_account_customerpartner->getSelfSupportByCustomerId($customer_id);
                        $ExistCount = $this->model_account_customerpartner->getItemCodeExist($itemCodeArr, $customer_id, $selfSupport['self_support']);
                        if ($ExistCount) {
                            if ($ExistCount['countNum'] > 0) {
                                $json['error'] = "Add failed, the Seller's Item Code already exists!";
                                goto end;
                            }
                        }
                        //校验CSV文件内Item Code重复
                        $number1 = count($itemCodeArray);
                        $number2 = count(array_unique($itemCodeArray));
                        if ($number1 != $number2) {
                            $json['error'] = "Duplicate Item Code in CSV file!";
                            goto end;
                        }

                        //插入product
                        $this->model_account_customerpartner->addProductByTemplate($productArr, $customer_id, $selfSupport['self_support']);
                    }
                } else {
                    // 上传的CSV内容格式与模板格式不符合！
                    $json['error'] = 'The content of the uploaded CSV file is incorrect.';
                    goto end;
                }
            }

        }
        if (!isset($json['error'])) {
            $json['text'] = 'Saved successfully.';
        }
        end:
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * csv_get_lines 读取CSV文件中的某几行数据
     * @param $csvFile CSV文件
     * @param int $offset 起始行数
     * @return array
     */
    function csv_get_lines($csvFile, $offset = 0)
    {
        if (!$fp = fopen($csvFile, 'r')) {
            return false;
        }
        $i = $j = 0;
        $line = null;
        while (false !== ($line = fgets($fp))) {
            if ($i++ < $offset) {
                continue;
            }
            break;
        }
        $data = array();
        while (!feof($fp)) {
            $data[] = fgetcsv($fp);
        }
        fclose($fp);
        $values = array();
        $line = preg_split("/,/", $line);
        $keys = array();
        $flag = true;
        foreach ($data as $d) {
            $entity = array();
            for ($i = 0; $i < count($line); $i++) {
                if ($i < count($d)) {
                    $entity[trim($line[$i])] = $d[$i];
                    if ($flag) {
                        $keys[] = trim($line[$i]);
                    }
                }
            }
            if ($flag) {
                $flag = false;
            }
            $values[] = $entity;
        }
        $result = array(
            "keys" => $keys,
            "values" => $values
        );
        return $result;
    }


    function uploadImages()
    {
        // 运行时间
        $run_id = time();
        // 登录用户ID
        $customer_id = $this->customer->getId();
        $this->load->language('account/customerpartner/addproduct');
        $this->load->model('account/customerpartner');
        $productId = '';
//        if ((request()->isMethod('POST')) AND $this->validateFormNew()) {
        if ((request()->isMethod('POST'))) {
            trim_strings($this->request->post);
            if ($this->config->get('module_wk_seller_group_status')) {
                if ($this->membershipData && isset($this->membershipData['remain'])) {
                    $this->load->model('account/wk_membership_catalog');
                    $this->model_account_wk_membership_catalog->insertInPay($this->membershipData['remain']);
                }
            }
            if ($this->request->post['product_group_ids'] == 'null') {
                $this->request->post['product_group_ids'] = '';
            }
            $this->request->post['product_group_ids'] = isset_and_not_empty($this->request->post, 'product_group_ids') ?
                explode(',', $this->request->post['product_group_ids']) : [];
            // $this->request->post['product_group_ids'] = [];
            $this->request->post['seller_id'] = $this->customer->getId();
            if (isset($this->request->post['clone']) && $this->request->post['clone']) {
                $result = $this->model_account_customerpartner->addProduct($this->request->post);
                //14103 所有上过架的产品都可以被检索到，如果产品已失效，则在检索结果页和商品详情页标注出商品无效的原因
                if ($this->request->post['status1'] == 1) {
                    //更新oc_product表中是否上架的字段
                    $this->orm->table(DB_PREFIX . 'product')->where('product_id', $result)->update(['is_once_available' => 1]);
                }
                session()->set('success', $this->language->get('text_success_add'));
            } else if (!isset($this->request->get['product_id'])) {
                $result = $this->model_account_customerpartner->addProduct($this->request->post);
                //14103 所有上过架的产品都可以被检索到，如果产品已失效，则在检索结果页和商品详情页标注出商品无效的原因
                if ($this->request->post['status1'] == 1) {
                    $this->orm->table(DB_PREFIX . 'product')->where('product_id', $result)->update(['is_once_available' => 1]);
                }
                session()->set('success', $this->language->get('text_success_add'));
            } else {
                // 获取产品id status 和 现在的status 比较得出是上架还是下架
                // 14086  库存订阅列表中的产品上下架提醒
                // 验证是否进行了status 的变更
                // status与oc_product中的 状态相同则不发站内信 相同则发站内信。
                $result = $this->model_account_customerpartner->verifyProductStatus($this->request->get['product_id'], $this->request->post['status1']);
                if ($result)
                    $this->model_account_customerpartner->sendProductionInfoToBuyer($this->request->get['product_id'], $customer_id, $this->request->post['status1']);
                $this->model_account_customerpartner->editProduct($this->request->post);
                //14103 所有上过架的产品都可以被检索到，如果产品已失效，则在检索结果页和商品详情页标注出商品无效的原因
                if ($this->request->post['status1'] == 1) {
                    $this->orm->table(DB_PREFIX . 'product')->where('product_id', $this->request->get['product_id'])->update(['is_once_available' => 1]);
                }
                session()->set('success', $this->language->get('text_success_update'));
            }

            if (!isset($this->request->get['product_id'])) {
                $productId = $result;
            } else {
                $productId = $this->request->get['product_id'];
            }
            $files = $this->request->files;
            $index = 0;
            foreach ($files as $key => $result) {
                $index++;
                if (strstr($key, 'image')) {
                    //是否存在路径
                    // 路径前缀
                    $path_prefix = DIR_PRODUCT_PACKAGE . "/" . $customer_id . "/" . $productId . "/image/";
                    if (!file_exists($path_prefix)) {
                        mkdir($path_prefix, 0777, true);
                    }
                    //文件后缀如果获取不到，则为空。取值类似于".png"
                    $file_extension = strripos($result['name'], '.') ? substr($result['name'], strripos($result['name'], '.')) : ''; // ''|.png
                    $new_file_name = $run_id . '_' . $index . $file_extension;   // 新的文件名
                    $real_file_path = $path_prefix . $new_file_name;    // 保存文件的真实路径
                    move_uploaded_file($result['tmp_name'], $real_file_path);

                    // 相对路径
                    $relative_file_path = $customer_id . "/" . $productId . "/image/" . $new_file_name;
                    $origin_file_name = $result['name'];    // 原始的文件名称
                    $this->model_account_customerpartner->addProductPackageImage($relative_file_path, $productId, $new_file_name, $origin_file_name);
                } elseif (strstr($key, 'file')) {
                    //是否存在路径
                    $path_prefix = DIR_PRODUCT_PACKAGE . "/" . $customer_id . "/" . $productId . "/file/";
                    if (!file_exists($path_prefix)) {
                        mkdir($path_prefix, 0777, true);
                    }
                    $file_extension = strripos($result['name'], '.') ? '' : substr($result['name'], strripos($result['name'], '.'));
                    $new_file_name = $run_id . '_' . $index . $file_extension;
                    $real_file_path = $path_prefix . $new_file_name;
                    move_uploaded_file($result['tmp_name'], $real_file_path);

                    $relative_file_path = $customer_id . "/" . $productId . "/file/" . $new_file_name;
                    $origin_file_name = $result['name'];
                    $this->model_account_customerpartner->addProductPackageFile($relative_file_path, $productId, $new_file_name, $origin_file_name);
                } elseif (strstr($key, 'video')) {
                    //video 上传
                    //是否存在路径
                    $path_prefix = DIR_PRODUCT_PACKAGE . "/" . $customer_id . "/" . $productId . "/video/";
                    if (!file_exists($path_prefix)) {
                        mkdir($path_prefix, 0777, true);
                    }
                    $file_extension = strripos($result['name'], '.') ? '' : substr($result['name'], strripos($result['name'], '.'));
                    $new_file_name = $run_id . '_' . $index . $file_extension;
                    $real_file_path = $path_prefix . $new_file_name;
                    move_uploaded_file($result['tmp_name'], $real_file_path);

                    $relative_file_path = $customer_id . "/" . $productId . "/video/" . $new_file_name;
                    $origin_file_name = $result['name'];
                    $this->model_account_customerpartner->addProductPackageVideo($relative_file_path, $productId, $new_file_name, $origin_file_name);
                }
            }

            //video 文件创建
            $json1['text'] = 'Saved successfully.';
            $json1['info'] = $productId;
        } else {
            $json1['error'] = $this->error;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json1));
    }


    protected function validateFormNew()
    {

        if (utf8_strlen($this->request->post['mpn']) < 3 || utf8_strlen($this->request->post['mpn']) > 255) {
            $this->error['mpn'] = $this->language->get('error_mpn');
        }
        if (utf8_strlen($this->request->post['product_description'][1]['name']) < 3 || utf8_strlen($this->request->post['product_description'][1]['name']) > 255) {
            $this->error['product_description'] = $this->language->get('error_name');
        }


        if ($this->request->post['length'] == '' || $this->request->post['width'] == '' || $this->request->post['height'] == '') {
            $this->error['dimensions '] = $this->language->get('error_dimensions');
        }


        if ($this->request->post['weight'] == '') {
            $this->error['weight '] = $this->language->get('error_weight');
        }

        if (!$this->error) {
            return true;
        } else {
            $this->error['warning '] = $this->language->get('error_warning');
            return false;
        }
    }

    public function deletePackageImage()
    {
        $this->load->model('account/customerpartner');
        $this->request;
        $this->model_account_customerpartner->deletePackageImage($this->request->request['id']);
        //删除服务器文件
        unlink(DIR_WORKSPACE . $this->request->request['path']);
    }

    public function deletePackageFile()
    {
        $this->load->model('account/customerpartner');
        $this->request;
        $this->model_account_customerpartner->deletePackageFile($this->request->request['id']);
        //删除服务器文件
        unlink(DIR_WORKSPACE . $this->request->request['path']);
    }


    public function deletePackageVideo()
    {
        $this->load->model('account/customerpartner');
        $this->request;
        $this->model_account_customerpartner->deletePackageVideo($this->request->request['id']);
        //删除服务器文件
        unlink(DIR_WORKSPACE . $this->request->request['path']);
    }


    public function addVideo()
    {
        $run_id = $_POST['runTime'];
        $productId = $_POST['productId'];
        // 登录用户ID
        $customer_id = $this->customer->getId();
        $dir1 = iconv('UTF-8', 'UTF-8', $_POST['filename']);

        //设置文件大小不超过100MB
        //$max_size=1446861248382;
        //允许上传的文件扩展名
        $file_type = array('.flv', '.wmv', '.rmvb', '.mp4', '.avi', '.MP4');

        $filetype = '.' . substr(strrchr($dir1, "."), 1);

        if (!in_array($filetype, $file_type)) {
            echo "none";
            return false;
            die;
        }
        $dir = DIR_PRODUCT_PACKAGE . "/" . $customer_id . "/" . $productId . "/video/" . md5($dir1);
        file_exists($dir) or mkdir($dir, 0777, true);

        $path = $dir . "/" . $_POST['blobname'];

        move_uploaded_file($_FILES["file"]["tmp_name"], $path);
        if (isset($_POST['lastone'])) {
            echo $_POST['lastone'];
            $count = $_POST['lastone'];

            $fp = fopen(DIR_PRODUCT_PACKAGE . "/" . $customer_id . "/" . $productId . "/video/" . $run_id . $dir1, "abw");
            for ($i = 0; $i <= $count; $i++) {
                $handle = fopen($dir . "/" . $i, "rb");
                fwrite($fp, fread($handle, filesize($dir . "/" . $i)));
                fclose($handle);
                //删除切片文件
                @unlink($dir . "/" . $i);
            }
            fclose($fp);
            if (file_exists(md5($dir1))) {
                echo '上传完成';

            }
            @rmdir($dir);
            $this->load->model('account/customerpartner');
            $this->model_account_customerpartner->addProductPackageVideo($customer_id . "/" . $productId . "/video/" . $run_id . $dir1, $productId, $run_id . $dir1);
        }
    }

    public function checkMpn()
    {
        $mpn = $this->request->request['mpn'];
        if (isset($this->request->request['id'])) {
            $id = $this->request->request['id'];
        }
        $this->load->model('account/customerpartner');
        /** @var ModelAccountCustomerpartner $modelAccountCustomerpartner */
        $modelAccountCustomerpartner = $this->model_account_customerpartner;
        if (isset($id)) {
            $result = $modelAccountCustomerpartner->checkMpn($mpn, 2);
        } else {
            $result = $modelAccountCustomerpartner->checkMpnNoComboFlag($mpn, 2);
        }
        if ($result) {
            if ($result['combo_flag'] == 0) {
                $json['success'] = true;
                $json['length'] = $result['length'];
                $json['width'] = $result['width'];
                $json['height'] = $result['height'];
                $json['weight'] = $result['weight'];
                if (isset($id)) {
                    $json['id'] = $id;
                }
            } else {
                $json['success'] = false;
                $json['msg'] = 'This MPN is Combo product cannot be added to details.';
                if (isset($id)) {
                    $json['id'] = $id;
                }
            }
        } else {
            $json['success'] = false;
            $json['msg'] = 'This MPN does not exist on the B2B.GIGACLOUDLOGISTICS.';
            if (isset($id)) {
                $json['id'] = $id;
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // region 多颜色关联查看
    public function associate()
    {
        $filter_search = get_value_or_default($this->request->request, 'filter_search', '');
        $product_id = get_value_or_default($this->request->request, 'product_id', null);
        $this->load->model('account/customerpartner');
        /** @var ModelAccountCustomerpartner $modelAccountCustomerpartner */
        $modelAccountCustomerpartner = $this->model_account_customerpartner;
        $results = $modelAccountCustomerpartner->associate($filter_search, $product_id);
        if ($results) {
            $json['colors'] = $results;
        } else {
            $json['colors'] = null;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    // endregion

    /**
     * 下载上传视频文件
     */
    public function downloadTemplateFile()
    {
        // 判断用户是否登录
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customer_order/downloadTemplateFile', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $data = array();
        $this->response->setOutput($this->load->view('account/customerpartner/addproduct_temp', $data));
    }

    public function checkMpnNoComboFlag()
    {
        $mpn = $this->request->request['mpn'];
        if (isset($this->request->request['id'])) {
            $id = $this->request->request['id'];
        }
        $this->load->model('account/customerpartner');
        if (in_array($this->customer->getId(), $this->config->get('config_customer_group_ignore_check'))) {
            $json['success'] = false;
        } else {
            if (isset($id)) {
                $result = $this->model_account_customerpartner->checkMpn($mpn);
            } else {
                $result = $this->model_account_customerpartner->checkMpnNoComboFlag($mpn);
            }
            if ($result) {
                $json['success'] = true;
                $json['length'] = $result['length'];
                $json['width'] = $result['width'];
                $json['height'] = $result['height'];
                $json['weight'] = $result['weight'];
                if (isset($id)) {
                    $json['id'] = $id;
                }
            } else {
                $json['success'] = false;
                $json['msg'] = 'This MPN does not exist on the B2B.GIGACLOUDLOGISTICS.';
                if (isset($id)) {
                    $json['id'] = $id;
                }
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // region checkColor
    public function checkColor()
    {
        $this->response->addHeader('Content-Type: application/json');
        $post = new \Illuminate\Support\Collection($this->request->post);
        $this->load->model('catalog/product');
        /** @var ModelCatalogProduct $modelCatalogProduct */
        $modelCatalogProduct = $this->model_catalog_product;
        $productId = $post->get('id', 0);
        $productInfo = $modelCatalogProduct->getProduct($productId);
        $productColorInfo = $modelCatalogProduct->getProductColor($post->get('id', 0), (int)$this->customer->getId());
        $ret = [
            'code' => !empty($productColorInfo['color']) ? 0 : 1,
            'sku' => $productInfo['sku'] ?? '',
        ];
        $this->response->setOutput(json_encode($ret));
    }
    // end region
}

?>
