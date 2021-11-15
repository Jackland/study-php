<?php
class ModelCustomerpartnerProfile extends Model {
    CONST DIMENSION_ID_1 = 1; //seller评分总维度：产品
    CONST DIMENSION_ID_2 = 2; //seller评分总维度：交易
    CONST DIMENSION_ID_3 = 3; //seller评分总维度：沟通
    CONST DIMENSION_ID_4 = 4; //seller评分总维度：信誉
	public function getBuyerAndSellerRelation($buyerId,$sellerId){
	    if(!isset($buyerId) || !isset($sellerId)){
	        return null;
        }
		$sql = "select * from oc_buyer_to_seller where buyer_id=" .$buyerId. " and seller_id=" . $sellerId . " and buyer_control_status = 1 and seller_control_status = 1";
        $query = $this->db->query($sql);
        if($query->num_rows == 0){
        	return null;
		}
        return $query->row;
	}

	public function getSellerComprehensiveScore($sellerList,$country_id,$isRandom = false){
	    if($isRandom){
	        $ret = [];
	        foreach($sellerList as $key => $value){
	            $ret[$value] = [
	                                'customer_id'=>$value,
	                                'score'=>$this->randomFloat(0,100),
                                ];
	        }
	        return $ret;
        }else{
	        $lastData = $this->orm->table('tb_customer_score as s')
                ->leftjoin(DB_PREFIX.'customerpartner_to_customer as c','c.customer_id','=','s.customer_id')
                ->whereIn('s.customer_id',$sellerList)
                ->whereNotNull('c.customer_id')
                ->selectRaw('max(s.task_number) as task_number,s.customer_id')
                ->groupBy('s.customer_id')
                ->get()
                ->map(function ($v){
                    return (array)$v;
                })
                ->toArray();

	        $ret = $this->orm->table('tb_customer_score as s')
                ->where(function ($query) use ($lastData) {
                    $query->where([
                        'customer_id'=> $lastData[0]['customer_id'],
                        'task_number'=> $lastData[0]['task_number'],
                        ]);
                    foreach($lastData as $key => $value){
                        if($key){
                            $query->orWhere(function ($q) use ($value){
                                return $q->where([
                                    'customer_id'=> $value['customer_id'],
                                    'task_number'=> $value['task_number'],
                                ]);
                            });
                        }
                    }

                    return $query;

                })
                ->whereIn('s.dimension_id',[self::DIMENSION_ID_1,self::DIMENSION_ID_2,self::DIMENSION_ID_3,self::DIMENSION_ID_4]) // seller评分总维度
                ->selectRaw('sum(round(score,2)) as score,customer_id')
                ->groupBy('s.customer_id')
                ->get()
                ->keyBy('customer_id')
                ->map(function ($v){
                    return (array)$v;
                })
                ->toArray();
	        return $ret;

        }
    }

    public function randomFloat($min = 0, $max = 10)
    {
        $num = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return sprintf("%.2f", $num);

    }



}
?>
