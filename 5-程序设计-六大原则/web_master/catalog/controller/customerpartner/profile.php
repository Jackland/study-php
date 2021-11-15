<?php

/**
 * @version 2.2.0.0
 * @copyright Webkul Software Pvt Ltd
 */

use App\Components\Storage\StorageCloud;
use App\Enums\Common\CountryEnum;
use App\Exception\InvalidSendMessageException;
use App\Logging\Logger;
use App\Models\Message\Msg;
use App\Repositories\Bd\AccountManagerRepository;
use App\Repositories\Warehouse\WarehouseRepository;
use App\Services\Message\MessageService;

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelCatalogCategory $model_catalog_category
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCatalogSearch $model_catalog_search
 * @property ModelCatalogSearchClickRecord $model_catalog_search_click_record;
 * @property ModelCustomerpartnerMaster $model_customerpartner_master
 * @property ModelCustomerpartnerProfile $model_customerpartner_profile
 * @property ModelCustomerpartnerSellerCenterIndex $model_customerpartner_seller_center_index
 * @property ModelExtensionModuleProductCategory $model_extension_module_product_category
 * @property ModelMessageMessage $model_message_message
 * @property ModelToolCsv $model_tool_csv
 *
 * Class ControllerCustomerpartnerProfile
 */
class ControllerCustomerpartnerProfile extends Controller
{
    const COUNTRY_ENG = [
        'JPN' => 107,
        'GBR' => 222,
        'DEU' => 81,
        'USA' => 223,
    ];
    /**
     * [$error description] Array to contain all errors
     * @var array
     */
    private $error = array();

    /**
     * [loadLocation To load the location of store entered by seller on the google map]
     * @return [map|string] [It will return google map with the location if location found else no location entered by seller string]
     */
    public function loadLocation()
    {
        if ($this->request->get('location')) {
            $location = '<iframe id="seller-location" width="100%" height="200" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q=' . $this->request->get('location') . '&output=embed&z=15"></iframe>';
        } else {
            $location = '<iframe id="seller-location" width="100%" height="200" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q=noida&output=embed&z=15"></iframe>';
            // $this->load->language('customerpartner/profile');
            // $this->response->setOutput($this->language->get('text_no_location_added'));
        }

        $this->response->setOutput($location);
    }

    /**
     * [feedback to load all the feedbacks about the seller]
     * @return [html] [it will return html file]
     * @throws Exception
     */
    public function feedback()
    {
        $seller_id = (int)$this->request->get('id', 0);

        $page = 1;

        $this->load->language('customerpartner/feedback');

        $data['action'] = url()->to(['customerpartner/profile/feedback', 'id' => $seller_id]);

        $this->load->model('customerpartner/master');

        $feedbacks = $this->model_customerpartner_master->getFeedbackList($seller_id);

        echo '<script>
		 $(document).ready(function () {
		 createCookie("time_diff");
		 });

		 function createCookie(name) {
			var rightNow = new Date();
			var jan1 = new Date(rightNow.getFullYear(), 0, 1, 0, 0, 0, 0);
			var temp = jan1.toGMTString();
			var jan2 = new Date(temp.substring(0, temp.lastIndexOf(" ")-1));
			var std_time_offset = (jan1 - jan2) / (1000 * 60 * 60);
		  	document.cookie = escape(name) + "=" + std_time_offset + "; path=/";
		 }
		</script>';

        if (isset($_COOKIE['time_diff'])) {
            $time_diff = $_COOKIE['time_diff'] * 3600;
        }

        $data['feedbacks'] = array();

        if ($feedbacks) {
            $review_fields = $this->model_customerpartner_master->getAllReviewFields();
            $data['review_fields'] = $review_fields;
            foreach ($feedbacks as $key => $feedback) {

                $review_attributes = array();

                if ($review_fields) {
                    foreach ($review_fields as $key => $value) {
                        $attribute_value = $this->model_customerpartner_master->getReviewAttributeValue($feedback['id'], $value['field_id']);
                        if (isset($attribute_value['field_value']) && $attribute_value['field_value']) {
                            $review_attributes[$value['field_id']] = $attribute_value['field_value'];
                        }
                    }
                }

                $date = strtotime($feedback['createdate']);
                if (isset($time_diff) && $time_diff) {
                    $date = $date + $time_diff;
                }
                $data['feedbacks'][] = array(
                    'id' => $feedback['id'],
                    'customer_id' => $feedback['customer_id'],
                    'seller_id' => $feedback['seller_id'],
                    'nickname' => $feedback['nickname'],
                    'summary' => $feedback['summary'],
                    'review' => $feedback['review'],
                    'createdate' => date('F j, Y', $date),
                    'review_attributes' => $review_attributes
                );
            }
        }

        $feedback_total = $this->model_customerpartner_master->getTotalFeedback($seller_id);

        $data['results'] = sprintf($this->language->get('text_pagination'), ($feedback_total) ? (($page - 1) * 5) + 1 : 0, ((($page - 1) * 5) > ($feedback_total - 5)) ? $feedback_total : ((($page - 1) * 5) + 5), $feedback_total, ceil($feedback_total / 5));

        $this->response->setOutput($this->load->view('customerpartner/feedback', $data));
    }

    /**
     * [productFeedback to get feedback on seller's product]
     * @return [html] [It will return html file]
     * @throws Exception
     */
    public function productFeedback()
    {
        $seller_id = (int)$this->request->get('id', 0);

        $page = 1;

        $this->load->language('customerpartner/feedback');

        $this->load->model('customerpartner/master');

        $reviews = $this->model_customerpartner_master->getProductFeedbackList($seller_id);

        $data['reviews'] = array();
        if ($reviews) {
            foreach ($reviews as $key => $review) {
                $d = date_create($review['date_added']);
                $data['reviews'][] = array(
                    'author' => $review['author'],
                    'name' => $review['name'],
                    'href' => url()->to(['product/product', 'product_id' => $review['product_id']]),
                    'text' => $review['text'],
                    'rating' => $review['rating'],
                    'date_added' => date_format($d, 'F j, Y'),
                );
            }
        }

        $product_feedback_total = $this->model_customerpartner_master->getTotalProductFeedbackList($seller_id);

        $data['pagination'] = '';

        $data['results'] = sprintf($this->language->get('text_pagination'), ($product_feedback_total) ? (($page - 1) * 5) + 1 : 0, ((($page - 1) * 5) > ($product_feedback_total - 5)) ? $product_feedback_total : ((($page - 1) * 5) + 5), $product_feedback_total, ceil($product_feedback_total / 5));
        $this->response->setOutput($this->load->view('customerpartner/review', $data));

    }

    /**
     * [Contact to load the languages and information about the sender and reciver]
     * @return   [html]  [it will return html file]
     * @throws Exception
     */
    public function contact()
    {
        $this->load->language('customerpartner/contact');
        $this->load->language('communication/wk_communication');
        //allowed extension
        $extension = configDB('module_wk_communication_type');
        $extensions = explode(',', $extension);
        $data['extension'] = $extensions;
        $data['max'] = configDB('module_wk_communication_max');
        $data['size'] = configDB('module_wk_communication_size');
        $data['size_mb'] = round($data['size'] / 1024, 2) . 'MB';
        $data['type'] = explode(",", configDB('module_wk_communication_type'));
        $data['id'] = $this->request->get('id', 0);

        $this->load->model('customerpartner/profile');
        $checkresult = $this->model_customerpartner_profile->getBuyerAndSellerRelation($this->customer->getId(), $this->request->get('id', 0));

        if (isset($checkresult)) {
            $data['checkResult'] = 0;
        } else {
            $data['checkResult'] = 1;
        }

        if ($this->request->get('itemCode')) {
            $data['itemCode'] = "ItemCode: " . $this->request->get('itemCode');
        }

        $data['email_from'] = $this->customer->getEmail();
        $data['customer_id_to'] = $this->request->post('seller_id');
        $data['action'] = url()->to(['account/customerpartner/sendquery']);
        $data['mail_action'] = url()->to(['account/customerpartner/sendquery/sendSMTPMail']);
        $this->response->setOutput($this->load->view('customerpartner/contact', $data));

    }


    private $upload_result = array();

    public function upload()
    {
        if ($this->request->files['file']['name']) {
            if ($this->validateUpload()) {
                $name = md5(time() . rand(100, 200));
                $ext = pathinfo($this->request->files['file']['name'], PATHINFO_EXTENSION);
                $filename = $name . ($ext ? '.' . $ext : '');
                $fullUrl = StorageCloud::image()->writeFile(request()->filesBag->get('file'), 'attachment', $filename);
                $this->upload_result['url'] = $fullUrl;
                $this->upload_result['success'] = true;
                unset($this->upload_result['error']);
            }
            $this->response->headers->set('Content-Type', 'application/json');
            $this->response->setOutput(json_encode($this->upload_result));
        }
    }

    /**
     * @return bool
     */
    public function validateUpload()
    {
        //站内信配置的单个文件大小  kb
        if ($this->request->files['file']['error']) {
            $this->upload_result['error'] = $this->language->get('error_upload_fail') . $this->request->files['file']['error'];
            return false;
        }
        $size = configDB('module_wk_communication_size');
        if ($this->request->files['file']['size'] > $size * 1024) {
            $size_mb = round($size / 1024, 2);
            $this->upload_result['error'] = $this->language->get('error_max_file_size') . $size_mb . 'MB.';
            return false;
        }
        return true;
    }

    /**
     * [writeFeedback to store customers feedbacks]
     * @return [json] [string containing successful/unsuccessful message]
     * @throws Exception
     */
    public function writeFeedback()
    {

        $this->load->language('customerpartner/feedback');

        $this->load->model('customerpartner/master');

        $json = array();

        if ($this->request->serverBag->get('REQUEST_METHOD') == 'POST') {

            if ((utf8_strlen(trim($this->request->post('name', ''))) < 3) || (utf8_strlen(trim($this->request->post('name', ''))) > 25)) {
                $json['error'] = $this->language->get('error_name');
            }

            if ((utf8_strlen(trim($this->request->post('text', ''))) < 25) || (utf8_strlen(trim($this->request->post('text', '')) > 1000))) {
                $json['error'] = $this->language->get('error_text');
            }

            $attribute_fields = $this->model_customerpartner_master->getAllReviewFields();

            if ($attribute_fields) {
                foreach ($attribute_fields as $key => $value) {
                    if (!isset($this->request->post['review_attributes'][$value['field_id']]) || $this->request->post['review_attributes'][$value['field_id']] < 0 || $this->request->post['review_attributes'][$value['field_id']] > 5) {
                        $json['error'] = $this->language->get('error_attribute');
                    }
                }
            }

            if (!isset($json['error'])) {
                $this->load->model('customerpartner/master');
                $this->model_customerpartner_master->saveFeedback($this->request->post, (int)$this->request->get('id', 0));
                $json['success'] = $this->language->get('text_success');
            }
        }
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * [collection to get seller's product's collection]
     * @return \Symfony\Component\HttpFoundation\RedirectResponse| [html] [It will return html file containing seller's products]
     * @throws Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function collection()
    {
        $run_begin = time();
        $seller_id = (int)$this->request->get('id', 0);

        $this->load->model('tool/image');

        $this->load->model('catalog/category');

        $this->load->model('account/customerpartner');

        $this->load->model('customerpartner/master');

        $this->load->language('customerpartner/collection');

        $this->load->language('product/category');

        $this->load->model('catalog/product');


        // add by LiLei 判断用户是否登录
        $customFields = $this->customer->getId();
        $isPartner = $this->customer->isPartner();

        $data['is_partner'] = $isPartner;
        if (null != $customFields && false == $isPartner) {
            $data['download_csv_privilege'] = 1;
        } else {
            $data['download_csv_privilege'] = 0;
        }

        $customerCountry = null;
        if ($customFields) {
            $data['isLogin'] = true;
            // 判断Customer国别
            $customerCountry = $this->customer->getCountryId();
        } else {
            $data['isLogin'] = false;
        }

        $data['text_compare'] = sprintf($this->language->get('text_compare'), count($this->session->get('compare', [])));

        $partner = $this->model_customerpartner_master->getProfile($seller_id);

        if (!$partner)
            return $this->response->redirectTo(url()->to(['error/not_found']));

        $data['compare'] = url()->to(['product/compare']);

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['page_limit'])) {
            $url .= '&page_limit=' . $this->request->get['page_limit'];
        }

        $url = "&id=" . $seller_id;


        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'p.sort_order';
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

        $limit = (int)$this->request->get('page_limit', 15);

        $filter_data = array(
            'customer_id' => $seller_id,
            'filter_category_id' => 0,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
            'filter_store' => configDB('config_store_id'),
            'filter_status' => 1,
            'filter_buyer_flag' => 1
        );

        $data['categories'] = array();

        //        $categories = $this->model_catalog_category->getCategories(0);

        $data['collection_url'] = url()->to(['customerpartner/profile/collection', 'id' => $seller_id]);

        if (isset($this->request->get['path'])) {
            $parts = explode('_', (string)$this->request->get['path']);
        } else {
            $parts = array();
        }

        $pathLevelCount = count($parts);

        if (isset($parts[0])) {
            $data['category_id'] = $category_id = $parts[0];
        } else {
            $data['category_id'] = $category_id = 0;
        }

        if (isset($parts[1])) {
            $data['child_id'] = $category_id = $parts[1];
        } else {
            $data['child_id'] = 0;
        }

        //获取
        $category_all_id = $this->model_catalog_product->getAllCategoryId($this->request->get('path', ''));

        $filter_data ['filter_category_id'] = $category_all_id;

        $data['categories'] = [];
        /*B2B页面改版*/
        /*第三级 分类*/
        $data['pathTwoId'] = $data['child_id'];
        if ($data['pathTwoId']) {

            $results = $this->model_catalog_category->getCategories($data['pathTwoId']);
            if ($pathLevelCount > 2) {
                $pathTmp = $this->request->get['path'];
                $pathTmpArr = explode('_', $pathTmp);
                array_pop($pathTmpArr);
                $pathTmp = implode('_', $pathTmpArr);
            }


            foreach ($results as $result) {
                $isActive = false;
                $isHref = '';
                if ($pathLevelCount <= 2) {
                    $isHref = $this->url->link('customerpartner/profile/collection', 'path=' . $this->request->get['path'] . '_' . $result['category_id'] . $url);
                } else {
                    //点击第三级、更多级分类
                    $isHref = $this->url->link('customerpartner/profile/collection', 'path=' . $pathTmp . '_' . $result['category_id'] . $url);
                }


                if (end($parts) == $result['category_id']) {
                    $isActive = true;
                }


                $data['categories'][] = array(
                    'name' => $result['name'],
                    'href' => $isHref,
                    'isActive' => $isActive
                );
            }
        }

        if (isset($this->request->get['path'])) {
            $url .= '&path=' . $this->request->get['path'];
        }

        isset($this->request->get['min_price']) && trim($this->request->get['min_price']) !== '' && $filter_data['min_price'] = $this->request->get['min_price'];
        isset($this->request->get['max_price']) && trim($this->request->get['max_price']) !== '' && $filter_data['max_price'] = $this->request->get['max_price'];
        isset($this->request->get['min_quantity']) && trim($this->request->get['min_quantity']) !== '' && $filter_data['min_quantity'] = $this->request->get['min_quantity'];
        isset($this->request->get['max_quantity']) && trim($this->request->get['max_quantity']) !== '' && $filter_data['max_quantity'] = $this->request->get['max_quantity'];

        $results = $this->model_account_customerpartner->getProductsSeller($filter_data);

        $product_total_str = $this->model_account_customerpartner->getTotalProductsSeller($filter_data, 1);

        $product_total = count(explode(',', $product_total_str));
        $data['products'] = array();
        //$receipt_array = $this->model_catalog_product->getReceptionProduct();
        foreach ($results as $result) {

            //$product_info = $this->model_catalog_product->getProduct($result['product_id'], $customFields);
            //
            //if (isset($product_info['price']) && $product_info['price']) {
            //    $result['price'] = $product_info['price'];
            //}
            //
            //if ($result['image'] && is_file(DIR_IMAGE . $result['image'])) {
            //    $image = $this->model_tool_image->resize($result['image'], configDB('theme_' . configDB('config_theme') . '_image_product_width'), configDB('theme_' . configDB('config_theme') . '_image_product_height'));
            //} else {
            //    $image = $this->model_tool_image->resize('placeholder.png', configDB('theme_' . configDB('config_theme') . '_image_product_width'), configDB('theme_' . configDB('config_theme') . '_image_product_height'));
            //}
            //
            //if ((configDB('config_customer_price') && $this->customer->isLogged()) || !configDB('config_customer_price')) {
            //    $price = $this->currency->formatCurrencyPrice($this->tax->calculate($result['price'], $result['tax_class_id'], configDB('config_tax')), $this->session->get('currency', 'USD'));
            //} else {
            //    $price = false;
            //}
            ////add by xxli
            //$discountResult = $this->model_catalog_product->getDiscount($customFields, $product_info['customer_id']);
            //if ($discountResult) {
            //    $price = $this->model_catalog_product->getDiscountPrice($result['price'], $discountResult);
            //    if ($customerCountry) {
            //        if ($this->customer->getGroupId() == 13) {
            //            $price = $this->country->getDisplayPrice($customerCountry, $price);
            //        }
            //    }
            //    $price = $this->currency->formatCurrencyPrice($price, $this->session->get('currency', 'USD'));
            //}
            ////end xxli
            //
            //if ((float)$result['special']) {
            //    $special = $this->currency->formatCurrencyPrice($this->tax->calculate($result['special'], $result['tax_class_id'], configDB('config_tax')), $this->session->get('currency', 'USD'));
            //} else {
            //    $special = false;
            //}
            //
            //if (configDB('config_tax')) {
            //    $tax = $this->currency->formatCurrencyPrice((float)$result['special'] ? $result['special'] : $result['price'], $this->session->get('currency', 'USD'));
            //} else {
            //    $tax = false;
            //}
            //
            //if (configDB('config_review_status')) {
            //    $rating = (int)$result['rating'];
            //} else {
            //    $rating = false;
            //}
            //
            //$tag_array = $this->model_catalog_product->getProductTagHtmlForThumb($result['product_id']);
            //
            ////查看该产品是否被订阅 edit by xxl
            //$productWishList = $this->model_catalog_product->getWishListProduct($result['product_id'],$customFields);
            //$data['products'][] = array(
            //    'loginId' => $this->customer->getId(),
            //    'totalSales' => $product_info['totalSale'],
            //    '30Day' => $product_info['30Day'],
            //    'pageView' => $product_info['pageView'],
            //    'customer_id' => $product_info['customer_id'],
            //    'sku' => $product_info['sku'],
            //    'mpn' => $product_info['mpn'],
            //    'self_support' => $product_info['self_support'],
            //    'price_display' => $product_info['price_display'],
            //    'quantity_display' => $product_info['quantity_display'],
            //    'seller_price' => $product_info['seller_price'],
            //    'can_sell' => $product_info['canSell'],
            //    'quantity' => $product_info['c2pQty'],
            //    'product_id' => $result['product_id'],
            //    'thumb' => $image,
            //    'name' => $result['name'],
            //    'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0, configDB('theme_' . configDB('config_theme') . '_product_description_length')) . '..',
            //    'price' => $price,
            //    'special' => $special,
            //    'minimum' => $result['minimum'],
            //    'tax' => $tax,
            //    'rating' => $result['rating'],
            //    'href' => $this->url->link('product/product', '&product_id=' . $result['product_id'], true),
            //    'contactUrl' => $this->url->link('customerpartner/profile', '&id=' . $product_info['customer_id'] . '&itemCode=' . $product_info['sku'] . '&contact=1'),
            //    'tag' => $tag_array,
            //    'productWishList' =>$productWishList,
            //    'receipt'     => isset($receipt_array[$result['product_id']]) ? $receipt_array[$result['product_id']] : null
            //);

            $data['products'][] = $result;
            $data['products_arr'][] = array(
                'product_id' => $result['product_id'],
                //'thumb'       => $image,
                'sku' => $result['sku'],
                //'name'        => $result['name'],

            );
        }

        $data['sorts'] = array();

        $data['sorts'][] = array(
            'text' => $this->language->get('text_default'),
            'value' => 'p.sort_order-ASC',
            'href' => $this->url->link('customerpartner/profile/collection', '&sort=p.sort_order&order=ASC' . $url, true)
        );

        $data['sorts'][] = array(
            'text' => $this->language->get('text_name_asc'),
            'value' => 'pd.name-ASC',
            'href' => $this->url->link('customerpartner/profile/collection', '&sort=pd.name&order=ASC' . $url, true)
        );

        $data['sorts'][] = array(
            'text' => $this->language->get('text_name_desc'),
            'value' => 'pd.name-DESC',
            'href' => $this->url->link('customerpartner/profile/collection', '&sort=pd.name&order=DESC' . $url, true)
        );

        $data['sorts'][] = array(
            'text' => $this->language->get('text_price_asc'),
            'value' => 'p.price-ASC',
            'href' => $this->url->link('customerpartner/profile/collection', '&sort=p.price&order=ASC' . $url, true)
        );

        $data['sorts'][] = array(
            'text' => $this->language->get('text_price_desc'),
            'value' => 'p.price-DESC',
            'href' => $this->url->link('customerpartner/profile/collection', '&sort=p.price&order=DESC' . $url, true)
        );

        //if (configDB('config_review_status')) {
        //    $data['sorts'][] = array(
        //        'text' => $this->language->get('text_rating_desc'),
        //        'value' => 'rating-DESC',
        //        'href' => $this->url->link('customerpartner/profile/collection', '&sort=rating&order=DESC' . $url, true)
        //    );
        //
        //    $data['sorts'][] = array(
        //        'text' => $this->language->get('text_rating_asc'),
        //        'value' => 'rating-ASC',
        //        'href' => $this->url->link('customerpartner/profile/collection', '&sort=rating&order=ASC' . $url, true)
        //    );
        //}
        //
        //$data['sorts'][] = array(
        //    'text' => $this->language->get('text_model_asc'),
        //    'value' => 'p.model-ASC',
        //    'href' => $this->url->link('customerpartner/profile/collection', '&sort=p.model&order=ASC' . $url, true)
        //);
        //
        //$data['sorts'][] = array(
        //    'text' => $this->language->get('text_model_desc'),
        //    'value' => 'p.model-DESC',
        //    'href' => $this->url->link('customerpartner/profile/collection', '&sort=p.model&order=DESC' . $url, true)
        //);

        $url = "id=" . $seller_id;

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['path'])) {
            $url .= '&path=' . $this->request->get['path'];
        }

        $data['limits'] = array();

        $limits = array_unique(array(15, 30, 60, 90, 120));

        sort($limits);

        foreach ($limits as $value) {
            $data['limits'][] = array(
                'text' => $value,
                'value' => $value,
                'href' => $this->url->link('customerpartner/profile/collection', $url . '&page_limit=' . $value, true)
            );
        }

        $url = "id=" . $seller_id;

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['page_limit'])) {
            $url .= '&page_limit=' . $this->request->get['page_limit'];
        }

        if (isset($this->request->get['path'])) {
            $url .= '&path=' . $this->request->get['path'];
        }

        $pagination = new Pagination();
        $pagination->total = $product_total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->pageList = $limits;
        $pagination->renderScript = false;
        $pagination->text = $this->language->get('text_pagination');
        $pagination->url = $this->url->link('customerpartner/profile/collection', $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $this->document->addLink($this->url->link('customerpartner/profile/collection', $url . '&page=' . $pagination->page, true), 'canonical');

        if ($pagination->limit && ceil($pagination->total / $pagination->limit) > $pagination->page) {
            $this->document->addLink($this->url->link('customerpartner/profile/collection', $url . '&page=' . ($pagination->page + 1), true), 'next');
        }

        if ($pagination->page > 1) {
            $this->document->addLink($this->url->link('customerpartner/profile/collection', $url . '&page=' . ($pagination->page - 1), true), 'prev');
        }

        $data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['limit'] = $limit;
        $data['login'] = url()->to(['account/login']);
        $run_end = time();
        $time = $run_end - $run_begin;
        $data['isJapanAddMoney'] = '';
        if (!empty($this->customer->getCountryId()) && $this->customer->getCountryId() == CountryEnum::JAPAN) {
            $data['isJapanAddMoney'] = '00';
        }
        $data['products_arr'] = isset($data['products_arr']) ? $data['products_arr'] : null;
        $data['products_total'] = $product_total;
        $data['products_json'] = json_encode($data['products_arr']);
        $data['products_to_csv'] = url()->to(['customerpartner/profile/allSellersProductsInfo', 'id' => $seller_id]);
        $this->cache->set($seller_id . '_product_total_str', $product_total_str);
        $this->response->setOutput($this->load->view('customerpartner/collection', $data));
    }


    /**
     * 处理20210430batch download临时问题处理
     */
    public function allSellersProductsInfo()
    {
        //验证是否有下载权限
        $seller_id = (int)$this->request->get('id', 0);
        $custom_id =  $this->customer->getId();
        // 判断是否为 buyer 非buyer用户 直接无法获取下载权限
        $this->load->model('account/customerpartner');
        $isPartner  = $this->customer->isPartner();
        if(null == $custom_id || true  == $isPartner){
            return $this->response->redirectTo(url()->to('common/home'));
        }
        $type = $this->request->get('type');
        $product_str = $this->request->get('product_str');
        if($type == 0 && null != $product_str){
            $product_total_str = $product_str;
        }else{
            if($this->cache->get($seller_id.'_product_total_str')){
                $product_total_str = $this->cache->get($seller_id.'_product_total_str');
            }else{
                echo "<script>window.location.href=document.referrer; </script>";
                exit;
            }

        }
        $this->load->model('catalog/product');
        $data = $this->model_catalog_product->getProductCategoryInfoByMySeller($product_total_str,$custom_id);
        //获取csv
        $this->load->model('tool/csv');
        $filename = 'ProductsInfo_'.date("YmdHis",time()).".csv";
        $this->model_tool_csv->getProductCategoryCsvByMySeller($filename,$data);

    }

    /**
     * [allSellersProductsInfo description] 获取seller下选中的产品导出
     */
    public function allSellersProductsInfoBk(){
        //验证是否有下载权限
        $seller_id = (int)$this->request->get('id', 0);
        $custom_id = $this->customer->getId();
        // 判断是否为 buyer 非buyer用户 直接无法获取下载权限
        $this->load->model('account/customerpartner');
        $isPartner = $this->customer->isPartner();
        if (null == $custom_id || true == $isPartner) {
            return $this->response->redirectTo(url()->to(['common/home']));
        }
        $type = $this->request->get('type');
        $product_str = $this->request->get('product_str');
        if ($type == 0 && null != $product_str) {
            $product_total_str = $product_str;
        } else {
            if ($this->cache->get($seller_id . '_product_total_str')) {
                $product_total_str = $this->cache->get($seller_id . '_product_total_str');
            } else {
                echo "<script>window.location.href=document.referrer; </script>";
                exit;
            }

        }
        $this->load->model('catalog/product');
        $data = $this->model_catalog_product->getProductCategoryInfo($product_total_str, $custom_id);
        //获取csv
        $this->load->model('tool/csv');
        $filename = 'ProductsInfo_' . date("YmdHis", time()) . ".csv";
        $this->model_tool_csv->getProductCategoryCsv($filename, $data);

    }

    /**
     * add by xxli
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws Exception
     */
    public function getCatagory()
    {
        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('account/customerpartner');

        $this->load->model('customerpartner/master');
        $seller_id = (int)$this->request->get('id', 0);

        $url = '';


        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['limit'])) {
            $url .= '&limit=' . $this->request->get['limit'];
        }

        $url = "&id=" . $seller_id;

        $sort = $this->request->get('sort', 'p.sort_order');
        $order = $this->request->get('order', 'ASC');
        $page = (int)$this->request->get('page', 1);
        $limit = (int)$this->request->get('limit', 15);

        $filter_data = array(
            'customer_id' => $seller_id,
            'filter_category_id' => 0,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
            'filter_store' => configDB('config_store_id'),
            'filter_status' => 1,
            'filter_buyer_flag' => 1
        );

        $data['categories'] = array();

        // 获取顶级分类
        $categories = $this->model_catalog_category->getCategories(0);

        foreach ($categories as $category) {
            $children_data = array();

            $children = $this->model_catalog_category->getCategories($category['category_id']);

            foreach ($children as $child) {


                $category_all_id = $this->model_catalog_product->getAllCategoryId($child['category_id']);
                $filter_data ['filter_category_id'] = $category_all_id;

                $products_in_category = $this->model_account_customerpartner->getTotalProductsSeller($filter_data);

                if ($products_in_category)
                    $children_data[] = array(
                        'category_id' => $child['category_id'],
                        'name' => $child['name'] . (configDB('config_product_count') ? ' (' . $products_in_category . ')' : ''),
                        'href' => $this->url->link('customerpartner/profile/collection', 'path=' . $category['category_id'] . '_' . $child['category_id'] . $url, true)
                    );
            }

            $filter_data ['filter_category_id'] = $category['category_id'];

            $products_in_category = $this->model_account_customerpartner->getTotalProductsSeller($filter_data);

            if ($products_in_category) {
                $data['categories'][] = array(
                    'category_id' => $category['category_id'],
                    'name' => $category['name'] . (configDB('config_product_count') ? ' (' . $products_in_category . ')' : ''),
                    'children' => $children_data,
                    'href' => $this->url->link('customerpartner/profile/collection', 'path=' . $category['category_id'] . $url, true)
                );
            } elseif ($children_data) {
                $data['categories'][] = array(
                    'category_id' => $category['category_id'],
                    'name' => $category['name'] . (configDB('config_product_count') ? ' (' . count($children_data) . ')' : ''),
                    'children' => $children_data,
                    'href' => $this->url->link('customerpartner/profile/collection', 'path=' . $category['category_id'] . $url, true)
                );
            }
        }

        $data['isJapanAddMoney'] = '';
        if (!empty($this->customer->getCountryId()) && $this->customer->getCountryId() == CountryEnum::JAPAN) {
            $data['isJapanAddMoney'] = '00';
        }
        $data['symbol_left'] = $this->currency->getSymbolLeft($this->session->get('currency', 'USD'));
        $data['symbol_right'] = $this->currency->getSymbolRight($this->session->get('currency', 'USD'));
        $data['collection_url'] = url()->to(['customerpartner/profile/collection', 'id' => $seller_id]);


        $this->response->setOutput($this->load->view('customerpartner/collection', $data));
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function establishContact()
    {
        $data = array();
        $this->load->model('message/message');
        $this->load->language('customerpartner/profile');
        $post_data = $this->request->post;
        if (!$this->customer->isLogged()) {
            $data['error'] = $this->language->get('error_need_login');
        } elseif ($this->customer->isPartner()) {
            $data['error'] = $this->language->get('error_need_buyer');
        } elseif (!isset($post_data['subject']) || !isset($post_data['message']) || !isset($post_data['seller_id'])) {
            $data['error'] = $this->language->get('error_invalid_param');
        }
        $send_id = customer()->getId(); // 申请的buyer id
        $seller_id = $post_data['seller_id']; // 需要建立联系的seller id
        if (!isset($data['error'])) {
            try {
                $this->model_message_message->checkSendMessageValid((int)$send_id, (int)$seller_id);
                $msgId = app(MessageService::class)->buildMsg($send_id, $post_data['subject'], $post_data['message'], [], [$seller_id]);
                $return = Msg::query()->where('id', $msgId)->update(['status' => 100]);
                if ($return) {
                    $data['success'] = $this->language->get('text_establish_success');
                } else {
                    $data['error'] = $this->language->get('error_save_message');
                }
            } catch (InvalidSendMessageException $e) {
                Logger::error($e);
                $data['error'] = $e->getMessage();
            } catch (Throwable $e) {
                Logger::error($e);
                $data['error'] = $e->getMessage();
            }
        }
        return $this->response->json($data);
    }

    // 店铺产品列表页，路由已修改至：seller_store/products
    // 因为业务逻辑较为复杂，因为保留代码逻辑
    // 修改原因：#11432 新做的店铺主页，原所有点击跳转到该路由下的基本都应该跳转到 seller_store/home（店铺主页）
    // 但因为系统中使用 customerpartner/profile 路由的太多，因此做该种形式的跳转
    /**
     * @param array $params
     * @return string|\Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws Exception
     */
    public function index($params = [])
    {
        $params = array_merge([
            'from_seller_store_products' => false, // 定义是否是从 seller_store/products 页面调用的，是则直接调用方法渲染视图，否则会重定向
            'id' => 0, // sellerId
        ], $params);

        if (!$params['from_seller_store_products']) {
            $queries = request()->query->all();
            unset($queries['route'], $queries['id']);
            if (count($queries) > 0) {
                // 携带除 route 和 id 以外的其他参数的，跳转到新的店铺产品页
                return $this->redirect(url()->withRoute('seller_store/products')->withCurrentQueries()->build(), 301);
            } else {
                // 跳转到店铺主页
                return $this->redirect(url()->withRoute('seller_store/home')->withCurrentQueries()->build(), 301);
            }
        }

        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $this->load->model('catalog/search');
        $this->load->model('customerpartner/master');
        $this->load->model('customerpartner/profile');
        $this->load->language('customerpartner/profile');
        $this->load->language('product/search');
        $this->load->model('customerpartner/seller_center/index');

        $seller_id = $params['id'];
        $data['isShowPrice'] = false;
        $customer_id = 0;
        if ($this->customer->isLogged()) {
            $customer_id = $this->customer->getId();
            $data['isShowPrice'] = true;
        }
        if (configDB('marketplace_seller_info_hide') && $seller_id != $customer_id) {
            return $this->response->redirectTo(url()->to(['common/home']));
        }
        if (request('click') ?? 0) {
            $this->load->model('catalog/search_click_record');
            $this->model_catalog_search_click_record->saveRecord(request('click'), request('order') ?? '');
        }
        $isPartner = $this->customer->isPartner();
        $data['is_partner'] = $isPartner;
        if (($customer_id && false == $isPartner)) {
            $data['download_csv_privilege'] = 1;
        } else {
            $data['download_csv_privilege'] = 0;
        }
        $request_search = isset($this->request->get['search']) ? (trim(mb_substr(htmlspecialchars_decode($this->request->get['search']), 0, 100))) : null;
        $filter_data['seller_id'] = $seller_id;
        $filter_data['search'] = isset($request_search) ? htmlentities(htmlspecialchars_decode($request_search)) : '';
        $filter_data['category_id'] = (int)$this->request->get('category_id', 0);
        $filter_data['min_price'] = $this->request->get('min_price', '');
        $filter_data['max_price'] = $this->request->get('max_price', '');
        $filter_data['min_quantity'] = $this->request->get('min_quantity', '');
        $filter_data['max_quantity'] = $this->request->get('max_quantity', '');
        $filter_data['qty_status'] = $this->request->get('qty_status', '');
        $filter_data['download_status'] = (int)$this->request->get('download_status', 0);
        $filter_data['wish_status'] = (int)$this->request->get('wish_status', 0);
        $filter_data['purchase_status'] = (int)$this->request->get('purchase_status', 0);
        $filter_data['relation_status'] = (int)$this->request->get('relation_status', 0);
        $filter_data['img_status'] = (int)$this->request->get('img_status', 0);
        //新增复杂交易类型的查询
        $filter_data['rebates'] = $this->request->get('rebates', false);
        $filter_data['margin'] = $this->request->get('margin', false);
        $filter_data['futures'] = $this->request->get('futures', false);
        //新增仓库库存的查询
        $filter_data['whId'] = $this->request->get('whId', '') == '' ? [] : explode(',', $this->request->get('whId'));
        $filter_data['sort'] = $this->request->get('sort', 'p.sort_order');
        $filter_data['order'] = $this->request->get('order', 'desc');
        $filter_data['page'] = intval($this->request->get('page', 1));
        $filter_data['limit'] = intval($this->request->get('limit', 20));
        $filter_data['start'] = ($filter_data['page'] - 1) * $filter_data['limit'];
        $filter_data['country'] = $this->session->get('country');

        // 从product页面跳转过来有没有夹带itemCode
        if (request('itemCode')) {
            $data['itemCode'] = request('itemCode');
        }

        $data['products'] = array();
        $categoryIds = null;
        $productIdList = [];
        $product_total = 0;
        if ($filter_data['seller_id']
            || $filter_data['category_id']
            || $filter_data['max_price'] != ''
            || $filter_data['min_price'] != ''
            || $filter_data['max_quantity'] != ''
            || $filter_data['min_quantity'] != '') {

            try {
                $tmp = $this->model_catalog_search->searchRelevanceProductId($filter_data, $customer_id);
            } catch (Exception $e) {
                Logger::app($e);
                $tmp = null;
            }
            if ($tmp) {
                $product_total = $tmp['total'];
                $product_total_str = implode(',', $tmp['allProductIds']);
                $results = $this->model_catalog_search->search($filter_data, $customer_id, $isPartner, $tmp);
                $categoryIds = $tmp['categoryIds'];
            } else {
                $product_total_str = '';
                $results = [];
            }

            foreach ($results as $result) {
                $data['products'][] = $result;
                //13642 【需求】一览界面批量下载功能优化 明细：增加输入关键字检索页面的下载功能，更新下载表格的英文表述
                $data['products_arr'][] = array(
                    'product_id' => $result['product_id'],
                    'sku' => $result['sku'],
                );
            }

        }


        //左侧店铺商品分类栏
        $this->load->model('extension/module/product_category');
        $categories = $this->model_extension_module_product_category->getCategoryById($filter_data['category_id'], $productIdList, $categoryIds);
        $category_all_list = [];
        foreach ($categories as $id1 => $category1) {
            $category_all_list[$category1['self_id']] = $category1['all_pid'];
            foreach ($category1['children'] ?? [] as $id2 => $category2) {
                $category_all_list[$category2['self_id']] = $category2['all_pid'];
                foreach ($category2['children'] ?? [] as $id3 => $category3) {
                    $category_all_list[$category3['self_id']] = $category3['all_pid'];
                }
            }
        }

        if (isset($category_all_list[$filter_data['category_id']])) {
            $pid_all = $category_all_list[$filter_data['category_id']];
            $pid_all_list = explode('_', $pid_all);
            $count = count($pid_all_list);

        } else {
            $count = 0;
            $pid_all_list = [];
        }

        $data['pid_count'] = $count;
        $data['pid_all_list'] = $pid_all_list;

        $data['categories'] = $categories;

        $storeUrl = $url = '&id=' . $filter_data['seller_id'];
        if (isset($this->request->get['category_id'])) {
            $url .= '&category_id=' . $this->request->get['category_id'];
        }
        $sellerUrl = $url;
        if (isset($this->request->get['min_price'])) {
            $url .= '&min_price=' . $filter_data['min_price'];
        }

        if (isset($this->request->get['max_price'])) {
            $url .= '&max_price=' . $filter_data['max_price'];
        }

        if (isset($this->request->get['min_quantity'])) {
            $url .= '&min_quantity=' . $filter_data['min_quantity'];
        }

        if (isset($this->request->get['max_quantity'])) {
            $url .= '&max_quantity=' . $filter_data['max_quantity'];
        }

        // 后续加的
        $searchCondition = [
            'rebates',
            'margin',
            'futures',
            'whId',
        ];
        array_map(function ($item) use (&$url) {
            if ($this->request->get($item, false)) {
                $url .= "&{$item}=" . $this->request->get($item);
            }
        }, $searchCondition);

        $mainUrl = $url;

        if (isset($this->request->get['download_status'])) {
            $url .= '&download_status=' . $filter_data['download_status'];
        }
        if (isset($this->request->get['wish_status'])) {
            $url .= '&wish_status=' . $filter_data['wish_status'];
        }
        if (isset($this->request->get['purchase_status'])) {
            $url .= '&purchase_status=' . $filter_data['purchase_status'];
        }
        if (isset($this->request->get['relation_status'])) {
            $url .= '&relation_status=' . $filter_data['relation_status'];
        }
        if (isset($this->request->get['img_status'])) {
            $url .= '&img_status=' . $filter_data['img_status'];
        }
        if (isset($this->request->get['qty_status'])) {
            $url .= '&qty_status=' . $filter_data['qty_status'];
        }
        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }
        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }
        if ($this->request->get('search')) {
            $url .= '&search=' . $this->request->get('search');
        }
        $data['limits'] = array();
        $limits = [20, 40, 60, 100];
        foreach ($limits as $value) {
            $data['limits'][] = array(
                'text' => $value,
                'value' => $value,
                'href' => $this->url->link('customerpartner/profile', $url . '&limit=' . $value)
            );
        }

        if (isset($this->request->get['limit'])) {
            $mainUrl .= '&limit=' . $this->request->get('limit');
            $url .= '&limit=' . $this->request->get('limit');
        }
        $pagination = new Pagination();
        $pagination->total = $product_total;
        $pagination->page = $filter_data['page'];
        $pagination->limit = $filter_data['limit'];
        $pagination->limit_key = 'limit';
        $pagination->pageList = $limits;
        $pagination->url = $this->url->link('seller_store/products', $url . '&page={page}');

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'),
            ($product_total) ? (($filter_data['page'] - 1) * $filter_data['limit']) + 1 : 0,
            ((($filter_data['page'] - 1) * $filter_data['limit']) > ($product_total - $filter_data['limit'])) ? $product_total : ((($filter_data['page'] - 1) * $filter_data['limit']) + $filter_data['limit']),
            $product_total, ceil($product_total / $filter_data['limit']));
        $filter_data['search'] = urldecode($filter_data['search']);
        $data = array_merge($data, $filter_data);
        $data['store_url'] = $this->url->link('seller_store/products', $storeUrl);
        $data['seller_url'] = $this->url->link('seller_store/products', $sellerUrl);
        $data['main_url'] = $this->url->link('seller_store/products', $mainUrl);
        $this->document->setTitle('Profile');

        $data['symbol_left'] = $this->currency->getSymbolLeft($this->session->get('currency', 'USD'));
        $data['symbol_right'] = $this->currency->getSymbolRight($this->session->get('currency', 'USD'));

        $data['products_arr'] = isset($data['products_arr']) ? $data['products_arr'] : null;
        $data['products_total'] = $product_total;
        $data['products_json'] = json_encode($data['products_arr']);
        if (isset($product_total_str)) {
            $data['products_to_csv'] = url()->to(['product/category/all_products_info', 'path' => ($request_search ?? '')]);
            $this->session->data['search_' . ($request_search ?? '')]['product_total_str'] = $product_total_str;
            $data['products_to_wish'] = url()->to(['account/wishlist/batchAdd', 'path' => ($request_search ?? '')]);
        }
        $data['isLogin'] = $customer_id ? true : false;
        $data['login'] = url()->to(['account/login']);
        /*
        * 仓库筛选条件，仅美国本土上门取货账号支持该筛选条件
        * 美国本土Buyer：招商经理为美国的BD，即招商经理表中区域信息为美国；
       */
        $accountManagerRepo = app(AccountManagerRepository::class);
        $data['isWarehouseProductDistribution'] = false;
        if ($this->customer->isCollectionFromDomicile()
            && $accountManagerRepo->isAmericanBd($customer_id)
        ) {
            $data['warehouseList'] = app(WarehouseRepository::class)::getActiveAmericanWarehouse();
            $data['isWarehouseProductDistribution'] = true;
        }

        return $this->render('customerpartner/store_home', $data, 'buyer_seller_store');
    }

}
