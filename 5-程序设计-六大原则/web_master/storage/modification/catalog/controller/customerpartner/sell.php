<?php
class ControllerCustomerpartnerSell extends Controller {

    private $error = array();
    private $data = array();

    public function index() {

        $this->data = array_merge($this->data, $this->load->language('customerpartner/sell'));

        $this->document->setTitle($this->language->get('heading_title'));

        $this->document->addStyle('catalog/view/theme/default/stylesheet/MP/sell.css?v=' . APP_VERSION);

        $this->load->model('tool/image');

        $this->load->model('customerpartner/master');

        $this->data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));
        $this->data['compare'] = $this->url->link('product/compare');
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'c2p.product_id';
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

        if (isset($this->request->get['limit'])) {
            $limit = (int)$this->request->get['limit'];
        } else {
            $limit = 20;
        }

        $buttontitle = $this->config->get('marketplace_sellbuttontitle');
        $sellerHeader = $this->config->get('marketplace_sellheader');

        $this->data['sell_title'] = $buttontitle[$this->config->get('config_language_id')];
        $this->data['sell_header'] = $sellerHeader[$this->config->get('config_language_id')];
        //$this->data['showpartners'] = $this->config->get('marketplace_showpartners');//被周苏阳于20191031注释。
        //$this->data['showproducts'] = $this->config->get('marketplace_showproducts');//被周苏阳于20191031注释。

        /**
         * Marketplace Sell page tab
         */
        //以下15行代码，被周苏阳于20191031注释。
        //$this->data['tabs'] = array();
        //$marketplace_tab = $this->config->get('marketplace_tab');
        //if(isset($marketplace_tab['heading']) AND $marketplace_tab['heading']){
        //	ksort($marketplace_tab['heading']);
        //	ksort($marketplace_tab['description']);
        //	foreach ($marketplace_tab['heading'] as $key => $value) {
        //		$text = $marketplace_tab['description'][$key][$this->config->get('config_language_id')];
        //	    $text = trim(html_entity_decode($text));
        //		$this->data['tabs'][] = array(
        //			'id' => $key,
        //			'hrefValue' => $value[$this->config->get('config_language_id')],
        //			'description' => $text,
        //		);
        //	}
        //}



        /**
         * Marketplace Sell page tab
         */

        /**
         * Marketplace shows sellers
         * [$partners get long term sellers ]
         * @var [type]
         */
        $partnerFilter = [
            'page_size' => get_value_or_default($this->request->get, 'page_size', (int)$this->config->get('marketplace_seller_list_limit') ?: 100),
            'page' => get_value_or_default($this->request->get, 'partner_page', 1) == '{page}' ? 1 : get_value_or_default($this->request->get, 'partner_page', 1),
            'country' => session('country')
        ];
        $partnersResults = $this->model_customerpartner_master->getOldPartnerByCountryId($partnerFilter);

        $this->data['partners'] = array();

        foreach ($partnersResults['data'] as $key => $result) {
            if ($result['avatar']) {
                $image = $this->model_tool_image->resize($result['avatar'], 254, 254);
            } else if($result['avatar'] == 'removed') {
                $image = '';
            } else if($this->config->get('marketplace_default_image_name')) {
                $image = $this->model_tool_image->resize($this->config->get('marketplace_default_image_name'), 254, 254);
            } else {
                $image = $this->model_tool_image->resize('no_image.png', 254, 254);
            }

            if (empty($image)) {
                $image = $this->model_tool_image->resize('no_image.png', 254, 254);
            }

            /**
             * 精细化管理 店铺产品总数
             */
            $buyer_id = $this->customer->isLogged() ? $this->customer->isPartner() ? 0 : $this->customer->getId() : 0;
            $this->data['partners'][] = array(
                'customer_id' 		=> $result['customer_id'],
                'name' 		  		=> (isset($result['firstname']) ? $result['firstname'] : '') . ' ' . (isset($result['lastname']) ? $result['lastname'] : ''),
                'screenname'		=> $result['screenname'],
                'companyname' 		=> $result['companyname'],
                'backgroundcolor' 		=> $result['backgroundcolor'],
                'country'  	  		=> $result['country'],
                'sellerHref'  		=> $this->url->link('customerpartner/profile', 'id=' . $result['customer_id'],true),
                'thumb'       		=> $image,
                'total_products'    => $this->model_customerpartner_master->getPartnerCollectionCount($result['customer_id'],$buyer_id),
            );

        }

        //Partners 分页
        $pagination = new Pagination();
        $pagination->total = $partnersResults['total'];
        $pagination->page = $partnerFilter['page'];
        $pagination->limit = $partnerFilter['page_size'];
        $pagination->page_key = 'partner_page';
        $pagination->limit_key = 'page_size';
        $pagination->url = $this->url->link('customerpartner/sell', '&partner_page={page}&page_size=' . $partnerFilter['page_size'], true);

        $this->data['partner_pagination'] = $pagination->render();
        $this->data['partner_results'] = sprintf(
            $this->language->get('text_pagination'),
            ($partnersResults['total']) ? (($partnerFilter['page'] - 1) * $partnerFilter['page_size']) + 1 : 0,
            ((($partnerFilter['page'] - 1) * $partnerFilter['page_size']) > ($partnersResults['total'] - $partnerFilter['page_size'])) ? $partnersResults['total'] : ((($partnerFilter['page'] - 1) * $partnerFilter['page_size']) + $partnerFilter['page_size']),
            $partnersResults['total'],
            ceil($partnersResults['total'] / $partnerFilter['page_size'])
        );
        /**
         * Marketplace shows seller
         */

        /**
         * Marketplace shows Seller's latest products
         */
        // add by LiLei 判断用户是否登录
        $customFields =  $this->customer->getId();
        if ($customFields) {
            $this -> data['isLogin'] = true;
        } else {
            $this -> data['isLogin'] = false;
        }

        $this->data['login'] = $this->url->link('account/login', '', true);
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['column_right'] = $this->load->controller('common/column_right');
		$this->data['content_top'] = $this->load->controller('common/content_top');
		$this->data['content_bottom'] = $this->load->controller('common/content_bottom');
        $this->data['footer'] = $this->load->controller('common/footer');
        $this->data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('customerpartner/sell', $this->data));
    }

    public function wkmpregistation(){

        $this->load->model('customerpartner/master');

        $json = array();

        if(isset($this->request->post['shop'])){
            $data = urldecode(html_entity_decode($this->request->post['shop'], ENT_QUOTES, 'UTF-8'));
            if($this->model_customerpartner_master->getShopData($data)){
                $json['error'] = true;
            }else{
                $json['success'] = true;
            }
        }

        $this->response->setOutput(json_encode($json));
    }
}
?>
