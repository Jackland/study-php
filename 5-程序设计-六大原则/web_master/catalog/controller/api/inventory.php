<?php

/**
 * @property ModelApiInventoryManagement $model_api_inventory_management
 */
class ControllerApiInventory extends ControllerApiBase {

    /**
     * @var ModelApiInventoryManagement $model_inventory
     */
    private $model_inventory;
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('api/inventory_management');
        $this->model_inventory = $this->model_api_inventory_management;
    }

    public function getUnBindStock()
    {
        $buyerIdStr = $this->request->post('buyer_id',null);
        $buyerIdArr = explode(',',$buyerIdStr);
        $unBindStock = $this->model_inventory->getProductCostMap($buyerIdArr);
        return $this->response->json(json_encode($unBindStock));
    }
}
