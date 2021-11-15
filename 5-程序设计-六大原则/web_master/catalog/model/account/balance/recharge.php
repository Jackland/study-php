<?php

use App\Components\Storage\StorageCloud;
/**
 * Class ModelAccountBalanceRecharge
 *
 * @property ModelLocalisationCurrency $model_localisation_currency
 */
class ModelAccountBalanceRecharge extends Model
{
    /**
     * @deprecated 严禁继续使用此方法生成序列号
     *  可以使用 SerialNumberRepository.getDateSerialNumber | SerialNumberRepository.getGlobalSerialNumber
     *
     * 获取交易序列号（充值交易ID）
     * @return array
     */
    public function getRechargeSerialNumber()
    {
        return $this->db->query('select getRechargeSerialNumber() as rechargeSerialNumber')->row;
    }

    public function getRechargeApply($serialNumber, $buyerId)
    {
        $result = $this->orm->table(DB_PREFIX . 'recharge_apply as ra')
            ->where([['ra.buyer_id', '=', $buyerId], ['ra.serial_number', '=', $serialNumber]])
            ->first();
        return $result;
    }

    public function getRechargeBySerialNumber($serialNumber)
    {
        $result = $this->orm->table(DB_PREFIX . 'recharge_apply as ra')
            ->where('ra.serial_number', '=', $serialNumber)
            ->first();
        return $result;
    }

    /**
     * 查询充值订单明细
     *
     * @param int $rechargeApplyId 充值订单ID
     * @param int $itemId 明细ID，如果不指定明细，则返回这个充值订单下的所有明细
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|\Illuminate\Support\Collection|object|null
     */
    public function getRechargeItem($rechargeApplyId, $itemId = 0)
    {
        $model = $this->orm->table(DB_PREFIX . 'recharge_apply_items as rai')
            ->leftJoin(DB_PREFIX . 'currency as occ', 'rai.account_currency_id', '=', 'occ.currency_id')
            ->where('rai.is_deleted', 0)
            ->select(['rai.*', 'occ.code as account_currency_code']);
        if ($itemId) {
            return $model->where('id', $itemId)
                ->first();
        } else {
            return $model->where('rai.recharge_apply_id', $rechargeApplyId)
                ->get();
        }
    }

    /**
     * 获取充值订单所有凭证
     *
     * @param $rechargeApplyId
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRechargeProofs($rechargeApplyId)
    {
        $proofs = $this->orm->table(DB_PREFIX . 'recharge_apply_proofs as ap')
            ->leftJoin(DB_PREFIX . 'file_upload as fu', 'ap.file_upload_id', '=', 'fu.file_upload_id')
            ->where('ap.recharge_apply_id', $rechargeApplyId)
            ->whereNotNull('fu.file_upload_id')
            ->select(['fu.*'])
            ->get();
//        $http = $this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url');
        foreach ($proofs as &$proof) {
            $proof->url = StorageCloud::root()->getImageUrl($proof->path) ;
        }
        return $proofs;
    }


    public function getDicCategory($dicCategory)
    {
        $list = $this->orm->table('tb_sys_dictionary as sd')
            ->where('sd.DicCategory', '=', $dicCategory)
            ->select('sd.DicKey', 'sd.DicValue')->get();
        $result = [];
        foreach ($list as $k => $v) {
            $result[$v->DicKey] = $v->DicValue;
        }
        return $result;
    }

    /**
     * 根据oc_recharge_apply_items的id查找对应Pcard 与 Wire transfer的信息
     * @param array $id_arr
     * @return array
     */
    public function getRechargeApplyInfo(array $id_arr):array
    {
        $builder=$this->orm->table('oc_recharge_apply_items as rai')
            ->leftJoin('oc_recharge_apply as ra','rai.recharge_apply_id','=','ra.id')
            ->select([
                'rai.recharge_apply_id','rai.id',
                'ra.serial_number'
            ])
            ->whereIn('rai.id',$id_arr)
            ->get();
        return obj2array($builder);
    }

    /**
     * 同一批次申请的关联申请单，状态不为此状态的数量
     * @param int $apply_id
     * @param int $status
     * @return int
     */
    public function brotherSerialNumberNotStatus(int $apply_id, int $status): int
    {
        return $this->orm->table('oc_recharge_apply_items')
            ->where([
                ['recharge_apply_id', '=', $apply_id],
                ['status', '!=', $status]
            ])
            ->count();
    }


    /**
     * 同一批次申请的关联申请单
     * @param int $apply_id
     * @return array
     */
    public function brotherSerialNumber(int $apply_id): array
    {
        $builder = $this->orm->table('oc_recharge_apply_items')
            ->select(['serial_number'])
            ->where('recharge_apply_id', '=', $apply_id)
            ->get();
        return obj2array($builder);
    }


    /**
     * 软删除
     * @param int $apply_id
     * @return int
     */
    public function applyDelete(int $apply_id):int
    {
        return $this->orm->table('oc_recharge_apply_items')
            ->where('recharge_apply_id', '=', $apply_id)
            ->update(['is_deleted' => 1]);

    }

    /**
     * 电汇、P卡的打款凭证
     * @param int $recharge_apply_id
     * @return array
     */
    public function paymentVoucher(int $recharge_apply_id): array
    {
        $result_arr = [];
        $builder = $this->orm->table('oc_recharge_apply_proofs as rap')
            ->join('oc_file_upload as fp', 'rap.file_upload_id', '=', 'fp.file_upload_id')
            ->select(['fp.path', 'fp.name', 'fp.suffix','fp.orig_name'])
            ->where('rap.recharge_apply_id', '=', $recharge_apply_id)
            ->get();
        $result_arr = obj2array($builder);
        return $result_arr;
    }

    /**
     * P卡、电汇、Airwallex记录
     * @param array $filter_data
     * @return array
     */
    public function searchRecord(array $filter_data): array
    {
        $query = $this->orm->table('oc_recharge_apply as ra')
            ->leftJoin('oc_recharge_apply_items as rai', 'rai.recharge_apply_id', '=', 'ra.id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'ra.buyer_id', '=', 'c.customer_id')
            ->leftJoin(DB_PREFIX . 'customer as cc', 'rai.buyer_id', '=', 'cc.customer_id')
            ->leftJoin(DB_PREFIX . 'country as cou', 'cou.country_id', '=', 'c.country_id')
            ->leftJoin(DB_PREFIX . 'country as country', 'country.country_id', '=', 'cc.country_id')
            ->leftJoin('oc_currency as cu1', 'rai.recharge_currency_id', '=', 'cu1.currency_id')
            ->leftJoin('oc_currency as cu2', 'rai.account_currency_id', '=', 'cu2.currency_id')
            ->select([
                'c.country_id', 'c.firstname','c.lastname', 'c.email',
                'cc.country_id as recharge_item_country_id', 'cc.firstname as recharge_item_firstname', 'cc.lastname as recharge_item_lastname', 'cc.email as recharge_item_email',
                'rai.id as recharge_item_id', 'rai.serial_number', 'rai.status', 'rai.commission', 'rai.is_brother',
                'rai.recharge_amount', 'rai.actual_amount', 'rai.recharge_apply_id',
                'ra.serial_number as parent_serial_number', 'ra.apply_date',
                'ra.recharge_method', 'ra.amount', 'ra.currency',
                'ra.apply_status', 'ra.apply_order_id', 'ra.recharge_order_header_id',
                'cou.iso_code_2 as country',
                'country.iso_code_2 as recharge_item_country',
                'cu1.code as recharge_currency_code', 'cu2.code as account_currency_code'
            ]);

        if (isset($filter_data['rechargeStatus']) && $filter_data['rechargeStatus'] > 0) {
            $query->where([
                ['ra.buyer_id', '=', $filter_data['customer_id']],
                ['rai.status', '=', $filter_data['rechargeStatus']],
                ['ra.recharge_method','!=','airwallex']
            ])->orWhere([
                ['ra.buyer_id', '=', $filter_data['customer_id']],
                ['ra.apply_status', '=', $filter_data['rechargeStatus']],
                ['ra.recharge_method', '=', 'airwallex'],
            ])->where(function ($query) {
                $query->where('rai.is_deleted', '=', 0)->orWhereNull('rai.is_deleted');
            });

        } else {
            $query = $query->where('ra.buyer_id', '=', $filter_data['customer_id'])
                ->where(function ($query) {
                    $query->where('rai.is_deleted', '=', 0)->orWhereNull('rai.is_deleted');
                });
        }

        if (isset($filter_data['timeFrom']) && $filter_data['timeFrom']) {
            $query = $query->where('ra.apply_date', '>=', $filter_data['timeFrom']);
        }
        if (isset($filter_data['timeTo']) && $filter_data['timeTo']) {
            $query = $query->where('ra.apply_date', '<=', $filter_data['timeTo'] . ' 23:59:59');
        }
        $count = $query->count();
        if (isset($filter_data['start']) && isset($filter_data['limit'])) {
            $query = $query->offset($filter_data['start'])
                ->limit($filter_data['limit']);
        }
        $query = $query->OrderBy('rai.id', 'desc');
        $list = $query->get();
        return ['total' => $count, 'record_list' => obj2array($list)];
    }

    public function saveRechargeApply($rechargeApply)
    {
        $data = [
            'serial_number' => $rechargeApply['serial_number'],
            'recharge_method' => $rechargeApply['recharge_method'],
            'amount' => $rechargeApply['amount'],
            'currency' => $rechargeApply['currency'],
            'buyer_id' => $rechargeApply['buyer_id'],
            'apply_status' => $rechargeApply['apply_status'],
            'apply_order_id' => 0,
            'apply_date' => date('Y-m-d H:i:s', time()),
            'recharge_order_header_id' => 0,
            'create_user_name' => $this->customer->getId(),
            'create_time' => date('Y-m-d H:i:s', time()),
            'program_code' => 'V1.0'
        ];
        return $this->orm->table(DB_PREFIX . 'recharge_apply')->insertGetId($data);
    }

    /**
     * 获取Buyer Airwallex绑定申请
     * @param int $buyerId BuyerId
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getAirwallexBindApply($buyerId)
    {
        $res = $this->orm->table(DB_PREFIX . 'buyer_airwallex_bind_apply')->where([['buyer_id', '=', $buyerId]])->first();;
        return $res;
    }

    /**
     * 保存Buyer Airwallex账户绑定申请
     * @param int $buyerId
     * @param string $airwallexEmail
     */
    public function saveAirwallexBindApply($buyerId, $airwallexEmail)
    {
        if (isset($airwallexEmail)) {
            // 先获取数据
            $res = $this->orm->table(DB_PREFIX . 'buyer_airwallex_bind_apply')->where([['buyer_id', '=', $buyerId]])->first();
            if ($res == null) {
                $this->orm->table(DB_PREFIX . 'buyer_airwallex_bind_apply')->insert([
                    'buyer_id' => $buyerId,
                    'is_send_flag' => 0,
                    'airwallex_email' => $airwallexEmail,
                    'create_time' => date("Y-m-d H:i:s", time()),
                    'create_user_name' => $buyerId
                ]);
            } else {
                if ($res->airwallex_email != $airwallexEmail) {
                    $this->orm->table(DB_PREFIX . 'buyer_airwallex_bind_apply')
                        ->where('buyer_id', $buyerId)
                        ->update(['airwallex_email' => $airwallexEmail, 'is_send_flag' => 0]);
                }
            }
        }
    }

    /**
     * 根据BuyerId获取Airwallex Buyer账户信息
     * @param int $buyerId BuyerId
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getBuyerAirwallexInfo($buyerId)
    {
        if (isset($buyerId)) {
            $build = $this->orm->table(DB_PREFIX . 'buyer');
            return $build->where('buyer_id', $buyerId)->select('airwallex_identifier', 'airwallex_id')->first();
        }
        return null;
    }

    /**
     * 保存Buyer Airwallex账户标识Identifier
     * @param int $buyerId BuyerId
     * @param string $identifier Airwallex Identifier
     * @return int|null
     */
    public function saveAirwallexIdentifier($buyerId, $identifier)
    {
        if (isset($buyerId) && isset($identifier)) {
            $build = $this->orm->table(DB_PREFIX . 'buyer');
            return $build->where('buyer_id', $buyerId)->update(['airwallex_identifier' => $identifier]);
        }
        return null;
    }

    /**
     * @param int $rechargeApplyId  充值订单ID
     * @param int $buyerId 操作人ID
     * @param array $proofs 凭证数组，格式：[[xx=>xx]]
     *
     * @return bool|int
     * @throws Exception
     */
    public function saveApplyProofs($rechargeApplyId, $buyerId, $proofs)
    {
        if (empty($proofs)) {
            return 0;
        }
        $date = date('Y-m-d H:i:s');
        $applyProofs = [];
        foreach ($proofs as $proof) {
            $data = [
                'path' => $proof['path'],//文件路径(相对路径或者url路径，必须携带后缀)
                'name' => $proof['name'],//'文件名称(必须携带后缀)'
                'suffix' => $proof['suffix'],//'文件后缀'
                'size' => $proof['size'],//
                'mime_type' => $proof['mime_type'],//'文件类型'
                'orig_name' => $proof['orig_name'],//'文件上传名称(必须携带后缀)'
                'date_added' => $date,
                'date_modified' => $date,
                'mark' => '充值凭证oc_recharge_apply_proofs 表关联oc_recharge_apply',
                'status' => 1,
                'add_operator' => $buyerId
            ];
            $fileUploadId = $this->orm->table(DB_PREFIX . 'file_upload')->insertGetId($data);
            if (!$fileUploadId) {
                //文件表创建失败抛出异常
                throw new Exception('proof save error!');
            }
            $applyProofs[] = [
                'recharge_apply_id' => $rechargeApplyId,
                'file_upload_id' => $fileUploadId,
            ];
        }
        $res = $this->orm->table('oc_recharge_apply_proofs')->insert($applyProofs);
        if (!$res) {
            //关联表创建失败抛出异常
            throw new Exception('proof relation error!');
        }
        return $res;
    }

    /**
     * 保存充值明细
     *
     * @param $rechargeApplyId
     * @param $items
     *
     * @return int 成功返回id
     * @throws Exception
     */
    public function saveApplyItem($rechargeApplyId, $items)
    {
        if (empty($items)) {
            return 0;
        }
        $insertData = [];
        $date = date('Y-m-d H:i:s');
        foreach ($items as $data) {
            $item = [
                'recharge_apply_id' => $rechargeApplyId,
                'serial_number' => $data['serial_number'],
                'status' => 1,
                'buyer_id' => $data['buyer_id'],
                'commission' => $data['commission'] ?? 0,//手续费
                'recharge_amount' => $data['recharge_amount'],//充值金额
                'recharge_currency_id' => $data['recharge_currency_id'],//充值币种
                'recharge_exchange_rate' => $data['recharge_exchange_rate'],//充值汇率
                'expect_amount' => $data['expect_amount'],//预计到账金额
                'account_currency_id' => $data['account_currency_id'],//到账币种
                'is_brother' => $data['is_brother'] ?? 0,//是否有兄弟订单
                'create_time' => $date,
                'program_code' => 'V1.1'
            ];
            if (isset($data['actual_amount'])) {
                $item['actual_amount'] = $data['actual_amount'];
            }
            if (isset($data['actual_exchange_rate'])) {
                $item['actual_exchange_rate'] = $data['actual_exchange_rate'];
            }
            $insertData[] = $item;
        }
        return $this->orm->table(DB_PREFIX . 'recharge_apply_items')->insert($insertData);
    }

    /**
     * 生成ApplyItem的SerialNumber
     *
     * @return string
     * @throws Exception
     */
    public function getApplyItemSerialNumber()
    {
        //时间Ymd+六位随机数
        $serialNumber = date('Ymd') . random_int(100000, 999999);
        if ($this->orm->table(DB_PREFIX . 'recharge_apply_items')
            ->where('serial_number', $serialNumber)
            ->first()) {
            //存在重新生成，直到生成为止
            return $this->getApplyItemSerialNumber();
        }
        return $serialNumber;
    }

    /**
     * 计算手续费
     *
     * @param $amount
     * @param string $currency
     */
    public function calculateCommission($amount, $currency)
    {
        if ($amount <= 0) {
            return 0;
        }
        $this->load->model('localisation/currency');
        $currency = strtoupper($currency);
        $exchangeRate = $this->model_localisation_currency->getExchangeRate($currency, 'USD');
        if (!$exchangeRate) {
            //币种未知或者其他错误导致没有汇率
            return false;
        }
        $amountUSD = $amount * $exchangeRate;
        $commissions = $this->getCommissions();
        foreach ($commissions as $commission) {
            if ($amountUSD < $commission['max']) {
                return $commission['commission'];
            }
        }
        return 0;
    }

    /**
     * 获取手续费区间
     *
     * @return int[][]
     */
    public function getCommissions()
    {
        return [
            ['max' => 5000, 'commission' => 45, 'desc' => 'Transfer amount < $5,000'],
            ['max' => 10000, 'commission' => 35, 'desc' => '$5,000 ≤ Transfer amount < $10,000'],
            ['max' => 15000, 'commission' => 25, 'desc' => '$10,000 ≤ Transfer amount < $15,000'],
            ['max' => 25000, 'commission' => 15, 'desc' => '$15,000 ≤ Transfer amount<$25,000'],
            ['max' => 0, 'commission' => 0, 'desc' => 'Transfer amount ≥$25,000'],
        ];
    }

    /**
     *
     * 计算预计到账金额
     * 如果美元和转换的币种汇率没有，则会返回0,
     *
     * @param float $rechargeAmount 充值金额
     * @param string $rechargeCurrency 充值币种
     * @param string $accountCurrency 到账币种
     * @param int $commission 手续费
     *
     * @return array 如果错误，error_code > 0 1:不足手续费 2:没有汇率
     */
    public function calculateExpectAmount($rechargeAmount, $rechargeCurrency, $accountCurrency, $commission)
    {
        $this->load->model('localisation/currency');
        $this->load->language('account/balance');
        $return = [
            'error_code'           => 0,
            'recharge_amount'      => floatval($rechargeAmount),
            'recharge_currency'    => $rechargeCurrency,
            'account_currency'     => $accountCurrency,
            'commission'           => floatval($commission),
            'expect_exchange_rate' => 0,
            'expect_amount'        => 0,
        ];
        //先把充值金额转换成美元
        $rechargeExchangeRate = $this->model_localisation_currency->getExchangeRate($rechargeCurrency, 'USD');
        $expectExchangeRate = $this->model_localisation_currency->getExchangeRate($rechargeCurrency, $accountCurrency);
        if ($rechargeExchangeRate === false || $expectExchangeRate === false) {
            //没有汇率返回0
            $return['error_code'] = 2;//没有汇率
            $return['error_message'] = $this->language->get('recharge_currency_does_not_exist');
            return $return;
        }
        $return['expect_exchange_rate'] = $expectExchangeRate;
        $return['expect_exchange_rate_show'] = round($return['expect_exchange_rate'], 3);
        if ($rechargeAmount <= 0) {
            $return['error_code'] = 3;//金额为0
            $return['error_message'] = $this->language->get('incorrect_recharge_amount');//不足手续费
            return $return;
        }
        if (!empty($commission)) {
            $amountUSD = $rechargeAmount * $rechargeExchangeRate;
            if ($amountUSD < $commission) {
                $return['error_code'] = 1;//不足手续费
                $return['error_message'] = $this->language->get('recharge_amount_less_commission');//不足手续费
                return $return;
            }
            //扣去手续费
            $amountUSD = $amountUSD - $commission;
            //转换成指定币种
            $expectExchangeRate = $this->model_localisation_currency->getExchangeRate('USD', $accountCurrency);
            if ($expectExchangeRate === false) {
                //没有汇率返回0
                $return['error_code'] = 2;//没有汇率
                $return['error_message'] = $this->language->get('recharge_currency_does_not_exist');
                return $return;
            }
            //汇率
            $expectAmount = $amountUSD * $expectExchangeRate;
        } else {
            //没手续费直接计算
            $expectAmount = $rechargeAmount * $expectExchangeRate;
        }
        //获取精度
        $currencies = $this->model_localisation_currency->getCurrenciesNoCache();
        $decimalPlace = $currencies[$accountCurrency]['decimal_place'] ?? 2;//到账币种精度，默认2
        $expectAmount = round($expectAmount, $decimalPlace);
        $return['expect_amount'] = $decimalPlace > 0 ? $expectAmount : intval($expectAmount);//不要小数点就转换成int
        return $return;
    }
}
