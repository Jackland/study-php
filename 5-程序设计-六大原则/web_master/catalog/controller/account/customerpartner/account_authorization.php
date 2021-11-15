<?php
/**
 * Create by PHPSTORM
 * User: yaopengfei
 * Date: 2020/7/7
 * Time: 下午3:25
 */

/**
 * @property ModelAccountCustomerpartnerAccountAuthorization $model_account_customerpartner_account_authorization
 * @property ModelAccountCustomerpartnerColumnLeft $model_account_customerpartner_column_left
 *
 * Class ControllerAccountCustomerpartnerAccountAuthorizationIndex
 */
class ControllerAccountCustomerpartnerAccountAuthorization extends Controller
{
    const TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_PENDING = 1;
    const TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_APPROVED = 2;
    const TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_REJECTED = 3;
    const TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_REVOKED = 4;

    private $customer_id;

    private $data = [];

    /**
     * 固定授权的菜单
     * @var array[]
     */
    static $fixedPermissionMenus = [
        [
            'id' => 'seller_central',
            'text' => 'Seller Central',
            'state' => [
                'checked' => true,
                'disabled' => true,
            ]
        ],
        [
            'id' => 'stock_in_management',
            'text' => 'Stock-In Management',
            'state' => [
                'checked' => true,
                'disabled' => true,
            ]
        ],
        [
            'id' => 'print_product_labels',
            'text' => 'Product Label Print',
            'state' => [
                'checked' => true,
                'disabled' => true,
            ]
        ],
        [
            'id' => 'inventory_management',
            'text' => 'Inventory Management',
            'state' => [
                'checked' => true,
                'disabled' => true,
            ]
        ],
    ];

    /**
     * 隐藏的菜单id
     * @var array
     */
    static $hidePermissionMenuIds = ['menu-account-authorization'];

    /**
     * ControllerAccountCustomerpartnerAccountAuthorizationIndex constructor.
     * @param $registry
     * @throws ReflectionException
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        if (empty($this->customer_id) || !$this->customer->isPartner()) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->language('account/customerpartner/account_authorization');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('account/customerpartner/account_authorization');
        $this->load->model('account/customerpartner/column_left');
    }

    /**
     * sellers的授权账户经理列表页面
     * @throws Exception
     */
    public function index()
    {
        $this->initPage();

        $authorizes = $this->model_account_customerpartner_account_authorization->authorizesBySellerId($this->customer_id);

        $statusNameMap = [
            self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_PENDING => 'Pending',
            self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_APPROVED => 'Approved',
            self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_REJECTED => 'Rejected',
            self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_REVOKED => 'Revoked',
        ];

        $isCanAddNew = true;
        $index = 1;
        foreach ($authorizes as &$authorize) {
            if (in_array($authorize['status'], [self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_APPROVED, self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_PENDING])) {
                $isCanAddNew = false;
            }
            $authorize['index'] = $index;
            $authorize['account_manager_name'] = $authorize['firstname'] . $authorize['lastname'];
            $authorize['status_name'] = $statusNameMap[$authorize['status']];
            $authorize['edit_link'] = $this->url->link('account/customerpartner/account_authorization/permission', 'authorize_id=' . $authorize['id'], true);
            $index++;
        }

        $this->data['is_can_add_new'] = $isCanAddNew;
        $this->data['add_new_link'] = $this->url->link('account/customerpartner/account_authorization/permission');
        $this->data['authorizes'] = $authorizes;
        $this->data['revoke_link'] = $this->url->link('account/customerpartner/account_authorization/revoke');
        $this->data['remove_link'] = $this->url->link('account/customerpartner/account_authorization/remove');

        $this->response->setOutput($this->load->view('account/customerpartner/account_authorization/list', $this->data));
    }

    /**
     * 新增或编辑授权页面
     * @throws Exception
     */
    public function permission()
    {
        $this->initPage();

        $authorizeId = request('authorize_id', '');
        if (empty($authorizeId)) {
            $this->data['operate_text'] = $this->language->get('text_add_new');
            $this->data['authorize_id'] = 0;
            $this->data['operate_button_text'] = $this->language->get('text_send_invitation');
            $this->data['operate_button_link'] = $this->url->link('account/customerpartner/account_authorization/add');

            $accountManagerInfo = $this->model_account_customerpartner_account_authorization->accountManagerBySellerId($this->customer_id);
            $this->data['account_manager_id'] = $accountManagerInfo ? $accountManagerInfo['customer_id'] : '';
            $this->data['account_manager_name'] = $accountManagerInfo ? $accountManagerInfo['firstname'] . $accountManagerInfo['lastname'] : '';

            $authorizedPermissions = [];

        } else {
            $this->data['operate_text'] = $this->language->get('text_edit');
            $this->data['authorize_id'] = $authorizeId;
            $this->data['operate_button_text'] = $this->language->get('text_save');
            $this->data['operate_button_link'] = $this->url->link('account/customerpartner/account_authorization/edit');

            $authorize = $this->model_account_customerpartner_account_authorization->authorizeById($authorizeId);
            if (empty($authorize) || $authorize['seller_id'] != $this->customer_id) {
                $this->response->redirect($this->url->link('account/customerpartner/account_authorization'));
            }
            $this->data['account_manager_id'] = $authorize['account_manager_id'];
            $this->data['account_manager_name'] = $authorize['firstname'] . $authorize['lastname'];

            $permissions = json_decode($authorize['permissions']);
            $authorizedPermissions = array_keys(array_column($permissions, 'is_auth', 'id'), true);
        }

        $this->data['breadcrumbs'][] = array(
            'text' => $this->data['operate_text'],
            'href' => $this->url->link('account/customerpartner/account_authorization/permission', '', true)
        );

        $sellerMenus = $this->model_account_customerpartner_column_left->menus();
        $this->data['menus'] = json_encode(array_merge(self::$fixedPermissionMenus, $this->markPermissionMenus($sellerMenus, $authorizedPermissions)));
        $this->data['operate_success_link'] = $this->url->link('account/customerpartner/account_authorization');

        $this->response->setOutput($this->load->view('account/customerpartner/account_authorization/permission', $this->data));
    }

    /**
     * 新增授权操作
     */
    public function add()
    {
        if (!request()->isMethod('POST')) {
            $this->response->returnjson(['code' => 0, 'msg' => $this->language->get('request method error')]);
        }

        $accountManagerInfo = $this->model_account_customerpartner_account_authorization->accountManagerBySellerId($this->customer_id);
        if (empty($accountManagerInfo)) {
            $this->response->returnjson(['code' => 0, 'msg' => $this->language->get('text_no_match_manger_notice')]);
        }

        $permissions = $this->request->post['permissions'];
        if (empty($permissions)) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Please check']);
        }

        if (empty(array_diff($permissions, array_column(self::$fixedPermissionMenus, 'id')))) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Please check']);
        }

        $authorize = $this->model_account_customerpartner_account_authorization->authorizeBySellerIdAndStatus($this->customer_id, [self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_APPROVED, self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_PENDING]);
        if ($authorize) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Not be add new']);
        }

        $sellerMenus = $this->model_account_customerpartner_column_left->menus();
        $formatPermissions = [];
        $this->transformStructMenus(array_merge(self::$fixedPermissionMenus, $this->markPermissionMenus($sellerMenus, $permissions)), $formatPermissions, '');

        $result = $this->model_account_customerpartner_account_authorization->insertAuthorize($this->customer_id, $accountManagerInfo['customer_id'], $formatPermissions);
        if (!$result) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Send Invitation Error']);
        }

        $this->response->returnjson(['code' => 1, 'msg' => 'Send Invitation Success']);
    }

    /**
     * 编辑授权操作
     */
    public function edit()
    {
        if (!request()->isMethod('POST')) {
            $this->response->returnjson(['code' => 0, 'msg' => $this->language->get('request method error')]);
        }

        $authorizeId = $this->request->post['authorize_id'];
        if (empty($authorizeId)) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Save Error']);
        }

        $authorize = $this->model_account_customerpartner_account_authorization->authorizeById($authorizeId);
        if (empty($authorize) || $authorize['seller_id'] != $this->customer_id) {
            $this->response->returnjson(['code' => 0, 'msg' => 'No Access, no permission']);
        }

        if (!in_array($authorize['status'], [self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_APPROVED, self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_PENDING,])) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Not be edit']);
        }

        $permissions = $this->request->post['permissions'];
        if (empty($permissions)) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Please check']);
        }

        if (empty(array_diff($permissions, array_column(self::$fixedPermissionMenus, 'id')))) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Please check']);
        }

        $sellerMenus = $this->model_account_customerpartner_column_left->menus();
        $formatPermissions = [];
        $this->transformStructMenus(array_merge(self::$fixedPermissionMenus,$this->markPermissionMenus($sellerMenus, $permissions)), $formatPermissions, '');

        $result = $this->model_account_customerpartner_account_authorization->updateAuthorize($authorizeId, [
            'permissions' => json_encode($formatPermissions),
        ]);
        if (!$result) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Save Error']);
        }

        $this->response->returnjson(['code' => 1, 'msg' => 'Save Success']);
    }

    /**
     * 取消授权操作
     */
    public function revoke()
    {
        if (!request()->isMethod('POST')) {
            $this->response->returnjson(['code' => 0, 'msg' => $this->language->get('request method error')]);
        }

        $authorizeId = $this->request->post['authorize_id'];
        if (empty($authorizeId)) {
            $this->response->returnjson(['code' => 0, 'msg' => 'revoke Error']);
        }

        $authorize = $this->model_account_customerpartner_account_authorization->authorizeById($authorizeId);
        if (empty($authorize) || $authorize['seller_id'] != $this->customer_id) {
            $this->response->returnjson(['code' => 0, 'msg' => 'No Access, no permission']);
        }

        if ($authorize['status'] != self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_APPROVED) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Not be revoke']);
        }

        $result = $this->model_account_customerpartner_account_authorization->updateAuthorize($authorizeId, [
            'status' => self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_REVOKED,
            'revoke_time' => date('Y-m-d H:i:s'),
        ]);

        if (!$result) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Revoke Error']);
        }

        $this->response->returnjson(['code' => 1, 'msg' => 'Revoke Success']);
    }

    /**
     * 移除授权操作
     */
    public function remove()
    {
        if (!request()->isMethod('POST')) {
            $this->response->returnjson(['code' => 0, 'msg' => $this->language->get('request method error')]);
        }

        $authorizeId = $this->request->post['authorize_id'];
        if (empty($authorizeId)) {
            $this->response->returnjson(['code' => 0, 'msg' => 'remove Error']);
        }

        $authorize = $this->model_account_customerpartner_account_authorization->authorizeById($authorizeId);
        if (empty($authorize) || $authorize['seller_id'] != $this->customer_id) {
            $this->response->returnjson(['code' => 0, 'msg' => 'No Access, no permission']);
        }

        if ($authorize['status'] == self::TABLE_OC_SELLER_ACCOUNT_MANAGER_AUTHORIZES_STATUS_APPROVED) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Not be remove']);
        }

        $result = $this->model_account_customerpartner_account_authorization->updateAuthorize($authorizeId, [
            'is_deleted' => 1,
        ]);

        if (!$result) {
            $this->response->returnjson(['code' => 0, 'msg' => 'Remove Error']);
        }

        $this->response->returnjson(['code' => 1, 'msg' => 'Remove Success']);
    }

    /**
     * 初始化授权页面
     * @throws ReflectionException
     */
    private function initPage()
    {
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/account_authorization', '', true)
            ],
        ];

        $data['separate_view'] = true;
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');

        $this->data = $data;
    }


    /**
     * 标记授权的栏目(转换为前端bootstrap-treeView数据格式)
     * {
     *   text: ' Product Information',
     *   state: {
     *       checked: true,
     *       disabled: false
     *       },
     *   nodes: [
     *       {
     *           text: 'Product List',
     *           state: {
     *               checked: true,
     *               disabled: false
     *          }
     *      }
     *     ]
     * }
     * @param array $menus
     * @param array $authorizedPermissions
     * @return array
     */
    private function markPermissionMenus($menus, $authorizedPermissions = [])
    {
        $formatMenus = [];
        foreach ($menus as $menu) {
            $menu['id'] = $menu['id'] ?? 0;
            if (in_array($menu['id'], self::$hidePermissionMenuIds)) {
                continue;
            }

            $formatMenu = [
                'id' => $menu['id'],
                'text' => $menu['name'],
            ];
            if (in_array($menu['id'], $authorizedPermissions)) {
                $formatMenu['state']['checked'] = true;
            }
            if (is_array($menu['children']) && !empty($menu['children'])) {
                $formatMenu['nodes'] = $this->markPermissionMenus($menu['children'], $authorizedPermissions);
            }
            $formatMenus[] = $formatMenu;
        }

        return $formatMenus;
    }

    /**
     * 将树型结构的栏目转换为线性结构
     * @param array $menus
     * @param array $data
     * @param string $parentId
     */
    private function transformStructMenus($menus = [], &$data = [], $parentId = '')
    {
        foreach ($menus as $menu) {
            $data[] = [
                'id' => $menu['id'],
                'name' => $menu['text'],
                'parent_id' => $parentId,
                'is_auth' => isset($menu['state']['checked']) && $menu['state']['checked'],
            ];
            if (isset($menu['nodes']) && is_array($menu['nodes']) && !empty($menu['nodes'])) {
                $this->transformStructMenus($menu['nodes'], $data, $menu['id']);
            }
        }
    }
}
