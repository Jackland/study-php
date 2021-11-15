<?php

/**
 * @property ModelCustomerpartnerMail $model_customerpartner_mail
 * @property ModelCustomerpartnerPartner $model_customerpartner_partner
 * @property ModelLocalisationLanguage $model_localisation_language
 * @property ModelLocalisationOrderStatus $model_localisation_order_status
 * @property ModelSettingEvent $model_setting_event
 * @property ModelSettingExtension $model_setting_extension
 * @property ModelSettingSetting $model_setting_setting
 * @property ModelSettingStore $model_setting_store
 * @property ModelUserUserGroup $model_user_user_group
 * @property ModelWkcustomfieldWkcustomfield $model_wkcustomfield_wkcustomfield
 * @property ModelToolImage $model_tool_image
 */
class ControllerExtensionModuleMarketplace extends Controller {

	private $error = array();
	private $files_array = array();

	public function adminmenu(){

		$marketplace = array();

		if ($this->config->get('module_marketplace_status')) {

				$this->load->language('extension/module/marketplace');

				if ($this->user->hasPermission('access', 'customerpartner/partner')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_partner'),
								'href'     => $this->url->link('customerpartner/partner', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/commission')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_commission'),
								'href'     => $this->url->link('customerpartner/commission', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/product')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_product'),
								'href'     => $this->url->link('customerpartner/product', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/sellercategory')) {
				    $marketplace[] = array(
				        'name'     => $this->language->get('menu_text_seller_categories'),
				        'href'     => $this->url->link('customerpartner/sellercategory', 'user_token=' . session('user_token'), true),
				        'children' => array()
				    );
				}

				if ($this->user->hasPermission('access', 'customerpartner/category')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_categories'),
								'href'     => $this->url->link('customerpartner/category', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/categorymapping')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_categories_mapping'),
								'href'     => $this->url->link('customerpartner/categorymapping', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/order')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_order'),
								'href'     => $this->url->link('customerpartner/order', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/income')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_income'),
								'href'     => $this->url->link('customerpartner/income', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/transaction')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_transaction'),
								'href'     => $this->url->link('customerpartner/transaction', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/shipping')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_shipping'),
								'href'     => $this->url->link('customerpartner/shipping', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}
				if($this->config->get('module_wk_communication_status')){
					if ($this->user->hasPermission('access', 'communication/wk_communication')) {
						$marketplace[] = array(
							'name'     => $this->language->get('text_wk_communication'),
							'href'     => $this->url->link('account/wk_communication', 'user_token=' . session('user_token'), true),
							'children' => array()
						);
					}
				}
				if ($this->user->hasPermission('access', 'customerpartner/mails')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_mail'),
								'href'     => $this->url->link('customerpartner/mails', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/review')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_seller_review'),
								'href'     => $this->url->link('customerpartner/review', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/reviewfield')) {
						$marketplace[] = array(
							'name'     => $this->language->get('text_review_field'),
							'href'     => $this->url->link('customerpartner/reviewfield', 'user_token=' . session('user_token'), true),
							'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/notification')) {
						$marketplace[] = array(
						'name'     => $this->language->get('text_notifications'),
						'href'     => $this->url->link('customerpartner/notification', 'user_token=' . session('user_token'), true),
						'children' => array()
						);
				}

				if ($this->user->hasPermission('access', 'customerpartner/information')) {
					$marketplace[] = array(
							'name'     => $this->language->get('menu_text_information'),
							'href'     => $this->url->link('customerpartner/information', 'user_token=' . session('user_token'), true),
							'children' => array()
					);
				}

				if ($this->user->hasPermission('access', 'wkcustomfield/wkcustomfield')) {
						$marketplace[] = array(
								'name'     => $this->language->get('text_custom_field'),
								'href'     => $this->url->link('wkcustomfield/wkcustomfield', 'user_token=' . session('user_token'), true),
								'children' => array()
						);
				}
				return $marketplace;
		}
	}

	public function install() {
		$this->load->model('customerpartner/partner');
		$this->model_customerpartner_partner->createCustomerpartnerTable();

		$marketplaceFolders = array(
           'customerpartner',
		   'shipping',
		   'wkcustomfield',
		);

       	$files = array();

		// Make path into an array
		foreach ($marketplaceFolders as $key => $folder) {
		    $path[] = DIR_APPLICATION . 'controller/'. $folder;
		}

		// While the path array is still populated keep looping through
		while (count($path) != 0) {
			$next = array_shift($path);

			foreach (glob($next) as $file) {
				// If directory add to path array
				if (is_dir($file)) {
					$path[] = $file . '/*';
				}

				// Add the file to the files to be deleted array
				if (is_file($file)) {
					$files[] = $file;
				}
			}
		}

		// Sort the file array
		sort($files);

		$this->load->model('user/user_group');
		foreach ($files as $file) {
			$controller = substr($file, strlen(DIR_APPLICATION . 'controller/'));

			$permission = substr($controller, 0, strrpos($controller, '.'));

			$this->model_user_user_group->addPermission($this->user->getId(),'access',$permission);
			$this->model_user_user_group->addPermission($this->user->getId(),'modify',$permission);
		}
		$this->load->model('setting/event');

		$this->model_setting_event->addEvent('webkul_marketplace', 'catalog/model/checkout/order/deleteOrder/after','event/marketplace/deleteMPOrder');
	}

	public function uninstall() {
		$this->load->model('customerpartner/partner');

		$this->model_customerpartner_partner->removeCustomerpartnerTable();

		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode('webkul_marketplace');
	}

	public function getdir($controller_path = ''){

		$copy = $controller_path;
		$path = DIR_CATALOG.'controller';
		if($path != $controller_path)
			$controller_path = $path.'/'.$controller_path;

		if(is_dir($controller_path)){
			if($controller_path_files = opendir($controller_path)){
				while(($new_file = readdir($controller_path_files)) !== false){
					if($new_file != '.' AND $new_file!= '..'){
						if(is_dir($controller_path.'/'.$new_file)){
							if($copy)
								$new_file = $copy.'/'.$new_file;
								$this->getdir($new_file);
						}elseif($copy!='module' AND $copy!='payment' AND $copy!='shipping' AND $copy!='api' AND $copy!='feed' AND $copy!='tool'){ // to discard folders
							$chk = explode(".",$new_file);
							if(end($chk)=='php')
								$this->files_array [] = $copy.'/'.prev($chk);
						}
					}
				}
			}
		}
	}

	public function index() {

		// upgradation code
		$this->load->model('customerpartner/partner');
		$this->model_customerpartner_partner->upgradeMarketplace();
		// upgradation code

	    $data = array();
	    $data = array_merge($data,$this->load->language('extension/module/marketplace'));

		$this->document->setTitle($data['heading_title1']);

		$this->load->model('setting/setting');

		$data['seller_product_store'] = array(

			'own_store' => $data['entry_ownstore'],
			'choose_store' => $data['entry_choosestore'],
			'multi_store' => $data['entry_mulistore'],
		);

		if ((request()->isMethod('POST')) && isset($this->request->files['marketplace_default_image']) && $this->imageValidation($this->request->files['marketplace_default_image']) && $this->validate()) {

			if (!isset($this->request->post['marketplace_allowed_categories'])) {

				$this->request->post['marketplace_allowed_categories'] = array();
			} else {
			  $this->request->post['marketplace_allowed_categories'] = $this->model_customerpartner_partner->addCategory($this->request->post['marketplace_allowed_categories']);
			}

			if(isset($this->request->files['marketplace_default_image']) && $this->request->files['marketplace_default_image']['name']) {
				move_uploaded_file($this->request->files['marketplace_default_image']["tmp_name"], DIR_IMAGE . "catalog/" . $this->request->files['marketplace_default_image']["name"]);
				$this->request->post['marketplace_default_image_name'] = "catalog/".$this->request->files['marketplace_default_image']["name"];
			}

			if(isset($this->request->post['marketplace_SefUrlspath']))
				$this->request->post['marketplace_SefUrlspath'] = array_values($this->request->post['marketplace_SefUrlspath']);
			if(isset($this->request->post['marketplace_SefUrlsvalue']))
				$this->request->post['marketplace_SefUrlsvalue'] = array_values($this->request->post['marketplace_SefUrlsvalue']);

			//remove blank tabs - checked heading
			if(isset($this->request->post['marketplace_tab']['heading'])){
				foreach ($this->request->post['marketplace_tab']['heading'] as $key => $value) {
					$left_this = false;
					foreach($value as $language_key => $language_value){
						if($language_value)
							$left_this = true;
					}
					if(!$left_this){
						unset($this->request->post['marketplace_tab']['heading'][$key]);
						unset($this->request->post['marketplace_tab']['description'][$key]);
					}
				}
			}

			$int_field_array = array(
				'marketplace_commission',
			  'marketplace_low_stock_quantity',
			  'marketplace_noofimages',
			  'marketplace_imagesize',
			  'marketplace_downloadsize',
			  'marketplace_min_cart_value',
			  'marketplace_product_quantity_restriction',
			  'marketplace_seller_list_limit',
			  'marketplace_seller_product_list_limit',
			);

			foreach ($int_field_array as $key => $value) {
			  if (isset($this->request->post[$value]) && $this->request->post[$value]) {
			    $this->request->post[$value] = (int)abs($this->request->post[$value]);
			  }
			}

			if (!isset($this->request->post['marketplace_adminmail']) || !$this->request->post['marketplace_adminmail']) {
			  $this->request->post['marketplace_adminmail'] = $this->config->get('config_email');
			}

			if (!isset($this->request->post['marketplace_default_image_name']) || !trim($this->request->post['marketplace_default_image_name'])) {
			  $this->request->post['marketplace_default_image_name'] = 'no_image.png';
			} else {
			  $this->request->post['marketplace_default_image_name'] = trim($this->request->post['marketplace_default_image_name']);
			}

			if (isset($this->request->post['marketplace_store'])) {
				$this->model_setting_setting->editSetting('module_marketplace', $this->request->post ,$this->request->post['marketplace_store']);
				$this->model_setting_setting->editSetting('marketplace', $this->request->post ,$this->request->post['marketplace_store']);
			} else {
				$this->model_setting_setting->editSetting('module_marketplace', $this->request->post);
				$this->model_setting_setting->editSetting('marketplace', $this->request->post);
			}

			session()->set('success', $this->language->get('text_success'));

			$store_id = 0;

			if (isset($this->request->post['marketplace_store']) && $this->request->post['marketplace_store']) {
				$store_id = $this->request->post['marketplace_store'];
			}

			$this->response->redirect($this->url->link('extension/module/marketplace', 'user_token=' . session('user_token').'&store_id='.$store_id, true));
		}

		$this->load->model('setting/store');

        $data['stores'] = array();

        $data['stores'][] = array(
            'store_id' => 0,
            'name'     => $this->config->get('config_name'),
        );

        $results = $this->model_setting_store->getStores();
				if ($results) {
					foreach ($results as $result) {
							$data['stores'][] = array(
									'store_id' => $result['store_id'],
									'name'     => $result['name'],
							);
					}
				}

		$config_data = array(

				'module_marketplace_status',

				//general
				'marketplace_mailtoseller',
				'marketplace_mailadmincustomercontactseller',
				'marketplace_notification_filter',
				'marketplace_customercontactseller',
				'marketplace_hideselleremail',
				'marketplace_adminmail',
				'marketplace_productapprov',
				'marketplace_categoryapprov',
				'marketplace_informationapprov',
				'marketplace_sellereditreview',
				'marketplace_partnerapprov',
				'marketplace_sellerorderstatus',
				'marketplace_available_order_status',
				'marketplace_order_status_sequence',
				'marketplace_becomepartnerregistration',
				'marketplace_allowed_shipping_method',
				'marketplace_complete_order_status',
				'marketplace_divide_shipping',
				'marketplace_default_image_name',
				'marketplace_cancel_order_status',
				'marketplace_seller_name_cart_status',
				'marketplace_seller_list_limit',
				'marketplace_seller_product_list_limit',
				'marketplace_min_cart_value',
				'marketplace_product_quantity_restriction',
				'marketplace_separate_view',

				//product tab
				'marketplace_allowedproductcolumn',
				'marketplace_allowedproducttabs',
				'marketplace_imagesize',
				'marketplace_noofimages',
				'marketplace_imageex',
				'marketplace_noofdownload',
				'marketplace_downloadex',
				'marketplace_downloadsize',
				'marketplace_productaddemail',
				'marketplace_product_reapprove',
				'marketplace_sellerdeleteproduct',
				'marketplace_sellerproductdelete',
				'marketplace_sellerproductshow',
				'marketplace_sellerbuyproduct',
				'marketplace_adminnotify',
				'marketplace_seller_product_store',

				//seo tab
				'marketplace_useseo',
				'marketplace_wksell',
				'marketplace_productlist',
				'marketplace_profile',
				'marketplace_addproduct',
				'marketplace_add_shipping_mod',
				'marketplace_dashboard',
				'marketplace_orderlist',
				'marketplace_order_info',
				'marketplace_soldlist',
				'marketplace_soldinvoice',
				'marketplace_editproduct',
				'marketplace_storeprofile',
				'marketplace_collection',
				'marketplace_feedback',
				'marketplace_store',
				'marketplace_downloads',
				'marketplace_transactions',
				//sef tab 2
				'marketplace_SefUrlspath',
				'marketplace_SefUrlsvalue',
				// sef product tab
				'marketplace_product_seo_name',
				'marketplace_product_seo_format',
				'marketplace_product_seo_default_name',
				'marketplace_product_seo_product_name',
				'marketplace_product_seo_page_ext',

				//commission
				'marketplace_boxcommission',
				'marketplace_commission_add',
				'marketplace_commission',
				'marketplace_commissionworkedon',

				//sell tab
				'marketplace_sellheader',
				'marketplace_sellbuttontitle',
				'marketplace_selldescription',
				'marketplace_showpartners',
				'marketplace_showproducts',
				'marketplace_tab',

				//profile tab
				'marketplace_allowedprofilecolumn',
				'marketplace_allowed_public_seller_profile',
				'marketplace_profile_email',
				'marketplace_profile_telephone',

				// 'marketplace_profile_profile',
				'marketplace_profile_store',
				'marketplace_profile_collection',
				'marketplace_profile_review',
				'marketplace_profile_product_review',
				'marketplace_profile_location',

				// module Configuration
				'marketplace_allowed_account_menu',
				'marketplace_account_menu_sequence',
				'marketplace_product_name_display',
				'marketplace_product_show_seller_product',
				'marketplace_product_image_display',

				//mail tab
				'marketplace_mail_keywords',
				'marketplace_mail_partner_request',
				'marketplace_mail_product_request',
				'marketplace_mail_transaction',
				'marketplace_mail_order',
				'marketplace_mail_partner_admin',
				'marketplace_mail_product_admin',
				'marketplace_mail_cutomer_to_seller',
				'marketplace_mail_seller_to_admin',
				'marketplace_mail_partner_approve',
				'marketplace_mail_product_approve',
				'marketplace_mail_admin_on_edit',
				'marketplace_mail_seller_on_edit',
				'marketplace_mail_order_status_change',

				//paypal tab
				'marketplace_paypal_mode',
				'marketplace_paypal_user',
				'marketplace_paypal_password',
				'marketplace_paypal_signature',
				'marketplace_paypal_appid',
				'marketplace_paypal_email_subject',

				// update in marketplace
				'marketplace_seller_manage_order',
				'marketplace_low_stock_notification',
				'marketplace_low_stock_quantity',
				'marketplace_review_only_order',
				'marketplace_seller_info_by_module',
				'marketplace_pdf_order_invoice',
				'marketplace_commission_tax',
				'marketplace_commission_unit_price',
				'marketplace_seller_info_hide',
				'marketplace_seller_category_required',
				'marketplace_allowed_seller_category_type',
				'marketplace_allowed_categories',
				'marketplace_mail_seller_low_stock',
				'marketplace_auto_generate_sku',

		);

		foreach ($config_data as $conf) {
			if (isset($this->request->post[$conf])) {
				$data[$conf] = $this->request->post[$conf];
			}
		}

		if (isset($this->request->get['store_id'])) {
			$data['marketplace_store'] = $this->request->get['store_id'];
			$data = array_merge($data,$this->model_setting_setting->getSetting('module_marketplace', $this->request->get['store_id']));
			$data = array_merge($data,$this->model_setting_setting->getSetting('marketplace', $this->request->get['store_id']));
		} else {
			$data = array_merge($data,$this->model_setting_setting->getSetting('module_marketplace', 0));
			$data = array_merge($data,$this->model_setting_setting->getSetting('marketplace', 0));
		}

		$this->load->model('tool/image');

		if(isset($data['marketplace_default_image_name']) && $data['marketplace_default_image_name']) {
			$data['marketplace_default_image_name'] = $data['marketplace_default_image_name'];
			$data['marketplace_default_image'] = $this->model_tool_image->resize($data['marketplace_default_image_name'], 90, 90);
		}

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if(isset($this->session->data['success'])) {
			$data['success'] = session('success');
			$this->session->remove('success');
		} else {
			$data['success'] = '';
		}

  		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
      		'separator' => false
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_module'),
			'href'      => $this->url->link('marketplace/extension', 'user_token=' . session('user_token').'&type=module', true),
      		'separator' => ' :: '
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('extension/module/marketplace', 'user_token=' . session('user_token'), true),
      		'separator' => ' :: '
   		);

		$data['action'] = $this->url->link('extension/module/marketplace', 'user_token=' . session('user_token'), true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . session('user_token').'&type=module', true);

		$product_table = $this->db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND table_name = '".DB_PREFIX."product'")->rows;

		$product_table = array_slice($product_table, 2, -3);

		$data['product_table'] = array();

		foreach($product_table as $key => $value){
			if ($value['COLUMN_NAME'] != 'status') {
				$data['product_table'][] = $value['COLUMN_NAME'];
			}
		}

		$data['product_table'][] = 'keyword';

		$data['product_tabs'] = array('links', 'attribute', 'options', 'discount', 'special', 'images', 'custom-field');

		//folder path for SEF urls
		$this->getdir();
		$data['paths'] = $this->files_array;

		$data['profile_table'] = array();
		$profile_table = $this->db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND table_name = '".DB_PREFIX."customerpartner_to_customer'")->rows;

		$profile_table = array_slice($profile_table, 3, -1);
		if($profile_table[11]['COLUMN_NAME'] == 'companyname') {
			unset($profile_table[11]);
		}
		foreach($profile_table as $key => $value){
			$data['profile_table'][] = $value['COLUMN_NAME'];
		}

		$data['account_menu'] = array(
			'profile' => $this->language->get('entry_mod_profile'),
			'dashboard' => $this->language->get('entry_mod_dashboard'),
			'orderhistory' => $this->language->get('entry_mod_order'),
			'transaction' => $this->language->get('entry_mod_transaction'),
			'productlist' => $this->language->get('entry_mod_productlist'),
			'category' => $this->language->get('entry_mod_category'),
			'addproduct' => $this->language->get('entry_mod_addproduct'),
			'product_options' => $this->language->get('entry_mod_product_options'),
			'downloads' => $this->language->get('entry_mod_downloads'),
			'manageshipping' => $this->language->get('entry_mod_manageshipping'),
			'asktoadmin' => $this->language->get('entry_mod_asktoadmin'),
			'notification' => $this->language->get('entry_mod_notification'),
			'information' => $this->language->get('entry_mod_information'),
			'review' => $this->language->get('entry_mod_review'),
			// add by lilei 增加seller管理buyer
			'buyers' => $this->language->get('entry_mod_buyers'),
			// add by lxx
			'brand' => $this->language->get('entry_mod_brand'),
			// add by lilei 增加Seller产品管理模块
			'product_manage' => $this->language->get('entry_mod_product_manage'),
			//add by lxx
            'rma_management' => $this->language->get('entry_mod_rma'),
            //add by lxx product_review
            'product_review'=>$this->language->get('product_review'),
            // Add by lester.you 2019-8-19 11:31:00
            'product_groups' => $this->language->get('product_groups'),
            'buyer_groups' => $this->language->get('buyer_groups'),
            'delicacy_price_setting'=>$this->language->get('entry_mod_delicacy_price_setting'),
            'invisible_setting_product' => $this->language->get('entry_mod_invisible_setting_product'),
            // Add by chenyang 2019-8-26
            'mp_market_business' => $this->language->get('entry_mod_marketing_business'),
            'mp_rebates' => $this->language->get('entry_mod_rebates'),
            'mp_margin' => $this->language->get('entry_mod_margin'),
            // add by wangjinxin 期货
            'mp_futures' => $this->language->get('entry_mod_futures'),
            //add by zjg
            'seller_bill_total'=>$this->language->get('seller_bill_total'),
            'seller_bill_detail'=>$this->language->get('seller_bill_detail'),

            //N-990 添加产品运费打包费查询
            'product_freight_inquiry'=>$this->language->get('product_freight_inquiry'),
            // add by wangjinxin 纯物流
            'sales_order_upload' => $this->language->get('sales_order_upload'),
            'sales_order_list' => $this->language->get('sales_order_list'),
            'external_platform_mapping' => $this->language->get('entry_mod_platform_mapping'),
            // 101594 促销 by lester.you 2020-6-22 15:51:00
            'promotion'=>$this->language->get('entry_mod_promotions'),
            // 100013 账户授权列表
            'account_authorization'=>$this->language->get('entry_account_authorization'),
            'warehouse_receipt'=>$this->language->get('entry_warehouse_receipt'),
        );

		if($this->config->get('wk_rma_status')) {
			$data['wk_rma_status'] = true;
			$data['account_menu']['manage_rma'] = $this->language->get('text_manage_rma');
			$data['account_menu']['status_rma'] = $this->language->get('text_manage_rma_status');
			$data['account_menu']['reason_rma'] = $this->language->get('text_manage_rma_reason');
			$data['marketplace_account_menu_sequence']['manage_rma'] = $this->language->get('text_manage_rma');
			$data['marketplace_account_menu_sequence']['status_rma'] = $this->language->get('text_manage_rma_status');
			$data['marketplace_account_menu_sequence']['reason_rma'] = $this->language->get('text_manage_rma_reason');
		} else {
			$data['wk_rma_status'] = false;
			if(isset($data['marketplace_account_menu_sequence']['manage_rma'])) {
				unset($data['marketplace_account_menu_sequence']['manage_rma']);
			}
			if(isset($data['marketplace_account_menu_sequence']['status_rma'])) {
				unset($data['marketplace_account_menu_sequence']['status_rma']);
			}
			if(isset($data['marketplace_account_menu_sequence']['reason_rma'])) {
				unset($data['marketplace_account_menu_sequence']['reason_rma']);
			}
		}

		$data['publicSellerProfile'] = array(
			'store' => $this->language->get('entry_store_tab'),
			'collection' => $this->language->get('entry_collection_tab'),
			'review' => $this->language->get('entry_review_tab'),
			'productReview' => $this->language->get('entry_product_review_tab'),
			//'location' => $this->language->get('entry_location_tab'),
		);


			/*
			Membership code
			Add memebership option to existing array
			 */
			if($this->config->get('module_wk_seller_group_status')) {
	        	$data['module_wk_seller_group_status'] = true;
	        	$data['account_menu']['membership'] = $this->language->get('entry_mod_membership');
	        	$data['marketplace_account_menu_sequence']['membership'] = $this->language->get('entry_mod_membership');
	        } else {
	        	$data['module_wk_seller_group_status'] = false;
	        	if(isset($data['account_menu']['membership'])) {
	        		unset($data['account_menu']['membership']);
	        	}
	        	if(isset($data['marketplace_account_menu_sequence']['membership'])) {
	        		unset($data['marketplace_account_menu_sequence']['membership']);
	        	}
	        }
	        /*
	        end here
	         */

        $this->load->model('localisation/language');
		/*Marketplace Product-Qutoes  start here*/
		if($this->config->get('total_wk_pro_quote_status')) {
			$this->load->language('extension/total/wk_pro_quote');
			$data['account_menu']['mp_pq'] = $this->language->get('entry_mod_pmpq');
			$data['marketplace_account_menu_sequence']['mp_pq'] = $this->language->get('entry_mod_pmpq');
		} else {
			$data['wk_pro_quote_status'] = false;
			if(isset($data['marketplace_account_menu_sequence']['mp_pq'])) {
				unset($data['marketplace_account_menu_sequence']['mp_pq']);
			}
			if(isset($data['marketplace_account_menu_sequence']['mp_pq'])) {
				unset($data['marketplace_account_menu_sequence']['mp_pq']);
			}
		}

        if($this->config->get('module_wk_communication_status')) {
            $data['module_wk_communication_status'] = true;
            $data['account_menu']['wk_communication'] = $this->language->get('text_wk_communication');
            $data['marketplace_account_menu_sequence']['wk_communication'] = $this->language->get('text_wk_communication');
        } else {
            $data['module_wk_communication_status'] = false;
            if(isset($data['account_menu']['wk_communication'])) {
                unset($data['account_menu']['wk_communication']);
            }
            if(isset($data['marketplace_account_menu_sequence']['wk_communication'])) {
                unset($data['marketplace_account_menu_sequence']['wk_communication']);
            }
        }

        /*
        Marketplace Product-Qutoes end here
         */
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['config_language_id'] = $this->config->get('config_language_id');

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('setting/extension');

		$shipping_methods = $this->model_setting_extension->getInstalled('shipping');
		foreach ($shipping_methods as $key => $shipping_method) {
			$file = glob(DIR_APPLICATION . 'controller/extension/shipping/'.$shipping_method.'.php');
			if($file){
				$this->load->language('extension/shipping/'.$shipping_method);
				$data['shipping_methods'][] = array(
					'code' => $shipping_method,
					'name' => $this->language->get('heading_title'),
				);
			}
		}

		$data['currency_symbol'] = $this->currency->getSymbolLeft($this->config->get('config_currency'));

		//get total mail
		$this->load->model('customerpartner/mail');
		$data['mails'] = $this->model_customerpartner_mail->gettotal();
		$data['user_token'] = session('user_token');
		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');
		$data['column_left'] = $this->load->controller('common/column_left');

		$this->response->setOutput($this->load->view('extension/module/marketplace',$data));
	}

	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/marketplace')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (isset($this->request->post['marketplace_SefUrlsvalue']) && $this->request->post['marketplace_SefUrlsvalue'] && count($this->request->post['marketplace_SefUrlsvalue']) != count(array_unique($this->request->post['marketplace_SefUrlsvalue']))) {
		  $this->error['warning'] = $this->language->get('error_sef');
		}

		if (isset($this->request->post['marketplace_SefUrlsvalue']) && $this->request->post['marketplace_SefUrlsvalue'] && is_array($this->request->post['marketplace_SefUrlsvalue'])) {
			foreach ($this->request->post['marketplace_SefUrlsvalue'] as $key => $value) {
				if (preg_match('/[^A-Za-z0-9_-]/', $value)) {
					$this->error['warning'] = $this->language->get('error_sef');
					break;
				}
			}
		}

		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}

	private function imageValidation($value){

        $this->load->language('extension/module/customerpartner');
		$error = true;

  		if (isset($value['name']) && !empty($value['name']) && is_file($value['tmp_name'])) {
			// Sanitize the filename
			$filename = basename(html_entity_decode($value['name'], ENT_QUOTES, 'UTF-8'));

			// Validate the filename length
			if ((utf8_strlen($filename) < 3) || (utf8_strlen($filename) > 255)) {
				$this->error['warning'] = $this->language->get('error_filename');
				$error = false;
			}

			// Allowed file extension types
			$allowed = array(
				'jpg',
				'jpeg',
				'gif',
				'png'
			);

			if (!in_array(utf8_strtolower(utf8_substr(strrchr($filename, '.'), 1)), $allowed)) {
				$this->error['warning'] = $this->language->get('error_filetype');
				$error = false;
			}

			// Allowed file mime types
			$allowed = array(
				'image/jpeg',
				'image/pjpeg',
				'image/png',
				'image/x-png',
				'image/gif'
			);

			if (!in_array($value['type'], $allowed)) {
				$this->error['warning'] = $this->language->get('error_filetype');
				$error = false;
			}

			// Check to see if any PHP files are trying to be uploaded
			$content = file_get_contents($value['tmp_name']);

			if (preg_match('/\<\?php/i', $content)) {
				$this->error['warning'] = $this->language->get('error_filetype');
				$error = false;
			}

			// Return any upload error
			if ($value['error'] != UPLOAD_ERR_OK) {
				$this->error['warning'] = $this->language->get('error_upload_' . $value['error']);
				$error = false;
			}
		}

		return $error;
	}

	public function autocomplete() {
		$json = array();

		if (isset($this->request->get['filter_name'])) {

			$allowed_categories = '';
			if (isset($this->request->post['allowed_categories']) && $this->request->post['allowed_categories']) {
				foreach ($this->request->post['allowed_categories'] as $categories) {

					$allowed_categories .= ','. $categories;
				}

				if ($allowed_categories) {
					$allowed_categories = ltrim($allowed_categories, ',');
				}
			}

			$this->load->model('customerpartner/partner');

			$filter_data = array(
				'filter_name' => $this->request->get['filter_name'],
				'sort'        => 'name',
				'order'       => 'ASC',
				'start'       => 0,
				'limit'       => 5
			);

			$results = $this->model_customerpartner_partner->getCategories($filter_data, $allowed_categories);

			foreach ($results as $result) {
				$json[] = array(
					'category_id' => $result['category_id'],
					'name'        => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'))
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

	public function customfield(){

		$data = array();

		$data['module_marketplace_status'] = $this->config->get('module_marketplace_status');

		if ($this->config->get('module_marketplace_status')) {
		    $this->load->language("wkcustomfield/wkcustomfield");
		    $this->load->model("wkcustomfield/wkcustomfield");
		    $wkcustomFields = array();
		    $data['wkcustomFields'] = $this->model_wkcustomfield_wkcustomfield->getOptionList();

		    $data['text_custom_field'] = $this->language->get('text_custom_field');
		    $data['entry_select_option'] = $this->language->get('entry_select_option');
		    $data['entry_select_date'] = $this->language->get('entry_select_date');
		    $data['entry_select_datetime'] = $this->language->get('entry_select_datetime');
		    $data['entry_select_time'] = $this->language->get('entry_select_time');
		    $data['entry_enter_text'] = $this->language->get('entry_enter_text');

		    if(isset($this->request->get['product_id']) || isset($this->request->post['product_custom_field'])){
		        if(isset($this->request->get['product_id'])){
		            $product_id = $this->request->get['product_id'];
		        } else {
		            $product_id = 0;
		        }
		        $data['wkPreCustomFields'] = array();
		        $wkPreCustomFieldOptions = array();
		        $wkPreCustomFields = $this->model_wkcustomfield_wkcustomfield->getProductFields($product_id);
		        if(isset($this->request->post['product_custom_field'])){
		            foreach ($this->request->post['product_custom_field'] as $key => $value) {
		                if(!isset($wkPreCustomFields[$key])){
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
		        foreach($wkPreCustomFields as $field){
		            $wkPreCustomFieldOptions = $this->model_wkcustomfield_wkcustomfield->getOptions($field['fieldId']);
		            if($field['fieldType'] == 'select' || $field['fieldType'] == 'checkbox' || $field['fieldType'] == 'radio' ){
		                $wkPreCustomProductFieldOptions = $this->model_wkcustomfield_wkcustomfield->getProductFieldOptions($product_id,$field['fieldId'],$field['id']);
		            } else {
		                $wkPreCustomProductFieldOptions = $this->model_wkcustomfield_wkcustomfield->getProductFieldOptionValue($product_id,$field['fieldId'],$field['id']);
		            }
		            $data['wkPreCustomFields'][] = array(
		                'fieldId'       => $field['fieldId'],
		                'fieldName'     => $field['fieldName'],
		                'fieldType'     => $field['fieldType'],
		                'fieldDes'      => $field['fieldDescription'],
		                'productFieldId'      => $field['id'],
		                'isRequired'    => $field['isRequired'],
		                'fieldOptions'  => $wkPreCustomProductFieldOptions,
		                'preFieldOptions' => $wkPreCustomFieldOptions,
		            );
		        }
		    }

		    if (isset($this->error['customFieldError'])) {
		        $data['customFieldError'] = $this->error['customFieldError'];
		    } else {
		        $data['customFieldError'] = array();
		    }
		}

		return $this->load->view('customerpartner/customfield',$data);
	}
}
?>
