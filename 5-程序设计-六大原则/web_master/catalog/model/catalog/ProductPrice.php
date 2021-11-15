<?php
/**
 * Class ModelCatalogProductPrice
 */
class ModelCatalogProductPrice extends Model
{
    /**
     * 产品原价改变
     * @param int $product_id
     */
    public function originalPriceChangeRateTwoWeek($product_id)
    {
        //更新14天内，产品价格变化率
        $date_now   = date('Y-m-d H:i:s');
        $date_start = date("Y-m-d 00:00:00", strtotime("-14 days"));

        $rate = '0.0'; //最终得到的比率


        //两周前，最晚一次的改价
        $sql       = "SELECT sph.product_id, sph.price, sph.add_date
FROM oc_seller_price_history AS sph
WHERE
  sph.product_id={$product_id}
  AND sph.`status`=1
  AND add_date<'{$date_start}'
  ORDER BY add_date DESC
  LIMIT 1";
        $query     = $this->db->query($sql);
        $row_other = $query->rows;


        //两周内的改价记录
        $sql   = "SELECT sph.product_id, sph.price, sph.add_date
FROM oc_seller_price_history sph
WHERE
    sph.product_id={$product_id}
    AND sph.`status`=1
	AND sph.add_date>='{$date_start}'
	ORDER BY add_date ASC";
        $query = $this->db->query($sql);
        $row_inner = $query->rows;

        $row_inner = array_merge($row_other, $row_inner);
        $row_last  = end($row_inner);

        $length = count($row_inner);
        if ($length > 1) {
            $price_max = 0;
            $index     = 0;
            foreach ($row_inner as $value) {
                $index++;
                if ($index == $length) {
                    break;
                }


                if ($value['price'] > $price_max) {
                    $price_max = $value['price'];
                }
            }

            $rate = $this->computRate($row_last['price'], $price_max);
        }






        //是否存在原纪录
        $sql   = "SELECT ppr.id FROM oc_product_crontab AS ppr WHERE ppr.product_id={$product_id}";
        $query = $this->db->query($sql);
        $one   = $query->row;
        if ($one) {
            $sql_exec = "
    UPDATE oc_product_crontab 
    SET price_change_rate = {$rate},
    price_change_rate_date_modified = '{$date_now}' 
    WHERE
        product_id = {$product_id}";
        } else {
            $sql_exec = "
    INSERT INTO oc_product_crontab 
    SET product_id = {$product_id},
    date_added='{$date_now}',
    price_change_rate = {$rate},
    price_change_rate_date_modified = '{$date_now}'";
        }

        $this->db->query($sql_exec);
    }



    /**
     * 计算价格变化率
     * @param float $currentPrice
     * @param float $otherPrice
     * @return string|null
     */
    public function computRate($currentPrice, $otherPrice)
    {
        $rate = '0.0';
        if($otherPrice > 0){
            if ($currentPrice && $otherPrice) {
                if (bccomp($otherPrice, 0.00, 2)) {
                    $diff = bcsub($currentPrice, $otherPrice, 2);
                    $rate = bcdiv($diff, $otherPrice, 6);

                    $rateArr = explode('.', $rate);
                    $int     = $rateArr[0];
                    $float   = $rateArr[1];
                    if($int < 0){
                        if (strlen($int) > 7) {
                            $int = substr($int, 0, 7);
                        }
                    } else {
                        if (strlen($int) > 6) {
                            $int = substr($int, 0, 6);
                        }
                    }
                    $rate = $int . '.' . $float;
                }
            }
        }

        return $rate;
    }
}