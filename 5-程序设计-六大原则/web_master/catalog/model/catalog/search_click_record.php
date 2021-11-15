<?php
/**
 * Class ModelCatalogSearchClickRecord
 */
class ModelCatalogSearchClickRecord extends Model
{
    protected $table;
    const SELECT = [1,2,3,4,11];
    const ORDER = [5,6,7,8,9,10];


    public function __construct(Registry $registry)
    {
        $this->table = DB_PREFIX . 'search_click_record';
        parent::__construct($registry);
    }

    public function saveRecord($click,$order='')
    {
        if ($click && is_numeric($click)){
            $customer_id = $this->customer->getId()?$this->customer->getId():0;
            $record = [
                'customer_id'   => $customer_id,
                'type'          => in_array($click, self::SELECT)?0:1,
                'field'         => $click,
                'operation'     => in_array($click, self::ORDER)&&$order? $order:'',
                'create_time'   => date('Y-m-d H:i:s')
            ];
            $this->orm->table($this->table)
                ->insert($record);
        }

    }

}