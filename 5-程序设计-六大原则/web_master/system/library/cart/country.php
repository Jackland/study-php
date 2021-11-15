<?php
/**
 * User: lilei
 * Date: 2019/1/16
 * Time: 11:13
 */

namespace Cart;
class Country
{

    private $japanCountryId = 22;

    private $europeCountryIds = array(
        // 德国
        81,
        // 英国
        222
    );

    public function __construct($registry)
    {
        $this->db = $registry->get('db');
        $this->config = $registry->get('config');
    }


    /**
     * 判断是否是欧洲国家
     * @param int $country_id
     * @return bool
     */
    public function isEuropeCountry($country_id)
    {
        return in_array($country_id, $this->europeCountryIds);
    }

    /**
     * 根据国别展示价格
     * @deprecated 已废弃，请使用SellerProductRatioRepository->calculationSellerDisplayPrice()
     * @param int $country_id
     * @param float $price
     * @return float|int
     */
    public function getDisplayPrice($country_id, $price)
    {
        if ($country_id && in_array($country_id, $this->europeCountryIds)) {
            $sql = "SELECT factor FROM oc_price_specific_split pss WHERE pss.country_id = " . (int)$country_id;
            $query = $this->db->query($sql);
            if (!empty($query->row) && !is_null($query->row['factor'])) {
                $factor = $query->row['factor'];
                $price = $price * $factor;
            }
        }
        return $price;
    }
}
