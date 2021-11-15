<?php

/**
 * Class ModelCustomerpartnerStoreRate
 */
class ModelCustomerpartnerStoreRate extends Model
{
    /**
     * 店铺退返品率
     * @param int $seller_id
     * @return int|float
     */
    public function returnsRate($seller_id)
    {
        $sql  = "SELECT `returns_rate` FROM oc_customerpartner_to_customer WHERE customer_id={$seller_id}";
        $row  = $this->db->query($sql)->row;
        $rate = isset($row['returns_rate']) ? $row['returns_rate'] : -1;
        return $rate;
    }

    /**
     * 店铺退返品率
     * @param $rate
     * @return string
     */
    public function returnsMarkByRate($rate)
    {
        if (is_null($rate) || $rate < 0) {
            $rate_mark = __('N/A', [], 'common');
        } else {
            if ($rate > 10) {
                // $rate > 10%
                $rate_mark = __('高', [], 'common');
            } elseif ($rate <= 10 && $rate > 4) {
                // 10% >= $rate > 4
                $rate_mark = __('中', [], 'common');
            } else {
                //0%<= $rate <= 4%
                $rate_mark = __('低', [], 'common');
            }
        }

        return $rate_mark;
    }


    /**
     * 店铺回复率
     * @param int $seller_id
     * @return int|float
     */
    public function responseRate($seller_id)
    {
        $sql  = "SELECT `response_rate` FROM oc_customerpartner_to_customer WHERE customer_id={$seller_id}";
        $row  = $this->db->query($sql)->row;
        $rate = isset($row['response_rate']) ? $row['response_rate'] : -1;
        return $rate;
    }

    /**
     * 店铺回复率
     * @param float|null $rate
     * @return string
     */
    public function responseMarkByRate($rate)
    {
        $rate_mark = '';
        if (is_null($rate) || $rate < 0) {
            $rate_mark = '';
        } else {
            if ($rate > 1) {
                //$rate > 1%
                $rate_mark = __('高', [], 'common');
            } elseif ($rate <= 1 && $rate > 0) {
                // 1% >= $rate > 0
                $rate_mark = __('中', [], 'common');
            } else {
                //$rate == 0%
                $rate_mark = __('低', [], 'common');
            }
        }

        return $rate_mark;
    }
}
