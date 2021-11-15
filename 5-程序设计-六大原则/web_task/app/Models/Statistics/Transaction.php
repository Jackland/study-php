<?php

namespace App\Models\Statistics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class Transaction
 * @package App\Models\Statistics
 */
class Transaction extends Model
{
    /**
     * Transaction constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * @return array
     */
    public function getSoldBuyer()
    {
        $objs = DB::select("SELECT
	c.date_added,
	c.customer_id as buyer_id,
	c.user_number,
	c.company_name,
	lt.last_time,
	concat(c.firstname,' ',c.lastname) as 'name',
	c.telephone,
	c.email,
	concat(bd.firstname,' ',bd.lastname) as 'bd_name',
	_to._sum as 'sum_money',
	cou.iso_code_2 as 'country'
FROM
	oc_customer AS c 
	left join (
		select count(*) as total, sum( oo.total - ifnull(oot.value,0)) as _sum, oo.customer_id 
		from oc_order  as oo
		left join oc_order_total as oot on oot.order_id = oo.order_id and oot.code='balance'
		where oo.order_status_id = 5 GROUP BY oo.customer_id
	) as _to on _to.customer_id = c.customer_id
	left join (
		select max(date_added) as last_time,customer_id 
		from oc_order 
		where date_added <='2019-12-03 23:59:59' and order_status_id =5
		GROUP BY customer_id
 	) as lt on lt.customer_id = c.customer_id
	left join tb_sys_buyer_account_manager as am on c.customer_id = am.BuyerId
	left join oc_customer as bd on bd.customer_id = am.AccountId
	left join oc_country as cou on cou.country_id = c.country_id
WHERE
	c.date_added <= '2019-05-30 23:59:59' and c.accounting_type = 2
	AND c.customer_id IN ( SELECT customer_id FROM oc_order WHERE order_status_id = 5 )
	and not exists(
			select sum( _o.total - ifnull(_ot.value,0)) as tm,_o.customer_id,_c.country_id
			from oc_order as _o
			join oc_customer as _c on _c.customer_id = _o.customer_id
			left join oc_order_total as _ot on _ot.order_id = _o.order_id and _ot.code='balance'
			where  _c.customer_id = c.customer_id and _o.order_status_id=5
			GROUP BY _o.customer_id,DATE_FORMAT(_o.date_added,'%Y%m') 
			HAVING tm > 
				case
					when _c.country_id = 81 then 20000
					when _c.country_id = 222 then 17060
					when _c.country_id = 107 then 2000000
					else 60000
				end
	)
	order by _to.total DESC");
        return $objs;
    }

    /**
     * @param int $buyer_id
     * @return \Illuminate\Support\Collection
     */
    final public function MostSales(int $buyer_id)
    {
        $objs = DB::table('oc_order as o')
            ->join('oc_order_product as op', 'op.order_id', '=', 'o.order_id')
            ->join('oc_product as p', 'p.product_id', '=', 'op.product_id')
            ->where([
                ['o.order_status_id', '=', 5],
                ['o.date_added', '<=', '2019-12-03 23:59:59'],
                ['o.customer_id', '=', $buyer_id],
            ])
            ->select(['p.sku', 'p.product_id'])
            ->selectRaw('sum(op.quantity) as sum_qty')
            ->groupBy(['p.product_id'])
            ->orderBy('sum_qty', 'DESC')
            ->limit(3)
            ->get();
        return $objs;
    }
}
