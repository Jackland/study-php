<?php

namespace Framework\DataProvider;

use Illuminate\Database\Query\Expression;

class Sort
{
    const EXPRESSION_PREFIX = '___EXPRESSION_';

    private $config = [
        'sortParam' => 'sort', // 默认排序字段参数
        'enableMultiple' => false, // 是否允许多字段同时排序
        'defaultOrder' => [], // 默认排序，在 request 无 sort 参数时排序，例如：['created_time' => SORT_ASC]
        'alwaysOrder' => [], // 固定排序，会在最终的排序结果中补充该排序，例如：['id' => SORT_ASC]
        'rules' => [], // 支持配置形式见 parseRules()
    ];

    private $request;
    private $rules;
    private $currentSortArr;

    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->request = request();
        $this->rules = $this->parseRules($this->config['rules']);
        $this->currentSortArr = $this->parseSort2Array($this->request->get($this->config['sortParam'], ''));
    }

    /**
     * 设置默认排序
     * @param array $defaultOrder
     */
    public function setDefaultOrder(array $defaultOrder): void
    {
        $this->config['defaultOrder'] = $defaultOrder;
    }

    /**
     * 设置规则
     * @param array $rules
     */
    public function setRules(array $rules): void
    {
        $this->config['rules'] = $rules;
        $this->rules = $this->parseRules($rules);
    }

    /**
     * 设置固定排序
     * @param array $order
     */
    public function setAlwaysOrder(array $order): void
    {
        $this->config['alwaysOrder'] = $order;
    }

    /**
     * 获取当前的排序
     * @return array [$attribute => $sort]
     */
    public function getCurrentSort(): array
    {
        return $this->currentSortArr;
    }

    /**
     * 获取当前的排序，按照字段
     * @return array
     * [
     *   ['attribute' => 'c.create_time', 'sort' => SORT_ASC, 'direction' => 'asc'],
     *   ['attribute' => new Expression('(b.count-b.left_count)'), 'sort' => SORT_ASC, 'direction' => 'asc'],
     * ]
     */
    public function getCurrentSortWithAttribute(): array
    {
        $result = [];
        foreach ($this->getCurrentSort() as $name => $sort) {
            $this->parseSortByName($name, $sort, $result);
        }
        if (count($result) === 0 && $this->config['defaultOrder']) {
            foreach ($this->config['defaultOrder'] as $name => $sort) {
                $this->parseSortByName($name, $sort, $result);
            }
        }
        if ($this->config['alwaysOrder']) {
            foreach ($this->config['alwaysOrder'] as $name => $sort) {
                $this->parseSortByName($name, $sort, $result);
            }
        }

        $data = [];
        foreach ($result as $item) {
            if (strpos($item['attribute'], static::EXPRESSION_PREFIX) === 0) {
                $item['attribute'] = $this->coverString2OriginExpression($item['attribute']);
                if (!$item['attribute']) {
                    continue;
                }
            }
            $data[] = $item;
        }
        return $data;
    }

    /**
     * @param string $name
     * @param int $sort
     * @param array $result
     */
    protected function parseSortByName($name, $sort, &$result)
    {
        if (!$this->canSort($name)) {
            return;
        }
        $direction = $this->sort2direction($sort);
        $directionRule = $this->rules[$name][$direction];
        foreach ($directionRule as $attribute => $ruleSort) {
            if (isset($result[$attribute])) {
                continue;
            }
            $result[$attribute] = ['attribute' => $attribute, 'sort' => $ruleSort, 'direction' => $this->sort2direction($ruleSort)];
        }
    }

    /**
     * 判断是否可以排序
     * @param $attribute
     * @return bool
     */
    public function canSort($attribute): bool
    {
        return array_key_exists($attribute, $this->rules);
    }

    /**
     * 创建 url
     * @param string $attribute
     * @param string|null $direction 'asc'|'desc'
     * @return string
     */
    public function createUrl(string $attribute, ?string $direction = null): string
    {
        if ($this->config['enableMultiple']) {
            $sortArr = $this->getCurrentSort();
            unset($sortArr[$attribute]);
        } else {
            $sortArr = [];
        }
        $newSort = $direction === null
            ? $this->getAttributeSort($attribute, true, SORT_ASC)
            : $this->direction2sort($direction);
        $sortArr = array_merge([$attribute => $newSort], $sortArr);
        $params = $this->request->query->all();
        $params[$this->config['sortParam']] = $this->parseSort2String($sortArr);

        $route = $this->request->get('route');
        unset($params['route']);
        array_unshift($params, $route);
        return url()->to($params);
    }

    /**
     * 获取某个字段的当前排序
     * @param string $attribute
     * @param bool $revert 是否反向
     * @param null $default
     * @return int|null SORT_ASC|SORT_DESC
     */
    public function getAttributeSort(string $attribute, $revert = false, $default = null): ?int
    {
        $sortArr = $this->getCurrentSort();
        if (isset($sortArr[$attribute])) {
            $sort = $sortArr[$attribute];
        } else {
            if (count($sortArr) > 0) {
                $sort = $default;
            } else {
                $sort = $this->config['defaultOrder'][$attribute] ?? $default;
            }
        }

        return $sort === null ? null
            : ($revert ? ($sort === SORT_ASC ? SORT_DESC : SORT_ASC) : $sort);
    }

    /**
     * 获取某个字段的当前排序
     * @param $attribute
     * @param bool $revert
     * @param null $default
     * @return string|null asc|desc
     */
    public function getAttributeSortDirection(string $attribute, $revert = false, $default = null): ?string
    {
        $sort = $this->getAttributeSort($attribute, $revert, $default);
        return $this->sort2direction($sort);
    }

    /**
     * 支持以下配置形式：
     * [
     *      'created_time',
     *      'created_time2' => 'c.created_time',
     *      'created_time3' => [
     *          'asc' => ['c.created_time' => SORT_ASC, 'b.id' => SORT_ASC],
     *          'desc' => ['c.created_time' => SORT_DESC, 'b.id' => SORT_DESC],
     *      ],
     *      'count' => new Expression('(b.count-b.left_count)'),
     * ]
     * 返回结果：
     * [
     *      'created_time' => [
     *          'asc' => ['created_time' => SORT_ASC],
     *          'desc' => ['created_time' => SORT_DESC],
     *       ],
     *      'created_time2' => [
     *          'asc' => ['c.created_time' => SORT_ASC],
     *          'desc' => ['c.created_time' => SORT_DESC],
     *      ],
     *      'created_time3' => [
     *          'asc' => ['c.created_time' => SORT_ASC, 'b.id' => SORT_ASC],
     *          'desc' => ['c.created_time' => SORT_DESC, 'b.id' => SORT_DESC],
     *      ],
     *      'count' => [
     *          'asc' => ['___EXPRESSION_md532' => SORT_ASC],
     *          'desc' => ['___EXPRESSION_md532' => SORT_DESC],
     *      ],
     * ]
     *
     * @param array $rules
     * @return array
     */
    protected function parseRules(array $rules)
    {
        $result = [];
        foreach ($rules as $name => $attribute) {
            if (is_numeric($name) && is_string($attribute)) {
                $result[$attribute] = [
                    'asc' => [$attribute => SORT_ASC],
                    'desc' => [$attribute => SORT_DESC],
                ];
            } elseif (is_string($name)) {
                if (is_string($attribute)) {
                    $result[$name] = [
                        'asc' => [$attribute => SORT_ASC],
                        'desc' => [$attribute => SORT_DESC],
                    ];
                } elseif (is_array($attribute) && isset($attribute['asc']) && isset($attribute['desc'])) {
                    $result[$name] = $attribute;
                } elseif ($attribute instanceof Expression) {
                    $expressionStr = $this->coverExpression2String($attribute);
                    $result[$name] = [
                        'asc' => [$expressionStr => SORT_ASC],
                        'desc' => [$expressionStr => SORT_DESC],
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * @var array [$string => $expressionObj]
     */
    private $_expressionCached = [];

    /**
     * 转化 Expression 为一个字符串
     * @param Expression $expression
     * @return string
     * @see coverString2OriginExpression 还原
     */
    protected function coverExpression2String(Expression $expression): string
    {
        $name = md5((string)$expression->getValue());
        $name = static::EXPRESSION_PREFIX . $name;
        $this->_expressionCached[$name] = $expression;
        return $name;
    }

    /**
     * 转化为原始 Expression
     * @param string $key
     * @return Expression|null
     * @see coverExpression2String 转化
     */
    protected function coverString2OriginExpression(string $key): ?Expression
    {
        return $this->_expressionCached[$key] ?? null;
    }

    /**
     * 解析排序到数组
     * @param string $sortString -created_at,updated_at
     * @return array ['created_at' => SORT_DESC, 'updated_at' => SORT_ASC]
     */
    protected function parseSort2Array(string $sortString): array
    {
        $result = [];
        $parts = preg_split('/\s*,\s*/', trim($sortString), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $part) {
            if (strpos($part, '-') === 0) {
                $attribute = substr($part, 1);
                $direction = SORT_DESC;
            } else {
                $attribute = $part;
                $direction = SORT_ASC;
            }
            if (array_key_exists($attribute, $this->rules)) {
                $result[$attribute] = $direction;
            }
        }
        return $result;
    }

    /**
     * 解析排序为字符串
     * @param array $sortArray ['created_at' => SORT_DESC, 'updated_at' => SORT_ASC]
     * @return string|null -created_at,updated_at
     */
    protected function parseSort2String(array $sortArray): ?string
    {
        $parts = [];
        foreach ($sortArray as $field => $direction) {
            $parts[] = ($direction === SORT_DESC ? '-' : '') . $field;
        }
        return implode(',', $parts);
    }

    /**
     * @param $sort
     * @return string|null
     */
    private function sort2direction($sort)
    {
        return $sort === null ? null :
            ($sort === SORT_ASC ? 'asc' : 'desc');
    }

    /**
     * @param $direction
     * @return int|null
     */
    private function direction2sort($direction)
    {
        return $direction === null ? null :
            ($direction === 'asc' ? SORT_ASC : SORT_DESC);
    }
}
