<?php

namespace App\Enums\SalesOrder;

use Framework\Enum\BaseEnum;

class HomePickCarrierType extends BaseEnum
{

    const UPS_2ND = 'UPS 2ND';
    const FEDEX_GROUND = 'FEDEX GROUND';
    const FEDEX_HOME_DELIVERY = 'FEDEX HOME DELIVERY';
    const UPS_NEXT_DAY_AIR = 'UPS NEXT DAY AIR';
    const FEDEX_GROUND_HOME_DELIVERY = 'FEDEX GROUND/HOME DELIVERY';
    const FEDEX_EXPRESS = 'FEDEX EXPRESS';
    const UPS_GROUND = 'UPS GROUND';
    const UPS_2ND_NEXT_DAY_AIR = 'UPS 2ND/NEXT DAY AIR';
    const UPS = 'UPS';
    const ESTES = 'ESTES';
    const CEVA = 'CEVA';
    const RESI = 'RESI';
    const EFW = 'EFW';
    const DM_TRANSPORTATION = 'DM TRANSPORTATION';
    const ABF = 'ABF';
    const AMXL = 'AMXL';
    const OTHER = 'OTHER';
    const PACKING_SLIP = 'PACKING SLIP';

    // amazon
    const DEFAULT = 'DEFAULT';
    const FEDEX = 'FEDEX';
    const ARROW = 'ARROW';
    const UPS_SUREPOST_GRD_PARCEL = 'UPS Surepost GRD Parcel';

    //wayfair
    const ESTES_EXPRESS = 'Estes-Express';
    const ROADRUNNER_TRANSPORTATION_SERVICES = 'RoadRunner Transportation Services';
    const XPO_LOGISTICS = 'XPO Logistics';
    const ZENITH_FREIGHT_LINES = 'Zenith Freight Lines';
    const A_DUIE_PYLE = 'A. Duie Pyle';
    const ABF_TRUCKING = 'ABF Trucking';
    const AVERITT_EXPRESS = 'Averitt Express';
    const YRC = 'YRC';

    //europe wayfair
    const DHL_PARCEL_UK = 'DHL Parcel UK';
    const XDP = 'XDP';
    const UPS_UK = 'UPS - UK';
    const DPD = 'DPD';


    //walmart
    const FEDEX_2DAY = 'FedEx 2Day';
    const FEDEX_HOME_DELIVERY_NO_SDR = 'FedEx Home Delivery no SDR';
    const FEDEX_2DYA_S2S = 'FedEx 2Day - S2S';
    const FEDEX_GROUND_S2S = 'FedEx Ground - S2S';
    const FHD = 'FHD3.0 S2H XML67';
    const UPS_SECOND_DAY_AIR = 'UPS Second day Air';
    const UPS_GROUND_S2S = 'UPS Ground - S2S';
    const UPS_GROUND_DSV = 'UPS Ground - DSV';
    const UPS_SECOND_DAY_AIR_S2S = 'UPS Second Day Air - S2S';
    const UPS_NEXT_DAY_AIR_S2S = 'UPS Next Day Air - S2S';
    const ESTES_FORWARDING_WORLDWIDE = 'Estes Forwarding Worldwide';
    const ESTES_FORWARDING_WORLDWIDE_BASIC_DELIVERY = 'Estes Forwarding Worldwide Basic Delivery';
    const PILOT = 'Pilot Freight Basic Delivery';
    const SEKO_WORLDWIDE = 'Seko Worldwide';
    const YELLOW_FREIGHT_SYSTEM = 'Yellow Freight System';
    const YELLOW_FREIGHT_SYSTEM_S2S = 'Yellow Freight System - S2S';

    const USPS_PRIORITY_MAIL = 'USPS Priority Mail';
    const FEDEX_3DAY = 'FedEx 3 Day (Upgrade)';
    const STANDARD_FEDEX_2DAY = '(Standard) FedEx 2Day';
    const STORE_LABEL = 'Store Label';

    const LTL_BOL_CUT_TYPES = 33;
    const ALIAS_LOWES = ['LOWES',"LOWE’S",'LOWE‘S'];
    const ALIAS_HOME_DEPOT = 'homedepot';

    //美国上门取货other导单入口的 LTL packing slip 裁剪
    const US_PICK_UP_OTHER_LTL_PACKING_SLIP = 39;
    //美国上门取货other导单入口的 LTL 正常label 裁剪
    const US_PICK_UP_OTHER_LTL_LABEL = 38;
    const EUROPE_WAYFAIR_COMMERCIAL_INVOICE = 42;

    public static function getOtherLTLTypeViewItems(): array
    {
        return [
            self::ESTES,
            self::CEVA,
            self::RESI,
            self::EFW,
            self::DM_TRANSPORTATION,
            self::ABF,
            self::AMXL,
            self::OTHER,
        ];
    }

    public static function getOtherNormalTypeViewItems()
    {
        return [
            self::FEDEX_GROUND_HOME_DELIVERY ,
            self::FEDEX_EXPRESS,
            self::UPS_GROUND,
            self::UPS_2ND_NEXT_DAY_AIR ,
            self::UPS ,
            self::FEDEX,
        ];
    }

    public static function getOtherCutTypeViewItems()
    {
        return [
            self::FEDEX_GROUND_HOME_DELIVERY => 34,
            self::FEDEX_EXPRESS => 35,
            self::UPS_GROUND => 36,
            self::UPS_2ND_NEXT_DAY_AIR => 37,
            self::UPS => 36,
            self::ESTES => 38,
            self::CEVA => 38,
            self::RESI => 38,
            self::EFW => 38,
            self::DM_TRANSPORTATION => 38,
            self::ABF => 38,
            self::OTHER => 38,
            self::FEDEX=>34,
            self::AMXL=>38,
        ];
    }



    public static function getAmazonCutTypeViewItems()
    {
        return [
            self::DEFAULT,
            self::UPS,
            self::ARROW,
            self::ABF,
            self::CEVA,
            self::UPS_SUREPOST_GRD_PARCEL,
            self::FEDEX,

        ];
    }

    public static function getAmazonLTLTypeViewItems()
    {
        return [
            self::ABF,
            self::CEVA,
        ];
    }

    public static function getWayfairLTLTypeViewItems()
    {
        return [
            self::ESTES_EXPRESS,
            self::ROADRUNNER_TRANSPORTATION_SERVICES,
            self::XPO_LOGISTICS,
            self::ZENITH_FREIGHT_LINES,
            self::A_DUIE_PYLE,
            self::ABF_TRUCKING,
            self::AVERITT_EXPRESS,
            self::YRC,
        ];
    }

    public static function getWayfairCutTypeViewItems()
    {
        return [
            self::UPS,
            self::FEDEX_EXPRESS,
            self::ESTES_EXPRESS,
            self::FEDEX,
            self::ROADRUNNER_TRANSPORTATION_SERVICES,
            self::XPO_LOGISTICS,
            self::ZENITH_FREIGHT_LINES,
            self::A_DUIE_PYLE,
            self::ABF_TRUCKING,
            self::AVERITT_EXPRESS,
            self::YRC,
        ];
    }

    public static function getEuropeWayfairCutTypeViewItems()
    {
        return [
            self::DHL_PARCEL_UK,
            self::XDP,
            self::UPS_UK,
            self::DPD,
        ];
    }

    public static function getWalmartAllCutTypeViewItems()
    {
        return [
            self::FEDEX_GROUND => 18,
            self::FEDEX_2DAY => 25,
            self::FEDEX_HOME_DELIVERY => 26,
            self::FEDEX_HOME_DELIVERY_NO_SDR => 26,
            self::FEDEX_2DYA_S2S => 25,
            self::FEDEX_GROUND_S2S => 19,
            self::FHD => 26,
            self::UPS_GROUND => 19,
            self::UPS_SECOND_DAY_AIR => 27,
            self::UPS_GROUND_S2S => 27,
            self::UPS_GROUND_DSV => 19,
            self::UPS_SECOND_DAY_AIR_S2S => 27,
            self::UPS_NEXT_DAY_AIR_S2S => 28,
            self::UPS_NEXT_DAY_AIR => 28,
            self::ESTES_FORWARDING_WORLDWIDE_BASIC_DELIVERY => 20,
            self::PILOT => 21,
            self::SEKO_WORLDWIDE => 22,
            self::YELLOW_FREIGHT_SYSTEM => 23,
            self::USPS_PRIORITY_MAIL => 18,
            self::YELLOW_FREIGHT_SYSTEM_S2S => 23,
            self::FEDEX_3DAY => 25,
            self::STORE_LABEL => 24,
            self::STANDARD_FEDEX_2DAY => 25,
        ];
    }


    public static function getWalmartCutTypeViewItems()
    {
        return [
            self::FEDEX_GROUND,
            self::FEDEX_2DAY,
            self::FEDEX_HOME_DELIVERY,
            self::FEDEX_HOME_DELIVERY_NO_SDR,
            self::FEDEX_2DYA_S2S,
            self::FEDEX_GROUND_S2S,
            self::FHD,
            self::UPS_GROUND,
            self::UPS_SECOND_DAY_AIR,
            self::UPS_GROUND_S2S,
            self::UPS_GROUND_DSV,
            self::UPS_SECOND_DAY_AIR_S2S,
            self::UPS_NEXT_DAY_AIR_S2S,
            self::UPS_NEXT_DAY_AIR,
            self::ESTES_FORWARDING_WORLDWIDE_BASIC_DELIVERY,
            self::PILOT,
            self::SEKO_WORLDWIDE,
            self::YELLOW_FREIGHT_SYSTEM,
            self::YELLOW_FREIGHT_SYSTEM_S2S,
        ];
    }

    public static function getWalmartLTLTypeViewItems()
    {
        return [
            self::ESTES_FORWARDING_WORLDWIDE,
            self::ESTES_FORWARDING_WORLDWIDE_BASIC_DELIVERY,
            self::PILOT,
            self::SEKO_WORLDWIDE,
            self::YELLOW_FREIGHT_SYSTEM,
            self::YELLOW_FREIGHT_SYSTEM_S2S,
        ];
    }

    public static function getCarrierNameViewItems()
    {
        return [
            /*-------other-------*/
            self::FEDEX_GROUND_HOME_DELIVERY => self::FEDEX,
            self::FEDEX_EXPRESS => self::FEDEX,
            self::UPS_GROUND => self::UPS,
            self::UPS_2ND_NEXT_DAY_AIR => self::UPS,
            self::UPS => self::UPS,
            self::ESTES =>self::ESTES,
            self::CEVA => self::CEVA,
            self::RESI => self::RESI,
            self::EFW => self::EFW,
            self::DM_TRANSPORTATION => self::DM_TRANSPORTATION,
            self::ABF => self::ABF,
            self::OTHER => self::OTHER,
            self::FEDEX=>self::FEDEX,
            /*-------walmart-------*/
            self::FEDEX_GROUND => self::FEDEX,
            self::FEDEX_2DAY => self::FEDEX,
            self::FEDEX_HOME_DELIVERY => self::FEDEX,
            self::FEDEX_HOME_DELIVERY_NO_SDR => self::FEDEX,
            self::FEDEX_2DYA_S2S => self::FEDEX,
            self::FEDEX_GROUND_S2S => self::FEDEX,
            self::FHD => self::FHD,
            self::UPS_SECOND_DAY_AIR => self::UPS,
            self::UPS_GROUND_S2S => self::UPS,
            self::UPS_GROUND_DSV => self::UPS,
            self::UPS_SECOND_DAY_AIR_S2S => self::UPS,
            self::UPS_NEXT_DAY_AIR_S2S => self::UPS,
            self::UPS_NEXT_DAY_AIR => self::UPS,
            self::ESTES_FORWARDING_WORLDWIDE_BASIC_DELIVERY => self::ESTES_FORWARDING_WORLDWIDE_BASIC_DELIVERY,
            self::PILOT => self::PILOT,
            self::SEKO_WORLDWIDE => self::SEKO_WORLDWIDE,
            self::YELLOW_FREIGHT_SYSTEM => self::YELLOW_FREIGHT_SYSTEM,
            self::USPS_PRIORITY_MAIL => self::UPS,
            self::YELLOW_FREIGHT_SYSTEM_S2S => self::YELLOW_FREIGHT_SYSTEM_S2S,
            self::FEDEX_3DAY => self::FEDEX,
            self::STORE_LABEL => self::STORE_LABEL,
            self::STANDARD_FEDEX_2DAY => self::FEDEX,
            /*-------usa wayfair-------*/
            self::ESTES_EXPRESS => self::ESTES_EXPRESS,
            self::ROADRUNNER_TRANSPORTATION_SERVICES => self::ROADRUNNER_TRANSPORTATION_SERVICES,
            self::XPO_LOGISTICS => self::XPO_LOGISTICS,
            self::ZENITH_FREIGHT_LINES => self::ZENITH_FREIGHT_LINES,
            self::A_DUIE_PYLE => self::A_DUIE_PYLE,
            self::ABF_TRUCKING => self::ABF_TRUCKING,
            self::AVERITT_EXPRESS => self::AVERITT_EXPRESS,
            self::YRC => self::YRC,
            /*-------amazon-------*/
            self::DEFAULT => self::OTHER,
            self::ARROW => self::ARROW,
            self::UPS_SUREPOST_GRD_PARCEL => self::UPS,
            /*-------europe ------*/
            self::DHL_PARCEL_UK => self::DHL_PARCEL_UK,
            self::XDP => self::XDP,
            self::UPS_UK => self::UPS,
            self::DPD => self::DPD,
        ];
    }


}
