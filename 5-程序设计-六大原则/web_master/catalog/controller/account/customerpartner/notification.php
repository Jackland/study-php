<?php

/**
 * @property ModelAccountCustomer $model_account_customer
 * @property ModelAccountCustomerGroup $model_account_customer_group
 * @property ModelAccountNotification $model_account_notification
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelCustomerpartnerRmaManagement $model_customerpartner_rma_management
 * @property ModelMpLocalisationOrderStatus $model_mp_localisation_order_status
 */
class ControllerAccountCustomerpartnerNotification extends Controller
{
    const RMA_NOTIFICATION_TOTAL = 1;
    const RMA_NOTIFICATION_UNREAD_TOTAL = 2;
    const BID_NOTIFICATION_TOTAL = 1;
    const BID_NOTIFICATION_UNREAD_TOTAL = 2;

    public function index()
    {
        $data = array();

        $data = array_merge($data, $this->load->language('account/customerpartner/notification'));

        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/notification', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('account/customerpartner');
        $this->load->model('account/notification');
        $this->load->model('mp_localisation/order_status');

        $data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

        if (!$data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode']))
            $this->response->redirect($this->url->link('account/account', '', true));

        $readAllUrl = '';
        isset($this->request->get['page']) && is_numeric($this->request->get['page']) && $readAllUrl .= '&page=' . $this->request->get['page'];
        isset($this->request->get['page_product']) && is_numeric($this->request->get['page_product']) && $readAllUrl .= '&page_product=' . $this->request->get['page_product'];
        isset($this->request->get['page_seller']) && is_numeric($this->request->get['page_seller']) && $readAllUrl .= '&page_seller=' . $this->request->get['page_seller'];
//        isset($this->request->get['tab']) && !empty($this->request->get['tab']) && $readAllUrl .= '&tab=' . $this->request->get['tab'];

        $this->document->setTitle($data['text_notifications']);

        if ($this->config->get('module_wk_seller_group_status')) {
            $this->load->model('account/customer_group');

            $isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());

            if ($isMember) {
                $allowedAccountMenu = $this->model_account_customer_group->getprofileOption($isMember['gid']);

                if ($allowedAccountMenu['value']) {
                    $accountMenu = explode(',', $allowedAccountMenu['value']);

                    if ($accountMenu) {
                        foreach ($accountMenu as $key => $value) {
                            $values = explode(':', $value);
                            $data['allowed'][$values[0]] = $values[1];
                        }
                    }
                }
            }
        }

        //导航
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $data['text_home'],
            'href' => $this->url->link('common/home', '', true),
            'separator' => false
        );
        $data['breadcrumbs'][] = array(
            'text' => $data['text_account'],
            'href' => 'javascript:void(0);',
            'separator' => false
        );
        $data['breadcrumbs'][] = array(
            'text' => $data['text_notifications'],
            'href' => $this->url->link('account/customerpartner/notification', '', true),
            'separator' => false
        );

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

        $data['isMember'] = true;

        if ($this->config->get('module_wk_seller_group_status')) {
            $data['module_wk_seller_group_status'] = true;

            $this->load->model('account/customer_group');

            $isMember = $this->model_account_customer_group->getSellerMembershipGroup($this->customer->getId());

            if ($isMember) {
                $allowedAccountMenu = $this->model_account_customer_group->getaccountMenu($isMember['gid']);

                if ($allowedAccountMenu['value']) {
                    $accountMenu = explode(',', $allowedAccountMenu['value']);

                    if ($accountMenu && !in_array('notification:notification', $accountMenu)) {
                        $data['isMember'] = false;
                    }
                }
            } else {
                $data['isMember'] = false;
            }
        } else {
            if (!is_array($this->config->get('marketplace_allowed_account_menu')) || !in_array('notification', $this->config->get('marketplace_allowed_account_menu'))) {
                $this->response->redirect($this->url->link('account/account', '', true));
            }
        }

        //获取左侧需要显示的状态
        $data['notification_filter'] = $this->config->get('marketplace_notification_filter');
        $data['order_statuses'] = $this->model_mp_localisation_order_status->getOrderStatuses();
        foreach ($data['order_statuses'] as $key => $value) {
            if (in_array($value['order_status_id'], $data['notification_filter'])) {
                $data['order_statuses'][$key]['total'] = $this->model_account_notification->getTotalSellerActivity(array($value['order_status_id']));
            } else {
                $data['order_statuses'][$key]['total'] = 0;
            }
        }
        /**
         * @todo 隐藏 return
         */
//        $data['return_total'] = $this->model_account_notification->getTotalSellerActivity(array('return'));

        $data['selected'] = array();

        if (isset($this->request->get['options']) && $this->request->get['options']) {
            $data['selected'] = explode(',', $this->request->get['options']);

            foreach ($data['selected'] as $key => $value) {
                $data['selected'][$key] = $this->db->escape($value);
            }
        }

        $data['all_notifications'] = $this->model_account_notification->getSellerActivityCount();

        $seller_notifications_total = $this->model_account_notification->getTotalSellerActivity($data['selected']);

        //Order tab
        $data['seller_notifications'] = array();
        $seller_notifications = $this->model_account_notification->getSellerActivity($data['selected']);
        $customerNicknameTemp = array();
        $orderUnreadIDArr = [];
        $this->load->model('account/customer');
        if ($seller_notifications) {
            foreach ($seller_notifications as $key => $seller_notification) {

                $seller_notification['is_read'] == 0 && $orderUnreadIDArr[] = $seller_notification['customer_activity_id'] . '_' . $seller_notification['is_mp'];
                $date_diff = (array)(new DateTime($seller_notification['date_added']))->diff(new DateTime());

                if (isset($date_diff['y']) && $date_diff['y']) {
                    $seller_notification['date_added'] = $date_diff['y'] . ' ' . $this->language->get('text_years');
                } elseif (isset($date_diff['m']) && $date_diff['m']) {
                    $seller_notification['date_added'] = $date_diff['m'] . ' ' . $this->language->get('text_months');
                } elseif (isset($date_diff['d']) && $date_diff['d']) {
                    $seller_notification['date_added'] = $date_diff['d'] . ' ' . $this->language->get('text_days');
                } elseif (isset($date_diff['h']) && $date_diff['h']) {
                    $seller_notification['date_added'] = $date_diff['h'] . ' ' . $this->language->get('text_hours');
                } elseif (isset($date_diff['i']) && $date_diff['i']) {
                    $seller_notification['date_added'] = $date_diff['i'] . ' ' . $this->language->get('text_minutes');
                } else {
                    $seller_notification['date_added'] = $date_diff['s'] . ' ' . $this->language->get('text_seconds');
                }

                $seller_notification['data'] = json_decode($seller_notification['data'], 1);
                if (isset($seller_notification['data']['customer_id']) && isset($seller_notification['data']['name'])) {
                    if (isset($customerNicknameTemp[$seller_notification['data']['customer_id']])) {
                        $seller_notification['data']['name'] = $customerNicknameTemp[$seller_notification['data']['customer_id']];
                    } else {
                        $nickname = $this->model_account_customer->getCustomerNicknameAndNumber($seller_notification['data']['customer_id']);
                        $seller_notification['data']['name'] = $nickname;
                        $customerNicknameTemp[$seller_notification['data']['customer_id']] = $nickname;
                    }
                }

                if ($seller_notification['key'] == 'order_status') {
                    $data['seller_notifications'][] = sprintf(
                            $this->language->get('text_order_add'),
                            $seller_notification['data']['order_id'] .
                            "&ca_id=" . $seller_notification['customer_activity_id'] . "&is_mp=" . $seller_notification['is_mp'],
                            $seller_notification['data']['order_id'],
                            $seller_notification['data']['name'],
                            $seller_notification['date_added']
                        ) . ($seller_notification['is_read'] ? '' : '<span class="badge-dot"></span>');
                } elseif ($seller_notification['key'] == 'return_account') {
                    $order_id = $this->model_account_notification->getReturnOrderId($seller_notification['data']['return_id']);

                    $data['seller_notifications'][] = sprintf(
                            $this->language->get('text_order_return'),
                            $seller_notification['data']['name'],
                            $order_id['order_id'] .
                            "&ca_id=" . $seller_notification['customer_activity_id'] . "&is_mp=" . $seller_notification['is_mp'],
                            $seller_notification['data']['return_id'],
                            $order_id['product'], $seller_notification['date_added']
                        ) . ($seller_notification['is_read'] ? '' : '<span class="badge-dot"></span>');
                } elseif ($seller_notification['key'] == 'order_status') {
                    $status = $this->model_mp_localisation_order_status->getOrderStatus($seller_notification['data']['status']);
                    if ($status) {
                        if (empty($data['selected']) || in_array('all', $data['selected'])) {
                            $data['seller_notifications'][] = sprintf(
                                    $this->language->get('text_order_status'),
                                    $seller_notification['data']['order_id'] .
                                    "&ca_id=" . $seller_notification['customer_activity_id'] . "&is_mp=" . $seller_notification['is_mp'],
                                    $seller_notification['data']['order_id'],
                                    $status['name'],
                                    $seller_notification['date_added']
                                ) . ($seller_notification['is_read'] ? '' : '<span class="badge-dot"></span>');
                        } else {
                            if (is_array($data['notification_filter']) && $data['notification_filter']) {
                                foreach ($data['notification_filter'] as $k => $value) {
                                    if (in_array($value, $data['selected']) && $seller_notification['data']['status'] == $value) {
                                        $data['seller_notifications'][] = sprintf(
                                                $this->language->get('text_order_status'),
                                                $seller_notification['data']['order_id'] .
                                                "&ca_id=" . $seller_notification['customer_activity_id'] . "&is_mp=" . $seller_notification['is_mp'],
                                                $seller_notification['data']['order_id'],
                                                $status['name'],
                                                $seller_notification['date_added']
                                            ) . ($seller_notification['is_read'] ? '' : '<span class="badge-dot"></span>');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $data['order_unread_url'] = $this->url->link('account/customerpartner/notification/read' . $readAllUrl, '', true) . '&ids=' . implode(',', $orderUnreadIDArr);

        //Product tab
        $seller_product_reviews = $this->model_account_notification->getSellerProductActivity();
        $seller_product_reviews_total = $this->model_account_notification->getSellerProductActivityTotal();
        $productUnreadIDArr = [];
        $authorNicknameTemp = array();
        if ($seller_product_reviews) {
            foreach ($seller_product_reviews as $key => $seller_product_review) {
                $seller_product_review['is_read'] == 0 && $productUnreadIDArr[] = $seller_product_review['customer_activity_id'] . '_1';
                $date_diff = (array)(new DateTime($seller_product_review['date_added']))->diff(new DateTime());

                if (isset($date_diff['y']) && $date_diff['y']) {
                    $seller_product_review['date_added'] = $date_diff['y'] . ' ' . $this->language->get('text_years');
                } elseif (isset($date_diff['m']) && $date_diff['m']) {
                    $seller_product_review['date_added'] = $date_diff['m'] . ' ' . $this->language->get('text_months');
                } elseif (isset($date_diff['d']) && $date_diff['d']) {
                    $seller_product_review['date_added'] = $date_diff['d'] . ' ' . $this->language->get('text_days');
                } elseif (isset($date_diff['h']) && $date_diff['h']) {
                    $seller_product_review['date_added'] = $date_diff['h'] . ' ' . $this->language->get('text_hours');
                } elseif (isset($date_diff['i']) && $date_diff['i']) {
                    $seller_product_review['date_added'] = $date_diff['i'] . ' ' . $this->language->get('text_minutes');
                } else {
                    $seller_product_review['date_added'] = $date_diff['s'] . ' ' . $this->language->get('text_seconds');
                }

                $seller_product_review['data'] = json_decode($seller_product_review['data'], 1);
                if (isset($seller_product_review['data']['review_id']) && isset($seller_product_review['data']['author'])) {
                    if (isset($authorNicknameTemp[$seller_product_review['data']['review_id']])) {
                        $seller_product_review['data']['author'] = $authorNicknameTemp[$seller_product_review['data']['review_id']];
                    } else {
                        $nickname = $this->model_account_notification->getReviewerNickname($seller_product_review['data']['review_id']);
                        $authorNicknameTemp[$seller_product_review['data']['review_id']] = $nickname;
                        $seller_product_review['data']['author'] = $nickname;
                    }
                }
                if ($seller_product_review['key'] == 'product_review') {
                    $data['seller_product_reviews'][] = sprintf(
                            $this->language->get('text_product_review'),
                            $seller_product_review['id'],
                            $seller_product_review['data']['author'],
                            $seller_product_review['data']['product_id'] .
                            "&ca_id=" . $seller_product_review['customer_activity_id'],
                            $seller_product_review['data']['product_name'],
                            $seller_product_review['date_added']
                        ) . ($seller_product_review['is_read'] ? '' : '<span class="badge-dot"></span>');
                } elseif ($seller_product_review['key'] == 'product_stock') {
                    $data['seller_product_reviews'][] = sprintf(
                            $this->language->get('text_product_stock'),
                            $seller_product_review['data']['product_id'] .
                            "&ca_id=" . $seller_product_review['customer_activity_id'],
                            $seller_product_review['data']['product_name'],
                            truncate($seller_product_review['data']['product_name'], 60),
                            $seller_product_review['date_added']
                        ) . ($seller_product_review['is_read'] ? '' : '<span class="badge-dot"></span>');
                } elseif ($seller_product_review['key'] == 'product_approve') {
                    $data['seller_product_reviews'][] = sprintf(
                            $this->language->get('text_product_approve'),
                            $seller_product_review['data']['product_id'] .
                            "&ca_id=" . $seller_product_review['customer_activity_id'],
                            $seller_product_review['data']['product_name'],
                            $seller_product_review['date_added']
                        ) . ($seller_product_review['is_read'] ? '' : '<span class="badge-dot"></span>');
                }
            }
        }
        $data['product_unread_url'] = $this->url->link('account/customerpartner/notification/read' . $readAllUrl . '&tab=product', '', true) . '&ids=' . implode(',', $productUnreadIDArr);

        //Seller tab
        $data['seller_reviews'] = array();
        $seller_reviews = $this->model_account_notification->getSellerReviews();
        $seller_reviews_total = $this->model_account_notification->getSellerReviewsTotal();
        if ($seller_reviews) {
            foreach ($seller_reviews as $key => $seller_review) {

                $date_diff = (array)(new DateTime($seller_review['createdate']))->diff(new DateTime());

                if (isset($date_diff['y']) && $date_diff['y']) {
                    $seller_review['createdate'] = $date_diff['y'] . ' ' . $this->language->get('text_years');
                } elseif (isset($date_diff['m']) && $date_diff['m']) {
                    $seller_review['createdate'] = $date_diff['m'] . ' ' . $this->language->get('text_months');
                } elseif (isset($date_diff['d']) && $date_diff['d']) {
                    $seller_review['createdate'] = $date_diff['d'] . ' ' . $this->language->get('text_days');
                } elseif (isset($date_diff['h']) && $date_diff['h']) {
                    $seller_review['createdate'] = $date_diff['h'] . ' ' . $this->language->get('text_hours');
                } elseif (isset($date_diff['i']) && $date_diff['i']) {
                    $seller_review['createdate'] = $date_diff['i'] . ' ' . $this->language->get('text_minutes');
                } else {
                    $seller_review['createdate'] = $date_diff['s'] . ' ' . $this->language->get('text_seconds');
                }

                $data['seller_reviews'][] = sprintf($this->language->get('text_seller_review'), $seller_review['id'], $seller_review['customer_id'], $seller_review['name'], $seller_review['createdate']);
            }
        }

        $categories = $this->model_account_notification->getSellerCategoryActivity();

        $categories_total = $this->model_account_notification->getSellerCategoryActivityTotal();

        if ($categories) {
            foreach ($categories as $key => $category) {

                $date_diff = (array)(new DateTime($category['date_added']))->diff(new DateTime());

                if (isset($date_diff['y']) && $date_diff['y']) {
                    $category['date_added'] = $date_diff['y'] . ' year(s)';
                } elseif (isset($date_diff['m']) && $date_diff['m']) {
                    $category['date_added'] = $date_diff['m'] . ' month(s)';
                } elseif (isset($date_diff['d']) && $date_diff['d']) {
                    $category['date_added'] = $date_diff['d'] . ' day(s)';
                } elseif (isset($date_diff['h']) && $date_diff['h']) {
                    $category['date_added'] = $date_diff['h'] . ' hour(s)';
                } elseif (isset($date_diff['i']) && $date_diff['i']) {
                    $category['date_added'] = $date_diff['i'] . ' minute(s)';
                } else {
                    $category['date_added'] = $date_diff['s'] . ' second(s)';
                }
                $category['data'] = json_decode($category['data'], 1);

                if (isset($category['data']['category_name']) && $category['data']['category_name']) {
                    $data['seller_reviews'][] = sprintf($this->language->get('text_category_approve'), $category['data']['category_name'], $category['data']['category_name'], $category['date_added']);
                }
            }
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        if (isset($this->request->get['page_product'])) {
            $page_product = $this->request->get['page_product'];
        } else {
            $page_product = 1;
        }

        if (isset($this->request->get['page_seller'])) {
            $page_seller = $this->request->get['page_seller'];
        } else {
            $page_seller = 1;
        }

        $data['page'] = $page;

        $url = '';

        if (isset($this->request->get['options'])) {
            $url = '&options=' . $this->request->get['options'];
        }

        //Pagination For Order Tab
        $pagination = new Pagination();
        $pagination->total = $seller_notifications_total;
        $pagination->page = $page;
        $pagination->limit = 10;
        $pagination->num_links = 5;
        $pagination->url = $this->url->link('account/customerpartner/notification', $url . '&page={page}&page_product=' . $page_product . '&page_seller=' . $page_seller, true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($seller_notifications_total) ? (($page - 1) * 10) + 1 : 0,
            ((($page - 1) * 10) > ($seller_notifications_total - 10)) ? $seller_notifications_total : ((($page - 1) * 10) + 10),
            $seller_notifications_total, ceil($seller_notifications_total / 10)
        );

        //Pagination For Product Tab
        $page_product = is_numeric($page_product) ? $page_product : 1;
        $pagination_product = new Pagination();
        $pagination_product->total = $seller_product_reviews_total;
        $pagination_product->page = $page_product;
        $pagination_product->limit = 10;
        $pagination_product->url = $this->url->link('account/customerpartner/notification', $url . '&page_product={page}&page=' . $page . '&page_seller=' . $page_seller . '&tab=product', true);

        $data['pagination_product'] = $pagination_product->render();

        $data['results_product'] = sprintf(
            $this->language->get('text_pagination'),
            ($seller_product_reviews_total) ? (($page_product - 1) * 10) + 1 : 0,
            ((($page_product - 1) * 10) > ($seller_product_reviews_total - 10)) ? $seller_product_reviews_total : ((($page_product - 1) * 10) + 10),
            $seller_product_reviews_total, ceil($seller_product_reviews_total / 10)
        );

        $seller_reviews_total = $seller_reviews_total + $categories_total;

        //Pagination For Seller Tab
        $pagination_seller = new Pagination();
        $pagination_seller->total = $seller_reviews_total;
        $pagination_seller->page = $page_seller;
        $pagination_seller->limit = 10;
        $pagination_seller->url = $this->url->link('account/customerpartner/notification', $url . '&page_seller={page}&page=' . $page . '&page_product=' . $page_product . '&tab=seller', true);
        $data['pagination_seller'] = $pagination_seller->render();
        $data['results_seller'] = sprintf($this->language->get('text_pagination'), ($seller_reviews_total) ? (($page_seller - 1) * 10) + 1 : 0, ((($page_seller - 1) * 10) > ($seller_reviews_total - 10)) ? $seller_reviews_total : ((($page_seller - 1) * 10) + 10), $seller_reviews_total, ceil($seller_reviews_total / 10));

        //Pagination For RMA Tab
        $this->load->model('account/customer');
        /** @var ModelAccountCustomer $modelAccountCustomer */
        $this->load->model('customerpartner/rma_management');
        /** @var ModelCustomerpartnerRmaManagement $modelCustomerpartnerRmaMangement */
        $modelCustomerpartnerRmaMangement = $this->model_customerpartner_rma_management;
        $modelAccountCustomer = $this->model_account_customer;
        /** @var ModelAccountNotification $modelAccountNotification */
        $modelAccountNotification = $this->model_account_notification;
        list($page, $perPage, $rma_get) = $modelAccountNotification->getPagePerPage(10, 'page_rma');
        $RmaTotal = $modelAccountNotification->getRmaActivityTotal(null);
        $RmaUnreadTotal = $modelAccountNotification->getRmaActivityTotal(false);
        if (isset($rma_get['flag']) && $rma_get['flag'] == static::RMA_NOTIFICATION_UNREAD_TOTAL) {
            $nowTotal = $RmaUnreadTotal;
            $RmaList = $modelAccountNotification->getRmaActivityList(['is_read' => 0], $perPage);
        } else {
            $nowTotal = $RmaTotal;
            $RmaList = $modelAccountNotification->getRmaActivityList([], $perPage);
        }
        $infoList = [];
        $rmaIds = [];
        foreach ($RmaList as $k => $v) {
            $rmaInfo = $this->getRmaInfo((int)$v['data']['rma_id']);
            $infoList[] = sprintf(
                $this->language->get('text_rma_add_mp'),
                $this->url->link(
                    'account/customerpartner/rma_management/rmaInfo',
                    ['rmaId' => $v['data']['rma_id'], 'activity_id' => $v['customer_activity_id']]),
                $rmaInfo['rma_order_id'] ?? 'Unknown',
                $modelAccountCustomer->getCustomerNicknameAndNumber((int)($v['data']['buyer_id'] ?? 0)),
                $this->dateDiffFormat($v['date_added']),
                $v['is_read'] ? '' : '<span class="badge-dot"></span>'
            );;
            ($v['is_read'] == 0) && $rmaIds[] = $v['customer_activity_id'] . '_1';
        }
        $pagination_rma = new Pagination();
        $pagination_rma->total = $nowTotal;
        $pagination_rma->page = $page;
        $pagination_rma->limit = $perPage;
        // 分页arg参数
        $pagination_rma->url = $this->url->link(
            'account/customerpartner/notification',
            array_merge($rma_get, ['page_rma' => '{page}'])
        );
        if (isset($rma_get['is_read'])) unset($rma_get['is_read']);
        if (isset($rma_get['route'])) unset($rma_get['route']);
        if (isset($rma_get['tab']))  $rma_get['tab'] = 'rma';

        $data['rma'] = [
            'menu' => [
                static::RMA_NOTIFICATION_UNREAD_TOTAL => [
                    'count' => $RmaUnreadTotal,
                    'title' => 'Unread notifications',
                    'url' => $this->url->link(
                        'account/customerpartner/notification',
                        array_merge($rma_get, ['flag' => static::RMA_NOTIFICATION_UNREAD_TOTAL])
                    ),
                ],
                static::RMA_NOTIFICATION_TOTAL => [
                    'count' => $RmaTotal,
                    'title' => 'All notifications',
                    'url' => $this->url->link(
                        'account/customerpartner/notification',
                        array_merge($rma_get, ['flag' => static::RMA_NOTIFICATION_TOTAL])
                    ),
                ],
            ],
            'readAllIds' => join(',', $rmaIds),
            'readAllUrl' => $this->url->link('account/customerpartner/notification/read', ['ids' => join(',', $rmaIds)]),
            'flag' => $rma_get['flag'] ?? static::RMA_NOTIFICATION_TOTAL, // 默认情况下显示所有通知
            'total' => $RmaTotal,
            'unread_total' => $RmaUnreadTotal,
            'list' => $infoList,
            'pagination' => $pagination_rma->render(),
            'results' => sprintf(
                $this->language->get('text_pagination'),
                ($nowTotal) ? (($page - 1) * $perPage) + 1 : 0,
                ((($page - 1) * $perPage) > ($nowTotal - $perPage)) ? $nowTotal : ((($page - 1) * $perPage) + $perPage),
                $nowTotal,
                ceil($nowTotal / $perPage))
        ];


        //返点议价，保证金等BID模块---start
        list($page, $perPage, $bid_get) = $modelAccountNotification->getPagePerPage(10, 'page_bid');
        $bidTotal = $modelAccountNotification->getBidActivityTotal(null);
        $bidUnreadTotal = $modelAccountNotification->getBidActivityTotal(false);
        if (isset($bid_get['flag']) && $bid_get['flag'] == static::BID_NOTIFICATION_UNREAD_TOTAL) {
            $nowTotal = $bidUnreadTotal;
            $bidList = $modelAccountNotification->getBidActivityList(['is_read' => 0], $perPage);
        } else {
            $nowTotal = $bidTotal;
            $bidList = $modelAccountNotification->getBidActivityList([], $perPage);
        }
        $bidInfoList = [];
        $bidIds = [];
        foreach ($bidList as $k => $v) {
            $agreement_link = '';
            if($v['data']['bid_type'] == 'rebates'){
                $agreement_link = $this->url->link('account/product_quotes/rebates_contract/view',['contract_id' => $v['data']['agreement_id'], 'activity_id' => $v['customer_activity_id']]);
            }
            $bidInfoList[] = sprintf(
                $this->language->get('text_bid_add_mp'),
                $modelAccountCustomer->getCustomerNicknameAndNumber((int)($v['data']['buyer_id'] ?? 0)),
                $this->url->link('product/product', 'product_id=' . $v['data']['product_id']),
                $v['data']['sku'],
                $agreement_link,
                $v['data']['agreement_id'],
                $this->dateDiffFormat($v['date_added']),
                $v['is_read'] ? '' : '<span class="badge-dot"></span>'
            );
            ($v['is_read'] == 0) && $bidIds[] = $v['customer_activity_id'] . '_1';
        }
        $pagination_rma = new Pagination();
        $pagination_rma->total = $nowTotal;
        $pagination_rma->page = $page;
        $pagination_rma->limit = $perPage;
        // 分页arg参数
        $pagination_rma->url = $this->url->link(
            'account/customerpartner/notification',
            array_merge($bid_get, ['page_bid' => '{page}'])
        );
        if (isset($bid_get['is_read'])) unset($bid_get['is_read']);
        if (isset($bid_get['route'])) unset($bid_get['route']);
        if (isset($bid_get['tab']))  $bid_get['tab'] = 'bid';

        $data['bid'] = [
            'menu' => [
                static::BID_NOTIFICATION_UNREAD_TOTAL => [
                    'count' => $bidUnreadTotal,
                    'title' => 'Unread notifications',
                    'url' => $this->url->link(
                        'account/customerpartner/notification',
                        array_merge($bid_get, ['flag' => static::BID_NOTIFICATION_UNREAD_TOTAL])
                    ),
                ],
                static::BID_NOTIFICATION_TOTAL => [
                    'count' => $bidTotal,
                    'title' => 'All notifications',
                    'url' => $this->url->link(
                        'account/customerpartner/notification',
                        array_merge($bid_get, ['flag' => static::BID_NOTIFICATION_TOTAL])
                    ),
                ],
            ],
            'readAllIds' => join(',', $bidIds),
            'readAllUrl' => $this->url->link('account/customerpartner/notification/read', ['ids' => join(',', $bidIds)]),
            'flag' => $bid_get['flag'] ?? static::BID_NOTIFICATION_TOTAL, // 默认情况下显示所有通知
            'total' => $bidTotal,
            'unread_total' => $bidUnreadTotal,
            'list' => $bidInfoList,
            'pagination' => $pagination_rma->render(),
            'results' => sprintf(
                $this->language->get('text_pagination'),
                ($nowTotal) ? (($page - 1) * $perPage) + 1 : 0,
                ((($page - 1) * $perPage) > ($nowTotal - $perPage)) ? $nowTotal : ((($page - 1) * $perPage) + $perPage),
                $nowTotal,
                ceil($nowTotal / $perPage))
        ];
        //返点议价，保证金等BID模块---end
        $data['back'] = $this->url->link('account/account', '', true);
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

        /**
         * customer_activity 表添加is_read字段，替换插件之前的逻辑
         * 之前的逻辑：只能控制所有的消息是否已读，且未读数量是总共的，无法确认某一个notification是否已读
         * 现在的逻辑：根据is_read字段来确认一个notification是否已读
         */
//        $marketplace_notification_viewed = $data['all_notifications'] + $seller_product_reviews_total + $seller_reviews_total;
//        $this->model_account_notification->addViewedNotification($marketplace_notification_viewed);
        $data['tab'] = strtolower($this->request->get['tab'] ?? '');
        $this->response->setOutput($this->load->view('account/customerpartner/notification', $data));
    }

    public function notifications()
    {
        if ($this->customer->getId()) {

            $this->load->model('account/notification');

            $this->load->model('account/customerpartner');

            $this->load->model('mp_localisation/order_status');

            $text_data = array_merge($this->load->language('account/customerpartner/notification'));

            if (!isset($this->request->get['json_notification'])) {
                $data['sellerProfile'] = $this->model_account_customerpartner->getProfile();
            }

            $data['processing_status_total'] = $this->model_account_notification->getTotalSellerActivityUnread(array('2'));

            $data['complete_status_total'] = $this->model_account_notification->getTotalSellerActivityUnread(array('5'));

            // rma 通知数量加入
            $this->load->model('account/customer');
            /** @var ModelAccountCustomer $modelAccountCustomer */
            $modelAccountCustomer = $this->model_account_customer;
            $this->load->model('customerpartner/rma_management');
            /** @var ModelCustomerpartnerRmaManagement $modelCustomerpartnerRmaMangement */
            $modelCustomerpartnerRmaMangement = $this->model_customerpartner_rma_management;
            /** @var ModelAccountNotification $modelAccountNotification */
            $modelAccountNotification = $this->model_account_notification;
            $data['rma_unread_total'] = $modelAccountNotification->getRmaActivityTotal(false);
            $data['rma_total'] = $modelAccountNotification->getRmaActivityTotal(null);
            $RmaList = $modelAccountNotification->getRmaActivityList(['is_read' => 0], 3);
            $infoList = [];
            foreach ($RmaList as $k => $v) {
                $rmaInfo = $this->getRmaInfo((int)$v['data']['rma_id']);
                $infoList[] = sprintf(
                    $this->language->get('text_rma_add_mp'),
                    $this->url->link('account/customerpartner/rma_management/rmaInfo',
                        ['rmaId' => $v['data']['rma_id'], 'activity_id' => $v['customer_activity_id']]
                    ),
                    $rmaInfo['rma_order_id'] ?? 'Unknown',
                    $modelAccountCustomer->getCustomerNicknameAndNumber((int)($v['data']['buyer_id'] ?? 0)),
                    $this->dateDiffFormat($v['date_added']),
                    ''
                );;
            }
            $data['rma_list'] = $infoList;

            //BID --START
            $data['bid_unread_total'] = $modelAccountNotification->getBidActivityTotal(false);
            $bidList = $modelAccountNotification->getBidActivityList(['is_read' => 0], 3);
            $bidInfoList = [];
            foreach ($bidList as $k => $v) {
                $agreement_link = '';
                if($v['data']['bid_type'] == 'rebates'){
                    $agreement_link = $this->url->link('account/product_quotes/rebates_contract/view',['contract_id' => $v['data']['agreement_id'], 'activity_id' => $v['customer_activity_id']]);
                }
                $bidInfoList[] = sprintf(
                    $this->language->get('text_bid_add_mp'),
                    $modelAccountCustomer->getCustomerNicknameAndNumber((int)($v['data']['buyer_id'] ?? 0)),
                    $this->url->link('product/product', 'product_id=' . $v['data']['product_id']),
                    $v['data']['sku'],
                    $agreement_link,
                    $v['data']['agreement_id'],
                    $this->dateDiffFormat($v['date_added']),
                    $v['is_read'] ? '' : '<span class="badge-dot"></span>'
                );
            }
            $data['bid_list'] = $bidInfoList;
            //BID --END

            $data['view_all'] = $this->url->link('account/customerpartner/notification', '', true);
            $data['notification_total'] = 0;
            $data['notification_total'] = $data['processing_status_total'] + $data['complete_status_total'] + $data['rma_unread_total'] + $data['bid_unread_total'];
            if ($data['notification_total'] < 0) {
                $data['notification_total'] = 0;
            }

            $data['seller_notifications'] = array();
            $seller_notifications = $this->model_account_notification->getSellerActivityUnread(array(), 3);
            $customerNicknameTemp = array();
            $this->load->model('account/customer');

            if ($seller_notifications) {
                foreach ($seller_notifications as $key => $seller_notification) {

                    $date_diff = (array)(new DateTime($seller_notification['date_added']))->diff(new DateTime());

                    if (isset($date_diff['y']) && $date_diff['y']) {
                        $seller_notification['date_added'] = $date_diff['y'] . ' ' . $this->language->get('text_years');
                    } elseif (isset($date_diff['m']) && $date_diff['m']) {
                        $seller_notification['date_added'] = $date_diff['m'] . ' ' . $this->language->get('text_months');
                    } elseif (isset($date_diff['d']) && $date_diff['d']) {
                        $seller_notification['date_added'] = $date_diff['d'] . ' ' . $this->language->get('text_days');
                    } elseif (isset($date_diff['h']) && $date_diff['h']) {
                        $seller_notification['date_added'] = $date_diff['h'] . ' ' . $this->language->get('text_hours');
                    } elseif (isset($date_diff['i']) && $date_diff['i']) {
                        $seller_notification['date_added'] = $date_diff['i'] . ' ' . $this->language->get('text_minutes');
                    } else {
                        $seller_notification['date_added'] = $date_diff['s'] . ' ' . $this->language->get('text_seconds');
                    }
                    $seller_notification['data'] = json_decode($seller_notification['data'], 1);
                    if (isset($seller_notification['data']['customer_id'])) {
                        if (isset($customerNicknameTemp[$seller_notification['data']['customer_id']])) {
                            $seller_notification['data']['name'] = $customerNicknameTemp[$seller_notification['data']['customer_id']];
                        } else {
                            $nickname = $this->model_account_customer->getCustomerNicknameAndNumber($seller_notification['data']['customer_id']);
                            $seller_notification['data']['name'] = $nickname;
                            $customerNicknameTemp[$seller_notification['data']['customer_id']] = $nickname;
                        }
                    }

                    if ($seller_notification['key'] == 'order_status') {
                        $data['seller_notifications'][] = sprintf(
                            $this->language->get('text_order_add_mp'),
                            $seller_notification['data']['order_id'] .
                            "&ca_id=" . $seller_notification['customer_activity_id'] . "&is_mp=" . $seller_notification['is_mp'],
                            $seller_notification['data']['order_id'],
                            $seller_notification['data']['name'],
                            $seller_notification['date_added']
                        );
                    } elseif ($seller_notification['key'] == 'return_account') {
                        $order_id = $this->model_account_notification->getReturnOrderId($seller_notification['data']['return_id']);
                        $data['seller_notifications'][] = sprintf(
                            $this->language->get('text_order_return_mp'),
                            $seller_notification['data']['name'],
                            $order_id['order_id'] .
                            "&ca_id=" . $seller_notification['customer_activity_id'] . "&is_mp=" . $seller_notification['is_mp'],
                            $seller_notification['data']['return_id'],
                            $order_id['product'],
                            $seller_notification['date_added']
                        );
                    } elseif ($seller_notification['key'] == 'order_status') {
                        $status = $this->model_mp_localisation_order_status->getOrderStatus($seller_notification['data']['status']);
                        if ($status) {
                            $data['seller_notifications'][] = sprintf(
                                $this->language->get('text_order_status_mp'),
                                $seller_notification['data']['order_id'] .
                                "&ca_id=" . $seller_notification['customer_activity_id'] . "&is_mp=" . $seller_notification['is_mp'],
                                $seller_notification['data']['order_id'],
                                $status['name'],
                                $seller_notification['date_added']
                            );
                        }
                    }
                }
            }

            $data['seller_product_reviews'] = array();

            $data['product_stock_total'] = $this->model_account_notification->getProductStockTotalUnread();

            $data['review_total'] = $this->model_account_notification->getReviewTotalUnread();

            /**
             * @todo 隐藏 approval
             */
//            $data['approval_total'] = $this->model_account_notification->getApprovalTotalUnread();

            $seller_product_reviews = $this->model_account_notification->getSellerProductActivityUnread(array(), 3);

//            $data['product_review_total'] = $this->model_account_notification->getSellerProductActivityTotal();
            /**
             * @todo 隐藏 approval
             */
            $data['product_review_total'] = $data['product_stock_total'] + $data['review_total'];
            $authorNicknameTemp = array();
            if ($seller_product_reviews) {
                foreach ($seller_product_reviews as $key => $seller_product_review) {
                    $date_diff = (array)(new DateTime($seller_product_review['date_added']))->diff(new DateTime());

                    if (isset($date_diff['y']) && $date_diff['y']) {
                        $seller_product_review['date_added'] = $date_diff['y'] . ' ' . $this->language->get('text_years');
                    } elseif (isset($date_diff['m']) && $date_diff['m']) {
                        $seller_product_review['date_added'] = $date_diff['m'] . ' ' . $this->language->get('text_months');
                    } elseif (isset($date_diff['d']) && $date_diff['d']) {
                        $seller_product_review['date_added'] = $date_diff['d'] . ' ' . $this->language->get('text_days');
                    } elseif (isset($date_diff['h']) && $date_diff['h']) {
                        $seller_product_review['date_added'] = $date_diff['h'] . ' ' . $this->language->get('text_hours');
                    } elseif (isset($date_diff['i']) && $date_diff['i']) {
                        $seller_product_review['date_added'] = $date_diff['i'] . ' ' . $this->language->get('text_minutes');
                    } else {
                        $seller_product_review['date_added'] = $date_diff['s'] . ' ' . $this->language->get('text_seconds');
                    }

                    $seller_product_review['data'] = json_decode($seller_product_review['data'], 1);
                    if (isset($seller_product_review['data']['review_id']) && isset($seller_product_review['data']['author'])) {
                        if (isset($authorNicknameTemp[$seller_product_review['data']['review_id']])) {
                            $seller_product_review['data']['author'] = $authorNicknameTemp[$seller_product_review['data']['review_id']];
                        } else {
                            $nickname = $this->model_account_notification->getReviewerNickname($seller_product_review['data']['review_id']);
                            $authorNicknameTemp[$seller_product_review['data']['review_id']] = $nickname;
                            $seller_product_review['data']['author'] = $nickname;
                        }
                    }
                    if ($seller_product_review['key'] == 'product_review') {
                        $data['seller_product_reviews'][] = sprintf(
                            $this->language->get('text_product_review'),
                            $seller_product_review['id'],
                            $seller_product_review['data']['author'],
                            $seller_product_review['data']['product_id'] .
                            "&ca_id=" . $seller_product_review['customer_activity_id'],
                            $seller_product_review['data']['product_name'],
                            $seller_product_review['date_added']
                        );
                    } elseif ($seller_product_review['key'] == 'product_stock') {
                        $data['seller_product_reviews'][] = sprintf(
                            $this->language->get('text_product_stock'),
                            $seller_product_review['data']['product_id'] .
                            "&ca_id=" . $seller_product_review['customer_activity_id'],
                            $seller_product_review['data']['product_name'],
                            truncate($seller_product_review['data']['product_name'], 50),
                            $seller_product_review['date_added']
                        );
                    }
                }
            }

            if (!isset($this->session->data['temp_separate_view']) && !(isset($this->request->get['json_notification']) && $this->request->get['json_notification'])) {
                if (preg_match('/route=account\/customerpartner/', request()->serverBag->get('QUERY_STRING'))) {
                    session()->set('temp_separate_view', true);
                } else {
                    session()->set('temp_separate_view', false);
                }
            }

            $data['separate_view'] = false;

            if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate' && isset($this->session->data['temp_separate_view']) && $this->session->data['temp_separate_view']) {
                $data['separate_view'] = true;
            }

            /**
             * @var array $text_data language的数据
             * @var array $data 主要数据
             * 如果返回的是json格式，就没必要把language里面所有的数据传到$data进而传给浏览器了（节约网络带宽）
             * 如果是页面，则需要把以上两个数组合并
             * 对比：json数据未优化前--10K左右，优化后 1.7K
             */
            if (isset($this->request->get['json_notification'])) {
                $data['notification_total'] += $data['product_review_total'];
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($data));
            } else {
                return $this->load->view('account/customerpartner/notification_header', array_merge($text_data, $data));
            }
        }
    }

    public function read()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/notification', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->load->model('account/customerpartner');

        $data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();
        if (!$data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode'])) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }

        $url = '';
        isset($this->request->get['page']) && is_numeric($this->request->get['page']) && $url .= '&page=' . $this->request->get['page'];
        isset($this->request->get['page_product']) && is_numeric($this->request->get['page_product']) && $url .= '&page_product=' . $this->request->get['page_product'];
        isset($this->request->get['page_seller']) && is_numeric($this->request->get['page_seller']) && $url .= '&page_seller=' . $this->request->get['page_seller'];
        isset($this->request->get['tab']) && !empty($this->request->get['tab']) && $url .= '&tab=' . $this->request->get['tab'];

        if (!isset($this->request->get['ids']) || empty($this->request->get['ids'])) {
            $this->response->redirect($this->url->link('account/customerpartner/notification' . $url, '', true));
        }

        $this->load->model('account/notification');

        $idsArr = explode(',', $this->request->get['ids']);
        trim_strings($idArr);
        $caIDArr = [];
        $mpCaIDArr = [];
        foreach ($idsArr as $idString) {
            $tempArr = explode('_', $idString);
            if (empty($tempArr[0]) || !is_numeric($tempArr[0])) {
                continue;
            }
            if (get_value_or_default($tempArr, 1, 1) == 1) {
                $mpCaIDArr[] = $tempArr[0];
            } else {
                $caIDArr[] = $tempArr[0];
            }
        }
        !empty($mpCaIDArr) && $this->model_account_notification->readAll($mpCaIDArr, 1);
        !empty($caIDArr) && $this->model_account_notification->readAll($caIDArr, 0);

        $this->load->language('account/customerpartner/notification');
        session()->set('success', $this->language->get('success_read_all'));

        $this->response->redirect($this->url->link('account/customerpartner/notification' . $url, '', true));
    }

    /**
     * @param string $diffDateTime
     * @return string
     */
    protected function dateDiffFormat(string $diffDateTime): string
    {
        $dataMap = [
            'y' => $this->language->get('text_years'),
            'm' => $this->language->get('text_months'),
            'd' => $this->language->get('text_days'),
            'h' => $this->language->get('text_hours'),
            'i' => $this->language->get('text_minutes'),
            's' => $this->language->get('text_seconds'),
        ];
        $diff = (array)(new DateTime($diffDateTime))->diff(new DateTime());
        $str = '';
        foreach ($dataMap as $k => $v) {
            if (isset($diff[$k]) && $diff[$k] > 0) {
                $str .= "{$diff[$k]} {$v}";
                break;
            }
        }

        return $str;
    }

    /**
     * @param int $rma_id
     * @return array
     */
    public function getRmaInfo(int $rma_id): array
    {
        $ret = $this->orm
            ->table(DB_PREFIX . 'yzc_rma_order')
            ->where(['id' => $rma_id])
            ->first();

        return $ret ? get_object_vars($ret) : [];
    }

}
