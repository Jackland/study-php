<?php

namespace Framework\DataProvider;

use Illuminate\Database\Query\Builder as QueryBuilder;

class SqlDataProvider extends BaseDataProvider implements DataProviderCursorGetInterface
{
    private $sql;
    private $params;
    private $db;

    public function __construct(string $sql, array $params = [])
    {
        $this->sql = $sql;
        $this->params = $params;

        $this->db = db();
    }

    protected function prepareTotalCount(): int
    {
        return $this->db->newQuery()->fromRaw($this->sql, $this->params)->count();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getList()
    {
        return $this->getListInner()->get();
    }

    /**
     * @inheritDoc
     */
    public function getListWithCursor()
    {
        return $this->getListInner()->cursor();
    }

    /**
     * @return \Illuminate\Database\Capsule\Manager|QueryBuilder
     * @throws \Framework\Exception\InvalidConfigException
     */
    protected function getListInner()
    {
        $sql = $this->sql;
        $params = $this->params;

        if ($this->isSortEnable()) {
            $sort = $this->getSort();
            $sortAttributes = $sort->getCurrentSortWithAttribute();
            if ($sortAttributes) {
                $orderBy = [];
                foreach ($sortAttributes as $item) {
                    $orderBy[] = $item['attribute'] . ' ' . $item['direction'];
                }

                $pattern = '/\s+order\s+by\s+([\w\s,\.]+)$/i';
                if (preg_match($pattern, $this->sql, $matches)) {
                    array_unshift($orderBy, $matches[1]);
                    $sql = preg_replace($pattern, '', $sql);
                }

                $sql .= ' ORDER BY ' . implode(', ', $orderBy);
            }
        }

        if ($this->isPaginatorEnable()) {
            $paginator = $this->getPaginator();
            $sql .= " LIMIT {$paginator->getLimit()} OFFSET {$paginator->getOffset()}";
        }

        return $this->db->selectRaw($sql, $params);
    }
}
