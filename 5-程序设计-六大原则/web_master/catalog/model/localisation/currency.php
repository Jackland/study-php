<?php

/**
 * Class ModelLocalisationCurrency
 */
class ModelLocalisationCurrency extends Model {
	public function getCurrencyByCode($currency) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "currency WHERE code = '" . $this->db->escape($currency) . "'");

		return $query->row;
	}
	public function getCurrencyByBuyerId($buyerId) {
		$query = $this->db->query("SELECT cny.* FROM `oc_currency` cny
INNER JOIN  oc_country t1 ON cny.`currency_id` = t1.`currency_id`
INNER JOIN  `oc_customer` t2 ON t1.`country_id`=t2.`country_id`
WHERE t2.`customer_id` =  $buyerId");

		return $query->row;
	}

	public function getCurrencies() {
		$currency_data = $this->cache->get('currency');

		if (!$currency_data) {
			$currency_data = $this->getCurrenciesNoCache();

			$this->cache->set('currency', $currency_data);
		}

		return $currency_data;
	}

    /**
     * 获取所有币种 ，没有缓存
     *
     * @return array
     */
    public function getCurrenciesNoCache() {
        $currency_data = array();
        $list = $this->orm->table(DB_PREFIX . "currency")->orderByRaw("FIND_IN_SET(code,'USD,EUR,GBP,JPY')")->get();
        $list = obj2array($list);
        foreach ($list as $result) {
            $currency_data[$result['code']] = array(
                'currency_id'   => $result['currency_id'],
                'title'         => $result['title'],
                'code'          => $result['code'],
                'symbol_left'   => $result['symbol_left'],
                'symbol_right'  => $result['symbol_right'],
                'decimal_place' => $result['decimal_place'],
                'value'         => $result['value'],
                'status'        => $result['status'],
                'date_modified' => $result['date_modified']
            );
        }
        return $currency_data;
    }

    /**
     * @param int $seller_id
     * user：wangjinxin
     * date：2019/11/8 11:37
     * @return string|null
     */
    public function getCurrencyCodeBySellerId(int $seller_id)
    {
        $res = $this->getCurrencyByBuyerId($seller_id);
        return $res['code'] ?? null;
    }


    /**
     * 获取汇率
     * 因为系统原因 UUU是人民币
     * 其他支持的参考 oc_currency 表
     *
     * @param string $leftCurrency 左边币种
     * @param string $rightCurrency 右边币种
     * @param boolean $isRmb 是否需要人民币，指定了币种，这个参数就失效了
     * @param integer|bool $precision 精度，这里返回的汇率默认只保留小数点后8位，如果有需要，额外指定下,如果不需要，传false
     *
     *
     * @return array|bool|mixed
     *         array 某一侧币种没有指定
     *         bool 币种不存在或者参数错误等情况
     *         mixed 指定了两边币种，返回指定汇率
     */
    public function getExchangeRate($leftCurrency = '', $rightCurrency = '',$isRmb = false,$precision = 8)
    {
        $currencies = $this->getCurrenciesNoCache();
        if (!$currencies) {
            return false;
        }
        if ($leftCurrency == 'UUU' || $rightCurrency == 'UUU') {
            $isRmb = true;
        }
        $currencies = array_column($currencies,'value','code');//转换成 币种=>汇率的格式
        $leftCurrency = $leftCurrency ? strtoupper($leftCurrency) : '';//转换大写
        $rightCurrency = $rightCurrency ? strtoupper($rightCurrency) : '';//转换大写
        //传入了不存在的币种返回错误--START
        if ($leftCurrency && !array_key_exists($leftCurrency, $currencies)) {
            return false;
        }
        if ($rightCurrency && !array_key_exists($rightCurrency, $currencies)) {
            return false;
        }
        //传入了不存在的币种返回错误--END
        $exchangeRates = [];
        foreach ($currencies as $keyL => $currencyL) {
            if ($leftCurrency && $keyL != $leftCurrency) {
                //传左边，返回左边币种对其余币种的汇率
                continue;
            }
            foreach ($currencies as $keyR => $currencyR) {
                if (!$isRmb && ($keyL == 'UUU' || $keyR == 'UUU')) {
                    //不需要人民币就不返回人民币的
                    continue;
                }
                if ($rightCurrency && $keyR != $rightCurrency) {
                    //如果指定右边，返回所有币种对该币种的汇率
                    continue;
                }
                if ($keyL == $keyR) {
                    $exchangeRateItem = 1;
                } else {
                    if ($keyL == 'USD' && $keyR == 'UUU') {
                        //美元对人民币 反转
                        $exchangeRateItem = $currencyR / $currencyL;
                    } elseif ($keyL == 'UUU' && $keyR == 'USD') {
                        //人民币对美元
                        $exchangeRateItem = $currencyR / $currencyL;
                    } elseif ($keyL == 'UUU') {
                        //人民币对其他
                        $exchangeRateItem = 1 / $currencyR;
                    } elseif ($keyL == 'USD') {
                        //美元对其他
                        $exchangeRateItem = $currencies['UUU'] / $currencyR;
                    } elseif ($keyR == 'UUU'){
                        //其他对人民币
                        $exchangeRateItem = $currencyL / $currencies['USD'];
                    }  elseif ($keyR == 'USD'){
                        //其他对美元
                        $exchangeRateItem = $currencyL / $currencies['UUU'];
                    }else {
                        $exchangeRateItem = $currencyL / $currencyR;
                    }
                }
                $exchangeRates[$keyL][$keyR] = $precision ? round($exchangeRateItem, (int)$precision) : $exchangeRateItem;
            }
        }
        if ($leftCurrency && $rightCurrency) {
            //两边都传
            //返回1单位左边币种转换成多少右边币种
            return $exchangeRates[$leftCurrency][$rightCurrency] ?? false;
        } elseif ($leftCurrency) {
            //只有左边
            //返回的是1单位左边币种转换成多少其他币种
            return $exchangeRates[$leftCurrency] ?? false;
        } elseif ($rightCurrency) {
            //只有右边
            //返回的是1单位币种转换成该币种的数量
            return array_combine(array_keys($exchangeRates),
                                 array_column(array_values($exchangeRates), $rightCurrency));
        }
        //返回总汇率表
        return $exchangeRates;
    }
}
