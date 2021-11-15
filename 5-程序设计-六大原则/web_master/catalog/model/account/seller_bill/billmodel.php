<?php

/**
 * Class ModelAccountSellerBillBillmodel
 */
class ModelAccountSellerBillBillmodel extends Model
{

    /**
     * 获取最近的一条
     * @return \Illuminate\Support\Collection
     */
    public function get_near_bill_id($seller_id)
    {
        $res = $this->orm->table('tb_seller_bill')->select('id')
            ->where('seller_id', (int)$seller_id)
            ->where('start_date','>=','2020-02-01')     //第一笔从2020-02-01号开始
            ->orderBy('end_date', 'desc')
            ->limit(1)
            ->get();
        return obj2array($res);
    }


    public function get_seller_bill_list($seller_id)
    {
        $select = ['id', 'start_date', 'end_date', 'settlement_status'];
        $res = $this->orm->table('tb_seller_bill')
            ->where('start_date','>=','2020-02-01')
            ->select($select)
            ->where('seller_id', (int)$seller_id)
            ->orderBy('start_date','desc')
            ->get();
        return obj2array($res);
    }

    /**
     * 获取账单详情
     * @param int $seller_id
     * @param int $bill_id
     * @return array
     */
    public function get_seller_bill($seller_id, $bill_id)
    {
        $select = [
            'tbs.serial_number', 'tbs.seller_id', 'tbs.start_date', 'tbs.end_date', 'tbs.id','tbs.remark','tbs.settle_apply',
            'tbs.settlement_date', 'tbs.reserve', 'tbs.previous_reserve', 'tbs.total', 'tbs.actual_settlement',
            'tbs.settlement', 'tbs.settlement_status', 'tbs.confirm_date', 'ssa.bank_account'
        ];
        $res = $this->orm->table('tb_seller_bill as tbs')
            ->leftJoin('tb_sys_seller_account_info as ssa' ,'tbs.seller_id','=','ssa.seller_id')
            ->select($select)->where('tbs.id', (int)$bill_id)->where('tbs.seller_id', (int)$seller_id)->limit(1)->get();
        return obj2array($res);
    }

    /**
     * 获取总单中的详细信息
     * @param int $bill_id
     * @return array
     */
    public function get_total_seller_bill($bill_id)
    {
        //获取当前订单的
        $select = ['type_id', 'code', 'value'];
        $res = $this->orm->table('tb_seller_bill_total')->select($select)->where('header_id', (int)$bill_id)->get();
        $res = obj2array($res);
        //获取树结构
        $select = ['type_id', 'code', 'description', 'parent_type_id'];
        $tree_data = $this->orm->table('tb_seller_bill_type')->select($select)->orderBy('rank_id')->orderBy('sort')->where('status', '=', 1)->get();
        $tree_data = obj2array($tree_data);
        $tree_data = array_combine(array_column($tree_data, 'type_id'), $tree_data);
        //该树结构只有两层
        $rtn = array();
        foreach ($res as $k => $v) {
            if (!isset($rtn[$tree_data[$v['type_id']]['parent_type_id']])) {
                $rtn[$tree_data[$v['type_id']]['parent_type_id']] = $tree_data[$tree_data[$v['type_id']]['parent_type_id']];
                $rtn[$tree_data[$v['type_id']]['parent_type_id']]['total']=0;
                $rtn[$tree_data[$v['type_id']]['parent_type_id']]['child'] = array();
            }
            $v['description'] = $tree_data[$v['type_id']]['description'];
            $rtn[$tree_data[$v['type_id']]['parent_type_id']]['total']+= $v['value'];
            $rtn[$tree_data[$v['type_id']]['parent_type_id']]['child'][] = $v;

        }
        return $rtn;
    }


    public function getBillFile($bill_id){
        return $this->orm->table('tb_sys_seller_bill_file')
            ->select('id','file_name','file_path')
            ->where(['seller_bill_id'=>$bill_id])
            ->get();
    }
}