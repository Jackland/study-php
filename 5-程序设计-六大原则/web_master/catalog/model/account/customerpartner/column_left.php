<?php
/**
 * Created by PHPSTORM
 * User: yaopengfei
 * Date: 2020/7/15
 * Time: 下午3:46
 */

use App\Enums\Common\CountryEnum;
use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Seller\SellerStoreAuditStatus;
use App\Enums\Seller\SellerStoreAuditType;
use App\Helper\TranslationHelper;
use App\Models\Customer\CustomerExts;
use App\Models\Seller\SellerStoreAudit;
use App\Repositories\Margin\AgreementRepository;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use App\Repositories\Message\StatisticsRepository as MessageStatisticsRepositoryAlias;
use App\Repositories\SellerAsset\SellerAssetRepository;
use App\Repositories\Tripartite\AgreementRepository as TripartiteAgreementRepository;

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelMessageMessage $model_message_message
 * @property ModelNoticeNotice $model_notice_notice
 * @property ModelAccountProductQuoteswkproductquotes $model_account_product_quotes_wk_product_quotes
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelAccountProductQuotesRebatesContract $model_account_product_quotes_rebates_contract
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelAccountNotification $model_account_notification
 * @property ModelCustomerpartnerMarketingCampaignHistory $model_customerpartner_marketing_campaign_history
 * @property ModelCustomerpartnerRmaManagement $model_customerpartner_rma_management
 * @property ModelStationLetterStationLetter $model_station_letter_station_letter
 *
 * Class ModelAccountCustomerpartnerColumnLeft
 */
class ModelAccountCustomerpartnerColumnLeft extends Model
{
    protected $menusList = [];

    /**
     *
     *因为现在 seller 的列表配置  将各个菜单简单抽出来，后期大家配置，直接找到对应模块，配置对应的菜单。
     * 1-将对应的列表模块的注释补全，支持中英文切换
     * 2-配置的时候 注意上下顺序 不可随意调动上下位置！
     * @return array
     * @throws Exception
     */
    public function menus()
    {
        // 左侧菜单固定需要翻译
        TranslationHelper::tempEnable();

        $this->load->language('account/customerpartner/column_left');
        $menuArr = $this->config->get('marketplace_allowed_account_menu');
        //message center
        $this->messageCenterList();

        //store management
        $this->profileList($menuArr);

        //product management   产品详情和产品管理 合并
        $this->productManagementList($menuArr);

        //Complex Transaction Management  复杂交易管理
        $this->marketBusinessList($menuArr);

        //Custom Management 精细化管理改成定制管理，针对专享的设置
        $this->delicacyPriceSetting($menuArr);

        //Incoming Shipment Management   start 入库单管理
        $this->warehouseReceiptList($menuArr);

        //Inventory Management 库存管理
        $this->warehouseInventoryManagementList($menuArr);

        //Purchase Order Management 采购单管理
        $this->orderHistoryList($menuArr);

        //rma management 退反品管理
        $this->rmaManagementList($menuArr);

        // Sales Order Management 纯物流 (自发货)
        $this->salesOrderManagementList($menuArr);

        // mapping management 映射管理
        $this->mappingManagementList($menuArr);

        //促销活动start  Promotions
        $this->promotionList($menuArr);

        //Buyer Management
        $this->buyersList($menuArr);

        //Brand Center  brand品牌管理
        $this->bandList($menuArr);

        //Billing Management
        $this->sellerBillList();

        //Account Authorization 账号授权
        $this->accountAuthorization($menuArr);


        // todo 目前这些列表 没有明确位置 也不知道是否还存在 都归类到最后
        $this->dashboardList($menuArr);
        $this->categoryList($menuArr);
        $this->transactionList($menuArr);
        $this->downloadsList($menuArr);
        $this->manageShippingList($menuArr);
        $this->reviewList($menuArr);
        $this->informationList($menuArr);
        $this->notificationList($menuArr);
        $this->productReviewList($menuArr);
        //todo end


        $data = $this->filterNotAuthorizedMenuIds($this->menusList);

        TranslationHelper::tempDisableAfterEnable();

        return $data;
    }


    /**
     * description:
     * @param array $menuArr
     * @return void
     */
    private function dashboardList($menuArr)
    {
        if (in_array('dashboard', $menuArr)) {
            $this->menusList[] = array(
                'id' => 'menu-dashboard',
                'icon' => 'fa-dashboard',
                'name' => $this->language->get('text_dashboard'),
                'href' => $this->url->link('account/customerpartner/dashboard', '', true),
                'children' => array()
            );
        }

    }


    /**
     * description:store management
     * @param array $menuArr
     * @throws
     * @return void
     */
    private function profileList(array $menuArr)
    {
        if (in_array('profile', $menuArr)) {
            $accountManagementChildren[] = [
                'id' => 'menu-profile-child-profile',
                'name' => __('我的店铺简介', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/profile', '', true),
                'children' => array()
            ];

            $accountManagementChildren[] = [
                'id' => 'menu-profile-child-store_home_design',
                'name' => __('店铺首页设计', [], 'catalog/seller_menu'),
                'num' => $this->checkStoreAuditNoticeNeedShow((int)customer()->getId(), SellerStoreAuditType::HOME),
                'href' => $this->url->link('customerpartner/seller_store/home', '', true),
                'target' => '_blank',
                'children' => array()
            ];

            $accountManagementChildren[] = [
                'id' => 'menu-profile-child-store_introduction_design',
                'name' => __('店铺介绍页设计', [], 'catalog/seller_menu'),
                'num' => $this->checkStoreAuditNoticeNeedShow((int)customer()->getId(), SellerStoreAuditType::INTRODUCTION),
                'href' => $this->url->link('customerpartner/seller_store/introduction', '', true),
                'children' => array()
            ];

            $this->load->model('account/customerpartner');
            if (!$this->customer->isUSA() || !$this->model_account_customerpartner->isRelationUsaSellerSpecialAccountManagerBySellerId($this->customer->getId())) {
                $accountManagementChildren[] = [
                    'id' => 'menu-profile-child-credit-manage',
                    'name' => __('授信管理', [], 'catalog/seller_menu'),
                    'href' => $this->url->link('customerpartner/credit_manage', '', true),
                    'children' => array()
                ];
            }

            if ($this->customer->getAccountType() == CustomerAccountingType::GIGA_ONSIDE) {
                $accountManagementChildren[] = [
                    'id' => 'menu-profile-child-giga-onsite',
                    'name' => __('GIGA Onsite系统', [], 'catalog/seller_menu'),
                    'href' => ONSITE_LOGIN_URL,//对接giga onsite
                    'target' => '_blank',
                    'children' => array()
                ];
            }
            if (app(SellerAssetRepository::class)->checkSellerRiskCountry($this->customer->getId())) {
                // #9869 seller资产管理
                $accountManagementChildren[] = [
                    'id' => 'menu-profile-child-account-manage',
                    'name' => __('资产管理', [], 'catalog/seller_menu'),
                    'href' => url(['account/customerpartner/assets_manage']),
                    'children' => array()
                ];
            }

            $this->menusList[] = array(
                'id' => 'menu-profile',
                'icon' => 'icon-dianpu-01',
                'name' => __('店铺管理', [], 'catalog/seller_menu'),
                'href' => '',
                'children' => $accountManagementChildren,
            );
        }

    }
    /**
     * description:
     * @return void
     */
    private function categoryList($menuArr)
    {
        if (in_array('category', $menuArr)) {
            $this->menusList = array(
                'id' => 'menu-category',
                'icon' => 'fa-tags',
                'name' => $this->language->get('text_category'),
                'href' => $this->url->link('account/customerpartner/category', '', true),
                'children' => array()
            );
        }

    }
    /**
     * description: 消息中心  message center
     * @return void
     * @throws Exception
     */
    private function messageCenterList()
    {
        if ($this->config->get('module_wk_communication_status') && $this->customer->getId()) {
            $this->load->model('notice/notice');
            $this->load->model('message/message');
            $this->load->model('station_letter/station_letter');
            $mail_unread_count = $this->model_message_message->unReadMessageCount($this->customer->getId());
            $platform_unread_count = $this->model_notice_notice->countNoticeNew(['is_read' => 0]);
            $stationLetterCount = $this->model_station_letter_station_letter->stationLetterCount($this->customer->getId(), 0);
            $ticket_count = $this->model_message_message->unReadTicketCount($this->customer->getId());
            $system_unread_by_type = $this->model_message_message->unReadSystemMessageCount($this->customer->getId());
            $system_unread_count = intval($system_unread_by_type['000']);

            // 平台小助手
            $customerId = $this->customer->getId();
            $gigaGenieUnreadCount = app(MessageStatisticsRepositoryAlias::class)->getCustomerInboxFromGigaGenieUnreadCount($customerId);
            $unread_num = $stationLetterCount + $platform_unread_count + $mail_unread_count + $system_unread_count + $ticket_count + $gigaGenieUnreadCount;
            //my messages
            $myMessageCount = $stationLetterCount + $platform_unread_count + $mail_unread_count + $system_unread_count;

            $this->menusList[] = [
                'id' => 'menu-communication',
                'icon' => 'icon-xinbaniconshangchuan-',
                'name' => __('消息中心', [], 'catalog/seller_menu'),
                'href' => '',
                'num' => $unread_num,
                'children' => [
//                    [
//                        'id' => 'menu-communication_child_my_messages',
//                        'name' => __('我的消息', [], 'catalog/seller_menu'),
//                        'href' => $this->url->link('message/seller', '', true),
//                        'num' => $myMessageCount,
//                        'children' => [],
//                    ],
                    [
                        'id' => 'menu-communication_child_new_message',
                        'name' => __('新建站内信', [], 'catalog/seller_menu'),
                        'href' => url('customerpartner/message_center/message/new'),
                        'num' => 0, // 需求规定该项不纳入总数统计
                        'children' => []
                    ],
                    [
                        'id' => 'menu-communication_child_platform_secretary',
                        'name' => __('平台小秘书', [], 'catalog/seller_menu'),
                        'href' => url(['customerpartner/message_center/platform_secretary']),
                        'num' => $gigaGenieUnreadCount,
                        'children' => [],
                    ],
                    [
                        'id' => 'menu-communication_child_my_messages',
                        'name' => __('我的消息', [], 'catalog/seller_menu'),
                        'href' => $this->url->link('customerpartner/message_center/my_message/buyers', '', true),
                        'num' => $myMessageCount,
                        'children' => [],
                    ],
                    [
                        'id' => 'menu-communication_child_customer_service',
                        'name' => __('客户服务', [], 'catalog/seller_menu'),
                        'href' => $this->url->link('account/ticket/lists', '', true),
                        'num' => $ticket_count,
                        'children' => [],
                    ],

//                        [
//                            'id' => 'menu-communication_child_common_words',
//                            'name' => 'Suggestions on Specific Scenario Corpus',
//                            'href' => url(['customerpartner/message_center/extension/words']),
//                            'name_info' => 'Suggestions on Specific Scenario Corpus',
//                            'children' => [],
//                        ],
                    [
                        'id' => 'menu-communication_child_set_language',
                        'name' => __('沟通语言设置', [], 'catalog/seller_menu'),
                        'href' => url(['customerpartner/message_center/extension/language']),
                        'children' => [],
                    ],

                ]
            ];
        }
    }

    /**
     * description:
     * @return void
     */
    private function productManagementList($menuArr)
    {
        $product = array();
        if (in_array('productlist', $menuArr)) {
            $product[] = array(
                'id' => 'menu-product-child-list',
                'name' => __('产品列表', [], 'catalog/seller_menu'),
                'href' => $this->url->link('customerpartner/product/lists/index', '', true),
                'children' => array()
            );
        }

        if (in_array('addproduct', $menuArr)) {
            $product[] = array(
                'id' => 'menu-product-child-add',
                'name' => __('添加产品', [], 'catalog/seller_menu'),
                'href' => $this->url->link('pro/product'),
                'children' => array()
            );
        }

        // 6446 隐藏seller自己维护产品属性
//            if (in_array('product_options', $menuArr)) {
//                $product[] = array(
//                    'id' => 'menu-product-child-options',
//                    'name' => $this->language->get('text_product_options'),
//                    'href' => $this->url->link('account/customerpartner/productoptions'),
//                    'children' => array()
//                );
//            }

        if (in_array('product_groups', $menuArr)) {
            $product[] = array(
                'id' => 'menu-product-child-group',
                'name' => __('产品分组', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/productgroup'),
                'children' => array()
            );
        }

        if (in_array('product_freight_inquiry', $menuArr)) {
            $product[] = array(
                'id' => 'menu-product-child-freight-inquiry',
                'name' => __('物流费查询', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/productfreight'),
                'children' => array()
            );
        }
        if (in_array('file_manage', $menuArr)) {
            $product[] = array(
                'id' => 'menu-product-child-file-manage',
                'name' => __('文件管理', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/file_manage/index'),
                'children' => array()
            );
        }

        if (in_array(customer()->getCountryId(), Country::getEuropeCountries())) {
            //  欧洲Seller设置产品价格比例
            $product[] = array(
                'id' => 'menu-product-child-freight-inquiry',
                'name' => __('价格比率设置', [], 'catalog/seller_menu'),
                'href' => url(['customerpartner/product/seller_product_ratio']),
                'children' => array()
            );
        }

        //原一级菜单的 [产品管理] 改成 [上架库存管理]
        if (in_array('product_manage', $menuArr)) {
            $href = $this->url->link('account/customerpartner/product_manage');
            //非美国
            if ($this->customer->getCountryId() == AMERICAN_COUNTRY_ID) {
                $href = $this->url->link('customerpartner/warehouse/inventory');
            }
            $product[] = array(
                'id' => 'menu-product-child-freight-inquiry',
                'name' => __('上架库存管理', [], 'catalog/seller_menu'),
                'href' => $href,
                'children' => []
            );


        }

        if ($product) {
            $this->menusList[] = array(
                'id' => 'menu-product',
                'icon' => 'icon-a-ProductDetails-01',
                'name' => __('产品管理', [], 'catalog/seller_menu'),
                'href' => '',
                'children' => $product
            );
        }
        //end region Product Management

    }


    /**
     * description:Incoming Shipment Management
     * @return void
     */
    private function warehouseReceiptList($menuArr)
    {
        if (in_array('warehouse_receipt', $menuArr)) {
            // 美国 & 非Inner accounting & 非gigaonsite
            if ($this->customer->getCountryId() == AMERICAN_COUNTRY_ID
                && ($this->customer->getAccountType() == CustomerAccountingType::OUTER || in_array($this->customer->getId(), explode(',', configDB('warehouse_receipt_test_account'))))) {
                $warehouseReceiptChild[] = [
                    'id' => 'menu-warehouse-receipted',
                    'name' => __('入库管理', [], 'catalog/seller_menu'),
                    'href' => $this->url->link('customerpartner/warehouse/receipt'),
                    'children' => [],
                ];
                $warehouseReceiptChild[] = [
                    'id' => 'menu-warehouse-receipt-child-product-label-print',
                    'name' => __('Label打印', [], 'catalog/seller_menu'),
                    'href' => $this->url->link('account/print_product_labels'),
                    'children' => [],
                ];

                $this->menusList[] = array(
                    'id' => 'menu-warehouse-receipt',
                    'icon' => 'icon-rukudanguanli-01',
                    'name' => __('入库管理', [], 'catalog/seller_menu'),
                    'href' => '',
                    'children' => $warehouseReceiptChild
                );
            }
        }

    }


    /**
     * description:Inventory Management
     * @return void
     */
    private function warehouseInventoryManagementList($menuArr)
    {
        if (in_array('warehouse_receipt', $menuArr)) {
            // 美国的账号才有
            if ($this->customer->getCountryId() == AMERICAN_COUNTRY_ID
                && $this->customer->getAccountType() == CustomerAccountingType::OUTER || in_array($this->customer->getId(), explode(',', configDB('warehouse_receipt_test_account')))) {
                $inventoryManChild[] = [
                    'id' => 'menu-warehouse-receipted',
                    'name' => __('批次库存查询', [], 'catalog/seller_menu'),
                    'href' => $this->url->link('customerpartner/warehouse/batch_inventory'),
                    'children' => [],
                ];
                $inventoryManChild[] = [
                    'id' => 'menu-warehouse-receipted',
                    'name' => __('入出库查询', [], 'catalog/seller_menu'),
                    'href' => $this->url->link('customerpartner/warehouse/inout_inventory'),
                    'children' => [],
                ];
                $inventoryManChild[] = [
                    'id' => 'menu-warehouse-receipted',
                    'name' => __('盘亏管理', [], 'catalog/seller_menu'),
                    'href' => $this->url->link('customerpartner/warehouse/inventory_loss'),
                    'children' => [],
                ];

                $this->menusList[] = array(
                    'id' => 'menu-warehouse_inventory_management',
                    'icon' => 'icon-kucunguanli-01',
                    'name' => __('库存管理', [], 'catalog/seller_menu'),
                    'href' => '',
                    'children' => $inventoryManChild
                );
            }
        }
    }


    /**
     * description:Purchase Order Management
     * @return void
     */
    private function orderHistoryList($menuArr)
    {
        if (in_array('orderhistory', $menuArr)) {
            $this->menusList[] = array(
                'id' => 'menu-orderhistory',
                'icon' => 'icon-dingdanorder',
                'name' => __('采购单管理', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/orderlist', '', true),
                'children' => array()
            );
        }

    }


    /**
     * description:
     * @return void
     */
    private function salesOrderManagementList($menuArr)
    {
        $excludeAccountingType = [
            CustomerAccountingType::INNER,
            CustomerAccountingType::SERVICE_SHOP,
            CustomerAccountingType::GIGA_ONSIDE,
            CustomerAccountingType::AMERICA_NATIVE,
        ];
        $countryGroup = [
            CountryEnum::AMERICA,
            CountryEnum::ENGLAND,
            CountryEnum::GERMANY,
        ];
        if (
            (
                (
                    in_array(customer()->getCountryId(), $countryGroup)
                    && !in_array(customer()->getAccountType(), $excludeAccountingType)
                )
                && customer()->getEmail() != 'joybuy-us@gigacloudlogistics.com'
            )
            || customer()->getId() == 2820
        ) {
            $sales_upload_menu = [];
            $notSupportSelfDeliveryExists = CustomerExts::query()->where([
                'customer_id'=> customer()->getId(),
                'not_support_self_delivery'=> YesNoEnum::YES,
            ])->exists();
            if($notSupportSelfDeliveryExists){
                $menuArr = [];
            }

            if (in_array('sales_order_upload', $menuArr)) {
                $sales_upload_menu[] = [
                    'name' => __('销售订单上传', [], 'catalog/seller_menu'),
                    'href' => $this->url->link('account/customerpartner/sales_order_management'),

                    'children' => array()
                ];
            }

            if (in_array('sales_order_list', $menuArr)) {
                $sales_upload_menu[] = [
                    'id' => 'menu-salesordersmanagement-child-list',
                    'name' => __('销售订单列表', [], 'catalog/seller_menu'),
                    'href' => $this->url->link('account/customerpartner/sales_order_list'),
                    'children' => array()
                ];
            }

            if (!empty($sales_upload_menu)) {
                $this->menusList[] = [
                    'id' => 'menu-salesordersmanagement',
                    'icon' => 'icon-xiaoshoudingdanguanli-01',
                    'name' => __('销售单管理', [], 'catalog/seller_menu'),
                    'href' => '',
                    'children' => $sales_upload_menu,
                ];
            }
        }
    }

    /**
     * 映射管理
     * @param $menuArr
     */
    private function mappingManagementList($menuArr)
    {
        // 内部seller不可见
//        if ($this->customer->isInnerAccount()) {
//            return;
//        }
        $mappingManagement = [];
        if (in_array('sales_order_upload', $menuArr)) {
            $mappingManagement[] = [
                'id' => 'menu-salesordersmanagement-child-upload',
                'name' => __('外部平台映射', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/mapping_management'),
                'children' => array()
            ];
        }
        if (!empty($mappingManagement)) {
            $this->menusList[] = [
                'id' => 'menu-mappingmanagement',
                'icon' => 'icon-yingshe',
                'name' => __('映射管理', [], 'catalog/seller_menu'),
                'href' => '',
                'children' => $mappingManagement,
            ];
        }
    }


    /**
     * description:
     * @return void
     */
    private function marketBusinessList($menuArr)
    {
        $marketBusiness = [];
        /**
         * 德 日 英 美 开通议价
         */
        $quoteEnable = false;
        if (!empty($this->customer->getCountryId()) && in_array($this->customer->getCountryId(), QUOTE_ENABLE_COUNTRY)) {
            $quoteEnable = true;
        }

        //marketing business 更名为 Product Bidding
        $this->load->model('account/product_quotes/wk_product_quotes');
        $this->load->model('account/product_quotes/margin_contract');
        $this->load->model('account/product_quotes/rebates_contract');
        $this->load->model('futures/agreement');
        $unread_num = $this->model_account_product_quotes_wk_product_quotes->quoteAppliedCount($this->customer->getId());
        $unread_num += app(AgreementRepository::class)->sellerMarginBidsHotspotCount(customer()->getId());
        $unread_num += $this->model_account_product_quotes_rebates_contract->rebatesAppliedCount($this->customer->getId());
        $unread_num += $this->model_futures_agreement->sellerAgreementTotal($this->customer->getId());

        // 议价 lester.you
        if (in_array('mp_pq', $menuArr)) {
            if ($quoteEnable) {
                $marketBusiness[] = [
                    'id' => 'menu-market-business-child-manage-quote-request',
                    'name' => __('复杂交易列表', [], 'catalog/seller_menu'),
                    'num' => $unread_num,
                    'href' => $this->url->link('account/customerpartner/wk_quotes_admin', '', true),
                    'children' => array()
                ];
                $marketBusiness[] = [
                    'id' => 'menu-market-business-child-manage-product-for-quote',
                    'name' => __('阶梯价格设置', [], 'catalog/seller_menu'),
                    'href' => $this->url->link('customerpartner/spot_price/index', '', true),
                    'children' => array()
                ];

            } else {
                $marketBusiness[] = [
                    'id' => 'menu-market-business-child-manage-quote-request',
                    'name' => __('复杂交易列表', [], 'catalog/seller_menu'),
                    'href' => $this->url->link('account/customerpartner/wk_quotes_admin', '', true),
                    'children' => array()
                ];
            }
        }
        //返点  by chenyang 2019/09/13
        if (in_array('mp_rebates', $menuArr)) {
            $marketBusiness[] = array(
                'id' => 'menu-market-business-child-rebate',
                'name' => __('返点设置', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/rebates', '', true),
                'children' => array()
            );
        }
        if (in_array('mp_margin', $menuArr)) {
            $marketBusiness[] = array(
                'id' => 'menu-market-business-child-margin',
                'name' => __('现货保证金设置', [], 'catalog/seller_menu'),
                'href' => $this->url->link('customerpartner/margin/contract', '', true),
                'children' => array()
            );
        }

        // 促销业务标签配置
//        if (in_array('mp_market_business', $menuArr)) {
//            $marketBusiness[] = array(
//                'id' => 'menu-market-business-child-setting',
//                'name' => __('Marketing', [], 'catalog/seller_menu'),
//                'href' => $this->url->link('account/customerpartner/market_business', '', true),
//                'children' => array()
//            );
//        }


        // add by wangjinxin 期货
        if (in_array('mp_futures', $menuArr)) {
            $marketBusiness[] = array(
                'id' => 'menu-market-business-child-futures',
                'name' => __('期货保证金设置', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/future/contract/list'),
                'children' => array()
            );
        }
        if ($marketBusiness) {
            $this->menusList[] = array(
                'id' => 'menu-market-business',
                'icon' => 'icon-jiaoyi',
                'name' => __('复杂交易管理', [], 'catalog/seller_menu'),
                'num' => $unread_num,
                'href' => '',
                'children' => $marketBusiness
            );
        }

    }

    private function notificationList($menuArr)
    {
        if (in_array('notification', $menuArr)) {
            // 加入通知条数
            $this->load->model('account/notification');
            $modelAccountNotification = $this->model_account_notification;
            $notification_count = $modelAccountNotification->getTotalSellerActivityUnread([2])
                + $modelAccountNotification->getTotalSellerActivityUnread([5])
                + $modelAccountNotification->getRmaActivityTotal(false)
                + $modelAccountNotification->getProductStockTotalUnread()
                + $modelAccountNotification->getReviewTotalUnread();

            $this->menusList[] = [
                'id' => 'menu-notification',
                'icon' => 'fa-bell-o',
                'name' => $this->language->get('text_notification'),
                'href' => $this->url->link('account/customerpartner/notification'),
                'children' => [],
                'num' => $notification_count,
            ];
        }
    }

    private function delicacyPriceSetting($menuArr)
    {
        $delicacyManagement = [];
        if (in_array('delicacy_price_setting', $menuArr)) {
            $delicacyManagement[] = array(
                'id' => 'menu-delicacy-management-child-setting-price',
                'name' => __('专享价格', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/delicacymanagement', '', true),
                'children' => array()
            );
        }
        if (in_array('invisible_setting_product', $menuArr)) {
            $delicacyManagement[] = array(
                'id' => 'menu-delicacy-management-child-setting-product',
                'name' => __('产品可见性设置', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/delicacymanagementgroup', '', true),
                'children' => array()
            );
        }
        if ($delicacyManagement) {
            $this->menusList[] = array(
                'id' => 'menu-delicacy-management',
                'icon' => 'icon-jingxihua-01',
                'name' => __('定制管理', [], 'catalog/seller_menu'),
                'href' => '',
                'children' => $delicacyManagement
            );
        }
        //endregion Delicacy Management
    }


    private function rmaManagementList($menuArr)
    {
        if (in_array('rma_management', $menuArr)) {
            $this->load->model('customerpartner/rma_management');
            $unread_num = $this->model_customerpartner_rma_management->getNoHandleRmaCount($this->customer->getId());

            $this->menusList[] = array(
                'id' => 'menu-rma-management',
                'icon' => 'icon-a-RMAManagement-01',
                'name' => __('RMA管理', [], 'catalog/seller_menu'),
                'num' => $unread_num,
                'href' => $this->url->link('account/customerpartner/rma_management', '', true),
                'children' => array()
            );
        }
    }


    private function promotionList($menuArr)
    {
        //促销活动start
        $num_red_dot = 0;
        $lowStockNum = 0;
        $marketingCampaign = [];
        if (in_array('promotion', $menuArr)) {
            $this->load->model('customerpartner/marketing_campaign/history');
            //应与ControllerCustomerpartnerMarketingCampaignIndex activity同步修改
            $num_red_dot = $this->model_customerpartner_marketing_campaign_history->getNoticeNumber($this->customer->getId());
            $marketingCampaign[] = array(
                'id' => 'menu-promotion-management-child-info',
                'name' => __('平台活动', [], 'catalog/seller_menu'),
                'num' => $num_red_dot,
                'href' => $this->url->link('customerpartner/marketing_campaign/index/activity', '', true),
                'children' => array()
            );
            // 正在进行中的活动才有低库存概念
            $lowStockNum = app(MarketingTimeLimitDiscountRepository::class)->getEffectiveTimeLimitLowQtyNumber(customer()->getId());

            $marketingCampaign[] = array(
                'id' => 'menu-promotion-management-child-history',
                'name' => __('店铺活动', [], 'catalog/seller_menu'),
                'num' => $lowStockNum,
                'name_info' => __('店铺活动', [], 'catalog/seller_menu'),
                'href' => $this->url->link('customerpartner/marketing_campaign/discount_tab/index', '', true),
                'children' => array()
            );
            }

        if ($marketingCampaign) {
            $this->menusList[] = array(
                'id' => 'menu-promotion-management',
                'icon' => 'icon-Promotions-01',
                'name' => __('促销活动', [], 'catalog/seller_menu'),
                'num' => $num_red_dot + $lowStockNum,
                'href' => '',
                'children' => $marketingCampaign
            );
        }
        //促销活动end
    }

    private function buyersList($menuArr)
    {
        $buyerManagement = [];
        // add by lilei Seller管理Buyer界面
        if (in_array('buyers', $menuArr)) {
            $buyerManagement[] = array(
                'id' => 'menu-buyer-management-child-buyer',
                'name' => __('买家列表', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/buyer_management/list', '', true),
                'children' => array()
            );
        }
        if (in_array('buyer_groups', $menuArr)) {
            $buyerManagement[] = array(
                'id' => 'menu-buyer-management-child-group',
                'name' => __('买家分组', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/buyergroup', '', true),
                'children' => array()
            );
        }

        // 采销协议管理
        $tripartiteAgreement = app(TripartiteAgreementRepository::class)->getPendingAgreementCountByOperatorId(customer()->getId(), true);
        $buyerManagement[] = array(
            'id' => 'menu-buyer-management-child-tripartite-agreement',
            'name' => __('采销协议管理', [], 'catalog/seller_menu'),
            'num' => $tripartiteAgreement,
            'href' => $this->url->to('customerpartner/tripartite_agreement'),
            'children' => array()
        );

        if ($buyerManagement) {
            $this->menusList[] = array(
                'id' => 'menu-buyer-management',
                'icon' => 'icon-BuyerManagement',
                'num' => $tripartiteAgreement,
                'name' => __('买家管理', [], 'catalog/seller_menu'),
                'href' => '',
                'children' => $buyerManagement
            );
        }
        //endregion Buyer Management
    }


    private function accountAuthorization($menuArr)
    {
        if (in_array('account_authorization', $menuArr)) {
            $this->menusList[] = array(
                'id' => 'menu-account-authorization',
                'icon' => 'icon-shouquan',
                'name' => __('账户授权', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/account_authorization', '', true),
                'children' => array()
            );
        }
    }

    /**
     * description:Billing Management
     * @return void
     */
    private function sellerBillList()
    {
        if (in_array('seller_bill_total', $this->config->get('marketplace_allowed_account_menu'))) {
            $seller_bill[] = array(
                'id' => 'menu-seller-bill-child-account-manage',
                'name' => __('收款账户管理', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/seller_bill/account_manage', '', true),
                'children' => array()
            );
            $seller_bill[] = array(
                'id' => 'menu-seller-bill-child-list',
                'name' => __('结算列表', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/seller_bill/bill_list', '', true),
                'children' => array()
            );
            /*$seller_bill[] = array(
                'id' => 'menu-seller-bill-child-list',
                'name' => $this->language->get('seller_bill_total'),
                'href' => $this->url->link('account/seller_bill/bill', '', true),
                'children' => array()
            );*/
            $seller_bill[] = array(
                'id' => 'menu-seller-bill-child-list',
                'name' => __('结算明细', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/seller_bill/bill_detail', '', true),
                'children' => array()
            );
        }

        // 美国外部用户显示seller账单,或者指定账号
        if ( !empty($seller_bill)
            && $this->customer->isUSA()
            && ($this->customer->isOuterAccount() || in_array($this->customer->getId(), SHOW_BILLING_MANAGEMENT_SELLER) || $this->customer->getAccountType() == 5)
        ) {
            $this->menusList[] = array(
                'id' => 'menu-seller-bill',
                'icon' => 'icon-jiesuanguanli-01',
                'name' => __('结算管理', [], 'catalog/seller_menu'),
                'href' => '',
                'children' => $seller_bill
            );
        }

    }

    private function bandList($menuArr)
    {
        if (in_array('brand', $menuArr)) {
            $this->menusList[] = array(
                'id' => 'menu-brand',
                'icon' => 'icon-a-BrandCenter-01',
                'name' => __('品牌中心', [], 'catalog/seller_menu'),
                'href' => $this->url->link('account/customerpartner/manufacturer', '', true),
                'children' => array()
            );
        }
    }

    private function productReviewList($menuArr)
    {
        if (in_array('product_review', $menuArr)) {
            $this->menusList[] = array(
                'id' => 'menu-product-review',
                'icon' => 'fa-comments-o',
                'name' => 'Product Reviews',
                'href' => $this->url->link('account/customerpartner/product_review', '', true),
                'children' => array()
            );
        }
    }

    private function informationList($menuArr)
    {
        if (in_array('information', $menuArr)) {
            $this->menusList[] = array(
                'id' => 'menu-information',
                'icon' => 'fa-info-circle',
                'name' => $this->language->get('text_information'),
                'href' => $this->url->link('account/customerpartner/information', '', true),
                'children' => array()
            );
        }
    }

    private function reviewList($menuArr)
    {
        if (in_array('review', $menuArr)) {
            $this->menusList[] = array(
                'id' => 'menu-review',
                'icon' => 'fa-comments-o',
                'name' => $this->language->get('text_review'),
                'href' => $this->url->link('account/customerpartner/review', '', true),
                'children' => array()
            );
        }
    }

    private function manageShippingList($menuArr)
    {
        if (in_array('manageshipping', $menuArr)) {
            $this->menusList[] = array(
                'id' => 'menu-manageshipping',
                'icon' => 'fa-truck',
                'name' => $this->language->get('text_manageshipping'),
                'href' => $this->url->link('account/customerpartner/add_shipping_mod', '', true),
                'children' => array()
            );
        }
    }

    private function downloadsList($menuArr)
    {
        if (in_array('downloads', $menuArr)) {
            $this->menusList[] = array(
                'id' => 'menu-downloads',
                'icon' => 'fa-download',
                'name' => $this->language->get('text_downloads'),
                'href' => $this->url->link('account/customerpartner/download', '', true),
                'children' => array()
            );
        }
    }

    /**
     * description:
     * @return void
     */
    private function transactionList($menuArr)
    {
        if (in_array('transaction', $menuArr)) {
            $this->menusList[] = array(
                'id' => 'menu-transaction',
                'icon' => 'fa-credit-card',
                'name' => $this->language->get('text_transaction'),
                'href' => $this->url->link('account/customerpartner/transaction', '', true),
                'children' => array()
            );
        }
    }

    /**
     * @param array $menus
     * @return array
     */
    private function filterNotAuthorizedMenuIds(array $menus)
    {
        $sellerAuthorizedMenuIds = session('seller_authorized_menu_ids', []);
        if (empty($sellerAuthorizedMenuIds)) {
            return $menus;
        }

        $filterMenus = [];
        foreach ($menus as $menu) {
            $childMenus = [];
            if (!empty($menu['children']) && is_array($menu['children'])) {
                foreach ($menu['children'] as $childMenu) {
                    if (isset($childMenu['id']) && in_array($childMenu['id'], $sellerAuthorizedMenuIds)) {
                        $childMenus[] = $childMenu;
                        continue;
                    }
                }
            }

            if (!empty($childMenus) || in_array($menu['id'], $sellerAuthorizedMenuIds)) {
                $menu['children'] = $childMenus;
                $filterMenus[] = $menu;
            }
        }

        return $filterMenus;
    }

    /**
     * 校验store模板 是否需要显示红色圆点通知
     * @param int $customerId
     * @param int $sellerAuditType 10-seller首页模板 20-seller商店介绍模板
     * @return bool
     */
    private function checkStoreAuditNoticeNeedShow(int $customerId, int $sellerAuditType): bool
    {
        $latestAuditInfo = SellerStoreAudit::query()
            ->where(['type' => $sellerAuditType, 'seller_id' => $customerId])
            ->orderByDesc('id')
            ->first();
        if (!$latestAuditInfo) {
            return 0;
        }
        if ($latestAuditInfo->status == SellerStoreAuditStatus::AUDIT_REFUSE) {
            return 1;
        }
        return 0;
    }

    /**
     * description:
     * @return void
     * @deprecated
     */
    private function productManageList($menuArr)
    {
        if (in_array('product_manage', $menuArr)) {
            $href = $this->url->link('account/customerpartner/product_manage');
            //非美国
            if ($this->customer->getCountryId() == AMERICAN_COUNTRY_ID) {
                $href = $this->url->link('customerpartner/warehouse/inventory');
            }
            $this->menusList[] = array(
                'id' => 'menu-product-manage',
                'icon' => 'fa-briefcase',
                'name' => $this->language->get('text_product_manage'),
                'href' => $href,
                'children' => array()
            );
        }
    }
}
