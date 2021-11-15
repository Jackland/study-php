<?php

use Illuminate\Database\Capsule\Manager as DB;
/**
 * Class ModelNoticeNotice
 *
 * @property ModelStationLetterStationLetter $model_station_letter_station_letter
 */
class ModelNoticeNotice extends Model {

    /**
     * 获取公告列表
     * @param array $filter_data
     * @return array
     */
    public function listNotice($filter_data=[])
    {
        $sql = $this->createNoticeSql();
        if(isset($filter_data['is_read']) && $filter_data['is_read']=='unread'){
            //未读
            $sql .= ' and (p.is_read is null or p.is_read =0) ';
        }
        if(isset($filter_data['notice_id'])){
            $sql .= ' and n.id='.$filter_data['notice_id'];
        }
        if(isset($filter_data['filter_type_id']) && $filter_data['filter_type_id']) {
            //1产品 2系统 3政策
            $sql .= ' and n.type_id=' . $filter_data['filter_type_id'];
        }

        $sql .= ' ORDER BY n.top_status DESC,n.publish_date DESC ';
        if(isset($filter_data['start']) && isset($filter_data['limit'])){
            $sql .= " LIMIT " . (int)$filter_data['start'] . "," . (int)$filter_data['limit'];
        }

        $notices = $this->db->query($sql)->rows;

        $isPartner = customer()->isPartner();
        foreach ($notices as &$notice) {
            if ($isPartner) {
                $notice['notice_href'] = $this->url->to(['customerpartner/message_center/notice/detail', 'notice_id' => $notice['notice_id'], 'type' => 'notice']);
            } else {
                $notice['notice_href'] = $this->url->to(['account/message_center/platform_notice/view', 'notice_id' => $notice['notice_id'], 'type' => 'notice']);
            }
        }
        return $notices;
    }

    /**
     * 已读本条公告
     * @param $notice_id
     */
    public function readNotice($notice_id){
        $notice_id = $this->db->escape($notice_id);
        $sql = 'SELECT  n.id,p.placeholder_id, p.is_read
FROM tb_sys_notice n
LEFT JOIN tb_sys_notice_placeholder p
    ON p.notice_id = n.id
WHERE   n.id= '.$notice_id.'
  AND ( p.customer_id = '.$this->customer->getId().'
        OR p.customer_id IS NULL ) ';
        $row = $this->db->query($sql)->row;
        if(!isset($row['placeholder_id'])){
            $this->db->query("insert into tb_sys_notice_placeholder set
notice_id=$notice_id,
customer_id=".$this->customer->getId().",
is_read=1,
create_time=now()")->row;
        }
    }


    /**
     * 当前分类的unread/total公告数量
     * @param array $filter_data
     * @return array
     */
    public function countNotice($filter_data=[])
    {
        $sql = 'select is_read,count(*) as total from (';
        $sql .= $this->createNoticeSql();
        if ($filter_data['filter_type_id']){
            $sql .= ' and n.type_id ='.$filter_data['filter_type_id'];
        }
        $sql .= ')_t  group by is_read';
        $rows = $this->db->query($sql)->rows;
        $unread = 0;
        $total = 0;
        foreach ($rows as $row){
            if($row['is_read']=='0'){
                //未读
                $unread = $row['total'];
            }
            //总数
            $total += $row['total'];
        }
        return [$unread,$total];
    }

    /**
     * 未读公告数量分类
     * @param array $filter_data
     * @return array
     */
    public function countUnreadNoticeByType()
    {
        $sql = 'select type_id,count(*) as total from (';
        $sql .= $this->createNoticeSql();
        $sql .= ' and p.is_read is null or p.is_read =0 ';
        $sql .= ')_t    group by type_id order by type_id';
        $rows = $this->db->query($sql)->rows;
        $result = [];
        foreach ($rows as $row){
            $result[$row['type_id']] = $row['total'];
        }
        return $result;
    }

    /**
     * 未读公告数量
     * @param array $filter_data
     * @return array
     */
    public function countUnreadNotice($filter_data=[])
    {
        $sql = 'select count(*) as total from (';
        $sql .= $this->createNoticeSql();
        $sql .= ' and p.is_read is null or p.is_read =0 ';
        $sql .= ')_t';
        return $this->db->query($sql)->row['total'];
    }


    public function queryTypeDic()
    {
        $rows = $this->db->query("select dicKey,dicValue from tb_sys_dictionary  where dicCategory in ('PLAT_NOTICE_TYPE')")->rows;
        $type_dic = [];
        foreach ($rows as $row){
            $type_dic[$row['dicKey']] = $row['dicValue'];
        }
        return $type_dic;
    }

    public function createNoticeSql()
    {
        if($this->customer->isLogged()){
            $customer_id = $this->customer->getId();
            $country_id = $this->customer->getCountryId();
            $identity = $this->customer->isPartner() ? '1' : '0';
        }else{
            $customer_id = -1;
            $country_id = -1;
            $identity = -1;
        }
        $now = date('Y-m-d H:i:s');
        //102075 增加是否需要确认字段
        $sql = 'SELECT DISTINCT n.id as notice_id,
case when p.is_read is null then 0
    else p.is_read
end as is_read,
n.type_id,n.title,n.top_status,n.publish_date,n.effective_time,n.content,
dic.dicValue as type_name,n.make_sure_status,
case when p.make_sure_status is null then 0
    else p.make_sure_status
end as p_make_sure_status
 FROM tb_sys_notice n
 LEFT JOIN tb_sys_dictionary dic ON dic.dicCategory in (\'PLAT_NOTICE_TYPE_DELETE\',\'PLAT_NOTICE_TYPE\') and dic.dicKey=n.type_id
 LEFT JOIN tb_sys_notice_to_object  n2o ON n.id = n2o.notice_id
 LEFT JOIN tb_sys_notice_object o ON o.id = n2o.notice_object_id
 LEFT JOIN tb_sys_notice_placeholder p ON p.notice_id=n.id AND (p.customer_id='.$customer_id.' OR p.customer_id IS NULL)
 WHERE 1
  AND n.publish_status=1
  AND  o.identity='.$identity.'
  AND (o.country_id ='.$country_id.' or o.country_id = 0)
  and n.publish_date<= \''.$now.'\'';
        return $sql;
    }

    /**
     * 获取notice的基础model
     * 关联了tb_sys_dictionary  dic
     *       tb_sys_notice_to_object n2o
     *       tb_sys_notice_object o
     *       tb_sys_notice_placeholder p
     * 需要传入基础条件
     * 返回字段在下面代码里自己看
     *
     * @param int $customerId 用户ID
     * @param int $countryId 用户国家
     * @param int $identity 用户地区
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function createNoticeModel($customerId,$countryId,$identity)
    {
        $now = date('Y-m-d H:i:s');
        $model = $this->orm->table('tb_sys_notice as n')
            ->select([
                         DB::raw('DISTINCT n.id as notice_id'),
                         DB::raw('case when p.is_read is null then 0 else p.is_read end as is_read'),
                         DB::raw('case when p.is_marked is null then 0 else p.is_marked end as is_marked'),
                         'n.type_id',
                         'n.title',
                         'n.top_status',
                         'n.publish_date',
                         'n.effective_time',
                         'n.content',
                         'dic.dicValue as type_name',
                         'n.make_sure_status',
                         DB::raw('case when p.make_sure_status is null then 0 else p.make_sure_status end as p_make_sure_status'),
                         DB::raw('0 as message_type')
                     ])
            ->leftJoin('tb_sys_dictionary as dic', function ($join) {
                $join->on('dic.dicKey', '=', 'n.type_id')
                    ->whereIn('dic.dicCategory', ['PLAT_NOTICE_TYPE_DELETE', 'PLAT_NOTICE_TYPE']);
            })
            ->leftJoin('tb_sys_notice_to_object as n2o', 'n.id', '=', 'n2o.notice_id')
            ->leftJoin('tb_sys_notice_object as o', 'o.id', '=', 'n2o.notice_object_id')
            ->leftJoin('tb_sys_notice_placeholder as p', function ($join) use ($customerId) {
                $join->on('p.notice_id', '=', 'n.id')
                    ->where(function ($query) use ($customerId) {
                        $query->where('p.customer_id', $customerId);
                        $query->orWhereNull('p.customer_id');
                    });
            })
            ->where('n.publish_status', 1)
            ->where('o.identity', $identity)
            ->whereIn('o.country_id', [$countryId, 0])
            ->where('n.publish_date', '<=', $now);
        return $model;
    }

    /**
     * guide 页面右侧弹窗
     *
     * @param int $customerId
     * @param int $countryId
     * @param int $identity 用户地区
     * @param $limit
     *
     * @return array
     */
    public function listColumnNotice($customerId, $countryId, $identity, $limit = 6)
    {
        $customerId = $customerId ?? -1;
        $countryId = $countryId ?? -1;
        $identity = $identity ?? -1;
        $this->load->language('information/notice');
        $model = $this->createNoticeModel($customerId, $countryId, $identity);
        //删除的不显示
        $model = $model->where(function ($query) {
            $query->where('p.is_del', 0)
                ->orWhereNull('p.is_del');
        });
        //需要重写返回数据
        $now = date('Y-m-d H:i:s');
        $model = $model->select([
                                    DB::raw('DISTINCT n.id as notice_id'),
                                    DB::raw('case when p.is_read is null then 0 else p.is_read end as is_read'),
                                    'n.type_id',
                                    'n.title',
                                    'n.top_status',
                                    'n.publish_date',
                                    'n.effective_time',
                                    'n.content',
                                    'dic.dicValue as type_name',
                                    'n.make_sure_status',
                                    DB::raw('case when p.make_sure_status is null then 0 else p.make_sure_status end as p_make_sure_status'),
                                    DB::raw("CASE WHEN n.effective_time>='{$now}' and n.top_status=1 then 1 else 0 end as top_status_order_by")
                                ]);
        $model = $model->orderByDesc('top_status_order_by')->orderByDesc('n.publish_date');
        $model = $model->limit($limit);
        $notices = $model->get();

        $isPartner = customer()->isPartner();
        foreach ($notices as &$notice) {
            if ($isPartner) {
                $notice->notice_href = $this->url->to(['customerpartner/message_center/notice/detail', 'notice_id' => $notice->notice_id, 'type' => 'notice']);
            } else {
                $notice->notice_href = $this->url->to(['account/message_center/platform_notice/view', 'notice_id' => $notice->notice_id, 'type' => 'notice']);
            }
        }
        return $notices->toArray();
    }

    /**
     * 需求100297
     * 用户登入第一次弹窗提醒
     *
     * @param int $customerId
     * @param int $countryId
     * @param int $identity 用户地区
     *
     * @return \Illuminate\Support\Collection
     */
    public function listLoginRemind($customerId, $countryId, $identity)
    {
        $customerId = $customerId ?? -1;
        $countryId = $countryId ?? -1;
        $identity = $identity ?? -1;
        $this->load->language('information/notice');
        $model = $this->createNoticeModel($customerId, $countryId, $identity);
        //限制条件：
        //删除的不弹
        $model = $model->where(function ($query){
            $query->whereNull('p.is_del')
                ->orWhere('p.is_del', 0);
        });
        //已读的公告不在弹框中显示；
        //已读未确认的公告显示在弹框中
        //（未读或者需要确认没确认的）
        $model = $model->where(function ($query) {
            $query->where(function ($query1) {
                $query1->whereNull('p.is_read')
                    ->orWhere('p.is_read', 0);
            })
                ->orWhere(function ($query1) {
                    $query1->where('n.make_sure_status', 1)
                        ->where(function ($query2) {
                            $query2->whereNull('p.make_sure_status')
                                ->orWhere('p.make_sure_status', 0);
                        });
                });
        });
        //发布时间超过展示有效期的公告（已过期的公告），不在弹框中显示；(过期的不要)
        $model = $model->where('effective_time','>',date('Y-m-d H:i:s'));
        //排序方式：置顶公告排序在普通公告前，多条置顶，则按发布时间倒序排列。
        $model = $model->orderByDesc('n.top_status')->orderByDesc('n.publish_date');
        $notices = $model->get();

        $isPartner = customer()->isPartner();
        foreach ($notices as &$notice) {
            if ($isPartner) {
                $notice->notice_href = $this->url->to(['customerpartner/message_center/notice/detail', 'notice_id' => $notice->notice_id, 'type' => 'notice']);
            } else {
                $notice->notice_href = $this->url->to(['account/message_center/platform_notice/view', 'notice_id' => $notice->notice_id, 'type' => 'notice']);
            }
        }
        return $notices;
    }

    public function createNoticeSqlNew()
    {
        if($this->customer->isLogged()){
            $customer_id = $this->customer->getId();
            $country_id = $this->customer->getCountryId();
            $identity = $this->customer->isPartner() ? '1' : '0';
        }else{
            $customer_id = -1;
            $country_id = -1;
            $identity = -1;
        }
        $deleteIdArr = $this->orm->table('tb_sys_notice_placeholder')
            ->where('is_del', 1)
            ->where(function ($query) use ($customer_id){
                return $query->where('customer_id', $customer_id)
                    ->orWhere('customer_id', 'is null');
            })
            ->pluck('notice_id')
            ->toArray();
        $deleteIds = '';
        if (!empty($deleteIdArr)){
            $deleteIds = implode(',',$deleteIdArr);
        }

        $now = date('Y-m-d H:i:s');
        $sql = 'SELECT DISTINCT n.id as notice_id,
                case when p.is_read is null then 0
                else p.is_read
                end as is_read,
                case when p.is_marked is null then 0
                else p.is_marked
                end as is_marked,
                n.type_id,n.title,n.top_status,n.publish_date,n.effective_time,n.content, dic.dicValue as type_name,n.make_sure_status,
                case when p.make_sure_status is null then 0
                else p.make_sure_status
                end as p_make_sure_status
                FROM tb_sys_notice n
                LEFT JOIN tb_sys_dictionary dic ON dic.dicCategory in (\'PLAT_NOTICE_TYPE\',\'PLAT_NOTICE_TYPE_DELETE\') and dic.dicKey=n.type_id
                LEFT JOIN tb_sys_notice_to_object  n2o ON n.id = n2o.notice_id
                LEFT JOIN tb_sys_notice_object o ON o.id = n2o.notice_object_id
                LEFT JOIN tb_sys_notice_placeholder p ON p.notice_id=n.id AND (p.customer_id='.$customer_id.' OR p.customer_id IS NULL)
                WHERE 1
                    AND n.publish_status=1
                    AND  o.identity='.$identity.'
                    AND (o.country_id ='.$country_id.' or o.country_id = 0)
                    AND n.publish_date<= \''.$now.'\'';
        if ($deleteIds){
            $sql .= ' AND n.id not in ('.$deleteIds.')';
        }

        return $sql;
    }

    public function countNoticeNew($filter_data=[])
    {
        $sql = 'select count(*) as total from (';
        $sql .= $this->createNoticeSqlNew();
        if(isset($filter_data['is_read']) && $filter_data['is_read'] >= 0){
            //未读
            if (!$filter_data['is_read']){
                $sql .= ' and (p.is_read is null or p.is_read =0) ';
            }else{
                $sql .= ' and p.is_read = 1';
            }
        }
        if(isset($filter_data['marked']) && $filter_data['marked'] >= 0){
            //未读
            if (!$filter_data['marked']){
                $sql .= ' and (p.is_marked is null or p.is_marked =0) ';
            }else{
                $sql .= ' and p.is_marked = 1';
            }
        }
        if(isset($filter_data['notice_id'])){
            $sql .= ' and n.id='.$filter_data['notice_id'];
        }
        if(isset($filter_data['filter_type_id']) && $filter_data['filter_type_id']) {
            //1产品 2系统 3政策
            $sql .= ' and n.type_id=' . $filter_data['filter_type_id'];
        }

        if (isset($filter_data['date_from']) && $filter_data['date_from']){
            $sql .= ' and n.publish_date >= \''.$filter_data['date_from'].' 00:00:00\'';
        }

        if (isset($filter_data['date_to']) && $filter_data['date_to']){
            $sql .= ' and n.publish_date <= \''.$filter_data['date_to'].' 23:59:59\'';
        }
        if (isset($filter_data['publish_date']) && $filter_data['publish_date']){
            $sql .= ' and n.publish_date >= \''.$filter_data['publish_date'].'\'';
        }
        if (isset($filter_data['subject']) && $filter_data['subject']){
            $sql .= ' and n.title like \'%'.$filter_data['subject'].'%\'';
        }

        $sql .= ')_t  ';
        $count = $this->db->connection('read')->query($sql)->row;

        return $count['total'];
    }

    /*
     * 批量 已读/未读
     * */
    public function batchRead($ids,$read=0)
    {
        try {
            $id_list = explode(',', $ids);
            $datas=[];
            foreach ($id_list as $k => $id) {
                //这里设置一个延迟，避免选了已读或其他操作后回到详情页马上再调用这个会重复插入数据
                $updateData = ['is_read' => $read, 'update_time' => date('Y-m-d H:i:s')];
                if ($read == 0) {
                    //设置成未读，之前确认的也要变成未确认
                    $updateData['make_sure_status'] = 0;
                }
                $update = $this->orm->table('tb_sys_notice_placeholder')
                    ->where('customer_id', $this->customer->getId())
                    ->where('notice_id', $id)
                    ->update($updateData);

                if (0 == $update) {
                    $placeholder = $this->orm->table('tb_sys_notice_placeholder')
                        ->where('customer_id', $this->customer->getId())
                        ->where('notice_id', $id)->count();
                    //避免已读重复插入
                    if (empty($placeholder) || $placeholder == 0) {
                        $data = [
                            'notice_id'   => $id,
                            'customer_id' => $this->customer->getId(),
                            'is_read'     => $read,
                            'create_time' => date('Y-m-d H:i:s'),
                            'update_time' => date('Y-m-d H:i:s'),
                            'is_marked'   => 0,
                            'is_del'      => 0
                        ];
                        $datas[] = $data;
                    }
                }
            }
            $insert = $this->orm->table('tb_sys_notice_placeholder')
                ->insert($datas);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * 确认通知
     *
     * @param integer $customerId 用户ID
     * @param array   $noticeIds  通知ID，支持[1,2...] 最好是数字数组，支持['1',2,...] 但是不要乱传字符串数组
     *
     * @return bool
     */
    public function batchSure($customerId, $noticeIds)
    {
        if (!$customerId || !$noticeIds || !is_array($noticeIds)) {
            return false;
        }
        try {
            $insertData = [];
            $noticeList = $this->orm->table('tb_sys_notice')
                ->whereIn('id', $noticeIds)->get(['id','make_sure_status']);
            foreach ($noticeList as $notice) {
                $update = $this->orm->table('tb_sys_notice_placeholder')
                    ->where('customer_id', $customerId)
                    ->where('notice_id', $notice->id)
                    ->update(['is_read' => 1, 'make_sure_status' => $notice->make_sure_status, 'update_time' => date('Y-m-d H:i:s')]);
                if (0 == $update) {
                    $data = [
                        'notice_id'        => $notice->id,
                        'customer_id'      => $customerId,
                        'is_read'          => 1,//已确认也是已读
                        'make_sure_status' => $notice->make_sure_status,//标记为已确认
                        'create_time'      => date('Y-m-d H:i:s'),
                        'update_time'      => date('Y-m-d H:i:s'),
                    ];
                    $insertData[] = $data;
                }
            }
            if ($insertData) {
                $insert = $this->orm->table('tb_sys_notice_placeholder')
                    ->insert($insertData);
            }
        } catch (Exception $exception) {
            return false;
        }
        return true;
    }
}
