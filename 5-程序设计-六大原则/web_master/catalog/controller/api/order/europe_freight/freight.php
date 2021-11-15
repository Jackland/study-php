<?php

use App\Enums\Country\Country;
use App\Logging\Logger;
use App\Models\SalesOrder\CustomerSalesOrderLine;

/**
 * Class ControllerApiOrderEuropeFreightFreight
 * @property ModelExtensionModuleEuropeFreight  $model_extension_module_europe_freight;

 */
class ControllerApiOrderEuropeFreightFreight extends ControllerApiBase
{
    private $europeFreight;
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        load()->model('extension/module/europe_freight');
    }

    public function index()
    {
        set_time_limit(0);
        $validator = $this->request->validate([
            'data' => 'required|array',
            'data.*.product_id' => 'required|integer',
            'data.*.line_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            Logger::salesOrder([__CLASS__, __FUNCTION__, '接口校验失败', $validator->errors()], 'warning');
            return $this->jsonFailed($validator->errors()->first());
        }
        $data = Request()->bodyBag()->get('data');
        $ret = [];
        try{
            // 为了方便java需要拼接参数
            foreach($data as &$items){
                $items['from'] = '';
                $items['to'] = '';
                $items['zip_code'] = '';
                $items['qty'] = '';
                $items['delivery_to_fba'] = 0;
                $info = CustomerSalesOrderLine::query()->alias('l')
                        ->leftJoinRelations(['customerSalesOrder as o'])
                        ->leftJoin('oc_customer as c','c.customer_id','o.buyer_id')
                        ->where('l.id',$items['line_id'])
                        ->select(['c.country_id','o.ship_country','o.ship_zip_code','l.qty','o.id','o.delivery_to_fba'])
                        ->get()
                        ->first();
                if($info){
                    if(in_array($info->country_id,EUROPE_COUNTRY_ID)){
                        $items['from'] = $info->country_id == Country::BRITAIN ? 'GBR':'DEU';
                    }
                    $items['to'] = $info->ship_country;
                    $items['zip_code'] =  $info->ship_zip_code;
                    $items['qty'] =  $info->qty;
                    $items['delivery_to_fba'] =  $info->delivery_to_fba;
                }

            }
            $ret = $this->model_extension_module_europe_freight->getFreight($data,true);
        } catch (Throwable $e) {
            Logger::salesOrder([__CLASS__, __FUNCTION__, '接口失败', $e->getMessage()], 'error');
            return $this->jsonFailed('接口调用失败');
        }

        return $this->jsonSuccess(['data' => $ret]);
    }
}
