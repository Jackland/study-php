<?php

/**
 * Class ModelExtensionModuleShipmentTime
 * Created by IntelliJ IDEA.
 * User: Administrator
 * Date: 2019/4/23
 * Time: 15:14
 */
class ModelExtensionModuleShipmentTime extends Model
{
    public function getShipmentTime($countryId) {
        return $this->orm::table(DB_PREFIX . 'shipment_time')->where('country_id',$countryId)->first();
    }
}