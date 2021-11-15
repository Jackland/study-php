<?php

namespace App\Components\RemoteApi\Yzcm;

use App\Components\RemoteApi\Exceptions\ApiResponseException;

class AirwallexApi extends BaseYzcmApi
{
    /**
     * @param $airwallexIdentifier
     * @return int
     */
    public function updateAirwallexBindInfo($airwallexIdentifier)
    {
        try {
            $this->api("/api/airwallex/getAirwallexId?airwallexIdentifier=$airwallexIdentifier", [], 'GET');
        } catch (ApiResponseException $e) {
            return false;
        }

        return true;
    }
}