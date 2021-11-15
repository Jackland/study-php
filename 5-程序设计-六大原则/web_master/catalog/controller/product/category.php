<?php

use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Repositories\Bd\AccountManagerRepository;
use App\Repositories\Warehouse\WarehouseRepository;
use Illuminate\Support\Carbon;

/**
 * Class ControllerProductCategory
 * @property ModelCatalogCategory model_catalog_category
 * @property ModelCatalogProduct model_catalog_product
 * @property ModelCatalogSearch $model_catalog_search
 * @property ModelCatalogSearchClickRecord $model_catalog_search_click_record
 * @property ModelaccountSearch $model_account_search
 * @property ModelCustomerPartnerBuyerToSeller $model_customerpartner_BuyerToSeller
 * @property ModelToolCsv $model_tool_csv
 * @property ModelToolImage $model_tool_image
 * @property ModelExtensionModuleProductCategory $model_extension_module_product_category
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 */
class ControllerProductCategory extends Controller {

    public function index1() {
        $this->load->language('product/category');

        $this->load->model('catalog/category');

        $this->load->model('catalog/product');

        $this->load->model('tool/image');

        $this->load->model('extension/module/product_show');
        $this->document->addStyle('catalog/view/theme/default/stylesheet/MP/profile.css');
        if (isset($this->request->get['filter'])) {
            $filter = $this->request->get['filter'];
        } else {
            $filter = '';
        }

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

        if (isset($this->request->get['page_limit'])) {
            $limit = (int)$this->request->get['page_limit'];
        } else {
            $limit = $this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
        }

        // add by LiLei 判断用户是否登录
        $customFields =  $this->customer->getId();
        $country_id = $this->customer->getCountryId();
        // 判断是否为 buyer 非buyer用户 直接无法获取下载权限
        $this->load->model('account/customerpartner');
        $isPartner  = $this->customer->isPartner();
        $data['is_partner'] = $isPartner;
        if(null != $customFields && false == $isPartner){
            $data['download_csv_privilege'] = 1;
        }else{
            $data['download_csv_privilege'] = 0;
        }


        if ($customFields) {
            $data['isLogin'] = true;
        } else {
            $data['isLogin'] = false;
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );


        /*B2B页面改版*/
        $pathLevelCount = 0;
        $pathOneId      = 0;
        $pathTwoId      = 0;


        if (isset($this->request->get['path'])) {
            $url = '';

            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }

            if (isset($this->request->get['page_limit'])) {
                $url .= '&limit=' . $this->request->get['page_limit'];
            }

            $path = '';

            $parts = explode('_', (string)$this->request->get['path']);

            /*B2B页面改版*/
            //因为下面有array_pop，所以这段代码要在array_pop上面
            $pathLevelCount = count($parts);
            if ($pathLevelCount >= 2) {
                $pathTwoId = $parts[1];
            }
            $pathOneId         = reset($parts);
            $data['pathOneId'] = $pathOneId;




            $data['parts'] = end($parts);
            $category_id = (int)array_pop($parts);

            foreach ($parts as $path_id) {
                if (!$path) {
                    $path = (int)$path_id;
                } else {
                    $path .= '_' . (int)$path_id;
                }

                $category_info = $this->model_catalog_category->getCategory($path_id);

                if ($category_info) {
                    $data['breadcrumbs'][] = array(
                        'text' => $category_info['name'],
                        'href' => $this->url->link('product/category', 'path=' . $path . $url)
                    );
                }
            }

        } else {
            $category_id = 0;
        }

        /*B2B页面改版*/
        $data['category_id'] = $category_id;




        $category_info = $this->model_catalog_category->getCategory($category_id);

        if ($category_info) {
            $this->document->setTitle($category_info['meta_title']);
            $this->document->setDescription($category_info['meta_description']);
            $this->document->setKeywords($category_info['meta_keyword']);

            $data['heading_title'] = $category_info['name'];

            $data['text_compare'] = sprintf($this->language->get('text_compare'), count(session('compare', [])));

            // Set the last category breadcrumb
            $data['breadcrumbs'][] = array(
                'text' => $category_info['name'],
                'href' => $this->url->link('product/category', 'path=' . $this->request->get['path'])
            );

            if ($category_info['image']) {
                $data['thumb'] = $this->model_tool_image->resize($category_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_height'));
            } else {
                $data['thumb'] = '';
            }

            $data['description'] = html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8');
            $data['compare'] = $this->url->link('product/compare');

            $url = '';

            if (isset($this->request->get['filter'])) {
                $url .= '&filter=' . $this->request->get['filter'];
            }

            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }

            if (isset($this->request->get['page_limit'])) {
                $url .= '&limit=' . $this->request->get['page_limit'];
            }

            $data['categories'] = array();
            /*B2B页面改版*/
            /*第三级 分类*/
            $data['pathTwoId'] = $pathTwoId;
            if($pathTwoId ) {

                $results  = $this->model_catalog_category->getCategories($pathTwoId);
                if($pathLevelCount > 2){
                    $pathTmp = $this->request->get['path'];
                    $pathTmpArr = explode('_', $pathTmp);
                    array_pop($pathTmpArr);
                    $pathTmp = implode('_', $pathTmpArr);
                }


                foreach ($results as $result) {
                    $isActive = false;
                    $isHref   = '';
                    if($pathLevelCount <=2){
                        $isHref = $this->url->link('product/category', 'path=' . $this->request->get['path'] . '_' . $result['category_id'] . $url);
                    } else {
                        //点击第三级、更多级分类
                        $isHref = $this->url->link('product/category', 'path=' . $pathTmp . '_' . $result['category_id']. $url);
                    }


                    if($category_id == $result['category_id']){
                        $isActive = true;
                    }


                    $data['categories'][] = array(
                        'name' => $result['name'],
                        'href' => $isHref,
                        'isActive'=>$isActive
                    );
                }
            }


            $data['products'] = array();
            //获取
            $category_all_id = $this->model_catalog_product->getAllCategoryId($this->request->get['path']);
            $filter_data = array(
                'filter_category_id' => $category_all_id,
                'filter_filter'      => $filter,
                'sort'               => $sort,
                'order'              => $order,
                'start'              => ($page - 1) * $limit,
                'limit'              => $limit,
                'customer_id'        => $customFields,
                'country'        =>session('country'),
            );
            isset($this->request->get['min_price']) && trim($this->request->get['min_price']) !== '' && $filter_data['min_price'] = $this->request->get['min_price'];
            isset($this->request->get['max_price']) && trim($this->request->get['max_price']) !== '' && $filter_data['max_price'] = $this->request->get['max_price'];
            isset($this->request->get['min_quantity']) && trim($this->request->get['min_quantity']) !== '' && $filter_data['min_quantity'] = $this->request->get['min_quantity'];
            isset($this->request->get['max_quantity']) && trim($this->request->get['max_quantity']) !== '' && $filter_data['max_quantity'] = $this->request->get['max_quantity'];
            $data['min_price'] = isset($this->request->get['min_price'])?:'';
            $data['max_price'] = isset($this->request->get['max_price'])?:'';
            $data['min_quantity'] = isset($this->request->get['min_quantity'])?:'';
            $data['max_quantity'] = isset($this->request->get['max_quantity'])?:'';


            $symbol_left = $this->currency->getSymbolLeft(session('currency'));
            $data['symbol_left'] = $symbol_left;
            $symbol_Right = $this->currency->getSymbolRight(session('currency'));
            $data['symbol_right'] = $symbol_Right;
            $data['str_filter_price_quantity'] = '';
            if (isset($this->request->get['min_price'])  && !isset($this->request->get['max_price'])) {
                $data['str_filter_price_quantity'] .= 'Unit Price:' . $symbol_left . $this->request->get['min_price'] . $symbol_Right . ' & Above';
            } elseif (!isset($this->request->get['min_price']) && isset($this->request->get['max_price'])) {
                $data['str_filter_price_quantity'] .= 'Unit Price: Under' . $symbol_left . $this->request->get['max_price'] . $symbol_Right;
            } elseif (isset($this->request->get['min_price']) && isset($this->request->get['max_price'])) {
                $data['str_filter_price_quantity'] .= 'Unit Price: ' . $symbol_left . $this->request->get['min_price'] . $symbol_Right . ' to ' . $symbol_left . $this->request->get['max_price'] . $symbol_Right;
            }

            if (isset($this->request->get['min_quantity']) && !isset($this->request->get['max_quantity'])) {
                $data['str_filter_price_quantity'] .= ' Qty Available:' . $this->request->get['min_quantity'] . ' & Above';
            } elseif (!isset($this->request->get['min_quantity']) && isset($this->request->get['max_quantity'])) {
                $data['str_filter_price_quantity'] .= ' Qty Available: Under ' . $this->request->get['max_quantity'];
            } elseif (isset($this->request->get['min_quantity']) && isset($this->request->get['max_quantity'])) {
                $data['str_filter_price_quantity'] .= ' Qty Available: ' . $this->request->get['min_quantity'] . ' to ' . $this->request->get['max_quantity'];
            }
            //13739 当Buyer通过品类去筛选产品时，和Buyer建立关联关系的产品排在前面
            $tmp = $this->model_catalog_product->getTotalProducts($filter_data,1);
            $product_total = $tmp['total'];
            $product_total_str = $tmp['product_total_str'];

            $results = $this->model_catalog_product->getProducts($filter_data,$customFields,$isPartner);
            //$receipt_array = $this->model_catalog_product->getReceptionProduct();
            foreach ($results as $result) {
//                if ($result['image']) {
//                    $image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
//                } else {
//                    $image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
//                }
//
//                if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
//                    $price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), session('currency'));
//                } else {
//                    $price = false;
//                }
//
//                if ((float)$result['special']) {
//                    $special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), session('currency'));
//                } else {
//                    $special = false;
//                }
//
//                if ($this->config->get('config_tax')) {
//                    $tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], session('currency'));
//                } else {
//                    $tax = false;
//                }
//
//                if ($this->config->get('config_review_status')) {
//                    $rating = (int)$result['rating'];
//                } else {
//                    $rating = false;
//                }
//                //add by xxli
//                $discountResult = $this->model_catalog_product->getDiscount($customFields,$result['customer_id']);
//                if($discountResult){
//                    $price = $this->model_catalog_product->getDiscountPrice($result['price'],$discountResult);
//                }
//                if ($country_id) {
//                    if ($this->customer->getGroupId() == 13) {
//                        $price = $this->country->getDisplayPrice($country_id, $price);
//                    }
//                }
//                $freight = $result['freight'];
//                //Price
//                if(in_array($result['customer_id'],PRODUCT_SHOW_ID) !== false){
//                    $max_price =  $result['price'];
//                    $min_price =  $result['price'];
//                }else{
//                    $max_price = (float)$this->model_extension_module_product_show->getProductHighestPrice($result['original_price'],$result['dm_info'],$result['product_id'],$discountResult['discount']??1,$freight) ;
//                    $min_price = (float)$this->model_extension_module_product_show->getProductLowestPrice($result['original_price'],$result['dm_info'],$result['product_id'],$discountResult['discount']??1,$freight);
//                }
//                if ($this->customer->getCountryId() == 107) {
//                    $max_price = (int)$max_price;
//                    $min_price = (int)$min_price;
//                }
//
//                if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
//                    $max_price_show = $this->currency->format($this->tax->calculate($max_price, $result['tax_class_id'], $this->config->get('config_tax')), session('currency'));
//                    $min_price_show = $this->currency->format($this->tax->calculate($min_price, $result['tax_class_id'], $this->config->get('config_tax')), session('currency'));
//                } else {
//                    $max_price_show = false;
//                    $min_price_show = false;
//                }
//
//                $price = $this->currency->format($price, session('currency'));
//
//                $tag_array = $this->model_catalog_product->getProductTagHtmlForThumb($result['product_id']);
//
////                $params = $this->model_catalog_product->getProductParamTogetherByBuilder($result['product_id']);
////                $params = obj2array($params);
//////                $day30Sale = $params[0]['data'];
////                if ($params[1]['data'] == 0 || $params[2]['data'] == 0) {
////                    $return_rate = 0;
////                } else {
////                    $return_rate = sprintf('%.2f', $params[2]['data'] * 100 / $params[1]['data']);
////                }
//                $this->load->model('extension/module/product_show');
//                $return_rate = $this->model_extension_module_product_show->getRmaInfo($result['product_id']);
//                $return_approval_rate = $this->model_catalog_product->returnApprovalRate($result['customer_id']);
//                $materialShow = $this->model_catalog_product->getMaterial($result['product_id']);
//                //end xxli
//                //查看该产品是否被订阅 edit by xxl
//                $productWishList = $this->model_catalog_product->getWishListProduct($result['product_id'],$customFields);
//                $data['products'][] = array(
//                    'screenname' => $result['screenname'],
//                    'material_show'=>$materialShow,
//                    'loginId' => $customFields,
//                    'totalSales' => $result['totalSale'],
//                    'unsee' => $result['unsee'],
//                    'can_sell'=> $result['canSell'],
//                    'all_days_sale'   => $result['totalSale'],
//                    '30Day' => $result['30Day'],
//                    'pageView' => $result['pageView'],
//                    'customer_id' =>$result['customer_id'],
//                    'seller_status' =>$result['seller_status'],
//                    'self_support' => $result['self_support'],
//                    'sku' => $result['sku'],
//                    'mpn' => $result['mpn'],
//                    'price_display' => $result['price_display'],
//                    'quantity_display' => $result['quantity_display'],
//                    'seller_price' => $result['seller_price'],
//                    'can_sell' => $result['canSell'],
//                    'quantity' => $result['c2pQty'],
//                    'product_id'  => $result['product_id'],
//                    'thumb'       => $image,
//                    'name'        => $result['name'],
//                    'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
//                    'price'       => $price,
//                    'special'     => $special,
//                    'tax'         => $tax,
//                    'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
//                    'rating'      => $result['rating'],
//                    'href'        => $this->url->link('product/product', 'path=' . $this->request->get['path'] . '&product_id=' . $result['product_id'] . $url),
//                    'contactUrl'        => $this->url->link('customerpartner/profile', '&id=' . $result['customer_id'].'&itemCode='.$result['sku'].'&contact=1'),
//                    'tag'           => $tag_array,
//                    'productWishList' =>$productWishList,
//                    'receipt'     => isset($receipt_array[$result['product_id']]) ? $receipt_array[$result['product_id']] : null,
//                    'margin_status' => $this->model_extension_module_product_show->getMarginStatus($result['product_id']),
//                    'rebate_status' => $this->model_extension_module_product_show->getRebateStatus($result['product_id']),
//                    'max_price' => $max_price ,
//                    'min_price' => $min_price,
//                    'max_price_show' => $max_price_show,
//                    'min_price_show' => $min_price_show,
//                    'return_rate' => $return_rate,
//                    'return_approval_rate' => $return_approval_rate,
//                );
                $data['products'][] = $result;
                $data['products_arr'][] = array(
                    'product_id'  => $result['product_id'],
                    //'thumb'       => $image,
                    'sku' => $result['sku'],
                    //'name'        => $result['name'],

                );
            }

            $url = '';

            if (isset($this->request->get['filter'])) {
                $url .= '&filter=' . $this->request->get['filter'];
            }

            if (isset($this->request->get['page_limit'])) {
                $url .= '&page_limit=' . $this->request->get['page_limit'];
            }

            $data['sorts'] = array();

            $data['sorts'][] = array(
                'text'  => $this->language->get('text_default'),
                'value' => 'p.sort_order-ASC',
                'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.sort_order&order=ASC' . $url)
            );

            $data['sorts'][] = array(
                'text'  => $this->language->get('text_name_asc'),
                'value' => 'pd.name-ASC',
                'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=pd.name&order=ASC' . $url)
            );

            $data['sorts'][] = array(
                'text'  => $this->language->get('text_name_desc'),
                'value' => 'pd.name-DESC',
                'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=pd.name&order=DESC' . $url)
            );

            $data['sorts'][] = array(
                'text'  => $this->language->get('text_price_asc'),
                'value' => 'p.price-ASC',
                'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.price&order=ASC' . $url)
            );

            $data['sorts'][] = array(
                'text'  => $this->language->get('text_price_desc'),
                'value' => 'p.price-DESC',
                'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.price&order=DESC' . $url)
            );

            //if ($this->config->get('config_review_status')) {
            //	$data['sorts'][] = array(
            //		'text'  => $this->language->get('text_rating_desc'),
            //		'value' => 'rating-DESC',
            //		'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=rating&order=DESC' . $url)
            //	);
            //
            //	$data['sorts'][] = array(
            //		'text'  => $this->language->get('text_rating_asc'),
            //		'value' => 'rating-ASC',
            //		'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=rating&order=ASC' . $url)
            //	);
            //}
            //
            //$data['sorts'][] = array(
            //	'text'  => $this->language->get('text_model_asc'),
            //	'value' => 'p.model-ASC',
            //	'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.model&order=ASC' . $url)
            //);
            //
            //$data['sorts'][] = array(
            //	'text'  => $this->language->get('text_model_desc'),
            //	'value' => 'p.model-DESC',
            //	'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.model&order=DESC' . $url)
            //);

            $url = '';

            if (isset($this->request->get['filter'])) {
                $url .= '&filter=' . $this->request->get['filter'];
            }

            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }

            $data['limits'] = array();

            $limits = array_unique(array($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit'), 30, 60, 90, 120));

            sort($limits);

            foreach($limits as $value) {
                $data['limits'][] = array(
                    'text'  => $value,
                    'value' => $value,
                    'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . $url . '&page_limit=' . $value)
                );
            }

            $url = '';

            if (isset($this->request->get['filter'])) {
                $url .= '&filter=' . $this->request->get['filter'];
            }

            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }

            if (isset($this->request->get['page_limit'])) {
                $url .= '&page_limit=' . $this->request->get['page_limit'];
            }

            $pagination = new Pagination();
            $pagination->total = $product_total;
            $pagination->page = $page;
            $pagination->limit = $limit;
            $pagination->pageList = [15, 30, 60, 90, 120];
            $pagination->url = $this->url->link('product/category', 'path=' . $this->request->get['path'] . $url . '&page={page}');

            $data['pagination'] = $pagination->render();

            $data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

            // http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html
            if ($page == 1) {
                $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id']), 'canonical');
            } else {
                $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . '&page='. $page), 'canonical');
            }

            if ($page > 1) {
                $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . (($page - 2) ? '&page='. ($page - 1) : '')), 'prev');
            }

            if ($limit && ceil($product_total / $limit) > $page) {
                $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . '&page='. ($page + 1)), 'next');
            }

            $data['sort'] = $sort;
            $data['order'] = $order;
            $data['limit'] = $limit;

            $data['continue'] = $this->url->link('common/home');
            $data['login'] = $this->url->link('account/login', '', true);
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');

            $data['products_arr'] = isset($data['products_arr']) ? $data['products_arr'] : null;
            $data['products_json'] = json_encode($data['products_arr']);

            $data['products_to_csv'] = $this->url->link('product/category/all_products_info&path='.$this->request->get['path']);

            $this->session->data[$this->request->get['path']]['product_total_str'] = $product_total_str;





            //用于左侧分类列表
            //@author zhousuyang
            $data['categoriesList'] = $this->load->controller('extension/module/category/getListShort');
            $data['isShowPrice'] = false;
            if ($data['isLogin']) {
                $this->load->model('customerpartner/BuyerToSeller');
                $data['isShowPrice'] = $this->model_customerpartner_BuyerToSeller->checkConnection($this->customer->getId());
            }
            $data['collection'] = $this->url->link('product/category', true);
            //END 用于左侧分类列表

            $this->response->setOutput($this->load->view('product/category1', $data));
        } else {
            $url = '';

            if (isset($this->request->get['path'])) {
                $url .= '&path=' . $this->request->get['path'];
            }

            if (isset($this->request->get['filter'])) {
                $url .= '&filter=' . $this->request->get['filter'];
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

            if (isset($this->request->get['page_limit'])) {
                $url .= '&limit=' . $this->request->get['page_limit'];
            }

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_error'),
                'href' => $this->url->link('product/category', $url)
            );

            $this->document->setTitle($this->language->get('text_error'));

            $data['continue'] = $this->url->link('common/home');
            $this->response->setStatusCode(404);

            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');

            $this->response->setOutput($this->load->view('error/not_found', $data));
        }
    }

    // 搜索页面下载
    public function all_products_info()
    {
        ini_set('memory_limit',-1);
        $customerId = customer()->getId();
        // 判断是否为 buyer 非buyer用户 直接无法获取下载权限
        if (!$customerId || customer()->isPartner()) {
            return $this->response->redirectTo(url('common/home'));
        }
        $type = request('type');
        $productStr = request('product_str');
        if ($type == 0 && null != $productStr) {
            $product_total_str = $productStr;
        } else {
            $sessionPath = $this->session->data['search_' . $this->request->get['path']]['product_total_str'] ?? '';
            if ($sessionPath) {
                $product_total_str = $sessionPath;
            } else {
                echo "<script>window.location.href=document.referrer; </script>";
                exit;
            }
        }
        $this->load->model('catalog/product');
        $this->load->model('tool/csv');
        $data = $this->model_catalog_product->getProductCategoryInfo($product_total_str, $customerId);
        $time = Carbon::now()->setTimezone(CountryHelper::getTimezone(customer()->getCountryId()))->format('YmdHis');
        $filename = 'ProductsInfo_' . $time . ".csv";
        $this->model_tool_csv->getProductCategoryCsv($filename, $data);
    }

    public function index()
    {
        $this->load->language('product/search');
        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $this->load->model('catalog/search');

        trim_strings($this->request->get);
        if ($this->customer->isLogged()) {
            $customer_id = $this->customer->getId();
        } else {
            $customer_id = 0;
        }
        $isPartner  = $this->customer->isPartner();
        if ($this->request->get['click'] ?? ''){
            $this->load->model('catalog/search_click_record');
            $this->model_catalog_search_click_record->saveRecord($this->request->get['click'],$this->request->get['order']??'');
        }

        $filter_data['search'] = isset($this->request->get['search'])?htmlentities(htmlspecialchars_decode($this->request->get['search'])):'';
        $filter_data['category_id'] = isset($this->request->get['category_id'])?intval($this->request->get['category_id']):0;
        $filter_data['sub_category'] = isset($this->request->get['sub_category'])?$this->request->get['sub_category']:'';
        $filter_data['min_price'] = isset($this->request->get['min_price'])?$this->request->get['min_price']:'';
        $filter_data['max_price'] = isset($this->request->get['max_price'])?$this->request->get['max_price']:'';
        $filter_data['min_quantity'] = isset($this->request->get['min_quantity'])?$this->request->get['min_quantity']:'';
        $filter_data['max_quantity'] = isset($this->request->get['max_quantity'])?$this->request->get['max_quantity']:'';
        $filter_data['qty_status'] = isset($this->request->get['qty_status'])?$this->request->get['qty_status']:'';
        $filter_data['download_status'] = isset($this->request->get['download_status'])?intval($this->request->get['download_status']):0;
        $filter_data['wish_status'] = isset($this->request->get['wish_status'])?intval($this->request->get['wish_status']):0;
        $filter_data['purchase_status'] = isset($this->request->get['purchase_status'])?intval($this->request->get['purchase_status']):0;
        $filter_data['relation_status'] = isset($this->request->get['relation_status'])?intval($this->request->get['relation_status']):0;
        $filter_data['img_status'] = isset($this->request->get['img_status'])?intval($this->request->get['img_status']):0;

        //新增复杂交易类型的查询
        $filter_data['rebates'] = $this->request->get('rebates',false);
        $filter_data['margin'] = $this->request->get('margin',false);
        $filter_data['futures'] = $this->request->get('futures',false);
        //新增仓库库存的查询
        $filter_data['whId'] = $this->request->get('whId','') == '' ? [] : explode(',',$this->request->get('whId'));
        $filter_data['sort'] = isset($this->request->get['sort'])?$this->request->get['sort']:'p.sort_order';
        $filter_data['order'] = isset($this->request->get['order'])?$this->request->get['order']:'desc';
        $filter_data['page'] = isset($this->request->get['page'])?intval($this->request->get['page']):1;
        $filter_data['limit'] = isset($this->request->get['limit'])?intval($this->request->get['limit']):20;
        $filter_data['start'] = ($filter_data['page'] - 1) * $filter_data['limit'];
        $filter_data['country'] = session('country');

        $data['is_partner'] = $isPartner;
        $data['download_csv_privilege'] = 0;
        if( $customer_id && false == $isPartner){
            $data['download_csv_privilege'] = 1;
        }

        $data['text_compare'] = sprintf($this->language->get('text_compare'), count(session('compare', []))); 

        $data['compare'] = $this->url->link('product/compare');
        $data['breadcrumbs'][0] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['products'] = array();
        $product_total = 0;
        $categoryIds = null;
        $productIdList = [];
        if (isset($filter_data['search'])
            || $filter_data['category_id']
            || $filter_data['max_price'] != ''
            || $filter_data['min_price'] != ''
            || $filter_data['max_quantity'] != ''
            || $filter_data['min_quantity'] != '')
        {


                try {
                    $tmp = $this->model_catalog_search->searchRelevanceProductId($filter_data, $customer_id);
                } catch (Exception $e) {
                    Logger::app($e);
                    $tmp = null;
                }

                if($tmp){
                    $product_total = $tmp['total'];
                    $product_total_str = implode(',',$tmp['allProductIds']);
                    $results = $this->model_catalog_search->search($filter_data,$customer_id,$isPartner,$tmp);
                    $categoryIds = $tmp['categoryIds'];
                }else{
                    $product_total_str = '';
                    $results = null;
                }

                if ($results) {
                    foreach ($results as $result) {
                        $data['products'][] = $result;
                        //13642 【需求】一览界面批量下载功能优化 明细：增加输入关键字检索页面的下载功能，更新下载表格的英文表述
                        $data['products_arr'][] = array(
                            'product_id'  => $result['product_id'],
                            'sku' => $result['sku'],
                        );
                    }
                }

                if (isset($this->request->get['search']) && $this->config->get('config_customer_search')) {
                    $this->load->model('account/search');
                    $search_data = array(
                        'keyword'       => $filter_data['search'],
                        'category_id'   => $filter_data['category_id'],
                        'sub_category'  => $filter_data['sub_category'],
                        'description'   => $filter_data['description'],
                        'products'      => $product_total,
                        'customer_id'   => $customer_id,
                        'ip'            => isset($this->request->server['REMOTE_ADDR'])?$this->request->server['REMOTE_ADDR']:'',
                    );

                    $this->model_account_search->addSearch($search_data);
                }
        }

        // 3 Level Category Search
        $this->load->model('extension/module/product_category');
        $categories = $this->model_extension_module_product_category->getCategoryById( $filter_data['category_id'],$productIdList,$categoryIds,true);
        $category_all_list = [];
        $category_name = 'All';
        foreach ($categories as $id1 => $category1){
            if($filter_data['category_id'] == $category1['self_id']){
                $category_name = $category1['name'];
            }
            $category_all_list[$category1['self_id']] =  $category1;
            foreach ($category1['children'] ?? [] as $id2 => $category2){
                $category_all_list[$category2['self_id']] =  $category2;
                if($filter_data['category_id'] == $category2['self_id']){
                    $category_name = $category2['name'];
                }
                foreach ($category2['children'] ?? [] as $id3 => $category3){
                    $category_all_list[$category3['self_id']] =  $category3;
                    if($filter_data['category_id'] == $category3['self_id']){
                        $category_name = $category3['name'];
                    }
                }
            }
        }

        if(isset($category_all_list[$filter_data['category_id']])){
            $pid_all = $category_all_list[$filter_data['category_id']];
            $info = explode('_',$pid_all['all_pid']);
            foreach($info as $key => $value){
                $data['breadcrumbs'][] = [
                    'text' => $category_all_list[$value]['name'],
                    'href' => $category_all_list[$value]['href'],
                ];
            }
        }
        $data['categories'] = $categories;
        $data['heading_title'] = 'Search '.$category_name;
        $this->document->setTitle($data['heading_title']);
        $data['category_name'] = $category_name;

        $url = '';
        $mainUrl = '';
        if (isset($this->request->get['search'])) {
            $url .= '&search=' . urlencode(html_entity_decode($this->request->get['search'], ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['tag'])) {
            $url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['category_id'])) {
            $url .= '&category_id=' . $this->request->get['category_id'];
        }
        if (isset($this->request->get['sub_category'])) {
            $url .= '&sub_category=' . $this->request->get['sub_category'];
        }
        if (isset($this->request->get['min_price'])){
            $url .= '&min_price='.$filter_data['min_price'];
        }
        if (isset($this->request->get['max_price'])){
            $url .= '&max_price='.$filter_data['max_price'];
        }
        if (isset($this->request->get['min_quantity'])){
            $url .= '&min_quantity='.$filter_data['min_quantity'];
        }
        if (isset($this->request->get['max_quantity'])){
            $url .= '&max_quantity='.$filter_data['max_quantity'];
        }

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
        if (isset($this->request->get['img_status'])){
            $url .= '&img_status=' . $filter_data['img_status'];
        }
        if (isset($this->request->get['qty_status'])){
            $url .= '&qty_status=' . $filter_data['qty_status'];
        }
        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }
        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        // 后续加的
        $searchCondition = [
            'rebates',
            'margin',
            'futures',
            'whId',
        ];
        array_map(function ($item) use (&$url) {
            if($this->request->get($item,false)){
                $url .= "&{$item}=".$this->request->get($item);
            }
        }, $searchCondition);

        $data['limits'] = array();
        $limits = [20,40,60,100];
        foreach($limits as $value) {
            $data['limits'][] = array(
                'text'  => $value,
                'value' => $value,
                'href'  => $this->url->link('product/category', $url . '&limit=' . $value)
            );
        }

        if (isset($this->request->get['limit'])) {
            $mainUrl .= '&limit=' . $this->request->get['limit'];
            $url .= '&limit=' . $this->request->get['limit'];
        }
        $pagination = new Pagination();
        $pagination->total = $product_total;
        $pagination->page = $filter_data['page'];
        $pagination->limit = $filter_data['limit'];
        $pagination->limit_key = 'limit';
        $pagination->pageList = $limits;
        $pagination->url = $this->url->link('product/category', $url . '&page={page}');

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'),
            ($product_total) ? (($filter_data['page'] - 1) * $filter_data['limit']) + 1 : 0,
            ((($filter_data['page'] - 1) * $filter_data['limit']) > ($product_total - $filter_data['limit'])) ? $product_total : ((($filter_data['page'] - 1) * $filter_data['limit']) + $filter_data['limit']),
            $product_total, ceil($product_total / $filter_data['limit']));

        $data = array_merge($data, $filter_data);
        $data['main_url'] = $this->url->link('product/category', $mainUrl);

        $data['symbol_left'] = $this->currency->getSymbolLeft(session('currency'));
        $data['symbol_right'] = $this->currency->getSymbolRight(session('currency'));
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        //13642【需求】一览界面批量下载功能优化 明细：增加输入关键字检索页面的下载功能，更新下载表格的英文表述
        $data['products_arr'] = isset($data['products_arr']) ? $data['products_arr'] : null;
        $data['products_total'] = $product_total;
        $data['products_json'] = json_encode($data['products_arr']);
        if(isset($product_total_str)){
            $data['products_to_csv'] = $this->url->link('/product/category/all_products_info&path=' . ($this->request->get['search'] ?? ''));
            $this->session->data['search_' . ($this->request->get['search'] ?? '')]['product_total_str'] = $product_total_str;

            $data['products_to_wish'] = $this->url->link('/account/wishlist/batchAdd&path=' . ($this->request->get['search'] ?? ''));

        }
        $data['heading_title'] = $category_name;

        $data['isLogin'] = $customer_id ? true : false;
        $data['login'] = $this->url->link('account/login', '', true);
        /*
         * 仓库筛选条件，仅美国本土上门取货账号支持该筛选条件
         * 美国本土Buyer：招商经理为美国的BD，即招商经理表中区域信息为美国；
        */
        $accountManagerRepo = app(AccountManagerRepository::class);
        $data['isWarehouseProductDistribution'] = false;
        if($this->customer->isCollectionFromDomicile()
            && $accountManagerRepo->isAmericanBd($customer_id)
        ){
            $data['warehouseList'] = app(WarehouseRepository::class)::getActiveAmericanWarehouse();
            $data['isWarehouseProductDistribution'] = true;
        }
        $this->response->setOutput($this->load->view('product/category', $data));

    }
}
