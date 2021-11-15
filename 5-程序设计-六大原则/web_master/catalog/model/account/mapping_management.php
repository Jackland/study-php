<?php

/**
 * Class ModelAccountMappingManagement
 * 页面上的External Platform Mapping菜单
 */
class ModelAccountMappingManagement extends Model
{
    public function isShowMappingManagement()
    {
        if (
            $this->isShowItemCodeMapping()
            || $this->isShowWarehouseMapping()
        ) {
            return 1;
        } else {
            return 0;
        }
    }


    public function isShowItemCodeMapping()
    {
        //102049 非内部Buyer
        $result = 0;
        if (!$this->customer->isPartner()) {
            switch ($this->customer->getCountryId()) {
                case 223://美 内部Buyer不可见
                    if ($this->customer->isCollectionFromDomicile()) {
                        //#2913 内部上门取货用户，暂时只对美国开放
                        $result = 1;
                    } else {
                        if (!$this->customer->isInnerBuyer()) {
                            $result = 1;
                        }
                    }
                    break;
                case 222://英 一件代发Buyer不可见
                    if ($this->customer->isCollectionFromDomicile()) {
                        $result = 1;
                    } else {
                        $result = 0;
                    }
                    break;
                case 81://德 一件代发Buyer不可见
                    if ($this->customer->isCollectionFromDomicile()) {
                        $result = 1;
                    } else {
                        $result = 0;
                    }
                    break;
                case 107://日 Buyer不可见
                    $result = 0;
                    break;
                default:
                    $result = 0;
                    break;
            }
        }

        return $result;
    }

    public function isShowWarehouseMapping()
    {
        //102049 非内部Buyer
        $result = 0;
        if (!$this->customer->isPartner()) {//是 Buyer
            switch ($this->customer->getCountryId()) {
                case 223://美 内部Buyer不可见
                    if ($this->customer->isCollectionFromDomicile()) {
                        //#2913 内部上门取货用户，暂时只对美国开放
                        $result = 1;
                    } else {
                        if (!$this->customer->isInnerBuyer()) {
                            $result = 1;
                        }
                    }
                    break;
                case 222://英 一件代发Buyer不可见
                    if ($this->customer->isCollectionFromDomicile()) {
                        $result = 1;
                    } else {
                        $result = 0;
                    }
                    break;
                case 81://德 一件代发Buyer不可见
                    if ($this->customer->isCollectionFromDomicile()) {
                        $result = 1;
                    } else {
                        $result = 0;
                    }
                    break;
                case 107://日 Buyer不可见
                    $result = 0;
                    break;
                default:
                    $result = 0;
                    break;
            }
        }

        return $result;
    }

    public function mappingManagementLink()
    {
        return $this->url->link("account/mapping_management", '', true);
    }
}