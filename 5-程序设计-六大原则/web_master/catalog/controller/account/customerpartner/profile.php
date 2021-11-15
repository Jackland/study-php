<?php

use App\Helper\UploadImageHelper;
use Catalog\model\futures\creditApply;
use App\Services\Store\StoreAuditService;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Enums\Store\StoreAuditStatus;
use App\Repositories\Seller\SellerRepository;
use App\Models\Store\StoreAudit;
use App\Repositories\Store\StoreAuditRepository;
use App\Enums\Customer\CustomerAccountingType;
use App\Helper\CustomerHelper;

/**
 * Class ControllerAccountCustomerpartnerProfile
 * @property ModelAccountAddress $model_account_address
 * @property ModelAccountCustomerGroup $model_account_customer_group
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelCustomerpartnerMaster $model_customerpartner_master
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountCustomerpartnerProfile extends Controller {

	private $error = array();
	public function index() {
        view()->meta(['name' => 'google', 'content' => 'notranslate']);

		$data = array();
		$data = array_merge($data, $this->load->language('account/customerpartner/profile'));

		if (!$this->customer->isLogged()) {
			session()->set('redirect', $this->url->link('account/customerpartner/profile', '', true));
			$this->response->redirect($this->url->link('account/login', '', true));
		}
        // 获取授信申请记录
        $data['first_apply'] = creditApply::hasCreditApply($this->customer->getId());
		$this->load->model('account/customerpartner');

		$data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

		if(!$data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode']))
			$this->response->redirect($this->url->link('account/account', '', true));

		$this->document->setTitle($data['heading_title']);

		$this->document->addStyle('catalog/view/theme/default/stylesheet/MP/sell.css?v=' . APP_VERSION);

        if ((request()->isMethod('POST')) && $this->validateForm()) {
            if (isset($this->request->post['avatar']) && $this->request->post['avatar']) {
                $this->request->post['avatar'] = ltrim(explode(',', $this->request->post['avatar'])[0], 'image/');
            }
            if (isset($this->request->post['companybanner']) && $this->request->post['companybanner']) {
                $this->request->post['companybanner'] = ltrim(explode(',', $this->request->post['companybanner'])[0], 'image/');
            }
            app(StoreAuditService::class)->insertStoreAudit($this->request->post);
            $this->session->set('success','Store information and Return Policy of the store will be submitted for approval. Check the Message Center for results.');
            $this->response->redirect($this->url->link('account/customerpartner/profile', '', true));
        }

		$partner = $this->model_account_customerpartner->getProfile();
		$partner_country = $this->model_account_customerpartner->getCountryByCustomerId();

        $default_img = '';

		if($partner) {
			if(isset($partner_country['name'])){
				$partner['country'] = $partner_country['name'];
			}
			$this->load->model('tool/image');
			if($this->config->get('marketplace_default_image_name') && file_exists(DIR_IMAGE . $this->config->get('marketplace_default_image_name'))){
				$default_img = $this->model_tool_image->resize($this->config->get('marketplace_default_image_name'), 100, 100);
			}else{
				$default_img = '';
			}
			$partner['defaultImg'] = $default_img;
			if (isset($this->request->post['avatar'])) {
				if ($this->request->post['avatar']) {
					$partner['avatar_img'] = $this->request->post['avatar'];
			        $partner['avatar'] = $this->model_tool_image->resize($this->request->post['avatar'], 100, 100);
				}else{
                    $partner['avatar_img'] = '';
			        $partner['avatar'] = '';
				}
			}elseif ($partner['avatar']) {
			    $partner['avatar_img'] = $partner['avatar'];
				$partner['avatar'] = $this->model_tool_image->resize($partner['avatar'], 100, 100);
			} else  {
				$partner['avatar'] = $default_img;
				$partner['avatar_img'] = '';
			}

			if (isset($this->request->post['companybanner'])) {
				if ($this->request->post['companybanner']) {
					$partner['companybanner_img'] = $this->request->post['companybanner'];
			        $partner['companybanner'] = $this->model_tool_image->resize($this->request->post['companybanner'], 100, 100);
				}else{
                    $partner['companybanner_img'] = '';
			        $partner['companybanner'] = '';
				}
			}elseif ($partner['companybanner']) {
			    $partner['companybanner_img'] = $partner['companybanner'];
				$partner['companybanner'] = $this->model_tool_image->resize($partner['companybanner'], 100, 100);
			}  else {
				$partner['companybanner'] = $default_img;
				$partner['companybanner_img'] = '';
			}
			$data['storeurl'] =$this->url->link('customerpartner/profile&id='.$this->customer->getId(),'',true);
		}

		if($this->config->get('module_wk_seller_group_status')) {
			$this->load->model('account/customer_group');
			$isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());//无效方法
			if($isMember) {
				$allowedAccountMenu = $this->model_account_customer_group->getprofileOption($isMember['gid']);//无效方法
				if($allowedAccountMenu['value']) {
					$accountMenu = explode(',',$allowedAccountMenu['value']);
					if($accountMenu) {
						foreach ($accountMenu as $key => $value) {
							$values = explode(':',$value);
							$data['allowed'][$values[0]] = $values[1];
						}
					}
				}
			}
		} else if($this->config->get('marketplace_allowedprofilecolumn')) {
			$data['allowed']  = $this->config->get('marketplace_allowedprofilecolumn');
		}

		$data['partner'] = $partner;

		if (!$data['partner']['country']) {
			$data['partner']['country'] = 'af';

			$address_id = $this->customer->getAddressId();

			if ($address_id) {
			  $this->load->model('account/address');

			  $address_data = $this->model_account_address->getAddress($address_id);

			  if (isset($address_data['iso_code_2']) && $address_data['iso_code_2']) {
			    $data['partner']['country'] = $address_data['iso_code_2'];
			  }
			}
		}

//		$data['countries'] = $this->model_account_customerpartner->getCountry();

      	$data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_account_m'),
            'href' => 'javascript:void(0)',
            'separator' => $this->language->get('text_separator')
        );

      	$data['breadcrumbs'][] = array(
        	'text'      => $data['heading_title'],
			'href'      => $this->url->link('account/customerpartner/profile', '', true),
        	'separator' => false
      	);

		$data['customer_details'] = array(
			'firstname' => $this->customer->getFirstName(),
			'lastname' => $this->customer->getLastName(),
			'email' => $this->customer->getEmail(),
            'telephone' => $this->customer->getValidMaskTelephone()
		);

		if (isset($this->request->post['paypalfirst'])) {
		  $data['partner']['paypalfirst'] = $this->request->post['paypalfirst'];
		} elseif (isset($partner['paypalfirstname']) ) {
		  $data['partner']['paypalfirst'] = $partner['paypalfirstname'];
		} else {
		  $data['partner']['paypalfirst'] = '';
		}

		if (isset($this->request->post['paypallast'])) {
		  $data['partner']['paypallast'] = $this->request->post['paypallast'];
		} elseif (isset($partner['paypallastname']) ) {
		  $data['partner']['paypallast'] = $partner['paypallastname'];
		} else {
		  $data['partner']['paypallast'] = '';
		}

		if (isset($this->request->post['paypalid'])) {
		  $data['partner']['paypalid'] = $this->request->post['paypalid'];
		} elseif (isset($partner['paypalid']) ) {
		  $data['partner']['paypalid'] = $partner['paypalid'];
		} else {
		  $data['partner']['paypalid'] = '';
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['screenname_error'])) {
			$data['screenname_error'] = $this->error['screenname_error'];
		} else {
			$data['screenname_error'] = '';
		}

		if (isset($this->error['companyname_error'])) {
			$data['companyname_error'] = $this->error['companyname_error'];
		} else {
			$data['companyname_error'] = '';
		}

		if (isset($this->error['paypal_error'])) {
			$data['paypal_error'] = $this->error['paypal_error'];
		} else {
			$data['paypal_error'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = session('success');
			$this->session->remove('success');
		} else {
			$data['success'] = '';
		}

		$data['action'] = $this->url->link('account/customerpartner/profile', '', true);
		$data['back'] = $this->url->link('customerpartner/seller_center/index', '', true);
		$data['view_profile'] = $this->url->link('customerpartner/profile&id='.$this->customer->getId(), '', true);

		$data['isMember'] = true;
		if($this->config->get('module_wk_seller_group_status')) {
      		$data['module_wk_seller_group_status'] = true;
      		$this->load->model('account/customer_group');
			$isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());
			if($isMember) {
				$allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);
				if($allowedAccountMenu['value']) {
					$accountMenu = explode(',',$allowedAccountMenu['value']);
					if($accountMenu && !in_array('profile:profile', $accountMenu)) {
						$data['isMember'] = false;
					}
				}
			} else {
				$data['isMember'] = false;
			}
  	} else {
  		if(!is_array($this->config->get('marketplace_allowed_account_menu')) || !in_array('profile', $this->config->get('marketplace_allowed_account_menu'))) {
  			$this->response->redirect($this->url->link('account/account','', true));
  		}
  	}

		$post_array = array('screenName','shortProfile','companyName','twitterId','facebookId','companyLocality','companyDescription','otherpayment','taxinfo');

		foreach ($post_array as $key => $value) {
			if (isset($this->request->post[$value])) {
			  $data['partner'][strtolower($value)] = $this->request->post[$value];
			}
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

        $data['separate_view'] = false;

        $data['separate_column_left'] = '';

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
        $logoPath = $partner['avatar_img'] ? '&path=image/' . $partner['avatar_img'] : '';
        $bannerPath = $partner['companybanner_img'] ? '&path=image/' . $partner['companybanner_img'] : '';
        $data['logo_url'] = $this->url->link('account/customerpartner/profile/getAttach' . $logoPath);
        $data['banner_url'] = $this->url->link('account/customerpartner/profile/getAttach' . $bannerPath);
        $data['upload_input'] = $this->load->controller('upload/upload_component/upload_input');

        //默认退返品
        $returnWarranty = app(SellerRepository::class)->getDefaultReturnWarranty();
        //新逻辑直接在这复写
        $profileDetail = CustomerPartnerToCustomer::query()->where('customer_id', customer()->getId())->first();
        $auditStatus = 0;
        if ($profileDetail && $profileDetail->store_audit_id && $this->request->serverBag->get('REQUEST_METHOD') !== 'POST') {
            if (isset($data['partner']['return_warranty']) && !empty($data['partner']['return_warranty'])) {
                $returnWarranty = json_decode($data['partner']['return_warranty'], true);
            }
            $auditDetail = StoreAudit::find($profileDetail->store_audit_id);
            $auditStatus = $auditDetail->status;
            if (in_array($auditDetail->status, StoreAuditStatus::getChangeStatus())) {
                if ($auditDetail->logo_url) {
                    $partner['avatar_img'] = $auditDetail->logo_url;
                    $partner['avatar'] = $this->model_tool_image->resize($auditDetail->logo_url, 100, 100);
                } else {
                    $partner['avatar_img'] = '';
                    $partner['avatar'] = $default_img;
                }
                if ($auditDetail->banner_url) {
                    $partner['companybanner_img'] = $auditDetail->banner_url;
                    $partner['companybanner'] = $this->model_tool_image->resize($auditDetail->banner_url, 100, 100);
                } else {
                    $partner['companybanner_img'] = '';
                    $partner['companybanner'] = $default_img;
                }
                $data['partner']['screenname'] = $auditDetail->store_name;
                $data['partner']['companydescription'] = $auditDetail->description;
                $data['partner']['avatar_img'] = $partner['avatar_img'];
                $data['partner']['companybanner_img'] = $partner['companybanner_img'];
                $logoPath = $partner['avatar_img'] ? '&path=image/' . $partner['avatar_img'] : '';
                $bannerPath = $partner['companybanner_img'] ? '&path=image/' . $partner['companybanner_img'] : '';
                $data['logo_url'] = $this->url->link('account/customerpartner/profile/getAttach' . $logoPath);
                $data['banner_url'] = $this->url->link('account/customerpartner/profile/getAttach' . $bannerPath);
                //重置退返品
                if ($auditDetail->return_warranty) {
                    $returnWarranty = json_decode($auditDetail->return_warranty, true);
                }
            }
        }
        //兼容报错情况
        if ($this->request->serverBag->get('REQUEST_METHOD') === 'POST' && !$this->validateForm()) {
            $returnWarranty = app(StoreAuditRepository::class)->getPostReturnWarranty($this->request->post());
        }
        $data['audit_status'] = $auditStatus;
        $data['audit_status_show'] = $auditStatus > 0 ? StoreAuditStatus::getDescription($auditStatus) : '';
        $data['audit_status_class'] = $auditStatus > 0 ? StoreAuditStatus::getClassItems($auditStatus) : '';
        $data['return_warranty'] = $returnWarranty;

        return $this->render('account/customerpartner/profile',$data);
	}

    public function getAttach()
    {
        if ($this->request->query->get('path')) {
            $t_path = $this->request->query->get('path');
            $attach = creditApply::getAttach($this->request->get['path']);
            if ($attach->isEmpty()) {
                $attach = [];
                $attach[0] = UploadImageHelper::getInfoFromOriginUrl($t_path, null, 'default/blank.png');
                $attach[0]['file_id'] = 10;
            } else {
                $attach = $attach->map(function ($item) {
                    $data = UploadImageHelper::getInfoFromOriginUrl($item->path, $item->orig_name, 'default/blank.png');
                    $data['file_id'] = $item->file_upload_id;
                    return $data;
                });
            }
        } else {
            $attach = [];
        }
        $this->response->returnJson($attach);
    }

	public function validateForm() {
		$error = false;
		$this->load->language('account/customerpartner/profile');
		if(strlen(trim($this->request->post['screenName'])) < 1) {
			$this->request->post['screenName'] = '';
			$this->error['screenname_error'] = $this->language->get('error_screen_name');
			$this->error['warning'] = $this->language->get('error_check_form');
			$error = true;
		}else{
			$this->load->model('customerpartner/master');
			$check_screenname = $this->model_customerpartner_master->getShopDataByScreenname($this->request->post['screenName']);
			if ($check_screenname && $check_screenname['customer_id'] != $this->customer->getId()) {
				$this->error['screenname_error'] = $this->language->get('error_screen_name_exists');
				$this->error['warning'] = $this->language->get('error_check_form');
				$error = true;
			}
		}
		$profile = $this->model_account_customerpartner->getProfile();

		if (isset($this->request->post['paypalid']) && $this->request->post['paypalid'] && isset($this->request->post['paypalfirst']) && $this->request->post['paypalfirst'] && isset($this->request->post['paypallast']) && $this->request->post['paypallast']) {
			if(!filter_var($this->request->post['paypalid'], FILTER_VALIDATE_EMAIL)) {
				$this->error['paypal_error'] = $this->language->get('error_paypal');
				$this->error['warning'] = $this->language->get('error_check_form');
				$error = true;
			} else {

				$API_UserName = $this->config->get('marketplace_paypal_user');

				$API_Password = $this->config->get('marketplace_paypal_password');

				$API_Signature = $this->config->get('marketplace_paypal_signature');

				$API_RequestFormat = "NV";

				$API_ResponseFormat = "NV";

				$API_EMAIL = $this->request->post['paypalid'];

				$bodyparams = array(
					"matchCriteria" => "NAME",
					"emailAddress" =>$this->request->post['paypalid'],
					"firstName" => $this->request->post['paypalfirst'],
					"lastName" => $this->request->post['paypallast']
				);

				if ($this->config->get('marketplace_paypal_mode')) {

					$API_AppID = "APP-80W284485P519543T";

					$curl_url = trim("https://svcs.sandbox.paypal.com/AdaptiveAccounts/GetVerifiedStatus");

					$header = array(
						"X-PAYPAL-SECURITY-USERID: " . $API_UserName ,
						"X-PAYPAL-SECURITY-SIGNATURE: " . $API_Signature ,
						"X-PAYPAL-SECURITY-PASSWORD: " . $API_Password ,
						"X-PAYPAL-APPLICATION-ID: " . $API_AppID ,
						"X-PAYPAL-REQUEST-DATA-FORMAT: " . $API_RequestFormat ,
						"X-PAYPAL-RESPONSE-DATA-FORMAT:" . $API_ResponseFormat ,
						"X-PAYPAL-SANDBOX-EMAIL-ADDRESS:" . $API_EMAIL ,
					);
				} else {

					$API_AppID = $this->config->get('marketplace_paypal_appid');

					$curl_url = trim("https://svcs.paypal.com/AdaptiveAccounts/GetVerifiedStatus");

					$header = array(
						"X-PAYPAL-SECURITY-USERID: " . $API_UserName ,
						"X-PAYPAL-SECURITY-SIGNATURE: " . $API_Signature ,
						"X-PAYPAL-SECURITY-PASSWORD: " . $API_Password ,
						"X-PAYPAL-APPLICATION-ID: " . $API_AppID ,
						"X-PAYPAL-REQUEST-DATA-FORMAT: " . $API_RequestFormat ,
						"X-PAYPAL-RESPONSE-DATA-FORMAT:" . $API_ResponseFormat ,
						"X-PAYPAL-EMAIL-ADDRESS:" . $API_EMAIL ,
					);
				}

				$body_data = http_build_query($bodyparams, "", chr(38));

				$curl = curl_init();

				curl_setopt($curl, CURLOPT_URL, $curl_url);

				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

				curl_setopt($curl, CURLOPT_POSTFIELDS, $body_data);


				curl_setopt($curl, CURLOPT_HTTPHEADER,$header);

				$response = strtolower(explode("=",explode('&', curl_exec($curl))[1])[1]);

				if ($response != 'success') {
					$this->error['paypal_error'] = $this->language->get('error_paypal');
					$this->error['warning'] = $this->language->get('error_check_form');
					$error = true;
				}
			}
		} else {
			// $this->request->post['paypalfirst'] = isset($profile['paypalfirstname']) && $profile['paypalfirstname'] ? $profile['paypalfirstname'] : '';
			// $this->request->post['paypallast'] = isset($profile['paypallastname']) && $profile['paypallastname'] ? $profile['paypallastname'] : '';
			// $this->request->post['paypalid'] = isset($profile['paypalid']) && $profile['paypalid'] ? $profile['paypalid'] : '';
		}

		if($error) {
			return false;
		} else {
			return true;
		}

	}

	public function saveImg()
	{
		if ($this->request->post['delete_avatar']) {
			//删除
			$this->request->post['avatar'] = '';
		}else{
			if ($_FILES['avatar_img']['error'] == UPLOAD_ERR_OK) {
				$imgName = $this->createImgName( $_FILES['avatar_img']['name']);
				$imgPath = "catalog/Logo/" . $imgName;
				move_uploaded_file($_FILES['avatar_img']['tmp_name'], DIR_IMAGE . $imgPath);
				$this->request->post['avatar'] = $imgPath;
			}
		}
		if ($this->request->post['delete_companybanner']) {
			//删除
			$this->request->post['companybanner'] = '';

		}else{
			if ($_FILES['companybanner_img']['error'] == UPLOAD_ERR_OK) {
				$imgName = $this->createImgName( $_FILES['companybanner_img']['name']);
				$imgPath = "catalog/Banner/" . $imgName;
				move_uploaded_file($_FILES['companybanner_img']['tmp_name'], DIR_IMAGE . $imgPath);
				$this->request->post['companybanner'] = $imgPath;
			}
		}

	}


	/**
	 * @param $tmpFileName
	 * @return string
	 */
	public function createImgName($tmpFileName)
	{
		$imgName = $this->customer->getId().'_'.$this->customer->getFirstName().'_'.$this->customer->getLastName().substr($tmpFileName, strrpos($tmpFileName, '.'));
		return $imgName;
	}
}
