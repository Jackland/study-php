<?php

class ModelCustomerRecharge extends Model
{
    /**
     * @param int $id
     * @return array|null
     */
    public function getRebateInfo(int $id)
    {
        $ret = $this->orm->table('oc_rebate_agreement')->where('id', $id)->first();
        return $ret ? get_object_vars($ret) : null;
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function getMarginInfo(int $id)
    {
        $ret = $this->orm->table('tb_sys_margin_agreement')->where('id', $id)->first();
        return $ret ? get_object_vars($ret) : null;
    }

    /**
     * 根据oc_recharge_apply_items的id查找对应Pcard 与 Wire transfer的信息
     * @param array $id_arr
     * @return array
     */
    public function getRechargeApplyInfoById(array $id_arr):array
    {
        $builder=$this->orm->table('oc_recharge_apply_items as rai')
            ->leftJoin('oc_recharge_apply as ra','rai.recharge_apply_id','=','ra.id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'ra.buyer_id', '=', 'c.customer_id')
            ->leftJoin(DB_PREFIX . 'country as cou', 'cou.country_id', '=', 'c.country_id')
            ->select([
                'c.firstname','c.lastname',
                'rai.recharge_apply_id','rai.id',
                'cou.iso_code_2 as country',
                'ra.serial_number'
            ])
            ->whereIn('rai.id',$id_arr)
            ->get();
        return obj2array($builder);
    }
}