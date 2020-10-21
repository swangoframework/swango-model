<?php
namespace Swango\Model\Operator;
use Swango\Model\Factory;
use Swango\Model\AbstractBaseGateway;
/**
 * @property \Swango\Db\Adapter $DB
 * @author fdream
 */
class Selector {
    public int $DB_type;
    public Factory $factory;
    public ?\Sql\Select $select;
    public function __construct(bool $use_slave_DB, Factory $factory, ?\Sql\Select $select = null) {
        $this->DB_type = $use_slave_DB ? \Gateway::SLAVE_DB : \Gateway::MASTER_DB;
        $this->factory = $factory;
        $this->select = $select;
    }
    public function __destruct() {
        unset($this->factory);
        $this->select = null;
    }
    protected function getSelect(): \Sql\Select {
        if (isset($this->select)) {
            return clone $this->select;
        }
        return new \Sql\Select($this->factory->getTableName());
    }
    protected function getDb(): \Swango\Db\Adapter {
        return \Gateway::getAdapter($this->DB_type);
    }
    /**
     * 在生成起析构时，会清理掉函数中的所有对象。所以不用担心未遍历完就报错导致的协程泄露
     *
     * @param \Traversable $resultset
     * @return \Generator
     */
    protected function yieldResult(\Traversable $resultset): \Generator {
        foreach ($resultset as $row) {
            $obj = $this->factory->createObject($row);
            if (method_exists($obj, '_localCacheTrait_Set')) {
                $obj->_localCacheTrait_Set();
            }
            yield $obj;
        }
    }
    public function exists($where): bool {
        $select = $this->getSelect();
        $select->where($where)->limit(1);
        $resultset = $this->getDb()->query($select);
        if (! is_array($resultset) || count($resultset) === 0) {
            return false;
        }
        $obj = $this->factory->createObject(current($resultset));
        if (method_exists($obj, '_localCacheTrait_Set')) {
            $obj->_localCacheTrait_Set();
        }
        return true;
    }
    public function getSum($where, string $column): int {
        $select = $this->getSelect();
        $select->columns([
            's' => new \Sql\Expression("SUM(`$column`)")
        ])->where($where);
        return $this->getDb()->selectWith($select)->current()->s ?? 0;
    }
    public function getCount($where, string $column = '*'): int {
        if ($column == '*') {
            $expression = 'COUNT(*)';
        } else {
            $expression = "COUNT(`$column`)";
        }
        $select = $this->getSelect();
        $select->columns([
            'c' => new \Sql\Expression($expression)
        ])->where($where);
        return $this->getDb()->selectWith($select)->current()->c ?? 0;
    }
    public function selectOne($where, $order = null, ?int $offset = null): AbstractBaseGateway {
        $select = $this->getSelect();
        $select->where($where);
        if (isset($order)) {
            $select->order($order);
        }
        if (isset($offset)) {
            $select->offset($offset);
        }
        $select->limit(1);
        $result = $this->getDb()->selectWith($select)->current();
        if (! $result) {
            $name = $this->factory->getNotFoundExceptionName();
            throw new $name();
        }
        $obj = $this->factory->createObject($result);
        if (method_exists($obj, '_localCacheTrait_Set')) {
            $obj->_localCacheTrait_Set();
        }
        return $obj;
    }
    public function selectMulti($where, $order = null, ?int $limit = null, ?int $offset = null): \Generator {
        $select = $this->getSelect();
        $select->where($where);
        if (isset($order)) {
            $select->order($order);
        }
        if (isset($offset)) {
            $select->offset($offset);
        }
        if (isset($limit)) {
            $select->limit($limit);
        }
        return $this->yieldResult($this->getDb()->selectWith($select));
    }
    /**
     *
     * @param string|\Sql\Select $sql
     * @param null|number|string ...$parameter
     * @return \Swango\Model\AbstractBaseGateway
     */
    public function selectOneWithSql($sql, ...$parameter): AbstractBaseGateway {
        $result = $this->getDb()->selectWith($sql, ...$parameter)->current();
        if (! $result) {
            $name = $this->factory->getNotFoundExceptionName();
            throw new $name();
        }
        $obj = $this->factory->createObject($result);
        if (method_exists($obj, '_localCacheTrait_Set')) {
            $obj->_localCacheTrait_Set();
        }
        return $obj;
    }
    /**
     *
     * @param string|\Sql\Select $sql
     * @param null|number|string ...$parameter
     * @return \Swango\Model\AbstractBaseGateway[]
     */
    public function selectMultiWithSql($sql, ...$parameter): \Generator {
        $resultset = $this->getDb()->selectWith($sql, ...$parameter);
        return $this->yieldResult($resultset);
    }
    public function __get(string $key) {
        if ($key === 'DB') {
            return $this->getDb();
        }
        return null;
    }
}