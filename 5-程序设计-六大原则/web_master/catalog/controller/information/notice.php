<?php

/**
 * @property ModelNoticeNotice $model_notice_notice
 */
class ControllerInformationNotice extends Controller
{
    private $data = array();

    public function isSeparate()
    {
        return $this->customer->isPartner()
            && $this->config->get('marketplace_separate_view')
            && isset($this->session->data['marketplace_separate_view'])
            && $this->session->data['marketplace_separate_view'] == 'separate';
    }

    public function framework()
    {
        $this->document->setTitle($this->language->get('text_title'));

        $this->data['back'] = $this->url->link('account/account', '', true);

        //面包屑
        $this->data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', '', true),
                'separator' => false
            ], [
                'text' => $this->language->get('text_title'),
                'href' => $this->url->link('information/notice', '', true),
                'separator' => false
            ],
        ];

        $this->data['column_left'] = $this->load->controller('common/column_left');
        $this->data['column_right'] = $this->load->controller('common/column_right');
        $this->data['content_top'] = $this->load->controller('common/content_top');
        $this->data['content_bottom'] = $this->load->controller('common/content_bottom');
        $this->data['footer'] = $this->load->controller('common/footer');
        $this->data['header'] = $this->load->controller('common/header');

        $this->data['separate_view'] = false;
        $this->data['separate_column_left'] = '';

        if ($this->isSeparate()) {
            $this->data['separate_view'] = true;
            $this->data['column_left'] = '';
            $this->data['column_right'] = '';
            $this->data['content_top'] = '';
            $this->data['content_bottom'] = '';
            $this->data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');

            $this->data['footer'] = $this->load->controller('account/customerpartner/footer');
            $this->data['header'] = $this->load->controller('account/customerpartner/header');
        }
    }

    public function index()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('information/notice', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->getList();
    }

    public function readAllNotice()
    {
        $filter_type_id = $this->request->get['filter_type_id'];
        $filter_data = ['filter_type_id' => $filter_type_id];
        $this->load->model('notice/notice');
        $notices = $this->model_notice_notice->listNotice($filter_data);
        if (!empty($notices)) {
            foreach ($notices as $notice) {
                $this->model_notice_notice->readNotice($notice['notice_id']);
            }
        }
        $this->response->redirect($this->url->link('information/notice&filter_type_id=' . $filter_type_id .'&readAll=1', '', true));
    }

    public function getList()
    {
        $this->load->language('information/notice');

        if (isset($this->request->get['is_read'])) {
            $is_read = $this->request->get['is_read'];
        } else {
            $is_read = 'unread';
        }
        if (isset($this->request->get['page'])) {
            $page_num = $this->request->get['page'];
        } else {
            $page_num = 1;
        }

        if (isset($this->request->get['limit'])) {
            $page_limit = $this->request->get['limit'];
        } else {
            $page_limit = 15;
        }


//1 product  2 system 3 policy
        if (isset($this->request->get['filter_type_id'])) {
            $filter_type_id = $this->request->get['filter_type_id'];
        } else {
            $filter_type_id = '1';
        }

        $this->load->model('notice/notice');
        $unread_count_by_type = $this->model_notice_notice->countUnreadNoticeByType();
        $this->data['unread_count_by_type'] = $unread_count_by_type;

//      从导航栏点击的  进入第一个有未读公告的标签页
        if (isset($this->request->get['fromNavibar'])) {
            if (empty($unread_count_by_type)) {
                $is_read = 'total';
            } else {
                foreach ($unread_count_by_type as $type_id => $num) {
                    if ((int)$num > 0) {
                        $filter_type_id = $type_id;
                        break;
                    }
                }
            }
        }
        $filter_data = [
            'is_read' => $is_read,
            'filter_type_id' => $filter_type_id,
            'start' => ($page_num - 1) * $page_limit,
            'limit' => $page_limit,
        ];
        $this->data['is_read'] = $is_read;
        $this->data['filter_type_id'] = $filter_type_id;

        $this->data['TYPE_DIC'] = $this->model_notice_notice->queryTypeDic();

        $this->data['notices'] = $this->model_notice_notice->listNotice($filter_data);

        list($unread_count, $total_count) = $this->model_notice_notice->countNotice($filter_data);
        $this->data['unread_count'] = $unread_count;
        $this->data['total_count'] = $total_count;

        if ($is_read == 'unread') {
            $count = $unread_count;
        } else {
            $count = $total_count;
        }

        //分页
        $total_pages = ceil($count / $page_limit);
        $pagination_results = sprintf($this->language->get('text_pagination'), ($count) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($count - $page_limit)) ? $count : ((($page_num - 1) * $page_limit) + $page_limit), $count, $total_pages);

        $this->data['page_num'] = $page_num;
        $this->data['total_pages'] = $total_pages < 1 ? 1 : $total_pages;
        $this->data['pagination_results'] = $pagination_results;

        $this->framework();

        $this->data['current'] = $this->url->link('information/notice', '', true);
        $this->data['form_action'] = $this->url->link('information/notice/getForm', '', true);
        $this->data['read_all_action'] = $this->url->link('information/notice/readAllNotice', '', true);
        if(isset($this->request->get['readAll'])){
            $this->data['readAll'] = $this->request->get['readAll'];
        }
        $this->response->setOutput($this->load->view('information/notice', $this->data));
    }

    public function getForm()
    {
        $this->load->language('information/notice');

        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('information/notice', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        if (!isset($this->request->get['notice_id'])) {
            $this->response->redirect($this->url->link('error/not_found', '', true));
        }

        $notice_id = $this->request->get['notice_id'];
        $filter_data = ['notice_id' => $notice_id];
        $this->load->model('notice/notice');
        $notices = $this->model_notice_notice->listNotice($filter_data);
        if (empty($notices)) {
            $this->response->redirect($this->url->link('error/not_found', '', true));
        }

        $this->model_notice_notice->readNotice($notice_id);
        $this->data['notice'] = $notices[0];
        if(isset($this->request->post['backUrl'])){
            $this->data['backUrl'] = $this->request->post['backUrl'];
        }else{
            $this->data['backUrl'] = 'javascript:history.back();';
        }

        $this->framework();

        $this->response->setOutput($this->load->view('information/noticeForm', $this->data));
    }

    /**
     *  导航栏未读公告图标 及数量
     * @return string
     * @throws Exception
     */
    public function unread_notice()
    {
        $data['notice_action'] = $this->url->link('information/notice&fromNavibar=1', '', true);
        $data['notice_count_unread_action'] = $this->url->link('information/notice/countUnreadNotice', '', true);
        $period = $this->config->get('module_notice_count_unread_period');
        if (is_null($period)) {
            $period = 600;//轮询间隔 s
        }
        $data['notice_count_unread_period'] = $period;
        $data['is_separate_view'] = $this->isSeparate();
        return $this->load->view('information/unread_notice', $data);
    }

    /**
     * 获取未读公告数
     * @return mixed
     * @throws Exception
     */
    public function countUnreadNotice()
    {
        $this->load->model('notice/notice');
        $count = $this->model_notice_notice->countUnreadNotice();
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($count));
    }

    /**
     * buyer Guide Menu右侧展示最新公告
     * @return string
     * @throws Exception
     */
    public function column_notice()
    {
        $country_id = $this->customer->getCountryId();
        $customerId = $this->customer->getId();
        $isPartner = $this->customer->isPartner();
        $identity = $isPartner ? '1' : '0';
        $this->load->model('notice/notice');
        $data['notices'] = $this->model_notice_notice->listColumnNotice($customerId,$country_id,$identity);
        $data['notice_action'] = $this->url->to('account/message_center/platform_notice');
        $data['notice_form_action'] = $this->url->link('information/notice/getForm', '', true);
        return $this->load->view('information/column_notice', $data);
    }

}
