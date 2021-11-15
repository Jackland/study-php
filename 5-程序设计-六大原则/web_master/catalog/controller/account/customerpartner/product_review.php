<?php
class ControllerAccountCustomerpartnerProductReview extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('account/customerpartner/product_review');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customerpartner/product_review');

        $this->getList();
    }

    public function add() {
        $this->load->language('account/customerpartner/product_review');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('account/customerpartner/product_review');

        if ((request()->isMethod('POST')) && $this->validateForm()) {
            $this->model_customerpartner_product_review->addReview($this->request->post);

            session()->set('success', $this->language->get('text_success'));

            $url = '';

            if (isset($this->request->get['filter_product'])) {
                $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_author'])) {
                $url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_status'])) {
                $url .= '&filter_status=' . $this->request->get['filter_status'];
            }

            //14052【需求优化】产品评论功能隐藏Buyer昵称和编码
            if (isset($this->request->get['filter_start_date_added'])) {
                $url .= '&filter_start_date_added=' . $this->request->get['filter_start_date_added'];
            }

            if (isset($this->request->get['filter_end_date_added'])) {
                $url .= '&filter_end_date_added=' . $this->request->get['filter_end_date_added'];
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

            $this->response->redirect($this->url->link('account/customerpartner/product_review', $url, true));
        }

        $this->getForm();
    }

    public function edit() {
        $this->load->language('account/customerpartner/product_review');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customerpartner/product_review');

        if ((request()->isMethod('POST')) && $this->validateForm()) {
            $this->model_customerpartner_product_review->editReview($this->request->get['review_id'], $this->request->post);

            session()->set('success', $this->language->get('text_success'));

            $url = '';

            if (isset($this->request->get['filter_product'])) {
                $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_author'])) {
                $url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_status'])) {
                $url .= '&filter_status=' . $this->request->get['filter_status'];
            }

            //14052【需求优化】产品评论功能隐藏Buyer昵称和编码
            if (isset($this->request->get['filter_start_date_added'])) {
                $url .= '&filter_start_date_added=' . $this->request->get['filter_start_date_added'];
            }

            if (isset($this->request->get['filter_end_date_added'])) {
                $url .= '&filter_end_date_added=' . $this->request->get['filter_end_date_added'];
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

            $this->response->redirect($this->url->link('account/customerpartner/product_review', $url, true));
        }

        $this->getForm();
    }

    public function delete() {
        $this->load->language('account/customerpartner/product_review');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('account/customerpartner/product_review');

        if (isset($this->request->post['selected']) && $this->validateDelete()) {
            foreach ($this->request->post['selected'] as $review_id) {
                $this->model_customerpartner_product_review->deleteReview($review_id);
            }

            session()->set('success', $this->language->get('text_success'));

            $url = '';

            if (isset($this->request->get['filter_product'])) {
                $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_author'])) {
                $url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
            }

            if (isset($this->request->get['filter_status'])) {
                $url .= '&filter_status=' . $this->request->get['filter_status'];
            }

            //14052【需求优化】产品评论功能隐藏Buyer昵称和编码
            if (isset($this->request->get['filter_start_date_added'])) {
                $url .= '&filter_start_date_added=' . $this->request->get['filter_start_date_added'];
            }

            if (isset($this->request->get['filter_end_date_added'])) {
                $url .= '&filter_end_date_added=' . $this->request->get['filter_end_date_added'];
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

            $this->response->redirect($this->url->link('account/customerpartner/product_review',  $url, true));
        }

        $this->getList();
    }

    protected function getList() {

        $customer_id = $this->customer->getId();
        if (isset($this->request->get['filter_product'])) {
            $filter_product = $this->request->get['filter_product'];
        } else {
            $filter_product = '';
        }

        if (isset($this->request->get['filter_author'])) {
            $filter_author = $this->request->get['filter_author'];
        } else {
            $filter_author = '';
        }

        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = '';
        }
        //14052【需求优化】产品评论功能隐藏Buyer昵称和编码
        if (isset($this->request->get['filter_start_date_added'])) {
            $filter_start_date_added = $this->request->get['filter_start_date_added'];
        } else {
            $filter_start_date_added = '';
        }

        if (isset($this->request->get['filter_end_date_added'])) {
            $filter_end_date_added = $this->request->get['filter_end_date_added'];
        } else {
            $filter_end_date_added = '';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'DESC';
        }

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'r.date_added';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $url = '';

        if (isset($this->request->get['filter_product'])) {
            $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_author'])) {
            $url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }
        //14052【需求优化】产品评论功能隐藏Buyer昵称和编码
        if (isset($this->request->get['filter_start_date_added'])) {
            $url .= '&filter_start_date_added=' . $this->request->get['filter_start_date_added'];
        }

        if (isset($this->request->get['filter_end_date_added'])) {
            $url .= '&filter_end_date_added=' . $this->request->get['filter_end_date_added'];
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

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_seller_center'),
            'href' => $this->url->link('customerpartner/seller_center/index', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('account/customerpartner/product_review', $url, true)
        );

        $data['add'] = $this->url->link('account/customerpartner/product_review/add',  true);
        $data['delete'] = $this->url->link('account/customerpartner/product_review/delete',  true);

        $data['reviews'] = array();

        $filter_data = array(
            'filter_product'    => $filter_product,
            'filter_author'     => $filter_author,
            'filter_status'     => $filter_status,
            'filter_start_date_added' => $filter_start_date_added,
            'filter_end_date_added' => $filter_end_date_added,
            'sort'              => $sort,
            'order'             => $order,
            'start'             => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit'             => $this->config->get('config_limit_admin'),
            'customer_id'      => $customer_id
        );

        $review_total = $this->model_customerpartner_product_review->getTotalReviews($filter_data);

        $results = $this->model_customerpartner_product_review->getReviews($filter_data);

        foreach ($results as $result) {
            $reviewFiles = $this->model_customerpartner_product_review->getReviewFiles( $result['review_id']);
            $data['reviews'][] = array(
                'mpn' => $result['mpn'],
                'sku' => $result['sku'],
                'seller_rating' => $result['seller_rating'],
                'review_id'  => $result['review_id'],
                'name'       => $result['name'],
                'author'     => $result['author'],
                'rating'     => $result['rating'],
                'status'     => ($result['status']) ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
                'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
                'edit'       => $this->url->link('account/customerpartner/product_review/edit',  '&review_id=' . $result['review_id'] . $url, true),
                'images' =>$reviewFiles
            );
        }


        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');

            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }

        if (isset($this->request->post['selected'])) {
            $data['selected'] = (array)$this->request->post['selected'];
        } else {
            $data['selected'] = array();
        }

        $url = '';

        if (isset($this->request->get['filter_product'])) {
            $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_author'])) {
            $url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        //14052【需求优化】产品评论功能隐藏Buyer昵称和编码
        if (isset($this->request->get['filter_start_date_added'])) {
            $url .= '&filter_start_date_added=' . $this->request->get['filter_start_date_added'];
        }

        if (isset($this->request->get['filter_end_date_added'])) {
            $url .= '&filter_end_date_added=' . $this->request->get['filter_end_date_added'];
        }

        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['sort_product'] = $this->url->link('account/customerpartner/product_review',  '&sort=pd.name' . $url, true);
        $data['sort_author'] = $this->url->link('account/customerpartner/product_review',  '&sort=r.author' . $url, true);
        $data['sort_rating'] = $this->url->link('account/customerpartner/product_review',  '&sort=r.rating' . $url, true);
        $data['sort_status'] = $this->url->link('account/customerpartner/product_review',  '&sort=r.status' . $url, true);
        $data['sort_date_added'] = $this->url->link('account/customerpartner/product_review',  '&sort=r.date_added' . $url, true);

        $url = '';

        if (isset($this->request->get['filter_product'])) {
            $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_author'])) {
            $url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        //14052【需求优化】产品评论功能隐藏Buyer昵称和编码
        if (isset($this->request->get['filter_start_date_added'])) {
            $url .= '&filter_start_date_added=' . $this->request->get['filter_start_date_added'];
        }

        if (isset($this->request->get['filter_end_date_added'])) {
            $url .= '&filter_end_date_added=' . $this->request->get['filter_end_date_added'];
        }

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        $pagination = new Pagination();
        $pagination->total = $review_total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link('account/customerpartner/product_review',  $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($review_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($review_total - $this->config->get('config_limit_admin'))) ? $review_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $review_total, ceil($review_total / $this->config->get('config_limit_admin')));

        $data['filter_product'] = $filter_product;
        $data['filter_author'] = $filter_author;
        $data['filter_status'] = $filter_status;
        $data['filter_start_date_added'] = $filter_start_date_added;
        $data['filter_end_date_added'] = $filter_end_date_added;

        $data['sort'] = $sort;
        $data['order'] = $order;

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

        $this->response->setOutput($this->load->view('account/customerpartner/product_review_list', $data));
    }

    protected function getForm() {
        $data['text_form'] = !isset($this->request->get['review_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['product'])) {
            $data['error_product'] = $this->error['product'];
        } else {
            $data['error_product'] = '';
        }

        if (isset($this->error['author'])) {
            $data['error_author'] = $this->error['author'];
        } else {
            $data['error_author'] = '';
        }

        if (isset($this->error['text'])) {
            $data['error_text'] = $this->error['text'];
        } else {
            $data['error_text'] = '';
        }

        if (isset($this->error['rating'])) {
            $data['error_rating'] = $this->error['rating'];
        } else {
            $data['error_rating'] = '';
        }

        if (isset($this->error['seller_review_number'])) {
            $data['error_seller_review_number'] = $this->error['seller_review_number'];
        } else {
            $data['error_seller_review_number'] = '';
        }

        $url = '';

        if (isset($this->request->get['filter_product'])) {
            $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_author'])) {
            $url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        //14052【需求优化】产品评论功能隐藏Buyer昵称和编码
        if (isset($this->request->get['filter_start_date_added'])) {
            $url .= '&filter_start_date_added=' . $this->request->get['filter_start_date_added'];
        }

        if (isset($this->request->get['filter_end_date_added'])) {
            $url .= '&filter_end_date_added=' . $this->request->get['filter_end_date_added'];
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

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_seller_center'),
            'href' => $this->url->link('customerpartner/seller_center/index', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('account/customerpartner/product_review', $url, true)
        );

        if (!isset($this->request->get['review_id'])) {
            $data['action'] = $this->url->link('account/customerpartner/product_review/add',  $url, true);
        } else {
            $data['action'] = $this->url->link('account/customerpartner/product_review/edit', '&review_id=' . $this->request->get['review_id'] . $url, true);
        }

        $data['cancel'] = $this->url->link('account/customerpartner/product_review',  $url, true);

//        if (isset($this->request->get['review_id']) && (!request()->isMethod('POST'))) {
        if (isset($this->request->get['review_id'])) {
            $review_info = $this->model_customerpartner_product_review->getReview($this->request->get['review_id']);
        }
        if (isset($this->request->get['review_id'])) {
        $review_files = $this->model_customerpartner_product_review->getReviewFiles($this->request->get['review_id']);
        }
        $this->load->model('catalog/product');
        //add by xxli
        if (isset($this->request->post['seller_review'])) {
            $data['seller_review'] = $this->request->post['seller_review'];
        } elseif (!empty($review_info)) {
            $data['seller_review'] = $review_info['seller_review'];
        } else {
            $data['seller_review'] = '';
        }

        if (isset($this->request->post['seller_review_number'])) {
            $data['seller_review_number'] = $this->request->post['seller_review_number'];
        } elseif (!empty($review_info)) {
            $data['seller_review_number'] = $review_info['seller_review_number'];
        } else {
            $data['seller_review_number'] = '';
        }
        if (isset($this->request->post['seller_rating'])) {
            $data['seller_rating'] = $this->request->post['seller_rating'];
        } elseif (!empty($review_info)) {
            $data['seller_rating'] = $review_info['seller_rating'];
        } else {
            $data['seller_rating'] = '';
        }
        $data['files'] = array();
        if($review_files){
            foreach ($review_files as $result) {
                $data['files'][] = "storage/reviewFiles/".$result['path'];
            }
        }else{
            $data['files'] = '';
        }

        //end
        if (isset($this->request->post['product_id'])) {
            $data['product_id'] = $this->request->post['product_id'];
        } elseif (!empty($review_info)) {
            $data['product_id'] = $review_info['product_id'];
        } else {
            $data['product_id'] = '';
        }

        if (isset($this->request->post['product'])) {
            $data['product'] = $this->request->post['product'];
        } elseif (!empty($review_info)) {
            $data['product'] = $review_info['product'];
        } else {
            $data['product'] = '';
        }

        if (isset($this->request->post['author'])) {
            $data['author'] = $this->request->post['author'];
        } elseif (!empty($review_info)) {
            $data['author'] = $review_info['author'];
        } else {
            $data['author'] = '';
        }

        if (isset($this->request->post['text'])) {
            $data['text'] = $this->request->post['text'];
        } elseif (!empty($review_info)) {
            $data['text'] = $review_info['text'];
        } else {
            $data['text'] = '';
        }

        if (isset($this->request->post['rating'])) {
            $data['rating'] = $this->request->post['rating'];
        } elseif (!empty($review_info)) {
            $data['rating'] = $review_info['rating'];
        } else {
            $data['rating'] = '';
        }

        if (isset($this->request->post['date_added'])) {
            $data['date_added'] = $this->request->post['date_added'];
        } elseif (!empty($review_info)) {
            $data['date_added'] = ($review_info['date_added'] != '0000-00-00 00:00' ? $review_info['date_added'] : '');
        } else {
            $data['date_added'] = '';
        }

        if (isset($this->request->post['status'])) {
            $data['status'] = $this->request->post['status'];
        } elseif (!empty($review_info)) {
            $data['status'] = $review_info['status'];
        } else {
            $data['status'] = '';
        }

        if (isset($this->request->post['sku'])) {
            $data['sku'] = $this->request->post['sku'];
        } elseif (!empty($review_info)) {
            $data['sku'] = $review_info['sku'];
        } else {
            $data['sku'] = '';
        }

        if (isset($this->request->post['mpn'])) {
            $data['mpn'] = $this->request->post['mpn'];
        } elseif (!empty($review_info)) {
            $data['mpn'] = $review_info['mpn'];
        } else {
            $data['mpn'] = '';
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

        $this->response->setOutput($this->load->view('account/customerpartner/product_review_form', $data));
    }

    protected function validateForm() {
//        if (!$this->user->hasPermission('modify', 'customerpartner/product_review')) {
//            $this->error['warning'] = $this->language->get('error_permission');
//        }
        if($this->request->post['seller_review_number']>=3){
            $this->error['seller_review_number'] = 'Review can only be modified 3 times.';
        }
        if (!$this->request->post['product_id']) {
            $this->error['product'] = $this->language->get('error_product');
        }

        if ((utf8_strlen($this->request->post['author']) < 3) || (utf8_strlen($this->request->post['author']) > 64)) {
            $this->error['author'] = $this->language->get('error_author');
        }

        if (utf8_strlen($this->request->post['text']) < 1) {
            $this->error['text'] = $this->language->get('error_text');
        }

//        if (!isset($this->request->post['rating']) || $this->request->post['rating'] < 0 || $this->request->post['rating'] > 5) {
//            $this->error['rating'] = $this->language->get('error_rating');
//        }

        return !$this->error;
    }

    protected function validateDelete() {
//        if (!$this->user->hasPermission('modify', 'customerpartner/product_review')) {
//            $this->error['warning'] = $this->language->get('error_permission');
//        }

        return !$this->error;
    }
}