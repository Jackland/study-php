<?php

/**
 * Class ControllerAccountCustomerpartnerMessageColumn
 *
 * @property ModelNoticeNotice $model_notice_notice
 * @property ModelStationLetterStationLetter $model_station_letter_station_letter
 * @property ModelMessageMessage $model_message_message
 */
class ControllerAccountCustomerpartnerMessageColumn extends Controller
{
    public function index($data = [])
    {

        $this->load->language('account/customerpartner/column_left');
        $this->load->model('message/message');
        $this->load->model('notice/notice');
        $this->load->model('station_letter/station_letter');
        $is_partner = $this->customer->isPartner();
        $customerId = $this->customer->getId();

        $data['menus'] = array();

        if ($this->config->get('module_marketplace_status') && is_array($this->config->get('marketplace_allowed_account_menu')) && $this->config->get('marketplace_allowed_account_menu')) {
            $route = $this->request->request['route'];
            $menu = $this->url->link($route, '', true);
            $data['menuLink'] = $menu;


            $message_count = $this->model_message_message->unReadMessageCount($customerId);
            $notice_count = $this->model_notice_notice->countNoticeNew(['is_read' => 0]);
            $notice_count += $this->model_station_letter_station_letter->stationLetterCount($customerId, 0);
            $ticket_count = $this->model_message_message->unReadTicketCount($customerId);
            $system_unread_by_type = $this->model_message_message->unReadSystemMessageCount($customerId);
            $system_unread_count = intval($system_unread_by_type['000']);
            $my_message_count = $message_count +  $notice_count + $system_unread_count;
            //第一列主要的消息功能菜单
            if($is_partner){
                $message_main[] = array(
                    'id' => 'message-seller',
                    'name' => $this->language->get('text_li_from_buyer'),
                    'href' => $this->url->link('message/seller', '', true),
                    'children' => array(),
                    'unread'=>$message_count
                );
            }else{
                $message_main[] = array(
                    'id' => 'message-seller',
                    'name' => $this->language->get('text_li_from_seller'),
                    'href' => $this->url->link('message/seller', '', true),
                    'children' => array(),
                    'unread'=>$message_count
                );
            }

            $message_main[] = array(
                'id' => 'message-system',
                'name' => $this->language->get('text_li_from_system'),
                'href' => $this->url->link('message/system', '', true),
                'children' => array(),
                'unread'=>$system_unread_count
            );

            $message_main[] = array(
                'id' => 'message-platform-notice',
                'name' => $this->language->get('text_li_notice'),
                'href' => $this->url->link('message/platform_notice', '', true),
                'children' => array(),
                'unread'=>$notice_count
            );

            $message_main[] = array(
                'id' => 'message-trash',
                'name' => $this->language->get('text_li_trash'),
                'href' => $this->url->link('message/trash', '', true),
                'children' => array()
            );
            $message_main[] = array(
                'id' => 'message-setting',
                'name' => $this->language->get('text_li_setting'),
                'href' => $this->url->link('message/setting', '', true),
                'children' => array()
            );

            if ($message_main) {
                $data['menus'][] = array(
                    'id' => 'message_menu_1',
                    'icon' => '',
                    'name' => $this->language->get('text_ul_main'),
                    'href' => '',
                    'unread'=>$my_message_count,
                    'children' => $message_main
                );
            }

            //第二列ticket等功能的菜单
//            if(!$is_partner){
                $message_second[] = array(
                    'id' => 'account-ticket-list',
                    'name' => $this->language->get('text_li_ticket'),
                    'href' => $this->url->link('account/ticket/lists', '', true),
                    'children' => array(),
                    'unread' => $ticket_count
                );

                if ($message_second) {
                    $data['menus'][] = array(
                        'id' => 'message_menu_2',
                        'icon' => '',
                        'name' => $this->language->get('text_ul_ticket'),
                        'href' => '',
                        'unread'=>$ticket_count,
                        'children' => $message_second
                    );
                }
//            }
        }

        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate'){
            return $this->load->view('account/customerpartner/message_column_top', $data);
        }else{
            if($this->customer->isLogged() && !$is_partner){
                $data['sales_link'] = $this->url->link('account/customer_order', '', true);
                $data['purchase_link'] = $this->url->link('account/order', '', true);
                $data['bid_link'] = $this->url->link('account/product_quotes/wk_quote_my', '', true);
                $data['rma_link'] = $this->url->link('account/rma_management', '', true);
                $data['inventory_link'] = $this->url->link('account/stock/management', '', true);
            }
            return $this->load->view('account/customerpartner/message_column_left', $data);
        }
    }
}
